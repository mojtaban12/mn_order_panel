<?php
/**
 * MN Order Panel - Sync Manager
 * مدیر همگام‌سازی
 */

// جلوگیری از load مجدد
if (class_exists('MN_Sync_Manager')) {
    return;
}

class MN_Sync_Manager {
    
    private $db;
    
    public function __construct() {
        // بارگذاری کلاس دیتابیس
        if (!class_exists('MN_Database')) {
            require_once __DIR__ . '/../config/database.php';
        }
        
        $this->db = MN_Database::get_instance();
    }
    
    /**
     * اضافه کردن به صف همگام‌سازی
     * 
     * @param string $entity_type نوع: 'order' یا 'customer'
     * @param int $entity_id شناسه
     * @param int $priority اولویت (1=بالا، 10=پایین)
     * @return int|bool شناسه صف یا false
     */
    public function add_to_queue($entity_type, $entity_id, $priority = 5) {
        try {
            // چک کردن که قبلاً در صف نباشه
            $existing = $this->db->get_row(
                "SELECT id FROM mn_sync_queue 
                 WHERE entity_type = ? AND entity_id = ? 
                 AND status IN ('pending', 'processing')",
                [$entity_type, $entity_id]
            );
            
            if ($existing) {
                return $existing->id; // قبلاً در صف هست
            }
            
            // اضافه به صف
            $this->db->query(
                "INSERT INTO mn_sync_queue 
                 (entity_type, entity_id, priority, status, created_at)
                 VALUES (?, ?, ?, 'pending', NOW())",
                [$entity_type, $entity_id, $priority]
            );
            
            return $this->db->get_var("SELECT LAST_INSERT_ID()");
            
        } catch (Exception $e) {
            error_log('Sync Manager - Add to Queue Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * پردازش صف همگام‌سازی (متد جدید برای sync چند سفارش)
     * 
     * @param int $batch_size تعداد آیتم‌ها برای پردازش
     * @return array نتایج
     */
    public function process_queue($batch_size = 10) {
        $results = [
            'completed' => 0,
            'failed' => 0,
            'errors' => []
        ];
        
        try {
            // دریافت آیتم‌های صف
            $queue_items = $this->get_pending_queue($batch_size);
            
            if (empty($queue_items)) {
                return $results;
            }
            
            foreach ($queue_items as $item) {
                $result = $this->process_queue_item($item);
                
                if ($result['success']) {
                    $results['completed']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = "#{$item->entity_id}: {$result['error']}";
                }
            }
            
            return $results;
            
        } catch (Exception $e) {
            error_log('Sync Manager - Process Queue Error: ' . $e->getMessage());
            return $results;
        }
    }
    
    /**
     * دریافت موارد صف که آماده همگام‌سازی هستند
     */
    public function get_pending_queue($limit = 50) {
        return $this->db->get_results("
            SELECT * FROM mn_sync_queue 
            WHERE status = 'pending' 
            AND attempts < max_attempts
            ORDER BY priority ASC, created_at ASC
            LIMIT ?
        ", [$limit]);
    }
    
    /**
     * پردازش یک آیتم از صف
     */
    public function process_queue_item($queue_item) {
        $start_time = microtime(true);
        
        try {
            // انتخاب Sync Handler مناسب
            if ($queue_item->entity_type === 'customer') {
                // بارگذاری کلاس در صورت نیاز
                if (!class_exists('MN_Customer_Sync')) {
                    require_once __DIR__ . '/class-customer-sync.php';
                }
                $syncer = new MN_Customer_Sync();
                $wp_entity_id = $syncer->sync($queue_item->entity_id);
                
            } elseif ($queue_item->entity_type === 'order') {
                // بارگذاری کلاس در صورت نیاز
                if (!class_exists('MN_Order_Sync')) {
                    require_once __DIR__ . '/class-order-sync.php';
                }
                $syncer = new MN_Order_Sync();
                $wp_entity_id = $syncer->sync($queue_item->entity_id);
                
            } else {
                throw new Exception("Unknown entity type: {$queue_item->entity_type}");
            }
            
            $execution_time = microtime(true) - $start_time;
            
            // بروزرسانی وضعیت به completed
            $this->db->query(
                "UPDATE mn_sync_queue 
                 SET status = 'completed', processed_at = NOW()
                 WHERE id = ?",
                [$queue_item->id]
            );
            
            // ثبت لاگ موفق
            $this->log_sync(
                $queue_item->id,
                $queue_item->entity_type,
                $queue_item->entity_id,
                'sync_completed',
                $wp_entity_id,
                "Successfully synced",
                $execution_time
            );
            
            return [
                'success' => true,
                'wp_entity_id' => $wp_entity_id,
                'execution_time' => $execution_time
            ];
            
        } catch (Exception $e) {
            $execution_time = microtime(true) - $start_time;
            
            // افزایش تعداد تلاش
            $new_attempts = $queue_item->attempts + 1;
            $status = ($new_attempts >= $queue_item->max_attempts) ? 'failed' : 'pending';
            
            $this->db->query(
                "UPDATE mn_sync_queue 
                 SET status = ?, attempts = ?, error_message = ?
                 WHERE id = ?",
                [$status, $new_attempts, $e->getMessage(), $queue_item->id]
            );
            
            // بازگشت وضعیت order به pending/failed
            if ($queue_item->entity_type === 'order') {
                $this->db->query(
                    "UPDATE mn_orders 
                     SET status = ?, last_sync_error = ?, sync_attempts = ?
                     WHERE id = ?",
                    [
                        $status === 'failed' ? 'failed' : 'pending',
                        $e->getMessage(),
                        $new_attempts,
                        $queue_item->entity_id
                    ]
                );
            }
            
            // ثبت لاگ خطا
            $this->log_sync(
                $queue_item->id,
                $queue_item->entity_type,
                $queue_item->entity_id,
                'sync_failed',
                null,
                $e->getMessage(),
                $execution_time
            );
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'execution_time' => $execution_time
            ];
        }
    }
    
    /**
     * ثبت لاگ
     */
    private function log_sync($queue_id, $entity_type, $entity_id, $action, $wp_entity_id, $message, $execution_time) {
        $this->db->query(
            "INSERT INTO mn_sync_log 
             (queue_id, entity_type, entity_id, action, wp_entity_id, message, execution_time)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $queue_id,
                $entity_type,
                $entity_id,
                $action,
                $wp_entity_id,
                $message,
                round($execution_time, 4)
            ]
        );
    }
    
    /**
     * دریافت آمار sync
     */
    public function get_stats() {
        return $this->db->get_row("
            SELECT 
                COUNT(*) as total_queue,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                COUNT(CASE WHEN status = 'processing' THEN 1 END) as processing,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed
            FROM mn_sync_queue
        ");
    }
    
    /**
     * پاک کردن آیتم‌های completed قدیمی
     */
    public function cleanup_old_completed($days = 7) {
        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $this->db->query("
            DELETE FROM mn_sync_queue 
            WHERE status = 'completed' 
            AND processed_at < ?
        ", [$date]);
        
        return $this->db->get_var("SELECT ROW_COUNT()");
    }
}
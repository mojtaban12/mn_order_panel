<?php
/**
 * MN Order Panel - Order Model
 * مدیریت سفارشات
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/class-customer.php';

class MN_Order {
    
    private $db;
    
    public function __construct() {
        $this->db = MN_Database::get_instance();
    }
    
    /**
     * ایجاد سفارش جدید
     * 
     * @param array $order_data اطلاعات سفارش
     * @param array $items آیتم‌های سفارش
     * @param int $panel_user_id ID کاربر پنل که سفارش را ثبت کرده
     * @return int|false ID سفارش یا false
     */
    public function create($order_data, $items, $panel_user_id) {
        
        try {
            // شروع Transaction
            $this->db->begin_transaction();
            
            // 1. محاسبه مجموع
            $total_amount = 0;
            foreach ($items as $item) {
                $total_amount += floatval($item['quantity']) * floatval($item['price']);
            }
            
            // 2. درج سفارش
            $order_insert_data = [
                'customer_id' => intval($order_data['customer_id']),
                'panel_user_id' => intval($panel_user_id),
                'total_amount' => $total_amount,
                'order_notes' => sanitize_textarea_field($order_data['order_notes'] ?? ''),
                'status' => 'pending'
            ];
            
            $order_id = $this->db->insert('mn_orders', $order_insert_data);
            
            if (!$order_id) {
                throw new Exception('Failed to create order');
            }
            
            // 3. درج آیتم‌های سفارش
            foreach ($items as $item) {
                $item_data = [
                    'order_id' => $order_id,
                    'product_id' => intval($item['product_id']),
                    'product_name' => sanitize_text_field($item['product_name']),
                    'product_sku' => sanitize_text_field($item['product_sku'] ?? ''),
                    'quantity' => intval($item['quantity']),
                    'price' => floatval($item['price']),
                    'total' => floatval($item['quantity']) * floatval($item['price'])
                ];
                
                $item_id = $this->db->insert('mn_order_items', $item_data);
                
                if (!$item_id) {
                    throw new Exception('Failed to add order item');
                }
            }
            
            // 4. افزودن به صف همگام‌سازی
            $this->add_to_sync_queue($order_id);
            
            // Commit
            $this->db->commit();
            
            return $order_id;
            
        } catch (Exception $e) {
            // Rollback
            $this->db->rollback();
            error_log('Order creation failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * دریافت سفارش با ID
     * 
     * @param int $order_id
     * @return object|null
     */
    public function get($order_id) {
        return $this->db->get_row(
            "SELECT * FROM mn_orders WHERE id = ?",
            [$order_id]
        );
    }
    
    /**
     * دریافت سفارش همراه با اطلاعات مشتری
     * 
     * @param int $order_id
     * @return object|null
     */
    public function get_with_customer($order_id) {
        return $this->db->get_row("
            SELECT 
                o.*,
                CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                c.phone as customer_phone,
                c.email as customer_email,
                c.address as customer_address,
                u.full_name as created_by
            FROM mn_orders o
            LEFT JOIN mn_customers c ON o.customer_id = c.id
            LEFT JOIN mn_panel_users u ON o.panel_user_id = u.id
            WHERE o.id = ?
        ", [$order_id]);
    }
    
    /**
     * دریافت آیتم‌های یک سفارش
     * 
     * @param int $order_id
     * @return array
     */
    public function get_items($order_id) {
        return $this->db->get_results(
            "SELECT * FROM mn_order_items WHERE order_id = ? ORDER BY id",
            [$order_id]
        );
    }
    
    /**
     * دریافت لیست سفارشات
     * 
     * @param array $filters فیلترها ['status', 'customer_id', 'date_from', 'date_to']
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function get_list($filters = [], $limit = 20, $offset = 0) {
        $where_conditions = ['1=1'];
        $params = [];
        
        // فیلتر وضعیت
        if (!empty($filters['status'])) {
            $where_conditions[] = "o.status = ?";
            $params[] = $filters['status'];
        }
        
        // فیلتر مشتری
        if (!empty($filters['customer_id'])) {
            $where_conditions[] = "o.customer_id = ?";
            $params[] = intval($filters['customer_id']);
        }
        
        // فیلتر تاریخ
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "DATE(o.created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = "DATE(o.created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        // فیلتر جستجو
        if (!empty($filters['search'])) {
            $search_like = '%' . $this->db->esc_like($filters['search']) . '%';
            $where_conditions[] = "(CONCAT(c.first_name, ' ', c.last_name) LIKE ? OR c.phone LIKE ?)";
            $params[] = $search_like;
            $params[] = $search_like;
        }
        
        $where_sql = implode(' AND ', $where_conditions);
        
        $params[] = $limit;
        $params[] = $offset;
        
        return $this->db->get_results("
            SELECT 
                o.id,
                o.customer_id,
                o.total_amount,
                o.status,
                o.wc_order_id,
                o.created_at,
                CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                c.phone as customer_phone,
                u.full_name as created_by,
                (SELECT COUNT(*) FROM mn_order_items WHERE order_id = o.id) as items_count
            FROM mn_orders o
            LEFT JOIN mn_customers c ON o.customer_id = c.id
            LEFT JOIN mn_panel_users u ON o.panel_user_id = u.id
            WHERE {$where_sql}
            ORDER BY o.created_at DESC
            LIMIT ? OFFSET ?
        ", $params);
    }
    
    /**
     * شمارش سفارشات
     * 
     * @param array $filters
     * @return int
     */
    public function count($filters = []) {
        $where_conditions = ['1=1'];
        $params = [];
        
        if (!empty($filters['status'])) {
            $where_conditions[] = "o.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['customer_id'])) {
            $where_conditions[] = "o.customer_id = ?";
            $params[] = intval($filters['customer_id']);
        }
        
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "DATE(o.created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = "DATE(o.created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $where_sql = implode(' AND ', $where_conditions);
        
        return (int) $this->db->get_var("
            SELECT COUNT(*) 
            FROM mn_orders o
            LEFT JOIN mn_customers c ON o.customer_id = c.id
            WHERE {$where_sql}
        ", $params);
    }
    
    /**
     * بروزرسانی وضعیت سفارش
     * 
     * @param int $order_id
     * @param string $status
     * @return bool
     */
    public function update_status($order_id, $status) {
        $valid_statuses = ['pending', 'syncing', 'synced', 'failed'];
        
        if (!in_array($status, $valid_statuses)) {
            return false;
        }
        
        return $this->db->update(
            'mn_orders',
            ['status' => $status],
            ['id' => $order_id]
        );
    }
    
    /**
     * علامت‌گذاری سفارش به عنوان sync شده
     * 
     * @param int $order_id
     * @param int $wc_order_id
     * @return bool
     */
    public function mark_as_synced($order_id, $wc_order_id) {
        return $this->db->update(
            'mn_orders',
            [
                'status' => 'synced',
                'wc_order_id' => $wc_order_id,
                'synced_at' => date('Y-m-d H:i:s')
            ],
            ['id' => $order_id]
        );
    }
    
    /**
     * ثبت خطای sync
     * 
     * @param int $order_id
     * @param string $error_message
     * @return bool
     */
    public function mark_sync_failed($order_id, $error_message) {
        return $this->db->update(
            'mn_orders',
            [
                'status' => 'failed',
                'last_sync_error' => $error_message,
                'sync_attempts' => new \stdClass() // برای increment
            ],
            ['id' => $order_id]
        );
    }
    
    /**
     * افزودن سفارش به صف همگام‌سازی
     * 
     * @param int $order_id
     * @param int $priority
     * @return int|false
     */
    public function add_to_sync_queue($order_id, $priority = 5) {
        // چک کردن اینکه قبلاً در صف نباشد
        $exists = $this->db->get_var(
            "SELECT COUNT(*) FROM mn_sync_queue 
             WHERE entity_type = 'order' AND entity_id = ? AND status IN ('pending', 'processing')",
            [$order_id]
        );
        
        if ($exists > 0) {
            return false; // قبلاً در صف است
        }
        
        return $this->db->insert('mn_sync_queue', [
            'entity_type' => 'order',
            'entity_id' => $order_id,
            'priority' => $priority,
            'status' => 'pending'
        ]);
    }
    
    /**
     * دریافت آمار سفارشات
     * 
     * @return object
     */
    public function get_statistics() {
        return $this->db->get_row("
            SELECT 
                COUNT(*) as total_orders,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
                COUNT(CASE WHEN status = 'syncing' THEN 1 END) as syncing_orders,
                COUNT(CASE WHEN status = 'synced' THEN 1 END) as synced_orders,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_orders,
                COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_orders,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN total_amount ELSE 0 END) as today_revenue,
                AVG(total_amount) as average_order_value,
                SUM(total_amount) as total_revenue
            FROM mn_orders
        ");
    }
    
    /**
     * دریافت سفارشات اخیر
     * 
     * @param int $limit
     * @return array
     */
    public function get_recent($limit = 10) {
        return $this->db->get_results("
            SELECT 
                o.id,
                o.total_amount,
                o.status,
                o.created_at,
                CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                u.full_name as created_by
            FROM mn_orders o
            LEFT JOIN mn_customers c ON o.customer_id = c.id
            LEFT JOIN mn_panel_users u ON o.panel_user_id = u.id
            ORDER BY o.created_at DESC
            LIMIT ?
        ", [$limit]);
    }
    
    /**
     * حذف سفارش (فقط اگر هنوز sync نشده باشد)
     * 
     * @param int $order_id
     * @return bool|array
     */
    public function delete($order_id) {
        $order = $this->get($order_id);
        
        if (!$order) {
            return ['success' => false, 'message' => 'سفارش یافت نشد'];
        }
        
        if ($order->status === 'synced') {
            return [
                'success' => false,
                'message' => 'سفارش sync شده قابل حذف نیست'
            ];
        }
        
        // حذف از صف sync
        $this->db->delete('mn_sync_queue', [
            'entity_type' => 'order',
            'entity_id' => $order_id
        ]);
        
        // حذف آیتم‌ها (به صورت خودکار با CASCADE حذف می‌شوند)
        // حذف سفارش
        $result = $this->db->delete('mn_orders', ['id' => $order_id]);
        
        return $result ? ['success' => true] : ['success' => false, 'message' => 'خطا در حذف'];
    }
    
}

// توابع کمکی
function mn_get_order($order_id) {
    $order = new MN_Order();
    return $order->get($order_id);
}

function mn_create_order($order_data, $items, $panel_user_id) {
    $order = new MN_Order();
    return $order->create($order_data, $items, $panel_user_id);
}
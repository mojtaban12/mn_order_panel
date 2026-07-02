<?php
/**
 * MN Order Panel - Customer Model
 * مدیریت مشتریان
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

class MN_Customer {
    
    private $db;
    
    public function __construct() {
        $this->db = MN_Database::get_instance();
    }
    
    /**
     * ایجاد یا بروزرسانی مشتری
     * اگر شماره تلفن تکراری باشد، مشتری موجود را بروزرسانی می‌کند
     * 
     * @param array $data اطلاعات مشتری
     * @return int|false ID مشتری
     */
    public function create_or_update($data) {
        // اعتبارسنجی
        if (empty($data['phone'])) {
            error_log('Customer phone is required');
            return false;
        }
        
        // آماده‌سازی داده‌ها
        $customer_data = [
            'first_name' => sanitize_text_field($data['first_name'] ?? ''),
            'last_name' => sanitize_text_field($data['last_name'] ?? ''),
            'phone' => sanitize_text_field($data['phone']),
            'email' => !empty($data['email']) ? sanitize_email($data['email']) : null,
            'address' => sanitize_textarea_field($data['address'] ?? ''),
            'city' => sanitize_text_field($data['city'] ?? ''),
            'state' => sanitize_text_field($data['state'] ?? ''),
            'postcode' => sanitize_text_field($data['postcode'] ?? '')
        ];
        
        // چک کردن مشتری تکراری با شماره تلفن
        $existing = $this->get_by_phone($customer_data['phone']);
        
        if ($existing) {
            // بروزرسانی مشتری موجود
            $this->db->update('mn_customers', $customer_data, ['id' => $existing->id]);
            return $existing->id;
        } else {
            // ایجاد مشتری جدید
            return $this->db->insert('mn_customers', $customer_data);
        }
    }
    
    /**
     * دریافت مشتری با ID
     * 
     * @param int $customer_id
     * @return object|null
     */
    public function get($customer_id) {
        return $this->db->get_row(
            "SELECT * FROM mn_customers WHERE id = ?",
            [$customer_id]
        );
    }
    
    /**
     * دریافت مشتری با شماره تلفن
     * 
     * @param string $phone
     * @return object|null
     */
    public function get_by_phone($phone) {
        return $this->db->get_row(
            "SELECT * FROM mn_customers WHERE phone = ?",
            [sanitize_text_field($phone)]
        );
    }
    
    /**
     * دریافت مشتری با ایمیل
     * 
     * @param string $email
     * @return object|null
     */
    public function get_by_email($email) {
        return $this->db->get_row(
            "SELECT * FROM mn_customers WHERE email = ?",
            [sanitize_email($email)]
        );
    }
    
    /**
     * جستجوی مشتریان
     * 
     * @param string $search_term عبارت جستجو
     * @param int $limit تعداد نتایج
     * @param int $offset شروع از
     * @return array
     */
    public function search($search_term, $limit = 20, $offset = 0) {
        $term_like = '%' . $this->db->esc_like($search_term) . '%';
        
        return $this->db->get_results("
            SELECT 
                id,
                CONCAT(first_name, ' ', last_name) as full_name,
                phone,
                email,
                city,
                wp_user_id,
                synced_at,
                created_at
            FROM mn_customers
            WHERE 
                first_name LIKE ? 
                OR last_name LIKE ? 
                OR phone LIKE ?
                OR email LIKE ?
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ", [
            $term_like,
            $term_like,
            $term_like,
            $term_like,
            $limit,
            $offset
        ]);
    }
    
    /**
     * دریافت آخرین مشتریان
     * 
     * @param int $limit
     * @return array
     */
    public function get_recent($limit = 10) {
        return $this->db->get_results("
            SELECT 
                id,
                CONCAT(first_name, ' ', last_name) as full_name,
                phone,
                email,
                city,
                created_at
            FROM mn_customers
            ORDER BY created_at DESC
            LIMIT ?
        ", [$limit]);
    }
    
    /**
     * دریافت تعداد سفارشات مشتری
     * 
     * @param int $customer_id
     * @return int
     */
    public function get_orders_count($customer_id) {
        return (int) $this->db->get_var(
            "SELECT COUNT(*) FROM mn_orders WHERE customer_id = ?",
            [$customer_id]
        );
    }
    
    /**
     * دریافت مجموع خریدهای مشتری
     * 
     * @param int $customer_id
     * @return float
     */
    public function get_total_spent($customer_id) {
        return (float) $this->db->get_var(
            "SELECT SUM(total_amount) FROM mn_orders WHERE customer_id = ?",
            [$customer_id]
        );
    }
    
    /**
     * دریافت آخرین سفارش مشتری
     * 
     * @param int $customer_id
     * @return object|null
     */
    public function get_last_order($customer_id) {
        return $this->db->get_row("
            SELECT * FROM mn_orders 
            WHERE customer_id = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ", [$customer_id]);
    }
    
    /**
     * بروزرسانی اطلاعات sync
     * 
     * @param int $customer_id
     * @param int $wp_user_id
     * @return bool
     */
    public function mark_as_synced($customer_id, $wp_user_id) {
        return $this->db->update(
            'mn_customers',
            [
                'wp_user_id' => $wp_user_id,
                'synced_at' => date('Y-m-d H:i:s')
            ],
            ['id' => $customer_id]
        );
    }
    
    /**
     * دریافت مشتریان که هنوز sync نشده‌اند
     * 
     * @param int $limit
     * @return array
     */
    public function get_unsynced($limit = 50) {
        return $this->db->get_results("
            SELECT * FROM mn_customers 
            WHERE wp_user_id IS NULL OR synced_at IS NULL
            ORDER BY created_at ASC
            LIMIT ?
        ", [$limit]);
    }
    
    /**
     * حذف مشتری (فقط اگر سفارشی نداشته باشد)
     * 
     * @param int $customer_id
     * @return bool|array false یا ['success' => false, 'message' => '...']
     */
    public function delete($customer_id) {
        // بررسی وجود سفارش
        $orders_count = $this->get_orders_count($customer_id);
        
        if ($orders_count > 0) {
            return [
                'success' => false,
                'message' => 'این مشتری دارای ' . $orders_count . ' سفارش است و قابل حذف نیست'
            ];
        }
        
        $result = $this->db->delete('mn_customers', ['id' => $customer_id]);
        
        return $result ? ['success' => true] : ['success' => false, 'message' => 'خطا در حذف'];
    }
    
    /**
     * دریافت آمار مشتریان
     * 
     * @return object
     */
    public function get_statistics() {
        return $this->db->get_row("
            SELECT 
                COUNT(*) as total_customers,
                COUNT(CASE WHEN wp_user_id IS NOT NULL THEN 1 END) as synced_customers,
                COUNT(CASE WHEN wp_user_id IS NULL THEN 1 END) as unsynced_customers,
                COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_customers
            FROM mn_customers
        ");
    }
    
}

// توابع کمکی برای استفاده آسان
function mn_get_customer($customer_id) {
    $customer = new MN_Customer();
    return $customer->get($customer_id);
}

function mn_create_customer($data) {
    $customer = new MN_Customer();
    return $customer->create_or_update($data);
}
<?php
/**
 * MN Order Panel - Order Sync
 * همگام‌سازی سفارش با ووکامرس
 */

// جلوگیری از load مجدد
if (class_exists('MN_Order_Sync')) {
    return;
}

require_once __DIR__ . '/../config/database.php';

class MN_Order_Sync {
    
    private $db;
    
    public function __construct() {
        $this->db = MN_Database::get_instance();
    }
    
    /**
     * همگام‌سازی سفارش با ووکامرس (متد wrapper برای API)
     * 
     * @param int $order_id
     * @return array ['success' => bool, 'wc_order_id' => int, 'message' => string, 'execution_time' => float]
     */
    public function sync_single_order($order_id) {
        $start_time = microtime(true);
        
        try {
            // فراخوانی متد اصلی sync
            $wc_order_id = $this->sync($order_id);
            $execution_time = microtime(true) - $start_time;
            
            return [
                'success' => true,
                'wc_order_id' => $wc_order_id,
                'message' => 'سفارش با موفقیت همگام‌سازی شد',
                'execution_time' => round($execution_time, 2)
            ];
            
        } catch (Exception $e) {
            $execution_time = microtime(true) - $start_time;
            
            return [
                'success' => false,
                'wc_order_id' => null,
                'message' => $e->getMessage(),
                'execution_time' => round($execution_time, 2)
            ];
        }
    }
    
    /**
     * همگام‌سازی سفارش با ووکامرس
     * 
     * @param int $order_id
     * @return int WooCommerce Order ID
     * @throws Exception
     */
    public function sync($order_id) {
        // دریافت سفارش
        $order = $this->db->get_row(
            "SELECT * FROM mn_orders WHERE id = ?",
            [$order_id]
        );
        
        if (!$order) {
            throw new Exception("Order #{$order_id} not found");
        }
        
        // جلوگیری از sync مجدد
        if ($order->wc_order_id) {
            throw new Exception("Order already synced. WC Order ID: {$order->wc_order_id}");
        }
        
        // دریافت مشتری
        $customer = $this->db->get_row(
            "SELECT * FROM mn_customers WHERE id = ?",
            [$order->customer_id]
        );
        
        if (!$customer) {
            throw new Exception("Customer #{$order->customer_id} not found");
        }
        
        // بارگذاری WordPress
        $this->load_wordpress();
        
        // همگام‌سازی مشتری (اگر قبلاً sync نشده)
        if (!$customer->wp_user_id) {
            // بارگذاری کلاس در صورت نیاز
            if (!class_exists('MN_Customer_Sync')) {
                require_once __DIR__ . '/class-customer-sync.php';
            }
            $customer_sync = new MN_Customer_Sync();
            $customer->wp_user_id = $customer_sync->sync($customer->id);
        }
        
        // ایجاد سفارش در ووکامرس
        $wc_order = wc_create_order([
            'customer_id' => $customer->wp_user_id,
            'status' => 'processing',
            'created_via' => 'mn_panel'
        ]);
        
        if (is_wp_error($wc_order)) {
            throw new Exception("Failed to create WC order: " . $wc_order->get_error_message());
        }
        
        // افزودن آیتم‌ها
        $this->add_order_items($wc_order, $order_id);
        
        // تنظیم آدرس مشتری
        $this->set_order_addresses($wc_order, $customer);
        
        // اضافه کردن یادداشت سفارش
        if (!empty($order->order_notes)) {
            $wc_order->set_customer_note($order->order_notes);
        }
        
        // محاسبه مجدد مجموع
        $wc_order->calculate_totals();
        
        // ذخیره متادیتا
        $wc_order->update_meta_data('_panel_user_id', $order->panel_user_id);
        $wc_order->update_meta_data('_mn_order_id', $order_id);
        $wc_order->update_meta_data('_created_via_panel', 'yes');
        
        $wc_order->save();
        
        $wc_order_id = $wc_order->get_id();
        
        // فقط بعد از موفقیت کامل، وضعیت رو تغییر بده
        $this->db->query(
            "UPDATE mn_orders 
             SET status = 'synced',
                 wc_order_id = ?,
                 synced_at = NOW(),
                 sync_attempts = sync_attempts + 1,
                 last_sync_error = NULL
             WHERE id = ?",
            [$wc_order_id, $order_id]
        );
        
        return $wc_order_id;
    }
    
    /**
     * بارگذاری WordPress
     */
    private function load_wordpress() {
        // اگر WordPress بارگذاری نشده، بارگذاری کن
        if (!function_exists('wc_create_order')) {
            // بارگذاری Settings برای دریافت مسیر wp-load
            if (!class_exists('MN_Settings')) {
                require_once __DIR__ . '/../config/settings.php';
            }
            
            $wp_load_path = MN_Settings::get('wp_load_path');
            
            if (!$wp_load_path || !file_exists($wp_load_path)) {
                throw new Exception('WordPress load path not configured or not found');
            }
            
            require_once $wp_load_path;
            
            if (!function_exists('wc_create_order')) {
                throw new Exception('WooCommerce is not active');
            }
        }
    }
    
    /**
     * افزودن آیتم‌های سفارش
     */
    private function add_order_items($wc_order, $order_id) {
        $items = $this->db->get_results(
            "SELECT * FROM mn_order_items WHERE order_id = ?",
            [$order_id]
        );
        
        foreach ($items as $item) {
            $product = wc_get_product($item->product_id);
            
            if (!$product) {
                // اگر محصول حذف شده، از placeholder استفاده کن
                error_log("Product #{$item->product_id} not found during sync of order #{$order_id}");
                
                // ایجاد آیتم دستی
                $item_id = wc_add_order_item($wc_order->get_id(), [
                    'order_item_name' => $item->product_name,
                    'order_item_type' => 'line_item'
                ]);
                
                if ($item_id) {
                    wc_add_order_item_meta($item_id, '_qty', $item->quantity);
                    wc_add_order_item_meta($item_id, '_line_subtotal', $item->total);
                    wc_add_order_item_meta($item_id, '_line_total', $item->total);
                    wc_add_order_item_meta($item_id, '_product_deleted', 'yes');
                }
                
                continue;
            }
            
            // افزودن محصول معتبر
            $wc_order->add_product($product, $item->quantity, [
                'subtotal' => $item->total,
                'total' => $item->total
            ]);
        }
    }
    
    /**
     * تنظیم آدرس‌های سفارش
     */
    private function set_order_addresses($wc_order, $customer) {
        // تبدیل نام استان/شهر به term_id
        $state_term = $this->get_state_city_term($customer->state, 0);
        $city_term = $state_term ? $this->get_state_city_term($customer->city, $state_term) : null;
        
        // آدرس billing
        $wc_order->set_billing_first_name($customer->first_name);
        $wc_order->set_billing_last_name($customer->last_name);
        $wc_order->set_billing_phone($customer->phone);
        $wc_order->set_billing_email($customer->email ?: '');
        $wc_order->set_billing_address_1($customer->address ?: '');
        
        // استفاده از term_id برای state/city
        $wc_order->set_billing_city($city_term ?: $customer->city);
        $wc_order->set_billing_state($state_term ?: $customer->state);
        
        $wc_order->set_billing_postcode($customer->postcode ?: '');
        $wc_order->set_billing_country('IR');
        
        // آدرس shipping
        $wc_order->set_shipping_first_name($customer->first_name);
        $wc_order->set_shipping_last_name($customer->last_name);
        $wc_order->set_shipping_address_1($customer->address ?: '');
        $wc_order->set_shipping_city($city_term ?: $customer->city);
        $wc_order->set_shipping_state($state_term ?: $customer->state);
        $wc_order->set_shipping_postcode($customer->postcode ?: '');
        $wc_order->set_shipping_country('IR');
    }
    
    /**
     * پیدا کردن term_id از نام استان/شهر
     * 
     * @param string $name نام استان یا شهر
     * @param int $parent_id ID والد (0 برای استان)
     * @return int|null term_id یا null
     */
    private function get_state_city_term($name, $parent_id = 0) {
        if (empty($name)) {
            return null;
        }
        
        global $wpdb;
        
        // جستجوی term با نام مشابه
        $query = "
            SELECT t.term_id 
            FROM {$wpdb->terms} t
            INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            WHERE tt.taxonomy = 'state_city'
            AND t.name = %s
            AND tt.parent = %d
            LIMIT 1
        ";
        
        $term_id = $wpdb->get_var(
            $wpdb->prepare($query, $name, $parent_id)
        );
        
        return $term_id ? intval($term_id) : null;
    }
}
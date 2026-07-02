<?php
/**
 * MN Order Panel - Customer Sync
 * همگام‌سازی مشتری با وردپرس
 */

// جلوگیری از load مجدد
if (class_exists('MN_Customer_Sync')) {
    return;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

class MN_Customer_Sync {
    
    private $db;
    
    public function __construct() {
        $this->db = MN_Database::get_instance();
    }
    
    /**
     * همگام‌سازی مشتری با وردپرس
     * 
     * @param int $customer_id
     * @return int WP User ID
     * @throws Exception
     */
    public function sync($customer_id) {
        // دریافت اطلاعات مشتری
        $customer = $this->db->get_row(
            "SELECT * FROM mn_customers WHERE id = ?",
            [$customer_id]
        );
        
        if (!$customer) {
            throw new Exception("Customer #{$customer_id} not found");
        }
        
        // اگر قبلاً sync شده، همان را برگردان
        if ($customer->wp_user_id) {
            return $customer->wp_user_id;
        }
        
        // جستجوی کاربر موجود با ایمیل یا شماره تلفن
        $existing_user_id = $this->find_existing_user($customer);
        
        if ($existing_user_id) {
            // بروزرسانی اطلاعات کاربر موجود
            $this->update_user_meta($existing_user_id, $customer);
            $wp_user_id = $existing_user_id;
        } else {
            // ایجاد کاربر جدید
            $wp_user_id = $this->create_user($customer);
        }
        
        // بروزرسانی mn_customers
        $this->db->update('mn_customers', [
            'wp_user_id' => $wp_user_id,
            'synced_at' => date('Y-m-d H:i:s')
        ], ['id' => $customer_id]);
        
        return $wp_user_id;
    }
    
    /**
     * جستجوی کاربر موجود
     */
    private function find_existing_user($customer) {
        global $wpdb;
        
        // جستجو با ایمیل
        if (!empty($customer->email)) {
            $user_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->users} WHERE user_email = %s",
                $customer->email
            ));
            
            if ($user_id) {
                return $user_id;
            }
        }
        
        // جستجو با شماره تلفن
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} 
             WHERE meta_key = 'billing_phone' AND meta_value = %s",
            $customer->phone
        ));
        
        return $user_id ?: null;
    }
    
    /**
     * ایجاد کاربر جدید در وردپرس
     */
    private function create_user($customer) {
        // استفاده از توابع وردپرس
        $username = $this->generate_username($customer);
        $email = !empty($customer->email) ? $customer->email : $username . '@noemail.local';
        $password = wp_generate_password(12, false);
        
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            throw new Exception("Failed to create WP user: " . $user_id->get_error_message());
        }
        
        // تنظیم نقش
        $user = new WP_User($user_id);
        $user->set_role('customer');
        
        // افزودن متا
        $this->update_user_meta($user_id, $customer);
        
        return $user_id;
    }
    
    /**
     * بروزرسانی متای کاربر (فقط مقادیر جدید)
     */
    private function update_user_meta($user_id, $customer) {
        // اطلاعات شخصی - فقط اگر جدید باشه
        if (!empty($customer->first_name)) {
            $existing = get_user_meta($user_id, 'first_name', true);
            if (empty($existing)) {
                update_user_meta($user_id, 'first_name', $customer->first_name);
                update_user_meta($user_id, 'billing_first_name', $customer->first_name);
                update_user_meta($user_id, 'shipping_first_name', $customer->first_name);
            }
        }
        
        if (!empty($customer->last_name)) {
            $existing = get_user_meta($user_id, 'last_name', true);
            if (empty($existing)) {
                update_user_meta($user_id, 'last_name', $customer->last_name);
                update_user_meta($user_id, 'billing_last_name', $customer->last_name);
                update_user_meta($user_id, 'shipping_last_name', $customer->last_name);
            }
        }
        
        // شماره تلفن - همیشه update (چون کلیدیه)
        update_user_meta($user_id, 'billing_phone', $customer->phone);
        
        // ایمیل - فقط اگر جدید باشه
        if (!empty($customer->email)) {
            $existing = get_user_meta($user_id, 'billing_email', true);
            if (empty($existing)) {
                update_user_meta($user_id, 'billing_email', $customer->email);
            }
        }
        
        // آدرس - فقط اگر جدید باشه
        if (!empty($customer->address)) {
            $existing = get_user_meta($user_id, 'billing_address_1', true);
            if (empty($existing)) {
                update_user_meta($user_id, 'billing_address_1', $customer->address);
                update_user_meta($user_id, 'shipping_address_1', $customer->address);
            }
        }
        
        // شهر - فقط اگر جدید باشه
        if (!empty($customer->city)) {
            $existing = get_user_meta($user_id, 'billing_city', true);
            if (empty($existing)) {
                update_user_meta($user_id, 'billing_city', $customer->city);
                update_user_meta($user_id, 'shipping_city', $customer->city);
            }
        }
        
        // استان - فقط اگر جدید باشه
        if (!empty($customer->state)) {
            $existing = get_user_meta($user_id, 'billing_state', true);
            if (empty($existing)) {
                update_user_meta($user_id, 'billing_state', $customer->state);
                update_user_meta($user_id, 'shipping_state', $customer->state);
            }
        }
        
        // کد پستی - فقط اگر جدید باشه
        if (!empty($customer->postcode)) {
            $existing = get_user_meta($user_id, 'billing_postcode', true);
            if (empty($existing)) {
                update_user_meta($user_id, 'billing_postcode', $customer->postcode);
                update_user_meta($user_id, 'shipping_postcode', $customer->postcode);
            }
        }
        
        // کشور - همیشه ایران
        update_user_meta($user_id, 'billing_country', 'IR');
        update_user_meta($user_id, 'shipping_country', 'IR');
        
        // علامت‌گذاری که از پنل ایجاد/بروزرسانی شده
        update_user_meta($user_id, '_updated_via_mn_panel', date('Y-m-d H:i:s'));
        update_user_meta($user_id, '_mn_customer_id', $customer->id);
    }
    
    /**
     * تولید نام کاربری یکتا
     */
    private function generate_username($customer) {
        global $wpdb;
        
        // استفاده از شماره تلفن
        $base_username = 'customer_' . preg_replace('/[^0-9]/', '', $customer->phone);
        $username = $base_username;
        $counter = 1;
        
        // چک کردن یکتا بودن
        while (username_exists($username)) {
            $username = $base_username . '_' . $counter;
            $counter++;
        }
        
        return $username;
    }
}
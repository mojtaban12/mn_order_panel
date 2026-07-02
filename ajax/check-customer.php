<?php
/**
 * MN Order Panel - Check Customer by Phone
 * چک کردن مشتری با شماره موبایل
 */

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['phone'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'شماره موبایل الزامی است'
    ]);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

$phone = trim($_GET['phone']);

// پاک کردن کاراکترهای اضافه
$phone = preg_replace('/[^0-9]/', '', $phone);

if (strlen($phone) < 10) {
    echo json_encode([
        'success' => false,
        'message' => 'شماره موبایل معتبر نیست'
    ]);
    exit;
}

try {
    $db = MN_Database::get_instance();
    
    // 1. چک کردن در دیتابیس پنل
    $panel_customer = $db->get_row(
        "SELECT * FROM mn_customers WHERE phone = ? ORDER BY id DESC LIMIT 1",
        [$phone]
    );
    
    if ($panel_customer && $panel_customer->wp_user_id) {
        // اگر در پنل هست و wp_user_id داره، از وردپرس بگیر
        $wp_load_path = MN_Settings::get('wp_load_path');
        if (file_exists($wp_load_path)) {
            require_once $wp_load_path;
            
            $wp_user = get_user_by('id', $panel_customer->wp_user_id);
            
            if ($wp_user) {
                // دریافت نقش‌های کاربر - ⭐ اضافه شد
                $user_roles = !empty($wp_user->roles) ? array_values($wp_user->roles) : [];
                // دریافت اطلاعات کامل از usermeta
                $customer_data = [
                    'exists' => true,
                    'source' => 'wordpress',
                    'wp_user_id' => $wp_user->ID,
                    'user_roles' => $user_roles, // ⭐ اضافه شد
                    'first_name' => get_user_meta($wp_user->ID, 'billing_first_name', true) ?: $wp_user->first_name,
                    'last_name' => get_user_meta($wp_user->ID, 'billing_last_name', true) ?: $wp_user->last_name,
                    'email' => $wp_user->user_email,
                    'phone' => $phone,
                    'address' => get_user_meta($wp_user->ID, 'billing_address_1', true),
                    'city' => get_user_meta($wp_user->ID, 'billing_city', true),
                    'state' => get_user_meta($wp_user->ID, 'billing_state', true),
                    'postcode' => get_user_meta($wp_user->ID, 'billing_postcode', true),
                    'total_orders' => wc_get_customer_order_count($wp_user->ID),
                    'total_spent' => wc_get_customer_total_spent($wp_user->ID)
                ];
                
                echo json_encode([
                    'success' => true,
                    'customer' => $customer_data
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    }
    
    // 2. اگر در پنل نبود، مستقیم از وردپرس جستجو کن
    $wp_load_path = MN_Settings::get('wp_load_path');
    if (file_exists($wp_load_path)) {
        require_once $wp_load_path;
        
        global $wpdb;
        
        // جستجو با شماره تلفن در usermeta
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} 
             WHERE meta_key = 'billing_phone' AND meta_value = %s
             LIMIT 1",
            $phone
        ));
        
        if ($user_id) {
            $wp_user = get_user_by('id', $user_id);
            
            if ($wp_user) {
                // دریافت نقش‌های کاربر
                $user_roles = !empty($wp_user->roles) ? array_values($wp_user->roles) : [];
                
                $customer_data = [
                    'exists' => true,
                    'source' => 'wordpress',
                    'wp_user_id' => $wp_user->ID,
                    'user_roles' => $user_roles,
                    'first_name' => get_user_meta($wp_user->ID, 'billing_first_name', true) ?: $wp_user->first_name,
                    'last_name' => get_user_meta($wp_user->ID, 'billing_last_name', true) ?: $wp_user->last_name,
                    'email' => $wp_user->user_email,
                    'phone' => $phone,
                    'address' => get_user_meta($wp_user->ID, 'billing_address_1', true),
                    'city' => get_user_meta($wp_user->ID, 'billing_city', true),
                    'state' => get_user_meta($wp_user->ID, 'billing_state', true),
                    'postcode' => get_user_meta($wp_user->ID, 'billing_postcode', true),
                    'total_orders' => wc_get_customer_order_count($wp_user->ID),
                    'total_spent' => wc_get_customer_total_spent($wp_user->ID)
                ];
                
                echo json_encode([
                    'success' => true,
                    'customer' => $customer_data
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
        
        // جستجو با username (اگر شماره موبایل باشه)
        $wp_user = get_user_by('login', $phone);
        if (!$wp_user) {
            $wp_user = get_user_by('login', '0' . $phone); // با صفر
        }
        
        if ($wp_user) {
            // دریافت نقش‌های کاربر
            $user_roles = !empty($wp_user->roles) ? array_values($wp_user->roles) : [];
            
            $customer_data = [
                'exists' => true,
                'source' => 'wordpress',
                'wp_user_id' => $wp_user->ID,
                'user_roles' => $user_roles,
                'first_name' => get_user_meta($wp_user->ID, 'billing_first_name', true) ?: $wp_user->first_name,
                'last_name' => get_user_meta($wp_user->ID, 'billing_last_name', true) ?: $wp_user->last_name,
                'email' => $wp_user->user_email,
                'phone' => $phone,
                'address' => get_user_meta($wp_user->ID, 'billing_address_1', true),
                'city' => get_user_meta($wp_user->ID, 'billing_city', true),
                'state' => get_user_meta($wp_user->ID, 'billing_state', true),
                'postcode' => get_user_meta($wp_user->ID, 'billing_postcode', true),
                'total_orders' => wc_get_customer_order_count($wp_user->ID),
                'total_spent' => wc_get_customer_total_spent($wp_user->ID)
            ];
            
            echo json_encode([
                'success' => true,
                'customer' => $customer_data
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    
    // 3. اگر در هیچ کدوم نبود
    if ($panel_customer) {
        // از دیتابیس پنل برگردون
        echo json_encode([
            'success' => true,
            'customer' => [
                'exists' => true,
                'source' => 'panel',
                'user_roles' => [], // ⭐ اضافه شد برای consistency
                'first_name' => $panel_customer->first_name,
                'last_name' => $panel_customer->last_name,
                'email' => $panel_customer->email,
                'phone' => $phone,
                'address' => $panel_customer->address,
                'city' => $panel_customer->city,
                'state' => $panel_customer->state,
                'postcode' => $panel_customer->postcode
            ]
        ], JSON_UNESCAPED_UNICODE);
    } else {
        // کاربر جدید
        echo json_encode([
            'success' => true,
            'customer' => [
                'exists' => false,
                'user_roles' => [], // ⭐ اضافه شد برای consistency
                'phone' => $phone
            ]
        ], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطا در دریافت اطلاعات',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
<?php
/**
 * MN Order Panel - Settings Manager
 * مدیریت تنظیمات سیستم از دیتابیس
 */

require_once __DIR__ . '/database.php';

class MN_Settings {
    
    private static $cache = null;
    
    /**
     * دریافت تمام تنظیمات (با کش)
     */
    public static function get_all() {
        if (self::$cache !== null) {
            return self::$cache;
        }
        
        $db = MN_Database::get_instance();
        $results = $db->get_results("SELECT setting_key, setting_value FROM mn_settings");
        
        $settings = [];
        foreach ($results as $row) {
            $settings[$row->setting_key] = $row->setting_value;
        }
        
        // تنظیمات پیش‌فرض در صورت خالی بودن دیتابیس
        $defaults = self::get_defaults();
        $settings = array_merge($defaults, $settings);
        
        self::$cache = $settings;
        return $settings;
    }
    
    /**
     * دریافت یک تنظیم خاص
     */
    public static function get($key, $default = null) {
        $settings = self::get_all();
        return isset($settings[$key]) ? $settings[$key] : $default;
    }
    
    /**
     * ذخیره یک تنظیم
     */
    public static function set($key, $value) {
        $db = MN_Database::get_instance();
        
        // بررسی وجود
        $exists = $db->get_var(
            "SELECT COUNT(*) FROM mn_settings WHERE setting_key = ?",
            [$key]
        );
        
        if ($exists) {
            $result = $db->update(
                'mn_settings',
                ['setting_value' => $value],
                ['setting_key' => $key]
            );
        } else {
            $result = $db->insert('mn_settings', [
                'setting_key' => $key,
                'setting_value' => $value
            ]);
        }
        
        // پاک کردن کش
        self::$cache = null;
        
        return $result;
    }
    
    /**
     * ذخیره چند تنظیم یکجا
     */
    public static function set_multiple($settings_array) {
        foreach ($settings_array as $key => $value) {
            self::set($key, $value);
        }
        return true;
    }
    
    /**
     * تنظیمات پیش‌فرض
     */
    private static function get_defaults() {
        return [
            // تنظیمات همگام‌سازی
            'sync_enabled' => '1',
            'sync_interval' => '300',              // 5 دقیقه (ثانیه)
            'max_sync_batch' => '50',              // تعداد در هر اجرای cron
            'sync_retry_attempts' => '3',
            
            // تنظیمات دیتابیس وردپرس (برای sync)
            'wp_db_host' => 'localhost',
            'wp_db_name' => 'wordpress_db',
            'wp_db_user' => 'root',
            'wp_db_pass' => '',
            'wp_db_prefix' => 'wp_',
            
            // مسیر wp-load.php برای دسترسی به توابع ووکامرس
            'wp_load_path' => '/var/www/html/wordpress/wp-load.php',
            
            // URL سایت وردپرس (برای لینک تصاویر)
            'wp_site_url' => 'https://yoursite.com',
            'wp_uploads_url' => 'https://yoursite.com/wp-content/uploads',
            
            // تنظیمات امنیتی
            'session_lifetime' => '18000',         // 5 ساعت (ثانیه)
            'max_login_attempts' => '3',
            'login_lockout_time' => '900',         // 15 دقیقه (ثانیه)
            
            // تنظیمات پنل
            'panel_title' => 'پنل ثبت سفارش',
            'products_per_page' => '30',
            'default_order_status' => 'processing',
            'show_out_of_stock' => '0',            // نمایش محصولات ناموجود
            
            // تنظیمات نمایش
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i:s',
            'currency_symbol' => 'تومان',
            'currency_position' => 'right',        // left, right
            
            // لاگ و debug
            'enable_debug_log' => '0',
            'log_retention_days' => '30'
        ];
    }
    
    /**
     * بازنشانی تنظیمات به حالت پیش‌فرض
     */
    public static function reset_to_defaults() {
        $db = MN_Database::get_instance();
        
        // پاک کردن همه تنظیمات
        $db->query("DELETE FROM mn_settings");
        
        // درج تنظیمات پیش‌فرض
        $defaults = self::get_defaults();
        foreach ($defaults as $key => $value) {
            $db->insert('mn_settings', [
                'setting_key' => $key,
                'setting_value' => $value
            ]);
        }
        
        self::$cache = null;
        return true;
    }
    
    /**
     * بررسی معتبر بودن مسیر wp-load.php
     */
    public static function validate_wp_load_path($path) {
        if (!file_exists($path)) {
            return [
                'valid' => false,
                'message' => 'فایل wp-load.php در مسیر مشخص شده یافت نشد'
            ];
        }
        
        // بررسی اینکه واقعا wp-load است
        $content = file_get_contents($path, false, null, 0, 500);
        if (strpos($content, 'ABSPATH') === false) {
            return [
                'valid' => false,
                'message' => 'فایل مشخص شده wp-load.php معتبر نیست'
            ];
        }
        
        return [
            'valid' => true,
            'message' => 'مسیر معتبر است'
        ];
    }
    
    /**
     * تست اتصال به دیتابیس وردپرس
     */
    public static function test_wp_db_connection() {
        try {
            $config = [
                'host' => self::get('wp_db_host'),
                'name' => self::get('wp_db_name'),
                'user' => self::get('wp_db_user'),
                'pass' => self::get('wp_db_pass')
            ];
            
            $dsn = "mysql:host={$config['host']};dbname={$config['name']}";
            $pdo = new PDO($dsn, $config['user'], $config['pass']);
            
            // تست یک کوئری ساده
            $prefix = self::get('wp_db_prefix');
            $stmt = $pdo->query("SELECT COUNT(*) FROM {$prefix}posts WHERE post_type='product'");
            $count = $stmt->fetchColumn();
            
            return [
                'success' => true,
                'message' => "اتصال موفق - {$count} محصول یافت شد"
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'خطا در اتصال: ' . $e->getMessage()
            ];
        }
    }
}

/**
 * فانکشن کمکی برای دریافت سریع تنظیمات
 */
function mn_get_option($key, $default = null) {
    return MN_Settings::get($key, $default);
}

/**
 * فانکشن کمکی برای ذخیره تنظیمات
 */
function mn_update_option($key, $value) {
    return MN_Settings::set($key, $value);
}
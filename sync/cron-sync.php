<?php
/**
 * MN Order Panel - Cron Sync Worker
 * این فایل را در crontab اضافه کنید یا از طریق مرورگر با یک secret key اجرا کنید
 * 
 * نحوه استفاده در Crontab:
 * 5 * * * * /usr/bin/php /path/to/mn_order_panel/sync/cron-sync.php >> /var/log/mn-sync.log 2>&1
 * 
 * یا از طریق مرورگر:
 * https://yoursite.com/mn_order_panel/sync/cron-sync.php?key=YOUR_SECRET_KEY
 */

// خاموش کردن خروجی خطا برای header
error_reporting(E_ALL & ~E_WARNING);

// تنظیمات
define('MN_CRON_SECRET_KEY', '6fd06ffc-5845-4ae8-9ebb-d8e8de373d78'); // حتما تغییر بده!
define('MN_CRON_LOCK_FILE', sys_get_temp_dir() . '/mn-sync.lock');

// اگر از مرورگر اجرا شده، چک کردن کلید امنیتی
if (php_sapi_name() !== 'cli') {
    if (!isset($_GET['key']) || $_GET['key'] !== MN_CRON_SECRET_KEY) {
        http_response_code(403);
        die('Unauthorized');
    }
    header('Content-Type: text/plain; charset=utf-8');
    ob_start(); // شروع output buffering
}

// مسیر wp-load
$wp_load_path = __DIR__ . '/../../wordpress/wp-load.php'; // تنظیم کن!

// اگر فایل تنظیمات موجود بود، مسیر را از آنجا بگیر
$settings_file = __DIR__ . '/../config/settings.php';
if (file_exists($settings_file)) {
    require_once $settings_file;
    $wp_load_path = MN_Settings::get('wp_load_path', $wp_load_path);
}

// بررسی وجود wp-load.php
if (!file_exists($wp_load_path)) {
    die("ERROR: wp-load.php not found at: {$wp_load_path}\n");
}

// Lock برای جلوگیری از اجرای همزمان
$lock = fopen(MN_CRON_LOCK_FILE, 'w');
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    die("Another sync process is already running\n");
}

try {
    echo "[" . date('Y-m-d H:i:s') . "] Starting sync process...\n";
    
    // بارگذاری وردپرس
    require_once $wp_load_path;
    echo "✓ WordPress loaded\n";
    
    // بارگذاری کلاس‌های sync
    require_once __DIR__ . '/class-sync-manager.php';
    
    $sync_manager = new MN_Sync_Manager();
    
    // دریافت تنظیمات
    $max_batch = 50;
    if (class_exists('MN_Settings')) {
        $max_batch = intval(MN_Settings::get('max_sync_batch', 50));
    }
    
    // دریافت موارد صف
    $pending_items = $sync_manager->get_pending_queue($max_batch);
    
    echo "Found " . count($pending_items) . " items to sync\n";
    
    if (count($pending_items) === 0) {
        echo "Nothing to sync. Exiting.\n";
        exit(0);
    }
    
    $success_count = 0;
    $failed_count = 0;
    
    foreach ($pending_items as $item) {
        try {
            $result = $sync_manager->process_queue_item($item);
            
            if ($result['success']) {
                $success_count++;
                $time = round($result['execution_time'], 2);
                echo "✓ Synced {$item->entity_type} #{$item->entity_id} ({$time}s)\n";
            } else {
                $failed_count++;
                echo "✗ Failed {$item->entity_type} #{$item->entity_id}: {$result['error']}\n";
            }
            
        } catch (Exception $e) {
            $failed_count++;
            echo "✗ Exception for {$item->entity_type} #{$item->entity_id}: {$e->getMessage()}\n";
        }
        
        // فاصله کوتاه بین هر sync
        usleep(100000); // 0.1 ثانیه
    }
    
    // آمار نهایی
    $stats = $sync_manager->get_stats();
    
    echo "\n";
    echo "==========================================\n";
    echo "[" . date('Y-m-d H:i:s') . "] Sync completed\n";
    echo "Success: {$success_count}\n";
    echo "Failed: {$failed_count}\n";
    echo "==========================================\n";
    echo "Queue Status:\n";
    echo "  Pending: {$stats->pending}\n";
    echo "  Processing: {$stats->processing}\n";
    echo "  Completed: {$stats->completed}\n";
    echo "  Failed: {$stats->failed}\n";
    echo "==========================================\n";
    
    // پاک کردن آیتم‌های completed قدیمی (بیشتر از 7 روز)
    if ($stats->completed > 1000) {
        $deleted = $sync_manager->cleanup_old_completed(7);
        echo "Cleaned up {$deleted} old completed items\n";
    }
    
    // Flush output buffer اگر از مرورگر اجرا شده
    if (php_sapi_name() !== 'cli') {
        ob_end_flush();
    }
    
} catch (Exception $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    error_log('MN Sync Fatal Error: ' . $e->getMessage());
    
    if (php_sapi_name() !== 'cli') {
        ob_end_flush();
    }
    
    exit(1);
} finally {
    if (is_resource($lock)) {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

exit(0);
<?php
/**
 * MN Order Panel - Installer
 * نصب خودکار دیتابیس و تنظیمات اولیه
 * 
 * نحوه استفاده:
 * 1. فایل config/database.php را باز کنید و اطلاعات دیتابیس را وارد کنید
 * 2. در مرورگر به آدرس install.php بروید
 * 3. پس از نصب موفق، این فایل را حذف کنید!
 */

// جلوگیری از اجرای مجدد
if (file_exists(__DIR__ . '/.installed')) {
    die('
    <!DOCTYPE html>
    <html dir="rtl" lang="fa">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>قبلا نصب شده</title>
        <style>
            body { font-family: Tahoma, Arial; background: #f5f5f5; padding: 40px; text-align: center; }
            .box { background: white; padding: 40px; border-radius: 10px; max-width: 500px; margin: 0 auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .error { color: #e74c3c; font-size: 18px; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class="box">
            <h2 class="error">⚠️ پنل قبلاً نصب شده است</h2>
            <p>برای نصب مجدد، ابتدا فایل <code>.installed</code> را حذف کنید.</p>
            <a href="index.php">رفتن به پنل</a>
        </div>
    </body>
    </html>
    ');
}

// تنظیمات دیتابیس
$db_config = [
    'host' => '192.168.150.100:3306',
    'user' => 'root',
    'pass' => 'PZ93uH2P9qntsn',
    'panel_db' => 'mn_panel_db',    // دیتابیس پنل
    'charset' => 'utf8mb4'
];

$errors = [];
$success_messages = [];
$pdo = null;

// اگر فرم ارسال شده
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // دریافت اطلاعات از فرم
    $db_config['host'] = $_POST['db_host'] ?? '192.168.150.100:3306';
    $db_config['user'] = $_POST['db_user'] ?? 'root';
    $db_config['pass'] = $_POST['db_pass'] ?? '';
    $db_config['panel_db'] = $_POST['panel_db'] ?? 'mn_panel_db';
    
    try {
        
        // ========================================
        // مرحله 1: اتصال به MySQL
        // ========================================
        $dsn = "mysql:host={$db_config['host']};charset={$db_config['charset']}";
        $pdo = new PDO($dsn, $db_config['user'], $db_config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $success_messages[] = "✓ اتصال به MySQL برقرار شد";
        
        // ========================================
        // مرحله 2: ایجاد دیتابیس پنل
        // ========================================
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_config['panel_db']}` 
                    CHARACTER SET {$db_config['charset']} 
                    COLLATE {$db_config['charset']}_unicode_ci");
        $success_messages[] = "✓ دیتابیس '{$db_config['panel_db']}' ایجاد شد";
        
        // انتخاب دیتابیس
        $pdo->exec("USE `{$db_config['panel_db']}`");
        
        // ========================================
        // مرحله 3: ایجاد جداول
        // ========================================
        
        // جدول کاربران پنل
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `mn_panel_users` (
              `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              `username` VARCHAR(50) UNIQUE NOT NULL,
              `password` VARCHAR(255) NOT NULL,
              `full_name` VARCHAR(100),
              `email` VARCHAR(100),
              `role` ENUM('admin', 'operator') DEFAULT 'operator',
              `is_active` TINYINT(1) DEFAULT 1,
              `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
              `last_login` DATETIME NULL,
              `last_login_ip` VARCHAR(45) NULL,
              INDEX `idx_username` (`username`),
              INDEX `idx_active` (`is_active`),
              INDEX `idx_role` (`role`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$db_config['charset']} COLLATE={$db_config['charset']}_unicode_ci
        ");
        $success_messages[] = "✓ جدول کاربران (mn_panel_users) ایجاد شد";
        
        // جدول مشتریان
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `mn_customers` (
              `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              `first_name` VARCHAR(50) NOT NULL,
              `last_name` VARCHAR(50) NOT NULL,
              `phone` VARCHAR(20) NOT NULL,
              `email` VARCHAR(100),
              `address` TEXT,
              `city` VARCHAR(50),
              `state` VARCHAR(50),
              `postcode` VARCHAR(20),
              `wp_user_id` INT NULL,
              `synced_at` DATETIME NULL,
              `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
              `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              INDEX `idx_phone` (`phone`),
              INDEX `idx_email` (`email`),
              INDEX `idx_wp_user` (`wp_user_id`),
              INDEX `idx_synced` (`synced_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$db_config['charset']} COLLATE={$db_config['charset']}_unicode_ci
        ");
        $success_messages[] = "✓ جدول مشتریان (mn_customers) ایجاد شد";
        
        // جدول سفارشات
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `mn_orders` (
              `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              `customer_id` INT UNSIGNED NOT NULL,
              `panel_user_id` INT UNSIGNED NOT NULL,
              `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
              `order_notes` TEXT,
              `status` ENUM('pending', 'syncing', 'synced', 'failed') DEFAULT 'pending',
              `wc_order_id` BIGINT NULL,
              `sync_attempts` INT DEFAULT 0,
              `last_sync_error` TEXT NULL,
              `synced_at` DATETIME NULL,
              `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
              `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              FOREIGN KEY (`customer_id`) REFERENCES `mn_customers`(`id`) ON DELETE RESTRICT,
              FOREIGN KEY (`panel_user_id`) REFERENCES `mn_panel_users`(`id`) ON DELETE RESTRICT,
              INDEX `idx_customer` (`customer_id`),
              INDEX `idx_panel_user` (`panel_user_id`),
              INDEX `idx_status` (`status`),
              INDEX `idx_wc_order` (`wc_order_id`),
              INDEX `idx_created` (`created_at`),
              INDEX `idx_synced` (`synced_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$db_config['charset']} COLLATE={$db_config['charset']}_unicode_ci
        ");
        $success_messages[] = "✓ جدول سفارشات (mn_orders) ایجاد شد";
        
        // جدول آیتم‌های سفارش
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `mn_order_items` (
              `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              `order_id` INT UNSIGNED NOT NULL,
              `product_id` BIGINT NOT NULL,
              `product_name` VARCHAR(255) NOT NULL,
              `product_sku` VARCHAR(100),
              `quantity` INT NOT NULL DEFAULT 1,
              `price` DECIMAL(10,2) NOT NULL,
              `total` DECIMAL(10,2) NOT NULL,
              `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
              FOREIGN KEY (`order_id`) REFERENCES `mn_orders`(`id`) ON DELETE CASCADE,
              INDEX `idx_order` (`order_id`),
              INDEX `idx_product` (`product_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$db_config['charset']} COLLATE={$db_config['charset']}_unicode_ci
        ");
        $success_messages[] = "✓ جدول آیتم‌های سفارش (mn_order_items) ایجاد شد";
        
        // جدول صف همگام‌سازی
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `mn_sync_queue` (
              `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              `entity_type` ENUM('order', 'customer') NOT NULL,
              `entity_id` INT UNSIGNED NOT NULL,
              `priority` TINYINT DEFAULT 5,
              `attempts` INT DEFAULT 0,
              `max_attempts` INT DEFAULT 3,
              `status` ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
              `error_message` TEXT NULL,
              `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
              `processed_at` DATETIME NULL,
              INDEX `idx_status_priority` (`status`, `priority`),
              INDEX `idx_entity` (`entity_type`, `entity_id`),
              INDEX `idx_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$db_config['charset']} COLLATE={$db_config['charset']}_unicode_ci
        ");
        $success_messages[] = "✓ جدول صف همگام‌سازی (mn_sync_queue) ایجاد شد";
        
        // جدول لاگ همگام‌سازی
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `mn_sync_log` (
              `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              `queue_id` INT UNSIGNED,
              `entity_type` ENUM('order', 'customer') NOT NULL,
              `entity_id` INT UNSIGNED NOT NULL,
              `action` VARCHAR(50) NOT NULL,
              `wp_entity_id` BIGINT NULL,
              `message` TEXT,
              `execution_time` FLOAT,
              `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
              FOREIGN KEY (`queue_id`) REFERENCES `mn_sync_queue`(`id`) ON DELETE SET NULL,
              INDEX `idx_entity` (`entity_type`, `entity_id`),
              INDEX `idx_created` (`created_at`),
              INDEX `idx_wp_entity` (`wp_entity_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$db_config['charset']} COLLATE={$db_config['charset']}_unicode_ci
        ");
        $success_messages[] = "✓ جدول لاگ (mn_sync_log) ایجاد شد";
        
        // جدول تنظیمات
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `mn_settings` (
              `setting_key` VARCHAR(100) PRIMARY KEY,
              `setting_value` TEXT,
              `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET={$db_config['charset']} COLLATE={$db_config['charset']}_unicode_ci
        ");
        $success_messages[] = "✓ جدول تنظیمات (mn_settings) ایجاد شد";
        
        // جدول تلاش‌های ورود
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `mn_login_attempts` (
              `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              `username` VARCHAR(50) NOT NULL,
              `ip_address` VARCHAR(45) NOT NULL,
              `attempt_time` DATETIME DEFAULT CURRENT_TIMESTAMP,
              `success` TINYINT(1) DEFAULT 0,
              INDEX `idx_username_ip` (`username`, `ip_address`),
              INDEX `idx_attempt_time` (`attempt_time`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$db_config['charset']} COLLATE={$db_config['charset']}_unicode_ci
        ");
        $success_messages[] = "✓ جدول تلاش‌های ورود (mn_login_attempts) ایجاد شد";
        
        // ========================================
        // مرحله 4: درج کاربر پیش‌فرض
        // ========================================
        // Username: admin
        // Password: admin
        $default_password = password_hash('admin', PASSWORD_BCRYPT);
        
        $stmt = $pdo->prepare("
            INSERT INTO mn_panel_users (username, password, full_name, role) 
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE password = VALUES(password)
        ");
        $stmt->execute(['admin', $default_password, 'مدیر سیستم', 'admin']);
        $success_messages[] = "✓ کاربر پیش‌فرض ایجاد شد (admin / admin)";
        
        // ========================================
        // مرحله 5: درج تنظیمات پیش‌فرض
        // ========================================
        $default_settings = [
            'sync_enabled' => '1',
            'sync_interval' => '300',
            'max_sync_batch' => '50',
            'sync_retry_attempts' => '3',
            'wp_db_host' => 'localhost',
            'wp_db_name' => 'wordpress_db',
            'wp_db_user' => 'root',
            'wp_db_pass' => '',
            'wp_db_prefix' => 'wp_',
            'wp_load_path' => '/var/www/html/wordpress/wp-load.php',
            'wp_site_url' => 'https://yoursite.com',
            'wp_uploads_url' => 'https://yoursite.com/wp-content/uploads',
            'session_lifetime' => '18000',
            'max_login_attempts' => '3',
            'login_lockout_time' => '900',
            'panel_title' => 'پنل ثبت سفارش',
            'products_per_page' => '30',
            'default_order_status' => 'processing',
            'show_out_of_stock' => '0',
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i:s',
            'currency_symbol' => 'تومان',
            'currency_position' => 'right',
            'enable_debug_log' => '0',
            'log_retention_days' => '30'
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO mn_settings (setting_key, setting_value) 
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        
        foreach ($default_settings as $key => $value) {
            $stmt->execute([$key, $value]);
        }
        $success_messages[] = "✓ تنظیمات پیش‌فرض درج شد";
        
        // ========================================
        // مرحله 6: بروزرسانی فایل config/database.php
        // ========================================
        $config_file = __DIR__ . '/config/database.php';
        if (file_exists($config_file)) {
            $config_content = file_get_contents($config_file);
            
            // جایگزینی مقادیر
            $config_content = preg_replace(
                "/'host' => '.*?'/",
                "'host' => '{$db_config['host']}'",
                $config_content
            );
            $config_content = preg_replace(
                "/'dbname' => '.*?'/",
                "'dbname' => '{$db_config['panel_db']}'",
                $config_content
            );
            $config_content = preg_replace(
                "/'user' => '.*?'/",
                "'user' => '{$db_config['user']}'",
                $config_content
            );
            $config_content = preg_replace(
                "/'pass' => '.*?'/",
                "'pass' => '{$db_config['pass']}'",
                $config_content
            );
            
            file_put_contents($config_file, $config_content);
            $success_messages[] = "✓ فایل config/database.php بروزرسانی شد";
        }
        
        // ========================================
        // مرحله 7: ایجاد فایل .installed
        // ========================================
        file_put_contents(__DIR__ . '/.installed', date('Y-m-d H:i:s'));
        $success_messages[] = "✓ فایل .installed ایجاد شد";
        
        $success_messages[] = "<strong>🎉 نصب با موفقیت انجام شد!</strong>";
        
    } catch (PDOException $e) {
        $errors[] = "❌ خطای دیتابیس: " . $e->getMessage();
    } catch (Exception $e) {
        $errors[] = "❌ خطا: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نصب پنل ثبت سفارش</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Tahoma, Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 600px;
            width: 100%;
            padding: 40px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
            text-align: center;
        }
        .subtitle {
            color: #666;
            text-align: center;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: bold;
            font-size: 14px;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
            font-family: Tahoma, Arial;
        }
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }
        .hint {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
        button {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .messages {
            margin-top: 25px;
            padding: 20px;
            border-radius: 8px;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .messages ul {
            list-style: none;
            padding: 0;
        }
        .messages li {
            padding: 5px 0;
            line-height: 1.6;
        }
        .success-box {
            text-align: center;
            padding: 30px;
        }
        .success-icon {
            font-size: 60px;
            margin-bottom: 20px;
        }
        .btn-link {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 30px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
        }
        .btn-link:hover {
            background: #5568d3;
        }
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!empty($success_messages) && empty($errors)): ?>
            <!-- نمایش موفقیت -->
            <div class="success-box">
                <div class="success-icon">🎉</div>
                <h1>نصب با موفقیت انجام شد!</h1>
                <div class="messages success">
                    <ul>
                        <?php foreach ($success_messages as $msg): ?>
                            <li><?php echo $msg; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <p style="margin-top: 20px; color: #666;">
                    اطلاعات ورود پیش‌فرض:<br>
                    <strong>نام کاربری:</strong> admin<br>
                    <strong>رمز عبور:</strong> admin
                </p>
                <a href="index.php" class="btn-link">ورود به پنل</a>
                <p style="margin-top: 20px; color: #e74c3c; font-size: 13px;">
                    ⚠️ لطفا فایل install.php را حذف کنید!
                </p>
            </div>
        <?php else: ?>
            <!-- فرم نصب -->
            <h1>🚀 نصب پنل ثبت سفارش</h1>
            <p class="subtitle">لطفا اطلاعات دیتابیس را وارد کنید</p>
            
            <div class="warning-box">
                <strong>⚠️ توجه:</strong> این فایل پس از نصب موفق باید حذف شود!
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="messages error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>هاست دیتابیس</label>
                    <input type="text" name="db_host" value="192.168.150.100:3306" required>
                    <div class="hint">معمولا localhost است</div>
                </div>
                
                <div class="form-group">
                    <label>نام کاربری دیتابیس</label>
                    <input type="text" name="db_user" value="root" required>
                </div>
                
                <div class="form-group">
                    <label>رمز عبور دیتابیس</label>
                    <input type="password" name="db_pass">
                    <div class="hint">در صورت خالی بودن خالی بگذارید</div>
                </div>
                
                <div class="form-group">
                    <label>نام دیتابیس پنل</label>
                    <input type="text" name="panel_db" value="mn_panel_db" required>
                    <div class="hint">نام دیتابیس مجزای پنل (خودکار ساخته می‌شود)</div>
                </div>
                
                <button type="submit">شروع نصب</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
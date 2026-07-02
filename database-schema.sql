-- ====================================================================
-- MN Order Panel - Database Schema (Complete)
-- دیتابیس مجزا برای پنل ثبت سفارش با سیستم احراز هویت
-- ====================================================================

-- ایجاد دیتابیس (در صورت نیاز)
CREATE DATABASE IF NOT EXISTS `mn_panel_db` 
DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `mn_panel_db`;

-- ====================================================================
-- 1. جدول کاربران پنل (بروز شده با فیلدهای امنیتی)
-- ====================================================================
CREATE TABLE IF NOT EXISTS `mn_panel_users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) UNIQUE NOT NULL,
  `password` VARCHAR(255) NOT NULL COMMENT 'bcrypt hash',
  `full_name` VARCHAR(100),
  `email` VARCHAR(100),
  `role` ENUM('admin', 'operator') DEFAULT 'operator',
  `is_active` TINYINT(1) DEFAULT 1,
  `failed_login_attempts` INT DEFAULT 0 COMMENT 'تعداد تلاش ناموفق',
  `last_login` DATETIME NULL,
  `last_login_ip` VARCHAR(45) NULL,
  `last_login_attempt` DATETIME NULL COMMENT 'آخرین تلاش ورود',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_username` (`username`),
  INDEX `idx_active` (`is_active`),
  INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- کاربر پیش‌فرض (username: admin, password: admin123)
-- رمز عبور: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
INSERT INTO `mn_panel_users` (`username`, `password`, `full_name`, `role`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'مدیر سیستم', 'admin')
ON DUPLICATE KEY UPDATE `username` = `username`;

-- ====================================================================
-- 2. جدول مشتریان (قبل از sync به وردپرس)
-- ====================================================================
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
  `wp_user_id` INT NULL COMMENT 'WordPress User ID بعد از sync',
  `synced_at` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_phone` (`phone`),
  INDEX `idx_email` (`email`),
  INDEX `idx_wp_user` (`wp_user_id`),
  INDEX `idx_synced` (`synced_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- 3. جدول سفارشات پنل (قبل از sync به ووکامرس)
-- ====================================================================
CREATE TABLE IF NOT EXISTS `mn_orders` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `customer_id` INT UNSIGNED NOT NULL,
  `panel_user_id` INT UNSIGNED NOT NULL COMMENT 'چه کسی سفارش را ثبت کرده',
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `order_notes` TEXT,
  `status` ENUM('pending', 'syncing', 'synced', 'failed') DEFAULT 'pending',
  `wc_order_id` BIGINT NULL COMMENT 'WooCommerce Order ID بعد از sync',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- 4. جدول آیتم‌های سفارش
-- ====================================================================
CREATE TABLE IF NOT EXISTS `mn_order_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT UNSIGNED NOT NULL,
  `product_id` BIGINT NOT NULL COMMENT 'WooCommerce Product ID',
  `product_name` VARCHAR(255) NOT NULL,
  `product_sku` VARCHAR(100),
  `quantity` INT NOT NULL DEFAULT 1,
  `price` DECIMAL(10,2) NOT NULL COMMENT 'قیمت واحد',
  `total` DECIMAL(10,2) NOT NULL COMMENT 'قیمت کل (quantity * price)',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `mn_orders`(`id`) ON DELETE CASCADE,
  INDEX `idx_order` (`order_id`),
  INDEX `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- 5. جدول صف همگام‌سازی
-- ====================================================================
CREATE TABLE IF NOT EXISTS `mn_sync_queue` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `entity_type` ENUM('order', 'customer') NOT NULL,
  `entity_id` INT UNSIGNED NOT NULL,
  `priority` TINYINT DEFAULT 5 COMMENT '1=بالا، 10=پایین',
  `attempts` INT DEFAULT 0,
  `max_attempts` INT DEFAULT 3,
  `status` ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
  `error_message` TEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `processed_at` DATETIME NULL,
  INDEX `idx_status_priority` (`status`, `priority`),
  INDEX `idx_entity` (`entity_type`, `entity_id`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- 6. جدول لاگ همگام‌سازی
-- ====================================================================
CREATE TABLE IF NOT EXISTS `mn_sync_log` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `queue_id` INT UNSIGNED,
  `entity_type` ENUM('order', 'customer') NOT NULL,
  `entity_id` INT UNSIGNED NOT NULL,
  `action` VARCHAR(50) NOT NULL COMMENT 'sync_started, sync_completed, sync_failed',
  `wp_entity_id` BIGINT NULL COMMENT 'WC Order ID یا WP User ID',
  `message` TEXT,
  `execution_time` FLOAT COMMENT 'زمان اجرا به ثانیه',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`queue_id`) REFERENCES `mn_sync_queue`(`id`) ON DELETE SET NULL,
  INDEX `idx_entity` (`entity_type`, `entity_id`),
  INDEX `idx_created` (`created_at`),
  INDEX `idx_wp_entity` (`wp_entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- 7. جدول تنظیمات سیستم
-- ====================================================================
CREATE TABLE IF NOT EXISTS `mn_settings` (
  `setting_key` VARCHAR(100) PRIMARY KEY,
  `setting_value` TEXT,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- تنظیمات پیش‌فرض
INSERT INTO `mn_settings` (`setting_key`, `setting_value`) VALUES
('sync_enabled', '1'),
('sync_interval', '300'),
('max_sync_batch', '50'),
('sync_retry_attempts', '3'),
('wp_db_host', '192.168.150.100:3306'),
('wp_db_name', 'puonak_db'),
('wp_db_user', 'root'),
('wp_db_pass', 'PZ93uH2P9qntsn'),
('wp_db_prefix', 'wp_'),
('wp_load_path', '/var/www/html/wp-load.php'),
('wp_site_url', 'https://www.puonak.com'),
('wp_uploads_url', 'https://www.puonak.com/wp-content/uploads'),
('session_lifetime', '18000'),
('max_login_attempts', '5'),
('login_lockout_time', '900'),
('panel_title', 'پنل ثبت سفارش'),
('products_per_page', '30'),
('default_order_status', 'processing'),
('show_out_of_stock', '0'),
('date_format', 'Y-m-d'),
('time_format', 'H:i:s'),
('currency_symbol', 'تومان'),
('currency_position', 'right'),
('enable_debug_log', '0'),
('log_retention_days', '30')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

-- ====================================================================
-- 8. جدول تلاش‌های ورود (برای امنیت - Legacy)
-- ====================================================================
CREATE TABLE IF NOT EXISTS `mn_login_attempts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `attempt_time` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `success` TINYINT(1) DEFAULT 0,
  INDEX `idx_username_ip` (`username`, `ip_address`),
  INDEX `idx_attempt_time` (`attempt_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- 9. جدول توکن‌های Remember Me
-- ====================================================================
CREATE TABLE IF NOT EXISTS `mn_user_tokens` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `token` VARCHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `token` (`token`),
  FOREIGN KEY (`user_id`) REFERENCES `mn_panel_users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user` (`user_id`),
  INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- 10. جدول لاگ ورود/خروج
-- ====================================================================
CREATE TABLE IF NOT EXISTS `mn_login_log` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NULL,
  `username` VARCHAR(50) DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT,
  `status` ENUM('success','logout','failed') NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `mn_panel_users`(`id`) ON DELETE SET NULL,
  INDEX `idx_user` (`user_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- ====================================================================
-- 11. جدجدول محصولات پنل
-- ====================================================================
CREATE TABLE IF NOT EXISTS `mn_products` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `wp_product_id` bigint(20) DEFAULT NULL COMMENT 'شناسه محصول در وردپرس',
  `title` varchar(500) NOT NULL COMMENT 'عنوان محصول',
  `sku` varchar(100) DEFAULT NULL COMMENT 'شناسه یکتا',
  -- قیمت‌گذاری
  `regular_price` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'قیمت فروش',
  `purchase_price` decimal(15,2) DEFAULT NULL COMMENT 'قیمت خرید (حسابداری)',
  `sale_price` decimal(15,2) DEFAULT NULL COMMENT 'قیمت تخفیف‌خورده',
  `discount_percent` decimal(5,2) DEFAULT NULL COMMENT 'درصد تخفیف',
  
  -- موجودی
  `stock_quantity` int(11) DEFAULT NULL COMMENT 'تعداد موجودی مجازی (برای فروش آنلاین)',
  `real_stock_quantity` int(11) DEFAULT NULL COMMENT 'تعداد موجودی واقعی (برای فروش حضوری)',
  `stock_status` enum('instock','outofstock','onbackorder') DEFAULT 'instock' COMMENT 'وضعیت موجودی',
  `manage_stock` tinyint(1) DEFAULT 1 COMMENT 'مدیریت موجودی',
  
  -- مشخصات فیزیکی
  `weight` decimal(10,2) DEFAULT NULL COMMENT 'وزن (گرم)',
  `length` decimal(10,2) DEFAULT NULL COMMENT 'طول (سانتی‌متر)',
  `width` decimal(10,2) DEFAULT NULL COMMENT 'عرض (سانتی‌متر)',
  `height` decimal(10,2) DEFAULT NULL COMMENT 'ارتفاع (سانتی‌متر)',
  -- تصویر
  `image_url` varchar(500) DEFAULT NULL COMMENT 'آدرس تصویر',
  `wp_image_id` bigint(20) DEFAULT NULL COMMENT 'شناسه تصویر در وردپرس',
  -- وضعیت
  `status` enum('active','inactive','draft') DEFAULT 'active' COMMENT 'وضعیت محصول',
  `is_synced` tinyint(1) DEFAULT 0 COMMENT 'همگام‌سازی شده؟',
  -- تاریخ‌ها
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL COMMENT 'ایجاد شده توسط',
  `synced_at` datetime DEFAULT NULL COMMENT 'تاریخ همگام‌سازی',
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `sku` (`sku`),
  KEY `wp_product_id` (`wp_product_id`),
  KEY `status` (`status`),
  KEY `stock_status` (`stock_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- ====================================================================
-- 12. جدول دسته‌ها و ویژگی‌های محصولات
-- ====================================================================
CREATE TABLE IF NOT EXISTS `mn_product_extra` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) NOT NULL COMMENT 'شناسه محصول در mn_products',
  `wp_product_id` bigint(20) DEFAULT NULL COMMENT 'شناسه محصول در وردپرس',
  `type` enum('category','attribute') NOT NULL COMMENT 'نوع: دسته یا ویژگی',
  
  -- برای دسته‌بندی (type = category)
  `category_id` bigint(20) DEFAULT NULL COMMENT 'شناسه دسته در وردپرس',
  `category_name` varchar(200) DEFAULT NULL COMMENT 'نام دسته',
  
  -- برای ویژگی (type = attribute)
  `attribute_id` int(11) DEFAULT NULL COMMENT 'شناسه attribute در وردپرس',
  `attribute_name` varchar(200) DEFAULT NULL COMMENT 'نام attribute (مثل pa_brand)',
  `attribute_label` varchar(200) DEFAULT NULL COMMENT 'برچسب attribute (مثل برند)',
  `term_id` bigint(20) DEFAULT NULL COMMENT 'شناسه term در وردپرس (اگر از لیست باشه)',
  `value` varchar(500) DEFAULT NULL COMMENT 'مقدار ویژگی یا دسته',
  
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `wp_product_id` (`wp_product_id`),
  KEY `type` (`type`),
  KEY `category_id` (`category_id`),
  KEY `attribute_name` (`attribute_name`),
  KEY `attribute_id` (`attribute_id`),
  KEY `term_id` (`term_id`),
  FOREIGN KEY (`product_id`) REFERENCES `mn_products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- 13. جدول -- -- جدول فاکتورهای خرید محصول
-- ====================================================================
CREATE TABLE IF NOT EXISTS `mn_product_invoices` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) NOT NULL COMMENT 'شناسه محصول در mn_products',
  `invoice_number` varchar(100) DEFAULT NULL COMMENT 'شماره فاکتور',
  `invoice_date` date NOT NULL COMMENT 'تاریخ فاکتور',
  
  -- اطلاعات خرید
  `supplier_name` varchar(300) NOT NULL COMMENT 'نام فروشنده/تامین‌کننده',
  `supplier_phone` varchar(50) DEFAULT NULL COMMENT 'تلفن فروشنده',
  `quantity` int(11) NOT NULL COMMENT 'تعداد خریداری شده',
  `unit_price` decimal(15,2) NOT NULL COMMENT 'قیمت واحد',
  `total_price` decimal(15,2) NOT NULL COMMENT 'مبلغ کل',
  `discount` decimal(15,2) DEFAULT 0.00 COMMENT 'تخفیف',
  `tax` decimal(15,2) DEFAULT 0.00 COMMENT 'مالیات/عوارض',
  `shipping_cost` decimal(15,2) DEFAULT 0.00 COMMENT 'هزینه حمل',
  `final_amount` decimal(15,2) NOT NULL COMMENT 'مبلغ نهایی',
  
  -- جزئیات پرداخت
  `payment_method` enum('cash','card','cheque','credit','other') DEFAULT 'cash' COMMENT 'نوع پرداخت',
  `payment_status` enum('paid','unpaid','partial') DEFAULT 'paid' COMMENT 'وضعیت پرداخت',
  `paid_amount` decimal(15,2) DEFAULT 0.00 COMMENT 'مبلغ پرداخت شده',
  
  -- اطلاعات تکمیلی
  `notes` text DEFAULT NULL COMMENT 'یادداشت',
  `invoice_file` varchar(500) DEFAULT NULL COMMENT 'فایل اسکن فاکتور',
  
  -- تاریخ‌ها
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL COMMENT 'ثبت شده توسط',
  
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `invoice_date` (`invoice_date`),
  KEY `supplier_name` (`supplier_name`),
  KEY `payment_status` (`payment_status`),
  FOREIGN KEY (`product_id`) REFERENCES `mn_products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ====================================================================
-- 15. جدول --- جدول تاریخچه تغییرات موجودی
-- ====================================================================
CREATE TABLE IF NOT EXISTS `mn_stock_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) NOT NULL COMMENT 'شناسه محصول در mn_products',
  `wp_product_id` bigint(20) DEFAULT NULL COMMENT 'شناسه محصول در وردپرس',
  `stock_type` enum('virtual','real') DEFAULT 'virtual' COMMENT 'نوع موجودی: مجازی یا واقعی',
  `change_type` enum('increase','decrease','set','sync') NOT NULL COMMENT 'نوع تغییر',
  `quantity_before` int(11) NOT NULL COMMENT 'موجودی قبل',
  `quantity_change` int(11) NOT NULL COMMENT 'مقدار تغییر',
  `quantity_after` int(11) NOT NULL COMMENT 'موجودی بعد',
  `reference_type` enum('manual','invoice','order','sync') DEFAULT 'manual' COMMENT 'منبع تغییر',
  `reference_id` bigint(20) DEFAULT NULL COMMENT 'شناسه منبع (فاکتور یا سفارش)',
  `notes` text DEFAULT NULL COMMENT 'یادداشت',
  `created_by` int(11) DEFAULT NULL COMMENT 'ایجاد شده توسط',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `wp_product_id` (`wp_product_id`),
  KEY `stock_type` (`stock_type`),
  KEY `reference_type` (`reference_type`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- ====================================================================
-- 16. جدول --- جدول تاریخچه تغییرات موجودی
-- ====================================================================
 
CREATE TABLE IF NOT EXISTS `mn_product_images` (
  `id`            bigint(20)   NOT NULL AUTO_INCREMENT,
  `product_id`    bigint(20)   NOT NULL COMMENT 'شناسه محصول در mn_products',
  `image_url`     varchar(500) NOT NULL COMMENT 'آدرس تصویر',
  `wp_image_id`   bigint(20)   DEFAULT NULL COMMENT 'شناسه تصویر در وردپرس',
  `is_primary`    tinyint(1)   DEFAULT 0 COMMENT 'تصویر اصلی؟',
  `sort_order`    int(11)      DEFAULT 0 COMMENT 'ترتیب نمایش',
  `alt_text`      varchar(300) DEFAULT NULL COMMENT 'متن جایگزین',
  `created_at`    datetime     DEFAULT CURRENT_TIMESTAMP,
 
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `is_primary` (`is_primary`),
  FOREIGN KEY (`product_id`) REFERENCES `mn_products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ====================================================================
-- View برای داشبورد
-- ====================================================================
CREATE OR REPLACE VIEW `v_dashboard_stats` AS
SELECT 
    (SELECT COUNT(*) FROM mn_orders WHERE status = 'pending') as pending_orders,
    (SELECT COUNT(*) FROM mn_orders WHERE status = 'syncing') as syncing_orders,
    (SELECT COUNT(*) FROM mn_orders WHERE status = 'synced') as synced_orders,
    (SELECT COUNT(*) FROM mn_orders WHERE status = 'failed') as failed_orders,
    (SELECT COUNT(*) FROM mn_orders WHERE DATE(created_at) = CURDATE()) as today_orders,
    (SELECT SUM(total_amount) FROM mn_orders WHERE DATE(created_at) = CURDATE()) as today_revenue,
    (SELECT COUNT(*) FROM mn_sync_queue WHERE status = 'pending') as queue_pending,
    (SELECT COUNT(*) FROM mn_customers WHERE synced_at IS NULL) as unsynced_customers;

-- ====================================================================
-- نمایش اطلاعات
-- ====================================================================
SELECT '✅ Database schema created successfully!' as Status;
SELECT '✅ Default admin user created (username: admin, password: admin123)' as Info;
SELECT '✅ Authentication system ready' as AuthStatus;
SELECT '⚠️  Please change default admin password after first login!' as Warning;
SELECT * FROM v_dashboard_stats;








-- ====================================================================
-- Migration: جدول scheduler داخلی پنل
-- ====================================================================

CREATE TABLE IF NOT EXISTS `mn_scheduler` (
  `id`           int(11)      NOT NULL AUTO_INCREMENT,
  `job_name`     varchar(100) NOT NULL COMMENT 'نام job',
  `interval_sec` int(11)      NOT NULL DEFAULT 3600 COMMENT 'فاصله اجرا به ثانیه',
  `last_run`     datetime     DEFAULT NULL COMMENT 'آخرین اجرا',
  `next_run`     datetime     DEFAULT NULL COMMENT 'زمان اجرای بعدی',
  `is_running`   tinyint(1)   DEFAULT 0 COMMENT 'در حال اجرا؟',
  `last_result`  text         DEFAULT NULL COMMENT 'نتیجه آخرین اجرا',
  `enabled`      tinyint(1)   DEFAULT 1 COMMENT 'فعال؟',
  `created_at`   datetime     DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `job_name` (`job_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جاب‌های پیش‌فرض
INSERT IGNORE INTO `mn_scheduler` (`job_name`, `interval_sec`, `next_run`, `enabled`) VALUES
('wc_sales_sync', 1800, NOW(), 1);  -- هر 30 دقیقه

-- ====================================================================
-- Migration: جدول فاکتورهای فروش از وردپرس
-- ====================================================================

CREATE TABLE IF NOT EXISTS `mn_wc_sales` (
  `id`               bigint(20)    NOT NULL AUTO_INCREMENT,
  `wc_order_id`      bigint(20)    NOT NULL COMMENT 'شناسه سفارش در وردپرس',
  `wc_order_item_id` bigint(20)    DEFAULT NULL COMMENT 'شناسه آیتم سفارش در WC',
  `product_id`       bigint(20)    NOT NULL COMMENT 'شناسه محصول در mn_products',
  `wp_product_id`    bigint(20)    DEFAULT NULL COMMENT 'شناسه محصول در وردپرس',

  -- خریدار
  `customer_name`    varchar(300)  DEFAULT NULL,
  `customer_phone`   varchar(50)   DEFAULT NULL,
  `customer_email`   varchar(200)  DEFAULT NULL,

  -- آیتم
  `product_title`    varchar(500)  NOT NULL,
  `quantity`         int(11)       NOT NULL DEFAULT 1,
  `unit_price`       decimal(15,2) NOT NULL DEFAULT 0,
  `total_price`      decimal(15,2) NOT NULL DEFAULT 0,

  -- سفارش
  `order_total`      decimal(15,2) DEFAULT NULL COMMENT 'مبلغ کل سفارش',
  `order_status`     varchar(50)   DEFAULT NULL COMMENT 'وضعیت سفارش WC',
  `order_date`       datetime      DEFAULT NULL COMMENT 'تاریخ ثبت سفارش در WC',

  -- موجودی
  `stock_updated`    tinyint(1)    DEFAULT 0 COMMENT 'موجودی کاهش یافت؟',
  `stock_before`     int(11)       DEFAULT NULL,
  `stock_after`      int(11)       DEFAULT NULL,

  `created_at`       datetime      DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `wc_order_item` (`wc_order_id`, `wc_order_item_id`),
  KEY `product_id`    (`product_id`),
  KEY `wp_product_id` (`wp_product_id`),
  KEY `order_date`    (`order_date`),
  KEY `stock_updated` (`stock_updated`),
  FOREIGN KEY (`product_id`) REFERENCES `mn_products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ذخیره آخرین order_id پردازش‌شده
INSERT IGNORE INTO `mn_settings` (`setting_key`, `setting_value`)
VALUES ('wc_sales_last_order_id', '0');

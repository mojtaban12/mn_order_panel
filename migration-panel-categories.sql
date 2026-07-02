-- ====================================================================
-- Migration: جدول دسته‌بندی‌های داخلی پنل
-- فقط برای فیلتر داخلی — sync با وردپرس ندارد
-- ====================================================================

CREATE TABLE IF NOT EXISTS `mn_panel_categories` (
  `id`          int(11)       NOT NULL AUTO_INCREMENT,
  `name`        varchar(200)  NOT NULL COMMENT 'نام دسته‌بندی',
  `slug`        varchar(200)  NOT NULL COMMENT 'شناسه یکتا',
  `parent_id`   int(11)       DEFAULT NULL COMMENT 'والد (NULL = دسته مادر)',
  `description` varchar(500)  DEFAULT NULL,
  `color`       varchar(20)   DEFAULT NULL COMMENT 'رنگ نمایشی (HEX)',
  `sort_order`  int(11)       DEFAULT 0,
  `created_at`  datetime      DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `parent_id` (`parent_id`),
  FOREIGN KEY (`parent_id`) REFERENCES `mn_panel_categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ستون panel_category_id به mn_products اضافه کنید
ALTER TABLE `mn_products`
  ADD COLUMN `panel_category_id` int(11) DEFAULT NULL COMMENT 'دسته داخلی پنل'
    AFTER `status`,
  ADD KEY `panel_category_id` (`panel_category_id`),
  ADD FOREIGN KEY (`panel_category_id`) REFERENCES `mn_panel_categories`(`id`) ON DELETE SET NULL;
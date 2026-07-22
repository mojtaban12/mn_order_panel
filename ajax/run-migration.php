<?php
/**
 * MN Order Panel - Database Migration
 * اضافه کردن ستون‌های حمل و نقل به جدول mn_orders
 *
 * ⚠️  یه بار اجرا کن، بعدش این فایل رو حذف کن.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

$db = MN_Database::get_instance();

$results = [];

$columns = [
    'shipping_cost' => [
        'check' => "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME   = 'mn_orders'
                      AND COLUMN_NAME  = 'shipping_cost'",
        'sql'   => "ALTER TABLE mn_orders
                    ADD COLUMN `shipping_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00
                    COMMENT 'هزینه حمل و نقل'
                    AFTER `total_amount`",
    ],
    'shipping_method' => [
        'check' => "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME   = 'mn_orders'
                      AND COLUMN_NAME  = 'shipping_method'",
        'sql'   => "ALTER TABLE mn_orders
                    ADD COLUMN `shipping_method` VARCHAR(255) NULL
                    COMMENT 'روش حمل و نقل'
                    AFTER `shipping_cost`",
    ],
];

foreach ($columns as $col => $def) {
    $exists = (int) $db->get_var($def['check']);

    if ($exists) {
        $results[$col] = '✅ ستون از قبل وجود دارد';
        continue;
    }

    $ok = $db->query($def['sql']);
    $results[$col] = $ok ? '✅ با موفقیت اضافه شد' : '❌ خطا در اضافه کردن';
}

echo json_encode([
    'success' => true,
    'results' => $results,
    'note'    => 'بعد از اجرای موفق این فایل را حذف کنید',
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

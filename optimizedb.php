<?php
/**
 * بهینه‌سازی دیتابیس وردپرس برای سرعت بیشتر
 * این فایل INDEX های لازم رو به جداول وردپرس اضافه می‌کنه
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/wp-bridge.php';

echo "<h2>🚀 بهینه‌سازی دیتابیس وردپرس</h2>";
echo "<hr>";

$wp_bridge = MN_WP_Bridge::get_instance();
$wpdb = $wp_bridge->wpdb;

$indexes_added = 0;
$indexes_existed = 0;

// لیست INDEX های مورد نیاز
$indexes = [
    [
        'table' => $wpdb->posts,
        'name' => 'idx_type_status',
        'columns' => '(post_type, post_status, ID)',
        'query' => "CREATE INDEX idx_type_status ON {$wpdb->posts} (post_type, post_status, ID)"
    ],
    [
        'table' => $wpdb->postmeta,
        'name' => 'idx_meta_key_value',
        'columns' => '(meta_key, meta_value(50))',
        'query' => "CREATE INDEX idx_meta_key_value ON {$wpdb->postmeta} (meta_key, meta_value(50))"
    ],
    [
        'table' => $wpdb->postmeta,
        'name' => 'idx_post_meta_key',
        'columns' => '(post_id, meta_key)',
        'query' => "CREATE INDEX idx_post_meta_key ON {$wpdb->postmeta} (post_id, meta_key)"
    ]
];

foreach ($indexes as $index) {
    echo "<h3>بررسی INDEX: {$index['name']}</h3>";
    
    // بررسی وجود INDEX
    $check_query = "SHOW INDEX FROM {$index['table']} WHERE Key_name = '{$index['name']}'";
    $exists = $wp_bridge->get_results($check_query);
    
    if (count($exists) > 0) {
        echo "✓ INDEX '{$index['name']}' قبلاً وجود دارد<br>";
        $indexes_existed++;
    } else {
        try {
            $wp_bridge->query($index['query']);
            echo "✅ INDEX '{$index['name']}' با موفقیت اضافه شد<br>";
            echo "   جدول: {$index['table']}<br>";
            echo "   ستون‌ها: {$index['columns']}<br>";
            $indexes_added++;
        } catch (Exception $e) {
            echo "❌ خطا در ایجاد INDEX: " . $e->getMessage() . "<br>";
        }
    }
    
    echo "<br>";
}

echo "<hr>";
echo "<h3>خلاصه:</h3>";
echo "INDEX های جدید اضافه شده: <strong>{$indexes_added}</strong><br>";
echo "INDEX های موجود: <strong>{$indexes_existed}</strong><br>";
echo "<br>";

if ($indexes_added > 0) {
    echo "<p style='color: green; font-weight: bold;'>✅ بهینه‌سازی انجام شد! حالا سرعت جستجو باید خیلی سریع‌تر باشه.</p>";
} else {
    echo "<p style='color: orange;'>⚠️ همه INDEX ها قبلاً وجود داشتند.</p>";
}

echo "<hr>";
echo "<h3>تست سرعت:</h3>";

// تست سرعت قبل و بعد
$start = microtime(true);

$test_query = "
    SELECT COUNT(*) 
    FROM {$wpdb->posts} p
    INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
    WHERE p.post_type = 'product' 
    AND p.post_status = 'publish'
    AND pm.meta_key = '_stock_status'
    AND pm.meta_value = 'instock'
";

$count = $wp_bridge->get_var($test_query);
$duration = round((microtime(true) - $start) * 1000, 2);

echo "کوئری تست: شمارش محصولات موجود<br>";
echo "تعداد: <strong>{$count}</strong> محصول<br>";
echo "زمان اجرا: <strong>{$duration} ms</strong><br>";

if ($duration < 100) {
    echo "<p style='color: green;'>✅ سرعت عالی!</p>";
} elseif ($duration < 500) {
    echo "<p style='color: orange;'>⚠️ سرعت متوسط</p>";
} else {
    echo "<p style='color: red;'>❌ سرعت ضعیف - ممکنه جدول خیلی بزرگ باشه</p>";
}

echo "<hr>";
echo "<p><a href='ajax/product-search.php?q=test'>تست جستجوی محصول</a></p>";
?>
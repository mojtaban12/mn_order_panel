<?php
/**
 * فایل دیباگ برای تست جستجوی محصولات
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>تست اتصال و جستجوی محصولات</h2>";
echo "<hr>";

// 1. تست اتصال به دیتابیس پنل
echo "<h3>1. تست دیتابیس پنل:</h3>";
try {
    require_once __DIR__ . '/config/database.php';
    $db = MN_Database::get_instance();
    echo "✅ اتصال به دیتابیس پنل موفق<br>";
} catch (Exception $e) {
    echo "❌ خطا: " . $e->getMessage() . "<br>";
    die();
}

// 2. تست تنظیمات
echo "<h3>2. تست تنظیمات:</h3>";
try {
    require_once __DIR__ . '/config/settings.php';
    $settings = MN_Settings::get_all();
    echo "✅ تنظیمات بارگذاری شد<br>";
    echo "تعداد تنظیمات: " . count($settings) . "<br>";
    echo "wp_db_name: " . ($settings['wp_db_name'] ?? 'تنظیم نشده') . "<br>";
    echo "wp_db_prefix: " . ($settings['wp_db_prefix'] ?? 'تنظیم نشده') . "<br>";
} catch (Exception $e) {
    echo "❌ خطا: " . $e->getMessage() . "<br>";
}

// 3. تست اتصال به دیتابیس وردپرس
echo "<h3>3. تست اتصال به وردپرس:</h3>";
try {
    require_once __DIR__ . '/config/wp-bridge.php';
    $wp_bridge = MN_WP_Bridge::get_instance();
    echo "✅ اتصال به دیتابیس وردپرس موفق<br>";
    
    $wpdb = $wp_bridge->wpdb;
    echo "Prefix: " . $wpdb->prefix . "<br>";
    echo "Posts table: " . $wpdb->posts . "<br>";
    
} catch (Exception $e) {
    echo "❌ خطا: " . $e->getMessage() . "<br>";
    die();
}

// 4. تست شمارش محصولات
echo "<h3>4. تست شمارش محصولات:</h3>";
try {
    $count_query = "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'";
    $product_count = $wp_bridge->get_var($count_query);
    echo "✅ تعداد محصولات منتشر شده: <strong>{$product_count}</strong><br>";
    
    if ($product_count == 0) {
        echo "⚠️ هیچ محصولی یافت نشد! لطفا در وردپرس محصول بسازید.<br>";
    }
} catch (Exception $e) {
    echo "❌ خطا: " . $e->getMessage() . "<br>";
}

// 5. تست جستجوی ساده
echo "<h3>5. تست جستجوی ساده (10 محصول اول):</h3>";
try {
    $simple_query = "
        SELECT 
            p.ID,
            p.post_title
        FROM {$wpdb->posts} p
        WHERE p.post_type = 'product' 
        AND p.post_status = 'publish'
        LIMIT 10
    ";
    
    $products = $wp_bridge->get_results($simple_query);
    echo "✅ تعداد نتایج: " . count($products) . "<br>";
    
    if (count($products) > 0) {
        echo "<ul>";
        foreach ($products as $p) {
            echo "<li>ID: {$p->ID} - {$p->post_title}</li>";
        }
        echo "</ul>";
    }
} catch (Exception $e) {
    echo "❌ خطا: " . $e->getMessage() . "<br>";
}

// 6. تست جستجوی کامل با قیمت
echo "<h3>6. تست جستجوی کامل با قیمت:</h3>";
try {
    $full_query = "
        SELECT 
            p.ID as product_id,
            p.post_title as name,
            pm_price.meta_value as price,
            pm_sku.meta_value as sku,
            pm_stock_status.meta_value as stock_status
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm_price 
            ON p.ID = pm_price.post_id AND pm_price.meta_key = '_price'
        LEFT JOIN {$wpdb->postmeta} pm_sku 
            ON p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'
        LEFT JOIN {$wpdb->postmeta} pm_stock_status 
            ON p.ID = pm_stock_status.post_id AND pm_stock_status.meta_key = '_stock_status'
        WHERE 
            p.post_type = 'product'
            AND p.post_status = 'publish'
        GROUP BY p.ID
        LIMIT 5
    ";
    
    $products = $wp_bridge->get_results($full_query);
    echo "✅ تعداد نتایج: " . count($products) . "<br>";
    
    if (count($products) > 0) {
        echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
        echo "<tr><th>ID</th><th>نام</th><th>SKU</th><th>قیمت</th><th>موجودی</th></tr>";
        foreach ($products as $p) {
            echo "<tr>";
            echo "<td>{$p->product_id}</td>";
            echo "<td>{$p->name}</td>";
            echo "<td>" . ($p->sku ?: '-') . "</td>";
            echo "<td>" . ($p->price ?: '0') . "</td>";
            echo "<td>" . ($p->stock_status ?: 'unknown') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "❌ خطا: " . $e->getMessage() . "<br>";
    echo "پیام کامل: <pre>" . print_r($e, true) . "</pre>";
}

// 7. تست متد prepare
echo "<h3>7. تست متد prepare:</h3>";
try {
    $search_term = "test";
    $term_like = '%' . $wp_bridge->esc_like($search_term) . '%';
    
    $prepared_query = $wp_bridge->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_title LIKE %s LIMIT 1",
        $term_like
    );
    
    echo "✅ کوئری آماده شد:<br>";
    echo "<pre>" . htmlspecialchars($prepared_query) . "</pre>";
    
    $result = $wp_bridge->get_var($prepared_query);
    echo "نتیجه: " . ($result ? "ID: $result" : "نتیجه‌ای یافت نشد") . "<br>";
    
} catch (Exception $e) {
    echo "❌ خطا در prepare: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<h3>✅ تست تمام شد!</h3>";
echo "<p><a href='ajax/product-search.php?q=test'>تست Ajax Search</a></p>";
?>
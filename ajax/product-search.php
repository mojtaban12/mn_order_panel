<?php
/**
 * MN Order Panel - Product Search Ajax Handler
 * جستجوی محصولات از دیتابیس وردپرس
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['q'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'پارامتر جستجو الزامی است'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../config/wp-bridge.php';
require_once __DIR__ . '/../config/settings.php';

try {
    $search_term = trim($_GET['q']);
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $per_page = 30;
    
    if (mb_strlen($search_term) < 2) {
        echo json_encode([
            'success' => true,
            'items' => [],
            'pagination' => ['more' => false],
            'message' => 'حداقل 2 کاراکتر وارد کنید'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $offset = ($page - 1) * $per_page;
    
    $wp_bridge = MN_WP_Bridge::get_instance();
    $wpdb = $wp_bridge->wpdb;
    
    $term_like = '%' . $wp_bridge->esc_like($search_term) . '%';
    $search_int = intval($search_term);
    
    $query = "
        SELECT DISTINCT
            p.ID as product_id,
            p.post_title as name
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm_stock 
            ON p.ID = pm_stock.post_id 
            AND pm_stock.meta_key = '_stock_status' 
            AND pm_stock.meta_value = 'instock'
        WHERE 
            p.post_type = 'product'
            AND p.post_status = 'publish'
            AND (
                p.post_title LIKE ?
                OR p.ID IN (
                    SELECT post_id FROM {$wpdb->postmeta} 
                    WHERE meta_key = '_sku' AND meta_value LIKE ?
                )
                OR p.ID = ?
            )
        ORDER BY p.ID DESC
        LIMIT ?
    ";
    
    $products = $wp_bridge->get_results($query, [
        $term_like,
        $term_like,
        $search_int,
        $per_page + 1
    ]);
    
    $has_more = count($products) > $per_page;
    if ($has_more) {
        array_pop($products);
    }
    
    $items = [];
    if (count($products) > 0) {
        $product_ids = array_map(function($p) { return $p->product_id; }, $products);
        $ids_placeholder = implode(',', $product_ids);
        
        // اضافه کردن _weight به meta_key ها
        $meta_query = "
            SELECT 
                post_id,
                meta_key,
                meta_value
            FROM {$wpdb->postmeta}
            WHERE post_id IN ({$ids_placeholder})
            AND meta_key IN ('_price', '_regular_price', '_sale_price', '_stock', '_sku', '_thumbnail_id', '_weight')
        ";
        
        $meta_results = $wp_bridge->get_results($meta_query);
        
        $meta_data = [];
        foreach ($meta_results as $meta) {
            $meta_data[$meta->post_id][$meta->meta_key] = $meta->meta_value;
        }
        
        foreach ($products as $product) {
            $pid = $product->product_id;
            $meta = $meta_data[$pid] ?? [];
            
            $price = floatval($meta['_price'] ?? 0);
            $weight = floatval($meta['_weight'] ?? 0);
            
            $items[] = [
                'id' => intval($pid),
                'text' => $product->name,
                'sku' => $meta['_sku'] ?? '-',
                'price' => $price,
                'price_formatted' => number_format($price),
                'regular_price' => floatval($meta['_regular_price'] ?? 0),
                'sale_price' => floatval($meta['_sale_price'] ?? 0),
                'stock_quantity' => $meta['_stock'] ?? null,
                'stock_status' => 'instock',
                'stock_text' => get_stock_text('instock', $meta['_stock'] ?? null),
                'in_stock' => true,
                'weight' => $weight,
                'image' => !empty($meta['_thumbnail_id']) 
                    ? get_product_image_url($meta['_thumbnail_id'], $wp_bridge, $wp_uploads_url)
                    : 'assets/img/no-image.png'
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'items' => $items,
        'pagination' => [
            'more' => $has_more,
            'page' => $page
        ],
        'total_found' => count($items)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطا در جستجو',
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_UNESCAPED_UNICODE);
    
    error_log('Product search error: ' . $e->getMessage());
}

function get_product_image_url($thumbnail_id, $wp_bridge, $uploads_url) {
    if (!$thumbnail_id) {
        return 'assets/img/no-image.png';
    }
    
    try {
        $wpdb = $wp_bridge->wpdb;
        $query = "SELECT meta_value FROM {$wpdb->postmeta} 
                  WHERE post_id = ? AND meta_key = '_wp_attached_file'";
        
        $file_path = $wp_bridge->get_var($query, [$thumbnail_id]);
        
        if ($file_path) {
            return rtrim($uploads_url, '/') . '/' . $file_path;
        }
    } catch (Exception $e) {
        error_log('Image URL error: ' . $e->getMessage());
    }
    
    return 'assets/img/no-image.png';
}

function get_stock_text($stock_status, $stock_quantity) {
    if ($stock_status !== 'instock') {
        return '❌ ناموجود';
    }
    
    if ($stock_quantity === null || $stock_quantity === '') {
        return '✓ موجود';
    }
    
    $qty = intval($stock_quantity);
    
    if ($qty === 0) {
        return '❌ ناموجود';
    } elseif ($qty < 5) {
        return "⚠️ {$qty} عدد باقی‌مانده";
    } else {
        return "✓ {$qty} عدد موجود";
    }
}
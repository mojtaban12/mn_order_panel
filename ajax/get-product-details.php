<?php
/**
 * MN Order Panel - Get Product Details
 * دریافت اطلاعات کامل یک محصول
 */

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'شناسه محصول الزامی است'
    ]);
    exit;
}

require_once __DIR__ . '/../config/wp-bridge.php';
require_once __DIR__ . '/../config/settings.php';

try {
    $product_id = intval($_GET['id']);
    
    if ($product_id <= 0) {
        throw new Exception('شناسه محصول نامعتبر است');
    }
    
    // اتصال به دیتابیس وردپرس
    $wp_bridge = MN_WP_Bridge::get_instance();
    $product = $wp_bridge->get_product($product_id);
    
    if (!$product) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'محصول یافت نشد'
        ]);
        exit;
    }
    
    // URL تصویر
    $wp_uploads_url = MN_Settings::get('wp_uploads_url', '');
    $image_url = get_product_image_url($product->thumbnail_id, $wp_bridge->wpdb, $wp_uploads_url);
    
    // گالری تصاویر
    $gallery_images = get_product_gallery($product_id, $wp_bridge->wpdb, $wp_uploads_url);
    
    // وضعیت موجودی
    $stock_text = get_stock_text($product->stock_status, $product->stock_quantity);
    $in_stock = $product->stock_status === 'instock';
    
    // قیمت‌ها
    $price = floatval($product->price);
    $regular_price = floatval($product->regular_price ?: $price);
    $sale_price = floatval($product->sale_price ?: 0);
    
    $has_discount = $sale_price > 0 && $sale_price < $regular_price;
    $discount_percent = 0;
    if ($has_discount) {
        $discount_percent = round((($regular_price - $sale_price) / $regular_price) * 100);
    }
    
    // خروجی
    echo json_encode([
        'success' => true,
        'product' => [
            'id' => intval($product->product_id),
            'name' => $product->name,
            'sku' => $product->sku ?: '-',
            'description' => $product->description ?: '',
            'short_description' => $product->short_description ?: '',
            'price' => $price,
            'price_formatted' => number_format($price),
            'regular_price' => $regular_price,
            'regular_price_formatted' => number_format($regular_price),
            'sale_price' => $sale_price,
            'sale_price_formatted' => $sale_price > 0 ? number_format($sale_price) : null,
            'has_discount' => $has_discount,
            'discount_percent' => $discount_percent,
            'stock_quantity' => $product->stock_quantity ?: null,
            'stock_status' => $product->stock_status,
            'stock_text' => $stock_text,
            'in_stock' => $in_stock,
            'image' => $image_url,
            'gallery' => $gallery_images,
            'weight' => $product->weight ?: null,
            'dimensions' => [
                'length' => $product->length ?: null,
                'width' => $product->width ?: null,
                'height' => $product->height ?: null
            ]
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطا در دریافت اطلاعات محصول',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    
    error_log('Get product details error: ' . $e->getMessage());
}

/**
 * دریافت URL تصویر محصول
 */
function get_product_image_url($thumbnail_id, $wpdb, $uploads_url) {
    if (!$thumbnail_id) {
        return 'assets/img/no-image.png';
    }
    
    $query = "SELECT meta_value FROM {$wpdb->postmeta} 
              WHERE post_id = ? AND meta_key = '_wp_attached_file'";
    
    $file_path = $wpdb->get_var($wpdb->prepare($query, [$thumbnail_id]));
    
    if ($file_path) {
        return rtrim($uploads_url, '/') . '/' . $file_path;
    }
    
    return 'assets/img/no-image.png';
}

/**
 * دریافت گالری تصاویر محصول
 */
function get_product_gallery($product_id, $wpdb, $uploads_url) {
    $query = "SELECT meta_value FROM {$wpdb->postmeta} 
              WHERE post_id = ? AND meta_key = '_product_image_gallery'";
    
    $gallery_ids = $wpdb->get_var($wpdb->prepare($query, [$product_id]));
    
    if (!$gallery_ids) {
        return [];
    }
    
    $ids = explode(',', $gallery_ids);
    $images = [];
    
    foreach ($ids as $id) {
        $id = intval($id);
        if ($id > 0) {
            $url = get_product_image_url($id, $wpdb, $uploads_url);
            if ($url !== 'assets/img/no-image.png') {
                $images[] = $url;
            }
        }
    }
    
    return $images;
}

/**
 * متن وضعیت موجودی
 */
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
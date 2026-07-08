<?php
/**
 * MN Order Panel - Sync محصولات "موجود در سایت" با وردپرس
 * فقط محصولاتی که sku آنها با P- شروع میشه و هنوز wp_product_id ندارن
 *
 * GET  ?action=pending&limit=N  → لیست محصولات منتظر سینک
 * POST ?action=pull             → { product_ids:[...] } — pull اطلاعات از WC
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/wp-bridge.php';
require_once __DIR__ . '/../config/settings.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = trim($_GET['action'] ?? '');

try {
    $db = MN_Database::get_instance();

    // ── GET: لیست منتظر سینک ────────────────
    if ($method === 'GET' && $action === 'pending') {
        $limit = max(1, min(500, intval($_GET['limit'] ?? 50)));

        $items = $db->get_results("
            SELECT id, title, sku
            FROM mn_products
            WHERE sku LIKE 'P-%' AND wp_product_id IS NULL
            ORDER BY id ASC
            LIMIT ?
        ", [$limit]);

        $total = (int) $db->get_var("
            SELECT COUNT(*) FROM mn_products
            WHERE sku LIKE 'P-%' AND wp_product_id IS NULL
        ");

        echo json_encode(['success' => true, 'items' => $items, 'remaining' => $total], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── POST: pull اطلاعات از WC ────────────
    if ($method === 'POST' && $action === 'pull') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $ids   = array_map('intval', $input['product_ids'] ?? []);
        if (empty($ids)) throw new Exception('لیست محصولات خالی است');

        $wp_bridge   = MN_WP_Bridge::get_instance();
        $uploads_url = rtrim(MN_Settings::get('wp_uploads_url', ''), '/');

        $done = 0; $notfound = 0; $errors = 0; $results = [];

        foreach ($ids as $id) {
            try {
                $product = $db->get_row("SELECT id, sku FROM mn_products WHERE id = ?", [$id]);
                if (!$product) { $errors++; continue; }

                $wp_product = $wp_bridge->get_product_by_sku($product->sku);

                if (!$wp_product) {
                    $notfound++;
                    $results[] = ['id' => $id, 'success' => false, 'message' => 'در وردپرس یافت نشد'];
                    continue;
                }

                // تصویر اصلی
                $image_url = null;
                if (!empty($wp_product->thumbnail_id)) {
                    $img = pull_image_url($wp_bridge, $wp_product->thumbnail_id, $uploads_url);
                    if ($img) $image_url = $img;
                }

                $update = [
                    'wp_product_id' => intval($wp_product->product_id),
                    'regular_price' => floatval($wp_product->regular_price ?: $wp_product->price),
                    'sale_price'    => !empty($wp_product->sale_price) ? floatval($wp_product->sale_price) : null,
                    'stock_status'  => $wp_product->stock_status ?: 'instock',
                    'weight'        => !empty($wp_product->weight) ? round(floatval($wp_product->weight) * 1000) : null,
                    'length'        => !empty($wp_product->length) ? floatval($wp_product->length) : null,
                    'width'         => !empty($wp_product->width)  ? floatval($wp_product->width)  : null,
                    'height'        => !empty($wp_product->height) ? floatval($wp_product->height) : null,
                    'is_synced'     => 1,
                    'synced_at'     => date('Y-m-d H:i:s'),
                ];
                if ($image_url) $update['image_url'] = $image_url;

                // موجودی واقعی وردپرس رو دست نمی‌زنیم (چون موجودی واقعی پنل مستقل مدیریت میشه)
                if (isset($wp_product->stock_quantity) && $wp_product->stock_quantity !== null) {
                    $update['stock_quantity'] = intval($wp_product->stock_quantity);
                }

                $db->update('mn_products', $update, ['id' => $id]);

                $done++;
                $results[] = ['id' => $id, 'success' => true, 'wp_product_id' => intval($wp_product->product_id)];

            } catch (Exception $e) {
                $errors++;
                $results[] = ['id' => $id, 'success' => false, 'message' => $e->getMessage()];
                error_log('sync-existing-products pull error: ' . $e->getMessage());
            }
        }

        echo json_encode([
            'success'  => true,
            'done'     => $done,
            'notfound' => $notfound,
            'errors'   => $errors,
            'results'  => $results,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    throw new Exception('درخواست نامعتبر است');

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    error_log('sync-existing-products: ' . $e->getMessage());
}

function pull_image_url($wp_bridge, $thumbnail_id, $uploads_url) {
    $wpdb      = $wp_bridge->wpdb;
    $file_path = $wp_bridge->get_var($wp_bridge->prepare(
        "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_wp_attached_file'",
        $thumbnail_id
    ));
    if (!$file_path) return null;
    return rtrim($uploads_url, '/') . '/' . ltrim($file_path, '/');
}
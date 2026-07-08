<?php
/**
 * MN Order Panel - Batch Sync محصولات با سایت
 *
 * GET  ?action=stats            → آمار کلی (کل / منتظر / سینک‌شده امروز)
 * GET  ?action=list&page=..     → لیست محصولات دارای sku برای انتخاب دستی
 * POST ?action=process          → { mode: 'selected'|'all', product_ids?, batch_size }
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

    // ── آمار ─────────────────────────────────
    if ($method === 'GET' && $action === 'stats') {
        $total = (int) $db->get_var(
            "SELECT COUNT(*) FROM mn_products WHERE sku IS NOT NULL AND sku != ''"
        );
        $pending = (int) $db->get_var(
            "SELECT COUNT(*) FROM mn_products
             WHERE sku IS NOT NULL AND sku != ''
               AND (synced_at IS NULL OR DATE(synced_at) <> CURDATE())"
        );
        $done_today = $total - $pending;

        echo json_encode([
            'success' => true,
            'total'   => $total,
            'pending' => $pending,
            'done_today' => $done_today,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── لیست برای انتخاب دستی ────────────────
    if ($method === 'GET' && $action === 'list') {
        $page     = max(1, intval($_GET['page'] ?? 1));
        $per_page = max(1, min(100, intval($_GET['per_page'] ?? 30)));
        $search   = trim($_GET['search'] ?? '');
        $offset   = ($page - 1) * $per_page;

        $where  = ["sku IS NOT NULL", "sku != ''"];
        $params = [];
        if ($search !== '') {
            $where[]  = "(title LIKE ? OR sku LIKE ?)";
            $like     = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
        }
        $where_sql = implode(' AND ', $where);

        $total = (int) $db->get_var("SELECT COUNT(*) FROM mn_products WHERE {$where_sql}", $params);

        $list_params = array_merge($params, [$per_page, $offset]);
        $items = $db->get_results("
            SELECT id, title, sku, wp_product_id, is_synced, synced_at,
                   stock_quantity, real_stock_quantity, regular_price
            FROM mn_products
            WHERE {$where_sql}
            ORDER BY id ASC
            LIMIT ? OFFSET ?
        ", $list_params);

        echo json_encode([
            'success' => true,
            'items'   => $items,
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $per_page,
                'total'        => $total,
                'total_pages'  => $total > 0 ? (int) ceil($total / $per_page) : 1,
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── پردازش یک batch ──────────────────────
    if ($method === 'POST' && $action === 'process') {
        $input      = json_decode(file_get_contents('php://input'), true) ?? [];
        $mode       = ($input['mode'] ?? 'all') === 'selected' ? 'selected' : 'all';
        $batch_size = max(1, min(50, intval($input['batch_size'] ?? 10)));

        $wp_bridge   = MN_WP_Bridge::get_instance();
        $uploads_url = rtrim(MN_Settings::get('wp_uploads_url', ''), '/');

        if ($mode === 'selected') {
            $ids = array_values(array_unique(array_map('intval', $input['product_ids'] ?? [])));
            $ids = array_slice($ids, 0, $batch_size);

            if (empty($ids)) {
                echo json_encode(['success' => true, 'processed' => 0, 'results' => [], 'done' => true], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $in_clause = implode(',', $ids);
            $rows = $db->get_results("SELECT * FROM mn_products WHERE id IN ({$in_clause})");

        } else {
            $rows = $db->get_results("
                SELECT * FROM mn_products
                WHERE sku IS NOT NULL AND sku != ''
                  AND (synced_at IS NULL OR DATE(synced_at) <> CURDATE())
                ORDER BY id ASC
                LIMIT ?
            ", [$batch_size]);
        }

        $results = [];
        foreach ($rows as $p) {
            $results[] = process_one_product($db, $wp_bridge, $uploads_url, $p);
        }

        $remaining = null;
        if ($mode === 'all') {
            $remaining = (int) $db->get_var("
                SELECT COUNT(*) FROM mn_products
                WHERE sku IS NOT NULL AND sku != ''
                  AND (synced_at IS NULL OR DATE(synced_at) <> CURDATE())
            ");
        }

        echo json_encode([
            'success'   => true,
            'processed' => count($results),
            'results'   => $results,
            'remaining' => $remaining,
            'done'      => empty($rows),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    throw new Exception('درخواست نامعتبر است');

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    error_log('sync-products-batch: ' . $e->getMessage());
}

// ════════════════════════════════════════════
// توابع پردازش (بدون wp-load — فقط MN_WP_Bridge)
// ════════════════════════════════════════════

function process_one_product($db, $wp, $uploads_url, $p) {
    try {
        // ── حالت ۱: قبلاً کامل سینک شده → فقط قیمت/موجودی ──
        if (!empty($p->wp_product_id) && intval($p->is_synced) === 1) {
            return push_price_stock($db, $wp, $p);
        }

        // ── حالت ۲: هنوز سینک نشده، SKU دارد → pull کامل ──
        if (!empty($p->sku)) {
            return pull_full_from_wp($db, $wp, $uploads_url, $p);
        }

        // ── بدون sku و بدون wp_product_id — کاری نمیشه کرد ──
        $db->update('mn_products', ['synced_at' => date('Y-m-d H:i:s')], ['id' => $p->id]);
        return ['id' => $p->id, 'title' => $p->title, 'success' => false, 'message' => 'SKU ندارد'];

    } catch (Exception $e) {
        // در هر صورت synced_at رو بزن تا در همین run دوباره پردازش نشه (بی‌نهایت لوپ نشه)
        $db->update('mn_products', ['synced_at' => date('Y-m-d H:i:s')], ['id' => $p->id]);
        return ['id' => $p->id, 'title' => $p->title, 'success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * حالت ۳ — فقط قیمت و موجودی رو با postmeta مستقیم push می‌کنیم (سبک‌ترین حالت ممکن)
 */
function push_price_stock($db, $wp, $p) {
    $wp_id = intval($p->wp_product_id);

    $sale    = (!empty($p->sale_price) && floatval($p->sale_price) > 0) ? floatval($p->sale_price) : null;
    $regular = floatval($p->regular_price);
    $price   = $sale ?: $regular;

    $wp->upsert_postmeta($wp_id, '_regular_price', (string) $regular);
    $wp->upsert_postmeta($wp_id, '_sale_price', $sale !== null ? (string) $sale : '');
    $wp->upsert_postmeta($wp_id, '_price', (string) $price);

    if (intval($p->manage_stock) === 1) {
        $qty = intval($p->stock_quantity) + intval($p->real_stock_quantity);
        $wp->upsert_postmeta($wp_id, '_stock', (string) $qty);
        $wp->upsert_postmeta($wp_id, '_stock_status', $qty > 0 ? 'instock' : 'outofstock');
    }

    $db->update('mn_products', ['synced_at' => date('Y-m-d H:i:s')], ['id' => $p->id]);

    return ['id' => $p->id, 'title' => $p->title, 'success' => true, 'mode' => 'price_stock'];
}

/**
 * حالت ۴ — SKU (به‌فرم P-{id}) رو در وردپرس پیدا می‌کنیم و اطلاعات کامل رو pull می‌کنیم
 */
function pull_full_from_wp($db, $wp, $uploads_url, $p) {
    $search_sku = normalize_wp_sku($p->sku);
    $wp_id      = $wp->find_id_by_sku($search_sku);

    if (!$wp_id) {
        $db->update('mn_products', ['synced_at' => date('Y-m-d H:i:s')], ['id' => $p->id]);
        return ['id' => $p->id, 'title' => $p->title, 'success' => false, 'message' => 'در وردپرس یافت نشد (' . $search_sku . ')'];
    }

    $product = $wp->get_product($wp_id);
    if (!$product) {
        $db->update('mn_products', ['synced_at' => date('Y-m-d H:i:s')], ['id' => $p->id]);
        return ['id' => $p->id, 'title' => $p->title, 'success' => false, 'message' => 'اطلاعات محصول ناقص است'];
    }

    // تصویر اصلی — بدون دانلود، فقط لینک مستقیم (سبک برای هزاران محصول)
    $image_url = null;
    if (!empty($product->thumbnail_id)) {
        $file = $wp->get_var($wp->prepare(
            "SELECT meta_value FROM {$wp->wpdb->postmeta} WHERE post_id = %d AND meta_key = '_wp_attached_file' LIMIT 1",
            $product->thumbnail_id
        ));
        if ($file) $image_url = rtrim($uploads_url, '/') . '/' . ltrim($file, '/');
    }

    $update = [
        'wp_product_id' => $wp_id,
        'regular_price' => floatval($product->regular_price ?: $product->price),
        'sale_price'    => !empty($product->sale_price) ? floatval($product->sale_price) : null,
        'stock_status'  => $product->stock_status ?: 'instock',
        'weight'        => !empty($product->weight) ? round(floatval($product->weight) * 1000) : null,
        'length'        => !empty($product->length) ? floatval($product->length) : null,
        'width'         => !empty($product->width)  ? floatval($product->width)  : null,
        'height'        => !empty($product->height) ? floatval($product->height) : null,
        'is_synced'     => 1,
        'synced_at'     => date('Y-m-d H:i:s'),
    ];
    if ($image_url) $update['image_url'] = $image_url;

    $db->update('mn_products', $update, ['id' => $p->id]);

    if ($image_url) {
        $exists = $db->get_var(
            "SELECT COUNT(*) FROM mn_product_images WHERE product_id = ? AND image_url = ?",
            [$p->id, $image_url]
        );
        if (!$exists) {
            $db->insert('mn_product_images', [
                'product_id' => $p->id,
                'image_url'  => $image_url,
                'is_primary' => 1,
                'sort_order' => 0,
            ]);
        }
    }

    // ── ویژگی‌ها ──────────────────────────────
    $attrs = $wp->get_results($wp->prepare("
        SELECT t.term_id, t.name AS term_name, tt.taxonomy,
               COALESCE(wat.attribute_label, REPLACE(tt.taxonomy, 'pa_', '')) AS attr_label
        FROM {$wp->wpdb->term_relationships} tr
        INNER JOIN {$wp->wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
        INNER JOIN {$wp->wpdb->terms} t ON tt.term_id = t.term_id
        LEFT JOIN {$wp->wpdb->prefix}woocommerce_attribute_taxonomies wat
            ON wat.attribute_name = REPLACE(tt.taxonomy, 'pa_', '')
        WHERE tr.object_id = %d AND tt.taxonomy LIKE 'pa_%'
    ", $wp_id));

    foreach ($attrs as $a) {
        $dup = $db->get_var(
            "SELECT id FROM mn_product_extra
             WHERE product_id = ? AND type = 'attribute' AND attribute_name = ? AND value = ?",
            [$p->id, $a->taxonomy, $a->term_name]
        );
        if (!$dup) {
            $db->insert('mn_product_extra', [
                'product_id'      => $p->id,
                'wp_product_id'   => $wp_id,
                'type'            => 'attribute',
                'attribute_name'  => $a->taxonomy,
                'attribute_label' => $a->attr_label,
                'term_id'         => intval($a->term_id),
                'value'           => $a->term_name,
            ]);
        }
    }

    return ['id' => $p->id, 'title' => $p->title, 'success' => true, 'mode' => 'full_pull', 'wp_product_id' => $wp_id];
}

function normalize_wp_sku($sku) {
    $sku = trim($sku);
    return (stripos($sku, 'P-') === 0) ? $sku : ('P-' . $sku);
}
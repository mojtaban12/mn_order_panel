<?php
/**
 * MN Order Panel - Search WordPress Products (for create-product form)
 * جستجوی محصولات از وردپرس برای پر کردن فرم ثبت محصول
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['q'])) {
    echo json_encode(['success' => false, 'items' => [], 'message' => 'پارامتر q الزامی است'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../config/wp-bridge.php';
require_once __DIR__ . '/../config/settings.php';

try {
    $q        = trim($_GET['q']);
    $page     = max(1, intval($_GET['page'] ?? 1));
    $per_page = 20;
    $offset   = ($page - 1) * $per_page;

    if (mb_strlen($q) < 2) {
        echo json_encode(['success' => true, 'items' => [], 'pagination' => ['more' => false]], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $wp_bridge   = MN_WP_Bridge::get_instance();
    $wpdb        = $wp_bridge->wpdb;
    $uploads_url = rtrim(MN_Settings::get('wp_uploads_url', ''), '/');
    $term_like   = '%' . $wp_bridge->esc_like($q) . '%';
    $search_int  = intval($q);

    // ── جستجوی محصولات ─────────────────────
    $products = $wp_bridge->get_results("
        SELECT DISTINCT p.ID AS product_id, p.post_title AS name
        FROM {$wpdb->posts} p
        WHERE p.post_type   = 'product'
          AND p.post_status IN ('publish','draft','private')
          AND (
              p.post_title LIKE ?
              OR p.ID = ?
              OR p.ID IN (
                  SELECT post_id FROM {$wpdb->postmeta}
                  WHERE meta_key = '_sku' AND meta_value LIKE ?
              )
          )
        ORDER BY p.post_date DESC
        LIMIT ?
    ", [$term_like, $search_int, $term_like, $per_page + 1]);

    $has_more = count($products) > $per_page;
    if ($has_more) array_pop($products);

    if (empty($products)) {
        echo json_encode(['success' => true, 'items' => [], 'pagination' => ['more' => false]], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── meta یکجا ───────────────────────────
    $ids_str = implode(',', array_map(function($p){ return intval($p->product_id); }, $products));

    $meta_results = $wp_bridge->get_results("
        SELECT post_id, meta_key, meta_value
        FROM {$wpdb->postmeta}
        WHERE post_id IN ({$ids_str})
          AND meta_key IN (
              '_sku','_regular_price','_sale_price',
              '_manage_stock','_stock','_stock_status',
              '_weight','_length','_width','_height',
              '_thumbnail_id'
          )
    ");

    $meta_data = [];
    foreach ($meta_results as $m) {
        $meta_data[$m->post_id][$m->meta_key] = $m->meta_value;
    }

    // ── thumbnail IDs → URLs ────────────────
    $thumb_ids = [];
    foreach ($products as $p) {
        $tid = $meta_data[$p->product_id]['_thumbnail_id'] ?? null;
        if ($tid) $thumb_ids[] = intval($tid);
    }
    $thumb_ids = array_unique($thumb_ids);

    $thumb_urls = [];
    if (!empty($thumb_ids)) {
        $tp    = implode(',', $thumb_ids);
        $files = $wp_bridge->get_results("
            SELECT post_id, meta_value
            FROM {$wpdb->postmeta}
            WHERE post_id IN ({$tp}) AND meta_key = '_wp_attached_file'
        ");
        foreach ($files as $f) {
            $thumb_urls[intval($f->post_id)] = $uploads_url . '/' . ltrim($f->meta_value, '/');
        }
    }

    // ── ساخت آیتم‌ها ────────────────────────

    // دریافت attributes همه محصولات — یک کوئری با JOIN مستقیم
    $attrs_map = [];
    if (!empty($ids_str)) {
        $attr_rows = $wp_bridge->get_results("
            SELECT
                tr.object_id                        AS product_id,
                t.term_id,
                t.name                              AS term_name,
                t.slug                              AS term_slug,
                tt.taxonomy,
                COALESCE(wat.attribute_label,
                    REPLACE(tt.taxonomy, 'pa_', '')) AS attr_label,
                COALESCE(wat.attribute_id, 0)       AS attribute_id,
                COALESCE(wat.attribute_name,
                    REPLACE(tt.taxonomy, 'pa_', '')) AS attribute_name
            FROM {$wpdb->term_relationships} tr
            INNER JOIN {$wpdb->term_taxonomy} tt
                ON tr.term_taxonomy_id = tt.term_taxonomy_id
            INNER JOIN {$wpdb->terms} t
                ON tt.term_id = t.term_id
            LEFT JOIN {$wpdb->prefix}woocommerce_attribute_taxonomies wat
                ON wat.attribute_name = REPLACE(tt.taxonomy, 'pa_', '')
            WHERE tr.object_id IN ({$ids_str})
              AND tt.taxonomy LIKE 'pa_%'
            ORDER BY tr.object_id, tt.taxonomy, t.name
        ");

        foreach ($attr_rows as $ar) {
            $pid_key = intval($ar->product_id);
            $tax     = $ar->taxonomy;
            if (!isset($attrs_map[$pid_key][$tax])) {
                $attrs_map[$pid_key][$tax] = [
                    'taxonomy'     => $tax,
                    'label'        => $ar->attr_label,
                    'attribute_id' => intval($ar->attribute_id),
                    'attribute_name' => $ar->attribute_name,
                    'terms'        => [],
                ];
            }
            $attrs_map[$pid_key][$tax]['terms'][] = [
                'term_id' => intval($ar->term_id),
                'name'    => $ar->term_name,
                'slug'    => $ar->term_slug,
            ];
        }
    }

    $items = [];
    foreach ($products as $product) {
        $pid      = intval($product->product_id);
        $meta     = $meta_data[$pid] ?? [];
        $thumb_id = isset($meta['_thumbnail_id']) ? intval($meta['_thumbnail_id']) : null;
        $img_url  = ($thumb_id && isset($thumb_urls[$thumb_id])) ? $thumb_urls[$thumb_id] : null;

        $reg_price  = floatval($meta['_regular_price'] ?? 0);
        $sale_price = floatval($meta['_sale_price']    ?? 0);
        $stock      = (isset($meta['_stock']) && $meta['_stock'] !== '') ? intval($meta['_stock']) : null;
        $manage     = ($meta['_manage_stock'] ?? '') === 'yes';
        $weight_g   = !empty($meta['_weight']) ? round(floatval($meta['_weight']) * 1000) : null;

        // آرایه ساده از ویژگی‌ها برای پر کردن فرم
        $attributes = [];
        if (!empty($attrs_map[$pid])) {
            foreach ($attrs_map[$pid] as $tax => $attr) {
                foreach ($attr['terms'] as $term) {
                    $attributes[] = [
                        'taxonomy'       => $tax,
                        'attribute_name' => $attr['attribute_name'],
                        'attribute_id'   => $attr['attribute_id'],
                        'label'          => $attr['label'],
                        'term_id'        => $term['term_id'],
                        'term_name'      => $term['name'],
                        'term_slug'      => $term['slug'],
                    ];
                }
            }
        }

        $items[] = [
            'wp_id'          => $pid,
            'title'          => $product->name,
            'sku'            => $meta['_sku'] ?? '',
            'regular_price'  => $reg_price,
            'sale_price'     => $sale_price > 0 ? $sale_price : null,
            'manage_stock'   => $manage,
            'stock_quantity' => $stock,
            'stock_status'   => $meta['_stock_status'] ?? 'instock',
            'weight'         => $weight_g,
            'length'         => !empty($meta['_length']) ? floatval($meta['_length']) : null,
            'width'          => !empty($meta['_width'])  ? floatval($meta['_width'])  : null,
            'height'         => !empty($meta['_height']) ? floatval($meta['_height']) : null,
            'thumbnail_id'   => $thumb_id,
            'image_url'      => $img_url,
            'attributes'     => $attributes,
            'label'          => $product->name
                                . (!empty($meta['_sku']) ? ' — ' . $meta['_sku'] : '')
                                . ($reg_price > 0 ? ' | ' . number_format($reg_price) : ''),
        ];
    }

    echo json_encode([
        'success'    => true,
        'items'      => $items,
        'pagination' => ['more' => $has_more, 'page' => $page],
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'file'    => basename($e->getFile()),
        'line'    => $e->getLine(),
    ], JSON_UNESCAPED_UNICODE);
    error_log('search-wp-products: ' . $e->getMessage() . ' — ' . $e->getFile() . ':' . $e->getLine());
}
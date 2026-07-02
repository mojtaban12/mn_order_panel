<?php
/**
 * DEBUG — بررسی دلیل عدم match سفارشات WC
 * بعد از debug حذف کن!
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/wp-bridge.php';

$db   = MN_Database::get_instance();
$wp   = MN_WP_Bridge::get_instance();
$wpdb = $wp->wpdb;

$last_order_id = intval(MN_Settings::get('wc_sales_last_order_id', 0));

// ── آخرین 5 سفارش WC (بدون فیلتر last_order_id) ──
$orders = $wp->get_results("
    SELECT
        p.ID            AS order_id,
        p.post_status   AS order_status,
        p.post_date     AS order_date
    FROM {$wpdb->posts} p
    WHERE p.post_type   = 'shop_order'
      AND p.post_status IN ('wc-completed','wc-processing','wc-on-hold')
    ORDER BY p.ID DESC
    LIMIT 5
");

$result = [
    'last_order_id_in_settings' => $last_order_id,
    'orders' => [],
    'panel_products_sample' => [],
];

foreach ($orders as $order) {
    $items = $wp->get_results("
        SELECT
            oi.order_item_id,
            oi.order_item_name AS product_name,
            MAX(CASE WHEN oim.meta_key = '_product_id'    THEN oim.meta_value END) AS wp_product_id,
            MAX(CASE WHEN oim.meta_key = '_qty'           THEN oim.meta_value END) AS qty
        FROM {$wpdb->prefix}woocommerce_order_items oi
        INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim
               ON oi.order_item_id = oim.order_item_id
        WHERE oi.order_id        = ?
          AND oi.order_item_type = 'line_item'
          AND oim.meta_key IN ('_product_id','_qty')
        GROUP BY oi.order_item_id
    ", [$order->order_id]);

    $items_detail = [];
    foreach ($items as $item) {
        $wp_pid = intval($item->wp_product_id);

        // چک پنل با wp_product_id
        $panel_by_wpid = $db->get_row(
            "SELECT id, title, sku, wp_product_id FROM mn_products WHERE wp_product_id = ? LIMIT 1",
            [$wp_pid]
        );

        // SKU محصول WC
        $wc_sku = $wp->get_var(
            "SELECT meta_value FROM {$wpdb->postmeta}
             WHERE post_id = ? AND meta_key = '_sku' LIMIT 1",
            [$wp_pid]
        );

        // چک پنل با SKU
        $panel_by_sku = $wc_sku
            ? $db->get_row("SELECT id, title, sku FROM mn_products WHERE sku = ? LIMIT 1", [$wc_sku])
            : null;

        $items_detail[] = [
            'order_item_id'   => $item->order_item_id,
            'product_name'    => $item->product_name,
            'wp_product_id'   => $wp_pid,
            'wc_sku'          => $wc_sku,
            'qty'             => $item->qty,
            'panel_by_wpid'   => $panel_by_wpid,
            'panel_by_sku'    => $panel_by_sku,
            'will_match'      => ($panel_by_wpid || $panel_by_sku) ? true : false,
            'skip_reason'     => !$panel_by_wpid && !$panel_by_sku
                                  ? 'محصول در پنل یافت نشد (نه wp_product_id نه SKU)'
                                  : null,
        ];
    }

    $result['orders'][] = [
        'order_id'     => $order->order_id,
        'status'       => $order->order_status,
        'date'         => $order->order_date,
        'will_process' => $order->order_id > $last_order_id ? true : false,
        'skip_reason'  => $order->order_id <= $last_order_id
                          ? 'order_id <= last_order_id در settings ('.$last_order_id.')'
                          : null,
        'items'        => $items_detail,
    ];
}

// نمونه محصولات پنل با wp_product_id
$result['panel_products_sample'] = $db->get_results(
    "SELECT id, title, sku, wp_product_id FROM mn_products
     WHERE wp_product_id IS NOT NULL
     ORDER BY id DESC LIMIT 10"
);

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
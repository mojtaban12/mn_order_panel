<?php
/**
 * MN Order Panel - Get Products List
 * دریافت لیست محصولات
 */

header('Content-Type: application/json; charset=utf-8');

// session_start();
// if (!isset($_SESSION['panel_user_id'])) {
//     http_response_code(401);
//     echo json_encode(['success' => false, 'message' => 'لطفاً وارد شوید']);
//     exit;
// }

require_once __DIR__ . '/../config/database.php';

try {
    $db = MN_Database::get_instance();

    // ── صفحه‌بندی ────────────────────────────
    $page     = isset($_GET['page'])     ? max(1, intval($_GET['page']))                  : 1;
    $per_page = isset($_GET['per_page']) ? max(1, min(100, intval($_GET['per_page'])))    : 20;
    $offset   = ($page - 1) * $per_page;

    // ── فیلترها ──────────────────────────────
    $search       = isset($_GET['search'])       ? trim($_GET['search'])       : '';
    $status       = isset($_GET['status'])       ? trim($_GET['status'])       : '';
    $stock_status = isset($_GET['stock_status']) ? trim($_GET['stock_status']) : '';

    // ── ساخت WHERE ───────────────────────────
    $where = ['1=1'];
    $params = [];

    if ($search !== '') {
        $like = '%' . $search . '%';
        $where[] = "(p.title LIKE ? OR p.sku LIKE ?)";
        $params[] = $like;
        $params[] = $like;
    }

    if ($status !== '') {
        $where[] = "p.status = ?";
        $params[] = $status;
    }

    if ($stock_status !== '') {
        $where[] = "p.stock_status = ?";
        $params[] = $stock_status;
    }

    $where_sql = implode(' AND ', $where);

    // ── شمارش ────────────────────────────────
    $total_row     = $db->get_row("SELECT COUNT(*) AS total FROM mn_products p WHERE {$where_sql}", $params);
    $total_products = (int) $total_row->total;
    $total_pages   = $total_products > 0 ? (int) ceil($total_products / $per_page) : 1;

    // ── دریافت داده ──────────────────────────
    $list_params   = array_merge($params, [$per_page, $offset]);
    $products      = $db->get_results("
        SELECT
            p.id,
            p.wp_product_id,
            p.title,
            p.sku,
            p.regular_price,
            p.sale_price,
            p.discount_percent,
            p.stock_quantity,
            p.real_stock_quantity,
            p.stock_status,
            p.weight,
            p.image_url,
            p.status,
            p.is_synced,
            p.created_at,
            p.updated_at,
            u.full_name AS created_by,
            (SELECT image_url FROM mn_product_images
             WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image,
            (SELECT COUNT(*) FROM mn_product_images
             WHERE product_id = p.id) AS image_count
        FROM mn_products p
        LEFT JOIN mn_panel_users u ON p.created_by = u.id
        WHERE {$where_sql}
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?
    ", $list_params);

    // ── آمار کلی ─────────────────────────────
    $stats = $db->get_row("
        SELECT
            COUNT(*) AS total,
            SUM(status = 'active')            AS active,
            SUM(status = 'inactive')          AS inactive,
            SUM(status = 'draft')             AS draft,
            SUM(stock_status = 'instock')     AS instock,
            SUM(stock_status = 'outofstock')  AS outofstock,
            SUM(is_synced = 1)                AS synced
        FROM mn_products
    ");

    echo json_encode([
        'success'  => true,
        'products' => $products,
        'pagination' => [
            'current_page' => $page,
            'per_page'     => $per_page,
            'total'        => $total_products,
            'total_pages'  => $total_pages,
            'from'         => $total_products > 0 ? $offset + 1 : 0,
            'to'           => min($offset + $per_page, $total_products),
        ],
        'stats' => [
            'total'       => (int) $stats->total,
            'active'      => (int) $stats->active,
            'inactive'    => (int) $stats->inactive,
            'draft'       => (int) $stats->draft,
            'instock'     => (int) $stats->instock,
            'outofstock'  => (int) $stats->outofstock,
            'synced'      => (int) $stats->synced,
        ],
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطا در دریافت محصولات',
        'error'   => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
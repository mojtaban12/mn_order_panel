<?php
/**
 * MN Order Panel - Get WC Sales List
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';

try {
    $db       = MN_Database::get_instance();
    $page     = max(1, intval($_GET['page']     ?? 1));
    $per_page = max(1, min(100, intval($_GET['per_page'] ?? 20)));
    $offset   = ($page - 1) * $per_page;

    $search    = trim($_GET['search']    ?? '');
    $status    = trim($_GET['status']    ?? '');
    $date_from = trim($_GET['date_from'] ?? '');
    $date_to   = trim($_GET['date_to']   ?? '');

    $where  = ['1=1'];
    $params = [];

    if ($search !== '') {
        $like     = '%' . $search . '%';
        $where[]  = "(s.product_title LIKE ? OR s.customer_name LIKE ? OR s.customer_phone LIKE ?)";
        $params[] = $like; $params[] = $like; $params[] = $like;
    }
    if ($status !== '') {
        $where[]  = "s.order_status = ?";
        $params[] = $status;
    }
    if ($date_from !== '') {
        $where[]  = "DATE(s.order_date) >= ?";
        $params[] = $date_from;
    }
    if ($date_to !== '') {
        $where[]  = "DATE(s.order_date) <= ?";
        $params[] = $date_to;
    }

    $where_sql = implode(' AND ', $where);

    // شمارش
    $total = (int) $db->get_var(
        "SELECT COUNT(*) FROM mn_wc_sales s WHERE {$where_sql}",
        $params
    );

    // داده
    $list_params   = array_merge($params, [$per_page, $offset]);
    $sales         = $db->get_results("
        SELECT s.*, p.title AS panel_product_title, p.panel_category_id
        FROM mn_wc_sales s
        LEFT JOIN mn_products p ON s.product_id = p.id
        WHERE {$where_sql}
        ORDER BY s.order_date DESC, s.wc_order_id DESC
        LIMIT ? OFFSET ?
    ", $list_params);

    // آمار کلی
    $stats = $db->get_row("
        SELECT
            COUNT(*)                          AS total,
            SUM(total_price)                  AS total_revenue,
            SUM(quantity)                     AS total_qty,
            SUM(stock_updated = 1)            AS stock_updated
        FROM mn_wc_sales
    ");

    echo json_encode([
        'success'    => true,
        'sales'      => $sales,
        'pagination' => [
            'current_page' => $page,
            'per_page'     => $per_page,
            'total'        => $total,
            'total_pages'  => $total > 0 ? (int)ceil($total / $per_page) : 1,
            'from'         => $total > 0 ? $offset + 1 : 0,
            'to'           => min($offset + $per_page, $total),
        ],
        'stats' => [
            'total'         => intval($stats->total),
            'total_revenue' => floatval($stats->total_revenue ?? 0),
            'total_qty'     => intval($stats->total_qty ?? 0),
            'stock_updated' => intval($stats->stock_updated ?? 0),
        ],
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

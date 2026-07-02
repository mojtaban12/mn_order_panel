<?php
/**
 * MN Order Panel - Get Orders List
 * دریافت لیست سفارشات
 */

header('Content-Type: application/json; charset=utf-8');

// session_start();

// // چک احراز هویت
// if (!isset($_SESSION['panel_user_id'])) {
//     http_response_code(401);
//     echo json_encode([
//         'success' => false,
//         'message' => 'لطفاً وارد شوید'
//     ]);
//     exit;
// }

require_once __DIR__ . '/../config/database.php';

try {
    $db = MN_Database::get_instance();
    
    // پارامترهای صفحه‌بندی
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = isset($_GET['per_page']) ? max(1, min(100, intval($_GET['per_page']))) : 20;
    $offset = ($page - 1) * $per_page;
    
    // فیلترها
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
    $date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
    
    // ساخت کوئری
    $where_conditions = ['1=1'];
    $params = [];
    
    // جستجو
    if (!empty($search)) {
        $where_conditions[] = "(
            o.id LIKE ? OR
            CONCAT(c.first_name, ' ', c.last_name) LIKE ? OR
            c.phone LIKE ?
        )";
        $search_param = '%' . $search . '%';
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    // فیلتر وضعیت
    if (!empty($status)) {
        $where_conditions[] = "o.status = ?";
        $params[] = $status;
    }
    
    // فیلتر تاریخ
    if (!empty($date_from)) {
        $where_conditions[] = "DATE(o.created_at) >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $where_conditions[] = "DATE(o.created_at) <= ?";
        $params[] = $date_to;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // شمارش کل رکوردها
    $count_query = "
        SELECT COUNT(*) as total
        FROM mn_orders o
        INNER JOIN mn_customers c ON o.customer_id = c.id
        WHERE {$where_clause}
    ";
    
    $total_result = $db->get_row($count_query, $params);
    $total_orders = $total_result->total;
    $total_pages = ceil($total_orders / $per_page);
    
    // دریافت سفارشات
    $query = "
        SELECT 
            o.id,
            o.customer_id,
            CONCAT(c.first_name, ' ', c.last_name) as customer_name,
            c.phone as customer_phone,
            o.total_amount,
            o.status,
            o.wc_order_id,
            o.order_notes,
            o.sync_attempts,
            o.last_sync_error,
            o.created_at,
            o.updated_at,
            o.synced_at,
            u.full_name as created_by
        FROM mn_orders o
        INNER JOIN mn_customers c ON o.customer_id = c.id
        LEFT JOIN mn_panel_users u ON o.panel_user_id = u.id
        WHERE {$where_clause}
        ORDER BY o.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $per_page;
    $params[] = $offset;
    
    $orders = $db->get_results($query, $params);
    
    // دریافت آمار
    $stats_query = "
        SELECT 
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'syncing' THEN 1 ELSE 0 END) as syncing,
            SUM(CASE WHEN status = 'synced' THEN 1 ELSE 0 END) as synced,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
        FROM mn_orders
    ";
    
    $stats = $db->get_row($stats_query);
    
    // پاسخ
    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $per_page,
            'total' => $total_orders,
            'total_pages' => $total_pages,
            'from' => $total_orders > 0 ? $offset + 1 : 0,
            'to' => min($offset + $per_page, $total_orders)
        ],
        'stats' => [
            'pending' => (int)$stats->pending,
            'syncing' => (int)$stats->syncing,
            'synced' => (int)$stats->synced,
            'failed' => (int)$stats->failed,
            'total' => $total_orders
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطا در دریافت سفارشات',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
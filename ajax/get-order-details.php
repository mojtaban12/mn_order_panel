<?php
/**
 * MN Order Panel - Get Order Details
 * دریافت جزئیات سفارش
 */

header('Content-Type: application/json; charset=utf-8');

session_start();

// چک احراز هویت - موقتاً غیرفعال
// TODO: بعداً فعال کن
// if (!isset($_SESSION['panel_user_id'])) {
//     http_response_code(401);
//     echo json_encode([
//         'success' => false,
//         'message' => 'لطفاً وارد شوید'
//     ]);
//     exit;
// }

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'شناسه سفارش الزامی است'
    ]);
    exit;
}

require_once __DIR__ . '/../config/database.php';

try {
    $db = MN_Database::get_instance();
    $order_id = intval($_GET['id']);
    
    // دریافت اطلاعات سفارش
    $order = $db->get_row(
        "SELECT 
            o.*,
            c.first_name,
            c.last_name,
            c.phone,
            c.email,
            c.address,
            c.city,
            c.state,
            c.postcode,
            c.wp_user_id,
            u.full_name as created_by_name,
            u.username as created_by_username
         FROM mn_orders o
         INNER JOIN mn_customers c ON o.customer_id = c.id
         LEFT JOIN mn_panel_users u ON o.panel_user_id = u.id
         WHERE o.id = ?",
        [$order_id]
    );
    
    if (!$order) {
        throw new Exception('سفارش یافت نشد');
    }
    
    // دریافت آیتم‌های سفارش
    $items = $db->get_results(
        "SELECT * FROM mn_order_items WHERE order_id = ? ORDER BY id",
        [$order_id]
    );
    
    // محاسبه خلاصه مالی
    $subtotal = 0;
    $total_discount = 0;
    
    foreach ($items as $item) {
        $subtotal += $item->total;
        // اگر قیمت با تخفیف داشتیم
        if (isset($item->original_price) && $item->original_price > $item->price) {
            $total_discount += ($item->original_price - $item->price) * $item->quantity;
        }
    }
    
    // دریافت لاگ‌های sync
    $sync_logs = $db->get_results(
        "SELECT * 
         FROM mn_sync_log 
         WHERE entity_type = 'order' AND entity_id = ?
         ORDER BY created_at DESC",
        [$order_id]
    );
    
    // آماده‌سازی پاسخ
    $response = [
        'success' => true,
        'order' => [
            'id' => $order->id,
            'customer_id' => $order->customer_id,
            'status' => $order->status,
            'wc_order_id' => $order->wc_order_id,
            'total_amount' => $order->total_amount,
            'order_notes' => $order->order_notes,
            'sync_attempts' => $order->sync_attempts,
            'last_sync_error' => $order->last_sync_error,
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
            'synced_at' => $order->synced_at,
            'created_by' => [
                'name' => $order->created_by_name,
                'username' => $order->created_by_username
            ],
            'customer' => [
                'first_name' => $order->first_name,
                'last_name' => $order->last_name,
                'full_name' => trim($order->first_name . ' ' . $order->last_name),
                'phone' => $order->phone,
                'email' => $order->email,
                'address' => $order->address,
                'city' => $order->city,
                'state' => $order->state,
                'postcode' => $order->postcode,
                'wp_user_id' => $order->wp_user_id
            ],
            'items' => $items,
            'summary' => [
                'subtotal' => $subtotal,
                'discount' => $total_discount,
                'total' => $order->total_amount,
                'items_count' => count($items)
            ]
        ],
        'sync_logs' => $sync_logs
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
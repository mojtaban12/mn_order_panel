<?php
/**
 * MN Order Panel - Delete Order
 * حذف سفارش
 */

header('Content-Type: application/json; charset=utf-8');

session_start();

// چک احراز هویت
if (!isset($_SESSION['panel_user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'لطفاً وارد شوید'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['order_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'شناسه سفارش الزامی است']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

try {
    $db = MN_Database::get_instance();
    $order_id = intval($input['order_id']);
    
    // دریافت سفارش
    $order = $db->get_row(
        "SELECT * FROM mn_orders WHERE id = ?",
        [$order_id]
    );
    
    if (!$order) {
        throw new Exception('سفارش یافت نشد');
    }
    
    // فقط سفارشات با وضعیت failed قابل حذف هستند
    // (یا می‌تونی این محدودیت رو بر اساس نیازت تغییر بدی)
    if ($order->status !== 'failed') {
        throw new Exception('فقط سفارشات با خطا قابل حذف هستند');
    }
    
    // اگه sync شده، نمیشه حذف کرد
    if ($order->wc_order_id) {
        throw new Exception('سفارش همگام‌سازی شده قابل حذف نیست');
    }
    
    // حذف (آیتم‌ها به صورت خودکار حذف میشن - CASCADE)
    $db->query("DELETE FROM mn_orders WHERE id = ?", [$order_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'سفارش با موفقیت حذف شد'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
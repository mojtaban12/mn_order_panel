<?php
/**
 * MN Order Panel - Sync Single Order
 * همگام‌سازی تک سفارش
 */

header('Content-Type: application/json; charset=utf-8');

session_start();

// چک احراز هویت - موقتاً غیرفعال
// TODO: بعداً فعال کن
// if (!isset($_SESSION['panel_user_id'])) {
//     http_response_code(401);
//     echo json_encode(['success' => false, 'message' => 'لطفاً وارد شوید']);
//     exit;
// }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

// بارگذاری کلاس‌های مورد نیاز
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

try {
    $order_id = intval($input['order_id']);
    $start_time = microtime(true);
    
    // بارگذاری کلاس sync
    require_once __DIR__ . '/../sync/class-order-sync.php';
    
    $order_sync = new MN_Order_Sync();
    
    // چک کردن کدوم متد داره
    if (method_exists($order_sync, 'sync_single_order')) {
        // متد جدید
        $result = $order_sync->sync_single_order($order_id);
        
        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'message' => 'سفارش با موفقیت همگام‌سازی شد',
                'wc_order_id' => $result['wc_order_id'] ?? null,
                'execution_time' => $result['execution_time'] ?? (microtime(true) - $start_time)
            ], JSON_UNESCAPED_UNICODE);
        } else {
            throw new Exception($result['message'] ?? 'خطا در همگام‌سازی');
        }
        
    } elseif (method_exists($order_sync, 'sync')) {
        // متد قدیمی
        $wc_order_id = $order_sync->sync($order_id);
        $execution_time = microtime(true) - $start_time;
        
        echo json_encode([
            'success' => true,
            'message' => 'سفارش با موفقیت همگام‌سازی شد',
            'wc_order_id' => $wc_order_id,
            'execution_time' => round($execution_time, 2)
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
        throw new Exception('متد sync در کلاس MN_Order_Sync یافت نشد');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
<?php
/**
 * MN Order Panel - Sync Multiple Orders
 * همگام‌سازی چند سفارش یکجا
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['order_ids']) || !is_array($input['order_ids'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'لیست سفارشات الزامی است']);
    exit;
}

// بارگذاری کلاس‌های مورد نیاز
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../sync/class-sync-manager.php';

try {
    $order_ids = array_map('intval', $input['order_ids']);
    
    if (empty($order_ids)) {
        throw new Exception('لیست سفارشات خالی است');
    }
    
    // استفاده از Sync Manager
    $sync_manager = new MN_Sync_Manager();
    
    // اضافه کردن به صف
    $results = [
        'total' => count($order_ids),
        'added' => 0,
        'synced' => 0,
        'failed' => 0,
        'errors' => []
    ];
    
    foreach ($order_ids as $order_id) {
        try {
            // اضافه به صف با اولویت بالا
            $queue_id = $sync_manager->add_to_queue('order', $order_id, 1);
            
            if ($queue_id) {
                $results['added']++;
            }
        } catch (Exception $e) {
            $results['errors'][] = "Order #{$order_id}: " . $e->getMessage();
        }
    }
    
    // پردازش صف (sync کردن)
    if ($results['added'] > 0) {
        $processed = $sync_manager->process_queue(count($order_ids));
        
        $results['synced'] = $processed['completed'] ?? 0;
        $results['failed'] = $processed['failed'] ?? 0;
    }
    
    $message = sprintf(
        '%d سفارش به صف اضافه شد. %d موفق، %d ناموفق',
        $results['added'],
        $results['synced'],
        $results['failed']
    );
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'results' => $results,
        'synced_count' => $results['synced']
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
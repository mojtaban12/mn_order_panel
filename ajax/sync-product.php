<?php
/**
 * MN Order Panel - Sync Product(s)
 * همگام‌سازی محصول / محصولات با WooCommerce
 */

header('Content-Type: application/json; charset=utf-8');

session_start();

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

if (empty($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'داده‌های ورودی نامعتبر است']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../sync/class-product-sync.php';

try {
    $sync = new MN_Product_Sync();

    // ── سینک یک محصول
    if (!empty($input['product_id'])) {
        $result = $sync->sync_single_product(intval($input['product_id']));

        if ($result['success']) {
            echo json_encode([
                'success'        => true,
                'message'        => $result['message'],
                'action'         => $result['action'],
                'wp_product_id'  => $result['wp_product_id'],
                'execution_time' => $result['execution_time'],
            ], JSON_UNESCAPED_UNICODE);
        } else {
            throw new Exception($result['message']);
        }

    // ── سینک چند محصول
    } elseif (!empty($input['product_ids']) && is_array($input['product_ids'])) {
        $ids = array_map('intval', $input['product_ids']);

        if (empty($ids))        throw new Exception('لیست محصولات خالی است');
        if (count($ids) > 50)   throw new Exception('حداکثر ۵۰ محصول در هر بار');

        $results       = $sync->sync_multiple($ids);
        $success_count = $results['created'] + $results['updated'];

        echo json_encode([
            'success'      => $results['failed'] < $results['total'],
            'message'      => sprintf('%d محصول سینک شد (%d ایجاد، %d بروزرسانی، %d خطا)',
                                 $success_count, $results['created'], $results['updated'], $results['failed']),
            'stats'        => $results,
            'synced_count' => $success_count,
        ], JSON_UNESCAPED_UNICODE);

    } else {
        throw new Exception('product_id یا product_ids الزامی است');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    error_log('sync-product.php: ' . $e->getMessage());
}

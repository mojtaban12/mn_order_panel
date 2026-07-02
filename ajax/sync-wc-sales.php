<?php
/**
 * MN Order Panel - Sync WC Sales
 * بررسی سفارشات اخیر وردپرس و آپدیت موجودی
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

// if (!isset($_SESSION['panel_user_id'])) {
//     http_response_code(401);
//     echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
//     exit;
// }

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/wp-bridge.php';
require_once __DIR__ . '/../includes/class-wc-sales-sync.php';

try {
    $input      = json_decode(file_get_contents('php://input'), true) ?? [];
    $batch_size = min(intval($input['batch_size'] ?? 20), 100);

    $sync   = new MN_WC_Sales_Sync($batch_size);
    $result = $sync->run();

    echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'file'    => basename($e->getFile()),
        'line'    => $e->getLine(),
    ], JSON_UNESCAPED_UNICODE);
    error_log('sync-wc-sales: ' . $e->getMessage());
}
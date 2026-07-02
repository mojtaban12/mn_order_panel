<?php
/**
 * MN Order Panel - Delete Product
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/class-product.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'فقط POST مجاز است']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $product_id = intval($input['product_id'] ?? 0);

    if (!$product_id) throw new Exception('شناسه محصول نامعتبر است');

    $pm     = new MN_Product();
    $result = $pm->delete($product_id);

    echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
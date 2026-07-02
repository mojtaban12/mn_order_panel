<?php
/**
 * محاسبه قیمت پلکانی
 */


header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['product_id']) || !isset($input['quantity']) || !isset($input['base_price'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

require_once __DIR__ . '/../includes/class-tiered-pricing.php';
require_once __DIR__ . '/../config/settings.php';

try {
    // بارگذاری WordPress (برای دسترسی به توابع)
    $wp_load_path = MN_Settings::get('wp_load_path');
    if (file_exists($wp_load_path)) {
        require_once $wp_load_path;
    }
    
    $tiered = new MN_Tiered_Pricing();
    
    $product_id = intval($input['product_id']);
    $quantity = intval($input['quantity']);
    $base_price = floatval($input['base_price']);
    $wp_user_id = isset($input['wp_user_id']) ? intval($input['wp_user_id']) : null;
    
    // دریافت نقش‌های کاربر
    $user_roles = null;
    $skip_role_check = false;
    
    if ($wp_user_id) {
        $user_roles = $tiered->get_user_roles($wp_user_id);
    } else if (isset($input['force_calculation']) && $input['force_calculation']) {
        // محاسبه فقط قوانین بدون رول
        $skip_role_check = true;
    }
    
    // محاسبه قیمت
    $result = $tiered->calculate_price($product_id, $quantity, $base_price, $user_roles, $skip_role_check);
    
    echo json_encode([
        'success' => true,
        'pricing' => [
            'original_price' => $result['original_price'],
            'final_price' => $result['final_price'],
            'discount_percent' => $result['discount_percent'],
            'discount_amount' => $result['discount_amount'],
            'rule_applied' => $result['rule_applied'],
            'total' => $result['final_price'] * $quantity,
            'savings' => $result['discount_amount'] * $quantity
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
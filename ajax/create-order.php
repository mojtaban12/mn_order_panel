<?php
/**
 * MN Order Panel - Create Order Ajax Handler
 * ثبت سفارش در دیتابیس مجزا + افزودن به صف sync
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'فقط درخواست POST مجاز است'
    ]);
    exit;
}

session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/class-customer.php';
require_once __DIR__ . '/../includes/class-order.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('داده‌های ورودی نامعتبر است');
    }
    
    validate_customer_data($input);
    validate_items($input);
    
    $db = MN_Database::get_instance();
    $db->begin_transaction();
    
    // ============================================
    // مرحله 1: ایجاد/بروزرسانی مشتری
    // ============================================
    $customer_model = new MN_Customer();
    
    $customer_data = [
        'first_name' => sanitize_text($input['customer']['first_name']),
        'last_name' => sanitize_text($input['customer']['last_name']),
        'phone' => sanitize_text($input['customer']['phone']),
        'email' => !empty($input['customer']['email']) ? sanitize_email($input['customer']['email']) : null,
        'address' => sanitize_textarea($input['customer']['address'] ?? ''),
        'city' => sanitize_text($input['customer']['city'] ?? ''),
        'state' => sanitize_text($input['customer']['state'] ?? ''),
        'postcode' => sanitize_text($input['customer']['postcode'] ?? '')
    ];
    
    $customer_id = $customer_model->create_or_update($customer_data);
    
    if (!$customer_id) {
        throw new Exception('خطا در ثبت اطلاعات مشتری');
    }
    
    // ============================================
    // مرحله 2: ایجاد سفارش با حمل و نقل
    // ============================================
    $order_model = new MN_Order();
    
    // دریافت اطلاعات حمل و نقل
    $shipping_cost = isset($input['shipping']['cost']) ? floatval($input['shipping']['cost']) : 0;
    $shipping_weight = isset($input['shipping']['weight']) ? floatval($input['shipping']['weight']) : 0;
    
    $order_data = [
        'customer_id' => $customer_id,
        'shipping_cost' => $shipping_cost,
        'order_notes' => sanitize_textarea($input['order_notes'] ?? '')
    ];
    
    // آماده‌سازی آیتم‌ها
    $items = [];
    foreach ($input['items'] as $item) {
        $items[] = [
            'product_id' => intval($item['product_id']),
            'product_name' => sanitize_text($item['product_name']),
            'product_sku' => sanitize_text($item['product_sku'] ?? ''),
            'quantity' => intval($item['quantity']),
            'price' => floatval($item['price']),
            'weight' => floatval($item['weight'] ?? 0)
        ];
    }
    
    // ID کاربر پنل
    $panel_user_id = isset($_SESSION['panel_user_id']) ? $_SESSION['panel_user_id'] : 1;
    
    $order_id = $order_model->create($order_data, $items, $panel_user_id);
    
    if (!$order_id) {
        throw new Exception('خطا در ثبت سفارش');
    }
    
    // ============================================
    // مرحله 3: Commit
    // ============================================
    $db->commit();
    
    // دریافت اطلاعات سفارش ثبت شده
    $order = $order_model->get_with_customer($order_id);
    
    // محاسبه آمار
    $stats = $order_model->get_statistics();
    
    // خروجی موفقیت
    echo json_encode([
        'success' => true,
        'message' => '✓ سفارش با موفقیت ثبت شد',
        'order' => [
            'id' => intval($order->id),
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'total_amount' => floatval($order->total_amount),
            'total_formatted' => number_format($order->total_amount),
            'shipping_cost' => $shipping_cost,
            'shipping_formatted' => number_format($shipping_cost),
            'status' => $order->status,
            'created_at' => $order->created_at
        ],
        'stats' => [
            'today_orders' => intval($stats->today_orders),
            'today_revenue' => floatval($stats->today_revenue),
            'pending_orders' => intval($stats->pending_orders)
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    if (isset($db) && $db) {
        $db->rollback();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    
    error_log('Create order error: ' . $e->getMessage());
}

function validate_customer_data($input) {
    if (empty($input['customer'])) {
        throw new Exception('اطلاعات مشتری الزامی است');
    }
    
    $customer = $input['customer'];
    
    if (empty($customer['first_name'])) {
        throw new Exception('نام مشتری الزامی است');
    }
    
    if (empty($customer['last_name'])) {
        throw new Exception('نام خانوادگی مشتری الزامی است');
    }
    
    if (empty($customer['phone'])) {
        throw new Exception('شماره تلفن مشتری الزامی است');
    }
    
    $phone = preg_replace('/[^0-9]/', '', $customer['phone']);
    if (strlen($phone) < 10) {
        throw new Exception('شماره تلفن معتبر نیست');
    }
    
    if (!empty($customer['email']) && !filter_var($customer['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('آدرس ایمیل معتبر نیست');
    }
}

function validate_items($input) {
    if (empty($input['items']) || !is_array($input['items'])) {
        throw new Exception('حداقل یک محصول باید انتخاب شود');
    }
    
    if (count($input['items']) === 0) {
        throw new Exception('سبد خرید خالی است');
    }
    
    foreach ($input['items'] as $index => $item) {
        if (empty($item['product_id'])) {
            throw new Exception("شناسه محصول در ردیف " . ($index + 1) . " الزامی است");
        }
        
        if (empty($item['product_name'])) {
            throw new Exception("نام محصول در ردیف " . ($index + 1) . " الزامی است");
        }
        
        if (empty($item['quantity']) || intval($item['quantity']) <= 0) {
            throw new Exception("تعداد محصول در ردیف " . ($index + 1) . " باید بیشتر از صفر باشد");
        }
        
        if (empty($item['price']) || floatval($item['price']) <= 0) {
            throw new Exception("قیمت محصول در ردیف " . ($index + 1) . " نامعتبر است");
        }
    }
}

function sanitize_text($value) {
    return trim(strip_tags($value ?? ''));
}

function sanitize_textarea($value) {
    return trim(strip_tags($value ?? ''));
}

function sanitize_email($value) {
    return filter_var(trim($value), FILTER_SANITIZE_EMAIL);
}
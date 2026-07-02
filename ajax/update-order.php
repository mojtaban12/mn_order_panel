<?php
/**
 * MN Order Panel - Update Order
 * بروزرسانی سفارش
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

if (!isset($input['order_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'شناسه سفارش الزامی است']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

try {
    $db = MN_Database::get_instance();
    $order_id = intval($input['order_id']);
    
    // دریافت سفارش فعلی
    $order = $db->get_row(
        "SELECT * FROM mn_orders WHERE id = ?",
        [$order_id]
    );
    
    if (!$order) {
        throw new Exception('سفارش یافت نشد');
    }
    
    // چک کردن که sync نشده باشه
    if ($order->wc_order_id) {
        throw new Exception('سفارش همگام‌سازی شده قابل ویرایش نیست');
    }
    
    // شروع transaction
    $db->begin_transaction();
    
    try {
        // 1. بروزرسانی اطلاعات مشتری (اگه ارسال شده)
        if (isset($input['customer'])) {
            $customer = $input['customer'];
            
            $db->query(
                "UPDATE mn_customers 
                 SET first_name = ?,
                     last_name = ?,
                     email = ?,
                     address = ?,
                     city = ?,
                     state = ?,
                     postcode = ?,
                     updated_at = NOW()
                 WHERE id = ?",
                [
                    $customer['first_name'] ?? '',
                    $customer['last_name'] ?? '',
                    $customer['email'] ?? '',
                    $customer['address'] ?? '',
                    $customer['city'] ?? '',
                    $customer['state'] ?? '',
                    $customer['postcode'] ?? '',
                    $order->customer_id
                ]
            );
        }
        
        // 2. بروزرسانی آیتم‌های سفارش
        if (isset($input['items']) && is_array($input['items'])) {
            // حذف آیتم‌های قبلی
            $db->query("DELETE FROM mn_order_items WHERE order_id = ?", [$order_id]);
            
            $total_amount = 0;
            
            // اضافه کردن آیتم‌های جدید
            foreach ($input['items'] as $item) {
                if (empty($item['product_id']) || empty($item['quantity'])) {
                    continue;
                }
                
                $db->query(
                    "INSERT INTO mn_order_items 
                     (order_id, product_id, product_name, product_sku, quantity, price, total)
                     VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [
                        $order_id,
                        $item['product_id'],
                        $item['product_name'] ?? '',
                        $item['product_sku'] ?? '',
                        $item['quantity'],
                        $item['price'],
                        $item['total']
                    ]
                );
                
                $total_amount += floatval($item['total']);
            }
            
            // 3. بروزرسانی مبلغ کل سفارش
            $db->query(
                "UPDATE mn_orders 
                 SET total_amount = ?,
                     order_notes = ?,
                     updated_at = NOW()
                 WHERE id = ?",
                [
                    $total_amount,
                    $input['order_notes'] ?? $order->order_notes,
                    $order_id
                ]
            );
        } else {
            // فقط یادداشت بروزرسانی شده
            if (isset($input['order_notes'])) {
                $db->query(
                    "UPDATE mn_orders SET order_notes = ?, updated_at = NOW() WHERE id = ?",
                    [$input['order_notes'], $order_id]
                );
            }
        }
        
        // Commit
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'سفارش با موفقیت بروزرسانی شد'
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
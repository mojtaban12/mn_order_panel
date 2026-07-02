<?php
/**
 * MN Order Panel - Batch Invoice Import
 * دریافت آرایه محصولات از JS و ثبت دسته‌ای
 */

header('Content-Type: application/json; charset=utf-8');
define('DOING_AJAX', true);
session_start();

set_time_limit(300); // 5 دقیقه برای فایل‌های بزرگ

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../includes/class-product.php';
require_once __DIR__ . '/../includes/class-invoice.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) throw new Exception('داده ورودی نامعتبر است');

    $category_id   = intval($input['category_id']   ?? 0);
    $supplier_name = trim($input['supplier_name']    ?? '');
    $invoice_date  = trim($input['invoice_date']     ?? '');
    $rows          = $input['rows']                  ?? [];
    $panel_user_id = intval($_SESSION['panel_user_id'] ?? 1);

    if (!$category_id)   throw new Exception('دسته‌بندی الزامی است');
    if (!$supplier_name) throw new Exception('نام فروشنده الزامی است');
    if (!$invoice_date)  throw new Exception('تاریخ فاکتور الزامی است');
    if (empty($rows))    throw new Exception('هیچ ردیفی برای ثبت وجود ندارد');

    $pm  = new MN_Product();
    $inv = new MN_Invoice();
    $db  = MN_Database::get_instance();

    $results = [];
    $done    = 0;
    $errors  = 0;

    foreach ($rows as $row) {
        $name         = trim($row['name']         ?? '');
        $quantity     = intval($row['quantity']    ?? 0);
        $purchase_p   = floatval($row['purchase_price'] ?? 0);
        $regular_p    = !empty($row['regular_price'])  ? floatval($row['regular_price'])    : null;
        $sale_p       = !empty($row['sale_price'])     ? floatval($row['sale_price'])       : null;
        $disc_percent = !empty($row['discount_percent'])? floatval($row['discount_percent']): null;

        if (!$name || $quantity <= 0 || $purchase_p <= 0) {
            $results[] = ['success' => false, 'message' => 'ردیف ناقص'];
            $errors++;
            continue;
        }

        try {
            $db->begin_transaction();

            // ── ساخت محصول خام ─────────────────
            $sku = $pm->generate_sku();

            $product_id = $pm->create([
                'title'               => $name,
                'sku'                 => $sku,
                'regular_price'       => $regular_p  ?? $purchase_p,
                'sale_price'          => $sale_p,
                'discount_percent'    => $disc_percent,
                'purchase_price'      => $purchase_p,
                'real_stock_quantity' => 0,
                'stock_quantity'      => 0,
                'stock_status'        => 'instock',
                'manage_stock'        => 1,
                'status'              => 'active',
                'panel_category_id'   => $category_id,
            ], [], $panel_user_id);

            if (!$product_id) throw new Exception('خطا در ساخت محصول');

            // ── ثبت فاکتور خرید ────────────────
            $total = $quantity * $purchase_p;

            $inv_id = $db->insert('mn_product_invoices', [
                'product_id'     => $product_id,
                'invoice_date'   => $invoice_date,
                'supplier_name'  => $supplier_name,
                'quantity'       => $quantity,
                'unit_price'     => $purchase_p,
                'total_price'    => $total,
                'final_amount'   => $total,
                'discount'       => 0,
                'tax'            => 0,
                'shipping_cost'  => 0,
                'payment_method' => 'cash',
                'payment_status' => 'paid',
                'paid_amount'    => $total,
                'created_by'     => $panel_user_id,
            ]);

            if (!$inv_id) throw new Exception('خطا در ثبت فاکتور');

            // ── آپدیت موجودی ───────────────────
            $db->update('mn_products', [
                'real_stock_quantity' => $quantity,
            ], ['id' => $product_id]);

            // ── لاگ موجودی ─────────────────────
            $db->insert('mn_stock_logs', [
                'product_id'      => $product_id,
                'stock_type'      => 'real',
                'change_type'     => 'increase',
                'quantity_before' => 0,
                'quantity_change' => $quantity,
                'quantity_after'  => $quantity,
                'reference_type'  => 'invoice',
                'reference_id'    => $inv_id,
                'notes'           => 'ایمپورت دسته‌ای — ' . $supplier_name,
                'created_by'      => $panel_user_id,
            ]);

            $db->commit();
            $done++;
            $results[] = ['success' => true, 'product_id' => $product_id, 'invoice_id' => $inv_id];

        } catch (Exception $e) {
            $db->rollback();
            $errors++;
            $results[] = ['success' => false, 'message' => $e->getMessage()];
            error_log('Import batch row error: ' . $e->getMessage() . ' — ' . $name);
        }
    }

    echo json_encode([
        'success' => true,
        'done'    => $done,
        'errors'  => $errors,
        'total'   => count($rows),
        'message' => $done . ' محصول ثبت شد' . ($errors > 0 ? '، ' . $errors . ' خطا' : ''),
        'results' => $results,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    error_log('import-invoice-batch: ' . $e->getMessage());
}
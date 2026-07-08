<?php
/**
 * MN Order Panel - Batch Import برای محصولات موجود در سایت
 * SKU پنل = P-{شناسه اکسل} — برای تشخیص از محصولات auto-generate شده (P_xxxxx)
 */

header('Content-Type: application/json; charset=utf-8');
session_start();
set_time_limit(300);

require_once __DIR__ . '/../config/database.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) throw new Exception('داده ورودی نامعتبر است');

    $category_id   = intval($input['category_id'] ?? 0) ?: null;
    $rows          = $input['rows'] ?? [];
    $panel_user_id = intval($_SESSION['panel_user_id'] ?? 1);

    if (empty($rows)) throw new Exception('هیچ ردیفی برای ثبت وجود ندارد');

    $db = MN_Database::get_instance();

    $created = 0;
    $updated = 0;
    $errors  = 0;
    $results = [];

    foreach ($rows as $row) {
        $excel_id   = trim((string) ($row['excel_id'] ?? ''));
        $name       = trim($row['name'] ?? '');
        $quantity   = intval($row['quantity'] ?? 0);
        $purchase_p = ($row['purchase_price'] ?? '') !== '' ? floatval($row['purchase_price']) : null;
        $regular_p  = ($row['regular_price']  ?? '') !== '' ? floatval($row['regular_price'])  : null;

        $numeric_id = preg_replace('/[^0-9]/', '', $excel_id);

        if ($numeric_id === '' || !$name) {
            $errors++;
            $results[] = ['success' => false, 'message' => 'شناسه یا نام ناقص'];
            continue;
        }

        $sku = 'P-' . $numeric_id;

        try {
            $existing = $db->get_row(
                "SELECT id, real_stock_quantity FROM mn_products WHERE sku = ?",
                [$sku]
            );

            if ($existing) {
                // ── محصول قبلاً وارد شده — فقط آپدیت ──
                $stock_before = intval($existing->real_stock_quantity ?? 0);
                $stock_after  = $stock_before + $quantity;

                $update = ['real_stock_quantity' => $stock_after];
                if ($purchase_p !== null) $update['purchase_price'] = $purchase_p;
                if ($regular_p  !== null) $update['regular_price']  = $regular_p;

                $db->update('mn_products', $update, ['id' => $existing->id]);

                $db->insert('mn_stock_logs', [
                    'product_id'      => $existing->id,
                    'stock_type'      => 'real',
                    'change_type'     => 'increase',
                    'quantity_before' => $stock_before,
                    'quantity_change' => $quantity,
                    'quantity_after'  => $stock_after,
                    'reference_type'  => 'manual',
                    'notes'           => 'ایمپورت محصول موجود در سایت — SKU: ' . $sku,
                    'created_by'      => $panel_user_id,
                ]);

                $updated++;
                $results[] = ['success' => true, 'product_id' => $existing->id, 'mode' => 'update'];

            } else {
                // ── ثبت محصول جدید در پنل (wp_product_id هنوز نامعلوم) ──
                $product_id = $db->insert('mn_products', [
                    'title'               => $name,
                    'sku'                 => $sku,
                    'regular_price'       => $regular_p ?? 0,
                    'purchase_price'      => $purchase_p,
                    'real_stock_quantity' => $quantity,
                    'stock_quantity'      => 0,
                    'stock_status'        => 'instock',
                    'manage_stock'        => 1,
                    'status'              => 'active',
                    'panel_category_id'   => $category_id,
                    'created_by'          => $panel_user_id,
                ]);

                if (!$product_id) throw new Exception('خطا در ثبت محصول');

                $db->insert('mn_stock_logs', [
                    'product_id'      => $product_id,
                    'stock_type'      => 'real',
                    'change_type'     => 'set',
                    'quantity_before' => 0,
                    'quantity_change' => $quantity,
                    'quantity_after'  => $quantity,
                    'reference_type'  => 'manual',
                    'notes'           => 'ایمپورت محصول موجود در سایت — SKU: ' . $sku,
                    'created_by'      => $panel_user_id,
                ]);

                $created++;
                $results[] = ['success' => true, 'product_id' => $product_id, 'mode' => 'create'];
            }

        } catch (Exception $e) {
            $errors++;
            $results[] = ['success' => false, 'message' => $e->getMessage()];
            error_log('import-existing-batch row error: ' . $e->getMessage() . ' — ' . $name);
        }
    }

    echo json_encode([
        'success' => true,
        'created' => $created,
        'updated' => $updated,
        'errors'  => $errors,
        'total'   => count($rows),
        'message' => $created . ' محصول جدید ثبت شد'
            . ($updated ? '، ' . $updated . ' محصول بروزرسانی شد' : '')
            . ($errors  ? '، ' . $errors  . ' خطا' : ''),
        'results' => $results,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    error_log('import-existing-batch: ' . $e->getMessage());
}
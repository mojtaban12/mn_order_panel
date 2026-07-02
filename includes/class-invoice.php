<?php
/**
 * MN Order Panel - Product Invoice Model
 * فاکتور خرید / تأمین محصول
 */

if (class_exists('MN_Invoice')) return;

require_once __DIR__ . '/../config/database.php';

class MN_Invoice {

    private $db;

    public function __construct() {
        $this->db = MN_Database::get_instance();
    }

    // ════════════════════════════════════════
    // ثبت فاکتور
    // ════════════════════════════════════════

    /**
     * ثبت فاکتور — دو حالت:
     *   mode=existing → محصول موجود، فقط موجودی آپدیت
     *   mode=new      → محصول جدید ساخته میشه، بعد فاکتور ثبت
     */
    public function create($data, $panel_user_id = 1) {
        try {
            $this->db->begin_transaction();

            $mode = $data['mode'] ?? 'existing';

            // ── حالت ۱: محصول جدید ─────────────
            if ($mode === 'new') {
                $product_id = $this->create_raw_product($data, $panel_user_id);
                if (!$product_id) throw new Exception('خطا در ساخت محصول');
                $data['product_id'] = $product_id;
            }

            // ── حالت ۲: محصول موجود ─────────────
            $product_id = intval($data['product_id']);
            $product    = $this->db->get_row(
                "SELECT id, real_stock_quantity, purchase_price FROM mn_products WHERE id = ?",
                [$product_id]
            );
            if (!$product) throw new Exception('محصول یافت نشد');

            $quantity   = intval($data['quantity']);
            $unit_price = floatval($data['unit_price']);
            $discount   = floatval($data['discount']      ?? 0);
            $tax        = floatval($data['tax']            ?? 0);
            $shipping   = floatval($data['shipping_cost']  ?? 0);
            $total      = $quantity * $unit_price;
            $final      = $total - $discount + $tax + $shipping;

            // ── ثبت فاکتور ──────────────────────
            $invoice_id = $this->db->insert('mn_product_invoices', [
                'product_id'     => $product_id,
                'invoice_number' => !empty($data['invoice_number']) ? trim($data['invoice_number']) : null,
                'invoice_date'   => $data['invoice_date'],
                'supplier_name'  => trim($data['supplier_name']),
                'supplier_phone' => !empty($data['supplier_phone']) ? trim($data['supplier_phone']) : null,
                'quantity'       => $quantity,
                'unit_price'     => $unit_price,
                'total_price'    => $total,
                'discount'       => $discount,
                'tax'            => $tax,
                'shipping_cost'  => $shipping,
                'final_amount'   => $final,
                'payment_method' => $data['payment_method'] ?? 'cash',
                'payment_status' => $data['payment_status'] ?? 'paid',
                'paid_amount'    => floatval($data['paid_amount'] ?? $final),
                'notes'          => !empty($data['notes']) ? trim($data['notes']) : null,
                'created_by'     => $panel_user_id,
            ]);
            if (!$invoice_id) throw new Exception('خطا در ثبت فاکتور');

            // ── آپدیت موجودی واقعی + قیمت‌ها ──────
            $stock_before  = intval($product->real_stock_quantity ?? 0);
            $stock_after   = $stock_before + $quantity;
            $regular_price = !empty($data['regular_price']) && floatval($data['regular_price']) > 0
                             ? floatval($data['regular_price']) : null;

            $product_update = [
                'real_stock_quantity' => $stock_after,
                'purchase_price'      => $unit_price,
            ];
            if ($regular_price) {
                $product_update['regular_price'] = $regular_price;
            }

            $this->db->update('mn_products', $product_update, ['id' => $product_id]);

            // ── لاگ موجودی ──────────────────────
            $this->db->insert('mn_stock_logs', [
                'product_id'      => $product_id,
                'stock_type'      => 'real',
                'change_type'     => 'increase',
                'quantity_before' => $stock_before,
                'quantity_change' => $quantity,
                'quantity_after'  => $stock_after,
                'reference_type'  => 'invoice',
                'reference_id'    => $invoice_id,
                'notes'           => 'فاکتور خرید #' . ($data['invoice_number'] ?? $invoice_id)
                                   . ' — ' . $data['supplier_name'],
                'created_by'      => $panel_user_id,
            ]);

            $this->db->commit();

            // ── sync با WooCommerce اگر محصول سینک شده ─
            $sync_result = null;
            if ($mode === 'existing') {
                $updated = $this->db->get_row(
                    "SELECT wp_product_id, is_synced FROM mn_products WHERE id = ?",
                    [$product_id]
                );
                if ($updated && $updated->wp_product_id && $updated->is_synced) {
                    $sync_result = $this->try_sync($product_id);
                }
            }

            $msg = ($mode === 'new' ? 'محصول ساخته شد و ' : '') .
                   'فاکتور ثبت شد — موجودی: ' . $stock_before . ' → ' . $stock_after;
            if ($sync_result) {
                $msg .= $sync_result['success'] ? ' | با WC همگام شد' : ' | sync: ' . $sync_result['message'];
            }

            return [
                'success'       => true,
                'invoice_id'    => $invoice_id,
                'product_id'    => $product_id,
                'stock_before'  => $stock_before,
                'stock_after'   => $stock_after,
                'final_amount'  => $final,
                'mode'          => $mode,
                'synced_wc'     => $sync_result['success'] ?? false,
                'message'       => $msg,
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            error_log('Invoice create error: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ════════════════════════════════════════
    // ساخت محصول خام از فاکتور
    // ════════════════════════════════════════

    private function create_raw_product($data, $panel_user_id) {
        require_once __DIR__ . '/class-product.php';
        $pm = new MN_Product();

        // auto SKU
        $sku = !empty($data['product_sku']) ? trim($data['product_sku']) : $pm->generate_sku();

        // چک تکراری نبودن SKU
        if ($pm->sku_exists($sku)) $sku = $pm->generate_sku();

        return $pm->create([
            'title'               => trim($data['product_title']),
            'sku'                 => $sku,
            'regular_price'       => floatval($data['regular_price'] ?? $data['unit_price']),
            'purchase_price'      => floatval($data['unit_price']),
            'real_stock_quantity' => 0, // از فاکتور آپدیت میشه
            'stock_quantity'      => 0,
            'stock_status'        => 'instock',
            'manage_stock'        => 1,
            'status'              => 'draft', // پیش‌نویس تا ادمین کامل کنه
            'panel_category_id'   => !empty($data['panel_category_id']) ? intval($data['panel_category_id']) : null,
        ], [], $panel_user_id);
    }

    // ════════════════════════════════════════
    // حذف فاکتور (برگشت موجودی)
    // ════════════════════════════════════════

    public function delete($invoice_id) {
        try {
            $this->db->begin_transaction();

            $inv = $this->db->get_row(
                "SELECT * FROM mn_product_invoices WHERE id = ?",
                [$invoice_id]
            );
            if (!$inv) throw new Exception('فاکتور یافت نشد');

            // برگشت موجودی
            $product = $this->db->get_row(
                "SELECT real_stock_quantity FROM mn_products WHERE id = ?",
                [$inv->product_id]
            );

            $stock_before = intval($product->real_stock_quantity ?? 0);
            $stock_after  = max(0, $stock_before - $inv->quantity);

            $this->db->update('mn_products',
                ['real_stock_quantity' => $stock_after],
                ['id' => $inv->product_id]
            );

            // لاگ برگشت
            $this->db->insert('mn_stock_logs', [
                'product_id'      => $inv->product_id,
                'stock_type'      => 'real',
                'change_type'     => 'decrease',
                'quantity_before' => $stock_before,
                'quantity_change' => -$inv->quantity,
                'quantity_after'  => $stock_after,
                'reference_type'  => 'invoice',
                'reference_id'    => $invoice_id,
                'notes'           => 'حذف فاکتور #' . $invoice_id,
            ]);

            $this->db->delete('mn_product_invoices', ['id' => $invoice_id]);

            $this->db->commit();
            return ['success' => true, 'message' => 'فاکتور حذف و موجودی برگشت داده شد'];

        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ════════════════════════════════════════
    // دریافت
    // ════════════════════════════════════════

    public function get($invoice_id) {
        return $this->db->get_row(
            "SELECT i.*, p.title AS product_title, p.sku
             FROM mn_product_invoices i
             LEFT JOIN mn_products p ON i.product_id = p.id
             WHERE i.id = ?",
            [$invoice_id]
        );
    }

    /**
     * لیست فاکتورهای یک محصول
     */
    public function get_by_product($product_id) {
        return $this->db->get_results(
            "SELECT * FROM mn_product_invoices
             WHERE product_id = ?
             ORDER BY invoice_date DESC, id DESC",
            [$product_id]
        );
    }

    /**
     * لیست کلی با فیلتر
     */
    public function get_list($filters = [], $limit = 20, $offset = 0) {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['product_id'])) {
            $where[]  = 'i.product_id = ?';
            $params[] = intval($filters['product_id']);
        }
        if (!empty($filters['supplier'])) {
            $where[]  = 'i.supplier_name LIKE ?';
            $params[] = '%' . $filters['supplier'] . '%';
        }
        if (!empty($filters['payment_status'])) {
            $where[]  = 'i.payment_status = ?';
            $params[] = $filters['payment_status'];
        }
        if (!empty($filters['date_from'])) {
            $where[]  = 'i.invoice_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[]  = 'i.invoice_date <= ?';
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['search'])) {
            $like     = '%' . $filters['search'] . '%';
            $where[]  = '(p.title LIKE ? OR i.invoice_number LIKE ? OR i.supplier_name LIKE ?)';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }

        $where_sql = implode(' AND ', $where);
        $params[]  = $limit;
        $params[]  = $offset;

        return $this->db->get_results("
            SELECT
                i.*,
                p.title AS product_title,
                p.sku   AS product_sku,
                p.panel_category_id
            FROM mn_product_invoices i
            LEFT JOIN mn_products p ON i.product_id = p.id
            WHERE {$where_sql}
            ORDER BY i.invoice_date DESC, i.id DESC
            LIMIT ? OFFSET ?
        ", $params);
    }

    public function count($filters = []) {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['product_id'])) { $where[] = 'i.product_id = ?'; $params[] = intval($filters['product_id']); }
        if (!empty($filters['payment_status'])) { $where[] = 'i.payment_status = ?'; $params[] = $filters['payment_status']; }
        if (!empty($filters['date_from'])) { $where[] = 'i.invoice_date >= ?'; $params[] = $filters['date_from']; }
        if (!empty($filters['date_to']))   { $where[] = 'i.invoice_date <= ?'; $params[] = $filters['date_to']; }
        if (!empty($filters['search'])) {
            $like = '%' . $filters['search'] . '%';
            $where[] = '(p.title LIKE ? OR i.invoice_number LIKE ? OR i.supplier_name LIKE ?)';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }

        return (int) $this->db->get_var(
            "SELECT COUNT(*) FROM mn_product_invoices i
             LEFT JOIN mn_products p ON i.product_id = p.id
             WHERE " . implode(' AND ', $where),
            $params
        );
    }

    /**
     * آمار کلی فاکتورها
     */
    public function get_stats() {
        return $this->db->get_row("
            SELECT
                COUNT(*)                          AS total,
                SUM(final_amount)                 AS total_amount,
                SUM(CASE WHEN payment_status = 'paid'    THEN final_amount ELSE 0 END) AS paid_amount,
                SUM(CASE WHEN payment_status = 'unpaid'  THEN final_amount ELSE 0 END) AS unpaid_amount,
                SUM(CASE WHEN payment_status = 'partial' THEN final_amount - paid_amount ELSE 0 END) AS remaining_amount,
                SUM(quantity)                     AS total_qty,
                COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) AS today_count
            FROM mn_product_invoices
        ");
    }

    // ════════════════════════════════════════
    // sync با WC
    // ════════════════════════════════════════
    private function try_sync($product_id) {
        try {
            require_once __DIR__ . '/class-product-sync.php';
            $sync = new MN_Product_Sync();
            return $sync->sync_single_product($product_id);
        } catch (Exception $e) {
            error_log('Invoice sync failed: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
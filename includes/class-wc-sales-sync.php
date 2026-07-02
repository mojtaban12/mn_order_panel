<?php
/**
 * MN Order Panel - WC Sales Sync
 * بررسی سفارشات اخیر وردپرس و کاهش موجودی پنل
 *
 * منطق:
 * 1. N سفارش آخر WC رو بخون (بر اساس آخرین order_id پردازش‌شده)
 * 2. هر آیتم: چک کن محصول در mn_products هست؟ (با wp_product_id یا SKU)
 * 3. اگر بود → موجودی مجازی رو کاهش بده + فاکتور فروش ثبت کن
 * 4. آخرین order_id رو آپدیت کن
 */

if (class_exists('MN_WC_Sales_Sync')) return;

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
if (!class_exists('MN_WP_Bridge')) {
    require_once __DIR__ . '/../config/wp-bridge.php';
}

class MN_WC_Sales_Sync {

    private $db;
    private $wp;
    private $wpdb;
    private $batch_size;

    public function __construct($batch_size = 20) {
        $this->db         = MN_Database::get_instance();
        $this->wp         = MN_WP_Bridge::get_instance();
        $this->wpdb       = $this->wp->wpdb;
        $this->batch_size = intval($batch_size);
    }

    // ════════════════════════════════════════
    // اجرای اصلی
    // ════════════════════════════════════════

    /**
     * @return array آمار پردازش
     */
    public function run() {
        $last_order_id = intval(MN_Settings::get('wc_sales_last_order_id', 0));

        // دریافت سفارشات جدید از WC
        $orders = $this->fetch_wc_orders($last_order_id);

        if (empty($orders)) {
            return [
                'success'      => true,
                'message'      => 'سفارش جدیدی یافت نشد',
                'processed'    => 0,
                'matched'      => 0,
                'stock_updated'=> 0,
                'errors'       => [],
            ];
        }

        $stats = [
            'processed'     => 0,
            'matched'       => 0,
            'stock_updated' => 0,
            'skipped'       => 0,
            'errors'        => [],
        ];

        $max_order_id = $last_order_id;

        foreach ($orders as $order) {
            try {
                $result = $this->process_order($order);
                $stats['processed']++;
                $stats['matched']      += $result['matched'];
                $stats['stock_updated']+= $result['stock_updated'];
                $stats['skipped']      += $result['skipped'];

                if ($order->order_id > $max_order_id) {
                    $max_order_id = $order->order_id;
                }
            } catch (Exception $e) {
                $stats['errors'][] = 'Order #' . $order->order_id . ': ' . $e->getMessage();
                error_log('WC Sales Sync - order ' . $order->order_id . ': ' . $e->getMessage());
            }
        }

        // ذخیره آخرین order_id
        if ($max_order_id > $last_order_id) {
            $this->db->update(
                'mn_settings',
                ['setting_value' => $max_order_id],
                ['setting_key'   => 'wc_sales_last_order_id']
            );
        }

        $stats['success'] = true;
        $stats['message'] = sprintf(
            '%d سفارش بررسی شد، %d آیتم تطبیق یافت، %d موجودی آپدیت شد',
            $stats['processed'], $stats['matched'], $stats['stock_updated']
        );

        return $stats;
    }

    // ════════════════════════════════════════
    // دریافت سفارشات از WC
    // ════════════════════════════════════════

    private function fetch_wc_orders($after_order_id) {
        $pfx    = $this->wpdb->prefix;
        $params = [];
        $where  = "p.post_type = 'shop_order' AND p.post_status IN ('wc-completed','wc-processing','wc-on-hold')";

        if ($after_order_id > 0) {
            $where   .= " AND p.ID > ?";
            $params[] = $after_order_id;
        }

        $params[] = $this->batch_size;

        return $this->wp->get_results("
            SELECT
                p.ID            AS order_id,
                p.post_status   AS order_status,
                p.post_date     AS order_date,
                MAX(CASE WHEN pm.meta_key = '_order_total'          THEN pm.meta_value END) AS order_total,
                MAX(CASE WHEN pm.meta_key = '_billing_first_name'   THEN pm.meta_value END) AS first_name,
                MAX(CASE WHEN pm.meta_key = '_billing_last_name'    THEN pm.meta_value END) AS last_name,
                MAX(CASE WHEN pm.meta_key = '_billing_phone'        THEN pm.meta_value END) AS phone,
                MAX(CASE WHEN pm.meta_key = '_billing_email'        THEN pm.meta_value END) AS email
            FROM {$pfx}posts p
            LEFT JOIN {$pfx}postmeta pm ON pm.post_id = p.ID
            WHERE {$where}
            GROUP BY p.ID
            ORDER BY p.ID ASC
            LIMIT ?
        ", $params);
    }

    // ════════════════════════════════════════
    // پردازش یک سفارش
    // ════════════════════════════════════════

    private function process_order($order) {
        $result = ['matched' => 0, 'stock_updated' => 0, 'skipped' => 0];

        // دریافت آیتم‌های سفارش
        $items = $this->fetch_order_items($order->order_id);
        if (empty($items)) return $result;

        $customer_name  = trim(($order->first_name ?? '') . ' ' . ($order->last_name ?? ''));
        $customer_phone = $order->phone ?? null;
        $customer_email = $order->email ?? null;
        $order_date     = $order->order_date ?? date('Y-m-d H:i:s');

        foreach ($items as $item) {
            // پیدا کردن محصول در پنل
            $panel_product = $this->find_panel_product($item->product_id, $item->sku);
            if (!$panel_product) {
                $result['skipped']++;
                continue;
            }

            // چک تکراری نبودن
            $already = $this->db->get_var(
                "SELECT COUNT(*) FROM mn_wc_sales
                 WHERE wc_order_id = ? AND wc_order_item_id = ?",
                [$order->order_id, $item->order_item_id]
            );
            if ($already > 0) {
                $result['skipped']++;
                continue;
            }

            $result['matched']++;

            // کاهش موجودی مجازی
            $stock_before   = null;
            $stock_after    = null;
            $stock_updated  = 0;
            $qty            = max(1, intval($item->qty));

            if ($panel_product->manage_stock) {
                $real_before    = intval($panel_product->real_stock_quantity ?? 0);
                $virtual_before = intval($panel_product->stock_quantity ?? 0);

                $remaining = $qty;

                // ── اول از موجودی واقعی کم کن ──
                $real_deduct = min($real_before, $remaining);
                $real_after  = $real_before - $real_deduct;
                $remaining  -= $real_deduct;

                // ── باقیمانده از موجودی مجازی کم کن ──
                $virtual_deduct = min($virtual_before, $remaining);
                $virtual_after  = max(0, $virtual_before - $virtual_deduct);

                $this->db->update('mn_products', [
                    'real_stock_quantity' => $real_after,
                    'stock_quantity'      => $virtual_after,
                ], ['id' => $panel_product->id]);

                if ($real_deduct > 0) {
                    $this->db->insert('mn_stock_logs', [
                        'product_id'      => $panel_product->id,
                        'wp_product_id'   => $item->product_id,
                        'stock_type'      => 'real',
                        'change_type'     => 'decrease',
                        'quantity_before' => $real_before,
                        'quantity_change' => -$real_deduct,
                        'quantity_after'  => $real_after,
                        'reference_type'  => 'order',
                        'reference_id'    => $order->order_id,
                        'notes'           => 'سفارش WC #' . $order->order_id . ' (کسر از موجودی واقعی)',
                    ]);
                }

                if ($virtual_deduct > 0) {
                    $this->db->insert('mn_stock_logs', [
                        'product_id'      => $panel_product->id,
                        'wp_product_id'   => $item->product_id,
                        'stock_type'      => 'virtual',
                        'change_type'     => 'decrease',
                        'quantity_before' => $virtual_before,
                        'quantity_change' => -$virtual_deduct,
                        'quantity_after'  => $virtual_after,
                        'reference_type'  => 'order',
                        'reference_id'    => $order->order_id,
                        'notes'           => 'سفارش WC #' . $order->order_id . ' (کسر از موجودی مجازی)',
                    ]);
                }

                $stock_before  = $real_before + $virtual_before;
                $stock_after   = $real_after  + $virtual_after;
                $stock_updated = 1;

                $result['stock_updated']++;
            }

            // ثبت فاکتور فروش
            $this->db->insert('mn_wc_sales', [
                'wc_order_id'      => $order->order_id,
                'wc_order_item_id' => $item->order_item_id,
                'product_id'       => $panel_product->id,
                'wp_product_id'    => $item->product_id,
                'customer_name'    => $customer_name ?: null,
                'customer_phone'   => $customer_phone,
                'customer_email'   => $customer_email,
                'product_title'    => $item->product_name,
                'quantity'         => $qty,
                'unit_price'       => floatval($item->unit_price),
                'total_price'      => floatval($item->unit_price) * $qty,
                'order_total'      => floatval($order->order_total ?? 0),
                'order_status'     => ltrim($order->order_status ?? '', 'wc-'),
                'order_date'       => $order_date,
                'stock_updated'    => $stock_updated,
                'stock_before'     => $stock_before,
                'stock_after'      => $stock_after,
            ]);
        }

        return $result;
    }

    // ════════════════════════════════════════
    // دریافت آیتم‌های سفارش
    // ════════════════════════════════════════

    private function fetch_order_items($order_id) {
        $pfx = $this->wpdb->prefix;

        return $this->wp->get_results("
            SELECT
                oi.order_item_id,
                oi.order_item_name                                              AS product_name,
                MAX(CASE WHEN oim.meta_key = '_product_id'   THEN oim.meta_value END) AS product_id,
                MAX(CASE WHEN oim.meta_key = '_variation_id' THEN oim.meta_value END) AS variation_id,
                MAX(CASE WHEN oim.meta_key = '_qty'          THEN oim.meta_value END) AS qty,
                MAX(CASE WHEN oim.meta_key = '_line_subtotal'THEN oim.meta_value END) AS line_subtotal,
                MAX(CASE WHEN oim.meta_key = '_line_total'   THEN oim.meta_value END) AS line_total,
                (
                    SELECT pm2.meta_value FROM {$pfx}postmeta pm2
                    WHERE pm2.post_id = MAX(CASE WHEN oim.meta_key = '_product_id' THEN CAST(oim.meta_value AS UNSIGNED) END)
                      AND pm2.meta_key = '_sku' LIMIT 1
                ) AS sku
            FROM {$pfx}woocommerce_order_items oi
            INNER JOIN {$pfx}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
            WHERE oi.order_id   = ?
              AND oi.order_item_type = 'line_item'
              AND oim.meta_key IN ('_product_id','_variation_id','_qty','_line_subtotal','_line_total')
            GROUP BY oi.order_item_id
        ", [$order_id]);
    }

    // ════════════════════════════════════════
    // پیدا کردن محصول در پنل
    // ════════════════════════════════════════

    private function find_panel_product($wp_product_id, $sku) {
        if ($wp_product_id) {
            $p = $this->db->get_row(
                "SELECT id, manage_stock, stock_quantity, real_stock_quantity FROM mn_products WHERE wp_product_id = ? LIMIT 1",
                [intval($wp_product_id)]
            );
            if ($p) return $p;
        }

        if ($sku) {
            $p = $this->db->get_row(
                "SELECT id, manage_stock, stock_quantity, real_stock_quantity FROM mn_products WHERE sku = ? LIMIT 1",
                [$sku]
            );
            if ($p) return $p;
        }

        return null;
    }

    // ════════════════════════════════════════
    // unit_price از line_total / qty
    // ════════════════════════════════════════

    private function calc_unit_price($item) {
        $qty   = max(1, intval($item->qty));
        $total = floatval($item->line_total ?? $item->line_subtotal ?? 0);
        return $qty > 0 ? round($total / $qty, 2) : $total;
    }
}
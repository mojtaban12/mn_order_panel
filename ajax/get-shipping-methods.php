<?php
/**
 * MN Order Panel - Get WooCommerce Shipping Methods
 * دریافت روش‌های حمل و نقل از دیتابیس وردپرس/ووکامرس
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/wp-bridge.php';

try {
    $bridge = MN_WP_Bridge::get_instance();
    $prefix = $bridge->wpdb->prefix;

    // ── دریافت همه زون‌ها و متدهای فعال ──────────────────────────
    // zone_id = 0 → "بقیه دنیا" (پیش‌فرض ووکامرس)
    // zone_id > 0 → زون‌های تعریف‌شده

    $methods_rows = $bridge->get_results("
        SELECT
            zm.instance_id,
            zm.method_id,
            zm.method_order,
            zm.is_enabled,
            COALESCE(z.zone_name, 'پیش‌فرض') AS zone_name,
            zm.zone_id
        FROM {$prefix}woocommerce_shipping_zone_methods zm
        LEFT JOIN {$prefix}woocommerce_shipping_zones z
               ON zm.zone_id = z.zone_id
        WHERE zm.is_enabled = 1
        ORDER BY zm.zone_id ASC, zm.method_order ASC
    ");

    $shipping_options = [];

    foreach ($methods_rows as $row) {
        $instance_id = (int) $row->instance_id;
        $method_id   = $row->method_id;   // flat_rate | free_shipping | local_pickup | …

        // تنظیمات از wp_options: woocommerce_{method_id}_{instance_id}_settings
        $option_key = "woocommerce_{$method_id}_{$instance_id}_settings";
        $raw = $bridge->get_var(
            "SELECT option_value FROM {$prefix}options WHERE option_name = ?",
            [$option_key]
        );

        if (!$raw) continue;

        $settings = @unserialize($raw);
        if (!is_array($settings)) continue;

        // بررسی enabled در settings
        $enabled = ($settings['enabled'] ?? 'yes') === 'yes';
        if (!$enabled) continue;

        // عنوان نمایشی
        $title = $settings['title'] ?? ucwords(str_replace('_', ' ', $method_id));

        // محاسبه قیمت
        $cost = 0;
        switch ($method_id) {
            case 'flat_rate':
                // هزینه می‌تونه عبارت ریاضی ساده باشه مثل "50000 * [qty]"
                // ما فقط عدد ثابت رو در نظر می‌گیریم
                $raw_cost = $settings['cost'] ?? '0';
                $cost = (float) preg_replace('/[^0-9.]/', '', $raw_cost);
                break;

            case 'free_shipping':
                $cost = 0;
                break;

            case 'local_pickup':
                $raw_cost = $settings['cost'] ?? '0';
                $cost = (float) preg_replace('/[^0-9.]/', '', $raw_cost);
                break;

            default:
                // سایر متدها — سعی می‌کنیم cost بخونیم
                $raw_cost = $settings['cost'] ?? '0';
                $cost = (float) preg_replace('/[^0-9.]/', '', $raw_cost);
                break;
        }

        $shipping_options[] = [
            'instance_id'  => $instance_id,
            'method_id'    => $method_id,
            'title'        => $title,
            'zone_name'    => $row->zone_name,
            'cost'         => $cost,
            'cost_formatted' => number_format($cost),
            'is_free'      => ($cost == 0),
        ];
    }

    echo json_encode([
        'success' => true,
        'methods' => $shipping_options,
        'count'   => count($shipping_options),
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطا در دریافت روش‌های حمل: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    error_log('get-shipping-methods error: ' . $e->getMessage());
}

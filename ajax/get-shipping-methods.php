<?php
/**
 * MN Order Panel - Get WooCommerce Shipping Methods (Zone-aware)
 * دریافت روش‌های حمل و نقل بر اساس استان انتخاب‌شده
 *
 * پارامترها:
 *   state_name  → نام فارسی استان (برای match با zone_name)
 *   weight      → وزن کل سبد (گرم) برای محاسبه wbs
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/wp-bridge.php';

// ════════════════════════════════════════════════════════════════
//  توابع کمکی
// ════════════════════════════════════════════════════════════════

/**
 * پیدا کردن zone_id متناسب با استان انتخاب‌شده
 * ابتدا با zone_name match می‌کنه، اگه نشد zone 0 (REST_OF_WORLD)
 */
function find_zone_for_state(object $bridge, string $prefix, string $state_name): ?int {
    if (!$state_name) return null;

    // نرمالایز کردن اسم استان برای مقایسه
    $clean = trim($state_name);

    // جستجو با مطابقت کامل یا جزئی در zone_name
    $row = $bridge->get_row(
        "SELECT zone_id FROM {$prefix}woocommerce_shipping_zones
         WHERE zone_name = ? OR zone_name LIKE ?
         ORDER BY zone_order ASC LIMIT 1",
        [$clean, '%' . $clean . '%']
    );

    return $row ? (int)$row->zone_id : null;
}

/**
 * دریافت settings یک shipping method
 * چند فرمت مختلف رو امتحان می‌کنه (برای پشتیبانی از پلاگین‌ها)
 */
function get_method_settings(object $bridge, string $prefix, string $method_id, int $instance_id): ?array {
    // فرمت‌های مختلفی که پلاگین‌ها استفاده می‌کنن
    $candidate_keys = [
        "woocommerce_{$method_id}_{$instance_id}_settings",   // استاندارد WC
        "{$method_id}_{$instance_id}_settings",
        "woocommerce_{$method_id}{$instance_id}_settings",
        "{$method_id}{$instance_id}",
        "{$method_id}_{$instance_id}",
    ];

    foreach ($candidate_keys as $key) {
        $raw = $bridge->get_var(
            "SELECT option_value FROM {$prefix}options WHERE option_name = ?",
            [$key]
        );
        if ($raw) {
            $parsed = @unserialize($raw);
            return is_array($parsed) ? $parsed : null;
        }
    }
    return null;
}

/**
 * پردازش روش wbs (WooCommerce Weight Based Shipping)
 * این پلاگین settings رو در table جداگانه یا option key خاص خودش نگه می‌داره
 */
function get_wbs_info(object $bridge, string $prefix, int $instance_id, float $weight_grams): array {
    // تلاش برای پیدا کردن rules جداگانه
    $wbs_keys = [
        "wbs_{$instance_id}_shipping_rules",
        "woocommerce_wbs_zone_{$instance_id}",
        "wbs_zone_{$instance_id}",
    ];

    $cost     = null;
    $title    = null;
    $settings = null;

    foreach ($wbs_keys as $k) {
        $raw = $bridge->get_var(
            "SELECT option_value FROM {$prefix}options WHERE option_name = ?", [$k]
        );
        if ($raw) {
            $settings = @unserialize($raw);
            break;
        }
    }

    // اگه settings پیدا شد، cost و title رو استخراج کن
    if (is_array($settings)) {
        $title = $settings['title'] ?? null;

        // بعضی wbs پلاگین‌ها جدول rate دارن
        if (!empty($settings['rates']) && $weight_grams > 0) {
            $weight_kg = $weight_grams / 1000;
            foreach ($settings['rates'] as $rate) {
                $min = floatval($rate['weight_from'] ?? 0);
                $max = floatval($rate['weight_to']   ?? PHP_FLOAT_MAX);
                if ($weight_kg >= $min && $weight_kg <= $max) {
                    $cost = floatval($rate['cost'] ?? 0);
                    break;
                }
            }
        } elseif (isset($settings['cost'])) {
            $cost = floatval($settings['cost']);
        }
    }

    return [
        'title'            => $title ?: 'حمل با محاسبه وزن',
        'cost'             => $cost,          // null = نمی‌دونیم
        'is_weight_based'  => true,
        'weight_grams'     => $weight_grams,
    ];
}

// ════════════════════════════════════════════════════════════════
//  اجرای اصلی
// ════════════════════════════════════════════════════════════════

try {
    $bridge     = MN_WP_Bridge::get_instance();
    $prefix     = $bridge->wpdb->prefix;
    $state_name = trim($_GET['state_name'] ?? '');
    $weight_grams = floatval($_GET['weight'] ?? 0); // وزن سبد به گرم

    // ── پیدا کردن zone مناسب ─────────────────────────────────────
    $specific_zone_id = find_zone_for_state($bridge, $prefix, $state_name);

    // zone_id هایی که می‌خوایم بخونیم
    // - اگه zone خاص پیدا شد: فقط همون (+ zone 0 اگه متدهای مشترک داشت)
    // - اگه نشد: فقط zone 0
    if ($specific_zone_id !== null) {
        $zone_ids = [$specific_zone_id, 0];
    } else {
        $zone_ids = [0];
    }

    $placeholders = implode(',', array_fill(0, count($zone_ids), '?'));

    $methods_rows = $bridge->get_results("
        SELECT
            zm.instance_id,
            zm.method_id,
            zm.zone_id,
            zm.method_order,
            COALESCE(z.zone_name, 'پیش‌فرض') AS zone_name
        FROM {$prefix}woocommerce_shipping_zone_methods zm
        LEFT JOIN {$prefix}woocommerce_shipping_zones z ON zm.zone_id = z.zone_id
        WHERE zm.zone_id IN ({$placeholders})
          AND zm.is_enabled = 1
        ORDER BY zm.zone_id DESC, zm.method_order ASC
    ", $zone_ids);

    // ── پردازش هر متد ────────────────────────────────────────────
    $shipping_options = [];

    foreach ($methods_rows as $row) {
        $instance_id = (int)$row->instance_id;
        $method_id   = $row->method_id;

        // اگه در zone خاص و zone 0 هر دو یه method_id مشابه داشتیم،
        // فقط zone خاص رو نشون بده (zone_id بیشتر اولویت داره)
        if ($specific_zone_id && (int)$row->zone_id === 0) {
            // بررسی کن آیا همین method_id در zone خاص هست
            $already_in_specific = false;
            foreach ($shipping_options as $opt) {
                if ($opt['method_id'] === $method_id) {
                    $already_in_specific = true;
                    break;
                }
            }
            if ($already_in_specific) continue;
        }

        // ── wbs ─────────────────────────────────────────────────
        if ($method_id === 'wbs') {
            $wbs = get_wbs_info($bridge, $prefix, $instance_id, $weight_grams);

            $shipping_options[] = [
                'instance_id'     => $instance_id,
                'method_id'       => 'wbs',
                'title'           => $wbs['title'],
                'zone_name'       => $row->zone_name,
                'cost'            => $wbs['cost'],           // null یا عدد
                'cost_formatted'  => $wbs['cost'] !== null ? number_format($wbs['cost']) : null,
                'is_free'         => $wbs['cost'] === 0.0,
                'is_weight_based' => true,
            ];
            continue;
        }

        // ── بقیه متدها ──────────────────────────────────────────
        $settings = get_method_settings($bridge, $prefix, $method_id, $instance_id);
        if (!$settings) continue;

        $enabled = ($settings['enabled'] ?? 'yes') === 'yes';
        if (!$enabled) continue;

        $title = $settings['title'] ?? ucwords(str_replace('_', ' ', $method_id));

        $cost = 0.0;
        switch ($method_id) {
            case 'flat_rate':
            case 'local_pickup':
                $raw_cost = $settings['cost'] ?? '0';
                $cost = (float) preg_replace('/[^0-9.]/', '', $raw_cost);
                break;
            case 'free_shipping':
                $cost = 0.0;
                break;
            default:
                $raw_cost = $settings['cost'] ?? '0';
                $cost = (float) preg_replace('/[^0-9.]/', '', $raw_cost);
        }

        $shipping_options[] = [
            'instance_id'     => $instance_id,
            'method_id'       => $method_id,
            'title'           => $title,
            'zone_name'       => $row->zone_name,
            'cost'            => $cost,
            'cost_formatted'  => number_format($cost),
            'is_free'         => ($cost == 0),
            'is_weight_based' => false,
        ];
    }

    echo json_encode([
        'success'      => true,
        'methods'      => $shipping_options,
        'count'        => count($shipping_options),
        'matched_zone' => $specific_zone_id,
        'state_name'   => $state_name,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطا: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    error_log('get-shipping-methods: ' . $e->getMessage());
}

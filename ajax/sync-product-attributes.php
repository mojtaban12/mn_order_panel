<?php
/**
 * MN Order Panel - Sync Product Attribute to WooCommerce
 * اگر attribute/term در WC نبود → ایجاد کن، بعد به محصول assign کن
 *
 * POST JSON:
 * {
 *   "product_id": 5,          // پنل product id
 *   "wp_product_id": 1234,    // WC product id
 *   "attribute_name": "رنگ",  // برچسب فارسی
 *   "term_value": "قرمز"      // مقدار
 * }
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'فقط POST']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/wp-bridge.php';
require_once __DIR__ . '/../config/settings.php';

try {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    $product_id    = intval($input['product_id']    ?? 0);
    $wp_product_id = intval($input['wp_product_id'] ?? 0);
    $attr_label    = trim($input['attribute_name']  ?? ''); // برچسب فارسی مثل «رنگ»
    $term_value    = trim($input['term_value']       ?? ''); // مقدار مثل «قرمز»

    if (!$attr_label || !$term_value) {
        throw new Exception('attribute_name و term_value الزامی هستند');
    }

    $wp   = MN_WP_Bridge::get_instance();
    $wpdb = $wp->wpdb;
    $db   = MN_Database::get_instance();

    // ── نرمال‌سازی نام taxonomy ─────────────
    // برای WC taxonomy: pa_ + slug (حروف لاتین کوچک)
    // برای نام‌های فارسی از transliterate ساده استفاده می‌کنیم
    $attr_slug     = make_slug($attr_label);
    $taxonomy      = 'pa_' . $attr_slug;
    $term_slug     = make_slug($term_value);

    // ════════════════════════════════════════
    // مرحله ۱: پیدا یا ایجاد attribute در wc_product_attributes
    // ════════════════════════════════════════
    $attribute_id  = 0;
    $attribute_row = null;

    try {
        $attribute_row = $wp->get_row("
            SELECT attribute_id, attribute_name
            FROM {$wpdb->prefix}wc_product_attributes
            WHERE attribute_name = ? OR attribute_label = ?
            LIMIT 1
        ", [$attr_slug, $attr_label]);
    } catch (Exception $e) { /* جدول ممکنه نباشه */ }

    if ($attribute_row) {
        $attribute_id = intval($attribute_row->attribute_id);
        $taxonomy     = 'pa_' . $attribute_row->attribute_name;
        $attr_slug    = $attribute_row->attribute_name;
    } else {
        // ایجاد attribute جدید
        try {
            $wp->query("
                INSERT INTO {$wpdb->prefix}wc_product_attributes
                    (attribute_name, attribute_label, attribute_type, attribute_orderby, attribute_public)
                VALUES (?, ?, 'select', 'menu_order', 0)
                ON DUPLICATE KEY UPDATE attribute_label = VALUES(attribute_label)
            ", [$attr_slug, $attr_label]);

            $attribute_id = intval($wp->get_var("
                SELECT attribute_id FROM {$wpdb->prefix}wc_product_attributes
                WHERE attribute_name = ? LIMIT 1
            ", [$attr_slug]));
        } catch (Exception $e) {
            // اگر جدول نبود ادامه بده بدون attribute_id
            error_log('wc_product_attributes insert failed: ' . $e->getMessage());
        }

        // ایجاد taxonomy در term_taxonomy (اگر term_taxonomy خالیه)
        $tax_count = $wp->get_var("
            SELECT COUNT(*) FROM {$wpdb->term_taxonomy}
            WHERE taxonomy = ? LIMIT 1
        ", [$taxonomy]);

        if (!$tax_count) {
            // ابتدا یه term placeholder بساز تا taxonomy شناخته بشه
            // WC این رو از options می‌خونه
            $current_attrs = $wp->get_var("
                SELECT option_value FROM {$wpdb->options}
                WHERE option_name = 'wc_attribute_taxonomies' LIMIT 1
            ");
            $attrs_list = $current_attrs ? maybe_unserialize_simple($current_attrs) : [];
            if (is_array($attrs_list)) {
                $new_attr          = new stdClass();
                $new_attr->attribute_id      = $attribute_id;
                $new_attr->attribute_name    = $attr_slug;
                $new_attr->attribute_label   = $attr_label;
                $new_attr->attribute_type    = 'select';
                $new_attr->attribute_orderby = 'menu_order';
                $new_attr->attribute_public  = 0;
                $attrs_list[] = $new_attr;

                $wp->query("
                    UPDATE {$wpdb->options}
                    SET option_value = ?
                    WHERE option_name = 'wc_attribute_taxonomies'
                ", [serialize($attrs_list)]);
            }
        }
    }

    // ════════════════════════════════════════
    // مرحله ۲: پیدا یا ایجاد term
    // ════════════════════════════════════════
    $term_id = 0;

    // جستجوی term با نام دقیق در این taxonomy
    $term_row = $wp->get_row("
        SELECT t.term_id, t.slug
        FROM {$wpdb->terms} t
        INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
        WHERE tt.taxonomy = ? AND t.name = ?
        LIMIT 1
    ", [$taxonomy, $term_value]);

    if ($term_row) {
        $term_id   = intval($term_row->term_id);
        $term_slug = $term_row->slug;
    } else {
        // ── ایجاد term جدید ──────────────────
        // یکتا بودن slug
        $existing_slug = $wp->get_var("
            SELECT COUNT(*) FROM {$wpdb->terms} WHERE slug = ?
        ", [$term_slug]);
        if ($existing_slug) {
            $term_slug = $term_slug . '-' . time();
        }

        $wp->query("
            INSERT INTO {$wpdb->terms} (name, slug, term_group)
            VALUES (?, ?, 0)
        ", [$term_value, $term_slug]);

        $term_id = intval($wp->get_var("SELECT LAST_INSERT_ID()"));

        if (!$term_id) throw new Exception('خطا در ایجاد term در وردپرس');

        // ایجاد term_taxonomy
        $wp->query("
            INSERT INTO {$wpdb->term_taxonomy} (term_id, taxonomy, description, parent, count)
            VALUES (?, ?, '', 0, 0)
            ON DUPLICATE KEY UPDATE term_id = term_id
        ", [$term_id, $taxonomy]);

        // delete transient WC
        $wp->query("
            DELETE FROM {$wpdb->options}
            WHERE option_name LIKE '_transient_wc_%' OR option_name LIKE '_transient_timeout_wc_%'
        ");
    }

    // ════════════════════════════════════════
    // مرحله ۳: assign term به محصول در WC
    // ════════════════════════════════════════
    $assigned = false;
    if ($wp_product_id && $term_id) {
        // چک که قبلاً assign نشده باشه
        $already = $wp->get_var("
            SELECT COUNT(*) FROM {$wpdb->term_relationships}
            WHERE object_id = ? AND term_taxonomy_id = (
                SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy}
                WHERE term_id = ? AND taxonomy = ? LIMIT 1
            )
        ", [$wp_product_id, $term_id, $taxonomy]);

        if (!$already) {
            $tt_id = $wp->get_var("
                SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy}
                WHERE term_id = ? AND taxonomy = ? LIMIT 1
            ", [$term_id, $taxonomy]);

            if ($tt_id) {
                $wp->query("
                    INSERT IGNORE INTO {$wpdb->term_relationships}
                        (object_id, term_taxonomy_id, term_order)
                    VALUES (?, ?, 0)
                ", [$wp_product_id, $tt_id]);

                // update count در term_taxonomy
                $wp->query("
                    UPDATE {$wpdb->term_taxonomy}
                    SET count = count + 1
                    WHERE term_taxonomy_id = ?
                ", [$tt_id]);

                $assigned = true;
            }
        }

        // بروزرسانی _product_attributes در postmeta
        update_wc_product_attributes_meta($wp, $wpdb, $wp_product_id, $taxonomy, $attr_label, $term_id, $attr_slug);
    }

    // ════════════════════════════════════════
    // مرحله ۴: ذخیره در mn_product_extra
    // ════════════════════════════════════════
    $extra_id = null;
    if ($product_id) {
        // چک تکراری
        $exists = $db->get_var("
            SELECT id FROM mn_product_extra
            WHERE product_id = ? AND type = 'attribute'
              AND attribute_name = ? AND value = ?
            LIMIT 1
        ", [$product_id, $taxonomy, $term_value]);

        if (!$exists) {
            $extra_id = $db->insert('mn_product_extra', [
                'product_id'      => $product_id,
                'wp_product_id'   => $wp_product_id ?: null,
                'type'            => 'attribute',
                'attribute_id'    => $attribute_id ?: null,
                'attribute_name'  => $taxonomy,
                'attribute_label' => $attr_label,
                'term_id'         => $term_id ?: null,
                'value'           => $term_value,
            ]);
        } else {
            $extra_id = $exists;
        }
    }

    echo json_encode([
        'success'      => true,
        'message'      => 'ویژگی با موفقیت ذخیره شد' . ($assigned ? ' و به محصول وردپرس اضافه شد' : ''),
        'attribute_id' => $attribute_id,
        'term_id'      => $term_id,
        'taxonomy'     => $taxonomy,
        'extra_id'     => $extra_id,
        'assigned_wc'  => $assigned,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'file'    => basename($e->getFile()),
        'line'    => $e->getLine(),
    ], JSON_UNESCAPED_UNICODE);
    error_log('sync-product-attributes: ' . $e->getMessage());
}

// ════════════════════════════════════════
// توابع کمکی
// ════════════════════════════════════════

/**
 * بروزرسانی _product_attributes در postmeta محصول
 */
function update_wc_product_attributes_meta($wp, $wpdb, $wp_product_id, $taxonomy, $attr_label, $term_id, $attr_name) {
    $current = $wp->get_var("
        SELECT meta_value FROM {$wpdb->postmeta}
        WHERE post_id = ? AND meta_key = '_product_attributes' LIMIT 1
    ", [$wp_product_id]);

    $attrs = $current ? maybe_unserialize_simple($current) : [];
    if (!is_array($attrs)) $attrs = [];

    // اضافه یا بروزرسانی این attribute
    if (!isset($attrs[$taxonomy])) {
        $attrs[$taxonomy] = [
            'name'         => $taxonomy,
            'value'        => '',
            'position'     => count($attrs),
            'is_visible'   => 1,
            'is_variation' => 0,
            'is_taxonomy'  => 1,
        ];
    }

    $serialized = serialize($attrs);

    $exists = $wp->get_var("
        SELECT COUNT(*) FROM {$wpdb->postmeta}
        WHERE post_id = ? AND meta_key = '_product_attributes'
    ", [$wp_product_id]);

    if ($exists) {
        $wp->query("
            UPDATE {$wpdb->postmeta}
            SET meta_value = ?
            WHERE post_id = ? AND meta_key = '_product_attributes'
        ", [$serialized, $wp_product_id]);
    } else {
        $wp->query("
            INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
            VALUES (?, '_product_attributes', ?)
        ", [$wp_product_id, $serialized]);
    }
}

/**
 * unserialize ساده بدون نیاز به WordPress
 */
function maybe_unserialize_simple($data) {
    if (!is_string($data)) return $data;
    if (substr($data, 0, 2) === 'a:' || substr($data, 0, 2) === 'O:') {
        $result = @unserialize($data);
        return $result !== false ? $result : $data;
    }
    return $data;
}

/**
 * ساخت slug از متن (فارسی/لاتین)
 */
function make_slug($text) {
    // حروف لاتین → lowercase
    $text = strtolower(trim($text));
    // حروف مجاز: حرف، عدد، خط تیره
    $text = preg_replace('/[^a-z0-9\-_\x{0600}-\x{06FF}]/u', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    $text = trim($text, '-');

    // اگر فارسی بود → transliterate ساده
    if (preg_match('/[\x{0600}-\x{06FF}]/u', $text)) {
        $map = [
            'آ'=>'a','ا'=>'a','ب'=>'b','پ'=>'p','ت'=>'t','ث'=>'s',
            'ج'=>'j','چ'=>'ch','ح'=>'h','خ'=>'kh','د'=>'d','ذ'=>'z',
            'ر'=>'r','ز'=>'z','ژ'=>'zh','س'=>'s','ش'=>'sh','ص'=>'s',
            'ض'=>'z','ط'=>'t','ظ'=>'z','ع'=>'a','غ'=>'gh','ف'=>'f',
            'ق'=>'q','ک'=>'k','گ'=>'g','ل'=>'l','م'=>'m','ن'=>'n',
            'و'=>'v','ه'=>'h','ی'=>'y','ي'=>'y','ة'=>'h','ئ'=>'y',
            ' '=>'-','-'=>'-',
        ];
        $result = '';
        $chars  = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($chars as $c) {
            $result .= $map[$c] ?? (preg_match('/[a-z0-9\-]/i', $c) ? $c : '');
        }
        $text = trim(preg_replace('/-+/', '-', $result), '-');
    }

    return $text ?: 'attr-' . time();
}

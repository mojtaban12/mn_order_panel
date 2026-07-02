<?php
/**
 * MN Order Panel - Product Sync
 * همگام‌سازی محصولات با WooCommerce از طریق wp-load
 *
 * منطق:
 *  - اگر wp_product_id دارد  → فقط عنوان، قیمت، موجودی مجازی update
 *  - اگر ندارد اما SKU دارد  → جستجو در WC، اگر پیدا شد wp_product_id ذخیره + update
 *  - اگر نه wp_product_id نه SKU → محصول جدید create
 */

if (class_exists('MN_Product_Sync')) {
    return;
}

require_once __DIR__ . '/../config/database.php';

class MN_Product_Sync {

    private $db;

    public function __construct() {
        $this->db = MN_Database::get_instance();
    }

    // ════════════════════════════════════════
    // public API
    // ════════════════════════════════════════

    /**
     * سینک یک محصول — wrapper برای ajax
     *
     * @param int $product_id
     * @return array ['success', 'action', 'wp_product_id', 'message', 'execution_time']
     */
    public function sync_single_product($product_id) {
        $start = microtime(true);

        try {
            [$action, $wp_product_id] = $this->sync($product_id);

            return [
                'success'        => true,
                'action'         => $action,
                'wp_product_id'  => $wp_product_id,
                'message'        => $action === 'created'
                    ? 'محصول با موفقیت در WooCommerce ایجاد شد (ID: ' . $wp_product_id . ')'
                    : 'محصول با موفقیت در WooCommerce بروزرسانی شد (ID: ' . $wp_product_id . ')',
                'execution_time' => round(microtime(true) - $start, 2),
            ];

        } catch (Exception $e) {
            return [
                'success'        => false,
                'action'         => null,
                'wp_product_id'  => null,
                'message'        => $e->getMessage(),
                'execution_time' => round(microtime(true) - $start, 2),
            ];
        }
    }

    /**
     * سینک چند محصول
     *
     * @param array $product_ids
     * @return array آمار
     */
    public function sync_multiple($product_ids) {
        $results = [
            'total'   => count($product_ids),
            'created' => 0,
            'updated' => 0,
            'failed'  => 0,
            'errors'  => [],
        ];

        foreach ($product_ids as $id) {
            $r = $this->sync_single_product((int) $id);
            if ($r['success']) {
                $r['action'] === 'created' ? $results['created']++ : $results['updated']++;
            } else {
                $results['failed']++;
                $results['errors'][] = ['id' => $id, 'message' => $r['message']];
            }
        }

        return $results;
    }

    // ════════════════════════════════════════
    // core sync logic
    // ════════════════════════════════════════

    /**
     * منطق اصلی سینک
     *
     * @param int $product_id
     * @return array [string $action, int $wp_product_id]
     * @throws Exception
     */
    private function sync($product_id) {

        // ── دریافت داده محصول ────────────────
        $product = $this->db->get_row("
            SELECT p.*,
                   (SELECT image_url FROM mn_product_images
                    WHERE product_id = p.id AND is_primary = 1
                    LIMIT 1) AS primary_image_url
            FROM mn_products p
            WHERE p.id = ?
        ", [$product_id]);

        if (!$product) {
            throw new Exception("محصول #{$product_id} یافت نشد");
        }

        // ── بارگذاری WordPress ───────────────
        $this->load_wordpress();

        // ── تعیین حالت ──────────────────────
        $wp_id = !empty($product->wp_product_id) ? intval($product->wp_product_id) : null;

        // اگر wp_product_id نداشت ولی SKU داشت → جستجو
        if (!$wp_id && !empty($product->sku)) {
            $found_id = $this->find_product_by_sku($product->sku);
            if ($found_id) {
                $wp_id = $found_id;
                $this->db->update('mn_products', ['wp_product_id' => $wp_id], ['id' => $product_id]);
            }
        }

        if ($wp_id) {
            // ── UPDATE ───────────────────────
            $wp_id = $this->do_update($product, $wp_id);
            $this->mark_synced($product_id, $wp_id);
            $this->write_log($product_id, 'sync_completed', $wp_id, 'بروزرسانی انجام شد');
            return ['updated', $wp_id];

        } else {
            // ── CREATE ───────────────────────
            $wp_id = $this->do_create($product);
            $this->db->update('mn_products', ['wp_product_id' => $wp_id], ['id' => $product_id]);
            $this->mark_synced($product_id, $wp_id);
            $this->sync_extra_images($product_id, $wp_id);
            $this->write_log($product_id, 'sync_completed', $wp_id, 'محصول جدید ایجاد شد');
            return ['created', $wp_id];
        }
    }

    // ════════════════════════════════════════
    // WooCommerce operations
    // ════════════════════════════════════════

    private function do_create($product) {
        $wc_product = new WC_Product_Simple();

        $wc_product->set_name($product->title);
        $wc_product->set_status($product->status === 'active' ? 'publish' : 'draft');
        $wc_product->set_catalog_visibility('visible');

        if (!empty($product->sku)) {
            $wc_product->set_sku($product->sku);
        }

        $this->apply_price($wc_product, $product);
        $this->apply_stock($wc_product, $product);
        $this->apply_dimensions($wc_product, $product);

        // تصویر اصلی
        $image_url = $product->primary_image_url ?: $product->image_url;
        if (!empty($image_url)) {
            $image_id = $this->get_or_upload_image($image_url, $product->title);
            if ($image_id) {
                $wc_product->set_image_id($image_id);
            }
        }

        $wp_id = $wc_product->save();

        if (!$wp_id || is_wp_error($wp_id)) {
            throw new Exception('خطا در ایجاد محصول در WooCommerce');
        }

        return $wp_id;
    }

    private function do_update($product, $wp_id) {
        $wc_product = wc_get_product($wp_id);

        if (!$wc_product) {
            // محصول در WC حذف شده → ایجاد مجدد
            $this->db->update('mn_products', ['wp_product_id' => null], ['id' => $product->id]);
            $product->wp_product_id = null;
            return $this->do_create($product);
        }

        // فقط فیلدهای مجاز برای update
        $wc_product->set_name($product->title);
        $this->apply_price($wc_product, $product);
        $this->apply_stock($wc_product, $product);

        $wc_product->save();

        return $wp_id;
    }

    // ════════════════════════════════════════
    // helper: apply fields
    // ════════════════════════════════════════

    private function apply_price($wc_product, $product) {
        $wc_product->set_regular_price((string) floatval($product->regular_price));

        if (!empty($product->sale_price) && floatval($product->sale_price) > 0) {
            $wc_product->set_sale_price((string) floatval($product->sale_price));
        } else {
            $wc_product->set_sale_price('');
        }
    }

    private function apply_stock($wc_product, $product) {
        $manage = (bool) $product->manage_stock;
        $wc_product->set_manage_stock($manage);

        if ($manage) {
            // موجودی سایت = stock_quantity مجازی (نه real_stock_quantity)
            $qty = $product->stock_quantity !== null ? intval($product->stock_quantity) : 0;
            $wc_product->set_stock_quantity($qty);
            $wc_product->set_stock_status($qty > 0 ? 'instock' : 'outofstock');
        } else {
            $wc_product->set_stock_status($product->stock_status ?? 'instock');
        }
    }

    private function apply_dimensions($wc_product, $product) {
        // وزن: پنل گرم دارد، WooCommerce کیلوگرم می‌خواهد
        if (!empty($product->weight)) {
            $wc_product->set_weight((string) round(floatval($product->weight) / 1000, 3));
        }
        if (!empty($product->length)) $wc_product->set_length((string) floatval($product->length));
        if (!empty($product->width))  $wc_product->set_width((string)  floatval($product->width));
        if (!empty($product->height)) $wc_product->set_height((string) floatval($product->height));
    }

    // ════════════════════════════════════════
    // helper: images
    // ════════════════════════════════════════

    /**
     * ست کردن تصاویر اضافی بعد از create
     */
    private function sync_extra_images($product_id, $wp_id) {
        $images = $this->db->get_results(
            "SELECT * FROM mn_product_images WHERE product_id = ? ORDER BY sort_order, id",
            [$product_id]
        );

        if (count($images) <= 1) return;

        $gallery_ids = [];
        $is_first    = true;
        foreach ($images as $img) {
            $att_id = $this->get_or_upload_image($img->image_url, $img->alt_text ?? '');
            if (!$att_id) continue;

            if ($is_first) {
                // تصویر اول = featured image (قبلاً set شده)
                $is_first = false;
                continue;
            }
            $gallery_ids[] = $att_id;
        }

        if (!empty($gallery_ids)) {
            $wc_product = wc_get_product($wp_id);
            if ($wc_product) {
                $wc_product->set_gallery_image_ids($gallery_ids);
                $wc_product->save();
            }
        }
    }

    /**
     * پیدا کردن attachment موجود یا sideload از URL
     */
    private function get_or_upload_image($url, $alt = '') {
        if (empty($url)) return null;

        // چک کنید آیا قبلاً آپلود شده
        global $wpdb;
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = 'attachment' AND guid = %s LIMIT 1",
            $url
        ));
        if ($existing) return (int) $existing;

        // تصویر محلی پنل (از فولدر images/) → sideload به WP
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = download_url($url);
        if (is_wp_error($tmp)) {
            error_log('download_url failed for ' . $url . ': ' . $tmp->get_error_message());
            return null;
        }

        $file_array = [
            'name'     => basename(parse_url($url, PHP_URL_PATH)),
            'tmp_name' => $tmp,
        ];

        $att_id = media_handle_sideload($file_array, 0, $alt);

        if (is_wp_error($att_id)) {
            @unlink($tmp);
            error_log('media_handle_sideload failed: ' . $att_id->get_error_message());
            return null;
        }

        return $att_id;
    }

    /**
     * پیدا کردن محصول با SKU در WC
     */
    private function find_product_by_sku($sku) {
        $product_id = wc_get_product_id_by_sku($sku);
        return $product_id ? intval($product_id) : null;
    }

    // ════════════════════════════════════════
    // helper: DB
    // ════════════════════════════════════════

    private function mark_synced($product_id, $wp_id) {
        $this->db->update('mn_products', [
            'is_synced'     => 1,
            'wp_product_id' => $wp_id,
            'synced_at'     => date('Y-m-d H:i:s'),
        ], ['id' => $product_id]);
    }

    private function write_log($product_id, $action, $wp_id, $message) {
        try {
            $this->db->insert('mn_sync_log', [
                'entity_type'  => 'product',
                'entity_id'    => $product_id,
                'action'       => $action,
                'wp_entity_id' => $wp_id,
                'message'      => $message,
            ]);
        } catch (Exception $e) {
            error_log('sync_log insert failed: ' . $e->getMessage());
        }
    }

    // ════════════════════════════════════════
    // بارگذاری WordPress
    // ════════════════════════════════════════

    private function load_wordpress() {
        if (function_exists('wc_get_product')) {
            return; // قبلاً لود شده
        }

        if (!class_exists('MN_Settings')) {
            require_once __DIR__ . '/../config/settings.php';
        }

        $wp_load_path = MN_Settings::get('wp_load_path');

        if (!$wp_load_path || !file_exists($wp_load_path)) {
            throw new Exception('مسیر wp-load.php پیکربندی نشده یا وجود ندارد');
        }

        require_once $wp_load_path;

        if (!function_exists('wc_get_product')) {
            throw new Exception('WooCommerce فعال نیست');
        }
    }
}
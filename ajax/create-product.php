<?php
/**
 * MN Order Panel - Create Product Ajax Handler
 * ثبت/ویرایش محصول در دیتابیس پنل
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'فقط درخواست POST مجاز است']);
    exit;
}

session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../includes/class-product.php';
require_once __DIR__ . '/../sync/class-product-sync.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('داده‌های ورودی نامعتبر است');
    }

    validate_product_input($input);

    $panel_user_id = isset($_SESSION['panel_user_id']) ? intval($_SESSION['panel_user_id']) : 1;

    // ============================================
    // آماده‌سازی داده‌های محصول
    // ============================================
    $edit_id = !empty($input['edit_id']) ? intval($input['edit_id']) : null;

    // ── SKU ──────────────────────────────────
    // ویرایش: SKU تغییر نمی‌کند — از DB می‌خونیم
    // ثبت جدید: اگر از WP آمد → همان SKU، وگرنه → auto generate
    if ($edit_id) {
        $existing = $product_model->get($edit_id);
        $sku_value = $existing ? $existing->sku : null; // قفل — تغییر نمی‌کند
    } else {
        if (!empty($input['sku'])) {
            // از WP آمده
            $sku_value = sanitize_text($input['sku']);
        } else {
            // دستی — auto generate
            $sku_value = $product_model->generate_sku();
        }
    }

    // ── wp_product_id ────────────────────────
    // ویرایش: تغییر نمی‌کند — از DB می‌خونیم
    // ثبت: فقط اگر از WP آمده
    if ($edit_id) {
        $wp_product_id_value = $existing ? $existing->wp_product_id : null;
    } else {
        $wp_product_id_value = !empty($input['wp_product_id']) ? intval($input['wp_product_id']) : null;
    }

    $product_data = [
        'wp_product_id'       => $wp_product_id_value,
        'title'               => sanitize_text($input['title']),
        'sku'                 => $sku_value,
        'regular_price'       => floatval($input['regular_price']),
        'purchase_price'      => !empty($input['purchase_price']) ? floatval($input['purchase_price']) : null,
        'sale_price'          => !empty($input['sale_price']) ? floatval($input['sale_price']) : null,
        'discount_percent'    => !empty($input['discount_percent']) ? floatval($input['discount_percent']) : null,
        'stock_quantity'      => isset($input['stock_quantity']) && $input['stock_quantity'] !== '' ? intval($input['stock_quantity']) : null,
        'real_stock_quantity' => isset($input['real_stock_quantity']) && $input['real_stock_quantity'] !== '' ? intval($input['real_stock_quantity']) : null,
        'stock_status'        => in_array($input['stock_status'] ?? '', ['instock','outofstock','onbackorder']) ? $input['stock_status'] : 'instock',
        'manage_stock'        => isset($input['manage_stock']) ? 1 : 0,
        'weight'              => !empty($input['weight']) ? floatval($input['weight']) : null,
        'length'              => !empty($input['length']) ? floatval($input['length']) : null,
        'width'               => !empty($input['width']) ? floatval($input['width']) : null,
        'height'              => !empty($input['height']) ? floatval($input['height']) : null,
        'image_url'           => null, // از mn_product_images مدیریت می‌شه
        'status'              => in_array($input['status'] ?? '', ['active','inactive','draft']) ? $input['status'] : 'active',
        'panel_category_id'   => !empty($input['panel_category_id']) ? intval($input['panel_category_id']) : null,
    ];

    // ============================================
    // آماده‌سازی extras (دسته‌ها و ویژگی‌ها)
    // ============================================
    $extras = [];

    // دسته‌ها
    if (!empty($input['categories']) && is_array($input['categories'])) {
        foreach ($input['categories'] as $cat) {
            if (empty($cat['name'])) continue;
            $extras[] = [
                'type'          => 'category',
                'category_id'   => !empty($cat['id']) ? intval($cat['id']) : null,
                'category_name' => sanitize_text($cat['name']),
                'value'         => sanitize_text($cat['name']),
            ];
        }
    }

    // ویژگی‌ها
    if (!empty($input['attributes']) && is_array($input['attributes'])) {
        foreach ($input['attributes'] as $attr) {
            if (empty($attr['name']) || empty($attr['value'])) continue;

            $taxonomy  = !empty($attr['taxonomy'])   ? sanitize_text($attr['taxonomy']) : null;
            // term_id و attribute_id: string از JS می‌آید، فقط اگر عدد > 0 باشد ذخیره کن
            $term_id   = (isset($attr['term_id'])     && (int)$attr['term_id']     > 0) ? (int)$attr['term_id']     : null;
            $attr_id   = (isset($attr['attribute_id'])&& (int)$attr['attribute_id']> 0) ? (int)$attr['attribute_id']: null;

            // attr['name'] = label فارسی که کاربر تایپ کرده (رنگ)
            // attr['attribute_name'] = slug از WP (color)
            // attr['label'] = label فارسی (معمولاً = name)
            $attr_label = sanitize_text($attr['name']); // همیشه label فارسی

            // attribute_name = slug (بدون pa_)
            if (!empty($attr['attribute_name'])) {
                $attr_slug = sanitize_text($attr['attribute_name']);
            } elseif ($taxonomy) {
                $attr_slug = str_replace('pa_', '', $taxonomy);
            } else {
                $attr_slug = ''; // کاربر دستی تایپ کرده، slug نداریم
            }

            $extras[] = [
                'type'            => 'attribute',
                'attribute_id'    => $attr_id,
                'attribute_name'  => $attr_slug ?: null,
                'attribute_label' => $attr_label,
                'term_id'         => $term_id,
                'value'           => sanitize_text($attr['value']),
            ];
        }
    }

    $product_model = new MN_Product();

    // ============================================
    // آماده‌سازی تصاویر
    // ============================================
    $images = [];
    if (!empty($input['images']) && is_array($input['images'])) {
        foreach ($input['images'] as $i => $img) {
            $url = trim($img['url'] ?? '');
            if (empty($url)) continue;
            $images[] = [
                'url'        => $url,
                'alt'        => sanitize_text($img['alt'] ?? ''),
                'is_primary' => ($i === 0) ? 1 : 0,
            ];
        }
    }

    // ============================================
    // ثبت یا ویرایش
    // ============================================

    if ($edit_id) {
        // SKU تکراری چک نمی‌شه چون SKU قفل شده و از DB آمده

        $result = $product_model->update($edit_id, $product_data, $extras);

        if (!$result) {
            throw new Exception('خطا در بروزرسانی محصول');
        }

        // ذخیره تصاویر (replace کامل)
        if (!empty($images)) {
            $product_model->save_images($edit_id, $images, true);
        }

        $product    = $product_model->get($edit_id);
        $all_images = $product_model->get_images($edit_id);

        // ── auto-sync اگر wp_product_id دارد ──
        $sync_result = null;
        if (!empty($product->wp_product_id)) {
            $sync_result = try_auto_sync($edit_id);
        }

        $message = '✓ محصول با موفقیت ویرایش شد';
        if ($sync_result) {
            $message .= $sync_result['success']
                ? ' و با وردپرس همگام شد'
                : ' (همگام‌سازی: ' . $sync_result['message'] . ')';
        }

        echo json_encode([
            'success' => true,
            'message' => $message,
            'product' => format_product_response($product),
            'images'  => format_images_response($all_images),
            'synced'  => $sync_result['success'] ?? false,
            'mode'    => 'edit'
        ], JSON_UNESCAPED_UNICODE);

    } else {
        // SKU از بالا generate شده — فقط چک تکراری نبودن
        if ($sku_value && $product_model->sku_exists($sku_value)) {
            $sku_value = $product_model->generate_sku();
            $product_data['sku'] = $sku_value;
        }

        $product_id = $product_model->create($product_data, $extras, $panel_user_id);

        if (!$product_id) {
            throw new Exception('خطا در ثبت محصول');
        }

        // ذخیره تصاویر
        if (!empty($images)) {
            $product_model->save_images($product_id, $images, true);
        }

        $product    = $product_model->get($product_id);
        $stats      = $product_model->get_statistics();
        $all_images = $product_model->get_images($product_id);

        // ── auto-sync اگر wp_product_id دارد (محصول از WP آمده) ──
        $sync_result = null;
        if (!empty($product->wp_product_id)) {
            $sync_result = try_auto_sync($product_id);
        }

        $message = '✓ محصول با موفقیت ثبت شد';
        if ($sync_result) {
            $message .= $sync_result['success']
                ? ' و با وردپرس همگام شد'
                : ' (همگام‌سازی: ' . $sync_result['message'] . ')';
        }

        echo json_encode([
            'success' => true,
            'message' => $message,
            'product' => format_product_response($product),
            'images'  => format_images_response($all_images),
            'synced'  => $sync_result['success'] ?? false,
            'stats'   => [
                'total_products'  => intval($stats->total_products),
                'active_products' => intval($stats->active_products),
                'today_added'     => intval($stats->today_added),
            ],
            'mode' => 'create'
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);

    error_log('Create product error: ' . $e->getMessage());
}

// ============================================
// توابع کمکی
// ============================================

/**
 * تلاش برای sync خودکار با وردپرس
 * اگر wp-load در دسترس نبود یا خطا داد، فقط لاگ می‌کند
 */
function try_auto_sync($product_id) {
    try {
        $sync   = new MN_Product_Sync();
        $result = $sync->sync_single_product($product_id);
        return $result;
    } catch (Exception $e) {
        error_log('auto-sync failed for product #' . $product_id . ': ' . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function validate_product_input($input) {
    if (empty($input['title'])) {
        throw new Exception('عنوان محصول الزامی است');
    }

    if (strlen(trim($input['title'])) < 2) {
        throw new Exception('عنوان محصول باید حداقل ۲ کاراکتر باشد');
    }

    if (!isset($input['regular_price']) || floatval($input['regular_price']) <= 0) {
        throw new Exception('قیمت فروش محصول الزامی و باید بیشتر از صفر باشد');
    }

    if (!empty($input['sale_price']) && floatval($input['sale_price']) >= floatval($input['regular_price'])) {
        throw new Exception('قیمت تخفیف‌خورده باید کمتر از قیمت اصلی باشد');
    }

    if (!empty($input['discount_percent'])) {
        $d = floatval($input['discount_percent']);
        if ($d < 0 || $d > 100) {
            throw new Exception('درصد تخفیف باید بین ۰ تا ۱۰۰ باشد');
        }
    }

    if (isset($input['stock_quantity']) && $input['stock_quantity'] !== '' && intval($input['stock_quantity']) < 0) {
        throw new Exception('موجودی مجازی نمی‌تواند منفی باشد');
    }

    if (isset($input['real_stock_quantity']) && $input['real_stock_quantity'] !== '' && intval($input['real_stock_quantity']) < 0) {
        throw new Exception('موجودی واقعی نمی‌تواند منفی باشد');
    }
}

function format_images_response($images) {
    if (empty($images)) return [];
    return array_map(function($img) {
        return [
            'id'         => intval($img->id),
            'url'        => $img->image_url,
            'alt'        => $img->alt_text ?? '',
            'is_primary' => (bool) $img->is_primary,
            'sort_order' => intval($img->sort_order),
        ];
    }, $images);
}

function format_product_response($product) {
    if (!$product) return null;
    return [
        'id'                  => intval($product->id),
        'title'               => $product->title,
        'sku'                 => $product->sku,
        'regular_price'       => floatval($product->regular_price),
        'regular_price_fmt'   => number_format($product->regular_price),
        'sale_price'          => $product->sale_price ? floatval($product->sale_price) : null,
        'stock_quantity'      => $product->stock_quantity !== null ? intval($product->stock_quantity) : null,
        'real_stock_quantity' => $product->real_stock_quantity !== null ? intval($product->real_stock_quantity) : null,
        'stock_status'        => $product->stock_status,
        'weight'              => $product->weight ? floatval($product->weight) : null,
        'status'              => $product->status,
        'is_synced'           => (bool) $product->is_synced,
        'created_at'          => $product->created_at,
    ];
}

function sanitize_text($value) {
    return trim(strip_tags($value ?? ''));
}
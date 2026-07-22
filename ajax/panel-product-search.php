<?php
/**
 * MN Order Panel - Panel Product Search Ajax Handler
 * جستجوی محصولات از دیتابیس پنل (mn_products)
 * این فایل جایگزین product-search.php (وردپرس) شده
 *
 * ویژگی: نرمالایز‌سازی خودکار کاراکترهای عربی/فارسی در جستجو
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['q'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'پارامتر جستجو الزامی است'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../config/database.php';

// ════════════════════════════════════════════════════════════════
//  توابع نرمالایز‌سازی عربی ↔ فارسی
// ════════════════════════════════════════════════════════════════

/**
 * نرمالایز ترم سرچ به فارسی استاندارد
 * ك→ک | ي→ی | ى→ی | ة→ه | اعداد عربی→فارسی
 * (فقط برای ترم سرچ، دیتابیس دست‌نخورده می‌مونه)
 */
function normalize_search_term(string $s): string {
    $ar = ['ك', 'ي', 'ى', 'ة', 'ؤ', 'ئ',
           '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '٠'];
    $fa = ['ک', 'ی', 'ی', 'ه', 'و', 'ی',
           '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '۰'];
    return str_replace($ar, $fa, $s);
}

/**
 * برمی‌گردونه یه عبارت SQL که ستون داده‌شده رو نرمالایز می‌کنه
 * مثال: normalize_col_sql('p.title')
 *   → REPLACE(REPLACE(REPLACE(REPLACE(p.title,'ي','ی'),'ك','ک'),'ى','ی'),'ة','ه')
 * اینطوری حتی مقادیر مخلوط (مثل کاليکو) هم پیدا می‌شن
 */
function normalize_col_sql(string $col): string {
    // جفت‌های [عربی، فارسی] که باید جایگزین بشن
    $pairs = [
        ['ي', 'ی'],
        ['ك', 'ک'],
        ['ى', 'ی'],
        ['ة', 'ه'],
        ['ؤ', 'و'],
        ['ئ', 'ی'],
    ];
    $expr = $col;
    foreach ($pairs as [$ar, $fa]) {
        $expr = "REPLACE({$expr}, '{$ar}', '{$fa}')";
    }
    return $expr;
}

// ════════════════════════════════════════════════════════════════

try {
    $search_term = trim($_GET['q']);
    $page        = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page    = 30;
    $offset      = ($page - 1) * $per_page;

    if (mb_strlen($search_term) < 2) {
        echo json_encode([
            'success'    => true,
            'items'      => [],
            'pagination' => ['more' => false],
            'message'    => 'حداقل 2 کاراکتر وارد کنید'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $db        = MN_Database::get_instance();
    $id_search = intval($search_term);

    // ترم سرچ رو به فارسی استاندارد نرمالایز می‌کنیم
    // (چه با کیبورد فارسی تایپ شده باشه چه عربی)
    $norm_term = normalize_search_term($search_term);
    $like      = '%' . $norm_term . '%';

    // در SQL، ستون title/sku هم نرمالایز می‌شه قبل از مقایسه
    // → حتی مقادیر مخلوط مثل «کاليکو» هم پیدا می‌شن
    $title_norm = normalize_col_sql('p.title');
    $sku_norm   = normalize_col_sql('p.sku');

    $params = [$like, $like];
    $where  = "({$title_norm} LIKE ? OR {$sku_norm} LIKE ?";

    if ($id_search > 0) {
        $where   .= " OR p.id = ? OR p.wp_product_id = ?";
        $params[] = $id_search;
        $params[] = $id_search;
    }
    $where .= ")";

    // فقط محصولات فعال
    $where .= " AND p.status = 'active'";

    $fetch_limit = $per_page + 1; // یه عدد بیشتر برای تشخیص has_more

    $sql = "
        SELECT
            p.id            AS panel_id,
            p.wp_product_id,
            p.title,
            p.sku,
            p.regular_price,
            p.sale_price,
            p.discount_percent,
            p.stock_quantity,
            p.stock_status,
            p.weight,
            p.image_url
        FROM mn_products p
        WHERE {$where}
        ORDER BY p.title ASC
        LIMIT ? OFFSET ?
    ";

    array_push($params, $fetch_limit, $offset);
    $rows = $db->get_results($sql, $params);

    $has_more = count($rows) > $per_page;
    if ($has_more) {
        array_pop($rows);
    }

    $items = [];
    foreach ($rows as $row) {
        // قیمت نمایشی: sale_price اگه وجود داشت، وگرنه regular_price
        $price = ($row->sale_price && floatval($row->sale_price) > 0)
            ? floatval($row->sale_price)
            : floatval($row->regular_price);

        $in_stock = ($row->stock_status === 'instock');

        // تصویر: از image_url مستقیم استفاده می‌کنیم (آدرس کامل یا نسبی)
        $image = $row->image_url ?: '';

        // شناسه‌ای که در سفارش ذخیره می‌شه:
        // ترجیحاً wp_product_id (برای sync با ووکامرس)، وگرنه panel id
        $order_id = ($row->wp_product_id > 0) ? intval($row->wp_product_id) : intval($row->panel_id);

        $items[] = [
            'id'              => $order_id,
            'panel_id'        => intval($row->panel_id),
            'text'            => $row->title,
            'sku'             => $row->sku ?: '-',
            'price'           => $price,
            'price_formatted' => number_format($price),
            'regular_price'   => floatval($row->regular_price),
            'sale_price'      => floatval($row->sale_price ?? 0),
            'discount_percent'=> floatval($row->discount_percent ?? 0),
            'stock_quantity'  => $row->stock_quantity,
            'stock_status'    => $row->stock_status,
            'stock_text'      => get_panel_stock_text($row->stock_status, $row->stock_quantity),
            'in_stock'        => $in_stock,
            'weight'          => floatval($row->weight ?? 0),
            'image'           => $image,
            'image_source'    => 'panel', // برای تشخیص در JS
        ];
    }

    echo json_encode([
        'success'     => true,
        'items'       => $items,
        'pagination'  => [
            'more' => $has_more,
            'page' => $page,
        ],
        'total_found' => count($items),
        'source'      => 'panel',
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطا در جستجو',
        'error'   => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);

    error_log('Panel product search error: ' . $e->getMessage());
}

function get_panel_stock_text($stock_status, $stock_quantity) {
    if ($stock_status !== 'instock') {
        return '❌ ناموجود';
    }

    if ($stock_quantity === null || $stock_quantity === '') {
        return '✓ موجود';
    }

    $qty = intval($stock_quantity);
    if ($qty === 0) {
        return '❌ ناموجود';
    } elseif ($qty < 5) {
        return "⚠️ {$qty} عدد باقی‌مانده";
    } else {
        return "✓ {$qty} عدد موجود";
    }
}

<?php
/**
 * MN Order Panel - صفحه ثبت/ویرایش محصول
 */

require_once __DIR__ . '/../includes/auth-check.php';
require_once '../config/settings.php';

$current_user    = get_panel_user();
$currency_symbol = MN_Settings::get('currency_symbol', 'تومان');
$panel_title     = MN_Settings::get('panel_title', 'پنل ثبت سفارش');

// حالت ویرایش
$edit_id      = isset($_GET['id']) ? intval($_GET['id']) : null;
$edit_product = null;
$edit_extras  = [];

if ($edit_id) {
    require_once __DIR__ . '/../includes/class-product.php';
    $pm = new MN_Product();
    $edit_product = $pm->get($edit_id);
    if ($edit_product) {
        $edit_extras  = $pm->get_extras($edit_id);
        $edit_images  = $pm->get_images($edit_id);
    }
}

$page_title = ($edit_id ? 'ویرایش محصول' : 'ثبت محصول جدید') . ' - ' . $panel_title;

$extra_css = '
<link rel="stylesheet" href="../assets/css/create-product.css">  ';  


ob_start();
?>

<div id="product-alert"></div>

<!-- ── جستجو و وارد کردن از وردپرس ── -->
<div class="wp-search-card" id="wp-search-card">
    <div class="wp-search-card-title">
        🔗 وارد کردن اطلاعات از وردپرس
        <span style="font-weight:400;font-size:11px;color:#3b82f6;">(اختیاری — جستجو کنید تا فرم پر شود)</span>
    </div>
    <div class="wp-search-row">
        <input type="text" id="wp-search-input" class="form-control"
               placeholder="نام محصول، SKU یا شناسه وردپرس را وارد کنید..."
               autocomplete="off">
        <button type="button" id="wp-search-clear-btn"
                style="background:none;border:none;color:#9ca3af;font-size:20px;cursor:pointer;padding:0 4px;display:none;">×</button>
    </div>
    <div class="wp-search-dropdown" id="wp-search-dropdown"></div>
    <div class="wp-selected-bar" id="wp-selected-bar">
        <span>✅ اطلاعات محصول «<strong id="wp-selected-name"></strong>» وارد شد</span>
        <span style="color:#6b7280;">WP ID: <strong id="wp-selected-id"></strong></span>
        <button class="wp-clear" id="wp-clear-btn" title="پاک کردن">×</button>
    </div>
</div>

<form id="product-form" novalidate>
    <?php if ($edit_id): ?>
        <input type="hidden" name="edit_id" value="<?php echo $edit_id; ?>">
    <?php endif; ?>

    <div class="product-form-wrapper">

        <!-- ── ستون اصلی ─────────────────────────────── -->
        <div class="main-col">

            <!-- اطلاعات پایه -->
            <div class="form-card">
                <div class="form-card-title">
                    <span class="icon">📦</span>
                    اطلاعات پایه محصول
                </div>

                <div class="form-group">
                    <label for="title">عنوان محصول <span class="required">*</span></label>
                    <input type="text" id="title" name="title" class="form-control"
                           placeholder="نام کامل محصول را وارد کنید..."
                           value="<?php echo htmlspecialchars($edit_product->title ?? ''); ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="sku">SKU / کد محصول
                            <?php if ($edit_id): ?>
                                <span class="hint" style="color:#ef4444;">🔒 غیرقابل تغییر</span>
                            <?php else: ?>
                                <span class="hint">خودکار تولید می‌شود</span>
                            <?php endif; ?>
                        </label>
                        <input type="text" id="sku" name="sku" class="form-control"
                               placeholder="P_30000 — خودکار"
                               value="<?php echo htmlspecialchars($edit_product->sku ?? ''); ?>"
                               readonly
                               style="background:#f3f4f6;color:#6b7280;cursor:default;">
                    </div>
                    <div class="form-group">
                        <label for="wp_product_id">شناسه وردپرس
                            <span class="hint" style="color:#ef4444;">🔒 غیرقابل تغییر</span>
                        </label>
                        <input type="number" id="wp_product_id" name="wp_product_id" class="form-control"
                               placeholder="از وردپرس تنظیم می‌شود"
                               value="<?php echo $edit_product->wp_product_id ?? ''; ?>"
                               readonly
                               style="background:#f3f4f6;color:#6b7280;cursor:default;">
                    </div>
                </div>
            </div>

            <!-- قیمت‌گذاری -->
            <div class="form-card">
                <div class="form-card-title">
                    <span class="icon">💰</span>
                    قیمت‌گذاری
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="regular_price">قیمت فروش <span class="required">*</span></label>
                        <div class="input-with-suffix">
                            <input type="number" id="regular_price" name="regular_price" class="form-control"
                                   placeholder="0" min="0" step="1"
                                   value="<?php echo $edit_product->regular_price ?? ''; ?>" required>
                            <span class="input-suffix"><?php echo $currency_symbol; ?></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="purchase_price">قیمت خرید <span class="hint">(حسابداری)</span></label>
                        <div class="input-with-suffix">
                            <input type="number" id="purchase_price" name="purchase_price" class="form-control"
                                   placeholder="0" min="0" step="1"
                                   value="<?php echo $edit_product->purchase_price ?? ''; ?>">
                            <span class="input-suffix"><?php echo $currency_symbol; ?></span>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="sale_price">قیمت تخفیف‌خورده</label>
                        <div class="input-with-suffix">
                            <input type="number" id="sale_price" name="sale_price" class="form-control"
                                   placeholder="0" min="0" step="1"
                                   value="<?php echo $edit_product->sale_price ?? ''; ?>">
                            <span class="input-suffix"><?php echo $currency_symbol; ?></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="discount_percent">درصد تخفیف</label>
                        <div class="input-with-suffix">
                            <input type="number" id="discount_percent" name="discount_percent" class="form-control"
                                   placeholder="0" min="0" max="100" step="0.01"
                                   value="<?php echo $edit_product->discount_percent ?? ''; ?>">
                            <span class="input-suffix">%</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- موجودی -->
            <div class="form-card">
                <div class="form-card-title">
                    <span class="icon">📊</span>
                    موجودی
                </div>

                <div class="toggle-group" style="margin-bottom:14px;">
                    <label class="toggle-switch">
                        <input type="checkbox" id="manage_stock" name="manage_stock"
                               <?php echo (!$edit_product || $edit_product->manage_stock) ? 'checked' : ''; ?>>
                        <span class="toggle-slider"></span>
                    </label>
                    <label for="manage_stock">مدیریت موجودی فعال باشد</label>
                </div>

                <div id="stock-fields">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="stock_quantity">موجودی مجازی <span class="hint">(فروش آنلاین)</span></label>
                            <input type="number" id="stock_quantity" name="stock_quantity" class="form-control"
                                   placeholder="0" min="0"
                                   value="<?php echo $edit_product->stock_quantity ?? ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="real_stock_quantity">موجودی واقعی <span class="hint">(فروش حضوری)</span></label>
                            <input type="number" id="real_stock_quantity" name="real_stock_quantity" class="form-control"
                                   placeholder="0" min="0"
                                   value="<?php echo $edit_product->real_stock_quantity ?? ''; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="stock_status">وضعیت موجودی</label>
                        <select id="stock_status" name="stock_status" class="form-control">
                            <option value="instock"     <?php echo ($edit_product->stock_status ?? 'instock') === 'instock'     ? 'selected' : ''; ?>>موجود</option>
                            <option value="outofstock"  <?php echo ($edit_product->stock_status ?? '') === 'outofstock'         ? 'selected' : ''; ?>>ناموجود</option>
                            <option value="onbackorder" <?php echo ($edit_product->stock_status ?? '') === 'onbackorder'        ? 'selected' : ''; ?>>پیش‌فروش</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- ابعاد و وزن -->
            <div class="form-card">
                <div class="form-card-title">
                    <span class="icon">📐</span>
                    ابعاد و وزن
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="weight">وزن</label>
                        <div class="input-with-suffix">
                            <input type="number" id="weight" name="weight" class="form-control"
                                   placeholder="0" min="0" step="0.01"
                                   value="<?php echo $edit_product->weight ?? ''; ?>">
                            <span class="input-suffix">گرم</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="length">طول</label>
                        <div class="input-with-suffix">
                            <input type="number" id="length" name="length" class="form-control"
                                   placeholder="0" min="0" step="0.1"
                                   value="<?php echo $edit_product->length ?? ''; ?>">
                            <span class="input-suffix">سانتی‌متر</span>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="width">عرض</label>
                        <div class="input-with-suffix">
                            <input type="number" id="width" name="width" class="form-control"
                                   placeholder="0" min="0" step="0.1"
                                   value="<?php echo $edit_product->width ?? ''; ?>">
                            <span class="input-suffix">سانتی‌متر</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="height">ارتفاع</label>
                        <div class="input-with-suffix">
                            <input type="number" id="height" name="height" class="form-control"
                                   placeholder="0" min="0" step="0.1"
                                   value="<?php echo $edit_product->height ?? ''; ?>">
                            <span class="input-suffix">سانتی‌متر</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- دسته‌بندی‌ها -->
            <div class="form-card">
                <div class="form-card-title">
                    <span class="icon">🏷️</span>
                    دسته‌بندی‌ها
                </div>

                <div class="dynamic-list" id="categories-list">
                    <?php
                    $cats = array_filter($edit_extras, fn($e) => $e->type === 'category');
                    foreach ($cats as $cat): ?>
                    <div class="dynamic-item cat-item">
                        <input type="text" class="form-control cat-id" placeholder="ID (اختیاری)"
                               style="width:90px;flex-shrink:0" value="<?php echo $cat->category_id ?? ''; ?>">
                        <input type="text" class="form-control cat-name" placeholder="نام دسته‌بندی..."
                               value="<?php echo htmlspecialchars($cat->category_name ?? ''); ?>">
                        <button type="button" class="btn-remove-item" title="حذف">×</button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn-add-item" id="add-category">
                    + افزودن دسته‌بندی
                </button>
            </div>

            <!-- ویژگی‌ها -->
            <div class="form-card">
                <div class="form-card-title">
                    <span class="icon">✨</span>
                    ویژگی‌ها
                </div>

                <div class="dynamic-list" id="attributes-list">
                    <?php
                    $attrs = array_filter($edit_extras, fn($e) => $e->type === 'attribute');
                    foreach ($attrs as $attr): ?>
                    <div class="dynamic-item attr-item">
                        <input type="text" class="form-control attr-name" placeholder="نام (مثل: برند)"
                               value="<?php echo htmlspecialchars($attr->attribute_name ?? ''); ?>">
                        <input type="text" class="form-control attr-label" placeholder="برچسب"
                               value="<?php echo htmlspecialchars($attr->attribute_label ?? ''); ?>">
                        <input type="text" class="form-control attr-value" placeholder="مقدار..."
                               value="<?php echo htmlspecialchars($attr->value ?? ''); ?>">
                        <button type="button" class="btn-remove-item" title="حذف">×</button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn-add-item" id="add-attribute">
                    + افزودن ویژگی
                </button>
            </div>

        </div><!-- /.main-col -->

        <!-- ── ستون کناری ─────────────────────────────── -->
        <div class="side-col">

            <!-- دسته داخلی پنل -->
            <div class="form-card">
                <div class="form-card-title">
                    <span class="icon">📁</span>
                    دسته‌بندی داخلی
                    <span style="font-size:10px;color:#ef4444;margin-right:4px;">* اجباری</span>
                </div>

                <input type="hidden" id="panel_category_id" name="panel_category_id"
                       value="<?php echo intval($edit_product->panel_category_id ?? 0); ?>">

                <div class="pcat-wrap">
                    <div class="pcat-input-row">
                        <input type="text" id="pcat-search" class="form-control"
                               placeholder="جستجوی دسته‌بندی..."
                               autocomplete="off">
                    </div>
                    <div class="pcat-dropdown" id="pcat-dropdown"></div>
                </div>

                <div class="pcat-selected" id="pcat-selected">
                    <span class="pcat-color-dot" id="pcat-dot" style="background:#e5e7eb;"></span>
                    <span id="pcat-name-display">—</span>
                    <button type="button" class="pcat-clear" id="pcat-clear" title="حذف انتخاب">×</button>
                </div>

                <div class="pcat-required" id="pcat-required">
                    ⚠️ انتخاب دسته‌بندی اجباری است
                </div>

                <div style="margin-top:8px;display:flex;align-items:center;gap:8px;font-size:11px;">
                    <span style="color:#9ca3af;">دسته وجود ندارد؟</span>
                    <button type="button" id="btn-add-cat-inline"
                            style="background:none;border:none;color:#16a34a;cursor:pointer;font-size:12px;font-weight:600;padding:0;font-family:inherit;">
                        ➕ افزودن دسته جدید
                    </button>
                </div>
            </div>

            <!-- انتشار -->
            <div class="form-card">
                <div class="form-card-title">
                    <span class="icon">🚀</span>
                    انتشار
                </div>

                <div class="form-group">
                    <label>وضعیت محصول</label>
                    <div class="status-radios" id="status-radios">
                        <label class="status-radio <?php echo ($edit_product->status ?? 'active') === 'active' ? 'selected-active' : ''; ?>">
                            <input type="radio" name="status" value="active"
                                   <?php echo ($edit_product->status ?? 'active') === 'active' ? 'checked' : ''; ?>>
                            ✅ فعال
                        </label>
                        <label class="status-radio <?php echo ($edit_product->status ?? '') === 'inactive' ? 'selected-inactive' : ''; ?>">
                            <input type="radio" name="status" value="inactive"
                                   <?php echo ($edit_product->status ?? '') === 'inactive' ? 'checked' : ''; ?>>
                            ⏸ غیرفعال
                        </label>
                        <label class="status-radio <?php echo ($edit_product->status ?? '') === 'draft' ? 'selected-draft' : ''; ?>">
                            <input type="radio" name="status" value="draft"
                                   <?php echo ($edit_product->status ?? '') === 'draft' ? 'checked' : ''; ?>>
                            📝 پیش‌نویس
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn-submit-product" id="btn-submit">
                    <span class="icon">💾</span>
                    <span id="btn-text"><?php echo $edit_id ? 'ذخیره تغییرات' : 'ثبت محصول'; ?></span>
                </button>

                <?php if ($edit_id): ?>
                <div style="margin-top:10px;text-align:center;">
                    <a href="create-product.php" style="font-size:12px;color:#6b7280;text-decoration:none;">
                        + ثبت محصول جدید
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- تصاویر محصول -->
            <div class="form-card">
                <div class="form-card-title">
                    <span class="icon">🖼️</span>
                    تصاویر محصول
                    <span id="images-count" style="font-size:11px;font-weight:400;color:#6b7280;margin-right:auto;">(۰ تصویر)</span>
                </div>

                <!-- grid پیش‌نمایش -->
                <div class="images-grid" id="images-grid" style="display:none;"></div>

                <!-- حالت خالی -->
                <div class="images-empty" id="images-empty">
                    <span class="upload-icon">🖼️</span>
                    هنوز تصویری اضافه نشده
                </div>

                <!-- تب‌های روش ورودی -->
                <div class="image-input-tabs">
                    <button type="button" class="img-tab active" data-tab="upload">📁 آپلود از سیستم</button>
                    <button type="button" class="img-tab" data-tab="url">🔗 آدرس URL</button>
                </div>

                <!-- پنل آپلود -->
                <div id="tab-upload">
                    <div class="upload-zone" id="upload-zone">
                        <input type="file" id="file-input" accept="image/jpeg,image/png,image/webp,image/gif" multiple>
                        <span class="upload-zone-icon">☁️</span>
                        <p>کلیک کنید یا تصویر را اینجا بکشید</p>
                        <small>JPG، PNG، WebP، GIF — حداکثر ۵ مگابایت</small>
                    </div>
                </div>

                <!-- پنل URL -->
                <div id="tab-url" style="display:none;">
                    <div class="add-url-row">
                        <input type="url" id="new-image-url" class="form-control"
                               placeholder="https://example.com/image.jpg">
                        <button type="button" class="btn-add-url" id="btn-add-url">+ افزودن</button>
                    </div>
                </div>

                <div style="font-size:11px;color:#9ca3af;margin-top:6px;">
                    اولین تصویر = تصویر اصلی. برای تغییر روی «اصلی» کلیک کنید.
                </div>
            </div>

            <!-- خلاصه قیمت -->
            <div class="form-card" id="price-summary" style="display:none;">
                <div class="form-card-title">
                    <span class="icon">🧮</span>
                    خلاصه قیمت
                </div>
                <div style="font-size:13px;line-height:2;">
                    <div style="display:flex;justify-content:space-between;">
                        <span>قیمت فروش:</span>
                        <strong id="summary-regular">—</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span>قیمت تخفیف‌خورده:</span>
                        <strong id="summary-sale" style="color:#16a34a;">—</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span>سود تقریبی:</span>
                        <strong id="summary-profit" style="color:#2563eb;">—</strong>
                    </div>
                </div>
            </div>

        </div><!-- /.side-col -->

    </div><!-- /.product-form-wrapper -->
</form>

<?php
$content = ob_get_clean();

$images_json = !empty($edit_images)
    ? json_encode(array_map(function($img){
        return ['url'=>$img->image_url,'alt'=>$img->alt_text??'','is_primary'=>(bool)$img->is_primary];
    }, $edit_images), JSON_UNESCAPED_UNICODE)
    : '[]';

$extra_js = '
<script>
    const AJAX_URL           = "../ajax/create-product.php";
    const WP_SEARCH_URL      = "../ajax/search-wp-products.php";
    const WP_IMAGES_URL      = "../ajax/fetch-wp-images.php";
    const WP_ATTR_SEARCH_URL = "../ajax/search-wp-attributes.php";
    const WP_ATTR_SYNC_URL   = "../ajax/sync-product-attributes.php";
    const PANEL_CATS_URL     = "../ajax/panel-categories.php";
    const CURRENCY           = "' . $currency_symbol . '";
    var imagesList            = ' . $images_json . ';
    var panelCategoryId       = ' . intval($edit_product->panel_category_id ?? 0) . ';
</script>
<script src="../assets/js/create-product.js"></script>
';

require_once __DIR__ . '/layout.php';
?>
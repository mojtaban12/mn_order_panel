<?php
/**
 * MN Order Panel - ثبت فاکتور خرید
 * دو حالت: محصول جدید | محصول موجود
 */

session_start();
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../config/settings.php';

$currency_symbol = MN_Settings::get('currency_symbol', 'تومان');

$prefill_product_id = intval($_GET['product_id'] ?? 0);
$prefill_title      = '';
if ($prefill_product_id) {
    require_once __DIR__ . '/../config/database.php';
    $prd = MN_Database::get_instance()->get_row(
        "SELECT title, sku FROM mn_products WHERE id = ?", [$prefill_product_id]
    );
    if ($prd) $prefill_title = $prd->title . ($prd->sku ? ' — ' . $prd->sku : '');
}

$page_title = 'ثبت فاکتور خرید - پنل';

$extra_css = '
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css">
<style>
/* Tabs */
.inv-tabs {
    display:flex;
    gap:8px;
    margin-bottom:20px;
}
.inv-tab {
    flex:1;
    padding:14px 20px;
    font-size:14px;
    font-weight:600;
    border:2px solid #e5e7eb;
    border-radius:10px;
    background:#fff;
    cursor:pointer;
    font-family:inherit;
    color:#6b7280;
    text-align:center;
    transition:all .15s;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:8px;
}
.inv-tab:hover { border-color:#93c5fd; color:#2563eb; background:#eff6ff; }
.inv-tab.active {
    border-color:#2563eb;
    background:#2563eb;
    color:#fff;
    box-shadow:0 4px 12px rgba(37,99,235,.2);
}
.inv-tab-pane { display:none; }
.inv-tab-pane.active { display:block; }

/* Layout */
.inv-layout { display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start; }
@media(max-width:800px){ .inv-layout{grid-template-columns:1fr;} }

/* Cards */
.form-card { background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:22px 24px;margin-bottom:18px; }
.form-card-title { font-size:14px;font-weight:700;color:#374151;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;gap:8px; }

/* Form */
.form-row   { display:grid;grid-template-columns:1fr 1fr;gap:14px; }
.form-row-3 { display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px; }
@media(max-width:600px){ .form-row,.form-row-3{grid-template-columns:1fr;} }
.form-group { display:flex;flex-direction:column;gap:5px;margin-bottom:14px; }
.form-group label { font-size:13px;font-weight:600;color:#374151; }
.req { color:#ef4444; }
.form-control {
    width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:7px;
    font-size:14px;font-family:inherit;color:#111827;background:#fff;
    box-sizing:border-box;transition:border-color .15s;
}
.form-control:focus { outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.12); }
.input-sfx { display:flex;align-items:stretch; }
.input-sfx .form-control { border-radius:7px 0 0 7px;flex:1; }
.sfx { background:#f3f4f6;border:1px solid #d1d5db;border-right:none;padding:0 10px;font-size:12px;color:#6b7280;display:flex;align-items:center;border-radius:0 7px 7px 0;white-space:nowrap; }

/* Product search */
.prod-wrap { position:relative; }
.prod-dd { display:none;position:absolute;top:100%;right:0;left:0;background:#fff;border:1px solid #bfdbfe;border-radius:0 0 8px 8px;box-shadow:0 8px 24px rgba(37,99,235,.12);z-index:999;max-height:260px;overflow-y:auto; }
.prod-dd.open { display:block; }
.prod-dd-item { display:flex;align-items:center;gap:10px;padding:9px 14px;cursor:pointer;border-bottom:1px solid #f3f4f6;font-size:13px;transition:background .1s; }
.prod-dd-item:hover { background:#eff6ff; }
.prod-dd-name { font-weight:600;color:#111827; }
.prod-dd-sku  { font-size:11px;color:#9ca3af; }
.prod-dd-stock{ font-size:11px;margin-right:auto;color:#6b7280; }
.prod-badge { display:none;align-items:center;gap:8px;padding:7px 12px;border-radius:7px;border:1.5px solid #3b82f6;background:#eff6ff;font-size:12px;color:#1e40af;font-weight:600;margin-top:6px; }
.prod-badge.show { display:flex; }
.prod-badge .clr { margin-right:auto;background:none;border:none;color:#ef4444;cursor:pointer;font-size:16px;padding:0; }

/* New product badge */
.new-prod-hint { background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px 14px;font-size:12px;color:#166534;margin-bottom:14px; }

/* Summary */
.summary-box { background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:16px; }
.s-row { display:flex;justify-content:space-between;align-items:center;padding:5px 0;font-size:13px; }
.s-row.total { font-weight:700;font-size:15px;border-top:1px solid #e5e7eb;padding-top:10px;margin-top:6px; }
.s-row.total span:last-child { color:#16a34a; }

.btn-submit { width:100%;padding:13px;background:#16a34a;color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:700;font-family:inherit;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:background .15s;margin-top:16px; }
.btn-submit:hover { background:#15803d; }
.btn-submit:disabled { opacity:.6;cursor:not-allowed; }

.inv-alert { display:none;padding:12px 16px;border-radius:8px;font-size:14px;margin-bottom:16px; }
.inv-alert.success { background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0; }
.inv-alert.error   { background:#fef2f2;color:#dc2626;border:1px solid #fecaca; }
</style>
';

ob_start();
?>

<div class="inv-alert" id="inv-alert"></div>

<!-- تب‌ها -->
<div class="inv-tabs">
    <button class="inv-tab <?php echo !$prefill_product_id ? 'active' : ''; ?>"
            onclick="switchTab('new', this)">📦 محصول جدید</button>
    <button class="inv-tab <?php echo $prefill_product_id ? 'active' : ''; ?>"
            onclick="switchTab('existing', this)">🔍 محصول موجود</button>
</div>

<form id="inv-form" novalidate>
<input type="hidden" id="mode" name="mode" value="<?php echo $prefill_product_id ? 'existing' : 'new'; ?>">

<!-- ════════════════════════════════════
     تب محصول جدید
════════════════════════════════════ -->
<div class="inv-tab-pane <?php echo !$prefill_product_id ? 'active' : ''; ?>" id="pane-new">
<div class="inv-layout">
<div>

    <div class="new-prod-hint">
        ✅ محصول خام ساخته میشه (وضعیت: پیش‌نویس) — بعداً می‌تونید تصویر، وزن و ویژگی اضافه کنید.
    </div>

    <!-- اطلاعات محصول -->
    <div class="form-card">
        <div class="form-card-title"><span>📦</span> اطلاعات محصول</div>
        <div class="form-group">
            <label>نام محصول <span class="req">*</span></label>
            <input type="text" name="product_title" class="form-control" placeholder="نام کامل محصول">
        </div>
        <div class="form-group">
            <label>دسته‌بندی داخلی</label>
            <select name="panel_category_id" id="pcat-new" class="form-control" data-cat-picker>
                <option value="">— بدون دسته —</option>
            </select>
        </div>
    </div>

    <?php include __DIR__ . '/partials/invoice-common-fields.php'; ?>

</div>
<?php include __DIR__ . '/partials/invoice-sidebar.php'; ?>
</div>
</div>

<!-- ════════════════════════════════════
     تب محصول موجود
════════════════════════════════════ -->
<div class="inv-tab-pane <?php echo $prefill_product_id ? 'active' : ''; ?>" id="pane-existing">
<div class="inv-layout">
<div>

    <!-- جستجوی محصول -->
    <div class="form-card">
        <div class="form-card-title"><span>🔍</span> انتخاب محصول</div>
        <input type="hidden" id="product_id" name="product_id" value="<?php echo $prefill_product_id; ?>">
        <div class="form-group">
            <label>جستجوی محصول <span class="req">*</span></label>
            <div class="prod-wrap">
                <input type="text" id="prod-search" class="form-control"
                       placeholder="نام، SKU یا کد محصول..."
                       value="<?php echo htmlspecialchars($prefill_title); ?>"
                       autocomplete="off">
                <div class="prod-dd" id="prod-dd"></div>
            </div>
            <div class="prod-badge <?php echo $prefill_product_id ? 'show' : ''; ?>" id="prod-badge">
                <span id="prod-badge-name"><?php echo htmlspecialchars($prefill_title); ?></span>
                <span id="prod-badge-stock" style="font-weight:400;color:#6b7280;font-size:11px;"></span>
                <button type="button" class="clr" id="prod-clear">×</button>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/partials/invoice-common-fields.php'; ?>

</div>
<?php include __DIR__ . '/partials/invoice-sidebar.php'; ?>
</div>
</div>

</form>

<?php
$content = ob_get_clean();
$extra_js = '
<script src="../assets/js/persian-date.min.js"></script>
<script src="../assets/js/persian-datepicker.min.js"></script>
<script>
    var AJAX = "../ajax/";
    var CUR  = "' . $currency_symbol . '";
</script>
<script src="../assets/js/create-invoice.js"></script>
';
require_once __DIR__ . '/layout.php';
?>
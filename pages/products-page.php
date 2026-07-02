<?php
/**
 * MN Order Panel - لیست محصولات
 */

session_start();
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../config/settings.php';

$currency_symbol = MN_Settings::get('currency_symbol', 'تومان');
$page_title      = 'لیست محصولات - پنل ثبت سفارش';

$extra_css = '
<style>

/* ── Stats ────────────────────────────── */
.stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-bottom: 20px;
}
@media (max-width: 700px) { .stats-row { grid-template-columns: repeat(2,1fr); } }

.stat-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 16px 18px;
    display: flex;
    align-items: center;
    gap: 14px;
}
.stat-icon {
    width: 44px; height: 44px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}
.stat-icon.blue   { background: #eff6ff; }
.stat-icon.green  { background: #f0fdf4; }
.stat-icon.yellow { background: #fffbeb; }
.stat-icon.red    { background: #fef2f2; }
.stat-val  { font-size: 22px; font-weight: 700; color: #111827; line-height: 1.1; }
.stat-lbl  { font-size: 12px; color: #6b7280; margin-top: 2px; }

/* ── Filter bar ───────────────────────── */
.filter-bar {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 16px 20px;
    margin-bottom: 16px;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: flex-end;
}
.filter-group { display: flex; flex-direction: column; gap: 4px; min-width: 160px; flex: 1; }
.filter-group label { font-size: 12px; font-weight: 600; color: #374151; }
.filter-control {
    padding: 8px 11px;
    border: 1px solid #d1d5db;
    border-radius: 7px;
    font-size: 13px;
    font-family: inherit;
    color: #111827;
    background: #fff;
    width: 100%;
    box-sizing: border-box;
}
.filter-control:focus { outline: none; border-color: #3b82f6; }
.filter-actions { display: flex; gap: 8px; flex-shrink: 0; }
.btn-filter {
    padding: 8px 16px;
    border-radius: 7px;
    border: none;
    font-size: 13px;
    font-family: inherit;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: opacity .15s;
    white-space: nowrap;
}
.btn-filter:hover { opacity: .85; }
.btn-filter.primary { background: #2563eb; color: #fff; }
.btn-filter.secondary { background: #f3f4f6; color: #374151; }
.btn-filter.success { background: #16a34a; color: #fff; }

/* ── Table card ───────────────────────── */
.table-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    overflow: hidden;
}
.table-card-header {
    padding: 14px 20px;
    border-bottom: 1px solid #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}
.table-card-header h2 {
    font-size: 14px;
    font-weight: 700;
    color: #374151;
    margin: 0;
}
.table-wrap { overflow-x: auto; max-height: 62vh; overflow-y: auto; }

table { width: 100%; border-collapse: collapse; font-size: 13px; }
thead th {
    padding: 10px 14px;
    text-align: right;
    font-weight: 600;
    color: #6b7280;
    background: #f9fafb;
    border-bottom: 1px solid #e5e7eb;
    position: sticky;
    top: 0;
    white-space: nowrap;
}
tbody tr { border-bottom: 1px solid #f3f4f6; transition: background .1s; }
tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: #f9fafb; }
td { padding: 10px 14px; vertical-align: middle; }

/* ── Product thumb ────────────────────── */
.product-thumb {
    width: 44px; height: 44px;
    border-radius: 7px;
    object-fit: cover;
    border: 1px solid #e5e7eb;
    flex-shrink: 0;
    background: #f3f4f6;
}
.product-thumb-placeholder {
    width: 44px; height: 44px;
    border-radius: 7px;
    border: 1px solid #e5e7eb;
    background: #f3f4f6;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}
.product-info { display: flex; align-items: center; gap: 10px; }
.product-title { font-weight: 600; color: #111827; max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.product-sku   { font-size: 11px; color: #9ca3af; margin-top: 2px; }

/* ── Badges ───────────────────────────── */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}
.badge-active    { background: #dcfce7; color: #16a34a; }
.badge-inactive  { background: #fef9c3; color: #a16207; }
.badge-draft     { background: #f3f4f6; color: #4b5563; }
.badge-instock   { background: #dbeafe; color: #1d4ed8; }
.badge-outofstock { background: #fee2e2; color: #dc2626; }
.badge-onbackorder { background: #fef3c7; color: #d97706; }
.badge-synced    { background: #d1fae5; color: #065f46; }

/* ── Action btns ──────────────────────── */
.action-btns { display: flex; gap: 6px; align-items: center; }
.act {
    width: 30px; height: 30px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px;
    transition: opacity .15s, transform .1s;
}
.act:hover { opacity: .8; transform: translateY(-1px); }
.act-edit   { background: #fef9c3; color: #a16207; }
.act-delete { background: #fee2e2; color: #dc2626; }
.act-view   { background: #dbeafe; color: #1d4ed8; }

/* ── Empty / loading ──────────────────── */
.table-empty {
    text-align: center;
    padding: 48px 20px;
    color: #9ca3af;
}
.table-empty .empty-icon { font-size: 40px; display: block; margin-bottom: 10px; }

/* ── Pagination ───────────────────────── */
.pagination-row {
    padding: 12px 20px;
    border-top: 1px solid #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px;
}
.pag-info { font-size: 12px; color: #6b7280; }
.pag-btns { display: flex; gap: 4px; }
.pag-btn {
    min-width: 32px; height: 32px;
    padding: 0 8px;
    border-radius: 6px;
    border: 1px solid #e5e7eb;
    background: #fff;
    font-size: 12px;
    font-family: inherit;
    cursor: pointer;
    transition: all .15s;
}
.pag-btn:hover:not(:disabled) { background: #eff6ff; border-color: #3b82f6; color: #2563eb; }
.pag-btn.active { background: #2563eb; color: #fff; border-color: #2563eb; }
.pag-btn:disabled { opacity: .4; cursor: not-allowed; }

/* ── Loading overlay ──────────────────── */
#loading-overlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,.35);
    z-index: 9999;
    align-items: center; justify-content: center;
}
#loading-overlay.active { display: flex; }
.spinner {
    width: 42px; height: 42px;
    border: 4px solid rgba(255,255,255,.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin .7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Confirm dialog ───────────────────── */
.mn-confirm-bg {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.45); z-index: 10000;
    align-items: center; justify-content: center;
}
.mn-confirm-bg.show { display: flex; }
.mn-confirm-box {
    background: #fff; border-radius: 12px;
    padding: 28px 32px; max-width: 380px; width: 90%;
    text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,.2);
}
.mn-confirm-box .icon { font-size: 42px; margin-bottom: 12px; }
.mn-confirm-box h3 { font-size: 16px; font-weight: 700; margin: 0 0 8px; }
.mn-confirm-box p  { font-size: 13px; color: #6b7280; margin: 0 0 20px; }
.mn-confirm-btns { display: flex; gap: 10px; justify-content: center; }
.mn-confirm-btns button {
    padding: 9px 22px; border-radius: 7px; border: none;
    font-size: 13px; font-family: inherit; cursor: pointer; font-weight: 600;
}
.mn-confirm-ok     { background: #dc2626; color: #fff; }
.mn-confirm-cancel { background: #f3f4f6; color: #374151; }
</style>
';

ob_start();
?>

<div id="loading-overlay"><div class="spinner"></div></div>

<!-- Confirm dialog -->
<div class="mn-confirm-bg" id="confirm-bg">
    <div class="mn-confirm-box">
        <div class="icon">🗑️</div>
        <h3>حذف محصول</h3>
        <p>آیا مطمئن هستید؟ این عمل قابل بازگشت نیست.</p>
        <div class="mn-confirm-btns">
            <button class="mn-confirm-ok"     id="confirm-ok">بله، حذف کن</button>
            <button class="mn-confirm-cancel" id="confirm-cancel">انصراف</button>
        </div>
    </div>
</div>

<!-- آمار -->
<div class="stats-row">
    <div class="stat-card">
        <div class="stat-icon blue">📦</div>
        <div><div class="stat-val" id="st-total">—</div><div class="stat-lbl">کل محصولات</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">✅</div>
        <div><div class="stat-val" id="st-active">—</div><div class="stat-lbl">فعال</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue">🏬</div>
        <div><div class="stat-val" id="st-instock">—</div><div class="stat-lbl">موجود</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red">❌</div>
        <div><div class="stat-val" id="st-outofstock">—</div><div class="stat-lbl">ناموجود</div></div>
    </div>
</div>

<!-- فیلترها -->
<div class="filter-bar">
    <div class="filter-group" style="max-width:240px;">
        <label>جستجو</label>
        <input type="text" id="f-search" class="filter-control" placeholder="نام محصول یا SKU...">
    </div>
    <div class="filter-group" style="max-width:150px;">
        <label>وضعیت</label>
        <select id="f-status" class="filter-control">
            <option value="">همه</option>
            <option value="active">فعال</option>
            <option value="inactive">غیرفعال</option>
            <option value="draft">پیش‌نویس</option>
        </select>
    </div>
    <div class="filter-group" style="max-width:150px;">
        <label>موجودی</label>
        <select id="f-stock" class="filter-control">
            <option value="">همه</option>
            <option value="instock">موجود</option>
            <option value="outofstock">ناموجود</option>
            <option value="onbackorder">پیش‌فروش</option>
        </select>
    </div>
    <div class="filter-actions">
        <button class="btn-filter primary" onclick="loadProducts(1)">🔍 جستجو</button>
        <button class="btn-filter secondary" onclick="resetFilters()">↺ ریست</button>
        <a href="create-product.php" class="btn-filter success" style="text-decoration:none;">+ محصول جدید</a>
    </div>
</div>

<!-- جدول -->
<div class="table-card">
    <div class="table-card-header">
        <h2>📋 لیست محصولات</h2>
        <span id="tbl-summary" style="font-size:12px;color:#6b7280;"></span>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th width="50"><input type="checkbox" id="chk-all"></th>
                    <th>محصول</th>
                    <th>SKU</th>
                    <th>قیمت فروش</th>
                    <th>موجودی</th>
                    <th>وضعیت</th>
                    <th>انبار</th>
                    <th>تصاویر</th>
                    <th>ثبت شده</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody id="products-tbody">
                <tr><td colspan="10" class="table-empty">
                    <span class="empty-icon">⏳</span>در حال بارگذاری...
                </td></tr>
            </tbody>
        </table>
    </div>

    <div class="pagination-row">
        <span class="pag-info" id="pag-info">—</span>
        <div class="pag-btns" id="pag-btns"></div>
    </div>
</div>

<?php
$content = ob_get_clean();
$extra_js = '
<script>
var AJAX = "../ajax/";
var CUR  = "' . addslashes($currency_symbol) . '";
</script>
<script src="../assets/js/products.js"></script>
';
require_once __DIR__ . '/layout.php';
?>
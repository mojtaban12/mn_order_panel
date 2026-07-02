<?php
/**
 * MN Order Panel - فاکتورهای فروش از وردپرس
 */

session_start();
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../config/settings.php';

$currency_symbol = MN_Settings::get('currency_symbol', 'تومان');
$page_title      = 'فاکتورهای فروش - پنل';

$extra_css = '
<style>
/* Stats */
.stats-row { display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px; }
@media(max-width:700px){ .stats-row{grid-template-columns:repeat(2,1fr);} }
.stat-card { background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:16px 18px;display:flex;align-items:center;gap:14px; }
.stat-icon { width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0; }
.stat-icon.blue{background:#eff6ff;} .stat-icon.green{background:#f0fdf4;} .stat-icon.purple{background:#f5f3ff;} .stat-icon.orange{background:#fff7ed;}
.stat-val { font-size:20px;font-weight:700;color:#111827;line-height:1.1; }
.stat-lbl { font-size:12px;color:#6b7280;margin-top:2px; }

/* Toolbar */
.toolbar { background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:14px 18px;margin-bottom:16px;display:flex;flex-wrap:wrap;gap:10px;align-items:center; }
.filter-ctrl { padding:8px 11px;border:1px solid #d1d5db;border-radius:7px;font-size:13px;font-family:inherit;color:#111827;background:#fff; }
.filter-ctrl:focus { outline:none;border-color:#3b82f6; }
.btn-sync { padding:9px 18px;background:#7c3aed;color:#fff;border:none;border-radius:7px;font-size:13px;font-family:inherit;cursor:pointer;display:flex;align-items:center;gap:6px;font-weight:600;transition:background .15s; }
.btn-sync:hover { background:#6d28d9; }
.btn-sync:disabled { opacity:.6;cursor:not-allowed; }
.sync-status { font-size:12px;color:#6b7280; }
.sync-status.running { color:#7c3aed; }
.sync-status.done    { color:#16a34a; }
.sync-status.error   { color:#dc2626; }

/* Table */
.table-card { background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden; }
.table-card-header { padding:14px 20px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between; }
.table-card-header h2 { font-size:14px;font-weight:700;color:#374151;margin:0; }
.table-wrap { overflow-x:auto;max-height:65vh;overflow-y:auto; }
table { width:100%;border-collapse:collapse;font-size:13px; }
thead th { padding:10px 14px;text-align:right;font-weight:600;color:#6b7280;background:#f9fafb;border-bottom:1px solid #e5e7eb;position:sticky;top:0;white-space:nowrap; }
tbody tr { border-bottom:1px solid #f3f4f6;transition:background .1s; }
tbody tr:last-child { border-bottom:none; }
tbody tr:hover { background:#f9fafb; }
td { padding:10px 14px;vertical-align:middle; }
.badge { display:inline-flex;align-items:center;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:600; }
.badge-completed { background:#dcfce7;color:#16a34a; }
.badge-processing { background:#dbeafe;color:#1d4ed8; }
.badge-on-hold    { background:#fef9c3;color:#a16207; }
.badge-other      { background:#f3f4f6;color:#374151; }
.stock-badge { font-size:11px;padding:2px 7px;border-radius:10px; }
.stock-dec { background:#fee2e2;color:#dc2626; }
.stock-no  { background:#f3f4f6;color:#9ca3af; }
.product-link { color:#2563eb;text-decoration:none;font-weight:600; }
.product-link:hover { text-decoration:underline; }

/* Pagination */
.pag-row { padding:12px 20px;border-top:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px; }
.pag-info { font-size:12px;color:#6b7280; }
.pag-btns { display:flex;gap:4px; }
.pag-btn { min-width:32px;height:32px;padding:0 8px;border-radius:6px;border:1px solid #e5e7eb;background:#fff;font-size:12px;font-family:inherit;cursor:pointer;transition:all .15s; }
.pag-btn:hover:not(:disabled) { background:#eff6ff;border-color:#3b82f6;color:#2563eb; }
.pag-btn.active { background:#2563eb;color:#fff;border-color:#2563eb; }
.pag-btn:disabled { opacity:.4;cursor:not-allowed; }

/* loading */
#loading-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:9999;align-items:center;justify-content:center; }
#loading-overlay.active { display:flex; }
.spinner { width:40px;height:40px;border:4px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite; }
@keyframes spin { to{transform:rotate(360deg);} }
</style>
';

ob_start();
?>

<div id="loading-overlay"><div class="spinner"></div></div>

<!-- آمار -->
<div class="stats-row">
    <div class="stat-card"><div class="stat-icon purple">🧾</div><div><div class="stat-val" id="st-total">—</div><div class="stat-lbl">کل فاکتورها</div></div></div>
    <div class="stat-card"><div class="stat-icon green">💰</div><div><div class="stat-val" id="st-revenue">—</div><div class="stat-lbl">درآمد کل</div></div></div>
    <div class="stat-card"><div class="stat-icon blue">📦</div><div><div class="stat-val" id="st-qty">—</div><div class="stat-lbl">تعداد فروش</div></div></div>
    <div class="stat-card"><div class="stat-icon orange">🔄</div><div><div class="stat-val" id="st-stock-updated">—</div><div class="stat-lbl">موجودی آپدیت شده</div></div></div>
</div>

<!-- toolbar -->
<div class="toolbar">
    <button class="btn-sync" id="btn-sync" onclick="runSync()">🔄 بررسی سفارشات WC</button>
    <span class="sync-status" id="sync-status"></span>

    <div style="flex:1;"></div>

    <input type="text"  class="filter-ctrl" id="f-search"   placeholder="جستجو محصول / مشتری..." style="width:200px;">
    <input type="date"  class="filter-ctrl" id="f-date-from">
    <input type="date"  class="filter-ctrl" id="f-date-to">
    <select class="filter-ctrl" id="f-status">
        <option value="">همه وضعیت‌ها</option>
        <option value="completed">completed</option>
        <option value="processing">processing</option>
        <option value="on-hold">on-hold</option>
    </select>
    <button class="pag-btn" onclick="loadSales(1)" style="background:#2563eb;color:#fff;border-color:#2563eb;padding:0 14px;">🔍</button>
</div>

<!-- جدول -->
<div class="table-card">
    <div class="table-card-header">
        <h2>🧾 فاکتورهای فروش WooCommerce</h2>
        <span id="tbl-summary" style="font-size:12px;color:#6b7280;"></span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>سفارش WC</th>
                    <th>تاریخ</th>
                    <th>خریدار</th>
                    <th>محصول</th>
                    <th>تعداد</th>
                    <th>قیمت واحد</th>
                    <th>جمع</th>
                    <th>وضعیت</th>
                    <th>موجودی</th>
                </tr>
            </thead>
            <tbody id="sales-tbody">
                <tr><td colspan="9" style="text-align:center;padding:48px;color:#9ca3af;">⏳ در حال بارگذاری...</td></tr>
            </tbody>
        </table>
    </div>
    <div class="pag-row">
        <span class="pag-info" id="pag-info">—</span>
        <div class="pag-btns" id="pag-btns"></div>
    </div>
</div>

<?php
$content = ob_get_clean();
$extra_js = '

<script>
    var AJAX = "../ajax/";
    var CUR  = "' . $currency_symbol . '";
</script>
<script src="../assets/js/wc-sales.js"></script>
';

require_once __DIR__ . '/layout.php';
?>
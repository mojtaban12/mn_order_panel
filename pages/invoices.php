<?php
/**
 * MN Order Panel - فاکتورهای خرید
 */

session_start();
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../config/settings.php';

$currency_symbol = MN_Settings::get('currency_symbol', 'تومان');
$page_title      = 'فاکتورهای خرید - پنل';

$extra_css = '
<style>
.stats-row { display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px; }
@media(max-width:700px){ .stats-row{grid-template-columns:repeat(2,1fr);} }
.stat-card { background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:16px 18px;display:flex;align-items:center;gap:12px; }
.stat-icon { width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0; }
.stat-icon.blue{background:#eff6ff;} .stat-icon.green{background:#f0fdf4;} .stat-icon.red{background:#fef2f2;} .stat-icon.yellow{background:#fffbeb;}
.stat-val { font-size:18px;font-weight:700;color:#111827;line-height:1.1; }
.stat-lbl { font-size:11px;color:#6b7280;margin-top:2px; }

.toolbar { background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:14px 18px;margin-bottom:16px;display:flex;flex-wrap:wrap;gap:10px;align-items:center; }
.fc { padding:8px 11px;border:1px solid #d1d5db;border-radius:7px;font-size:13px;font-family:inherit;color:#111827;background:#fff; }
.fc:focus { outline:none;border-color:#3b82f6; }
.btn-new { padding:9px 18px;background:#16a34a;color:#fff;border:none;border-radius:7px;font-size:13px;font-family:inherit;cursor:pointer;font-weight:600;display:flex;align-items:center;gap:6px;text-decoration:none; }
.btn-new:hover { background:#15803d; }

.table-card { background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden; }
.table-card-header { padding:14px 20px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between; }
.table-card-header h2 { font-size:14px;font-weight:700;color:#374151;margin:0; }
.table-wrap { overflow-x:auto;max-height:60vh;overflow-y:auto; }
table { width:100%;border-collapse:collapse;font-size:13px; }
thead th { padding:10px 14px;text-align:right;font-weight:600;color:#6b7280;background:#f9fafb;border-bottom:1px solid #e5e7eb;position:sticky;top:0;white-space:nowrap; }
tbody tr { border-bottom:1px solid #f3f4f6;transition:background .1s; }
tbody tr:last-child { border-bottom:none; }
tbody tr:hover { background:#f9fafb; }
td { padding:10px 14px;vertical-align:middle; }

.badge { display:inline-flex;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:600; }
.badge-paid     { background:#dcfce7;color:#16a34a; }
.badge-unpaid   { background:#fee2e2;color:#dc2626; }
.badge-partial  { background:#fef9c3;color:#a16207; }

.act { width:30px;height:30px;border-radius:6px;border:none;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;font-size:14px;transition:opacity .15s; }
.act:hover { opacity:.75; }
.act-view   { background:#dbeafe;color:#1d4ed8; }
.act-delete { background:#fee2e2;color:#dc2626; }

.pag-row { padding:12px 20px;border-top:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px; }
.pag-info { font-size:12px;color:#6b7280; }
.pag-btns { display:flex;gap:4px; }
.pag-btn { min-width:32px;height:32px;padding:0 8px;border-radius:6px;border:1px solid #e5e7eb;background:#fff;font-size:12px;font-family:inherit;cursor:pointer;transition:all .15s; }
.pag-btn:hover:not(:disabled) { background:#eff6ff;border-color:#3b82f6;color:#2563eb; }
.pag-btn.active { background:#2563eb;color:#fff;border-color:#2563eb; }
.pag-btn:disabled { opacity:.4;cursor:not-allowed; }

#loading-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:9999;align-items:center;justify-content:center; }
#loading-overlay.active { display:flex; }
.spinner { width:40px;height:40px;border:4px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite; }
@keyframes spin { to{transform:rotate(360deg);} }

/* confirm */
.mn-confirm-bg { display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:10000;align-items:center;justify-content:center; }
.mn-confirm-bg.show { display:flex; }
.mn-confirm-box { background:#fff;border-radius:12px;padding:28px 32px;max-width:360px;width:90%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.2); }
.mn-confirm-box .icon { font-size:38px;margin-bottom:10px; }
.mn-confirm-box h3 { font-size:15px;font-weight:700;margin:0 0 8px; }
.mn-confirm-box p  { font-size:13px;color:#6b7280;margin:0 0 18px; }
.mn-confirm-btns { display:flex;gap:10px;justify-content:center; }
.mn-confirm-btns button { padding:9px 22px;border-radius:7px;border:none;font-size:13px;font-family:inherit;cursor:pointer;font-weight:600; }
.mn-confirm-ok     { background:#dc2626;color:#fff; }
.mn-confirm-cancel { background:#f3f4f6;color:#374151; }
</style>
';

ob_start();
?>

<div id="loading-overlay"><div class="spinner"></div></div>

<div class="mn-confirm-bg" id="confirm-bg">
    <div class="mn-confirm-box">
        <div class="icon">🗑️</div>
        <h3>حذف فاکتور</h3>
        <p>موجودی واقعی محصول برگشت داده می‌شود. ادامه می‌دهید؟</p>
        <div class="mn-confirm-btns">
            <button class="mn-confirm-ok"     id="confirm-ok">بله، حذف کن</button>
            <button class="mn-confirm-cancel" id="confirm-cancel">انصراف</button>
        </div>
    </div>
</div>

<!-- آمار -->
<div class="stats-row">
    <div class="stat-card"><div class="stat-icon blue">🧾</div><div><div class="stat-val" id="st-total">—</div><div class="stat-lbl">کل فاکتورها</div></div></div>
    <div class="stat-card"><div class="stat-icon green">✅</div><div><div class="stat-val" id="st-paid">—</div><div class="stat-lbl">پرداخت شده</div></div></div>
    <div class="stat-card"><div class="stat-icon red">❌</div><div><div class="stat-val" id="st-unpaid">—</div><div class="stat-lbl">پرداخت نشده</div></div></div>
    <div class="stat-card"><div class="stat-icon yellow">📦</div><div><div class="stat-val" id="st-qty">—</div><div class="stat-lbl">کل اقلام خریده شده</div></div></div>
</div>

<!-- toolbar -->
<div class="toolbar">
    <a href="create-invoice.php" class="btn-new">+ فاکتور جدید</a>
    <input type="text"  class="fc" id="f-search"   placeholder="محصول / فروشنده / شماره..." style="width:200px;">
    <select class="fc"  id="f-status">
        <option value="">همه وضعیت‌ها</option>
        <option value="paid">پرداخت شده</option>
        <option value="unpaid">پرداخت نشده</option>
        <option value="partial">نیم‌پرداخت</option>
    </select>
    <input type="date"  class="fc" id="f-date-from">
    <input type="date"  class="fc" id="f-date-to">
    <button class="pag-btn" onclick="loadInvoices(1)" style="background:#2563eb;color:#fff;border-color:#2563eb;padding:0 14px;">🔍</button>
</div>

<!-- جدول -->
<div class="table-card">
    <div class="table-card-header">
        <h2>🧾 فاکتورهای خرید</h2>
        <span id="tbl-summary" style="font-size:12px;color:#6b7280;"></span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>شماره</th>
                    <th>تاریخ</th>
                    <th>محصول</th>
                    <th>فروشنده</th>
                    <th>تعداد</th>
                    <th>قیمت واحد</th>
                    <th>مبلغ نهایی</th>
                    <th>پرداخت</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody id="inv-tbody">
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
<script src="../assets/js/invoices.js"></script>
';
require_once __DIR__ . '/layout.php';
?>
<?php
/**
 * MN Order Panel - همگام‌سازی محصولات با سایت
 */

session_start();
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../config/settings.php';

$page_title = 'همگام‌سازی محصولات با سایت - پنل';

$extra_css = '
<style>
.stats-row { display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px; }
@media(max-width:700px){ .stats-row{grid-template-columns:1fr;} }
.stat-card { background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:16px 18px;text-align:center; }
.stat-val { font-size:26px;font-weight:700;color:#111827; }
.stat-lbl { font-size:12px;color:#6b7280;margin-top:4px; }

.sync-panel { background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px 22px;margin-bottom:18px; }
.sync-actions { display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px; }
.btn { padding:10px 18px;border:none;border-radius:7px;font-size:13px;font-family:inherit;cursor:pointer;font-weight:600;transition:opacity .15s;display:inline-flex;align-items:center;gap:6px; }
.btn:disabled { opacity:.5;cursor:not-allowed; }
.btn-primary { background:#2563eb;color:#fff; }
.btn-success { background:#16a34a;color:#fff; }
.btn-secondary { background:#f3f4f6;color:#374151; }

.progress-wrap { background:#f3f4f6;border-radius:10px;height:16px;overflow:hidden;margin:12px 0; }
.progress-bar { height:100%;background:linear-gradient(90deg,#16a34a,#22c55e);border-radius:10px;transition:width .25s;width:0; }
.progress-text { font-size:13px;color:#374151;font-weight:600;text-align:center;margin-bottom:4px; }

.sync-log { max-height:220px;overflow-y:auto;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:10px 12px;font-size:12px;font-family:monospace;display:none; }
.sync-log-row { padding:3px 0;border-bottom:1px dashed #e5e7eb; }
.sync-log-row.ok { color:#16a34a; }
.sync-log-row.fail { color:#dc2626; }

.table-card { background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden; }
.table-card-header { padding:14px 20px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap; }
.table-card-header h2 { font-size:14px;font-weight:700;color:#374151;margin:0; }
.fc { padding:7px 11px;border:1px solid #d1d5db;border-radius:7px;font-size:13px;font-family:inherit;width:220px; }
.table-wrap { overflow-x:auto;max-height:55vh;overflow-y:auto; }
table { width:100%;border-collapse:collapse;font-size:13px; }
thead th { padding:9px 12px;text-align:right;font-weight:600;color:#6b7280;background:#f9fafb;border-bottom:1px solid #e5e7eb;position:sticky;top:0; }
tbody tr { border-bottom:1px solid #f3f4f6; }
tbody tr:hover { background:#f9fafb; }
td { padding:9px 12px;vertical-align:middle; }
.badge { display:inline-flex;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:600; }
.badge-synced { background:#dcfce7;color:#16a34a; }
.badge-pending { background:#fef9c3;color:#a16207; }
.pag-row { padding:12px 20px;border-top:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px; }
.pag-btns { display:flex;gap:4px; }
.pag-btn { min-width:30px;height:30px;padding:0 8px;border-radius:6px;border:1px solid #e5e7eb;background:#fff;font-size:12px;font-family:inherit;cursor:pointer; }
.pag-btn.active { background:#2563eb;color:#fff;border-color:#2563eb; }
.pag-btn:disabled { opacity:.4;cursor:not-allowed; }
</style>
';

ob_start();
?>

    <div class="stats-row">
        <div class="stat-card"><div class="stat-val" id="st-total">—</div><div class="stat-lbl">کل محصولات سایتی</div></div>
        <div class="stat-card"><div class="stat-val" id="st-pending" style="color:#d97706;">—</div><div class="stat-lbl">منتظر سینک</div></div>
        <div class="stat-card"><div class="stat-val" id="st-done" style="color:#16a34a;">—</div><div class="stat-lbl">سینک شده امروز</div></div>
    </div>

    <div class="sync-panel">
        <div class="sync-actions">
            <button class="btn btn-success" id="btn-sync-all">🔄 سینک همه محصولات</button>
            <button class="btn btn-primary" id="btn-sync-selected" disabled>✅ سینک انتخاب‌شده‌ها (<span id="sel-count">0</span>)</button>
            <button class="btn btn-secondary" id="btn-stop" style="display:none;">⏹ توقف</button>
        </div>

        <div id="progress-area" style="display:none;">
            <div class="progress-text" id="progress-text">0 از 0</div>
            <div class="progress-wrap"><div class="progress-bar" id="progress-bar"></div></div>
            <div style="text-align:center;">
                <button class="btn btn-secondary" style="padding:4px 12px;font-size:11px;" id="btn-toggle-log">نمایش جزئیات</button>
            </div>
            <div class="sync-log" id="sync-log"></div>
        </div>
    </div>

    <div class="table-card">
        <div class="table-card-header">
            <h2>📦 محصولات سایتی</h2>
            <input type="text" class="fc" id="f-search" placeholder="جستجوی نام یا SKU...">
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th width="40"><input type="checkbox" id="chk-all"></th>
                    <th>محصول</th>
                    <th>SKU</th>
                    <th>wp_product_id</th>
                    <th>وضعیت</th>
                    <th>آخرین سینک</th>
                </tr>
                </thead>
                <tbody id="tbody">
                <tr><td colspan="6" style="text-align:center;padding:40px;color:#9ca3af;">⏳ در حال بارگذاری...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="pag-row">
            <span id="pag-info" style="font-size:12px;color:#6b7280;"></span>
            <div class="pag-btns" id="pag-btns"></div>
        </div>
    </div>

<?php
$content = ob_get_clean();
$extra_js = '
<script>
    var AJAX = "../ajax/";
</script>
<script src="../assets/js/sync-products.js"></script>
';
require_once __DIR__ . '/layout.php';
?>
<?php
/**
 * MN Order Panel - ایمپورت دسته‌ای محصولات از Excel
 */

session_start();
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

$currency_symbol = MN_Settings::get('currency_symbol', 'تومان');
$db   = MN_Database::get_instance();
$cats = $db->get_results("
    SELECT c.id, c.name, p.name AS parent_name
    FROM mn_panel_categories c
    LEFT JOIN mn_panel_categories p ON c.parent_id = p.id
    ORDER BY c.parent_id IS NOT NULL, c.name
");

$page_title = 'ایمپورت دسته‌ای محصولات - پنل';

$extra_css = '
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css">
<style>
.import-layout { display:grid;grid-template-columns:320px 1fr;gap:20px;align-items:start; }
.import-tab { padding:10px 20px;border:2px solid #e5e7eb;border-radius:9px;background:#fff;cursor:pointer;font-family:inherit;font-size:13px;font-weight:600;color:#6b7280;transition:all .15s; }
.import-tab.active { border-color:#2563eb;background:#2563eb;color:#fff; }
.import-pane { display:none; }
.import-pane.active { display:block; }
@media(max-width:900px){ .import-layout{grid-template-columns:1fr;} }

.step-card { background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px 22px;margin-bottom:16px; }
.step-title { font-size:13px;font-weight:700;color:#374151;margin-bottom:14px;display:flex;align-items:center;gap:8px; }
.step-num { width:24px;height:24px;border-radius:50%;background:#2563eb;color:#fff;font-size:12px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0; }

.fc { width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:7px;font-size:13px;font-family:inherit;color:#111827;background:#fff;box-sizing:border-box; }
.fc:focus { outline:none;border-color:#3b82f6; }
.fg { margin-bottom:14px; }
.fg label { display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px; }

.drop-zone { border:2px dashed #d1d5db;border-radius:10px;padding:32px 20px;text-align:center;cursor:pointer;transition:all .2s;background:#fafafa;position:relative; }
.drop-zone:hover,.drop-zone.drag { border-color:#3b82f6;background:#eff6ff; }
.drop-zone input[type=file] { position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%; }
.drop-icon { font-size:36px;margin-bottom:8px; }
.drop-text { font-size:13px;color:#6b7280; }
.drop-text strong { color:#2563eb; }

.col-map { background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:14px;margin-bottom:14px; }
.col-map-title { font-size:12px;font-weight:700;color:#92400e;margin-bottom:10px; }
.col-map-row { display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px;align-items:center; }
.col-map-row label { font-size:12px;color:#374151;font-weight:600; }
.col-map-row select { padding:6px 8px;border:1px solid #d1d5db;border-radius:6px;font-size:12px;font-family:inherit; }

/* stats */
.stat-row { display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px; }
.s-box { background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:14px;text-align:center; }
.s-val { font-size:22px;font-weight:700;color:#111827; }
.s-lbl { font-size:11px;color:#6b7280;margin-top:2px; }

/* progress */
.progress-wrap { background:#f3f4f6;border-radius:8px;height:12px;overflow:hidden;margin:10px 0; }
.progress-bar  { height:100%;background:#16a34a;border-radius:8px;transition:width .3s;width:0; }

.btn { padding:10px 18px;border:none;border-radius:7px;font-size:13px;font-family:inherit;cursor:pointer;font-weight:600;transition:all .15s;display:inline-flex;align-items:center;gap:6px; }
.btn-success { background:#16a34a;color:#fff;width:100%;justify-content:center; } .btn-success:hover { background:#15803d; }
.btn-secondary { background:#f3f4f6;color:#374151; } .btn-secondary:hover { background:#e5e7eb; }
.btn:disabled { opacity:.5;cursor:not-allowed; }

.alert { padding:12px 14px;border-radius:8px;font-size:13px;margin-bottom:12px; }
.alert-success { background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0; }
.alert-error   { background:#fef2f2;color:#dc2626;border:1px solid #fecaca; }
.alert-info    { background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe; }
</style>
';

ob_start();
?>

    <div class="import-tabs" style="display:flex;gap:8px;margin-bottom:18px;">
        <button class="import-tab active" data-tab="new" onclick="switchImportTab('new', this)">
            📦 محصول کاملاً جدید
        </button>
        <button class="import-tab" data-tab="existing" onclick="switchImportTab('existing', this)">
            🔗 محصولات موجود در سایت
        </button>
    </div>

    <!-- ══════ تب ۱: محصول جدید (موجود — بدون تغییر) ══════ -->
    <div class="import-pane active" id="pane-new">
        <div class="import-layout">
            <!-- همون محتوای فعلی import-layout شما اینجا -->
        </div>
    </div>

    <!-- ══════ تب ۲: محصولات موجود در سایت ══════ -->
    <div class="import-pane" id="pane-existing">
        <div class="import-layout">

            <div>
                <div class="step-card">
                    <div class="step-title"><span class="step-num">۱</span> تنظیمات اولیه</div>
                    <div class="fg">
                        <label>دسته‌بندی داخلی (اختیاری)</label>
                        <select id="ex-sel-cat" class="fc" data-cat-picker>
                            <option value="">— بدون دسته —</option>
                            <?php foreach ($cats as $c): ?>
                                <option value="<?php echo $c->id; ?>">
                                    <?php echo $c->parent_name ? htmlspecialchars($c->parent_name) . ' / ' : ''; ?>
                                    <?php echo htmlspecialchars($c->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:10px 12px;font-size:11.5px;color:#1e40af;">
                        💡 شناسه اکسل باید همون شناسه‌ای باشه که در وردپرس به فرمت <code>P-{شناسه}</code> به عنوان SKU ذخیره شده.
                    </div>
                </div>

                <div class="step-card">
                    <div class="step-title"><span class="step-num">۲</span> انتخاب فایل</div>
                    <div class="drop-zone" id="ex-drop-zone">
                        <input type="file" id="ex-file-input" accept=".xlsx,.xls,.csv">
                        <div class="drop-icon">📂</div>
                        <div class="drop-text">رها کنید یا <strong>کلیک کنید</strong></div>
                        <div style="font-size:11px;color:#9ca3af;margin-top:4px;">xlsx, xls, csv</div>
                    </div>
                    <div id="ex-file-name" style="display:none;margin-top:8px;font-size:12px;color:#374151;text-align:center;"></div>
                </div>
            </div>

            <div>
                <div id="ex-alert-box"></div>

                <div class="step-card" id="ex-col-map-card" style="display:none;">
                    <div class="step-title"><span class="step-num">۳</span> تطبیق ستون‌ها</div>
                    <div class="col-map" id="ex-col-map-wrap"></div>
                    <div class="fg" style="margin-bottom:18px;">
                        <label>ردیف header در فایل</label>
                        <input type="number" id="ex-inp-header-row" class="fc" value="1" min="1" max="20">
                    </div>
                    <div style="display:flex;gap:8px;">
                        <button class="btn btn-success" id="ex-btn-import" style="flex:1;">⚡ شروع ایمپورت</button>
                        <button class="btn btn-secondary" id="ex-btn-reset" title="ریست">🔄</button>
                    </div>
                </div>

                <div class="step-card" id="ex-import-section" style="display:none;">
                    <div class="step-title"><span class="step-num">۴</span> در حال ایمپورت...</div>
                    <div class="stat-row">
                        <div class="s-box"><div class="s-val" id="ex-st-total">0</div><div class="s-lbl">کل ردیف</div></div>
                        <div class="s-box"><div class="s-val" id="ex-st-done" style="color:#16a34a;">0</div><div class="s-lbl">ثبت/آپدیت</div></div>
                        <div class="s-box"><div class="s-val" id="ex-st-error" style="color:#dc2626;">0</div><div class="s-lbl">خطا</div></div>
                    </div>
                    <div style="font-size:12px;color:#6b7280;margin-bottom:4px;"><span id="ex-prog-text">0 از 0 (0%)</span></div>
                    <div class="progress-wrap"><div class="progress-bar" id="ex-progress-bar"></div></div>
                </div>

                <!-- ── سینک جمعی با وردپرس ── -->
                <div class="step-card">
                    <div class="step-title"><span class="step-num">۵</span> همگام‌سازی جمعی با وردپرس</div>
                    <p style="font-size:12.5px;color:#6b7280;margin-bottom:12px;">
                        بعد از ایمپورت، برای گرفتن قیمت فروش واقعی، وزن، ابعاد و تصویر از سایت، دکمه زیر را بزنید.
                    </p>
                    <div class="stat-row">
                        <div class="s-box"><div class="s-val" id="sync-remaining">—</div><div class="s-lbl">منتظر سینک</div></div>
                        <div class="s-box"><div class="s-val" id="sync-done" style="color:#16a34a;">0</div><div class="s-lbl">سینک شده</div></div>
                        <div class="s-box"><div class="s-val" id="sync-notfound" style="color:#d97706;">0</div><div class="s-lbl">یافت نشد</div></div>
                    </div>
                    <button class="btn btn-success" id="btn-bulk-sync" style="width:100%;">🔄 شروع همگام‌سازی جمعی</button>
                </div>
            </div>

        </div>
    </div>

<?php
$content = ob_get_clean();
$extra_js = '
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/persian-date@1.1.0/dist/persian-date.min.jss"></script>
<script src="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"></script>
<script>
    var AJAX = "../ajax/";
    var CUR  = "' . $currency_symbol . '";
</script>
<script src="../assets/js/import-products.js"></script>
<script src="../assets/js/import-existing-products.js"></script>

';
require_once __DIR__ . '/layout.php';
?>
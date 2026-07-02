<?php
/**
 * MN Order Panel - Scheduler Settings
 * تنظیمات زمان‌بندی خودکار
 */

session_start();
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/class-scheduler.php';

$scheduler = MN_Scheduler::get_instance();

// handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $input  = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $input['action'] ?? '';
    $id     = intval($input['id'] ?? 0);

    try {
        if ($action === 'toggle') {
            $scheduler->toggle($id, (bool)$input['enabled']);
            echo json_encode(['success' => true]);
        } elseif ($action === 'run_now') {
            $scheduler->run_now($id);
            echo json_encode(['success' => true, 'message' => '✓ job اجرا شد']);
        } elseif ($action === 'update_interval') {
            $interval = intval($input['interval_sec'] ?? 1800);
            MN_Database::get_instance()->update('mn_scheduler',
                ['interval_sec' => $interval],
                ['id' => $id]
            );
            echo json_encode(['success' => true]);
        } else {
            throw new Exception('action نامعتبر');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

$page_title = 'زمان‌بندی خودکار - پنل';
$jobs = $scheduler->get_jobs();

$extra_css = '
<style>
.scheduler-card { background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden; }
.sch-header { padding:16px 20px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;gap:10px; }
.sch-header h2 { font-size:14px;font-weight:700;color:#374151;margin:0; }

.sch-row {
    display:flex;align-items:center;gap:16px;
    padding:18px 20px;border-bottom:1px solid #f3f4f6;flex-wrap:wrap;
}
.sch-row:last-child { border-bottom:none; }
.sch-name { font-weight:700;color:#111827;font-size:14px;min-width:180px; }
.sch-meta { font-size:12px;color:#6b7280; }
.sch-meta span { margin-left:12px; }
.sch-status { font-size:12px;padding:3px 10px;border-radius:20px;font-weight:600; }
.sch-running { background:#dbeafe;color:#1e40af; }
.sch-waiting { background:#f0fdf4;color:#16a34a; }
.sch-disabled{ background:#f3f4f6;color:#9ca3af; }
.sch-result  { font-size:11px;color:#6b7280;background:#f9fafb;padding:6px 10px;border-radius:6px;max-width:400px;word-break:break-word; }

/* toggle switch */
.toggle-sw { position:relative;width:40px;height:22px;flex-shrink:0; }
.toggle-sw input { opacity:0;width:0;height:0; }
.toggle-sl { position:absolute;inset:0;background:#d1d5db;border-radius:22px;cursor:pointer;transition:background .2s; }
.toggle-sl:before { content:"";position:absolute;width:16px;height:16px;left:3px;top:3px;background:#fff;border-radius:50%;transition:transform .2s; }
.toggle-sw input:checked + .toggle-sl { background:#16a34a; }
.toggle-sw input:checked + .toggle-sl:before { transform:translateX(18px); }

.interval-input { width:80px;padding:6px 8px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;font-family:inherit;text-align:center; }
.btn-run { padding:7px 14px;background:#7c3aed;color:#fff;border:none;border-radius:7px;font-size:12px;font-family:inherit;cursor:pointer;font-weight:600;transition:background .15s; }
.btn-run:hover { background:#6d28d9; }
.btn-run:disabled { opacity:.5;cursor:not-allowed; }

.help-box { background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:14px 18px;margin-bottom:20px;font-size:13px;color:#92400e; }
.help-box strong { display:block;margin-bottom:6px;font-size:14px; }
</style>
';

ob_start();
?>

<div class="help-box">
    <strong>⚙️ نحوه کار Scheduler</strong>
    هر بار که یک صفحه پنل باز می‌شود، scheduler چک می‌کند آیا وقت اجرای یک job رسیده یا نه.
    اگر رسیده بود، در پس‌زمینه اجرا می‌کند و صفحه ادمین منتظر نمی‌ماند.
    <br><br>
    <strong>interval</strong> را به ثانیه وارد کنید — مثال: 1800 = هر 30 دقیقه | 3600 = هر 1 ساعت
</div>

<div class="scheduler-card">
    <div class="sch-header">
        <span style="font-size:20px;">⏰</span>
        <h2>Jobهای زمان‌بندی‌شده</h2>
        <span style="margin-right:auto;font-size:12px;color:#6b7280;" id="sch-msg"></span>
    </div>

    <?php if (empty($jobs)): ?>
    <div style="padding:48px;text-align:center;color:#9ca3af;">
        هیچ jobی ثبت نشده. پس از باز کردن هر صفحه‌ای از پنل، jobها خودکار ثبت می‌شوند.
    </div>
    <?php else: ?>

    <?php foreach ($jobs as $job):
        $status_class = $job->is_running ? 'sch-running' : ($job->enabled ? 'sch-waiting' : 'sch-disabled');
        $status_text  = $job->is_running ? '⏳ در حال اجرا' : ($job->enabled ? '✓ فعال' : '— غیرفعال');
        $next_run     = $job->next_run ? date('H:i — Y/m/d', strtotime($job->next_run)) : '—';
        $last_run     = $job->last_run ? date('H:i — Y/m/d', strtotime($job->last_run)) : 'هنوز اجرا نشده';

        $job_labels = [
            'wc_sales_sync' => '🔄 همگام‌سازی فروش WooCommerce',
        ];
        $label = $job_labels[$job->job_name] ?? $job->job_name;
    ?>
    <div class="sch-row" id="job-<?php echo $job->id; ?>">

        <!-- toggle -->
        <label class="toggle-sw">
            <input type="checkbox" <?php echo $job->enabled ? 'checked' : ''; ?>
                   onchange="toggleJob(<?php echo $job->id; ?>, this.checked)">
            <span class="toggle-sl"></span>
        </label>

        <div style="flex:1;min-width:200px;">
            <div class="sch-name"><?php echo htmlspecialchars($label); ?></div>
            <div class="sch-meta">
                <span>⏱ interval:</span>
                <input type="number" class="interval-input" value="<?php echo $job->interval_sec; ?>"
                       id="interval-<?php echo $job->id; ?>"
                       onchange="updateInterval(<?php echo $job->id; ?>, this.value)"
                       min="60" step="60"> ثانیه
            </div>
            <div class="sch-meta" style="margin-top:4px;">
                <span>🕐 آخرین اجرا: <?php echo $last_run; ?></span>
                <span>⏭ بعدی: <?php echo $next_run; ?></span>
            </div>
            <?php if ($job->last_result): ?>
            <div class="sch-result" style="margin-top:6px;"><?php echo htmlspecialchars($job->last_result); ?></div>
            <?php endif; ?>
        </div>

        <span class="sch-status <?php echo $status_class; ?>"><?php echo $status_text; ?></span>

        <button class="btn-run" id="run-<?php echo $job->id; ?>"
                onclick="runNow(<?php echo $job->id; ?>)" <?php echo $job->is_running ? 'disabled' : ''; ?>>
            ▶ اجرا الان
        </button>

    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();

$extra_js = '
<script>
var SCH_URL = "../ajax/scheduler-settings.php";

function toggleJob(id, enabled) {
    fetch(SCH_URL, {
        method: "POST",
        headers: {"Content-Type":"application/json"},
        body: JSON.stringify({action:"toggle", id:id, enabled:enabled}),
    }).then(function(r){return r.json();}).then(function(res){
        showMsg(res.success ? "✓ ذخیره شد" : "✗ " + res.message);
    });
}

function updateInterval(id, val) {
    fetch(SCH_URL, {
        method: "POST",
        headers: {"Content-Type":"application/json"},
        body: JSON.stringify({action:"update_interval", id:id, interval_sec:parseInt(val)}),
    }).then(function(r){return r.json();}).then(function(res){
        showMsg(res.success ? "✓ interval ذخیره شد" : "✗ " + res.message);
    });
}

function runNow(id) {
    var btn = document.getElementById("run-" + id);
    btn.disabled = true;
    btn.textContent = "⏳";
    fetch(SCH_URL, {
        method: "POST",
        headers: {"Content-Type":"application/json"},
        body: JSON.stringify({action:"run_now", id:id}),
    }).then(function(r){return r.json();}).then(function(res){
        showMsg(res.success ? "✓ job در پس‌زمینه اجرا شد" : "✗ " + res.message);
        setTimeout(function(){ location.reload(); }, 3000);
    });
}

function showMsg(msg) {
    var el = document.getElementById("sch-msg");
    el.textContent = msg;
    setTimeout(function(){ el.textContent = ""; }, 4000);
}
</script>
';

require_once __DIR__ . '/layout.php';
?>

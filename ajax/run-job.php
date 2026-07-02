<?php
/**
 * MN Order Panel - Background Job Runner
 * فقط از scheduler صدا زده میشه (internal HTTP)
 */

ignore_user_abort(true);
set_time_limit(120);

// پاسخ فوری به scheduler — ادامه در background
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    ob_start();
    header('Content-Length: 0');
    header('Connection: close');
    ob_end_flush();
    flush();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../includes/class-scheduler.php';

// ── Security: فقط از scheduler قابل اجراست ──
$headers  = function_exists('getallheaders') ? getallheaders() : [];
$secret   = $headers['X-Scheduler-Secret'] ?? '';
$expected = MN_Settings::get('scheduler_secret', 'mn_scheduler');

if ($secret !== $expected) {
    http_response_code(403);
    error_log('run-job.php: unauthorized request');
    exit;
}

// ── دریافت پارامترها ──
$input    = json_decode(file_get_contents('php://input'), true) ?? [];
$job_name = trim($input['job_name'] ?? '');
$job_id   = intval($input['job_id']   ?? 0);

if (!$job_name || !$job_id) {
    error_log('run-job.php: missing job_name or job_id');
    exit;
}

error_log("Scheduler: running job [{$job_name}] id={$job_id}");

try {

    switch ($job_name) {

        case 'wc_sales_sync':
            require_once __DIR__ . '/../config/wp-bridge.php';
            require_once __DIR__ . '/../includes/class-wc-sales-sync.php';

            $sync   = new MN_WC_Sales_Sync(20);
            $result = $sync->run();

            $msg = $result['message'] ?? '';
            if (!empty($result['errors'])) {
                $msg .= ' | errors: ' . implode(', ', array_slice($result['errors'], 0, 3));
            }
            MN_Scheduler::mark_done($job_id, $msg);
            error_log("Scheduler job [{$job_name}] done: {$msg}");
            break;

        default:
            throw new Exception('Job ناشناخته: ' . $job_name);
    }

} catch (Exception $e) {
    MN_Scheduler::mark_failed($job_id, $e->getMessage());
    error_log("Scheduler job [{$job_name}] failed: " . $e->getMessage());
}
<?php
/**
 * DEBUG scheduler — بعد از debug حذف کن
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

$db = MN_Database::get_instance();

// وضعیت جدول
$jobs = $db->get_results("SELECT * FROM mn_scheduler");

// چک کن db->get_var UPDATE کار میکنه یا نه
$test_update = $db->get_var("SELECT 1");

// چک URL ساخته شده
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? '';
$script   = $_SERVER['SCRIPT_NAME'] ?? '';
$base_regex = preg_replace('#/ajax/[^/]+\.php$#', '', $script);
$url_built  = $protocol . '://' . $host . $base_regex . '/ajax/run-job.php';

// چک panel_base_url در settings
$panel_base_url = MN_Settings::get('panel_base_url', 'NOT SET');

// چک curl موجوده؟
$curl_available = function_exists('curl_init');

// چک now vs next_run
$due = $db->get_results("
    SELECT id, job_name, is_running, next_run, 
           NOW() as server_now,
           (next_run <= NOW()) as is_due
    FROM mn_scheduler
");

echo json_encode([
    'jobs'            => $jobs,
    'due_check'       => $due,
    'url_from_script' => $url_built,
    'panel_base_url'  => $panel_base_url,
    'curl_available'  => $curl_available,
    'server_time'     => date('Y-m-d H:i:s'),
    'db_test'         => $test_update,
    'php_sapi'        => php_sapi_name(),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
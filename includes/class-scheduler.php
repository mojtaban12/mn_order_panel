<?php
/**
 * MN Order Panel - Internal Scheduler
 */

if (class_exists('MN_Scheduler')) return;

require_once __DIR__ . '/../config/database.php';

class MN_Scheduler {

    private static $instance = null;
    private $db;

    private function __construct() {
        $this->db = MN_Database::get_instance();
    }

    public static function get_instance() {
        if (!self::$instance) self::$instance = new self();
        return self::$instance;
    }

    // ════════════════════════════════════════
    // tick
    // ════════════════════════════════════════
    public function tick() {
        try {
            // آزاد کردن jobهای منجمد (بیش از 5 دقیقه is_running=1)
            $frozen = $this->db->get_results("
                SELECT id FROM mn_scheduler
                WHERE is_running = 1
                  AND last_run < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ");
            foreach ($frozen as $f) {
                $this->db->update('mn_scheduler', ['is_running' => 0], ['id' => $f->id]);
                error_log('Scheduler: released frozen job id=' . $f->id);
            }

            // jobهایی که وقتشون رسیده
            $due = $this->db->get_results("
                SELECT * FROM mn_scheduler
                WHERE enabled   = 1
                  AND is_running = 0
                  AND (next_run IS NULL OR next_run <= NOW())
                ORDER BY next_run ASC
            ");

            foreach ($due as $job) {
                $this->fire($job);
            }

        } catch (Exception $e) {
            error_log('Scheduler tick error: ' . $e->getMessage());
        }
    }

    // ════════════════════════════════════════
    // fire
    // ════════════════════════════════════════
    private function fire($job) {
        // mark is_running=1 قبل از ارسال
        $this->db->update('mn_scheduler', [
            'is_running' => 1,
            'last_run'   => date('Y-m-d H:i:s'),
        ], ['id' => $job->id]);

        $url = $this->build_url('/ajax/run-job.php');
        if (!$url) {
            error_log('Scheduler: cannot build URL');
            $this->db->update('mn_scheduler', ['is_running' => 0], ['id' => $job->id]);
            return;
        }

        $this->async_post($url, [
            'job_name' => $job->job_name,
            'job_id'   => $job->id,
        ]);
    }

    // ════════════════════════════════════════
    // URL
    // ════════════════════════════════════════
    private function build_url($path) {
        try {
            require_once __DIR__ . '/../config/settings.php';
            $base = rtrim(MN_Settings::get('panel_base_url', ''), '/');
            if ($base) return $base . $path;
        } catch (Exception $e) {}

        if (empty($_SERVER['HTTP_HOST'])) return null;
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $script   = $_SERVER['SCRIPT_NAME'] ?? '';
        $base     = preg_replace('#/(pages|ajax)/[^/]+\.php$#', '', $script);
        return $protocol . '://' . $_SERVER['HTTP_HOST'] . rtrim($base, '/') . $path;
    }

    // ════════════════════════════════════════
    // Non-blocking POST
    // ════════════════════════════════════════
    private function async_post($url, $data) {
        $body   = json_encode($data);
        $secret = $this->get_secret();

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($body),
                'X-Scheduler-Secret: ' . $secret,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_NOSIGNAL       => 1,
        ]);
        curl_exec($ch);
        $err = curl_error($ch);
        if ($err) error_log('Scheduler cURL: ' . $err . ' → ' . $url);
        curl_close($ch);
    }

    // ════════════════════════════════════════
    // register
    // ════════════════════════════════════════
    public function register($job_name, $interval_sec, $enabled = true) {
        $exists = $this->db->get_var(
            "SELECT id FROM mn_scheduler WHERE job_name = ?", [$job_name]
        );

        if (!$exists) {
            $this->db->insert('mn_scheduler', [
                'job_name'     => $job_name,
                'interval_sec' => $interval_sec,
                'next_run'     => date('Y-m-d H:i:s'),
                'enabled'      => $enabled ? 1 : 0,
            ]);
        }
        // اگر هست → دست نزن (next_run رو حفظ کن)
    }

    // ════════════════════════════════════════
    // mark_done / mark_failed
    // ════════════════════════════════════════
    public static function mark_done($job_id, $result_msg) {
        try {
            $db  = MN_Database::get_instance();
            $job = $db->get_row(
                "SELECT interval_sec FROM mn_scheduler WHERE id = ?", [$job_id]
            );
            if (!$job) return;

            $next = date('Y-m-d H:i:s', time() + intval($job->interval_sec));
            $db->update('mn_scheduler', [
                'is_running'  => 0,
                'last_run'    => date('Y-m-d H:i:s'),
                'next_run'    => $next,
                'last_result' => mb_substr($result_msg, 0, 500),
            ], ['id' => $job_id]);

        } catch (Exception $e) {
            error_log('Scheduler mark_done: ' . $e->getMessage());
        }
    }

    public static function mark_failed($job_id, $error_msg) {
        try {
            $db   = MN_Database::get_instance();
            // retry بعد از 5 دقیقه
            $next = date('Y-m-d H:i:s', time() + 300);
            $db->update('mn_scheduler', [
                'is_running'  => 0,
                'next_run'    => $next,
                'last_result' => '❌ ' . mb_substr($error_msg, 0, 490),
            ], ['id' => $job_id]);

        } catch (Exception $e) {
            error_log('Scheduler mark_failed: ' . $e->getMessage());
        }
    }

    // ════════════════════════════════════════
    // UI helpers
    // ════════════════════════════════════════
    public function get_jobs() {
        return $this->db->get_results(
            "SELECT * FROM mn_scheduler ORDER BY job_name"
        );
    }

    public function toggle($job_id, $enabled) {
        return $this->db->update('mn_scheduler',
            ['enabled' => $enabled ? 1 : 0],
            ['id' => $job_id]
        );
    }

    public function run_now($job_id) {
        $job = $this->db->get_row(
            "SELECT * FROM mn_scheduler WHERE id = ?", [$job_id]
        );
        if (!$job || $job->is_running) return false;
        $this->fire($job);
        return true;
    }

    // ════════════════════════════════════════
    // secret
    // ════════════════════════════════════════
    private function get_secret() {
        try {
            require_once __DIR__ . '/../config/settings.php';
            $s = MN_Settings::get('scheduler_secret', '');
            if (!$s) {
                $s = bin2hex(random_bytes(16));
                $this->db->insert('mn_settings', [
                    'setting_key'   => 'scheduler_secret',
                    'setting_value' => $s,
                ]);
            }
            return $s;
        } catch (Exception $e) {
            return 'mn_scheduler_default';
        }
    }
}
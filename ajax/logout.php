<?php
/**
 * MN Order Panel - Logout API (AJAX)
 * خروج از سیستم — نسخه AJAX برای فراخوانی از جاوااسکریپت
 */

header('Content-Type: application/json; charset=utf-8');

session_start();

require_once __DIR__ . '/../config/database.php';

try {
    $db = MN_Database::get_instance();

    // ثبت لاگ خروج
    if (isset($_SESSION['panel_user_id'])) {
        $db->query(
            "INSERT INTO mn_login_log (user_id, username, ip_address, user_agent, status)
             VALUES (?, ?, ?, ?, 'logout')",
            [
                $_SESSION['panel_user_id'],
                $_SESSION['panel_username'] ?? '',
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]
        );
    }

    // حذف remember token
    if (isset($_COOKIE['mn_remember_token'])) {
        $token = $_COOKIE['mn_remember_token'];

        $db->query("DELETE FROM mn_user_tokens WHERE token = ?", [$token]);

        setcookie('mn_remember_token', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }

    // پاک کردن session
    $_SESSION = [];
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    session_destroy();

    echo json_encode([
        'success'  => true,
        'message'  => 'با موفقیت خارج شدید',
        'redirect' => '../pages/login.php'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    $_SESSION = [];
    session_destroy();

    echo json_encode([
        'success'  => true,
        'redirect' => '../pages/login.php'
    ], JSON_UNESCAPED_UNICODE);

    error_log('Logout Error: ' . $e->getMessage());
}
<?php
/**
 * MN Order Panel - Auth Check Middleware
 * چک کردن لاگین بودن کاربر
 * 
 * این فایل رو در ابتدای هر صفحه که نیاز به لاگین داره include کن
 * مثال: require_once __DIR__ . '/../includes/auth-check.php';
 */

// شروع session اگه شروع نشده
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// چک کردن timeout session (30 دقیقه)
$session_timeout = 1800; // 30 minutes
if (isset($_SESSION['panel_last_activity'])) {
    if (time() - $_SESSION['panel_last_activity'] > $session_timeout) {
        // Session timeout
        session_unset();
        session_destroy();
        
        header('Location: login.php?timeout=1');
        exit;
    }
}

// بروزرسانی last activity
$_SESSION['panel_last_activity'] = time();

// چک کردن لاگین بودن
if (!isset($_SESSION['panel_user_id'])) {
    // چک کردن remember token
    if (isset($_COOKIE['mn_remember_token'])) {
        require_once __DIR__ . '/../config/database.php';
        
        try {
            $db = MN_Database::get_instance();
            $token = $_COOKIE['mn_remember_token'];
            
            // دریافت token از دیتابیس
            $token_data = $db->get_row(
                "SELECT ut.*, u.* 
                 FROM mn_user_tokens ut
                 INNER JOIN mn_panel_users u ON ut.user_id = u.id
                 WHERE ut.token = ? 
                 AND ut.expires_at > NOW()
                 AND u.status = 'active'",
                [$token]
            );
            
            if ($token_data) {
                // بازسازی session
                $_SESSION['panel_user_id'] = $token_data->user_id;
                $_SESSION['panel_username'] = $token_data->username;
                $_SESSION['panel_full_name'] = $token_data->full_name;
                $_SESSION['panel_role'] = $token_data->role;
                $_SESSION['panel_login_time'] = time();
                $_SESSION['panel_last_activity'] = time();
                
                // ادامه می‌ده به صفحه
                return;
            } else {
                // Token نامعتبر - حذف cookie
                setcookie('mn_remember_token', '', time() - 3600, '/');
            }
        } catch (Exception $e) {
            error_log('Auth Check Error: ' . $e->getMessage());
        }
    }
    
    // هدایت به صفحه لاگین
    $current_page = $_SERVER['REQUEST_URI'];
    header('Location: login.php?redirect=' . urlencode($current_page));
    exit;
}

// تابع کمکی: چک کردن نقش کاربر
function check_role($required_role) {
    if (!isset($_SESSION['panel_role'])) {
        return false;
    }
    
    // سلسله مراتب نقش‌ها: admin > operator
    $roles = ['admin' => 2, 'operator' => 1];
    
    $user_role_level = $roles[$_SESSION['panel_role']] ?? 0;
    $required_role_level = $roles[$required_role] ?? 0;
    
    return $user_role_level >= $required_role_level;
}

// تابع کمکی: دریافت اطلاعات کاربر فعلی
function get_panel_user() {
    return [
        'id' => $_SESSION['panel_user_id'] ?? null,
        'username' => $_SESSION['panel_username'] ?? null,
        'full_name' => $_SESSION['panel_full_name'] ?? null,
        'role' => $_SESSION['panel_role'] ?? null
    ];
}
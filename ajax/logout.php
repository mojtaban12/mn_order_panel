<?php
/**
 * MN Order Panel - Login API
 * API ورود به سیستم
 */

header('Content-Type: application/json; charset=utf-8');

session_start();

// اگه قبلاً لاگین کرده
if (isset($_SESSION['panel_user_id'])) {
    echo json_encode([
        'success' => true,
        'message' => 'قبلاً وارد شده‌اید',
        'redirect' => '../pages/orders-list.php'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['username']) || !isset($input['password'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'نام کاربری و رمز عبور الزامی است'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../config/database.php';

try {
    $db = MN_Database::get_instance();
    
    $username = trim($input['username']);
    $password = $input['password'];
    $remember = isset($input['remember']) ? (bool)$input['remember'] : false;
    
    // دریافت کاربر از دیتابیس
    $user = $db->get_row(
        "SELECT * FROM mn_panel_users WHERE username = ? AND status = 'active'",
        [$username]
    );
    
    if (!$user) {
        // تاخیر برای جلوگیری از Brute Force
        sleep(1);
        
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'نام کاربری یا رمز عبور اشتباه است'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // بررسی رمز عبور
    if (!password_verify($password, $user->password)) {
        // تاخیر برای جلوگیری از Brute Force
        sleep(1);
        
        // ثبت تلاش ناموفق
        $db->query(
            "UPDATE mn_panel_users 
             SET failed_login_attempts = failed_login_attempts + 1,
                 last_login_attempt = NOW()
             WHERE id = ?",
            [$user->id]
        );
        
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'نام کاربری یا رمز عبور اشتباه است'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // چک کردن قفل بودن حساب
    if ($user->failed_login_attempts >= 5) {
        $locked_until = strtotime($user->last_login_attempt . ' +15 minutes');
        
        if (time() < $locked_until) {
            $remaining = ceil(($locked_until - time()) / 60);
            
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'message' => "حساب شما به دلیل تلاش‌های ناموفق قفل شده. {$remaining} دقیقه دیگر تلاش کنید."
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    
    // ورود موفق
    // ساخت session
    $_SESSION['panel_user_id'] = $user->id;
    $_SESSION['panel_username'] = $user->username;
    $_SESSION['panel_full_name'] = $user->full_name;
    $_SESSION['panel_role'] = $user->role;
    $_SESSION['panel_login_time'] = time();
    $_SESSION['panel_last_activity'] = time();
    
    // ساخت token برای remember me
    if ($remember) {
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        // ذخیره token در دیتابیس
        $db->query(
            "INSERT INTO mn_user_tokens (user_id, token, expires_at)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE token = ?, expires_at = ?",
            [$user->id, $token, $expiry, $token, $expiry]
        );
        
        // ساخت cookie
        setcookie(
            'mn_remember_token',
            $token,
            [
                'expires' => strtotime('+30 days'),
                'path' => '/',
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax'
            ]
        );
    }
    
    // بروزرسانی اطلاعات ورود
    $db->query(
        "UPDATE mn_panel_users 
         SET failed_login_attempts = 0,
             last_login = NOW(),
             last_login_ip = ?
         WHERE id = ?",
        [$_SERVER['REMOTE_ADDR'], $user->id]
    );
    
    // ثبت لاگ ورود
    $db->query(
        "INSERT INTO mn_login_log (user_id, username, ip_address, user_agent, status)
         VALUES (?, ?, ?, ?, 'success')",
        [
            $user->id,
            $user->username,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'ورود موفقیت‌آمیز',
        'user' => [
            'id' => $user->id,
            'username' => $user->username,
            'full_name' => $user->full_name,
            'role' => $user->role
        ],
        'redirect' => '../pages/orders-list.php'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطا در ورود به سیستم'
    ], JSON_UNESCAPED_UNICODE);
    
    error_log('Login Error: ' . $e->getMessage());
}
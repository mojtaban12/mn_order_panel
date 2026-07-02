<?php
/**
 * MN Order Panel - Login Page
 * صفحه ورود
 */

session_start();

// اگه قبلاً لاگین کرده، به صفحه اصلی هدایت کن
if (isset($_SESSION['panel_user_id'])) {
    header('Location: orders-list.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به پنل ثبت سفارش</title>
    
    <!-- Bootstrap RTL -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="../assets/css/all.min.css">
    
    <style>
        body {
            font-family: 'Vazirmatn', 'Segoe UI', Tahoma, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            max-width: 450px;
            width: 100%;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.5s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .login-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        
        .login-icon i {
            font-size: 2.5rem;
            color: white;
        }
        
        .login-title {
            font-size: 1.8rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        
        .login-subtitle {
            color: #666;
            font-size: 0.95rem;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-label {
            font-weight: 600;
            color: #555;
            margin-bottom: 8px;
            display: block;
        }
        
        .input-group {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            z-index: 10;
        }
        
        .form-control {
            padding: 12px 45px 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
        }
        
        .btn-login {
            width: 100%;
            padding: 14px;
            font-size: 1.1rem;
            font-weight: 600;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            color: white;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .btn-login:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .btn-login .spinner-border {
            width: 1.2rem;
            height: 1.2rem;
            border-width: 2px;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            padding: 12px 15px;
            margin-bottom: 20px;
            animation: shake 0.5s;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .remember-me input[type="checkbox"] {
            margin-left: 8px;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .remember-me label {
            margin: 0;
            cursor: pointer;
            user-select: none;
            color: #666;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 30px;
            color: #999;
            font-size: 0.9rem;
        }
        
        .password-toggle {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #999;
            z-index: 10;
            transition: color 0.3s;
        }
        
        .password-toggle:hover {
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h1 class="login-title">پنل ثبت سفارش</h1>
                <p class="login-subtitle">برای ادامه وارد شوید</p>
            </div>
            
            <!-- پیام خطا -->
            <div id="alert-box" style="display: none;"></div>
            
            <form id="login-form">
                <div class="form-group">
                    <label class="form-label">نام کاربری</label>
                    <div class="input-group">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" 
                               class="form-control" 
                               id="username" 
                               name="username"
                               placeholder="نام کاربری خود را وارد کنید"
                               autocomplete="username"
                               required
                               autofocus>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">رمز عبور</label>
                    <div class="input-group">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" 
                               class="form-control" 
                               id="password" 
                               name="password"
                               placeholder="رمز عبور خود را وارد کنید"
                               autocomplete="current-password"
                               required>
                        <i class="fas fa-eye password-toggle" id="password-toggle"></i>
                    </div>
                </div>
                
                <div class="remember-me">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">مرا به خاطر بسپار</label>
                </div>
                
                <button type="submit" class="btn btn-login" id="login-btn">
                    <span id="login-text">ورود</span>
                    <span id="login-spinner" style="display: none;">
                        <span class="spinner-border spinner-border-sm" role="status"></span>
                        در حال ورود...
                    </span>
                </button>
            </form>
            
            <div class="login-footer">
                <p>طراحی و پیاده سازی توسط کیوان کلود</p>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="../assets/js/jquery.min.js"></script>
    
    <script>
        // نمایش/مخفی کردن رمز عبور
        $('#password-toggle').on('click', function() {
            const passwordField = $('#password');
            const icon = $(this);
            
            if (passwordField.attr('type') === 'password') {
                passwordField.attr('type', 'text');
                icon.removeClass('fa-eye').addClass('fa-eye-slash');
            } else {
                passwordField.attr('type', 'password');
                icon.removeClass('fa-eye-slash').addClass('fa-eye');
            }
        });
        
        // نمایش پیام
        function showAlert(message, type = 'danger') {
            const alertBox = $('#alert-box');
            alertBox.html(`
                <div class="alert alert-${type}">
                    <i class="fas fa-${type === 'danger' ? 'exclamation-circle' : 'check-circle'}"></i>
                    ${message}
                </div>
            `).show();
            
            // مخفی کردن خودکار بعد از 5 ثانیه
            if (type === 'success') {
                setTimeout(() => alertBox.fadeOut(), 5000);
            }
        }
        
        // ارسال فرم
        $('#login-form').on('submit', function(e) {
            e.preventDefault();
            
            const username = $('#username').val().trim();
            const password = $('#password').val();
            const remember = $('#remember').is(':checked');
            
            if (!username || !password) {
                showAlert('لطفاً نام کاربری و رمز عبور را وارد کنید');
                return;
            }
            
            // غیرفعال کردن دکمه
            $('#login-btn').prop('disabled', true);
            $('#login-text').hide();
            $('#login-spinner').show();
            $('#alert-box').hide();
            
            // ارسال درخواست
            $.ajax({
                url: '../ajax/login.php',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    username: username,
                    password: password,
                    remember: remember
                }),
                success: function(response) {
                    if (response.success) {
                        showAlert('ورود موفقیت‌آمیز بود! در حال انتقال...', 'success');
                        
                        setTimeout(() => {
                            window.location.href = response.redirect || 'orders-list.php';
                        }, 1000);
                    } else {
                        showAlert(response.message || 'نام کاربری یا رمز عبور اشتباه است');
                        resetButton();
                    }
                },
                error: function(xhr) {
                    if (xhr.status === 401) {
                        showAlert('نام کاربری یا رمز عبور اشتباه است');
                    } else {
                        showAlert('خطا در ارتباط با سرور. لطفاً دوباره تلاش کنید.');
                    }
                    resetButton();
                }
            });
        });
        
        function resetButton() {
            $('#login-btn').prop('disabled', false);
            $('#login-text').show();
            $('#login-spinner').hide();
        }
        
        // Focus روی اولین input
        $(document).ready(function() {
            $('#username').focus();
        });
        
        // Enter در هر input
        $('#username, #password').on('keypress', function(e) {
            if (e.which === 13) {
                $('#login-form').submit();
            }
        });
    </script>
</body>
</html>
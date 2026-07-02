<?php
/**
 * تولید Hash رمز عبور جدید
 * این فایل رو آپلود کن و در مرورگر باز کن
 */

// رمز عبور دلخواه
$password = 'admin123'; // این رو تغییر بده

// تولید hash
$hash = password_hash($password, PASSWORD_DEFAULT);

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>Password Hash Generator</title>
    <style>
        body {
            font-family: 'Vazirmatn', Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #667eea;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            border-right: 4px solid #667eea;
        }
        .success {
            background: #d4edda;
            border-right-color: #28a745;
            color: #155724;
        }
        .code {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            direction: ltr;
            text-align: left;
            overflow-x: auto;
        }
        .btn {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background: #5568d3;
        }
        input[type="text"] {
            width: 100%;
            padding: 10px;
            font-size: 16px;
            border: 2px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>🔐 Password Hash Generator</h1>
        
        <form method="post">
            <div style="margin: 20px 0;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">رمز عبور جدید:</label>
                <input type="text" name="new_password" value="<?php echo htmlspecialchars($password); ?>" required>
            </div>
            <button type="submit" class="btn">تولید Hash</button>
        </form>

        <?php if (isset($_POST['new_password'])): 
            $password = $_POST['new_password'];
            $hash = password_hash($password, PASSWORD_DEFAULT);
        ?>
        
        <div class="info success" style="margin-top: 20px;">
            <strong>✓ Hash با موفقیت تولید شد!</strong>
        </div>

        <div class="info">
            <strong>رمز عبور:</strong><br>
            <code><?php echo htmlspecialchars($password); ?></code>
        </div>

        <div class="info">
            <strong>Hash:</strong><br>
            <div class="code"><?php echo $hash; ?></div>
        </div>

        <div class="info">
            <strong>Query برای آپدیت دیتابیس:</strong><br>
            <div class="code">
UPDATE mn_panel_users 
SET password = '<?php echo $hash; ?>' 
WHERE username = 'admin';
            </div>
        </div>

        <div class="info">
            <strong>تست Hash:</strong><br>
            <?php 
            $verify = password_verify($password, $hash);
            echo $verify ? '✓ Hash صحیح است' : '✗ خطا در Hash';
            ?>
        </div>

        <?php endif; ?>

        <hr style="margin: 30px 0;">

        <h2>🔍 تست Hash موجود</h2>
        <form method="post">
            <div style="margin: 20px 0;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Hash موجود:</label>
                <input type="text" name="test_hash" placeholder="Hash را اینجا وارد کنید" required>
            </div>
            <div style="margin: 20px 0;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">رمز عبور برای تست:</label>
                <input type="text" name="test_password" placeholder="رمز عبور را وارد کنید" required>
            </div>
            <button type="submit" name="test" class="btn">تست کن</button>
        </form>

        <?php if (isset($_POST['test'])): 
            $test_hash = $_POST['test_hash'];
            $test_password = $_POST['test_password'];
            $result = password_verify($test_password, $test_hash);
        ?>
        
        <div class="info <?php echo $result ? 'success' : ''; ?>" style="margin-top: 20px;">
            <?php if ($result): ?>
                <strong>✓ رمز عبور صحیح است!</strong><br>
                رمز عبور "<strong><?php echo htmlspecialchars($test_password); ?></strong>" با Hash مطابقت دارد.
            <?php else: ?>
                <strong>✗ رمز عبور اشتباه است</strong><br>
                Hash با رمز عبور مطابقت ندارد.
            <?php endif; ?>
        </div>

        <?php endif; ?>
    </div>
</body>
</html>
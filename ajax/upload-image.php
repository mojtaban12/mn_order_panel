<?php
/**
 * MN Order Panel - Upload Product Image
 * آپلود تصویر محصول به فولدر images/
 */

header('Content-Type: application/json; charset=utf-8');

session_start();

// بررسی لاگین
if (empty($_SESSION['panel_user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'فقط POST مجاز است']);
    exit;
}

try {
    if (empty($_FILES['image'])) {
        throw new Exception('فایلی آپلود نشده');
    }

    $file  = $_FILES['image'];
    $error = $file['error'];

    if ($error !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE   => 'حجم فایل بیشتر از حد مجاز سرور است',
            UPLOAD_ERR_FORM_SIZE  => 'حجم فایل بیشتر از حد مجاز فرم است',
            UPLOAD_ERR_PARTIAL    => 'فایل ناقص آپلود شد',
            UPLOAD_ERR_NO_FILE    => 'فایلی انتخاب نشده',
            UPLOAD_ERR_NO_TMP_DIR => 'پوشه موقت وجود ندارد',
            UPLOAD_ERR_CANT_WRITE => 'خطا در نوشتن روی دیسک',
        ];
        throw new Exception($errors[$error] ?? 'خطای ناشناخته در آپلود');
    }

    // ── بررسی نوع فایل ──────────────────────
    $allowed_mime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $allowed_ext  = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mime     = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowed_mime)) {
        throw new Exception('فرمت فایل مجاز نیست. فقط JPG، PNG، WebP و GIF');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext)) {
        throw new Exception('پسوند فایل معتبر نیست');
    }

    // ── بررسی حجم (5MB) ─────────────────────
    $max_size = 5 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        throw new Exception('حجم فایل نباید بیشتر از ۵ مگابایت باشد');
    }

    // ── ساخت پوشه images/ ───────────────────
    // پوشه images در ریشه پروژه (یک سطح بالاتر از ajax/)
    $upload_dir = dirname(__DIR__) . '/images';

    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            throw new Exception('خطا در ساخت پوشه images');
        }
        // فایل htaccess برای جلوگیری از اجرای PHP
        file_put_contents($upload_dir . '/.htaccess',
            "Options -ExecCGI\nAddHandler cgi-script .php .pl .py .sh\nRemoveHandler .php\n"
        );
        // index.php خالی
        file_put_contents($upload_dir . '/index.php', '<?php // silence');
    }

    // ── ساخت نام فایل یکتا ──────────────────
    $filename  = date('Ymd') . '_' . uniqid() . '.' . $ext;
    $dest_path = $upload_dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest_path)) {
        throw new Exception('خطا در ذخیره فایل روی سرور');
    }

    // ── ساخت URL ─────────────────────────────
    // URL نسبت به ریشه پنل
    $base_url = rtrim(
        (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
        . '://' . $_SERVER['HTTP_HOST']
        . dirname(dirname($_SERVER['SCRIPT_NAME'])),
        '/'
    );
    $image_url = $base_url . '/images/' . $filename;

    echo json_encode([
        'success'   => true,
        'url'       => $image_url,
        'filename'  => $filename,
        'size'      => $file['size'],
        'size_fmt'  => format_size($file['size']),
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    error_log('Image upload error: ' . $e->getMessage());
}

function format_size($bytes) {
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}
<?php
/**
 * MN Order Panel - Download WP Product Images
 * دانلود تصاویر محصول وردپرس به فولدر images/ پنل
 */
header('Content-Type: application/json; charset=utf-8');
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'فقط POST']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/wp-bridge.php';
require_once __DIR__ . '/../config/settings.php';

try {
    $input      = json_decode(file_get_contents('php://input'), true) ?? [];
    $wp_id      = intval($input['wp_product_id'] ?? 0);
    $product_id = intval($input['product_id']    ?? 0);

    if (!$wp_id) throw new Exception('wp_product_id الزامی است');

    $wp_bridge   = MN_WP_Bridge::get_instance();
    $wpdb        = $wp_bridge->wpdb;
    $uploads_url = rtrim(MN_Settings::get('wp_uploads_url', ''), '/');

    // ── featured image ───────────────────────
    $thumb_id = $wp_bridge->get_var("
        SELECT meta_value FROM {$wpdb->postmeta}
        WHERE post_id = ? AND meta_key = '_thumbnail_id' LIMIT 1
    ", [$wp_id]);

    // ── gallery ──────────────────────────────
    $gallery_raw = $wp_bridge->get_var("
        SELECT meta_value FROM {$wpdb->postmeta}
        WHERE post_id = ? AND meta_key = '_product_image_gallery' LIMIT 1
    ", [$wp_id]);

    $all_ids = [];
    if ($thumb_id)    $all_ids[] = intval($thumb_id);
    if ($gallery_raw) {
        foreach (explode(',', $gallery_raw) as $gid) {
            $gid = intval(trim($gid));
            if ($gid && !in_array($gid, $all_ids)) $all_ids[] = $gid;
        }
    }

    if (empty($all_ids)) {
        echo json_encode(['success' => true, 'images' => [], 'message' => 'محصول تصویری ندارد'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── file paths از postmeta ───────────────
    $ids_str = implode(',', $all_ids);
    $files   = $wp_bridge->get_results("
        SELECT post_id, meta_value
        FROM {$wpdb->postmeta}
        WHERE post_id IN ({$ids_str}) AND meta_key = '_wp_attached_file'
    ");

    $file_map = [];
    foreach ($files as $f) $file_map[intval($f->post_id)] = $f->meta_value;

    // ── ساخت پوشه images/ ────────────────────
    $upload_dir = dirname(__DIR__) . '/images';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
        file_put_contents($upload_dir . '/.htaccess',
            "Options -ExecCGI\nAddHandler cgi-script .php .pl .py\nRemoveHandler .php\n");
        file_put_contents($upload_dir . '/index.php', '<?php // silence');
    }

    // ── دانلود با cURL ───────────────────────
    $db       = MN_Database::get_instance();
    $saved    = [];
    $is_first = true;
    $sort     = 0;

    if ($product_id) {
        $db->delete('mn_product_images', ['product_id' => $product_id]);
    }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $base_url  = rtrim($protocol . '://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');

    foreach ($all_ids as $att_id) {
        if (!isset($file_map[$att_id])) continue;

        $remote_url = $uploads_url . '/' . ltrim($file_map[$att_id], '/');
        $ext        = strtolower(pathinfo($remote_url, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) $ext = 'jpg';

        $filename   = date('Ymd') . '_wp' . $att_id . '_' . uniqid() . '.' . $ext;
        $local_path = $upload_dir . '/' . $filename;
        $local_url  = $base_url . '/images/' . $filename;

        $ch = curl_init($remote_url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $data = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err || $http !== 200 || empty($data)) {
            error_log("fetch-wp-images: failed $remote_url — $err HTTP$http");
            continue;
        }

        file_put_contents($local_path, $data);

        if ($product_id) {
            $db->insert('mn_product_images', [
                'product_id' => $product_id,
                'image_url'  => $local_url,
                'is_primary' => $is_first ? 1 : 0,
                'sort_order' => $sort,
            ]);
            if ($is_first) {
                $db->update('mn_products', ['image_url' => $local_url], ['id' => $product_id]);
            }
        }

        $saved[]  = ['url' => $local_url, 'att_id' => $att_id, 'is_primary' => $is_first];
        $is_first = false;
        $sort++;
    }

    echo json_encode([
        'success' => true,
        'images'  => $saved,
        'count'   => count($saved),
        'message' => count($saved) . ' تصویر دانلود شد',
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'file'    => basename($e->getFile()),
        'line'    => $e->getLine(),
    ], JSON_UNESCAPED_UNICODE);
    error_log('fetch-wp-images: ' . $e->getMessage());
}
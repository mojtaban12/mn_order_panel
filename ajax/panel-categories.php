<?php
/**
 * MN Order Panel - Panel Categories AJAX
 * عملیات CRUD دسته‌بندی‌های داخلی پنل
 *
 * GET  ?action=list               → لیست همه
 * GET  ?action=tree               → درخت برای dropdown
 * POST action=create              → ایجاد
 * POST action=update&id=N         → ویرایش
 * POST action=delete&id=N         → حذف
 * POST action=assign              → assign به محصول
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/class-panel-category.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = trim($_GET['action'] ?? $_POST['action'] ?? '');
    $id     = intval($_GET['id'] ?? 0);

    $cat = new MN_Panel_Category();

    // ── GET ──────────────────────────────────
    if ($method === 'GET') {

        if ($action === 'tree') {
            $tree = $cat->get_tree();
            echo json_encode(['success' => true, 'categories' => $tree], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // list (default)
        $search = trim($_GET['search'] ?? '');
        $list   = $cat->get_list($search);
        echo json_encode(['success' => true, 'categories' => $list, 'total' => count($list)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── POST ─────────────────────────────────
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($input)) $input = $_POST;

    $action = $action ?: ($input['action'] ?? '');

    // ── create ───────────────────────────────
    if ($action === 'create') {
        if (empty($input['name'])) throw new Exception('نام دسته الزامی است');

        $new_id = $cat->create($input);
        if (!$new_id) throw new Exception('خطا در ایجاد دسته');

        echo json_encode([
            'success'  => true,
            'message'  => '✓ دسته‌بندی ایجاد شد',
            'category' => $cat->get($new_id),
        ], JSON_UNESCAPED_UNICODE);

    // ── update ───────────────────────────────
    } elseif ($action === 'update') {
        $id = $id ?: intval($input['id'] ?? 0);
        if (!$id) throw new Exception('شناسه دسته الزامی است');

        $cat->update($id, $input);
        echo json_encode([
            'success'  => true,
            'message'  => '✓ دسته‌بندی ویرایش شد',
            'category' => $cat->get($id),
        ], JSON_UNESCAPED_UNICODE);

    // ── delete ───────────────────────────────
    } elseif ($action === 'delete') {
        $id = $id ?: intval($input['id'] ?? 0);
        if (!$id) throw new Exception('شناسه دسته الزامی است');

        $result = $cat->delete($id);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);

    // ── assign ───────────────────────────────
    } elseif ($action === 'assign') {
        $product_id  = intval($input['product_id']  ?? 0);
        $category_id = intval($input['category_id'] ?? 0);

        if (!$product_id) throw new Exception('product_id الزامی است');

        $cat->assign_to_product($product_id, $category_id ?: null);
        echo json_encode([
            'success'  => true,
            'message'  => $category_id ? '✓ دسته اختصاص یافت' : '✓ دسته حذف شد',
            'category' => $category_id ? $cat->get($category_id) : null,
        ], JSON_UNESCAPED_UNICODE);

    } else {
        throw new Exception('action نامعتبر است');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'file'    => basename($e->getFile()),
        'line'    => $e->getLine(),
    ], JSON_UNESCAPED_UNICODE);
    error_log('panel-categories: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
}
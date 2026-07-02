<?php
/**
 * MN Order Panel - Invoice AJAX Handler
 *
 * POST ?action=create  → ثبت فاکتور
 * POST ?action=delete  → حذف فاکتور
 * GET  ?action=list    → لیست
 * GET  ?action=get&id= → یک فاکتور
 * GET  ?action=stats   → آمار
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/class-invoice.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = trim($_GET['action'] ?? '');

try {
    $inv            = new MN_Invoice();
    $panel_user_id  = isset($_SESSION['panel_user_id']) ? intval($_SESSION['panel_user_id']) : 1;

    // ── GET ──────────────────────────────────
    if ($method === 'GET') {

        if ($action === 'stats') {
            echo json_encode(['success' => true, 'stats' => $inv->get_stats()], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($action === 'get') {
            $id   = intval($_GET['id'] ?? 0);
            $item = $inv->get($id);
            echo json_encode(['success' => (bool)$item, 'invoice' => $item], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // list
        $page     = max(1, intval($_GET['page']     ?? 1));
        $per_page = max(1, min(100, intval($_GET['per_page'] ?? 20)));
        $filters  = [
            'product_id'     => intval($_GET['product_id'] ?? 0) ?: null,
            'search'         => trim($_GET['search']         ?? ''),
            'supplier'       => trim($_GET['supplier']       ?? ''),
            'payment_status' => trim($_GET['payment_status'] ?? ''),
            'date_from'      => trim($_GET['date_from']      ?? ''),
            'date_to'        => trim($_GET['date_to']        ?? ''),
        ];

        $total = $inv->count($filters);
        $list  = $inv->get_list($filters, $per_page, ($page - 1) * $per_page);

        echo json_encode([
            'success'    => true,
            'invoices'   => $list,
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $per_page,
                'total'        => $total,
                'total_pages'  => $total > 0 ? (int)ceil($total / $per_page) : 1,
                'from'         => $total > 0 ? ($page - 1) * $per_page + 1 : 0,
                'to'           => min($page * $per_page, $total),
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── POST ─────────────────────────────────
    $input  = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $action ?: ($input['action'] ?? '');

    if ($action === 'create') {
        $mode = $input['mode'] ?? 'existing';

        // validation مشترک
        if (empty($input['invoice_date']))  throw new Exception('تاریخ فاکتور الزامی است');
        if (empty($input['supplier_name'])) throw new Exception('نام فروشنده الزامی است');
        if (empty($input['quantity']) || intval($input['quantity']) <= 0)
            throw new Exception('تعداد باید بیشتر از صفر باشد');
        if (empty($input['unit_price']) || floatval($input['unit_price']) <= 0)
            throw new Exception('قیمت واحد الزامی است');

        if ($mode === 'new') {
            // validation محصول جدید
            if (empty($input['product_title'])) throw new Exception('نام محصول الزامی است');
            if (empty($input['regular_price']) || floatval($input['regular_price']) <= 0)
                throw new Exception('قیمت فروش محصول الزامی است');
        } else {
            // validation محصول موجود
            if (empty($input['product_id'])) throw new Exception('محصول الزامی است');
        }

        $result = $inv->create($input, $panel_user_id);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);

    } elseif ($action === 'delete') {
        $id     = intval($input['id'] ?? 0);
        if (!$id) throw new Exception('شناسه فاکتور الزامی است');
        $result = $inv->delete($id);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);

    } else {
        throw new Exception('action نامعتبر است');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    error_log('invoices.php: ' . $e->getMessage());
}
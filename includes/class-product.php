<?php
/**
 * MN Order Panel - Product Model
 * مدیریت محصولات
 */

require_once __DIR__ . '/../config/database.php';

class MN_Product {

    private $db;

    public function __construct() {
        $this->db = MN_Database::get_instance();
    }

    // ════════════════════════════════════════
    // ایجاد محصول
    // ════════════════════════════════════════
    public function create($product_data, $extras = [], $panel_user_id = 1) {
        try {
            $this->db->begin_transaction();

            $insert_data = [
                'wp_product_id'       => !empty($product_data['wp_product_id'])       ? intval($product_data['wp_product_id'])        : null,
                'title'               => $product_data['title'],
                'sku'                 => !empty($product_data['sku'])                 ? $product_data['sku']                         : null,
                'regular_price'       => floatval($product_data['regular_price']),
                'purchase_price'      => !empty($product_data['purchase_price'])      ? floatval($product_data['purchase_price'])     : null,
                // sale_price: 0 = حذف تخفیف → null ذخیره شود
                'sale_price'          => (isset($product_data['sale_price']) && $product_data['sale_price'] !== '' && floatval($product_data['sale_price']) > 0)
                                            ? floatval($product_data['sale_price']) : null,
                'discount_percent'    => (isset($product_data['discount_percent']) && $product_data['discount_percent'] !== '' && floatval($product_data['discount_percent']) > 0)
                                            ? floatval($product_data['discount_percent']) : null,
                'stock_quantity'      => isset($product_data['stock_quantity'])       ? intval($product_data['stock_quantity'])        : null,
                'real_stock_quantity' => isset($product_data['real_stock_quantity'])  ? intval($product_data['real_stock_quantity'])   : null,
                'stock_status'        => $product_data['stock_status']                ?? 'instock',
                'manage_stock'        => isset($product_data['manage_stock'])         ? intval($product_data['manage_stock'])          : 1,
                'weight'              => !empty($product_data['weight'])              ? floatval($product_data['weight'])              : null,
                'length'              => !empty($product_data['length'])              ? floatval($product_data['length'])              : null,
                'width'               => !empty($product_data['width'])               ? floatval($product_data['width'])               : null,
                'height'              => !empty($product_data['height'])              ? floatval($product_data['height'])              : null,
                'image_url'           => !empty($product_data['image_url'])           ? $product_data['image_url']                    : null,
                'status'              => $product_data['status']                      ?? 'active',
                'panel_category_id'   => !empty($product_data['panel_category_id'])  ? intval($product_data['panel_category_id'])    : null,
                'created_by'          => intval($panel_user_id),
            ];

            $product_id = $this->db->insert('mn_products', $insert_data);
            if (!$product_id) throw new Exception('خطا در ثبت محصول');

            foreach ($extras as $extra) {
                $this->save_single_extra($product_id, $extra);
            }

            if (!empty($product_data['stock_quantity'])) {
                $this->log_stock_change($product_id, 'virtual', 'set', 0, intval($product_data['stock_quantity']), 'manual', null, 'موجودی اولیه', $panel_user_id);
            }
            if (!empty($product_data['real_stock_quantity'])) {
                $this->log_stock_change($product_id, 'real', 'set', 0, intval($product_data['real_stock_quantity']), 'manual', null, 'موجودی اولیه', $panel_user_id);
            }

            $this->db->commit();
            return $product_id;

        } catch (Exception $e) {
            $this->db->rollback();
            error_log('Product creation failed: ' . $e->getMessage());
            return false;
        }
    }

    // ════════════════════════════════════════
    // بروزرسانی محصول
    // ════════════════════════════════════════
    public function update($product_id, $product_data, $extras = []) {
        try {
            $this->db->begin_transaction();

            $allowed_fields = [
                'title', 'sku', 'regular_price', 'purchase_price', 'sale_price',
                'discount_percent', 'stock_quantity', 'real_stock_quantity',
                'stock_status', 'manage_stock', 'weight', 'length', 'width',
                'height', 'image_url', 'status', 'panel_category_id',
            ];

            $update_data = [];
            foreach ($allowed_fields as $field) {
                if (!array_key_exists($field, $product_data)) continue;

                // sale_price و discount_percent: 0 یعنی حذف تخفیف
                if (in_array($field, ['sale_price', 'discount_percent'])) {
                    $v = $product_data[$field];
                    $update_data[$field] = ($v !== null && $v !== '' && floatval($v) > 0)
                        ? floatval($v) : null;
                } elseif ($field === 'panel_category_id') {
                    $update_data[$field] = !empty($product_data[$field]) ? intval($product_data[$field]) : null;
                } else {
                    $update_data[$field] = $product_data[$field];
                }
            }

            if (!empty($update_data)) {
                $result = $this->db->update('mn_products', $update_data, ['id' => $product_id]);
                if ($result === false) throw new Exception('خطا در بروزرسانی محصول');
            }

            if (!empty($extras)) {
                $this->db->delete('mn_product_extra', ['product_id' => $product_id]);
                foreach ($extras as $extra) {
                    $this->save_single_extra($product_id, $extra);
                }
            }

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollback();
            error_log('Product update failed: ' . $e->getMessage());
            return false;
        }
    }

    // ════════════════════════════════════════
    // دریافت محصول
    // ════════════════════════════════════════
    public function get($product_id) {
        return $this->db->get_row(
            "SELECT * FROM mn_products WHERE id = ?",
            [$product_id]
        );
    }

    public function get_extras($product_id) {
        return $this->db->get_results(
            "SELECT * FROM mn_product_extra WHERE product_id = ? ORDER BY type, id",
            [$product_id]
        );
    }

    public function get_invoices($product_id) {
        return $this->db->get_results(
            "SELECT * FROM mn_product_invoices WHERE product_id = ? ORDER BY invoice_date DESC",
            [$product_id]
        );
    }

    // ════════════════════════════════════════
    // save_single_extra
    // ════════════════════════════════════════
    private function save_single_extra($product_id, $extra) {
        $int_cols = ['attribute_id', 'term_id', 'category_id', 'wp_product_id'];
        $allowed  = [
            'type', 'wp_product_id',
            'category_id', 'category_name',
            'attribute_id', 'attribute_name', 'attribute_label', 'term_id',
            'value',
        ];

        $row = ['product_id' => $product_id];

        foreach ($allowed as $col) {
            if (!array_key_exists($col, $extra)) continue;
            $val = $extra[$col];
            if (in_array($col, $int_cols)) {
                $row[$col] = ($val !== null && $val !== '' && intval($val) > 0) ? intval($val) : null;
            } else {
                $row[$col] = ($val !== null && $val !== '') ? $val : null;
            }
        }

        return $this->db->insert('mn_product_extra', $row);
    }

    // ════════════════════════════════════════
    // تصاویر
    // ════════════════════════════════════════
    public function save_images($product_id, $images, $replace = true) {
        try {
            if ($replace) {
                $this->db->delete('mn_product_images', ['product_id' => $product_id]);
            }

            $has_primary = false;
            foreach ($images as $i => $img) {
                if (empty($img['url'])) continue;
                $is_primary = !$has_primary && (!isset($img['is_primary']) || $img['is_primary']) ? 1 : 0;
                if ($is_primary) $has_primary = true;
                $this->db->insert('mn_product_images', [
                    'product_id' => $product_id,
                    'image_url'  => trim($img['url']),
                    'alt_text'   => !empty($img['alt']) ? trim($img['alt']) : null,
                    'is_primary' => $is_primary,
                    'sort_order' => $i,
                ]);
            }

            $primary = $this->get_primary_image($product_id);
            if ($primary) {
                $this->db->update('mn_products', ['image_url' => $primary->image_url], ['id' => $product_id]);
            }

            return true;
        } catch (Exception $e) {
            error_log('save_images failed: ' . $e->getMessage());
            return false;
        }
    }

    public function get_images($product_id) {
        return $this->db->get_results(
            "SELECT * FROM mn_product_images WHERE product_id = ? ORDER BY sort_order, id",
            [$product_id]
        );
    }

    public function get_primary_image($product_id) {
        return $this->db->get_row(
            "SELECT * FROM mn_product_images WHERE product_id = ? AND is_primary = 1 LIMIT 1",
            [$product_id]
        ) ?: $this->db->get_row(
            "SELECT * FROM mn_product_images WHERE product_id = ? ORDER BY sort_order LIMIT 1",
            [$product_id]
        );
    }

    public function set_primary_image($product_id, $image_id) {
        $this->db->query(
            "UPDATE mn_product_images SET is_primary = 0 WHERE product_id = ?",
            [$product_id]
        );
        $result = $this->db->update('mn_product_images', ['is_primary' => 1], ['id' => $image_id, 'product_id' => $product_id]);
        $img = $this->db->get_row("SELECT image_url FROM mn_product_images WHERE id = ?", [$image_id]);
        if ($img) {
            $this->db->update('mn_products', ['image_url' => $img->image_url], ['id' => $product_id]);
        }
        return (bool) $result;
    }

    public function delete_image($image_id, $product_id) {
        $img = $this->db->get_row(
            "SELECT * FROM mn_product_images WHERE id = ? AND product_id = ?",
            [$image_id, $product_id]
        );
        if (!$img) return false;
        $this->db->delete('mn_product_images', ['id' => $image_id]);
        if ($img->is_primary) {
            $next = $this->db->get_row(
                "SELECT id FROM mn_product_images WHERE product_id = ? ORDER BY sort_order LIMIT 1",
                [$product_id]
            );
            if ($next) {
                $this->set_primary_image($product_id, $next->id);
            } else {
                $this->db->update('mn_products', ['image_url' => null], ['id' => $product_id]);
            }
        }
        return true;
    }

    // ════════════════════════════════════════
    // لیست و آمار
    // ════════════════════════════════════════
    public function get_list($filters = [], $limit = 20, $offset = 0) {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]  = "p.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['stock_status'])) {
            $where[]  = "p.stock_status = ?";
            $params[] = $filters['stock_status'];
        }
        if (!empty($filters['panel_category_id'])) {
            $where[]  = "p.panel_category_id = ?";
            $params[] = intval($filters['panel_category_id']);
        }
        if (!empty($filters['search'])) {
            $like     = '%' . $this->db->esc_like($filters['search']) . '%';
            $where[]  = "(p.title LIKE ? OR p.sku LIKE ?)";
            $params[] = $like;
            $params[] = $like;
        }

        $where_sql = implode(' AND ', $where);
        $params[]  = $limit;
        $params[]  = $offset;

        return $this->db->get_results("
            SELECT p.*
            FROM mn_products p
            WHERE {$where_sql}
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?
        ", $params);
    }

    public function count($filters = []) {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]  = "status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $like     = '%' . $this->db->esc_like($filters['search']) . '%';
            $where[]  = "(title LIKE ? OR sku LIKE ?)";
            $params[] = $like;
            $params[] = $like;
        }

        return (int) $this->db->get_var(
            "SELECT COUNT(*) FROM mn_products WHERE " . implode(' AND ', $where),
            $params
        );
    }

    public function update_status($product_id, $status) {
        $valid = ['active', 'inactive', 'draft'];
        if (!in_array($status, $valid)) return false;
        return $this->db->update('mn_products', ['status' => $status], ['id' => $product_id]);
    }

    public function delete($product_id) {
        $product = $this->get($product_id);
        if (!$product) return ['success' => false, 'message' => 'محصول یافت نشد'];
        if ($product->is_synced) return ['success' => false, 'message' => 'محصول sync شده قابل حذف نیست'];

        $result = $this->db->delete('mn_products', ['id' => $product_id]);
        return $result
            ? ['success' => true]
            : ['success' => false, 'message' => 'خطا در حذف محصول'];
    }

    public function get_statistics() {
        return $this->db->get_row("
            SELECT
                COUNT(*) as total_products,
                COUNT(CASE WHEN status = 'active'          THEN 1 END) as active_products,
                COUNT(CASE WHEN status = 'inactive'        THEN 1 END) as inactive_products,
                COUNT(CASE WHEN stock_status = 'instock'   THEN 1 END) as instock_products,
                COUNT(CASE WHEN stock_status = 'outofstock'THEN 1 END) as outofstock_products,
                COUNT(CASE WHEN is_synced = 1              THEN 1 END) as synced_products,
                COUNT(CASE WHEN DATE(created_at) = CURDATE()THEN 1 END) as today_added
            FROM mn_products
        ");
    }

    /**
     * ساخت SKU خودکار از P_30000 به بالا
     * فقط SKUهایی که با P_ شروع و عدد >= 30000 دارن بررسی می‌شن
     */
    public function generate_sku() {
        $max = $this->db->get_var("
            SELECT MAX(CAST(SUBSTRING(sku, 3) AS UNSIGNED))
            FROM mn_products
            WHERE sku REGEXP '^P_[0-9]+\$'
              AND CAST(SUBSTRING(sku, 3) AS UNSIGNED) >= 30000
        ");
        $next = $max ? intval($max) + 1 : 30000;
        return 'P_' . $next;
    }

    public function sku_exists($sku, $exclude_id = null) {
        if (empty($sku)) return false;
        if ($exclude_id) {
            $count = $this->db->get_var(
                "SELECT COUNT(*) FROM mn_products WHERE sku = ? AND id != ?",
                [$sku, $exclude_id]
            );
        } else {
            $count = $this->db->get_var(
                "SELECT COUNT(*) FROM mn_products WHERE sku = ?",
                [$sku]
            );
        }
        return intval($count) > 0;
    }

    // ════════════════════════════════════════
    // لاگ موجودی
    // ════════════════════════════════════════
    public function log_stock_change($product_id, $stock_type, $change_type, $before, $change, $reference_type = 'manual', $reference_id = null, $notes = '', $created_by = null) {
        return $this->db->insert('mn_stock_logs', [
            'product_id'      => $product_id,
            'stock_type'      => $stock_type,
            'change_type'     => $change_type,
            'quantity_before' => $before,
            'quantity_change' => $change,
            'quantity_after'  => $before + $change,
            'reference_type'  => $reference_type,
            'reference_id'    => $reference_id,
            'notes'           => $notes,
            'created_by'      => $created_by,
        ]);
    }
}

// توابع کمکی
function mn_get_product($product_id) {
    $p = new MN_Product();
    return $p->get($product_id);
}

function mn_create_product($data, $extras = [], $panel_user_id = 1) {
    $p = new MN_Product();
    return $p->create($data, $extras, $panel_user_id);
}
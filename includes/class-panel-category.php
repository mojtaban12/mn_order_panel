<?php
/**
 * MN Order Panel - Panel Category Model
 * دسته‌بندی داخلی پنل (بدون sync با وردپرس)
 */

if (class_exists('MN_Panel_Category')) return;

require_once __DIR__ . '/../config/database.php';

class MN_Panel_Category {

    private $db;

    public function __construct() {
        $this->db = MN_Database::get_instance();
    }

    // ════════════════════════════════════════
    // CRUD
    // ════════════════════════════════════════

    /**
     * ایجاد دسته جدید
     */
    public function create($data) {
        $slug = $this->make_unique_slug($data['name'], $data['slug'] ?? '');

        return $this->db->insert('mn_panel_categories', [
            'name'        => trim($data['name']),
            'slug'        => $slug,
            'parent_id'   => !empty($data['parent_id']) ? intval($data['parent_id']) : null,
            'description' => !empty($data['description']) ? trim($data['description']) : null,
            'color'       => !empty($data['color']) ? trim($data['color']) : null,
            'sort_order'  => isset($data['sort_order']) ? intval($data['sort_order']) : 0,
        ]);
    }

    /**
     * بروزرسانی دسته
     */
    public function update($id, $data) {
        $update = [];

        if (isset($data['name']) && $data['name'] !== '') {
            $update['name'] = trim($data['name']);
        }
        if (isset($data['slug'])) {
            $update['slug'] = $this->make_unique_slug($data['name'] ?? '', $data['slug'], $id);
        }
        if (array_key_exists('parent_id', $data)) {
            $update['parent_id'] = !empty($data['parent_id']) ? intval($data['parent_id']) : null;
        }
        if (array_key_exists('description', $data)) {
            $update['description'] = !empty($data['description']) ? trim($data['description']) : null;
        }
        if (array_key_exists('color', $data)) {
            $update['color'] = !empty($data['color']) ? trim($data['color']) : null;
        }
        if (isset($data['sort_order'])) {
            $update['sort_order'] = intval($data['sort_order']);
        }

        if (empty($update)) return true;
        return $this->db->update('mn_panel_categories', $update, ['id' => $id]);
    }

    /**
     * حذف دسته
     */
    public function delete($id) {
        // چک وجود محصول
        $product_count = (int) $this->db->get_var(
            "SELECT COUNT(*) FROM mn_products WHERE panel_category_id = ?", [$id]
        );
        if ($product_count > 0) {
            return ['success' => false, 'message' => $product_count . ' محصول در این دسته وجود دارد'];
        }

        // زیردسته‌ها → parent رو null کن
        $this->db->get_var(
            "UPDATE mn_panel_categories SET parent_id = NULL WHERE parent_id = ? LIMIT 1000",
            [$id]
        );

        $result = $this->db->delete('mn_panel_categories', ['id' => $id]);
        return $result
            ? ['success' => true]
            : ['success' => false, 'message' => 'خطا در حذف'];
    }

    /**
     * دریافت یک دسته
     */
    public function get($id) {
        return $this->db->get_row(
            "SELECT c.*, p.name AS parent_name,
                    (SELECT COUNT(*) FROM mn_products WHERE panel_category_id = c.id) AS product_count
             FROM mn_panel_categories c
             LEFT JOIN mn_panel_categories p ON c.parent_id = p.id
             WHERE c.id = ?",
            [$id]
        );
    }

    /**
     * لیست همه دسته‌ها (flat با parent_name)
     */
    public function get_list($search = '') {
        $where  = '1=1';
        $params = [];
        if ($search !== '') {
            $where    = 'c.name LIKE ?';
            $params[] = '%' . $search . '%';
        }

        return $this->db->get_results("
            SELECT
                c.id, c.name, c.slug, c.parent_id, c.color, c.sort_order, c.description,
                p.name  AS parent_name,
                (SELECT COUNT(*) FROM mn_products WHERE panel_category_id = c.id) AS product_count
            FROM mn_panel_categories c
            LEFT JOIN mn_panel_categories p ON c.parent_id = p.id
            WHERE {$where}
            ORDER BY c.parent_id IS NOT NULL, c.sort_order, c.name
        ", $params);
    }

    /**
     * درخت دسته‌ها (برای select/dropdown)
     * خروجی: آرایه با indent برای نمایش سلسله‌مراتب
     */
    public function get_tree() {
        $all = $this->get_list();

        // ساخت map
        $map = [];
        foreach ($all as $item) {
            $map[$item->id] = $item;
            $item->children = [];
        }

        $roots = [];
        foreach ($all as $item) {
            if ($item->parent_id && isset($map[$item->parent_id])) {
                $map[$item->parent_id]->children[] = $item;
            } else {
                $roots[] = $item;
            }
        }

        // flatten با indent
        $result = [];
        $this->flatten_tree($roots, $result, 0);
        return $result;
    }

    private function flatten_tree($nodes, &$result, $depth) {
        foreach ($nodes as $node) {
            $node->depth  = $depth;
            $node->indent = str_repeat('— ', $depth);
            $result[]     = $node;
            if (!empty($node->children)) {
                $this->flatten_tree($node->children, $result, $depth + 1);
            }
        }
    }

    // ════════════════════════════════════════
    // assign به محصول
    // ════════════════════════════════════════

    public function assign_to_product($product_id, $category_id) {
        return $this->db->update('mn_products',
            ['panel_category_id' => $category_id ?: null],
            ['id' => $product_id]
        );
    }

    // ════════════════════════════════════════
    // helpers
    // ════════════════════════════════════════

    private function make_unique_slug($name, $input_slug = '', $exclude_id = null) {
        $base = $input_slug ?: $this->slugify($name);
        $slug = $base;
        $i    = 1;

        while (true) {
            $where  = "slug = ?";
            $params = [$slug];
            if ($exclude_id) {
                $where   .= " AND id != ?";
                $params[] = $exclude_id;
            }
            $exists = $this->db->get_var(
                "SELECT COUNT(*) FROM mn_panel_categories WHERE {$where}", $params
            );
            if (!$exists) break;
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    private function slugify($text) {
        $map = [
            'آ'=>'a','ا'=>'a','ب'=>'b','پ'=>'p','ت'=>'t','ث'=>'s','ج'=>'j','چ'=>'ch',
            'ح'=>'h','خ'=>'kh','د'=>'d','ذ'=>'z','ر'=>'r','ز'=>'z','ژ'=>'zh','س'=>'s',
            'ش'=>'sh','ص'=>'s','ض'=>'z','ط'=>'t','ظ'=>'z','ع'=>'a','غ'=>'gh','ف'=>'f',
            'ق'=>'q','ک'=>'k','گ'=>'g','ل'=>'l','م'=>'m','ن'=>'n','و'=>'v','ه'=>'h',
            'ی'=>'y','ي'=>'y',' '=>'-',
        ];
        $result = '';
        $chars  = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($chars as $c) {
            $result .= $map[$c] ?? (preg_match('/[a-z0-9\-]/i', $c) ? strtolower($c) : '');
        }
        return trim(preg_replace('/-+/', '-', $result), '-') ?: 'cat-' . time();
    }
}
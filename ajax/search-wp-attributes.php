<?php
/**
 * MN Order Panel - Search WooCommerce Attributes & Terms
 *
 * mode=attribute&q=نوع  → سرچ با label/name + مقادیر هر attribute
 * mode=terms&taxonomy=pa_type&q=  → همه terms یک taxonomy
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/wp-bridge.php';

try {
    $q        = trim($_GET['q']        ?? '');
    $mode     = trim($_GET['mode']     ?? 'attribute');
    $taxonomy = trim($_GET['taxonomy'] ?? '');

    $wp   = MN_WP_Bridge::get_instance();
    $wpdb = $wp->wpdb;

    // ════════════════════════════════════════
    // حالت ۱: جستجوی attribute با label فارسی
    // ════════════════════════════════════════
    if ($mode === 'attribute') {

        $where  = '1=1';

        if ($q !== '') {
            $like   = '%' . $wp->esc_like($q) . '%';
            $where  = "(attr.attribute_label LIKE '{$like}' OR attr.attribute_name LIKE '{$like}')";
        }

        // دقیقاً همان کوئری که تأیید شد
        $rows = $wp->get_results("
            SELECT
                attr.attribute_id,
                attr.attribute_name   AS name,
                attr.attribute_label  AS label,
                CONCAT('pa_', attr.attribute_name) AS taxonomy,
                GROUP_CONCAT(terms.name ORDER BY terms.name SEPARATOR '||') AS term_names,
                GROUP_CONCAT(terms.term_id ORDER BY terms.name SEPARATOR '||') AS term_ids,
                GROUP_CONCAT(terms.slug ORDER BY terms.name SEPARATOR '||') AS term_slugs
            FROM {$wpdb->prefix}woocommerce_attribute_taxonomies AS attr
            LEFT JOIN {$wpdb->term_taxonomy} AS tax
                ON tax.taxonomy = CONCAT('pa_', attr.attribute_name)
            LEFT JOIN {$wpdb->terms} AS terms
                ON terms.term_id = tax.term_id
            WHERE {$where}
            GROUP BY attr.attribute_id
            ORDER BY attr.attribute_name
            LIMIT 30
        ");
        
        // var_dump("SELECT
        //         attr.attribute_id,
        //         attr.attribute_name   AS name,
        //         attr.attribute_label  AS label,
        //         CONCAT('pa_', attr.attribute_name) AS taxonomy,
        //         GROUP_CONCAT(terms.name ORDER BY terms.name SEPARATOR '||') AS term_names,
        //         GROUP_CONCAT(terms.term_id ORDER BY terms.name SEPARATOR '||') AS term_ids,
        //         GROUP_CONCAT(terms.slug ORDER BY terms.name SEPARATOR '||') AS term_slugs
        //     FROM {$wpdb->prefix}woocommerce_attribute_taxonomies AS attr
        //     LEFT JOIN {$wpdb->term_taxonomy} AS tax
        //         ON tax.taxonomy = CONCAT('pa_', attr.attribute_name)
        //     LEFT JOIN {$wpdb->terms} AS terms
        //         ON terms.term_id = tax.term_id
        //     WHERE {$where}
        //     GROUP BY attr.attribute_id
        //     ORDER BY attr.attribute_name
        //     LIMIT 30");
            
        $attributes = [];
        foreach ($rows as $r) {
            // تبدیل GROUP_CONCAT به آرایه terms
            $terms = [];
            if (!empty($r->term_names)) {
                $names = explode('||', $r->term_names);
                $ids   = explode('||', $r->term_ids);
                $slugs = explode('||', $r->term_slugs);
                foreach ($names as $i => $name) {
                    if ($name === '') continue;
                    $terms[] = [
                        'term_id' => intval($ids[$i] ?? 0),
                        'name'    => $name,
                        'slug'    => $slugs[$i] ?? '',
                    ];
                }
            }

            $attributes[] = [
                'id'       => intval($r->attribute_id),
                'name'     => $r->name,
                'label'    => $r->label,
                'taxonomy' => $r->taxonomy,
                'terms'    => $terms,
            ];
        }

        echo json_encode([
            'success'    => true,
            'mode'       => 'attribute',
            'attributes' => $attributes,
        ], JSON_UNESCAPED_UNICODE);

    // ════════════════════════════════════════
    // حالت ۲: همه terms یک taxonomy خاص
    // ════════════════════════════════════════
    } elseif ($mode === 'terms') {

        if (!$taxonomy) {
            echo json_encode(['success' => true, 'mode' => 'terms', 'terms' => []], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $params     = [$taxonomy];
        $name_where = '';
        if ($q !== '') {
            $name_where = ' AND t.name LIKE ?';
            $params[]   = '%' . $wp->esc_like($q) . '%';
        }

        $terms = $wp->get_results("
            SELECT t.term_id, t.name, t.slug
            FROM {$wpdb->terms} t
            INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            WHERE tt.taxonomy = ? {$name_where}
            ORDER BY t.name ASC
            LIMIT 100
        ", $params);

        $result = [];
        foreach ($terms as $t) {
            $result[] = [
                'term_id' => intval($t->term_id),
                'name'    => $t->name,
                'slug'    => $t->slug,
            ];
        }

        echo json_encode([
            'success'  => true,
            'mode'     => 'terms',
            'taxonomy' => $taxonomy,
            'terms'    => $result,
        ], JSON_UNESCAPED_UNICODE);

    } else {
        throw new Exception('mode نامعتبر است');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'file'    => basename($e->getFile()),
        'line'    => $e->getLine(),
    ], JSON_UNESCAPED_UNICODE);
    error_log('search-wp-attributes: ' . $e->getMessage());
}
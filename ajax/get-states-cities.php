<?php
/**
 * MN Order Panel - Get States and Cities
 * دریافت لیست استان‌ها و شهرها از WordPress
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/wp-bridge.php';

try {
    // دریافت نوع درخواست
    $type = $_GET['type'] ?? 'states';
    $parent_id = isset($_GET['parent_id']) ? intval($_GET['parent_id']) : 0;
    
    // اتصال به دیتابیس وردپرس
    $wp_bridge = MN_WP_Bridge::get_instance();
    $wpdb = $wp_bridge->wpdb;
    
    if ($type === 'states') {
        // دریافت استان‌ها - با collation برای حذف تکراری‌های ی/ي
        $query = "
            SELECT 
                MAX(t.term_id) as id,
                t.name,
                MAX(t.slug) as slug,
                COUNT(DISTINCT child.term_id) as cities_count
            FROM {$wpdb->terms} t
            INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            LEFT JOIN {$wpdb->term_taxonomy} child ON child.parent = tt.term_id AND child.taxonomy = 'state_city'
            WHERE tt.taxonomy = 'state_city'
            AND tt.parent = 0
            GROUP BY t.name COLLATE utf8mb4_persian_ci
            HAVING cities_count > 0
            ORDER BY t.name COLLATE utf8mb4_persian_ci ASC
        ";
        
        $results = $wp_bridge->get_results($query);
        
    } else {
        // دریافت شهرها (parent = استان)
        $query = "
            SELECT 
                t.term_id as id,
                t.name,
                t.slug,
                tt.parent as state_id
            FROM {$wpdb->terms} t
            INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            WHERE tt.taxonomy = 'state_city'
            AND tt.parent = ?
            ORDER BY t.name ASC
        ";
        
        $results = $wp_bridge->get_results($query, [$parent_id]);
    }
    
    $data = [];
    foreach ($results as $row) {
        $data[] = [
            'id' => intval($row->id),
            'name' => $row->name,
            'slug' => $row->slug,
            'state_id' => isset($row->state_id) ? intval($row->state_id) : 0,
            'cities_count' => isset($row->cities_count) ? intval($row->cities_count) : 0
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $data,
        'count' => count($data)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطا در دریافت اطلاعات',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    
    error_log('States/Cities error: ' . $e->getMessage());
}
<?php
/**
 * MN Order Panel - WordPress Bridge
 * اتصال مستقیم به دیتابیس وردپرس (فقط برای خواندن محصولات)
 */

class MN_WP_Bridge {
    
    private static $instance = null;
    public $wpdb;  // باید public باشه برای دسترسی
    private $connection;
    
    // تنظیمات دیتابیس وردپرس (باید با wp-config.php هماهنگ باشد)
    private $wp_config = [
        'host'    => 'localhost',
        'dbname'  => 'dbname',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8',
        'prefix'  => 'wp_'
    ];

    /**
     * Singleton Pattern
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * دریافت کانکشن (برای استفاده مستقیم)
     */
    public static function get_connection() {
        return self::get_instance()->wpdb;
    }
    
    /**
     * Constructor - اتصال به دیتابیس وردپرس
     */
    private function __construct() {
        $this->init_connection();
        $this->init_wpdb_object();
    }
    
    /**
     * ایجاد اتصال PDO به دیتابیس وردپرس
     */
    private function init_connection() {
        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $this->wp_config['host'],
                $this->wp_config['dbname'],
                $this->wp_config['charset']
            );
            
            $this->connection = new PDO(
                $dsn,
                $this->wp_config['user'],
                $this->wp_config['pass'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
            
        } catch (PDOException $e) {
            die('WordPress DB Connection Failed: ' . $e->getMessage());
        }
    }
    
    /**
     * ایجاد شیء شبیه $wpdb برای سهولت استفاده
     */
    private function init_wpdb_object() {
        $prefix = $this->wp_config['prefix'];
        
        // ایجاد شیء ساده با property های مورد نیاز
        $this->wpdb = new stdClass();
        $this->wpdb->prefix = $prefix;
        $this->wpdb->posts = $prefix . 'posts';
        $this->wpdb->postmeta = $prefix . 'postmeta';
        $this->wpdb->users = $prefix . 'users';
        $this->wpdb->usermeta = $prefix . 'usermeta';
        $this->wpdb->terms = $prefix . 'terms';
        $this->wpdb->term_taxonomy = $prefix . 'term_taxonomy';
        $this->wpdb->term_relationships = $prefix . 'term_relationships';
        
        // متدهای کمکی
        $this->wpdb->get_results = [$this, 'get_results'];
        $this->wpdb->get_row = [$this, 'get_row'];
        $this->wpdb->get_var = [$this, 'get_var'];
        $this->wpdb->prepare = [$this, 'prepare'];
        $this->wpdb->esc_like = [$this, 'esc_like'];
    }
    
    /**
     * Prepare Query (شبیه wpdb->prepare)
     */
    public function prepare($query, ...$args) {
        if (empty($args)) {
            return $query;
        }
        
        // جایگزینی %s، %d، %f
        $query = preg_replace_callback(
            '/%[sdf]/',
            function($match) use (&$args) {
                $arg = array_shift($args);
                
                switch ($match[0]) {
                    case '%d': // integer
                        return intval($arg);
                    case '%f': // float
                        return floatval($arg);
                    case '%s': // string
                    default:
                        return $this->connection->quote($arg);
                }
            },
            $query
        );
        
        return $query;
    }
    
    /**
     * دریافت چند رکورد
     */
    public function get_results($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('WP Bridge Error (get_results): ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت یک رکورد
     */
    public function get_row($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log('WP Bridge Error (get_row): ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * دریافت یک مقدار
     */
    public function get_var($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('WP Bridge Error (get_var): ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * اجرای کوئری (برای CREATE, UPDATE, DELETE)
     */
    public function query($query, $params = []) {
        try {
            if (empty($params)) {
                return $this->connection->exec($query);
            }
            
            $stmt = $this->connection->prepare($query);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log('WP Bridge Error (query): ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Escape for LIKE
     */
    public function esc_like($text) {
        return addcslashes($text, '_%\\');
    }
    
    /**
     * جستجوی محصولات ووکامرس
     * 
     * @param string $search_term عبارت جستجو
     * @param int $limit تعداد نتایج
     * @param int $offset شروع از
     * @return array لیست محصولات
     */
    public function search_products($search_term, $limit = 30, $offset = 0) {
        $term_like = '%' . $this->esc_like($search_term) . '%';
        
        $query = $this->prepare("
            SELECT 
                p.ID as product_id,
                p.post_title as name,
                p.post_name as slug,
                pm_price.meta_value as price,
                pm_regular_price.meta_value as regular_price,
                pm_stock.meta_value as stock_quantity,
                pm_sku.meta_value as sku,
                pm_stock_status.meta_value as stock_status,
                pm_image.meta_value as thumbnail_id
            FROM {$this->wpdb->posts} p
            LEFT JOIN {$this->wpdb->postmeta} pm_price 
                ON p.ID = pm_price.post_id AND pm_price.meta_key = '_price'
            LEFT JOIN {$this->wpdb->postmeta} pm_regular_price 
                ON p.ID = pm_regular_price.post_id AND pm_regular_price.meta_key = '_regular_price'
            LEFT JOIN {$this->wpdb->postmeta} pm_stock 
                ON p.ID = pm_stock.post_id AND pm_stock.meta_key = '_stock'
            LEFT JOIN {$this->wpdb->postmeta} pm_sku 
                ON p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'
            LEFT JOIN {$this->wpdb->postmeta} pm_stock_status 
                ON p.ID = pm_stock_status.post_id AND pm_stock_status.meta_key = '_stock_status'
            LEFT JOIN {$this->wpdb->postmeta} pm_image 
                ON p.ID = pm_image.post_id AND pm_image.meta_key = '_thumbnail_id'
            WHERE 
                p.post_type = 'product'
                AND p.post_status = 'publish'
                AND (
                    p.post_title LIKE %s 
                    OR pm_sku.meta_value LIKE %s
                    OR p.ID = %d
                )
            GROUP BY p.ID
            ORDER BY 
                CASE 
                    WHEN p.post_title LIKE %s THEN 1
                    WHEN pm_sku.meta_value LIKE %s THEN 2
                    ELSE 3
                END,
                p.post_title ASC
            LIMIT %d OFFSET %d
        ", 
            $term_like,              // عنوان
            $term_like,              // SKU
            intval($search_term),    // ID محصول
            $term_like,              // اولویت عنوان
            $term_like,              // اولویت SKU
            $limit, 
            $offset
        );
        
        return $this->get_results($query);
    }
    
    /**
     * دریافت اطلاعات کامل یک محصول
     * 
     * @param int $product_id
     * @return object|null
     */
    public function get_product($product_id) {
        $query = $this->prepare("
            SELECT 
                p.ID as product_id,
                p.post_title as name,
                p.post_content as description,
                p.post_excerpt as short_description,
                pm_price.meta_value as price,
                pm_regular_price.meta_value as regular_price,
                pm_sale_price.meta_value as sale_price,
                pm_stock.meta_value as stock_quantity,
                pm_sku.meta_value as sku,
                pm_stock_status.meta_value as stock_status,
                pm_weight.meta_value as weight,
                pm_length.meta_value as length,
                pm_width.meta_value as width,
                pm_height.meta_value as height,
                pm_image.meta_value as thumbnail_id
            FROM {$this->wpdb->posts} p
            LEFT JOIN {$this->wpdb->postmeta} pm_price 
                ON p.ID = pm_price.post_id AND pm_price.meta_key = '_price'
            LEFT JOIN {$this->wpdb->postmeta} pm_regular_price 
                ON p.ID = pm_regular_price.post_id AND pm_regular_price.meta_key = '_regular_price'
            LEFT JOIN {$this->wpdb->postmeta} pm_sale_price 
                ON p.ID = pm_sale_price.post_id AND pm_sale_price.meta_key = '_sale_price'
            LEFT JOIN {$this->wpdb->postmeta} pm_stock 
                ON p.ID = pm_stock.post_id AND pm_stock.meta_key = '_stock'
            LEFT JOIN {$this->wpdb->postmeta} pm_sku 
                ON p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'
            LEFT JOIN {$this->wpdb->postmeta} pm_stock_status 
                ON p.ID = pm_stock_status.post_id AND pm_stock_status.meta_key = '_stock_status'
            LEFT JOIN {$this->wpdb->postmeta} pm_weight 
                ON p.ID = pm_weight.post_id AND pm_weight.meta_key = '_weight'
            LEFT JOIN {$this->wpdb->postmeta} pm_length 
                ON p.ID = pm_length.post_id AND pm_length.meta_key = '_length'
            LEFT JOIN {$this->wpdb->postmeta} pm_width 
                ON p.ID = pm_width.post_id AND pm_width.meta_key = '_width'
            LEFT JOIN {$this->wpdb->postmeta} pm_height 
                ON p.ID = pm_height.post_id AND pm_height.meta_key = '_height'
            LEFT JOIN {$this->wpdb->postmeta} pm_image 
                ON p.ID = pm_image.post_id AND pm_image.meta_key = '_thumbnail_id'
            WHERE p.ID = %d AND p.post_type = 'product'
        ", $product_id);
        
        return $this->get_row($query);
    }
    
    /**
     * دریافت URL تصویر محصول
     * 
     * @param int $attachment_id
     * @return string|null
     */
    public function get_attachment_url($attachment_id) {
        if (!$attachment_id) {
            return null;
        }
        
        $query = $this->prepare("
            SELECT meta_value 
            FROM {$this->wpdb->postmeta} 
            WHERE post_id = %d AND meta_key = '_wp_attached_file'
        ", $attachment_id);
        
        $file_path = $this->get_var($query);
        
        if ($file_path) {
            // فرض می‌کنیم uploads در مسیر استاندارد است
            // می‌توانید این مسیر را در settings قرار دهید
            return '/wp-content/uploads/' . $file_path;
        }
        
        return null;
    }
}
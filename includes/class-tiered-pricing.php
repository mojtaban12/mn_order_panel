<?php
/**
 * MN Order Panel - Tiered Pricing Manager
 * مدیریت قیمت پلکانی
 */

class MN_Tiered_Pricing {
    
    private $wp_bridge;
    private $cache_key = 'mn_tiered_pricing_rules';
    private $cache_duration = 86400; // 24 ساعت
    
    public function __construct() {
        require_once __DIR__ . '/../config/wp-bridge.php';
        $this->wp_bridge = MN_WP_Bridge::get_instance();
    }
    
    /**
     * دریافت قوانین قیمت پلکانی (با کش)
     */
    public function get_rules() {
        // چک کردن کش
        $cached = $this->get_cache();
        if ($cached !== false) {
            return $cached;
        }
        
        // دریافت از دیتابیس
        $rules = $this->fetch_rules_from_db();
        
        // ذخیره در کش
        $this->set_cache($rules);
        
        return $rules;
    }
    
    /**
     * دریافت قوانین از دیتابیس وردپرس
     */
    private function fetch_rules_from_db() {
        $wpdb = $this->wp_bridge->wpdb;
        
        // دریافت تمام قوانین
        $query = "
            SELECT p.ID as rule_id, p.post_title
            FROM {$wpdb->posts} p
            WHERE p.post_type = 'tpt-global-rule'
            AND p.post_status = 'publish'
        ";
        
        $rules_posts = $this->wp_bridge->get_results($query);
        
        $rules = [];
        
        foreach ($rules_posts as $rule_post) {
            $rule_id = $rule_post->rule_id;
            
            // دریافت متا
            $meta_query = "
                SELECT meta_key, meta_value
                FROM {$wpdb->postmeta}
                WHERE post_id = {$rule_id}
                AND meta_key IN (
                    '_tpt_percentage_rules',
                    '_tpt_included_categories',
                    '_tpt_included_user_roles',
                    '_tpt_included_products',
                    '_tpt_excluded_products'
                )
            ";
            
            $meta_results = $this->wp_bridge->get_results($meta_query);
            
            $rule_data = [
                'id' => $rule_id,
                'title' => $rule_post->post_title,
                'percentage_rules' => [],
                'included_categories' => [],
                'included_user_roles' => [],
                'included_products' => [],
                'excluded_products' => []
            ];
            
            foreach ($meta_results as $meta) {
                $value = maybe_unserialize($meta->meta_value);
                
                switch ($meta->meta_key) {
                    case '_tpt_percentage_rules':
                        // تبدیل به آرایه مرتب شده
                        if (is_array($value)) {
                            ksort($value); // مرتب کردن بر اساس تعداد
                            $rule_data['percentage_rules'] = $value;
                        }
                        break;
                        
                    case '_tpt_included_categories':
                        $rule_data['included_categories'] = is_array($value) ? array_map('intval', $value) : [];
                        break;
                        
                    case '_tpt_included_user_roles':
                        $rule_data['included_user_roles'] = is_array($value) ? $value : [];
                        break;
                        
                    case '_tpt_included_products':
                        $rule_data['included_products'] = is_array($value) ? array_map('intval', $value) : [];
                        break;
                        
                    case '_tpt_excluded_products':
                        $rule_data['excluded_products'] = is_array($value) ? array_map('intval', $value) : [];
                        break;
                }
            }
            
            $rules[] = $rule_data;
        }
        
        return $rules;
    }
    
    /**
     * محاسبه قیمت با تخفیف پلکانی
     * 
     * @param int $product_id
     * @param int $quantity
     * @param float $base_price
     * @param array|null $user_roles
     * @param bool $skip_role_check آیا قوانین نیازمند رول رو نادیده بگیریم؟
     * @return array ['price' => float, 'discount_percent' => int, 'rule_applied' => string]
     */
    public function calculate_price($product_id, $quantity, $base_price, $user_roles = null, $skip_role_check = false) {
        $rules = $this->get_rules();
        
        // 🔥 Debug logging
        error_log('=== TIERED PRICING DEBUG ===');
        error_log('Product ID: ' . $product_id);
        error_log('Quantity: ' . $quantity);
        error_log('Base Price: ' . $base_price);
        error_log('User Roles: ' . json_encode($user_roles));
        error_log('Skip Role Check: ' . ($skip_role_check ? 'YES' : 'NO'));
        error_log('Total Rules: ' . count($rules));
        
        $best_discount = 0;
        $applied_rule = null;
        
        foreach ($rules as $rule) {
            error_log('--- Checking Rule: ' . $rule['title'] . ' (ID: ' . $rule['id'] . ')');
            error_log('    Required Roles: ' . json_encode($rule['included_user_roles']));
            error_log('    Included Products: ' . json_encode($rule['included_products']));
            error_log('    Excluded Products: ' . json_encode($rule['excluded_products']));
            error_log('    Percentage Rules: ' . json_encode($rule['percentage_rules']));
            
            // چک کردن اینکه محصول در لیست excluded نباشد
            if (in_array($product_id, $rule['excluded_products'])) {
                error_log('    ❌ Product is excluded');
                continue;
            }
            
            // چک کردن نقش کاربر
            $requires_role = !empty($rule['included_user_roles']);
            error_log('    Requires Role: ' . ($requires_role ? 'YES' : 'NO'));
            
            if ($requires_role) {
                if ($skip_role_check) {
                    error_log('    ❌ Skipping - role required but skip_role_check=true');
                    continue;
                }
                
                if ($user_roles === null) {
                    error_log('    ❌ Skipping - user_roles is null');
                    continue;
                }
                
                $has_role = false;
                foreach ($user_roles as $role) {
                    if (in_array($role, $rule['included_user_roles'])) {
                        $has_role = true;
                        error_log('    ✅ User has required role: ' . $role);
                        break;
                    }
                }
                
                if (!$has_role) {
                    error_log('    ❌ User does not have required role');
                    continue;
                }
            }
            
            // چک کردن محصول خاص
            if (!empty($rule['included_products'])) {
                if (!in_array($product_id, $rule['included_products'])) {
                    error_log('    ❌ Product not in included list');
                    continue;
                }
            }
            
            // چک کردن دسته (اگر محصول خاص تعریف نشده)
            if (empty($rule['included_products']) && !empty($rule['included_categories'])) {
                $product_in_category = $this->is_product_in_categories($product_id, $rule['included_categories']);
                if (!$product_in_category) {
                    error_log('    ❌ Product not in required categories');
                    continue;
                }
            }
            
            // محاسبه درصد تخفیف بر اساس تعداد
            $discount = $this->get_discount_for_quantity($quantity, $rule['percentage_rules']);
            error_log('    💰 Calculated discount: ' . $discount . '%');
            
            if ($discount > $best_discount) {
                $best_discount = $discount;
                $applied_rule = $rule['title'];
                error_log('    ✅ NEW BEST DISCOUNT: ' . $discount . '% from rule: ' . $rule['title']);
            }
        }
        
        // محاسبه قیمت نهایی
        $final_price = $base_price;
        if ($best_discount > 0) {
            $final_price = $base_price * (1 - $best_discount / 100);
        }
        
        error_log('=== FINAL RESULT ===');
        error_log('Best Discount: ' . $best_discount . '%');
        error_log('Applied Rule: ' . ($applied_rule ?: 'None'));
        error_log('Final Price: ' . $final_price);
        error_log('========================');
        
        return [
            'original_price' => $base_price,
            'final_price' => $final_price,
            'discount_percent' => $best_discount,
            'discount_amount' => $base_price - $final_price,
            'rule_applied' => $applied_rule
        ];
    }
    
    /**
     * دریافت درصد تخفیف بر اساس تعداد
     */
    private function get_discount_for_quantity($quantity, $percentage_rules) {
        $discount = 0;
        
        foreach ($percentage_rules as $min_qty => $percent) {
            if ($quantity >= $min_qty) {
                $discount = intval($percent);
            } else {
                break;
            }
        }
        
        return $discount;
    }
    
    /**
     * چک کردن اینکه محصول در دسته‌های مشخص شده هست یا نه
     */
    private function is_product_in_categories($product_id, $category_ids) {
        $wpdb = $this->wp_bridge->wpdb;
        
        $placeholders = implode(',', array_fill(0, count($category_ids), '%d'));
        
        $query = "
            SELECT COUNT(*) 
            FROM {$wpdb->term_relationships} tr
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            WHERE tr.object_id = %d
            AND tt.taxonomy = 'product_cat'
            AND tt.term_id IN ({$placeholders})
        ";
        
        $params = array_merge([$product_id], $category_ids);
        $count = $this->wp_bridge->get_var($query, $params);
        
        return $count > 0;
    }
    
    /**
     * دریافت نقش‌های کاربر از وردپرس
     */
    public function get_user_roles($wp_user_id) {
        if (!$wp_user_id) {
            error_log('⚠️ get_user_roles: No user ID provided');
            return [];
        }
        
        // روش اول: استفاده از WordPress API (بهتر و دقیق‌تر)
        if (function_exists('get_user_by')) {
            $user = get_user_by('ID', $wp_user_id);
            if ($user && !empty($user->roles)) {
                $roles = array_values($user->roles);
                error_log('🎯 User Roles (from WP API) for ID ' . $wp_user_id . ': ' . json_encode($roles));
                return $roles;
            }
        }
        
        // روش دوم: مستقیم از دیتابیس (fallback)
        $wpdb = $this->wp_bridge->wpdb;
        
        $query = "
            SELECT meta_value
            FROM {$wpdb->usermeta}
            WHERE user_id = %d
            AND meta_key = '{$wpdb->prefix}capabilities'
        ";
        
        $capabilities = $this->wp_bridge->get_var($query, [$wp_user_id]);
        
        if ($capabilities) {
            $caps = maybe_unserialize($capabilities);
            if (is_array($caps)) {
                $roles = array_keys($caps);
                error_log('🎯 User Roles (from DB) for ID ' . $wp_user_id . ': ' . json_encode($roles));
                return $roles;
            }
        }
        
        error_log('⚠️ No roles found for user: ' . $wp_user_id);
        return [];
    }
    
    /**
     * کش: دریافت
     */
    private function get_cache() {
        $cache_file = sys_get_temp_dir() . '/' . $this->cache_key . '.json';
        
        if (!file_exists($cache_file)) {
            return false;
        }
        
        // چک کردن expire
        if ((time() - filemtime($cache_file)) > $this->cache_duration) {
            return false;
        }
        
        $content = file_get_contents($cache_file);
        return json_decode($content, true);
    }
    
    /**
     * کش: ذخیره
     */
    private function set_cache($data) {
        $cache_file = sys_get_temp_dir() . '/' . $this->cache_key . '.json';
        file_put_contents($cache_file, json_encode($data));
    }
    
    /**
     * کش: پاک کردن
     */
    public function clear_cache() {
        $cache_file = sys_get_temp_dir() . '/' . $this->cache_key . '.json';
        if (file_exists($cache_file)) {
            unlink($cache_file);
        }
    }
}
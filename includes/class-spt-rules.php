<?php
/**
 * Rules Engine for Smart Product Tabs
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SPT_Rules {
    
    /**
     * Cache for condition results during single page load
     */
    private static $condition_cache = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        // Hook into WordPress init for any initialization
        add_action('init', array($this, 'init'));
    }
    
    /**
     * Initialize
     */
    public function init() {
        // Add any initialization logic here
    }
    
    /**
     * Check if rule conditions are met for a product
     */
    public function check_conditions($product_id, $rule) {
        // Create cache key for this check
        $cache_key = 'product_' . $product_id . '_rule_' . $rule->id;
        
        // Check cache first
        if (isset(self::$condition_cache[$cache_key])) {
            return self::$condition_cache[$cache_key];
        }
        
        $conditions = json_decode($rule->conditions, true);
        
        // If no conditions or empty conditions, show for all products
        if (empty($conditions) || $conditions['type'] === 'all') {
            self::$condition_cache[$cache_key] = true;
            return true;
        }
        
        $result = $this->evaluate_condition($product_id, $conditions);
        
        // Cache the result
        self::$condition_cache[$cache_key] = $result;
        
        return $result;
    }
    
    /**
     * Evaluate a single condition
     */
    private function evaluate_condition($product_id, $condition) {
        $type = $condition['type'] ?? 'all';
        
        switch ($type) {
            case 'category':
                return $this->check_category_condition($product_id, $condition);
                
            case 'price_range':
                return $this->check_price_condition($product_id, $condition);
                
            case 'stock_status':
                return $this->check_stock_condition($product_id, $condition);
                
            case 'custom_field':
                return $this->check_custom_field_condition($product_id, $condition);
                
            case 'product_type':
                return $this->check_product_type_condition($product_id, $condition);
                
            case 'tags':
                return $this->check_tags_condition($product_id, $condition);
                
            case 'featured':
                return $this->check_featured_condition($product_id, $condition);
                
            case 'sale':
                return $this->check_sale_condition($product_id, $condition);
                
            default:
                return true; // Default to showing tab
        }
    }
    
    /**
     * Check category condition
     */
    private function check_category_condition($product_id, $condition) {
        $required_categories = $condition['value'] ?? array();
        $operator = $condition['operator'] ?? 'in';
        
        if (empty($required_categories)) {
            return true;
        }
        
        // Ensure it's an array
        if (!is_array($required_categories)) {
            $required_categories = array($required_categories);
        }
        
        $product_categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
        
        if (is_wp_error($product_categories)) {
            return false;
        }
        
        $product_category_ids = array_map('intval', $product_categories);
        
        switch ($operator) {
            case 'in':
                // Product must be in at least one of the specified categories
                return !empty(array_intersect($required_categories, $product_category_ids));
                
            case 'not_in':
                // Product must not be in any of the specified categories
                return empty(array_intersect($required_categories, $product_category_ids));
                
            case 'all':
                // Product must be in all specified categories
                return empty(array_diff($required_categories, $product_category_ids));
                
            default:
                return !empty(array_intersect($required_categories, $product_category_ids));
        }
    }
    
    /**
     * Check price condition
     */
    private function check_price_condition($product_id, $condition) {
        $min_price = isset($condition['min']) ? floatval($condition['min']) : 0;
        $max_price = isset($condition['max']) ? floatval($condition['max']) : 999999;
        $operator = $condition['operator'] ?? 'between';
        
        $product = wc_get_product($product_id);
        if (!$product) {
            return false;
        }
        
        $product_price = floatval($product->get_price());
        
        switch ($operator) {
            case 'between':
                return $product_price >= $min_price && $product_price <= $max_price;
                
            case 'greater_than':
                return $product_price > $min_price;
                
            case 'less_than':
                return $product_price < $max_price;
                
            case 'equals':
                return $product_price == $min_price;
                
            case 'not_equals':
                return $product_price != $min_price;
                
            default:
                return $product_price >= $min_price && $product_price <= $max_price;
        }
    }
    
    /**
     * Check stock status condition
     */
    private function check_stock_condition($product_id, $condition) {
        $required_status = $condition['value'] ?? 'instock';
        $operator = $condition['operator'] ?? 'equals';
        
        $product = wc_get_product($product_id);
        if (!$product) {
            return false;
        }
        
        $product_status = $product->get_stock_status();
        
        switch ($operator) {
            case 'equals':
                return $product_status === $required_status;
                
            case 'not_equals':
                return $product_status !== $required_status;
                
            default:
                return $product_status === $required_status;
        }
    }
    
    /**
     * Check custom field condition
     */
    private function check_custom_field_condition($product_id, $condition) {
        $field_key = $condition['key'] ?? '';
        $field_value = $condition['value'] ?? '';
        $operator = $condition['operator'] ?? 'equals';
        
        if (empty($field_key)) {
            return true;
        }
        
        $meta_value = get_post_meta($product_id, $field_key, true);
        
        // Handle array values for 'in' operations
        if (is_array($field_value)) {
            switch ($operator) {
                case 'equals':
                case 'in':
                    return in_array($meta_value, $field_value);
                case 'not_equals':
                case 'not_in':
                    return !in_array($meta_value, $field_value);
                case 'contains':
                    foreach ($field_value as $val) {
                        if (strpos($meta_value, $val) !== false) {
                            return true;
                        }
                    }
                    return false;
                case 'not_contains':
                    foreach ($field_value as $val) {
                        if (strpos($meta_value, $val) !== false) {
                            return false;
                        }
                    }
                    return true;
                default:
                    return in_array($meta_value, $field_value);
            }
        }
        
        // Handle single values
        switch ($operator) {
            case 'equals':
                return $meta_value == $field_value;
                
            case 'not_equals':
                return $meta_value != $field_value;
                
            case 'contains':
                return strpos($meta_value, $field_value) !== false;
                
            case 'not_contains':
                return strpos($meta_value, $field_value) === false;
                
            case 'empty':
                return empty($meta_value);
                
            case 'not_empty':
                return !empty($meta_value);
                
            case 'greater_than':
                return floatval($meta_value) > floatval($field_value);
                
            case 'less_than':
                return floatval($meta_value) < floatval($field_value);
                
            default:
                return $meta_value == $field_value;
        }
    }
    
    /**
     * Check product type condition
     */
    private function check_product_type_condition($product_id, $condition) {
        $required_types = $condition['value'] ?? array();
        $operator = $condition['operator'] ?? 'in';
        
        if (empty($required_types)) {
            return true;
        }
        
        // Ensure it's an array
        if (!is_array($required_types)) {
            $required_types = array($required_types);
        }
        
        $product = wc_get_product($product_id);
        if (!$product) {
            return false;
        }
        
        $product_type = $product->get_type();
        
        switch ($operator) {
            case 'in':
                return in_array($product_type, $required_types);
                
            case 'not_in':
                return !in_array($product_type, $required_types);
                
            default:
                return in_array($product_type, $required_types);
        }
    }
    
    /**
     * Check product tags condition
     */
    private function check_tags_condition($product_id, $condition) {
        $required_tags = $condition['value'] ?? array();
        $operator = $condition['operator'] ?? 'in';
        
        if (empty($required_tags)) {
            return true;
        }
        
        // Ensure it's an array
        if (!is_array($required_tags)) {
            $required_tags = array($required_tags);
        }
        
        $product_tags = wp_get_post_terms($product_id, 'product_tag', array('fields' => 'ids'));
        
        if (is_wp_error($product_tags)) {
            return false;
        }
        
        $product_tag_ids = array_map('intval', $product_tags);
        
        switch ($operator) {
            case 'in':
                return !empty(array_intersect($required_tags, $product_tag_ids));
                
            case 'not_in':
                return empty(array_intersect($required_tags, $product_tag_ids));
                
            case 'all':
                return empty(array_diff($required_tags, $product_tag_ids));
                
            default:
                return !empty(array_intersect($required_tags, $product_tag_ids));
        }
    }
    
    /**
     * Check featured product condition
     */
    private function check_featured_condition($product_id, $condition) {
        $required_featured = $condition['value'] ?? true;
        
        $product = wc_get_product($product_id);
        if (!$product) {
            return false;
        }
        
        $is_featured = $product->is_featured();
        
        return $required_featured ? $is_featured : !$is_featured;
    }
    
    /**
     * Check sale condition
     */
    private function check_sale_condition($product_id, $condition) {
        $required_on_sale = $condition['value'] ?? true;
        
        $product = wc_get_product($product_id);
        if (!$product) {
            return false;
        }
        
        $is_on_sale = $product->is_on_sale();
        
        return $required_on_sale ? $is_on_sale : !$is_on_sale;
    }
    
    /**
     * Check user role condition
     */
    public function check_user_role($rule) {
        if ($rule->user_role_condition === 'all') {
            return true;
        }
        
        if ($rule->user_role_condition === 'logged_in') {
            return is_user_logged_in();
        }
        
        if ($rule->user_role_condition === 'specific_role') {
            if (!is_user_logged_in()) {
                return false;
            }
            
            $allowed_roles = json_decode($rule->user_roles, true);
            if (empty($allowed_roles)) {
                return false;
            }
            
            $user = wp_get_current_user();
            return !empty(array_intersect($allowed_roles, $user->roles));
        }
        
        return true;
    }
    
    /**
     * Evaluate complex conditions (Advanced feature)
     */
    public function evaluate_complex_condition($product_id, $condition_group) {
        if (empty($condition_group) || !is_array($condition_group)) {
            return true;
        }
        
        $result = null;
        $current_logic = 'AND';
        
        foreach ($condition_group as $item) {
            // Check if this is a logic operator
            if (isset($item['logic'])) {
                $current_logic = strtoupper($item['logic']);
                continue;
            }
            
            // Evaluate the condition
            $condition_result = $this->evaluate_condition($product_id, $item);
            
            // Apply logic
            if ($result === null) {
                // First condition
                $result = $condition_result;
            } else {
                if ($current_logic === 'AND') {
                    $result = $result && $condition_result;
                } elseif ($current_logic === 'OR') {
                    $result = $result || $condition_result;
                }
            }
            
            // Reset logic for next iteration
            $current_logic = 'AND';
        }
        
        return $result !== null ? $result : true;
    }
    
    /**
     * Get all available condition types (without attributes)
     */
    public function get_condition_types() {
        return array(
            'all' => esc_html__('All Products', 'smart-product-tabs-for-woocommerce'),
            'category' => esc_html__('Product Category', 'smart-product-tabs-for-woocommerce'),
            'price_range' => esc_html__('Price Range', 'smart-product-tabs-for-woocommerce'),
            'stock_status' => esc_html__('Stock Status', 'smart-product-tabs-for-woocommerce'),
            'custom_field' => esc_html__('Custom Field', 'smart-product-tabs-for-woocommerce'),
            'product_type' => esc_html__('Product Type', 'smart-product-tabs-for-woocommerce'),
            'tags' => esc_html__('Product Tags', 'smart-product-tabs-for-woocommerce'),
            'featured' => esc_html__('Featured Product', 'smart-product-tabs-for-woocommerce'),
            'sale' => esc_html__('On Sale', 'smart-product-tabs-for-woocommerce')
        );
    }
    
    /**
     * Get available operators for a condition type (without attributes)
     */
    public function get_condition_operators($condition_type) {
        $operators = array();
        
        switch ($condition_type) {
            case 'category':
            case 'tags':
                $operators = array(
                    'in' => esc_html__('In any of', 'smart-product-tabs-for-woocommerce'),
                    'not_in' => esc_html__('Not in any of', 'smart-product-tabs-for-woocommerce'),
                    'all' => esc_html__('In all of', 'smart-product-tabs-for-woocommerce')
                );
                break;
                
            case 'custom_field':
                $operators = array(
                    'equals' => esc_html__('Equals', 'smart-product-tabs-for-woocommerce'),
                    'not_equals' => esc_html__('Not equals', 'smart-product-tabs-for-woocommerce'),
                    'contains' => esc_html__('Contains', 'smart-product-tabs-for-woocommerce'),
                    'not_contains' => esc_html__('Does not contain', 'smart-product-tabs-for-woocommerce'),
                    'empty' => esc_html__('Is empty', 'smart-product-tabs-for-woocommerce'),
                    'not_empty' => esc_html__('Is not empty', 'smart-product-tabs-for-woocommerce'),
                    'greater_than' => esc_html__('Greater than', 'smart-product-tabs-for-woocommerce'),
                    'less_than' => esc_html__('Less than', 'smart-product-tabs-for-woocommerce')
                );
                break;
                
            case 'price_range':
                $operators = array(
                    'between' => esc_html__('Between', 'smart-product-tabs-for-woocommerce'),
                    'greater_than' => esc_html__('Greater than', 'smart-product-tabs-for-woocommerce'),
                    'less_than' => esc_html__('Less than', 'smart-product-tabs-for-woocommerce'),
                    'equals' => esc_html__('Equals', 'smart-product-tabs-for-woocommerce'),
                    'not_equals' => esc_html__('Not equals', 'smart-product-tabs-for-woocommerce')
                );
                break;
                
            case 'stock_status':
            case 'product_type':
                $operators = array(
                    'equals' => esc_html__('Is', 'smart-product-tabs-for-woocommerce'),
                    'not_equals' => esc_html__('Is not', 'smart-product-tabs-for-woocommerce')
                );
                break;
                
            default:
                $operators = array(
                    'equals' => esc_html__('Equals', 'smart-product-tabs-for-woocommerce'),
                    'not_equals' => esc_html__('Not equals', 'smart-product-tabs-for-woocommerce')
                );
        }
        
        return apply_filters('spt_condition_operators', $operators, $condition_type);
    }
    
    /**
     * Validate rule conditions
     */
    public function validate_conditions($conditions) {
        if (empty($conditions)) {
            return true;
        }
        
        $conditions_array = json_decode($conditions, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        
        // Basic validation
        if (!isset($conditions_array['type'])) {
            return false;
        }
        
        $valid_types = array_keys($this->get_condition_types());
        if (!in_array($conditions_array['type'], $valid_types)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get products that match specific conditions (for testing/debugging)
     */
    public function get_matching_products($conditions, $limit = 10) {
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'fields' => 'ids'
        );
        
        $products = get_posts($args);
        $matching_products = array();
        
        foreach ($products as $product_id) {
            $rule = (object) array('conditions' => json_encode($conditions));
            if ($this->check_conditions($product_id, $rule)) {
                $matching_products[] = $product_id;
            }
        }
        
        return $matching_products;
    }
    
    /**
     * Clear condition cache
     */
    public static function clear_cache() {
        self::$condition_cache = array();
    }
    
    /**
     * Get condition cache statistics
     */
    public static function get_cache_stats() {
        return array(
            'total_cached' => count(self::$condition_cache),
            'cache_keys' => array_keys(self::$condition_cache)
        );
    }
}
<?php
/**
 * Frontend Display for Smart Product Tabs (Updated)
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SPT_Frontend {
    
    /**
     * Rules engine instance
     */
    private $rules_engine;
    
    /**
     * Analytics instance
     */
    private $analytics;
    
    /**
     * Cache for rules during single page load
     */
    private static $rules_cache = null;
    
    /**
     * Cache for tab settings during single page load
     */
    private static $tab_settings_cache = null;
    
    /**
     * Constructor
     */
    public function __construct() {
        add_filter('woocommerce_product_tabs', array($this, 'add_custom_tabs'), 99);
        add_filter('woocommerce_product_tabs', array($this, 'filter_default_tabs'), 5);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_spt_track_tab_view', array($this, 'ajax_track_tab_view'));
        add_action('wp_ajax_nopriv_spt_track_tab_view', array($this, 'ajax_track_tab_view'));
        add_action('wp_ajax_spt_get_tab_content', array($this, 'ajax_get_tab_content'));
        add_action('wp_ajax_nopriv_spt_get_tab_content', array($this, 'ajax_get_tab_content'));
        add_filter('body_class', array($this, 'add_body_classes'));
        
        // Initialize dependencies when needed
        add_action('init', array($this, 'init_dependencies'));
    }
    
    /**
     * Initialize dependencies
     */
    public function init_dependencies() {
        if (class_exists('SPT_Rules')) {
            $this->rules_engine = new SPT_Rules();
        }
        if (class_exists('SPT_Analytics')) {
            $this->analytics = new SPT_Analytics();
        }
    }
    
    /**
     * Add custom tabs to WooCommerce product tabs
     */
    public function add_custom_tabs($tabs) {
        global $product;
        
        if (!$product) {
            return $tabs;
        }
        
        // Get active rules
        $rules = $this->get_active_rules();
        
        if (empty($rules)) {
            return $tabs;
        }
        
        foreach ($rules as $rule) {
            // Check conditions using rules engine
            if (!$this->check_rule_conditions($product->get_id(), $rule)) {
                continue;
            }
            
            // Check user role permissions
            if (!$this->check_user_role_permissions($rule)) {
                continue;
            }
            
            // Check mobile display
            if ($this->is_mobile() && $rule->mobile_hidden) {
                continue;
            }
            
            // Add tab
            $tab_key = 'spt_' . $rule->id;
            $tabs[$tab_key] = array(
                'title' => $this->process_merge_tags($rule->tab_title, $product),
                'priority' => intval($rule->priority),
                'callback' => array($this, 'render_tab_content'),
                'rule_id' => $rule->id,
                'rule' => $rule
            );
        }
        
        // Apply tab ordering from settings
        $tabs = $this->apply_tab_ordering($tabs);
        
        return $tabs;
    }
    
    /**
     * Filter default WooCommerce tabs based on settings
     */
    public function filter_default_tabs($tabs) {
        $tab_settings = $this->get_tab_settings();
        
        foreach ($tab_settings as $setting) {
            if (isset($tabs[$setting->tab_key]) && $setting->tab_type === 'default') {
                if (!$setting->is_enabled) {
                    // Remove disabled tabs
                    unset($tabs[$setting->tab_key]);
                } else {
                    // Update tab properties
                    if (!empty($setting->custom_title)) {
                        $tabs[$setting->tab_key]['title'] = $setting->custom_title;
                    }
                    
                    $tabs[$setting->tab_key]['priority'] = $setting->sort_order;
                    
                    // Hide on mobile if needed
                    if ($this->is_mobile() && $setting->mobile_hidden) {
                        unset($tabs[$setting->tab_key]);
                    }
                }
            }
        }
        
        return $tabs;
    }
    
    /**
     * Render tab content
     */
    public function render_tab_content($tab_key, $tab) {
        global $product;
        
        if (!isset($tab['rule'])) {
            return;
        }
        
        $rule = $tab['rule'];
        
        // Process content with merge tags
        $content = $this->process_merge_tags($rule->tab_content, $product);
        
        // Process shortcodes
        $content = do_shortcode($content);
        
        // Apply content filters
        $content = apply_filters('spt_tab_content', $content, $rule, $product);
        
        // Wrap content in container
        echo '<div class="spt-tab-content" data-tab-key="' . esc_attr($tab_key) . '" data-rule-id="' . esc_attr($rule->id) . '">';
        echo wp_kses_post($content);
        echo '</div>';
        
        // Track tab view
        $this->track_tab_view($tab_key, $product->get_id());
    }
    
    /**
     * Check rule conditions using the rules engine
     */
    private function check_rule_conditions($product_id, $rule) {
        if (!$this->rules_engine) {
            // Fallback if rules engine not available
            return true;
        }
        
        return $this->rules_engine->check_conditions($product_id, $rule);
    }
    
    /**
     * Check user role permissions (matches admin logic)
     */
    private function check_user_role_permissions($rule) {
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
            if (empty($allowed_roles) || !is_array($allowed_roles)) {
                return false;
            }
            
            $user = wp_get_current_user();
            return !empty(array_intersect($allowed_roles, $user->roles));
        }
        
        return true;
    }
    
    /**
     * Get active rules from database with caching
     */
    private function get_active_rules() {
        // Use static cache for single page load
        if (self::$rules_cache !== null) {
            return self::$rules_cache;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'spt_rules';
        $cache_key = 'spt_active_rules_v2';
        
        // Try to get from WordPress cache first
        $rules = wp_cache_get($cache_key);
        
        if (false === $rules) {
            $rules = $wpdb->get_results(
                "SELECT * FROM $table WHERE is_active = 1 ORDER BY priority ASC, created_at DESC"
            );
            
            // Cache for 5 minutes
            wp_cache_set($cache_key, $rules, '', 300);
        }
        
        // Store in static cache
        self::$rules_cache = $rules;
        
        return $rules;
    }
    
    /**
     * Get tab settings with caching
     */
    private function get_tab_settings() {
        // Use static cache for single page load
        if (self::$tab_settings_cache !== null) {
            return self::$tab_settings_cache;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'spt_tab_settings';
        $cache_key = 'spt_tab_settings_v2';
        
        // Try to get from WordPress cache first
        $settings = wp_cache_get($cache_key);
        
        if (false === $settings) {
            $settings = $wpdb->get_results("SELECT * FROM $table ORDER BY sort_order ASC");
            
            // Cache for 10 minutes
            wp_cache_set($cache_key, $settings, '', 600);
        }
        
        // Store in static cache
        self::$tab_settings_cache = $settings;
        
        return $settings;
    }
    
    /**
     * Apply tab ordering from settings
     */
    private function apply_tab_ordering($tabs) {
        $tab_settings = $this->get_tab_settings();
        
        $ordered_tabs = array();
        $remaining_tabs = $tabs;
        
        // First, add tabs according to saved order
        foreach ($tab_settings as $setting) {
            if (isset($remaining_tabs[$setting->tab_key])) {
                // Check if tab is enabled
                if ($setting->is_enabled) {
                    // Check mobile display
                    if (!($this->is_mobile() && $setting->mobile_hidden)) {
                        $ordered_tabs[$setting->tab_key] = $remaining_tabs[$setting->tab_key];
                        
                        // Override title if custom title is set
                        if (!empty($setting->custom_title) && $setting->tab_type === 'default') {
                            $ordered_tabs[$setting->tab_key]['title'] = $setting->custom_title;
                        }
                        
                        // Set priority from settings
                        $ordered_tabs[$setting->tab_key]['priority'] = $setting->sort_order;
                    }
                }
                unset($remaining_tabs[$setting->tab_key]);
            }
        }
        
        // Add any remaining tabs (new custom tabs not in settings)
        foreach ($remaining_tabs as $key => $tab) {
            $ordered_tabs[$key] = $tab;
        }
        
        return $ordered_tabs;
    }
    
    /**
     * Process merge tags in content (enhanced version)
     */
    private function process_merge_tags($content, $product) {
        if (!$product) {
            return $content;
        }
        
        $merge_tags = array(
            '{product_name}' => $product->get_name(),
            '{product_price}' => wc_price($product->get_price()),
            '{product_sku}' => $product->get_sku(),
            '{product_category}' => $this->get_product_categories($product),
            '{product_short_description}' => $product->get_short_description(),
            '{product_weight}' => $this->get_formatted_weight($product),
            '{product_dimensions}' => $this->get_product_dimensions($product),
            '{product_stock_status}' => $this->get_formatted_stock_status($product),
            '{product_stock_quantity}' => $this->get_formatted_stock_quantity($product),
            '{product_type}' => $this->get_formatted_product_type($product),
            '{product_tags}' => $this->get_product_tags($product),
            '{product_rating}' => $this->get_product_rating($product),
            '{product_review_count}' => $this->get_product_review_count($product),
            '{product_sale_price}' => $this->get_formatted_sale_price($product),
            '{product_regular_price}' => $this->get_formatted_regular_price($product),
            '{product_featured}' => $this->get_featured_status($product),
            '{product_on_sale}' => $this->get_sale_status($product),
        );
        
        // Add custom field merge tags
        $content = $this->process_custom_field_tags($content, $product);
        
        // Add attribute merge tags (enhanced to work with all attributes)
        $content = $this->process_attribute_tags($content, $product);
        
        // Replace standard merge tags
        $content = str_replace(array_keys($merge_tags), array_values($merge_tags), $content);
        
        // Apply filters for custom merge tags
        $content = apply_filters('spt_process_merge_tags', $content, $product);
        
        return $content;
    }
    
    /**
     * Process custom field merge tags
     */
    private function process_custom_field_tags($content, $product) {
        // Pattern: {custom_field_key_name}
        $pattern = '/\{custom_field_([^}]+)\}/';
        
        return preg_replace_callback($pattern, function($matches) use ($product) {
            $field_key = $matches[1];
            $field_value = get_post_meta($product->get_id(), $field_key, true);
            return !empty($field_value) ? $field_value : '';
        }, $content);
    }
    
    /**
     * Process attribute merge tags (enhanced)
     */
    private function process_attribute_tags($content, $product) {
        // Pattern: {attribute_name}
        $pattern = '/\{attribute_([^}]+)\}/';
        
        return preg_replace_callback($pattern, function($matches) use ($product) {
            $attribute_name = $matches[1];
            
            // Try to get attribute value
            $attribute_value = $product->get_attribute($attribute_name);
            
            // If not found, try with pa_ prefix for product attributes
            if (empty($attribute_value)) {
                $attribute_value = $product->get_attribute('pa_' . $attribute_name);
            }
            
            return !empty($attribute_value) ? $attribute_value : '';
        }, $content);
    }
    
    /**
     * Get product categories as string
     */
    private function get_product_categories($product) {
        $categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'));
        return !empty($categories) && !is_wp_error($categories) ? implode(', ', $categories) : '';
    }
    
    /**
     * Get product tags as string
     */
    private function get_product_tags($product) {
        $tags = wp_get_post_terms($product->get_id(), 'product_tag', array('fields' => 'names'));
        return !empty($tags) && !is_wp_error($tags) ? implode(', ', $tags) : '';
    }
    
    /**
     * Get product dimensions formatted
     */
    private function get_product_dimensions($product) {
        $dimensions = array();
        
        if ($product->get_length()) {
            $dimensions[] = $product->get_length() . get_option('woocommerce_dimension_unit');
        }
        if ($product->get_width()) {
            $dimensions[] = $product->get_width() . get_option('woocommerce_dimension_unit');
        }
        if ($product->get_height()) {
            $dimensions[] = $product->get_height() . get_option('woocommerce_dimension_unit');
        }
        
        return !empty($dimensions) ? implode(' × ', $dimensions) : '';
    }
    
    /**
     * Get formatted weight
     */
    private function get_formatted_weight($product) {
        $weight = $product->get_weight();
        if ($weight) {
            return $weight . ' ' . get_option('woocommerce_weight_unit');
        }
        return '';
    }
    
    /**
     * Get formatted stock status
     */
    private function get_formatted_stock_status($product) {
        $status = $product->get_stock_status();
        $status_labels = array(
            'instock' => __('In Stock', 'smart-product-tabs'),
            'outofstock' => __('Out of Stock', 'smart-product-tabs'),
            'onbackorder' => __('On Backorder', 'smart-product-tabs')
        );
        
        return isset($status_labels[$status]) ? $status_labels[$status] : $status;
    }
    
    /**
     * Get formatted stock quantity
     */
    private function get_formatted_stock_quantity($product) {
        $quantity = $product->get_stock_quantity();
        return $quantity !== null ? $quantity : '';
    }
    
    /**
     * Get formatted product type
     */
    private function get_formatted_product_type($product) {
        $type = $product->get_type();
        $product_types = wc_get_product_types();
        return isset($product_types[$type]) ? $product_types[$type] : $type;
    }
    
    /**
     * Get product rating
     */
    private function get_product_rating($product) {
        $rating = $product->get_average_rating();
        return $rating ? number_format($rating, 1) : '';
    }
    
    /**
     * Get product review count
     */
    private function get_product_review_count($product) {
        return $product->get_review_count();
    }
    
    /**
     * Get formatted sale price
     */
    private function get_formatted_sale_price($product) {
        $sale_price = $product->get_sale_price();
        return $sale_price ? wc_price($sale_price) : '';
    }
    
    /**
     * Get formatted regular price
     */
    private function get_formatted_regular_price($product) {
        $regular_price = $product->get_regular_price();
        return $regular_price ? wc_price($regular_price) : '';
    }
    
    /**
     * Get featured status
     */
    private function get_featured_status($product) {
        return $product->is_featured() ? __('Yes', 'smart-product-tabs') : __('No', 'smart-product-tabs');
    }
    
    /**
     * Get sale status
     */
    private function get_sale_status($product) {
        return $product->is_on_sale() ? __('Yes', 'smart-product-tabs') : __('No', 'smart-product-tabs');
    }
    
    /**
     * Check if current request is mobile
     */
    private function is_mobile() {
        return wp_is_mobile();
    }
    
    /**
     * Track tab view using analytics
     */
    private function track_tab_view($tab_key, $product_id) {
        // Only track if analytics is enabled
        if (!get_option('spt_enable_analytics', 1)) {
            return;
        }
        
        if ($this->analytics) {
            $this->analytics->track_tab_view($tab_key, $product_id);
        }
    }
    
    /**
     * AJAX: Track tab view
     */
    public function ajax_track_tab_view() {
        check_ajax_referer('spt_frontend_nonce', 'nonce');
        
        $tab_key = sanitize_text_field($_POST['tab_key'] ?? '');
        $product_id = intval($_POST['product_id'] ?? 0);
        
        if (empty($tab_key) || empty($product_id)) {
            wp_send_json_error('Invalid parameters');
            return;
        }
        
        $this->track_tab_view($tab_key, $product_id);
        wp_send_json_success('View tracked');
    }
    
    /**
     * AJAX: Get tab content for lazy loading
     */
    public function ajax_get_tab_content() {
        check_ajax_referer('spt_frontend_nonce', 'nonce');
        
        $rule_id = intval($_POST['rule_id'] ?? 0);
        $product_id = intval($_POST['product_id'] ?? 0);
        
        if (empty($rule_id) || empty($product_id)) {
            wp_send_json_error('Invalid parameters');
            return;
        }
        
        // Get rule
        $rule = $this->get_rule_by_id($rule_id);
        if (!$rule) {
            wp_send_json_error('Rule not found');
            return;
        }
        
        // Get product
        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error('Product not found');
            return;
        }
        
        // Check conditions again for security
        if (!$this->check_rule_conditions($product_id, $rule)) {
            wp_send_json_error('Conditions not met');
            return;
        }
        
        // Check user role
        if (!$this->check_user_role_permissions($rule)) {
            wp_send_json_error('Access denied');
            return;
        }
        
        $content = $this->process_merge_tags($rule->tab_content, $product);
        $content = do_shortcode($content);
        
        wp_send_json_success(array(
            'content' => $content,
            'title' => $this->process_merge_tags($rule->tab_title, $product)
        ));
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets() {
        if (!is_product()) {
            return;
        }
        
        // Enqueue styles
        wp_enqueue_style(
            'spt-frontend',
            SPT_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            SPT_VERSION
        );
        
        wp_enqueue_style(
            'spt-mobile',
            SPT_PLUGIN_URL . 'assets/css/mobile.css',
            array('spt-frontend'),
            SPT_VERSION
        );
        
        // Enqueue scripts
        wp_enqueue_script(
            'spt-frontend',
            SPT_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            SPT_VERSION,
            true
        );
        
        // Localize script for AJAX
        wp_localize_script('spt-frontend', 'spt_frontend', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'product_id' => get_the_ID(),
            'track_views' => get_option('spt_enable_analytics', 1),
            'nonce' => wp_create_nonce('spt_frontend_nonce')
        ));
    }
    
    /**
     * Add custom body classes for styling
     */
    public function add_body_classes($classes) {
        if (is_product()) {
            $classes[] = 'spt-enabled';
            
            if ($this->is_mobile()) {
                $classes[] = 'spt-mobile';
            }
            
            // Add class for number of custom tabs
            $rules = $this->get_active_rules();
            if (!empty($rules)) {
                $classes[] = 'spt-has-custom-tabs';
                $classes[] = 'spt-custom-tabs-' . count($rules);
            }
        }
        
        return $classes;
    }
    
    /**
     * Get rule by ID
     */
    private function get_rule_by_id($rule_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'spt_rules';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $rule_id));
    }
    
    /**
     * Check if tab should be displayed based on conditions
     */
    public function should_display_tab($rule, $product_id) {
        // Check if rule is active
        if (!$rule->is_active) {
            return false;
        }
        
        // Check conditions
        if (!$this->check_rule_conditions($product_id, $rule)) {
            return false;
        }
        
        // Check user role
        if (!$this->check_user_role_permissions($rule)) {
            return false;
        }
        
        // Check mobile display
        if ($this->is_mobile() && $rule->mobile_hidden) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get all tabs for a product (including default and custom)
     */
    public function get_product_tabs($product_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return array();
        }
        
        // Temporarily set global product for WooCommerce filters
        $GLOBALS['product'] = $product;
        
        // Get WooCommerce tabs
        $wc_tabs = apply_filters('woocommerce_product_tabs', array());
        
        // Get custom tabs
        $custom_tabs = array();
        $rules = $this->get_active_rules();
        
        foreach ($rules as $rule) {
            if ($this->should_display_tab($rule, $product_id)) {
                $tab_key = 'spt_' . $rule->id;
                $custom_tabs[$tab_key] = array(
                    'title' => $this->process_merge_tags($rule->tab_title, $product),
                    'priority' => $rule->priority,
                    'rule_id' => $rule->id,
                    'type' => 'custom'
                );
            }
        }
        
        return array_merge($wc_tabs, $custom_tabs);
    }
    
    /**
     * Clear all caches
     */
    public function clear_caches() {
        wp_cache_delete('spt_active_rules_v2');
        wp_cache_delete('spt_tab_settings_v2');
        self::$rules_cache = null;
        self::$tab_settings_cache = null;
        
        // Clear rules engine cache if available
        if ($this->rules_engine && method_exists($this->rules_engine, 'clear_cache')) {
            $this->rules_engine->clear_cache();
        }
    }
    
    /**
     * Get tab statistics for current product
     */
    public function get_product_tab_stats($product_id) {
        if (!$this->analytics) {
            return array();
        }
        
        return $this->analytics->get_product_tab_stats($product_id);
    }
    
    /**
     * Get available merge tags for documentation
     */
    public function get_available_merge_tags() {
        return array(
            'product_info' => array(
                '{product_name}' => __('Product name', 'smart-product-tabs'),
                '{product_sku}' => __('Product SKU', 'smart-product-tabs'),
                '{product_type}' => __('Product type (simple, variable, etc.)', 'smart-product-tabs'),
                '{product_category}' => __('Product categories', 'smart-product-tabs'),
                '{product_tags}' => __('Product tags', 'smart-product-tabs'),
                '{product_short_description}' => __('Short description', 'smart-product-tabs'),
                '{product_featured}' => __('Featured status (Yes/No)', 'smart-product-tabs'),
                '{product_on_sale}' => __('Sale status (Yes/No)', 'smart-product-tabs'),
            ),
            'pricing' => array(
                '{product_price}' => __('Current price', 'smart-product-tabs'),
                '{product_regular_price}' => __('Regular price', 'smart-product-tabs'),
                '{product_sale_price}' => __('Sale price', 'smart-product-tabs'),
            ),
            'inventory' => array(
                '{product_stock_status}' => __('Stock status', 'smart-product-tabs'),
                '{product_stock_quantity}' => __('Stock quantity', 'smart-product-tabs'),
            ),
            'physical' => array(
                '{product_weight}' => __('Product weight with unit', 'smart-product-tabs'),
                '{product_dimensions}' => __('Product dimensions', 'smart-product-tabs'),
            ),
            'reviews' => array(
                '{product_rating}' => __('Average rating', 'smart-product-tabs'),
                '{product_review_count}' => __('Number of reviews', 'smart-product-tabs'),
            ),
            'custom' => array(
                '{custom_field_[key]}' => __('Custom field value (replace [key] with field name)', 'smart-product-tabs'),
                '{attribute_[name]}' => __('Product attribute value (replace [name] with attribute name)', 'smart-product-tabs'),
            )
        );
    }
    
    /**
     * Debug method to validate rule conditions
     */
    public function debug_rule_conditions($product_id, $rule_id = null) {
        if (!WP_DEBUG || !current_user_can('manage_options')) {
            return;
        }
        
        $rules = $rule_id ? array($this->get_rule_by_id($rule_id)) : $this->get_active_rules();
        $debug_info = array();
        
        foreach ($rules as $rule) {
            if (!$rule) continue;
            
            $debug_info[$rule->id] = array(
                'rule_name' => $rule->rule_name,
                'is_active' => $rule->is_active,
                'conditions' => json_decode($rule->conditions, true),
                'user_role_condition' => $rule->user_role_condition,
                'mobile_hidden' => $rule->mobile_hidden,
                'checks' => array(
                    'conditions_met' => $this->check_rule_conditions($product_id, $rule),
                    'user_role_ok' => $this->check_user_role_permissions($rule),
                    'mobile_display' => !($this->is_mobile() && $rule->mobile_hidden),
                    'should_display' => $this->should_display_tab($rule, $product_id)
                )
            );
        }
        
        error_log('SPT Debug - Product ID: ' . $product_id . ' - Rules: ' . print_r($debug_info, true));
        
        return $debug_info;
    }
    
    /**
     * Handle cache invalidation when rules are updated
     */
    public function invalidate_caches() {
        $this->clear_caches();
        
        // Also clear any frontend-specific transients
        delete_transient('spt_product_tabs_' . get_the_ID());
        
        do_action('spt_frontend_caches_cleared');
    }
    
    /**
     * Get cached product tabs with expiration
     */
    public function get_cached_product_tabs($product_id, $force_refresh = false) {
        if ($force_refresh) {
            delete_transient('spt_product_tabs_' . $product_id);
        }
        
        $cached_tabs = get_transient('spt_product_tabs_' . $product_id);
        
        if (false === $cached_tabs) {
            $cached_tabs = $this->get_product_tabs($product_id);
            // Cache for 1 hour
            set_transient('spt_product_tabs_' . $product_id, $cached_tabs, HOUR_IN_SECONDS);
        }
        
        return $cached_tabs;
    }
    
    /**
     * Process dynamic content that might need real-time processing
     */
    private function process_dynamic_content($content, $product) {
        // Handle dynamic pricing for variable products
        if ($product->is_type('variable')) {
            $content = $this->process_variable_product_tags($content, $product);
        }
        
        // Handle user-specific content
        if (is_user_logged_in()) {
            $content = $this->process_user_specific_tags($content, $product);
        }
        
        // Handle time-sensitive content
        $content = $this->process_time_sensitive_tags($content, $product);
        
        return $content;
    }
    
    /**
     * Process variable product specific merge tags
     */
    private function process_variable_product_tags($content, $product) {
        if (!$product->is_type('variable')) {
            return $content;
        }
        
        $variable_tags = array(
            '{product_min_price}' => wc_price($product->get_variation_price('min')),
            '{product_max_price}' => wc_price($product->get_variation_price('max')),
            '{product_variation_count}' => count($product->get_children()),
        );
        
        return str_replace(array_keys($variable_tags), array_values($variable_tags), $content);
    }
    
    /**
     * Process user-specific merge tags
     */
    private function process_user_specific_tags($content, $product) {
        $user = wp_get_current_user();
        
        $user_tags = array(
            '{user_name}' => $user->display_name,
            '{user_email}' => $user->user_email,
            '{user_role}' => implode(', ', $user->roles),
        );
        
        return str_replace(array_keys($user_tags), array_values($user_tags), $content);
    }
    
    /**
     * Process time-sensitive merge tags
     */
    private function process_time_sensitive_tags($content, $product) {
        $time_tags = array(
            '{current_date}' => date_i18n(get_option('date_format')),
            '{current_time}' => date_i18n(get_option('time_format')),
            '{current_year}' => date('Y'),
        );
        
        // Add sale countdown if product is on sale
        if ($product->is_on_sale()) {
            $sale_end = $product->get_date_on_sale_to();
            if ($sale_end) {
                $time_tags['{sale_ends}'] = $sale_end->date_i18n(get_option('date_format'));
                $time_tags['{days_until_sale_ends}'] = max(0, $sale_end->diff(new DateTime())->days);
            }
        }
        
        return str_replace(array_keys($time_tags), array_values($time_tags), $content);
    }
    
    /**
     * Enhanced render method with better error handling
     */
    public function enhanced_render_tab_content($tab_key, $tab) {
        global $product;
        
        try {
            if (!isset($tab['rule'])) {
                throw new Exception('Rule not found for tab');
            }
            
            $rule = $tab['rule'];
            
            // Double-check conditions for security
            if (!$this->should_display_tab($rule, $product->get_id())) {
                return;
            }
            
            // Process content with merge tags
            $content = $this->process_merge_tags($rule->tab_content, $product);
            
            // Process dynamic content
            $content = $this->process_dynamic_content($content, $product);
            
            // Process shortcodes
            $content = do_shortcode($content);
            
            // Apply content filters
            $content = apply_filters('spt_tab_content', $content, $rule, $product);
            
            // Wrap content in container with enhanced data attributes
            echo '<div class="spt-tab-content" 
                       data-tab-key="' . esc_attr($tab_key) . '" 
                       data-rule-id="' . esc_attr($rule->id) . '"
                       data-product-id="' . esc_attr($product->get_id()) . '"
                       data-tab-type="custom">';
            
            if (empty(trim($content))) {
                echo '<p class="spt-empty-content">' . 
                     apply_filters('spt_empty_content_message', 
                                   __('Content will be available soon.', 'smart-product-tabs'), 
                                   $rule, $product) . 
                     '</p>';
            } else {
                echo wp_kses_post($content);
            }
            
            echo '</div>';
            
            // Track tab view
            $this->track_tab_view($tab_key, $product->get_id());
            
            // Trigger action for extensions
            do_action('spt_tab_content_rendered', $tab_key, $rule, $product);
            
        } catch (Exception $e) {
            if (WP_DEBUG) {
                error_log('SPT Frontend Error: ' . $e->getMessage());
            }
            
            // Show fallback content in debug mode
            if (WP_DEBUG && current_user_can('manage_options')) {
                echo '<div class="spt-error-debug">Error: ' . esc_html($e->getMessage()) . '</div>';
            }
        }
    }
    
    /**
     * Get rule evaluation summary for debugging
     */
    public function get_rule_evaluation_summary($product_id) {
        if (!current_user_can('manage_options')) {
            return array();
        }
        
        $rules = $this->get_active_rules();
        $summary = array();
        
        foreach ($rules as $rule) {
            $conditions = json_decode($rule->conditions, true);
            
            $summary[] = array(
                'rule_id' => $rule->id,
                'rule_name' => $rule->rule_name,
                'tab_title' => $rule->tab_title,
                'conditions' => $conditions,
                'evaluation' => array(
                    'is_active' => $rule->is_active,
                    'conditions_met' => $this->check_rule_conditions($product_id, $rule),
                    'user_role_ok' => $this->check_user_role_permissions($rule),
                    'mobile_ok' => !($this->is_mobile() && $rule->mobile_hidden),
                    'final_result' => $this->should_display_tab($rule, $product_id)
                )
            );
        }
        
        return $summary;
    }
    
    /**
     * Generate schema markup for product tabs
     */
    public function generate_tab_schema($product_id) {
        $tabs = $this->get_product_tabs($product_id);
        $product = wc_get_product($product_id);
        
        if (empty($tabs) || !$product) {
            return '';
        }
        
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->get_name(),
            'additionalProperty' => array()
        );
        
        foreach ($tabs as $tab_key => $tab) {
            if (strpos($tab_key, 'spt_') === 0) {
                $schema['additionalProperty'][] = array(
                    '@type' => 'PropertyValue',
                    'name' => $tab['title'],
                    'description' => wp_strip_all_tags($tab['content'] ?? '')
                );
            }
        }
        
        return json_encode($schema, JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Add schema markup to product pages
     */
    public function add_schema_markup() {
        if (!is_product()) {
            return;
        }
        
        $schema = $this->generate_tab_schema(get_the_ID());
        if (!empty($schema)) {
            echo '<script type="application/ld+json">' . $schema . '</script>';
        }
    }
    
    /**
     * Initialize schema markup if enabled
     */
    public function maybe_init_schema() {
        if (get_option('spt_enable_schema', 0)) {
            add_action('wp_head', array($this, 'add_schema_markup'));
        }
    }
}
<?php
/**
 * Frontend Display for Smart Product Tabs
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
     * Constructor
     */
    public function __construct() {
        add_filter('woocommerce_product_tabs', array($this, 'add_custom_tabs'), 99);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_spt_track_tab_view', array($this, 'ajax_track_tab_view'));
        add_action('wp_ajax_nopriv_spt_track_tab_view', array($this, 'ajax_track_tab_view'));
        
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
            // Check conditions
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
     * Check rule conditions
     */
    private function check_rule_conditions($product_id, $rule) {
        if (!$this->rules_engine) {
            return true; // Fallback if rules engine not available
        }
        
        return $this->rules_engine->check_conditions($product_id, $rule);
    }
    
    /**
     * Check user role permissions
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
            if (empty($allowed_roles)) {
                return false;
            }
            
            $user = wp_get_current_user();
            return !empty(array_intersect($allowed_roles, $user->roles));
        }
        
        return true;
    }
    
    /**
     * Process merge tags in content
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
            '{product_weight}' => $product->get_weight(),
            '{product_dimensions}' => $this->get_product_dimensions($product),
            '{product_stock_status}' => $product->get_stock_status(),
            '{product_stock_quantity}' => $product->get_stock_quantity(),
        );
        
        // Add custom field merge tags
        $content = $this->process_custom_field_tags($content, $product);
        
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
     * Get product categories as string
     */
    private function get_product_categories($product) {
        $categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'));
        return !empty($categories) ? implode(', ', $categories) : '';
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
     * Apply tab ordering from settings
     */
    private function apply_tab_ordering($tabs) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'spt_tab_settings';
        $tab_settings = $wpdb->get_results("SELECT * FROM $table ORDER BY sort_order ASC");
        
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
     * Check if current request is mobile
     */
    private function is_mobile() {
        return wp_is_mobile();
    }
    
    /**
     * Get active rules from database
     */
    private function get_active_rules() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'spt_rules';
        $cache_key = 'spt_active_rules';
        
        // Try to get from cache first
        $rules = wp_cache_get($cache_key);
        
        if (false === $rules) {
            $rules = $wpdb->get_results(
                "SELECT * FROM $table WHERE is_active = 1 ORDER BY priority ASC, created_at DESC"
            );
            
            // Cache for 5 minutes
            wp_cache_set($cache_key, $rules, '', 300);
        }
        
        return $rules;
    }
    
    /**
     * Track tab view
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
     * Get tab content for AJAX requests
     */
    public function ajax_get_tab_content() {
        check_ajax_referer('spt_frontend_nonce', 'nonce');
        
        $rule_id = intval($_POST['rule_id'] ?? 0);
        $product_id = intval($_POST['product_id'] ?? 0);
        
        if (empty($rule_id) || empty($product_id)) {
            wp_send_json_error('Invalid parameters');
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'spt_rules';
        $rule = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $rule_id));
        
        if (!$rule) {
            wp_send_json_error('Rule not found');
            return;
        }
        
        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error('Product not found');
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
     * Add custom body classes for styling
     */
    public function add_body_classes($classes) {
        if (is_product()) {
            $classes[] = 'spt-enabled';
            
            if ($this->is_mobile()) {
                $classes[] = 'spt-mobile';
            }
        }
        
        return $classes;
    }
    
    /**
     * Filter default WooCommerce tabs
     */
    public function filter_default_tabs($tabs) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'spt_tab_settings';
        $settings = $wpdb->get_results("SELECT * FROM $table WHERE tab_type = 'default'");
        
        foreach ($settings as $setting) {
            if (isset($tabs[$setting->tab_key])) {
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
     * Get tab statistics for current product
     */
    public function get_product_tab_stats($product_id) {
        if (!$this->analytics) {
            return array();
        }
        
        return $this->analytics->get_product_tab_stats($product_id);
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
}
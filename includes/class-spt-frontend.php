<?php
/**
 * FIXED Frontend Display - Proper Tab Display with Rules and Merge Tags
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
        
        // Analytics AJAX handlers
        add_action('wp_ajax_spt_track_tab_view', array($this, 'ajax_track_tab_view'));
        add_action('wp_ajax_nopriv_spt_track_tab_view', array($this, 'ajax_track_tab_view'));
        
        add_action('wp_ajax_spt_get_tab_content', array($this, 'ajax_get_tab_content'));
        add_action('wp_ajax_nopriv_spt_get_tab_content', array($this, 'ajax_get_tab_content'));
        add_filter('body_class', array($this, 'add_body_classes'));
        
        // Initialize dependencies
        $this->init_dependencies();
        
        // Add click tracking script
        add_action('wp_footer', array($this, 'add_click_tracking_script'));
    }
    
    /**
     * Initialize dependencies
     */
    public function init_dependencies() {
        if (class_exists('SPT_Analytics')) {
            $this->analytics = new SPT_Analytics();
        }
        
        if (class_exists('SPT_Rules')) {
            $this->rules_engine = new SPT_Rules();
        }
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            debug_log('SPT Frontend: Dependencies initialized - Analytics: ' . ($this->analytics ? 'YES' : 'NO') . ', Rules: ' . ($this->rules_engine ? 'YES' : 'NO'));
        }
    }
    
    /**
     * Add click tracking script
     */
    public function add_click_tracking_script() {
        if (!is_product() || !get_option('spt_enable_analytics', 1)) {
            return;
        }
        
        global $product;
        if (!$product) {
            return;
        }
        
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            console.log('SPT: Click tracking initialized for product:', <?php echo $product->get_id(); ?>);
            
            // Define default WooCommerce tabs to exclude from analytics
            var defaultTabs = ['#description', '#additional_information', '#reviews'];
                    
            // Track actual tab clicks
            $(document).on('click', '.woocommerce-tabs .tabs a', function(e) {
                var $clickedTab = $(this);
                var tabHref = $clickedTab.attr('href');
                
                console.log('SPT: User clicked tab:', tabHref);
                
                // Skip tracking for default WooCommerce tabs
                if (defaultTabs.indexOf(tabHref) !== -1) {
                    console.log('SPT: Skipping default tab:', tabHref);
                    return;
                }                
                
                if (tabHref && typeof spt_frontend !== 'undefined') {
                    sptTrackTabClick(tabHref);
                }
            });
            
            function sptTrackTabClick(tabHref) {
                var tabKey = tabHref.replace('#', '').replace('tab-', '');
                
                console.log('SPT: Tracking user click on tab:', tabKey);
                
                $.ajax({
                    url: spt_frontend.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'spt_track_tab_view',
                        tab_key: tabKey,
                        product_id: spt_frontend.product_id,
                        nonce: spt_frontend.nonce,
                        user_click: 1,
                        is_custom_tab: 1 // Flag to indicate this is a custom SPT tab
                    },
                    success: function(response) {
                        console.log('SPT: Click tracking success:', response);
                    },
                    error: function(xhr, status, error) {
                        console.error('SPT: Click tracking error:', xhr.responseText);
                    }
                });
            }
        });
        </script>
        <?php
    }
    
    /**
     * AJAX handler - track user clicks
     */
    public function ajax_track_tab_view() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'spt_frontend_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        $tab_key = sanitize_text_field($_POST['tab_key'] ?? '');
        $product_id = intval($_POST['product_id'] ?? 0);
        $user_click = isset($_POST['user_click']) ? intval($_POST['user_click']) : 0;
        $is_custom_tab = isset($_POST['is_custom_tab']) ? intval($_POST['is_custom_tab']) : 0;
        
        if (empty($tab_key) || empty($product_id)) {
            wp_send_json_error('Invalid parameters');
            return;
        }
        
        // ONLY track user clicks on custom SPT tabs
        if (!$user_click || !$is_custom_tab) {
            wp_send_json_error('Only custom tab clicks are tracked');
            return;
        }
        
        // Skip default WooCommerce tabs
        $default_tabs = array('description', 'additional_information', 'reviews');
        if (in_array($tab_key, $default_tabs)) {
            wp_send_json_error('Default tabs are not tracked');
            return;
        }        
        
        // Ensure analytics is available
        if (!$this->analytics) {
            if (class_exists('SPT_Analytics')) {
                $this->analytics = new SPT_Analytics();
            } else {
                wp_send_json_error('Analytics not available');
                return;
            }
        }
        
        // Track the actual user click
        try {
            $result = $this->analytics->track_tab_view($tab_key, $product_id);
            
            if ($result) {
                wp_send_json_success(array(
                    'message' => 'User click tracked successfully',
                    'tab_key' => $tab_key,
                    'product_id' => $product_id,
                    'timestamp' => current_time('Y-m-d H:i:s'),
                    'type' => 'custom_tab_click'
                ));
            } else {
                wp_send_json_error('Failed to track user click');
            }
        } catch (Exception $e) {
            wp_send_json_error('Exception: ' . $e->getMessage());
        }
    }
    
    /**
     * FIXED: Add custom tabs to WooCommerce product tabs with proper condition checking
     */
    public function add_custom_tabs($tabs) {
        global $product;
        
        if (!$product) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                debug_log('SPT Frontend: No product found for custom tabs');
            }
            return $tabs;
        }
        
        $rules = $this->get_active_rules();
        
        if (empty($rules)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                debug_log('SPT Frontend: No active rules found');
            }
            return $tabs;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            debug_log('SPT Frontend: Processing ' . count($rules) . ' rules for product ID: ' . $product->get_id());
        }
        
        foreach ($rules as $rule) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                debug_log('SPT Frontend: Checking rule: ' . $rule->rule_name . ' (ID: ' . $rule->id . ')');
            }
            
            // Check conditions using rules engine
            if (!$this->check_rule_conditions($product->get_id(), $rule)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    debug_log('SPT Frontend: Rule conditions not met for rule: ' . $rule->rule_name);
                }
                continue;
            }
            
            // Check user role permissions
            if (!$this->check_user_role_permissions($rule)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    debug_log('SPT Frontend: User role permissions not met for rule: ' . $rule->rule_name);
                }
                continue;
            }
            
            // Check mobile display
            if ($this->is_mobile() && $rule->mobile_hidden) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    debug_log('SPT Frontend: Rule hidden on mobile: ' . $rule->rule_name);
                }
                continue;
            }
            
            // Add tab
            $tab_key = 'spt_' . $rule->id;
            $processed_title = $this->process_merge_tags($rule->tab_title, $product);
            
            $tabs[$tab_key] = array(
                'title' => $processed_title,
                'priority' => intval($rule->priority),
                'callback' => array($this, 'render_tab_content'),
                'rule_id' => $rule->id,
                'rule' => $rule
            );
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                debug_log('SPT Frontend: Added tab: ' . $processed_title . ' (Key: ' . $tab_key . ', Priority: ' . $rule->priority . ')');
            }
        }
        
        // Apply tab ordering
        $tabs = $this->apply_tab_ordering($tabs);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            debug_log('SPT Frontend: Final tabs count: ' . count($tabs));
        }
        
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
                    unset($tabs[$setting->tab_key]);
                    continue;
                }
                
                // Apply custom title if set
                if (!empty($setting->custom_title)) {
                    $tabs[$setting->tab_key]['title'] = $setting->custom_title;
                }
                
                // Apply priority/sort order
                $tabs[$setting->tab_key]['priority'] = $setting->sort_order;
                
                // Check mobile hidden
                if ($this->is_mobile() && $setting->mobile_hidden) {
                    unset($tabs[$setting->tab_key]);
                }
            }
        }
        
        return $tabs;
    }
    
    /**
     * FIXED: Render tab content with proper merge tag processing
     */
    public function render_tab_content($tab_key, $tab) {
        global $product;
        
        if (!isset($tab['rule'])) {
            echo '<p>' . esc_html__('Tab content not available.', 'smart-product-tabs-for-woocommerce') . '</p>';
            return;
        }
        
        $rule = $tab['rule'];
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            debug_log('SPT Frontend: Rendering content for tab: ' . $rule->tab_title . ' (Rule ID: ' . $rule->id . ')');
        }
        
        // Process content with merge tags
        $content = $this->process_merge_tags($rule->tab_content, $product);
        
        // Process shortcodes
        $content = do_shortcode($content);
        
        // Apply content filters
        $content = apply_filters('spt_tab_content', $content, $rule, $product);
        
        // Wrap content in container
        echo '<div class="spt-tab-content" data-tab-key="' . esc_attr($tab_key) . '" data-rule-id="' . esc_attr($rule->id) . '">';
        
        if (!empty($content)) {
            echo wp_kses_post($content);
        } else {
            echo '<p>' . esc_html__('No content available for this tab.', 'smart-product-tabs-for-woocommerce') . '</p>';
        }
        
        echo '</div>';
    }
    
    /**
     * FIXED: Check rule conditions using the rules engine properly
     */
    private function check_rule_conditions($product_id, $rule) {
        if (!$this->rules_engine) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                debug_log('SPT Frontend: Rules engine not available, allowing all rules');
            }
            return true; // If no rules engine, allow all rules
        }
        
        try {
            $result = $this->rules_engine->check_conditions($product_id, $rule);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                debug_log('SPT Frontend: Rule condition check for rule "' . $rule->rule_name . '": ' . ($result ? 'PASS' : 'FAIL'));
                
                // Debug the conditions
                $conditions = json_decode($rule->conditions, true);
                if ($conditions) {
                    debug_log('SPT Frontend: Conditions: ' . print_r($conditions, true));
                }
            }
            
            return $result;
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                debug_log('SPT Frontend: Error checking conditions for rule "' . $rule->rule_name . '": ' . $e->getMessage());
            }
            return false;
        }
    }
    
    /**
     * FIXED: Check user role permissions properly
     */
    private function check_user_role_permissions($rule) {
        // Use rules engine if available
        if ($this->rules_engine && method_exists($this->rules_engine, 'check_user_role')) {
            return $this->rules_engine->check_user_role($rule);
        }
        
        // Fallback implementation
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
        if (self::$rules_cache !== null) {
            return self::$rules_cache;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'spt_rules';
        $cache_key = 'spt_active_rules_v3';
        
        $rules = wp_cache_get($cache_key);
        
        if (false === $rules) {
            $rules = $wpdb->get_results(
                "SELECT * FROM $table WHERE is_active = 1 ORDER BY priority ASC, created_at DESC"
            );
            
            if ($wpdb->last_error) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    debug_log('SPT Frontend: Database error getting rules: ' . $wpdb->last_error);
                }
                $rules = array();
            }
            
            wp_cache_set($cache_key, $rules, '', 300);
        }
        
        self::$rules_cache = $rules;
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            debug_log('SPT Frontend: Retrieved ' . count($rules) . ' active rules from database');
        }
        
        return $rules;
    }
    
    /**
     * Get tab settings with caching
     */
    private function get_tab_settings() {
        if (self::$tab_settings_cache !== null) {
            return self::$tab_settings_cache;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'spt_tab_settings';
        $cache_key = 'spt_tab_settings_v3';
        
        $settings = wp_cache_get($cache_key);
        
        if (false === $settings) {
            $settings = $wpdb->get_results("SELECT * FROM $table ORDER BY sort_order ASC");
            
            if ($wpdb->last_error) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    debug_log('SPT Frontend: Database error getting tab settings: ' . $wpdb->last_error);
                }
                $settings = array();
            }
            
            wp_cache_set($cache_key, $settings, '', 600);
        }
        
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
        
        // First, add tabs according to settings order
        foreach ($tab_settings as $setting) {
            if (isset($remaining_tabs[$setting->tab_key])) {
                if ($setting->is_enabled) {
                    if (!($this->is_mobile() && $setting->mobile_hidden)) {
                        $ordered_tabs[$setting->tab_key] = $remaining_tabs[$setting->tab_key];
                        
                        // Apply custom title for default tabs
                        if (!empty($setting->custom_title) && $setting->tab_type === 'default') {
                            $ordered_tabs[$setting->tab_key]['title'] = $setting->custom_title;
                        }
                        
                        // Set priority from sort order
                        $ordered_tabs[$setting->tab_key]['priority'] = $setting->sort_order;
                    }
                }
                unset($remaining_tabs[$setting->tab_key]);
            }
        }
        
        // Add any remaining tabs (custom tabs not in settings)
        foreach ($remaining_tabs as $key => $tab) {
            $ordered_tabs[$key] = $tab;
        }
        
        return $ordered_tabs;
    }
    
    /**
     * FIXED: Enhanced merge tag processing with more product data
     */
    private function process_merge_tags($content, $product) {
        if (!$product || empty($content)) {
            return $content;
        }
        
        // Basic product information
        $merge_tags = array(
            '{product_name}' => $product->get_name(),
            '{product_price}' => wc_price($product->get_price()),
            '{product_regular_price}' => wc_price($product->get_regular_price()),
            '{product_sale_price}' => $product->get_sale_price() ? wc_price($product->get_sale_price()) : '',
            '{product_sku}' => $product->get_sku(),
            '{product_category}' => $this->get_product_categories($product),
            '{product_tags}' => $this->get_product_tags($product),
            '{product_short_description}' => $product->get_short_description(),
            '{product_weight}' => $product->get_weight(),
            '{product_dimensions}' => $this->get_product_dimensions($product),
            '{product_stock_status}' => $product->get_stock_status(),
            '{product_stock_quantity}' => $product->get_stock_quantity(),
            '{product_type}' => $product->get_type(),
            '{product_featured}' => $product->is_featured() ? esc_html__('Yes', 'smart-product-tabs-for-woocommerce') : esc_html__('No', 'smart-product-tabs-for-woocommerce'),
            '{product_on_sale}' => $product->is_on_sale() ? esc_html__('Yes', 'smart-product-tabs-for-woocommerce') : esc_html__('No', 'smart-product-tabs-for-woocommerce'),
            '{product_permalink}' => $product->get_permalink(),
            '{product_rating}' => $product->get_average_rating(),
            '{product_review_count}' => $product->get_review_count(),
        );
        
        // Add attribute merge tags
        $attributes = $product->get_attributes();
        foreach ($attributes as $attribute_name => $attribute) {
            $attribute_key = '{attribute_' . sanitize_title($attribute_name) . '}';
            
            if ($attribute->is_taxonomy()) {
                $terms = wp_get_post_terms($product->get_id(), $attribute_name, array('fields' => 'names'));
                $merge_tags[$attribute_key] = !empty($terms) && !is_wp_error($terms) ? implode(', ', $terms) : '';
            } else {
                $merge_tags[$attribute_key] = implode(', ', $attribute->get_options());
            }
        }
        
        // Process custom field merge tags (pattern: {custom_field_[key]})
        preg_match_all('/\{custom_field_([^}]+)\}/', $content, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $field_key) {
                $field_value = get_post_meta($product->get_id(), $field_key, true);
                $merge_tags['{custom_field_' . $field_key . '}'] = $field_value ? $field_value : '';
            }
        }
        
        // Apply all merge tags
        $content = str_replace(array_keys($merge_tags), array_values($merge_tags), $content);
        
        // Process conditional merge tags (Advanced feature)
        $content = $this->process_conditional_merge_tags($content, $product);
        
        return $content;
    }
    
    /**
     * Get product categories as formatted string
     */
    private function get_product_categories($product) {
        $categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'));
        return !empty($categories) && !is_wp_error($categories) ? implode(', ', $categories) : '';
    }
    
    /**
     * Get product tags as formatted string
     */
    private function get_product_tags($product) {
        $tags = wp_get_post_terms($product->get_id(), 'product_tag', array('fields' => 'names'));
        return !empty($tags) && !is_wp_error($tags) ? implode(', ', $tags) : '';
    }
    
    /**
     * Get formatted product dimensions
     */
    private function get_product_dimensions($product) {
        $dimensions = array();
        
        if ($product->get_length()) {
            $dimensions[] = $product->get_length();
        }
        if ($product->get_width()) {
            $dimensions[] = $product->get_width();
        }
        if ($product->get_height()) {
            $dimensions[] = $product->get_height();
        }
        
        if (!empty($dimensions)) {
            return implode(' × ', $dimensions) . ' ' . get_option('woocommerce_dimension_unit');
        }
        
        return '';
    }
    
    /**
     * Process conditional merge tags (Advanced feature)
     * Example: {if:on_sale}This product is on sale!{/if:on_sale}
     */
    private function process_conditional_merge_tags($content, $product) {
        // Pattern for conditional tags
        $pattern = '/\{if:([^}]+)\}(.*?)\{\/if:\1\}/s';
        
        return preg_replace_callback($pattern, function($matches) use ($product) {
            $condition = $matches[1];
            $conditional_content = $matches[2];
            
            $show_content = false;
            
            switch ($condition) {
                case 'on_sale':
                    $show_content = $product->is_on_sale();
                    break;
                case 'featured':
                    $show_content = $product->is_featured();
                    break;
                case 'in_stock':
                    $show_content = $product->is_in_stock();
                    break;
                case 'has_weight':
                    $show_content = !empty($product->get_weight());
                    break;
                case 'has_dimensions':
                    $show_content = !empty($product->get_length()) || !empty($product->get_width()) || !empty($product->get_height());
                    break;
                case 'has_sku':
                    $show_content = !empty($product->get_sku());
                    break;
                case 'downloadable':
                    $show_content = $product->is_downloadable();
                    break;
                case 'virtual':
                    $show_content = $product->is_virtual();
                    break;
            }
            
            return $show_content ? $conditional_content : '';
        }, $content);
    }
    
    /**
     * Check if current request is mobile
     */
    private function is_mobile() {
        return wp_is_mobile();
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
        
        $rule = $this->get_rule_by_id($rule_id);
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
     * Enqueue frontend assets with proper localization
     */
    public function enqueue_assets() {
        if (!is_product()) {
            return;
        }
        
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
        
        wp_enqueue_script(
            'spt-frontend',
            SPT_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            SPT_VERSION,
            true
        );
        
        wp_localize_script('spt-frontend', 'spt_frontend', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'product_id' => get_the_ID(),
            'track_views' => get_option('spt_enable_analytics', 1),
            'nonce' => wp_create_nonce('spt_frontend_nonce'),
            'debug' => (defined('WP_DEBUG') && WP_DEBUG) || isset($_GET['spt_debug']),
            'analytics_available' => $this->analytics ? 'yes' : 'no',
            'tracking_mode' => 'click_only'
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
     * Clear caches when rules are updated
     */
    public static function clear_caches() {
        self::$rules_cache = null;
        self::$tab_settings_cache = null;
        wp_cache_delete('spt_active_rules_v3');
        wp_cache_delete('spt_tab_settings_v3');
    }
    
    
}
?>
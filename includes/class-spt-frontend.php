<?php
/**
 * FIXED Frontend Display - Only Track Actual Tab Clicks
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
        
        // FIXED: Only add click tracking script, no automatic tracking
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
            error_log('SPT Frontend: Analytics initialized: ' . ($this->analytics ? 'YES' : 'NO'));
        }
    }
    
    /**
     * FIXED: Add ONLY click tracking script - no automatic page load tracking
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
            console.log('SPT: Click-only tracking initialized for product:', <?php echo $product->get_id(); ?>);
            
            // Track ONLY actual tab clicks - no automatic tracking
            $(document).on('click', '.woocommerce-tabs .tabs a', function(e) {
                var $clickedTab = $(this);
                var tabHref = $clickedTab.attr('href');
                
                console.log('SPT: User clicked tab:', tabHref);
                
                // Only track if this is an actual user click
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
                        user_click: 1 // Flag to indicate this is a real user click
                    },
                    success: function(response) {
                        console.log('SPT: Click tracking success:', response);
                    },
                    error: function(xhr, status, error) {
                        console.error('SPT: Click tracking error:', xhr.responseText);
                    }
                });
            }
            
            // Add debug panel if debug mode is enabled
            if (spt_frontend.debug) {
                $('body').append('<div id="spt-debug-panel" style="position:fixed;bottom:10px;right:10px;z-index:9999;background:#333;color:#fff;padding:10px;border-radius:5px;font-size:12px;max-width:250px;">' +
                    '<strong>SPT Analytics Debug</strong><br>' +
                    'Mode: Click-only tracking<br>' +
                    'Product ID: ' + spt_frontend.product_id + '<br>' +
                    '<button id="spt-test-click" style="background:#0073aa;color:white;border:none;padding:5px;margin-top:5px;border-radius:3px;cursor:pointer;">Test Click</button>' +
                    '<div id="spt-debug-result" style="margin-top:5px;font-size:10px;"></div>' +
                    '</div>');
                
                $('#spt-test-click').click(function() {
                    console.log('SPT: Manual test click triggered');
                    sptTrackTabClick('#description');
                    $('#spt-debug-result').html('Test click sent');
                });
            }
        });
        </script>
        <?php
    }
    
    /**
     * FIXED: AJAX handler - only track real user clicks
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
        
        if (empty($tab_key) || empty($product_id)) {
            wp_send_json_error('Invalid parameters');
            return;
        }
        
        // FIXED: Only track if this is marked as a user click
        if (!$user_click) {
            wp_send_json_error('Only user clicks are tracked');
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
                    'type' => 'user_click'
                ));
            } else {
                wp_send_json_error('Failed to track user click');
            }
        } catch (Exception $e) {
            wp_send_json_error('Exception: ' . $e->getMessage());
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
                    unset($tabs[$setting->tab_key]);
                } else {
                    if (!empty($setting->custom_title)) {
                        $tabs[$setting->tab_key]['title'] = $setting->custom_title;
                    }
                    
                    $tabs[$setting->tab_key]['priority'] = $setting->sort_order;
                    
                    if ($this->is_mobile() && $setting->mobile_hidden) {
                        unset($tabs[$setting->tab_key]);
                    }
                }
            }
        }
        
        return $tabs;
    }
    
    /**
     * FIXED: Render tab content WITHOUT automatic tracking
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
        
        // REMOVED: No automatic tracking when content is rendered
        // Only track when user actually clicks the tab
    }
    
    /**
     * Check rule conditions using the rules engine
     */
    private function check_rule_conditions($product_id, $rule) {
        if (!$this->rules_engine) {
            return true;
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
        $cache_key = 'spt_active_rules_v2';
        
        $rules = wp_cache_get($cache_key);
        
        if (false === $rules) {
            $rules = $wpdb->get_results(
                "SELECT * FROM $table WHERE is_active = 1 ORDER BY priority ASC, created_at DESC"
            );
            
            wp_cache_set($cache_key, $rules, '', 300);
        }
        
        self::$rules_cache = $rules;
        
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
        $cache_key = 'spt_tab_settings_v2';
        
        $settings = wp_cache_get($cache_key);
        
        if (false === $settings) {
            $settings = $wpdb->get_results("SELECT * FROM $table ORDER BY sort_order ASC");
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
        
        foreach ($tab_settings as $setting) {
            if (isset($remaining_tabs[$setting->tab_key])) {
                if ($setting->is_enabled) {
                    if (!($this->is_mobile() && $setting->mobile_hidden)) {
                        $ordered_tabs[$setting->tab_key] = $remaining_tabs[$setting->tab_key];
                        
                        if (!empty($setting->custom_title) && $setting->tab_type === 'default') {
                            $ordered_tabs[$setting->tab_key]['title'] = $setting->custom_title;
                        }
                        
                        $ordered_tabs[$setting->tab_key]['priority'] = $setting->sort_order;
                    }
                }
                unset($remaining_tabs[$setting->tab_key]);
            }
        }
        
        foreach ($remaining_tabs as $key => $tab) {
            $ordered_tabs[$key] = $tab;
        }
        
        return $ordered_tabs;
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
        );
        
        $content = str_replace(array_keys($merge_tags), array_values($merge_tags), $content);
        
        return $content;
    }
    
    /**
     * Get product categories as string
     */
    private function get_product_categories($product) {
        $categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'));
        return !empty($categories) && !is_wp_error($categories) ? implode(', ', $categories) : '';
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
}
?>
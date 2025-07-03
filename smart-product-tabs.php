<?php
/**
 * Plugin Name: Smart Product Tabs for WooCommerce
 * Plugin URI: https://your-domain.com/smart-product-tabs
 * Description: Automatically generate and manage product tabs with advanced conditional rules, user role targeting, and mobile optimization.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://your-domain.com
 * Text Domain: smart-product-tabs
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SPT_VERSION', '1.0.0');
define('SPT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SPT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SPT_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Smart Product Tabs class
 */
class Smart_Product_Tabs {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Check if WooCommerce is active
        if (!$this->is_woocommerce_active()) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Load includes
        $this->load_includes();
        
        // Initialize classes
        $this->init_classes();
        
        // Load assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Check if WooCommerce is active
     */
    private function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }
    
    /**
     * Load plugin includes
     */
    private function load_includes() {
        require_once SPT_PLUGIN_PATH . 'includes/class-spt-admin.php';
        require_once SPT_PLUGIN_PATH . 'includes/class-spt-frontend.php';
        require_once SPT_PLUGIN_PATH . 'includes/class-spt-rules.php';
        require_once SPT_PLUGIN_PATH . 'includes/class-spt-analytics.php';
        require_once SPT_PLUGIN_PATH . 'includes/class-spt-templates.php';
    }
    
    /**
     * Initialize classes
     */
    private function init_classes() {
        new SPT_Admin();
        new SPT_Frontend();
        new SPT_Rules();
        new SPT_Analytics();
        new SPT_Templates();
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        if (is_product()) {
            wp_enqueue_style('spt-frontend', SPT_PLUGIN_URL . 'assets/css/frontend.css', array(), SPT_VERSION);
            wp_enqueue_style('spt-mobile', SPT_PLUGIN_URL . 'assets/css/mobile.css', array(), SPT_VERSION);
            wp_enqueue_script('spt-frontend', SPT_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), SPT_VERSION, true);
        }
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'woocommerce_page_smart-product-tabs') !== false) {
            wp_enqueue_style('spt-admin', SPT_PLUGIN_URL . 'assets/css/admin.css', array(), SPT_VERSION);
            wp_enqueue_script('spt-admin', SPT_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'jquery-ui-sortable'), SPT_VERSION, true);
            
            // Localize script for AJAX
            wp_localize_script('spt-admin', 'spt_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('spt_ajax_nonce')
            ));
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $this->create_tables();
        
        // Add default settings
        $this->add_default_settings();
        
        // Schedule cleanup event
        if (!wp_next_scheduled('spt_cleanup_analytics')) {
            wp_schedule_event(time(), 'daily', 'spt_cleanup_analytics');
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('spt_cleanup_analytics');
    }
    
    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Rules table
        $table_rules = $wpdb->prefix . 'spt_rules';
        $sql_rules = "CREATE TABLE $table_rules (
            id int(11) NOT NULL AUTO_INCREMENT,
            rule_name varchar(255) NOT NULL,
            tab_title varchar(255) NOT NULL,
            tab_content longtext,
            content_type varchar(20) DEFAULT 'rich_editor',
            conditions text,
            user_role_condition varchar(50) DEFAULT 'all',
            user_roles text,
            priority int(11) DEFAULT 10,
            is_active tinyint(1) DEFAULT 1,
            mobile_hidden tinyint(1) DEFAULT 0,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Tab settings table
        $table_settings = $wpdb->prefix . 'spt_tab_settings';
        $sql_settings = "CREATE TABLE $table_settings (
            id int(11) NOT NULL AUTO_INCREMENT,
            tab_key varchar(100) NOT NULL UNIQUE,
            tab_type enum('default', 'custom') NOT NULL,
            custom_title varchar(255),
            is_enabled tinyint(1) DEFAULT 1,
            sort_order int(11) DEFAULT 10,
            mobile_hidden tinyint(1) DEFAULT 0,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Analytics table
        $table_analytics = $wpdb->prefix . 'spt_analytics';
        $sql_analytics = "CREATE TABLE $table_analytics (
            id int(11) NOT NULL AUTO_INCREMENT,
            tab_key varchar(100) NOT NULL,
            product_id int(11) NOT NULL,
            views int(11) DEFAULT 0,
            date date NOT NULL,
            PRIMARY KEY (id),
            KEY idx_tab_date (tab_key, date)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_rules);
        dbDelta($sql_settings);
        dbDelta($sql_analytics);
    }
    
    /**
     * Add default settings
     */
    private function add_default_settings() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'spt_tab_settings';
        
        // Default WooCommerce tabs
        $default_tabs = array(
            array('description', 'default', 'Description', 1, 10, 0),
            array('additional_information', 'default', 'Additional Information', 1, 20, 0),
            array('reviews', 'default', 'Reviews', 1, 30, 0)
        );
        
        foreach ($default_tabs as $tab) {
            $wpdb->replace($table, array(
                'tab_key' => $tab[0],
                'tab_type' => $tab[1],
                'custom_title' => $tab[2],
                'is_enabled' => $tab[3],
                'sort_order' => $tab[4],
                'mobile_hidden' => $tab[5]
            ));
        }
    }
    
    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        echo '<div class="error"><p><strong>' . __('Smart Product Tabs for WooCommerce') . '</strong> ' . __('requires WooCommerce to be installed and active.') . '</p></div>';
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain('smart-product-tabs', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
}

// Initialize the plugin
Smart_Product_Tabs::get_instance();
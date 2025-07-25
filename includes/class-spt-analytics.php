<?php
/**
 * FIXED Analytics System with Better Error Handling and Debugging
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SPT_Analytics {
    
    /**
     * Analytics table name
     */
    private $table_name;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'spt_analytics';
        
        add_action('init', array($this, 'init'));
        add_action('spt_cleanup_analytics', array($this, 'cleanup_old_data'));
        add_action('wp_ajax_spt_get_analytics_data', array($this, 'ajax_get_analytics_data'));
        
        // Ensure table exists
        $this->maybe_create_table();
    }
    
    /**
     * Initialize
     */
    public function init() {
        if (!wp_next_scheduled('spt_cleanup_analytics')) {
            wp_schedule_event(time(), 'daily', 'spt_cleanup_analytics');
        }
    }
    
    /**
     * Ensure analytics table exists
     */
    private function maybe_create_table() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'");
        
        if ($table_exists != $this->table_name) {
            $this->create_analytics_table();
        }
    }
    
    /**
     * Create analytics table
     */
    private function create_analytics_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table_name} (
            id int(11) NOT NULL AUTO_INCREMENT,
            tab_key varchar(100) NOT NULL,
            product_id int(11) NOT NULL,
            views int(11) DEFAULT 0,
            date date NOT NULL,
            PRIMARY KEY (id),
            KEY idx_tab_date (tab_key, date),
            KEY idx_product_date (product_id, date),
            KEY idx_date (date),
            UNIQUE KEY unique_tab_product_date (tab_key, product_id, date)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Verify table was created
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'");
        if ($table_exists != $this->table_name) {
            debug_log('SPT Analytics: Failed to create table ' . $this->table_name);
        } else {
            debug_log('SPT Analytics: Table created successfully: ' . $this->table_name);
        }
    }
    
    /**
     * FIXED: Track tab view with comprehensive error handling and debugging
     */
    public function track_tab_view($tab_key, $product_id) {
        // Debug start
        debug_log('SPT Analytics: track_tab_view called with tab_key=' . $tab_key . ', product_id=' . $product_id);
        
        // Check if analytics is enabled
        if (!get_option('spt_enable_analytics', 1)) {
            debug_log('SPT Analytics: Tracking disabled in settings');
            return false;
        }
        
        // Don't track admin users or bots
        if ($this->is_bot()) {
            debug_log('SPT Analytics: Skipping tracking - admin user or bot detected');
            return false;
        }
        
        // Validate inputs
        if (empty($tab_key) || empty($product_id)) {
            debug_log('SPT Analytics: Invalid parameters - tab_key: "' . $tab_key . '", product_id: "' . $product_id . '"');
            return false;
        }
        
        global $wpdb;
        
        // Check if table exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'");
        if ($table_exists != $this->table_name) {
            debug_log('SPT Analytics: Table does not exist: ' . $this->table_name);
            // Try to create it
            $this->create_analytics_table();
            
            // Check again
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'");
            if ($table_exists != $this->table_name) {
                debug_log('SPT Analytics: Failed to create table after retry');
                return false;
            }
        }
        
        $today = current_time('Y-m-d');
        
        // Clean tab key (remove # and tab- prefix if present)
        $tab_key = str_replace(array('#', 'tab-'), '', $tab_key);
        
        debug_log("SPT Analytics: Attempting to track - Tab: {$tab_key}, Product: {$product_id}, Date: {$today}");
        
        // Try to insert or update using INSERT ... ON DUPLICATE KEY UPDATE
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = $wpdb->prepare("INSERT INTO {$this->table_name} (tab_key, product_id, views, date) 
             VALUES (%s, %d, 1, %s) 
             ON DUPLICATE KEY UPDATE views = views + 1",
            $tab_key,
            intval($product_id),
            $today
        );
        
        debug_log('SPT Analytics: SQL Query: ' . $sql);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
        $result = $wpdb->query($sql);
        
        if ($result === false) {
            debug_log('SPT Analytics: Database error - ' . $wpdb->last_error);
            debug_log('SPT Analytics: Last query: ' . $wpdb->last_query);
            
            // Try alternative approach - check if record exists first
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $existing = $wpdb->get_row($wpdb->prepare( "SELECT id, views FROM {$this->table_name} WHERE tab_key = %s AND product_id = %d AND date = %s",
                $tab_key,
                intval($product_id),
                $today
            ));
            
            if ($existing) {
                // Update existing record
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $update_result = $wpdb->update(
                    $this->table_name,
                    array('views' => $existing->views + 1),
                    array('id' => $existing->id),
                    array('%d'),
                    array('%d')
                );
                
                if ($update_result !== false) {
                    debug_log("SPT Analytics: Successfully updated existing record (ID: {$existing->id})");
                    return true;
                } else {
                    debug_log('SPT Analytics: Failed to update existing record - ' . $wpdb->last_error);
                    return false;
                }
            } else {
                // Insert new record
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $insert_result = $wpdb->insert(
                    $this->table_name,
                    array(
                        'tab_key' => $tab_key,
                        'product_id' => intval($product_id),
                        'views' => 1,
                        'date' => $today
                    ),
                    array('%s', '%d', '%d', '%s')
                );
                
                if ($insert_result !== false) {
                    debug_log("SPT Analytics: Successfully inserted new record (ID: {$wpdb->insert_id})");
                    return true;
                } else {
                    debug_log('SPT Analytics: Failed to insert new record - ' . $wpdb->last_error);
                    return false;
                }
            }
        } else {
            // Success with ON DUPLICATE KEY UPDATE
            debug_log("SPT Analytics: Successfully tracked view for tab: {$tab_key} (affected rows: {$result})");
            
            // Verify the data was actually saved
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $verification = $wpdb->get_row($wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE tab_key = %s AND product_id = %d AND date = %s",
                $tab_key,
                intval($product_id),
                $today
            ));
            
            if ($verification) {
                debug_log("SPT Analytics: Verification successful - Record ID: {$verification->id}, Views: {$verification->views}");
                return true;
            } else {
                debug_log('SPT Analytics: Verification failed - record not found after insert');
                return false;
            }
        }
    }
    
    /**
     * Get popular tabs
     */
    public function get_popular_tabs($limit = 10, $days = 30) {
        global $wpdb;

        $date_from = gmdate('Y-m-d', strtotime("-{$days} days"));

        $sql = "SELECT tab_key, SUM(views) as total_views, COUNT(DISTINCT product_id) as products_count
             FROM {$this->table_name}
             WHERE date >= %s
             GROUP BY tab_key
             ORDER BY total_views DESC
             LIMIT %d";
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared    
        $results = $wpdb->get_results($wpdb->prepare($sql,$date_from,$limit));

        // Enhance results with tab titles
        foreach ($results as &$result) {
            $result->display_name = $this->get_tab_display_name($result->tab_key);
            $result->tab_type = $this->get_tab_type($result->tab_key);
        }

        return $results;
    }
    
    
    /**
     * Get display name for tab key
     */
    private function get_tab_display_name($tab_key) {
        global $wpdb;

        // Check if it's a custom SPT tab (format: spt_X)
        if (preg_match('/^spt_(\d+)$/', $tab_key, $matches)) {
            $rule_id = $matches[1];
            $rules_table = $wpdb->prefix . 'spt_rules';
            
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $rule_title = $wpdb->get_var($wpdb->prepare("SELECT tab_title FROM $rules_table WHERE id = %d",$rule_id));

            if ($rule_title) {
                return $rule_title . ' (' . $tab_key . ')';
            }
        }

        // Check default WooCommerce tabs
        $default_tabs = array(
            'description' => esc_html__('Description', 'smart-product-tabs-for-woocommerce'),
            'additional_information' => esc_html__('Additional Information', 'smart-product-tabs-for-woocommerce'),
            'reviews' => esc_html__('Reviews', 'smart-product-tabs-for-woocommerce')
        );

        if (isset($default_tabs[$tab_key])) {
            return $default_tabs[$tab_key];
        }

        // Fallback to formatted tab key
        return ucwords(str_replace(array('_', '-'), ' ', $tab_key)) . ' (' . $tab_key . ')';
    }  
    
    
    /**
     * Get tab type (custom or default)
     */
    private function get_tab_type($tab_key) {
        if (preg_match('/^spt_\d+$/', $tab_key)) {
            return 'custom';
        }

        $default_tabs = array('description', 'additional_information', 'reviews');
        if (in_array($tab_key, $default_tabs)) {
            return 'default';
        }

        return 'unknown';
    }    
    
    /**
     * Get tab performance for specific period
     */
    public function get_tab_performance($tab_key, $days = 30) {
        global $wpdb;
        
        $date_from = gmdate('Y-m-d', strtotime("-{$days} days"));
        
        $sql = "SELECT date, SUM(views) as daily_views, COUNT(DISTINCT product_id) as products_viewed
             FROM {$this->table_name}
             WHERE tab_key = %s AND date >= %s
             GROUP BY date
             ORDER BY date ASC";
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results($wpdb->prepare($sql,$tab_key,$date_from));
    }
    
    /**
     * Get product-specific tab statistics
     */
    public function get_product_tab_stats($product_id, $days = 30) {
        global $wpdb;
        
        $date_from = gmdate('Y-m-d', strtotime("-{$days} days"));
        
        $sql = "SELECT tab_key, SUM(views) as total_views, 
                    MAX(date) as last_viewed,
                    COUNT(DISTINCT date) as active_days
             FROM {$this->table_name}
             WHERE product_id = %d AND date >= %s
             GROUP BY tab_key
             ORDER BY total_views DESC";
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results($wpdb->prepare($sql,$product_id,$date_from));
    }
    
    /**
     * Get overall analytics summary with better data handling
     */
    public function get_analytics_summary($days = 30) {
        global $wpdb;
        
        $date_from = gmdate('Y-m-d', strtotime("-{$days} days"));
        
        // Total views
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total_views = $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(views), 0) FROM {$this->table_name} WHERE date >= %s",$date_from));
        
        // Unique products viewed
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $unique_products = $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT product_id) FROM {$this->table_name} WHERE date >= %s",$date_from));
        
        // Total active tabs
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $active_tabs = $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT tab_key) FROM {$this->table_name} WHERE date >= %s",$date_from));
        
        // Average views per day
        
        $sql = "SELECT COALESCE(AVG(daily_views), 0) FROM (
                SELECT date, SUM(views) as daily_views 
                FROM {$this->table_name} 
                WHERE date >= %s 
                GROUP BY date
             ) as daily_stats";
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
        $avg_daily_views = $wpdb->get_var($wpdb->prepare($sql,$date_from));
        
        return array(
            'total_views' => intval($total_views ?: 0),
            'unique_products' => intval($unique_products ?: 0),
            'active_tabs' => intval($active_tabs ?: 0),
            'avg_daily_views' => floatval($avg_daily_views ?: 0),
            'period_days' => $days
        );
    }
    
    /**
     * Get top performing products
     */
    public function get_top_products($limit = 10, $days = 30) {
        global $wpdb;

        $date_from = gmdate('Y-m-d', strtotime("-{$days} days"));
        
        $sql = "SELECT product_id, SUM(views) as total_views, 
                    COUNT(DISTINCT tab_key) as tabs_viewed,
                    COUNT(DISTINCT date) as active_days
             FROM {$this->table_name}
             WHERE date >= %s
             GROUP BY product_id
             ORDER BY total_views DESC
             LIMIT %d";
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
        $results = $wpdb->get_results($wpdb->prepare($sql,$date_from,$limit));

        // Enhance with product data
        foreach ($results as &$result) {
            $product = wc_get_product($result->product_id);
            if ($product) {
                $result->product_name = $product->get_name();
                $result->product_url = $product->get_permalink();
                $result->product_price = $product->get_price();
                $result->product_status = $product->get_status();
            } else {
                $result->product_name = esc_html__('Product Not Found', 'smart-product-tabs-for-woocommerce');
                $result->product_url = '';
                $result->product_price = 0;
                $result->product_status = 'not_found';
            }
        }

        return $results;
    }
    
    /**
     * Get engagement metrics
     */
    public function get_engagement_metrics($days = 30) {
        global $wpdb;
        
        $date_from = gmdate('Y-m-d', strtotime("-{$days} days"));
        
        // Tab engagement rate
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $products_with_views = $wpdb->get_var($wpdb->prepare( "SELECT COUNT(DISTINCT product_id) FROM {$this->table_name} WHERE date >= %s", $date_from));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total_products = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'");
        
        $engagement_rate = $total_products > 0 ? ($products_with_views / $total_products) * 100 : 0;
        
        // Average tabs per product view
        
        $sql = "SELECT COALESCE(AVG(tabs_count), 0) FROM (
                SELECT product_id, COUNT(DISTINCT tab_key) as tabs_count
                FROM {$this->table_name}
                WHERE date >= %s
                GROUP BY product_id
             ) as product_stats";
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
        $avg_tabs_per_product = $wpdb->get_var($wpdb->prepare($sql,$date_from));
        
        // Most active day
        
        $sql = "SELECT date, SUM(views) as total_views
             FROM {$this->table_name}
             WHERE date >= %s
             GROUP BY date
             ORDER BY total_views DESC
             LIMIT 1";
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
        $most_active_day = $wpdb->get_row($wpdb->prepare($sql, $date_from));
        
        return array(
            'engagement_rate' => round($engagement_rate, 2),
            'avg_tabs_per_product' => round(floatval($avg_tabs_per_product ?: 0), 2),
            'most_active_day' => $most_active_day ? $most_active_day->date : null,
            'most_active_day_views' => $most_active_day ? intval($most_active_day->total_views) : 0
        );
    }
    
    /**
     * Get daily analytics data for charts
     */
    public function get_daily_analytics($days = 30) {
        global $wpdb;
        
        $date_from = gmdate('Y-m-d', strtotime("-{$days} days"));
        
        $sql = "SELECT date, 
                    SUM(views) as total_views,
                    COUNT(DISTINCT tab_key) as unique_tabs,
                    COUNT(DISTINCT product_id) as unique_products
             FROM {$this->table_name}
             WHERE date >= %s
             GROUP BY date
             ORDER BY date ASC";
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results($wpdb->prepare($sql, $date_from ));
    }
    
    /**
     * Check if current request is from a bot
     */
    private function is_bot() {
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        
        $bot_patterns = array(
            'bot', 'crawler', 'spider', 'crawling', 'facebook', 'google',
            'baidu', 'bing', 'msn', 'duckduckbot', 'teoma', 'slurp',
            'yandex', 'lighthouse', 'pagespeed'
        );
        
        foreach ($bot_patterns as $pattern) {
            if (stripos($user_agent, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Export analytics data
     */
    public function export_analytics($format = 'csv', $days = 30) {
        global $wpdb;
        
        $date_from = gmdate('Y-m-d', strtotime("-{$days} days"));
        
        $sql = "SELECT a.date, a.tab_key, a.product_id, a.views,
                    p.post_title as product_name
             FROM {$this->table_name} a
             LEFT JOIN {$wpdb->posts} p ON a.product_id = p.ID
             WHERE a.date >= %s
             ORDER BY a.date DESC, a.views DESC";
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
        $data = $wpdb->get_results($wpdb->prepare( $sql, $date_from), ARRAY_A);
        
        if ($format === 'csv') {
            return $this->convert_to_csv($data);
        } elseif ($format === 'json') {
            return json_encode($data, JSON_PRETTY_PRINT);
        }
        
        return $data;
    }
    
    /**
     * Convert data to CSV format
     */
    private function convert_to_csv($data) {
        if (empty($data)) {
            return '';
        }
        
        $csv = '';
        
        // Add headers
        $headers = array_keys($data[0]);
        $csv .= implode(',', $headers) . "\n";
        
        // Add data rows
        foreach ($data as $row) {
            $csv .= implode(',', array_map(function($value) {
                return '"' . str_replace('"', '""', $value) . '"';
            }, $row)) . "\n";
        }
        
        return $csv;
    }
    
    /**
     * Cleanup old analytics data
     */
    public function cleanup_old_data() {
        global $wpdb;
        
        $retention_days = apply_filters('spt_analytics_retention_days', 90);
        $cutoff_date = gmdate('Y-m-d', strtotime("-{$retention_days} days"));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $deleted = $wpdb->query($wpdb->prepare( "DELETE FROM {$this->table_name} WHERE date < %s", $cutoff_date ));
        
        if ($deleted > 0) {
            debug_log("SPT Analytics: Cleaned up {$deleted} old records older than {$cutoff_date}");
        }
        
        return $deleted;
    }
    
    /**
     * AJAX handler for analytics data
     */
    public function ajax_get_analytics_data() {
        check_ajax_referer('spt_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $type = sanitize_text_field(wp_unslash($_POST['type'] ?? 'summary'));
        $days = intval($_POST['days'] ?? 30);
        
        switch ($type) {
            case 'summary':
                $data = $this->get_analytics_summary($days);
                break;
                
            case 'popular_tabs':
                $data = $this->get_popular_tabs(10, $days);
                break;
                
            case 'daily_analytics':
                $data = $this->get_daily_analytics($days);
                break;
                
            case 'top_products':
                $data = $this->get_top_products(10, $days);
                break;
                
            case 'engagement':
                $data = $this->get_engagement_metrics($days);
                break;
                
            default:
                wp_send_json_error('Invalid data type');
                return;
        }
        
        wp_send_json_success($data);
    }
    
    /**
     * Reset all analytics data
     */
    public function reset_analytics() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $wpdb->query("TRUNCATE TABLE {$this->table_name}");
        
        if ($result !== false) {
            do_action('spt_analytics_reset');
            return true;
        }
        
        return false;
    }
    
    /**
     * Get database table size
     */
    public function get_table_size() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as record_count,
                ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb
             FROM information_schema.TABLES 
             WHERE table_schema = %s 
             AND table_name = %s",
            DB_NAME,
            $this->table_name
        ));
        
        return $result ? $result : (object) array('record_count' => 0, 'size_mb' => 0);
    }
    
    /**
     * Debug method to test analytics directly
     */
    public function debug_test_tracking() {
        debug_log('SPT Analytics: Starting debug test');
        
        // Test with simple data
        $result = $this->track_tab_view('debug_test', 1);
        
        debug_log('SPT Analytics: Debug test result: ' . ($result ? 'SUCCESS' : 'FAILED'));
        
        return $result;
    }
    
    /**
     * Get current table status
     */
    public function get_table_status() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") === $this->table_name;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $record_count = $table_exists ? $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}") : 0;
        
        return array(
            'table_exists' => $table_exists,
            'table_name' => $this->table_name,
            'record_count' => $record_count,
            'last_error' => $wpdb->last_error
        );
    }
    
    
    
    public function get_daily_analytics_enhanced($days = 30) {
        global $wpdb;

        $date_from = gmdate('Y-m-d', strtotime("-{$days} days"));
        
        // Build query with validated table name
        $table_name = esc_sql($this->table_name);
        $sql = "SELECT date, 
                       SUM(views) as total_views,
                       COUNT(DISTINCT tab_key) as unique_tabs,
                       COUNT(DISTINCT product_id) as unique_products,
                       GROUP_CONCAT(DISTINCT tab_key ORDER BY tab_key) as active_tabs
                FROM {$table_name}
                WHERE date >= %s
                GROUP BY date
                ORDER BY date ASC";

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
        $results = $wpdb->get_results($wpdb->prepare($sql, $date_from));        
        
        

        // Format dates for better display
        foreach ($results as &$result) {
            $result->formatted_date = gmdate('M j, Y', strtotime($result->date));
            $result->day_of_week = gmdate('l', strtotime($result->date));
        }

        return $results;
    }        
    
    
}
?>
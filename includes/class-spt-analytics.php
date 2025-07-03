<?php
/**
 * Analytics System for Smart Product Tabs
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
    }
    
    /**
     * Initialize
     */
    public function init() {
        // Schedule cleanup if not already scheduled
        if (!wp_next_scheduled('spt_cleanup_analytics')) {
            wp_schedule_event(time(), 'daily', 'spt_cleanup_analytics');
        }
    }
    
    /**
     * Track tab view
     */
    public function track_tab_view($tab_key, $product_id) {
        // Check if analytics is enabled
        if (!get_option('spt_enable_analytics', 1)) {
            return false;
        }
        
        // Don't track admin users or bots
        if (is_admin() || $this->is_bot()) {
            return false;
        }
        
        global $wpdb;
        $today = current_time('Y-m-d');
        
        // Try to update existing record first
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_name} 
             SET views = views + 1 
             WHERE tab_key = %s AND product_id = %d AND date = %s",
            $tab_key,
            $product_id,
            $today
        ));
        
        // If no existing record, insert new one
        if ($updated === 0) {
            $wpdb->insert(
                $this->table_name,
                array(
                    'tab_key' => $tab_key,
                    'product_id' => $product_id,
                    'views' => 1,
                    'date' => $today
                ),
                array('%s', '%d', '%d', '%s')
            );
        }
        
        return true;
    }
    
    /**
     * Get popular tabs
     */
    public function get_popular_tabs($limit = 10, $days = 30) {
        global $wpdb;
        
        $date_from = date('Y-m-d', strtotime("-{$days} days"));
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT tab_key, SUM(views) as total_views, COUNT(DISTINCT product_id) as products_count
             FROM {$this->table_name}
             WHERE date >= %s
             GROUP BY tab_key
             ORDER BY total_views DESC
             LIMIT %d",
            $date_from,
            $limit
        ));
    }
    
    /**
     * Get tab performance for specific period
     */
    public function get_tab_performance($tab_key, $days = 30) {
        global $wpdb;
        
        $date_from = date('Y-m-d', strtotime("-{$days} days"));
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT date, SUM(views) as daily_views, COUNT(DISTINCT product_id) as products_viewed
             FROM {$this->table_name}
             WHERE tab_key = %s AND date >= %s
             GROUP BY date
             ORDER BY date ASC",
            $tab_key,
            $date_from
        ));
    }
    
    /**
     * Get product-specific tab statistics
     */
    public function get_product_tab_stats($product_id, $days = 30) {
        global $wpdb;
        
        $date_from = date('Y-m-d', strtotime("-{$days} days"));
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT tab_key, SUM(views) as total_views, 
                    MAX(date) as last_viewed,
                    COUNT(DISTINCT date) as active_days
             FROM {$this->table_name}
             WHERE product_id = %d AND date >= %s
             GROUP BY tab_key
             ORDER BY total_views DESC",
            $product_id,
            $date_from
        ));
    }
    
    /**
     * Get overall analytics summary
     */
    public function get_analytics_summary($days = 30) {
        global $wpdb;
        
        $date_from = date('Y-m-d', strtotime("-{$days} days"));
        
        // Total views
        $total_views = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(views) FROM {$this->table_name} WHERE date >= %s",
            $date_from
        ));
        
        // Unique products viewed
        $unique_products = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT product_id) FROM {$this->table_name} WHERE date >= %s",
            $date_from
        ));
        
        // Total active tabs
        $active_tabs = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT tab_key) FROM {$this->table_name} WHERE date >= %s",
            $date_from
        ));
        
        // Average views per day
        $avg_daily_views = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(daily_views) FROM (
                SELECT date, SUM(views) as daily_views 
                FROM {$this->table_name} 
                WHERE date >= %s 
                GROUP BY date
             ) as daily_stats",
            $date_from
        ));
        
        return array(
            'total_views' => intval($total_views),
            'unique_products' => intval($unique_products),
            'active_tabs' => intval($active_tabs),
            'avg_daily_views' => floatval($avg_daily_views),
            'period_days' => $days
        );
    }
    
    /**
     * Get top performing products
     */
    public function get_top_products($limit = 10, $days = 30) {
        global $wpdb;
        
        $date_from = date('Y-m-d', strtotime("-{$days} days"));
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT product_id, SUM(views) as total_views, 
                    COUNT(DISTINCT tab_key) as tabs_viewed,
                    COUNT(DISTINCT date) as active_days
             FROM {$this->table_name}
             WHERE date >= %s
             GROUP BY product_id
             ORDER BY total_views DESC
             LIMIT %d",
            $date_from,
            $limit
        ));
        
        // Enhance with product data
        foreach ($results as &$result) {
            $product = wc_get_product($result->product_id);
            if ($product) {
                $result->product_name = $product->get_name();
                $result->product_url = $product->get_permalink();
                $result->product_price = $product->get_price();
            } else {
                $result->product_name = __('Product Not Found', 'smart-product-tabs');
                $result->product_url = '';
                $result->product_price = 0;
            }
        }
        
        return $results;
    }
    
    /**
     * Get engagement metrics
     */
    public function get_engagement_metrics($days = 30) {
        global $wpdb;
        
        $date_from = date('Y-m-d', strtotime("-{$days} days"));
        
        // Tab engagement rate (products with tab views vs total products)
        $products_with_views = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT product_id) FROM {$this->table_name} WHERE date >= %s",
            $date_from
        ));
        
        $total_products = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'"
        );
        
        $engagement_rate = $total_products > 0 ? ($products_with_views / $total_products) * 100 : 0;
        
        // Average tabs per product view
        $avg_tabs_per_product = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(tabs_count) FROM (
                SELECT product_id, COUNT(DISTINCT tab_key) as tabs_count
                FROM {$this->table_name}
                WHERE date >= %s
                GROUP BY product_id
             ) as product_stats",
            $date_from
        ));
        
        // Most active day
        $most_active_day = $wpdb->get_row($wpdb->prepare(
            "SELECT date, SUM(views) as total_views
             FROM {$this->table_name}
             WHERE date >= %s
             GROUP BY date
             ORDER BY total_views DESC
             LIMIT 1",
            $date_from
        ));
        
        return array(
            'engagement_rate' => round($engagement_rate, 2),
            'avg_tabs_per_product' => round(floatval($avg_tabs_per_product), 2),
            'most_active_day' => $most_active_day ? $most_active_day->date : null,
            'most_active_day_views' => $most_active_day ? intval($most_active_day->total_views) : 0
        );
    }
    
    /**
     * Get daily analytics data for charts
     */
    public function get_daily_analytics($days = 30) {
        global $wpdb;
        
        $date_from = date('Y-m-d', strtotime("-{$days} days"));
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT date, 
                    SUM(views) as total_views,
                    COUNT(DISTINCT tab_key) as unique_tabs,
                    COUNT(DISTINCT product_id) as unique_products
             FROM {$this->table_name}
             WHERE date >= %s
             GROUP BY date
             ORDER BY date ASC",
            $date_from
        ));
    }
    
    /**
     * Export analytics data
     */
    public function export_analytics($format = 'csv', $days = 30) {
        global $wpdb;
        
        $date_from = date('Y-m-d', strtotime("-{$days} days"));
        
        $data = $wpdb->get_results($wpdb->prepare(
            "SELECT a.date, a.tab_key, a.product_id, a.views,
                    p.post_title as product_name
             FROM {$this->table_name} a
             LEFT JOIN {$wpdb->posts} p ON a.product_id = p.ID
             WHERE a.date >= %s
             ORDER BY a.date DESC, a.views DESC",
            $date_from
        ), ARRAY_A);
        
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
        $cutoff_date = date('Y-m-d', strtotime("-{$retention_days} days"));
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE date < %s",
            $cutoff_date
        ));
        
        // Log cleanup if any data was deleted
        if ($deleted > 0) {
            error_log("SPT Analytics: Cleaned up {$deleted} old records older than {$cutoff_date}");
        }
        
        return $deleted;
    }
    
    /**
     * Check if current request is from a bot
     */
    private function is_bot() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
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
     * Get tab comparison data
     */
    public function get_tab_comparison($tab_keys = array(), $days = 30) {
        if (empty($tab_keys)) {
            return array();
        }
        
        global $wpdb;
        $date_from = date('Y-m-d', strtotime("-{$days} days"));
        $placeholders = implode(',', array_fill(0, count($tab_keys), '%s'));
        
        $query_params = array_merge($tab_keys, array($date_from));
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT tab_key, 
                    SUM(views) as total_views,
                    COUNT(DISTINCT product_id) as unique_products,
                    COUNT(DISTINCT date) as active_days,
                    AVG(views) as avg_daily_views
             FROM {$this->table_name}
             WHERE tab_key IN ($placeholders) AND date >= %s
             GROUP BY tab_key
             ORDER BY total_views DESC",
            $query_params
        ));
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
        
        $type = sanitize_text_field($_POST['type'] ?? 'summary');
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
     * Get analytics data for admin dashboard
     */
    public function get_dashboard_data() {
        return array(
            'summary' => $this->get_analytics_summary(30),
            'popular_tabs' => $this->get_popular_tabs(5, 30),
            'top_products' => $this->get_top_products(5, 30),
            'engagement' => $this->get_engagement_metrics(30)
        );
    }
    
    /**
     * Generate analytics report
     */
    public function generate_report($days = 30) {
        $summary = $this->get_analytics_summary($days);
        $popular_tabs = $this->get_popular_tabs(10, $days);
        $top_products = $this->get_top_products(10, $days);
        $engagement = $this->get_engagement_metrics($days);
        $daily_data = $this->get_daily_analytics($days);
        
        return array(
            'period' => array(
                'days' => $days,
                'start_date' => date('Y-m-d', strtotime("-{$days} days")),
                'end_date' => date('Y-m-d')
            ),
            'summary' => $summary,
            'popular_tabs' => $popular_tabs,
            'top_products' => $top_products,
            'engagement' => $engagement,
            'daily_analytics' => $daily_data,
            'generated_at' => current_time('mysql')
        );
    }
    
    /**
     * Reset all analytics data
     */
    public function reset_analytics() {
        global $wpdb;
        
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
}
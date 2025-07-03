<?php
/**
 * Admin Interface for Smart Product Tabs
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SPT_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_spt_save_rule', array($this, 'ajax_save_rule'));
        add_action('wp_ajax_spt_delete_rule', array($this, 'ajax_delete_rule'));
        add_action('wp_ajax_spt_update_tab_order', array($this, 'ajax_update_tab_order'));
        add_action('wp_ajax_spt_toggle_tab', array($this, 'ajax_toggle_tab'));
        add_action('admin_notices', array($this, 'admin_notices'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Product Tabs', 'smart-product-tabs'),
            __('Product Tabs', 'smart-product-tabs'),
            'manage_woocommerce',
            'smart-product-tabs',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Main admin page
     */
    public function admin_page() {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'rules';
        ?>
        <div class="wrap">
            <h1><?php _e('Smart Product Tabs', 'smart-product-tabs'); ?></h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=smart-product-tabs&tab=rules" class="nav-tab <?php echo $active_tab === 'rules' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Tab Rules', 'smart-product-tabs'); ?>
                </a>
                <a href="?page=smart-product-tabs&tab=default-tabs" class="nav-tab <?php echo $active_tab === 'default-tabs' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Default Tabs', 'smart-product-tabs'); ?>
                </a>
                <a href="?page=smart-product-tabs&tab=templates" class="nav-tab <?php echo $active_tab === 'templates' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Templates', 'smart-product-tabs'); ?>
                </a>
                <a href="?page=smart-product-tabs&tab=analytics" class="nav-tab <?php echo $active_tab === 'analytics' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Analytics', 'smart-product-tabs'); ?>
                </a>
                <a href="?page=smart-product-tabs&tab=settings" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Settings', 'smart-product-tabs'); ?>
                </a>
            </nav>
            
            <div class="tab-content">
                <?php
                switch ($active_tab) {
                    case 'rules':
                        $this->render_rules_tab();
                        break;
                    case 'default-tabs':
                        $this->render_default_tabs();
                        break;
                    case 'templates':
                        $this->render_templates_tab();
                        break;
                    case 'analytics':
                        $this->render_analytics_tab();
                        break;
                    case 'settings':
                        $this->render_settings_tab();
                        break;
                    default:
                        $this->render_rules_tab();
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render rules tab
     */
    private function render_rules_tab() {
        $rules = $this->get_all_rules();
        ?>
        <div class="spt-rules-container">
            <div class="spt-rules-header">
                <h2><?php _e('Tab Rules', 'smart-product-tabs'); ?></h2>
                <button type="button" class="button-primary" id="add-new-rule">
                    <?php _e('Add New Rule', 'smart-product-tabs'); ?>
                </button>
            </div>
            
            <!-- Rules List -->
            <div class="spt-rules-list">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Rule Name', 'smart-product-tabs'); ?></th>
                            <th><?php _e('Tab Title', 'smart-product-tabs'); ?></th>
                            <th><?php _e('Conditions', 'smart-product-tabs'); ?></th>
                            <th><?php _e('Priority', 'smart-product-tabs'); ?></th>
                            <th><?php _e('Status', 'smart-product-tabs'); ?></th>
                            <th><?php _e('Actions', 'smart-product-tabs'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rules)): ?>
                        <tr>
                            <td colspan="6"><?php _e('No rules found. Create your first rule!', 'smart-product-tabs'); ?></td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($rules as $rule): ?>
                        <tr data-rule-id="<?php echo esc_attr($rule->id); ?>">
                            <td><strong><?php echo esc_html($rule->rule_name); ?></strong></td>
                            <td><?php echo esc_html($rule->tab_title); ?></td>
                            <td><?php echo $this->format_conditions($rule->conditions); ?></td>
                            <td><?php echo esc_html($rule->priority); ?></td>
                            <td>
                                <span class="status-<?php echo $rule->is_active ? 'active' : 'inactive'; ?>">
                                    <?php echo $rule->is_active ? __('Active', 'smart-product-tabs') : __('Inactive', 'smart-product-tabs'); ?>
                                </span>
                            </td>
                            <td>
                                <button type="button" class="button edit-rule" data-rule-id="<?php echo esc_attr($rule->id); ?>">
                                    <?php _e('Edit', 'smart-product-tabs'); ?>
                                </button>
                                <button type="button" class="button delete-rule" data-rule-id="<?php echo esc_attr($rule->id); ?>">
                                    <?php _e('Delete', 'smart-product-tabs'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Rule Form Modal -->
            <div id="rule-form-modal" class="spt-modal" style="display: none;">
                <div class="spt-modal-content">
                    <div class="spt-modal-header">
                        <h3 id="modal-title"><?php _e('Add New Rule', 'smart-product-tabs'); ?></h3>
                        <span class="spt-modal-close">&times;</span>
                    </div>
                    <div class="spt-modal-body">
                        <?php $this->render_rule_form(); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render rule form
     */
    private function render_rule_form() {
        ?>
        <form id="spt-rule-form" method="post">
            <input type="hidden" id="rule_id" name="rule_id" value="">
            
            <table class="form-table">
                <!-- Basic Info -->
                <tr>
                    <th scope="row">
                        <label for="rule_name"><?php _e('Rule Name', 'smart-product-tabs'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="rule_name" name="rule_name" class="regular-text" required>
                        <p class="description"><?php _e('Internal name for this rule', 'smart-product-tabs'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="tab_title"><?php _e('Tab Title', 'smart-product-tabs'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="tab_title" name="tab_title" class="regular-text" required>
                        <p class="description"><?php _e('Use {product_name} for dynamic titles', 'smart-product-tabs'); ?></p>
                    </td>
                </tr>
                
                <!-- Display Conditions -->
                <tr>
                    <th scope="row">
                        <label for="condition_type"><?php _e('Show Tab When', 'smart-product-tabs'); ?></label>
                    </th>
                    <td>
                        <select id="condition_type" name="condition_type">
                            <option value="all"><?php _e('All Products', 'smart-product-tabs'); ?></option>
                            <option value="category"><?php _e('Product Category', 'smart-product-tabs'); ?></option>
                            <option value="attribute"><?php _e('Product Attribute', 'smart-product-tabs'); ?></option>
                            <option value="price_range"><?php _e('Price Range', 'smart-product-tabs'); ?></option>
                            <option value="stock_status"><?php _e('Stock Status', 'smart-product-tabs'); ?></option>
                            <option value="custom_field"><?php _e('Custom Field', 'smart-product-tabs'); ?></option>
                        </select>
                        <div id="condition_details" style="margin-top:10px;">
                            <?php $this->render_condition_fields(); ?>
                        </div>
                    </td>
                </tr>
                
                <!-- User Role Targeting -->
                <tr>
                    <th scope="row">
                        <label for="user_role_condition"><?php _e('Show Tab For', 'smart-product-tabs'); ?></label>
                    </th>
                    <td>
                        <select id="user_role_condition" name="user_role_condition">
                            <option value="all"><?php _e('All Users', 'smart-product-tabs'); ?></option>
                            <option value="logged_in"><?php _e('Logged-in Users Only', 'smart-product-tabs'); ?></option>
                            <option value="specific_role"><?php _e('Specific Role(s)', 'smart-product-tabs'); ?></option>
                        </select>
                        <div id="role_selector" style="display:none; margin-top:10px;">
                            <?php foreach (wp_roles()->roles as $role => $details): ?>
                            <label style="display: block; margin: 5px 0;">
                                <input type="checkbox" name="user_roles[]" value="<?php echo esc_attr($role); ?>">
                                <?php echo esc_html($details['name']); ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </td>
                </tr>
                
                <!-- Content Type -->
                <tr>
                    <th scope="row"><?php _e('Content Type', 'smart-product-tabs'); ?></th>
                    <td>
                        <label>
                            <input type="radio" name="content_type" value="rich_editor" checked>
                            <?php _e('Rich Editor', 'smart-product-tabs'); ?>
                        </label>
                        <label style="margin-left: 20px;">
                            <input type="radio" name="content_type" value="plain_text">
                            <?php _e('Plain Text', 'smart-product-tabs'); ?>
                        </label>
                    </td>
                </tr>
                
                <!-- Content Editor -->
                <tr>
                    <th scope="row">
                        <label for="tab_content"><?php _e('Tab Content', 'smart-product-tabs'); ?></label>
                    </th>
                    <td>
                        <div id="rich_editor_container">
                            <?php 
                            wp_editor('', 'tab_content', array(
                                'media_buttons' => true,
                                'textarea_rows' => 10,
                                'teeny' => false
                            )); 
                            ?>
                        </div>
                        <div id="plain_text_container" style="display:none;">
                            <textarea id="tab_content_plain" name="tab_content_plain" rows="10" cols="50" class="large-text"></textarea>
                        </div>
                        <div class="merge-tags-help" style="margin-top: 10px;">
                            <strong><?php _e('Available merge tags:', 'smart-product-tabs'); ?></strong><br>
                            <code>{product_name}</code>, <code>{product_category}</code>, <code>{product_price}</code>, 
                            <code>{product_sku}</code>, <code>{custom_field_[key]}</code>
                        </div>
                    </td>
                </tr>
                
                <!-- Display Settings -->
                <tr>
                    <th scope="row"><?php _e('Display Settings', 'smart-product-tabs'); ?></th>
                    <td>
                        <label>
                            <?php _e('Priority:', 'smart-product-tabs'); ?>
                            <input type="number" name="priority" value="10" min="1" max="100" style="width: 80px;">
                        </label><br><br>
                        
                        <label>
                            <input type="checkbox" name="mobile_hidden">
                            <?php _e('Hide on mobile', 'smart-product-tabs'); ?>
                        </label><br><br>
                        
                        <label>
                            <input type="checkbox" name="is_active" checked>
                            <?php _e('Active', 'smart-product-tabs'); ?>
                        </label>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" class="button-primary" value="<?php _e('Save Rule', 'smart-product-tabs'); ?>">
                <button type="button" class="button" id="cancel-rule"><?php _e('Cancel', 'smart-product-tabs'); ?></button>
            </p>
        </form>
        <?php
    }
    
    /**
     * Render condition fields
     */
    private function render_condition_fields() {
        ?>
        <div class="condition-field" data-condition="category" style="display:none;">
            <select name="condition_category" multiple style="width: 300px;">
                <?php
                $categories = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));
                foreach ($categories as $category) {
                    echo '<option value="' . esc_attr($category->term_id) . '">' . esc_html($category->name) . '</option>';
                }
                ?>
            </select>
        </div>
        
        <div class="condition-field" data-condition="price_range" style="display:none;">
            <label><?php _e('Min Price:', 'smart-product-tabs'); ?></label>
            <input type="number" name="condition_price_min" step="0.01" style="width: 100px;">
            <label style="margin-left: 20px;"><?php _e('Max Price:', 'smart-product-tabs'); ?></label>
            <input type="number" name="condition_price_max" step="0.01" style="width: 100px;">
        </div>
        
        <div class="condition-field" data-condition="stock_status" style="display:none;">
            <select name="condition_stock_status">
                <option value="instock"><?php _e('In Stock', 'smart-product-tabs'); ?></option>
                <option value="outofstock"><?php _e('Out of Stock', 'smart-product-tabs'); ?></option>
                <option value="onbackorder"><?php _e('On Backorder', 'smart-product-tabs'); ?></option>
            </select>
        </div>
        <?php
    }
    
    /**
     * Render default tabs management
     */
    private function render_default_tabs() {
        $tab_settings = $this->get_tab_settings();
        ?>
        <div class="spt-default-tabs">
            <h2><?php _e('Manage Default Tabs', 'smart-product-tabs'); ?></h2>
            <p><?php _e('Reorder and configure WooCommerce default tabs and your custom rules.', 'smart-product-tabs'); ?></p>
            
            <div class="spt-sorting-container">
                <ul id="sortable-tabs" class="sortable-list">
                    <?php foreach ($tab_settings as $tab): ?>
                    <li class="tab-item" data-tab-key="<?php echo esc_attr($tab->tab_key); ?>">
                        <div class="tab-handle">
                            <span class="dashicons dashicons-menu"></span>
                        </div>
                        <div class="tab-info">
                            <strong><?php echo esc_html($tab->custom_title); ?></strong>
                            <span class="tab-type">(<?php echo esc_html($tab->tab_type); ?>)</span>
                        </div>
                        <div class="tab-controls">
                            <label>
                                <input type="checkbox" class="tab-enabled" <?php checked($tab->is_enabled); ?>>
                                <?php _e('Enabled', 'smart-product-tabs'); ?>
                            </label>
                            <label>
                                <input type="checkbox" class="tab-mobile-hidden" <?php checked($tab->mobile_hidden); ?>>
                                <?php _e('Hide on Mobile', 'smart-product-tabs'); ?>
                            </label>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                
                <button type="button" id="save-tab-order" class="button-primary">
                    <?php _e('Save Order', 'smart-product-tabs'); ?>
                </button>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render templates tab
     */
    private function render_templates_tab() {
        ?>
        <div class="spt-templates">
            <h2><?php _e('Templates', 'smart-product-tabs'); ?></h2>
            <p><?php _e('Import pre-built templates or export your current configuration.', 'smart-product-tabs'); ?></p>
            
            <div class="template-actions">
                <h3><?php _e('Built-in Templates', 'smart-product-tabs'); ?></h3>
                <!-- Template content will be handled by SPT_Templates class -->
            </div>
        </div>
        <?php
    }
    
    /**
     * Render analytics tab
     */
    private function render_analytics_tab() {
        ?>
        <div class="spt-analytics">
            <h2><?php _e('Analytics', 'smart-product-tabs'); ?></h2>
            <p><?php _e('Track tab performance and user engagement.', 'smart-product-tabs'); ?></p>
            
            <div class="analytics-content">
                <!-- Analytics content will be handled by SPT_Analytics class -->
            </div>
        </div>
        <?php
    }
    
    /**
     * Render settings tab
     */
    private function render_settings_tab() {
        ?>
        <div class="spt-settings">
            <h2><?php _e('Settings', 'smart-product-tabs'); ?></h2>
            <form method="post" action="options.php">
                <?php
                settings_fields('spt_settings');
                do_settings_sections('spt_settings');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Enable Analytics', 'smart-product-tabs'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="spt_enable_analytics" value="1" <?php checked(get_option('spt_enable_analytics', 1)); ?>>
                                <?php _e('Track tab views and performance', 'smart-product-tabs'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * AJAX: Save rule
     */
    public function ajax_save_rule() {
        check_ajax_referer('spt_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions', 'smart-product-tabs'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'spt_rules';
        
        $rule_id = isset($_POST['rule_id']) ? intval($_POST['rule_id']) : 0;
        $rule_data = array(
            'rule_name' => sanitize_text_field($_POST['rule_name']),
            'tab_title' => sanitize_text_field($_POST['tab_title']),
            'tab_content' => wp_kses_post($_POST['tab_content']),
            'content_type' => sanitize_text_field($_POST['content_type']),
            'conditions' => $this->prepare_conditions($_POST),
            'user_role_condition' => sanitize_text_field($_POST['user_role_condition']),
            'user_roles' => isset($_POST['user_roles']) ? json_encode(array_map('sanitize_text_field', $_POST['user_roles'])) : '',
            'priority' => intval($_POST['priority']),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'mobile_hidden' => isset($_POST['mobile_hidden']) ? 1 : 0
        );
        
        if ($rule_id > 0) {
            $result = $wpdb->update($table, $rule_data, array('id' => $rule_id));
        } else {
            $result = $wpdb->insert($table, $rule_data);
        }
        
        if ($result !== false) {
            wp_send_json_success(__('Rule saved successfully', 'smart-product-tabs'));
        } else {
            wp_send_json_error(__('Failed to save rule', 'smart-product-tabs'));
        }
    }
    
    /**
     * AJAX: Delete rule
     */
    public function ajax_delete_rule() {
        check_ajax_referer('spt_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions', 'smart-product-tabs'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'spt_rules';
        $rule_id = intval($_POST['rule_id']);
        
        $result = $wpdb->delete($table, array('id' => $rule_id));
        
        if ($result !== false) {
            wp_send_json_success(__('Rule deleted successfully', 'smart-product-tabs'));
        } else {
            wp_send_json_error(__('Failed to delete rule', 'smart-product-tabs'));
        }
    }
    
    /**
     * Get all rules
     */
    private function get_all_rules() {
        global $wpdb;
        $table = $wpdb->prefix . 'spt_rules';
        return $wpdb->get_results("SELECT * FROM $table ORDER BY priority ASC, created_at DESC");
    }
    
    /**
     * Get tab settings
     */
    private function get_tab_settings() {
        global $wpdb;
        $table = $wpdb->prefix . 'spt_tab_settings';
        return $wpdb->get_results("SELECT * FROM $table ORDER BY sort_order ASC");
    }
    
    /**
     * Format conditions for display
     */
    private function format_conditions($conditions_json) {
        $conditions = json_decode($conditions_json, true);
        if (empty($conditions)) {
            return __('All Products', 'smart-product-tabs');
        }
        
        $type = $conditions['type'] ?? 'all';
        switch ($type) {
            case 'category':
                return __('Category: ', 'smart-product-tabs') . ($conditions['value'] ?? '');
            case 'price_range':
                return __('Price Range: ', 'smart-product-tabs') . ($conditions['min'] ?? '0') . ' - ' . ($conditions['max'] ?? '∞');
            default:
                return ucfirst($type);
        }
    }
    
    /**
     * Prepare conditions from form data
     */
    private function prepare_conditions($post_data) {
        $condition_type = sanitize_text_field($post_data['condition_type']);
        
        $conditions = array('type' => $condition_type);
        
        switch ($condition_type) {
            case 'category':
                $conditions['value'] = isset($post_data['condition_category']) ? array_map('intval', $post_data['condition_category']) : array();
                break;
            case 'price_range':
                $conditions['min'] = isset($post_data['condition_price_min']) ? floatval($post_data['condition_price_min']) : 0;
                $conditions['max'] = isset($post_data['condition_price_max']) ? floatval($post_data['condition_price_max']) : 999999;
                break;
            case 'stock_status':
                $conditions['value'] = sanitize_text_field($post_data['condition_stock_status']);
                break;
        }
        
        return json_encode($conditions);
    }
    
    /**
     * Admin notices
     */
    public function admin_notices() {
        if (isset($_GET['spt_message'])) {
            $message = sanitize_text_field($_GET['spt_message']);
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
    }
}
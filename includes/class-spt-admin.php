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
        add_action('admin_init', array($this, 'handle_form_submissions'));
        add_action('wp_ajax_spt_update_tab_order', array($this, 'ajax_update_tab_order'));
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
     * Handle form submissions
     */
    public function handle_form_submissions() {
        // Handle rule save/update
        if (isset($_POST['spt_save_rule']) && wp_verify_nonce($_POST['spt_rule_nonce'], 'spt_save_rule')) {
            $this->save_rule();
        }
        
        // Handle rule delete
        if (isset($_GET['spt_action']) && $_GET['spt_action'] === 'delete_rule' && 
            isset($_GET['rule_id']) && wp_verify_nonce($_GET['_wpnonce'], 'spt_delete_rule_' . $_GET['rule_id'])) {
            $this->delete_rule($_GET['rule_id']);
        }
        
        // Handle default tabs settings
        if (isset($_POST['spt_save_default_tabs']) && wp_verify_nonce($_POST['spt_default_tabs_nonce'], 'spt_save_default_tabs')) {
            $this->save_default_tabs();
        }
    }
    
    /**
     * Main admin page router
     */
    public function admin_page() {
        $action = isset($_GET['spt_action']) ? sanitize_text_field($_GET['spt_action']) : '';
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'rules';
        
        // Handle specific actions
        switch ($action) {
            case 'add_rule':
                $this->render_add_rule_page();
                return;
            case 'edit_rule':
                $this->render_edit_rule_page();
                return;
            default:
                $this->render_main_page($active_tab);
        }
    }
    
    /**
     * Render main admin page
     */
    private function render_main_page($active_tab) {
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
     * Render add rule page
     */
    private function render_add_rule_page() {
        ?>
        <div class="wrap">
            <h1>
                <?php _e('Add New Rule', 'smart-product-tabs'); ?>
                <a href="<?php echo admin_url('admin.php?page=smart-product-tabs'); ?>" class="page-title-action">
                    <?php _e('Back to Rules', 'smart-product-tabs'); ?>
                </a>
            </h1>
            
            <?php $this->render_rule_form(); ?>
        </div>
        <?php
    }
    
    /**
     * Render edit rule page
     */
    private function render_edit_rule_page() {
        $rule_id = isset($_GET['rule_id']) ? intval($_GET['rule_id']) : 0;
        $rule = $this->get_rule_by_id($rule_id);
        
        if (!$rule) {
            wp_die(__('Rule not found.', 'smart-product-tabs'));
        }
        
        ?>
        <div class="wrap">
            <h1>
                <?php _e('Edit Rule', 'smart-product-tabs'); ?>
                <a href="<?php echo admin_url('admin.php?page=smart-product-tabs'); ?>" class="page-title-action">
                    <?php _e('Back to Rules', 'smart-product-tabs'); ?>
                </a>
            </h1>
            
            <?php $this->render_rule_form($rule); ?>
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
                <a href="<?php echo admin_url('admin.php?page=smart-product-tabs&spt_action=add_rule'); ?>" class="button-primary">
                    <?php _e('Add New Rule', 'smart-product-tabs'); ?>
                </a>
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
                                <a href="<?php echo admin_url('admin.php?page=smart-product-tabs&spt_action=edit_rule&rule_id=' . $rule->id); ?>" class="button">
                                    <?php _e('Edit', 'smart-product-tabs'); ?>
                                </a>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=smart-product-tabs&spt_action=delete_rule&rule_id=' . $rule->id), 'spt_delete_rule_' . $rule->id); ?>" 
                                   class="button" 
                                   onclick="return confirm('<?php _e('Are you sure you want to delete this rule?', 'smart-product-tabs'); ?>')">
                                    <?php _e('Delete', 'smart-product-tabs'); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render rule form
     */
    private function render_rule_form($rule = null) {
        $is_edit = $rule !== null;
        ?>
        <form method="post" action="">
            <?php wp_nonce_field('spt_save_rule', 'spt_rule_nonce'); ?>
            <input type="hidden" name="spt_save_rule" value="1">
            <?php if ($is_edit): ?>
                <input type="hidden" name="rule_id" value="<?php echo esc_attr($rule->id); ?>">
            <?php endif; ?>
            
            <table class="form-table">
                <!-- Basic Info -->
                <tr>
                    <th scope="row">
                        <label for="rule_name"><?php _e('Rule Name', 'smart-product-tabs'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="rule_name" name="rule_name" class="regular-text" 
                               value="<?php echo $is_edit ? esc_attr($rule->rule_name) : ''; ?>" required>
                        <p class="description"><?php _e('Internal name for this rule', 'smart-product-tabs'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="tab_title"><?php _e('Tab Title', 'smart-product-tabs'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="tab_title" name="tab_title" class="regular-text" 
                               value="<?php echo $is_edit ? esc_attr($rule->tab_title) : ''; ?>" required>
                        <p class="description"><?php _e('Use {product_name} for dynamic titles', 'smart-product-tabs'); ?></p>
                    </td>
                </tr>
                
                <!-- Display Conditions -->
                <tr>
                    <th scope="row">
                        <label for="condition_type"><?php _e('Show Tab When', 'smart-product-tabs'); ?></label>
                    </th>
                    <td>
                        <?php 
                        $conditions = $is_edit ? json_decode($rule->conditions, true) : array();
                        $condition_type = isset($conditions['type']) ? $conditions['type'] : 'all';
                        ?>
                        <select name="condition_type" id="condition_type">
                            <option value="all" <?php selected($condition_type, 'all'); ?>><?php _e('All Products', 'smart-product-tabs'); ?></option>
                            <option value="category" <?php selected($condition_type, 'category'); ?>><?php _e('Product Category', 'smart-product-tabs'); ?></option>
                            <option value="attribute" <?php selected($condition_type, 'attribute'); ?>><?php _e('Product Attribute', 'smart-product-tabs'); ?></option>
                            <option value="price_range" <?php selected($condition_type, 'price_range'); ?>><?php _e('Price Range', 'smart-product-tabs'); ?></option>
                            <option value="stock_status" <?php selected($condition_type, 'stock_status'); ?>><?php _e('Stock Status', 'smart-product-tabs'); ?></option>
                            <option value="custom_field" <?php selected($condition_type, 'custom_field'); ?>><?php _e('Custom Field', 'smart-product-tabs'); ?></option>
                        </select>
                        <div id="condition_details" style="margin-top:10px;">
                            <?php $this->render_condition_fields($conditions); ?>
                        </div>
                    </td>
                </tr>
                
                <!-- User Role Targeting -->
                <tr>
                    <th scope="row">
                        <label for="user_role_condition"><?php _e('Show Tab For', 'smart-product-tabs'); ?></label>
                    </th>
                    <td>
                        <?php $user_role_condition = $is_edit ? $rule->user_role_condition : 'all'; ?>
                        <select id="user_role_condition" name="user_role_condition">
                            <option value="all" <?php selected($user_role_condition, 'all'); ?>><?php _e('All Users', 'smart-product-tabs'); ?></option>
                            <option value="logged_in" <?php selected($user_role_condition, 'logged_in'); ?>><?php _e('Logged-in Users Only', 'smart-product-tabs'); ?></option>
                            <option value="specific_role" <?php selected($user_role_condition, 'specific_role'); ?>><?php _e('Specific Role(s)', 'smart-product-tabs'); ?></option>
                        </select>
                        <div id="role_selector" style="<?php echo $user_role_condition !== 'specific_role' ? 'display:none;' : ''; ?> margin-top:10px;">
                            <?php 
                            $selected_roles = $is_edit ? json_decode($rule->user_roles, true) : array();
                            if (!is_array($selected_roles)) $selected_roles = array();
                            
                            foreach (wp_roles()->roles as $role => $details): 
                            ?>
                            <label style="display: block; margin: 5px 0;">
                                <input type="checkbox" name="user_roles[]" value="<?php echo esc_attr($role); ?>" 
                                       <?php checked(in_array($role, $selected_roles)); ?>>
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
                        <?php $content_type = $is_edit ? $rule->content_type : 'rich_editor'; ?>
                        <label>
                            <input type="radio" name="content_type" value="rich_editor" <?php checked($content_type, 'rich_editor'); ?>>
                            <?php _e('Rich Editor', 'smart-product-tabs'); ?>
                        </label>
                        <label style="margin-left: 20px;">
                            <input type="radio" name="content_type" value="plain_text" <?php checked($content_type, 'plain_text'); ?>>
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
                        <div id="rich_editor_container" style="<?php echo $content_type === 'plain_text' ? 'display:none;' : ''; ?>">
                            <?php 
                            $content = $is_edit ? $rule->tab_content : '';
                            wp_editor($content, 'tab_content', array(
                                'media_buttons' => true,
                                'textarea_rows' => 10,
                                'teeny' => false
                            )); 
                            ?>
                        </div>
                        <div id="plain_text_container" style="<?php echo $content_type === 'rich_editor' ? 'display:none;' : ''; ?>">
                            <textarea id="tab_content_plain" name="tab_content_plain" rows="10" cols="50" class="large-text"><?php echo $is_edit && $content_type === 'plain_text' ? esc_textarea($rule->tab_content) : ''; ?></textarea>
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
                            <input type="number" name="priority" value="<?php echo $is_edit ? esc_attr($rule->priority) : '10'; ?>" 
                                   min="1" max="100" style="width: 80px;">
                        </label><br><br>
                        
                        <label>
                            <input type="checkbox" name="mobile_hidden" value="1" <?php echo $is_edit ? checked($rule->mobile_hidden, 1) : ''; ?>>
                            <?php _e('Hide on mobile', 'smart-product-tabs'); ?>
                        </label><br><br>
                        
                        <label>
                            <input type="checkbox" name="is_active" value="1" <?php echo $is_edit ? checked($rule->is_active, 1) : 'checked'; ?>>
                            <?php _e('Active', 'smart-product-tabs'); ?>
                        </label>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" class="button-primary" value="<?php echo $is_edit ? __('Update Rule', 'smart-product-tabs') : __('Save Rule', 'smart-product-tabs'); ?>">
                <a href="<?php echo admin_url('admin.php?page=smart-product-tabs'); ?>" class="button">
                    <?php _e('Cancel', 'smart-product-tabs'); ?>
                </a>
            </p>
        </form>
        
        <script>
        jQuery(document).ready(function($) {
            // Handle condition type change
            $('#condition_type').change(function() {
                var conditionType = $(this).val();
                $('.condition-field').hide();
                $('.condition-field[data-condition="' + conditionType + '"]').show();
            }).trigger('change');
            
            // Handle user role condition change
            $('#user_role_condition').change(function() {
                if ($(this).val() === 'specific_role') {
                    $('#role_selector').show();
                } else {
                    $('#role_selector').hide();
                }
            });
            
            // Handle content type change
            $('input[name="content_type"]').change(function() {
                if ($(this).val() === 'rich_editor') {
                    $('#rich_editor_container').show();
                    $('#plain_text_container').hide();
                } else {
                    $('#rich_editor_container').hide();
                    $('#plain_text_container').show();
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render condition fields
     */
    private function render_condition_fields($conditions = array()) {
        ?>
        <div class="condition-field" data-condition="category" style="display:none;">
            <select name="condition_category[]" multiple style="width: 300px;">
                <?php
                $categories = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));
                $selected_categories = isset($conditions['value']) ? (array) $conditions['value'] : array();
                foreach ($categories as $category) {
                    echo '<option value="' . esc_attr($category->term_id) . '" ' . 
                         (in_array($category->term_id, $selected_categories) ? 'selected' : '') . '>' . 
                         esc_html($category->name) . '</option>';
                }
                ?>
            </select>
        </div>
        
        <div class="condition-field" data-condition="price_range" style="display:none;">
            <label><?php _e('Min Price:', 'smart-product-tabs'); ?></label>
            <input type="number" name="condition_price_min" step="0.01" style="width: 100px;" 
                   value="<?php echo isset($conditions['min']) ? esc_attr($conditions['min']) : ''; ?>">
            <label style="margin-left: 20px;"><?php _e('Max Price:', 'smart-product-tabs'); ?></label>
            <input type="number" name="condition_price_max" step="0.01" style="width: 100px;" 
                   value="<?php echo isset($conditions['max']) ? esc_attr($conditions['max']) : ''; ?>">
        </div>
        
        <div class="condition-field" data-condition="stock_status" style="display:none;">
            <select name="condition_stock_status">
                <option value="instock" <?php echo isset($conditions['value']) ? selected($conditions['value'], 'instock') : ''; ?>><?php _e('In Stock', 'smart-product-tabs'); ?></option>
                <option value="outofstock" <?php echo isset($conditions['value']) ? selected($conditions['value'], 'outofstock') : ''; ?>><?php _e('Out of Stock', 'smart-product-tabs'); ?></option>
                <option value="onbackorder" <?php echo isset($conditions['value']) ? selected($conditions['value'], 'onbackorder') : ''; ?>><?php _e('On Backorder', 'smart-product-tabs'); ?></option>
            </select>
        </div>
        
        <div class="condition-field" data-condition="custom_field" style="display:none;">
            <label><?php _e('Field Key:', 'smart-product-tabs'); ?></label>
            <input type="text" name="condition_custom_key" style="width: 150px;" 
                   value="<?php echo isset($conditions['key']) ? esc_attr($conditions['key']) : ''; ?>">
            <label style="margin-left: 20px;"><?php _e('Field Value:', 'smart-product-tabs'); ?></label>
            <input type="text" name="condition_custom_value" style="width: 150px;" 
                   value="<?php echo isset($conditions['value']) ? esc_attr($conditions['value']) : ''; ?>">
        </div>
        
        <div class="condition-field" data-condition="attribute" style="display:none;">
            <label><?php _e('Attribute:', 'smart-product-tabs'); ?></label>
            <input type="text" name="condition_attribute_name" style="width: 150px;" 
                   value="<?php echo isset($conditions['attribute']) ? esc_attr($conditions['attribute']) : ''; ?>">
            <label style="margin-left: 20px;"><?php _e('Value:', 'smart-product-tabs'); ?></label>
            <input type="text" name="condition_attribute_value" style="width: 150px;" 
                   value="<?php echo isset($conditions['value']) ? esc_attr($conditions['value']) : ''; ?>">
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
            
            <form method="post" action="">
                <?php wp_nonce_field('spt_save_default_tabs', 'spt_default_tabs_nonce'); ?>
                <input type="hidden" name="spt_save_default_tabs" value="1">
                
                <div class="spt-sorting-container">
                    <ul id="sortable-tabs" class="sortable-list">
                        <?php foreach ($tab_settings as $tab): ?>
                        <li class="tab-item" data-tab-key="<?php echo esc_attr($tab->tab_key); ?>">
                            <input type="hidden" name="tab_order[<?php echo esc_attr($tab->tab_key); ?>][sort_order]" value="<?php echo esc_attr($tab->sort_order); ?>" class="sort-order-input">
                            <div class="tab-handle">
                                <span class="dashicons dashicons-menu"></span>
                            </div>
                            <div class="tab-info">
                                <strong><?php echo esc_html($tab->custom_title ?: $tab->tab_key); ?></strong>
                                <span class="tab-type">(<?php echo esc_html($tab->tab_type); ?>)</span>
                                <?php if ($tab->tab_type === 'default'): ?>
                                    <input type="text" name="tab_order[<?php echo esc_attr($tab->tab_key); ?>][custom_title]" 
                                           value="<?php echo esc_attr($tab->custom_title); ?>" 
                                           placeholder="Custom title" style="margin-left: 10px; width: 200px;">
                                <?php endif; ?>
                            </div>
                            <div class="tab-controls">
                                <label>
                                    <input type="checkbox" name="tab_order[<?php echo esc_attr($tab->tab_key); ?>][is_enabled]" 
                                           value="1" <?php checked($tab->is_enabled); ?>>
                                    <?php _e('Enabled', 'smart-product-tabs'); ?>
                                </label>
                                <label>
                                    <input type="checkbox" name="tab_order[<?php echo esc_attr($tab->tab_key); ?>][mobile_hidden]" 
                                           value="1" <?php checked($tab->mobile_hidden); ?>>
                                    <?php _e('Hide on Mobile', 'smart-product-tabs'); ?>
                                </label>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <p class="submit">
                        <input type="submit" class="button-primary" value="<?php _e('Save Changes', 'smart-product-tabs'); ?>">
                    </p>
                </div>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#sortable-tabs').sortable({
                handle: '.tab-handle',
                placeholder: 'tab-placeholder',
                axis: 'y',
                opacity: 0.8,
                update: function(event, ui) {
                    $('#sortable-tabs .tab-item').each(function(index) {
                        $(this).find('.sort-order-input').val(index + 1);
                    });
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Save rule
     */
    private function save_rule() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions', 'smart-product-tabs'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'spt_rules';
        
        $rule_id = isset($_POST['rule_id']) ? intval($_POST['rule_id']) : 0;
        $content_type = sanitize_text_field($_POST['content_type']);
        
        // Get content based on type
        if ($content_type === 'rich_editor') {
            $content = wp_kses_post($_POST['tab_content']);
        } else {
            $content = sanitize_textarea_field($_POST['tab_content_plain']);
        }
        
        $rule_data = array(
            'rule_name' => sanitize_text_field($_POST['rule_name']),
            'tab_title' => sanitize_text_field($_POST['tab_title']),
            'tab_content' => $content,
            'content_type' => $content_type,
            'conditions' => $this->prepare_conditions($_POST),
            'user_role_condition' => sanitize_text_field($_POST['user_role_condition']),
            'user_roles' => isset($_POST['user_roles']) ? json_encode(array_map('sanitize_text_field', $_POST['user_roles'])) : '',
            'priority' => intval($_POST['priority']),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'mobile_hidden' => isset($_POST['mobile_hidden']) ? 1 : 0
        );
        
        if ($rule_id > 0) {
            $result = $wpdb->update($table, $rule_data, array('id' => $rule_id));
            $message = __('Rule updated successfully', 'smart-product-tabs');
        } else {
            $result = $wpdb->insert($table, $rule_data);
            $message = __('Rule created successfully', 'smart-product-tabs');
        }
        
        if ($result !== false) {
            wp_redirect(admin_url('admin.php?page=smart-product-tabs&spt_message=' . urlencode($message)));
            exit;
        } else {
            wp_redirect(admin_url('admin.php?page=smart-product-tabs&spt_error=' . urlencode(__('Failed to save rule', 'smart-product-tabs'))));
            exit;
        }
    }
    
    /**
     * Delete rule
     */
    private function delete_rule($rule_id) {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions', 'smart-product-tabs'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'spt_rules';
        $rule_id = intval($rule_id);
        
        $result = $wpdb->delete($table, array('id' => $rule_id));
        
        if ($result !== false) {
            wp_redirect(admin_url('admin.php?page=smart-product-tabs&spt_message=' . urlencode(__('Rule deleted successfully', 'smart-product-tabs'))));
        } else {
            wp_redirect(admin_url('admin.php?page=smart-product-tabs&spt_error=' . urlencode(__('Failed to delete rule', 'smart-product-tabs'))));
        }
        exit;
    }
    
    /**
     * Save default tabs settings
     */
    private function save_default_tabs() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions', 'smart-product-tabs'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'spt_tab_settings';
        
        if (isset($_POST['tab_order']) && is_array($_POST['tab_order'])) {
            foreach ($_POST['tab_order'] as $tab_key => $tab_data) {
                $tab_key = sanitize_text_field($tab_key);
                
                $update_data = array(
                    'sort_order' => intval($tab_data['sort_order']),
                    'is_enabled' => isset($tab_data['is_enabled']) ? 1 : 0,
                    'mobile_hidden' => isset($tab_data['mobile_hidden']) ? 1 : 0
                );
                
                // Add custom title for default tabs
                if (isset($tab_data['custom_title'])) {
                    $update_data['custom_title'] = sanitize_text_field($tab_data['custom_title']);
                }
                
                $wpdb->update(
                    $table,
                    $update_data,
                    array('tab_key' => $tab_key)
                );
            }
        }
        
        wp_redirect(admin_url('admin.php?page=smart-product-tabs&tab=default-tabs&spt_message=' . urlencode(__('Tab settings saved successfully', 'smart-product-tabs'))));
        exit;
    }
    
    /**
     * AJAX: Update tab order (keep this for drag-drop functionality)
     */
    public function ajax_update_tab_order() {
        check_ajax_referer('spt_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'spt_tab_settings';
        $tab_order = $_POST['tab_order'] ?? array();
        
        foreach ($tab_order as $tab_data) {
            $wpdb->update(
                $table,
                array('sort_order' => intval($tab_data['sort_order'])),
                array('tab_key' => sanitize_text_field($tab_data['tab_key']))
            );
        }
        
        wp_send_json_success(__('Tab order updated successfully', 'smart-product-tabs'));
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
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'spt_settings')) {
            update_option('spt_enable_analytics', isset($_POST['spt_enable_analytics']) ? 1 : 0);
            echo '<div class="notice notice-success"><p>' . __('Settings saved.', 'smart-product-tabs') . '</p></div>';
        }
        ?>
        <div class="spt-settings">
            <h2><?php _e('Settings', 'smart-product-tabs'); ?></h2>
            <form method="post" action="">
                <?php wp_nonce_field('spt_settings'); ?>
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
     * Get rule by ID
     */
    private function get_rule_by_id($rule_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'spt_rules';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $rule_id));
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
                if (isset($conditions['value']) && is_array($conditions['value'])) {
                    $category_names = array();
                    foreach ($conditions['value'] as $cat_id) {
                        $term = get_term($cat_id, 'product_cat');
                        if ($term && !is_wp_error($term)) {
                            $category_names[] = $term->name;
                        }
                    }
                    return __('Category: ', 'smart-product-tabs') . implode(', ', $category_names);
                }
                return __('Category', 'smart-product-tabs');
            case 'price_range':
                $min = $conditions['min'] ?? '0';
                $max = $conditions['max'] ?? '∞';
                return __('Price Range: ', 'smart-product-tabs') . $min . ' - ' . $max;
            case 'stock_status':
                return __('Stock Status: ', 'smart-product-tabs') . ucfirst($conditions['value'] ?? '');
            case 'custom_field':
                return __('Custom Field: ', 'smart-product-tabs') . ($conditions['key'] ?? '');
            case 'attribute':
                return __('Attribute: ', 'smart-product-tabs') . ($conditions['attribute'] ?? '');
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
            case 'custom_field':
                $conditions['key'] = sanitize_text_field($post_data['condition_custom_key']);
                $conditions['value'] = sanitize_text_field($post_data['condition_custom_value']);
                break;
            case 'attribute':
                $conditions['attribute'] = sanitize_text_field($post_data['condition_attribute_name']);
                $conditions['value'] = sanitize_text_field($post_data['condition_attribute_value']);
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
        
        if (isset($_GET['spt_error'])) {
            $error = sanitize_text_field($_GET['spt_error']);
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error) . '</p></div>';
        }
    }
}
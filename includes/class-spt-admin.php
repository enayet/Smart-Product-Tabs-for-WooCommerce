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
        
        add_action('wp_ajax_spt_reset_analytics', array($this, 'ajax_reset_analytics'));
        add_action('admin_init', array($this, 'handle_analytics_export'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            esc_html__('Product Tabs', 'smart-product-tabs-for-woocommerce'),
            esc_html__('Product Tabs', 'smart-product-tabs-for-woocommerce'),
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

        // Handle analytics settings (moved from removed settings tab)
        if (isset($_POST['save_analytics_settings']) && wp_verify_nonce($_POST['_wpnonce'], 'spt_analytics_settings')) {
            $this->save_analytics_settings();
        }        
        
        // Handle template download
        if (isset($_GET['spt_action']) && $_GET['spt_action'] === 'download_template' && 
            isset($_GET['file']) && wp_verify_nonce($_GET['_wpnonce'], 'spt_download_template')) {
            $this->download_template_file($_GET['file']);
        }        
        
        
    }
    
    
    /**
     * Save analytics settings
     */
    private function save_analytics_settings() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions', 'smart-product-tabs-for-woocommerce'));
        }

        update_option('spt_enable_analytics', isset($_POST['spt_enable_analytics']) ? 1 : 0);
        update_option('spt_analytics_retention_days', intval($_POST['spt_analytics_retention_days']));

        wp_redirect(admin_url('admin.php?page=smart-product-tabs&tab=analytics&spt_message=' . 
                    urlencode(esc_html__('Analytics settings saved successfully', 'smart-product-tabs-for-woocommerce'))));
        exit;
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
            <h1><?php esc_html_e('Smart Product Tabs', 'smart-product-tabs-for-woocommerce'); ?></h1>

            <nav class="nav-tab-wrapper">
                <a href="?page=smart-product-tabs&tab=rules" class="nav-tab <?php echo $active_tab === 'rules' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Tab Rules', 'smart-product-tabs-for-woocommerce'); ?>
                </a>
                <a href="?page=smart-product-tabs&tab=default-tabs" class="nav-tab <?php echo $active_tab === 'default-tabs' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Default Tabs', 'smart-product-tabs-for-woocommerce'); ?>
                </a>
                <a href="?page=smart-product-tabs&tab=templates" class="nav-tab <?php echo $active_tab === 'templates' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Templates', 'smart-product-tabs-for-woocommerce'); ?>
                </a>
                <a href="?page=smart-product-tabs&tab=analytics" class="nav-tab <?php echo $active_tab === 'analytics' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Analytics', 'smart-product-tabs-for-woocommerce'); ?>
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
                <?php esc_html_e('Add New Rule', 'smart-product-tabs-for-woocommerce'); ?>
                <a href="<?php echo admin_url('admin.php?page=smart-product-tabs'); ?>" class="page-title-action">
                    <?php esc_html_e('Back to Rules', 'smart-product-tabs-for-woocommerce'); ?>
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
            wp_die(esc_html__('Rule not found.', 'smart-product-tabs-for-woocommerce'));
        }
        
        ?>
        <div class="wrap">
            <h1>
                <?php esc_html_e('Edit Rule', 'smart-product-tabs-for-woocommerce'); ?>
                <a href="<?php echo admin_url('admin.php?page=smart-product-tabs'); ?>" class="page-title-action">
                    <?php esc_html_e('Back to Rules', 'smart-product-tabs-for-woocommerce'); ?>
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
                <h2><?php esc_html_e('Tab Rules', 'smart-product-tabs-for-woocommerce'); ?></h2>
                <a href="<?php echo admin_url('admin.php?page=smart-product-tabs&spt_action=add_rule'); ?>" class="button-primary">
                    <?php esc_html_e('Add New Rule', 'smart-product-tabs-for-woocommerce'); ?>
                </a>
            </div>
            
            <!-- Rules List -->
            <div class="spt-rules-list">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Rule Name', 'smart-product-tabs-for-woocommerce'); ?></th>
                            <th><?php esc_html_e('Tab Title', 'smart-product-tabs-for-woocommerce'); ?></th>
                            <th><?php esc_html_e('Conditions', 'smart-product-tabs-for-woocommerce'); ?></th>
                            <th><?php esc_html_e('Priority', 'smart-product-tabs-for-woocommerce'); ?></th>
                            <th><?php esc_html_e('Status', 'smart-product-tabs-for-woocommerce'); ?></th>
                            <th><?php esc_html_e('Actions', 'smart-product-tabs-for-woocommerce'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rules)): ?>
                        <tr>
                            <td colspan="6"><?php esc_html_e('No rules found. Create your first rule!', 'smart-product-tabs-for-woocommerce'); ?></td>
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
                                    <?php echo $rule->is_active ? esc_html__('Active', 'smart-product-tabs-for-woocommerce') : esc_html__('Inactive', 'smart-product-tabs-for-woocommerce'); ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=smart-product-tabs&spt_action=edit_rule&rule_id=' . $rule->id); ?>" class="button">
                                    <?php esc_html_e('Edit', 'smart-product-tabs-for-woocommerce'); ?>
                                </a>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=smart-product-tabs&spt_action=delete_rule&rule_id=' . $rule->id), 'spt_delete_rule_' . $rule->id); ?>" 
                                   class="button" 
                                   onclick="return confirm('<?php esc_html_e('Are you sure you want to delete this rule?', 'smart-product-tabs-for-woocommerce'); ?>')">
                                    <?php esc_html_e('Delete', 'smart-product-tabs-for-woocommerce'); ?>
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
                        <label for="rule_name"><?php esc_html_e('Rule Name', 'smart-product-tabs-for-woocommerce'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text" id="rule_name" name="rule_name" class="regular-text" 
                               value="<?php echo $is_edit ? esc_attr($rule->rule_name) : ''; ?>" required>
                        <p class="description"><?php esc_html_e('Internal name for this rule (only visible in admin)', 'smart-product-tabs-for-woocommerce'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="tab_title"><?php esc_html_e('Tab Title', 'smart-product-tabs-for-woocommerce'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text" id="tab_title" name="tab_title" class="regular-text" 
                               value="<?php echo $is_edit ? esc_attr($rule->tab_title) : ''; ?>" required>
                        <p class="description">
                            <?php esc_html_e('Title displayed on the tab. Use merge tags like {product_name} for dynamic content.', 'smart-product-tabs-for-woocommerce'); ?><br>
                            <strong><?php esc_html_e('Available merge tags:', 'smart-product-tabs-for-woocommerce'); ?></strong>
                            <code>{product_name}</code>, <code>{product_category}</code>, <code>{product_price}</code>
                        </p>
                    </td>
                </tr>
                
                <!-- Display Conditions -->
                <tr class="condition-row">
                    <th scope="row">
                        <label for="condition_type"><?php esc_html_e('Show Tab When', 'smart-product-tabs-for-woocommerce'); ?></label>
                    </th>
                    <td>
                        <?php 
                        $conditions = $is_edit ? json_decode($rule->conditions, true) : array();
                        $condition_type = isset($conditions['type']) ? $conditions['type'] : 'all';
                        ?>
                        <select id="condition_type" name="condition_type" class="enhanced-select">
                            <option value="all" <?php selected($condition_type, 'all'); ?>><?php esc_html_e('All Products', 'smart-product-tabs-for-woocommerce'); ?></option>
                            <optgroup label="<?php esc_html_e('Product Properties', 'smart-product-tabs-for-woocommerce'); ?>">
                                <option value="category" <?php selected($condition_type, 'category'); ?>><?php esc_html_e('Product Category', 'smart-product-tabs-for-woocommerce'); ?></option>
                                <option value="tags" <?php selected($condition_type, 'tags'); ?>><?php esc_html_e('Product Tags', 'smart-product-tabs-for-woocommerce'); ?></option>
                                <option value="product_type" <?php selected($condition_type, 'product_type'); ?>><?php esc_html_e('Product Type', 'smart-product-tabs-for-woocommerce'); ?></option>
                            </optgroup>
                            <optgroup label="<?php esc_html_e('Pricing & Stock', 'smart-product-tabs-for-woocommerce'); ?>">
                                <option value="price_range" <?php selected($condition_type, 'price_range'); ?>><?php esc_html_e('Price Range', 'smart-product-tabs-for-woocommerce'); ?></option>
                                <option value="stock_status" <?php selected($condition_type, 'stock_status'); ?>><?php esc_html_e('Stock Status', 'smart-product-tabs-for-woocommerce'); ?></option>
                                <option value="sale" <?php selected($condition_type, 'sale'); ?>><?php esc_html_e('On Sale Status', 'smart-product-tabs-for-woocommerce'); ?></option>
                            </optgroup>
                            <optgroup label="<?php esc_html_e('Special Properties', 'smart-product-tabs-for-woocommerce'); ?>">
                                <option value="featured" <?php selected($condition_type, 'featured'); ?>><?php esc_html_e('Featured Product', 'smart-product-tabs-for-woocommerce'); ?></option>
                                <option value="custom_field" <?php selected($condition_type, 'custom_field'); ?>><?php esc_html_e('Custom Field', 'smart-product-tabs-for-woocommerce'); ?></option>
                            </optgroup>
                        </select>
                        <p class="description"><?php esc_html_e('Choose when this tab should appear on product pages', 'smart-product-tabs-for-woocommerce'); ?></p>
                        
                        <div id="condition_details" style="margin-top:15px;">
                            <?php $this->render_condition_fields($conditions); ?>
                        </div>
                    </td>
                </tr>
                
                <!-- User Role Targeting -->
                <tr>
                    <th scope="row">
                        <label for="user_role_condition"><?php esc_html_e('Show Tab For', 'smart-product-tabs-for-woocommerce'); ?></label>
                    </th>
                    <td>
                        <?php $user_role_condition = $is_edit ? $rule->user_role_condition : 'all'; ?>
                        <select id="user_role_condition" name="user_role_condition">
                            <option value="all" <?php selected($user_role_condition, 'all'); ?>><?php esc_html_e('All Users (including guests)', 'smart-product-tabs-for-woocommerce'); ?></option>
                            <option value="logged_in" <?php selected($user_role_condition, 'logged_in'); ?>><?php esc_html_e('Logged-in Users Only', 'smart-product-tabs-for-woocommerce'); ?></option>
                            <option value="specific_role" <?php selected($user_role_condition, 'specific_role'); ?>><?php esc_html_e('Specific User Role(s)', 'smart-product-tabs-for-woocommerce'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('Control which users can see this tab', 'smart-product-tabs-for-woocommerce'); ?></p>
                        
                        <div id="role_selector" style="<?php echo $user_role_condition !== 'specific_role' ? 'display:none;' : ''; ?> margin-top:10px;" class="role-selector">
                            <p><strong><?php esc_html_e('Select allowed roles:', 'smart-product-tabs-for-woocommerce'); ?></strong></p>
                            <div class="role-grid">
                                <?php 
                                $selected_roles = $is_edit ? json_decode($rule->user_roles, true) : array();
                                if (!is_array($selected_roles)) $selected_roles = array();
                                
                                foreach (wp_roles()->roles as $role => $details): 
                                ?>
                                <label class="role-option">
                                    <input type="checkbox" name="user_roles[]" value="<?php echo esc_attr($role); ?>" 
                                           <?php checked(in_array($role, $selected_roles)); ?>>
                                    <span class="role-name"><?php echo esc_html($details['name']); ?></span>
                                    <span class="role-key">(<?php echo esc_html($role); ?>)</span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </td>
                </tr>
                
                <!-- Content Type -->
                <tr>
                    <th scope="row"><?php esc_html_e('Content Type', 'smart-product-tabs-for-woocommerce'); ?></th>
                    <td>
                        <?php $content_type = $is_edit ? $rule->content_type : 'rich_editor'; ?>
                        <fieldset class="content-type-selector">
                            <legend class="screen-reader-text"><?php esc_html_e('Content Type', 'smart-product-tabs-for-woocommerce'); ?></legend>
                            <label class="content-type-option">
                                <input type="radio" name="content_type" value="rich_editor" <?php checked($content_type, 'rich_editor'); ?>>
                                <span class="option-title"><?php esc_html_e('Rich Editor', 'smart-product-tabs-for-woocommerce'); ?></span>
                                <span class="option-description"><?php esc_html_e('WYSIWYG editor with formatting options', 'smart-product-tabs-for-woocommerce'); ?></span>
                            </label>
                            <label class="content-type-option">
                                <input type="radio" name="content_type" value="plain_text" <?php checked($content_type, 'plain_text'); ?>>
                                <span class="option-title"><?php esc_html_e('Plain Text', 'smart-product-tabs-for-woocommerce'); ?></span>
                                <span class="option-description"><?php esc_html_e('Simple text area without formatting', 'smart-product-tabs-for-woocommerce'); ?></span>
                            </label>
                        </fieldset>
                    </td>
                </tr>
                
                <!-- Content Editor -->
                <tr>
                    <th scope="row">
                        <label for="tab_content"><?php esc_html_e('Tab Content', 'smart-product-tabs-for-woocommerce'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <div id="rich_editor_container" style="<?php echo $content_type === 'plain_text' ? 'display:none;' : ''; ?>">
                            <?php 
                            $content = $is_edit ? $rule->tab_content : '';
                            wp_editor($content, 'tab_content', array(
                                'media_buttons' => true,
                                'textarea_rows' => 12,
                                'teeny' => false,
                                'tinymce' => array(
                                    'toolbar1' => 'bold,italic,underline,strikethrough,|,bullist,numlist,|,link,unlink,|,spellchecker,fullscreen',
                                    'toolbar2' => 'formatselect,|,forecolor,backcolor,|,alignleft,aligncenter,alignright,alignjustify,|,outdent,indent,|,undo,redo'
                                )
                            )); 
                            ?>
                        </div>
                        <div id="plain_text_container" style="<?php echo $content_type === 'rich_editor' ? 'display:none;' : ''; ?>">
                            <textarea id="tab_content_plain" name="tab_content_plain" rows="12" cols="50" class="large-text" placeholder="<?php esc_html_e('Enter your tab content here...', 'smart-product-tabs-for-woocommerce'); ?>"><?php echo $is_edit && $content_type === 'plain_text' ? esc_textarea($rule->tab_content) : ''; ?></textarea>
                        </div>
                        
                        <div class="merge-tags-help">
                            <strong><?php esc_html_e('Available merge tags:', 'smart-product-tabs-for-woocommerce'); ?></strong><br>
                            <div class="merge-tags-grid">
                                <div class="tag-group">
                                    <strong><?php esc_html_e('Product Info:', 'smart-product-tabs-for-woocommerce'); ?></strong>
                                    <code>{product_name}</code>
                                    <code>{product_category}</code>
                                    <code>{product_price}</code>
                                    <code>{product_sku}</code>
                                </div>
                                <div class="tag-group">
                                    <strong><?php esc_html_e('Product Details:', 'smart-product-tabs-for-woocommerce'); ?></strong>
                                    <code>{product_weight}</code>
                                    <code>{product_dimensions}</code>
                                    <code>{product_stock_status}</code>
                                    <code>{product_stock_quantity}</code>
                                </div>
                                <div class="tag-group">
                                    <strong><?php esc_html_e('Custom Fields:', 'smart-product-tabs-for-woocommerce'); ?></strong>
                                    <code>{custom_field_[key]}</code>
                                    <small><?php esc_html_e('Replace [key] with actual field name', 'smart-product-tabs-for-woocommerce'); ?></small>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                
                <!-- Display Settings -->
                <tr>
                    <th scope="row"><?php esc_html_e('Display Settings', 'smart-product-tabs-for-woocommerce'); ?></th>
                    <td>
                        <div class="display-settings-grid">
                            <div class="setting-group">
                                <label for="priority">
                                    <strong><?php esc_html_e('Priority:', 'smart-product-tabs-for-woocommerce'); ?></strong>
                                    <input type="number" id="priority" name="priority" value="<?php echo $is_edit ? esc_attr($rule->priority) : '10'; ?>" min="1" max="100" style="width: 80px;">
                                </label>
                                <p class="description"><?php esc_html_e('Lower numbers appear first (1-100)', 'smart-product-tabs-for-woocommerce'); ?></p>
                            </div>
                            
                            <div class="setting-group">
                                <label class="setting-checkbox">
                                    <input type="checkbox" name="mobile_hidden" id="mobile_hidden" value="1" <?php echo $is_edit ? checked($rule->mobile_hidden, 1) : ''; ?>>
                                    <span class="checkmark"></span>
                                    <strong><?php esc_html_e('Hide on mobile devices', 'smart-product-tabs-for-woocommerce'); ?></strong>
                                </label>
                                <p class="description"><?php esc_html_e('Tab will not appear on mobile/tablet devices', 'smart-product-tabs-for-woocommerce'); ?></p>
                            </div>
                            
                            <div class="setting-group">
                                <label class="setting-checkbox">
                                    <input type="checkbox" name="is_active" id="is_active" value="1" <?php echo $is_edit ? checked($rule->is_active, 1) : 'checked'; ?>>
                                    <span class="checkmark"></span>
                                    <strong><?php esc_html_e('Active', 'smart-product-tabs-for-woocommerce'); ?></strong>
                                </label>
                                <p class="description"><?php esc_html_e('Uncheck to temporarily disable this tab', 'smart-product-tabs-for-woocommerce'); ?></p>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
            
            <div class="form-actions">
                <input type="submit" class="button-primary" value="<?php echo $is_edit ? esc_html__('Update Rule', 'smart-product-tabs-for-woocommerce') : esc_html__('Save Rule', 'smart-product-tabs-for-woocommerce'); ?>">
                <a href="<?php echo admin_url('admin.php?page=smart-product-tabs'); ?>" class="button">
                    <?php esc_html_e('Cancel', 'smart-product-tabs-for-woocommerce'); ?>
                </a>
            </div>
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
     * Render condition fields without attribute options
     */
    private function render_condition_fields($conditions = array()) {
        ?>
        <div class="condition-field" data-condition="category" style="display:none;">
            <label for="condition_category"><?php esc_html_e('Select Categories:', 'smart-product-tabs-for-woocommerce'); ?></label>
            <select name="condition_category[]" id="condition_category" multiple style="width: 300px; height: 150px;">
                <?php echo $this->get_hierarchical_category_options($conditions); ?>
            </select>
            <p class="description"><?php esc_html_e('Hold Ctrl/Cmd to select multiple categories', 'smart-product-tabs-for-woocommerce'); ?></p>
        </div>
        
        <div class="condition-field" data-condition="price_range" style="display:none;">
            <label><?php esc_html_e('Min Price:', 'smart-product-tabs-for-woocommerce'); ?></label>
            <input type="number" name="condition_price_min" step="0.01" style="width: 100px;" 
                   value="<?php echo isset($conditions['min']) ? esc_attr($conditions['min']) : ''; ?>" 
                   placeholder="0.00">
            <label style="margin-left: 20px;"><?php esc_html_e('Max Price:', 'smart-product-tabs-for-woocommerce'); ?></label>
            <input type="number" name="condition_price_max" step="0.01" style="width: 100px;" 
                   value="<?php echo isset($conditions['max']) ? esc_attr($conditions['max']) : ''; ?>" 
                   placeholder="999999">
            <p class="description"><?php esc_html_e('Leave empty for no limit', 'smart-product-tabs-for-woocommerce'); ?></p>
        </div>
        
        <div class="condition-field" data-condition="stock_status" style="display:none;">
            <select name="condition_stock_status">
                <?php
                $stock_value = isset($conditions['value']) ? $conditions['value'] : 'instock';
                if (is_array($stock_value)) {
                    $stock_value = 'instock';
                }
                ?>
                <option value="instock" <?php selected($stock_value, 'instock'); ?>><?php esc_html_e('In Stock', 'smart-product-tabs-for-woocommerce'); ?></option>
                <option value="outofstock" <?php selected($stock_value, 'outofstock'); ?>><?php esc_html_e('Out of Stock', 'smart-product-tabs-for-woocommerce'); ?></option>
                <option value="onbackorder" <?php selected($stock_value, 'onbackorder'); ?>><?php esc_html_e('On Backorder', 'smart-product-tabs-for-woocommerce'); ?></option>
            </select>
        </div>
        
        <div class="condition-field" data-condition="custom_field" style="display:none;">
            <label for="condition_custom_field_key"><?php esc_html_e('Field Key:', 'smart-product-tabs-for-woocommerce'); ?></label>
            <input type="text" name="condition_custom_field_key" id="condition_custom_field_key" style="width: 200px;" 
                   value="<?php echo isset($conditions['key']) ? esc_attr($conditions['key']) : ''; ?>" 
                   placeholder="_custom_field_key">
            <br><br>
            <label for="condition_custom_field_value"><?php esc_html_e('Field Value:', 'smart-product-tabs-for-woocommerce'); ?></label>
            <input type="text" name="condition_custom_field_value" id="condition_custom_field_value" style="width: 200px;" 
                   value="<?php echo isset($conditions['value']) ? esc_attr($conditions['value']) : ''; ?>" 
                   placeholder="Expected value">
            <br><br>
            <label for="condition_custom_field_operator"><?php esc_html_e('Operator:', 'smart-product-tabs-for-woocommerce'); ?></label>
            <select name="condition_custom_field_operator" id="condition_custom_field_operator">
                <option value="equals" <?php echo isset($conditions['operator']) ? selected($conditions['operator'], 'equals', false) : ''; ?>><?php esc_html_e('Equals', 'smart-product-tabs-for-woocommerce'); ?></option>
                <option value="not_equals" <?php echo isset($conditions['operator']) ? selected($conditions['operator'], 'not_equals', false) : ''; ?>><?php esc_html_e('Not Equals', 'smart-product-tabs-for-woocommerce'); ?></option>
                <option value="contains" <?php echo isset($conditions['operator']) ? selected($conditions['operator'], 'contains', false) : ''; ?>><?php esc_html_e('Contains', 'smart-product-tabs-for-woocommerce'); ?></option>
                <option value="not_empty" <?php echo isset($conditions['operator']) ? selected($conditions['operator'], 'not_empty', false) : ''; ?>><?php esc_html_e('Not Empty', 'smart-product-tabs-for-woocommerce'); ?></option>
                <option value="empty" <?php echo isset($conditions['operator']) ? selected($conditions['operator'], 'empty', false) : ''; ?>><?php esc_html_e('Empty', 'smart-product-tabs-for-woocommerce'); ?></option>
            </select>
        </div>
        
        <div class="condition-field" data-condition="product_type" style="display:none;">
            <select name="condition_product_type[]" multiple style="width: 300px;">
                <?php
                $product_types = wc_get_product_types();
                $selected_types = isset($conditions['value']) ? (array) $conditions['value'] : array();
                foreach ($product_types as $key => $label) {
                    $selected = in_array($key, $selected_types) ? 'selected' : '';
                    echo '<option value="' . esc_attr($key) . '" ' . $selected . '>' . esc_html($label) . '</option>';
                }
                ?>
            </select>
            <p class="description"><?php esc_html_e('Hold Ctrl/Cmd to select multiple types', 'smart-product-tabs-for-woocommerce'); ?></p>
        </div>
        
        <div class="condition-field" data-condition="tags" style="display:none;">
            <select name="condition_tags[]" multiple style="width: 300px; height: 120px;">
                <?php
                $tags = get_terms(array(
                    'taxonomy' => 'product_tag',
                    'hide_empty' => false,
                    'orderby' => 'name',
                    'order' => 'ASC'
                ));
                $selected_tags = isset($conditions['value']) ? (array) $conditions['value'] : array();
                foreach ($tags as $tag) {
                    $selected = in_array($tag->term_id, $selected_tags) ? 'selected' : '';
                    echo '<option value="' . esc_attr($tag->term_id) . '" ' . $selected . '>' . esc_html($tag->name) . '</option>';
                }
                ?>
            </select>
            <p class="description"><?php esc_html_e('Hold Ctrl/Cmd to select multiple tags', 'smart-product-tabs-for-woocommerce'); ?></p>
        </div>
        
        <div class="condition-field" data-condition="featured" style="display:none;">
            <select name="condition_featured">
                <?php
                $featured_value = isset($conditions['value']) ? $conditions['value'] : '';
                if (is_array($featured_value)) {
                    $featured_value = '';
                }
                ?>
                <option value="1" <?php selected($featured_value, '1'); ?>><?php esc_html_e('Is Featured', 'smart-product-tabs-for-woocommerce'); ?></option>
                <option value="0" <?php selected($featured_value, '0'); ?>><?php esc_html_e('Is Not Featured', 'smart-product-tabs-for-woocommerce'); ?></option>
            </select>
        </div>
        
        <div class="condition-field" data-condition="sale" style="display:none;">
            <select name="condition_sale">
                <?php
                $sale_value = isset($conditions['value']) ? $conditions['value'] : '';
                if (is_array($sale_value)) {
                    $sale_value = '';
                }
                ?>
                <option value="1" <?php selected($sale_value, '1'); ?>><?php esc_html_e('On Sale', 'smart-product-tabs-for-woocommerce'); ?></option>
                <option value="0" <?php selected($sale_value, '0'); ?>><?php esc_html_e('Not On Sale', 'smart-product-tabs-for-woocommerce'); ?></option>
            </select>
        </div>
        <?php
    }
    
    /**
     * Get hierarchical category options with proper indentation
     */
    private function get_hierarchical_category_options($conditions = array(), $parent = 0, $level = 0) {
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'parent' => $parent,
            'orderby' => 'name',
            'order' => 'ASC'
        ));
        
        $output = '';
        $selected_categories = isset($conditions['value']) ? (array) $conditions['value'] : array();
        
        foreach ($categories as $category) {
            // Create indentation based on level
            $indent = str_repeat('&nbsp;&nbsp;&nbsp;', $level);
            $prefix = $level > 0 ? '├─ ' : '';
            $selected = in_array($category->term_id, $selected_categories) ? 'selected' : '';
            
            $output .= sprintf(
                '<option value="%d" %s>%s%s%s (%d)</option>',
                esc_attr($category->term_id),
                $selected,
                $indent,
                $prefix,
                esc_html($category->name),
                $category->count
            );
            
            // Get child categories recursively
            $output .= $this->get_hierarchical_category_options($conditions, $category->term_id, $level + 1);
        }
        
        return $output;
    }
    
    /**
     * Render default tabs management
     */
    private function render_default_tabs() {
        $tab_settings = $this->get_tab_settings();
        ?>
        <div class="spt-default-tabs">
            <h2><?php esc_html_e('Manage Default Tabs', 'smart-product-tabs-for-woocommerce'); ?></h2>
            <p><?php esc_html_e('Reorder and configure WooCommerce default tabs and your custom rules.', 'smart-product-tabs-for-woocommerce'); ?></p>
            
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
                                    <?php esc_html_e('Enabled', 'smart-product-tabs-for-woocommerce'); ?>
                                </label>
                                <label>
                                    <input type="checkbox" name="tab_order[<?php echo esc_attr($tab->tab_key); ?>][mobile_hidden]" 
                                           value="1" <?php checked($tab->mobile_hidden); ?>>
                                    <?php esc_html_e('Hide on Mobile', 'smart-product-tabs-for-woocommerce'); ?>
                                </label>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <p class="submit">
                        <input type="submit" class="button-primary" value="<?php esc_html_e('Save Changes', 'smart-product-tabs-for-woocommerce'); ?>">
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
        tolerance: 'pointer',
        stop: function(event, ui) {
            // Update sort order for all items after drag stops
            $('#sortable-tabs .tab-item').each(function(index) {
                $(this).find('.sort-order-input').val(index + 1);
            });
            
            // Clean up the opacity and any other inline styles left by jQuery UI
            $('#sortable-tabs .tab-item').each(function() {
                $(this).css('opacity', '').removeAttr('style');
            });
        }
    });
    
    // Set initial sort order values
    $('#sortable-tabs .tab-item').each(function(index) {
        $(this).find('.sort-order-input').val(index + 1);
    });
    
    // Additional cleanup before form submission to prevent any jump
    $('.button-primary').on('click', function(e) {
        // Clean up any remaining inline styles before form submission
        $('#sortable-tabs .tab-item').each(function() {
            $(this).removeAttr('style');
        });
    });
});
</script>
       
<style>

</style>
        <?php
    }
    
    /**
     * Save rule
     */
    private function save_rule() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions', 'smart-product-tabs-for-woocommerce'));
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
            $message = esc_html__('Rule updated successfully', 'smart-product-tabs-for-woocommerce');
        } else {
            $result = $wpdb->insert($table, $rule_data);
            $message = esc_html__('Rule created successfully', 'smart-product-tabs-for-woocommerce');
        }
        
        if ($result !== false) {
            wp_redirect(admin_url('admin.php?page=smart-product-tabs&spt_message=' . urlencode($message)));
            exit;
        } else {
            wp_redirect(admin_url('admin.php?page=smart-product-tabs&spt_error=' . urlencode(esc_html__('Failed to save rule', 'smart-product-tabs-for-woocommerce'))));
            exit;
        }
    }
    
    /**
     * Enhanced prepare_conditions method to handle all condition types (without attributes)
     */
    private function prepare_conditions($post_data) {
        $condition_type = sanitize_text_field($post_data['condition_type']);
        
        $conditions = array('type' => $condition_type);
        
        switch ($condition_type) {
            case 'category':
                $conditions['value'] = isset($post_data['condition_category']) ? array_map('intval', $post_data['condition_category']) : array();
                $conditions['operator'] = 'in'; // Default operator
                break;
                
            case 'price_range':
                $conditions['min'] = isset($post_data['condition_price_min']) ? floatval($post_data['condition_price_min']) : 0;
                $conditions['max'] = isset($post_data['condition_price_max']) ? floatval($post_data['condition_price_max']) : 999999;
                $conditions['operator'] = 'between';
                break;
                
            case 'stock_status':
                $conditions['value'] = sanitize_text_field($post_data['condition_stock_status'] ?? 'instock');
                $conditions['operator'] = 'equals';
                break;
                
            case 'custom_field':
                $conditions['key'] = sanitize_text_field($post_data['condition_custom_field_key'] ?? '');
                $conditions['value'] = sanitize_text_field($post_data['condition_custom_field_value'] ?? '');
                $conditions['operator'] = sanitize_text_field($post_data['condition_custom_field_operator'] ?? 'equals');
                break;
                
            case 'product_type':
                $conditions['value'] = isset($post_data['condition_product_type']) ? array_map('sanitize_text_field', $post_data['condition_product_type']) : array();
                $conditions['operator'] = 'in';
                break;
                
            case 'tags':
                $conditions['value'] = isset($post_data['condition_tags']) ? array_map('intval', $post_data['condition_tags']) : array();
                $conditions['operator'] = 'in';
                break;
                
            case 'featured':
                $conditions['value'] = intval($post_data['condition_featured'] ?? 1);
                $conditions['operator'] = 'equals';
                break;
                
            case 'sale':
                $conditions['value'] = intval($post_data['condition_sale'] ?? 1);
                $conditions['operator'] = 'equals';
                break;
        }
        
        return json_encode($conditions);
    }
    
    /**
     * Enhanced format_conditions method for better display (without attributes)
     */
    private function format_conditions($conditions_json) {
        $conditions = json_decode($conditions_json, true);
        
        if (empty($conditions) || $conditions['type'] === 'all') {
            return '<span class="condition-all">' . esc_html__('All Products', 'smart-product-tabs-for-woocommerce') . '</span>';
        }
        
        $type = $conditions['type'];
        
        switch ($type) {
            case 'category':
                if (empty($conditions['value'])) {
                    return '<span class="condition-invalid">' . esc_html__('No categories selected', 'smart-product-tabs-for-woocommerce') . '</span>';
                }
                
                $category_names = array();
                foreach ($conditions['value'] as $cat_id) {
                    $term = get_term($cat_id);
                    if ($term && !is_wp_error($term)) {
                        $category_names[] = $term->name;
                    }
                }
                
                return sprintf(
                    '<span class="condition-category">%s: %s</span>',
                    esc_html__('Category', 'smart-product-tabs-for-woocommerce'),
                    !empty($category_names) ? implode(', ', $category_names) : esc_html__('Invalid categories', 'smart-product-tabs-for-woocommerce')
                );
                
            case 'price_range':
                $currency = get_woocommerce_currency_symbol();
                return sprintf(
                    '<span class="condition-price">%s: %s%s - %s%s</span>',
                    esc_html__('Price Range', 'smart-product-tabs-for-woocommerce'),
                    $currency,
                    number_format($conditions['min'] ?? 0, 2),
                    $currency,
                    number_format($conditions['max'] ?? 999999, 2)
                );
                
            case 'stock_status':
                $status_labels = array(
                    'instock' => esc_html__('In Stock', 'smart-product-tabs-for-woocommerce'),
                    'outofstock' => esc_html__('Out of Stock', 'smart-product-tabs-for-woocommerce'),
                    'onbackorder' => esc_html__('On Backorder', 'smart-product-tabs-for-woocommerce')
                );
                $status = $conditions['value'] ?? 'instock';
                return sprintf(
                    '<span class="condition-stock">%s: %s</span>',
                    esc_html__('Stock Status', 'smart-product-tabs-for-woocommerce'),
                    $status_labels[$status] ?? $status
                );
                
            case 'custom_field':
                $field_key = $conditions['key'] ?? '';
                $field_value = $conditions['value'] ?? '';
                $field_operator = $conditions['operator'] ?? 'equals';
                
                // Handle array values properly
                if (is_array($field_value)) {
                    $field_value = implode(', ', $field_value);
                }
                
                return sprintf(
                    '<span class="condition-custom">%s: %s %s %s</span>',
                    esc_html__('Custom Field', 'smart-product-tabs-for-woocommerce'),
                    esc_html($field_key),
                    esc_html($field_operator),
                    esc_html($field_value)
                );
                
            case 'product_type':
                $types = $conditions['value'] ?? array();
                $type_labels = wc_get_product_types();
                $type_names = array();
                foreach ($types as $type) {
                    $type_names[] = $type_labels[$type] ?? $type;
                }
                return sprintf(
                    '<span class="condition-type">%s: %s</span>',
                    esc_html__('Product Type', 'smart-product-tabs-for-woocommerce'),
                    implode(', ', $type_names)
                );
                
            case 'tags':
                if (empty($conditions['value'])) {
                    return '<span class="condition-invalid">' . esc_html__('No tags selected', 'smart-product-tabs-for-woocommerce') . '</span>';
                }
                
                $tag_names = array();
                foreach ($conditions['value'] as $tag_id) {
                    $term = get_term($tag_id);
                    if ($term && !is_wp_error($term)) {
                        $tag_names[] = $term->name;
                    }
                }
                
                return sprintf(
                    '<span class="condition-tags">%s: %s</span>',
                    esc_html__('Tags', 'smart-product-tabs-for-woocommerce'),
                    !empty($tag_names) ? implode(', ', $tag_names) : esc_html__('Invalid tags', 'smart-product-tabs-for-woocommerce')
                );
                
            case 'featured':
                $value = $conditions['value'] ?? 1;
                return sprintf(
                    '<span class="condition-featured">%s</span>',
                    $value ? esc_html__('Featured Products', 'smart-product-tabs-for-woocommerce') : esc_html__('Non-Featured Products', 'smart-product-tabs-for-woocommerce')
                );
                
            case 'sale':
                $value = $conditions['value'] ?? 1;
                return sprintf(
                    '<span class="condition-sale">%s</span>',
                    $value ? esc_html__('On Sale Products', 'smart-product-tabs-for-woocommerce') : esc_html__('Regular Price Products', 'smart-product-tabs-for-woocommerce')
                );
                
            default:
                return '<span class="condition-unknown">' . sprintf(esc_html__('Unknown: %s', 'smart-product-tabs-for-woocommerce'), ucfirst($type)) . '</span>';
        }
    }
    
    /**
     * Delete rule
     */
    private function delete_rule($rule_id) {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions', 'smart-product-tabs-for-woocommerce'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'spt_rules';
        $rule_id = intval($rule_id);
        
        $result = $wpdb->delete($table, array('id' => $rule_id));
        
        if ($result !== false) {
            wp_redirect(admin_url('admin.php?page=smart-product-tabs&spt_message=' . urlencode(esc_html__('Rule deleted successfully', 'smart-product-tabs-for-woocommerce'))));
        } else {
            wp_redirect(admin_url('admin.php?page=smart-product-tabs&spt_error=' . urlencode(esc_html__('Failed to delete rule', 'smart-product-tabs-for-woocommerce'))));
        }
        exit;
    }
    
    /**
     * Save default tabs settings
     */
    private function save_default_tabs() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions', 'smart-product-tabs-for-woocommerce'));
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
        
        wp_redirect(admin_url('admin.php?page=smart-product-tabs&tab=default-tabs&spt_message=' . urlencode(esc_html__('Tab settings saved successfully', 'smart-product-tabs-for-woocommerce'))));
        exit;
    }
    
    /**
     * AJAX: Update tab order
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
        
        wp_send_json_success(esc_html__('Tab order updated successfully', 'smart-product-tabs-for-woocommerce'));
    }
    
    /**
     * COMPLETE Templates Tab Implementation
     * Replace the render_templates_tab() method in class-spt-admin.php
     */
    private function render_templates_tab() {
        // Get templates instance
        $templates = new SPT_Templates();
        $builtin_templates = $templates->get_builtin_templates();
        ?>
        <div class="spt-templates">
            <div class="templates-header">
                <h2><?php esc_html_e('Templates', 'smart-product-tabs-for-woocommerce'); ?></h2>
                <p><?php esc_html_e('Import pre-built templates or export your current configuration.', 'smart-product-tabs-for-woocommerce'); ?></p>
            </div>
            
            
        <!-- Enhanced Template Grid - Three Column Layout -->
        <div class="template-grid">
            <?php
            // Get your templates data (replace this with your actual template fetching logic)
            $templates = new SPT_Templates();
            $templates = $templates->get_builtin_templates();
            
            foreach ($templates as $template_key => $template_data) :
                $template_name = $template_data['name'] ?? 'Unnamed Template';
                $description = $template_data['description'] ?? 'No description available';
                $version = $template_data['version'] ?? '1.0';
                $tabs_count = isset($template_data['rules']) ? count($template_data['rules']) : 0;
                $icon = $template_data['icon'] ?? '📦'; // Default icon
            ?>
            
            <div class="template-card">
                <div class="template-preview">
                    <div class="template-icon"><?php echo esc_html($icon); ?></div>
                </div>
                
                <div class="template-info">
                    <h4><?php echo esc_html($template_name); ?></h4>
                    <p><?php echo esc_html($description); ?></p>
                    
                    <div class="template-meta">
                        <span><?php printf(esc_html__('Version %s', 'smart-product-tabs-for-woocommerce'), esc_html($version)); ?></span>
                        <span class="tabs-count">
                            <?php printf(_n('%d Tab', '%d Tabs', $tabs_count, 'smart-product-tabs-for-woocommerce'), $tabs_count); ?>
                        </span>
                    </div>
                    
                    <div class="template-actions">
                        <button class="button button-secondary template-preview-btn" 
                                data-template-key="<?php echo esc_attr($template_key); ?>" 
                                data-template-name="<?php echo esc_attr($template_name); ?>">
                            <?php esc_html_e('Details', 'smart-product-tabs-for-woocommerce'); ?>
                        </button>
                        <button class="button button-primary template-install" 
                                data-template-key="<?php echo esc_attr($template_key); ?>" 
                                data-template-name="<?php echo esc_attr($template_name); ?>">
                            <?php esc_html_e('Install', 'smart-product-tabs-for-woocommerce'); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <?php endforeach; ?>
        </div>            
            

            <!-- Built-in Templates -->
<div class="templates-section">


    <!-- Import/Export Section -->
    <div class="import-export-section">
        <div class="import-section">
            <h4><?php esc_html_e('Import Template', 'smart-product-tabs-for-woocommerce'); ?></h4>

            <!-- File Upload Form - FIXED VERSION -->
            <form class="template-upload-form" method="post" enctype="multipart/form-data">
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Upload File', 'smart-product-tabs-for-woocommerce'); ?></th>
                        <td>
                            <div class="file-upload-area">
                                <input type="file" name="template_file" accept=".json" id="template-file-input" required>
                                <p><?php esc_html_e('Select a .json template file to upload', 'smart-product-tabs-for-woocommerce'); ?></p>
                                <small><?php esc_html_e('Maximum file size: 5MB', 'smart-product-tabs-for-woocommerce'); ?></small>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Import Options', 'smart-product-tabs-for-woocommerce'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="replace_existing" value="1">
                                <?php esc_html_e('Replace existing rules with same names', 'smart-product-tabs-for-woocommerce'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('If unchecked, rules with duplicate names will be skipped', 'smart-product-tabs-for-woocommerce'); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" class="button-primary" value="<?php esc_html_e('Import Template', 'smart-product-tabs-for-woocommerce'); ?>">
                    <span class="spinner" id="upload-spinner" style="display: none;"></span>
                </p>

                <!-- Progress indicator -->
                <div class="upload-progress" style="display: none;">
                    <div class="upload-progress-bar"></div>
                </div>

                <!-- Status messages container -->
                <div class="import-status" style="margin-top: 15px;"></div>
            </form>

            <!-- Import Instructions -->
            <div class="import-instructions" style="margin-top: 20px; padding: 15px; background: #f0f8ff; border-left: 4px solid #0073aa;">
                <h5><?php esc_html_e('Import Instructions:', 'smart-product-tabs-for-woocommerce'); ?></h5>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li><?php esc_html_e('Only JSON files exported from Smart Product Tabs are supported', 'smart-product-tabs-for-woocommerce'); ?></li>
                    <li><?php esc_html_e('Maximum file size is 5MB', 'smart-product-tabs-for-woocommerce'); ?></li>
                    <li><?php esc_html_e('Backup your existing rules before importing', 'smart-product-tabs-for-woocommerce'); ?></li>
                    <li><?php esc_html_e('Rules with duplicate names will be skipped unless you check "Replace existing"', 'smart-product-tabs-for-woocommerce'); ?></li>
                </ul>
            </div>
        </div>

        <div class="export-section">
            <h4><?php esc_html_e('Export Current Rules', 'smart-product-tabs-for-woocommerce'); ?></h4>
            <p><?php esc_html_e('Download your current rules as a JSON file for backup or transfer to another site.', 'smart-product-tabs-for-woocommerce'); ?></p>

            <form id="export-form">
                <p class="submit">
                    <button type="button" class="button-primary" id="export-rules">
                        <?php esc_html_e('Download Export File', 'smart-product-tabs-for-woocommerce'); ?>
                    </button>
                </p>
            </form>
        </div>
    </div>

    <!-- Enhanced Template Preview Modal -->
        <!-- Enhanced Template Preview Modal -->
        <div id="template-preview-modal" class="spt-modal" style="display: none;">
            <div class="spt-modal-content">
                <!-- Fixed Header -->
                <div class="spt-modal-header">
                    <h3 id="preview-template-title"><?php esc_html_e('Template Preview', 'smart-product-tabs-for-woocommerce'); ?></h3>
                    <button type="button" class="spt-modal-close" aria-label="<?php esc_html_e('Close', 'smart-product-tabs-for-woocommerce'); ?>">&times;</button>
                </div>

                <!-- Scrollable Body -->
                <div class="spt-modal-body">
                    <div id="preview-template-content">
                        <!-- Template preview content loaded via AJAX -->
                    </div>
                </div>

                <!-- Fixed Footer -->
                <div class="spt-modal-footer">
                    <button type="button" class="button button-primary" id="install-from-preview">
                        <?php esc_html_e('Install This Template', 'smart-product-tabs-for-woocommerce'); ?>
                    </button>
                    <button type="button" class="button" id="close-preview">
                        <?php esc_html_e('Close', 'smart-product-tabs-for-woocommerce'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Hidden nonce for AJAX calls -->
        <input type="hidden" id="spt-ajax-nonce" value="<?php echo wp_create_nonce('spt_ajax_nonce'); ?>" />
    </div>
</div>

<script type="text/javascript">

jQuery(document).ready(function($) {
    // Use fallback for nonce if spt_admin_ajax is not defined
    var sptNonce = (typeof spt_admin_ajax !== 'undefined' && spt_admin_ajax.nonce) ? 
                   spt_admin_ajax.nonce : 
                   '<?php echo wp_create_nonce('spt_ajax_nonce'); ?>';
    
    var ajaxUrl = (typeof spt_admin_ajax !== 'undefined' && spt_admin_ajax.ajax_url) ? 
                  spt_admin_ajax.ajax_url : 
                  '<?php echo admin_url('admin-ajax.php'); ?>';


    // REMOVED: Old template installation with confirm() - now handled by SPTTemplates.js

    // File import functionality
    $('#import-template-file').on('click', function() {
        var fileInput = $('#template_file')[0];
        if (!fileInput.files.length) {
            alert('Please select a file to import.');
            return;
        }

        var formData = new FormData();
        formData.append('action', 'spt_import_template');
        formData.append('import_type', 'file');
        formData.append('template_file', fileInput.files[0]);
        formData.append('nonce', sptNonce);

        $(this).prop('disabled', true).text('Importing...');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('Template imported successfully! ' + response.data.message);
                    location.reload();
                } else {
                    alert('Import failed: ' + response.data);
                }
            },
            error: function() {
                alert('Import failed. Please try again.');
            },
            complete: function() {
                $('#import-template-file').prop('disabled', false).text('Import File');
            }
        });
    });

    // Text import functionality  
    $('#import-template-text').on('click', function() {
        var templateText = $('#template_text').val().trim();
        if (!templateText) {
            alert('Please enter template data.');
            return;
        }

        $(this).prop('disabled', true).text('Importing...');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'spt_import_template',
                import_type: 'text',
                template_data: templateText,
                nonce: sptNonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Template imported successfully! ' + response.data.message);
                    location.reload();
                } else {
                    alert('Import failed: ' + response.data);
                }
            },
            error: function() {
                alert('Import failed. Please try again.');
            },
            complete: function() {
                $('#import-template-text').prop('disabled', false).text('Import Text');
            }
        });
    });

    // Export functionality
    $('#export-rules').on('click', function() {
        $(this).prop('disabled', true).text('Exporting...');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'spt_export_rules',
                nonce: sptNonce
            },
            success: function(response) {
                if (response.success) {
                    // Create and download file
                    var blob = new Blob([response.data.data], { type: 'application/json' });
                    var url = window.URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = response.data.filename;
                    a.click();
                    window.URL.revokeObjectURL(url);
                    
                    alert('Export completed! ' + response.data.rules_count + ' rules exported.');
                } else {
                    alert('Export failed: ' + response.data);
                }
            },
            error: function() {
                alert('Export failed. Please try again.');
            },
            complete: function() {
                $('#export-rules').prop('disabled', false).text('Download Export File');
            }
        });
    });
});
   
</script>

        <style>
        .template-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .template-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.2s ease;
            background: #fff;
        }

        .template-card:hover {
            border-color: #0073aa;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .template-preview {
            height: 120px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            position: relative;
        }

        .template-icon {
            font-size: 48px;
        }

        .template-info {
            padding: 20px;
        }

        .template-info h4 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 16px;
        }

        .template-info p {
            margin: 0 0 15px 0;
            color: #666;
            font-size: 13px;
            line-height: 1.5;
        }

        .template-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 12px;
            color: #999;
        }

        .template-actions {
            display: flex;
            gap: 10px;
        }

        .template-actions .button {
            flex: 1;
            text-align: center;
            justify-content: center;
        }

        .import-export-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin: 30px 0;
        }

        .import-section, .export-section {
            padding: 20px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 6px;
        }

        .file-upload-area {
            border: 2px dashed #ddd;
            padding: 20px;
            text-align: center;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .file-upload-area:hover {
            border-color: #0073aa;
            background: #f8f9fa;
        }

        .spt-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999999;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .spt-modal-content {
            background: #fff;
            border-radius: 8px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .spt-modal-header {
            padding: 20px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .spt-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }

        .spt-modal-body {
            padding: 20px;
        }

        .spt-modal-footer {
            padding: 20px;
            border-top: 1px solid #ddd;
            text-align: right;
        }

        @media (max-width: 768px) {
            .template-grid {
                grid-template-columns: 1fr;
            }

            .import-export-section {
                grid-template-columns: 1fr;
            }
        }
        </style>
        
        
        
<!-- Additional JavaScript for enhanced upload handling -->
<script type="text/javascript">
jQuery(document).ready(function($) {
    // Enhanced file input handling
    $('#template-file-input').on('change', function() {
        var file = this.files[0];
        var $statusDiv = $('.import-status');
        
        if (file) {
            // Clear previous messages
            $statusDiv.html('');
            
            // Validate file
            if (!file.name.toLowerCase().endsWith('.json')) {
                $statusDiv.html('<div class="notice notice-error"><p>Please select a valid JSON file.</p></div>');
                $(this).val('');
                return;
            }
            
            if (file.size > 5 * 1024 * 1024) {
                $statusDiv.html('<div class="notice notice-error"><p>File too large. Maximum size is 5MB.</p></div>');
                $(this).val('');
                return;
            }
            
            // Show file info
            var fileSize = (file.size / 1024).toFixed(1) + ' KB';
            if (file.size > 1024 * 1024) {
                fileSize = (file.size / (1024 * 1024)).toFixed(1) + ' MB';
            }
            
            $statusDiv.html('<div class="notice notice-info"><p><strong>File selected:</strong> ' + file.name + ' (' + fileSize + ')</p></div>');
        }
    });
    
    // Enhanced form submission with progress
    $('.template-upload-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submitBtn = $form.find('input[type="submit"]');
        var $spinner = $('#upload-spinner');
        var $progress = $('.upload-progress');
        var $progressBar = $('.upload-progress-bar');
        var $statusDiv = $('.import-status');
        
        // Validate file selection
        var fileInput = $form.find('input[type="file"]')[0];
        if (!fileInput || !fileInput.files || !fileInput.files.length) {
            $statusDiv.html('<div class="notice notice-error"><p>Please select a file to upload.</p></div>');
            return;
        }
        
        // Show progress
        $submitBtn.prop('disabled', true).val('Uploading...');
        $spinner.show();
        $progress.show();
        $progressBar.css('width', '0%');
        
        // Prepare form data
        var formData = new FormData();
        formData.append('action', 'spt_import_template');
        formData.append('import_type', 'file');
        formData.append('nonce', spt_admin_ajax.nonce);
        formData.append('template_file', fileInput.files[0]);
        
        if ($form.find('input[name="replace_existing"]:checked').length) {
            formData.append('replace_existing', '1');
        }
        
        // Simulate progress animation
        var progressInterval = setInterval(function() {
            var currentWidth = parseInt($progressBar.css('width'));
            if (currentWidth < 90) {
                $progressBar.css('width', (currentWidth + 10) + '%');
            }
        }, 200);
        
        $.ajax({
            url: spt_admin_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                clearInterval(progressInterval);
                $progressBar.css('width', '100%');
                
                if (response && response.success) {
                    var message = 'Import completed successfully!';
                    if (response.data) {
                        var details = [];
                        if (response.data.imported) details.push(response.data.imported + ' rules imported');
                        if (response.data.updated) details.push(response.data.updated + ' rules updated');
                        if (response.data.skipped) details.push(response.data.skipped + ' rules skipped');
                        if (details.length) message += ' (' + details.join(', ') + ')';
                    }
                    
                    $statusDiv.html('<div class="notice notice-success"><p>' + message + '</p></div>');
                    
                    // Clear form
                    $form[0].reset();
                    
                    // Reload page after delay
                    setTimeout(function() {
                        if (confirm('Import completed! Would you like to reload the page to see the imported rules?')) {
                            location.reload();
                        }
                    }, 2000);
                } else {
                    var errorMsg = 'Import failed';
                    if (response && response.data) {
                        errorMsg += ': ' + response.data;
                    }
                    $statusDiv.html('<div class="notice notice-error"><p>' + errorMsg + '</p></div>');
                }
            },
            error: function(xhr, status, error) {
                clearInterval(progressInterval);
                console.error('Upload error:', xhr.responseText);
                $statusDiv.html('<div class="notice notice-error"><p>Upload failed: ' + error + '</p></div>');
            },
            complete: function() {
                $submitBtn.prop('disabled', false).val('Import Template');
                $spinner.hide();
                setTimeout(function() {
                    $progress.hide();
                }, 1000);
            }
        });
    });
});
</script>

<style>
/* Enhanced styling for upload form */
.file-upload-area {
    border: 2px dashed #ccc;
    border-radius: 8px;
    padding: 30px 20px;
    text-align: center;
    transition: all 0.3s ease;
    background: #fafafa;
}

.file-upload-area:hover {
    border-color: #0073aa;
    background: #f0f8ff;
}

.file-upload-area input[type="file"] {
    margin: 15px 0;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #fff;
    width: 100%;
    max-width: 400px;
}

.upload-progress {
    width: 100%;
    height: 8px;
    background: #f0f0f0;
    border-radius: 4px;
    overflow: hidden;
    margin: 10px 0;
}

.upload-progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #0073aa, #00a0d2);
    width: 0%;
    transition: width 0.3s ease;
    border-radius: 4px;
}

.import-instructions h5 {
    margin: 0 0 10px 0;
    color: #0073aa;
    font-weight: 600;
}

.import-instructions ul {
    color: #333;
    line-height: 1.6;
}

.import-status .notice {
    margin: 10px 0;
    padding: 10px 15px;
    border-radius: 4px;
}

.import-status .notice p {
    margin: 0;
}

.spinner {
    background: url('<?php echo admin_url('images/spinner.gif'); ?>') no-repeat;
    background-size: 20px 20px;
    display: inline-block;
    width: 20px;
    height: 20px;
    margin-left: 10px;
    vertical-align: middle;
}

@media (max-width: 768px) {
    .file-upload-area {
        padding: 20px 15px;
    }
    
    .import-instructions {
        font-size: 14px;
    }
}
</style>        
        
        

        <?php
    }
    
    
    /**
     * Render analytics tab - FIXED VERSION
     * Replace the render_analytics_tab() method in class-spt-admin.php with this implementation
     */
    private function render_analytics_tab() {
        // Get analytics instance
        $analytics = new SPT_Analytics();

        // Get dashboard data
        $summary = $analytics->get_analytics_summary(30);
        $popular_tabs = $analytics->get_popular_tabs(10, 30);
        $top_products = $analytics->get_top_products(5, 30);
        $engagement = $analytics->get_engagement_metrics(30);
        $daily_data = $analytics->get_daily_analytics(30);

        ?>
        <div class="spt-analytics">
            <div class="analytics-header">
                <h2><?php esc_html_e('Analytics Dashboard', 'smart-product-tabs-for-woocommerce'); ?></h2>
                <div class="analytics-controls">
                    <select id="analytics-period">
                        <option value="7"><?php esc_html_e('Last 7 days', 'smart-product-tabs-for-woocommerce'); ?></option>
                        <option value="30" selected><?php esc_html_e('Last 30 days', 'smart-product-tabs-for-woocommerce'); ?></option>
                        <option value="90"><?php esc_html_e('Last 90 days', 'smart-product-tabs-for-woocommerce'); ?></option>
                    </select>
                    <button type="button" class="button" id="refresh-analytics">
                        <?php esc_html_e('Refresh', 'smart-product-tabs-for-woocommerce'); ?>
                    </button>
                    <button type="button" class="button" id="export-analytics">
                        <?php esc_html_e('Export Data', 'smart-product-tabs-for-woocommerce'); ?>
                    </button>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="analytics-summary">
                <div class="analytics-card">
                    <h3><?php esc_html_e('Total Views', 'smart-product-tabs-for-woocommerce'); ?></h3>
                    <div class="metric-value"><?php echo number_format($summary['total_views']); ?></div>
                    <div class="metric-change"><?php esc_html_e('Last 30 days', 'smart-product-tabs-for-woocommerce'); ?></div>
                </div>

                <div class="analytics-card">
                    <h3><?php esc_html_e('Unique Products', 'smart-product-tabs-for-woocommerce'); ?></h3>
                    <div class="metric-value"><?php echo number_format($summary['unique_products']); ?></div>
                    <div class="metric-change"><?php esc_html_e('With tab views', 'smart-product-tabs-for-woocommerce'); ?></div>
                </div>

                <div class="analytics-card">
                    <h3><?php esc_html_e('Active Tabs', 'smart-product-tabs-for-woocommerce'); ?></h3>
                    <div class="metric-value"><?php echo number_format($summary['active_tabs']); ?></div>
                    <div class="metric-change"><?php esc_html_e('Different tabs viewed', 'smart-product-tabs-for-woocommerce'); ?></div>
                </div>

                <div class="analytics-card">
                    <h3><?php esc_html_e('Avg Daily Views', 'smart-product-tabs-for-woocommerce'); ?></h3>
                    <div class="metric-value"><?php echo number_format($summary['avg_daily_views'], 1); ?></div>
                    <div class="metric-change"><?php esc_html_e('Per day average', 'smart-product-tabs-for-woocommerce'); ?></div>
                </div>

                <div class="analytics-card">
                    <h3><?php esc_html_e('Engagement Rate', 'smart-product-tabs-for-woocommerce'); ?></h3>
                    <div class="metric-value"><?php echo number_format($engagement['engagement_rate'], 1); ?>%</div>
                    <div class="metric-change"><?php esc_html_e('Products with views', 'smart-product-tabs-for-woocommerce'); ?></div>
                </div>
            </div>

            <!-- Enhanced Charts Section with Better Layout -->
            <!-- Simplified Charts Section -->
            <div class="analytics-charts">
                <div class="chart-container">
                    <h4><?php esc_html_e('Popular Tabs (Last 30 Days)', 'smart-product-tabs-for-woocommerce'); ?></h4>
                    <?php if (!empty($popular_tabs)): ?>
                        <div class="enhanced-chart-table">
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th style="width: 70%;"><?php esc_html_e('Tab Name', 'smart-product-tabs-for-woocommerce'); ?></th>
                                        <th style="width: 15%; text-align: center;"><?php esc_html_e('Views', 'smart-product-tabs-for-woocommerce'); ?></th>
                                        <th style="width: 15%; text-align: center;"><?php esc_html_e('Products', 'smart-product-tabs-for-woocommerce'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $max_views = max(array_column($popular_tabs, 'total_views'));
                                    foreach ($popular_tabs as $tab): 
                                        // Only show custom tabs (skip default WooCommerce tabs)
                                        if ($tab->tab_type !== 'custom') {
                                            continue;
                                        }
                                        $percentage = $max_views > 0 ? ($tab->total_views / $max_views) * 100 : 0;
                                    ?>
                                    <tr>
                                        <td class="tab-name-cell">
                                            <div class="tab-name-container">
                                                <strong class="tab-display-name"><?php echo esc_html($tab->display_name); ?></strong>
                                                <div class="tab-progress-bar">
                                                    <div class="progress-fill" style="width: <?php echo $percentage; ?>%;"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="text-align: center;">
                                            <span class="metric-number"><?php echo number_format($tab->total_views); ?></span>
                                        </td>
                                        <td style="text-align: center;">
                                            <span class="metric-number"><?php echo number_format($tab->products_count); ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="chart-placeholder">
                            <p><?php esc_html_e('No custom tab data available yet. Tab views will appear here once users interact with your custom product tabs.', 'smart-product-tabs-for-woocommerce'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="chart-container">
                    <h4><?php esc_html_e('Top Performing Products', 'smart-product-tabs-for-woocommerce'); ?></h4>
                    <?php if (!empty($top_products)): ?>
                        <div class="enhanced-chart-table">
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th style="width: 70%;"><?php esc_html_e('Product Name', 'smart-product-tabs-for-woocommerce'); ?></th>
                                        <th style="width: 15%; text-align: center;"><?php esc_html_e('Views', 'smart-product-tabs-for-woocommerce'); ?></th>
                                        <th style="width: 15%; text-align: center;"><?php esc_html_e('Tabs', 'smart-product-tabs-for-woocommerce'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $max_views = max(array_column($top_products, 'total_views'));
                                    foreach ($top_products as $product): 
                                        $percentage = $max_views > 0 ? ($product->total_views / $max_views) * 100 : 0;
                                        $status_class = $product->product_status === 'publish' ? 'product-active' : 'product-inactive';
                                    ?>
                                    <tr>
                                        <td class="product-name-cell">
                                            <div class="product-name-container">
                                                <?php if ($product->product_url && $product->product_status === 'publish'): ?>
                                                    <a href="<?php echo esc_url($product->product_url); ?>" target="_blank" class="product-link">
                                                        <strong><?php echo esc_html(wp_trim_words($product->product_name, 10)); ?></strong>
                                                    </a>
                                                <?php else: ?>
                                                    <strong class="<?php echo $status_class; ?>"><?php echo esc_html(wp_trim_words($product->product_name, 10)); ?></strong>
                                                <?php endif; ?>
                                                <div class="product-progress-bar">
                                                    <div class="progress-fill" style="width: <?php echo $percentage; ?>%;"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="text-align: center;">
                                            <span class="metric-number"><?php echo number_format($product->total_views); ?></span>
                                        </td>
                                        <td style="text-align: center;">
                                            <span class="metric-number"><?php echo number_format($product->tabs_viewed); ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="chart-placeholder">
                            <p><?php esc_html_e('No product data available yet.', 'smart-product-tabs-for-woocommerce'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Daily Analytics Chart -->
            <?php if (!empty($daily_data)): ?>
            <div class="chart-container" style="margin-top: 30px;">
                <h4><?php esc_html_e('Daily Analytics Trend', 'smart-product-tabs-for-woocommerce'); ?></h4>
                <div class="daily-chart">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Date', 'smart-product-tabs-for-woocommerce'); ?></th>
                                <th><?php esc_html_e('Total Views', 'smart-product-tabs-for-woocommerce'); ?></th>
                                <th><?php esc_html_e('Unique Tabs', 'smart-product-tabs-for-woocommerce'); ?></th>
                                <th><?php esc_html_e('Unique Products', 'smart-product-tabs-for-woocommerce'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($daily_data, -10) as $day): ?>
                            <tr>
                                <td><?php echo esc_html(date('M j, Y', strtotime($day->date))); ?></td>
                                <td><?php echo number_format($day->total_views); ?></td>
                                <td><?php echo number_format($day->unique_tabs); ?></td>
                                <td><?php echo number_format($day->unique_products); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Analytics Settings (moved from removed settings tab) -->
            <div class="analytics-settings" style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-radius: 6px;">
                <h4><?php esc_html_e('Analytics Settings', 'smart-product-tabs-for-woocommerce'); ?></h4>
                <form method="post" action="">
                    <?php wp_nonce_field('spt_analytics_settings'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable Analytics', 'smart-product-tabs-for-woocommerce'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="spt_enable_analytics" value="1" <?php checked(get_option('spt_enable_analytics', 1)); ?>>
                                    <?php esc_html_e('Track tab views and performance metrics', 'smart-product-tabs-for-woocommerce'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Disable this to stop collecting analytics data.', 'smart-product-tabs-for-woocommerce'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Data Retention', 'smart-product-tabs-for-woocommerce'); ?></th>
                            <td>
                                <select name="spt_analytics_retention_days">
                                    <option value="30" <?php selected(get_option('spt_analytics_retention_days', 90), 30); ?>>30 days</option>
                                    <option value="90" <?php selected(get_option('spt_analytics_retention_days', 90), 90); ?>>90 days</option>
                                    <option value="365" <?php selected(get_option('spt_analytics_retention_days', 90), 365); ?>>1 year</option>
                                </select>
                                <p class="description"><?php esc_html_e('How long to keep analytics data before automatic cleanup.', 'smart-product-tabs-for-woocommerce'); ?></p>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <input type="submit" name="save_analytics_settings" class="button-primary" value="<?php esc_html_e('Save Settings', 'smart-product-tabs-for-woocommerce'); ?>">
                        <button type="button" class="button" id="reset-analytics" style="margin-left: 10px;">
                            <?php esc_html_e('Reset All Data', 'smart-product-tabs-for-woocommerce'); ?>
                        </button>
                    </p>
                </form>
            </div>

            <!-- Debug Information (only for admins when WP_DEBUG is on) -->
            <?php if (WP_DEBUG && current_user_can('manage_options')): ?>
            <div class="analytics-debug" style="margin-top: 20px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107;">
                <h4><?php esc_html_e('Debug Information', 'smart-product-tabs-for-woocommerce'); ?></h4>
                <?php
                global $wpdb;
                $table_stats = $analytics->get_table_size();
                $total_records = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}spt_analytics");
                ?>
                <p><strong><?php esc_html_e('Database Records:', 'smart-product-tabs-for-woocommerce'); ?></strong> <?php echo number_format($total_records); ?></p>
                <p><strong><?php esc_html_e('Table Size:', 'smart-product-tabs-for-woocommerce'); ?></strong> <?php echo $table_stats->size_mb; ?> MB</p>
                <p><strong><?php esc_html_e('Analytics Enabled:', 'smart-product-tabs-for-woocommerce'); ?></strong> <?php echo get_option('spt_enable_analytics', 1) ? 'Yes' : 'No'; ?></p>
            </div>
            <?php endif; ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Handle period changes
            $('#analytics-period').on('change', function() {
                var period = $(this).val();
                window.location.href = '?page=smart-product-tabs&tab=analytics&period=' + period;
            });

            // Handle refresh button
            $('#refresh-analytics').on('click', function() {
                location.reload();
            });

            // Handle reset analytics
            $('#reset-analytics').on('click', function() {
                if (confirm('<?php esc_html_e('Are you sure you want to reset all analytics data? This cannot be undone.', 'smart-product-tabs-for-woocommerce'); ?>')) {
                    $.post(ajaxurl, {
                        action: 'spt_reset_analytics',
                        nonce: '<?php echo wp_create_nonce('spt_reset_analytics'); ?>'
                    }, function(response) {
                        if (response.success) {
                            alert('<?php esc_html_e('Analytics data has been reset.', 'smart-product-tabs-for-woocommerce'); ?>');
                            location.reload();
                        } else {
                            alert('<?php esc_html_e('Failed to reset analytics data.', 'smart-product-tabs-for-woocommerce'); ?>');
                        }
                    });
                }
            });

            // Handle export analytics
            $('#export-analytics').on('click', function() {
                var period = $('#analytics-period').val();
                window.open('?page=smart-product-tabs&spt_action=export_analytics&period=' + period + '&format=csv&_wpnonce=<?php echo wp_create_nonce('spt_export_analytics'); ?>');
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render settings tab
     */
    private function render_settings_tab() {
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'spt_settings')) {
            update_option('spt_enable_analytics', isset($_POST['spt_enable_analytics']) ? 1 : 0);
            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved.', 'smart-product-tabs-for-woocommerce') . '</p></div>';
        }
        ?>
        <div class="spt-settings">
            <h2><?php esc_html_e('Settings', 'smart-product-tabs-for-woocommerce'); ?></h2>
            <form method="post" action="">
                <?php wp_nonce_field('spt_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Analytics', 'smart-product-tabs-for-woocommerce'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="spt_enable_analytics" value="1" <?php checked(get_option('spt_enable_analytics', 1)); ?>>
                                <?php esc_html_e('Track tab views and performance', 'smart-product-tabs-for-woocommerce'); ?>
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
    
    
    
    
    /**
     * AJAX: Reset analytics data
     */
    public function ajax_reset_analytics() {
        check_ajax_referer('spt_reset_analytics', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $analytics = new SPT_Analytics();
        $result = $analytics->reset_analytics();

        if ($result) {
            wp_send_json_success(esc_html__('Analytics data has been reset successfully.', 'smart-product-tabs-for-woocommerce'));
        } else {
            wp_send_json_error(esc_html__('Failed to reset analytics data.', 'smart-product-tabs-for-woocommerce'));
        }
    }    
    
    
    /**
     * Handle analytics export
     */
    public function handle_analytics_export() {
        if (isset($_GET['spt_action']) && $_GET['spt_action'] === 'export_analytics' && 
            wp_verify_nonce($_GET['_wpnonce'], 'spt_export_analytics')) {

            if (!current_user_can('manage_woocommerce')) {
                wp_die(esc_html__('Insufficient permissions', 'smart-product-tabs-for-woocommerce'));
            }

            $period = isset($_GET['period']) ? intval($_GET['period']) : 30;
            $format = isset($_GET['format']) ? sanitize_text_field($_GET['format']) : 'csv';

            $analytics = new SPT_Analytics();
            $export_data = $analytics->export_analytics($format, $period);

            if ($format === 'csv') {
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="spt-analytics-' . date('Y-m-d') . '.csv"');
                echo $export_data;
            } else {
                header('Content-Type: application/json');
                header('Content-Disposition: attachment; filename="spt-analytics-' . date('Y-m-d') . '.json"');
                echo $export_data;
            }

            exit;
        }
    }    
    

}
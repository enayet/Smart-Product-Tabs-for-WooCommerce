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
                        <label for="rule_name"><?php _e('Rule Name', 'smart-product-tabs'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text" id="rule_name" name="rule_name" class="regular-text" 
                               value="<?php echo $is_edit ? esc_attr($rule->rule_name) : ''; ?>" required>
                        <p class="description"><?php _e('Internal name for this rule (only visible in admin)', 'smart-product-tabs'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="tab_title"><?php _e('Tab Title', 'smart-product-tabs'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text" id="tab_title" name="tab_title" class="regular-text" 
                               value="<?php echo $is_edit ? esc_attr($rule->tab_title) : ''; ?>" required>
                        <p class="description">
                            <?php _e('Title displayed on the tab. Use merge tags like {product_name} for dynamic content.', 'smart-product-tabs'); ?><br>
                            <strong><?php _e('Available merge tags:', 'smart-product-tabs'); ?></strong>
                            <code>{product_name}</code>, <code>{product_category}</code>, <code>{product_price}</code>
                        </p>
                    </td>
                </tr>
                
                <!-- Display Conditions -->
                <tr class="condition-row">
                    <th scope="row">
                        <label for="condition_type"><?php _e('Show Tab When', 'smart-product-tabs'); ?></label>
                    </th>
                    <td>
                        <?php 
                        $conditions = $is_edit ? json_decode($rule->conditions, true) : array();
                        $condition_type = isset($conditions['type']) ? $conditions['type'] : 'all';
                        ?>
                        <select id="condition_type" name="condition_type" class="enhanced-select">
                            <option value="all" <?php selected($condition_type, 'all'); ?>><?php _e('All Products', 'smart-product-tabs'); ?></option>
                            <optgroup label="<?php _e('Product Properties', 'smart-product-tabs'); ?>">
                                <option value="category" <?php selected($condition_type, 'category'); ?>><?php _e('Product Category', 'smart-product-tabs'); ?></option>
                                <option value="attribute" <?php selected($condition_type, 'attribute'); ?>><?php _e('Product Attribute', 'smart-product-tabs'); ?></option>
                                <option value="tags" <?php selected($condition_type, 'tags'); ?>><?php _e('Product Tags', 'smart-product-tabs'); ?></option>
                                <option value="product_type" <?php selected($condition_type, 'product_type'); ?>><?php _e('Product Type', 'smart-product-tabs'); ?></option>
                            </optgroup>
                            <optgroup label="<?php _e('Pricing & Stock', 'smart-product-tabs'); ?>">
                                <option value="price_range" <?php selected($condition_type, 'price_range'); ?>><?php _e('Price Range', 'smart-product-tabs'); ?></option>
                                <option value="stock_status" <?php selected($condition_type, 'stock_status'); ?>><?php _e('Stock Status', 'smart-product-tabs'); ?></option>
                                <option value="sale" <?php selected($condition_type, 'sale'); ?>><?php _e('On Sale Status', 'smart-product-tabs'); ?></option>
                            </optgroup>
                            <optgroup label="<?php _e('Special Properties', 'smart-product-tabs'); ?>">
                                <option value="featured" <?php selected($condition_type, 'featured'); ?>><?php _e('Featured Product', 'smart-product-tabs'); ?></option>
                                <option value="custom_field" <?php selected($condition_type, 'custom_field'); ?>><?php _e('Custom Field', 'smart-product-tabs'); ?></option>
                            </optgroup>
                        </select>
                        <p class="description"><?php _e('Choose when this tab should appear on product pages', 'smart-product-tabs'); ?></p>
                        
                        <div id="condition_details" style="margin-top:15px;">
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
                            <option value="all" <?php selected($user_role_condition, 'all'); ?>><?php _e('All Users (including guests)', 'smart-product-tabs'); ?></option>
                            <option value="logged_in" <?php selected($user_role_condition, 'logged_in'); ?>><?php _e('Logged-in Users Only', 'smart-product-tabs'); ?></option>
                            <option value="specific_role" <?php selected($user_role_condition, 'specific_role'); ?>><?php _e('Specific User Role(s)', 'smart-product-tabs'); ?></option>
                        </select>
                        <p class="description"><?php _e('Control which users can see this tab', 'smart-product-tabs'); ?></p>
                        
                        <div id="role_selector" style="<?php echo $user_role_condition !== 'specific_role' ? 'display:none;' : ''; ?> margin-top:10px;" class="role-selector">
                            <p><strong><?php _e('Select allowed roles:', 'smart-product-tabs'); ?></strong></p>
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
                    <th scope="row"><?php _e('Content Type', 'smart-product-tabs'); ?></th>
                    <td>
                        <?php $content_type = $is_edit ? $rule->content_type : 'rich_editor'; ?>
                        <fieldset class="content-type-selector">
                            <legend class="screen-reader-text"><?php _e('Content Type', 'smart-product-tabs'); ?></legend>
                            <label class="content-type-option">
                                <input type="radio" name="content_type" value="rich_editor" <?php checked($content_type, 'rich_editor'); ?>>
                                <span class="option-title"><?php _e('Rich Editor', 'smart-product-tabs'); ?></span>
                                <span class="option-description"><?php _e('WYSIWYG editor with formatting options', 'smart-product-tabs'); ?></span>
                            </label>
                            <label class="content-type-option">
                                <input type="radio" name="content_type" value="plain_text" <?php checked($content_type, 'plain_text'); ?>>
                                <span class="option-title"><?php _e('Plain Text', 'smart-product-tabs'); ?></span>
                                <span class="option-description"><?php _e('Simple text area without formatting', 'smart-product-tabs'); ?></span>
                            </label>
                        </fieldset>
                    </td>
                </tr>
                
                <!-- Content Editor -->
                <tr>
                    <th scope="row">
                        <label for="tab_content"><?php _e('Tab Content', 'smart-product-tabs'); ?> <span class="required">*</span></label>
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
                            <textarea id="tab_content_plain" name="tab_content_plain" rows="12" cols="50" class="large-text" placeholder="<?php _e('Enter your tab content here...', 'smart-product-tabs'); ?>"><?php echo $is_edit && $content_type === 'plain_text' ? esc_textarea($rule->tab_content) : ''; ?></textarea>
                        </div>
                        
                        <div class="merge-tags-help">
                            <strong><?php _e('Available merge tags:', 'smart-product-tabs'); ?></strong><br>
                            <div class="merge-tags-grid">
                                <div class="tag-group">
                                    <strong><?php _e('Product Info:', 'smart-product-tabs'); ?></strong>
                                    <code>{product_name}</code>
                                    <code>{product_category}</code>
                                    <code>{product_price}</code>
                                    <code>{product_sku}</code>
                                </div>
                                <div class="tag-group">
                                    <strong><?php _e('Product Details:', 'smart-product-tabs'); ?></strong>
                                    <code>{product_weight}</code>
                                    <code>{product_dimensions}</code>
                                    <code>{product_stock_status}</code>
                                    <code>{product_stock_quantity}</code>
                                </div>
                                <div class="tag-group">
                                    <strong><?php _e('Custom Fields:', 'smart-product-tabs'); ?></strong>
                                    <code>{custom_field_[key]}</code>
                                    <small><?php _e('Replace [key] with actual field name', 'smart-product-tabs'); ?></small>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                
                <!-- Display Settings -->
                <tr>
                    <th scope="row"><?php _e('Display Settings', 'smart-product-tabs'); ?></th>
                    <td>
                        <div class="display-settings-grid">
                            <div class="setting-group">
                                <label for="priority">
                                    <strong><?php _e('Priority:', 'smart-product-tabs'); ?></strong>
                                    <input type="number" id="priority" name="priority" value="<?php echo $is_edit ? esc_attr($rule->priority) : '10'; ?>" min="1" max="100" style="width: 80px;">
                                </label>
                                <p class="description"><?php _e('Lower numbers appear first (1-100)', 'smart-product-tabs'); ?></p>
                            </div>
                            
                            <div class="setting-group">
                                <label class="setting-checkbox">
                                    <input type="checkbox" name="mobile_hidden" id="mobile_hidden" value="1" <?php echo $is_edit ? checked($rule->mobile_hidden, 1) : ''; ?>>
                                    <span class="checkmark"></span>
                                    <strong><?php _e('Hide on mobile devices', 'smart-product-tabs'); ?></strong>
                                </label>
                                <p class="description"><?php _e('Tab will not appear on mobile/tablet devices', 'smart-product-tabs'); ?></p>
                            </div>
                            
                            <div class="setting-group">
                                <label class="setting-checkbox">
                                    <input type="checkbox" name="is_active" id="is_active" value="1" <?php echo $is_edit ? checked($rule->is_active, 1) : 'checked'; ?>>
                                    <span class="checkmark"></span>
                                    <strong><?php _e('Active', 'smart-product-tabs'); ?></strong>
                                </label>
                                <p class="description"><?php _e('Uncheck to temporarily disable this tab', 'smart-product-tabs'); ?></p>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
            
            <div class="form-actions">
                <input type="submit" class="button-primary" value="<?php echo $is_edit ? __('Update Rule', 'smart-product-tabs') : __('Save Rule', 'smart-product-tabs'); ?>">
                <a href="<?php echo admin_url('admin.php?page=smart-product-tabs'); ?>" class="button">
                    <?php _e('Cancel', 'smart-product-tabs'); ?>
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
     * Render condition fields with proper category hierarchy
     */
    private function render_condition_fields($conditions = array()) {
        ?>
        <div class="condition-field" data-condition="category" style="display:none;">
            <label for="condition_category"><?php _e('Select Categories:', 'smart-product-tabs'); ?></label>
            <select name="condition_category[]" id="condition_category" multiple style="width: 300px; height: 150px;">
                <?php echo $this->get_hierarchical_category_options($conditions); ?>
            </select>
            <p class="description"><?php _e('Hold Ctrl/Cmd to select multiple categories', 'smart-product-tabs'); ?></p>
        </div>
        
        <div class="condition-field" data-condition="attribute" style="display:none;">
            <label for="condition_attribute"><?php _e('Attribute:', 'smart-product-tabs'); ?></label>
            <select name="condition_attribute" id="condition_attribute" style="width: 200px;">
                <option value=""><?php _e('Select Attribute', 'smart-product-tabs'); ?></option>
                <?php
                $attributes = wc_get_attribute_taxonomies();
                foreach ($attributes as $attribute) {
                    $selected = isset($conditions['attribute']) ? selected($conditions['attribute'], $attribute->attribute_name, false) : '';
                    echo '<option value="' . esc_attr($attribute->attribute_name) . '" ' . $selected . '>' . esc_html($attribute->attribute_label) . '</option>';
                }
                ?>
            </select>
            <br><br>
            <label for="condition_attribute_value"><?php _e('Value:', 'smart-product-tabs'); ?></label>
            <input type="text" name="condition_attribute_value" id="condition_attribute_value" style="width: 200px;" 
                   value="<?php echo isset($conditions['value']) ? esc_attr($conditions['value']) : ''; ?>" 
                   placeholder="<?php _e('Attribute value', 'smart-product-tabs'); ?>">
        </div>
        
        <div class="condition-field" data-condition="price_range" style="display:none;">
            <label><?php _e('Min Price:', 'smart-product-tabs'); ?></label>
            <input type="number" name="condition_price_min" step="0.01" style="width: 100px;" 
                   value="<?php echo isset($conditions['min']) ? esc_attr($conditions['min']) : ''; ?>" 
                   placeholder="0.00">
            <label style="margin-left: 20px;"><?php _e('Max Price:', 'smart-product-tabs'); ?></label>
            <input type="number" name="condition_price_max" step="0.01" style="width: 100px;" 
                   value="<?php echo isset($conditions['max']) ? esc_attr($conditions['max']) : ''; ?>" 
                   placeholder="999999">
            <p class="description"><?php _e('Leave empty for no limit', 'smart-product-tabs'); ?></p>
        </div>
        
        <div class="condition-field" data-condition="stock_status" style="display:none;">
            <select name="condition_stock_status">
                <option value="instock" <?php echo isset($conditions['value']) ? selected($conditions['value'], 'instock', false) : ''; ?>><?php _e('In Stock', 'smart-product-tabs'); ?></option>
                <option value="outofstock" <?php echo isset($conditions['value']) ? selected($conditions['value'], 'outofstock', false) : ''; ?>><?php _e('Out of Stock', 'smart-product-tabs'); ?></option>
                <option value="onbackorder" <?php echo isset($conditions['value']) ? selected($conditions['value'], 'onbackorder', false) : ''; ?>><?php _e('On Backorder', 'smart-product-tabs'); ?></option>
            </select>
        </div>
        
        <div class="condition-field" data-condition="custom_field" style="display:none;">
            <label for="condition_custom_field_key"><?php _e('Field Key:', 'smart-product-tabs'); ?></label>
            <input type="text" name="condition_custom_field_key" id="condition_custom_field_key" style="width: 200px;" 
                   value="<?php echo isset($conditions['key']) ? esc_attr($conditions['key']) : ''; ?>" 
                   placeholder="_custom_field_key">
            <br><br>
            <label for="condition_custom_field_value"><?php _e('Field Value:', 'smart-product-tabs'); ?></label>
            <input type="text" name="condition_custom_field_value" id="condition_custom_field_value" style="width: 200px;" 
                   value="<?php echo isset($conditions['value']) ? esc_attr($conditions['value']) : ''; ?>" 
                   placeholder="Expected value">
            <br><br>
            <label for="condition_custom_field_operator"><?php _e('Operator:', 'smart-product-tabs'); ?></label>
            <select name="condition_custom_field_operator" id="condition_custom_field_operator">
                <option value="equals" <?php echo isset($conditions['operator']) ? selected($conditions['operator'], 'equals', false) : ''; ?>><?php _e('Equals', 'smart-product-tabs'); ?></option>
                <option value="not_equals" <?php echo isset($conditions['operator']) ? selected($conditions['operator'], 'not_equals', false) : ''; ?>><?php _e('Not Equals', 'smart-product-tabs'); ?></option>
                <option value="contains" <?php echo isset($conditions['operator']) ? selected($conditions['operator'], 'contains', false) : ''; ?>><?php _e('Contains', 'smart-product-tabs'); ?></option>
                <option value="not_empty" <?php echo isset($conditions['operator']) ? selected($conditions['operator'], 'not_empty', false) : ''; ?>><?php _e('Not Empty', 'smart-product-tabs'); ?></option>
                <option value="empty" <?php echo isset($conditions['operator']) ? selected($conditions['operator'], 'empty', false) : ''; ?>><?php _e('Empty', 'smart-product-tabs'); ?></option>
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
            <p class="description"><?php _e('Hold Ctrl/Cmd to select multiple types', 'smart-product-tabs'); ?></p>
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
            <p class="description"><?php _e('Hold Ctrl/Cmd to select multiple tags', 'smart-product-tabs'); ?></p>
        </div>
        
        <div class="condition-field" data-condition="featured" style="display:none;">
            <select name="condition_featured">
                <option value="1" <?php echo isset($conditions['value']) ? selected($conditions['value'], 1, false) : ''; ?>><?php _e('Is Featured', 'smart-product-tabs'); ?></option>
                <option value="0" <?php echo isset($conditions['value']) ? selected($conditions['value'], 0, false) : ''; ?>><?php _e('Is Not Featured', 'smart-product-tabs'); ?></option>
            </select>
        </div>
        
        <div class="condition-field" data-condition="sale" style="display:none;">
            <select name="condition_sale">
                <option value="1" <?php echo isset($conditions['value']) ? selected($conditions['value'], 1, false) : ''; ?>><?php _e('On Sale', 'smart-product-tabs'); ?></option>
                <option value="0" <?php echo isset($conditions['value']) ? selected($conditions['value'], 0, false) : ''; ?>><?php _e('Not On Sale', 'smart-product-tabs'); ?></option>
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
     * Enhanced prepare_conditions method to handle all condition types
     */
    private function prepare_conditions($post_data) {
        $condition_type = sanitize_text_field($post_data['condition_type']);
        
        $conditions = array('type' => $condition_type);
        
        switch ($condition_type) {
            case 'category':
                $conditions['value'] = isset($post_data['condition_category']) ? array_map('intval', $post_data['condition_category']) : array();
                $conditions['operator'] = 'in'; // Default operator
                break;
                
            case 'attribute':
                $conditions['attribute'] = sanitize_text_field($post_data['condition_attribute'] ?? '');
                $conditions['value'] = sanitize_text_field($post_data['condition_attribute_value'] ?? '');
                $conditions['operator'] = 'equals';
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
     * Enhanced format_conditions method for better display
     */
    private function format_conditions($conditions_json) {
        $conditions = json_decode($conditions_json, true);
        
        if (empty($conditions) || $conditions['type'] === 'all') {
            return '<span class="condition-all">' . __('All Products', 'smart-product-tabs') . '</span>';
        }
        
        $type = $conditions['type'];
        
        switch ($type) {
            case 'category':
                if (empty($conditions['value'])) {
                    return '<span class="condition-invalid">' . __('No categories selected', 'smart-product-tabs') . '</span>';
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
                    __('Category', 'smart-product-tabs'),
                    !empty($category_names) ? implode(', ', $category_names) : __('Invalid categories', 'smart-product-tabs')
                );
                
            case 'attribute':
                return sprintf(
                    '<span class="condition-attribute">%s: %s = %s</span>',
                    __('Attribute', 'smart-product-tabs'),
                    esc_html($conditions['attribute'] ?? ''),
                    esc_html($conditions['value'] ?? '')
                );
                
            case 'price_range':
                $currency = get_woocommerce_currency_symbol();
                return sprintf(
                    '<span class="condition-price">%s: %s%s - %s%s</span>',
                    __('Price Range', 'smart-product-tabs'),
                    $currency,
                    number_format($conditions['min'] ?? 0, 2),
                    $currency,
                    number_format($conditions['max'] ?? 999999, 2)
                );
                
            case 'stock_status':
                $status_labels = array(
                    'instock' => __('In Stock', 'smart-product-tabs'),
                    'outofstock' => __('Out of Stock', 'smart-product-tabs'),
                    'onbackorder' => __('On Backorder', 'smart-product-tabs')
                );
                $status = $conditions['value'] ?? 'instock';
                return sprintf(
                    '<span class="condition-stock">%s: %s</span>',
                    __('Stock Status', 'smart-product-tabs'),
                    $status_labels[$status] ?? $status
                );
                
            case 'custom_field':
                return sprintf(
                    '<span class="condition-custom">%s: %s %s %s</span>',
                    __('Custom Field', 'smart-product-tabs'),
                    esc_html($conditions['key'] ?? ''),
                    esc_html($conditions['operator'] ?? 'equals'),
                    esc_html($conditions['value'] ?? '')
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
                    __('Product Type', 'smart-product-tabs'),
                    implode(', ', $type_names)
                );
                
            case 'tags':
                if (empty($conditions['value'])) {
                    return '<span class="condition-invalid">' . __('No tags selected', 'smart-product-tabs') . '</span>';
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
                    __('Tags', 'smart-product-tabs'),
                    !empty($tag_names) ? implode(', ', $tag_names) : __('Invalid tags', 'smart-product-tabs')
                );
                
            case 'featured':
                $value = $conditions['value'] ?? 1;
                return sprintf(
                    '<span class="condition-featured">%s</span>',
                    $value ? __('Featured Products', 'smart-product-tabs') : __('Non-Featured Products', 'smart-product-tabs')
                );
                
            case 'sale':
                $value = $conditions['value'] ?? 1;
                return sprintf(
                    '<span class="condition-sale">%s</span>',
                    $value ? __('On Sale Products', 'smart-product-tabs') : __('Regular Price Products', 'smart-product-tabs')
                );
                
            default:
                return '<span class="condition-unknown">' . sprintf(__('Unknown: %s', 'smart-product-tabs'), ucfirst($type)) . '</span>';
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
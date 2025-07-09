<?php
/**
 * Templates System for Smart Product Tabs
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SPT_Templates {
    
    /**
     * Templates directory
     */
    private $templates_dir;
    
    /**
     * Constructor
     */
    public function __construct() {
        
        add_action('wp_ajax_spt_import_template', array($this, 'ajax_import_template'));
        add_action('wp_ajax_spt_export_rules', array($this, 'ajax_export_rules'));
        add_action('wp_ajax_spt_install_builtin_template', array($this, 'ajax_install_builtin_template'));
        add_action('wp_ajax_spt_get_template_preview', array($this, 'ajax_get_template_preview'));

        add_action('init', array($this, 'init'));
    }
    
    /**
     * Initialize
     */
    public function init() {

    }

    /**
     * Get built-in templates (updated without attributes)
     */
    public function get_builtin_templates() {
        return array(
            'electronics' => array(
                'name' => __('Electronics Store Pack', 'smart-product-tabs'),
                'description' => __('Perfect for electronics stores with Specifications, Warranty, and Support tabs', 'smart-product-tabs'),
                'version' => '1.0',
                'author' => 'Smart Product Tabs',
                'preview_image' => SPT_PLUGIN_URL . 'assets/images/electronics-preview.png',
                'tabs_count' => 4,
                'categories' => array('Electronics', 'Technology'),
                'rules' => array(
                    array(
                        'rule_name' => 'Electronics Specifications',
                        'tab_title' => 'Specifications',
                        'tab_content' => '<h3>Product Specifications</h3>
                                        <table class="shop_attributes">
                                            <tr><td><strong>Brand:</strong></td><td>{custom_field_brand}</td></tr>
                                            <tr><td><strong>Model:</strong></td><td>{custom_field_model}</td></tr>
                                            <tr><td><strong>SKU:</strong></td><td>{product_sku}</td></tr>
                                            <tr><td><strong>Weight:</strong></td><td>{product_weight}</td></tr>
                                            <tr><td><strong>Dimensions:</strong></td><td>{product_dimensions}</td></tr>
                                        </table>',
                        'content_type' => 'rich_editor',
                        'conditions' => json_encode(array('type' => 'category', 'value' => array('electronics', 'computers', 'phones'), 'operator' => 'in')),
                        'user_role_condition' => 'all',
                        'priority' => 15,
                        'is_active' => 1,
                        'mobile_hidden' => 0
                    ),
                    array(
                        'rule_name' => 'Electronics Warranty',
                        'tab_title' => 'Warranty & Support',
                        'tab_content' => '<h3>Warranty Information</h3>
                                        <p><strong>Manufacturer Warranty:</strong> 1 Year</p>
                                        <p><strong>Extended Warranty:</strong> Available for purchase</p>
                                        <h3>Support</h3>
                                        <ul>
                                            <li>24/7 Technical Support</li>
                                            <li>Online Troubleshooting Guides</li>
                                            <li>Repair Service Centers</li>
                                            <li>Replacement Parts Available</li>
                                        </ul>
                                        <p><strong>Support Contact:</strong> support@yourstore.com</p>',
                        'content_type' => 'rich_editor',
                        'conditions' => json_encode(array('type' => 'category', 'value' => array('electronics', 'computers', 'phones'), 'operator' => 'in')),
                        'user_role_condition' => 'all',
                        'priority' => 25,
                        'is_active' => 1,
                        'mobile_hidden' => 0
                    ),
                    array(
                        'rule_name' => 'Electronics Manual',
                        'tab_title' => 'User Manual',
                        'tab_content' => '<h3>User Manual & Documentation</h3>
                                        <p>Download the complete user manual for {product_name}</p>
                                        <ul>
                                            <li><a href="#" target="_blank">Quick Start Guide (PDF)</a></li>
                                            <li><a href="#" target="_blank">Complete User Manual (PDF)</a></li>
                                            <li><a href="#" target="_blank">Safety Instructions (PDF)</a></li>
                                            <li><a href="#" target="_blank">Video Tutorials</a></li>
                                        </ul>
                                        <p><em>Note: Replace the # links with actual manual download links</em></p>',
                        'content_type' => 'rich_editor',
                        'conditions' => json_encode(array('type' => 'category', 'value' => array('electronics', 'computers', 'phones'), 'operator' => 'in')),
                        'user_role_condition' => 'all',
                        'priority' => 35,
                        'is_active' => 1,
                        'mobile_hidden' => 0
                    ),
                    array(
                        'rule_name' => 'Electronics Compatibility',
                        'tab_title' => 'Compatibility',
                        'tab_content' => '<h3>Compatibility Information</h3>
                                        <p><strong>Compatible Systems:</strong></p>
                                        <ul>
                                            <li>Windows 10/11</li>
                                            <li>macOS 10.15+</li>
                                            <li>Linux Ubuntu 18.04+</li>
                                            <li>Android 8.0+</li>
                                            <li>iOS 12.0+</li>
                                        </ul>
                                        <p><strong>System Requirements:</strong></p>
                                        <ul>
                                            <li>Minimum 4GB RAM</li>
                                            <li>USB 3.0 Port</li>
                                            <li>Internet Connection Required</li>
                                        </ul>',
                        'content_type' => 'rich_editor',
                        'conditions' => json_encode(array('type' => 'category', 'value' => array('electronics', 'computers', 'phones'), 'operator' => 'in')),
                        'user_role_condition' => 'all',
                        'priority' => 45,
                        'is_active' => 1,
                        'mobile_hidden' => 0
                    )
                )
            ),
            'fashion' => array(
                'name' => __('Fashion Store Pack', 'smart-product-tabs'),
                'description' => __('Perfect for clothing stores with Size Guide, Care Instructions, and Materials tabs', 'smart-product-tabs'),
                'version' => '1.0',
                'author' => 'Smart Product Tabs',
                'preview_image' => SPT_PLUGIN_URL . 'assets/images/fashion-preview.png',
                'tabs_count' => 3,
                'categories' => array('Fashion', 'Clothing', 'Apparel'),
                'rules' => array(
                    array(
                        'rule_name' => 'Fashion Size Guide',
                        'tab_title' => 'Size Guide',
                        'tab_content' => '<h3>Size Guide for {product_name}</h3>
                                        <div class="size-guide-table">
                                            <table>
                                                <thead>
                                                    <tr>
                                                        <th>Size</th>
                                                        <th>Chest (in)</th>
                                                        <th>Waist (in)</th>
                                                        <th>Hip (in)</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr><td>XS</td><td>32-34</td><td>26-28</td><td>34-36</td></tr>
                                                    <tr><td>S</td><td>34-36</td><td>28-30</td><td>36-38</td></tr>
                                                    <tr><td>M</td><td>36-38</td><td>30-32</td><td>38-40</td></tr>
                                                    <tr><td>L</td><td>38-40</td><td>32-34</td><td>40-42</td></tr>
                                                    <tr><td>XL</td><td>40-42</td><td>34-36</td><td>42-44</td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <p><strong>How to Measure:</strong></p>
                                        <ul>
                                            <li><strong>Chest:</strong> Measure around the fullest part of your chest</li>
                                            <li><strong>Waist:</strong> Measure around your natural waistline</li>
                                            <li><strong>Hip:</strong> Measure around the fullest part of your hips</li>
                                        </ul>',
                        'content_type' => 'rich_editor',
                        'conditions' => json_encode(array('type' => 'category', 'value' => array('clothing', 'fashion', 'apparel'), 'operator' => 'in')),
                        'user_role_condition' => 'all',
                        'priority' => 15,
                        'is_active' => 1,
                        'mobile_hidden' => 0
                    ),
                    array(
                        'rule_name' => 'Fashion Care Instructions',
                        'tab_title' => 'Care Instructions',
                        'tab_content' => '<h3>Care Instructions</h3>
                                        <div class="care-instructions">
                                            <p><strong>Material:</strong> {custom_field_material}</p>
                                            <h4>Washing Instructions:</h4>
                                            <ul>
                                                <li>Machine wash cold (30°C max)</li>
                                                <li>Use mild detergent</li>
                                                <li>Wash with similar colors</li>
                                                <li>Do not bleach</li>
                                            </ul>
                                            <h4>Drying Instructions:</h4>
                                            <ul>
                                                <li>Tumble dry low heat</li>
                                                <li>Remove promptly</li>
                                                <li>Can be line dried</li>
                                            </ul>
                                            <h4>Ironing:</h4>
                                            <ul>
                                                <li>Iron on low to medium heat</li>
                                                <li>Iron inside out to protect print/design</li>
                                                <li>Use pressing cloth if needed</li>
                                            </ul>
                                            <h4>Storage:</h4>
                                            <ul>
                                                <li>Store in cool, dry place</li>
                                                <li>Hang or fold neatly</li>
                                                <li>Avoid direct sunlight</li>
                                            </ul>
                                        </div>',
                        'content_type' => 'rich_editor',
                        'conditions' => json_encode(array('type' => 'category', 'value' => array('clothing', 'fashion', 'apparel'), 'operator' => 'in')),
                        'user_role_condition' => 'all',
                        'priority' => 25,
                        'is_active' => 1,
                        'mobile_hidden' => 0
                    ),
                    array(
                        'rule_name' => 'Fashion Materials',
                        'tab_title' => 'Materials & Features',
                        'tab_content' => '<h3>Materials & Features</h3>
                                        <div class="materials-info">
                                            <h4>Material Composition:</h4>
                                            <p>{custom_field_material_composition}</p>
                                            
                                            <h4>Key Features:</h4>
                                            <ul>
                                                <li>Breathable fabric</li>
                                                <li>Moisture-wicking properties</li>
                                                <li>Wrinkle-resistant</li>
                                                <li>Fade-resistant colors</li>
                                                <li>Pre-shrunk</li>
                                            </ul>
                                            
                                            <h4>Sustainability:</h4>
                                            <ul>
                                                <li>Eco-friendly manufacturing process</li>
                                                <li>Sustainable materials</li>
                                                <li>Fair trade certified</li>
                                                <li>Recyclable packaging</li>
                                            </ul>
                                            
                                            <h4>Country of Origin:</h4>
                                            <p>{custom_field_country_of_origin}</p>
                                        </div>',
                        'content_type' => 'rich_editor',
                        'conditions' => json_encode(array('type' => 'category', 'value' => array('clothing', 'fashion', 'apparel'), 'operator' => 'in')),
                        'user_role_condition' => 'all',
                        'priority' => 35,
                        'is_active' => 1,
                        'mobile_hidden' => 0
                    )
                )
            ),
            'digital' => array(
                'name' => __('Digital Products Pack', 'smart-product-tabs'),
                'description' => __('Perfect for digital downloads with System Requirements, License, and Download tabs', 'smart-product-tabs'),
                'version' => '1.0',
                'author' => 'Smart Product Tabs',
                'preview_image' => SPT_PLUGIN_URL . 'assets/images/digital-preview.png',
                'tabs_count' => 3,
                'categories' => array('Digital', 'Software', 'Downloads'),
                'rules' => array(
                    array(
                        'rule_name' => 'Digital Requirements',
                        'tab_title' => 'System Requirements',
                        'tab_content' => '<h3>System Requirements</h3>
                                        <div class="system-requirements">
                                            <h4>Minimum Requirements:</h4>
                                            <ul>
                                                <li><strong>Operating System:</strong> Windows 10 / macOS 10.14 / Ubuntu 18.04</li>
                                                <li><strong>Processor:</strong> Intel Core i3 or AMD equivalent</li>
                                                <li><strong>Memory:</strong> 4 GB RAM</li>
                                                <li><strong>Storage:</strong> 2 GB available space</li>
                                                <li><strong>Graphics:</strong> DirectX 11 compatible</li>
                                                <li><strong>Network:</strong> Internet connection required for activation</li>
                                            </ul>
                                            
                                            <h4>Recommended Requirements:</h4>
                                            <ul>
                                                <li><strong>Operating System:</strong> Windows 11 / macOS 12+ / Ubuntu 20.04</li>
                                                <li><strong>Processor:</strong> Intel Core i5 or AMD Ryzen 5</li>
                                                <li><strong>Memory:</strong> 8 GB RAM</li>
                                                <li><strong>Storage:</strong> 5 GB available space (SSD recommended)</li>
                                                <li><strong>Graphics:</strong> Dedicated graphics card</li>
                                            </ul>
                                        </div>',
                        'content_type' => 'rich_editor',
                        'conditions' => json_encode(array('type' => 'category', 'value' => array('digital', 'software', 'downloads'), 'operator' => 'in')),
                        'user_role_condition' => 'all',
                        'priority' => 15,
                        'is_active' => 1,
                        'mobile_hidden' => 0
                    ),
                    array(
                        'rule_name' => 'Digital License',
                        'tab_title' => 'License & Terms',
                        'tab_content' => '<h3>License Information</h3>
                                        <div class="license-info">
                                            <p><strong>License Type:</strong> Single User License</p>
                                            <p><strong>Valid For:</strong> Lifetime</p>
                                            
                                            <h4>What You Can Do:</h4>
                                            <ul>
                                                <li>Install on up to 3 devices</li>
                                                <li>Use for personal or commercial projects</li>
                                                <li>Receive free updates for 1 year</li>
                                                <li>Access to customer support</li>
                                            </ul>
                                            
                                            <h4>Restrictions:</h4>
                                            <ul>
                                                <li>Cannot redistribute or resell</li>
                                                <li>Cannot share license with others</li>
                                                <li>Cannot reverse engineer</li>
                                            </ul>
                                            
                                            <h4>Refund Policy:</h4>
                                            <p>30-day money-back guarantee. If you are not satisfied with your purchase, 
                                            contact us within 30 days for a full refund.</p>
                                            
                                            <p><a href="#" target="_blank">View Full License Agreement</a></p>
                                        </div>',
                        'content_type' => 'rich_editor',
                        'conditions' => json_encode(array('type' => 'category', 'value' => array('digital', 'software', 'downloads'), 'operator' => 'in')),
                        'user_role_condition' => 'all',
                        'priority' => 25,
                        'is_active' => 1,
                        'mobile_hidden' => 0
                    ),
                    array(
                        'rule_name' => 'Digital Download',
                        'tab_title' => 'Download & Installation',
                        'tab_content' => '<h3>Download & Installation Guide</h3>
                                        <div class="download-info">
                                            <h4>After Purchase:</h4>
                                            <ol>
                                                <li>You will receive a download link via email</li>
                                                <li>Download link is valid for 7 days</li>
                                                <li>You can re-download from your account page</li>
                                                <li>Keep your license key safe</li>
                                            </ol>
                                            
                                            <h4>Installation Steps:</h4>
                                            <ol>
                                                <li>Download the installer file</li>
                                                <li>Run the installer as administrator</li>
                                                <li>Follow the installation wizard</li>
                                                <li>Enter your license key when prompted</li>
                                                <li>Complete the activation process</li>
                                            </ol>
                                            
                                            <h4>Troubleshooting:</h4>
                                            <ul>
                                                <li><strong>Installation fails:</strong> Try running as administrator</li>
                                                <li><strong>License key invalid:</strong> Check for typos, copy-paste recommended</li>
                                                <li><strong>Activation issues:</strong> Ensure internet connection</li>
                                                <li><strong>Need help:</strong> Contact our support team</li>
                                            </ul>
                                            
                                            <p><strong>Support:</strong> support@yourstore.com</p>
                                        </div>',
                        'content_type' => 'rich_editor',
                        'conditions' => json_encode(array('type' => 'category', 'value' => array('digital', 'software', 'downloads'), 'operator' => 'in')),
                        'user_role_condition' => 'logged_in',
                        'priority' => 35,
                        'is_active' => 1,
                        'mobile_hidden' => 0
                    )
                )
            )
        );
    }
    
    /**
     * Install built-in template
     */
    public function install_builtin_template($template_key) {
        $templates = $this->get_builtin_templates();
        
        if (!isset($templates[$template_key])) {
            return new WP_Error('invalid_template', __('Template not found', 'smart-product-tabs'));
        }
        
        $template = $templates[$template_key];
        
        return $this->import_template_data($template);
    }
    
    /**
     * Export current rules to template
     */
    public function export_rules() {
        global $wpdb;

        $rules_table = $wpdb->prefix . 'spt_rules';
        $rules = $wpdb->get_results("SELECT * FROM $rules_table ORDER BY priority ASC", ARRAY_A);

        $export_data = array(
            'version' => SPT_VERSION,
            'export_date' => current_time('mysql'),
            'export_type' => 'rules_export',
            'name' => sprintf(__('Export from %s', 'smart-product-tabs'), get_bloginfo('name')),
            'description' => sprintf(__('Exported on %s', 'smart-product-tabs'), current_time('F j, Y')),
            'rules' => $rules
        );

        // REMOVED: Tab settings export functionality
        // No longer include tab_settings in export

        return $export_data;
    }
    
    /**
     * Import template data
     */
    public function import_template_data($template_data, $replace_existing = false) {
        global $wpdb;

        $rules_table = $wpdb->prefix . 'spt_rules';
        // REMOVED: $settings_table reference

        $imported_count = 0;
        $skipped_count = 0;
        $updated_count = 0;
        $errors = array();

        try {
            // Start transaction
            $wpdb->query('START TRANSACTION');

            // Process rules
            if (isset($template_data['rules']) && is_array($template_data['rules'])) {
                foreach ($template_data['rules'] as $rule_data) {

                    // Check if rule already exists
                    $existing_rule = $wpdb->get_row($wpdb->prepare(
                        "SELECT id FROM $rules_table WHERE rule_name = %s",
                        $rule_data['rule_name']
                    ));

                    if ($existing_rule && !$replace_existing) {
                        // Skip existing rule
                        $skipped_count++;
                        continue;
                    }

                    // Prepare rule data for insert/update
                    $rule_insert_data = array(
                        'rule_name' => sanitize_text_field($rule_data['rule_name']),
                        'tab_title' => sanitize_text_field($rule_data['tab_title']),
                        'tab_content' => wp_kses_post($rule_data['tab_content']),
                        'content_type' => sanitize_text_field($rule_data['content_type'] ?? 'simple_text'),
                        'conditions' => sanitize_text_field($rule_data['conditions'] ?? ''),
                        'user_role_condition' => sanitize_text_field($rule_data['user_role_condition'] ?? 'all'),
                        'user_roles' => sanitize_text_field($rule_data['user_roles'] ?? ''),
                        'priority' => intval($rule_data['priority'] ?? 10),
                        'is_active' => intval($rule_data['is_active'] ?? 1),
                        'mobile_hidden' => intval($rule_data['mobile_hidden'] ?? 0),
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    );

                    if ($existing_rule && $replace_existing) {
                        // Update existing rule
                        $wpdb->update($rules_table, $rule_insert_data, array('id' => $existing_rule->id));
                        $updated_count++;
                    } else {
                        // Insert new rule
                        $wpdb->insert($rules_table, $rule_insert_data);
                        $imported_count++;
                    }
                }
            }

            // REMOVED: Tab settings processing
            // No longer process tab_settings from import data

            // Commit transaction
            $wpdb->query('COMMIT');

            // Clear any caches
            wp_cache_flush();

            $result = array(
                'success' => true,
                'imported' => $imported_count,
                'updated' => $updated_count,
                'skipped' => $skipped_count,
                'errors' => $errors
            );

            do_action('spt_template_imported', $template_data, $result);

            return $result;

        } catch (Exception $e) {
            // Rollback transaction
            $wpdb->query('ROLLBACK');

            return new WP_Error('import_failed', __('Import failed: ', 'smart-product-tabs') . $e->getMessage());
        }
    }
    

    

    
    /**
     * Validate template data
     */
    private function validate_template_data($template_data) {
        $errors = array();

        // Basic structure validation
        if (!is_array($template_data)) {
            $errors[] = __('Invalid template data format', 'smart-product-tabs');
            return $errors;
        }

        // Check for required fields
        if (!isset($template_data['rules']) || !is_array($template_data['rules'])) {
            $errors[] = __('No rules found in template', 'smart-product-tabs');
            return $errors;
        }

        // Validate each rule
        foreach ($template_data['rules'] as $index => $rule) {
            $rule_errors = $this->validate_rule_data($rule, $index);
            $errors = array_merge($errors, $rule_errors);
        }

        // REMOVED: Tab settings validation
        // No longer validate tab_settings

        return $errors;
    }
    
    /**
     * AJAX: Import template
     */
    public function ajax_import_template() {
        check_ajax_referer('spt_ajax_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $import_type = isset($_POST['import_type']) ? sanitize_text_field($_POST['import_type']) : '';
        
        // Fallback logic
        if (empty($import_type)) {
            if (!empty($_FILES['template_file'])) {
                $import_type = 'file';
            } elseif (isset($_POST['template_data'])) {
                $import_type = 'text';
            } else {
                $import_type = 'unknown';
            }
        }        
        

        if ($import_type === 'file') {
            // Handle file upload with enhanced validation
            if (empty($_FILES['template_file'])) {
                wp_send_json_error(__('No file uploaded', 'smart-product-tabs'));
                return;
            }

            $file = $_FILES['template_file'];

            // Enhanced file validation
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error_messages = array(
                    UPLOAD_ERR_INI_SIZE => 'File too large (server limit)',
                    UPLOAD_ERR_FORM_SIZE => 'File too large (form limit)',
                    UPLOAD_ERR_PARTIAL => 'File upload incomplete',
                    UPLOAD_ERR_NO_FILE => 'No file uploaded',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
                    UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
                );

                $error_message = $error_messages[$file['error']] ?? 'Unknown upload error';
                wp_send_json_error(__('File upload error: ', 'smart-product-tabs') . $error_message);
                return;
            }

            // Validate file type and extension
            $allowed_types = array('application/json', 'text/plain');
            if (!in_array($file['type'], $allowed_types) && !preg_match('/\.json$/i', $file['name'])) {
                wp_send_json_error(__('Only JSON files are allowed', 'smart-product-tabs'));
                return;
            }

            // Check file size (5MB limit)
            if ($file['size'] > 5 * 1024 * 1024) {
                wp_send_json_error(__('File too large. Maximum size is 5MB', 'smart-product-tabs'));
                return;
            }

            $content = file_get_contents($file['tmp_name']);

            if ($content === false) {
                wp_send_json_error(__('Failed to read uploaded file', 'smart-product-tabs'));
                return;
            }

            $template_data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error(__('Invalid JSON file: ', 'smart-product-tabs') . json_last_error_msg());
                return;
            }

        } elseif ($import_type === 'text') {
            // Handle text input with size validation
            //$template_json = $_POST['template_data'] ?? '';
            $template_json = isset($_POST['template_data']) ? stripslashes($_POST['template_data']) : '';
            //exit ($template_json);

            if (empty($template_json)) {
                wp_send_json_error(__('No template data provided', 'smart-product-tabs'));
                return;
            }

            // Check JSON size (prevent memory issues)
            if (strlen($template_json) > 2 * 1024 * 1024) { // 2MB limit for text
                wp_send_json_error(__('Template data too large. Maximum size is 2MB', 'smart-product-tabs'));
                return;
            }

            $template_data = json_decode($template_json, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error(__('Invalid JSON data: ', 'smart-product-tabs') . json_last_error_msg());
                return;
            }
        } else {
            wp_send_json_error(__('Invalid import type', 'smart-product-tabs'));
            return;
        }

        // Validate template data structure
        $validation_errors = $this->validate_template_data($template_data);
        if (!empty($validation_errors)) {
            wp_send_json_error(implode(', ', $validation_errors));
            return;
        }

        // Import template
        $replace_existing = isset($_POST['replace_existing']) && $_POST['replace_existing'] === '1';
        $result = $this->import_template_data($template_data, $replace_existing);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
            return;
        }

        // Enhanced success response
        $message = sprintf(
            __('%d rules imported', 'smart-product-tabs'),
            $result['imported']
        );

        if ($result['updated'] > 0) {
            $message .= sprintf(
                __(', %d rules updated', 'smart-product-tabs'),
                $result['updated']
            );
        }

        if ($result['skipped'] > 0) {
            $message .= sprintf(
                __(', %d rules skipped', 'smart-product-tabs'),
                $result['skipped']
            );
        }

        $result['message'] = $message;

        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Export rules
     */
    public function ajax_export_rules() {
        check_ajax_referer('spt_ajax_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        // REMOVED: include_settings parameter (no longer needed)
        // Always export as file format only
        $export_format = 'file';

        try {
            // UPDATED: Remove include_settings parameter
            $export_data = $this->export_rules();

            if (empty($export_data['rules'])) {
                wp_send_json_error(__('No rules found to export', 'smart-product-tabs'));
                return;
            }

            // Always save to file and return download URL
            $filename = 'spt_export_' . date('Y-m-d_H-i-s') . '.json';
            $filepath = $this->save_template_file($export_data, $filename);

            if ($filepath) {
                $download_url = SPT_PLUGIN_URL . 'assets/templates/' . $filename;
                wp_send_json_success(array(
                    'download_url' => $download_url,
                    'filename' => $filename,
                    'rules_count' => count($export_data['rules'])
                ));
            } else {
                // Fallback: return JSON data directly for blob download
                wp_send_json_success(array(
                    'data' => json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                    'filename' => $filename,
                    'rules_count' => count($export_data['rules'])
                ));
            }

        } catch (Exception $e) {
            wp_send_json_error(__('Export failed: ', 'smart-product-tabs') . $e->getMessage());
        }
    }
    
    /**
     * AJAX: Install built-in template
     */
    public function ajax_install_builtin_template() {
        check_ajax_referer('spt_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $template_key = sanitize_text_field($_POST['template_key'] ?? '');
        
        if (empty($template_key)) {
            wp_send_json_error(__('Template key is required', 'smart-product-tabs'));
            return;
        }
        
        $result = $this->install_builtin_template($template_key);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
            return;
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Create template from existing rules
     */
    public function create_template_from_rules($rule_ids, $template_name, $template_description = '') {
        global $wpdb;
        
        if (empty($rule_ids) || !is_array($rule_ids)) {
            return new WP_Error('invalid_rules', __('No rules selected', 'smart-product-tabs'));
        }
        
        $rules_table = $wpdb->prefix . 'spt_rules';
        $placeholders = implode(',', array_fill(0, count($rule_ids), '%d'));
        
        $rules = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $rules_table WHERE id IN ($placeholders)",
            $rule_ids
        ), ARRAY_A);
        
        if (empty($rules)) {
            return new WP_Error('no_rules_found', __('No rules found', 'smart-product-tabs'));
        }
        
        // Remove IDs for clean import
        foreach ($rules as &$rule) {
            unset($rule['id']);
            unset($rule['created_at']);
        }
        
        $template_data = array(
            'version' => SPT_VERSION,
            'export_date' => current_time('mysql'),
            'export_type' => 'custom_template',
            'name' => $template_name,
            'description' => $template_description,
            'author' => get_bloginfo('name'),
            'rules' => $rules
        );
        
        return $template_data;
    }
    


    

    

    
    /**
     * Preview template before import
     */
    public function preview_template($template_data) {
        if (!is_array($template_data) || !isset($template_data['rules'])) {
            return new WP_Error('invalid_data', __('Invalid template data', 'smart-product-tabs'));
        }
        
        $preview = array(
            'name' => $template_data['name'] ?? __('Unknown Template', 'smart-product-tabs'),
            'description' => $template_data['description'] ?? '',
            'version' => $template_data['version'] ?? '1.0',
            'rules_count' => count($template_data['rules']),
            'rules' => array()
        );
        
        foreach ($template_data['rules'] as $rule) {
            $preview['rules'][] = array(
                'rule_name' => $rule['rule_name'] ?? '',
                'tab_title' => $rule['tab_title'] ?? '',
                'conditions' => $this->format_conditions_preview($rule['conditions'] ?? ''),
                'user_role_condition' => $rule['user_role_condition'] ?? 'all',
                'priority' => $rule['priority'] ?? 10,
                'is_active' => $rule['is_active'] ?? 1
            );
        }
        
        return $preview;
    }
    
    /**
     * Format conditions for preview
     */
    private function format_conditions_preview($conditions_json) {
        if (empty($conditions_json)) {
            return __('All products', 'smart-product-tabs');
        }

        $conditions = json_decode($conditions_json, true);
        if (!$conditions || json_last_error() !== JSON_ERROR_NONE) {
            return __('Invalid conditions', 'smart-product-tabs');
        }

        $type = $conditions['type'] ?? 'all';
        $value = $conditions['value'] ?? '';
        $operator = $conditions['operator'] ?? 'in';

        switch ($type) {
            case 'category':
                if (is_array($value)) {
                    return sprintf(__('Categories: %s', 'smart-product-tabs'), implode(', ', $value));
                }
                return sprintf(__('Category: %s', 'smart-product-tabs'), $value);

            case 'product_type':
                return sprintf(__('Product type: %s', 'smart-product-tabs'), $value);

            case 'tag':
                if (is_array($value)) {
                    return sprintf(__('Tags: %s', 'smart-product-tabs'), implode(', ', $value));
                }
                return sprintf(__('Tag: %s', 'smart-product-tabs'), $value);

            case 'featured':
                return $value ? __('Featured products only', 'smart-product-tabs') : __('Non-featured products only', 'smart-product-tabs');

            case 'sale':
                return $value ? __('On sale products only', 'smart-product-tabs') : __('Non-sale products only', 'smart-product-tabs');

            case 'price_range':
                if (isset($value['min']) && isset($value['max'])) {
                    return sprintf(__('Price: %s - %s', 'smart-product-tabs'), 
                        wc_price($value['min']), wc_price($value['max']));
                }
                return __('Price range condition', 'smart-product-tabs');

            default:
                return __('Custom conditions', 'smart-product-tabs');
        }
    }
    
    /**
     * Import template from URL
     */
    public function import_template_from_url($url) {
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'headers' => array(
                'Accept' => 'application/json'
            )
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $template_data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_json', __('Invalid JSON response from URL', 'smart-product-tabs'));
        }
        
        // Validate template data
        $validation_errors = $this->validate_template_data($template_data);
        if (!empty($validation_errors)) {
            return new WP_Error('validation_failed', implode(', ', $validation_errors));
        }
        
        return $this->import_template_data($template_data);
    }
    
    
    
    /**
     * AJAX handler for template preview
     * Add this method to your SPT_Templates class
     */
    public function ajax_get_template_preview() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'spt_ajax_nonce')) {
            wp_send_json_error(__('Security check failed', 'smart-product-tabs'));
            return;
        }

        $template_key = sanitize_text_field($_POST['template_key']);

        if (empty($template_key)) {
            wp_send_json_error(__('Invalid template key', 'smart-product-tabs'));
            return;
        }

        try {
            // Get built-in templates
            $templates_manager = new SPT_Templates();
            $builtin_templates = $templates_manager->get_builtin_templates();

            if (!isset($builtin_templates[$template_key])) {
                wp_send_json_error(__('Template not found', 'smart-product-tabs'));
                return;
            }

            $template = $builtin_templates[$template_key];

            // Prepare template data for preview
            $preview_data = array(
                'name' => $template['name'],
                'description' => $template['description'] ?? '',
                'version' => $template['version'] ?? '1.0',
                'author' => $template['author'] ?? 'Smart Product Tabs',
                'tabs_count' => 0,
                'categories' => $template['categories'] ?? array(),
                'rules' => array()
            );

            // Load and parse template rules if available
            if (isset($template['rules']) && is_array($template['rules'])) {
                $preview_data['rules'] = $template['rules'];
                $preview_data['tabs_count'] = count($template['rules']);
            } elseif (isset($template['file']) && file_exists($template['file'])) {
                // Load from file if rules not directly available
                $file_content = file_get_contents($template['file']);
                $template_data = json_decode($file_content, true);

                if ($template_data && isset($template_data['rules'])) {
                    $preview_data['rules'] = $template_data['rules'];
                    $preview_data['tabs_count'] = count($template_data['rules']);
                }
            }

            wp_send_json_success($preview_data);

        } catch (Exception $e) {
            error_log('Template preview error: ' . $e->getMessage());
            wp_send_json_error(__('Failed to load template preview', 'smart-product-tabs'));
        }
    } 
    
    /**
     * Validate individual rule data
     */
    private function validate_rule_data($rule_data, $index) {
        $errors = array();

        // Required fields
        $required_fields = array('rule_name', 'tab_title');
        foreach ($required_fields as $field) {
            if (empty($rule_data[$field])) {
                $errors[] = sprintf(__('Rule %d: Missing required field "%s"', 'smart-product-tabs'), $index + 1, $field);
            }
        }

        // Validate field lengths
        if (strlen($rule_data['rule_name'] ?? '') > 100) {
            $errors[] = sprintf(__('Rule %d: Rule name too long (max 100 characters)', 'smart-product-tabs'), $index + 1);
        }

        if (strlen($rule_data['tab_title'] ?? '') > 100) {
            $errors[] = sprintf(__('Rule %d: Tab title too long (max 100 characters)', 'smart-product-tabs'), $index + 1);
        }

        // Validate priority
        $priority = intval($rule_data['priority'] ?? 10);
        if ($priority < 1 || $priority > 100) {
            $errors[] = sprintf(__('Rule %d: Priority must be between 1 and 100', 'smart-product-tabs'), $index + 1);
        }

        return $errors;
    }    
    
    
    /**
     * Save template file to uploads directory
     */
    private function save_template_file($template_data, $filename) {
        // Create templates directory if it doesn't exist
        $templates_dir = SPT_PLUGIN_PATH . 'assets/templates/';
        if (!file_exists($templates_dir)) {
            wp_mkdir_p($templates_dir);
        }

        $filepath = $templates_dir . $filename;
        $json_data = json_encode($template_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if (file_put_contents($filepath, $json_data) !== false) {
            return $filepath;
        }

        return false;
    }    
    
    
    /**
     * Get template file contents for download
     */
    private function get_template_file_contents($filename) {
        $filepath = SPT_PLUGIN_PATH . 'assets/templates/' . $filename;

        if (!file_exists($filepath)) {
            return false;
        }

        return file_get_contents($filepath);
    }

    /**
     * Clean up old export files (optional cleanup method)
     */
    public function cleanup_old_export_files() {
        $templates_dir = SPT_PLUGIN_PATH . 'assets/templates/';

        if (!is_dir($templates_dir)) {
            return;
        }

        $files = glob($templates_dir . 'spt_export_*.json');
        $cutoff_time = time() - (24 * 60 * 60); // 24 hours ago

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                unlink($file);
            }
        }
    }    

    
       
    
}
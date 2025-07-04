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
        $this->templates_dir = SPT_PLUGIN_PATH . 'assets/templates/';
        
        add_action('wp_ajax_spt_import_template', array($this, 'ajax_import_template'));
        add_action('wp_ajax_spt_export_rules', array($this, 'ajax_export_rules'));
        add_action('wp_ajax_spt_install_builtin_template', array($this, 'ajax_install_builtin_template'));
        add_action('init', array($this, 'init'));
    }
    
    /**
     * Initialize
     */
    public function init() {
        // Create templates directory if it doesn't exist
        if (!file_exists($this->templates_dir)) {
            wp_mkdir_p($this->templates_dir);
        }
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
    public function export_rules($include_settings = false) {
        global $wpdb;
        
        // Get all rules
        $rules_table = $wpdb->prefix . 'spt_rules';
        $rules = $wpdb->get_results("SELECT * FROM $rules_table ORDER BY priority ASC", ARRAY_A);
        
        $export_data = array(
            'version' => SPT_VERSION,
            'export_date' => current_time('mysql'),
            'export_type' => 'custom_export',
            'name' => sprintf(__('Export from %s', 'smart-product-tabs'), get_bloginfo('name')),
            'description' => sprintf(__('Exported on %s', 'smart-product-tabs'), current_time('F j, Y')),
            'rules' => $rules
        );
        
        // Include tab settings if requested
        if ($include_settings) {
            $settings_table = $wpdb->prefix . 'spt_tab_settings';
            $settings = $wpdb->get_results("SELECT * FROM $settings_table ORDER BY sort_order ASC", ARRAY_A);
            $export_data['tab_settings'] = $settings;
        }
        
        return $export_data;
    }
    
    /**
     * Import template data
     */
    public function import_template_data($template_data, $replace_existing = false) {
        global $wpdb;
        
        if (!is_array($template_data)) {
            return new WP_Error('invalid_data', __('Invalid template data', 'smart-product-tabs'));
        }
        
        $rules_table = $wpdb->prefix . 'spt_rules';
        $imported_count = 0;
        $skipped_count = 0;
        $errors = array();
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Import rules
            if (isset($template_data['rules']) && is_array($template_data['rules'])) {
                foreach ($template_data['rules'] as $rule_data) {
                    // Check if rule already exists
                    if (!$replace_existing) {
                        $existing = $wpdb->get_var($wpdb->prepare(
                            "SELECT id FROM $rules_table WHERE rule_name = %s",
                            $rule_data['rule_name']
                        ));
                        
                        if ($existing) {
                            $skipped_count++;
                            continue;
                        }
                    }
                    
                    // Prepare rule data
                    $insert_data = array(
                        'rule_name' => sanitize_text_field($rule_data['rule_name']),
                        'tab_title' => sanitize_text_field($rule_data['tab_title']),
                        'tab_content' => wp_kses_post($rule_data['tab_content']),
                        'content_type' => sanitize_text_field($rule_data['content_type'] ?? 'rich_editor'),
                        'conditions' => $rule_data['conditions'],
                        'user_role_condition' => sanitize_text_field($rule_data['user_role_condition'] ?? 'all'),
                        'user_roles' => $rule_data['user_roles'] ?? '',
                        'priority' => intval($rule_data['priority'] ?? 10),
                        'is_active' => intval($rule_data['is_active'] ?? 1),
                        'mobile_hidden' => intval($rule_data['mobile_hidden'] ?? 0)
                    );
                    
                    // Insert or update rule
                    if ($replace_existing && isset($rule_data['id'])) {
                        $result = $wpdb->update($rules_table, $insert_data, array('id' => $rule_data['id']));
                    } else {
                        $result = $wpdb->insert($rules_table, $insert_data);
                    }
                    
                    if ($result !== false) {
                        $imported_count++;
                    } else {
                        $errors[] = sprintf(__('Failed to import rule: %s', 'smart-product-tabs'), $rule_data['rule_name']);
                    }
                }
            }
            
            // Import tab settings if available
            if (isset($template_data['tab_settings']) && is_array($template_data['tab_settings'])) {
                $settings_table = $wpdb->prefix . 'spt_tab_settings';
                
                foreach ($template_data['tab_settings'] as $setting_data) {
                    $setting_insert_data = array(
                        'tab_key' => sanitize_text_field($setting_data['tab_key']),
                        'tab_type' => sanitize_text_field($setting_data['tab_type']),
                        'custom_title' => sanitize_text_field($setting_data['custom_title'] ?? ''),
                        'is_enabled' => intval($setting_data['is_enabled'] ?? 1),
                        'sort_order' => intval($setting_data['sort_order'] ?? 10),
                        'mobile_hidden' => intval($setting_data['mobile_hidden'] ?? 0)
                    );
                    
                    // Use REPLACE to handle duplicates
                    $wpdb->replace($settings_table, $setting_insert_data);
                }
            }
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            // Clear any caches
            wp_cache_flush();
            
            $result = array(
                'success' => true,
                'imported' => $imported_count,
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
     * Save template to file
     */
    public function save_template_file($template_data, $filename) {
        if (!$filename) {
            $filename = 'spt_template_' . date('Y-m-d_H-i-s') . '.json';
        }
        
        // Ensure .json extension
        if (!preg_match('/\.json$/i', $filename)) {
            $filename .= '.json';
        }
        
        $filepath = $this->templates_dir . $filename;
        
        $json_data = json_encode($template_data, JSON_PRETTY_PRINT);
        
        if (file_put_contents($filepath, $json_data)) {
            return $filepath;
        }
        
        return false;
    }
    
    /**
     * Load template from file
     */
    public function load_template_file($filepath) {
        if (!file_exists($filepath)) {
            return new WP_Error('file_not_found', __('Template file not found', 'smart-product-tabs'));
        }
        
        $content = file_get_contents($filepath);
        $template_data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_json', __('Invalid JSON in template file', 'smart-product-tabs'));
        }
        
        return $template_data;
    }
    
    /**
     * Get saved templates
     */
    public function get_saved_templates() {
        $templates = array();
        
        if (!is_dir($this->templates_dir)) {
            return $templates;
        }
        
        $files = glob($this->templates_dir . '*.json');
        
        foreach ($files as $file) {
            $template_data = $this->load_template_file($file);
            
            if (!is_wp_error($template_data)) {
                $templates[] = array(
                    'file' => basename($file),
                    'filepath' => $file,
                    'name' => $template_data['name'] ?? basename($file, '.json'),
                    'description' => $template_data['description'] ?? '',
                    'version' => $template_data['version'] ?? '1.0',
                    'export_date' => $template_data['export_date'] ?? '',
                    'rules_count' => count($template_data['rules'] ?? array()),
                    'size' => filesize($file),
                    'modified' => filemtime($file)
                );
            }
        }
        
        return $templates;
    }
    
    /**
     * Delete template file
     */
    public function delete_template_file($filename) {
        $filepath = $this->templates_dir . $filename;
        
        if (file_exists($filepath) && unlink($filepath)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Validate template data
     */
    public function validate_template_data($template_data) {
        $errors = array();
        
        if (!is_array($template_data)) {
            $errors[] = __('Template data must be an array', 'smart-product-tabs');
            return $errors;
        }
        
        // Check required fields
        if (!isset($template_data['rules']) || !is_array($template_data['rules'])) {
            $errors[] = __('Template must contain rules array', 'smart-product-tabs');
        }
        
        // Validate rules
        if (isset($template_data['rules'])) {
            foreach ($template_data['rules'] as $index => $rule) {
                if (!isset($rule['rule_name']) || empty($rule['rule_name'])) {
                    $errors[] = sprintf(__('Rule %d is missing rule_name', 'smart-product-tabs'), $index + 1);
                }
                
                if (!isset($rule['tab_title']) || empty($rule['tab_title'])) {
                    $errors[] = sprintf(__('Rule %d is missing tab_title', 'smart-product-tabs'), $index + 1);
                }
                
                if (!isset($rule['tab_content'])) {
                    $errors[] = sprintf(__('Rule %d is missing tab_content', 'smart-product-tabs'), $index + 1);
                }
            }
        }
        
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
        
        $import_type = sanitize_text_field($_POST['import_type'] ?? 'file');
        
        if ($import_type === 'file') {
            // Handle file upload
            if (empty($_FILES['template_file'])) {
                wp_send_json_error(__('No file uploaded', 'smart-product-tabs'));
                return;
            }
            
            $file = $_FILES['template_file'];
            
            // Validate file
            if ($file['error'] !== UPLOAD_ERR_OK) {
                wp_send_json_error(__('File upload error', 'smart-product-tabs'));
                return;
            }
            
            if ($file['type'] !== 'application/json' && !preg_match('/\.json$/i', $file['name'])) {
                wp_send_json_error(__('Only JSON files are allowed', 'smart-product-tabs'));
                return;
            }
            
            $content = file_get_contents($file['tmp_name']);
            $template_data = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error(__('Invalid JSON file', 'smart-product-tabs'));
                return;
            }
            
        } elseif ($import_type === 'text') {
            // Handle text input
            $template_json = sanitize_textarea_field($_POST['template_data'] ?? '');
            
            if (empty($template_json)) {
                wp_send_json_error(__('No template data provided', 'smart-product-tabs'));
                return;
            }
            
            $template_data = json_decode($template_json, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error(__('Invalid JSON data', 'smart-product-tabs'));
                return;
            }
        } else {
            wp_send_json_error(__('Invalid import type', 'smart-product-tabs'));
            return;
        }
        
        // Validate template data
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
        
        $include_settings = isset($_POST['include_settings']) && $_POST['include_settings'] === '1';
        $export_format = sanitize_text_field($_POST['export_format'] ?? 'json');
        
        $export_data = $this->export_rules($include_settings);
        
        if ($export_format === 'file') {
            // Save to file and return download URL
            $filename = 'spt_export_' . date('Y-m-d_H-i-s') . '.json';
            $filepath = $this->save_template_file($export_data, $filename);
            
            if ($filepath) {
                $download_url = SPT_PLUGIN_URL . 'assets/templates/' . $filename;
                wp_send_json_success(array(
                    'download_url' => $download_url,
                    'filename' => $filename
                ));
            } else {
                wp_send_json_error(__('Failed to save export file', 'smart-product-tabs'));
            }
        } else {
            // Return JSON data
            wp_send_json_success(array(
                'data' => json_encode($export_data, JSON_PRETTY_PRINT),
                'filename' => 'spt_export_' . date('Y-m-d_H-i-s') . '.json'
            ));
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
     * Get template statistics
     */
    public function get_template_statistics() {
        $builtin_templates = $this->get_builtin_templates();
        $saved_templates = $this->get_saved_templates();
        
        return array(
            'builtin_count' => count($builtin_templates),
            'saved_count' => count($saved_templates),
            'total_builtin_rules' => array_sum(array_column($builtin_templates, 'tabs_count')),
            'total_saved_rules' => array_sum(array_column($saved_templates, 'rules_count')),
            'templates_dir_size' => $this->get_templates_directory_size()
        );
    }
    
    /**
     * Get templates directory size
     */
    private function get_templates_directory_size() {
        $size = 0;
        
        if (is_dir($this->templates_dir)) {
            $files = glob($this->templates_dir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    $size += filesize($file);
                }
            }
        }
        
        return $size;
    }
    
    /**
     * Backup current rules before import
     */
    public function backup_current_rules() {
        $backup_data = $this->export_rules(true);
        $backup_data['backup_type'] = 'auto_backup';
        $backup_data['name'] = sprintf(__('Auto Backup - %s', 'smart-product-tabs'), current_time('F j, Y g:i A'));
        
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.json';
        return $this->save_template_file($backup_data, $filename);
    }
    
    /**
     * Clean up old backup files
     */
    public function cleanup_old_backups($keep_days = 30) {
        $cutoff_time = time() - ($keep_days * DAY_IN_SECONDS);
        $files = glob($this->templates_dir . 'backup_*.json');
        $deleted_count = 0;
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                if (unlink($file)) {
                    $deleted_count++;
                }
            }
        }
        
        return $deleted_count;
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
        $conditions = json_decode($conditions_json, true);
        
        if (empty($conditions) || !isset($conditions['type'])) {
            return __('All Products', 'smart-product-tabs');
        }
        
        $type = $conditions['type'];
        
        switch ($type) {
            case 'category':
                return __('Category-based', 'smart-product-tabs');
            case 'price_range':
                return __('Price-based', 'smart-product-tabs');
            case 'attribute':
                return __('Attribute-based', 'smart-product-tabs');
            case 'stock_status':
                return __('Stock-based', 'smart-product-tabs');
            default:
                return ucfirst($type);
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
}
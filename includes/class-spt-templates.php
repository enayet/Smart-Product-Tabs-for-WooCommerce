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
        add_action('wp_ajax_spt_get_template_preview', array($this, 'ajax_get_template_preview'));  

        add_action('init', array($this, 'init'));
    }
    
    
  
    
    
    /**
     * Initialize
     */
    public function init() {

    }

    /**
     * Get built-in templates from JSON files
     */
    public function get_builtin_templates() {
        $templates = array();
        
        // Define the available templates and their JSON files
        $template_files = array(
            'electronics' => 'electronics-pack.json',
            'fashion' => 'fashion-pack.json', 
            'digital' => 'digital-pack.json'
        );
        
        foreach ($template_files as $template_key => $filename) {
            $file_path = $this->templates_dir . $filename;
            
            if (file_exists($file_path)) {
                $json_content = file_get_contents($file_path);
                $template_data = json_decode($json_content, true);
                
                if ($template_data && json_last_error() === JSON_ERROR_NONE) {
                    // Add the template key for reference
                    $template_data['template_key'] = $template_key;
                    $template_data['source_file'] = $filename;
                    
                    // Ensure required fields exist
                    if (!isset($template_data['name'])) {
                        $template_data['name'] = ucfirst($template_key) . ' Template';
                    }
                    if (!isset($template_data['description'])) {
                        $template_data['description'] = 'Template loaded from ' . $filename;
                    }
                    if (!isset($template_data['tabs_count']) && isset($template_data['rules'])) {
                        $template_data['tabs_count'] = count($template_data['rules']);
                    }
                    
                    $templates[$template_key] = $template_data;
                } else {
                    debug_log('SPT Templates: Failed to parse JSON file: ' . $filename . ' - ' . json_last_error_msg());
                }
            } else {
                debug_log('SPT Templates: Template file not found: ' . $file_path);
            }
        }

        
        return $templates;
    }
    
    /**
     * Load template from JSON file
     */
    public function load_template_from_file($template_key) {
        $template_files = array(
            'electronics' => 'electronics-pack.json',
            'fashion' => 'fashion-pack.json', 
            'digital' => 'digital-pack.json'
        );
        
        if (!isset($template_files[$template_key])) {
            return new WP_Error('invalid_template_key', esc_html__('Invalid template key', 'smart-product-tabs-for-woocommerce'));
        }
        
        $filename = $template_files[$template_key];
        $file_path = $this->templates_dir . $filename;
        
        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', sprintf(esc_html__('Template file not found: %s', 'smart-product-tabs-for-woocommerce'), $filename));
        }
        
        $json_content = file_get_contents($file_path);
        if ($json_content === false) {
            return new WP_Error('file_read_error', sprintf(esc_html__('Could not read template file: %s', 'smart-product-tabs-for-woocommerce'), $filename));
        }
        
        $template_data = json_decode($json_content, true);
        if ($template_data === null || json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_parse_error', sprintf(esc_html__('Invalid JSON in template file: %s - %s', 'smart-product-tabs-for-woocommerce'), $filename, json_last_error_msg()));
        }
        
        return $template_data;
    }    

    /**
     * Install built-in template (updated to use JSON files)
     */
   
public function install_builtin_template($template_key, $remove_existing = true) {
    global $wpdb;
    
    try {
        // Get template data from JSON file
        $template_data = $this->load_template_from_file($template_key);
        
        if (is_wp_error($template_data)) {
            return $template_data;
        }
        
        if (!$template_data || !isset($template_data['rules']) || !is_array($template_data['rules'])) {
            return new WP_Error('template_not_found', esc_html__('Template not found or invalid', 'smart-product-tabs-for-woocommerce'));
        }
        
        $removed_count = 0;
        
        // Remove existing rules if requested
        if ($remove_existing) {
            $rules_table = $wpdb->prefix . 'spt_rules';
            $existing_count = $wpdb->get_var("SELECT COUNT(*) FROM $rules_table");
            
            if ($existing_count > 0) {
                $deleted = $wpdb->query("DELETE FROM $rules_table");
                if ($deleted !== false) {
                    $removed_count = $existing_count;
                }
            }
        }
        
        // Use existing import_template_data method to handle the actual import
        $import_result = $this->import_template_data($template_data, false); // false = don't replace existing since we already removed them
        
        if (is_wp_error($import_result)) {
            return $import_result;
        }
        
        // Return combined results
        return array(
            'imported' => $import_result['imported'] ?? 0,
            'removed' => $removed_count,
            'template_name' => $template_data['name'] ?? ucfirst($template_key) . ' Template'
        );
        
    } catch (Exception $e) {
        debug_log('SPT Template Installation Error: ' . $e->getMessage());
        return new WP_Error('installation_failed', esc_html__('Installation failed: ', 'smart-product-tabs-for-woocommerce') . $e->getMessage());
    }
}  
    
    
    
/**
 * Clear template-related caches
 */
private function clear_template_cache() {
    // Clear WordPress object cache
    wp_cache_flush();
    
    // Clear any plugin-specific caches
    delete_transient('spt_active_rules');
    delete_transient('spt_template_list');
    
    // Clear WooCommerce cache if available
    if (function_exists('wc_delete_product_transients')) {
        // Clear product transients to refresh tab display
        $products = get_posts(array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));
        
        foreach ($products as $product_id) {
            wc_delete_product_transients($product_id);
        }
    }
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
        'name' => sprintf(esc_html__('Export from %s', 'smart-product-tabs-for-woocommerce'), get_bloginfo('name')),
        'description' => sprintf(esc_html__('Exported on %s', 'smart-product-tabs-for-woocommerce'), current_time('F j, Y')),
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
                    'tab_content' => wp_kses_post($rule_data['tab_content'] ?? ''),
                    'content_type' => sanitize_text_field($rule_data['content_type'] ?? 'rich_editor'),
                    'conditions' => sanitize_text_field($rule_data['conditions'] ?? '{"type":"all"}'),
                    'user_role_condition' => sanitize_text_field($rule_data['user_role_condition'] ?? 'all'),
                    'user_roles' => sanitize_text_field($rule_data['user_roles'] ?? ''),
                    'priority' => intval($rule_data['priority'] ?? 10),
                    'is_active' => intval($rule_data['is_active'] ?? 1),
                    'mobile_hidden' => intval($rule_data['mobile_hidden'] ?? 0),
                    'created_at' => current_time('mysql')
                );

                if ($existing_rule && $replace_existing) {
                    // Update existing rule
                    $result = $wpdb->update($rules_table, $rule_insert_data, array('id' => $existing_rule->id));
                    if ($result !== false) {
                        $updated_count++;
                    } else {
                        $errors[] = 'Failed to update rule: ' . $rule_data['rule_name'];
                    }
                } else {
                    // Insert new rule
                    $result = $wpdb->insert($rules_table, $rule_insert_data);
                    if ($result !== false) {
                        $imported_count++;
                    } else {
                        $errors[] = 'Failed to insert rule: ' . $rule_data['rule_name'];
                    }
                }
            }
        }

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

        debug_log('SPT Import Exception: ' . $e->getMessage());
        return new WP_Error('import_failed', esc_html__('Import failed: ', 'smart-product-tabs-for-woocommerce') . $e->getMessage());
    }
}
    

    

    
    /**
     * Validate template data
     */
private function validate_template_data($template_data) {
    $errors = array();

    // Basic structure validation
    if (!is_array($template_data)) {
        $errors[] = esc_html__('Invalid template data format', 'smart-product-tabs-for-woocommerce');
        return $errors;
    }

    // Check for required fields
    if (!isset($template_data['rules']) || !is_array($template_data['rules'])) {
        $errors[] = esc_html__('No rules found in template', 'smart-product-tabs-for-woocommerce');
        return $errors;
    }

    if (empty($template_data['rules'])) {
        $errors[] = esc_html__('Template contains no rules', 'smart-product-tabs-for-woocommerce');
        return $errors;
    }

    // Validate each rule
    foreach ($template_data['rules'] as $index => $rule) {
        $rule_errors = $this->validate_rule_data($rule, $index);
        $errors = array_merge($errors, $rule_errors);
    }

    return $errors;
}
    
    /**
     * AJAX: Import template
     */
public function ajax_import_template() {
    // Debug logging
    debug_log('SPT Import: Starting template import');
    debug_log('SPT Import: POST data - ' . print_r($_POST, true));
    debug_log('SPT Import: FILES data - ' . print_r($_FILES, true));
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'spt_ajax_nonce')) {
        debug_log('SPT Import: Invalid nonce');
        wp_send_json_error('Security check failed');
        return;
    }
    
    if (!current_user_can('manage_woocommerce')) {
        debug_log('SPT Import: Insufficient permissions');
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    $import_type = sanitize_text_field($_POST['import_type'] ?? 'file');
    $template_data = null;
    
    if ($import_type === 'file') {
        // FIXED: Proper file upload handling
        if (empty($_FILES['template_file']['tmp_name'])) {
            debug_log('SPT Import: No file uploaded');
            wp_send_json_error(esc_html__('No file uploaded', 'smart-product-tabs-for-woocommerce'));
            return;
        }
        
        $file = $_FILES['template_file'];
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $upload_errors = array(
                UPLOAD_ERR_INI_SIZE => 'File too large (server limit)',
                UPLOAD_ERR_FORM_SIZE => 'File too large (form limit)', 
                UPLOAD_ERR_PARTIAL => 'File partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'No temporary directory',
                UPLOAD_ERR_CANT_WRITE => 'Cannot write to disk',
                UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
            );
            
            $error_message = $upload_errors[$file['error']] ?? 'Unknown upload error';
            debug_log('SPT Import: Upload error - ' . $error_message);
            wp_send_json_error(esc_html__('Upload error: ', 'smart-product-tabs-for-woocommerce') . $error_message);
            return;
        }
        
        // Validate file type
        $allowed_types = array('application/json', 'text/plain');
        $file_type = wp_check_filetype($file['name'], array('json' => 'application/json'));
        
        if (!$file_type['ext'] || !in_array($file_type['type'], $allowed_types)) {
            debug_log('SPT Import: Invalid file type - ' . $file['type']);
            wp_send_json_error(esc_html__('Invalid file type. Please upload a JSON file', 'smart-product-tabs-for-woocommerce'));
            return;
        }
        
        // Validate file size (5MB limit)
        if ($file['size'] > 5 * 1024 * 1024) {
            debug_log('SPT Import: File too large - ' . $file['size'] . ' bytes');
            wp_send_json_error(esc_html__('File too large. Maximum size is 5MB', 'smart-product-tabs-for-woocommerce'));
            return;
        }
        
        // FIXED: Secure file reading
        $upload_dir = wp_upload_dir();
        $temp_file = $upload_dir['basedir'] . '/spt_temp_' . uniqid() . '.json';
        
        // Move uploaded file to secure location
        if (!move_uploaded_file($file['tmp_name'], $temp_file)) {
            debug_log('SPT Import: Failed to move uploaded file');
            wp_send_json_error(esc_html__('Failed to process uploaded file', 'smart-product-tabs-for-woocommerce'));
            return;
        }
        
        // Read file contents
        $template_json = file_get_contents($temp_file);
        
        // Clean up temp file
        unlink($temp_file);
        
        if ($template_json === false) {
            debug_log('SPT Import: Failed to read file contents');
            wp_send_json_error(esc_html__('Failed to read uploaded file', 'smart-product-tabs-for-woocommerce'));
            return;
        }
        
        // Validate JSON
        $template_data = json_decode($template_json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            debug_log('SPT Import: Invalid JSON - ' . json_last_error_msg());
            wp_send_json_error(esc_html__('Invalid JSON file: ', 'smart-product-tabs-for-woocommerce') . json_last_error_msg());
            return;
        }
        
    } else {
        debug_log('SPT Import: Unsupported import type - ' . $import_type);
        wp_send_json_error(esc_html__('Unsupported import type', 'smart-product-tabs-for-woocommerce'));
        return;
    }
    
    // Validate template data structure
    $validation_errors = $this->validate_template_data($template_data);
    if (!empty($validation_errors)) {
        debug_log('SPT Import: Validation errors - ' . implode(', ', $validation_errors));
        wp_send_json_error(implode(', ', $validation_errors));
        return;
    }
    
    // Import template
    $replace_existing = isset($_POST['replace_existing']) && $_POST['replace_existing'] === '1';
    $result = $this->import_template_data($template_data, $replace_existing);
    
    if (is_wp_error($result)) {
        debug_log('SPT Import: Import failed - ' . $result->get_error_message());
        wp_send_json_error($result->get_error_message());
        return;
    }
    
    // Enhanced success response
    $message = sprintf(
        esc_html__('%d rules imported', 'smart-product-tabs-for-woocommerce'),
        $result['imported']
    );
    
    if ($result['updated'] > 0) {
        $message .= sprintf(
            esc_html__(', %d rules updated', 'smart-product-tabs-for-woocommerce'),
            $result['updated']
        );
    }
    
    if ($result['skipped'] > 0) {
        $message .= sprintf(
            esc_html__(', %d rules skipped', 'smart-product-tabs-for-woocommerce'),
            $result['skipped']
        );
    }
    
    $result['message'] = $message;
    
    debug_log('SPT Import: Success - ' . $message);
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

    try {
        $export_data = $this->export_rules();

        if (empty($export_data['rules'])) {
            wp_send_json_error(esc_html__('No rules found to export', 'smart-product-tabs-for-woocommerce'));
            return;
        }

        // Always return JSON data for direct blob download
        $filename = 'spt_export_' . date('Y-m-d_H-i-s') . '.json';
        wp_send_json_success(array(
            'data' => json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'filename' => $filename,
            'rules_count' => count($export_data['rules'])
        ));

    } catch (Exception $e) {
        wp_send_json_error(esc_html__('Export failed: ', 'smart-product-tabs-for-woocommerce') . $e->getMessage());
    }
}

    
    /**
     * AJAX: Install built-in template
     */
    
    
public function ajax_install_builtin_template() {
    check_ajax_referer('spt_ajax_nonce', 'nonce');
    
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(esc_html__('Insufficient permissions', 'smart-product-tabs-for-woocommerce'));
        return;
    }
    
    $template_key = sanitize_text_field($_POST['template_key'] ?? '');
    $remove_existing = boolval($_POST['remove_existing'] ?? true);
    
    if (empty($template_key)) {
        wp_send_json_error(esc_html__('Template key is required', 'smart-product-tabs-for-woocommerce'));
        return;
    }
    
    // Install template with existing tabs removal
    $result = $this->install_builtin_template($template_key, $remove_existing);
    
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
        return;
    }
    
    // Return success with detailed information
    wp_send_json_success(array(
        'imported' => $result['imported'],
        'removed' => $result['removed'],
        'template_name' => $result['template_name'],
        'message' => sprintf(
            esc_html__('Template "%s" installed successfully! %d rules imported, %d existing rules removed.', 'smart-product-tabs-for-woocommerce'),
            $result['template_name'],
            $result['imported'],
            $result['removed']
        )
    ));
}    
    
    
    
    /**
     * Create template from existing rules
     */
    public function create_template_from_rules($rule_ids, $template_name, $template_description = '') {
        global $wpdb;
        
        if (empty($rule_ids) || !is_array($rule_ids)) {
            return new WP_Error('invalid_rules', esc_html__('No rules selected', 'smart-product-tabs-for-woocommerce'));
        }
        
        $rules_table = $wpdb->prefix . 'spt_rules';
        $placeholders = implode(',', array_fill(0, count($rule_ids), '%d'));
        
        $rules = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $rules_table WHERE id IN ($placeholders)",
            $rule_ids
        ), ARRAY_A);
        
        if (empty($rules)) {
            return new WP_Error('no_rules_found', esc_html__('No rules found', 'smart-product-tabs-for-woocommerce'));
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
            return new WP_Error('invalid_data', esc_html__('Invalid template data', 'smart-product-tabs-for-woocommerce'));
        }
        
        $preview = array(
            'name' => $template_data['name'] ?? esc_html__('Unknown Template', 'smart-product-tabs-for-woocommerce'),
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
            return esc_html__('All products', 'smart-product-tabs-for-woocommerce');
        }

        $conditions = json_decode($conditions_json, true);
        if (!$conditions || json_last_error() !== JSON_ERROR_NONE) {
            return esc_html__('Invalid conditions', 'smart-product-tabs-for-woocommerce');
        }

        $type = $conditions['type'] ?? 'all';
        $value = $conditions['value'] ?? '';
        $operator = $conditions['operator'] ?? 'in';

        switch ($type) {
            case 'category':
                if (is_array($value)) {
                    return sprintf(esc_html__('Categories: %s', 'smart-product-tabs-for-woocommerce'), implode(', ', $value));
                }
                return sprintf(esc_html__('Category: %s', 'smart-product-tabs-for-woocommerce'), $value);

            case 'product_type':
                return sprintf(esc_html__('Product type: %s', 'smart-product-tabs-for-woocommerce'), $value);

            case 'tag':
                if (is_array($value)) {
                    return sprintf(esc_html__('Tags: %s', 'smart-product-tabs-for-woocommerce'), implode(', ', $value));
                }
                return sprintf(esc_html__('Tag: %s', 'smart-product-tabs-for-woocommerce'), $value);

            case 'featured':
                return $value ? esc_html__('Featured products only', 'smart-product-tabs-for-woocommerce') : esc_html__('Non-featured products only', 'smart-product-tabs-for-woocommerce');

            case 'sale':
                return $value ? esc_html__('On sale products only', 'smart-product-tabs-for-woocommerce') : esc_html__('Non-sale products only', 'smart-product-tabs-for-woocommerce');

            case 'price_range':
                if (isset($value['min']) && isset($value['max'])) {
                    return sprintf(esc_html__('Price: %s - %s', 'smart-product-tabs-for-woocommerce'), 
                        wc_price($value['min']), wc_price($value['max']));
                }
                return esc_html__('Price range condition', 'smart-product-tabs-for-woocommerce');

            default:
                return esc_html__('Custom conditions', 'smart-product-tabs-for-woocommerce');
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
            return new WP_Error('invalid_json', esc_html__('Invalid JSON response from URL', 'smart-product-tabs-for-woocommerce'));
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
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'spt_ajax_nonce')) {
        wp_send_json_error(esc_html__('Security check failed', 'smart-product-tabs-for-woocommerce'));
        return;
    }

    $template_key = sanitize_text_field($_POST['template_key'] ?? '');

    if (empty($template_key)) {
        wp_send_json_error(esc_html__('Invalid template key', 'smart-product-tabs-for-woocommerce'));
        return;
    }

    try {
        // Load template from JSON file using the existing method
        $template_data = $this->load_template_from_file($template_key);
        
        if (is_wp_error($template_data)) {
            wp_send_json_error($template_data->get_error_message());
            return;
        }

        // Prepare template data for preview with all necessary information
        $preview_data = array(
            'name' => $template_data['name'] ?? 'Unknown Template',
            'description' => $template_data['description'] ?? 'No description available',
            'version' => $template_data['version'] ?? '1.0',
            'author' => $template_data['author'] ?? 'Unknown Author',
            'rules_count' => isset($template_data['rules']) ? count($template_data['rules']) : 0,
            'rules' => array()
        );

        // Process rules for preview
        if (isset($template_data['rules']) && is_array($template_data['rules'])) {
            foreach ($template_data['rules'] as $rule) {
                $preview_rule = array(
                    'tab_title' => $rule['tab_title'] ?? 'Untitled Tab',
                    'rule_name' => $rule['rule_name'] ?? 'Unnamed Rule',
                    'priority' => $rule['priority'] ?? 10,
                    'is_active' => isset($rule['is_active']) ? (bool)$rule['is_active'] : true,
                    'conditions' => $rule['conditions'] ?? array()
                );
                
                $preview_data['rules'][] = $preview_rule;
            }
        }

        // Send successful response
        wp_send_json_success($preview_data);
        
    } catch (Exception $e) {
        debug_log('SPT Template Preview Error: ' . $e->getMessage());
        wp_send_json_error(esc_html__('Failed to load template preview', 'smart-product-tabs-for-woocommerce'));
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
            $errors[] = sprintf(esc_html__('Rule %d: Missing required field "%s"', 'smart-product-tabs-for-woocommerce'), $index + 1, $field);
        }
    }

    // Validate field lengths
    if (isset($rule_data['rule_name']) && strlen($rule_data['rule_name']) > 255) {
        $errors[] = sprintf(esc_html__('Rule %d: Rule name too long (max 255 characters)', 'smart-product-tabs-for-woocommerce'), $index + 1);
    }

    if (isset($rule_data['tab_title']) && strlen($rule_data['tab_title']) > 255) {
        $errors[] = sprintf(esc_html__('Rule %d: Tab title too long (max 255 characters)', 'smart-product-tabs-for-woocommerce'), $index + 1);
    }

    // Validate priority
    if (isset($rule_data['priority'])) {
        $priority = intval($rule_data['priority']);
        if ($priority < 1 || $priority > 100) {
            $errors[] = sprintf(esc_html__('Rule %d: Priority must be between 1 and 100', 'smart-product-tabs-for-woocommerce'), $index + 1);
        }
    }

    return $errors;
}     
    
    
}
/**
 * Admin JavaScript for Smart Product Tabs (Simplified & Fixed)
 */

(function($) {
    'use strict';

    /**
     * Main SPT Admin object
     */
    var SPTAdmin = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initSortable();
            this.initConditionalFields();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Condition handling
            $(document).on('change', '#condition_type', this.handleConditionChange);
            $(document).on('change', '#user_role_condition', this.handleUserRoleChange);
            $(document).on('change', 'input[name="content_type"]', this.handleContentTypeChange);

            // Form validation
            $(document).on('blur', '#rule_name, #tab_title', this.validateRequiredField);
            $(document).on('submit', 'form', this.validateForm);
            
            // Real-time condition updates
            $(document).on('change blur', '.condition-field input, .condition-field select', function() {
                $(this).removeClass('error');
                SPTAdmin.updateConditionPreview();
            });
        },

        /**
         * Initialize sortable functionality
         */
        initSortable: function() {
            if ($('#sortable-tabs').length) {
                $('#sortable-tabs').sortable({
                    handle: '.tab-handle',
                    placeholder: 'tab-placeholder',
                    axis: 'y',
                    opacity: 0.8,
                    update: function(event, ui) {
                        SPTAdmin.updateTabOrder();
                    }
                });
            }
        },

        /**
         * Initialize conditional fields
         */
        initConditionalFields: function() {
            // Initialize condition fields visibility
            this.handleConditionChange();
            this.handleUserRoleChange();
            this.handleContentTypeChange();
        },

        /**
         * Handle condition type change
         */
        handleConditionChange: function() {
            var conditionType = $('#condition_type').val();
            
            // Hide all condition fields
            $('.condition-field').hide();
            
            // Show relevant field
            $('.condition-field[data-condition="' + conditionType + '"]').show();
            
            // Update condition preview
            SPTAdmin.updateConditionPreview();
        },

        /**
         * Handle user role condition change
         */
        handleUserRoleChange: function() {
            var roleCondition = $('#user_role_condition').val();
            
            if (roleCondition === 'specific_role') {
                $('#role_selector').show();
            } else {
                $('#role_selector').hide();
            }
        },

        /**
         * Handle content type change
         */
        handleContentTypeChange: function() {
            var contentType = $('input[name="content_type"]:checked').val();
            
            if (contentType === 'rich_editor') {
                $('#rich_editor_container').show();
                $('#plain_text_container').hide();
            } else {
                $('#rich_editor_container').hide();
                $('#plain_text_container').show();
            }
        },

        /**
         * Update condition preview in real-time
         */
        updateConditionPreview: function() {
            var conditionType = $('#condition_type').val();
            var previewText = '';
            
            switch (conditionType) {
                case 'all':
                    previewText = 'This tab will show on all products';
                    break;
                case 'category':
                    var selectedCategories = $('select[name="condition_category[]"] option:selected');
                    if (selectedCategories.length > 0) {
                        var categoryNames = [];
                        selectedCategories.each(function() {
                            categoryNames.push($(this).text().replace(/^\s+/, '').replace(/\s+\(\d+\)$/, ''));
                        });
                        previewText = 'This tab will show on products in: ' + categoryNames.join(', ');
                    } else {
                        previewText = 'No categories selected';
                    }
                    break;
                case 'price_range':
                    var minPrice = $('input[name="condition_price_min"]').val() || '0';
                    var maxPrice = $('input[name="condition_price_max"]').val() || '∞';
                    previewText = 'This tab will show on products priced between ' + minPrice + ' and ' + maxPrice;
                    break;

                case 'attribute':
                    var attribute = $('select[name="condition_attribute"]').val();
                    var value = $('input[name="condition_attribute_value"]').val();
                    if (attribute && value) {
                        // Handle array values properly
                        var displayValue = Array.isArray(value) ? value.join(', ') : value;
                        previewText = 'This tab will show on products with ' + attribute + ' = ' + displayValue;
                    } else {
                        previewText = 'Select attribute and value';
                    }
                    break;                    
                    
                case 'stock_status':
                    var status = $('select[name="condition_stock_status"]').val();
                    previewText = 'This tab will show on products that are ' + status;
                    break;
                case 'custom_field':
                    var key = $('input[name="condition_custom_field_key"]').val();
                    var value = $('input[name="condition_custom_field_value"]').val();
                    var operator = $('select[name="condition_custom_field_operator"]').val();
                    if (key) {
                        previewText = 'This tab will show on products where custom field "' + key + '" ' + operator + ' "' + value + '"';
                    } else {
                        previewText = 'Enter custom field key';
                    }
                    break;
                default:
                    previewText = 'This tab will show based on ' + conditionType + ' condition';
            }
            
            // Update or create preview element
            var $preview = $('.condition-preview');
            if ($preview.length === 0) {
                $preview = $('<div class="condition-preview"></div>');
                $('#condition_details').append($preview);
            }
            $preview.text(previewText);
        },

        /**
         * Update tab order (for sortable)
         */
        updateTabOrder: function() {
            $('#sortable-tabs .tab-item').each(function(index) {
                $(this).find('.sort-order-input').val(index + 1);
            });
        },

        /**
         * Validate required field
         */
        validateRequiredField: function() {
            var $field = $(this);
            if (!$field.val().trim()) {
                $field.addClass('error');
            } else {
                $field.removeClass('error');
            }
        },

        /**
         * Validate form before submission
         */
        validateForm: function(e) {
            var isValid = true;
            var $form = $(this);
            
            // Only validate if this is a rule form
            if (!$form.find('#rule_name').length) {
                return true;
            }
            
            // Check required fields
            $form.find('#rule_name, #tab_title').each(function() {
                if (!$(this).val().trim()) {
                    $(this).addClass('error');
                    isValid = false;
                } else {
                    $(this).removeClass('error');
                }
            });
            
            // Check content
            var contentType = $('input[name="content_type"]:checked').val();
            var content = '';
            
            if (contentType === 'rich_editor') {
                if (typeof tinyMCE !== 'undefined' && tinyMCE.get('tab_content')) {
                    content = tinyMCE.get('tab_content').getContent();
                } else {
                    content = $('#tab_content').val();
                }
            } else {
                content = $('#tab_content_plain').val();
            }
            
            if (!content.trim()) {
                isValid = false;
                alert('Tab content is required.');
            }
            
            // Validate condition-specific fields
            var conditionType = $('#condition_type').val();
            var conditionValidation = this.validateConditionFields(conditionType);
            if (!conditionValidation.valid) {
                isValid = false;
                alert('Please fix the following issues:\n' + conditionValidation.errors.join('\n'));
            }
            
            if (!isValid) {
                e.preventDefault();
                return false;
            }
            
            return true;
        },

        /**
         * Validate condition-specific fields
         */
        validateConditionFields: function(conditionType) {
            var result = { valid: true, errors: [] };
            
            switch (conditionType) {
                case 'category':
                    var selectedCategories = $('select[name="condition_category[]"] option:selected');
                    if (selectedCategories.length === 0) {
                        result.valid = false;
                        result.errors.push('Please select at least one category');
                        $('select[name="condition_category[]"]').addClass('error');
                    }
                    break;
                    
                case 'attribute':
                    var attribute = $('select[name="condition_attribute"]').val();
                    var value = $('input[name="condition_attribute_value"]').val();
                    
                    if (!attribute) {
                        result.valid = false;
                        result.errors.push('Please select an attribute');
                        $('select[name="condition_attribute"]').addClass('error');
                    }
                    
                    if (!value.trim()) {
                        result.valid = false;
                        result.errors.push('Attribute value is required');
                        $('input[name="condition_attribute_value"]').addClass('error');
                    }
                    break;
                    
                case 'price_range':
                    var minPrice = parseFloat($('input[name="condition_price_min"]').val()) || 0;
                    var maxPrice = parseFloat($('input[name="condition_price_max"]').val()) || 999999;
                    
                    if (minPrice < 0) {
                        result.valid = false;
                        result.errors.push('Minimum price must be 0 or greater');
                        $('input[name="condition_price_min"]').addClass('error');
                    }
                    
                    if (maxPrice <= minPrice) {
                        result.valid = false;
                        result.errors.push('Maximum price must be greater than minimum price');
                        $('input[name="condition_price_max"]').addClass('error');
                    }
                    break;
                    
                case 'custom_field':
                    var fieldKey = $('input[name="condition_custom_field_key"]').val();
                    
                    if (!fieldKey.trim()) {
                        result.valid = false;
                        result.errors.push('Custom field key is required');
                        $('input[name="condition_custom_field_key"]').addClass('error');
                    }
                    break;
            }
            
            return result;
        },

        /**
         * Show success message
         */
        showSuccess: function(message) {
            this.showNotice(message, 'success');
        },

        /**
         * Show error message
         */
        showError: function(message) {
            this.showNotice(message, 'error');
        },

        /**
         * Show notice
         */
        showNotice: function(message, type) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible spt-notice"><p>' + message + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss</span></button></div>');
            
            // Remove any existing notices
            $('.spt-notice').remove();
            
            // Add new notice
            $('.wrap h1').after($notice);
            
            // Handle dismiss
            $notice.on('click', '.notice-dismiss', function() {
                $notice.fadeOut(300, function() {
                    $notice.remove();
                });
            });
            
            // Auto-dismiss success notices
            if (type === 'success') {
                setTimeout(function() {
                    $notice.fadeOut(300, function() {
                        $notice.remove();
                    });
                }, 5000);
            }
        }
    };

    /**
     * Template Manager (simplified)
     */
    var SPTTemplateManager = {
        
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $(document).on('click', '.template-install', this.installTemplate);
            $(document).on('submit', '.template-upload-form', this.uploadTemplate);
        },

        installTemplate: function(e) {
            e.preventDefault();
            
            // Only proceed if we have AJAX capabilities
            if (typeof spt_ajax === 'undefined') {
                alert('AJAX not available. Please refresh the page and try again.');
                return;
            }
            
            var templateKey = $(this).data('template-key');
            var templateName = $(this).data('template-name');
            
            if (!confirm('Install template "' + templateName + '"? This will add new tab rules to your site.')) {
                return;
            }
            
            $(this).prop('disabled', true).text('Installing...');
            
            $.ajax({
                url: spt_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'spt_install_builtin_template',
                    template_key: templateKey,
                    nonce: spt_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        SPTAdmin.showSuccess('Template installed successfully! ' + response.data.imported + ' rules imported.');
                        
                        // Reload page after delay
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        SPTAdmin.showError(response.data);
                    }
                },
                error: function() {
                    SPTAdmin.showError('An error occurred. Please try again.');
                },
                complete: function() {
                    $('.template-install').prop('disabled', false).text('Install');
                }
            });
        },

        uploadTemplate: function(e) {
            e.preventDefault();
            
            // Only proceed if we have AJAX capabilities
            if (typeof spt_ajax === 'undefined') {
                alert('AJAX not available. Please refresh the page and try again.');
                return;
            }
            
            var formData = new FormData(this);
            formData.append('action', 'spt_import_template');
            formData.append('nonce', spt_ajax.nonce);
            
            var $submitBtn = $(this).find('input[type="submit"]');
            $submitBtn.prop('disabled', true).val('Uploading...');
            
            $.ajax({
                url: spt_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        var message = 'Import completed: ' + response.data.imported + ' rules imported, ' + response.data.skipped + ' skipped';
                        SPTAdmin.showSuccess(message);
                        
                        // Reset form
                        e.target.reset();
                        
                        // Reload page after delay
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        SPTAdmin.showError(response.data);
                    }
                },
                error: function() {
                    SPTAdmin.showError('Upload failed. Please try again.');
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).val('Import Template');
                }
            });
        }
    };

    /**
     * Export functionality
     */
    var SPTExport = {
        
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $(document).on('click', '#export-rules', this.exportRules);
        },

        exportRules: function(e) {
            e.preventDefault();
            
            // Only proceed if we have AJAX capabilities
            if (typeof spt_ajax === 'undefined') {
                alert('AJAX not available. Please refresh the page and try again.');
                return;
            }
            
            var includeSettings = $('#export_include_settings').is(':checked');
            var exportFormat = $('#export_format').val();
            
            $(this).prop('disabled', true).text('Exporting...');
            
            $.ajax({
                url: spt_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'spt_export_rules',
                    include_settings: includeSettings ? '1' : '0',
                    export_format: exportFormat,
                    nonce: spt_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        if (exportFormat === 'file') {
                            // Trigger download
                            window.open(response.data.download_url);
                            SPTAdmin.showSuccess('Export completed successfully!');
                        } else {
                            // Show JSON data in a new window/tab for copy-paste
                            var newWindow = window.open('', '_blank');
                            newWindow.document.write('<pre>' + response.data.data + '</pre>');
                            newWindow.document.title = 'Export Data - ' + response.data.filename;
                            SPTAdmin.showSuccess('Export data opened in new tab. Copy and save the content.');
                        }
                    } else {
                        SPTAdmin.showError(response.data);
                    }
                },
                error: function() {
                    SPTAdmin.showError('Export failed. Please try again.');
                },
                complete: function() {
                    $('#export-rules').prop('disabled', false).text('Export Rules');
                }
            });
        }
    };

    /**
     * Initialize everything when document is ready
     */
    $(document).ready(function() {
        SPTAdmin.init();
        
        // Initialize additional modules based on page content
        if ($('.spt-templates').length) {
            SPTTemplateManager.init();
            SPTExport.init();
        }
    });

})(jQuery);
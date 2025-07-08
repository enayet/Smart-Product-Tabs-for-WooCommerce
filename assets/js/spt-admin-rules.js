/**
 * Smart Product Tabs - Admin Rules Management
 * Handles rule creation, editing, validation, and conditional fields
 */

(function($) {
    'use strict';

    /**
     * SPT Rules Management
     */
    var SPTRules = {
        
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
                SPTRules.updateConditionPreview();
            });

            // Rule actions
            $(document).on('click', '.rule-delete', this.deleteRule);
            $(document).on('click', '.rule-duplicate', this.duplicateRule);
            $(document).on('click', '.rule-toggle-status', this.toggleRuleStatus);
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
                        SPTRules.updateTabOrder();
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
            SPTRules.updateConditionPreview();
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
            var preview = 'Show this tab ';

            switch (conditionType) {
                case 'always':
                    preview += 'on all products';
                    break;
                case 'category':
                    var categories = $('#category_condition').val();
                    if (categories && categories.length) {
                        preview += 'only on products in: ' + categories.join(', ');
                    } else {
                        preview += 'when category is selected';
                    }
                    break;
                case 'product_type':
                    var productType = $('#product_type_condition').val();
                    if (productType) {
                        preview += 'only on ' + productType + ' products';
                    } else {
                        preview += 'when product type is selected';
                    }
                    break;
                case 'user_role':
                    var roleCondition = $('#user_role_condition').val();
                    if (roleCondition === 'logged_in') {
                        preview += 'only for logged-in users';
                    } else if (roleCondition === 'guest') {
                        preview += 'only for guest users';
                    } else if (roleCondition === 'specific_role') {
                        var roles = $('#specific_roles').val();
                        if (roles && roles.length) {
                            preview += 'only for users with roles: ' + roles.join(', ');
                        } else {
                            preview += 'when specific roles are selected';
                        }
                    }
                    break;
                default:
                    preview += 'when conditions are met';
            }

            $('#condition_preview').text(preview);
        },

        /**
         * Validate required field
         */
        validateRequiredField: function() {
            var $field = $(this);
            var value = $field.val().trim();
            
            if (!value) {
                $field.addClass('error');
                return false;
            } else {
                $field.removeClass('error');
                return true;
            }
        },

        /**
         * Validate form before submission
         */
        validateForm: function(e) {
            var isValid = true;
            var $form = $(this);

            // Check required fields
            $form.find('input[required], select[required], textarea[required]').each(function() {
                if (!SPTRules.validateRequiredField.call(this)) {
                    isValid = false;
                }
            });

            // Check rule name uniqueness (if we have AJAX available)
            var ruleName = $('#rule_name').val();
            if (ruleName && typeof spt_admin_ajax !== 'undefined') {
                // This would require an AJAX check to the backend
                // For now, we'll just do client-side validation
            }

            if (!isValid) {
                e.preventDefault();
                SPTRules.showError('Please fill in all required fields');
                
                // Focus on first error field
                $form.find('.error').first().focus();
            }

            return isValid;
        },

        /**
         * Update tab order after sorting
         */
        updateTabOrder: function() {
            if (typeof spt_admin_ajax === 'undefined') {
                console.log('AJAX not available for tab ordering');
                return;
            }

            var order = [];
            $('#sortable-tabs .tab-item').each(function(index) {
                order.push({
                    id: $(this).data('rule-id'),
                    priority: index + 1
                });
            });

            $.ajax({
                url: spt_admin_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'spt_update_tab_order',
                    order: order,
                    nonce: spt_admin_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        SPTRules.showSuccess('Tab order updated successfully');
                    } else {
                        SPTRules.showError('Failed to update tab order: ' + response.data);
                    }
                },
                error: function() {
                    SPTRules.showError('Error updating tab order');
                }
            });
        },

        /**
         * Delete rule
         */
        deleteRule: function(e) {
            e.preventDefault();
            
            var ruleId = $(this).data('rule-id');
            var ruleName = $(this).data('rule-name');
            var $row = $(this).closest('tr');

            if (!confirm('Delete rule "' + ruleName + '"? This cannot be undone.')) {
                return;
            }

            if (typeof spt_admin_ajax === 'undefined') {
                alert('AJAX not available. Please refresh the page and try again.');
                return;
            }

            $(this).prop('disabled', true).text('Deleting...');

            $.ajax({
                url: spt_admin_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'spt_delete_rule',
                    rule_id: ruleId,
                    nonce: spt_admin_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $row.fadeOut(300, function() {
                            $(this).remove();
                        });
                        SPTRules.showSuccess('Rule deleted successfully');
                    } else {
                        SPTRules.showError('Failed to delete rule: ' + response.data);
                    }
                },
                error: function() {
                    SPTRules.showError('Error deleting rule');
                },
                complete: function() {
                    $(this).prop('disabled', false).text('Delete');
                }
            });
        },

        /**
         * Duplicate rule
         */
        duplicateRule: function(e) {
            e.preventDefault();
            
            var ruleId = $(this).data('rule-id');
            var ruleName = $(this).data('rule-name');

            if (typeof spt_admin_ajax === 'undefined') {
                alert('AJAX not available. Please refresh the page and try again.');
                return;
            }

            $(this).prop('disabled', true).text('Duplicating...');

            $.ajax({
                url: spt_admin_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'spt_duplicate_rule',
                    rule_id: ruleId,
                    nonce: spt_admin_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload(); // Reload to show the new rule
                    } else {
                        SPTRules.showError('Failed to duplicate rule: ' + response.data);
                    }
                },
                error: function() {
                    SPTRules.showError('Error duplicating rule');
                },
                complete: function() {
                    $(this).prop('disabled', false).text('Duplicate');
                }
            });
        },

        /**
         * Toggle rule status (active/inactive)
         */
        toggleRuleStatus: function(e) {
            e.preventDefault();
            
            var ruleId = $(this).data('rule-id');
            var currentStatus = $(this).data('status');
            var newStatus = currentStatus === '1' ? '0' : '1';
            var $button = $(this);

            if (typeof spt_admin_ajax === 'undefined') {
                alert('AJAX not available. Please refresh the page and try again.');
                return;
            }

            $button.prop('disabled', true);

            $.ajax({
                url: spt_admin_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'spt_toggle_rule_status',
                    rule_id: ruleId,
                    status: newStatus,
                    nonce: spt_admin_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $button.data('status', newStatus);
                        $button.text(newStatus === '1' ? 'Deactivate' : 'Activate');
                        $button.toggleClass('button-secondary', newStatus === '1');
                        $button.toggleClass('button-primary', newStatus === '0');
                        
                        var statusText = newStatus === '1' ? 'Active' : 'Inactive';
                        $button.closest('tr').find('.rule-status').text(statusText);
                        
                        SPTRules.showSuccess('Rule status updated successfully');
                    } else {
                        SPTRules.showError('Failed to update rule status: ' + response.data);
                    }
                },
                error: function() {
                    SPTRules.showError('Error updating rule status');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
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
     * Initialize on document ready
     */
    $(document).ready(function() {
        SPTRules.init();
    });

    // Export for other modules to use
    window.SPTRules = SPTRules;

})(jQuery);
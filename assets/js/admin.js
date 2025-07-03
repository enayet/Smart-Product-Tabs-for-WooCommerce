/**
 * Admin JavaScript for Smart Product Tabs
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
            this.initRichEditor();
            this.initAnalytics();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Rule management
            $(document).on('click', '#add-new-rule', this.showRuleModal);
            $(document).on('click', '.edit-rule', this.editRule);
            $(document).on('click', '.delete-rule', this.deleteRule);
            $(document).on('submit', '#spt-rule-form', this.saveRule);
            $(document).on('click', '#cancel-rule', this.hideRuleModal);
            $(document).on('click', '.spt-modal-close', this.hideRuleModal);

            // Condition handling
            $(document).on('change', '#condition_type', this.handleConditionChange);
            $(document).on('change', '#user_role_condition', this.handleUserRoleChange);
            $(document).on('change', 'input[name="content_type"]', this.handleContentTypeChange);

            // Tab ordering
            $(document).on('click', '#save-tab-order', this.saveTabOrder);
            $(document).on('change', '.tab-enabled, .tab-mobile-hidden', this.toggleTabSetting);

            // Template management
            $(document).on('click', '.install-template', this.installTemplate);
            $(document).on('click', '#export-rules', this.exportRules);
            $(document).on('submit', '#import-template-form', this.importTemplate);
            $(document).on('change', '#import-type', this.handleImportTypeChange);

            // Analytics
            $(document).on('change', '#analytics-period', this.loadAnalyticsData);
            $(document).on('click', '.analytics-refresh', this.refreshAnalytics);

            // Modal handling
            $(document).on('click', '.spt-modal', function(e) {
                if (e.target === this) {
                    SPTAdmin.hideRuleModal();
                }
            });

            // Form validation
            $(document).on('blur', '#rule_name, #tab_title', this.validateRequiredField);
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
         * Initialize rich editor
         */
        initRichEditor: function() {
            // Handle editor switching
            this.handleContentTypeChange();
        },

        /**
         * Initialize analytics
         */
        initAnalytics: function() {
            if ($('.spt-analytics').length) {
                this.loadAnalyticsData();
                this.initAnalyticsCharts();
            }
        },

        /**
         * Show rule modal
         */
        showRuleModal: function(e) {
            e.preventDefault();
            
            // Reset form
            $('#spt-rule-form')[0].reset();
            $('#rule_id').val('');
            $('#modal-title').text(spt_admin_strings.add_new_rule);
            
            // Reset editor content
            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('tab_content')) {
                tinyMCE.get('tab_content').setContent('');
            }
            
            // Show modal
            $('#rule-form-modal').fadeIn(300);
            
            // Focus first field
            setTimeout(function() {
                $('#rule_name').focus();
            }, 350);
        },

        /**
         * Hide rule modal
         */
        hideRuleModal: function(e) {
            if (e) e.preventDefault();
            $('#rule-form-modal').fadeOut(300);
        },

        /**
         * Edit rule
         */
        editRule: function(e) {
            e.preventDefault();
            
            var ruleId = $(this).data('rule-id');
            
            // Show loading
            SPTAdmin.showLoading();
            
            // Get rule data via AJAX
            $.ajax({
                url: spt_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'spt_get_rule',
                    rule_id: ruleId,
                    nonce: spt_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        SPTAdmin.populateRuleForm(response.data);
                        $('#modal-title').text(spt_admin_strings.edit_rule);
                        $('#rule-form-modal').fadeIn(300);
                    } else {
                        SPTAdmin.showError(response.data);
                    }
                },
                error: function() {
                    SPTAdmin.showError(spt_admin_strings.ajax_error);
                },
                complete: function() {
                    SPTAdmin.hideLoading();
                }
            });
        },

        /**
         * Populate rule form with data
         */
        populateRuleForm: function(rule) {
            $('#rule_id').val(rule.id);
            $('#rule_name').val(rule.rule_name);
            $('#tab_title').val(rule.tab_title);
            
            // Set content type
            $('input[name="content_type"][value="' + rule.content_type + '"]').prop('checked', true);
            this.handleContentTypeChange();
            
            // Set content
            if (rule.content_type === 'rich_editor') {
                if (typeof tinyMCE !== 'undefined' && tinyMCE.get('tab_content')) {
                    tinyMCE.get('tab_content').setContent(rule.tab_content);
                } else {
                    $('#tab_content').val(rule.tab_content);
                }
            } else {
                $('#tab_content_plain').val(rule.tab_content);
            }
            
            // Set conditions
            var conditions = JSON.parse(rule.conditions || '{}');
            $('#condition_type').val(conditions.type || 'all').trigger('change');
            
            // Set condition values based on type
            setTimeout(function() {
                SPTAdmin.setConditionValues(conditions);
            }, 100);
            
            // Set user role condition
            $('#user_role_condition').val(rule.user_role_condition).trigger('change');
            
            // Set user roles if specific roles
            if (rule.user_role_condition === 'specific_role' && rule.user_roles) {
                var roles = JSON.parse(rule.user_roles);
                roles.forEach(function(role) {
                    $('input[name="user_roles[]"][value="' + role + '"]').prop('checked', true);
                });
            }
            
            // Set other settings
            $('input[name="priority"]').val(rule.priority);
            $('input[name="mobile_hidden"]').prop('checked', rule.mobile_hidden == 1);
            $('input[name="is_active"]').prop('checked', rule.is_active == 1);
        },

        /**
         * Set condition values
         */
        setConditionValues: function(conditions) {
            switch (conditions.type) {
                case 'category':
                    if (conditions.value && Array.isArray(conditions.value)) {
                        conditions.value.forEach(function(catId) {
                            $('select[name="condition_category"] option[value="' + catId + '"]').prop('selected', true);
                        });
                    }
                    break;
                case 'price_range':
                    $('input[name="condition_price_min"]').val(conditions.min || '');
                    $('input[name="condition_price_max"]').val(conditions.max || '');
                    break;
                case 'stock_status':
                    $('select[name="condition_stock_status"]').val(conditions.value || 'instock');
                    break;
            }
        },

        /**
         * Delete rule
         */
        deleteRule: function(e) {
            e.preventDefault();
            
            var ruleId = $(this).data('rule-id');
            var ruleName = $(this).closest('tr').find('td:first strong').text();
            
            if (!confirm(spt_admin_strings.confirm_delete.replace('%s', ruleName))) {
                return;
            }
            
            // Show loading
            SPTAdmin.showLoading();
            
            $.ajax({
                url: spt_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'spt_delete_rule',
                    rule_id: ruleId,
                    nonce: spt_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Remove row from table
                        $('tr[data-rule-id="' + ruleId + '"]').fadeOut(300, function() {
                            $(this).remove();
                            SPTAdmin.checkEmptyTable();
                        });
                        SPTAdmin.showSuccess(response.data);
                    } else {
                        SPTAdmin.showError(response.data);
                    }
                },
                error: function() {
                    SPTAdmin.showError(spt_admin_strings.ajax_error);
                },
                complete: function() {
                    SPTAdmin.hideLoading();
                }
            });
        },

        /**
         * Save rule
         */
        saveRule: function(e) {
            e.preventDefault();
            
            // Validate form
            if (!SPTAdmin.validateRuleForm()) {
                return;
            }
            
            // Get form data
            var formData = new FormData(this);
            formData.append('action', 'spt_save_rule');
            formData.append('nonce', spt_ajax.nonce);
            
            // Get content from appropriate editor
            var contentType = $('input[name="content_type"]:checked').val();
            if (contentType === 'rich_editor') {
                if (typeof tinyMCE !== 'undefined' && tinyMCE.get('tab_content')) {
                    formData.set('tab_content', tinyMCE.get('tab_content').getContent());
                }
            } else {
                formData.set('tab_content', $('#tab_content_plain').val());
            }
            
            // Show loading
            SPTAdmin.showLoading();
            
            $.ajax({
                url: spt_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        SPTAdmin.showSuccess(response.data);
                        SPTAdmin.hideRuleModal();
                        
                        // Reload page to show updated rules
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        SPTAdmin.showError(response.data);
                    }
                },
                error: function() {
                    SPTAdmin.showError(spt_admin_strings.ajax_error);
                },
                complete: function() {
                    SPTAdmin.hideLoading();
                }
            });
        },

        /**
         * Validate rule form
         */
        validateRuleForm: function() {
            var isValid = true;
            var errors = [];
            
            // Check required fields
            if (!$('#rule_name').val().trim()) {
                errors.push(spt_admin_strings.rule_name_required);
                $('#rule_name').addClass('error');
                isValid = false;
            } else {
                $('#rule_name').removeClass('error');
            }
            
            if (!$('#tab_title').val().trim()) {
                errors.push(spt_admin_strings.tab_title_required);
                $('#tab_title').addClass('error');
                isValid = false;
            } else {
                $('#tab_title').removeClass('error');
            }
            
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
                errors.push(spt_admin_strings.content_required);
                isValid = false;
            }
            
            if (!isValid) {
                SPTAdmin.showError(errors.join('<br>'));
            }
            
            return isValid;
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
         * Update tab order
         */
        updateTabOrder: function() {
            // Visual feedback
            $('#save-tab-order').addClass('button-primary-disabled').text(spt_admin_strings.saving);
        },

        /**
         * Save tab order
         */
        saveTabOrder: function(e) {
            e.preventDefault();
            
            var tabOrder = [];
            $('#sortable-tabs .tab-item').each(function(index) {
                tabOrder.push({
                    tab_key: $(this).data('tab-key'),
                    sort_order: index + 1
                });
            });
            
            // Show loading
            SPTAdmin.showLoading();
            
            $.ajax({
                url: spt_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'spt_update_tab_order',
                    tab_order: tabOrder,
                    nonce: spt_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        SPTAdmin.showSuccess(spt_admin_strings.order_saved);
                        $('#save-tab-order').removeClass('button-primary-disabled').text(spt_admin_strings.save_order);
                    } else {
                        SPTAdmin.showError(response.data);
                    }
                },
                error: function() {
                    SPTAdmin.showError(spt_admin_strings.ajax_error);
                },
                complete: function() {
                    SPTAdmin.hideLoading();
                }
            });
        },

        /**
         * Toggle tab setting
         */
        toggleTabSetting: function() {
            var $this = $(this);
            var tabKey = $this.closest('.tab-item').data('tab-key');
            var setting = $this.hasClass('tab-enabled') ? 'enabled' : 'mobile_hidden';
            var value = $this.is(':checked') ? 1 : 0;
            
            $.ajax({
                url: spt_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'spt_toggle_tab',
                    tab_key: tabKey,
                    setting: setting,
                    value: value,
                    nonce: spt_ajax.nonce
                },
                success: function(response) {
                    if (!response.success) {
                        // Revert checkbox on error
                        $this.prop('checked', !$this.is(':checked'));
                        SPTAdmin.showError(response.data);
                    }
                },
                error: function() {
                    // Revert checkbox on error
                    $this.prop('checked', !$this.is(':checked'));
                    SPTAdmin.showError(spt_admin_strings.ajax_error);
                }
            });
        },

        /**
         * Install template
         */
        installTemplate: function(e) {
            e.preventDefault();
            
            var templateKey = $(this).data('template-key');
            var templateName = $(this).data('template-name');
            
            if (!confirm(spt_admin_strings.confirm_install_template.replace('%s', templateName))) {
                return;
            }
            
            // Show loading
            SPTAdmin.showLoading();
            
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
                        SPTAdmin.showSuccess(spt_admin_strings.template_installed.replace('%d', response.data.imported));
                        
                        // Reload page after delay
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        SPTAdmin.showError(response.data);
                    }
                },
                error: function() {
                    SPTAdmin.showError(spt_admin_strings.ajax_error);
                },
                complete: function() {
                    SPTAdmin.hideLoading();
                }
            });
        },

        /**
         * Export rules
         */
        exportRules: function(e) {
            e.preventDefault();
            
            var includeSettings = $('#export_include_settings').is(':checked');
            var exportFormat = $('#export_format').val();
            
            // Show loading
            SPTAdmin.showLoading();
            
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
                            SPTAdmin.showSuccess(spt_admin_strings.export_success);
                        } else {
                            // Show JSON data in modal
                            SPTAdmin.showExportModal(response.data.data, response.data.filename);
                        }
                    } else {
                        SPTAdmin.showError(response.data);
                    }
                },
                error: function() {
                    SPTAdmin.showError(spt_admin_strings.ajax_error);
                },
                complete: function() {
                    SPTAdmin.hideLoading();
                }
            });
        },

        /**
         * Show export modal
         */
        showExportModal: function(data, filename) {
            var modal = $('<div class="spt-export-modal">');
            modal.html(`
                <div class="spt-modal-content">
                    <div class="spt-modal-header">
                        <h3>${spt_admin_strings.export_data}</h3>
                        <span class="spt-modal-close">&times;</span>
                    </div>
                    <div class="spt-modal-body">
                        <p>${spt_admin_strings.copy_export_data}</p>
                        <textarea readonly style="width:100%;height:300px;">${data}</textarea>
                        <p>
                            <button type="button" class="button" id="copy-export-data">${spt_admin_strings.copy_to_clipboard}</button>
                            <button type="button" class="button-primary" id="download-export-data" data-filename="${filename}">${spt_admin_strings.download_file}</button>
                        </p>
                    </div>
                </div>
            `);
            
            $('body').append(modal);
            modal.fadeIn(300);
            
            // Handle copy to clipboard
            modal.on('click', '#copy-export-data', function() {
                modal.find('textarea').select();
                document.execCommand('copy');
                SPTAdmin.showSuccess(spt_admin_strings.copied_to_clipboard);
            });
            
            // Handle download
            modal.on('click', '#download-export-data', function() {
                var filename = $(this).data('filename');
                var blob = new Blob([data], { type: 'application/json' });
                var url = window.URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = filename;
                a.click();
                window.URL.revokeObjectURL(url);
            });
            
            // Handle close
            modal.on('click', '.spt-modal-close, .spt-export-modal', function(e) {
                if (e.target === this) {
                    modal.fadeOut(300, function() {
                        modal.remove();
                    });
                }
            });
        },

        /**
         * Import template
         */
        importTemplate: function(e) {
            e.preventDefault();
            
            var formData = new FormData(this);
            formData.append('action', 'spt_import_template');
            formData.append('nonce', spt_ajax.nonce);
            
            // Show loading
            SPTAdmin.showLoading();
            
            $.ajax({
                url: spt_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        var message = spt_admin_strings.import_success
                            .replace('%imported%', response.data.imported)
                            .replace('%skipped%', response.data.skipped);
                        
                        SPTAdmin.showSuccess(message);
                        
                        // Reset form
                        $('#import-template-form')[0].reset();
                        
                        // Reload page after delay
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        SPTAdmin.showError(response.data);
                    }
                },
                error: function() {
                    SPTAdmin.showError(spt_admin_strings.ajax_error);
                },
                complete: function() {
                    SPTAdmin.hideLoading();
                }
            });
        },

        /**
         * Handle import type change
         */
        handleImportTypeChange: function() {
            var importType = $(this).val();
            
            if (importType === 'file') {
                $('#import-file-field').show();
                $('#import-text-field').hide();
            } else {
                $('#import-file-field').hide();
                $('#import-text-field').show();
            }
        },

        /**
         * Load analytics data
         */
        loadAnalyticsData: function() {
            var period = $('#analytics-period').val() || 30;
            
            // Show loading
            $('.analytics-content').addClass('loading');
            
            $.ajax({
                url: spt_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'spt_get_analytics_data',
                    type: 'summary',
                    days: period,
                    nonce: spt_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        SPTAdmin.updateAnalyticsSummary(response.data);
                        SPTAdmin.loadAnalyticsCharts(period);
                    }
                },
                complete: function() {
                    $('.analytics-content').removeClass('loading');
                }
            });
        },

        /**
         * Update analytics summary
         */
        updateAnalyticsSummary: function(data) {
            $('.total-views').text(data.total_views || 0);
            $('.unique-products').text(data.unique_products || 0);
            $('.active-tabs').text(data.active_tabs || 0);
            $('.avg-daily-views').text(Math.round(data.avg_daily_views || 0));
        },

        /**
         * Initialize analytics charts
         */
        initAnalyticsCharts: function() {
            this.loadAnalyticsCharts(30);
        },

        /**
         * Load analytics charts
         */
        loadAnalyticsCharts: function(days) {
            // Load popular tabs chart
            $.ajax({
                url: spt_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'spt_get_analytics_data',
                    type: 'popular_tabs',
                    days: days,
                    nonce: spt_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        SPTAdmin.renderPopularTabsChart(response.data);
                    }
                }
            });
            
            // Load daily analytics chart
            $.ajax({
                url: spt_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'spt_get_analytics_data',
                    type: 'daily_analytics',
                    days: days,
                    nonce: spt_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        SPTAdmin.renderDailyChart(response.data);
                    }
                }
            });
        },

        /**
         * Render popular tabs chart
         */
        renderPopularTabsChart: function(data) {
            var $container = $('#popular-tabs-chart');
            if (!$container.length) return;
            
            $container.empty();
            
            if (!data || data.length === 0) {
                $container.html('<p>' + spt_admin_strings.no_data + '</p>');
                return;
            }
            
            var maxViews = Math.max.apply(Math, data.map(function(item) { return item.total_views; }));
            
            data.forEach(function(item) {
                var percentage = maxViews > 0 ? (item.total_views / maxViews) * 100 : 0;
                var bar = $('<div class="chart-bar">');
                bar.html(`
                    <div class="bar-label">${item.tab_key}</div>
                    <div class="bar-fill" style="width: ${percentage}%"></div>
                    <div class="bar-value">${item.total_views}</div>
                `);
                $container.append(bar);
            });
        },

        /**
         * Render daily chart
         */
        renderDailyChart: function(data) {
            var $container = $('#daily-chart');
            if (!$container.length) return;
            
            $container.empty();
            
            if (!data || data.length === 0) {
                $container.html('<p>' + spt_admin_strings.no_data + '</p>');
                return;
            }
            
            // Simple line chart
            var maxViews = Math.max.apply(Math, data.map(function(item) { return item.total_views; }));
            var chartHeight = 200;
            
            var svg = $(`<svg width="100%" height="${chartHeight}" viewBox="0 0 600 ${chartHeight}">`);
            var points = [];
            
            data.forEach(function(item, index) {
                var x = (index / (data.length - 1)) * 580 + 10;
                var y = chartHeight - 20 - ((item.total_views / maxViews) * (chartHeight - 40));
                points.push(x + ',' + y);
            });
            
            if (points.length > 1) {
                var polyline = $(`<polyline points="${points.join(' ')}" fill="none" stroke="#0073aa" stroke-width="2">`);
                svg.append(polyline);
            }
            
            $container.append(svg);
        },

        /**
         * Refresh analytics
         */
        refreshAnalytics: function(e) {
            e.preventDefault();
            SPTAdmin.loadAnalyticsData();
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
         * Check if table is empty
         */
        checkEmptyTable: function() {
            var $table = $('.spt-rules-list table tbody');
            if ($table.find('tr').length === 0) {
                $table.html('<tr><td colspan="6">' + spt_admin_strings.no_rules_found + '</td></tr>');
            }
        },

        /**
         * Show loading indicator
         */
        showLoading: function() {
            if (!$('#spt-loading').length) {
                $('body').append('<div id="spt-loading" class="spt-loading"><div class="spinner"></div></div>');
            }
            $('#spt-loading').show();
        },

        /**
         * Hide loading indicator
         */
        hideLoading: function() {
            $('#spt-loading').hide();
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
     * Localized strings object
     */
    var spt_admin_strings = {
        add_new_rule: 'Add New Rule',
        edit_rule: 'Edit Rule',
        confirm_delete: 'Are you sure you want to delete the rule "%s"?',
        confirm_install_template: 'Install template "%s"? This will add new tab rules to your site.',
        ajax_error: 'An error occurred. Please try again.',
        rule_name_required: 'Rule name is required',
        tab_title_required: 'Tab title is required',
        content_required: 'Tab content is required',
        saving: 'Saving...',
        save_order: 'Save Order',
        order_saved: 'Tab order saved successfully',
        template_installed: '%d rules imported successfully from template',
        export_success: 'Rules exported successfully',
        export_data: 'Export Data',
        copy_export_data: 'Copy the JSON data below:',
        copy_to_clipboard: 'Copy to Clipboard',
        download_file: 'Download File',
        copied_to_clipboard: 'Copied to clipboard!',
        import_success: 'Import completed: %imported% rules imported, %skipped% skipped',
        no_data: 'No data available',
        no_rules_found: 'No rules found. Create your first rule!'
    };

    /**
     * Analytics Dashboard Widget
     */
    var SPTAnalyticsDashboard = {
        
        init: function() {
            this.bindEvents();
            this.loadDashboardData();
        },

        bindEvents: function() {
            $(document).on('click', '.spt-dashboard-refresh', this.refreshData);
            $(document).on('change', '.spt-dashboard-period', this.changePeriod);
        },

        loadDashboardData: function() {
            var period = $('.spt-dashboard-period').val() || 7;
            
            $.ajax({
                url: spt_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'spt_get_analytics_data',
                    type: 'dashboard',
                    days: period,
                    nonce: spt_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        SPTAnalyticsDashboard.updateDashboard(response.data);
                    }
                }
            });
        },

        updateDashboard: function(data) {
            // Update summary cards
            $('.spt-card-views .card-value').text(data.summary.total_views || 0);
            $('.spt-card-products .card-value').text(data.summary.unique_products || 0);
            $('.spt-card-tabs .card-value').text(data.summary.active_tabs || 0);
            $('.spt-card-engagement .card-value').text(data.engagement.engagement_rate + '%' || '0%');

            // Update popular tabs list
            this.updatePopularTabs(data.popular_tabs || []);
            
            // Update top products list
            this.updateTopProducts(data.top_products || []);
        },

        updatePopularTabs: function(tabs) {
            var $list = $('.spt-popular-tabs-list');
            $list.empty();
            
            if (tabs.length === 0) {
                $list.html('<li>No tab data available</li>');
                return;
            }
            
            tabs.forEach(function(tab, index) {
                var $item = $('<li class="tab-item">');
                $item.html(`
                    <span class="tab-rank">#${index + 1}</span>
                    <span class="tab-name">${tab.tab_key}</span>
                    <span class="tab-views">${tab.total_views} views</span>
                `);
                $list.append($item);
            });
        },

        updateTopProducts: function(products) {
            var $list = $('.spt-top-products-list');
            $list.empty();
            
            if (products.length === 0) {
                $list.html('<li>No product data available</li>');
                return;
            }
            
            products.forEach(function(product, index) {
                var $item = $('<li class="product-item">');
                $item.html(`
                    <span class="product-rank">#${index + 1}</span>
                    <span class="product-name">
                        <a href="${product.product_url}" target="_blank">${product.product_name}</a>
                    </span>
                    <span class="product-views">${product.total_views} views</span>
                `);
                $list.append($item);
            });
        },

        refreshData: function(e) {
            e.preventDefault();
            $(this).addClass('updating');
            SPTAnalyticsDashboard.loadDashboardData();
            
            setTimeout(function() {
                $('.spt-dashboard-refresh').removeClass('updating');
            }, 1000);
        },

        changePeriod: function() {
            SPTAnalyticsDashboard.loadDashboardData();
        }
    };

    /**
     * Template Manager
     */
    var SPTTemplateManager = {
        
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $(document).on('click', '.template-preview', this.showTemplatePreview);
            $(document).on('click', '.template-install', this.installTemplate);
            $(document).on('click', '.template-delete', this.deleteTemplate);
            $(document).on('submit', '.template-upload-form', this.uploadTemplate);
        },

        showTemplatePreview: function(e) {
            e.preventDefault();
            
            var templateData = $(this).data('template');
            var modal = SPTTemplateManager.createPreviewModal(templateData);
            $('body').append(modal);
            modal.fadeIn(300);
        },

        createPreviewModal: function(template) {
            var modal = $('<div class="spt-template-preview-modal spt-modal">');
            
            var rulesHtml = '';
            if (template.rules && template.rules.length > 0) {
                template.rules.forEach(function(rule) {
                    rulesHtml += `
                        <tr>
                            <td>${rule.rule_name}</td>
                            <td>${rule.tab_title}</td>
                            <td>${rule.conditions || 'All Products'}</td>
                            <td>${rule.priority || 10}</td>
                        </tr>
                    `;
                });
            }
            
            modal.html(`
                <div class="spt-modal-content template-preview-content">
                    <div class="spt-modal-header">
                        <h3>Template Preview: ${template.name}</h3>
                        <span class="spt-modal-close">&times;</span>
                    </div>
                    <div class="spt-modal-body">
                        <div class="template-info">
                            <p><strong>Description:</strong> ${template.description}</p>
                            <p><strong>Version:</strong> ${template.version}</p>
                            <p><strong>Rules Count:</strong> ${template.rules_count || template.rules.length}</p>
                        </div>
                        
                        <h4>Rules Preview</h4>
                        <table class="wp-list-table widefat striped">
                            <thead>
                                <tr>
                                    <th>Rule Name</th>
                                    <th>Tab Title</th>
                                    <th>Conditions</th>
                                    <th>Priority</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${rulesHtml || '<tr><td colspan="4">No rules found</td></tr>'}
                            </tbody>
                        </table>
                        
                        <div class="template-actions">
                            <button type="button" class="button-primary install-template-btn" 
                                    data-template-key="${template.key || template.template_key}">
                                Install Template
                            </button>
                            <button type="button" class="button close-preview">Close</button>
                        </div>
                    </div>
                </div>
            `);
            
            // Bind close events
            modal.on('click', '.spt-modal-close, .close-preview, .spt-template-preview-modal', function(e) {
                if (e.target === this || $(e.target).hasClass('close-preview') || $(e.target).hasClass('spt-modal-close')) {
                    modal.fadeOut(300, function() {
                        modal.remove();
                    });
                }
            });
            
            // Bind install event
            modal.on('click', '.install-template-btn', function() {
                var templateKey = $(this).data('template-key');
                modal.fadeOut(300, function() {
                    modal.remove();
                });
                SPTTemplateManager.installTemplate.call($(`[data-template-key="${templateKey}"]`)[0]);
            });
            
            return modal;
        },

        installTemplate: function(e) {
            e.preventDefault();
            return SPTAdmin.installTemplate.call(this, e);
        },

        deleteTemplate: function(e) {
            e.preventDefault();
            
            var templateName = $(this).data('template-name');
            var templateFile = $(this).data('template-file');
            
            if (!confirm(`Delete template "${templateName}"? This action cannot be undone.`)) {
                return;
            }
            
            $.ajax({
                url: spt_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'spt_delete_template',
                    template_file: templateFile,
                    nonce: spt_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        SPTAdmin.showSuccess('Template deleted successfully');
                        location.reload();
                    } else {
                        SPTAdmin.showError(response.data);
                    }
                },
                error: function() {
                    SPTAdmin.showError('Error deleting template');
                }
            });
        },

        uploadTemplate: function(e) {
            e.preventDefault();
            return SPTAdmin.importTemplate.call(this, e);
        }
    };

    /**
     * Rule Builder Helper
     */
    var SPTRuleBuilder = {
        
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $(document).on('click', '.add-condition-group', this.addConditionGroup);
            $(document).on('click', '.remove-condition', this.removeCondition);
            $(document).on('change', '.condition-logic', this.updateConditionLogic);
        },

        addConditionGroup: function(e) {
            e.preventDefault();
            
            var template = `
                <div class="condition-group">
                    <select class="condition-logic">
                        <option value="AND">AND</option>
                        <option value="OR">OR</option>
                    </select>
                    <select class="condition-type">
                        <option value="category">Category</option>
                        <option value="price_range">Price Range</option>
                        <option value="attribute">Attribute</option>
                        <option value="stock_status">Stock Status</option>
                    </select>
                    <div class="condition-values">
                        <!-- Dynamic content based on condition type -->
                    </div>
                    <button type="button" class="button remove-condition">Remove</button>
                </div>
            `;
            
            $('.conditions-container').append(template);
        },

        removeCondition: function(e) {
            e.preventDefault();
            $(this).closest('.condition-group').remove();
        },

        updateConditionLogic: function() {
            // Update visual indicators for logic operators
            var $group = $(this).closest('.condition-group');
            $group.attr('data-logic', $(this).val().toLowerCase());
        }
    };

    /**
     * Initialize everything when document is ready
     */
    $(document).ready(function() {
        SPTAdmin.init();
        
        // Initialize additional modules based on page
        if ($('.spt-analytics-dashboard').length) {
            SPTAnalyticsDashboard.init();
        }
        
        if ($('.spt-templates').length) {
            SPTTemplateManager.init();
        }
        
        if ($('.spt-rule-builder').length) {
            SPTRuleBuilder.init();
        }
    });

})(jQuery);
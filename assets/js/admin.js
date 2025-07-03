/**
 * Admin JavaScript for Smart Product Tabs (Simplified)
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

            // Analytics
            $(document).on('change', '#analytics-period', this.loadAnalyticsData);
            $(document).on('click', '.analytics-refresh', this.refreshAnalytics);
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
            
            if (!isValid) {
                e.preventDefault();
                return false;
            }
            
            return true;
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
                $container.html('<p>No data available</p>');
                return;
            }
            
            var maxViews = Math.max.apply(Math, data.map(function(item) { return item.total_views; }));
            
            data.forEach(function(item) {
                var percentage = maxViews > 0 ? (item.total_views / maxViews) * 100 : 0;
                var bar = $('<div class="chart-bar">');
                bar.html(
                    '<div class="bar-label">' + item.tab_key + '</div>' +
                    '<div class="bar-fill" style="width: ' + percentage + '%"></div>' +
                    '<div class="bar-value">' + item.total_views + '</div>'
                );
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
                $container.html('<p>No data available</p>');
                return;
            }
            
            // Simple line chart
            var maxViews = Math.max.apply(Math, data.map(function(item) { return item.total_views; }));
            var chartHeight = 200;
            
            var svg = $('<svg width="100%" height="' + chartHeight + '" viewBox="0 0 600 ' + chartHeight + '">');
            var points = [];
            
            data.forEach(function(item, index) {
                var x = (index / (data.length - 1)) * 580 + 10;
                var y = chartHeight - 20 - ((item.total_views / maxViews) * (chartHeight - 40));
                points.push(x + ',' + y);
            });
            
            if (points.length > 1) {
                var polyline = $('<polyline points="' + points.join(' ') + '" fill="none" stroke="#0073aa" stroke-width="2">');
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
                $item.html(
                    '<span class="tab-rank">#' + (index + 1) + '</span>' +
                    '<span class="tab-name">' + tab.tab_key + '</span>' +
                    '<span class="tab-views">' + tab.total_views + ' views</span>'
                );
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
                $item.html(
                    '<span class="product-rank">#' + (index + 1) + '</span>' +
                    '<span class="product-name">' +
                        '<a href="' + product.product_url + '" target="_blank">' + product.product_name + '</a>' +
                    '</span>' +
                    '<span class="product-views">' + product.total_views + ' views</span>'
                );
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
        
        // Initialize additional modules based on page
        if ($('.spt-analytics-dashboard').length) {
            SPTAnalyticsDashboard.init();
        }
        
        if ($('.spt-templates').length) {
            SPTTemplateManager.init();
            SPTExport.init();
        }
    });

})(jQuery);
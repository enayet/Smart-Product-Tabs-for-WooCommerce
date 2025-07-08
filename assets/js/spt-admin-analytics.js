/**
 * Smart Product Tabs - Admin Analytics
 * Handles analytics dashboard, data visualization, and reporting
 */

(function($) {
    'use strict';

    /**
     * SPT Analytics Manager
     */
    var SPTAnalytics = {
        
        /**
         * Current analytics data
         */
        data: {
            summary: null,
            popularTabs: null,
            dailyData: null,
            topProducts: null,
            engagement: null
        },

        /**
         * Chart instances
         */
        charts: {},

        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.loadAnalyticsData();
            this.initCharts();
            this.setupAutoRefresh();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Analytics controls
            $(document).on('change', '#analytics-period', this.handlePeriodChange);
            $(document).on('click', '#refresh-analytics', this.refreshAnalytics);
            $(document).on('click', '#export-analytics', this.exportAnalytics);
            $(document).on('click', '#reset-analytics', this.resetAnalytics);
            
            // Settings
            $(document).on('submit', '.analytics-settings form', this.saveAnalyticsSettings);
            $(document).on('change', 'input[name="spt_enable_analytics"]', this.toggleAnalyticsStatus);
            
            // Data filtering
            $(document).on('click', '.analytics-filter', this.applyFilter);
            $(document).on('change', '#analytics-date-range', this.handleDateRangeChange);
            
            // Chart interactions
            $(document).on('click', '.chart-tab-bar', this.showTabDetails);
            $(document).on('click', '.chart-product-bar', this.showProductDetails);
        },

        /**
         * Handle period change
         */
        handlePeriodChange: function() {
            var period = $(this).val();
            SPTAnalytics.loadAnalyticsData(period);
        },

        /**
         * Refresh analytics data
         */
        refreshAnalytics: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var period = $('#analytics-period').val() || 30;
            
            $button.prop('disabled', true).text('Refreshing...');
            
            SPTAnalytics.loadAnalyticsData(period, function() {
                $button.prop('disabled', false).text('Refresh');
                SPTAnalytics.showSuccess('Analytics data refreshed successfully');
            });
        },

        /**
         * Load analytics data via AJAX
         */
        loadAnalyticsData: function(period, callback) {
            period = period || 30;
            
            if (typeof spt_admin_ajax === 'undefined') {
                console.log('AJAX not available for analytics');
                return;
            }

            // Show loading state
            $('.analytics-summary').addClass('loading');
            
            var requests = [
                this.fetchAnalyticsData('summary', period),
                this.fetchAnalyticsData('popular_tabs', period),
                this.fetchAnalyticsData('daily_analytics', period),
                this.fetchAnalyticsData('top_products', period),
                this.fetchAnalyticsData('engagement', period)
            ];

            $.when.apply($, requests).done(function(summary, popularTabs, dailyData, topProducts, engagement) {
                // Store data
                SPTAnalytics.data.summary = summary[0].data || {};
                SPTAnalytics.data.popularTabs = popularTabs[0].data || [];
                SPTAnalytics.data.dailyData = dailyData[0].data || [];
                SPTAnalytics.data.topProducts = topProducts[0].data || [];
                SPTAnalytics.data.engagement = engagement[0].data || {};

                // Update UI
                SPTAnalytics.updateSummaryCards();
                SPTAnalytics.updateCharts();
                SPTAnalytics.updateTables();

                $('.analytics-summary').removeClass('loading');

                if (callback) callback();
            }).fail(function() {
                $('.analytics-summary').removeClass('loading');
                SPTAnalytics.showError('Failed to load analytics data');
            });
        },

        /**
         * Fetch specific analytics data
         */
        fetchAnalyticsData: function(type, period) {
            return $.ajax({
                url: spt_admin_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'spt_get_analytics_data',
                    type: type,
                    days: period,
                    nonce: spt_admin_ajax.nonce
                }
            });
        },

        /**
         * Update summary cards
         */
        updateSummaryCards: function() {
            var summary = this.data.summary;
            
            if (!summary) return;

            // Update each metric card
            this.updateMetricCard('.total-views', summary.total_views || 0);
            this.updateMetricCard('.unique-products', summary.unique_products || 0);
            this.updateMetricCard('.unique-tabs', summary.unique_tabs || 0);
            this.updateMetricCard('.avg-engagement', summary.avg_engagement || 0, '%');
            
            // Update trend indicators if available
            if (summary.trends) {
                this.updateTrendIndicators(summary.trends);
            }
        },

        /**
         * Update individual metric card
         */
        updateMetricCard: function(selector, value, suffix) {
            var $card = $(selector);
            var $valueEl = $card.find('.metric-value');
            
            if ($valueEl.length) {
                var displayValue = this.formatNumber(value) + (suffix || '');
                $valueEl.text(displayValue);
                
                // Add animation
                $valueEl.addClass('updated');
                setTimeout(function() {
                    $valueEl.removeClass('updated');
                }, 1000);
            }
        },

        /**
         * Update trend indicators
         */
        updateTrendIndicators: function(trends) {
            for (var metric in trends) {
                var trend = trends[metric];
                var $indicator = $('.' + metric.replace('_', '-') + ' .metric-trend');
                
                if ($indicator.length && trend !== undefined) {
                    var trendText = trend > 0 ? '+' + trend + '%' : trend + '%';
                    var trendClass = trend > 0 ? 'positive' : (trend < 0 ? 'negative' : 'neutral');
                    
                    $indicator.text(trendText).removeClass('positive negative neutral').addClass(trendClass);
                }
            }
        },

        /**
         * Initialize charts
         */
        initCharts: function() {
            // Simple bar chart for popular tabs
            this.initPopularTabsChart();
            
            // Line chart for daily analytics
            this.initDailyChart();
            
            // Product performance chart
            this.initProductChart();
        },

        /**
         * Initialize popular tabs chart
         */
        initPopularTabsChart: function() {
            var $container = $('#popular-tabs-chart');
            if (!$container.length) return;

            // Create simple HTML bar chart
            this.charts.popularTabs = $container;
            this.updatePopularTabsChart();
        },

        /**
         * Update popular tabs chart
         */
        updatePopularTabsChart: function() {
            var $chart = this.charts.popularTabs;
            if (!$chart || !this.data.popularTabs) return;

            var html = '';
            var maxViews = Math.max.apply(Math, this.data.popularTabs.map(function(tab) { return tab.views; }));

            this.data.popularTabs.forEach(function(tab, index) {
                var percentage = maxViews > 0 ? (tab.views / maxViews * 100) : 0;
                html += '<div class="chart-bar-item" data-tab="' + tab.tab_key + '">';
                html += '<div class="chart-bar-label">' + tab.tab_key + '</div>';
                html += '<div class="chart-bar-container">';
                html += '<div class="chart-bar chart-tab-bar" style="width: ' + percentage + '%;" data-views="' + tab.views + '"></div>';
                html += '</div>';
                html += '<div class="chart-bar-value">' + SPTAnalytics.formatNumber(tab.views) + '</div>';
                html += '</div>';
            });

            $chart.html(html);
        },

        /**
         * Initialize daily analytics chart
         */
        initDailyChart: function() {
            var $container = $('#daily-analytics-chart');
            if (!$container.length) return;

            this.charts.daily = $container;
            this.updateDailyChart();
        },

        /**
         * Update daily analytics chart
         */
        updateDailyChart: function() {
            var $chart = this.charts.daily;
            if (!$chart || !this.data.dailyData) return;

            // Create simple line chart using CSS
            var html = '<div class="daily-chart-container">';
            var maxViews = Math.max.apply(Math, this.data.dailyData.map(function(day) { return day.total_views; }));

            this.data.dailyData.slice(-14).forEach(function(day, index) {
                var height = maxViews > 0 ? (day.total_views / maxViews * 100) : 0;
                html += '<div class="daily-chart-bar" style="height: ' + height + '%;" title="' + day.date + ': ' + day.total_views + ' views"></div>';
            });

            html += '</div>';
            $chart.html(html);
        },

        /**
         * Initialize product performance chart
         */
        initProductChart: function() {
            var $container = $('#product-performance-chart');
            if (!$container.length) return;

            this.charts.products = $container;
            this.updateProductChart();
        },

        /**
         * Update product performance chart
         */
        updateProductChart: function() {
            var $chart = this.charts.products;
            if (!$chart || !this.data.topProducts) return;

            var html = '';
            var maxViews = Math.max.apply(Math, this.data.topProducts.map(function(product) { return product.views; }));

            this.data.topProducts.forEach(function(product, index) {
                var percentage = maxViews > 0 ? (product.views / maxViews * 100) : 0;
                html += '<div class="chart-bar-item" data-product="' + product.product_id + '">';
                html += '<div class="chart-bar-label">' + (product.product_name || 'Product #' + product.product_id) + '</div>';
                html += '<div class="chart-bar-container">';
                html += '<div class="chart-bar chart-product-bar" style="width: ' + percentage + '%;" data-views="' + product.views + '"></div>';
                html += '</div>';
                html += '<div class="chart-bar-value">' + SPTAnalytics.formatNumber(product.views) + '</div>';
                html += '</div>';
            });

            $chart.html(html);
        },

        /**
         * Update all charts
         */
        updateCharts: function() {
            this.updatePopularTabsChart();
            this.updateDailyChart();
            this.updateProductChart();
        },

        /**
         * Update data tables
         */
        updateTables: function() {
            this.updatePopularTabsTable();
            this.updateTopProductsTable();
        },

        /**
         * Update popular tabs table
         */
        updatePopularTabsTable: function() {
            var $table = $('#popular-tabs-table tbody');
            if (!$table.length || !this.data.popularTabs) return;

            var html = '';
            this.data.popularTabs.forEach(function(tab, index) {
                html += '<tr>';
                html += '<td>' + (index + 1) + '</td>';
                html += '<td>' + tab.tab_key + '</td>';
                html += '<td>' + SPTAnalytics.formatNumber(tab.views) + '</td>';
                html += '<td>' + SPTAnalytics.formatNumber(tab.unique_products) + '</td>';
                html += '</tr>';
            });

            $table.html(html);
        },

        /**
         * Update top products table
         */
        updateTopProductsTable: function() {
            var $table = $('#top-products-table tbody');
            if (!$table.length || !this.data.topProducts) return;

            var html = '';
            this.data.topProducts.forEach(function(product, index) {
                html += '<tr>';
                html += '<td>' + (index + 1) + '</td>';
                html += '<td>' + (product.product_name || 'Product #' + product.product_id) + '</td>';
                html += '<td>' + SPTAnalytics.formatNumber(product.views) + '</td>';
                html += '<td>' + SPTAnalytics.formatNumber(product.unique_tabs) + '</td>';
                html += '</tr>';
            });

            $table.html(html);
        },

        /**
         * Export analytics data
         */
        exportAnalytics: function(e) {
            e.preventDefault();
            
            if (typeof spt_admin_ajax === 'undefined') {
                alert('Export not available - AJAX not loaded');
                return;
            }

            var $button = $(this);
            var period = $('#analytics-period').val() || 30;
            
            $button.prop('disabled', true).text('Exporting...');

            $.ajax({
                url: spt_admin_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'spt_export_analytics',
                    period: period,
                    nonce: spt_admin_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        SPTAnalytics.downloadAnalyticsData(response.data, 'spt-analytics-' + period + 'days.csv');
                        SPTAnalytics.showSuccess('Analytics data exported successfully');
                    } else {
                        SPTAnalytics.showError('Export failed: ' + response.data);
                    }
                },
                error: function() {
                    SPTAnalytics.showError('Export failed. Please try again.');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Export Data');
                }
            });
        },

        /**
         * Download analytics data as CSV
         */
        downloadAnalyticsData: function(data, filename) {
            var blob = new Blob([data], { type: 'text/csv' });
            var url = window.URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        },

        /**
         * Reset analytics data
         */
        resetAnalytics: function(e) {
            e.preventDefault();
            
            if (!confirm('Reset all analytics data? This cannot be undone.')) {
                return;
            }

            if (typeof spt_admin_ajax === 'undefined') {
                alert('Reset not available - AJAX not loaded');
                return;
            }

            var $button = $(this);
            $button.prop('disabled', true).text('Resetting...');

            $.ajax({
                url: spt_admin_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'spt_reset_analytics',
                    nonce: spt_admin_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Clear all data and refresh
                        SPTAnalytics.clearData();
                        SPTAnalytics.loadAnalyticsData();
                        SPTAnalytics.showSuccess('Analytics data reset successfully');
                    } else {
                        SPTAnalytics.showError('Reset failed: ' + response.data);
                    }
                },
                error: function() {
                    SPTAnalytics.showError('Reset failed. Please try again.');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Reset Data');
                }
            });
        },

        /**
         * Save analytics settings
         */
        saveAnalyticsSettings: function(e) {
            e.preventDefault();
            
            if (typeof spt_admin_ajax === 'undefined') {
                SPTAnalytics.showError('Settings cannot be saved - AJAX not available');
                return;
            }

            var $form = $(this);
            var $submitBtn = $form.find('input[type="submit"]');
            
            $submitBtn.prop('disabled', true).val('Saving...');

            $.ajax({
                url: spt_admin_ajax.ajax_url,
                type: 'POST',
                data: $form.serialize() + '&action=spt_save_analytics_settings&nonce=' + spt_admin_ajax.nonce,
                success: function(response) {
                    if (response.success) {
                        SPTAnalytics.showSuccess('Settings saved successfully');
                    } else {
                        SPTAnalytics.showError('Failed to save settings: ' + response.data);
                    }
                },
                error: function() {
                    SPTAnalytics.showError('Failed to save settings');
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).val('Save Settings');
                }
            });
        },

        /**
         * Toggle analytics status
         */
        toggleAnalyticsStatus: function() {
            var enabled = $(this).is(':checked');
            var $dashboard = $('.analytics-dashboard');
            
            if (enabled) {
                $dashboard.removeClass('analytics-disabled');
                SPTAnalytics.showSuccess('Analytics tracking enabled');
            } else {
                $dashboard.addClass('analytics-disabled');
                SPTAnalytics.showSuccess('Analytics tracking disabled');
            }
        },

        /**
         * Setup auto-refresh
         */
        setupAutoRefresh: function() {
            // Auto-refresh every 5 minutes if user is active
            var lastActivity = Date.now();
            var refreshInterval = 5 * 60 * 1000; // 5 minutes

            // Track user activity
            $(document).on('click mousemove keypress', function() {
                lastActivity = Date.now();
            });

            // Auto-refresh function
            setInterval(function() {
                var timeSinceActivity = Date.now() - lastActivity;
                
                // Only refresh if user was active in last 10 minutes
                if (timeSinceActivity < 10 * 60 * 1000) {
                    SPTAnalytics.loadAnalyticsData();
                }
            }, refreshInterval);
        },

        /**
         * Show tab details
         */
        showTabDetails: function() {
            var tabKey = $(this).closest('.chart-bar-item').data('tab');
            var views = $(this).data('views');
            
            alert('Tab: ' + tabKey + '\nViews: ' + SPTAnalytics.formatNumber(views));
        },

        /**
         * Show product details
         */
        showProductDetails: function() {
            var productId = $(this).closest('.chart-bar-item').data('product');
            var views = $(this).data('views');
            
            if (confirm('Product ID: ' + productId + '\nViews: ' + SPTAnalytics.formatNumber(views) + '\n\nView product in admin?')) {
                window.open('post.php?post=' + productId + '&action=edit', '_blank');
            }
        },

        /**
         * Clear all data
         */
        clearData: function() {
            this.data = {
                summary: null,
                popularTabs: null,
                dailyData: null,
                topProducts: null,
                engagement: null
            };
            
            // Clear UI
            $('.metric-value').text('0');
            $('.chart-container').html('');
            $('table tbody').html('');
        },

        /**
         * Format number for display
         */
        formatNumber: function(num) {
            if (num >= 1000000) {
                return (num / 1000000).toFixed(1) + 'M';
            } else if (num >= 1000) {
                return (num / 1000).toFixed(1) + 'K';
            }
            return num.toString();
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
        // Only initialize if we're on the analytics page
        if ($('.spt-analytics').length || $('.analytics-dashboard').length) {
            SPTAnalytics.init();
        }
    });

    // Export for other modules to use
    window.SPTAnalytics = SPTAnalytics;

})(jQuery);
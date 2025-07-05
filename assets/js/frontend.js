/**
 * Frontend JavaScript for Smart Product Tabs
 * Optimized and streamlined version with enhanced analytics tracking
 */

(function($) {
    'use strict';

    /**
     * Main SPT Frontend object
     */
    var SPTFrontend = {
        
        /**
         * Configuration
         */
        config: {
            trackViews: true,
            animationSpeed: 300,
            mobileBreakpoint: 768
        },

        /**
         * Initialize
         */
        init: function() {
            this.loadConfig();
            this.bindEvents();
            this.initMobileOptimization();
            this.initEnhancedTracking();
            this.initAccessibility();
            this.handleDeepLinks();
        },

        /**
         * Load configuration from localized data
         */
        loadConfig: function() {
            if (typeof spt_frontend !== 'undefined') {
                this.config.trackViews = spt_frontend.track_views == '1';
                this.config.productId = spt_frontend.product_id;
                this.config.ajaxUrl = spt_frontend.ajax_url;
                this.config.nonce = spt_frontend.nonce;
            }
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Tab click events with enhanced tracking
            $(document).on('click', '.woocommerce-tabs .tabs li a', this.handleTabClick);
            
            // Mobile accordion toggle
            $(document).on('click', '.spt-mobile-tab-header', this.toggleMobileTab);
            
            // Window resize for mobile optimization
            $(window).on('resize', this.debounce(this.handleResize, 250));
            
            // Deep link handling
            $(window).on('hashchange', this.handleHashChange);
            
            // Touch events for mobile
            if (this.isTouchDevice()) {
                this.initTouchEvents();
            }
        },

        /**
         * Enhanced handle tab click with better tracking
         */
        handleTabClick: function(e) {
            var $tab = $(this);
            var $tabPanel = $($tab.attr('href'));
            
            // Track tab view BEFORE other processing
            SPTFrontend.trackTabView($tab);
            
            // Add visual feedback
            $tab.addClass('spt-tracking');
            setTimeout(function() {
                $tab.removeClass('spt-tracking');
            }, 500);
            
            // Update URL hash for deep linking
            if ($tab.attr('href').indexOf('#tab-') === 0) {
                SPTFrontend.updateUrlHash($tab.attr('href'));
            }
            
            // Custom event
            $(document).trigger('spt:tab-activated', [$tab, $tabPanel]);
        },

        /**
         * Enhanced track tab view with better error handling
         */
        trackTabView: function($tab) {
            if (!this.config.trackViews) {
                return;
            }
            
            var tabKey = this.getTabKey($tab);
            var productId = this.config.productId;
            
            if (!tabKey || !productId) {
                return;
            }
            
            // Throttle tracking to avoid spam
            var trackingKey = 'spt_tracked_' + tabKey + '_' + productId;
            if (sessionStorage.getItem(trackingKey)) {
                return;
            }
            
            // Mark as tracked immediately to prevent duplicates
            sessionStorage.setItem(trackingKey, '1');
            
            // Debug logging
            if (window.console && window.console.log && window.location.search.indexOf('spt_debug=1') !== -1) {
                console.log('SPT: Tracking view for tab:', tabKey, 'product:', productId);
            }
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'spt_track_tab_view',
                    tab_key: tabKey,
                    product_id: productId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (window.console && window.console.log && window.location.search.indexOf('spt_debug=1') !== -1) {
                        console.log('SPT: View tracked successfully', response);
                    }
                },
                error: function(xhr, status, error) {
                    if (window.console && window.console.error) {
                        console.error('SPT: Failed to track view:', error);
                    }
                    // Remove the tracking flag so it can be retried
                    sessionStorage.removeItem(trackingKey);
                }
            });
        },

        /**
         * Get tab key from tab element
         */
        getTabKey: function($tab) {
            var href = $tab.attr('href');
            if (href && href.indexOf('#') === 0) {
                return href.substring(1);
            }
            return null;
        },

        /**
         * Initialize mobile optimization
         */
        initMobileOptimization: function() {
            if (this.isMobile()) {
                this.enableMobileMode();
            }
        },

        /**
         * Check if current device is mobile
         */
        isMobile: function() {
            return $(window).width() <= this.config.mobileBreakpoint;
        },

        /**
         * Check if device supports touch
         */
        isTouchDevice: function() {
            return 'ontouchstart' in window || navigator.maxTouchPoints > 0;
        },

        /**
         * Enable mobile mode
         */
        enableMobileMode: function() {
            var $tabsContainer = $('.woocommerce-tabs');
            
            if ($tabsContainer.hasClass('spt-mobile-enabled')) {
                return;
            }
            
            $tabsContainer.addClass('spt-mobile-enabled');
            this.optimizeTabNavigation($tabsContainer);
        },

        /**
         * Optimize tab navigation for mobile
         */
        optimizeTabNavigation: function($container) {
            var $tabsList = $container.find('.tabs');
            
            if ($tabsList.length) {
                $tabsList.addClass('spt-mobile-tabs');
                
                // Add scroll indicators if needed
                if (this.hasHorizontalScroll($tabsList)) {
                    this.addScrollIndicators($tabsList);
                }
            }
        },

        /**
         * Check if element has horizontal scroll
         */
        hasHorizontalScroll: function($element) {
            return $element[0].scrollWidth > $element[0].clientWidth;
        },

        /**
         * Add scroll indicators
         */
        addScrollIndicators: function($element) {
            var $indicator = $('<div class="spt-scroll-indicator">Swipe to see more tabs</div>');
            $element.parent().prepend($indicator);
            
            // Hide indicator after user interaction
            $element.on('scroll touchstart', function() {
                $indicator.fadeOut();
            });
        },

        /**
         * Toggle mobile tab (accordion mode)
         */
        toggleMobileTab: function(e) {
            e.preventDefault();
            
            var $header = $(this);
            var $item = $header.closest('.spt-accordion-item');
            var $content = $item.find('.spt-mobile-tab-content');
            var $toggle = $header.find('.tab-toggle');
            
            // Close other open tabs
            $('.spt-accordion-item').not($item).each(function() {
                $(this).find('.spt-mobile-tab-content').slideUp(SPTFrontend.config.animationSpeed);
                $(this).find('.tab-toggle').text('+');
                $(this).removeClass('active');
            });
            
            // Toggle current tab
            if ($item.hasClass('active')) {
                $content.slideUp(SPTFrontend.config.animationSpeed);
                $toggle.text('+');
                $item.removeClass('active');
            } else {
                $content.slideDown(SPTFrontend.config.animationSpeed);
                $toggle.text('−');
                $item.addClass('active');
                
                // Track view for accordion
                var tabKey = $item.data('tab');
                if (tabKey) {
                    SPTFrontend.trackTabView($('<a href="' + tabKey + '">'));
                }
            }
        },

        /**
         * Initialize touch events
         */
        initTouchEvents: function() {
            var $tabsContainer = $('.woocommerce-tabs');
            var startX = 0;
            var currentX = 0;
            var threshold = 50;
            
            $tabsContainer.on('touchstart', function(e) {
                startX = e.originalEvent.touches[0].clientX;
            });
            
            $tabsContainer.on('touchmove', function(e) {
                currentX = e.originalEvent.touches[0].clientX;
            });
            
            $tabsContainer.on('touchend', function(e) {
                var deltaX = startX - currentX;
                
                if (Math.abs(deltaX) > threshold) {
                    if (deltaX > 0) {
                        SPTFrontend.navigateTab('next');
                    } else {
                        SPTFrontend.navigateTab('prev');
                    }
                }
            });

            // Enhanced mobile tracking
            $tabsContainer.on('touchstart', '.tabs a', function() {
                SPTFrontend.trackTabView($(this));
            });
        },

        /**
         * Navigate to next/previous tab
         */
        navigateTab: function(direction) {
            var $currentTab = $('.woocommerce-tabs .tabs li.active');
            var $targetTab;
            
            if (direction === 'next') {
                $targetTab = $currentTab.next('li:visible');
                if (!$targetTab.length) {
                    $targetTab = $('.woocommerce-tabs .tabs li:visible:first');
                }
            } else {
                $targetTab = $currentTab.prev('li:visible');
                if (!$targetTab.length) {
                    $targetTab = $('.woocommerce-tabs .tabs li:visible:last');
                }
            }
            
            if ($targetTab.length) {
                $targetTab.find('a').trigger('click');
            }
        },

        /**
         * Initialize accessibility features
         */
        initAccessibility: function() {
            // Add ARIA attributes
            $('.woocommerce-tabs .tabs a').each(function() {
                var $link = $(this);
                var targetId = $link.attr('href');
                var $target = $(targetId);
                
                if ($target.length) {
                    $link.attr('aria-controls', targetId.substring(1));
                    $target.attr('aria-labelledby', $link.attr('id') || 'tab-' + targetId.substring(1));
                }
            });
            
            // Keyboard navigation
            $('.woocommerce-tabs .tabs').on('keydown', 'a', function(e) {
                var $current = $(this);
                var $tabs = $('.woocommerce-tabs .tabs a');
                var currentIndex = $tabs.index($current);
                var $target;
                
                switch (e.which) {
                    case 37: // Left arrow
                        e.preventDefault();
                        $target = $tabs.eq(currentIndex - 1);
                        if (!$target.length) {
                            $target = $tabs.last();
                        }
                        $target.focus().trigger('click');
                        break;
                        
                    case 39: // Right arrow
                        e.preventDefault();
                        $target = $tabs.eq(currentIndex + 1);
                        if (!$target.length) {
                            $target = $tabs.first();
                        }
                        $target.focus().trigger('click');
                        break;
                        
                    case 36: // Home
                        e.preventDefault();
                        $tabs.first().focus().trigger('click');
                        break;
                        
                    case 35: // End
                        e.preventDefault();
                        $tabs.last().focus().trigger('click');
                        break;
                }
            });
        },

        /**
         * Handle deep links
         */
        handleDeepLinks: function() {
            var hash = window.location.hash;
            
            if (hash && hash.indexOf('#tab-') === 0) {
                this.activateTabByHash(hash);
            }
        },

        /**
         * Handle hash change
         */
        handleHashChange: function() {
            var hash = window.location.hash;
            
            if (hash && hash.indexOf('#tab-') === 0) {
                SPTFrontend.activateTabByHash(hash);
            }
        },

        /**
         * Activate tab by hash
         */
        activateTabByHash: function(hash) {
            var $tab = $('.woocommerce-tabs .tabs a[href="' + hash + '"]');
            
            if ($tab.length) {
                setTimeout(function() {
                    $tab.trigger('click');
                    
                    if (SPTFrontend.isMobile()) {
                        $('html, body').animate({
                            scrollTop: $('.woocommerce-tabs').offset().top - 100
                        }, 300);
                    }
                }, 100);
            }
        },

        /**
         * Update URL hash
         */
        updateUrlHash: function(hash) {
            if (history.replaceState) {
                history.replaceState(null, null, hash);
            } else {
                window.location.hash = hash;
            }
        },

        /**
         * Handle window resize
         */
        handleResize: function() {
            var wasMobile = $('.woocommerce-tabs').hasClass('spt-mobile-enabled');
            var isMobile = SPTFrontend.isMobile();
            
            if (isMobile && !wasMobile) {
                SPTFrontend.enableMobileMode();
            } else if (!isMobile && wasMobile) {
                SPTFrontend.disableMobileMode();
            }
        },

        /**
         * Disable mobile mode
         */
        disableMobileMode: function() {
            var $tabsContainer = $('.woocommerce-tabs');
            $tabsContainer.removeClass('spt-mobile-enabled');
            
            // Remove accordion if it exists
            $tabsContainer.find('.spt-mobile-accordion').remove();
            
            // Show original tabs
            $tabsContainer.find('.tabs, .woocommerce-Tabs-panel').show();
        },

        /**
         * Initialize enhanced tracking
         */
        initEnhancedTracking: function() {
            // Track initial page load (for default active tab)
            var $activeTab = $('.woocommerce-tabs .tabs li.active a');
            if ($activeTab.length) {
                setTimeout(function() {
                    SPTFrontend.trackTabView($activeTab);
                }, 1000);
            }
            
            // Add debug info for development
            if (this.config.trackViews && window.location.search.indexOf('spt_debug=1') !== -1) {
                console.log('SPT: Enhanced tracking initialized');
                setTimeout(function() {
                    SPTFrontend.debugTracking();
                }, 2000);
            }
        },

        /**
         * Debug method to check tracking configuration
         */
        debugTracking: function() {
            if (!window.console) return;
            
            console.log('SPT Tracking Debug:');
            console.log('- Tracking enabled:', this.config.trackViews);
            console.log('- Product ID:', this.config.productId);
            console.log('- AJAX URL:', this.config.ajaxUrl);
            console.log('- Nonce:', this.config.nonce);
            console.log('- Available tabs:', $('.woocommerce-tabs .tabs li a').length);
            
            // Test tracking for first tab
            var $firstTab = $('.woocommerce-tabs .tabs li a').first();
            if ($firstTab.length) {
                console.log('- First tab key:', this.getTabKey($firstTab));
                console.log('- Test tracking call...');
                this.trackTabView($firstTab);
            }
        },

        /**
         * Debounce function
         */
        debounce: function(func, wait, immediate) {
            var timeout;
            return function() {
                var context = this, args = arguments;
                var later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                var callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        },

        /**
         * Get current active tab
         */
        getCurrentTab: function() {
            return $('.woocommerce-tabs .tabs li.active a');
        },

        /**
         * Get all visible tabs
         */
        getVisibleTabs: function() {
            return $('.woocommerce-tabs .tabs li:visible a');
        },

        /**
         * Check if tab exists
         */
        tabExists: function(tabKey) {
            return $('.woocommerce-tabs .tabs a[href="#' + tabKey + '"]').length > 0;
        },

        /**
         * Activate tab by key
         */
        activateTab: function(tabKey) {
            var $tab = $('.woocommerce-tabs .tabs a[href="#' + tabKey + '"]');
            if ($tab.length) {
                $tab.trigger('click');
                return true;
            }
            return false;
        }
    };

    /**
     * Tab Analytics Helper
     */
    var SPTAnalytics = {
        
        /**
         * Track custom event
         */
        trackEvent: function(eventType, tabKey, data) {
            if (!SPTFrontend.config.trackViews) {
                return;
            }
            
            $.ajax({
                url: SPTFrontend.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'spt_track_custom_event',
                    event_type: eventType,
                    tab_key: tabKey,
                    product_id: SPTFrontend.config.productId,
                    data: data,
                    nonce: SPTFrontend.config.nonce
                }
            });
        },

        /**
         * Track tab engagement time
         */
        trackEngagementTime: function(tabKey, timeSpent) {
            this.trackEvent('engagement_time', tabKey, { time_spent: timeSpent });
        },

        /**
         * Track tab interaction
         */
        trackInteraction: function(tabKey, interactionType) {
            this.trackEvent('interaction', tabKey, { interaction_type: interactionType });
        }
    };

    /**
     * Initialize everything when document is ready
     */
    $(document).ready(function() {
        // Only initialize on product pages with tabs
        if ($('.woocommerce-tabs').length) {
            SPTFrontend.init();
            
            // Make SPTFrontend globally accessible for debugging
            window.SPTFrontend = SPTFrontend;
            window.SPTAnalytics = SPTAnalytics;
            
            // Trigger initialization complete event
            $(document).trigger('spt:initialized');
        }
    });

})(jQuery);
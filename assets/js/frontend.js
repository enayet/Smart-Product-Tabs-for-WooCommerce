/**
 * Frontend JavaScript for Smart Product Tabs
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
            lazyLoad: true,
            animationSpeed: 300,
            mobileBreakpoint: 768,
            accordionMode: false
        },

        /**
         * Initialize
         */
        init: function() {
            this.loadConfig();
            this.bindEvents();
            this.initMobileOptimization();
            this.initAnalyticsTracking();
            this.initLazyLoading();
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
            // Tab click events
            $(document).on('click', '.woocommerce-tabs .tabs li a', this.handleTabClick);
            
            // Mobile accordion toggle
            $(document).on('click', '.spt-mobile-tab-header', this.toggleMobileTab);
            
            // Lazy loading trigger
            $(document).on('click', '.spt-lazy-tab', this.loadTabContent);
            
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
         * Handle tab click
         */
        handleTabClick: function(e) {
            var $tab = $(this);
            var $tabPanel = $($tab.attr('href'));
            
            // Track tab view
            SPTFrontend.trackTabView($tab);
            
            // Handle lazy loading
            if ($tabPanel.hasClass('spt-lazy-content') && !$tabPanel.data('loaded')) {
                SPTFrontend.loadTabContent.call($tabPanel[0]);
            }
            
            // Update URL hash for deep linking
            if ($tab.attr('href').indexOf('#tab-') === 0) {
                SPTFrontend.updateUrlHash($tab.attr('href'));
            }
            
            // Custom event
            $(document).trigger('spt:tab-activated', [$tab, $tabPanel]);
        },

        /**
         * Track tab view
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
                    if (response.success) {
                        sessionStorage.setItem(trackingKey, '1');
                    }
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
            
            // Check for mobile-hidden tabs
            this.handleMobileHiddenTabs();
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
            
            // Convert tabs to accordion on mobile if enabled
            if (this.config.accordionMode) {
                this.convertToAccordion($tabsContainer);
            }
            
            // Optimize tab navigation for mobile
            this.optimizeTabNavigation($tabsContainer);
        },

        /**
         * Convert tabs to accordion for mobile
         */
        convertToAccordion: function($container) {
            var $tabs = $container.find('.tabs li');
            var $panels = $container.find('.woocommerce-Tabs-panel');
            
            var $accordion = $('<div class="spt-mobile-accordion">');
            
            $tabs.each(function(index) {
                var $tab = $(this);
                var $link = $tab.find('a');
                var tabId = $link.attr('href');
                var $panel = $(tabId);
                
                if ($panel.length) {
                    var $accordionItem = $(`
                        <div class="spt-accordion-item" data-tab="${tabId}">
                            <div class="spt-mobile-tab-header">
                                <span class="tab-title">${$link.text()}</span>
                                <span class="tab-toggle">+</span>
                            </div>
                            <div class="spt-mobile-tab-content">
                                ${$panel.html()}
                            </div>
                        </div>
                    `);
                    
                    $accordion.append($accordionItem);
                }
            });
            
            // Hide original tabs on mobile
            $container.find('.tabs, .woocommerce-Tabs-panel').hide();
            $container.append($accordion);
        },

        /**
         * Optimize tab navigation for mobile
         */
        optimizeTabNavigation: function($container) {
            var $tabsList = $container.find('.tabs');
            
            if ($tabsList.length) {
                // Add swipe/scroll functionality
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
         * Handle mobile hidden tabs
         */
        handleMobileHiddenTabs: function() {
            if (this.isMobile()) {
                $('.spt-mobile-hidden').hide();
            }
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
                
                // Track view
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
                        // Swipe left - next tab
                        SPTFrontend.navigateTab('next');
                    } else {
                        // Swipe right - previous tab
                        SPTFrontend.navigateTab('prev');
                    }
                }
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
         * Initialize lazy loading
         */
        initLazyLoading: function() {
            if (!this.config.lazyLoad) {
                return;
            }
            
            // Mark non-active tabs for lazy loading
            $('.woocommerce-Tabs-panel').not('.active').each(function() {
                var $panel = $(this);
                if (!$panel.hasClass('spt-lazy-content')) {
                    $panel.addClass('spt-lazy-content');
                    $panel.data('original-content', $panel.html());
                    $panel.html('<div class="spt-loading-placeholder">Click to load content...</div>');
                }
            });
        },

        /**
         * Load tab content (lazy loading)
         */
        loadTabContent: function() {
            var $panel = $(this);
            
            if ($panel.data('loaded')) {
                return;
            }
            
            // Show loading indicator
            $panel.html('<div class="spt-loading">Loading...</div>');
            
            // Simulate content loading (replace with actual AJAX call if needed)
            setTimeout(function() {
                var originalContent = $panel.data('original-content');
                $panel.html(originalContent);
                $panel.data('loaded', true);
                $panel.removeClass('spt-lazy-content');
                
                // Trigger content loaded event
                $(document).trigger('spt:content-loaded', [$panel]);
            }, 500);
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
                // Small delay to ensure page is fully loaded
                setTimeout(function() {
                    $tab.trigger('click');
                    
                    // Scroll to tabs if needed
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
            
            // Update mobile hidden tabs
            SPTFrontend.handleMobileHiddenTabs();
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
            
            // Show mobile-hidden tabs on desktop
            $('.spt-mobile-hidden').show();
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
        },

        /**
         * Get tab content
         */
        getTabContent: function(tabKey) {
            var $panel = $('#' + tabKey);
            return $panel.length ? $panel.html() : null;
        },

        /**
         * Update tab content
         */
        updateTabContent: function(tabKey, content) {
            var $panel = $('#' + tabKey);
            if ($panel.length) {
                $panel.html(content);
                $(document).trigger('spt:content-updated', [$panel, content]);
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
     * Enhanced Tab Features
     */
    var SPTEnhanced = {
        
        /**
         * Initialize enhanced features
         */
        init: function() {
            this.initTabBadges();
            this.initTabTooltips();
            this.initTabAnimations();
            this.initTabSearch();
        },

        /**
         * Initialize tab badges (notifications, counts, etc.)
         */
        initTabBadges: function() {
            $('.woocommerce-tabs .tabs a').each(function() {
                var $tab = $(this);
                var badge = $tab.data('badge');
                
                if (badge) {
                    $tab.append('<span class="spt-tab-badge">' + badge + '</span>');
                }
            });
        },

        /**
         * Initialize tab tooltips
         */
        initTabTooltips: function() {
            $('.woocommerce-tabs .tabs a[title]').each(function() {
                var $tab = $(this);
                var title = $tab.attr('title');
                
                if (title) {
                    $tab.on('mouseenter', function() {
                        SPTEnhanced.showTooltip($tab, title);
                    }).on('mouseleave', function() {
                        SPTEnhanced.hideTooltip();
                    });
                }
            });
        },

        /**
         * Show tooltip
         */
        showTooltip: function($element, text) {
            var $tooltip = $('<div class="spt-tooltip">' + text + '</div>');
            $('body').append($tooltip);
            
            var offset = $element.offset();
            $tooltip.css({
                top: offset.top - $tooltip.outerHeight() - 10,
                left: offset.left + ($element.outerWidth() / 2) - ($tooltip.outerWidth() / 2)
            }).fadeIn(200);
        },

        /**
         * Hide tooltip
         */
        hideTooltip: function() {
            $('.spt-tooltip').fadeOut(200, function() {
                $(this).remove();
            });
        },

        /**
         * Initialize tab animations
         */
        initTabAnimations: function() {
            // Add smooth transitions for tab content
            $('.woocommerce-Tabs-panel').css({
                'transition': 'opacity 0.3s ease-in-out',
                'opacity': '0'
            });
            
            // Show active tab with animation
            $('.woocommerce-Tabs-panel.active').css('opacity', '1');
            
            // Handle tab switching animations
            $(document).on('spt:tab-activated', function(e, $tab, $panel) {
                $('.woocommerce-Tabs-panel').css('opacity', '0');
                $panel.css('opacity', '1');
            });
        },

        /**
         * Initialize tab search functionality
         */
        initTabSearch: function() {
            if ($('.spt-tab-search').length) {
                $('.spt-tab-search input').on('input', function() {
                    var query = $(this).val().toLowerCase();
                    SPTEnhanced.filterTabs(query);
                });
            }
        },

        /**
         * Filter tabs based on search query
         */
        filterTabs: function(query) {
            $('.woocommerce-tabs .tabs li').each(function() {
                var $tab = $(this);
                var text = $tab.find('a').text().toLowerCase();
                
                if (text.indexOf(query) !== -1 || query === '') {
                    $tab.show();
                } else {
                    $tab.hide();
                }
            });
        }
    };

    /**
     * Initialize everything when document is ready
     */
    $(document).ready(function() {
        // Only initialize on product pages with tabs
        if ($('.woocommerce-tabs').length) {
            SPTFrontend.init();
            SPTEnhanced.init();
            
            // Make SPTFrontend globally accessible for debugging
            window.SPTFrontend = SPTFrontend;
            window.SPTAnalytics = SPTAnalytics;
            
            // Trigger initialization complete event
            $(document).trigger('spt:initialized');
        }
    });

})(jQuery);
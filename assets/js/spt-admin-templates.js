/**
 * FIXED Smart Product Tabs - Admin Templates JavaScript
 * Complete replacement for assets/js/spt-admin-templates.js
 */

(function($) {
    'use strict';
    
    // Ensure DOM is ready
    $(document).ready(function() {
        // Initialize template functionality
        if (typeof SPTTemplates !== 'undefined') {
            SPTTemplates.init();
        }
    });

})(jQuery);

/**
 * FIXED Main Templates Object
 */
var SPTTemplates = {
    
    /**
     * Initialize all template functionality
     */
    init: function() {
        this.bindEvents();
        this.setupModalHandlers();
    },
    
    /**
     * Bind all event handlers
     */
    bindEvents: function() {
        var $ = jQuery;
        
        // Template preview buttons
        $(document).on('click', '.template-preview-btn', this.handlePreview);
        
        // Template install buttons - FIXED to use modal instead of confirm()
        $(document).on('click', '.template-install', this.handleInstall);
        
        // Import functionality
        $(document).on('click', '#import-template-file', this.handleFileImport);
        $(document).on('click', '#import-template-text', this.handleTextImport);
        
        // Export functionality
        $(document).on('click', '#export-rules', this.handleExport);
    },
    
    /**
     * Setup modal event handlers
     */
    setupModalHandlers: function() {
        var $ = jQuery;
        
        // Modal close handlers - Fixed with better error checking
        $(document).off('click', '.spt-modal-close, #close-preview').on('click', '.spt-modal-close, #close-preview', function(e) {
            e.preventDefault();
            e.stopPropagation();
            try {
                $('#template-preview-modal').hide();
            } catch (error) {
                console.warn('Error closing modal:', error);
            }
        });
        
        // Close modal on backdrop click - Fixed with better error checking
        $(document).off('click', '.spt-modal').on('click', '.spt-modal', function(e) {
            try {
                if (e.target === this) {
                    $(this).hide();
                }
            } catch (error) {
                console.warn('Error closing modal on backdrop click:', error);
            }
        });
        
        // Install from preview modal - Fixed with better error checking
        $(document).off('click', '#install-from-preview').on('click', '#install-from-preview', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            try {
                var templateKey = $(this).data('template-key') || $('.template-preview-btn[data-template-key]').first().data('template-key');
                var templateName = $(this).data('template-name') || $('.template-preview-btn[data-template-key]').first().data('template-name');
                
                if (!templateKey) {
                    console.error('No template key found for install from preview');
                    return;
                }
                
                var $button = $('.template-install[data-template-key="' + templateKey + '"]');
                var $card = $button.closest('.template-card');
                
                $('#template-preview-modal').hide();
                SPTTemplates.showInstallConfirmationModal(templateKey, templateName, $button, $card);
            } catch (error) {
                console.error('Error in install from preview:', error);
            }
        });
    },

    /**
     * Get AJAX URL with fallback
     */
    getAjaxUrl: function() {
        return (typeof spt_admin_ajax !== 'undefined' && spt_admin_ajax.ajax_url) ? 
               spt_admin_ajax.ajax_url : 
               ajaxurl || '/wp-admin/admin-ajax.php';
    },

    /**
     * Get AJAX nonce with fallback
     */
    getAjaxNonce: function() {
        var nonce = '';
        
        // Try multiple sources for nonce
        if (typeof spt_admin_ajax !== 'undefined' && spt_admin_ajax.nonce) {
            nonce = spt_admin_ajax.nonce;
        } else if ($('#spt-ajax-nonce').length) {
            nonce = $('#spt-ajax-nonce').val();
        }
        
        if (!nonce) {
            console.warn('SPT Templates: No nonce found. Some functionality may not work.');
        }
        return nonce || '';
    },
    
    /**
     * Show success message
     */
    showSuccessMessage: function(message) {
        var $ = jQuery;
        var $notice = $('<div class="notice notice-success is-dismissible"><p>' + message + '</p></div>');
        $('.spt-templates, .wrap').first().prepend($notice);
        
        setTimeout(function() {
            $notice.fadeOut();
        }, 5000);
    },
    
    /**
     * Show error message
     */
    showErrorMessage: function(message) {
        var $ = jQuery;
        var $notice = $('<div class="notice notice-error is-dismissible"><p>' + message + '</p></div>');
        $('.spt-templates, .wrap').first().prepend($notice);
        
        setTimeout(function() {
            $notice.fadeOut();
        }, 8000);
    },

    /**
     * FIXED: Show enhanced confirmation modal for template installation
     */
    showInstallConfirmationModal: function(templateKey, templateName, $button, $card) {
        var $ = jQuery;
        
        var modalHtml = 
            '<div id="install-confirmation-modal" class="spt-modal" style="display: flex;">' +
                '<div class="spt-modal-content install-confirmation-modal">' +
                    '<div class="spt-modal-header">' +
                        '<h3>⚠️ Confirm Template Installation</h3>' +
                        '<button type="button" class="spt-modal-close" aria-label="Close">&times;</button>' +
                    '</div>' +
                    
                    '<div class="spt-modal-body">' +
                        '<div class="install-warning-content">' +
                            '<div class="warning-icon">' +
                                '<span class="dashicons dashicons-warning"></span>' +
                            '</div>' +
                            
                            '<div class="warning-message">' +
                                '<h4>Install Template: ' + templateName + '</h4>' +
                                '<p>This action will:</p>' +
                                '<ul class="warning-list">' +
                                    '<li>Remove all existing tab rules</li>' +
                                    '<li>Install new rules from the template</li>' +
                                    '<li>This action cannot be undone</li>' +
                                '</ul>' +
                            '</div>' +
                        '</div>' +
                        
                        '<div class="recommendation">' +
                            '<p><strong>Recommendation:</strong> Export your current configuration first to create a backup.</p>' +
                        '</div>' +
                    '</div>' +
                    
                    '<div class="spt-modal-footer">' +
                        '<button type="button" class="button" id="cancel-install">Cancel</button>' +
                        '<button type="button" class="button button-secondary" id="export-first">Export First</button>' +
                        '<button type="button" class="button button-primary install-confirm-btn" ' +
                                'data-template-key="' + templateKey + '" ' +
                                'data-template-name="' + templateName + '" ' +
                                'style="background-color: #d63384; border-color: #d63384;">Install & Remove Existing</button>' +
                    '</div>' +
                '</div>' +
            '</div>';
        
        // Remove existing modal if present
        $('#install-confirmation-modal').remove();
        
        // Add modal to body
        $('body').append(modalHtml);
        
        // Bind modal events
        this.bindInstallModalEvents($button, $card);
    },

    /**
     * FIXED: Bind events for the installation confirmation modal
     */
    bindInstallModalEvents: function($originalButton, $originalCard) {
        var $ = jQuery;
        var $modal = $('#install-confirmation-modal');
        
        // Close modal handlers
        $modal.on('click', '.spt-modal-close, #cancel-install', function() {
            $modal.remove();
        });
        
        // Close on backdrop click
        $modal.on('click', function(e) {
            if (e.target === this) {
                $modal.remove();
            }
        });
        
        // Export first button
        $modal.on('click', '#export-first', function() {
            $modal.remove();
            // Trigger existing export functionality
            $('#export-rules').click();
        });
      
        // Confirm installation button
        $modal.on('click', '.install-confirm-btn', function() {
            var templateKey = $(this).data('template-key');
            var templateName = $(this).data('template-name');
            
            $modal.remove();
            
            // Proceed with installation
            SPTTemplates.proceedWithInstallation(templateKey, templateName, $originalButton, $originalCard);
        });
    },

    /**
     * FIXED: Proceed with actual template installation
     */
proceedWithInstallation: function(templateKey, templateName, $button, $card) {
    var $ = jQuery;
    
    // Show loading state
    $button.prop('disabled', true).text('Installing...');
    $card.addClass('loading');
    
    // Show installation progress modal with animation
    this.showInstallationProgressModal(templateName);
    
    // Delay the actual AJAX call to let the animation play
    setTimeout(function() {
        $.ajax({
            url: SPTTemplates.getAjaxUrl(),
            type: 'POST',
            data: {
                action: 'spt_install_builtin_template',
                template_key: templateKey,
                remove_existing: true,
                nonce: SPTTemplates.getAjaxNonce()
            },
            success: function(response) {
                $button.prop('disabled', false).text('Install');
                $card.removeClass('loading');
                
                if (response.success) {
                    // Small delay to show completion of step 3
                    setTimeout(function() {
                        $('.step[data-step="3"]').removeClass('active').addClass('completed');
                        // Then show success modal
                        setTimeout(function() {
                            SPTTemplates.showInstallationSuccessModal(templateName, response.data.imported || 0);
                        }, 800);
                    }, 500);
                } else {
                    SPTTemplates.showInstallationErrorModal(response.data || 'Installation failed');
                }
            },
            error: function(xhr, status, error) {
                $button.prop('disabled', false).text('Install');
                $card.removeClass('loading');
                
                console.error('Installation AJAX Error:', {xhr: xhr, status: status, error: error});
                SPTTemplates.showInstallationErrorModal('Installation failed. Please try again.');
            }
        });
    }, 4000); // Start AJAX after 4 seconds to let animation complete
},
    
    
    /**
     * Handle template preview
     */
    handlePreview: function(e) {
        var $ = jQuery;
        e.preventDefault();
        
        var templateKey = $(this).data('template-key');
        var templateName = $(this).data('template-name') || 'Unknown Template';
        var $modalBody = $('#template-preview-modal .spt-modal-body');
        
        console.log('Preview clicked for template:', templateKey, templateName);
        
        // Show modal with loading state
        $('#template-preview-modal').show();
        $('#preview-template-title').text('Loading Preview...');
        $modalBody.html('<div class="spt-modal-loading"><div class="spt-modal-loading-content"><div class="spt-modal-loading-spinner"></div><div id="preview-template-content">Loading template preview...</div></div></div>');
        
        // Store template key in install button for later use
        $('#install-from-preview').data('template-key', templateKey).data('template-name', templateName);
        
        $.ajax({
            url: SPTTemplates.getAjaxUrl(),
            type: 'POST',
            data: {
                action: 'spt_get_template_preview',
                template_key: templateKey,
                nonce: SPTTemplates.getAjaxNonce()
            },
            success: function(response) {
                console.log('Preview response:', response);
                
                if (response.success && response.data) {
                    var template = response.data;
                    
                    // Update modal title
                    $('#preview-template-title').text('Template Preview: ' + (template.name || templateName));
                    
                    // Generate preview content using the correct method
                    var content = SPTTemplates.generateTemplatePreviewHTML(template);
                    $('#preview-template-content').html(content);
                    
                } else {
                    $('#preview-template-title').text('Preview Error');
                    $('#preview-template-content').html(
                        '<div class="spt-status-message error">' +
                        '<p><strong>Error:</strong> ' + (response.data || 'Unable to load template preview') + '</p>' +
                        '</div>'
                    );
                }
            },
            error: function(xhr, status, error) {
                console.error('Preview AJAX Error:', {xhr: xhr, status: status, error: error});
                $('#preview-template-title').text('Preview Error');
                $('#preview-template-content').html(
                    '<div class="spt-status-message error">' +
                    '<p><strong>Error:</strong> Failed to load template preview. Please try again.</p>' +
                    '</div>'
                );
            }
        });
    },  
    
    
    /**
     * FIXED: Handle template installation - NO MORE CONFIRM BOX
     */
    handleInstall: function(e) {
        var $ = jQuery;
        e.preventDefault();

        var templateKey = $(this).data('template-key');
        var templateName = $(this).data('template-name') || 'this template';
        var $button = $(this);
        var $card = $button.closest('.template-card');

        // Show enhanced confirmation modal instead of simple confirm()
        SPTTemplates.showInstallConfirmationModal(templateKey, templateName, $button, $card);
    },
    
    /**
     * Generate template preview HTML
     */
    generateTemplatePreviewHTML: function(template) {
        var content = '<div class="template-preview-details">';
        content += '<p><strong>Name:</strong> ' + (template.name || 'Unknown') + '</p>';
        content += '<p><strong>Description:</strong> ' + (template.description || 'No description') + '</p>';
        content += '<p><strong>Version:</strong> ' + (template.version || '1.0') + '</p>';
        
        if (template.author) {
            content += '<p><strong>Author:</strong> ' + template.author + '</p>';
        }
        
        if (template.rules) {
            content += '<p><strong>Rules Count:</strong> ' + template.rules.length + '</p>';
        } else {
            content += '<p><strong>Rules Count:</strong> ' + (template.rules_count || 0) + '</p>';
        }
        
        if (template.rules && template.rules.length > 0) {
            content += '<h4>Tab Rules Preview:</h4>';
            content += '<div class="tabs-preview-list">';
            
            template.rules.forEach(function(rule) {
                content += '<div class="tab-preview-item">';
                content += '<h5>' + (rule.tab_title || 'Untitled Tab') + '</h5>';
                content += '<p><strong>Rule Name:</strong> ' + (rule.rule_name || 'Unnamed Rule') + '</p>';
                content += '<p><strong>Priority:</strong> ' + (rule.priority || 10) + '</p>';
                content += '<p><strong>Status:</strong> ' + (rule.is_active ? 'Active' : 'Inactive') + '</p>';
                
                // Show conditions if available
                if (rule.conditions && rule.conditions.length > 0) {
                    content += '<p><strong>Conditions:</strong> ' + rule.conditions.length + ' condition(s)</p>';
                }
                
                content += '</div>';
            });
            
            content += '</div>';
        }
        content += '</div>';
        
        return content;
    },

    /**
     * Show installation progress modal
     */
showInstallationProgressModal: function(templateName) {
    var $ = jQuery;
    
    var progressHtml = 
        '<div id="install-progress-modal" class="spt-modal" style="display: flex;">' +
            '<div class="spt-modal-content install-progress-modal">' +
                '<div class="spt-modal-header">' +
                    '<h3>Installing Template</h3>' +
                '</div>' +
                
                '<div class="spt-modal-body">' +
                    '<div class="install-progress-content">' +
                        '<div class="progress-spinner">' +
                            '<div class="spinner"></div>' +
                        '</div>' +
                        
                        '<h4>Installing: ' + templateName + '</h4>' +
                        '<p>Please wait while the template is being installed...</p>' +
                        
                        '<div class="progress-steps">' +
                            '<div class="step" data-step="1">' +
                                '<div class="step-number">1</div>' +
                                '<div class="step-text">Preparing installation</div>' +
                            '</div>' +
                            '<div class="step" data-step="2">' +
                                '<div class="step-number">2</div>' +
                                '<div class="step-text">Removing existing rules</div>' +
                            '</div>' +
                            '<div class="step" data-step="3">' +
                                '<div class="step-number">3</div>' +
                                '<div class="step-text">Installing new rules</div>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>' +
        '</div>';
    
    // Remove existing modal
    $('#install-progress-modal').remove();
    
    // Add to body
    $('body').append(progressHtml);
    
    // Start the animated progress sequence
    SPTTemplates.animateProgressSteps();
},
    
 /*    
 * Animate progress steps sequentially
 */
animateProgressSteps: function() {
    var $ = jQuery;
    
    // Reset all steps to inactive state
    $('.progress-steps .step').removeClass('active completed');
    
    // Animate steps one by one with delays
    setTimeout(function() {
        $('.step[data-step="1"]').addClass('active');
    }, 500); // First step after 0.5s
    
    setTimeout(function() {
        $('.step[data-step="1"]').removeClass('active').addClass('completed');
        $('.step[data-step="2"]').addClass('active');
    }, 2000); // Second step after 2s total
    
    setTimeout(function() {
        $('.step[data-step="2"]').removeClass('active').addClass('completed');
        $('.step[data-step="3"]').addClass('active');
    }, 3500); // Third step after 3.5s total
    
    // Keep the third step active until installation completes
},    
    

    /**
     * Show installation success modal
     */
    showInstallationSuccessModal: function(templateName, importedCount) {
        var $ = jQuery;
        
        $('#install-progress-modal').remove();
        
        var successHtml = 
            '<div id="install-success-modal" class="spt-modal" style="display: flex;">' +
                '<div class="spt-modal-content install-success-modal">' +
                    '<div class="spt-modal-header">' +
                        '<h3>✅ Installation Successful</h3>' +
                        '<button type="button" class="spt-modal-close" aria-label="Close">&times;</button>' +
                    '</div>' +
                    
                    '<div class="spt-modal-body">' +
                        '<div class="success-content">' +
                            '<div class="success-icon">' +
                                '<span class="dashicons dashicons-yes"></span>' +
                            '</div>' +
                            
                            '<div class="success-message">' +
                                '<h4>Template installed successfully!</h4>' +
                                '<p><strong>Template:</strong> ' + templateName + '</p>' +
                                '<p><strong>Rules imported:</strong> ' + importedCount + '</p>' +
                                '<p>Your template has been installed and is ready to use.</p>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                    
                    '<div class="spt-modal-footer">' +
                        '<button type="button" class="button" id="close-success">Close</button>' +
                        '<button type="button" class="button button-primary" id="view-installed-tabs">View Installed Tabs</button>' +
                    '</div>' +
                '</div>' +
            '</div>';
        
        $('body').append(successHtml);
        
        // Bind success modal events
        $('#install-success-modal').on('click', '.spt-modal-close, #close-success', function() {
            $('#install-success-modal').remove();
            // Reload page to show updated tabs
            location.reload();
        });
        
        $('#install-success-modal').on('click', '#view-installed-tabs', function() {
            $('#install-success-modal').remove();
            // Redirect to tab rules page
            window.location.href = 'admin.php?page=smart-product-tabs';
        });
    },

    /**
     * Show installation error modal
     */
    showInstallationErrorModal: function(errorMessage) {
        var $ = jQuery;
        
        $('#install-progress-modal').remove();
        
        var errorHtml = 
            '<div id="install-error-modal" class="spt-modal" style="display: flex;">' +
                '<div class="spt-modal-content install-error-modal">' +
                    '<div class="spt-modal-header">' +
                        '<h3>❌ Installation Failed</h3>' +
                        '<button type="button" class="spt-modal-close" aria-label="Close">&times;</button>' +
                    '</div>' +
                    
                    '<div class="spt-modal-body">' +
                        '<div class="error-content">' +
                            '<div class="error-icon">' +
                                '<span class="dashicons dashicons-warning"></span>' +
                            '</div>' +
                            
                            '<div class="error-message">' +
                                '<h4>Template installation failed</h4>' +
                                '<p><strong>Error:</strong> ' + errorMessage + '</p>' +
                                '<p>Your existing tabs have not been modified. Please try again or contact support.</p>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                    
                    '<div class="spt-modal-footer">' +
                        '<button type="button" class="button button-primary" id="close-error">Close</button>' +
                    '</div>' +
                '</div>' +
            '</div>';
        
        $('body').append(errorHtml);
        
        // Bind error modal events
        $('#install-error-modal').on('click', '.spt-modal-close, #close-error', function() {
            $('#install-error-modal').remove();
        });
    },

    /**
     * Handle file import
     */
    handleFileImport: function(e) {
        var $ = jQuery;
        e.preventDefault();
        
        var fileInput = $('#template_file')[0];
        if (!fileInput.files.length) {
            alert('Please select a file to import.');
            return;
        }

        var formData = new FormData();
        formData.append('action', 'spt_import_template');
        formData.append('import_type', 'file');
        formData.append('template_file', fileInput.files[0]);
        formData.append('nonce', SPTTemplates.getAjaxNonce());

        $(this).prop('disabled', true).text('Importing...');

        $.ajax({
            url: SPTTemplates.getAjaxUrl(),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    SPTTemplates.showSuccessMessage('Template imported successfully! ' + response.data.message);
                    location.reload();
                } else {
                    SPTTemplates.showErrorMessage('Import failed: ' + response.data);
                }
            },
            error: function() {
                SPTTemplates.showErrorMessage('Import failed. Please try again.');
            },
            complete: function() {
                $('#import-template-file').prop('disabled', false).text('Import File');
            }
        });
    },

    /**
     * Handle text import
     */
    handleTextImport: function(e) {
        var $ = jQuery;
        e.preventDefault();
        
        var templateText = $('#template_text').val().trim();
        if (!templateText) {
            alert('Please enter template data.');
            return;
        }

        $(this).prop('disabled', true).text('Importing...');

        $.ajax({
            url: SPTTemplates.getAjaxUrl(),
            type: 'POST',
            data: {
                action: 'spt_import_template',
                import_type: 'text',
                template_data: templateText,
                nonce: SPTTemplates.getAjaxNonce()
            },
            success: function(response) {
                if (response.success) {
                    SPTTemplates.showSuccessMessage('Template imported successfully! ' + response.data.message);
                    location.reload();
                } else {
                    SPTTemplates.showErrorMessage('Import failed: ' + response.data);
                }
            },
            error: function() {
                SPTTemplates.showErrorMessage('Import failed. Please try again.');
            },
            complete: function() {
                $('#import-template-text').prop('disabled', false).text('Import Text');
            }
        });
    },

    /**
     * Handle export
     */
    handleExport: function(e) {
        var $ = jQuery;
        e.preventDefault();

        $(this).prop('disabled', true).text('Exporting...');

        $.ajax({
            url: SPTTemplates.getAjaxUrl(),
            type: 'POST',
            data: {
                action: 'spt_export_rules',
                nonce: SPTTemplates.getAjaxNonce()
            },
            success: function(response) {
                if (response.success) {
                    // Create and download file
                    var blob = new Blob([response.data.data], { type: 'application/json' });
                    var url = window.URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = response.data.filename;
                    a.click();
                    window.URL.revokeObjectURL(url);
                    
                    SPTTemplates.showSuccessMessage('Export completed! ' + response.data.rules_count + ' rules exported.');
                } else {
                    SPTTemplates.showErrorMessage('Export failed: ' + response.data);
                }
            },
            error: function() {
                SPTTemplates.showErrorMessage('Export failed. Please try again.');
            },
            complete: function() {
                $('#export-rules').prop('disabled', false).text('Download Export File');
            }
        });
    }
}; 


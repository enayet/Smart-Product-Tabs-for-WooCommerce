/**
 * Smart Product Tabs - Admin Templates JavaScript
 * Enhanced functionality for template preview and installation
 */

jQuery(document).ready(function($) {
    

    



    // Initialize template functionality
    SPTTemplates.init();

/**
 * Main Templates Object
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
        // Template preview buttons
        $(document).on('click', '.template-preview-btn', this.handlePreview);
        
        // Template install buttons
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
        // Modal close handlers
        $(document).on('click', '.spt-modal-close, #close-preview', function() {
            $('#template-preview-modal').hide();
        });
        
        // Close modal when clicking backdrop
        $(document).on('click', '#template-preview-modal', function(e) {
            if (e.target === this) {
                $(this).hide();
            }
        });
        
        // Close modal with Escape key
        $(document).on('keydown', function(e) {
            if (e.keyCode === 27 && $('#template-preview-modal').is(':visible')) {
                $('#template-preview-modal').hide();
            }
        });
    },
    
    /**
     * Handle template preview
     */
    handlePreview: function(e) {
        e.preventDefault();
        
        var templateKey = $(this).data('template-key');
        var templateName = $(this).data('template-name') || 'Template';
        var $modal = $('#template-preview-modal');
        var $modalBody = $modal.find('.spt-modal-body');
        
        // Show modal immediately
        $modal.show();
        
        // Set initial title
        $('#preview-template-title').text('Loading Template Preview...');
        
        // Show loading spinner
        SPTTemplates.showModalSpinner($modalBody, 'Loading template details...');
        
        // Make AJAX request
        $.ajax({
            url: SPTTemplates.getAjaxUrl(),
            type: 'POST',
            data: {
                action: 'spt_get_template_preview',
                template_key: templateKey,
                nonce: SPTTemplates.getAjaxNonce()
            },
            success: function(response) {
                SPTTemplates.hideModalSpinner($modalBody);
                
                if (response.success && response.data) {
                    var template = response.data;
                    
                    // Update modal title
                    $('#preview-template-title').text('Template Preview: ' + (template.name || templateName));
                    
                    // Generate preview content
                    var content = SPTTemplates.generateTemplatePreviewHTML(template);
                    $('#preview-template-content').html(content);
                    
                    // Set up install button
                    $('#install-from-preview').off('click').on('click', function() {
                        $modal.hide();
                        // Trigger install on the original install button
                        $('.template-install[data-template-key="' + templateKey + '"]').click();
                    });
                    
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
                SPTTemplates.hideModalSpinner($modalBody);
                $('#preview-template-title').text('Preview Error');
                $('#preview-template-content').html(
                    '<div class="spt-status-message error">' +
                    '<p><strong>Error:</strong> Failed to load template preview. Please try again.</p>' +
                    '<p><em>Details: ' + error + '</em></p>' +
                    '</div>'
                );
            }
        });
    },
    
    /**
     * Handle template installation
     */
    handleInstall: function(e) {
        e.preventDefault();
        
        var templateKey = $(this).data('template-key');
        var templateName = $(this).data('template-name') || 'this template';
        var $button = $(this);
        var $card = $button.closest('.template-card');
        
        // Confirmation dialog
        if (!confirm('Install "' + templateName + '"?\n\nThis will add new tab rules to your site.')) {
            return;
        }
        
        // Show loading state
        $button.prop('disabled', true).text('Installing...');
        $card.addClass('loading');
        
        $.ajax({
            url: SPTTemplates.getAjaxUrl(),
            type: 'POST',
            data: {
                action: 'spt_install_builtin_template',
                template_key: templateKey,
                nonce: SPTTemplates.getAjaxNonce()
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    SPTTemplates.showSuccessMessage('Template installed successfully! ' + (response.data.imported || '0') + ' rules imported.');
                    
                    // Reload page after delay
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    SPTTemplates.showErrorMessage('Installation failed: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                SPTTemplates.showErrorMessage('Installation failed. Please try again.\nError: ' + error);
            },
            complete: function() {
                $button.prop('disabled', false).text('Install Template');
                $card.removeClass('loading');
            }
        });
    },
    
    

    
    
    
    /**
     * Handle file import
     */
    handleFileImport: function(e) {
        e.preventDefault();
        
        var fileInput = $('#template_file')[0];
        if (!fileInput.files.length) {
            alert('Please select a file to import.');
            return;
        }
        
        var formData = new FormData();
        formData.append('action', 'spt_import_template_file');
        formData.append('template_file', fileInput.files[0]);
        formData.append('nonce', SPTTemplates.getAjaxNonce());
        
        var $button = $(this);
        $button.prop('disabled', true).text('Importing...');
        
        $.ajax({
            url: SPTTemplates.getAjaxUrl(),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    SPTTemplates.showSuccessMessage('Template imported successfully! ' + response.data.imported + ' rules imported.');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    SPTTemplates.showErrorMessage('Import failed: ' + response.data);
                }
            },
            error: function() {
                SPTTemplates.showErrorMessage('Import failed. Please try again.');
            },
            complete: function() {
                $button.prop('disabled', false).text('Import Template');
            }
        });
    },
    
    /**
     * Handle text import
     */
    handleTextImport: function(e) {
        e.preventDefault();
        
        var templateText = $('#template_text').val().trim();
        if (!templateText) {
            alert('Please enter template data to import.');
            return;
        }
        
        var $button = $(this);
        $button.prop('disabled', true).text('Importing...');
        
        $.ajax({
            url: SPTTemplates.getAjaxUrl(),
            type: 'POST',
            data: {
                action: 'spt_import_template_text',
                template_text: templateText,
                nonce: SPTTemplates.getAjaxNonce()
            },
            success: function(response) {
                if (response.success) {
                    SPTTemplates.showSuccessMessage('Template imported successfully! ' + response.data.imported + ' rules imported.');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    SPTTemplates.showErrorMessage('Import failed: ' + response.data);
                }
            },
            error: function() {
                SPTTemplates.showErrorMessage('Import failed. Please try again.');
            },
            complete: function() {
                $button.prop('disabled', false).text('Import Template');
            }
        });
    },
    
    /**
     * Handle export
     */
    handleExport: function(e) {
        e.preventDefault();
        
        var $button = $(this);
        $button.prop('disabled', true).text('Exporting...');
        
        $.ajax({
            url: SPTTemplates.getAjaxUrl(),
            type: 'POST',
            data: {
                action: 'spt_export_rules',
                nonce: SPTTemplates.getAjaxNonce()
            },
            success: function(response) {
                if (response.success) {
                    // Create download link
                    var blob = new Blob([response.data.content], { type: 'application/json' });
                    var url = window.URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = url;
                    a.download = response.data.filename;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                    
                    SPTTemplates.showSuccessMessage('Export completed successfully!');
                } else {
                    SPTTemplates.showErrorMessage('Export failed: ' + response.data);
                }
            },
            error: function() {
                SPTTemplates.showErrorMessage('Export failed. Please try again.');
            },
            complete: function() {
                $button.prop('disabled', false).text('Download Export File');
            }
        });
    },
    
    /**
     * Show loading spinner in modal
     */
    showModalSpinner: function($container, message) {
        var spinnerHTML = 
            '<div class="spt-modal-loading">' +
                '<div class="spt-modal-loading-content">' +
                    '<div class="spt-modal-loading-spinner"></div>' +
                    '<div class="spt-modal-loading-text">' + (message || 'Loading...') + '</div>' +
                '</div>' +
            '</div>';
        
        $container.html(spinnerHTML);
    },
    
    /**
     * Hide loading spinner
     */
    hideModalSpinner: function($container) {
        $container.find('.spt-modal-loading').remove();
    },
    
    /**
     * Generate template preview HTML
     */
    generateTemplatePreviewHTML: function(template) {
        var html = '<div class="template-preview-details">';
        
        // Basic template information
        html += '<p><strong>Description:</strong> ' + (template.description || 'No description available') + '</p>';
        html += '<p><strong>Version:</strong> ' + (template.version || '1.0') + '</p>';
        html += '<p><strong>Author:</strong> ' + (template.author || 'Unknown') + '</p>';
        
        // Rules/tabs information
        if (template.rules && template.rules.length > 0) {
            html += '<p><strong>Number of Tabs:</strong> ' + template.rules.length + '</p>';
            
            html += '<h4>Included Tabs</h4>';
            html += '<div class="tabs-preview-list">';
            
            template.rules.forEach(function(rule, index) {
                html += '<div class="tab-preview-item">';
                html += '<h5>' + (rule.tab_title || 'Tab ' + (index + 1)) + '</h5>';
                
                if (rule.tab_type) {
                    html += '<p><strong>Type:</strong> ' + rule.tab_type + '</p>';
                }
                
                if (rule.conditions && rule.conditions.length > 0) {
                    html += '<p><strong>Conditions:</strong> ' + rule.conditions.length + ' condition(s)</p>';
                }
                
                if (rule.tab_content) {
                    var contentPreview = rule.tab_content.substring(0, 100);
                    if (rule.tab_content.length > 100) {
                        contentPreview += '...';
                    }
                    html += '<p><strong>Content Preview:</strong> ' + contentPreview + '</p>';
                }
                
                html += '</div>';
            });
            
            html += '</div>';
        } else {
            html += '<p><strong>Number of Tabs:</strong> 0 (No tabs included)</p>';
        }
        
        // Additional metadata
        if (template.tags && template.tags.length > 0) {
            html += '<p><strong>Tags:</strong> ' + template.tags.join(', ') + '</p>';
        }
        
        if (template.compatibility) {
            html += '<p><strong>Compatibility:</strong> ' + template.compatibility + '</p>';
        }
        
        html += '</div>';
        
        return html;
    },
    
    /**
     * Get AJAX URL with fallback
     */
    getAjaxUrl: function() {
        if (typeof spt_admin_ajax !== 'undefined' && spt_admin_ajax.ajax_url) {
            return spt_admin_ajax.ajax_url;
        }
        return ajaxurl || '/wp-admin/admin-ajax.php';
    },
    
    /**
     * Get AJAX nonce with fallback
     */
    getAjaxNonce: function() {
        if (typeof spt_admin_ajax !== 'undefined' && spt_admin_ajax.nonce) {
            return spt_admin_ajax.nonce;
        }
        // Fallback - look for nonce in hidden input or data attribute
        var nonce = $('#spt-ajax-nonce').val() || $('[data-spt-nonce]').data('spt-nonce');
        if (!nonce) {
            console.warn('SPT: No AJAX nonce found. Some functionality may not work.');
        }
        return nonce || '';
    },
    
    /**
     * Show success message
     */
    showSuccessMessage: function(message) {
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
        var $notice = $('<div class="notice notice-error is-dismissible"><p>' + message + '</p></div>');
        $('.spt-templates, .wrap').first().prepend($notice);
        
        setTimeout(function() {
            $notice.fadeOut();
        }, 8000);
    }
};
    
    
    });
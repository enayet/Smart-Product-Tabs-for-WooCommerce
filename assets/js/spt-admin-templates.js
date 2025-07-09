/**
 * Updated Smart Product Tabs - Admin Templates JavaScript
 * Removed template save functionality and copy/paste export
 * Only supports file download export
 */

(function($) {
    'use strict';

    /**
     * SPT Template Manager
     */
    var SPTTemplates = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initValidation();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Template installation
            $(document).on('click', '.template-install', this.installTemplate);
            
            // File upload import only
            $(document).on('submit', '.template-upload-form', this.uploadTemplate);
            
            // Template preview
            $(document).on('click', '.template-preview', this.previewTemplate);
            
            // File input change
            $(document).on('change', 'input[type="file"]', this.validateFileUpload);
        },

        /**
         * Initialize validation
         */
        initValidation: function() {
            // Add file validation
            this.setupFileValidation();
        },

        /**
         * Install built-in template
         */
        installTemplate: function(e) {
            e.preventDefault();
            
            if (typeof spt_admin_ajax === 'undefined') {
                alert('AJAX not available. Please refresh the page and try again.');
                return;
            }
            
            var templateKey = $(this).data('template-key');
            var templateName = $(this).data('template-name');
            var $button = $(this);
            
            if (!confirm('Install template "' + templateName + '"? This will add new tab rules to your site.')) {
                return;
            }
            
            $button.prop('disabled', true).text('Installing...');
            
            $.ajax({
                url: spt_admin_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'spt_install_builtin_template',
                    template_key: templateKey,
                    nonce: spt_admin_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        SPTTemplates.showSuccess('Template installed successfully! ' + response.data.imported + ' rules imported.');
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        SPTTemplates.showError('Installation failed: ' + response.data);
                    }
                },
                error: function() {
                    SPTTemplates.showError('Installation failed. Please try again.');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Install Template');
                }
            });
        },

        /**
         * Upload template file
         */
        uploadTemplate: function(e) {
            e.preventDefault();
            
            if (typeof spt_admin_ajax === 'undefined') {
                alert('AJAX not available. Please refresh the page and try again.');
                return;
            }

            var $form = $(this);
            var formData = new FormData(this);
            var $submitBtn = $form.find('input[type="submit"]');

            // Check if file is selected
            var fileInput = $form.find('input[type="file"]')[0];
            if (!fileInput.files.length) {
                alert('Please select a file to upload');
                return;
            }

            // Validate file type
            var fileName = fileInput.files[0].name;
            if (!fileName.toLowerCase().endsWith('.json')) {
                alert('Please select a valid JSON file');
                return;
            }

            formData.append('action', 'spt_import_template');
            formData.append('import_type', 'file');
            formData.append('nonce', spt_admin_ajax.nonce);

            $submitBtn.prop('disabled', true).val('Uploading...');

            $.ajax({
                url: spt_admin_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        var message = 'Import completed: ' + response.data.imported + ' rules imported';
                        if (response.data.skipped > 0) {
                            message += ', ' + response.data.skipped + ' skipped';
                        }
                        SPTTemplates.showSuccess(message);

                        // Clear form
                        $form[0].reset();

                        // Reload page
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        SPTTemplates.showError('Import failed: ' + response.data);
                    }
                },
                error: function() {
                    SPTTemplates.showError('Upload failed. Please try again.');
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).val('Import Template');
                }
            });
        },

        /**
         * Preview template before installation
         */
        previewTemplate: function(e) {
            e.preventDefault();
            
            var templateKey = $(this).data('template-key');
            var templateName = $(this).data('template-name');
            
            if (typeof spt_admin_ajax === 'undefined') {
                alert('Preview not available - AJAX not loaded');
                return;
            }

            $.ajax({
                url: spt_admin_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'spt_preview_template',
                    template_key: templateKey,
                    nonce: spt_admin_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        SPTTemplates.showTemplatePreview(templateName, response.data);
                    } else {
                        alert('Preview failed: ' + response.data);
                    }
                },
                error: function() {
                    alert('Preview failed. Please try again.');
                }
            });
        },

        /**
         * Show template preview in modal/popup
         */
        showTemplatePreview: function(templateName, templateData) {
            var preview = SPTTemplates.generatePreviewHTML(templateName, templateData);
            
            // Create modal
            var $modal = $('<div id="template-preview-modal" style="display:none;"><div class="modal-content">' + preview + '</div></div>');
            
            $('body').append($modal);
            
            // Simple modal styling
            $modal.css({
                position: 'fixed',
                top: 0,
                left: 0,
                width: '100%',
                height: '100%',
                backgroundColor: 'rgba(0,0,0,0.7)',
                zIndex: 100000,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center'
            });
            
            $modal.find('.modal-content').css({
                backgroundColor: '#fff',
                padding: '20px',
                borderRadius: '6px',
                maxWidth: '80%',
                maxHeight: '80%',
                overflow: 'auto'
            });
            
            $modal.show();
            
            // Close on click outside or escape
            $modal.on('click', function(e) {
                if (e.target === this) {
                    $(this).remove();
                }
            });
            
            $(document).on('keydown.modal', function(e) {
                if (e.keyCode === 27) { // Escape
                    $modal.remove();
                    $(document).off('keydown.modal');
                }
            });
        },

        /**
         * Generate preview HTML
         */
        generatePreviewHTML: function(templateName, templateData) {
            var html = '<h3>Template Preview: ' + templateName + '</h3>';
            html += '<p><strong>Rules:</strong> ' + (templateData.rules ? templateData.rules.length : 0) + '</p>';
            html += '<p><strong>Description:</strong> ' + (templateData.description || 'No description') + '</p>';
            html += '<button type="button" onclick="$(this).closest(\'#template-preview-modal\').remove();">Close</button>';
            return html;
        },

        /**
         * Validate template data
         */
        validateTemplateData: function(jsonString) {
            try {
                var data = JSON.parse(jsonString);
                
                // Basic validation
                if (!data || typeof data !== 'object') {
                    return { valid: false, error: 'Invalid data format' };
                }
                
                if (!data.rules || !Array.isArray(data.rules)) {
                    return { valid: false, error: 'No rules found in template' };
                }
                
                if (data.rules.length === 0) {
                    return { valid: false, error: 'Template contains no rules' };
                }
                
                // Validate each rule has required fields
                for (var i = 0; i < data.rules.length; i++) {
                    var rule = data.rules[i];
                    if (!rule.rule_name || !rule.tab_title) {
                        return { valid: false, error: 'Rule ' + (i + 1) + ' missing required fields' };
                    }
                }
                
                return { valid: true };
            } catch (e) {
                return { valid: false, error: 'Invalid JSON format: ' + e.message };
            }
        },

        /**
         * Setup file validation
         */
        setupFileValidation: function() {
            $(document).on('change', 'input[type="file"][accept=".json"]', function() {
                var file = this.files[0];
                if (file) {
                    var fileName = file.name.toLowerCase();
                    if (!fileName.endsWith('.json')) {
                        alert('Please select a valid JSON file');
                        $(this).val('');
                        return;
                    }
                    
                    // Check file size (5MB limit)
                    if (file.size > 5 * 1024 * 1024) {
                        alert('File too large. Maximum size is 5MB');
                        $(this).val('');
                        return;
                    }
                }
            });
        },

        /**
         * Validate JSON input - REMOVED (no longer needed)
         */

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
     * SPT Export Manager - UPDATED: Only file download, no copy/paste
     */
    var SPTExport = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            $(document).on('click', '#export-rules', this.exportRules);
        },

        /**
         * Export rules - UPDATED: Direct blob download only
         */
        exportRules: function(e) {
            e.preventDefault();
            
            if (typeof spt_admin_ajax === 'undefined') {
                alert('AJAX not available. Please refresh the page and try again.');
                return;
            }
            
            var $button = $(this);
            
            $button.prop('disabled', true).text('Exporting...');
            
            $.ajax({
                url: spt_admin_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'spt_export_rules',
                    nonce: spt_admin_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Always use blob download
                        SPTExport.downloadAsFile(response.data.data, response.data.filename);
                        SPTExport.showSuccess('Export file downloaded!');
                    } else {
                        SPTExport.showError('Export failed: ' + response.data);
                    }
                },
                error: function() {
                    SPTExport.showError('Export failed. Please try again.');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Download Export File');
                }
            });
        },

        /**
         * Download data as file
         */
        downloadAsFile: function(data, filename) {
            var blob = new Blob([data], { type: 'application/json' });
            var url = window.URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = filename || 'spt-export.json';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
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
        // Initialize based on page content
        if ($('.spt-templates').length || $('.template-upload-form').length) {
            SPTTemplates.init();
        }
        
        if ($('#export-rules').length || $('.export-section').length) {
            SPTExport.init();
        }
    });

    // Export for other modules to use
    window.SPTTemplates = SPTTemplates;
    window.SPTExport = SPTExport;

})(jQuery);
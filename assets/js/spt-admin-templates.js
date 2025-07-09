/**
 * FIXED: Smart Product Tabs - Admin Templates JavaScript
 * This fixes the file upload issues and JavaScript errors
 */

(function($) {
    'use strict';

    /**
     * SPT Template Manager - FIXED VERSION
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
            
            // FIXED: File upload import form handling
            $(document).on('submit', '.template-upload-form', this.uploadTemplate);
            
            // Template preview
            $(document).on('click', '.template-preview-btn', this.previewTemplate);
            
            // FIXED: File input change validation
            $(document).on('change', 'input[type="file"][name="template_file"]', this.validateFileUpload);
        },

        /**
         * Initialize validation
         */
        initValidation: function() {
            this.setupFileValidation();
        },

        /**
         * FIXED: Upload template file with proper error handling
         */
        uploadTemplate: function(e) {
            e.preventDefault();
            
            // Check if AJAX is available
            if (typeof spt_admin_ajax === 'undefined') {
                alert('AJAX not available. Please refresh the page and try again.');
                return;
            }

            var $form = $(this);
            var $submitBtn = $form.find('input[type="submit"]');

            // Validate file selection
            var fileInput = $form.find('input[type="file"][name="template_file"]')[0];
            if (!fileInput || !fileInput.files || !fileInput.files.length) {
                alert('Please select a file to upload');
                return;
            }

            var file = fileInput.files[0];

            // Validate file type
            if (!file.name.toLowerCase().endsWith('.json')) {
                alert('Please select a valid JSON file');
                return;
            }

            // Validate file size (5MB limit)
            if (file.size > 5 * 1024 * 1024) {
                alert('File too large. Maximum size is 5MB');
                return;
            }

            // FIXED: Proper FormData creation
            var formData = new FormData();
            formData.append('action', 'spt_import_template');
            formData.append('import_type', 'file');
            formData.append('nonce', spt_admin_ajax.nonce);
            formData.append('template_file', file); // Add the file directly
            
            // Add replace existing option
            var replaceExisting = $form.find('input[name="replace_existing"]:checked').length > 0;
            if (replaceExisting) {
                formData.append('replace_existing', '1');
            }

            $submitBtn.prop('disabled', true).val('Uploading...');

            $.ajax({
                url: spt_admin_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    console.log('Upload response:', response); // Debug logging
                    
                    if (response && response.success) {
                        var message = 'Import completed successfully!';
                        if (response.data) {
                            if (response.data.imported) {
                                message += ' ' + response.data.imported + ' rules imported';
                            }
                            if (response.data.updated) {
                                message += ', ' + response.data.updated + ' rules updated';
                            }
                            if (response.data.skipped) {
                                message += ', ' + response.data.skipped + ' rules skipped';
                            }
                        }
                        
                        SPTTemplates.showSuccess(message);

                        // Clear form
                        $form[0].reset();

                        // Reload page after delay
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        var errorMsg = 'Import failed';
                        if (response && response.data) {
                            errorMsg += ': ' + response.data;
                        }
                        SPTTemplates.showError(errorMsg);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Upload error:', xhr.responseText); // Debug logging
                    SPTTemplates.showError('Upload failed. Server error: ' + error);
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).val('Import Template');
                }
            });
        },

        /**
         * FIXED: File upload validation
         */
        validateFileUpload: function() {
            var file = this.files[0];
            var $input = $(this);
            
            if (file) {
                // Check file type
                if (!file.name.toLowerCase().endsWith('.json')) {
                    alert('Please select a valid JSON file');
                    $input.val(''); // Clear the input
                    return false;
                }
                
                // Check file size (5MB limit)
                if (file.size > 5 * 1024 * 1024) {
                    alert('File too large. Maximum size is 5MB');
                    $input.val(''); // Clear the input
                    return false;
                }
                
                console.log('File validated:', file.name, 'Size:', file.size + ' bytes');
            }
            
            return true;
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
                    action: 'spt_get_template_preview',
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
         * Show template preview in modal
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
            
            // Close handlers
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
         * Setup file validation
         */
        setupFileValidation: function() {
            // Remove any existing handlers to prevent duplicates
            $(document).off('change', 'input[type="file"][accept=".json"]');
            
            // Add new handler
            $(document).on('change', 'input[type="file"][accept=".json"]', function() {
                SPTTemplates.validateFileUpload.call(this);
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
     * SPT Export Manager - FIXED VERSION
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
         * Export rules - Direct blob download
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
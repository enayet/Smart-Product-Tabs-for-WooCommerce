/**
 * Smart Product Tabs - Admin Templates & Import/Export
 * Handles template management, import/export functionality
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
            
            // File upload import
            $(document).on('submit', '.template-upload-form', this.uploadTemplate);
            
            // Text import
            $(document).on('submit', '.template-text-form', this.importFromText);
            
            // Template preview
            $(document).on('click', '.template-preview', this.previewTemplate);
            
            // File input change
            $(document).on('change', 'input[type="file"]', this.validateFileUpload);
            
            // JSON validation on paste
            $(document).on('paste blur', 'textarea[name="template_data"]', this.validateJSONInput);
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
                        
                        // Update button state
                        $button.text('Installed').addClass('disabled').prop('disabled', true);
                        
                        // Optionally reload rules list
                        setTimeout(function() {
                            if (confirm('Would you like to view the new rules?')) {
                                window.location.href = spt_admin_ajax.rules_url || 'admin.php?page=smart-product-tabs';
                            }
                        }, 1000);
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
            var $submitBtn = $form.find('input[type="submit"]');
            var formData = new FormData(this);
            
            // Add AJAX data
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

                        // Reload page after success
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
         * Import from text/JSON
         */
        importFromText: function(e) {
            e.preventDefault();
            
            if (typeof spt_admin_ajax === 'undefined') {
                alert('AJAX not available. Please refresh the page and try again.');
                return;
            }

            var $form = $(this);
            var templateData = $form.find('textarea[name="template_data"]').val();
            var replaceExisting = $form.find('input[name="replace_existing"]').is(':checked');
            var $submitBtn = $form.find('input[type="submit"]');

            if (!templateData.trim()) {
                alert('Please paste template JSON data');
                return;
            }

            // Validate JSON before sending
            var validation = SPTTemplates.validateTemplateData(templateData);
            if (!validation.valid) {
                alert('Invalid JSON data: ' + validation.error);
                return;
            }

            $submitBtn.prop('disabled', true).val('Importing...');

            $.ajax({
                url: spt_admin_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'spt_import_template',
                    import_type: 'text',
                    template_data: templateData,
                    replace_existing: replaceExisting ? '1' : '0',
                    nonce: spt_admin_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var message = 'Import completed: ' + response.data.imported + ' rules imported';
                        if (response.data.skipped > 0) {
                            message += ', ' + response.data.skipped + ' skipped';
                        }
                        SPTTemplates.showSuccess(message);

                        // Clear form
                        $form.find('textarea[name="template_data"]').val('');

                        // Reload page
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        SPTTemplates.showError('Import failed: ' + response.data);
                    }
                },
                error: function() {
                    SPTTemplates.showError('Import failed. Please try again.');
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).val('Import from Text');
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
            
            if (templateData.description) {
                html += '<p><strong>Description:</strong> ' + templateData.description + '</p>';
            }
            
            if (templateData.rules && templateData.rules.length > 0) {
                html += '<h4>Included Rules:</h4><ul>';
                templateData.rules.forEach(function(rule, index) {
                    html += '<li><strong>' + rule.tab_title + '</strong>';
                    if (rule.rule_name !== rule.tab_title) {
                        html += ' (' + rule.rule_name + ')';
                    }
                    if (rule.conditions) {
                        try {
                            var conditions = JSON.parse(rule.conditions);
                            html += ' - ' + conditions.type + ': ' + conditions.value;
                        } catch (e) {
                            // Ignore JSON parse errors
                        }
                    }
                    html += '</li>';
                });
                html += '</ul>';
            }
            
            html += '<br><button type="button" onclick="jQuery(this).closest(\'#template-preview-modal\').remove();">Close</button>';
            
            return html;
        },

        /**
         * Validate file upload
         */
        validateFileUpload: function() {
            var $file = $(this);
            var file = this.files[0];
            
            if (!file) return;
            
            // Check file type
            if (file.type !== 'application/json' && !file.name.endsWith('.json')) {
                alert('Please select a JSON file');
                $file.val('');
                return;
            }
            
            // Check file size (max 1MB)
            if (file.size > 1024 * 1024) {
                alert('File too large. Maximum size is 1MB');
                $file.val('');
                return;
            }
            
            // Read and validate JSON
            var reader = new FileReader();
            reader.onload = function(e) {
                var validation = SPTTemplates.validateTemplateData(e.target.result);
                if (!validation.valid) {
                    alert('Invalid template file: ' + validation.error);
                    $file.val('');
                }
            };
            reader.readAsText(file);
        },

        /**
         * Validate JSON input
         */
        validateJSONInput: function() {
            var $textarea = $(this);
            var value = $textarea.val().trim();
            
            if (!value) return;
            
            setTimeout(function() {
                var validation = SPTTemplates.validateTemplateData(value);
                if (!validation.valid) {
                    $textarea.addClass('error');
                    $textarea.attr('title', 'Invalid JSON: ' + validation.error);
                } else {
                    $textarea.removeClass('error');
                    $textarea.removeAttr('title');
                }
            }, 500);
        },

        /**
         * Setup file validation
         */
        setupFileValidation: function() {
            // Add drag and drop for file areas
            $('.file-upload-area').on('dragover', function(e) {
                e.preventDefault();
                $(this).addClass('drag-hover');
            });
            
            $('.file-upload-area').on('dragleave', function(e) {
                e.preventDefault();
                $(this).removeClass('drag-hover');
            });
            
            $('.file-upload-area').on('drop', function(e) {
                e.preventDefault();
                $(this).removeClass('drag-hover');
                
                var files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    var $fileInput = $(this).find('input[type="file"]');
                    $fileInput[0].files = files;
                    $fileInput.trigger('change');
                }
            });
        },

        /**
         * Template validation helper
         */
        validateTemplateData: function(jsonString) {
            try {
                var data = JSON.parse(jsonString);

                if (!data.rules || !Array.isArray(data.rules)) {
                    return { valid: false, error: 'Template must contain a rules array' };
                }

                for (var i = 0; i < data.rules.length; i++) {
                    var rule = data.rules[i];
                    if (!rule.rule_name || !rule.tab_title) {
                        return { 
                            valid: false, 
                            error: 'Rule ' + (i + 1) + ' is missing required fields (rule_name, tab_title)' 
                        };
                    }
                }

                return { valid: true, data: data };
            } catch (e) {
                return { valid: false, error: 'Invalid JSON format: ' + e.message };
            }
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
     * SPT Export Manager
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
            $(document).on('change', '#export_format', this.handleFormatChange);
        },

        /**
         * Handle export format change
         */
        handleFormatChange: function() {
            var format = $(this).val();
            var $exportBtn = $('#export-rules');
            
            if (format === 'file') {
                $exportBtn.text('Download Export File');
            } else {
                $exportBtn.text('Generate Export Data');
            }
        },

        /**
         * Export rules
         */
        exportRules: function(e) {
            e.preventDefault();
            
            if (typeof spt_admin_ajax === 'undefined') {
                alert('AJAX not available. Please refresh the page and try again.');
                return;
            }
            
            var includeSettings = $('#export_include_settings').is(':checked');
            var exportFormat = $('#export_format').val();
            var $button = $(this);
            
            $button.prop('disabled', true).text('Exporting...');
            
            $.ajax({
                url: spt_admin_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'spt_export_rules',
                    include_settings: includeSettings ? '1' : '0',
                    export_format: exportFormat,
                    nonce: spt_admin_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        if (exportFormat === 'file') {
                            // Trigger download
                            if (response.data.download_url) {
                                window.open(response.data.download_url);
                                SPTExport.showSuccess('Export completed successfully!');
                            } else {
                                // Fallback: create blob and download
                                SPTExport.downloadAsFile(response.data.data, response.data.filename);
                                SPTExport.showSuccess('Export file downloaded!');
                            }
                        } else {
                            // Show JSON data in a new window/tab for copy-paste
                            SPTExport.showExportData(response.data.data, response.data.filename);
                            SPTExport.showSuccess('Export data opened in new tab. Copy and save the content.');
                        }
                    } else {
                        SPTExport.showError('Export failed: ' + response.data);
                    }
                },
                error: function() {
                    SPTExport.showError('Export failed. Please try again.');
                },
                complete: function() {
                    $button.prop('disabled', false).text($('#export_format').val() === 'file' ? 'Download Export File' : 'Generate Export Data');
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
         * Show export data in new window
         */
        showExportData: function(data, filename) {
            var newWindow = window.open('', '_blank');
            newWindow.document.write('<html><head><title>Export Data - ' + (filename || 'SPT Export') + '</title></head><body>');
            newWindow.document.write('<h3>Smart Product Tabs Export</h3>');
            newWindow.document.write('<p>Copy the content below and save it as a .json file:</p>');
            newWindow.document.write('<textarea style="width:100%;height:80%;" readonly>' + data + '</textarea>');
            newWindow.document.write('</body></html>');
            newWindow.document.close();
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
        if ($('.spt-templates').length || $('.template-upload-form').length || $('.template-text-form').length) {
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
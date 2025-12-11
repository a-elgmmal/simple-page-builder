/**
 * Simple Page Builder - Admin JavaScript
 */

(function($) {
    'use strict';

    const SPB = {
        // Initialize
        init: function() {
            this.tabSwitching();
            this.bindEvents();
            this.loadInitialData();
        },

        // Tab Switching
        tabSwitching: function() {
            // Set active tab based on current URL
            const urlParams = new URLSearchParams(window.location.search);
            const currentTab = urlParams.get('tab') || 'api-keys';
            
            $('.spb-tab').removeClass('active');
            $('.spb-tab[data-tab="' + currentTab + '"]').addClass('active');
            
            $('.spb-tab-content').removeClass('active');
            $('#' + currentTab).addClass('active');
            
            // Handle tab clicks (navigation will happen via href)
            $('.spb-tab').on('click', function(e) {
                // Let the href handle navigation, but update active states
                const tabId = $(this).data('tab');
                
                // Update active tab
                $('.spb-tab').removeClass('active');
                $(this).addClass('active');
                
                // Update active content
                $('.spb-tab-content').removeClass('active');
                $('#' + tabId).addClass('active');
                
                // Load data for the new tab
                if (tabId === 'api-keys') {
                    SPB.loadApiKeys();
                } else if (tabId === 'activity-log') {
                    SPB.loadActivityLogs();
                } else if (tabId === 'created-pages') {
                    SPB.loadCreatedPages();
                }
            });
        },

        // Bind Events
        bindEvents: function() {
            // Show/Hide Generate Key Form
            $(document).on('click', '#spb-generate-key-btn', function() {
                $('#spb-generate-key-card').slideDown();
                $(this).hide();
            });
            
            $(document).on('click', '#spb-cancel-generate', function() {
                $('#spb-generate-key-card').slideUp();
                $('#spb-generate-key-form')[0].reset();
                $('#spb-generate-key-btn').show();
            });
            
            // Generate API Key
            $(document).on('submit', '#spb-generate-key-form', this.handleGenerateKey);
            
            // Revoke/Restore Key
            $(document).on('click', '.spb-revoke-key', this.handleRevokeKey);
            
            // Save Settings
            $(document).on('submit', '#spb-settings-form', this.handleSaveSettings);
            
            // Regenerate Secret
            $(document).on('click', '#spb-regenerate-secret', this.handleRegenerateSecret);
            
            // Toggle Secret Visibility
            $(document).on('click', '#spb-toggle-secret', this.handleToggleSecret);
            
            // Copy Secret
            $(document).on('click', '#spb-copy-secret', this.handleCopySecret);
            
            // Refresh Pages
            $(document).on('click', '#spb-refresh-pages', function() {
                SPB.loadCreatedPages();
            });
            
            // Copy to Clipboard
            $(document).on('click', '.spb-copy-button', this.handleCopy);
            
            // Copy Code Blocks
            $(document).on('click', '.spb-copy-code-button', this.handleCopyCode);
            
            // Export Logs CSV
            $(document).on('click', '#spb-export-logs', this.handleExportLogs);
            
            // Filter Logs
            $(document).on('change', '.spb-log-filter', this.handleFilterLogs);
            
            // Filter API Keys
            $(document).on('change', '.spb-key-filter', this.handleFilterKeys);
            
            // Pagination
            $(document).on('click', '.spb-pagination button', this.handlePagination);
            
            // Modal Close
            $(document).on('click', '.spb-modal-close, .spb-modal-overlay', function(e) {
                if (e.target === this) {
                    $('.spb-modal-overlay').removeClass('active');
                }
            });
            
            // View Key Details
            $(document).on('click', '.spb-view-details', this.handleViewDetails);
        },

        // Load Initial Data
        loadInitialData: function() {
            // Load data for current active tab
            const urlParams = new URLSearchParams(window.location.search);
            const activeTab = urlParams.get('tab') || 'api-keys';
            
            if (activeTab === 'api-keys') {
                this.loadApiKeys();
            } else if (activeTab === 'activity-log') {
                this.loadActivityLogs();
            } else if (activeTab === 'created-pages') {
                this.loadCreatedPages();
            }
        },

        // AJAX Helper
        ajax: function(action, data, callback) {
            const ajaxData = {
                action: action,
                nonce: spbSettings.nonce,
                ...data
            };

            $.ajax({
                url: spbSettings.ajaxUrl,
                type: 'POST',
                data: ajaxData,
                beforeSend: function() {
                    SPB.showLoading();
                },
                success: function(response) {
                    SPB.hideLoading();
                    if (response.success) {
                        if (callback) callback(response.data);
                    } else {
                        SPB.showAlert('error', response.data || 'An error occurred');
                    }
                },
                error: function(xhr, status, error) {
                    SPB.hideLoading();
                    SPB.showAlert('error', 'Request failed: ' + error);
                }
            });
        },

        // Generate API Key
        handleGenerateKey: function(e) {
            e.preventDefault();
            
            const form = $(this);
            const name = form.find('#spb-key-name').val();
            const expiration = form.find('#spb-key-expiration').val();
            
            if (!name) {
                SPB.showAlert('error', 'Key name is required');
                return;
            }

            SPB.ajax('spb_generate_key', {
                name: name,
                expiration: expiration || null
            }, function(data) {
                SPB.showGeneratedKey(data);
                form[0].reset();
                $('#spb-generate-key-card').slideUp();
                $('#spb-generate-key-btn').show();
                SPB.loadApiKeys();
            });
        },
        
        // Regenerate Secret
        handleRegenerateSecret: function(e) {
            e.preventDefault();
            if (!confirm('Are you sure you want to regenerate the webhook secret? Existing webhook integrations will need to be updated.')) {
                return;
            }
            
            const newSecret = 'whsec_' + Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
            $('#spb-webhook-secret').val(newSecret);
            SPB.showAlert('success', 'Webhook secret regenerated. Don\'t forget to save!');
        },
        
        // Toggle Secret Visibility
        handleToggleSecret: function(e) {
            e.preventDefault();
            const input = $('#spb-webhook-secret');
            const button = $(this);
            
            if (input.attr('type') === 'password') {
                input.attr('type', 'text');
                button.text('👁️‍🗨️');
            } else {
                input.attr('type', 'password');
                button.text('👁️');
            }
        },
        
        // Copy Secret
        handleCopySecret: function(e) {
            e.preventDefault();
            const targetId = $(this).data('copy-target');
            const input = $('#' + targetId);
            const text = input.val();
            
            if (!text) {
                SPB.showAlert('error', 'No secret to copy');
                return;
            }
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function() {
                    SPB.showAlert('success', 'Secret copied to clipboard!');
                });
            } else {
                // Fallback for older browsers
                input.select();
                document.execCommand('copy');
                SPB.showAlert('success', 'Secret copied to clipboard!');
            }
        },

        // Show Generated Key
        showGeneratedKey: function(data) {
            const modal = $('<div class="spb-modal-overlay active">' +
                '<div class="spb-modal">' +
                '<div class="spb-modal-header">' +
                '<h2>API Key Generated</h2>' +
                '<button class="spb-modal-close">&times;</button>' +
                '</div>' +
                '<div class="spb-modal-body">' +
                '<div class="spb-alert spb-alert-warning">' +
                '<strong>Important:</strong> Save this key securely. You won\'t be able to see it again!' +
                '</div>' +
                '<div class="spb-key-display">' +
                '<button class="spb-copy-button spb-button spb-button-small" data-copy="' + data.key + '">Copy</button>' +
                '<div class="spb-key-value">' + data.key + '</div>' +
                '</div>' +
                '<div class="spb-form-group">' +
                '<label>Key Name:</label>' +
                '<div>' + data.name + '</div>' +
                '</div>' +
                '</div>' +
                '</div>' +
                '</div>');
            
            $('body').append(modal);
        },

        // Revoke/Regenerate Key
        handleRevokeKey: function(e) {
            e.preventDefault();
            
            const keyId = $(this).data('key-id');
            const isRegenerate = $(this).hasClass('spb-regenerate-key');
            const action = isRegenerate ? 'regenerate' : 'revoke';
            const actionText = isRegenerate ? 'regenerate' : 'revoke';
            
            if (!confirm('Are you sure you want to ' + actionText + ' this API key?')) {
                return;
            }

            SPB.ajax('spb_revoke_key', {
                id: keyId,
                action_type: action
            }, function(response) {
                // response is already the data object from wp_send_json_success
                if (action === 'regenerate' && response && response.key) {
                    // Show the new key in a modal (same as generate)
                    SPB.showGeneratedKey({
                        key: response.key,
                        prefix: response.prefix,
                        name: response.name || 'Regenerated Key'
                    });
                } else {
                    const message = (response && response.message) ? response.message : 'API key ' + actionText + 'd successfully';
                    SPB.showAlert('success', message);
                }
                SPB.loadApiKeys();
            });
        },
        

        // Save Settings
        handleSaveSettings: function(e) {
            e.preventDefault();
            
            const form = $(this);
            const settings = {
                webhookUrl: form.find('#spb-webhook-url').val(),
                webhookSecret: form.find('#spb-webhook-secret').val(),
                rateLimit: form.find('#spb-rate-limit').val(),
                isApiEnabled: form.find('#spb-api-enabled').is(':checked'),
                expirationDefault: form.find('#spb-expiration-default').val(),
                isJwtEnabled: form.find('#spb-jwt-enabled').is(':checked'),
                jwtExpiration: form.find('#spb-jwt-expiration').val()
            };

            SPB.ajax('spb_save_settings', {
                settings: settings
            }, function() {
                SPB.showAlert('success', 'Settings saved successfully');
            });
        },

        // Copy to Clipboard
        handleCopy: function(e) {
            e.preventDefault();
            const text = $(this).data('copy');
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function() {
                    SPB.showAlert('success', 'Copied to clipboard!');
                });
            } else {
                // Fallback for older browsers
                const textarea = $('<textarea>').val(text).appendTo('body').select();
                document.execCommand('copy');
                textarea.remove();
                SPB.showAlert('success', 'Copied to clipboard!');
            }
        },
        
        // Copy Code Block
        handleCopyCode: function(e) {
            e.preventDefault();
            const targetId = $(this).data('copy-target');
            const codeElement = $('#' + targetId);
            const text = codeElement.text() || codeElement.find('code').text();
            
            if (!text) {
                SPB.showAlert('error', 'No code to copy');
                return;
            }
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function() {
                    SPB.showAlert('success', 'Copied to clipboard!');
                });
            } else {
                // Fallback for older browsers
                const textarea = $('<textarea>').val(text).appendTo('body').select();
                document.execCommand('copy');
                textarea.remove();
                SPB.showAlert('success', 'Copied to clipboard!');
            }
        },

        // Export Logs CSV
        handleExportLogs: function(e) {
            e.preventDefault();
            
            const filters = {
                status: $('#spb-filter-status').val(),
                dateFrom: $('#spb-filter-date-from').val(),
                dateTo: $('#spb-filter-date-to').val(),
                apiKey: $('#spb-filter-api-key').val()
            };

            SPB.ajax('spb_export_logs_csv', filters, function(response) {
                // response is already the data object from wp_send_json_success
                const csvData = (response && response.csv) ? response.csv : (response && response.data && response.data.csv) ? response.data.csv : null;
                
                if (csvData) {
                    // Create download link
                    const blob = new Blob([csvData], { type: 'text/csv;charset=utf-8;' });
                    const url = window.URL.createObjectURL(blob);
                    const a = $('<a>').attr({
                        href: url,
                        download: 'spb-logs-' + new Date().toISOString().split('T')[0] + '.csv'
                    }).appendTo('body');
                    a[0].click();
                    a.remove();
                    window.URL.revokeObjectURL(url);
                    SPB.showAlert('success', 'CSV exported successfully');
                } else {
                    SPB.showAlert('error', 'Failed to export CSV: ' + (response && response.message ? response.message : 'Unknown error'));
                }
            });
        },

        // Filter Logs
        handleFilterLogs: function() {
            SPB.loadActivityLogs();
        },

        // View Key Details
        handleViewDetails: function(e) {
            e.preventDefault();
            const keyId = $(this).data('key-id');
            
            SPB.ajax('spb_get_key_details', {
                id: keyId
            }, function(data) {
                SPB.showKeyDetails(data);
            });
        },

        // Show Key Details
        showKeyDetails: function(data) {
            const modal = $('<div class="spb-modal-overlay active">' +
                '<div class="spb-modal">' +
                '<div class="spb-modal-header">' +
                '<h2>API Key Details</h2>' +
                '<button class="spb-modal-close">&times;</button>' +
                '</div>' +
                '<div class="spb-modal-body">' +
                '<div class="spb-form-group">' +
                '<label>Key Name:</label>' +
                '<div>' + SPB.escapeHtml(data.name) + '</div>' +
                '</div>' +
                '<div class="spb-form-group">' +
                '<label>Key Preview:</label>' +
                '<div><code style="background: #f6f7f7; padding: 4px 8px; border-radius: 4px; font-size: 12px; color: #1d2327;">' + SPB.escapeHtml(data.prefix) + '***</code></div>' +
                '</div>' +
                '<div class="spb-form-group">' +
                '<label>Status:</label>' +
                '<div><span class="spb-badge spb-badge-' + (data.status === 'ACTIVE' ? 'success' : 'danger') + '">' + data.status + '</span></div>' +
                '</div>' +
                '<div class="spb-form-group">' +
                '<label>Created:</label>' +
                '<div>' + SPB.formatDate(data.created) + '</div>' +
                '</div>' +
                '<div class="spb-form-group">' +
                '<label>Expires:</label>' +
                '<div>' + (data.expires_at ? SPB.formatDate(data.expires_at) : '<span style="color: #646970;">Never</span>') + '</div>' +
                '</div>' +
                '<div class="spb-form-group">' +
                '<label>Last Used:</label>' +
                '<div>' + (data.last_used ? SPB.formatDate(data.last_used) : '<span style="color: #646970;">Never</span>') + '</div>' +
                '</div>' +
                '<div class="spb-form-group">' +
                '<label>Request Count:</label>' +
                '<div>' + data.request_count + '</div>' +
                '</div>' +
                '</div>' +
                '</div>' +
                '</div>');
            
            $('body').append(modal);
        },

        // Load API Keys
        loadApiKeys: function(page) {
            page = page || 1;
            const status = $('#spb-filter-key-status').val() || '';
            
            SPB.ajax('spb_get_keys', {
                page: page,
                status: status
            }, function(response) {
                SPB.renderApiKeys(response.keys || response.data || [], response.pagination);
            });
        },

        // Render API Keys
        renderApiKeys: function(keys, pagination) {
            const tbody = $('#spb-keys-table tbody');
            tbody.empty();
            
            if (keys.length === 0) {
                tbody.append('<tr><td colspan="8" class="spb-empty-state"><p>No API keys found. Generate your first key to get started.</p></td></tr>');
                $('#spb-generate-key-btn').show();
                $('#spb-keys-pagination').empty();
                return;
            }
            
            $('#spb-generate-key-btn').show();
            
            keys.forEach(function(key) {
                const row = $('<tr>');
                row.append('<td><strong>' + SPB.escapeHtml(key.name) + '</strong></td>');
                row.append('<td><code style="background: #f6f7f7; padding: 4px 8px; border-radius: 4px; font-size: 12px; color: #1d2327;">' + SPB.escapeHtml(key.prefix) + '***</code></td>');
                row.append('<td>' + SPB.formatDate(key.created) + '</td>');
                row.append('<td>' + (key.expires_at ? SPB.formatDate(key.expires_at) : '<span style="color: #646970;">Never</span>') + '</td>');
                row.append('<td>' + (key.last_used ? SPB.formatDate(key.last_used) : '<span style="color: #646970;">Never</span>') + '</td>');
                row.append('<td>' + (key.request_count || 0) + '</td>');
                row.append('<td><span class="spb-badge spb-badge-' + (key.status === 'ACTIVE' ? 'success' : key.status === 'EXPIRED' ? 'warning' : 'danger') + '">' + key.status + '</span></td>');
                row.append('<td style="white-space: nowrap;">' +
                    '<button class="spb-view-details spb-button spb-button-small" data-key-id="' + key.id + '" style="margin-right: 8px;">Details</button>' +
                    (key.status === 'ACTIVE' ? 
                     '<button class="spb-revoke-key spb-button spb-button-warning spb-button-small" data-key-id="' + key.id + '">Revoke</button>' : 
                     (key.status === 'REVOKED' ? 
                      '<button class="spb-revoke-key spb-regenerate-key spb-button spb-button-secondary spb-button-small" data-key-id="' + key.id + '">Regenerate</button>' : 
                      '')) +
                    '</td>');
                tbody.append(row);
            });
            
            if (pagination) {
                SPB.renderPagination('spb-keys-pagination', pagination, 'loadApiKeys');
            }
        },
        
        // Filter API Keys
        handleFilterKeys: function() {
            SPB.loadApiKeys(1);
        },
        
        // Handle Pagination
        handlePagination: function(e) {
            e.preventDefault();
            const button = $(this);
            const action = button.data('action');
            const target = button.data('target');
            const page = parseInt(button.data('page')) || 1;
            
            if (action === 'page' && target) {
                if (target === 'keys') {
                    SPB.loadApiKeys(page);
                } else if (target === 'logs') {
                    SPB.loadActivityLogs(page);
                } else if (target === 'pages') {
                    SPB.loadCreatedPages(page);
                }
            }
        },
        
        // Render Pagination
        renderPagination: function(containerId, pagination, loadFunction) {
            const container = $('#' + containerId);
            container.empty();
            
            if (!pagination || pagination.total_pages <= 1) {
                return;
            }
            
            const currentPage = pagination.page;
            const totalPages = pagination.total_pages;
            const target = containerId.replace('spb-', '').replace('-pagination', '');
            
            let html = '<div class="spb-pagination-info">';
            html += 'Showing ' + ((currentPage - 1) * pagination.per_page + 1) + ' to ' + 
                    Math.min(currentPage * pagination.per_page, pagination.total) + 
                    ' of ' + pagination.total + ' entries';
            html += '</div>';
            html += '<div class="spb-pagination-buttons">';
            
            // Previous button
            if (currentPage > 1) {
                html += '<button class="spb-button spb-button-small" data-action="page" data-target="' + target + '" data-page="' + (currentPage - 1) + '">Previous</button>';
            } else {
                html += '<button class="spb-button spb-button-small" disabled>Previous</button>';
            }
            
            // Page numbers
            let startPage = Math.max(1, currentPage - 2);
            let endPage = Math.min(totalPages, currentPage + 2);
            
            if (startPage > 1) {
                html += '<button class="spb-button spb-button-small" data-action="page" data-target="' + target + '" data-page="1">1</button>';
                if (startPage > 2) {
                    html += '<span class="spb-pagination-ellipsis">...</span>';
                }
            }
            
            for (let i = startPage; i <= endPage; i++) {
                if (i === currentPage) {
                    html += '<button class="spb-button spb-button-small spb-button-primary" disabled>' + i + '</button>';
                } else {
                    html += '<button class="spb-button spb-button-small" data-action="page" data-target="' + target + '" data-page="' + i + '">' + i + '</button>';
                }
            }
            
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    html += '<span class="spb-pagination-ellipsis">...</span>';
                }
                html += '<button class="spb-button spb-button-small" data-action="page" data-target="' + target + '" data-page="' + totalPages + '">' + totalPages + '</button>';
            }
            
            // Next button
            if (currentPage < totalPages) {
                html += '<button class="spb-button spb-button-small" data-action="page" data-target="' + target + '" data-page="' + (currentPage + 1) + '">Next</button>';
            } else {
                html += '<button class="spb-button spb-button-small" disabled>Next</button>';
            }
            
            html += '</div>';
            container.html(html);
        },

        // Load Activity Logs
        loadActivityLogs: function(page) {
            page = page || 1;
            const filters = {
                page: page,
                status: $('#spb-filter-status').val() || '',
                dateFrom: $('#spb-filter-date-from').val() || '',
                dateTo: $('#spb-filter-date-to').val() || '',
                apiKey: $('#spb-filter-api-key').val() || ''
            };

            SPB.ajax('spb_get_logs', filters, function(response) {
                SPB.renderActivityLogs(response.logs || response.data || [], response.pagination);
            });
        },

        // Render Activity Logs
        renderActivityLogs: function(logs, pagination) {
            const tbody = $('#spb-logs-table tbody');
            tbody.empty();
            
            if (logs.length === 0) {
                tbody.append('<tr><td colspan="8" class="spb-empty-state">No logs found</td></tr>');
                $('#spb-logs-pagination').empty();
                return;
            }
            
            logs.forEach(function(log) {
                const row = $('<tr>');
                row.append('<td>' + log.timestamp + '</td>');
                row.append('<td><code>' + (log.apiKeyName || 'Unknown') + '</code></td>');
                row.append('<td>' + log.endpoint + '</td>');
                row.append('<td><span class="spb-badge spb-badge-' + (log.status === 'SUCCESS' ? 'success' : 'danger') + '">' + log.status + '</span></td>');
                row.append('<td>' + log.pagesCreated + '</td>');
                row.append('<td>' + Math.round(log.responseTime) + 'ms</td>');
                row.append('<td>' + log.ipAddress + '</td>');
                row.append('<td><span class="spb-badge spb-badge-' + (log.webhookStatus === 'SENT' ? 'success' : log.webhookStatus === 'FAILED' ? 'danger' : 'neutral') + '">' + log.webhookStatus + '</span></td>');
                tbody.append(row);
            });
            
            if (pagination) {
                SPB.renderPagination('spb-logs-pagination', pagination, 'loadActivityLogs');
            }
        },

        // Load Created Pages
        loadCreatedPages: function(page) {
            page = page || 1;
            
            SPB.ajax('spb_get_created_pages', {
                page: page
            }, function(response) {
                SPB.renderCreatedPages(response.pages || response.data || [], response.pagination);
            });
        },

        // Render Created Pages
        renderCreatedPages: function(pages, pagination) {
            const tbody = $('#spb-pages-table tbody');
            tbody.empty();
            
            if (pages.length === 0) {
                tbody.append('<tr><td colspan="3" class="spb-empty-state"><p>No pages created via API yet.</p></td></tr>');
                $('#spb-pages-pagination').empty();
                return;
            }
            
            pages.forEach(function(page) {
                const row = $('<tr>');
                row.append('<td><a href="' + SPB.escapeHtml(page.page_url) + '" target="_blank" style="color: #2271b1; text-decoration: none; font-weight: 500;">' + SPB.escapeHtml(page.page_title) + '</a></td>');
                row.append('<td>' + SPB.formatDate(page.created_at) + '</td>');
                row.append('<td>' + SPB.escapeHtml(page.api_key_name) + '</td>');
                tbody.append(row);
            });
            
            if (pagination) {
                SPB.renderPagination('spb-pages-pagination', pagination, 'loadCreatedPages');
            }
        },

        // Show Alert
        showAlert: function(type, message) {
            const alert = $('<div class="spb-alert spb-alert-' + type + '">' + message + '</div>');
            $('.spb-tab-content.active').prepend(alert);
            
            setTimeout(function() {
                alert.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },

        // Show Loading
        showLoading: function() {
            if ($('.spb-loading').length === 0) {
                $('.spb-tab-content.active').append('<div class="spb-loading"><span class="spb-spinner"></span> Loading...</div>');
            }
        },

        // Hide Loading
        hideLoading: function() {
            $('.spb-loading').remove();
        },
        
        // Utility Functions
        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text ? text.replace(/[&<>"']/g, function(m) { return map[m]; }) : '';
        },
        
        formatDate: function(dateString) {
            if (!dateString) return 'Never';
            const date = new Date(dateString);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        SPB.init();
    });

})(jQuery);


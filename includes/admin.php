<?php
class SimplePageBuilder_Admin {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        $plugin_file = SPB_PATH . 'simple-page-builder.php';
        add_filter('plugin_action_links_' . plugin_basename($plugin_file), [$this, 'add_settings_link']);
        
        // AJAX Endpoints
        add_action('wp_ajax_spb_get_keys', [$this, 'handle_get_keys']);
        add_action('wp_ajax_spb_generate_key', [$this, 'handle_generate_key']);
        add_action('wp_ajax_spb_revoke_key', [$this, 'handle_revoke_key']);
        add_action('wp_ajax_spb_get_key_details', [$this, 'handle_get_key_details']);
        add_action('wp_ajax_spb_get_logs', [$this, 'handle_get_logs']);
        add_action('wp_ajax_spb_export_logs_csv', [$this, 'handle_export_logs_csv']);
        add_action('wp_ajax_spb_get_created_pages', [$this, 'handle_get_created_pages']);
        add_action('wp_ajax_spb_save_settings', [$this, 'handle_save_settings']);
    }

    public function add_admin_menu() {
        // Add under Tools menu as required by Task.md
        add_management_page(
            'Simple Page Builder',
            'Page Builder',
            'manage_options',
            'simple-page-builder',
            [$this, 'render_admin_page']
        );
    }

    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('tools.php?page=simple-page-builder') . '">' . __('Settings') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function enqueue_assets($hook) {
        // Check if we're on our plugin page
        if ($hook !== 'tools_page_simple-page-builder') return;

        // Enqueue CSS
        wp_enqueue_style('spb-admin-css', SPB_URL . 'admin/css/admin.css', [], SPB_VERSION);
        
        // Enqueue JavaScript (jQuery is already loaded in WordPress admin)
        wp_enqueue_script('spb-admin-js', SPB_URL . 'admin/js/admin.js', ['jquery'], SPB_VERSION, true);
        
        // Localize script with WordPress settings
        wp_localize_script('spb-admin-js', 'spbSettings', [
            'nonce' => wp_create_nonce('spb_admin_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php')
        ]);
    }

    public function render_admin_page() {
        // Get current tab from URL
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'api-keys';
        $valid_tabs = ['api-keys', 'activity-log', 'created-pages', 'settings', 'documentation'];
        if (!in_array($current_tab, $valid_tabs)) {
            $current_tab = 'api-keys';
        }
        ?>
        <div class="wrap spb-admin-wrap">
            <div class="spb-admin-header">
                <h1><?php echo esc_html__('Simple Page Builder', 'simple-page-builder'); ?></h1>
            </div>

            <nav class="spb-tabs">
                <a href="<?php echo esc_url(admin_url('tools.php?page=simple-page-builder&tab=api-keys')); ?>" 
                   class="spb-tab <?php echo $current_tab === 'api-keys' ? 'active' : ''; ?>" 
                   data-tab="api-keys">
                    API Keys
                </a>
                <a href="<?php echo esc_url(admin_url('tools.php?page=simple-page-builder&tab=activity-log')); ?>" 
                   class="spb-tab <?php echo $current_tab === 'activity-log' ? 'active' : ''; ?>" 
                   data-tab="activity-log">
                    Activity Log
                </a>
                <a href="<?php echo esc_url(admin_url('tools.php?page=simple-page-builder&tab=created-pages')); ?>" 
                   class="spb-tab <?php echo $current_tab === 'created-pages' ? 'active' : ''; ?>" 
                   data-tab="created-pages">
                    Created Pages
                </a>
                <a href="<?php echo esc_url(admin_url('tools.php?page=simple-page-builder&tab=settings')); ?>" 
                   class="spb-tab <?php echo $current_tab === 'settings' ? 'active' : ''; ?>" 
                   data-tab="settings">
                    Settings
                </a>
                <a href="<?php echo esc_url(admin_url('tools.php?page=simple-page-builder&tab=documentation')); ?>" 
                   class="spb-tab <?php echo $current_tab === 'documentation' ? 'active' : ''; ?>" 
                   data-tab="documentation">
                    API Documentation
                </a>
            </nav>

            <?php
            // Render appropriate tab content
            switch ($current_tab) {
                case 'api-keys':
                    $this->render_api_keys_tab();
                    break;
                case 'activity-log':
                    $this->render_activity_log_tab();
                    break;
                case 'created-pages':
                    $this->render_created_pages_tab();
                    break;
                case 'settings':
                    $this->render_settings_tab();
                    break;
                case 'documentation':
                    $this->render_documentation_tab();
                    break;
            }
            ?>
        </div>
        <?php
    }

    // --- TAB RENDERS ---

    private function render_api_keys_tab() {
        ?>
        <div id="api-keys" class="spb-tab-content active">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <h2>API Keys</h2>
                <button type="button" id="spb-generate-key-btn" class="spb-button" style="display: none;">+ Generate New Key</button>
            </div>
            
            <div class="spb-card" id="spb-generate-key-card" style="display: none;">
                <div class="spb-card-header">
                    <h3 class="spb-card-title">Generate New API Key</h3>
                </div>
                <form id="spb-generate-key-form">
                    <div class="spb-form-group">
                        <label for="spb-key-name">Key Name <span style="color: #d63638;">*</span></label>
                        <input type="text" id="spb-key-name" name="name" required placeholder="e.g., Production Server, Mobile App">
                        <p class="description">A friendly name to identify this API key</p>
                    </div>
                    <div class="spb-form-group">
                        <label for="spb-key-expiration">Expiration Date (Optional)</label>
                        <input type="date" id="spb-key-expiration" name="expiration">
                        <p class="description">Leave empty for no expiration</p>
                    </div>
                    <div style="display: flex; gap: 12px;">
                        <button type="submit" class="spb-button">Generate API Key</button>
                        <button type="button" id="spb-cancel-generate" class="spb-button spb-button-secondary">Cancel</button>
                    </div>
                </form>
            </div>

            <div class="spb-card">
                <div class="spb-card-header">
                    <h3 class="spb-card-title">Existing API Keys</h3>
                </div>
                <table id="spb-keys-table" class="spb-table">
                    <thead>
                        <tr>
                            <th>Key Name</th>
                            <th>Token Preview</th>
                            <th>Created</th>
                            <th>Last Used</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="6" class="spb-loading">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    private function render_activity_log_tab() {
        ?>
        <div id="activity-log" class="spb-tab-content active">
            <h2>Activity Logs</h2>
            
            <div class="spb-card">
                <div class="spb-filters">
                    <div class="spb-filter-group">
                        <label for="spb-filter-status">Status</label>
                        <select id="spb-filter-status" class="spb-log-filter">
                            <option value="">All</option>
                            <option value="SUCCESS">Success</option>
                            <option value="FAILED">Failed</option>
                        </select>
                    </div>
                    <div class="spb-filter-group">
                        <label for="spb-filter-date-from">Date From</label>
                        <input type="date" id="spb-filter-date-from" class="spb-log-filter">
                    </div>
                    <div class="spb-filter-group">
                        <label for="spb-filter-date-to">Date To</label>
                        <input type="date" id="spb-filter-date-to" class="spb-log-filter">
                    </div>
                    <div class="spb-filter-group">
                        <label for="spb-filter-api-key">API Key</label>
                        <select id="spb-filter-api-key" class="spb-log-filter">
                            <option value="">All Keys</option>
                            <?php
                            global $wpdb;
                            $keys = $wpdb->get_results("SELECT DISTINCT api_key_name FROM {$wpdb->prefix}spb_api_logs WHERE api_key_name IS NOT NULL ORDER BY api_key_name");
                            foreach ($keys as $key) {
                                echo '<option value="' . esc_attr($key->api_key_name) . '">' . esc_html($key->api_key_name) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="spb-filter-group" style="flex: 0 0 auto;">
                        <label>&nbsp;</label>
                        <button type="button" id="spb-export-logs" class="spb-button">Export CSV</button>
                    </div>
                </div>
            </div>

            <div class="spb-card">
                <table id="spb-logs-table" class="spb-table">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>API Key</th>
                            <th>Endpoint</th>
                            <th>Status</th>
                            <th>Pages Created</th>
                            <th>Response Time</th>
                            <th>IP Address</th>
                            <th>Webhook Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="8" class="spb-loading">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    private function render_created_pages_tab() {
        ?>
        <div id="created-pages" class="spb-tab-content active">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <h2>Created Pages</h2>
                <button type="button" id="spb-refresh-pages" class="spb-button spb-button-secondary">Refresh</button>
            </div>
            
            <div class="spb-card">
                <table id="spb-pages-table" class="spb-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Created Date</th>
                            <th>Created By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="3" class="spb-loading">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    private function render_settings_tab() {
        $webhook_url = get_option('spb_webhook_url', '');
        $webhook_secret = get_option('spb_webhook_secret', '');
        $rate_limit = get_option('spb_rate_limit', 100);
        $api_enabled = get_option('spb_api_enabled', 'yes') === 'yes';
        $expiration_default = get_option('spb_key_expiration_default', 'never');
        ?>
        <div id="settings" class="spb-tab-content active">
            <h2>Settings</h2>
            
            <form id="spb-settings-form">
                <div class="spb-card">
                    <div class="spb-card-header">
                        <h3 class="spb-card-title">Webhook Configuration</h3>
                    </div>
                    <div class="spb-form-group">
                        <label for="spb-webhook-url">Webhook URL</label>
                        <input type="url" id="spb-webhook-url" name="webhookUrl" value="<?php echo esc_attr($webhook_url); ?>" placeholder="https://example.com/webhook">
                        <p class="description">We'll send a POST request here when pages are created.</p>
                    </div>

                    <div class="spb-form-group">
                        <label for="spb-webhook-secret">Secret Key</label>
                        <div style="display: flex; gap: 12px; align-items: center;">
                            <input type="password" id="spb-webhook-secret" name="webhookSecret" value="<?php echo esc_attr($webhook_secret); ?>" placeholder="Your webhook secret" style="flex: 1;">
                            <button type="button" id="spb-regenerate-secret" class="spb-button spb-button-secondary">Regenerate</button>
                        </div>
                        <p class="description">Secret key for HMAC-SHA256 signature verification</p>
                    </div>
                </div>

                <div class="spb-card">
                    <div class="spb-card-header">
                        <h3 class="spb-card-title">Security & Limits</h3>
                    </div>
                    <div class="spb-form-group">
                        <label for="spb-rate-limit">Rate Limiting</label>
                        <input type="number" id="spb-rate-limit" name="rateLimit" value="<?php echo esc_attr($rate_limit); ?>" min="1" step="1">
                        <p class="description">Max requests per hour per key</p>
                    </div>

                    <div class="spb-form-group">
                        <label style="display: flex; align-items: center; cursor: pointer;">
                            <span class="spb-toggle-switch">
                                <input type="checkbox" id="spb-api-enabled" name="isApiEnabled" <?php checked($api_enabled); ?>>
                                <span class="spb-toggle-slider"></span>
                            </span>
                            <span>Enable API Access</span>
                        </label>
                        <p class="description" style="margin-left: 56px;">Globally enable/disable external access</p>
                    </div>

                    <div class="spb-form-group">
                        <label for="spb-expiration-default">API Key Expiration Default</label>
                        <select id="spb-expiration-default" name="expirationDefault">
                            <option value="30" <?php selected($expiration_default, '30'); ?>>30 days</option>
                            <option value="60" <?php selected($expiration_default, '60'); ?>>60 days</option>
                            <option value="90" <?php selected($expiration_default, '90'); ?>>90 days</option>
                            <option value="never" <?php selected($expiration_default, 'never'); ?>>Never</option>
                        </select>
                        <p class="description">Default expiration period for new API keys</p>
                    </div>
                </div>

                <div style="text-align: right; margin-top: 24px;">
                    <button type="submit" class="spb-button">Save Changes</button>
                </div>
            </form>
        </div>
        <?php
    }

    private function render_documentation_tab() {
        $site_url = get_site_url();
        $api_endpoint = $site_url . '/wp-json/pagebuilder/v1/create-pages';
        ?>
        <div id="documentation" class="spb-tab-content active">
            <h2>API Documentation</h2>
            
            <div class="spb-form-group">
                <h3>Endpoint</h3>
                <p><code><?php echo esc_html($api_endpoint); ?></code></p>
            </div>

            <div class="spb-form-group">
                <h3>Authentication</h3>
                <p>All requests must include an API key in the Authorization header:</p>
                <pre style="background: #f6f7f7; padding: 15px; border-radius: 4px; overflow-x: auto;"><code>Authorization: Bearer YOUR_API_KEY_HERE</code></pre>
            </div>

            <div class="spb-form-group">
                <h3>cURL Example</h3>
                <pre style="background: #f6f7f7; padding: 15px; border-radius: 4px; overflow-x: auto;"><code>curl -X POST <?php echo esc_html($api_endpoint); ?> \
  -H "Authorization: Bearer YOUR_API_KEY_HERE" \
  -H "Content-Type: application/json" \
  -d '{
    "pages": [
      {
        "title": "About Us",
        "content": "&lt;h1&gt;Welcome&lt;/h1&gt;&lt;p&gt;This is the about page.&lt;/p&gt;",
        "status": "publish"
      },
      {
        "title": "Contact",
        "content": "&lt;p&gt;Contact us here.&lt;/p&gt;",
        "status": "publish"
      }
    ]
  }'</code></pre>
            </div>

            <div class="spb-form-group">
                <h3>Request Body</h3>
                <pre style="background: #f6f7f7; padding: 15px; border-radius: 4px; overflow-x: auto;"><code>{
  "pages": [
    {
      "title": "Page Title (required)",
      "content": "Page content (HTML, optional)",
      "status": "publish|draft|pending (optional, default: publish)"
    }
  ]
}</code></pre>
            </div>

            <div class="spb-form-group">
                <h3>Response Example</h3>
                <pre style="background: #f6f7f7; padding: 15px; border-radius: 4px; overflow-x: auto;"><code>{
  "success": true,
  "pages": [
    {
      "id": 123,
      "title": "About Us",
      "url": "<?php echo esc_html($site_url); ?>/about-us"
    }
  ],
  "errors": []
}</code></pre>
            </div>

            <div class="spb-form-group">
                <h3>Webhook Notifications</h3>
                <p>When pages are created, a POST request is sent to your configured webhook URL with the following payload:</p>
                <pre style="background: #f6f7f7; padding: 15px; border-radius: 4px; overflow-x: auto;"><code>{
  "event": "pages_created",
  "timestamp": "2025-10-07T14:30:00Z",
  "request_id": "req_abc123xyz",
  "api_key_name": "Production Server",
  "total_pages": 2,
  "pages": [
    {
      "id": 123,
      "title": "About Us",
      "url": "<?php echo esc_html($site_url); ?>/about-us"
    }
  ]
}</code></pre>
                <p><strong>Webhook Signature Verification:</strong></p>
                <p>The webhook includes an <code>X-Webhook-Signature</code> header with an HMAC-SHA256 signature. Verify it using your webhook secret:</p>
                <pre style="background: #f6f7f7; padding: 15px; border-radius: 4px; overflow-x: auto;"><code>// PHP Example
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'];
$secret = 'your_webhook_secret';

$computed = hash_hmac('sha256', $payload, $secret);

if (hash_equals($signature, $computed)) {
    // Verified!
    $data = json_decode($payload, true);
}</code></pre>
            </div>

            <div class="spb-form-group">
                <h3>Rate Limiting</h3>
                <p>Each API key has a rate limit (default: 100 requests per hour). If exceeded, you'll receive a <code>429 Too Many Requests</code> response.</p>
            </div>

            <div class="spb-form-group">
                <h3>Error Codes</h3>
                <ul>
                    <li><strong>401</strong> - Missing or invalid Authorization header</li>
                    <li><strong>403</strong> - Invalid API key, revoked key, expired key, or insufficient permissions</li>
                    <li><strong>400</strong> - Invalid request parameters</li>
                    <li><strong>429</strong> - Rate limit exceeded</li>
                    <li><strong>503</strong> - API is disabled</li>
                </ul>
            </div>
        </div>
        <?php
    }

    // --- AJAX HANDLERS ---

    public function handle_get_keys() {
        check_ajax_referer('spb_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized', 403);

        global $wpdb;
        $keys = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, prefix, status, permissions, created_at as created, expires_at, last_used, request_count 
             FROM {$wpdb->prefix}spb_api_keys 
             ORDER BY created_at DESC"
        ));

        wp_send_json_success($keys);
    }

    public function handle_generate_key() {
        check_ajax_referer('spb_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized', 403);

        $name = sanitize_text_field($_POST['name']);
        $expiration = isset($_POST['expiration']) && !empty($_POST['expiration']) ? sanitize_text_field($_POST['expiration']) : null;
        
        if (empty($name)) {
            wp_send_json_error('Key name is required', 400);
        }
        
        // Generate Key
        $key = bin2hex(random_bytes(32)); // 64 chars
        $prefix = substr($key, 0, 8);
        $hash = wp_hash_password($key);

        // Calculate expiration date if provided
        $expires_at = null;
        if ($expiration) {
            $expires_at = date('Y-m-d H:i:s', strtotime($expiration));
        }

        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'spb_api_keys', [
            'name' => $name,
            'api_key_hash' => $hash,
            'prefix' => $prefix,
            'status' => 'ACTIVE',
            'permissions' => 'read_write',
            'created_at' => current_time('mysql'),
            'expires_at' => $expires_at,
            'request_count' => 0
        ]);

        wp_send_json_success([
            'id' => $wpdb->insert_id,
            'key' => $key, // Shown once
            'prefix' => $prefix,
            'name' => $name,
            'created' => current_time('mysql'),
            'status' => 'ACTIVE',
            'expires_at' => $expires_at
        ]);
    }

    public function handle_revoke_key() {
        check_ajax_referer('spb_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized', 403);

        $id = intval($_POST['id']);
        global $wpdb;
        $wpdb->update($wpdb->prefix . 'spb_api_keys', ['status' => 'REVOKED'], ['id' => $id]);
        
        wp_send_json_success();
    }

    public function handle_get_key_details() {
        check_ajax_referer('spb_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized', 403);

        $id = intval($_POST['id']);
        global $wpdb;
        $key = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name, prefix, status, created_at as created, expires_at, last_used, request_count 
             FROM {$wpdb->prefix}spb_api_keys 
             WHERE id = %d",
            $id
        ));

        if (!$key) {
            wp_send_json_error('Key not found', 404);
        }

        wp_send_json_success($key);
    }

    public function handle_get_logs() {
        check_ajax_referer('spb_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized', 403);

        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $date_from = isset($_POST['dateFrom']) ? sanitize_text_field($_POST['dateFrom']) : '';
        $date_to = isset($_POST['dateTo']) ? sanitize_text_field($_POST['dateTo']) : '';
        $api_key = isset($_POST['apiKey']) ? sanitize_text_field($_POST['apiKey']) : '';

        global $wpdb;
        $where = ['1=1'];
        $params = [];

        if ($status) {
            $where[] = 'status = %s';
            $params[] = $status;
        }

        if ($date_from) {
            $where[] = 'created_at >= %s';
            $params[] = $date_from . ' 00:00:00';
        }

        if ($date_to) {
            $where[] = 'created_at <= %s';
            $params[] = $date_to . ' 23:59:59';
        }

        if ($api_key) {
            $where[] = 'api_key_name = %s';
            $params[] = $api_key;
        }

        $where_clause = implode(' AND ', $where);
        $query = "SELECT id, api_key_name as apiKeyName, endpoint, status, status_code as statusCode, 
                         pages_created as pagesCreated, response_time as responseTime, 
                         ip_address as ipAddress, webhook_status as webhookStatus, 
                         error_details as errorDetails, created_at as timestamp 
                  FROM {$wpdb->prefix}spb_api_logs 
                  WHERE $where_clause 
                  ORDER BY created_at DESC 
                  LIMIT 100";

        if (!empty($params)) {
            $logs = $wpdb->get_results($wpdb->prepare($query, $params));
        } else {
            $logs = $wpdb->get_results($query);
        }

        wp_send_json_success($logs);
    }

    public function handle_export_logs_csv() {
        check_ajax_referer('spb_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die('Unauthorized', 403);

        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $date_from = isset($_POST['dateFrom']) ? sanitize_text_field($_POST['dateFrom']) : '';
        $date_to = isset($_POST['dateTo']) ? sanitize_text_field($_POST['dateTo']) : '';
        $api_key = isset($_POST['apiKey']) ? sanitize_text_field($_POST['apiKey']) : '';

        global $wpdb;
        $where = ['1=1'];
        $params = [];

        if ($status) {
            $where[] = 'status = %s';
            $params[] = $status;
        }

        if ($date_from) {
            $where[] = 'created_at >= %s';
            $params[] = $date_from . ' 00:00:00';
        }

        if ($date_to) {
            $where[] = 'created_at <= %s';
            $params[] = $date_to . ' 23:59:59';
        }

        if ($api_key) {
            $where[] = 'api_key_name = %s';
            $params[] = $api_key;
        }

        $where_clause = implode(' AND ', $where);
        $query = "SELECT * FROM {$wpdb->prefix}spb_api_logs WHERE $where_clause ORDER BY created_at DESC";

        if (!empty($params)) {
            $logs = $wpdb->get_results($wpdb->prepare($query, $params));
        } else {
            $logs = $wpdb->get_results($query);
        }

        // Generate CSV
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="spb-logs-' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');
        
        // Headers
        fputcsv($output, ['ID', 'API Key Name', 'Endpoint', 'Status', 'Status Code', 'Pages Created', 
                          'IP Address', 'Response Time (ms)', 'Webhook Status', 'Error Details', 'Timestamp']);

        // Data
        foreach ($logs as $log) {
            fputcsv($output, [
                $log->id,
                $log->api_key_name,
                $log->endpoint,
                $log->status,
                $log->status_code,
                $log->pages_created,
                $log->ip_address,
                round($log->response_time, 2),
                $log->webhook_status,
                $log->error_details,
                $log->created_at
            ]);
        }

        fclose($output);
        exit;
    }

    public function handle_get_created_pages() {
        check_ajax_referer('spb_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized', 403);

        global $wpdb;
        $pages = $wpdb->get_results(
            "SELECT page_id, page_title, page_url, api_key_name, created_at 
             FROM {$wpdb->prefix}spb_created_pages 
             ORDER BY created_at DESC 
             LIMIT 100"
        );

        wp_send_json_success($pages);
    }

    public function handle_save_settings() {
        check_ajax_referer('spb_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized', 403);

        $settings = $_POST['settings'];
        update_option('spb_webhook_url', esc_url_raw($settings['webhookUrl']));
        update_option('spb_webhook_secret', sanitize_text_field($settings['webhookSecret']));
        update_option('spb_rate_limit', intval($settings['rateLimit']));
        update_option('spb_api_enabled', $settings['isApiEnabled'] === 'true' || $settings['isApiEnabled'] === true ? 'yes' : 'no');
        update_option('spb_key_expiration_default', sanitize_text_field($settings['expirationDefault']));

        wp_send_json_success();
    }
}
new SimplePageBuilder_Admin();

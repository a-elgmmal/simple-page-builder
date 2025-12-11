<?php
class SimplePageBuilder_API {
    
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        // Token generation endpoint (requires API key)
        register_rest_route('pagebuilder/v1', '/auth/token', [
            'methods' => 'POST',
            'callback' => [$this, 'generate_token'],
            'permission_callback' => [$this, 'check_api_key_auth'],
        ]);
        
        // Create pages endpoint (supports both API key and JWT)
        register_rest_route('pagebuilder/v1', '/create-pages', [
            'methods' => 'POST',
            'callback' => [$this, 'create_pages'],
            'permission_callback' => [$this, 'check_auth'],
        ]);
    }

    /**
     * Authentication Middleware - Supports both API Keys and JWT Tokens
     */
    public function check_auth($request) {
        // 1. Global Kill Switch
        if (get_option('spb_api_enabled') !== 'yes') {
            return new WP_Error('service_unavailable', 'API is currently disabled', ['status' => 503]);
        }

        // 2. Header Check
        $auth_header = $request->get_header('authorization');
        if (!$auth_header || !str_starts_with($auth_header, 'Bearer ')) {
            return new WP_Error('no_auth', 'Missing or invalid Authorization Header', ['status' => 401]);
        }

        $token = substr($auth_header, 7);
        
        // 3. Try JWT authentication first (if enabled)
        if (get_option('spb_jwt_enabled') === 'yes') {
            $jwt_result = $this->check_jwt_auth($token);
            if (!is_wp_error($jwt_result)) {
                // JWT authentication successful
                $request->set_param('api_key_record', $jwt_result);
                return true;
            }
            // If JWT fails, fall through to API key authentication
        }
        
        // 4. Try API Key authentication
        $api_key_result = $this->check_api_key_auth($request);
        if (!is_wp_error($api_key_result)) {
            return true;
        }
        
        // Both authentication methods failed
        return new WP_Error('invalid_auth', 'Invalid authentication token or API key', ['status' => 401]);
    }
    
    /**
     * Check JWT Token Authentication
     */
    private function check_jwt_auth($token) {
        $secret = SimplePageBuilder_JWT::get_secret();
        $payload = SimplePageBuilder_JWT::decode($token, $secret);
        
        if (is_wp_error($payload)) {
            return $payload;
        }
        
        // Extract API key ID from JWT payload
        if (!isset($payload['key_id'])) {
            return new WP_Error('invalid_token', 'Token missing key_id', ['status' => 401]);
        }
        
        // Get the API key record
        global $wpdb;
        $key_record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}spb_api_keys WHERE id = %d",
            intval($payload['key_id'])
        ));
        
        if (!$key_record) {
            return new WP_Error('invalid_token', 'Token references invalid API key', ['status' => 401]);
        }
        
        // Check if key is still active
        if ($key_record->status !== 'ACTIVE') {
            return new WP_Error('invalid_key_status', 'API key associated with token is not active', ['status' => 403]);
        }
        
        // Check expiration
        if ($key_record->expires_at && strtotime($key_record->expires_at) < time()) {
            return new WP_Error('expired_key', 'API key associated with token has expired', ['status' => 403]);
        }
        
        // Check permissions
        if ($key_record->permissions === 'read') {
            return new WP_Error('insufficient_permissions', 'This key does not have write permissions', ['status' => 403]);
        }
        
        // Rate limit check
        $rate_limit = (int) get_option('spb_rate_limit', 100);
        if ($this->is_rate_limited($key_record->id, $rate_limit)) {
            return new WP_Error('rate_limit', 'Rate limit exceeded', ['status' => 429]);
        }
        
        return $key_record;
    }
    
    /**
     * Check API Key Authentication (original method)
     */
    public function check_api_key_auth($request) {
        $auth_header = $request->get_header('authorization');
        if (!$auth_header || !str_starts_with($auth_header, 'Bearer ')) {
            return new WP_Error('no_auth', 'Missing or invalid Authorization Header', ['status' => 401]);
        }

        $token = substr($auth_header, 7);
        $prefix = substr($token, 0, 8);
        
        global $wpdb;
        $table = $wpdb->prefix . 'spb_api_keys';
        
        $key_record = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE prefix = %s", $prefix));

        // Key Validation
        if (!$key_record) {
            return new WP_Error('invalid_key', 'Invalid API Key', ['status' => 403]);
        }

        if (!wp_check_password($token, $key_record->api_key_hash)) {
            return new WP_Error('invalid_key', 'Invalid API Key', ['status' => 403]);
        }

        // Expiration Check
        if ($key_record->expires_at && strtotime($key_record->expires_at) < time()) {
            if ($key_record->status === 'ACTIVE') {
                $wpdb->update($wpdb->prefix . 'spb_api_keys', ['status' => 'EXPIRED'], ['id' => $key_record->id]);
                $key_record->status = 'EXPIRED';
            }
            return new WP_Error('expired_key', 'API Key has expired', ['status' => 403]);
        }

        // Status Check
        if ($key_record->status !== 'ACTIVE') {
            $error_message = $key_record->status === 'REVOKED' ? 'API Key is revoked' : 'API Key is ' . strtolower($key_record->status);
            return new WP_Error('invalid_key_status', $error_message, ['status' => 403]);
        }

        // Permission Check
        if ($key_record->permissions === 'read') {
             return new WP_Error('insufficient_permissions', 'This key does not have write permissions', ['status' => 403]);
        }
        
        // Rate Limit Check
        $rate_limit = (int) get_option('spb_rate_limit', 100);
        if ($this->is_rate_limited($key_record->id, $rate_limit)) {
            $this->log_request($key_record, $request->get_route(), 'FAILED', 429, 0, microtime(true), 'Rate limit exceeded');
            return new WP_Error('rate_limit', 'Rate limit exceeded', ['status' => 429]);
        }

        // Store key record for use in callback
        $request->set_param('api_key_record', $key_record);
        
        return true;
    }
    
    /**
     * Generate JWT Token Endpoint
     * Requires valid API key to generate JWT token
     */
    public function generate_token($request) {
        $start_time = microtime(true);
        $key_record = $request->get_param('api_key_record');
        
        if (!$key_record) {
            return new WP_Error('unauthorized', 'Valid API key required to generate token', ['status' => 401]);
        }
        
        // Generate JWT token
        $secret = SimplePageBuilder_JWT::get_secret();
        $expiration = SimplePageBuilder_JWT::get_expiration();
        
        $payload = [
            'key_id' => $key_record->id,
            'key_name' => $key_record->name,
            'permissions' => $key_record->permissions
        ];
        
        $jwt_token = SimplePageBuilder_JWT::encode($payload, $secret, $expiration);
        
        // Update stats
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}spb_api_keys SET request_count = request_count + 1, last_used = %s WHERE id = %d",
            current_time('mysql'),
            $key_record->id
        ));
        
        // Log request
        $this->log_request($key_record, '/auth/token', 'SUCCESS', 200, 0, $start_time);
        
        return new WP_REST_Response([
            'success' => true,
            'token' => $jwt_token,
            'token_type' => 'Bearer',
            'expires_in' => $expiration,
            'expires_at' => date('c', time() + $expiration)
        ], 200);
    }

    /**
     * Main Endpoint Handler
     */
    public function create_pages($request) {
        $start_time = microtime(true);
        $params = $request->get_json_params();
        $key_record = $request->get_param('api_key_record');
        
        if (!isset($params['pages']) || !is_array($params['pages'])) {
            $this->log_request($key_record, '/create-pages', 'FAILED', 400, 0, $start_time, 'Missing pages array');
            return new WP_Error('invalid_params', 'Missing pages array', ['status' => 400]);
        }

        $created_pages = [];
        $errors = [];
        
        foreach ($params['pages'] as $page_data) {
            if (empty($page_data['title'])) continue;

            $post_data = [
                'post_title'    => sanitize_text_field($page_data['title']),
                'post_content'  => isset($page_data['content']) ? wp_kses_post($page_data['content']) : '',
                'post_status'   => isset($page_data['status']) ? sanitize_text_field($page_data['status']) : 'publish',
                'post_type'     => 'page',
                'post_author'   => get_current_user_id() ?: 1 // Fallback to admin
            ];

            $page_id = wp_insert_post($post_data);

            if (!is_wp_error($page_id)) {
                $page_url = get_permalink($page_id);
                
                $created_pages[] = [
                    'id' => $page_id,
                    'title' => $page_data['title'],
                    'url' => $page_url
                ];
                
                // Track created page in meta for reference
                update_post_meta($page_id, '_spb_created_by_key', $key_record->id);
                
                // Track created page in database table
                global $wpdb;
                $wpdb->insert($wpdb->prefix . 'spb_created_pages', [
                    'page_id' => $page_id,
                    'page_title' => sanitize_text_field($page_data['title']),
                    'page_url' => esc_url_raw($page_url),
                    'api_key_id' => $key_record->id,
                    'api_key_name' => $key_record->name,
                    'created_at' => current_time('mysql')
                ]);
            } else {
                $errors[] = $page_id->get_error_message();
            }
        }

        // Trigger Webhook with Retry Logic
        $webhook_status = $this->trigger_webhook_safely($created_pages, $key_record->name);

        // Update Stats
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}spb_api_keys SET request_count = request_count + 1, last_used = %s WHERE id = %d",
            current_time('mysql'),
            $key_record->id
        ));

        // Log Request
        $this->log_request(
            $key_record, 
            '/create-pages', 
            'SUCCESS', 
            201, 
            count($created_pages), 
            $start_time, 
            null, 
            $webhook_status
        );

        return new WP_REST_Response([
            'success' => true,
            'pages' => $created_pages,
            'errors' => $errors
        ], 201);
    }

    /**
     * Webhook Logic with Retry
     */
    private function trigger_webhook_safely($pages, $key_name) {
        $url = get_option('spb_webhook_url');
        $secret = get_option('spb_webhook_secret');
        
        if (empty($url) || empty($pages)) return 'SKIPPED';

        $payload = json_encode([
            'event' => 'pages_created',
            'timestamp' => gmdate('c'), // ISO 8601 format (e.g., 2025-10-07T14:30:00Z)
            'request_id' => wp_generate_uuid4(),
            'api_key_name' => $key_name,
            'total_pages' => count($pages),
            'pages' => $pages
        ]);

        $signature = hash_hmac('sha256', $payload, $secret);

        $args = [
            'body' => $payload,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Webhook-Signature' => $signature
            ],
            'timeout' => 10, // 10s timeout requirement
            'blocking' => true, // Must be blocking to check success for retry
        ];

        // Retry Logic: 1 attempt + 2 retries = 3 attempts total
        $attempts = 0;
        $max_attempts = 3;
        $delivered = false;

        while ($attempts < $max_attempts && !$delivered) {
            $response = wp_remote_post($url, $args);
            
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) >= 200 && wp_remote_retrieve_response_code($response) < 300) {
                $delivered = true;
            } else {
                $attempts++;
                if ($attempts < $max_attempts) {
                    // Exponential backoff: 2^1 = 2s, 2^2 = 4s
                    sleep(pow(2, $attempts)); 
                }
            }
        }

        return $delivered ? 'SENT' : 'FAILED';
    }

    private function is_rate_limited($key_id, $limit) {
        global $wpdb;
        $table = $wpdb->prefix . 'spb_api_logs';
        // Calculate one hour ago using WordPress timezone
        $current_time = current_time('mysql');
        $one_hour_ago = date('Y-m-d H:i:s', strtotime($current_time) - 3600);
        
        // Count all requests (SUCCESS and FAILED) in the last hour for this key
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE api_key_id = %d AND created_at >= %s",
            $key_id,
            $one_hour_ago
        ));
        
        // If count >= limit, block the request (this would be the limit+1 request)
        return $count >= $limit;
    }

    private function log_request($key_record, $endpoint, $status, $code, $pages_count, $start_time, $error = null, $webhook_status = 'SKIPPED') {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'spb_api_logs', [
            'api_key_id' => $key_record ? $key_record->id : null,
            'api_key_name' => $key_record ? $key_record->name : 'Unknown',
            'endpoint' => $endpoint,
            'status' => $status,
            'status_code' => $code,
            'pages_created' => $pages_count,
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'response_time' => (microtime(true) - $start_time) * 1000,
            'webhook_status' => $webhook_status,
            'error_details' => $error,
            'created_at' => current_time('mysql')
        ]);
    }
}

new SimplePageBuilder_API();


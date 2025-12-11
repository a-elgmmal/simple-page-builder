<?php
class SimplePageBuilder_DB {
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // API Keys Table
        $table_keys = $wpdb->prefix . 'spb_api_keys';
        $sql_keys = "CREATE TABLE $table_keys (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            api_key_hash varchar(255) NOT NULL,
            prefix varchar(10) NOT NULL,
            status varchar(20) DEFAULT 'ACTIVE' NOT NULL,
            permissions varchar(20) DEFAULT 'read_write' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            expires_at datetime NULL,
            last_used datetime NULL,
            request_count int DEFAULT 0 NOT NULL,
            PRIMARY KEY  (id),
            KEY prefix (prefix)
        ) $charset_collate;";

        // Activity Logs Table
        $table_logs = $wpdb->prefix . 'spb_api_logs';
        $sql_logs = "CREATE TABLE $table_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            api_key_id mediumint(9) NULL,
            api_key_name varchar(100) NULL,
            endpoint varchar(255) NOT NULL,
            status varchar(20) NOT NULL,
            status_code int NOT NULL,
            pages_created int DEFAULT 0,
            ip_address varchar(45) NOT NULL,
            response_time float NOT NULL,
            webhook_status varchar(20) DEFAULT 'SKIPPED',
            error_details text NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Created Pages Table
        $table_pages = $wpdb->prefix . 'spb_created_pages';
        $sql_pages = "CREATE TABLE $table_pages (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            page_id bigint(20) NOT NULL,
            page_title varchar(255) NOT NULL,
            page_url varchar(500) NOT NULL,
            api_key_id mediumint(9) NOT NULL,
            api_key_name varchar(100) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY page_id (page_id),
            KEY api_key_id (api_key_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_keys);
        dbDelta($sql_logs);
        dbDelta($sql_pages);

        // Default Options
        add_option('spb_webhook_url', '');
        add_option('spb_webhook_secret', '');
        add_option('spb_rate_limit', 100);
        add_option('spb_api_enabled', 'yes');
        add_option('spb_key_expiration_default', 'never'); // Options: 30, 60, 90, never
        add_option('spb_jwt_enabled', 'yes'); // Enable JWT authentication
        add_option('spb_jwt_expiration', 3600); // JWT token expiration in seconds (default: 1 hour)
    }

    public static function deactivate() {
        // Optional cleanup
    }
}


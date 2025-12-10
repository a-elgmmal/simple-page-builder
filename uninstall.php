<?php
// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Note: Database tables and options are NOT deleted on uninstall
// This is to preserve data in case the plugin is reactivated
// Task.md does not specify database cleanup requirements

// If you want to clean up data, uncomment the following:

/*
global $wpdb;

// 1. Drop Custom Tables
$table_keys = $wpdb->prefix . 'spb_api_keys';
$table_logs = $wpdb->prefix . 'spb_api_logs';
$table_pages = $wpdb->prefix . 'spb_created_pages';

$wpdb->query("DROP TABLE IF EXISTS $table_keys");
$wpdb->query("DROP TABLE IF EXISTS $table_logs");
$wpdb->query("DROP TABLE IF EXISTS $table_pages");

// 2. Delete Options
delete_option('spb_webhook_url');
delete_option('spb_webhook_secret');
delete_option('spb_rate_limit');
delete_option('spb_api_enabled');
delete_option('spb_key_expiration_default');

// 3. Clear Post Meta (Optional - can be heavy operation)
// $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_spb_created_by_key'");
*/


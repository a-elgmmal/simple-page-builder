<?php
/**
 * Plugin Name: Simple Page Builder
 * Description: Automatically create Bulk pages via a secure REST API endpoint.
 * Version: 1.0.2
 * Author: Abdelrahman ElGmmal
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SPB_VERSION', '1.0.2');
define('SPB_PATH', plugin_dir_path(__FILE__));
define('SPB_URL', plugin_dir_url(__FILE__));

// Database setup
require_once SPB_PATH . 'includes/database.php';
// API Handler
require_once SPB_PATH . 'includes/api-handler.php';
// Admin Interface
if (is_admin()) {
    require_once SPB_PATH . 'includes/admin.php';
}

// Hooks
register_activation_hook(__FILE__, ['SimplePageBuilder_DB', 'create_tables']);
register_deactivation_hook(__FILE__, ['SimplePageBuilder_DB', 'deactivate']);
// Uninstall logic should be in uninstall.php


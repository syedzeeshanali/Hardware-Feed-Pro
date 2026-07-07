<?php
/**
 * Plugin Name: HardwareFeed Pro
 * Description: 2026-Ready GMC Feed Generator with Batch Processing and AJAX Filters.
 * Version: 4.0
 * Author: Syed Zeeshan Ali
 * Author URI: https://www.szeeshanali.com
 */

if (!defined('ABSPATH')) exit;

// Define Constants
define('HFP_PATH', plugin_dir_path(__FILE__));
define('HFP_URL', plugin_dir_url(__FILE__));

// Load Includes
require_once HFP_PATH . 'includes/class-hfp-admin.php';
require_once HFP_PATH . 'includes/class-hfp-feed.php';

// Initialize
add_action('plugins_loaded', function() {
    new HFP_Admin();
    new HFP_Feed();
});

// Activation / Deactivation
register_activation_hook(__FILE__, function() {
    flush_rewrite_rules();
});
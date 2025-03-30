<?php

/**
 * Plugin Name: Headless Access Manager
 * Plugin URI: https://stegetfore.se
 * Description: Manages user roles, permissions, and form data for a headless WordPress site with Next.js frontend.
 * Version: 1.0.0
 * Author: Tibor Berki
 * Author URI: https://stegetfore.se
 * License: GPL v2 or later
 * Text Domain: headless-access-manager
 * Domain Path: /languages
 *
 * @package HeadlessAccessManager
 */

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('HAM_VERSION', '1.0.0');
define('HAM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HAM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HAM_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('HAM_PLUGIN_FILE', __FILE__);

// Include necessary files
require_once HAM_PLUGIN_DIR . 'inc/constants.php';
require_once HAM_PLUGIN_DIR . 'inc/loader.php';
require_once HAM_PLUGIN_DIR . 'inc/admin/form-submission-fix.php';

// Hook activation and deactivation functions
register_activation_hook(__FILE__, 'ham_activate');
register_deactivation_hook(__FILE__, 'ham_deactivate');

/**
 * Plugin activation function.
 */
function ham_activate()
{
    require_once HAM_PLUGIN_DIR . 'inc/activation.php';
    ham_activation();
}

/**
 * Plugin deactivation function.
 */
function ham_deactivate()
{
    require_once HAM_PLUGIN_DIR . 'inc/deactivation.php';
    ham_deactivation();
}

// Initialize the plugin
add_action('plugins_loaded', 'ham_init');

/**
 * Initialize the plugin.
 */
function ham_init()
{
    // Load translations
    load_plugin_textdomain('headless-access-manager', false, dirname(HAM_PLUGIN_BASENAME) . '/languages');

    // Initialize the plugin components
    HAM_Loader::instance()->init();
}

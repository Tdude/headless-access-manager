<?php
/**
 * Plugin Name: Headless Access Manager
 * Plugin URI: https://stegetfore.se
 * Description: Manages user roles, permissions, and form data for a headless WordPress site with Next.js frontend.
 * Version: 1.0.3
 * Author: Tibor Berki
 * Author URI: https://stegetfore.se
 * License: GPL v2 or later
 * Text Domain: headless-access-manager
 * Domain Path: /languages
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * @package HeadlessAccessManager
 */

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    exit;
}

// Include the Composer autoloader
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Define plugin constants
define('HAM_VERSION', '1.0.3');
define('HAM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HAM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HAM_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('HAM_PLUGIN_FILE', __FILE__);

// DEBUGGING IN DEV
// define('HAM_JWT_SECRET_KEY', 'the-very-long-and-secret-key-here');
// define( 'WP_DEBUG', true );
// define( 'WP_DEBUG_LOG', true ); // Log errors to wp-content/debug.log
// define( 'WP_DEBUG_DISPLAY', false ); // Don't display errors in HTML
// @ini_set( 'display_errors', 0 ); // Ensure errors are not displayed

// Include necessary files
require_once HAM_PLUGIN_DIR . 'inc/constants.php';
require_once HAM_PLUGIN_DIR . 'inc/loader.php';
require_once HAM_PLUGIN_DIR . 'inc/activation.php';
require_once HAM_PLUGIN_DIR . 'inc/deactivation.php';
require_once HAM_PLUGIN_DIR . 'inc/core/post-types.php';
require_once HAM_PLUGIN_DIR . 'inc/core/capabilities.php';
require_once HAM_PLUGIN_DIR . 'inc/admin/meta-boxes.php';
require_once HAM_PLUGIN_DIR . 'inc/admin/class-ham-user-profile.php';
// Include admin list table customization classes
require_once HAM_PLUGIN_DIR . 'inc/admin/class-ham-student-admin-list-table.php';
require_once HAM_PLUGIN_DIR . 'inc/admin/class-ham-teacher-admin-list-table.php';
// Include admin assets loader
require_once HAM_PLUGIN_DIR . 'inc/admin/class-ham-admin-assets.php';

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    HeadlessAccessManager
 * @subpackage HeadlessAccessManager/includes
 * @author     Tibor Berki <tibor.berki@stegetfore.se>
 */
// Hook activation and deactivation functions
register_activation_hook(__FILE__, 'ham_activate');
register_deactivation_hook(__FILE__, 'ham_deactivate');

/**
 * Plugin activation function.
 */
function ham_activate()
{
    ham_activation();
}

/**
 * Plugin deactivation function.
 */
function ham_deactivate()
{
    ham_deactivation();
}

// Initialize the plugin
add_action('plugins_loaded', 'ham_init');

/**
 * Plugin initialization function.
 */
function ham_init()
{
    // Load plugin textdomain for translations
    load_plugin_textdomain(
        'headless-access-manager',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );

    // Initialize the plugin components
    HAM_Loader::instance()->init();
}
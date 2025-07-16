<?php
/**
 * Plugin Name: Headless Access Manager
 * Plugin URI: https://stegetfore.se
 * Description: Manages user roles, permissions, and form data for a headless WordPress site with Next.js frontend.
 * Version: 1.0.5
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
define('HAM_VERSION', '1.0.5');
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
require_once HAM_PLUGIN_DIR . 'inc/core/class-ham-statistics-manager.php';
require_once HAM_PLUGIN_DIR . 'inc/admin/class-ham-user-profile.php';
// Include admin list table customization classes
// Admin list tables are now loaded through admin-loader.php
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

/**
 * Get students assigned to a specific teacher.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response|WP_Error Response object with student data.
 */
function ham_get_teacher_students($request) {
    $teacher_id = $request->get_param('teacher_id');
    
    // For debugging, return a simple array of mock students
    return rest_ensure_response([
        [
            'id' => 201,
            'name' => 'API Student 1',
            'display_name' => 'API Student 1'
        ],
        [
            'id' => 202,
            'name' => 'API Student 2',
            'display_name' => 'API Student 2'
        ],
        [
            'id' => 203,
            'name' => 'API Student 3',
            'display_name' => 'API Student 3'
        ]
    ]);
    
    /* The real implementation will be uncommented after testing
    // Get the teacher's assigned classes
    $assigned_class_ids = get_post_meta($teacher_id, '_ham_class_ids', true);
    $assigned_class_ids = is_array($assigned_class_ids) ? array_filter(array_map('intval', $assigned_class_ids)) : [];

    // Get the teacher's assigned school
    $assigned_school_id = get_post_meta($teacher_id, '_ham_school_id', true);
    $assigned_school_id = !empty($assigned_school_id) ? intval($assigned_school_id) : null;
    
    $accessible_student_ids = [];
    
    // Priority 1: Get students from assigned classes
    if (!empty($assigned_class_ids)) {
        foreach ($assigned_class_ids as $class_id) {
            $students_in_class = get_post_meta($class_id, '_ham_student_ids', true);
            if (is_array($students_in_class)) {
                $accessible_student_ids = array_merge($accessible_student_ids, $students_in_class);
            }
        }
        $accessible_student_ids = array_unique(array_filter(array_map('intval', $accessible_student_ids)));
    }
    
    $results = [];
    
    if (!empty($accessible_student_ids)) {
        // Query students by IDs from classes
        $student_query = new WP_Query([
            'post_type'      => HAM_CPT_STUDENT,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'post__in'       => $accessible_student_ids,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);
        
        // Build the results array
        if ($student_query->have_posts()) {
            while ($student_query->have_posts()) {
                $student_query->the_post();
                $student_id = get_the_ID();
                
                // Get the WP User ID connected to this student CPT
                $user_id = get_post_meta($student_id, '_ham_user_id', true);
                $user_info = get_userdata($user_id);
                
                $results[] = [
                    'id'           => $student_id,
                    'name'         => get_the_title(),
                    'display_name' => $user_info ? $user_info->display_name : null,
                ];
            }
        }
        wp_reset_postdata();
    }
    
    return rest_ensure_response($results);
    */
}

// Initialize the plugin
add_action('plugins_loaded', 'ham_init');

/**
 * Plugin initialization function.
 */
function ham_init()
{
    // Register the teachers/students endpoint directly
    add_action('rest_api_init', function() {
        register_rest_route('ham/v1', '/teachers/(?P<teacher_id>\d+)/students', [
            'methods'             => 'GET',
            'callback'            => 'ham_get_teacher_students',
            'permission_callback' => function() { return true; }, // For testing, we'll allow open access
            'args'                => [
                'teacher_id' => [
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ],
            ],
        ]);
    });
    
    // Load plugin textdomain for translations
    load_plugin_textdomain(
        'headless-access-manager',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );

    // Initialize the plugin components
    HAM_Loader::instance()->init();
}
<?php

/**
 * File: inc/activation.php
 *
 * Handles plugin activation tasks.
 *
 * @package HeadlessAccessManager
 */

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Plugin activation function.
 *
 * Performs tasks needed when plugin is activated:
 * - Create roles and capabilities
 * - Register post types to flush rewrite rules
 * - Create default school if needed
 */
function ham_activation()
{
    // Make sure core files are loaded
    require_once HAM_PLUGIN_DIR . 'inc/core/roles.php';
    require_once HAM_PLUGIN_DIR . 'inc/core/capabilities.php';
    require_once HAM_PLUGIN_DIR . 'inc/core/post-types.php';

    // Initialize roles and capabilities
    HAM_Roles::init();
    HAM_Capabilities::init();

    // Create roles
    HAM_Roles::create_roles();

    // Add capabilities to roles
    HAM_Capabilities::add_capabilities_to_roles();

    // Register post types to flush rewrite rules
    HAM_Post_Types::register_post_types();

    // Flush rewrite rules
    flush_rewrite_rules();

    // Create a default school if no schools exist
    ham_maybe_create_default_school();

    // Set version number in options
    update_option('ham_version', HAM_VERSION);
}

/**
 * Create a default school if no schools exist.
 */
function ham_maybe_create_default_school()
{
    $schools = get_posts(array(
        'post_type'      => HAM_CPT_SCHOOL,
        'posts_per_page' => 1,
        'post_status'    => 'publish',
    ));

    if (empty($schools)) {
        // Create a default school
        $school_id = wp_insert_post(array(
            'post_title'   => __('Default School', 'headless-access-manager'),
            'post_type'    => HAM_CPT_SCHOOL,
            'post_status'  => 'publish',
        ));
    }
}

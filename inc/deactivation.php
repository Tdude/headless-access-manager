<?php

/**
 * File: inc/deactivation.php
 *
 * Handles plugin deactivation tasks.
 *
 * @package HeadlessAccessManager
 */

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Plugin deactivation function.
 *
 * Performs tasks needed when plugin is deactivated:
 * - Flush rewrite rules
 * - Optionally remove roles and capabilities (only if requested by admin)
 */
function ham_deactivation()
{
    // Flush rewrite rules
    flush_rewrite_rules();

    // We don't remove custom roles and data by default on deactivation
    // This is a safety measure to prevent accidental data loss

    // If you want to fully clean up on deactivation, enable this section
    /*
    if ( get_option( 'ham_cleanup_on_deactivation' ) ) {
        // Clean up roles
        ham_remove_roles();

        // Remove plugin options
        delete_option( 'ham_version' );
        delete_option( 'ham_cleanup_on_deactivation' );
    }
    */
}

/**
 * Remove custom roles created by the plugin.
 */
function ham_remove_roles()
{
    // Make sure roles file is loaded
    require_once HAM_PLUGIN_DIR . 'inc/core/roles.php';

    $roles = array(
        HAM_ROLE_STUDENT,
        HAM_ROLE_TEACHER,
        HAM_ROLE_PRINCIPAL,
        HAM_ROLE_SCHOOL_HEAD,
    );

    foreach ($roles as $role) {
        remove_role($role);
    }
}

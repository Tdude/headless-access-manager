<?php

/**
 * File: inc/core/roles.php
 *
 * Creates and manages custom user roles.
 *
 * @package HeadlessAccessManager
 */

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class HAM_Roles
 *
 * Handles custom user roles for the plugin.
 */
class HAM_Roles
{
    /**
     * Initialize roles functionality.
     */
    public static function init()
    {
        // No action needed if just including the file
    }

    /**
     * Create custom user roles.
     */
    public static function create_roles()
    {
        // Student role
        add_role(
            HAM_ROLE_STUDENT,
            __('Student', 'headless-access-manager'),
            array(
                'read' => true,
            )
        );

        // Teacher role
        add_role(
            HAM_ROLE_TEACHER,
            __('Teacher', 'headless-access-manager'),
            array(
                'read' => true,
            )
        );

        // Principal role
        add_role(
            HAM_ROLE_PRINCIPAL,
            __('Principal', 'headless-access-manager'),
            array(
                'read' => true,
            )
        );

        // School head role
        add_role(
            HAM_ROLE_SCHOOL_HEAD,
            __('School Head', 'headless-access-manager'),
            array(
                'read' => true,
            )
        );
    }

    /**
     * Get all HAM roles.
     *
     * @return array Array of role slugs.
     */
    public static function get_all_roles()
    {
        return array(
            HAM_ROLE_STUDENT,
            HAM_ROLE_TEACHER,
            HAM_ROLE_PRINCIPAL,
            HAM_ROLE_SCHOOL_HEAD,
        );
    }

    /**
     * Check if a user has any HAM role.
     *
     * @param WP_User|int $user User object or ID.
     * @return bool True if user has any HAM role.
     */
    public static function has_ham_role($user)
    {
        if (! $user instanceof WP_User) {
            $user = get_user_by('id', $user);
        }

        if (! $user) {
            return false;
        }

        foreach (self::get_all_roles() as $role) {
            if (in_array($role, (array) $user->roles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the HAM role of a user.
     *
     * @param WP_User|int $user User object or ID.
     * @return string|false Role slug or false if user has no HAM role.
     */
    public static function get_ham_role($user)
    {
        if (! $user instanceof WP_User) {
            $user = get_user_by('id', $user);
        }

        if (! $user) {
            return false;
        }

        foreach (self::get_all_roles() as $role) {
            if (in_array($role, (array) $user->roles, true)) {
                return $role;
            }
        }

        return false;
    }
}

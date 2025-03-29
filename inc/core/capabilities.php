<?php

/**
 * File: inc/core/capabilities.php
 *
 * Defines and manages plugin-specific capabilities.
 *
 * @package HeadlessAccessManager
 */

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class HAM_Capabilities
 *
 * Handles custom capabilities for the plugin.
 */
class HAM_Capabilities
{
    /**
     * Initialize capabilities functionality.
     */
    public static function init()
    {
        // No action needed if just including the file
    }

    /**
     * Get all plugin capabilities.
     *
     * @return array Associative array of capabilities grouped by category.
     */
    public static function get_all_capabilities()
    {
        return array(
            'assessment' => array(
                'submit_assessment',
                'view_own_assessments',
                'view_others_assessments',
                'edit_assessments',
            ),
            'statistics' => array(
                'view_own_stats',
                'view_class_stats',
                'view_teacher_stats',
                'view_school_stats',
                'view_multi_school_stats',
            ),
            'management' => array(
                'manage_students',
                'manage_teachers',
                'manage_school_users',
                'manage_school_classes',
                'manage_schools',
            ),
        );
    }

    /**
     * Get a flat array of all capabilities.
     *
     * @return array Array of capability names.
     */
    public static function get_capabilities_flat()
    {
        $capabilities = self::get_all_capabilities();
        $flat = array();

        foreach ($capabilities as $group) {
            $flat = array_merge($flat, $group);
        }

        return $flat;
    }

    /**
     * Add capabilities to roles.
     */
    public static function add_capabilities_to_roles()
    {
        // Student capabilities
        $student_caps = array(
            'view_own_assessments',
            'view_own_stats',
        );

        // Teacher capabilities
        $teacher_caps = array_merge(
            $student_caps,
            array(
                'submit_assessment',
                'view_class_stats',
                'manage_students',
            )
        );

        // Principal capabilities
        $principal_caps = array_merge(
            $teacher_caps,
            array(
                'view_teacher_stats',
                'view_school_stats',
                'manage_teachers',
                'manage_school_users',
                'manage_school_classes',
            )
        );

        // School head capabilities
        $school_head_caps = array_merge(
            $principal_caps,
            array(
                'view_multi_school_stats',
                'manage_schools',
            )
        );

        // Add capabilities to roles
        self::add_capabilities_to_role(HAM_ROLE_STUDENT, $student_caps);
        self::add_capabilities_to_role(HAM_ROLE_TEACHER, $teacher_caps);
        self::add_capabilities_to_role(HAM_ROLE_PRINCIPAL, $principal_caps);
        self::add_capabilities_to_role(HAM_ROLE_SCHOOL_HEAD, $school_head_caps);

        // Add capabilities to administrator role
        $administrator = get_role('administrator');
        if ($administrator) {
            foreach (self::get_capabilities_flat() as $cap) {
                $administrator->add_cap($cap);
            }
        }
    }

    /**
     * Add capabilities to a specific role.
     *
     * @param string $role_slug Role slug.
     * @param array  $caps      Array of capabilities to add.
     */
    private static function add_capabilities_to_role($role_slug, $caps)
    {
        $role = get_role($role_slug);

        if (! $role) {
            return;
        }

        foreach ($caps as $cap) {
            $role->add_cap($cap);
        }
    }
}

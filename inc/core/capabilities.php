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
        add_filter('map_meta_cap', array(__CLASS__, 'map_meta_capabilities'), 10, 4);
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
                'edit_assessments', // General capability for editing assessments
            ),
            'statistics' => array(
                'view_own_stats',
                'view_class_stats',
                'view_teacher_stats',
                'view_school_stats',
                'view_multi_school_stats',
            ),
            'management' => array(
                'manage_students', // General management capabilities, may need review later
                'manage_teachers',
                'manage_school_users',
                'manage_school_classes',
                'manage_schools',
            ),
            // CPT Specific Capabilities
            'ham_student' => array(
                'edit_ham_student',
                'read_ham_student',
                'delete_ham_student',
                'edit_ham_students',
                'edit_others_ham_students',
                'publish_ham_students',
                'read_private_ham_students',
            ),
            'ham_teacher' => array(
                'edit_ham_teacher',
                'read_ham_teacher',
                'delete_ham_teacher',
                'edit_ham_teachers',
                'edit_others_ham_teachers',
                'publish_ham_teachers',
                'read_private_ham_teachers',
            ),
            'ham_class' => array(
                'edit_ham_class',
                'read_ham_class',
                'delete_ham_class',
                'edit_ham_classes',
                'edit_others_ham_classes',
                'publish_ham_classes',
                'read_private_ham_classes',
            ),
            'ham_principal' => array(
                'edit_ham_principal',
                'read_ham_principal',
                'delete_ham_principal',
                'edit_ham_principals',
                'edit_others_ham_principals',
                'publish_ham_principals',
                'read_private_ham_principals',
            ),
            'ham_school_head' => array(
                'edit_ham_school_head',
                'read_ham_school_head',
                'delete_ham_school_head',
                'edit_ham_school_heads',
                'edit_others_ham_school_heads',
                'publish_ham_school_heads',
                'read_private_ham_school_heads',
            ),
            'ham_school' => array(
                'edit_ham_school',
                'read_ham_school',
                'delete_ham_school',
                'edit_ham_schools',
                'edit_others_ham_schools',
                'publish_ham_schools',
                'read_private_ham_schools',
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
        // --- Base Capabilities for each CPT (used for easier merging) ---
        $student_cpt_caps = self::get_all_capabilities()['ham_student'];
        $teacher_cpt_caps = self::get_all_capabilities()['ham_teacher'];
        $class_cpt_caps = self::get_all_capabilities()['ham_class'];
        $principal_cpt_caps = self::get_all_capabilities()['ham_principal'];
        $school_head_cpt_caps = self::get_all_capabilities()['ham_school_head'];
        $school_cpt_caps = self::get_all_capabilities()['ham_school'];

        // --- Role Specific Capabilities ---

        // Student capabilities (existing plugin-specific)
        $student_plugin_caps = array(
            'view_own_assessments',
            'view_own_stats',
        );
        $student_total_caps = $student_plugin_caps;

        // Teacher capabilities
        $teacher_plugin_caps = array_merge(
            $student_plugin_caps,
            array(
                'submit_assessment',
                'view_class_stats',
                'manage_students', // Existing management cap
            )
        );
        $teacher_assigned_cpt_caps = array(
            // Student CPT - full control (will be filtered by map_meta_cap)
            'read_ham_student',
            'edit_ham_students', 
            'publish_ham_students',
            'edit_others_ham_students',
            'delete_ham_student', // Assuming teachers can delete students they manage
            'read_private_ham_students',
            // Class CPT - read their own, potentially edit their own (map_meta_cap)
            'read_ham_class',
            'edit_ham_classes', // To see Classes menu
            // 'edit_ham_class', // If they can edit their assigned class details
        );
        $teacher_total_caps = array_unique(array_merge($teacher_plugin_caps, $teacher_assigned_cpt_caps));

        // Principal capabilities
        $principal_plugin_caps = array_merge(
            $teacher_plugin_caps, // Inherits teacher's plugin caps
            array(
                'view_teacher_stats',
                'view_school_stats',
                'manage_teachers', // Existing management cap
                'manage_school_users',
                'manage_school_classes',
            )
        );
        $principal_assigned_cpt_caps = array_merge(
            $teacher_assigned_cpt_caps, // Inherits teacher's CPT caps for students/classes
            array(
                // Teacher CPT - full control over teachers in their school (filtered by map_meta_cap)
                'read_ham_teacher',
                'edit_ham_teachers',
                'publish_ham_teachers',
                'edit_others_ham_teachers',
                'delete_ham_teacher', // Assuming principals can delete teachers in their school
                'read_private_ham_teachers',
                // Class CPT - broader control than teachers
                'edit_others_ham_classes',
                'publish_ham_classes',
                'delete_ham_class', // Assuming principals can delete classes in their school
                // Principal CPT - read-only for other principals, menu access
                'read_ham_principal',
                'edit_ham_principals', // To see Principals menu
                'read_private_ham_principals',
                // School CPT - read/edit their own school (filtered by map_meta_cap)
                'read_ham_school',
                'edit_ham_school', 
                'edit_ham_schools' // To see Schools menu (likely only their own)
            )
        );
        $principal_total_caps = array_unique(array_merge($principal_plugin_caps, $principal_assigned_cpt_caps));

        // School Head capabilities
        $school_head_plugin_caps = array_merge(
            $principal_plugin_caps, // Inherits principal's plugin caps
            array(
                'view_multi_school_stats',
                'manage_schools', // Existing management cap
            )
        );
        $school_head_assigned_cpt_caps = array_merge(
            $principal_assigned_cpt_caps, // Inherits principal's CPT caps
            array(
                // Principal CPT - broader control
                'edit_others_ham_principals',
                'publish_ham_principals',
                'delete_ham_principal',
                // School CPT - broader control
                'edit_others_ham_schools',
                'publish_ham_schools',
                'delete_ham_school',
                // School Head CPT - read-only for others, menu access
                'read_ham_school_head',
                'edit_ham_school_heads', // To see School Heads menu
                'read_private_ham_school_heads',
            )
        );
        $school_head_total_caps = array_unique(array_merge($school_head_plugin_caps, $school_head_assigned_cpt_caps));

        // Add capabilities to roles
        self::add_capabilities_to_role(HAM_ROLE_STUDENT, $student_total_caps);
        self::add_capabilities_to_role(HAM_ROLE_TEACHER, $teacher_total_caps);
        self::add_capabilities_to_role(HAM_ROLE_PRINCIPAL, $principal_total_caps);
        self::add_capabilities_to_role(HAM_ROLE_SCHOOL_HEAD, $school_head_total_caps);

        // Add ALL capabilities (plugin-specific and CPT-specific) to administrator role
        $administrator = get_role('administrator');
        if ($administrator) {
            $all_plugin_caps = self::get_capabilities_flat(); // This now includes CPT caps
            foreach ($all_plugin_caps as $cap) {
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

    /**
     * Map meta capabilities for HAM CPTs.
     *
     * @param array   $caps    Array of primitive capabilities required by the user.
     * @param string  $cap     The meta capability being checked (e.g., 'edit_post').
     * @param int     $user_id The ID of the user being checked.
     * @param array   $args    Additional arguments. Typically $args[0] is the post ID.
     * @return array  Modified array of primitive capabilities.
     */
    public static function map_meta_capabilities($caps, $cap, $user_id, $args)
    {
        // TODO: DEV MODE - The following logic is temporarily simplified for development.
        // Ensure strict capability checks are re-instated and thoroughly tested before production.

        $post_id = null;
        $post_type_in_args = null;

        // Determine post_id or post_type from $args
        if (!empty($args)) {
            if (is_numeric($args[0])) {
                $post_id = absint($args[0]);
            } elseif (is_string($args[0]) && post_type_exists($args[0])) {
                $post_type_in_args = $args[0];
            }
        }

        $current_post_type = null;
        if ($post_id) {
            $current_post_type = get_post_type($post_id);
        } elseif ($post_type_in_args) {
            $current_post_type = $post_type_in_args;
        } else {
            return $caps; // No specific post type context
        }

        // Check if the capability is for one of our CPTs
        $ham_cpts = array(
            HAM_CPT_STUDENT,
            HAM_CPT_TEACHER,
            HAM_CPT_CLASS,
            HAM_CPT_PRINCIPAL,
            HAM_CPT_SCHOOL_HEAD,
            HAM_CPT_SCHOOL,
            HAM_CPT_ASSESSMENT, // Included for dev mode, though its final caps might differ
        );

        if (!in_array($current_post_type, $ham_cpts)) {
            return $caps; // Not one of our CPTs that use custom capabilities
        }

        $user_obj = get_userdata($user_id);
        if (!$user_obj) {
            return array('do_not_allow');
        }

        $cpt_object = get_post_type_object($current_post_type);
        if (!$cpt_object || !isset($cpt_object->cap)) {
            return $caps; // Should not happen if CPT is registered correctly
        }

        // Administrator logic - Admins get their capabilities directly
        if (user_can($user_id, 'manage_options')) {
            if (isset($cpt_object->cap->$cap)) {
                return array($cpt_object->cap->$cap);
            }
            return $caps; // Fallback for admin if direct mapping isn't found
        }

        // --- START DEV MODE PERMISSIVE BLOCK FOR HAM ROLES ---
        $ham_user_roles = [HAM_ROLE_TEACHER, HAM_ROLE_PRINCIPAL, HAM_ROLE_SCHOOL_HEAD];
        $user_has_ham_role = false;
        foreach ($ham_user_roles as $ham_role) {
            if (in_array($ham_role, $user_obj->roles)) {
                $user_has_ham_role = true;
                break;
            }
        }

        if ($user_has_ham_role) {
            // DEV MODE: For HAM roles, grant direct mapping to primitive capabilities for HAM CPTs.
            if (isset($cpt_object->cap->$cap)) {
                return array($cpt_object->cap->$cap);
            }
            // Special handling for generic 'read' meta cap if not directly in $cpt_object->cap
            if ($cap === 'read') {
                if (!$post_id && isset($cpt_object->cap->edit_posts)) { // List table access
                    return array($cpt_object->cap->edit_posts);
                }
                if ($post_id && isset($cpt_object->cap->read_post)) { // Single post access
                    return array($cpt_object->cap->read_post);
                }
            }
            // If it's a HAM role, on a HAM CPT, but the specific meta cap isn't directly mapped
            // or handled above, deny for safety even in dev mode.
            return array('do_not_allow');
        }
        // --- END DEV MODE PERMISSIVE BLOCK FOR HAM ROLES ---

        // If the user is not an admin and does not have one of the HAM roles covered by the
        // DEV MODE block, they should be denied access to these CPTs by default.
        // The original specific logic for roles (like the detailed Teacher checks)
        // is bypassed when the DEV MODE block is active for those roles.
        return array('do_not_allow');
    }

    /**
     * Helper: Get Teacher CPT ID for a given WordPress User ID.
     * @param int $user_id WordPress User ID.
     * @return int|false Teacher CPT Post ID or false if not found.
     */
    private static function get_teacher_cpt_id_for_user($user_id) {
        $query_args = array(
            'post_type' => HAM_CPT_TEACHER,
            'posts_per_page' => 1,
            'meta_query' => array(
                array(
                    'key' => '_ham_user_id', // Assuming '_ham_user_id' is the meta key linking WP User to Teacher CPT
                    'value' => $user_id,
                    'compare' => '=',
                    'type' => 'NUMERIC',
                ),
            ),
            'fields' => 'ids',
        );
        $teacher_posts = get_posts($query_args);
        return !empty($teacher_posts) ? $teacher_posts[0] : false;
    }

    /**
     * Helper: Get Class CPT IDs that a given Student CPT ID belongs to.
     * @param int $student_id Student CPT Post ID.
     * @return array Array of Class CPT Post IDs.
     */
    private static function get_student_parent_class_ids($student_id) {
        $parent_class_ids = array();
        $all_classes_query = new WP_Query(array(
            'post_type' => HAM_CPT_CLASS,
            'posts_per_page' => -1,
            'fields' => 'ids',
        ));

        if ($all_classes_query->have_posts()) {
            foreach ($all_classes_query->posts as $class_id) {
                $students_in_class = get_post_meta($class_id, '_ham_student_ids', true);
                $students_in_class = is_array($students_in_class) ? $students_in_class : array();
                if (in_array($student_id, $students_in_class)) {
                    $parent_class_ids[] = $class_id;
                }
            }
        }
        return array_unique($parent_class_ids);
    }

} // End of HAM_Capabilities class

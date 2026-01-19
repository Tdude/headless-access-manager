<?php

/**
 * File: inc/core/user-meta.php
 *
 * Handles user meta fields for storing relationships between users and schools/classes.
 *
 * @package HeadlessAccessManager
 */

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class HAM_User_Meta
 *
 * Manages user meta fields and relationships.
 */
class HAM_User_Meta
{
    /**
     * Initialize user meta functionality.
     */
    public static function init()
    {
        // Add hooks for saving user meta
        add_action('personal_options_update', array( __CLASS__, 'save_user_meta' ));
        add_action('edit_user_profile_update', array( __CLASS__, 'save_user_meta' ));
    }

    /**
     * Save user meta when profile is updated.
     *
     * @param int $user_id User ID.
     */
    public static function save_user_meta($user_id)
    {
        if (! current_user_can('edit_user', $user_id)) {
            return;
        }

        // Process school IDs (multi) for teachers; keep ham_school_id for others/backwards compatibility
        if (isset($_POST['ham_school_ids']) && is_array($_POST['ham_school_ids'])) {
            $school_ids = array_values(array_filter(array_map('absint', (array) $_POST['ham_school_ids'])));

            if (! empty($school_ids)) {
                update_user_meta($user_id, HAM_USER_META_SCHOOL_IDS, $school_ids);
                update_user_meta($user_id, HAM_USER_META_SCHOOL_ID, absint($school_ids[0]));
            } else {
                delete_user_meta($user_id, HAM_USER_META_SCHOOL_IDS);
                delete_user_meta($user_id, HAM_USER_META_SCHOOL_ID);
            }
        } elseif (isset($_POST['ham_school_id'])) {
            $school_id = absint($_POST['ham_school_id']);
            if ($school_id > 0) {
                update_user_meta($user_id, HAM_USER_META_SCHOOL_IDS, [absint($school_id)]);
                update_user_meta($user_id, HAM_USER_META_SCHOOL_ID, $school_id);
            } else {
                delete_user_meta($user_id, HAM_USER_META_SCHOOL_IDS);
                delete_user_meta($user_id, HAM_USER_META_SCHOOL_ID);
            }
        }

        // Process class IDs
        if (isset($_POST['ham_class_ids']) && is_array($_POST['ham_class_ids'])) {
            $class_ids = array_map('absint', $_POST['ham_class_ids']);
            $class_ids = array_filter($class_ids);

            if (! empty($class_ids)) {
                update_user_meta($user_id, HAM_USER_META_CLASS_IDS, $class_ids);
            } else {
                delete_user_meta($user_id, HAM_USER_META_CLASS_IDS);
            }
        }

        // Process managed school IDs (for School Head)
        if (isset($_POST['ham_managed_school_ids']) && is_array($_POST['ham_managed_school_ids'])) {
            $managed_school_ids = array_map('absint', $_POST['ham_managed_school_ids']);
            $managed_school_ids = array_filter($managed_school_ids);

            if (! empty($managed_school_ids)) {
                update_user_meta($user_id, HAM_USER_META_MANAGED_SCHOOL_IDS, $managed_school_ids);
            } else {
                delete_user_meta($user_id, HAM_USER_META_MANAGED_SCHOOL_IDS);
            }
        }
    }

    /**
     * Get a user's school ID.
     *
     * @param int $user_id User ID.
     * @return int|false School ID or false if not set.
     */
    public static function get_user_school_id($user_id)
    {
        $school_id = get_user_meta($user_id, HAM_USER_META_SCHOOL_ID, true);
        return ! empty($school_id) ? (int) $school_id : false;
    }

    /**
     * Get a user's class IDs.
     *
     * @param int $user_id User ID.
     * @return array Array of class IDs.
     */
    public static function get_user_class_ids($user_id)
    {
        $class_ids = get_user_meta($user_id, HAM_USER_META_CLASS_IDS, true);
        return ! empty($class_ids) && is_array($class_ids) ? $class_ids : array();
    }

    /**
     * Get schools managed by a user.
     *
     * @param int $user_id User ID.
     * @return array Array of school IDs.
     */
    public static function get_user_managed_school_ids($user_id)
    {
        $school_ids = get_user_meta($user_id, HAM_USER_META_MANAGED_SCHOOL_IDS, true);
        return ! empty($school_ids) && is_array($school_ids) ? $school_ids : array();
    }

    /**
     * Set a user's school.
     *
     * @param int $user_id   User ID.
     * @param int $school_id School ID.
     * @return bool True on success, false on failure.
     */
    public static function set_user_school($user_id, $school_id)
    {
        return update_user_meta($user_id, HAM_USER_META_SCHOOL_ID, absint($school_id));
    }

    /**
     * Set a user's classes.
     *
     * @param int   $user_id   User ID.
     * @param array $class_ids Array of class IDs.
     * @return bool True on success, false on failure.
     */
    public static function set_user_classes($user_id, $class_ids)
    {
        if (! is_array($class_ids)) {
            $class_ids = array( absint($class_ids) );
        } else {
            $class_ids = array_map('absint', $class_ids);
        }

        return update_user_meta($user_id, HAM_USER_META_CLASS_IDS, $class_ids);
    }

    /**
     * Set schools managed by a user.
     *
     * @param int   $user_id    User ID.
     * @param array $school_ids Array of school IDs.
     * @return bool True on success, false on failure.
     */
    public static function set_user_managed_schools($user_id, $school_ids)
    {
        if (! is_array($school_ids)) {
            $school_ids = array( absint($school_ids) );
        } else {
            $school_ids = array_map('absint', $school_ids);
        }

        return update_user_meta($user_id, HAM_USER_META_MANAGED_SCHOOL_IDS, $school_ids);
    }
}

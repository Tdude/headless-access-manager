<?php

/**
 * File: inc/helpers/utilities.php
 *
 * General utility functions used throughout the plugin.
 *
 * @package HeadlessAccessManager
 */

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Get all schools as an array of post objects.
 *
 * @param array $args Additional query arguments.
 * @return array Array of school post objects.
 */
function ham_get_schools($args = array())
{
    $default_args = array(
        'post_type'      => HAM_CPT_SCHOOL,
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'orderby'        => 'title',
        'order'          => 'ASC',
    );

    $args = wp_parse_args($args, $default_args);

    return get_posts($args);
}

/**
 * Get all classes as an array of post objects, optionally filtered by school.
 *
 * @param int   $school_id Optional school ID to filter by.
 * @param array $args      Additional query arguments.
 * @return array Array of class post objects.
 */
function ham_get_classes($school_id = 0, $args = array())
{
    $default_args = array(
        'post_type'      => HAM_CPT_CLASS,
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'orderby'        => 'title',
        'order'          => 'ASC',
    );

    $args = wp_parse_args($args, $default_args);

    // Filter by school if provided
    if ($school_id > 0) {
        $args['meta_query'] = array(
            array(
                'key'   => '_ham_school_id',
                'value' => absint($school_id),
            ),
        );
    }

    return get_posts($args);
}

/**
 * Get users by HAM role.
 *
 * @param string $role     Role slug.
 * @param int    $school_id Optional school ID to filter by.
 * @return array Array of WP_User objects.
 */
function ham_get_users_by_role($role, $school_id = 0)
{
    $args = array(
        'role' => $role,
    );

    $users = get_users($args);

    // Filter by school if provided
    if ($school_id > 0) {
        $filtered_users = array();

        foreach ($users as $user) {
            $user_school_id = get_user_meta($user->ID, HAM_USER_META_SCHOOL_ID, true);

            if ($user_school_id == $school_id) {
                $filtered_users[] = $user;
            }
        }

        return $filtered_users;
    }

    return $users;
}

/**
 * Get all users in a specific class.
 *
 * @param int    $class_id Class ID.
 * @param string $role     Optional role to filter by.
 * @return array Array of WP_User objects.
 */
function ham_get_users_by_class($class_id, $role = '')
{
    $args = array();

    if (! empty($role)) {
        $args['role'] = $role;
    }

    $users = get_users($args);
    $filtered_users = array();

    foreach ($users as $user) {
        $class_ids = get_user_meta($user->ID, HAM_USER_META_CLASS_IDS, true);

        if (! empty($class_ids) && is_array($class_ids) && in_array($class_id, $class_ids)) {
            $filtered_users[] = $user;
        }
    }

    return $filtered_users;
}

/**
 * Get student assessments.
 *
 * @param int   $student_id Student user ID.
 * @param array $args       Additional query arguments.
 * @return array Array of assessment post objects.
 */
function ham_get_student_assessments($student_id, $args = array())
{
    $default_args = array(
        'post_type'      => HAM_CPT_ASSESSMENT,
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'meta_query'     => array(
            array(
                'key'   => HAM_ASSESSMENT_META_STUDENT_ID,
                'value' => absint($student_id),
            ),
        ),
        'orderby'        => 'date',
        'order'          => 'DESC',
    );

    $args = wp_parse_args($args, $default_args);

    return get_posts($args);
}

/**
 * Get teacher assessments.
 *
 * @param int   $teacher_id Teacher user ID.
 * @param array $args       Additional query arguments.
 * @return array Array of assessment post objects.
 */
function ham_get_teacher_assessments($teacher_id, $args = array())
{
    $default_args = array(
        'post_type'      => HAM_CPT_ASSESSMENT,
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'author'         => absint($teacher_id),
        'orderby'        => 'date',
        'order'          => 'DESC',
    );

    $args = wp_parse_args($args, $default_args);

    return get_posts($args);
}

/**
 * Get assessment data.
 *
 * @param int $assessment_id Assessment post ID.
 * @return array|false Assessment data array or false if not found.
 */
function ham_get_assessment_data($assessment_id)
{
    $assessment_data = get_post_meta($assessment_id, HAM_ASSESSMENT_META_DATA, true);

    if (empty($assessment_data)) {
        return false;
    }

    // If data is stored as a serialized string, unserialize it
    if (is_string($assessment_data) && ! is_array($assessment_data)) {
        $assessment_data = maybe_unserialize($assessment_data);
    }

    return $assessment_data;
}

/**
 * Format error response for API.
 *
 * @param string $code    Error code.
 * @param string $message Error message.
 * @param int    $status  HTTP status code.
 * @param array  $data    Additional error data.
 * @return WP_Error WP_Error object.
 */
function ham_api_error($code, $message, $status = 400, $data = array())
{
    return new WP_Error($code, $message, array_merge(array( 'status' => $status ), $data));
}

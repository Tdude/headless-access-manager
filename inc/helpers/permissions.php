<?php

/**
 * File: inc/helpers/permissions.php
 *
 * Helper functions for permission checking.
 *
 * @package HeadlessAccessManager
 */

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Check if a user can access a specific student's data.
 *
 * @param int $user_id    The user ID to check permissions for.
 * @param int $student_id The student ID to check access to.
 * @return bool True if user can access, false otherwise.
 */
function ham_can_access_student($user_id, $student_id)
{
    // Self-access is always allowed
    if ($user_id == $student_id) {
        return true;
    }

    // Get user and student data
    $user = get_user_by('id', $user_id);
    $student = get_user_by('id', $student_id);

    if (! $user || ! $student) {
        return false;
    }

    // Check if student has proper role
    if (! in_array(HAM_ROLE_STUDENT, (array) $student->roles)) {
        return false;
    }

    // Administrator can access any student
    if (in_array('administrator', (array) $user->roles)) {
        return true;
    }

    // Check based on role
    if (in_array(HAM_ROLE_SCHOOL_HEAD, (array) $user->roles)) {
        // School head can access any student in their managed schools
        $managed_school_ids = get_user_meta($user_id, HAM_USER_META_MANAGED_SCHOOL_IDS, true);
        $student_school_id = get_user_meta($student_id, HAM_USER_META_SCHOOL_ID, true);

        if (is_array($managed_school_ids) && in_array($student_school_id, $managed_school_ids)) {
            return true;
        }
    } elseif (in_array(HAM_ROLE_PRINCIPAL, (array) $user->roles)) {
        // Principal can access any student in their school
        $principal_school_id = get_user_meta($user_id, HAM_USER_META_SCHOOL_ID, true);
        $student_school_id = get_user_meta($student_id, HAM_USER_META_SCHOOL_ID, true);

        if ($principal_school_id && $principal_school_id == $student_school_id) {
            return true;
        }
    } elseif (in_array(HAM_ROLE_TEACHER, (array) $user->roles)) {
        // Teacher can access students in their classes
        $teacher_class_ids = get_user_meta($user_id, HAM_USER_META_CLASS_IDS, true);
        $student_class_ids = get_user_meta($student_id, HAM_USER_META_CLASS_IDS, true);

        if (is_array($teacher_class_ids) && is_array($student_class_ids)) {
            $common_classes = array_intersect($teacher_class_ids, $student_class_ids);
            if (! empty($common_classes)) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Check if a user can access a specific class's data.
 *
 * @param int $user_id  The user ID to check permissions for.
 * @param int $class_id The class ID to check access to.
 * @return bool True if user can access, false otherwise.
 */
function ham_can_access_class($user_id, $class_id)
{
    $user = get_user_by('id', $user_id);

    if (! $user) {
        return false;
    }

    // Administrator can access any class
    if (in_array('administrator', (array) $user->roles)) {
        return true;
    }

    // Get class school
    $class_school_id = get_post_meta($class_id, '_ham_school_id', true);

    // Check based on role
    if (in_array(HAM_ROLE_SCHOOL_HEAD, (array) $user->roles)) {
        // School head can access any class in their managed schools
        $managed_school_ids = get_user_meta($user_id, HAM_USER_META_MANAGED_SCHOOL_IDS, true);

        if (is_array($managed_school_ids) && in_array($class_school_id, $managed_school_ids)) {
            return true;
        }
    } elseif (in_array(HAM_ROLE_PRINCIPAL, (array) $user->roles)) {
        // Principal can access any class in their school
        $principal_school_id = get_user_meta($user_id, HAM_USER_META_SCHOOL_ID, true);

        if ($principal_school_id && $principal_school_id == $class_school_id) {
            return true;
        }
    } elseif (in_array(HAM_ROLE_TEACHER, (array) $user->roles)) {
        // Teacher can access their own classes
        $teacher_class_ids = get_user_meta($user_id, HAM_USER_META_CLASS_IDS, true);

        if (is_array($teacher_class_ids) && in_array($class_id, $teacher_class_ids)) {
            return true;
        }
    } elseif (in_array(HAM_ROLE_STUDENT, (array) $user->roles)) {
        // Student can access their own classes
        $student_class_ids = get_user_meta($user_id, HAM_USER_META_CLASS_IDS, true);

        if (is_array($student_class_ids) && in_array($class_id, $student_class_ids)) {
            return true;
        }
    }

    return false;
}

/**
 * Check if a user can access a specific school's data.
 *
 * @param int $user_id   The user ID to check permissions for.
 * @param int $school_id The school ID to check access to.
 * @return bool True if user can access, false otherwise.
 */
function ham_can_access_school($user_id, $school_id)
{
    $user = get_user_by('id', $user_id);

    if (! $user) {
        return false;
    }

    // Administrator can access any school
    if (in_array('administrator', (array) $user->roles)) {
        return true;
    }

    // Check based on role
    if (in_array(HAM_ROLE_SCHOOL_HEAD, (array) $user->roles)) {
        // School head can access their managed schools
        $managed_school_ids = get_user_meta($user_id, HAM_USER_META_MANAGED_SCHOOL_IDS, true);

        if (is_array($managed_school_ids) && in_array($school_id, $managed_school_ids)) {
            return true;
        }
    } elseif (in_array(HAM_ROLE_PRINCIPAL, (array) $user->roles)) {
        // Principal can access their school
        $principal_school_id = get_user_meta($user_id, HAM_USER_META_SCHOOL_ID, true);

        if ($principal_school_id && $principal_school_id == $school_id) {
            return true;
        }
    } elseif (in_array(HAM_ROLE_TEACHER, (array) $user->roles) || in_array(HAM_ROLE_STUDENT, (array) $user->roles)) {
        // Teacher/Student can access their own school
        $user_school_id = get_user_meta($user_id, HAM_USER_META_SCHOOL_ID, true);

        if ($user_school_id && $user_school_id == $school_id) {
            return true;
        }
    }

    return false;
}

/**
 * Check if a user can manage users at a specific school.
 *
 * @param int $user_id   The user ID to check permissions for.
 * @param int $school_id The school ID to check management permissions for.
 * @return bool True if user can manage, false otherwise.
 */
function ham_can_manage_school_users($user_id, $school_id)
{
    $user = get_user_by('id', $user_id);

    if (! $user) {
        return false;
    }

    // Administrator can manage any school
    if (in_array('administrator', (array) $user->roles)) {
        return true;
    }

    // Check if user has the required capability
    if (! user_can($user_id, 'manage_school_users')) {
        return false;
    }

    // School head can manage their managed schools
    if (in_array(HAM_ROLE_SCHOOL_HEAD, (array) $user->roles)) {
        $managed_school_ids = get_user_meta($user_id, HAM_USER_META_MANAGED_SCHOOL_IDS, true);

        if (is_array($managed_school_ids) && in_array($school_id, $managed_school_ids)) {
            return true;
        }
    }

    // Principal can manage their school
    if (in_array(HAM_ROLE_PRINCIPAL, (array) $user->roles)) {
        $principal_school_id = get_user_meta($user_id, HAM_USER_META_SCHOOL_ID, true);

        if ($principal_school_id && $principal_school_id == $school_id) {
            return true;
        }
    }

    return false;
}

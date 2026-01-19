<?php

/**
 * File: inc/constants.php
 *
 * Defines constants used throughout the plugin.
 *
 * @package HeadlessAccessManager
 */

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    exit;
}

// API namespace and version
define('HAM_API_NAMESPACE', 'ham/v1');

// Custom post type slugs
define('HAM_CPT_SCHOOL', 'ham_school');
define('HAM_CPT_CLASS', 'ham_class');
define('HAM_CPT_ASSESSMENT', 'ham_assessment');
define('HAM_CPT_ASSESSMENT_TPL', 'ham_assessment_tpl');
define('HAM_CPT_TEACHER', 'ham_teacher');
define('HAM_CPT_STUDENT', 'ham_student');
define('HAM_CPT_PRINCIPAL', 'ham_principal');
define('HAM_CPT_SCHOOL_HEAD', 'ham_school_head');

// User roles
define('HAM_ROLE_STUDENT', 'ham_student');
define('HAM_ROLE_TEACHER', 'ham_teacher');
define('HAM_ROLE_PRINCIPAL', 'ham_principal');
define('HAM_ROLE_SCHOOL_HEAD', 'ham_school_head');

// User meta keys
define('HAM_USER_META_SCHOOL_ID', '_ham_school_id');
define('HAM_USER_META_SCHOOL_IDS', '_ham_school_ids');
define('HAM_USER_META_CLASS_IDS', '_ham_class_ids');
define('HAM_USER_META_MANAGED_SCHOOL_IDS', '_ham_managed_school_ids');

// Assessment meta keys
define('HAM_ASSESSMENT_META_STUDENT_ID', '_ham_student_id');
define('HAM_ASSESSMENT_META_DATA', '_ham_assessment_data');
define('HAM_ASSESSMENT_META_DATE', '_ham_assessment_date');

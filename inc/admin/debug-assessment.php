<?php
/**
 * Debug page for assessment meta values
 */

// Load WordPress
require_once dirname(__FILE__, 5) . '/wp-load.php';

// Environment + security check: only allow in development and for admins
if (function_exists('wp_get_environment_type')) {
    $ham_env = wp_get_environment_type();
} elseif (defined('WP_ENVIRONMENT_TYPE')) {
    $ham_env = WP_ENVIRONMENT_TYPE;
} else {
    $ham_env = (defined('WP_DEBUG') && WP_DEBUG) ? 'development' : 'production';
}

if ($ham_env !== 'development') {
    wp_die('This debug tool is only available in the development environment.');
}

// Security check
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized access');
}

// Get a recent assessment post
$assessments = get_posts([
    'post_type' => 'ham_assessment',
    'posts_per_page' => 5,
    'orderby' => 'date',
    'order' => 'DESC',
]);

echo '<h1>Assessment Meta Debug</h1>';

if (empty($assessments)) {
    echo '<p>No assessments found</p>';
    return;
}

foreach ($assessments as $assessment) {
    echo '<h2>Assessment #' . $assessment->ID . ' - ' . $assessment->post_title . '</h2>';
    
    // Get all meta
    $all_meta = get_post_meta($assessment->ID);
    
    echo '<h3>All Meta Keys</h3>';
    echo '<ul>';
    foreach ($all_meta as $key => $values) {
        echo '<li><strong>' . $key . '</strong>: ' . print_r($values[0], true) . '</li>';
    }
    echo '</ul>';
    
    // Check specific meta keys
    echo '<h3>Student ID Lookup</h3>';
    $student_id_1 = get_post_meta($assessment->ID, '_ham_student_id', true);
    $student_id_2 = get_post_meta($assessment->ID, 'student_id', true);
    
    echo '<ul>';
    echo '<li><strong>_ham_student_id</strong>: ' . $student_id_1 . '</li>';
    echo '<li><strong>student_id</strong>: ' . $student_id_2 . '</li>';
    echo '</ul>';
    
    // Try to get student name if any ID exists
    $student_id = $student_id_1 ?: $student_id_2 ?: null;
    if ($student_id) {
        $student = get_post($student_id);
        if ($student) {
            echo '<p>Found student: #' . $student->ID . ' - ' . $student->post_title . ' (post_type: ' . $student->post_type . ')</p>';
        } else {
            echo '<p>Student ID ' . $student_id . ' does not exist in the database</p>';
        }
    } else {
        echo '<p>No student ID found with any meta key</p>';
    }
    
    echo '<hr>';
}

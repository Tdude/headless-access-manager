<?php

/**
 * File: inc/core/class-ham-statistics-manager.php
 *
 * Handles fetching and processing of detailed statistics for students, teachers, and classes.
 *
 * @package HeadlessAccessManager
 */

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class HAM_Statistics_Manager
 *
 * Provides methods to get detailed assessment statistics.
 */
class HAM_Statistics_Manager
{
    /**
     * Extracts and calculates evaluation details from an assessment post object.
     *
     * @param WP_Post $post The assessment post object.
     * @return array An array containing the grade and comments.
     */
    private static function _extract_evaluation_details_from_post(WP_Post $post) {
        $assessment_data = get_post_meta($post->ID, HAM_ASSESSMENT_META_DATA, true);
        $values = [];

        if (is_array($assessment_data)) {
            foreach (['anknytning', 'ansvar'] as $section_key) {
                if (isset($assessment_data[$section_key]['questions']) && is_array($assessment_data[$section_key]['questions'])) {
                    foreach ($assessment_data[$section_key]['questions'] as $answer) {
                        if (is_numeric($answer)) {
                            $values[] = floatval($answer);
                        }
                    }
                }
            }
        }

        $average_score = count($values) ? array_sum($values) / count($values) : 0;
        $comments = isset($assessment_data['comments']) ? $assessment_data['comments'] : '';

        return [
            'grade'    => number_format($average_score, 2),
            'comments' => esc_textarea($comments),
        ];
    }

    /**
     * Get all evaluations for a specific student.
     *
     * @param int $student_post_id The ID of the student (post ID).
     * @return array An array of evaluation data.
     */
    public static function get_student_evaluations($student_post_id)
    {
        if (empty($student_post_id)) {
            return [];
        }

        $args = [
            'post_type'      => HAM_CPT_ASSESSMENT,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => HAM_ASSESSMENT_META_STUDENT_ID,
                    'value'   => $student_post_id,
                    'compare' => '=',
                ],
                [
                    'key'     => '_ham_assessment_source',
                    'value'   => 'frontend',
                    'compare' => '=',
                ],
            ],
        ];

        $evaluations = [];
        $posts = get_posts($args);

        foreach ($posts as $post) {
            $details = self::_extract_evaluation_details_from_post($post);
            $teacher = get_user_by('id', $post->post_author);

            $evaluations[] = [
                'id'           => $post->ID,
                'date'         => get_the_date('Y-m-d', $post->ID),
                'teacher_id'   => $post->post_author,
                'teacher_name' => $teacher ? $teacher->display_name : __('Unknown Teacher', 'headless-access-manager'),
                'grade'        => $details['grade'],
                'comments'     => $details['comments'],
            ];
        }

        return $evaluations;
    }

    /**
     * Get all evaluations performed by a specific teacher.
     *
     * @param int $teacher_id The ID of the teacher (user ID).
     * @return array An array of evaluation data.
     */
    public static function get_teacher_evaluations($teacher_id)
    {
        if (empty($teacher_id)) {
            return [];
        }

        $args = [
            'post_type'      => HAM_CPT_ASSESSMENT,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'author'         => $teacher_id,
            'meta_query'     => [
                ['key' => HAM_ASSESSMENT_META_STUDENT_ID, 'compare' => 'EXISTS'],
                ['key' => '_ham_assessment_source', 'value' => 'frontend', 'compare' => '='],
            ],
        ];

        $evaluations = [];
        $posts = get_posts($args);

        foreach ($posts as $post) {
            $details = self::_extract_evaluation_details_from_post($post);
            $student_id = get_post_meta($post->ID, HAM_ASSESSMENT_META_STUDENT_ID, true);
            $student_post = get_post($student_id);

            $evaluations[] = [
                'id'           => $post->ID,
                'date'         => get_the_date('Y-m-d', $post->ID),
                'student_id'   => $student_id,
                'student_name' => $student_post ? $student_post->post_title : __('Unknown Student', 'headless-access-manager'),
                'grade'        => $details['grade'],
                'comments'     => $details['comments'],
            ];
        }

        return $evaluations;
    }

    /**
     * Get all evaluations for a specific class with caching.
     *
     * @param int $class_id The ID of the class (post ID).
     * @return array An array of evaluation data.
     */
    public static function get_class_evaluations($class_id)
    {
        if (empty($class_id)) {
            return [];
        }

        $cache_key = "ham_class_evaluations_{$class_id}";
        $cached_evaluations = get_transient($cache_key);

        if (false !== $cached_evaluations) {
            return $cached_evaluations;
        }

        $student_ids = get_post_meta($class_id, '_ham_student_ids', true);
        if (empty($student_ids) || !is_array($student_ids)) {
            return [];
        }

        $args = [
            'post_type'      => HAM_CPT_ASSESSMENT,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => HAM_ASSESSMENT_META_STUDENT_ID, 'value' => $student_ids, 'compare' => 'IN'],
                ['key' => '_ham_assessment_source', 'value' => 'frontend', 'compare' => '='],
            ],
        ];

        $evaluations = [];
        $posts = get_posts($args);

        // Pre-fetch student and teacher data to reduce queries inside loop
        $student_ids_from_evals = wp_list_pluck($posts, HAM_ASSESSMENT_META_STUDENT_ID, 'ID');
        $teacher_ids = wp_list_pluck($posts, 'post_author');
        
        $students = get_posts(['post__in' => array_unique(array_values($student_ids_from_evals)), 'post_type' => HAM_CPT_STUDENT, 'posts_per_page' => -1]);
        $student_map = array_column($students, 'post_title', 'ID');

        $teachers = get_users(['include' => array_unique($teacher_ids)]);
        $teacher_map = array_column($teachers, 'display_name', 'ID');

        foreach ($posts as $post) {
            $details = self::_extract_evaluation_details_from_post($post);
            $student_id = get_post_meta($post->ID, HAM_ASSESSMENT_META_STUDENT_ID, true);

            $evaluations[] = [
                'id'           => $post->ID,
                'date'         => get_the_date('Y-m-d', $post->ID),
                'teacher_name' => isset($teacher_map[$post->post_author]) ? $teacher_map[$post->post_author] : __('Unknown Teacher', 'headless-access-manager'),
                'student_name' => isset($student_map[$student_id]) ? $student_map[$student_id] : __('Unknown Student', 'headless-access-manager'),
                'grade'        => $details['grade'],
                'comments'     => $details['comments'],
            ];
        }

        // Cache the results for 1 hour. 
        // Note: A robust implementation should invalidate this cache when a new evaluation is added/updated/deleted for this class.
        set_transient($cache_key, $evaluations, HOUR_IN_SECONDS);

        return $evaluations;
    }

    /**
     * Get the average score for an entire class, optimized with caching.
     *
     * @param int $class_id The ID of the class (post ID).
     * @return float The average score for the class.
     */
    public static function get_class_average_score($class_id)
    {
        if (empty($class_id)) {
            return 0.0;
        }

        $cache_key = "ham_class_avg_score_{$class_id}";
        $cached_score = get_transient($cache_key);

        if (false !== $cached_score) {
            return floatval($cached_score);
        }

        // Fetch all evaluations for the class using the optimized and cached method.
        $evaluations = self::get_class_evaluations($class_id);

        if (empty($evaluations)) {
            return 0.0;
        }

        $total_score = array_sum(wp_list_pluck($evaluations, 'grade'));
        $average_score = $total_score / count($evaluations);
        
        // Cache the result for 1 hour.
        set_transient($cache_key, $average_score, HOUR_IN_SECONDS);

        return $average_score;
    }
}

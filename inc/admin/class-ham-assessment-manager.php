<?php

/**
 * File: inc/admin/class-ham-assessment-manager.php
 *
 * Handles assessment management in the WordPress admin.
 *
 * @package HeadlessAccessManager
 */

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class HAM_Assessment_Manager
 *
 * Handles assessment management in the WordPress admin.
 */
class HAM_Assessment_Manager
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        // Fix metadata on existing assessments (one-time operation)
        add_action('admin_init', array($this, 'fix_assessment_metadata'));

        // Register AJAX handlers
        add_action('wp_ajax_ham_get_assessment_details', array($this, 'ajax_get_assessment_details'));
        add_action('wp_ajax_ham_get_assessment_stats', array($this, 'ajax_get_assessment_stats'));

        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Filter out frontend assessments from the default post type listing
        add_action('pre_get_posts', array($this, 'filter_assessment_admin_listing'));

        // Delete button functionality in the modal list for assessments
        add_action('wp_ajax_ham_delete_assessment', array($this, 'ajax_delete_assessment'));
    }

    /**
     * Fix metadata on existing assessments.
     * This ensures all frontend submissions have the correct source flag.
     */
    public function fix_assessment_metadata()
    {
        // Only run for administrators to avoid unnecessary DB operations
        if (!current_user_can('manage_options')) {
            return;
        }

        // Force re-run if explicitly requested via URL parameter
        $force_run = isset($_GET['fix_assessments']) && $_GET['fix_assessments'] === '1';

        // Check if we've already run this fix
        $fix_run = get_option('ham_assessment_metadata_fixed', false);
        if ($fix_run && !$force_run) {
            return;
        }

        global $wpdb;

        // Find ALL assessments, regardless of metadata
        $assessments = get_posts(array(
            'post_type' => HAM_CPT_ASSESSMENT,
            'posts_per_page' => -1,
            'post_status' => 'any',
            'meta_query' => array(
                array(
                    'key' => HAM_ASSESSMENT_META_STUDENT_ID,
                    'value' => '',
                    'compare' => '!=',
                ),
            ),
        ));

        $count = 0;

        // For each assessment, determine if it's frontend or admin
        foreach ($assessments as $assessment) {
            $is_frontend = false;

            // Strong indicators of frontend submission
            $frontend_indicators = array(
                // Check if created via API
                get_post_meta($assessment->ID, '_ham_api_created', true),
                // Check for zero author (common in API submissions)
                $assessment->post_author == 0,
                // Check for REST API creation
                strpos($assessment->post_content, 'rest-api') !== false,
                // Check submission metadata
                get_post_meta($assessment->ID, '_ham_frontend_submission', true)
            );

            // If any indicator is true, mark as frontend
            foreach ($frontend_indicators as $indicator) {
                if ($indicator) {
                    $is_frontend = true;
                    break;
                }
            }

            // Set the source metadata
            update_post_meta($assessment->ID, '_ham_assessment_source', $is_frontend ? 'frontend' : 'admin');
            $count++;
        }

        // Mark as completed to avoid running again
        update_option('ham_assessment_metadata_fixed', true);

        if ($count > 0) {
            // Add an admin notice that metadata was fixed
            add_action('admin_notices', function () use ($count) {
                echo '<div class="notice notice-success is-dismissible"><p>' .
                    sprintf(__('Updated source metadata for %d assessments.', 'headless-access-manager'), $count) .
                    '</p></div>';
            });
        }

        // If force run was triggered via URL, redirect back to assessment list
        if ($force_run) {
            // Remove the parameter to avoid looping
            wp_redirect(admin_url('edit.php?post_type=' . HAM_CPT_ASSESSMENT . '&metadata_fixed=' . $count));
            exit;
        }
    }

    /**
     * Render assessments list page.
     */
    public static function render_assessments_page()
    {
        // Get current screen to determine which source filter to use
        $screen = get_current_screen();
        $source = 'all'; // Show all assessments in the custom admin page

        // Check if explicitly requesting admin source only
        if (isset($_GET['source']) && $_GET['source'] === 'admin' && current_user_can('manage_options')) {
            $source = 'admin';
        }

        // Get assessments with the appropriate filter
        $assessments = self::get_assessments($source);

        // Double-check: filter out any assessments that might still be from frontend
        if ($source === 'admin') {
            foreach ($assessments as $key => $assessment) {
                // Check author (frontend often uses author 0)
                if ($assessment['author_id'] == 0) {
                    unset($assessments[$key]);
                    continue;
                }

                // Check source metadata
                if (isset($assessment['meta']['_ham_assessment_source']) && $assessment['meta']['_ham_assessment_source'] === 'frontend') {
                    unset($assessments[$key]);
                    continue;
                }

                // Check API created flag
                if (isset($assessment['meta']['_ham_api_created']) && $assessment['meta']['_ham_api_created']) {
                    unset($assessments[$key]);
                    continue;
                }
            }

            // Reset array keys
            $assessments = array_values($assessments);
        }

        // Include the template
        include HAM_PLUGIN_DIR . 'templates/admin/assessments-list.php';
    }

    /**
     * Render assessment statistics page.
     */
    public static function render_statistics_page()
    {
        // For statistics, we likely want all assessments to provide complete stats
        $source = 'all';

        // Get statistics data (pass the source filter)
        $stats = self::get_assessment_statistics($source);

        // Include the template
        include HAM_PLUGIN_DIR . 'templates/admin/assessment-statistics.php';
    }

    /**
     * Helper function to resolve teacher name and ID from student ID and/or post author
     * This ensures consistent teacher resolution across list and modal views.
     *
     * @param int $student_id The student CPT ID
     * @param int $post_author The post author ID (fallback)
     * @return array Array with teacher_id and teacher_name keys
     */
    public static function resolve_teacher($student_id, $post_author = 0) {
        $teacher_name = esc_html__('Unknown Teacher', 'headless-access-manager');
        $teacher_id = $post_author; // Default to post_author for backward compatibility
        
        // Find all classes the student is part of
        $student_class_ids = array();
        $class_args = array(
            'post_type' => HAM_CPT_CLASS,
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_ham_student_ids',
                    // Search for the student ID as an integer element in the serialized array
                    'value' => 'i:' . $student_id . ';',
                    'compare' => 'LIKE'
                )
            )
        );
        
        $class_query = new WP_Query($class_args);
        
        if ($class_query->have_posts()) {
            while ($class_query->have_posts()) {
                $class_query->the_post();
                $student_class_ids[] = get_the_ID();
            }
            wp_reset_postdata();
        }
        
        // If student is in classes, find teachers assigned to those classes
        if (!empty($student_class_ids)) {
            $teacher_args = array(
                'post_type' => HAM_CPT_TEACHER,
                'posts_per_page' => 1, // Just get the first matching teacher
                'meta_query' => array()
            );
            
            // Build the meta query to find teachers in the same classes as the student
            $meta_query_clauses = array('relation' => 'OR');
            foreach ($student_class_ids as $class_id) {
                $meta_query_clauses[] = array(
                    'key' => '_ham_class_ids',
                    'value' => 'i:' . $class_id . ';',
                    'compare' => 'LIKE'
                );
            }
            $teacher_args['meta_query'] = $meta_query_clauses;
            
            $teacher_query = new WP_Query($teacher_args);
            
            if ($teacher_query->have_posts()) {
                $teacher_query->the_post();
                $teacher_cpt_id = get_the_ID();
                $teacher_name = get_the_title();
                
                // Get the WP user ID associated with this teacher CPT
                $wp_user_id = get_post_meta($teacher_cpt_id, '_ham_user_id', true);
                if (!empty($wp_user_id)) {
                    $teacher_id = $wp_user_id;
                }
                
                wp_reset_postdata();
            }
        }
        
        // Fallback: If no teacher found via class relationship, use post_author
        if ($teacher_name === esc_html__('Unknown Teacher', 'headless-access-manager') && !empty($post_author)) {
            $teacher_name = get_the_author_meta('display_name', $post_author);
            
            // Additional fallback: If author name is empty, try to find teacher CPT by user ID
            if (empty($teacher_name)) {
                $teacher_cpt_args = array(
                    'post_type' => HAM_CPT_TEACHER,
                    'posts_per_page' => 1,
                    'meta_query' => array(
                        array(
                            'key' => '_ham_user_id',
                            'value' => $post_author,
                            'compare' => '='
                        )
                    )
                );
                
                $teacher_cpt_query = new WP_Query($teacher_cpt_args);
                if ($teacher_cpt_query->have_posts()) {
                    $teacher_cpt_query->the_post();
                    $teacher_name = get_the_title();
                    wp_reset_postdata();
                }
            }
        }
        
        return array(
            'teacher_id' => $teacher_id,
            'teacher_name' => $teacher_name
        );
    }
    
    /**
     * Get all assessments with student information.
     *
     * @param string $source Optional. Filter by assessment source ('admin', 'frontend', or 'all'). Default 'all'.
     * @return array Array of assessment data.
     */

    public static function get_assessments($source = 'all')
    {
        $args = array(
            'post_type'      => HAM_CPT_ASSESSMENT,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
            // Only get posts that have a student ID meta value - these are actual assessments
            'meta_query'     => array(
                array(
                    'key'     => HAM_ASSESSMENT_META_STUDENT_ID,
                    'value'   => '',
                    'compare' => '!=',
                ),
            ),
        );

        // Add source filter if specified
        if ($source !== 'all') {
            // Add a meta query to check for the source flag
            if ($source === 'admin') {
                // Admin templates either have the admin flag or don't have any source flag
                $args['meta_query'][] = array(
                    'relation' => 'OR',
                    array(
                        'key'     => '_ham_assessment_source',
                        'value'   => 'admin',
                        'compare' => '=',
                    ),
                    array(
                        'key'     => '_ham_assessment_source',
                        'compare' => 'NOT EXISTS',
                    ),
                );
            } elseif ($source === 'frontend') {
                // Frontend submissions have the frontend source flag
                $args['meta_query'][] = array(
                    'key'     => '_ham_assessment_source',
                    'value'   => 'frontend',
                    'compare' => '=',
                );
            }
        }

        // Debug: Log the query arguments
        //error_log('Assessment query args: ' . print_r($args, true));

        $assessments = array();
        $posts = get_posts($args);

        // Debug: Log the number of posts found
        //error_log('Number of assessment posts found: ' . count($posts));

        foreach ($posts as $post) {
            $student_id = get_post_meta($post->ID, HAM_ASSESSMENT_META_STUDENT_ID, true);

            // Debug: Log the post and student ID
            //error_log('Processing post ID: ' . $post->ID . ', Student ID: ' . $student_id);

            // Skip posts without a student ID (likely templates)
            if (empty($student_id)) {
                //error_log('Skipping post ' . $post->ID . ' - no student ID');
                continue;
            }
            
            // Student is a CPT, not a WordPress user - get the student name from the CPT
            $student_post = get_post($student_id);
            $student_name = $student_post ? $student_post->post_title : esc_html__('Unknown Student', 'headless-access-manager');
            
            // Debug information about the student CPT
            //error_log('Student CPT found: ' . ($student_post ? 'Yes' : 'No') . ', Name: ' . $student_name);

            $assessment_data = get_post_meta($post->ID, HAM_ASSESSMENT_META_DATA, true);

            // Debug: Log the assessment data structure
            //error_log('Assessment data structure for post ' . $post->ID . ': ' . (is_array($assessment_data) ? json_encode(array_keys($assessment_data)) : 'not an array'));

            // Fine-grained numeric evaluation logic
            $values = array();
            if (is_array($assessment_data)) {
                foreach (array('anknytning', 'ansvar') as $section_key) {
                    if (isset($assessment_data[$section_key]['questions']) && is_array($assessment_data[$section_key]['questions'])) {
                        foreach ($assessment_data[$section_key]['questions'] as $question_id => $answer) {
                            if ($answer !== '' && $answer !== null) {
                                $numeric = floatval($answer);
                                // Normalize 1-5 scale to 0-1
                                $normalized = ($numeric - 1) / 4;
                                $values[] = $normalized;
                            }
                        }
                    }
                }
            }
            $average = count($values) ? array_sum($values) / count($values) : 0;
            $summary_stage = self::map_average_to_stage($average);

            $completion_percentage = self::calculate_completion_percentage($assessment_data);

            // Get teacher info - first check based on class relationship, fallback to post_author
            $teacher_name = esc_html__('Unknown Teacher', 'headless-access-manager');
            $teacher_id = $post->post_author; // Default to post_author for backward compatibility
            
            // Find all classes the student is part of
            $student_classes = array();
            $class_args = array(
                'post_type' => HAM_CPT_CLASS,
                'posts_per_page' => -1,
                'meta_query' => array(
                    array(
                        'key' => '_ham_student_ids',
                        // Search for the student ID as an integer element in the serialized array
                        'value' => 'i:' . $student_id . ';',
                        'compare' => 'LIKE'
                    )
                )
            );
            
            $class_query = new WP_Query($class_args);
            $student_class_ids = array();
            
            if ($class_query->have_posts()) {
                while ($class_query->have_posts()) {
                    $class_query->the_post();
                    $student_class_ids[] = get_the_ID();
                }
                wp_reset_postdata();
            }
            
            // If student is in classes, find teachers assigned to those classes
            if (!empty($student_class_ids)) {
                $teacher_args = array(
                    'post_type' => HAM_CPT_TEACHER,
                    'posts_per_page' => 1, // Just get the first matching teacher
                    'meta_query' => array()
                );
                
                // Build the meta query to find teachers in the same classes as the student
                $meta_query_clauses = array('relation' => 'OR');
                foreach ($student_class_ids as $class_id) {
                    $meta_query_clauses[] = array(
                        'key' => '_ham_class_ids',
                        'value' => 'i:' . $class_id . ';',
                        'compare' => 'LIKE'
                    );
                }
                $teacher_args['meta_query'] = $meta_query_clauses;
                
                $teacher_query = new WP_Query($teacher_args);
                
                if ($teacher_query->have_posts()) {
                    $teacher_query->the_post();
                    $teacher_cpt_id = get_the_ID();
                    $teacher_name = get_the_title();
                    
                    // Get the WP user ID associated with this teacher CPT
                    $wp_user_id = get_post_meta($teacher_cpt_id, '_ham_user_id', true);
                    if (!empty($wp_user_id)) {
                        $teacher_id = $wp_user_id;
                    }
                    
                    wp_reset_postdata();
                }
            }
            
            // Fallback: If no teacher found via class relationship, use post_author
            if ($teacher_name === esc_html__('Unknown Teacher', 'headless-access-manager') && !empty($post->post_author)) {
                $teacher_name = get_the_author_meta('display_name', $post->post_author);
                
                // Additional fallback: If author name is empty, try to find teacher CPT by user ID
                if (empty($teacher_name)) {
                    $teacher_cpt_args = array(
                        'post_type' => HAM_CPT_TEACHER,
                        'posts_per_page' => 1,
                        'meta_query' => array(
                            array(
                                'key' => '_ham_user_id',
                                'value' => $post->post_author,
                                'compare' => '='
                            )
                        )
                    );
                    
                    $teacher_cpt_query = new WP_Query($teacher_cpt_args);
                    if ($teacher_cpt_query->have_posts()) {
                        $teacher_cpt_query->the_post();
                        $teacher_name = get_the_title();
                        wp_reset_postdata();
                    }
                }
            }
            
            $assessments[] = array(
                'id'           => $post->ID,
                'title'        => $post->post_title,
                'date'         => get_the_date('Y-m-d H:i:s', $post->ID),
                'student_id'   => $student_id,
                'student_name' => $student_name,
                'completion'   => $completion_percentage,
                'author_id'    => $teacher_id,
                'author_name'  => $teacher_name,
                'stage'        => $summary_stage,
                'stage_score'  => $average,
            );
        }

        // Debug: Log the final assessments array
        //error_log('Final assessments count: ' . count($assessments));

        return $assessments;
    }

    /**
     * Calculate completion percentage of an assessment.
     *
     * @param array $assessment_data The assessment data.
     * @return int Completion percentage.
     */
    private static function calculate_completion_percentage($assessment_data)
    {
        if (empty($assessment_data) || !is_array($assessment_data)) {
            return 0;
        }

        $total_questions = 0;
        $answered_questions = 0;

        // Count questions in each section
        foreach (array('anknytning', 'ansvar') as $section_key) {
            if (isset($assessment_data[$section_key]['questions']) && is_array($assessment_data[$section_key]['questions'])) {
                foreach ($assessment_data[$section_key]['questions'] as $question_id => $answer) {
                    $total_questions++;
                    if (!empty($answer)) {
                        $answered_questions++;
                    }
                }
            }
        }

        if ($total_questions === 0) {
            return 0;
        }

        return round(($answered_questions / $total_questions) * 100);
    }

    /**
     * Get assessment statistics.
     *
     * @param string $source Optional. Filter by assessment source ('admin', 'frontend', or 'all'). Default 'all'.
     * @return array Statistics data.
     */
    public static function get_assessment_statistics($source = 'all')
    {
        $assessments = self::get_assessments($source);

        // Initialize statistics data
        $stats = array(
            'total_assessments' => count($assessments),
            'total_students' => 0,
            'average_completion' => 0,
            'section_averages' => array(
                'anknytning' => 0,
                'ansvar' => 0
            ),
            'question_averages' => array(),
            'stage_distribution' => array(
                'ej' => 0,
                'trans' => 0,
                'full' => 0
            ),
            'monthly_submissions' => array()
        );

        if (empty($assessments)) {
            return $stats;
        }

        // Process each assessment
        $student_ids = array();
        $completion_sum = 0;
        $section_sums = array('anknytning' => 0, 'ansvar' => 0);
        $section_counts = array('anknytning' => 0, 'ansvar' => 0);
        $question_sums = array();
        $question_counts = array();
        $stage_counts = array('ej' => 0, 'trans' => 0, 'full' => 0);
        $monthly_data = array();

        foreach ($assessments as $assessment) {
            // Track unique students
            if (!in_array($assessment['student_id'], $student_ids)) {
                $student_ids[] = $assessment['student_id'];
            }

            // Sum completion percentages
            $completion_sum += $assessment['completion'];

            // Get full assessment data
            $assessment_data = get_post_meta($assessment['id'], HAM_ASSESSMENT_META_DATA, true);

            if (!empty($assessment_data) && is_array($assessment_data)) {
                // Process section data
                foreach (array('anknytning', 'ansvar') as $section_key) {
                    if (isset($assessment_data[$section_key]['questions']) && is_array($assessment_data[$section_key]['questions'])) {
                        foreach ($assessment_data[$section_key]['questions'] as $question_id => $answer) {
                            if (!empty($answer)) {
                                // Track section averages
                                $section_sums[$section_key] += intval($answer);
                                $section_counts[$section_key]++;

                                // Track question averages
                                $question_key = $section_key . '_' . $question_id;
                                if (!isset($question_sums[$question_key])) {
                                    $question_sums[$question_key] = 0;
                                    $question_counts[$question_key] = 0;
                                }
                                $question_sums[$question_key] += intval($answer);
                                $question_counts[$question_key]++;

                                // Get stage information
                                $stage = self::get_answer_stage($section_key, $question_id, $answer);
                                if ($stage) {
                                    $stage_counts[$stage]++;
                                }
                            }
                        }
                    }
                }
            }

            // Track monthly submissions
            $month = date('Y-m', strtotime($assessment['date']));
            if (!isset($monthly_data[$month])) {
                $monthly_data[$month] = 0;
            }
            $monthly_data[$month]++;
        }

        // Calculate final statistics
        $stats['total_students'] = count($student_ids);
        $stats['average_completion'] = $stats['total_assessments'] > 0 ? round($completion_sum / $stats['total_assessments']) : 0;

        // Calculate section averages
        foreach ($section_sums as $section => $sum) {
            $stats['section_averages'][$section] = $section_counts[$section] > 0 ? round($sum / $section_counts[$section], 1) : 0;
        }

        // Calculate question averages
        foreach ($question_sums as $question => $sum) {
            $stats['question_averages'][$question] = $question_counts[$question] > 0 ? round($sum / $question_counts[$question], 1) : 0;
        }

        // Calculate stage distribution percentages
        $total_answers = array_sum($stage_counts);
        if ($total_answers > 0) {
            foreach ($stage_counts as $stage => $count) {
                $stats['stage_distribution'][$stage] = round(($count / $total_answers) * 100);
            }
        }

        // Sort and format monthly data
        ksort($monthly_data);
        foreach ($monthly_data as $month => $count) {
            $stats['monthly_submissions'][] = array(
                'month' => $month,
                'count' => $count
            );
        }

        return $stats;
    }

    /**
     * Get the stage for a specific answer.
     *
     * @param string $section_key Section key.
     * @param string $question_id Question ID.
     * @param string $answer Answer value.
     * @return string|null Stage value or null if not found.
     */
    private static function get_answer_stage($section_key, $question_id, $answer)
    {
        // Get the questions structure
        $structure = self::get_questions_structure();

        if (empty($structure) || !isset($structure[$section_key]['questions'][$question_id]['options'])) {
            return null;
        }

        $options = $structure[$section_key]['questions'][$question_id]['options'];

        foreach ($options as $option) {
            if ($option['value'] === $answer && isset($option['stage'])) {
                return $option['stage'];
            }
        }

        return null;
    }

    /**
     * Get the questions structure from the most recent assessment.
     *
     * @return array Questions structure.
     */
    private static function get_questions_structure()
    {
        // Define default options for questions
        $default_options = array(
            array('value' => '1', 'label' => 'Inte alls', 'stage' => 'ej'),
            array('value' => '2', 'label' => 'Sällan', 'stage' => 'ej'),
            array('value' => '3', 'label' => 'Ibland', 'stage' => 'trans'),
            array('value' => '4', 'label' => 'Ofta', 'stage' => 'trans'),
            array('value' => '5', 'label' => 'Alltid', 'stage' => 'full')
        );

        // Hardcoded question texts - normalize all keys to lowercase for consistency
        $question_texts = array(
            'anknytning' => array(
                'a1' => 'Närvaro',
                'a2' => 'Dialog 1',
                'a3' => 'Dialog 2',
                'a4' => 'Kontakt',
                'a5' => 'Samarbete',
                'a6' => 'Vid konflikt', // Updated to match frontend
                'a7' => 'Engagemang'
            ),
            'ansvar' => array(
                'b1' => 'Uppgift',
                'b2' => 'Initiativ',
                'b3' => 'Material',
                'b4' => 'Tid',
                'b5' => 'Regler',
                'b6' => 'Vid konflikt',
                'b7' => 'Ansvar'
            )
        );

        // Get the most recent assessment that has question data
        $assessments = get_posts(array(
            'post_type'      => HAM_CPT_ASSESSMENT,
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => array(
                array(
                    'key'     => HAM_ASSESSMENT_META_DATA,
                    'compare' => 'EXISTS',
                )
            )
        ));

        if (empty($assessments)) {
            error_log('No assessments found with question data');
            return self::get_default_questions_structure();
        }

        $assessment_data = get_post_meta($assessments[0]->ID, HAM_ASSESSMENT_META_DATA, true);

        if (empty($assessment_data) || !is_array($assessment_data)) {
            error_log('Assessment data is empty or not an array');
            return self::get_default_questions_structure();
        }

        // Return only the questions part of the structure
        $structure = array();

        // Process each section
        foreach (array('anknytning', 'ansvar') as $section) {
            if (!isset($assessment_data[$section]) || !is_array($assessment_data[$section])) {
                $structure[$section] = array(
                    'title' => ucfirst($section),
                    'questions' => array()
                );
                continue;
            }

            $section_data = $assessment_data[$section];
            $section_structure = array(
                'title' => isset($section_data['title']) ? $section_data['title'] : ucfirst($section),
                'questions' => array()
            );

            // Process questions
            if (isset($section_data['questions']) && is_array($section_data['questions'])) {
                foreach ($section_data['questions'] as $question_id => $question_data) {
                    // Normalize question ID to lowercase
                    $question_id = strtolower($question_id);

                    // If question_data is an array and has a text property, use it
                    if (is_array($question_data) && isset($question_data['text'])) {
                        $section_structure['questions'][$question_id] = $question_data;
                    }
                    // Otherwise, create a structure with the question text
                    else {
                        $section_structure['questions'][$question_id] = array(
                            'text' => isset($question_texts[$section][$question_id]) ?
                                    $question_texts[$section][$question_id] :
                                    ucfirst($question_id)
                        );
                    }

                    // Ensure options are set for each question
                    if (!isset($section_structure['questions'][$question_id]['options']) || !is_array($section_structure['questions'][$question_id]['options']) || empty($section_structure['questions'][$question_id]['options'])) {
                        $section_structure['questions'][$question_id]['options'] = $default_options;
                    }
                }
            }

            $structure[$section] = $section_structure;
        }

        return $structure;
    }

    /**
     * Get default questions structure when no data is available.
     *
     * @return array Default questions structure.
     */
    private static function get_default_questions_structure()
    {
        return array(
            'anknytning' => array(
                'title' => 'Anknytningstecken',
                'questions' => array()
            ),
            'ansvar' => array(
                'title' => 'Ansvarstecken',
                'questions' => array()
            )
        );
    }



/**
 * Debug function to dump data to a file.
 *
 * @param mixed $data The data to dump.
 * @param string $prefix A prefix for the log file.
 */
private function debug_dump($data, $prefix = 'debug')
{
    $file = HAM_PLUGIN_DIR . 'logs/' . $prefix . '-' . date('Y-m-d-H-i-s') . '.log';

    // Create logs directory if it doesn't exist
    if (!file_exists(HAM_PLUGIN_DIR . 'logs/')) {
        mkdir(HAM_PLUGIN_DIR . 'logs/', 0755, true);
    }
    
    file_put_contents($file, print_r($data, true));
}


/**
 * AJAX handler for getting assessment details.
 */
public function ajax_get_assessment_details()
{
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ham_assessment_nonce')) {
        wp_send_json_error('Invalid nonce');
        return;
    }

    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }

    // Check assessment ID
    if (!isset($_POST['assessment_id']) || empty($_POST['assessment_id'])) {
        wp_send_json_error('Missing assessment ID');
        return;
    }

    $assessment_id = intval($_POST['assessment_id']);

    // Debug information
    //error_log('Processing assessment details request for ID: ' . $assessment_id);

    // Get assessment data
    $assessment = get_post($assessment_id);

    // Get student ID from assessment meta
    $student_id = get_post_meta($assessment_id, HAM_ASSESSMENT_META_STUDENT_ID, true);

    // Debug information
    //error_log("MODAL - All meta for assessment {$assessment_id}: " . print_r(get_post_meta($assessment_id), true));

    if (!empty($student_id)) {
        $student_post = get_post($student_id);
        if ($student_post) {
            $student_name = $student_post->post_title;
            //error_log("MODAL - Found student post: {$student_post->ID}, post_type: {$student_post->post_type}, title: {$student_post->post_title}");
        } else {
            //error_log("MODAL - Could not find student post with ID: {$student_id}");
            
            // Debug - try again with a different approach
            // Students are custom post types, not WordPress users
            // Check both potential meta keys for student ID
            $student_id = get_post_meta($post->ID, HAM_ASSESSMENT_META_STUDENT_ID, true);
            $alt_student_id = get_post_meta($post->ID, 'student_id', true);
            
            // Use whichever meta key has a value
            if (empty($student_id) && !empty($alt_student_id)) {
                $student_id = $alt_student_id;
            }
            
            $student_name = esc_html__("Unknown Student", "headless-access-manager");
            
            if (!empty($student_id)) {
                $student_post = get_post($student_id);
                $student_post = $wpdb->get_row(
                    $wpdb->prepare("SELECT ID, post_title, post_type FROM {$wpdb->posts} WHERE ID = %d", $student_id)
                );
                if ($student_post) {
                    error_log("MODAL - Direct DB query found: ID: {$student_post->ID}, title: {$student_post->post_title}, type: {$student_post->post_type}");
                } else {
                    error_log("MODAL - Direct DB query found nothing for ID: {$student_id}");
                }
            } else {
                error_log("MODAL - No student ID found for assessment: {$assessment_id}");
            }
        }
    } else {
        error_log("MODAL - No student ID found for assessment: {$assessment_id}");
    }
        
        // Debug
        //error_log('Student ID: ' . $student_id . ', Student name resolved: ' . $student_name);

        $assessment_data = get_post_meta($assessment_id, HAM_ASSESSMENT_META_DATA, true);

        // Get the source of this assessment (admin, frontend, or undefined)
        $source = get_post_meta($assessment_id, '_ham_assessment_source', true);
        if (empty($source)) {
            // If no source is set, assume it's an admin assessment for backward compatibility
            $source = 'admin';
        }

        // Dump raw data for debugging
        $this->debug_dump($assessment_data, 'raw-assessment-data-' . $assessment_id);

        // Process the assessment data to ensure it's in the right format
        $processed_assessment_data = $this->process_assessment_data($assessment_data);

        // Log processed data in a more readable way
        //error_log('PROCESSED ASSESSMENT DATA STRUCTURE:');
        /*
        if (!empty($processed_assessment_data['anknytning']['questions'])) {
            foreach ($processed_assessment_data['anknytning']['questions'] as $qkey => $qdata) {
                //error_log("Question key: $qkey");
                if (!empty($qdata) && is_array($qdata)) {
                    if (isset($qdata['text'])) {
                        error_log(" - text: {$qdata['text']}");
                    }

                    if (isset($qdata['answer'])) {
                        error_log(" - answer: {$qdata['answer']}");
                    }

                    if (isset($qdata['options']) && is_array($qdata['options'])) {
                        error_log(" - has options: " . count($qdata['options']));
                        foreach ($qdata['options'] as $i => $opt) {
                            if (is_array($opt)) {
                                error_log("   - option[$i]: value={$opt['value']}, label={$opt['label']}, stage={$opt['stage']}");
                            }
                        }
                    }
                }
            }
        } else {
            error_log("No processed anknytning questions found!");
        }
        */

        // Get the questions structure - create a proper structure with question text and options
        // Do not rely on get_questions_structure() as it doesn't contain the proper structure
        $questions_structure = array();

        // Define the default options
        $default_options = array(
            array('value' => '1', 'label' => 'Inte alls', 'stage' => 'ej'),
            array('value' => '2', 'label' => 'Sällan', 'stage' => 'ej'),
            array('value' => '3', 'label' => 'Ibland', 'stage' => 'trans'),
            array('value' => '4', 'label' => 'Ofta', 'stage' => 'trans'),
            array('value' => '5', 'label' => 'Alltid', 'stage' => 'full')
        );

        // Define default question texts - these should match the frontend
        $question_texts = array(
            'anknytning' => array(
                'a1' => 'Närvaro',
                'a2' => 'Dialog 1',
                'a3' => 'Dialog 2',
                'a4' => 'Kontakt',
                'a5' => 'Samarbete',
                'a6' => 'Vid konflikt',
                'a7' => 'Engagemang'
            ),
            'ansvar' => array(
                'b1' => 'Uppgift',
                'b2' => 'Initiativ',
                'b3' => 'Material',
                'b4' => 'Tid',
                'b5' => 'Regler',
                'b6' => 'Vid konflikt',
                'b7' => 'Ansvar'
            )
        );

        // Build a proper structure for each section, ensuring correct order from the predefined list
        foreach ($question_texts as $section => $section_questions) {
            $questions = array();

            // Iterate over the DEFINED questions in order to maintain the correct sequence
            foreach ($section_questions as $qkey => $qtext) {
                // Normalize key just in case, though it should be consistent
                $qkey = strtolower($qkey);

                // Add question with proper text and options
                $questions[$qkey] = array(
                    'text'    => $qtext,
                    'options' => $default_options,
                );
            }

            $questions_structure[$section] = array(
                'title'     => $section == 'anknytning' ? 'Anknytningstecken' : 'Ansvarstecken',
                'questions' => $questions,
            );
        }

        // Dump processed data for debugging
        $this->debug_dump($processed_assessment_data, 'processed-assessment-data-' . $assessment_id);
        $this->debug_dump($questions_structure, 'questions-structure-' . $assessment_id);

        // Debug information
        //error_log('Raw assessment data: ' . print_r($assessment_data, true));
        //error_log('Processed assessment data: ' . print_r($processed_assessment_data, true));

        // Get teacher info - first check based on class relationship, fallback to post_author
        $teacher_name = esc_html__('Unknown Teacher', 'headless-access-manager');
        $teacher_id = $assessment->post_author; // Default to post_author for backward compatibility
        
        // Find all classes the student is part of
        $student_classes = array();
        $class_args = array(
            'post_type' => HAM_CPT_CLASS,
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_ham_student_ids',
                    // Search for the student ID as an integer element in the serialized array
                    'value' => 'i:' . $student_id . ';',
                    'compare' => 'LIKE'
                )
            )
        );
        
        $class_query = new WP_Query($class_args);
        $student_class_ids = array();
        
        if ($class_query->have_posts()) {
            while ($class_query->have_posts()) {
                $class_query->the_post();
                $student_class_ids[] = get_the_ID();
            }
            wp_reset_postdata();
        }
        
        // If student is in classes, find teachers assigned to those classes
        if (!empty($student_class_ids)) {
            $teacher_args = array(
                'post_type' => HAM_CPT_TEACHER,
                'posts_per_page' => 1, // Just get the first matching teacher
                'meta_query' => array()
            );
            
            // Build the meta query to find teachers in the same classes as the student
            $meta_query_clauses = array('relation' => 'OR');
            foreach ($student_class_ids as $class_id) {
                $meta_query_clauses[] = array(
                    'key' => '_ham_class_ids',
                    'value' => 'i:' . $class_id . ';',
                    'compare' => 'LIKE'
                );
            }
            $teacher_args['meta_query'] = $meta_query_clauses;
            
            $teacher_query = new WP_Query($teacher_args);
            
            if ($teacher_query->have_posts()) {
                $teacher_query->the_post();
                $teacher_cpt_id = get_the_ID();
                $teacher_name = get_the_title();
                
                // Get the WP user ID associated with this teacher CPT
                $wp_user_id = get_post_meta($teacher_cpt_id, '_ham_user_id', true);
                if (!empty($wp_user_id)) {
                    $teacher_id = $wp_user_id;
                }
                
                wp_reset_postdata();
            }
        }
        
        // Fallback: If no teacher found via class relationship, use post_author
        if ($teacher_name === esc_html__('Unknown Teacher', 'headless-access-manager') && !empty($assessment->post_author)) {
            $teacher_name = get_the_author_meta('display_name', $assessment->post_author);
            
            // Additional fallback: If author name is empty, try to find teacher CPT by user ID
            if (empty($teacher_name)) {
                $teacher_cpt_args = array(
                    'post_type' => HAM_CPT_TEACHER,
                    'posts_per_page' => 1,
                    'meta_query' => array(
                        array(
                            'key' => '_ham_user_id',
                            'value' => $assessment->post_author,
                            'compare' => '='
                        )
                    )
                );
                
                $teacher_cpt_query = new WP_Query($teacher_cpt_args);
                if ($teacher_cpt_query->have_posts()) {
                    $teacher_cpt_query->the_post();
                    $teacher_name = get_the_title();
                    wp_reset_postdata();
                }
            }
        }
        
        //error_log('Modal display - Teacher resolution: Student ID: ' . $student_id . ', Classes: ' . implode(',', $student_class_ids) . ', Teacher name: ' . $teacher_name);
        
        $response = array(
            'id' => $assessment_id,
            'title' => $assessment->post_title,
            'date' => get_the_date('Y-m-d H:i:s', $assessment_id),
            'student_id' => $student_id,
            'student_name' => $student_name,
            'author_id' => $teacher_id,
            'author_name' => $teacher_name,
            'assessment_data' => $processed_assessment_data,
            'questions_structure' => $questions_structure,
            'source' => $source // Include the source in the response for reference
        );

        wp_send_json_success($response);
    }

    /**
     * Process assessment data to ensure it's in the right format for display.
     *
     * @param array $data Raw assessment data.
     * @return array Processed assessment data.
     */
    private function process_assessment_data($data)
    {
        if (!is_array($data)) {
            return array();
        }

        //error_log('Raw data structure: ' . json_encode($data));
        $this->debug_dump($data, 'raw-data-structure');

        $processed_data = array();

        // Process each section
        foreach (array('anknytning', 'ansvar') as $section) {
            if (!isset($data[$section]) || !is_array($data[$section])) {
                $processed_data[$section] = array(
                    'questions' => array(),
                    'comments' => ''
                );
                continue;
            }

            $section_data = $data[$section];
            $processed_section = array(
                'questions' => array(),
                'comments' => isset($section_data['comments']) ? $section_data['comments'] : ''
            );

            //error_log('Section ' . $section . ' data: ' . json_encode($section_data));

            // Get questions structure for reference
            $questions_structure = self::get_questions_structure();
            $section_structure = isset($questions_structure[$section]) && isset($questions_structure[$section]['questions'])
                ? $questions_structure[$section]['questions']
                : array();

            // Process questions
            if (isset($section_data['questions']) && is_array($section_data['questions'])) {
                foreach ($section_data['questions'] as $question_id => $answer) {
                    //error_log('Question ' . $question_id . ' answer: ' . json_encode($answer));

                    // If answer is an array, extract the relevant information
                    if (is_array($answer)) {
                        // Convert the array to a more readable format
                        $processed_answer = array();

                        // Extract text or value
                        if (isset($answer['text'])) {
                            $processed_answer['text'] = $answer['text'];
                        }

                        if (isset($answer['value'])) {
                            $processed_answer['value'] = $answer['value'];
                            //error_log("Found value in answer: " . $answer['value']);
                        } elseif (isset($answer['selected'])) {
                            $processed_answer['value'] = $answer['selected'];
                            //error_log("Found selected in answer: " . $answer['selected']);
                        }

                        // Extract stage if available
                        if (isset($answer['stage'])) {
                            $processed_answer['stage'] = $answer['stage'];
                            //error_log("Found stage directly in answer: " . $answer['stage']);
                        }

                        // Look for any numeric property that might be the value
                        if (!isset($processed_answer['value'])) {
                            foreach ($answer as $key => $value) {
                                if (is_numeric($value) && $key !== 'text') {
                                    $processed_answer['value'] = $value;
                                    //error_log("Found numeric value in answer: $key = $value");
                                    break;
                                }
                            }
                        }

                        // If we still don't have a value, use the first option value as a default
                        if (!isset($processed_answer['value']) && isset($section_structure[$question_id]) &&
                            isset($section_structure[$question_id]['options']) &&
                            is_array($section_structure[$question_id]['options']) &&
                            !empty($section_structure[$question_id]['options'])) {

                            $first_option = $section_structure[$question_id]['options'][0];
                            $processed_answer['value'] = $first_option['value'];
                            //error_log("Using first option value as default: " . $first_option['value']);

                            // Also include the stage if available and not already set
                            if (!isset($processed_answer['stage']) && isset($first_option['stage'])) {
                                $processed_answer['stage'] = $first_option['stage'];
                                //error_log("Using first option stage as default: " . $first_option['stage']);
                            }
                        }

                        // If we have a value but no stage, try to find the stage from the matching option
                        if (isset($processed_answer['value']) && !isset($processed_answer['stage']) &&
                            isset($section_structure[$question_id]) &&
                            isset($section_structure[$question_id]['options']) &&
                            is_array($section_structure[$question_id]['options'])) {

                            //error_log("Looking for option with value: " . $processed_answer['value']);
                            $found_matching_option = false;

                            foreach ($section_structure[$question_id]['options'] as $option) {
                                //error_log("Checking option: " . json_encode($option));

                                // Convert both values to strings for comparison
                                $option_value = (string) $option['value'];
                                $selected_value = (string) $processed_answer['value'];

                                if (isset($option['value']) && $option_value === $selected_value && isset($option['stage'])) {
                                    $processed_answer['stage'] = $option['stage'];
                                    //error_log("Found matching option with stage: " . $option['stage']);
                                    $found_matching_option = true;
                                    break;
                                }
                            }

                            if (!$found_matching_option) {
                                error_log("No matching option found for value: " . $processed_answer['value']);
                            }
                        }

                        //error_log("Final processed answer: " . json_encode($processed_answer));
                        $processed_section['questions'][$question_id] = $processed_answer;
                    } else {
                        // If answer is not an array, keep it as is
                        $processed_answer = array('value' => $answer);
                        //error_log("Answer is a primitive: $answer");

                        // Try to find the stage from the matching option
                        if (isset($section_structure[$question_id]) &&
                            isset($section_structure[$question_id]['options']) &&
                            is_array($section_structure[$question_id]['options'])) {

                            //error_log("Looking for option with value: $answer");
                            $found_matching_option = false;

                            foreach ($section_structure[$question_id]['options'] as $option) {
                                //error_log("Checking option: " . json_encode($option));

                                // Convert both values to strings for comparison
                                $option_value = (string) $option['value'];
                                $selected_value = (string) $answer;

                                if (isset($option['value']) && $option_value === $selected_value && isset($option['stage'])) {
                                    $processed_answer['stage'] = $option['stage'];
                                    //error_log("Found matching option with stage: " . $option['stage']);
                                    $found_matching_option = true;
                                    break;
                                }
                            }

                            if (!$found_matching_option) {
                                error_log("No matching option found for value: $answer");
                            }
                        }

                        //error_log("Final processed answer: " . json_encode($processed_answer));
                        $processed_section['questions'][$question_id] = $processed_answer;
                    }
                }
            }

            $processed_data[$section] = $processed_section;
        }

        $this->debug_dump($processed_data, 'processed-data-structure');
        return $processed_data;
    }

    /**
     * AJAX handler for getting assessment statistics.
     */
    public function ajax_get_assessment_stats()
    {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ham_assessment_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        // Get statistics
        $stats = self::get_assessment_statistics();

        wp_send_json_success($stats);
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @param string $hook Current admin page.
     */
    public function enqueue_admin_assets($hook)
    {
        // Debug information
        //error_log('Enqueuing assets for hook: ' . $hook);

        // Only enqueue on our plugin pages
        if (strpos($hook, 'ham-assessments') === false && strpos($hook, 'ham-assessment-stats') === false && strpos($hook, 'page_ham-assessment-stats') === false) {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            'ham-assessment-manager',
            plugins_url('assets/css/assessment-manager.css', HAM_PLUGIN_FILE),
            array(),
            HAM_VERSION
        );

        // Enqueue JavaScript
        wp_enqueue_script(
            'ham-assessment-manager',
            plugins_url('assets/js/assessment-manager.js', HAM_PLUGIN_FILE),
            array('jquery', 'wp-util'),
            HAM_VERSION,
            true
        );

        // Add Chart.js for statistics page
        if (strpos($hook, 'ham-assessment-stats') !== false || strpos($hook, 'page_ham-assessment-stats') !== false) {
            //error_log('Loading Chart.js on hook: ' . $hook);

            wp_enqueue_script(
                'chart-js',
                'https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js',
                array(),
                '3.7.1',
                true
            );
        }

        // Localize script
        wp_localize_script('ham-assessment-manager', 'hamAssessment', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ham_assessment_nonce'),
            'texts' => array(
                'loading' => esc_html__('Loading...', 'headless-access-manager'),
                'error' => esc_html__('Error loading data', 'headless-access-manager'),
                'noData' => esc_html__('No data available', 'headless-access-manager'),
                'viewDetails' => esc_html__('View Details', 'headless-access-manager'),
                'close' => esc_html__('Close', 'headless-access-manager'),
                'student' => esc_html__('Student', 'headless-access-manager'),
                'date' => esc_html__('Date', 'headless-access-manager'),
                'author' => esc_html__('Author', 'headless-access-manager'),
                'question' => esc_html__('Question', 'headless-access-manager'),
                'answer' => esc_html__('Answer', 'headless-access-manager'),
                'comments' => esc_html__('Comments', 'headless-access-manager'),
                'confirmDelete' => esc_html__('Are you sure you want to delete this assessment?', 'headless-access-manager'),
            )
        ));
    }

    /**
     * Filter the post type listing to exclude frontend assessments
     *
     * @param WP_Query $query The WP query object
     */
    public function filter_assessment_admin_listing($query)
    {
        global $pagenow, $typenow;

        // Only apply on the post type listing page for ham_assessment
        if (!is_admin() || $pagenow !== 'edit.php' || $typenow !== HAM_CPT_ASSESSMENT || !$query->is_main_query()) {
            return;
        }

        // Skip if explicitly requesting to see all assessments
        if (isset($_GET['view']) && $_GET['view'] === 'all') {
            return;
        }

        // Skip if we're viewing a single assessment
        if (isset($_GET['post'])) {
            return;
        }

        // Direct SQL filter for frontend submissions - more reliable than meta query
        add_filter('posts_where', array($this, 'exclude_frontend_assessments_sql'), 10, 2);
    }

    /**
     * Add SQL conditions to exclude frontend submissions
     * This is more reliable than meta_query for complex conditions
     *
     * @param string $where The WHERE clause of the query
     * @param WP_Query $query The WP_Query instance
     * @return string Modified WHERE clause
     */
    public function exclude_frontend_assessments_sql($where, $query)
    {
        global $wpdb;

        // Only apply once per request
        remove_filter('posts_where', array($this, 'exclude_frontend_assessments_sql'), 10);

        // SQL to exclude frontend submissions based on multiple indicators
        $where .= " AND (
            /* Submissions with admin source */
            EXISTS (
                SELECT 1 FROM {$wpdb->postmeta}
                WHERE {$wpdb->postmeta}.post_id = {$wpdb->posts}.ID
                AND {$wpdb->postmeta}.meta_key = '_ham_assessment_source'
                AND {$wpdb->postmeta}.meta_value = 'admin'
            )
            OR
            /* Identified admin posts */
            (
                {$wpdb->posts}.post_author != 0
                AND NOT EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta}
                    WHERE {$wpdb->postmeta}.post_id = {$wpdb->posts}.ID
                    AND (
                        ({$wpdb->postmeta}.meta_key = '_ham_assessment_source' AND {$wpdb->postmeta}.meta_value = 'frontend')
                        OR
                        ({$wpdb->postmeta}.meta_key = '_ham_api_created' AND {$wpdb->postmeta}.meta_value = '1')
                    )
                )
                AND {$wpdb->posts}.post_title NOT LIKE '%frontend%'
                AND {$wpdb->posts}.post_title NOT LIKE '%submission%'
                AND {$wpdb->posts}.post_title NOT LIKE '%tryggve%'
            )
        )";

        return $where;
    }

    /**
     * AJAX handler for deleting assessments
     *
     * Checks for proper authentication
     * and permissions before performing the deletion.
     *
     * @uses check_ajax_referer() For security verification
     * @uses current_user_can() To check user permissions
     * @uses wp_delete_post() To delete the assessment
     * @uses wp_send_json_error() To send error response
     * @uses wp_send_json_success() To send success response
     *
     * @return void Sends JSON response and exits
     */
    public function ajax_delete_assessment()
    {
        // Verify nonce
        if (!check_ajax_referer('ham_assessment_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Invalid security token.'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions.'));
        }

        $assessment_id = intval($_POST['assessment_id']);
        if (!$assessment_id) {
            wp_send_json_error(array('message' => 'Invalid assessment ID.'));
        }

        // Delete the assessment
        $result = wp_delete_post($assessment_id, true);

        if ($result) {
            wp_send_json_success(array('message' => 'Assessment deleted successfully.'));
        } else {
            wp_send_json_error(array('message' => 'Failed to delete assessment.'));
        }
    }

    /**
     * Map average score to a stage.
     *
     * @param float $average Average score.
     * @return string Stage value.
     */
    private static function map_average_to_stage($average) {
        // Evaluation grades
        if ($average < 0.4) {
            return 'ej'; // "not"
        } elseif ($average < 0.7) {
            return 'trans';
        } else {
            return 'full';
        }
    }
}

// Initialize the class
new HAM_Assessment_Manager();
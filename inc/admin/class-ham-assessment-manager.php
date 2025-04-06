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
class HAM_Assessment_Manager {
    /**
     * Constructor.
     */
    public function __construct() {
        // Register AJAX handlers
        add_action('wp_ajax_ham_get_assessment_details', array($this, 'ajax_get_assessment_details'));
        add_action('wp_ajax_ham_get_assessment_stats', array($this, 'ajax_get_assessment_stats'));

        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Render assessments list page.
     */
    public static function render_assessments_page() {
        // Get assessments
        $assessments = self::get_assessments();
        
        // Include the template
        include HAM_PLUGIN_DIR . 'templates/admin/assessments-list.php';
    }

    /**
     * Render assessment statistics page.
     */
    public static function render_statistics_page() {
        // Get statistics data
        $stats = self::get_assessment_statistics();
        
        // Include the template
        include HAM_PLUGIN_DIR . 'templates/admin/assessment-statistics.php';
    }

    /**
     * Get all assessments with student information.
     *
     * @return array Array of assessment data.
     */
    public static function get_assessments() {
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

        // Debug: Log the query arguments
        error_log('Assessment query args: ' . print_r($args, true));

        $assessments = array();
        $posts = get_posts($args);

        // Debug: Log the number of posts found
        error_log('Number of assessment posts found: ' . count($posts));

        foreach ($posts as $post) {
            $student_id = get_post_meta($post->ID, HAM_ASSESSMENT_META_STUDENT_ID, true);
            
            // Debug: Log the post and student ID
            error_log('Processing post ID: ' . $post->ID . ', Student ID: ' . $student_id);
            
            // Skip posts without a student ID (likely templates)
            if (empty($student_id)) {
                error_log('Skipping post ' . $post->ID . ' - no student ID');
                continue;
            }
            
            $student = get_user_by('id', $student_id);
            $student_name = $student ? $student->display_name : esc_html__('Unknown Student', 'headless-access-manager');
            
            $assessment_data = get_post_meta($post->ID, HAM_ASSESSMENT_META_DATA, true);
            
            // Debug: Log the assessment data structure
            error_log('Assessment data structure for post ' . $post->ID . ': ' . (is_array($assessment_data) ? json_encode(array_keys($assessment_data)) : 'not an array'));
            
            $completion_percentage = self::calculate_completion_percentage($assessment_data);
            
            $assessments[] = array(
                'id'           => $post->ID,
                'title'        => $post->post_title,
                'date'         => get_the_date('Y-m-d H:i:s', $post->ID),
                'student_id'   => $student_id,
                'student_name' => $student_name,
                'completion'   => $completion_percentage,
                'author_id'    => $post->post_author,
                'author_name'  => get_the_author_meta('display_name', $post->post_author),
            );
        }

        // Debug: Log the final assessments array
        error_log('Final assessments count: ' . count($assessments));

        return $assessments;
    }

    /**
     * Calculate completion percentage of an assessment.
     *
     * @param array $assessment_data The assessment data.
     * @return int Completion percentage.
     */
    private static function calculate_completion_percentage($assessment_data) {
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
     * @return array Statistics data.
     */
    public static function get_assessment_statistics() {
        $assessments = self::get_assessments();
        
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
    private static function get_answer_stage($section_key, $question_id, $answer) {
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
    private static function get_questions_structure() {
        // Define default options for questions
        $default_options = array(
            array('value' => '1', 'label' => 'Inte alls', 'stage' => 'ej'),
            array('value' => '2', 'label' => 'Sällan', 'stage' => 'ej'),
            array('value' => '3', 'label' => 'Ibland', 'stage' => 'trans'),
            array('value' => '4', 'label' => 'Ofta', 'stage' => 'trans'),
            array('value' => '5', 'label' => 'Alltid', 'stage' => 'full')
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

        // Hardcoded question texts as a fallback
        $question_texts = array(
            'anknytning' => array(
                'A1' => 'Närvaro',
                'A2' => 'Dialog 1',
                'A3' => 'Dialog 2',
                'A4' => 'Kontakt',
                'A5' => 'Samarbete',
                'A6' => 'Intresse',
                'A7' => 'Engagemang'
            ),
            'ansvar' => array(
                'B1' => 'Uppgift',
                'B2' => 'Initiativ',
                'B3' => 'Material',
                'B4' => 'Tid',
                'B5' => 'Regler',
                'B6' => 'Konflikt',
                'B7' => 'Ansvar'
            )
        );

        // Log the raw assessment data for debugging
        error_log('Raw assessment data: ' . json_encode($assessment_data));
        
        if (isset($assessment_data['anknytning']) && isset($assessment_data['anknytning']['questions'])) {
            $questions = array();
            
            // Process each question to ensure it has a text property
            foreach ($assessment_data['anknytning']['questions'] as $question_id => $question_data) {
                // If question_data is an array and has a text property, use it
                if (is_array($question_data) && isset($question_data['text'])) {
                    $questions[$question_id] = $question_data;
                } 
                // Otherwise, create a structure with the question text
                else {
                    $questions[$question_id] = array(
                        'text' => isset($question_texts['anknytning'][$question_id]) ? 
                                $question_texts['anknytning'][$question_id] : 
                                ucfirst($question_id)
                    );
                }
                
                // Ensure options are set for each question
                if (!isset($questions[$question_id]['options']) || !is_array($questions[$question_id]['options']) || empty($questions[$question_id]['options'])) {
                    $questions[$question_id]['options'] = $default_options;
                }
                
                // Log the processed question data
                error_log('Processed question data for ' . $question_id . ': ' . json_encode($questions[$question_id]));
            }
            
            $structure['anknytning'] = array(
                'title' => 'Anknytningstecken',
                'questions' => $questions
            );
        } else {
            $structure['anknytning'] = array(
                'title' => 'Anknytningstecken',
                'questions' => array()
            );
            error_log('Anknytning questions not found in assessment data');
        }

        if (isset($assessment_data['ansvar']) && isset($assessment_data['ansvar']['questions'])) {
            $questions = array();
            
            // Process each question to ensure it has a text property
            foreach ($assessment_data['ansvar']['questions'] as $question_id => $question_data) {
                // If question_data is an array and has a text property, use it
                if (is_array($question_data) && isset($question_data['text'])) {
                    $questions[$question_id] = $question_data;
                } 
                // Otherwise, create a structure with the question text
                else {
                    $questions[$question_id] = array(
                        'text' => isset($question_texts['ansvar'][$question_id]) ? 
                                $question_texts['ansvar'][$question_id] : 
                                ucfirst($question_id)
                    );
                }
                
                // Ensure options are set for each question
                if (!isset($questions[$question_id]['options']) || !is_array($questions[$question_id]['options']) || empty($questions[$question_id]['options'])) {
                    $questions[$question_id]['options'] = $default_options;
                }
                
                // Log the processed question data
                error_log('Processed question data for ' . $question_id . ': ' . json_encode($questions[$question_id]));
            }
            
            $structure['ansvar'] = array(
                'title' => 'Ansvarstecken',
                'questions' => $questions
            );
        } else {
            $structure['ansvar'] = array(
                'title' => 'Ansvarstecken',
                'questions' => array()
            );
            error_log('Ansvar questions not found in assessment data');
        }

        return $structure;
    }

    /**
     * Get default questions structure when no data is available.
     *
     * @return array Default questions structure.
     */
    private static function get_default_questions_structure() {
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
    private function debug_dump($data, $prefix = 'debug') {
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
    public function ajax_get_assessment_details() {
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
        error_log('Processing assessment details request for ID: ' . $assessment_id);

        // Get assessment data
        $assessment = get_post($assessment_id);
        if (!$assessment || $assessment->post_type !== HAM_CPT_ASSESSMENT) {
            wp_send_json_error('Assessment not found');
            return;
        }

        $student_id = get_post_meta($assessment_id, HAM_ASSESSMENT_META_STUDENT_ID, true);
        $student = get_user_by('id', $student_id);
        $student_name = $student ? $student->display_name : esc_html__('Unknown Student', 'headless-access-manager');

        $assessment_data = get_post_meta($assessment_id, HAM_ASSESSMENT_META_DATA, true);
        
        // Dump raw data for debugging
        $this->debug_dump($assessment_data, 'raw-assessment-data-' . $assessment_id);
        
        // Process the assessment data to ensure it's in the right format
        $processed_assessment_data = $this->process_assessment_data($assessment_data);
        
        $questions_structure = self::get_questions_structure();
        
        // Dump processed data for debugging
        $this->debug_dump($processed_assessment_data, 'processed-assessment-data-' . $assessment_id);
        $this->debug_dump($questions_structure, 'questions-structure-' . $assessment_id);
        
        // Debug information
        error_log('Raw assessment data: ' . print_r($assessment_data, true));
        error_log('Processed assessment data: ' . print_r($processed_assessment_data, true));

        $response = array(
            'id' => $assessment_id,
            'title' => $assessment->post_title,
            'date' => get_the_date('Y-m-d H:i:s', $assessment_id),
            'student_id' => $student_id,
            'student_name' => $student_name,
            'author_id' => $assessment->post_author,
            'author_name' => get_the_author_meta('display_name', $assessment->post_author),
            'assessment_data' => $processed_assessment_data,
            'questions_structure' => $questions_structure
        );

        wp_send_json_success($response);
    }
    
    /**
     * Process assessment data to ensure it's in the right format for display.
     *
     * @param array $data Raw assessment data.
     * @return array Processed assessment data.
     */
    private function process_assessment_data($data) {
        if (!is_array($data)) {
            return array();
        }
        
        error_log('Raw data structure: ' . json_encode($data));
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
            
            error_log('Section ' . $section . ' data: ' . json_encode($section_data));
            
            // Get questions structure for reference
            $questions_structure = self::get_questions_structure();
            $section_structure = isset($questions_structure[$section]) && isset($questions_structure[$section]['questions']) 
                ? $questions_structure[$section]['questions'] 
                : array();
            
            // Process questions
            if (isset($section_data['questions']) && is_array($section_data['questions'])) {
                foreach ($section_data['questions'] as $question_id => $answer) {
                    error_log('Question ' . $question_id . ' answer: ' . json_encode($answer));
                    
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
                            error_log("Found value in answer: " . $answer['value']);
                        } elseif (isset($answer['selected'])) {
                            $processed_answer['value'] = $answer['selected'];
                            error_log("Found selected in answer: " . $answer['selected']);
                        }
                        
                        // Extract stage if available
                        if (isset($answer['stage'])) {
                            $processed_answer['stage'] = $answer['stage'];
                            error_log("Found stage directly in answer: " . $answer['stage']);
                        }
                        
                        // Look for any numeric property that might be the value
                        if (!isset($processed_answer['value'])) {
                            foreach ($answer as $key => $value) {
                                if (is_numeric($value) && $key !== 'text') {
                                    $processed_answer['value'] = $value;
                                    error_log("Found numeric value in answer: $key = $value");
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
                            error_log("Using first option value as default: " . $first_option['value']);
                            
                            // Also include the stage if available and not already set
                            if (!isset($processed_answer['stage']) && isset($first_option['stage'])) {
                                $processed_answer['stage'] = $first_option['stage'];
                                error_log("Using first option stage as default: " . $first_option['stage']);
                            }
                        }
                        
                        // If we have a value but no stage, try to find the stage from the matching option
                        if (isset($processed_answer['value']) && !isset($processed_answer['stage']) && 
                            isset($section_structure[$question_id]) && 
                            isset($section_structure[$question_id]['options']) && 
                            is_array($section_structure[$question_id]['options'])) {
                            
                            error_log("Looking for option with value: " . $processed_answer['value']);
                            $found_matching_option = false;
                            
                            foreach ($section_structure[$question_id]['options'] as $option) {
                                error_log("Checking option: " . json_encode($option));
                                
                                // Convert both values to strings for comparison
                                $option_value = (string) $option['value'];
                                $selected_value = (string) $processed_answer['value'];
                                
                                if (isset($option['value']) && $option_value === $selected_value && isset($option['stage'])) {
                                    $processed_answer['stage'] = $option['stage'];
                                    error_log("Found matching option with stage: " . $option['stage']);
                                    $found_matching_option = true;
                                    break;
                                }
                            }
                            
                            if (!$found_matching_option) {
                                error_log("No matching option found for value: " . $processed_answer['value']);
                            }
                        }
                        
                        error_log("Final processed answer: " . json_encode($processed_answer));
                        $processed_section['questions'][$question_id] = $processed_answer;
                    } else {
                        // If answer is not an array, keep it as is
                        $processed_answer = array('value' => $answer);
                        error_log("Answer is a primitive: $answer");
                        
                        // Try to find the stage from the matching option
                        if (isset($section_structure[$question_id]) && 
                            isset($section_structure[$question_id]['options']) && 
                            is_array($section_structure[$question_id]['options'])) {
                            
                            error_log("Looking for option with value: $answer");
                            $found_matching_option = false;
                            
                            foreach ($section_structure[$question_id]['options'] as $option) {
                                error_log("Checking option: " . json_encode($option));
                                
                                // Convert both values to strings for comparison
                                $option_value = (string) $option['value'];
                                $selected_value = (string) $answer;
                                
                                if (isset($option['value']) && $option_value === $selected_value && isset($option['stage'])) {
                                    $processed_answer['stage'] = $option['stage'];
                                    error_log("Found matching option with stage: " . $option['stage']);
                                    $found_matching_option = true;
                                    break;
                                }
                            }
                            
                            if (!$found_matching_option) {
                                error_log("No matching option found for value: $answer");
                            }
                        }
                        
                        error_log("Final processed answer: " . json_encode($processed_answer));
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
    public function ajax_get_assessment_stats() {
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
    public function enqueue_admin_assets($hook) {
        // Only enqueue on our plugin pages
        if (strpos($hook, 'ham-assessments') === false && strpos($hook, 'ham-assessment-stats') === false) {
            return;
        }

        // Debug information
        error_log('Enqueuing assets for hook: ' . $hook);

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
        if (strpos($hook, 'ham-assessment-stats') !== false) {
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
            )
        ));
    }
}

// Initialize the class
new HAM_Assessment_Manager();

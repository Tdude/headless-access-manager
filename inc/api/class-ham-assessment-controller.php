<?php

/**
 * File: inc/api/class-ham-assessments-controller.php
 *
 * Assessments controller for API endpoints.
 *
 * @package HeadlessAccessManager
 */

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class HAM_Assessment_Controller
 *
 * Handles assessment-related API endpoints.
 */
class HAM_Assessment_Controller extends HAM_Base_Controller
{
    /**
     * Resource route base.
     *
     * @var string
     */
    protected $rest_base = 'assessments';

    /**
     * Register routes for assessments.
     */
    public static function register_routes()
    {
        $controller = new self();

        // Get all assessments (filtered)
        register_rest_route(
            $controller->namespace,
            '/' . $controller->rest_base,
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $controller, 'get_items' ),
                    'permission_callback' => array( $controller, 'get_items_permissions_check' ),
                    'args'                => array(
                        'student_id' => array(
                            'required'          => false,
                            'sanitize_callback' => 'absint',
                        ),
                        'teacher_id' => array(
                            'required'          => false,
                            'sanitize_callback' => 'absint',
                        ),
                        'class_id'   => array(
                            'required'          => false,
                            'sanitize_callback' => 'absint',
                        ),
                        'school_id'  => array(
                            'required'          => false,
                            'sanitize_callback' => 'absint',
                        ),
                        'from_date'  => array(
                            'required'          => false,
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'to_date'    => array(
                            'required'          => false,
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                    ),
                ),
            )
        );

        // Get single assessment
        register_rest_route(
            $controller->namespace,
            '/' . $controller->rest_base . '/(?P<id>[\d]+)',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $controller, 'get_item' ),
                    'permission_callback' => array( $controller, 'get_item_permissions_check' ),
                    'args'                => array(
                        'id' => array(
                            'required'          => true,
                            'validate_callback' => function ($param) {
                                return is_numeric($param);
                            },
                        ),
                    ),
                ),
            )
        );

        // Create assessment
        register_rest_route(
            $controller->namespace,
            '/' . $controller->rest_base,
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $controller, 'create_item' ),
                    'permission_callback' => array( $controller, 'create_item_permissions_check' ),
                    'args'                => array(
                        'student_id'      => array(
                            'required'          => true,
                            'sanitize_callback' => 'absint',
                        ),
                        'assessment_date' => array(
                            'required'          => false,
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'assessment_data' => array(
                            'required' => true,
                        ),
                    ),
                ),
            )
        );

        // Update assessment
        register_rest_route(
            $controller->namespace,
            '/' . $controller->rest_base . '/(?P<id>[\d]+)',
            array(
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $controller, 'update_item' ),
                    'permission_callback' => array( $controller, 'update_item_permissions_check' ),
                    'args'                => array(
                        'id'              => array(
                            'required'          => true,
                            'validate_callback' => function ($param) {
                                return is_numeric($param);
                            },
                        ),
                        'assessment_date' => array(
                            'required'          => false,
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'assessment_data' => array(
                            'required' => false,
                        ),
                    ),
                ),
            )
        );
    }

    /**
     * Get a collection of assessments.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function get_items($request)
    {
        $student_id = $request->get_param('student_id');
        $teacher_id = $request->get_param('teacher_id');
        $class_id   = $request->get_param('class_id');
        $school_id  = $request->get_param('school_id');
        $from_date  = $request->get_param('from_date');
        $to_date    = $request->get_param('to_date');

        $args = array(
            'post_type'      => HAM_CPT_ASSESSMENT,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        // Meta query
        $meta_query = array();

        // Filter by student
        if (! empty($student_id)) {
            $meta_query[] = array(
                'key'   => HAM_ASSESSMENT_META_STUDENT_ID,
                'value' => absint($student_id),
            );
        }

        // Filter by date range
        if (! empty($from_date) || ! empty($to_date)) {
            $date_query = array();

            if (! empty($from_date)) {
                $date_query['after'] = $from_date;
            }

            if (! empty($to_date)) {
                $date_query['before'] = $to_date;
            }

            $args['date_query'] = array( $date_query );
        }

        // Apply meta query if not empty
        if (! empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }

        // Filter by teacher (author)
        if (! empty($teacher_id)) {
            $args['author'] = absint($teacher_id);
        }

        // Get assessments
        $assessments = get_posts($args);

        // Additional filtering by class or school if needed
        if (! empty($class_id) || ! empty($school_id)) {
            $filtered_assessments = array();

            foreach ($assessments as $assessment) {
                $assessment_student_id = get_post_meta($assessment->ID, HAM_ASSESSMENT_META_STUDENT_ID, true);
                $student = get_user_by('id', $assessment_student_id);

                if (! $student) {
                    continue;
                }

                // Filter by class
                if (! empty($class_id)) {
                    $student_class_ids = get_user_meta($student->ID, HAM_USER_META_CLASS_IDS, true);

                    if (empty($student_class_ids) || ! is_array($student_class_ids) || ! in_array($class_id, $student_class_ids)) {
                        continue;
                    }
                }

                // Filter by school
                if (! empty($school_id)) {
                    $student_school_id = get_user_meta($student->ID, HAM_USER_META_SCHOOL_ID, true);

                    if (empty($student_school_id) || $student_school_id != $school_id) {
                        continue;
                    }
                }

                $filtered_assessments[] = $assessment;
            }

            $assessments = $filtered_assessments;
        }

        // Prepare response
        $data = array();

        foreach ($assessments as $assessment) {
            $data[] = $this->prepare_assessment_for_response($assessment);
        }

        return new WP_REST_Response($data, 200);
    }

    /**
     * Check if a given request has access to get assessments.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return bool|WP_Error True if the request has permission, WP_Error otherwise.
     */
    public function get_items_permissions_check($request)
    {
        $jwt_valid = $this->validate_jwt($request);

        if (is_wp_error($jwt_valid)) {
            return $jwt_valid;
        }

        $current_user_id = get_current_user_id();
        $current_user = get_user_by('id', $current_user_id);

        if (! $current_user) {
            return new WP_Error(
                'ham_rest_user_not_found',
                __('Current user not found.', 'headless-access-manager'),
                array( 'status' => 500 )
            );
        }

        // Administrator can access all assessments
        if (in_array('administrator', (array) $current_user->roles)) {
            return true;
        }

        $student_id = $request->get_param('student_id');
        $teacher_id = $request->get_param('teacher_id');
        $class_id   = $request->get_param('class_id');
        $school_id  = $request->get_param('school_id');

        // Students can only access their own assessments
        if (in_array(HAM_ROLE_STUDENT, (array) $current_user->roles)) {
            if (empty($student_id) || $student_id != $current_user_id) {
                return new WP_Error(
                    'ham_rest_forbidden',
                    __('Students can only access their own assessments.', 'headless-access-manager'),
                    array( 'status' => 403 )
                );
            }

            return true;
        }

        // Teachers can access assessments they created or for students in their classes
        if (in_array(HAM_ROLE_TEACHER, (array) $current_user->roles)) {
            // If filtering by teacher, must be the current user
            if (! empty($teacher_id) && $teacher_id != $current_user_id) {
                return new WP_Error(
                    'ham_rest_forbidden',
                    __('Teachers can only access assessments they created.', 'headless-access-manager'),
                    array( 'status' => 403 )
                );
            }

            // If filtering by class, must be one of the teacher's classes
            if (! empty($class_id)) {
                $teacher_class_ids = get_user_meta($current_user_id, HAM_USER_META_CLASS_IDS, true);

                if (empty($teacher_class_ids) || ! is_array($teacher_class_ids) || ! in_array($class_id, $teacher_class_ids)) {
                    return new WP_Error(
                        'ham_rest_forbidden',
                        __('Teachers can only access assessments for their classes.', 'headless-access-manager'),
                        array( 'status' => 403 )
                    );
                }
            }

            // If filtering by student, must be in one of the teacher's classes
            if (! empty($student_id)) {
                $teacher_class_ids = get_user_meta($current_user_id, HAM_USER_META_CLASS_IDS, true);
                $student_class_ids = get_user_meta($student_id, HAM_USER_META_CLASS_IDS, true);

                if (empty($teacher_class_ids) || empty($student_class_ids) ||
                    ! is_array($teacher_class_ids) || ! is_array($student_class_ids)) {
                    return new WP_Error(
                        'ham_rest_forbidden',
                        __('Teachers can only access assessments for students in their classes.', 'headless-access-manager'),
                        array( 'status' => 403 )
                    );
                }

                $common_classes = array_intersect($teacher_class_ids, $student_class_ids);

                if (empty($common_classes)) {
                    return new WP_Error(
                        'ham_rest_forbidden',
                        __('Teachers can only access assessments for students in their classes.', 'headless-access-manager'),
                        array( 'status' => 403 )
                    );
                }
            }

            return true;
        }

        // Principals can access assessments for their school
        if (in_array(HAM_ROLE_PRINCIPAL, (array) $current_user->roles)) {
            $principal_school_id = get_user_meta($current_user_id, HAM_USER_META_SCHOOL_ID, true);

            // If filtering by school, must be the principal's school
            if (! empty($school_id) && $school_id != $principal_school_id) {
                return new WP_Error(
                    'ham_rest_forbidden',
                    __('Principals can only access assessments for their school.', 'headless-access-manager'),
                    array( 'status' => 403 )
                );
            }

            return true;
        }

        // School heads can access assessments for their managed schools
        if (in_array(HAM_ROLE_SCHOOL_HEAD, (array) $current_user->roles)) {
            $managed_school_ids = get_user_meta($current_user_id, HAM_USER_META_MANAGED_SCHOOL_IDS, true);

            // If filtering by school, must be one of the school head's managed schools
            if (! empty($school_id)) {
                if (empty($managed_school_ids) || ! is_array($managed_school_ids) || ! in_array($school_id, $managed_school_ids)) {
                    return new WP_Error(
                        'ham_rest_forbidden',
                        __('School heads can only access assessments for their managed schools.', 'headless-access-manager'),
                        array( 'status' => 403 )
                    );
                }
            }

            return true;
        }

        return new WP_Error(
            'ham_rest_forbidden',
            __('You do not have permission to access assessments.', 'headless-access-manager'),
            array( 'status' => 403 )
        );
    }

    /**
     * Get a single assessment.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function get_item($request)
    {
        $id = $request->get_param('id');

        if (empty($id) || ! is_numeric($id)) {
            return new WP_Error(
                'ham_rest_invalid_id',
                __('Invalid assessment ID.', 'headless-access-manager'),
                array( 'status' => 400 )
            );
        }

        $assessment = get_post($id);

        if (! $assessment || $assessment->post_type !== HAM_CPT_ASSESSMENT) {
            return new WP_Error(
                'ham_rest_not_found',
                __('Assessment not found.', 'headless-access-manager'),
                array( 'status' => 404 )
            );
        }

        $response = $this->prepare_assessment_for_response($assessment);

        return new WP_REST_Response($response, 200);
    }

    /**
     * Prepare assessment data for response.
     *
     * @param WP_Post $assessment Assessment post object.
     * @return array Prepared assessment data.
     */
    protected function prepare_assessment_for_response($assessment)
    {
        // Get student data - student ID is stored as post meta and references a CPT ID
        $student_id = get_post_meta($assessment->ID, HAM_ASSESSMENT_META_STUDENT_ID, true);
        $student_name = __('Unknown Student', 'headless-access-manager');
        
        if (!empty($student_id)) {
            // First try: Get student name from the CPT directly
            $student_post = get_post($student_id);
            if ($student_post) {
                $student_name = $student_post->post_title;
            } else {
                // Second try: If we can't get the CPT, check if it's a user ID
                $student_user = get_user_by('id', $student_id);
                if ($student_user) {
                    $student_name = $student_user->display_name;
                }
            }
        }
        
        // Get teacher data (the post author)
        $teacher_id = $assessment->post_author;
        $teacher_name = __('Unknown Teacher', 'headless-access-manager');
        
        if (!empty($teacher_id)) {
            // First try: Get teacher as a user
            $teacher_user = get_user_by('id', $teacher_id);
            if ($teacher_user) {
                $teacher_name = $teacher_user->display_name;
            } else {
                // Second try: Maybe it's a CPT ID?
                $args = array(
                    'post_type' => HAM_CPT_TEACHER,
                    'meta_query' => array(
                        array(
                            'key' => '_ham_user_id',
                            'value' => $teacher_id,
                            'compare' => '='
                        )
                    ),
                    'posts_per_page' => 1
                );
                
                $teacher_query = new WP_Query($args);
                if ($teacher_query->have_posts()) {
                    $teacher_query->the_post();
                    $teacher_name = get_the_title();
                    wp_reset_postdata();
                }
            }
        }
        
        // Get assessment data
        $assessment_data = get_post_meta($assessment->ID, HAM_ASSESSMENT_META_DATA, true);
        
        // Determine completion status
        $completion = 'not'; // Default to 'not established'
        if (!empty($assessment_data)) {
            // You may want to customize this logic based on your specific requirements
            // This is just a simple example of determining completion status
            $anknytning_questions = !empty($assessment_data['anknytning']['questions']) ? $assessment_data['anknytning']['questions'] : array();
            $ansvar_questions = !empty($assessment_data['ansvar']['questions']) ? $assessment_data['ansvar']['questions'] : array();
            
            $total_questions = count($anknytning_questions) + count($ansvar_questions);
            $answered_questions = 0;
            $high_scores = 0; // Scores of 4-5
            
            foreach ($anknytning_questions as $score) {
                if (!empty($score)) {
                    $answered_questions++;
                    if ((int) $score >= 4) {
                        $high_scores++;
                    }
                }
            }
            
            foreach ($ansvar_questions as $score) {
                if (!empty($score)) {
                    $answered_questions++;
                    if ((int) $score >= 4) {
                        $high_scores++;
                    }
                }
            }
            
            if ($answered_questions > 0) {
                $high_score_percentage = ($high_scores / $answered_questions) * 100;
                if ($high_score_percentage >= 80) {
                    $completion = 'full'; // Established
                } elseif ($high_score_percentage >= 40) {
                    $completion = 'trans'; // Developing
                }
            }
        }
        
        return array(
            'id'           => $assessment->ID,
            'title'        => $assessment->post_title,
            'date'         => $assessment->post_date,
            'student_id'   => (int) $student_id,
            'student_name' => $student_name,
            'teacher_id'   => (int) $teacher_id,
            'teacher_name' => $teacher_name,
            'author_id'    => (int) $teacher_id,     // Keep for backward compatibility
            'author_name'  => $teacher_name,         // Keep for backward compatibility
            'completion'   => $completion,
            'stage'        => $completion,           // Keep for backward compatibility
            'data'         => $assessment_data,
        );
    }
}

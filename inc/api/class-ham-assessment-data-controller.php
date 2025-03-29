<?php

/**
 * File: inc/api/class-ham-assessment-data-controller.php
 *
 * Handles assessment data for API endpoints.
 *
 * @package HeadlessAccessManager
 */

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class HAM_Assessment_Data_Controller
 *
 * Handles assessment data API endpoints.
 */
class HAM_Assessment_Data_Controller extends HAM_Base_Controller
{
    /**
     * Resource route base.
     *
     * @var string
     */
    protected $rest_base = 'evaluation';

    /**
     * Register routes for assessment data.
     */
    public static function register_routes()
    {
        $controller = new self();

        // Save evaluation data
        register_rest_route(
            $controller->namespace,
            '/' . $controller->rest_base . '/save',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $controller, 'save_evaluation' ),
                    'permission_callback' => array( $controller, 'save_evaluation_permissions_check' ),
                    'args'                => array(
                        'student_id' => array(
                            'required'          => true,
                            'validate_callback' => function ($param) {
                                return is_numeric($param);
                            },
                        ),
                        'formData' => array(
                            'required' => true,
                        ),
                    ),
                ),
            )
        );

        // Get evaluation data
        register_rest_route(
            $controller->namespace,
            '/' . $controller->rest_base . '/get/(?P<id>[\d]+)',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $controller, 'get_evaluation' ),
                    'permission_callback' => array( $controller, 'get_evaluation_permissions_check' ),
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

        // List evaluations for a student
        register_rest_route(
            $controller->namespace,
            '/' . $controller->rest_base . '/list/(?P<student_id>[\d]+)',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $controller, 'list_evaluations' ),
                    'permission_callback' => array( $controller, 'list_evaluations_permissions_check' ),
                    'args'                => array(
                        'student_id' => array(
                            'required'          => true,
                            'validate_callback' => function ($param) {
                                return is_numeric($param);
                            },
                        ),
                    ),
                ),
            )
        );

        // Get evaluation questions structure
        register_rest_route(
            $controller->namespace,
            '/' . $controller->rest_base . '/questions',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $controller, 'get_questions_structure' ),
                    'permission_callback' => array( $controller, 'get_questions_permissions_check' ),
                ),
            )
        );
    }

    /**
     * Save evaluation data.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function save_evaluation($request)
    {
        $student_id = $request->get_param('student_id');
        $form_data = $request->get_param('formData');

        // Validate student
        $student = get_user_by('id', $student_id);
        if (! $student || ! in_array(HAM_ROLE_STUDENT, (array) $student->roles)) {
            return new WP_Error(
                'invalid_student',
                __('Invalid student ID.', 'headless-access-manager'),
                array( 'status' => 400 )
            );
        }

        // Create or update assessment
        $assessment_id = 0;

        // Check if an ID was provided (for updates)
        if (isset($form_data['id']) && is_numeric($form_data['id'])) {
            $assessment_id = absint($form_data['id']);
            $assessment = get_post($assessment_id);

            // Verify assessment exists and belongs to this student
            if (! $assessment || $assessment->post_type !== HAM_CPT_ASSESSMENT ||
                 get_post_meta($assessment_id, HAM_ASSESSMENT_META_STUDENT_ID, true) != $student_id) {
                $assessment_id = 0; // Reset to create new assessment
            }
        }

        if ($assessment_id === 0) {
            // Create new assessment
            $assessment_title = sprintf(
                __('Evaluation for %s - %s', 'headless-access-manager'),
                $student->display_name,
                current_time('Y-m-d')
            );

            $assessment_id = wp_insert_post(array(
                'post_title'   => $assessment_title,
                'post_type'    => HAM_CPT_ASSESSMENT,
                'post_status'  => 'publish',
                'post_author'  => get_current_user_id(),
            ));

            if (is_wp_error($assessment_id)) {
                return $assessment_id;
            }

            update_post_meta($assessment_id, HAM_ASSESSMENT_META_STUDENT_ID, $student_id);
        }

        // Save assessment data
        update_post_meta($assessment_id, HAM_ASSESSMENT_META_DATA, $form_data);

        return new WP_REST_Response(
            array(
                'id'      => $assessment_id,
                'message' => __('Evaluation saved successfully.', 'headless-access-manager'),
            ),
            200
        );
    }

    /**
     * Check if a request has access to save evaluation.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return bool|WP_Error True if has access, WP_Error otherwise.
     */
    public function save_evaluation_permissions_check($request)
    {
        $jwt_valid = $this->validate_jwt($request);

        if (is_wp_error($jwt_valid)) {
            return $jwt_valid;
        }

        // Check if user has submit_assessment capability
        $capability_check = $this->check_permission('submit_assessment');

        if (is_wp_error($capability_check)) {
            return $capability_check;
        }

        // Check if user has access to the student
        $student_id = $request->get_param('student_id');
        $current_user_id = get_current_user_id();

        if (! ham_can_access_student($current_user_id, $student_id)) {
            return new WP_Error(
                'unauthorized',
                __('You do not have permission to save evaluations for this student.', 'headless-access-manager'),
                array( 'status' => 403 )
            );
        }

        return true;
    }

    /**
     * Get evaluation data.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function get_evaluation($request)
    {
        $assessment_id = $request->get_param('id');
        $assessment = get_post($assessment_id);

        if (! $assessment || $assessment->post_type !== HAM_CPT_ASSESSMENT) {
            return new WP_Error(
                'not_found',
                __('Evaluation not found.', 'headless-access-manager'),
                array( 'status' => 404 )
            );
        }

        $form_data = get_post_meta($assessment_id, HAM_ASSESSMENT_META_DATA, true);

        if (empty($form_data)) {
            // Return default structure if no data exists
            $form_data = array(
                'anknytning' => array(
                    'comments' => array(),
                ),
                'ansvar' => array(
                    'comments' => array(),
                ),
            );

            // Fetch questions structure to initialize form data
            $questions = $this->get_latest_questions_structure();

            if ($questions) {
                foreach ($questions as $section => $section_data) {
                    if (isset($section_data['questions'])) {
                        foreach ($section_data['questions'] as $question_id => $question) {
                            $form_data[$section][$question_id] = '';
                        }
                    }
                }
            }
        }

        $student_id = get_post_meta($assessment_id, HAM_ASSESSMENT_META_STUDENT_ID, true);
        $student = get_user_by('id', $student_id);

        return new WP_REST_Response(
            array(
                'id'         => $assessment_id,
                'student_id' => $student_id,
                'student_name' => $student ? $student->display_name : '',
                'created_by' => $assessment->post_author,
                'date'       => $assessment->post_date,
                'formData'   => $form_data,
            ),
            200
        );
    }

    /**
     * Check if a request has access to get evaluation.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return bool|WP_Error True if has access, WP_Error otherwise.
     */
    public function get_evaluation_permissions_check($request)
    {
        $jwt_valid = $this->validate_jwt($request);

        if (is_wp_error($jwt_valid)) {
            return $jwt_valid;
        }

        $assessment_id = $request->get_param('id');
        $assessment = get_post($assessment_id);

        if (! $assessment || $assessment->post_type !== HAM_CPT_ASSESSMENT) {
            return new WP_Error(
                'not_found',
                __('Evaluation not found.', 'headless-access-manager'),
                array( 'status' => 404 )
            );
        }

        // Check if user has access to the student
        $student_id = get_post_meta($assessment_id, HAM_ASSESSMENT_META_STUDENT_ID, true);
        $current_user_id = get_current_user_id();

        if (! ham_can_access_student($current_user_id, $student_id)) {
            return new WP_Error(
                'unauthorized',
                __('You do not have permission to view evaluations for this student.', 'headless-access-manager'),
                array( 'status' => 403 )
            );
        }

        return true;
    }

    /**
     * List evaluations for a student.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function list_evaluations($request)
    {
        $student_id = $request->get_param('student_id');

        // Get assessments for student
        $assessments = ham_get_student_assessments($student_id);

        if (empty($assessments)) {
            return new WP_REST_Response(array(), 200);
        }

        $data = array();

        foreach ($assessments as $assessment) {
            $data[] = array(
                'id'         => $assessment->ID,
                'title'      => $assessment->post_title,
                'date'       => $assessment->post_date,
                'created_by' => $assessment->post_author,
            );
        }

        return new WP_REST_Response($data, 200);
    }

    /**
     * Check if a request has access to list evaluations.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return bool|WP_Error True if has access, WP_Error otherwise.
     */
    public function list_evaluations_permissions_check($request)
    {
        $jwt_valid = $this->validate_jwt($request);

        if (is_wp_error($jwt_valid)) {
            return $jwt_valid;
        }

        // Check if user has access to the student
        $student_id = $request->get_param('student_id');
        $current_user_id = get_current_user_id();

        if (! ham_can_access_student($current_user_id, $student_id)) {
            return new WP_Error(
                'unauthorized',
                __('You do not have permission to view evaluations for this student.', 'headless-access-manager'),
                array( 'status' => 403 )
            );
        }

        return true;
    }

    /**
     * Get questions structure for evaluations.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function get_questions_structure($request)
    {
        $structure = $this->get_latest_questions_structure();

        if (empty($structure)) {
            return new WP_Error(
                'not_found',
                __('No assessment questions found.', 'headless-access-manager'),
                array( 'status' => 404 )
            );
        }

        return new WP_REST_Response($structure, 200);
    }

    /**
     * Check if a request has access to get questions structure.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return bool|WP_Error True if has access, WP_Error otherwise.
     */
    public function get_questions_permissions_check($request)
    {
        $jwt_valid = $this->validate_jwt($request);

        if (is_wp_error($jwt_valid)) {
            return $jwt_valid;
        }

        return true;
    }

    /**
     * Get the latest questions structure from assessments.
     *
     * @return array|false Questions structure or false if none found.
     */
    private function get_latest_questions_structure()
    {
        // Get the most recent assessment that has question data
        $assessments = get_posts(array(
            'post_type'      => HAM_CPT_ASSESSMENT,
            'posts_per_page' => 1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => array(
                array(
                    'key'     => HAM_ASSESSMENT_META_DATA,
                    'compare' => 'EXISTS',
                ),
            ),
        ));

        if (empty($assessments)) {
            return $this->get_default_questions_structure();
        }

        $assessment_data = get_post_meta($assessments[0]->ID, HAM_ASSESSMENT_META_DATA, true);

        if (empty($assessment_data) || !is_array($assessment_data)) {
            return $this->get_default_questions_structure();
        }

        // Return only the questions part of the structure
        $structure = array();

        if (isset($assessment_data['anknytning']) && isset($assessment_data['anknytning']['questions'])) {
            $structure['anknytning'] = array(
                'title' => 'Anknytningstecken',
                'questions' => $assessment_data['anknytning']['questions']
            );
        }

        if (isset($assessment_data['ansvar']) && isset($assessment_data['ansvar']['questions'])) {
            $structure['ansvar'] = array(
                'title' => 'Ansvarstecken',
                'questions' => $assessment_data['ansvar']['questions']
            );
        }

        return !empty($structure) ? $structure : $this->get_default_questions_structure();
    }

    /**
     * Get default questions structure.
     *
     * @return array Default structure.
     */
    private function get_default_questions_structure()
    {
        // Return a default structure based on the requirements
        return array(
            'anknytning' => array(
                'title' => 'Anknytningstecken',
                'questions' => array(
                    'narvaro' => array(
                        'text' => 'Närvaro',
                        'options' => array(
                            array('value' => '1', 'label' => 'Kommer inte till skolan', 'stage' => 'ej'),
                            array('value' => '2', 'label' => 'Kommer till skolan, ej till lektion', 'stage' => 'ej'),
                            array('value' => '3', 'label' => 'Kommer till min lektion ibland', 'stage' => 'trans'),
                            array('value' => '4', 'label' => 'Kommer alltid till min lektion', 'stage' => 'trans'),
                            array('value' => '5', 'label' => 'Kommer till andras lektioner', 'stage' => 'full'),
                        ),
                    ),
                    'dialog1' => array(
                        'text' => 'Dialog 1',
                        'options' => array(
                            array('value' => '1', 'label' => 'Helt tyst', 'stage' => 'ej'),
                            array('value' => '2', 'label' => 'Säger enstaka ord till mig', 'stage' => 'ej'),
                            array('value' => '3', 'label' => 'Vi pratar ibland', 'stage' => 'trans'),
                            array('value' => '4', 'label' => 'Har full dialog med mig', 'stage' => 'trans'),
                            array('value' => '5', 'label' => 'Har dialog med andra vuxna', 'stage' => 'full'),
                        ),
                    ),
                    // Additional default fields as needed...
                ),
            ),
            'ansvar' => array(
                'title' => 'Ansvarstecken',
                'questions' => array(
                    'impulskontroll' => array(
                        'text' => 'Impulskontroll',
                        'options' => array(
                            array('value' => '1', 'label' => 'Helt impulsstyrd', 'stage' => 'ej'),
                            array('value' => '2', 'label' => 'Kan ibland hålla negativa känslor', 'stage' => 'ej'),
                            array('value' => '3', 'label' => 'Skäms över negativa beteenden', 'stage' => 'trans'),
                            array('value' => '4', 'label' => 'Kan ta mot tillsägelse', 'stage' => 'trans'),
                            array('value' => '5', 'label' => 'Kan prata om det som hänt', 'stage' => 'full'),
                        ),
                    ),
                    'fokus' => array(
                        'text' => 'Fokus',
                        'options' => array(
                            array('value' => '1', 'label' => 'Kan inte koncentrera sig', 'stage' => 'ej'),
                            array('value' => '2', 'label' => 'Kan fokusera en kort stund vid enskild tillsägelse', 'stage' => 'ej'),
                            array('value' => '3', 'label' => 'Kan fokusera självmant tillsammans med andra', 'stage' => 'trans'),
                            array('value' => '4', 'label' => 'Pratar om fokus och förbättrar sig', 'stage' => 'trans'),
                            array('value' => '5', 'label' => 'Kan fokusera och koncentrera sig', 'stage' => 'full'),
                        ),
                    ),
                    // Additional default fields as needed...
                ),
            ),
        );
    }
}

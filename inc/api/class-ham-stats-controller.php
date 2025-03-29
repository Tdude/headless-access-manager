<?php

/**
 * File: inc/api/class-ham-stats-controller.php
 *
 * Statistics controller for API endpoints.
 *
 * @package HeadlessAccessManager
 */

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class HAM_Stats_Controller
 *
 * Handles statistics-related API endpoints.
 */
class HAM_Stats_Controller extends HAM_Base_Controller
{
    /**
     * Resource route base.
     *
     * @var string
     */
    protected $rest_base = 'stats';

    /**
     * Register routes for statistics.
     */
    public static function register_routes()
    {
        $controller = new self();

        // Student progress
        register_rest_route(
            $controller->namespace,
            '/' . $controller->rest_base . '/student/(?P<student_id>[\d]+)/progress',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $controller, 'get_student_progress' ),
                    'permission_callback' => array( $controller, 'get_student_progress_permissions_check' ),
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

        // Class statistics
        register_rest_route(
            $controller->namespace,
            '/' . $controller->rest_base . '/class/(?P<class_id>[\d]+)',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $controller, 'get_class_stats' ),
                    'permission_callback' => array( $controller, 'get_class_stats_permissions_check' ),
                    'args'                => array(
                        'class_id' => array(
                            'required'          => true,
                            'validate_callback' => function ($param) {
                                return is_numeric($param);
                            },
                        ),
                    ),
                ),
            )
        );

        // Teacher statistics
        register_rest_route(
            $controller->namespace,
            '/' . $controller->rest_base . '/teacher/(?P<teacher_id>[\d]+)',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $controller, 'get_teacher_stats' ),
                    'permission_callback' => array( $controller, 'get_teacher_stats_permissions_check' ),
                    'args'                => array(
                        'teacher_id' => array(
                            'required'          => true,
                            'validate_callback' => function ($param) {
                                return is_numeric($param);
                            },
                        ),
                    ),
                ),
            )
        );

        // School statistics
        register_rest_route(
            $controller->namespace,
            '/' . $controller->rest_base . '/school/(?P<school_id>[\d]+)',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $controller, 'get_school_stats' ),
                    'permission_callback' => array( $controller, 'get_school_stats_permissions_check' ),
                    'args'                => array(
                        'school_id' => array(
                            'required'          => true,
                            'validate_callback' => function ($param) {
                                return is_numeric($param);
                            },
                        ),
                    ),
                ),
            )
        );

        // Multi-school statistics (for school head)
        register_rest_route(
            $controller->namespace,
            '/' . $controller->rest_base . '/schools',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $controller, 'get_multi_school_stats' ),
                    'permission_callback' => array( $controller, 'get_multi_school_stats_permissions_check' ),
                ),
            )
        );
    }

    /**
     * Get student progress statistics.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function get_student_progress($request)
    {
        $student_id = $request->get_param('student_id');
        $student = get_user_by('id', $student_id);

        if (! $student || ! in_array(HAM_ROLE_STUDENT, (array) $student->roles)) {
            return new WP_Error(
                'ham_rest_invalid_student',
                __('Invalid student ID.', 'headless-access-manager'),
                array( 'status' => 400 )
            );
        }

        // Get student assessments
        $assessments = ham_get_student_assessments($student_id);

        if (empty($assessments)) {
            return new WP_REST_Response(
                array(
                    'student_id'   => $student_id,
                    'student_name' => $student->display_name,
                    'assessments'  => array(),
                    'summary'      => array(
                        'total_assessments' => 0,
                    ),
                ),
                200
            );
        }

        // Prepare assessment data
        $assessment_data = array();

        foreach ($assessments as $assessment) {
            $data = get_post_meta($assessment->ID, HAM_ASSESSMENT_META_DATA, true);
            $date = get_post_meta($assessment->ID, HAM_ASSESSMENT_META_DATE, true);

            if (empty($date)) {
                $date = $assessment->post_date;
            }

            $assessment_data[] = array(
                'id'              => $assessment->ID,
                'date'            => $date,
                'data'            => $data,
                'teacher_id'      => $assessment->post_author,
                'teacher_name'    => get_the_author_meta('display_name', $assessment->post_author),
            );
        }

        // Basic summary statistics
        $summary = array(
            'total_assessments' => count($assessments),
        );

        // Add more detailed statistics here based on assessment data structure
        // This is just a placeholder and should be expanded based on actual assessment data format

        return new WP_REST_Response(
            array(
                'student_id'   => $student_id,
                'student_name' => $student->display_name,
                'assessments'  => $assessment_data,
                'summary'      => $summary,
            ),
            200
        );
    }

    /**
     * Check if a given request has access to get student progress.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return bool|WP_Error True if the request has permission, WP_Error otherwise.
     */
    public function get_student_progress_permissions_check($request)
    {
        $jwt_valid = $this->validate_jwt($request);

        if (is_wp_error($jwt_valid)) {
            return $jwt_valid;
        }

        // Check if user has view_own_stats capability (for students)
        $capability_check = $this->check_permission('view_own_stats');

        if (is_wp_error($capability_check)) {
            return $capability_check;
        }

        $student_id = $request->get_param('student_id');
        $current_user_id = get_current_user_id();

        // Users can always see their own progress
        if ($student_id == $current_user_id) {
            return true;
        }

        // For other users, check if they can access the student's data
        if (! ham_can_access_student($current_user_id, $student_id)) {
            return new WP_Error(
                'ham_rest_forbidden',
                __('You do not have permission to access this student\'s progress.', 'headless-access-manager'),
                array( 'status' => 403 )
            );
        }

        return true;
    }

    /**
     * Get class statistics.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function get_class_stats($request)
    {
        $class_id = $request->get_param('class_id');
        $class = get_post($class_id);

        if (! $class || $class->post_type !== HAM_CPT_CLASS) {
            return new WP_Error(
                'ham_rest_class_not_found',
                __('Class not found.', 'headless-access-manager'),
                array( 'status' => 404 )
            );
        }

        // Get students in class
        $students = ham_get_users_by_class($class_id, HAM_ROLE_STUDENT);

        if (empty($students)) {
            return new WP_REST_Response(
                array(
                    'class_id'    => $class_id,
                    'class_name'  => $class->post_title,
                    'students'    => array(),
                    'summary'     => array(
                        'total_students'    => 0,
                        'total_assessments' => 0,
                    ),
                ),
                200
            );
        }

        // Get assessments for all students in class
        $student_ids = array_map(function ($student) {
            return $student->ID;
        }, $students);

        $assessment_data = array();
        $total_assessments = 0;

        foreach ($student_ids as $student_id) {
            $assessments = ham_get_student_assessments($student_id);
            $total_assessments += count($assessments);

            if (! empty($assessments)) {
                $student = get_user_by('id', $student_id);

                $assessment_data[] = array(
                    'student_id'        => $student_id,
                    'student_name'      => $student->display_name,
                    'assessment_count'  => count($assessments),
                );
            }
        }

        // Basic summary statistics
        $summary = array(
            'total_students'    => count($students),
            'total_assessments' => $total_assessments,
            'avg_assessments_per_student' => $total_assessments > 0 ? round($total_assessments / count($students), 2) : 0,
        );

        return new WP_REST_Response(
            array(
                'class_id'    => $class_id,
                'class_name'  => $class->post_title,
                'students'    => $assessment_data,
                'summary'     => $summary,
            ),
            200
        );
    }

    /**
     * Check if a given request has access to get class statistics.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return bool|WP_Error True if the request has permission, WP_Error otherwise.
     */
    public function get_class_stats_permissions_check($request)
    {
        $jwt_valid = $this->validate_jwt($request);

        if (is_wp_error($jwt_valid)) {
            return $jwt_valid;
        }

        // Check if user has view_class_stats capability
        $capability_check = $this->check_permission('view_class_stats');

        if (is_wp_error($capability_check)) {
            return $capability_check;
        }

        $class_id = $request->get_param('class_id');
        $current_user_id = get_current_user_id();

        // Check if user has access to the class
        if (! ham_can_access_class($current_user_id, $class_id)) {
            return new WP_Error(
                'ham_rest_forbidden',
                __('You do not have permission to access statistics for this class.', 'headless-access-manager'),
                array( 'status' => 403 )
            );
        }

        return true;
    }

    /**
     * Get teacher statistics.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function get_teacher_stats($request)
    {
        $teacher_id = $request->get_param('teacher_id');
        $teacher = get_user_by('id', $teacher_id);

        if (! $teacher || ! in_array(HAM_ROLE_TEACHER, (array) $teacher->roles)) {
            return new WP_Error(
                'ham_rest_invalid_teacher',
                __('Invalid teacher ID.', 'headless-access-manager'),
                array( 'status' => 400 )
            );
        }

        // Get teacher assessments
        $assessments = ham_get_teacher_assessments($teacher_id);

        // Get teacher classes
        $class_ids = get_user_meta($teacher_id, HAM_USER_META_CLASS_IDS, true);
        $classes = array();

        if (! empty($class_ids) && is_array($class_ids)) {
            foreach ($class_ids as $class_id) {
                $class = get_post($class_id);

                if ($class && $class->post_type === HAM_CPT_CLASS) {
                    $student_count = count(ham_get_users_by_class($class_id, HAM_ROLE_STUDENT));

                    $classes[] = array(
                        'id'            => $class_id,
                        'name'          => $class->post_title,
                        'student_count' => $student_count,
                    );
                }
            }
        }

        // Group assessments by month
        $assessments_by_month = array();

        foreach ($assessments as $assessment) {
            $date = new DateTime($assessment->post_date);
            $month_key = $date->format('Y-m');

            if (! isset($assessments_by_month[ $month_key ])) {
                $assessments_by_month[ $month_key ] = array(
                    'month'      => $date->format('F Y'),
                    'count'      => 0,
                );
            }

            $assessments_by_month[ $month_key ]['count']++;
        }

        // Convert to indexed array and sort by month
        $assessments_by_month = array_values($assessments_by_month);
        usort($assessments_by_month, function ($a, $b) {
            return strcmp($a['month'], $b['month']);
        });

        // Basic summary statistics
        $summary = array(
            'total_assessments'   => count($assessments),
            'total_classes'       => count($classes),
            'total_students'      => array_sum(array_column($classes, 'student_count')),
        );

        return new WP_REST_Response(
            array(
                'teacher_id'          => $teacher_id,
                'teacher_name'        => $teacher->display_name,
                'classes'             => $classes,
                'assessments_by_month' => $assessments_by_month,
                'summary'             => $summary,
            ),
            200
        );
    }

    /**
     * Check if a given request has access to get teacher statistics.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return bool|WP_Error True if the request has permission, WP_Error otherwise.
     */
    public function get_teacher_stats_permissions_check($request)
    {
        $jwt_valid = $this->validate_jwt($request);

        if (is_wp_error($jwt_valid)) {
            return $jwt_valid;
        }

        // Check if user has view_teacher_stats capability
        $capability_check = $this->check_permission('view_teacher_stats');

        if (is_wp_error($capability_check)) {
            return $capability_check;
        }

        $teacher_id = $request->get_param('teacher_id');
        $current_user_id = get_current_user_id();

        // Teachers can view their own stats
        if ($teacher_id == $current_user_id) {
            return true;
        }

        // Check if user has permission to view teacher's stats
        $teacher = get_user_by('id', $teacher_id);

        if (! $teacher || ! in_array(HAM_ROLE_TEACHER, (array) $teacher->roles)) {
            return new WP_Error(
                'ham_rest_invalid_teacher',
                __('Invalid teacher ID.', 'headless-access-manager'),
                array( 'status' => 400 )
            );
        }

        $teacher_school_id = get_user_meta($teacher_id, HAM_USER_META_SCHOOL_ID, true);

        // Principal can view stats for teachers in their school
        if (in_array(HAM_ROLE_PRINCIPAL, (array) wp_get_current_user()->roles)) {
            $principal_school_id = get_user_meta($current_user_id, HAM_USER_META_SCHOOL_ID, true);

            if ($principal_school_id == $teacher_school_id) {
                return true;
            }
        }

        // School head can view stats for teachers in their managed schools
        if (in_array(HAM_ROLE_SCHOOL_HEAD, (array) wp_get_current_user()->roles)) {
            $managed_school_ids = get_user_meta($current_user_id, HAM_USER_META_MANAGED_SCHOOL_IDS, true);

            if (is_array($managed_school_ids) && in_array($teacher_school_id, $managed_school_ids)) {
                return true;
            }
        }

        return new WP_Error(
            'ham_rest_forbidden',
            __('You do not have permission to access statistics for this teacher.', 'headless-access-manager'),
            array( 'status' => 403 )
        );
    }

    /**
     * Get school statistics.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function get_school_stats($request)
    {
        $school_id = $request->get_param('school_id');
        $school = get_post($school_id);

        if (! $school || $school->post_type !== HAM_CPT_SCHOOL) {
            return new WP_Error(
                'ham_rest_school_not_found',
                __('School not found.', 'headless-access-manager'),
                array( 'status' => 404 )
            );
        }

        // Get teachers in school
        $teachers = ham_get_users_by_role(HAM_ROLE_TEACHER, $school_id);

        // Get classes in school
        $classes = ham_get_classes($school_id);

        // Get students in school
        $students = ham_get_users_by_role(HAM_ROLE_STUDENT, $school_id);

        // Calculate total assessments for the school
        $total_assessments = 0;

        foreach ($students as $student) {
            $assessments = ham_get_student_assessments($student->ID);
            $total_assessments += count($assessments);
        }

        // Prepare teacher data
        $teacher_data = array();

        foreach ($teachers as $teacher) {
            $assessments = ham_get_teacher_assessments($teacher->ID);
            $class_ids = get_user_meta($teacher->ID, HAM_USER_META_CLASS_IDS, true);
            $class_count = is_array($class_ids) ? count($class_ids) : 0;

            $teacher_data[] = array(
                'id'               => $teacher->ID,
                'name'             => $teacher->display_name,
                'assessment_count' => count($assessments),
                'class_count'      => $class_count,
            );
        }

        // Prepare class data
        $class_data = array();

        foreach ($classes as $class) {
            $students_in_class = ham_get_users_by_class($class->ID, HAM_ROLE_STUDENT);
            $student_count = count($students_in_class);

            $class_data[] = array(
                'id'            => $class->ID,
                'name'          => $class->post_title,
                'student_count' => $student_count,
            );
        }

        // Basic summary statistics
        $summary = array(
            'total_teachers'    => count($teachers),
            'total_classes'     => count($classes),
            'total_students'    => count($students),
            'total_assessments' => $total_assessments,
            'avg_assessments_per_student' => count($students) > 0 ? round($total_assessments / count($students), 2) : 0,
            'avg_assessments_per_teacher' => count($teachers) > 0 ? round($total_assessments / count($teachers), 2) : 0,
        );

        return new WP_REST_Response(
            array(
                'school_id'   => $school_id,
                'school_name' => $school->post_title,
                'teachers'    => $teacher_data,
                'classes'     => $class_data,
                'summary'     => $summary,
            ),
            200
        );
    }

    /**
     * Check if a given request has access to get school statistics.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return bool|WP_Error True if the request has permission, WP_Error otherwise.
     */
    public function get_school_stats_permissions_check($request)
    {
        $jwt_valid = $this->validate_jwt($request);

        if (is_wp_error($jwt_valid)) {
            return $jwt_valid;
        }

        // Check if user has view_school_stats capability
        $capability_check = $this->check_permission('view_school_stats');

        if (is_wp_error($capability_check)) {
            return $capability_check;
        }

        $school_id = $request->get_param('school_id');
        $current_user_id = get_current_user_id();

        // Check if user has access to the school
        if (! ham_can_access_school($current_user_id, $school_id)) {
            return new WP_Error(
                'ham_rest_forbidden',
                __('You do not have permission to access statistics for this school.', 'headless-access-manager'),
                array( 'status' => 403 )
            );
        }

        return true;
    }

    /**
     * Get multi-school statistics for school head.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function get_multi_school_stats($request)
    {
        $current_user_id = get_current_user_id();
        $current_user = get_user_by('id', $current_user_id);

        // Administrator can see all schools
        if (in_array('administrator', (array) $current_user->roles)) {
            $school_ids = array_map(function ($school) {
                return $school->ID;
            }, ham_get_schools());
        }
        // School head can see their managed schools
        elseif (in_array(HAM_ROLE_SCHOOL_HEAD, (array) $current_user->roles)) {
            $school_ids = get_user_meta($current_user_id, HAM_USER_META_MANAGED_SCHOOL_IDS, true);

            if (empty($school_ids) || ! is_array($school_ids)) {
                return new WP_REST_Response(
                    array(
                        'schools' => array(),
                        'summary' => array(
                            'total_schools' => 0,
                        ),
                    ),
                    200
                );
            }
        } else {
            return new WP_Error(
                'ham_rest_forbidden',
                __('You do not have permission to access multi-school statistics.', 'headless-access-manager'),
                array( 'status' => 403 )
            );
        }

        // Prepare school statistics
        $school_data = array();
        $total_teachers = 0;
        $total_classes = 0;
        $total_students = 0;
        $total_assessments = 0;

        foreach ($school_ids as $school_id) {
            $school = get_post($school_id);

            if (! $school || $school->post_type !== HAM_CPT_SCHOOL) {
                continue;
            }

            // Get counts for this school
            $teachers = ham_get_users_by_role(HAM_ROLE_TEACHER, $school_id);
            $teacher_count = count($teachers);
            $total_teachers += $teacher_count;

            $classes = ham_get_classes($school_id);
            $class_count = count($classes);
            $total_classes += $class_count;

            $students = ham_get_users_by_role(HAM_ROLE_STUDENT, $school_id);
            $student_count = count($students);
            $total_students += $student_count;

            $school_assessments = 0;

            foreach ($students as $student) {
                $assessments = ham_get_student_assessments($student->ID);
                $school_assessments += count($assessments);
            }

            $total_assessments += $school_assessments;

            $school_data[] = array(
                'id'               => $school_id,
                'name'             => $school->post_title,
                'teacher_count'    => $teacher_count,
                'class_count'      => $class_count,
                'student_count'    => $student_count,
                'assessment_count' => $school_assessments,
                'avg_assessments_per_student' => $student_count > 0 ? round($school_assessments / $student_count, 2) : 0,
            );
        }

        // Basic multi-school summary statistics
        $summary = array(
            'total_schools'    => count($school_data),
            'total_teachers'   => $total_teachers,
            'total_classes'    => $total_classes,
            'total_students'   => $total_students,
            'total_assessments' => $total_assessments,
            'avg_assessments_per_student' => $total_students > 0 ? round($total_assessments / $total_students, 2) : 0,
            'avg_students_per_school' => count($school_data) > 0 ? round($total_students / count($school_data), 2) : 0,
            'avg_teachers_per_school' => count($school_data) > 0 ? round($total_teachers / count($school_data), 2) : 0,
        );

        return new WP_REST_Response(
            array(
                'schools' => $school_data,
                'summary' => $summary,
            ),
            200
        );
    }

    /**
     * Check if a given request has access to get multi-school statistics.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return bool|WP_Error True if the request has permission, WP_Error otherwise.
     */
    public function get_multi_school_stats_permissions_check($request)
    {
        $jwt_valid = $this->validate_jwt($request);

        if (is_wp_error($jwt_valid)) {
            return $jwt_valid;
        }

        // Check if user has view_multi_school_stats capability
        $capability_check = $this->check_permission('view_multi_school_stats');

        if (is_wp_error($capability_check)) {
            return $capability_check;
        }

        return true;
    }
}

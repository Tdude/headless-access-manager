<?php

/**
 * File: inc/api/class-ham-data-controller.php
 *
 * Data controller for API endpoints (schools, classes).
 *
 * @package HeadlessAccessManager
 */

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class HAM_Data_Controller
 *
 * Handles school and class data API endpoints.
 */
class HAM_Data_Controller extends HAM_Base_Controller
{
    /**
     * Resource route base.
     *
     * @var string
     */
    protected $rest_base = 'data';

    /**
     * Register routes for data.
     */
    public static function register_routes()
    {
        $controller = new self();

        // Schools endpoints
        register_rest_route(
            $controller->namespace,
            '/schools',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $controller, 'get_schools' ),
                    'permission_callback' => array( $controller, 'get_schools_permissions_check' ),
                ),
            )
        );

        register_rest_route(
            $controller->namespace,
            '/schools/(?P<id>[\d]+)',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $controller, 'get_school' ),
                    'permission_callback' => array( $controller, 'get_school_permissions_check' ),
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

        // Classes endpoints
        register_rest_route(
            $controller->namespace,
            '/classes',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $controller, 'get_classes' ),
                    'permission_callback' => array( $controller, 'get_classes_permissions_check' ),
                    'args'                => array(
                        'school_id' => array(
                            'required'          => false,
                            'sanitize_callback' => 'absint',
                        ),
                    ),
                ),
            )
        );

        register_rest_route(
            $controller->namespace,
            '/classes/(?P<id>[\d]+)',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $controller, 'get_class' ),
                    'permission_callback' => array( $controller, 'get_class_permissions_check' ),
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
    }

    /**
     * Get all schools.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function get_schools($request)
    {
        $current_user_id = get_current_user_id();
        $current_user = get_user_by('id', $current_user_id);

        // Administrator can see all schools
        if (in_array('administrator', (array) $current_user->roles)) {
            $schools = ham_get_schools();
        }
        // School head can see their managed schools
        elseif (in_array(HAM_ROLE_SCHOOL_HEAD, (array) $current_user->roles)) {
            $managed_school_ids = get_user_meta($current_user_id, HAM_USER_META_MANAGED_SCHOOL_IDS, true);

            if (empty($managed_school_ids) || ! is_array($managed_school_ids)) {
                return new WP_REST_Response(array(), 200);
            }

            $schools = ham_get_schools(array(
                'post__in' => $managed_school_ids,
            ));
        }
        // Principal, teacher, student can see their school
        else {
            $school_id = get_user_meta($current_user_id, HAM_USER_META_SCHOOL_ID, true);

            if (empty($school_id)) {
                return new WP_REST_Response(array(), 200);
            }

            $schools = ham_get_schools(array(
                'p' => $school_id,
            ));
        }

        // Prepare response
        $data = array();

        foreach ($schools as $school) {
            $data[] = $this->prepare_school_for_response($school);
        }

        return new WP_REST_Response($data, 200);
    }

    /**
     * Check if a given request has access to get schools.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return bool|WP_Error True if the request has permission, WP_Error otherwise.
     */
    public function get_schools_permissions_check($request)
    {
        $jwt_valid = $this->validate_jwt($request);

        if (is_wp_error($jwt_valid)) {
            return $jwt_valid;
        }

        return true;
    }

    /**
     * Get a specific school.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function get_school($request)
    {
        $school_id = $request->get_param('id');
        $school = get_post($school_id);

        if (! $school || $school->post_type !== HAM_CPT_SCHOOL) {
            return new WP_Error(
                'ham_rest_school_not_found',
                __('School not found.', 'headless-access-manager'),
                array( 'status' => 404 )
            );
        }

        return new WP_REST_Response($this->prepare_school_for_response($school), 200);
    }

    /**
     * Check if a given request has access to get a specific school.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return bool|WP_Error True if the request has permission, WP_Error otherwise.
     */
    public function get_school_permissions_check($request)
    {
        $jwt_valid = $this->validate_jwt($request);

        if (is_wp_error($jwt_valid)) {
            return $jwt_valid;
        }

        $school_id = $request->get_param('id');
        $current_user_id = get_current_user_id();

        if (! ham_can_access_school($current_user_id, $school_id)) {
            return new WP_Error(
                'ham_rest_forbidden',
                __('You do not have permission to access this school.', 'headless-access-manager'),
                array( 'status' => 403 )
            );
        }

        return true;
    }

    /**
     * Get all classes, optionally filtered by school.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function get_classes($request)
    {
        $school_id = $request->get_param('school_id');
        $current_user_id = get_current_user_id();
        $current_user = get_user_by('id', $current_user_id);

        // Administrator can see all classes
        if (in_array('administrator', (array) $current_user->roles)) {
            $classes = ham_get_classes($school_id);
        }
        // School head can see classes in their managed schools
        elseif (in_array(HAM_ROLE_SCHOOL_HEAD, (array) $current_user->roles)) {
            $managed_school_ids = get_user_meta($current_user_id, HAM_USER_META_MANAGED_SCHOOL_IDS, true);

            if (! empty($school_id)) {
                // Check if requested school is managed by school head
                if (empty($managed_school_ids) || ! is_array($managed_school_ids) || ! in_array($school_id, $managed_school_ids)) {
                    return new WP_REST_Response(array(), 200);
                }

                $classes = ham_get_classes($school_id);
            } else {
                // Get classes from all managed schools
                if (empty($managed_school_ids) || ! is_array($managed_school_ids)) {
                    return new WP_REST_Response(array(), 200);
                }

                $classes = array();

                foreach ($managed_school_ids as $managed_school_id) {
                    $school_classes = ham_get_classes($managed_school_id);
                    $classes = array_merge($classes, $school_classes);
                }
            }
        }
        // Principal can see classes in their school
        elseif (in_array(HAM_ROLE_PRINCIPAL, (array) $current_user->roles)) {
            $principal_school_id = get_user_meta($current_user_id, HAM_USER_META_SCHOOL_ID, true);

            if (! empty($school_id) && $school_id != $principal_school_id) {
                return new WP_REST_Response(array(), 200);
            }

            $classes = ham_get_classes($principal_school_id);
        }
        // Teacher can see their classes
        elseif (in_array(HAM_ROLE_TEACHER, (array) $current_user->roles)) {
            $teacher_class_ids = get_user_meta($current_user_id, HAM_USER_META_CLASS_IDS, true);

            if (empty($teacher_class_ids) || ! is_array($teacher_class_ids)) {
                return new WP_REST_Response(array(), 200);
            }

            $classes = get_posts(array(
                'post_type'      => HAM_CPT_CLASS,
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'post__in'       => $teacher_class_ids,
                'orderby'        => 'title',
                'order'          => 'ASC',
            ));

            // Filter by school if provided
            if (! empty($school_id)) {
                $filtered_classes = array();

                foreach ($classes as $class) {
                    $class_school_id = get_post_meta($class->ID, '_ham_school_id', true);

                    if ($class_school_id == $school_id) {
                        $filtered_classes[] = $class;
                    }
                }

                $classes = $filtered_classes;
            }
        }
        // Student can see their classes
        elseif (in_array(HAM_ROLE_STUDENT, (array) $current_user->roles)) {
            $student_class_ids = get_user_meta($current_user_id, HAM_USER_META_CLASS_IDS, true);

            if (empty($student_class_ids) || ! is_array($student_class_ids)) {
                return new WP_REST_Response(array(), 200);
            }

            $classes = get_posts(array(
                'post_type'      => HAM_CPT_CLASS,
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'post__in'       => $student_class_ids,
                'orderby'        => 'title',
                'order'          => 'ASC',
            ));

            // Filter by school if provided
            if (! empty($school_id)) {
                $filtered_classes = array();

                foreach ($classes as $class) {
                    $class_school_id = get_post_meta($class->ID, '_ham_school_id', true);

                    if ($class_school_id == $school_id) {
                        $filtered_classes[] = $class;
                    }
                }

                $classes = $filtered_classes;
            }
        } else {
            $classes = array();
        }

        // Prepare response
        $data = array();

        foreach ($classes as $class) {
            $data[] = $this->prepare_class_for_response($class);
        }

        return new WP_REST_Response($data, 200);
    }

    /**
     * Check if a given request has access to get classes.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return bool|WP_Error True if the request has permission, WP_Error otherwise.
     */
    public function get_classes_permissions_check($request)
    {
        $jwt_valid = $this->validate_jwt($request);

        if (is_wp_error($jwt_valid)) {
            return $jwt_valid;
        }

        $school_id = $request->get_param('school_id');

        if (! empty($school_id)) {
            $current_user_id = get_current_user_id();

            if (! ham_can_access_school($current_user_id, $school_id)) {
                return new WP_Error(
                    'ham_rest_forbidden',
                    __('You do not have permission to access classes from this school.', 'headless-access-manager'),
                    array( 'status' => 403 )
                );
            }
        }

        return true;
    }

    /**
     * Get a specific class.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function get_class($request)
    {
        $class_id = $request->get_param('id');
        $class = get_post($class_id);

        if (! $class || $class->post_type !== HAM_CPT_CLASS) {
            return new WP_Error(
                'ham_rest_class_not_found',
                __('Class not found.', 'headless-access-manager'),
                array( 'status' => 404 )
            );
        }

        return new WP_REST_Response($this->prepare_class_for_response($class), 200);
    }

    /**
     * Check if a given request has access to get a specific class.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return bool|WP_Error True if the request has permission, WP_Error otherwise.
     */
    public function get_class_permissions_check($request)
    {
        $jwt_valid = $this->validate_jwt($request);

        if (is_wp_error($jwt_valid)) {
            return $jwt_valid;
        }

        $class_id = $request->get_param('id');
        $current_user_id = get_current_user_id();

        if (! ham_can_access_class($current_user_id, $class_id)) {
            return new WP_Error(
                'ham_rest_forbidden',
                __('You do not have permission to access this class.', 'headless-access-manager'),
                array( 'status' => 403 )
            );
        }

        return true;
    }

    /**
     * Prepare school data for response.
     *
     * @param WP_Post $school School post object.
     * @return array School data.
     */
    protected function prepare_school_for_response($school)
    {
        $data = array(
            'id'    => $school->ID,
            'name'  => $school->post_title,
        );

        // Add featured image if available
        if (has_post_thumbnail($school->ID)) {
            $data['featured_image'] = get_the_post_thumbnail_url($school->ID, 'medium');
        }

        return $data;
    }

    /**
     * Prepare class data for response.
     *
     * @param WP_Post $class Class post object.
     * @return array Class data.
     */
    protected function prepare_class_for_response($class)
    {
        $school_id = get_post_meta($class->ID, '_ham_school_id', true);
        $school = get_post($school_id);

        $data = array(
            'id'         => $class->ID,
            'name'       => $class->post_title,
            'school_id'  => $school_id,
            'school_name' => $school ? $school->post_title : '',
        );

        return $data;
    }
}

<?php

/**
 * File: inc/api/class-ham-users-controller.php
 *
 * Users controller for API endpoints.
 *
 * @package HeadlessAccessManager
 */

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class HAM_Users_Controller
 *
 * Handles user-related API endpoints.
 */
class HAM_Users_Controller extends HAM_Base_Controller
{
    /**
     * Resource route base.
     *
     * @var string
     */
    protected $rest_base = 'users';

    /**
     * Register routes for users.
     */
    public static function register_routes()
    {
        $controller = new self();

        // Current user info
        register_rest_route(
            $controller->namespace,
            '/' . $controller->rest_base . '/me',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $controller, 'get_current_user' ),
                    'permission_callback' => array( $controller, 'get_current_user_permissions_check' ),
                ),
            )
        );

        // Get users
        register_rest_route(
            $controller->namespace,
            '/' . $controller->rest_base,
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $controller, 'get_items' ),
                    'permission_callback' => array( $controller, 'get_items_permissions_check' ),
                    'args'                => array(
                        'role'      => array(
                            'required'          => false,
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'school_id' => array(
                            'required'          => false,
                            'sanitize_callback' => 'absint',
                        ),
                        'class_id'  => array(
                            'required'          => false,
                            'sanitize_callback' => 'absint',
                        ),
                    ),
                ),
            )
        );

        // Get single user
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

        // Create user
        register_rest_route(
            $controller->namespace,
            '/' . $controller->rest_base,
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $controller, 'create_item' ),
                    'permission_callback' => array( $controller, 'create_item_permissions_check' ),
                    'args'                => array(
                        'username'  => array(
                            'required'          => true,
                            'sanitize_callback' => 'sanitize_user',
                        ),
                        'email'     => array(
                            'required'          => true,
                            'sanitize_callback' => 'sanitize_email',
                        ),
                        'password'  => array(
                            'required' => true,
                        ),
                        'role'      => array(
                            'required'          => true,
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'name'      => array(
                            'required'          => false,
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'school_id' => array(
                            'required'          => false,
                            'sanitize_callback' => 'absint',
                        ),
                        'class_ids' => array(
                            'required' => false,
                        ),
                    ),
                ),
            )
        );

        // Update user
        register_rest_route(
            $controller->namespace,
            '/' . $controller->rest_base . '/(?P<id>[\d]+)',
            array(
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $controller, 'update_item' ),
                    'permission_callback' => array( $controller, 'update_item_permissions_check' ),
                    'args'                => array(
                        'id'        => array(
                            'required'          => true,
                            'validate_callback' => function ($param) {
                                return is_numeric($param);
                            },
                        ),
                        'email'     => array(
                            'required'          => false,
                            'sanitize_callback' => 'sanitize_email',
                        ),
                        'password'  => array(
                            'required' => false,
                        ),
                        'role'      => array(
                            'required'          => false,
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'name'      => array(
                            'required'          => false,
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'school_id' => array(
                            'required'          => false,
                            'sanitize_callback' => 'absint',
                        ),
                        'class_ids' => array(
                            'required' => false,
                        ),
                    ),
                ),
            )
        );
    }

    /**
     * Get current user info.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function get_current_user($request)
    {
        $user = wp_get_current_user();

        if (! $user->exists()) {
            return new WP_Error(
                'ham_rest_user_not_found',
                __('User not found.', 'headless-access-manager'),
                array( 'status' => 404 )
            );
        }

        return $this->prepare_user_response($user);
    }

    /**
     * Check if a given request has access to get current user info.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return bool|WP_Error True if the request has permission, WP_Error otherwise.
     */
    public function get_current_user_permissions_check($request)
    {
        return $this->validate_jwt($request);
    }

    /**
     * Get a collection of users.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function get_items($request)
    {
        $role      = $request->get_param('role');
        $school_id = $request->get_param('school_id');
        $class_id  = $request->get_param('class_id');

        $args = array();

        // Filter by role if provided
        if (! empty($role)) {
            $args['role'] = $role;
        } else {
            // If no role specified, only get HAM roles
            $args['role__in'] = HAM_Roles::get_all_roles();
        }

        // Get users
        $users = get_users($args);

        // Filter by school if provided
        if (! empty($school_id)) {
            $filtered_users = array();

            foreach ($users as $user) {
                $user_school_id = get_user_meta($user->ID, HAM_USER_META_SCHOOL_ID, true);

                if ($user_school_id == $school_id) {
                    $filtered_users[] = $user;
                }
            }

            $users = $filtered_users;
        }

        // Filter by class if provided
        if (! empty($class_id)) {
            $filtered_users = array();

            foreach ($users as $user) {
                $class_ids = get_user_meta($user->ID, HAM_USER_META_CLASS_IDS, true);

                if (is_array($class_ids) && in_array($class_id, $class_ids)) {
                    $filtered_users[] = $user;
                }
            }

            $users = $filtered_users;
        }

        // Prepare response
        $data = array();

        foreach ($users as $user) {
            $data[] = $this->prepare_user_for_response($user);
        }

        return new WP_REST_Response($data, 200);
    }

    /**
     * Check if a given request has access to get users.
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

        // Check if user has proper capabilities
        $capability_check = $this->check_permission(array(
            'manage_students',
            'manage_teachers',
            'manage_school_users',
            'manage_schools',
        ));

        if (is_wp_error($capability_check)) {
            return $capability_check;
        }

        // Check if school_id parameter is provided and user has access to it
        $school_id = $request->get_param('school_id');

        if (! empty($school_id) && ! ham_can_access_school(get_current_user_id(), $school_id)) {
            return new WP_Error(
                'ham_rest_forbidden',
                __('You do not have permission to access users from this school.', 'headless-access-manager'),
                array( 'status' => 403 )
            );
        }

        // Check if class_id parameter is provided and user has access to it
        $class_id = $request->get_param('class_id');

        if (! empty($class_id) && ! ham_can_access_class(get_current_user_id(), $class_id)) {
            return new WP_Error(
                'ham_rest_forbidden',
                __('You do not have permission to access users from this class.', 'headless-access-manager'),
                array( 'status' => 403 )
            );
        }

        return true;
    }

    /**
     * Get a single user.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function get_item($request)
    {
        $user_id = $request->get_param('id');
        $user    = get_user_by('id', $user_id);

        if (! $user) {
            return new WP_Error(
                'ham_rest_user_not_found',
                __('User not found.', 'headless-access-manager'),
                array( 'status' => 404 )
            );
        }

        return $this->prepare_user_response($user);
    }

    /**
     * Check if a given request has access to get a specific user.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return bool|WP_Error True if the request has permission, WP_Error otherwise.
     */
    public function get_item_permissions_check($request)
    {
        $jwt_valid = $this->validate_jwt($request);

        if (is_wp_error($jwt_valid)) {
            return $jwt_valid;
        }

        $user_id     = $request->get_param('id');
        $current_user_id = get_current_user_id();

        // Users can always access their own data
        if ($user_id == $current_user_id) {
            return true;
        }

        // Check if current user has permission to access the target user
        $user = get_user_by('id', $user_id);

        if (! $user) {
            return new WP_Error(
                'ham_rest_user_not_found',
                __('User not found.', 'headless-access-manager'),
                array( 'status' => 404 )
            );
        }

        // Check if target user is a student
        if (in_array(HAM_ROLE_STUDENT, (array) $user->roles)) {
            return ham_can_access_student($current_user_id, $user_id);
        }

        // For non-student users, check if current user has management capabilities
        $capability_check = $this->check_permission(array(
            'manage_students',
            'manage_teachers',
            'manage_school_users',
            'manage_schools',
        ));

        if (is_wp_error($capability_check)) {
            return $capability_check;
        }

        // Check if target user is in a school that current user can manage
        $user_school_id = get_user_meta($user_id, HAM_USER_META_SCHOOL_ID, true);

        if (! empty($user_school_id) && ! ham_can_access_school($current_user_id, $user_school_id)) {
            return new WP_Error(
                'ham_rest_forbidden',
                __('You do not have permission to access this user.', 'headless-access-manager'),
                array( 'status' => 403 )
            );
        }

        return true;
    }

    /**
     * Create a new user.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function create_item($request)
    {
        $username  = $request->get_param('username');
        $email     = $request->get_param('email');
        $password  = $request->get_param('password');
        $role      = $request->get_param('role');
        $name      = $request->get_param('name');
        $school_id = $request->get_param('school_id');
        $class_ids = $request->get_param('class_ids');

        // Validate role
        if (! in_array($role, HAM_Roles::get_all_roles())) {
            return new WP_Error(
                'ham_rest_invalid_role',
                __('Invalid role.', 'headless-access-manager'),
                array( 'status' => 400 )
            );
        }

        // Validate school ID
        if (! empty($school_id)) {
            $school = get_post($school_id);

            if (! $school || $school->post_type !== HAM_CPT_SCHOOL) {
                return new WP_Error(
                    'ham_rest_invalid_school',
                    __('Invalid school ID.', 'headless-access-manager'),
                    array( 'status' => 400 )
                );
            }
        }

        // Validate class IDs
        if (! empty($class_ids) && is_array($class_ids)) {
            foreach ($class_ids as $class_id) {
                $class = get_post($class_id);

                if (! $class || $class->post_type !== HAM_CPT_CLASS) {
                    return new WP_Error(
                        'ham_rest_invalid_class',
                        __('Invalid class ID.', 'headless-access-manager'),
                        array( 'status' => 400 )
                    );
                }
            }
        }

        // Create user
        $user_data = array(
            'user_login' => $username,
            'user_email' => $email,
            'user_pass'  => $password,
            'role'       => $role,
        );

        if (! empty($name)) {
            $user_data['display_name'] = $name;
            $user_data['nickname']     = $name;
            $user_data['first_name']   = $name; // Simplified, might want to split first/last name in a real implementation
        }

        $user_id = wp_insert_user($user_data);

        if (is_wp_error($user_id)) {
            return $user_id;
        }

        // Set school and class metadata
        if (! empty($school_id)) {
            update_user_meta($user_id, HAM_USER_META_SCHOOL_ID, $school_id);
        }

        if (! empty($class_ids) && is_array($class_ids)) {
            update_user_meta($user_id, HAM_USER_META_CLASS_IDS, array_map('absint', $class_ids));
        }

        // Get user
        $user = get_user_by('id', $user_id);

        return $this->prepare_user_response($user);
    }

    /**
     * Check if a given request has access to create a user.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return bool|WP_Error True if the request has permission, WP_Error otherwise.
     */
    public function create_item_permissions_check($request)
    {
        $jwt_valid = $this->validate_jwt($request);

        if (is_wp_error($jwt_valid)) {
            return $jwt_valid;
        }

        $role = $request->get_param('role');

        // Check if user has permission to create users with this role
        $current_user_id = get_current_user_id();
        $current_user = get_user_by('id', $current_user_id);

        if (! $current_user) {
            return new WP_Error(
                'ham_rest_user_not_found',
                __('Current user not found.', 'headless-access-manager'),
                array( 'status' => 500 )
            );
        }

        // School head can create any user
        if (in_array(HAM_ROLE_SCHOOL_HEAD, (array) $current_user->roles)) {
            return true;
        }

        // Principal can create teachers and students
        if (in_array(HAM_ROLE_PRINCIPAL, (array) $current_user->roles)) {
            if (in_array($role, array( HAM_ROLE_TEACHER, HAM_ROLE_STUDENT ))) {
                return true;
            }
        }

        // Teacher can create students if they have the capability
        if (in_array(HAM_ROLE_TEACHER, (array) $current_user->roles) && $role === HAM_ROLE_STUDENT) {
            if (user_can($current_user_id, 'manage_students')) {
                return true;
            }
        }

        return new WP_Error(
            'ham_rest_forbidden',
            __('You do not have permission to create users with this role.', 'headless-access-manager'),
            array( 'status' => 403 )
        );
    }

    /**
     * Update a user.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function update_item($request)
    {
        $user_id   = $request->get_param('id');
        $user      = get_user_by('id', $user_id);

        if (! $user) {
            return new WP_Error(
                'ham_rest_user_not_found',
                __('User not found.', 'headless-access-manager'),
                array( 'status' => 404 )
            );
        }

        $email     = $request->get_param('email');
        $password  = $request->get_param('password');
        $role      = $request->get_param('role');
        $name      = $request->get_param('name');
        $school_id = $request->get_param('school_id');
        $class_ids = $request->get_param('class_ids');

        // Prepare user data for update
        $user_data = array(
            'ID' => $user_id,
        );

        if (! empty($email)) {
            $user_data['user_email'] = $email;
        }

        if (! empty($password)) {
            $user_data['user_pass'] = $password;
        }

        if (! empty($name)) {
            $user_data['display_name'] = $name;
            $user_data['nickname']     = $name;
            $user_data['first_name']   = $name; // Simplified, might want to split first/last name in a real implementation
        }

        // Update user
        $result = wp_update_user($user_data);

        if (is_wp_error($result)) {
            return $result;
        }

        // Update role if provided
        if (! empty($role) && in_array($role, HAM_Roles::get_all_roles())) {
            // Remove existing HAM roles
            foreach (HAM_Roles::get_all_roles() as $ham_role) {
                $user->remove_role($ham_role);
            }

            // Add new role
            $user->add_role($role);
        }

        // Update school and class metadata
        if (isset($school_id)) {
            if (empty($school_id)) {
                delete_user_meta($user_id, HAM_USER_META_SCHOOL_ID);
            } else {
                $school = get_post($school_id);

                if (! $school || $school->post_type !== HAM_CPT_SCHOOL) {
                    return new WP_Error(
                        'ham_rest_invalid_school',
                        __('Invalid school ID.', 'headless-access-manager'),
                        array( 'status' => 400 )
                    );
                }

                update_user_meta($user_id, HAM_USER_META_SCHOOL_ID, $school_id);
            }
        }

        if (isset($class_ids)) {
            if (empty($class_ids) || ! is_array($class_ids)) {
                delete_user_meta($user_id, HAM_USER_META_CLASS_IDS);
            } else {
                // Validate class IDs
                foreach ($class_ids as $class_id) {
                    $class = get_post($class_id);

                    if (! $class || $class->post_type !== HAM_CPT_CLASS) {
                        return new WP_Error(
                            'ham_rest_invalid_class',
                            __('Invalid class ID.', 'headless-access-manager'),
                            array( 'status' => 400 )
                        );
                    }
                }

                update_user_meta($user_id, HAM_USER_META_CLASS_IDS, array_map('absint', $class_ids));
            }
        }

        // Get updated user
        $user = get_user_by('id', $user_id);

        return $this->prepare_user_response($user);
    }

    /**
     * Check if a given request has access to update a user.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return bool|WP_Error True if the request has permission, WP_Error otherwise.
     */
    public function update_item_permissions_check($request)
    {
        $jwt_valid = $this->validate_jwt($request);

        if (is_wp_error($jwt_valid)) {
            return $jwt_valid;
        }

        $user_id = $request->get_param('id');
        $current_user_id = get_current_user_id();

        // Users can always update their own data
        if ($user_id == $current_user_id) {
            return true;
        }

        // Check if current user has permission to update the target user
        $user = get_user_by('id', $user_id);

        if (! $user) {
            return new WP_Error(
                'ham_rest_user_not_found',
                __('User not found.', 'headless-access-manager'),
                array( 'status' => 404 )
            );
        }

        // Use the same permission checks as get_item
        return $this->get_item_permissions_check($request);
    }

    /**
     * Prepare a user for response.
     *
     * @param WP_User $user User object.
     * @return WP_REST_Response Response object.
     */
    protected function prepare_user_response($user)
    {
        $data = $this->prepare_user_for_response($user);
        return new WP_REST_Response($data, 200);
    }

    /**
     * Prepare user data for response.
     *
     * @param WP_User $user User object.
     * @return array User data.
     */
    protected function prepare_user_for_response($user)
    {
        $data = array(
            'id'       => $user->ID,
            'username' => $user->user_login,
            'email'    => $user->user_email,
            'name'     => $user->display_name,
            'roles'    => $user->roles,
        );

        // Add HAM-specific user data
        $data['school_id'] = get_user_meta($user->ID, HAM_USER_META_SCHOOL_ID, true);
        $data['class_ids'] = get_user_meta($user->ID, HAM_USER_META_CLASS_IDS, true);

        if (in_array(HAM_ROLE_SCHOOL_HEAD, (array) $user->roles, true)) {
            $data['managed_school_ids'] = get_user_meta($user->ID, HAM_USER_META_MANAGED_SCHOOL_IDS, true);
        }

        // Add capabilities
        $data['capabilities'] = array();

        $all_caps = HAM_Capabilities::get_capabilities_flat();
        foreach ($all_caps as $cap) {
            if (user_can($user->ID, $cap)) {
                $data['capabilities'][] = $cap;
            }
        }

        return $data;
    }
}

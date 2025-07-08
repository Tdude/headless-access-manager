<?php

/**
 * File: inc/api/class-ham-base-controller.php
 *
 * Base controller class for HAM API endpoints.
 *
 * @package HeadlessAccessManager
 */

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class HAM_Base_Controller
 *
 * Base controller class for HAM API endpoints.
 */
abstract class HAM_Base_Controller
{
    /**
     * API namespace.
     *
     * @var string
     */
    protected $namespace = HAM_API_NAMESPACE;

    /**
     * Resource route base.
     *
     * @var string
     */
    protected $rest_base = '';

    /**
     * Register routes for this controller.
     */
    abstract public static function register_routes();

    /**
     * Check if a request has a valid JWT authentication.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return bool|WP_Error True if the authentication is valid, WP_Error otherwise.
     */
    public function validate_jwt($request)
    {
        // Get token from authorization header
        $auth_header = $request->get_header('Authorization');

        if (! $auth_header || empty($auth_header)) {
            return new WP_Error(
                'ham_jwt_auth_no_auth_header',
                __('Authorization header not found.', 'headless-access-manager'),
                array( 'status' => 401 )
            );
        }

        // Extract the token
        list($token_type, $token) = explode(' ', $auth_header, 2);

        if (empty($token_type) || strtolower($token_type) !== 'bearer') {
            return new WP_Error(
                'ham_jwt_auth_bad_auth_header',
                __('Authorization header must be in format: Bearer {token}', 'headless-access-manager'),
                array( 'status' => 401 )
            );
        }

        // Validate the token
        $user_id = $this->validate_token($token);

        if (is_wp_error($user_id)) {
            return $user_id;
        }

        // Set the current user for this request
        wp_set_current_user($user_id);

        return true;
    }

    /**
     * Validate a JWT token and return the user ID.
     *
     * @param string $token JWT token.
     * @return int|WP_Error User ID if token is valid, WP_Error otherwise.
     */
    protected function validate_token($token)
    {
        // Use the firebase/php-jwt library to validate the token.
        // Ensure the library is loaded via Composer.
        if (!class_exists('Firebase\JWT\JWT')) {
            return new WP_Error(
                'ham_jwt_missing_library',
                __('JWT library not found. Please run composer install.', 'headless-access-manager'),
                array('status' => 500)
            );
        }

        if (!defined('HAM_JWT_SECRET_KEY')) {
            return new WP_Error(
                'ham_jwt_no_secret',
                __('JWT secret key is not configured.', 'headless-access-manager'),
                array('status' => 500)
            );
        }

        try {
            // Decode token
            $payload = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key(HAM_JWT_SECRET_KEY, 'HS256'));

            // Check if user_id exists in payload and is numeric
            if (!isset($payload->user_id) || !is_numeric($payload->user_id)) {
                return new WP_Error(
                    'ham_jwt_auth_invalid_payload',
                    __('Invalid token payload.', 'headless-access-manager'),
                    array('status' => 401)
                );
            }

            // Check if user still exists
            $user = get_user_by('id', $payload->user_id);

            if (!$user) {
                return new WP_Error(
                    'ham_jwt_auth_user_not_found',
                    __('User not found.', 'headless-access-manager'),
                    array('status' => 401)
                );
            }

            return $user->ID;
        } catch (Exception $e) {
            return new WP_Error(
                'ham_jwt_auth_invalid_token',
                $e->getMessage(),
                array('status' => 401)
            );
        }
    }

    /**
     * Check if the current user has required capabilities.
     *
     * @param string|array $capabilities Required capabilities (can be single capability or array).
     * @return bool|WP_Error True if user has capability, WP_Error otherwise.
     */
    protected function check_permission($capabilities)
    {
        if (! is_user_logged_in()) {
            return new WP_Error(
                'ham_rest_not_logged_in',
                __('You must be logged in to access this endpoint.', 'headless-access-manager'),
                array( 'status' => 401 )
            );
        }

        if (! is_array($capabilities)) {
            $capabilities = array( $capabilities );
        }

        $user_id = get_current_user_id();

        foreach ($capabilities as $capability) {
            if (user_can($user_id, $capability)) {
                return true;
            }
        }

        return new WP_Error(
            'ham_rest_forbidden',
            __('You do not have permission to access this resource.', 'headless-access-manager'),
            array( 'status' => 403 )
        );
    }

    /**
     * Prepare item for REST response.
     *
     * @param mixed $item    Raw item data.
     * @param array $fields  Fields to include in response.
     * @param array $remove  Fields to remove from response.
     * @return array Prepared item data.
     */
    protected function prepare_item_for_response($item, $fields = array(), $remove = array())
    {
        // If $item is already an array, use it directly
        if (is_array($item)) {
            $data = $item;
        }
        // If $item is an object with 'to_array' method, use it
        elseif (is_object($item) && method_exists($item, 'to_array')) {
            $data = $item->to_array();
        }
        // If $item is a WP_Post, convert it to array
        elseif ($item instanceof WP_Post) {
            $data = array(
                'id'           => $item->ID,
                'title'        => $item->post_title,
                'content'      => $item->post_content,
                'date_created' => $item->post_date,
                'author'       => $item->post_author,
            );
        }
        // If $item is a WP_User, convert it to array
        elseif ($item instanceof WP_User) {
            $data = array(
                'id'       => $item->ID,
                'username' => $item->user_login,
                'email'    => $item->user_email,
                'name'     => $item->display_name,
            );
        }
        // Default to empty array
        else {
            $data = array();
        }

        // Only include specified fields if provided
        if (! empty($fields)) {
            $data = array_intersect_key($data, array_flip($fields));
        }

        // Remove specified fields if provided
        if (! empty($remove)) {
            $data = array_diff_key($data, array_flip($remove));
        }

        return $data;
    }

    /**
     * Get item schema.
     *
     * @return array Item schema.
     */
    public function get_item_schema()
    {
        return array(
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'ham_' . $this->rest_base,
            'type'       => 'object',
            'properties' => array(),
        );
    }
}

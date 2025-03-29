<?php

/**
 * File: inc/api/class-ham-auth-controller.php
 *
 * Authentication controller for API endpoints.
 *
 * @package HeadlessAccessManager
 */

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class HAM_Auth_Controller
 *
 * Handles authentication for the API.
 */
class HAM_Auth_Controller extends HAM_Base_Controller
{
    /**
     * Resource route base.
     *
     * @var string
     */
    protected $rest_base = 'auth';

    /**
     * Register routes for authentication.
     */
    public static function register_routes()
    {
        $controller = new self();

        register_rest_route(
            $controller->namespace,
            '/' . $controller->rest_base . '/token',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $controller, 'generate_token' ),
                    'permission_callback' => '__return_true',
                    'args'                => array(
                        'username' => array(
                            'required'          => true,
                            'sanitize_callback' => 'sanitize_user',
                        ),
                        'password' => array(
                            'required' => true,
                        ),
                    ),
                ),
            )
        );

        register_rest_route(
            $controller->namespace,
            '/' . $controller->rest_base . '/validate',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $controller, 'validate_token_endpoint' ),
                    'permission_callback' => array( $controller, 'validate_permission' ),
                ),
            )
        );
    }

    /**
     * Generate a JWT token for a valid user.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function generate_token($request)
    {
        $username = $request->get_param('username');
        $password = $request->get_param('password');

        // Authenticate user
        $user = wp_authenticate($username, $password);

        if (is_wp_error($user)) {
            return new WP_Error(
                'ham_auth_invalid_credentials',
                __('Invalid credentials.', 'headless-access-manager'),
                array( 'status' => 401 )
            );
        }

        // Generate token
        $token = $this->generate_jwt_token($user);

        if (is_wp_error($token)) {
            return $token;
        }

        // Prepare user data for response
        $user_data = array(
            'id'       => $user->ID,
            'username' => $user->user_login,
            'email'    => $user->user_email,
            'name'     => $user->display_name,
            'roles'    => $user->roles,
        );

        // Add HAM-specific user data
        $user_data['school_id'] = get_user_meta($user->ID, HAM_USER_META_SCHOOL_ID, true);
        $user_data['class_ids'] = get_user_meta($user->ID, HAM_USER_META_CLASS_IDS, true);

        if (in_array(HAM_ROLE_SCHOOL_HEAD, (array) $user->roles, true)) {
            $user_data['managed_school_ids'] = get_user_meta($user->ID, HAM_USER_META_MANAGED_SCHOOL_IDS, true);
        }

        // Prepare capabilities
        $user_data['capabilities'] = array();

        $all_caps = HAM_Capabilities::get_capabilities_flat();
        foreach ($all_caps as $cap) {
            if (user_can($user->ID, $cap)) {
                $user_data['capabilities'][] = $cap;
            }
        }

        return new WP_REST_Response(
            array(
                'token' => $token,
                'user'  => $user_data,
            ),
            200
        );
    }

    /**
     * Generate a JWT token for a user.
     *
     * @param WP_User $user User object.
     * @return string|WP_Error JWT token string or error.
     */
    protected function generate_jwt_token($user)
    {
        // This is a placeholder for JWT token generation logic
        // You'll need to implement this with a proper JWT library

        // Example implementation using FireBase JWT library:
        // try {
        //     $issued_at = time();
        //     $expiration = $issued_at + ( DAY_IN_SECONDS * 7 ); // Token valid for 7 days
        //
        //     $payload = array(
        //         'iss'  => get_bloginfo( 'url' ),
        //         'iat'  => $issued_at,
        //         'nbf'  => $issued_at,
        //         'exp'  => $expiration,
        //         'data' => array(
        //             'user_id' => $user->ID,
        //         ),
        //     );
        //
        //     $secret_key = get_option( 'ham_jwt_secret', false );
        //
        //     if ( ! $secret_key ) {
        //         // Generate a secret key if not exists
        //         $secret_key = bin2hex( random_bytes( 32 ) );
        //         update_option( 'ham_jwt_secret', $secret_key );
        //     }
        //
        //     $token = JWT::encode( $payload, $secret_key, 'HS256' );
        //
        //     return $token;
        // } catch ( Exception $e ) {
        //     return new WP_Error(
        //         'ham_jwt_encode_error',
        //         $e->getMessage(),
        //         array( 'status' => 500 )
        //     );
        // }

        // For demonstration, return a simple placeholder token
        // This is NOT secure and should be replaced with proper JWT implementation
        return base64_encode(json_encode(array(
            'user_id' => $user->ID,
            'exp'     => time() + (DAY_IN_SECONDS * 7),
        )));
    }

    /**
     * Validate a token endpoint.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function validate_token_endpoint($request)
    {
        // Token has already been validated in permission callback
        return new WP_REST_Response(
            array(
                'valid' => true,
                'user'  => array(
                    'id'    => get_current_user_id(),
                    'roles' => wp_get_current_user()->roles,
                ),
            ),
            200
        );
    }

    /**
     * Check if token is valid for token validation endpoint.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return bool|WP_Error True if token is valid, WP_Error otherwise.
     */
    public function validate_permission($request)
    {
        return $this->validate_jwt($request);
    }
}

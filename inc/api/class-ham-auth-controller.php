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

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

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
        
        // Add a development-only endpoint for user information
        if (defined('WP_DEBUG') && WP_DEBUG) {
            //error_log('HAM Auth: Development mode enabled - registering development endpoints');
            
            register_rest_route(
                $controller->namespace,
                '/user/current',
                array(
                    array(
                        'methods'             => WP_REST_Server::READABLE,
                        'callback'            => array( $controller, 'get_current_user_info' ),
                        'permission_callback' => '__return_true', // Allow all access in dev mode
                    ),
                )
            );
        }
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
        try {
            $issued_at = time();
            $expiration = $issued_at + ( DAY_IN_SECONDS * 7 ); // Token valid for 7 days

            // Enhanced payload with user information needed by the frontend
            $payload = array(
                'iss'  => get_bloginfo( 'url' ),
                'iat'  => $issued_at,
                'nbf'  => $issued_at,
                'exp'  => $expiration,
                'user_id' => $user->ID,
                // Add user information fields needed by the frontend
                'user_display_name' => $user->display_name,
                'user_login' => $user->user_login,
                'user_email' => $user->user_email,
                'roles' => $user->roles,
            );

            if (defined('HAM_JWT_SECRET_KEY')) {
                $secret_key = HAM_JWT_SECRET_KEY;
            } else {
                return new WP_Error(
                    'ham_jwt_no_secret',
                    __('JWT secret key is not set.', 'headless-access-manager'),
                    array('status' => 500)
                );
            }

            $token = JWT::encode($payload, $secret_key, 'HS256');
            return $token;
        } catch (Exception $e) {
            return new WP_Error(
                'ham_jwt_encode_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Validate a JWT token and extract user ID.
     *
     * @param string $token JWT token.
     * @return int|bool User ID if valid, false otherwise.
     */
    protected function validate_token($token)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // In development mode, accept any valid JWT
            try {
                if (defined('HAM_JWT_SECRET_KEY')) {
                    $secret_key = HAM_JWT_SECRET_KEY;
                } else {
                    return false;
                }
                $decoded = JWT::decode($token, new Key($secret_key, 'HS256'));
                if (isset($decoded->user_id) && is_numeric($decoded->user_id)) {
                    return (int) $decoded->user_id;
                }
            } catch (Exception $e) {
                return false;
            }
        } else {
            try {
                if (defined('HAM_JWT_SECRET_KEY')) {
                    $secret_key = HAM_JWT_SECRET_KEY;
                } else {
                    return false;
                }
                $decoded = JWT::decode($token, new Key($secret_key, 'HS256'));
                if (!isset($decoded->user_id) || !isset($decoded->exp)) {
                    return false;
                }
                if ($decoded->exp < time()) {
                    return false;
                }
                return (int) $decoded->user_id;
            } catch (Exception $e) {
                return false;
            }
        }
        return false;
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
                    'id'         => get_current_user_id(),
                    'username'   => wp_get_current_user()->user_login,
                    'email'      => wp_get_current_user()->user_email,
                    'name'       => wp_get_current_user()->display_name,
                    'roles'      => wp_get_current_user()->roles,
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
        // Allow all access in development mode (WP_DEBUG enabled)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            //error_log('HAM Auth: Development mode enabled - bypassing authentication validation');
            return true;
        }
        
        // Get token from Authorization header
        $auth_header = $request->get_header('Authorization');
        
        if (!$auth_header) {
            return new WP_Error(
                'ham_missing_auth',
                __('Authorization header not found.', 'headless-access-manager'),
                array('status' => 401)
            );
        }
        
        // Check for Bearer token format
        if (!preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
            return new WP_Error(
                'ham_invalid_auth_format',
                __('Authorization header format is invalid.', 'headless-access-manager'),
                array('status' => 401)
            );
        }
        
        $token = $matches[1];
        
        try {
            // Verify token and get user ID
            $user_id = $this->validate_token($token);
            
            if (!$user_id) {
                return new WP_Error(
                    'ham_invalid_token',
                    __('Invalid token.', 'headless-access-manager'),
                    array('status' => 401)
                );
            }
            
            // Set current user
            wp_set_current_user($user_id);
            return true;
        } catch (Exception $e) {
            return new WP_Error(
                'ham_auth_error',
                $e->getMessage(),
                array('status' => 401)
            );
        }
    }
    
    /**
     * Get auth token from the request.
     *
     * @param WP_REST_Request $request The request object.
     * @return string|null Auth token or null if not found.
     */
    protected function get_auth_token_from_request($request)
    {
        // Get token from Authorization header
        $auth_header = $request->get_header('Authorization');
        
        if ($auth_header) {
            // Check for Bearer token format
            if (preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
                return $matches[1];
            }
        }
        
        // Fallback to X-HAM-Auth header
        $ham_auth_header = $request->get_header('X-HAM-Auth');
        if ($ham_auth_header) {
            return $ham_auth_header;
        }
        
        return null;
    }
    
    /**
     * Get current user information - development mode endpoint
     *
     * @return WP_REST_Response User information
     */
    public function get_current_user_info() {
        // In development mode, use the first admin account
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $admin_users = get_users(array(
                'role' => 'administrator',
                'number' => 1,
            ));
            
            if (!empty($admin_users)) {
                $user = $admin_users[0];
                
                return new WP_REST_Response(array(
                    'id' => $user->ID,
                    'username' => $user->user_login,
                    'email' => $user->user_email,
                    'display_name' => $user->display_name,
                    'roles' => $user->roles,
                ));
            }
        }
        
        // Fallback to current user (which might be anonymous)
        $current_user = wp_get_current_user();
        
        return new WP_REST_Response(array(
            'id' => $current_user->ID,
            'username' => $current_user->user_login,
            'email' => $current_user->user_email,
            'display_name' => $current_user->display_name,
            'roles' => $current_user->roles,
        ));
    }
}

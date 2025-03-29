<?php

/**
 * File: inc/api/class-ham-assessment-templates-controller.php
 *
 * Handles assessment templates for API endpoints.
 *
 * @package HeadlessAccessManager
 */

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class HAM_Assessment_Templates_Controller
 *
 * Handles assessment templates API endpoints.
 */
class HAM_Assessment_Templates_Controller extends HAM_Base_Controller
{
    /**
     * Resource route base.
     *
     * @var string
     */
    protected $rest_base = 'assessment-templates';

    /**
     * Register routes for assessment templates.
     */
    public static function register_routes()
    {
        $controller = new self();

        // Get all templates
        register_rest_route(
            $controller->namespace,
            '/' . $controller->rest_base,
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $controller, 'get_templates' ),
                    'permission_callback' => array( $controller, 'get_templates_permissions_check' ),
                ),
            )
        );

        // Get single template
        register_rest_route(
            $controller->namespace,
            '/' . $controller->rest_base . '/(?P<id>[\d]+)',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $controller, 'get_template' ),
                    'permission_callback' => array( $controller, 'get_template_permissions_check' ),
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

        // Get template form structure
        register_rest_route(
            $controller->namespace,
            '/' . $controller->rest_base . '/(?P<id>[\d]+)/structure',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $controller, 'get_template_structure' ),
                    'permission_callback' => array( $controller, 'get_template_permissions_check' ),
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
     * Get all templates.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function get_templates($request)
    {
        $templates = get_posts(array(
            'post_type'      => 'ham_assessment_tpl',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ));

        if (empty($templates)) {
            return new WP_REST_Response(array(), 200);
        }

        $data = array();

        foreach ($templates as $template) {
            $data[] = $this->prepare_template_for_response($template);
        }

        return new WP_REST_Response($data, 200);
    }

    /**
     * Check if a request has access to get templates.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return bool|WP_Error True if has access, WP_Error otherwise.
     */
    public function get_templates_permissions_check($request)
    {
        $jwt_valid = $this->validate_jwt($request);

        if (is_wp_error($jwt_valid)) {
            return $jwt_valid;
        }

        return true;
    }

    /**
     * Get a single template.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function get_template($request)
    {
        $template_id = $request->get_param('id');
        $template = get_post($template_id);

        if (! $template || $template->post_type !== 'ham_assessment_tpl') {
            return new WP_Error(
                'ham_template_not_found',
                __('Template not found.', 'headless-access-manager'),
                array( 'status' => 404 )
            );
        }

        return new WP_REST_Response($this->prepare_template_for_response($template), 200);
    }

    /**
     * Check if a request has access to get a specific template.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return bool|WP_Error True if has access, WP_Error otherwise.
     */
    public function get_template_permissions_check($request)
    {
        $jwt_valid = $this->validate_jwt($request);

        if (is_wp_error($jwt_valid)) {
            return $jwt_valid;
        }

        return true;
    }

    /**
     * Get template structure.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function get_template_structure($request)
    {
        $template_id = $request->get_param('id');
        $template = get_post($template_id);

        if (! $template || $template->post_type !== 'ham_assessment_tpl') {
            return new WP_Error(
                'ham_template_not_found',
                __('Template not found.', 'headless-access-manager'),
                array( 'status' => 404 )
            );
        }

        // Get template structure
        $structure = get_post_meta($template_id, '_ham_template_structure', true);

        if (empty($structure)) {
            $structure = array();
        }

        return new WP_REST_Response($structure, 200);
    }

    /**
     * Prepare template for response.
     *
     * @param WP_Post $template Template post object.
     * @return array Prepared template data.
     */
    private function prepare_template_for_response($template)
    {
        return array(
            'id'          => $template->ID,
            'title'       => $template->post_title,
            'description' => $template->post_content,
            'slug'        => $template->post_name,
        );
    }
}

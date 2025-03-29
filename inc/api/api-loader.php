<?php

/**
 * Update to inc/api/api-loader.php
 *
 * Add the new controllers to the API loader
 */

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class HAM_API_Loader
 *
 * Initializes and registers all API endpoints.
 */
class HAM_API_Loader
{
    /**
     * Initialize API functionality.
     */
    public static function init()
    {
        // Include controller files
        self::include_files();

        // Register hooks
        add_action('rest_api_init', array( __CLASS__, 'register_routes' ));
    }

    /**
     * Include controller files.
     */
    private static function include_files()
    {
        // Include base controller
        require_once HAM_PLUGIN_DIR . 'inc/api/class-ham-base-controller.php';

        // Include specific controllers
        require_once HAM_PLUGIN_DIR . 'inc/api/class-ham-auth-controller.php';
        require_once HAM_PLUGIN_DIR . 'inc/api/class-ham-users-controller.php';
        require_once HAM_PLUGIN_DIR . 'inc/api/class-ham-data-controller.php';
        require_once HAM_PLUGIN_DIR . 'inc/api/class-ham-assessment-controller.php';
        require_once HAM_PLUGIN_DIR . 'inc/api/class-ham-stats-controller.php';

        // Include new assessment template controllers
        require_once HAM_PLUGIN_DIR . 'inc/api/class-ham-assessment-templates-controller.php';
        require_once HAM_PLUGIN_DIR . 'inc/api/class-ham-assessment-data-controller.php';
    }

    /**
     * Register API routes.
     */
    public static function register_routes()
    {
        // Initialize controllers and register their routes
        HAM_Auth_Controller::register_routes();
        HAM_Users_Controller::register_routes();
        HAM_Data_Controller::register_routes();
        HAM_Assessment_Controller::register_routes();
        HAM_Stats_Controller::register_routes();

        // Register new assessment template routes
        HAM_Assessment_Templates_Controller::register_routes();
        HAM_Assessment_Data_Controller::register_routes();
    }
}

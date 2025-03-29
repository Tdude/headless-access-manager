<?php

/**
 * File: inc/loader.php
 *
 * Main loader class that initializes all plugin components.
 *
 * @package HeadlessAccessManager
 */

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class HAM_Loader
 *
 * Manages loading of all plugin files and initializes components.
 */
class HAM_Loader
{
    /**
     * Singleton instance
     *
     * @var HAM_Loader
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return HAM_Loader
     */
    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize the plugin.
     */
    public function init()
    {
        // Load core functionality
        $this->load_core();

        // Load API if not in admin area or during REST API requests
        if (wp_doing_ajax() || defined('REST_REQUEST') && REST_REQUEST) {
            $this->load_api();
        }

        // Load admin functionality if in admin area
        if (is_admin()) {
            $this->load_admin();
        }
    }

    /**
     * Load core functionality.
     */
    private function load_core()
    {
        // Include helper functions first
        require_once HAM_PLUGIN_DIR . 'inc/helpers/utilities.php';
        require_once HAM_PLUGIN_DIR . 'inc/helpers/permissions.php';

        // Include core files
        require_once HAM_PLUGIN_DIR . 'inc/core/roles.php';
        require_once HAM_PLUGIN_DIR . 'inc/core/capabilities.php';
        require_once HAM_PLUGIN_DIR . 'inc/core/post-types.php';
        require_once HAM_PLUGIN_DIR . 'inc/core/user-meta.php';

        // Initialize core components
        HAM_Roles::init();
        HAM_Capabilities::init();
        HAM_Post_Types::init();
        HAM_User_Meta::init();
    }

    /**
     * Load API functionality.
     */
    private function load_api()
    {
        require_once HAM_PLUGIN_DIR . 'inc/api/api-loader.php';
        HAM_API_Loader::init();
    }

    /**
     * Load admin functionality.
     */
    private function load_admin()
    {
        require_once HAM_PLUGIN_DIR . 'inc/admin/admin-loader.php';
        HAM_Admin_Loader::init();
    }
}

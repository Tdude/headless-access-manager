<?php
/**
 * Handles enqueueing of admin-specific assets (CSS, JS).
 *
 * @package HeadlessAccessManager
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class HAM_Admin_Assets
 */
class HAM_Admin_Assets {

    /**
     * Initialize hooks.
     */
    public static function init() {
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts_styles']);
    }

    /**
     * Enqueue scripts and styles for the admin area.
     *
     * @param string $hook_suffix The current admin page hook.
     */
    public static function enqueue_scripts_styles($hook_suffix) {
        global $post_type;
        $current_screen = get_current_screen();

        // Define our CPTs that use the auto-filter feature
        $ham_cpts_with_filters = [
            HAM_CPT_STUDENT,
            HAM_CPT_TEACHER,
            HAM_CPT_CLASS,
            HAM_CPT_PRINCIPAL,
            HAM_CPT_SCHOOL
        ];

        // Enqueue the dynamic select populator script on specific CPT edit screens
        if ('post.php' === $hook_suffix || 'post-new.php' === $hook_suffix) {
            $common_populator_config = [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'ajaxAction' => 'get_classes_for_school', // Shared AJAX action
                'nonce' => wp_create_nonce('ham_get_classes_for_school_nonce'), // Shared nonce
                'dataKey' => 'school_id',
                'messages'        => [
                    'selectTriggerFirst' => __('Please select a school first.', 'headless-access-manager'),
                    'loading'            => __('Loading classes...', 'headless-access-manager'),
                    'noItemsFound'       => __('No classes found for this school.', 'headless-access-manager'),
                    'errorLoading'       => __('Error loading classes.', 'headless-access-manager'), // General error
                    'ajaxError'          => __('AJAX request failed.', 'headless-access-manager'), // Specific AJAX error
                ],
            ];

            if (HAM_CPT_TEACHER === $post_type) {
                wp_enqueue_script(
                    'ham-dynamic-select-populator',
                    HAM_PLUGIN_URL . 'assets/js/ham-dynamic-select-populator.js',
                    ['jquery'],
                    HAM_VERSION,
                    true
                );
                $teacher_config = array_merge($common_populator_config, [
                    'triggerSelector' => '#ham_teacher_school_id',
                    'targetSelector' => '#ham_teacher_class_ids',
                    'debug' => defined('WP_DEBUG') && WP_DEBUG, // Enable debug only if WP_DEBUG is true
                ]);
                wp_add_inline_script('ham-dynamic-select-populator', 'jQuery(document).ready(function($) { HAM.dynamicSelectPopulator(' . wp_json_encode($teacher_config) . '); });', 'after');
            } elseif (HAM_CPT_STUDENT === $post_type) {
                wp_enqueue_script(
                    'ham-dynamic-select-populator',
                    HAM_PLUGIN_URL . 'assets/js/ham-dynamic-select-populator.js',
                    ['jquery'],
                    HAM_VERSION,
                    true
                );
                $student_config = array_merge($common_populator_config, [
                    'triggerSelector' => '#ham_student_school_id',
                    'targetSelector' => '#ham_student_class_ids',
                    'debug' => defined('WP_DEBUG') && WP_DEBUG,
                ]);
                wp_add_inline_script('ham-dynamic-select-populator', 'jQuery(document).ready(function($) { HAM.dynamicSelectPopulator(' . wp_json_encode($student_config) . '); });', 'after');
            }
        }

        // Only load on edit.php for our specific CPTs
        if ('edit.php' === $hook_suffix && in_array($post_type, $ham_cpts_with_filters)) {
            // General admin enhancements
            wp_enqueue_script(
                'ham-admin-enhancements',
                HAM_PLUGIN_URL . 'assets/js/ham-admin-enhancements.js',
                ['jquery'], // Dependency
                HAM_VERSION, // Versioning
                true // In footer
            );
            
            // AJAX table filtering
            wp_enqueue_script(
                'ham-ajax-table-filters',
                HAM_PLUGIN_URL . 'assets/js/ham-ajax-table-filters.js',
                ['jquery'], // Dependency
                HAM_VERSION, // Versioning
                true // In footer
            );
            
            // Localize the script with data needed for AJAX requests
            wp_localize_script(
                'ham-ajax-table-filters',
                'ham_ajax',
                [
                    'nonce' => wp_create_nonce('ham_ajax_filter_nonce'),
                    'post_type' => $post_type,
                ]
            );
        }
    }
}

// Initialize the asset loader.
HAM_Admin_Assets::init();

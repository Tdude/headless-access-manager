<?php

/**
 * File: inc/admin/admin-loader.php
 *
 * Initializes and loads all admin functionality.
 *
 * @package HeadlessAccessManager
 */

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class HAM_Admin_Loader
 *
 * Initializes and loads all admin functionality.
 */
class HAM_Admin_Loader
{
    /**
     * Initialize admin functionality.
     */
    public static function init()
    {
        self::include_files();
        add_action('admin_menu', array( __CLASS__, 'setup_admin_menu' ));
        add_action('add_meta_boxes', array( __CLASS__, 'setup_meta_boxes' ));
        add_action('save_post', array( __CLASS__, 'save_post_meta' ));

        // Add columns to the class list table
        add_filter('manage_' . HAM_CPT_CLASS . '_posts_columns', array(__CLASS__, 'add_class_list_columns'));
        add_filter('manage_edit-' . HAM_CPT_CLASS . '_sortable_columns', array(__CLASS__, 'set_class_list_sortable_columns'));
        add_action('manage_' . HAM_CPT_CLASS . '_posts_custom_column', array(__CLASS__, 'populate_class_list_columns'), 10, 2);
        add_action('pre_get_posts', array(__CLASS__, 'handle_class_list_sorting'));
    }

    /**
     * Include admin files.
     */
    private static function include_files()
    {
        require_once HAM_PLUGIN_DIR . 'inc/admin/admin-menu.php';
        require_once HAM_PLUGIN_DIR . 'inc/admin/class-ham-user-profile.php';
        require_once HAM_PLUGIN_DIR . 'inc/admin/meta-boxes.php';
        require_once HAM_PLUGIN_DIR . 'inc/admin/class-ham-assessment-templates-admin.php';
        require_once HAM_PLUGIN_DIR . 'inc/admin/class-ham-assessment-meta-boxes.php';
    }

    /**
     * Setup admin menu.
     */
    public static function setup_admin_menu()
    {
        HAM_Admin_Menu::setup_menu();
    }

    /**
     * Setup meta boxes.
     */
    public static function setup_meta_boxes()
    {
        HAM_Meta_Boxes::register_meta_boxes();
    }

    /**
     * Save post meta.
     *
     * @param int $post_id Post ID.
     */
    public static function save_post_meta($post_id)
    {
        HAM_Meta_Boxes::save_meta_boxes($post_id);
    }

    /**
     * Add extra columns to the class list table.
     *
     * @param array $columns Current columns.
     * @return array Modified columns.
     */
    public static function add_class_list_columns($columns)
    {
        $new_columns = array();

        // Insert columns before the date column
        foreach ($columns as $key => $value) {
            if ($key === 'date') {
                $new_columns['school'] = __('School', 'headless-access-manager');
            }
            $new_columns[$key] = $value;
        }

        // If date column doesn't exist, add our column at the end
        if (!isset($columns['date'])) {
            $new_columns['school'] = __('School', 'headless-access-manager');
        }

        return $new_columns;
    }

    /**
     * Populate school columns for the class list table.
     *
     * @param string $column  Column name.
     * @param int    $post_id Post ID.
     */

    public static function populate_class_list_columns($column, $post_id)
    {
        if ($column === 'school') {
            $school_id = get_post_meta($post_id, '_ham_school_id', true);

            if (!empty($school_id)) {
                $school = get_post($school_id);

                if ($school && $school->post_type === HAM_CPT_SCHOOL) {
                    echo esc_html($school->post_title);
                } else {
                    echo '—';
                }
            } else {
                echo '—';
            }
        }
    }

    /**
     * Define sortable columns for the class list table.
     *
     * @param array $columns Current sortable columns.
     * @return array Modified sortable columns.
     */
    public static function set_class_list_sortable_columns($columns)
    {
        $columns['school'] = 'school';
        return $columns;
    }

    /**
     * Handle sorting for custom columns in the class list table.
     *
     * @param WP_Query $query The WP_Query instance.
     */
    public static function handle_class_list_sorting($query)
    {
        // Only handle queries in the admin area for our post type
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== HAM_CPT_CLASS) {
            return;
        }

        // Check if we're sorting by the school column
        $orderby = $query->get('orderby');

        if ('school' === $orderby) {
            // Set meta_key to the school ID meta field
            $query->set('meta_key', '_ham_school_id');

            // Sort by school title, which requires a JOIN and special ordering
            // This approach sorts by the school post title rather than just the ID

            // First, modify the query to join with the posts table for schools
            add_filter('posts_join', array(__CLASS__, 'join_school_posts_table'));

            // Then, modify the orderby clause to sort by school title
            add_filter('posts_orderby', array(__CLASS__, 'orderby_school_title'));
        }
    }

    /**
     * Join the posts table to get school titles.
     *
     * @param string $join Current JOIN clause.
     * @return string Modified JOIN clause.
     */
    public static function join_school_posts_table($join)
    {
        global $wpdb;

        // Join with posts table for school titles
        $join .= " LEFT JOIN {$wpdb->postmeta} AS school_meta ON ({$wpdb->posts}.ID = school_meta.post_id AND school_meta.meta_key = '_ham_school_id') ";
        $join .= " LEFT JOIN {$wpdb->posts} AS school_posts ON (school_meta.meta_value = school_posts.ID) ";

        // Remove the filter to prevent affecting other queries
        remove_filter('posts_join', array(__CLASS__, 'join_school_posts_table'));

        return $join;
    }

    /**
     * Modify the ORDER BY clause to sort by school title.
     *
     * @param string $orderby Current ORDER BY clause.
     * @return string Modified ORDER BY clause.
     */
    public static function orderby_school_title($orderby)
    {
        global $wpdb;

        // Get the sort order from the query
        $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'DESC' ? 'DESC' : 'ASC';

        // Set the ORDER BY to use the school post title
        $orderby = "school_posts.post_title {$order}";

        // Remove the filter to prevent affecting other queries
        remove_filter('posts_orderby', array(__CLASS__, 'orderby_school_title'));

        return $orderby;
    }
}

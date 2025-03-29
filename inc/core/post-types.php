<?php

/**
 * File: inc/core/post-types.php
 *
 * Registers custom post types for the plugin.
 *
 * @package HeadlessAccessManager
 */

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class HAM_Post_Types
 *
 * Handles registration and management of custom post types.
 */
class HAM_Post_Types
{
    /**
     * Initialize post types.
     */
    public static function init()
    {
        add_action('init', array( __CLASS__, 'register_post_types' ));
    }

    /**
     * Register custom post types.
     */
    public static function register_post_types()
    {
        self::register_school_post_type();
        self::register_class_post_type();
        self::register_assessment_post_type();
    }

    /**
     * Register School post type.
     */
    private static function register_school_post_type()
    {
        $labels = array(
            'name'                  => _x('Schools', 'Post type general name', 'headless-access-manager'),
            'singular_name'         => _x('School', 'Post type singular name', 'headless-access-manager'),
            'menu_name'             => _x('Schools', 'Admin Menu text', 'headless-access-manager'),
            'name_admin_bar'        => _x('School', 'Add New on Toolbar', 'headless-access-manager'),
            'add_new'               => __('Add New', 'headless-access-manager'),
            'add_new_item'          => __('Add New School', 'headless-access-manager'),
            'new_item'              => __('New School', 'headless-access-manager'),
            'edit_item'             => __('Edit School', 'headless-access-manager'),
            'view_item'             => __('View School', 'headless-access-manager'),
            'all_items'             => __('All Schools', 'headless-access-manager'),
            'search_items'          => __('Search Schools', 'headless-access-manager'),
            'not_found'             => __('No schools found.', 'headless-access-manager'),
            'not_found_in_trash'    => __('No schools found in Trash.', 'headless-access-manager'),
            'featured_image'        => _x('School Cover Image', 'Overrides the "Featured Image" phrase', 'headless-access-manager'),
            'set_featured_image'    => _x('Set cover image', 'Overrides the "Set featured image" phrase', 'headless-access-manager'),
            'remove_featured_image' => _x('Remove cover image', 'Overrides the "Remove featured image" phrase', 'headless-access-manager'),
            'use_featured_image'    => _x('Use as cover image', 'Overrides the "Use as featured image" phrase', 'headless-access-manager'),
            'archives'              => _x('School archives', 'The post type archive label used in nav menus', 'headless-access-manager'),
            'insert_into_item'      => _x('Insert into school', 'Overrides the "Insert into post" phrase', 'headless-access-manager'),
            'uploaded_to_this_item' => _x('Uploaded to this school', 'Overrides the "Uploaded to this post" phrase', 'headless-access-manager'),
            'filter_items_list'     => _x('Filter schools list', 'Screen reader text for the filter links heading on the post type listing screen', 'headless-access-manager'),
            'items_list_navigation' => _x('Schools list navigation', 'Screen reader text for the pagination heading on the post type listing screen', 'headless-access-manager'),
            'items_list'            => _x('Schools list', 'Screen reader text for the items list heading on the post type listing screen', 'headless-access-manager'),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'school' ),
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'menu_icon'          => 'dashicons-building',
            'supports'           => array( 'title', 'thumbnail' ),
            'show_in_rest'       => true,
        );

        register_post_type(HAM_CPT_SCHOOL, $args);
    }

    /**
     * Register Class post type.
     */
    private static function register_class_post_type()
    {
        $labels = array(
            'name'                  => _x('Classes', 'Post type general name', 'headless-access-manager'),
            'singular_name'         => _x('Class', 'Post type singular name', 'headless-access-manager'),
            'menu_name'             => _x('Classes', 'Admin Menu text', 'headless-access-manager'),
            'name_admin_bar'        => _x('Class', 'Add New on Toolbar', 'headless-access-manager'),
            'add_new'               => __('Add New', 'headless-access-manager'),
            'add_new_item'          => __('Add New Class', 'headless-access-manager'),
            'new_item'              => __('New Class', 'headless-access-manager'),
            'edit_item'             => __('Edit Class', 'headless-access-manager'),
            'view_item'             => __('View Class', 'headless-access-manager'),
            'all_items'             => __('All Classes', 'headless-access-manager'),
            'search_items'          => __('Search Classes', 'headless-access-manager'),
            'not_found'             => __('No classes found.', 'headless-access-manager'),
            'not_found_in_trash'    => __('No classes found in Trash.', 'headless-access-manager'),
            'featured_image'        => _x('Class Cover Image', 'Overrides the "Featured Image" phrase', 'headless-access-manager'),
            'set_featured_image'    => _x('Set cover image', 'Overrides the "Set featured image" phrase', 'headless-access-manager'),
            'remove_featured_image' => _x('Remove cover image', 'Overrides the "Remove featured image" phrase', 'headless-access-manager'),
            'use_featured_image'    => _x('Use as cover image', 'Overrides the "Use as featured image" phrase', 'headless-access-manager'),
            'archives'              => _x('Class archives', 'The post type archive label used in nav menus', 'headless-access-manager'),
            'insert_into_item'      => _x('Insert into class', 'Overrides the "Insert into post" phrase', 'headless-access-manager'),
            'uploaded_to_this_item' => _x('Uploaded to this class', 'Overrides the "Uploaded to this post" phrase', 'headless-access-manager'),
            'filter_items_list'     => _x('Filter classes list', 'Screen reader text for the filter links heading on the post type listing screen', 'headless-access-manager'),
            'items_list_navigation' => _x('Classes list navigation', 'Screen reader text for the pagination heading on the post type listing screen', 'headless-access-manager'),
            'items_list'            => _x('Classes list', 'Screen reader text for the items list heading on the post type listing screen', 'headless-access-manager'),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'class' ),
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'menu_icon'          => 'dashicons-groups',
            'supports'           => array( 'title' ),
            'show_in_rest'       => true,
        );

        register_post_type(HAM_CPT_CLASS, $args);
    }

    /**
     * Register Assessment post type.
     */
    private static function register_assessment_post_type()
    {
        $labels = array(
            'name'                  => _x('Assessments', 'Post type general name', 'headless-access-manager'),
            'singular_name'         => _x('Assessment', 'Post type singular name', 'headless-access-manager'),
            'menu_name'             => _x('Assessments', 'Admin Menu text', 'headless-access-manager'),
            'name_admin_bar'        => _x('Assessment', 'Add New on Toolbar', 'headless-access-manager'),
            'add_new'               => __('Add New', 'headless-access-manager'),
            'add_new_item'          => __('Add New Assessment', 'headless-access-manager'),
            'new_item'              => __('New Assessment', 'headless-access-manager'),
            'edit_item'             => __('Edit Assessment', 'headless-access-manager'),
            'view_item'             => __('View Assessment', 'headless-access-manager'),
            'all_items'             => __('All Assessments', 'headless-access-manager'),
            'search_items'          => __('Search Assessments', 'headless-access-manager'),
            'not_found'             => __('No assessments found.', 'headless-access-manager'),
            'not_found_in_trash'    => __('No assessments found in Trash.', 'headless-access-manager'),
            'filter_items_list'     => _x('Filter assessments list', 'Screen reader text for the filter links heading on the post type listing screen', 'headless-access-manager'),
            'items_list_navigation' => _x('Assessments list navigation', 'Screen reader text for the pagination heading on the post type listing screen', 'headless-access-manager'),
            'items_list'            => _x('Assessments list', 'Screen reader text for the items list heading on the post type listing screen', 'headless-access-manager'),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'assessment' ),
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'menu_icon'          => 'dashicons-clipboard',
            'supports'           => array( 'title', 'author', 'custom-fields' ),
            'show_in_rest'       => true,
        );

        register_post_type(HAM_CPT_ASSESSMENT, $args);
    }
}

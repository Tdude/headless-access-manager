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
class HAM_Post_Types {
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
        self::register_assessment_post_type();
        self::register_student_post_type();
        self::register_teacher_post_type();
        self::register_class_post_type();
        self::register_school_post_type();
        self::register_principal_post_type();
        self::register_school_head_post_type();
    }

    /**
     * Register Assessment post type.
     */
    private static function register_assessment_post_type()
    {
        $labels = array(
            'name'                  => _x('Question Bank', 'Post type general name', 'headless-access-manager'),
            'singular_name'         => _x('Question', 'Post type singular name', 'headless-access-manager'),
            'menu_name'             => _x('Question Bank', 'Admin Menu text', 'headless-access-manager'),
            'name_admin_bar'        => _x('Question', 'Add New on Toolbar', 'headless-access-manager'),
            'add_new'               => __('Add New', 'headless-access-manager'),
            'add_new_item'          => __('Add New Question', 'headless-access-manager'),
            'new_item'              => __('New Question', 'headless-access-manager'),
            'edit_item'             => __('Edit Question', 'headless-access-manager'),
            'view_item'             => __('View Question', 'headless-access-manager'),
            'all_items'             => __('All Questions', 'headless-access-manager'),
            'search_items'          => __('Search Questions', 'headless-access-manager'),
            'not_found'             => __('No questions found.', 'headless-access-manager'),
            'not_found_in_trash'    => __('No questions found in Trash.', 'headless-access-manager'),
            'filter_items_list'     => _x('Filter questions list', 'Screen reader text for the filter links heading on the post type listing screen', 'headless-access-manager'),
            'items_list_navigation' => _x('Questions list navigation', 'Screen reader text for the pagination heading on the post type listing screen', 'headless-access-manager'),
            'items_list'            => _x('Questions list', 'Screen reader text for the items list heading on the post type listing screen', 'headless-access-manager'),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => 'headless-access-manager',
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'assessment' ),
            'capability_type'    => 'ham_assessment',
            'capabilities'       => array(
                'edit_post'          => 'edit_ham_assessment',
                'read_post'          => 'read_ham_assessment',
                'delete_post'        => 'delete_ham_assessment',
                'edit_posts'         => 'edit_ham_assessments',
                'edit_others_posts'  => 'edit_others_ham_assessments',
                'publish_posts'      => 'publish_ham_assessments',
                'read_private_posts' => 'read_private_ham_assessments',
                'create_posts'       => 'edit_ham_assessments',
            ),
            'map_meta_cap'       => true,
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'menu_icon'          => 'dashicons-clipboard',
            'supports'           => array( 'title', 'editor', 'custom-fields' ),
            'show_in_rest'       => true,
        );

        register_post_type(HAM_CPT_ASSESSMENT, $args);
    }

    /**
     * Register Student post type.
     */
    private static function register_student_post_type()
    {
        $labels = array(
            'name'                  => _x('Students', 'Post type general name', 'headless-access-manager'),
            'singular_name'         => _x('Student', 'Post type singular name', 'headless-access-manager'),
            'menu_name'             => _x('Students', 'Admin Menu text', 'headless-access-manager'),
            'name_admin_bar'        => _x('Student', 'Add New on Toolbar', 'headless-access-manager'),
            'add_new'               => __('Add New', 'headless-access-manager'),
            'add_new_item'          => __('Add New Student', 'headless-access-manager'),
            'new_item'              => __('New Student', 'headless-access-manager'),
            'edit_item'             => __('Edit Student', 'headless-access-manager'),
            'view_item'             => __('View Student', 'headless-access-manager'),
            'all_items'             => __('Students', 'headless-access-manager'),
            'search_items'          => __('Search Students', 'headless-access-manager'),
            'not_found'             => __('No students found.', 'headless-access-manager'),
            'not_found_in_trash'    => __('No students found in Trash.', 'headless-access-manager'),
            'featured_image'        => _x('Student Cover Image', 'Overrides the "Featured Image" phrase', 'headless-access-manager'),
            'set_featured_image'    => _x('Set cover image', 'Overrides the "Set featured image" phrase', 'headless-access-manager'),
            'remove_featured_image' => _x('Remove cover image', 'Overrides the "Remove featured image" phrase', 'headless-access-manager'),
            'use_featured_image'    => _x('Use as cover image', 'Overrides the "Use as featured image" phrase', 'headless-access-manager'),
            'archives'              => _x('Student archives', 'The post type archive label used in nav menus', 'headless-access-manager'),
            'insert_into_item'      => _x('Insert into student post type', 'Overrides the "Insert into post" phrase', 'headless-access-manager'),
            'uploaded_to_this_item' => _x('Uploaded to this student post type', 'Overrides the "Uploaded to this post" phrase', 'headless-access-manager'),
            'filter_items_list'     => _x('Filter students list', 'Screen reader text for the filter links heading on the post type listing screen', 'headless-access-manager'),
            'items_list_navigation' => _x('Students list navigation', 'Screen reader text for the pagination heading on the post type listing screen', 'headless-access-manager'),
            'items_list'            => _x('Students list', 'Screen reader text for the items list heading on the post type listing screen', 'headless-access-manager'),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => 'headless-access-manager',
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'student' ),
            'capability_type'    => 'ham_student',
            'capabilities'       => array(
                'edit_post'          => 'edit_ham_student',
                'read_post'          => 'read_ham_student',
                'delete_post'        => 'delete_ham_student',
                'edit_posts'         => 'edit_ham_students',
                'edit_others_posts'  => 'edit_others_ham_students',
                'publish_posts'      => 'publish_ham_students',
                'read_private_posts' => 'read_private_ham_students',
                'create_posts'       => 'edit_ham_students',
            ),
            'map_meta_cap'       => true,
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'menu_icon'          => 'dashicons-id',
            'supports'           => array( 'title' ),
            'show_in_rest'       => true,
        );

        register_post_type(HAM_CPT_STUDENT, $args);
    }

    /**
     * Register Teacher post type.
     */
    private static function register_teacher_post_type()
    {
        $labels = array(
            'name'                  => _x('Teachers', 'Post type general name', 'headless-access-manager'),
            'singular_name'         => _x('Teacher', 'Post type singular name', 'headless-access-manager'),
            'menu_name'             => _x('Teachers', 'Admin Menu text', 'headless-access-manager'),
            'name_admin_bar'        => _x('Teacher', 'Add New on Toolbar', 'headless-access-manager'),
            'add_new'               => __('Add New', 'headless-access-manager'),
            'add_new_item'          => __('Add New Teacher', 'headless-access-manager'),
            'new_item'              => __('New Teacher', 'headless-access-manager'),
            'edit_item'             => __('Edit Teacher', 'headless-access-manager'),
            'view_item'             => __('View Teacher', 'headless-access-manager'),
            'all_items'             => __('Teachers', 'headless-access-manager'),
            'search_items'          => __('Search Teachers', 'headless-access-manager'),
            'not_found'             => __('No teachers found.', 'headless-access-manager'),
            'not_found_in_trash'    => __('No teachers found in Trash.', 'headless-access-manager'),
            'featured_image'        => _x('Teacher Cover Image', 'Overrides the "Featured Image" phrase', 'headless-access-manager'),
            'set_featured_image'    => _x('Set cover image', 'Overrides the "Set featured image" phrase', 'headless-access-manager'),
            'remove_featured_image' => _x('Remove cover image', 'Overrides the "Remove featured image" phrase', 'headless-access-manager'),
            'use_featured_image'    => _x('Use as cover image', 'Overrides the "Use as featured image" phrase', 'headless-access-manager'),
            'archives'              => _x('Teacher archives', 'The post type archive label used in nav menus', 'headless-access-manager'),
            'insert_into_item'      => _x('Insert into teacher post type', 'Overrides the "Insert into post" phrase', 'headless-access-manager'),
            'uploaded_to_this_item' => _x('Uploaded to this teacher post type', 'Overrides the "Uploaded to this post" phrase', 'headless-access-manager'),
            'filter_items_list'     => _x('Filter teachers list', 'Screen reader text for the filter links heading on the post type listing screen', 'headless-access-manager'),
            'items_list_navigation' => _x('Teachers list navigation', 'Screen reader text for the pagination heading on the post type listing screen', 'headless-access-manager'),
            'items_list'            => _x('Teachers list', 'Screen reader text for the items list heading on the post type listing screen', 'headless-access-manager'),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => 'headless-access-manager',
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'teacher' ),
            'capability_type'    => 'ham_teacher',
            'capabilities'       => array(
                'edit_post'          => 'edit_ham_teacher',
                'read_post'          => 'read_ham_teacher',
                'delete_post'        => 'delete_ham_teacher',
                'edit_posts'         => 'edit_ham_teachers',
                'edit_others_posts'  => 'edit_others_ham_teachers',
                'publish_posts'      => 'publish_ham_teachers',
                'read_private_posts' => 'read_private_ham_teachers',
                'create_posts'       => 'edit_ham_teachers',
            ),
            'map_meta_cap'       => true,
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'menu_icon'          => 'dashicons-businessman',
            'supports'           => array( 'title' ),
            'show_in_rest'       => true,
        );

        register_post_type(HAM_CPT_TEACHER, $args);
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
            'all_items'             => __('Classes', 'headless-access-manager'),
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
            'show_in_menu'       => 'headless-access-manager',
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'class' ),
            'capability_type'    => 'ham_class',
            'capabilities'       => array(
                'edit_post'          => 'edit_ham_class',
                'read_post'          => 'read_ham_class',
                'delete_post'        => 'delete_ham_class',
                'edit_posts'         => 'edit_ham_classes',
                'edit_others_posts'  => 'edit_others_ham_classes',
                'publish_posts'      => 'publish_ham_classes',
                'read_private_posts' => 'read_private_ham_classes',
                'create_posts'       => 'edit_ham_classes',
            ),
            'map_meta_cap'       => true,
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
     * Register School post type.
     */
    private static function register_school_post_type()
    {
        error_log('HAM DEBUG: Attempting to register ham_school CPT.'); // DEBUG LINE
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
            'all_items'             => __('Schools', 'headless-access-manager'),
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
            'show_in_menu'       => 'headless-access-manager',
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'school' ),
            'capability_type'    => 'ham_school',
            'capabilities'       => array(
                'edit_post'          => 'edit_ham_school',
                'read_post'          => 'read_ham_school',
                'delete_post'        => 'delete_ham_school',
                'edit_posts'         => 'edit_ham_schools',
                'edit_others_posts'  => 'edit_others_ham_schools',
                'publish_posts'      => 'publish_ham_schools',
                'read_private_posts' => 'read_private_ham_schools',
                'create_posts'       => 'edit_ham_schools',
            ),
            'map_meta_cap'       => true,
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'menu_icon'          => 'dashicons-admin-multisite',
            'supports'           => array( 'title' ),
            'show_in_rest'       => true,
        );

        register_post_type(HAM_CPT_SCHOOL, $args);
        error_log('HAM DEBUG: Successfully called register_post_type for ham_school.'); // DEBUG LINE
    }

    /**
     * Register Principal post type.
     */
    private static function register_principal_post_type()
    {
        error_log('HAM DEBUG: Attempting to register ham_principal CPT.'); // DEBUG LINE
        $labels = array(
            'name'                  => _x('Principals', 'Post type general name', 'headless-access-manager'),
            'singular_name'         => _x('Principal', 'Post type singular name', 'headless-access-manager'),
            'menu_name'             => _x('Principals', 'Admin Menu text', 'headless-access-manager'),
            'name_admin_bar'        => _x('Principal', 'Add New on Toolbar', 'headless-access-manager'),
            'add_new'               => __('Add New', 'headless-access-manager'),
            'add_new_item'          => __('Add New Principal', 'headless-access-manager'),
            'new_item'              => __('New Principal', 'headless-access-manager'),
            'edit_item'             => __('Edit Principal', 'headless-access-manager'),
            'view_item'             => __('View Principal', 'headless-access-manager'),
            'all_items'             => __('Principals', 'headless-access-manager'),
            'search_items'          => __('Search Principals', 'headless-access-manager'),
            'not_found'             => __('No principals found.', 'headless-access-manager'),
            'not_found_in_trash'    => __('No principals found in Trash.', 'headless-access-manager'),
            'featured_image'        => _x('Principal Cover Image', 'Overrides the "Featured Image" phrase', 'headless-access-manager'),
            'set_featured_image'    => _x('Set cover image', 'Overrides the "Set featured image" phrase', 'headless-access-manager'),
            'remove_featured_image' => _x('Remove cover image', 'Overrides the "Remove featured image" phrase', 'headless-access-manager'),
            'use_featured_image'    => _x('Use as cover image', 'Overrides the "Use as featured image" phrase', 'headless-access-manager'),
            'archives'              => _x('Principal archives', 'The post type archive label used in nav menus', 'headless-access-manager'),
            'insert_into_item'      => _x('Insert into principal post type', 'Overrides the "Insert into post" phrase', 'headless-access-manager'),
            'uploaded_to_this_item' => _x('Uploaded to this principal post type', 'Overrides the "Uploaded to this post" phrase', 'headless-access-manager'),
            'filter_items_list'     => _x('Filter principals list', 'Screen reader text for the filter links heading on the post type listing screen', 'headless-access-manager'),
            'items_list_navigation' => _x('Principals list navigation', 'Screen reader text for the pagination heading on the post type listing screen', 'headless-access-manager'),
            'items_list'            => _x('Principals list', 'Screen reader text for the items list heading on the post type listing screen', 'headless-access-manager'),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => 'headless-access-manager',
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'principal' ),
            'capability_type'    => 'ham_principal',
            'capabilities'       => array(
                'edit_post'          => 'edit_ham_principal',
                'read_post'          => 'read_ham_principal',
                'delete_post'        => 'delete_ham_principal',
                'edit_posts'         => 'edit_ham_principals',
                'edit_others_posts'  => 'edit_others_ham_principals',
                'publish_posts'      => 'publish_ham_principals',
                'read_private_posts' => 'read_private_ham_principals',
                'create_posts'       => 'edit_ham_principals',
            ),
            'map_meta_cap'       => true,
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'menu_icon'          => 'dashicons-universal-access',
            'supports'           => array( 'title' ),
            'show_in_rest'       => true,
        );

        register_post_type(HAM_CPT_PRINCIPAL, $args);
        error_log('HAM DEBUG: Successfully called register_post_type for ham_principal.'); // DEBUG LINE
    }

    /**
     * Register School Head post type.
     */
    private static function register_school_head_post_type()
    {
        $labels = array(
            'name'                  => _x('School Heads', 'Post type general name', 'headless-access-manager'),
            'singular_name'         => _x('School Head', 'Post type singular name', 'headless-access-manager'),
            'menu_name'             => _x('School Heads', 'Admin Menu text', 'headless-access-manager'),
            'name_admin_bar'        => _x('School Head', 'Add New on Toolbar', 'headless-access-manager'),
            'add_new'               => __('Add New', 'headless-access-manager'),
            'add_new_item'          => __('Add New School Head', 'headless-access-manager'),
            'new_item'              => __('New School Head', 'headless-access-manager'),
            'edit_item'             => __('Edit School Head', 'headless-access-manager'),
            'view_item'             => __('View School Head', 'headless-access-manager'),
            'all_items'             => __('School Heads', 'headless-access-manager'),
            'search_items'          => __('Search School Heads', 'headless-access-manager'),
            'not_found'             => __('No school heads found.', 'headless-access-manager'),
            'not_found_in_trash'    => __('No school heads found in Trash.', 'headless-access-manager'),
            'featured_image'        => _x('School Head Cover Image', 'Overrides the "Featured Image" phrase', 'headless-access-manager'),
            'set_featured_image'    => _x('Set cover image', 'Overrides the "Set featured image" phrase', 'headless-access-manager'),
            'remove_featured_image' => _x('Remove cover image', 'Overrides the "Remove featured image" phrase', 'headless-access-manager'),
            'use_featured_image'    => _x('Use as cover image', 'Overrides the "Use as featured image" phrase', 'headless-access-manager'),
            'archives'              => _x('School Head archives', 'The post type archive label used in nav menus', 'headless-access-manager'),
            'insert_into_item'      => _x('Insert into school head post type', 'Overrides the "Insert into post" phrase', 'headless-access-manager'),
            'uploaded_to_this_item' => _x('Uploaded to this school head post type', 'Overrides the "Uploaded to this post" phrase', 'headless-access-manager'),
            'filter_items_list'     => _x('Filter school heads list', 'Screen reader text for the filter links heading on the post type listing screen', 'headless-access-manager'),
            'items_list_navigation' => _x('School Heads list navigation', 'Screen reader text for the pagination heading on the post type listing screen', 'headless-access-manager'),
            'items_list'            => _x('School Heads list', 'Screen reader text for the items list heading on the post type listing screen', 'headless-access-manager'),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => 'headless-access-manager',
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'school-head' ),
            'capability_type'    => 'ham_school_head',
            'capabilities'       => array(
                'edit_post'          => 'edit_ham_school_head',
                'read_post'          => 'read_ham_school_head',
                'delete_post'        => 'delete_ham_school_head',
                'edit_posts'         => 'edit_ham_school_heads',
                'edit_others_posts'  => 'edit_others_ham_school_heads',
                'publish_posts'      => 'publish_ham_school_heads',
                'read_private_posts' => 'read_private_ham_school_heads',
                'create_posts'       => 'edit_ham_school_heads',
            ),
            'map_meta_cap'       => true,
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'menu_icon'          => 'dashicons-admin-network',
            'supports'           => array( 'title' ),
            'show_in_rest'       => true,
        );

        register_post_type(HAM_CPT_SCHOOL_HEAD, $args);
    }
}

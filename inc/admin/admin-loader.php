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
    public static function fix_admin_bar_logo_link($wp_admin_bar)
    {
        if (!is_admin() || !is_admin_bar_showing()) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $target = admin_url('admin.php?page=ham-assessments');

        if (is_object($wp_admin_bar) && method_exists($wp_admin_bar, 'add_node')) {
            $wp_admin_bar->add_node(
                array(
                    'id'   => 'wp-logo',
                    'href' => $target,
                )
            );

            $wp_admin_bar->add_node(
                array(
                    'id'   => 'site-name',
                    'href' => $target,
                )
            );
        }
    }

    /**
     * Enqueue Select2 for class CPT edit screens.
     */
    public static function enqueue_select2_for_class_edit($hook) {
        global $post;
        // Only enqueue on supported edit screens
        if (
            ($hook === 'post.php' || $hook === 'post-new.php') &&
            isset($post) && in_array($post->post_type, array(HAM_CPT_CLASS, HAM_CPT_ASSESSMENT), true)
        ) {

            //error_log('HAM DEBUG: enqueue_select2_for_class_edit IS FIRING for post ID: ' . (isset($post->ID) ? $post->ID : 'N/A') . ' on hook: ' . $hook);

            $select2_js_url = HAM_PLUGIN_URL . 'assets/vendor/select2/js/select2.min.js';
            $select2_css_url = HAM_PLUGIN_URL . 'assets/vendor/select2/css/select2.min.css';
            $select2_js_path = HAM_PLUGIN_DIR . 'assets/vendor/select2/js/select2.min.js';
            $select2_css_path = HAM_PLUGIN_DIR . 'assets/vendor/select2/css/select2.min.css';

            if (file_exists($select2_js_path) && file_exists($select2_css_path)) {
                wp_register_script('ham-select2', $select2_js_url, array('jquery'), '4.1.0', true);
                wp_register_style('ham-select2', $select2_css_url, array(), '4.1.0');
                wp_enqueue_script('ham-select2');
                wp_enqueue_style('ham-select2');
            }
        }
    }

    /**
     * Enqueue CPT theming styles.
     */
    public static function enqueue_cpt_theming_styles($hook_suffix) {
        $screen = get_current_screen();
        $target_cpts = [
            HAM_CPT_STUDENT,
            HAM_CPT_TEACHER,
            HAM_CPT_PRINCIPAL,
            HAM_CPT_SCHOOL_HEAD,
            HAM_CPT_ASSESSMENT,
            HAM_CPT_SCHOOL,
            HAM_CPT_CLASS,
        ];

        if ($screen && isset($screen->post_type) && in_array($screen->post_type, $target_cpts) &&
            ($hook_suffix === 'edit.php' || $hook_suffix === 'post.php' || $hook_suffix === 'post-new.php')) {
            
            // Define the correct path to the CSS file from the plugin's root
            // HAM_PLUGIN_URL is assumed to be defined and point to the plugin's base URL
            $css_url = HAM_PLUGIN_URL . 'assets/css/template-editor.css';
            $css_path = HAM_PLUGIN_DIR . 'assets/css/template-editor.css'; // For file_exists check

            if (file_exists($css_path)) {
                wp_enqueue_style(
                    'ham-template-editor', // Handle for this stylesheet
                    $css_url,
                    [], // No dependencies for this base style
                    '1.0.1' // Increment version for cache busting
                );
            }
        }
    }

    /**
     * Initialize admin functionality.
     */
    public static function init()
    {
        // Enqueue CPT Theming Styles
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_cpt_theming_styles'));

        add_action('admin_bar_menu', array(__CLASS__, 'fix_admin_bar_logo_link'), 1);

        // Class CPT Meta Boxes (already refactored)
        add_action('add_meta_boxes_' . HAM_CPT_CLASS, ['HAM_Class_Meta_Boxes', 'register_meta_boxes']);
        add_action('save_post_' . HAM_CPT_CLASS, ['HAM_Class_Meta_Boxes', 'save_meta_boxes']);

        // School CPT Meta Boxes (without columns - now handled by HAM_School_Admin_List_Table)
        add_action('add_meta_boxes_' . HAM_CPT_SCHOOL, ['HAM_School_Meta_Boxes', 'register_meta_boxes']);
        add_action('save_post_' . HAM_CPT_SCHOOL, ['HAM_School_Meta_Boxes', 'save_meta_boxes']);

        // Teacher CPT Meta Boxes
        add_action('add_meta_boxes_' . HAM_CPT_TEACHER, ['HAM_Teacher_Meta_Boxes', 'register_meta_boxes']);
        add_action('save_post_' . HAM_CPT_TEACHER, ['HAM_Teacher_Meta_Boxes', 'save_meta_boxes']);

        // Principal CPT Meta Boxes (no columns - columns now handled by HAM_Principal_Admin_List_Table)
        // Legacy column registration removed to prevent duplicate columns
        HAM_Principal_Meta_Boxes::init(); // Initialize other functionality
        // Principal CPT Meta Boxes (New)
        add_action('add_meta_boxes_' . HAM_CPT_PRINCIPAL, ['HAM_Principal_Meta_Boxes', 'register_meta_boxes']);
        add_action('save_post_' . HAM_CPT_PRINCIPAL, ['HAM_Principal_Meta_Boxes', 'save_meta_boxes']);

        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_select2_for_class_edit'));

        self::include_files();
        
        // Initialize AJAX handlers now that the file has been included
        HAM_Ajax_Handlers::init();
        
        // Setup hooks
        add_action('admin_menu', array( __CLASS__, 'setup_admin_menu' ));
        // Remove old hooks for class, school, teacher meta boxes that are now handled by specific classes
        // add_action('add_meta_boxes_' . HAM_CPT_CLASS, array('HAM_Meta_Boxes', 'register_class_meta_box')); // Removed
        // add_action('add_meta_boxes_' . HAM_CPT_SCHOOL, array('HAM_Meta_Boxes', 'register_school_principal_meta_box')); // Removed
        add_action('add_meta_boxes_' . HAM_CPT_ASSESSMENT, array('HAM_Meta_Boxes', 'register_assessment_meta_boxes')); // Keep Assessment
        // add_action('add_meta_boxes_' . HAM_CPT_TEACHER, array('HAM_Meta_Boxes', 'register_teacher_classes_meta_box')); // Removed
        
        add_action('save_post', array( __CLASS__, 'save_post_meta' )); // General save, review if still needed for these CPTs
        // add_action('save_post', array('HAM_Meta_Boxes', 'save_school_principal_meta_box')); // Removed
        // add_action('save_post', array('HAM_Meta_Boxes', 'save_teacher_classes_meta_box')); // Removed

        // Hook for modifying student columns and rendering the custom column
        add_filter('manage_' . HAM_CPT_STUDENT . '_posts_columns', array(__CLASS__, 'modify_student_admin_columns'));
        add_action('manage_' . HAM_CPT_STUDENT . '_posts_custom_column', array(__CLASS__, 'render_student_classes_column'), 10, 2);

        // Class columns and sorting now handled by HAM_Class_Admin_List_Table
        
        // Hooks for other CPTs
        add_filter('manage_' . HAM_CPT_ASSESSMENT . '_posts_columns', array(__CLASS__, 'modify_generic_cpt_columns'));
        add_filter('manage_' . HAM_CPT_SCHOOL . '_posts_columns', array(__CLASS__, 'modify_generic_cpt_columns'));
        // School principals column is handled by HAM_School_Admin_List_Table
        add_filter('manage_' . HAM_CPT_TEACHER . '_posts_columns', array(__CLASS__, 'modify_generic_cpt_columns'));
        // Principal CPT columns now handled by HAM_Principal_Admin_List_Table
        add_filter('manage_' . HAM_CPT_ASSESSMENT . '_posts_columns', array(__CLASS__, 'modify_generic_cpt_columns'));
        add_filter('manage_' . HAM_CPT_SCHOOL_HEAD . '_posts_columns', array(__CLASS__, 'modify_generic_cpt_columns'));


        // Add Actions column for CRUD to Teachers and Students CPTs
        add_filter('manage_student_posts_columns', array(__CLASS__, 'add_actions_column'));
        add_filter('manage_teacher_posts_columns', array(__CLASS__, 'add_actions_column'));
        add_action('manage_student_posts_custom_column', array(__CLASS__, 'render_actions_column'), 10, 2);
        add_action('manage_teacher_posts_custom_column', array(__CLASS__, 'render_actions_column'), 10, 2);

        // Add School filter dropdown to Teachers, Students, Classes, Principals CPTs
        add_action('restrict_manage_posts', array(__CLASS__, 'add_school_filter_dropdown'));
        add_action('pre_get_posts', array(__CLASS__, 'filter_cpt_by_school'));

        // Register the generic CRUD AJAX handler
        HAM_CRUD_Handler::register();

        // Enqueue the CRUD JS for relevant admin screens
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_crud_script'));

        // Restrict admin area access for custom roles
        add_action('admin_init', array(__CLASS__, 'restrict_admin_area_access'));

        // Set primary column for CPTs to ensure row actions are present
        add_filter('list_table_primary_column', array(__CLASS__, 'set_primary_column'), 10, 2);


    }

    /**
     * Enqueue CRUD JS and localize ajaxUrl
     */
    public static function enqueue_crud_script($hook)
    {
        global $typenow;
        // Only load on supported CPT admin pages
        $cpts = array('student', 'teacher', 'principal', 'school_head', 'class', 'assessment'); // update as needed
        if (in_array($typenow, $cpts)) {
            wp_enqueue_script('ham-crud', HAM_PLUGIN_URL . 'assets/js/ham-crud.js', array('jquery'), '1.0', true);
            wp_localize_script('ham-crud', 'hamGlobals', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
            ));
        }
    }

    /**
     * Add a school filter dropdown to the admin list pages.
     * This is a legacy implementation that is being phased out in favor of the new admin list table classes.
     * 
     * Note: All CPTs now use the modern admin list table classes with unified filtering,
     * so this legacy filter is now disabled to prevent duplicate filters.
     */
    public static function add_school_filter_dropdown() {
        global $typenow;
        
        // All CPTs now use the new admin list table filter system
        // Legacy filter is disabled to prevent duplicate filters
        return;
        
        // Legacy code below is kept for reference but won't execute
        $selected = isset($_GET['ham_school_filter']) ? intval($_GET['ham_school_filter']) : '';
        $schools = function_exists('ham_get_schools') ? ham_get_schools() : array();
        echo '<select name="ham_school_filter" class="ham-ajax-filter"><option value="">'.esc_html__('All Schools', 'headless-access-manager').'</option>';
        foreach ($schools as $school) {
            echo '<option value="'.esc_attr($school->ID).'"'.selected($selected, $school->ID, false).'>'.esc_html($school->post_title).'</option>';
        }
        echo '</select>';
        
        // Ensure JS is enqueued for AJAX filtering
        add_action('admin_enqueue_scripts', function() {
            global $typenow;
            $cpts = array(HAM_CPT_CLASS, HAM_CPT_PRINCIPAL, HAM_CPT_STUDENT, HAM_CPT_TEACHER, HAM_CPT_SCHOOL);
            if (in_array($typenow, $cpts)) {
                wp_enqueue_script(
                    'ham-ajax-filters',
                    HAM_PLUGIN_URL . 'assets/js/ham-ajax-table-filters.js',
                    ['jquery'],
                    HAM_VERSION,
                    true
                );
                
                wp_localize_script('ham-ajax-filters', 'hamAjaxFilters', [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('ham_ajax_filter_nonce'),
                    'postType' => $typenow,
                    'i18n' => [
                        'loading' => __('Loading...', 'headless-access-manager'),
                        'error' => __('Error loading content', 'headless-access-manager'),
                        'noResults' => __('No results found', 'headless-access-manager'),
                    ]
                ]);
            }
        });
    }

    /**
     * Filter CPTs by selected school in admin list.
     */
    public static function filter_cpt_by_school($query) {
        global $pagenow, $typenow;
        if (!is_admin() || $pagenow !== 'edit.php' || !$query->is_main_query()) {
            return;
        }

        $cpts = array(HAM_CPT_CLASS); // CPTs that use this filter (Principal now uses generic system)
        if (!in_array($typenow, $cpts)) {
            return;
        }

        if (!empty($_GET['ham_school_filter'])) {
            $school_id = intval($_GET['ham_school_filter']);
            if ($typenow === HAM_CPT_PRINCIPAL) {
                // Principals: filter by managed schools (meta is array or CSV)
                $query->set('meta_query', array(
                    array(
                        'key' => '_ham_managed_school_ids',
                        'value' => '"' . $school_id . '"',
                        'compare' => 'LIKE'
                    )
                ));
            } else {
                // Classes: filter by _ham_school_id
                $query->set('meta_key', '_ham_school_id');
                $query->set('meta_value', $school_id);
            }
        }
    }

    /**
     * Sets the primary column for our CPTs to ensure row actions are displayed correctly.
     *
     * @param string $default   The default primary column.
     * @param string $screen_id The current screen ID.
     * @return string The primary column name.
     */
    public static function set_primary_column($default, $screen_id) {
        $cpts = [
            'edit-' . HAM_CPT_SCHOOL,
            'edit-' . HAM_CPT_TEACHER,
            'edit-' . HAM_CPT_PRINCIPAL,
            'edit-' . HAM_CPT_ASSESSMENT,
            'edit-' . HAM_CPT_SCHOOL_HEAD,
        ];

        if (in_array($screen_id, $cpts, true)) {
            return 'title';
        }

        return $default;
    }



    /**
     * Include admin files.
     */
    private static function include_files()
    {
        require_once HAM_PLUGIN_DIR . 'inc/admin/admin-menu.php';
        require_once HAM_PLUGIN_DIR . 'inc/admin/class-ham-user-profile.php';
        require_once HAM_PLUGIN_DIR . 'inc/admin/class-ham-ajax-handlers.php';
        
        // Base admin list table class must be included first
        require_once HAM_PLUGIN_DIR . 'inc/admin/class-ham-base-admin-list-table.php';
        
        // CPT-specific admin list tables
        require_once HAM_PLUGIN_DIR . 'inc/admin/class-ham-school-admin-list-table.php';
        require_once HAM_PLUGIN_DIR . 'inc/admin/class-ham-student-admin-list-table.php';
        require_once HAM_PLUGIN_DIR . 'inc/admin/class-ham-teacher-admin-list-table.php';
        require_once HAM_PLUGIN_DIR . 'inc/admin/class-ham-class-admin-list-table.php';
        require_once HAM_PLUGIN_DIR . 'inc/admin/class-ham-principal-admin-list-table.php';
        
        // Refactored Meta Box Handlers
        require_once HAM_PLUGIN_DIR . 'inc/admin/meta-boxes/class-ham-class-meta-boxes.php';
        require_once HAM_PLUGIN_DIR . 'inc/admin/meta-boxes/class-ham-school-meta-boxes.php';
        require_once HAM_PLUGIN_DIR . 'inc/admin/meta-boxes/class-ham-teacher-meta-boxes.php';
        require_once HAM_PLUGIN_DIR . 'inc/admin/meta-boxes/class-ham-principal-meta-boxes.php';

        require_once HAM_PLUGIN_DIR . 'inc/admin/meta-boxes.php'; // Contains Assessment CPT meta boxes and potentially others to be refactored or kept.
        
        require_once HAM_PLUGIN_DIR . 'inc/admin/class-ham-assessment-templates-admin.php';
        require_once HAM_PLUGIN_DIR . 'inc/admin/class-ham-assessment-meta-boxes.php';
        require_once HAM_PLUGIN_DIR . 'inc/admin/class-ham-assessment-manager.php';
        require_once HAM_PLUGIN_DIR . 'inc/admin/class-ham-crud-handler.php';
    }

    /**
     * Setup admin menu.
     */
    /**
     * Injects a hidden modal for CRUD operations in the admin footer.
     */
    public static function inject_crud_modal() {
        global $typenow;
        $cpts = array('student', 'teacher', 'class');
        if (!in_array($typenow, $cpts)) return;
        ?>
        <div id="ham-crud-modal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
            <div style="background:#fff; padding:2em; max-width:500px; margin:auto; border-radius:8px; position:relative;">
                <button id="ham-crud-modal-close" style="position:absolute; top:8px; right:8px;">&times;</button>
                <div id="ham-crud-modal-content">
                    <!-- Dynamic form content injected by JS -->
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Adds an Add New button above the list table for CRUD modal.
     */
    public static function add_crud_add_new_button() {
        global $typenow;
        $cpts = array('student', 'teacher', 'class');
        if (!in_array($typenow, $cpts)) return;
        echo '<button type="button" class="button button-primary" id="ham-crud-add-new" style="margin-right: 10px;">' . __('Add New', 'headless-access-manager') . '</button>';
    }



    public static function setup_admin_menu()
    {
        HAM_Admin_Menu::setup_menu();
    }

    /**
     * Setup meta boxes.
     */
    public static function setup_meta_boxes()
    {

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
     * Add Classes column to Students list table.
     */
    public static function add_student_classes_column($columns) {
        $columns['ham_student_classes'] = __('Class', 'headless-access-manager');
        return $columns;
    }

    /**
     * Render content for the 'Class(es)' column in the Student CPT list table.
     * This is the CORRECT version using get_post_meta for the Student CPT.
     *
     * @param string $column_name The name of the custom column.
     * @param int    $post_id     The ID of the current student post.
     */
    public static function render_student_classes_column($column_name, $post_id) {
        if ($column_name === 'ham_student_classes') {
            // Students are CPTs, their class assignments are stored as post meta.
            $class_ids = get_post_meta($post_id, '_ham_class_ids', true);
            if (!empty($class_ids) && is_array($class_ids)) {
                $class_links = [];
                foreach ($class_ids as $class_id) {
                    $class_post = get_post($class_id);
                    if ($class_post) {
                        $edit_link = get_edit_post_link($class_id);
                        $class_links[] = $edit_link ? '<a href="' . esc_url($edit_link) . '">' . esc_html($class_post->post_title) . '</a>' : esc_html($class_post->post_title);
                    }
                }
                echo implode(', ', $class_links);
            } else {
                echo '—';
            }
        }
    }

    /**
     * Add Actions column to list tables.
     */
    public static function add_actions_column($columns) {
        $columns['ham_crud_actions'] = __('Actions', 'headless-access-manager');
        return $columns;
    }

    /**
     * Render Actions column for CRUD.
     */
    public static function render_actions_column($column, $post_id) {
        if ($column === 'ham_crud_actions') {
            echo '<button class="button ham-crud-edit" data-id="' . esc_attr($post_id) . '">' . __('Edit', 'headless-access-manager') . '</button> ';
            echo '<button class="button button-danger ham-crud-delete" data-id="' . esc_attr($post_id) . '">' . __('Delete', 'headless-access-manager') . '</button>';
        }
    }

    /**
     * Modify columns for the Student CPT list table.
     *
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public static function modify_student_admin_columns($columns) {

        // Rename 'title' column
        if (isset($columns['title'])) {
            $columns['title'] = __('Student Name', 'headless-access-manager');
        }


        // Remove 'date' column
        unset($columns['date']);
        // Remove any other 'Class' column if it's a duplicate
        if (isset($columns['class']) && $columns['class'] !== __('Class', 'headless-access-manager')) {
             unset($columns['class']);
        }
        // Ensure 'cb' (checkbox) is first
        if (isset($columns['cb'])) {
            $cb = $columns['cb'];
            unset($columns['cb']);
            $columns = array_merge(['cb' => $cb], $columns);
        }
        return $columns;
    }

    /**
     * Modify columns for the Class CPT list table.
     *
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public static function modify_class_admin_columns($columns) {
        // Rename 'title' column
        if (isset($columns['title'])) {
            $columns['title'] = __('Class Name', 'headless-access-manager');
        }
        // Add 'Assigned Teachers' column
        $new_columns = [];
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') { // Place after the renamed title
                $new_columns['ham_school_name'] = __('School', 'headless-access-manager');
                $new_columns['ham_assigned_teachers'] = __('Assigned Teachers', 'headless-access-manager');
            }
        }
        $columns = $new_columns;

        // Remove 'date' column
        unset($columns['date']);
        // Ensure 'cb' (checkbox) is first
        if (isset($columns['cb'])) {
            $cb = $columns['cb'];
            unset($columns['cb']);
            $columns = array_merge(['cb' => $cb], $columns);
        }
        return $columns;
    }

    /**
     * Render content for the 'Assigned Teachers' column in the Class CPT list table.
     *
     * @param string $column_name The name of the custom column.
     * @param int    $class_id    The ID of the current class post.
     */
    public static function render_class_assigned_teachers_column($column_name, $class_id) {
        if ($column_name === 'ham_assigned_teachers') {
            $args = [
                'post_type' => HAM_CPT_TEACHER,
                'posts_per_page' => -1,
                'meta_query' => [
                    [
                        'key' => '_ham_class_ids', 
                        'value' => 'i:' . $class_id . ';', // Reverted to this LIKE comparison
                        'compare' => 'LIKE',
                    ],
                ],
            ];
            $teachers_query = new WP_Query($args);
            if ($teachers_query->have_posts()) {
                $teacher_links = [];
                while ($teachers_query->have_posts()) {
                    $teachers_query->the_post();
                    $teacher_id = get_the_ID();
                    $edit_link = get_edit_post_link($teacher_id);
                    $teacher_links[] = $edit_link ? '<a href="' . esc_url($edit_link) . '">' . esc_html(get_the_title()) . '</a>' : esc_html(get_the_title());
                }
                wp_reset_postdata();
                echo implode(', ', $teacher_links);
            } else {
                echo '—'; // Using em dash for no data
            }
        }
    }

    /**
     * Render content for the 'School' column in the Class CPT list table.
     *
     * @param string $column_name The name of the custom column.
     * @param int    $class_id    The ID of the current class post.
     */
    public static function render_class_school_column($column_name, $class_id) {
        if ($column_name === 'ham_school_name') {
            // Get the associated school ID from class meta
            $school_id = get_post_meta($class_id, '_ham_school_id', true);
            
            if (!empty($school_id)) {
                // Get the school name
                $school = get_post($school_id);
                if ($school && $school->post_type === HAM_CPT_SCHOOL) {
                    echo '<a href="' . get_edit_post_link($school_id) . '">' . esc_html($school->post_title) . '</a>';
                } else {
                    echo '<span class="ham-meta-empty">' . esc_html__('School not found', 'headless-access-manager') . '</span>';
                }
            } else {
                echo '<span class="ham-meta-empty">' . esc_html__('No school assigned', 'headless-access-manager') . '</span>';
            }
        }
    }

    /**
     * Returns a map of CPTs to their desired admin column titles.
     *
     * @return array
     */
    private static function get_cpt_column_titles() {
        return [
            HAM_CPT_SCHOOL      => __('School Name', 'headless-access-manager'),
            HAM_CPT_TEACHER     => __('Teacher Name', 'headless-access-manager'),
            HAM_CPT_PRINCIPAL   => __('Principal Name', 'headless-access-manager'),
            HAM_CPT_ASSESSMENT  => __('Assessment Title', 'headless-access-manager'),
            HAM_CPT_SCHOOL_HEAD => __('School Head Name', 'headless-access-manager'),
        ];
    }

    /**
     * Modify admin columns for generic CPTs.
     *
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public static function modify_generic_cpt_columns($columns) {
        global $typenow;
        $cpt_column_titles = self::get_cpt_column_titles();

        // Rename 'title' column
        if (isset($cpt_column_titles[$typenow]) && isset($columns['title'])) {
            $columns['title'] = $cpt_column_titles[$typenow];
        }

        // Remove 'date' column
        unset($columns['date']);

        // The 'cb' checkbox column is left untouched to let WordPress handle it.

        return $columns;
    }

    /**
     * Adds the 'Assigned Teachers' column to the Class CPT list table.
     *
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public static function add_class_assigned_teachers_column($columns) {
        $new_columns = [];
        $inserted = false;
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') { // Insert after the 'title' column
                $new_columns['ham_class_assigned_teachers'] = __('Assigned Teachers', 'headless-access-manager');
                $inserted = true;
            }
        }
        if (!$inserted) { // Fallback if 'title' not found
             $new_columns['ham_class_assigned_teachers'] = __('Assigned Teachers', 'headless-access-manager');
        }
        return $new_columns;
    }

    /**
     * Restrict access to the WordPress admin area for non-administrator roles,
     * except for users with specific capabilities (e.g., managing their own profile or specific CPTs).
     */
    public static function restrict_admin_area_access() {
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return; // Allow AJAX requests for all logged-in users.
        }

        // Allow users with 'edit_posts' capability (covers most roles that need some admin access).
        // You might want to refine this to a more specific capability if needed.
        if (current_user_can('edit_posts')) {
            return;
        }

        // Allow access for specific custom roles if they need to access wp-admin for specific tasks
        // (e.g., if they have custom menu pages).
        // Example: Check for your plugin's custom roles.
        $user = wp_get_current_user();
        $allowed_roles = array(
            HAM_ROLE_PRINCIPAL, 
            HAM_ROLE_TEACHER, 
            HAM_ROLE_STUDENT, 
            HAM_ROLE_SCHOOL_HEAD 
            // Add any other custom roles that should have limited admin access.
        );

        $user_roles = (array) $user->roles;
        $is_allowed_custom_role = false;
        foreach ($allowed_roles as $allowed_role) {
            if (in_array($allowed_role, $user_roles)) {
                // Further checks can be added here if these roles should only see specific pages.
                // For now, if they have one of these roles, we assume they might have a reason
                // to be in wp-admin (e.g., profile page, or a specific CPT list they manage).
                // A more robust solution would check specific capabilities for specific pages.
                $is_allowed_custom_role = true;
                break;
            }
        }

        if ($is_allowed_custom_role) {
            // If you want to redirect them to a specific page within wp-admin instead of their profile:
            // Example: wp_redirect(admin_url('edit.php?post_type=your_cpt')); exit;
            // For now, allowing them into wp-admin if they have a designated role.
            // They will be limited by their capabilities for what they can see/do.
            return;
        }

        // If the user is not an administrator and doesn't have 'edit_posts' capability,
        // and is not one of the specifically allowed custom roles, redirect them.
        // Redirect non-admins to the site's homepage.
        if (!current_user_can('manage_options')) { // 'manage_options' is a capability only admins typically have.
            wp_redirect(home_url());
            exit;
        }
    }

} // End of HAM_Admin_Loader class


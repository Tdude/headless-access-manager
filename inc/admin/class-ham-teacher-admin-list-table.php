<?php
/**
 * Customizes the admin list table for the Teacher CPT.
 *
 * @package HeadlessAccessManager
 */

if (!defined('ABSPATH')) {
    exit;
}

// Make sure the base class is loaded
require_once __DIR__ . '/class-ham-base-admin-list-table.php';

/**
 * Class HAM_Teacher_Admin_List_Table
 */
class HAM_Teacher_Admin_List_Table extends HAM_Base_Admin_List_Table {

    /**
     * The post type this class manages.
     *
     * @var string
     */
    protected $post_type = HAM_CPT_TEACHER;
    
    /**
     * Constructor. Hooks into WordPress actions and filters.
     */
    public function __construct() {
        // Call parent constructor to setup hooks
        parent::__construct(HAM_CPT_TEACHER);
        
        // Register filters for this CPT
        $this->register_teacher_filters();
        
        // Note: AJAX handler is registered in the parent class (HAM_Base_Admin_List_Table)
    }
    
    /**
     * Register filters specific to the Teacher CPT.
     */
    protected function register_teacher_filters() {
        // School filter
        $this->register_filter('ham_filter_school_id', [
            'type' => 'meta',
            'meta_key' => '_ham_school_id',
            'label' => __('School', 'headless-access-manager'),
            'placeholder' => __('Filter by School', 'headless-access-manager'),
            'field_type' => 'select',
            'options_callback' => [$this, 'get_school_options']
        ]);
        
        // Any other teacher-specific filters can be registered here
    }
    
    /**
     * Get options for the school filter dropdown.
     *
     * @return array Array of school IDs and names.
     */
    public function get_school_options() {
        $school_options = [];
        
        // Get all schools
        $schools = get_posts([
            'post_type' => HAM_CPT_SCHOOL,
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        
        foreach ($schools as $school) {
            $school_options[$school->ID] = $school->post_title;
        }
        
        return $school_options;
    }

    /**
     * Add custom columns to the teacher list table.
     *
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public function add_admin_columns($columns) {
        // Use standardized column helper for consistent naming
        return $this->standardize_columns($columns, [
            'school' => __('School', 'headless-access-manager'),
            'classes' => __('Classes', 'headless-access-manager')
        ]);
    }

    /**
     * Render content for custom columns.
     *
     * @param string $column_name The name of the column to render.
     * @param int    $post_id     The ID of the current post.
     */
    public function render_custom_columns($column_name, $post_id) {
        // CRITICAL FIX: Track cell rendering to prevent duplication during AJAX
        static $rendered_cells = array();
        $cell_key = $post_id . '_' . $column_name;
        
        // Skip if we've already rendered this cell (happens during AJAX filtering)
        if (defined('DOING_AJAX') && DOING_AJAX && isset($rendered_cells[$cell_key])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                //error_log("HAM TEACHER TABLE: Skipping duplicate render for {$column_name} cell on post {$post_id}");
            }
            return;
        }
        
        // Mark this cell as rendered
        $rendered_cells[$cell_key] = true;

        switch ($column_name) {
            case 'school':
            case 'ham_school':
                $school_id = get_post_meta($post_id, '_ham_school_id', true);
                if ($school_id) {
                    $edit_link = get_edit_post_link($school_id);
                    if ($edit_link) {
                        echo '<a href="' . esc_url($edit_link) . '">' . esc_html(get_the_title($school_id)) . '</a>';
                    } else {
                        echo esc_html(get_the_title($school_id));
                    }
                } else {
                    echo '&mdash;';
                }
                break;

            case 'classes':
            case 'ham_classes':
                // Get class IDs assigned to this teacher
                $class_ids = get_post_meta($post_id, '_ham_class_ids', true); 
                if (!empty($class_ids) && is_array($class_ids)) {
                    $class_links = [];
                    foreach ($class_ids as $class_id) {
                        $edit_link = get_edit_post_link($class_id);
                        $class_links[] = $edit_link ? 
                            '<a href="' . esc_url($edit_link) . '">' . esc_html(get_the_title($class_id)) . '</a>' : 
                            esc_html(get_the_title($class_id));
                    }
                    echo implode(', ', $class_links);
                } else {
                    echo '&mdash;';
                }
                break;
        }
    }

    // add_table_filters method removed - using base class implementation
    
    /**
     * Renders a filtered table via AJAX.
     *
     * @param string $html    Empty string passed by apply_filters.
     * @param array  $filters The filter parameters.
     * @return string HTML for the filtered table.
     */
    public function render_filtered_table($html = '', $filters = []) {
        // Call the parent implementation which has all the necessary logic
        return parent::render_filtered_table($html, $filters);
    }

    /**
     * Modify the main query based on selected filters.
     *
     * @param WP_Query $query The WP_Query instance.
     */
    public function filter_query($query) {
        global $pagenow;
        $post_type = isset($_GET['post_type']) ? $_GET['post_type'] : '';

        if (is_admin() && $pagenow == 'edit.php' && HAM_CPT_TEACHER === $post_type && $query->is_main_query()) {
            // Filter by school
            if (isset($_GET['ham_filter_school_id']) && absint($_GET['ham_filter_school_id']) > 0) {
                $school_id = absint($_GET['ham_filter_school_id']);
                $meta_query = $query->get('meta_query') ?: [];
                $meta_query[] = [
                    'key' => '_ham_school_id',
                    'value' => $school_id,
                    'compare' => '='
                ];
                $query->set('meta_query', $meta_query);
            }
        }
    }
    
    /**
     * Makes columns sortable in the admin list table.
     *
     * @param array $columns The sortable columns.
     * @return array Modified sortable columns.
     */
    public function make_columns_sortable($columns) {
        // Use standardized column names
        $columns['ham_school'] = 'ham_school';
        $columns['ham_classes'] = 'ham_classes';
        
        // Keep backward compatibility for any existing sort URLs
        $columns['school'] = 'ham_school';
        $columns['classes'] = 'ham_classes';
        
        return $columns;
    }
    
    /**
     * Handles the sorting logic for custom columns.
     *
     * @param WP_Query $query The WordPress query object.
     */
    public function sort_columns($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        
        // Only target the teacher CPT list table
        $screen = get_current_screen();
        if (!$screen || 'edit-' . HAM_CPT_TEACHER !== $screen->id) {
            return;
        }
        
        $orderby = $query->get('orderby');
        
        // Sort by school - handle both standardized and legacy column names
        if ('ham_school' === $orderby || 'school' === $orderby) {
            $query->set('meta_key', '_ham_school_id');
            $query->set('orderby', 'meta_value_num');
        }
        
        // Sort by class - more complex as teachers can have multiple classes
        // Handle both standardized and legacy column names
        if ('ham_classes' === $orderby || 'classes' === $orderby) {
            add_filter('posts_clauses', [$this, 'modify_class_sorting_query'], 10, 2);
        }
    }
    
    /**
     * Modifies the query clauses for class sorting.
     * 
     * @param array    $clauses The query clauses.
     * @param WP_Query $query   The WP_Query instance.
     * @return array Modified query clauses.
     */
    public function modify_class_sorting_query($clauses, $query) {
        global $wpdb;
        
        if (is_admin() && $query->is_main_query() && 
            isset($query->query['orderby']) && $query->query['orderby'] === 'classes') {
            
            // Remove this filter to avoid infinite loops
            remove_filter('posts_clauses', [__CLASS__, 'modify_class_sorting_query']);
            
            // Instead of complex SQL joins, we'll just use a manual post-processing sort
            // Create a temporary table for sorting teachers by class name
            add_action('pre_get_posts', function($q) use ($query) {
                if ($q === $query) {
                    // Add an action to sort results after the main query
                    add_filter('the_posts', [__CLASS__, 'sort_teachers_by_class'], 10, 2);
                }
            });
            
            // No need for special SQL clauses - we'll sort manually
        }
        
        return $clauses;
    }
    
    /**
     * Sort teachers by their assigned classes' names.
     * This is a manual PHP sort that runs after the query to avoid complex SQL.
     *
     * @param array    $posts Array of post objects.
     * @param WP_Query $query The current WP_Query object.
     * @return array Sorted posts.
     */
    public function sort_teachers_by_class($posts, $query) {
        if (!is_admin() || !isset($query->query['orderby']) || $query->query['orderby'] !== 'classes') {
            return $posts;
        }
        
        // Remove the filter to avoid infinite recursion
        remove_filter('the_posts', [__CLASS__, 'sort_teachers_by_class']);
        
        // Get class names for each teacher
        $teacher_classes = [];
        foreach ($posts as $post) {
            $class_ids = get_post_meta($post->ID, '_ham_class_ids', true);
            if (!empty($class_ids) && is_array($class_ids)) {
                $class_names = [];
                foreach ($class_ids as $class_id) {
                    $class_title = get_the_title($class_id);
                    if ($class_title) {
                        $class_names[] = $class_title;
                    }
                }
                sort($class_names); // Sort class names alphabetically
                $teacher_classes[$post->ID] = implode(', ', $class_names);
            } else {
                $teacher_classes[$post->ID] = ''; // Empty string for teachers with no classes
            }
        }
        
        // Sort teachers by class names
        usort($posts, function($a, $b) use ($teacher_classes, $query) {
            $a_classes = isset($teacher_classes[$a->ID]) ? $teacher_classes[$a->ID] : '';
            $b_classes = isset($teacher_classes[$b->ID]) ? $teacher_classes[$b->ID] : '';
            
            if ($query->get('order') === 'DESC') {
                return strcasecmp($b_classes, $a_classes);
            } else {
                return strcasecmp($a_classes, $b_classes);
            }
        });
        
        return $posts;
    }
}

/**
 * Initialize the admin list table for teachers.
 * The initialization is now handled through the admin loader, which ensures
 * it runs at the appropriate time in the WordPress lifecycle (after 'init' hook).
 */
add_action('admin_init', function() {
    if (is_admin()) {
        new HAM_Teacher_Admin_List_Table();
    }
});

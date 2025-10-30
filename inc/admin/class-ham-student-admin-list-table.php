<?php
/**
 * Handles admin list table customizations for the Student CPT.
 *
 * @package HeadlessAccessManager
 */

if (!defined('ABSPATH')) {
    exit;
}

// Make sure the base class is loaded
require_once __DIR__ . '/class-ham-base-admin-list-table.php';

/**
 * Class HAM_Student_Admin_List_Table
 */
class HAM_Student_Admin_List_Table extends HAM_Base_Admin_List_Table {

    /**
     * The post type this class manages.
     *
     * @var string
     */
    protected $post_type = HAM_CPT_STUDENT;
    
    /**
     * Constructor. Hooks into WordPress actions and filters.
     */
    public function __construct() {
        // Call parent constructor to setup hooks
        parent::__construct(HAM_CPT_STUDENT);
        
        // Register filters for this CPT
        $this->register_student_filters();
        
        // Note: AJAX handler is registered in the parent class (HAM_Base_Admin_List_Table)
    }
    
    /**
     * Register filters specific to the Student CPT.
     */
    protected function register_student_filters() {
        // School filter
        $this->register_filter('ham_filter_school_id', [
            'type' => 'meta',
            'meta_key' => '_ham_school_id',
            'label' => __('School', 'headless-access-manager'),
            'placeholder' => __('Filter by School', 'headless-access-manager'),
            'field_type' => 'select',
            'options_callback' => [$this, 'get_school_options']
        ]);
        
        // Any other student-specific filters can be registered here
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
     * Adds custom columns to the Student CPT list table.
     *
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public function add_admin_columns($columns) {
        // Use the standardized column helper from the base class
        // This ensures consistent column naming without prefixes
        return $this->standardize_columns($columns, [
            'school' => __('School', 'headless-access-manager'),
            'class' => __('Class', 'headless-access-manager')
        ]);
    }

    /**
     * Renders content for custom columns.
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
                //error_log("HAM STUDENT TABLE: Skipping duplicate render for {$column_name} cell on post {$post_id}");
            }
            return;
        }
        
        // Mark this cell as rendered
        $rendered_cells[$cell_key] = true;
        
        switch ($column_name) {
            case 'school':
                // Direct relationship to a single school
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

            case 'class': // Handles the 'class' column consistently
                // For classes, we need to find all classes that have this student in their _ham_student_ids meta
                $class_links = [];
                $all_classes_query = new WP_Query([
                    'post_type' => HAM_CPT_CLASS,
                    'posts_per_page' => -1,
                    'fields' => 'ids',
                ]);
                
                if ($all_classes_query->have_posts()) {
                    $processed_classes = [];
                    
                    foreach ($all_classes_query->posts as $class_id) {
                        // Skip if we've already processed this class
                        if (in_array($class_id, $processed_classes)) {
                            continue;
                        }
                        
                        $students_in_class = get_post_meta($class_id, '_ham_student_ids', true);
                        $students_in_class = is_array($students_in_class) ? $students_in_class : [];
                        
                        if (in_array($post_id, $students_in_class)) {
                            $processed_classes[] = $class_id;
                            $edit_link = get_edit_post_link($class_id);
                            $class_links[] = $edit_link ? 
                                '<a href="' . esc_url($edit_link) . '">' . esc_html(get_the_title($class_id)) . '</a>' : 
                                esc_html(get_the_title($class_id));
                        }
                    }
                }
                
                if (!empty($class_links)) {
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
     * Modifies the main query based on selected filters.
     *
     * @param WP_Query $query The WP_Query instance (passed by reference).
     */
    public function filter_query($query) {
        global $pagenow;
        // Ensure it's the main query, in the admin, for the Student CPT, and our filter is set.
        if ($query->is_main_query() && is_admin() && 'edit.php' === $pagenow && isset($_GET['post_type']) && HAM_CPT_STUDENT === $_GET['post_type'] && isset($_GET['ham_filter_school_id'])) {
            $school_id_filter = absint($_GET['ham_filter_school_id']);
            if ($school_id_filter > 0) {
                $meta_query = $query->get('meta_query') ?: [];
                $meta_query[] = [
                    'key' => '_ham_school_id',
                    'value' => $school_id_filter,
                    'compare' => '=',
                    'type' => 'NUMERIC',
                ];
                $query->set('meta_query', $meta_query);
            }
        }
    }
    
    /**
     * Makes the School and Class columns sortable in the admin list table.
     *
     * @param array $columns The sortable columns.
     * @return array Modified sortable columns.
     */
    public function make_columns_sortable($columns) {
        $columns['school'] = 'school';
        $columns['class'] = 'class';
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
        
        $orderby = $query->get('orderby');
        
        if ('school' === $orderby) {
            $query->set('meta_key', '_ham_school_id');
            $query->set('orderby', 'meta_value_num');
        }
        
        // For class column sorting, we need a more complex approach since students are linked to classes
        // via the class's _ham_student_ids meta array rather than students having their own class meta
        if ('ham_class' === $orderby) {
            // This is more complex and may require a custom SQL JOIN
            // For now, let's implement a basic solution
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
            isset($query->query['orderby']) && $query->query['orderby'] === 'ham_class') {
            
            // Remove this filter to avoid infinite loops
            remove_filter('posts_clauses', [$this, 'modify_class_sorting_query']);
            
            // Join on postmeta table for class titles
            // This is a simplified approach - it will work if students are in a single class
            $clauses['join'] .= " LEFT JOIN (
                SELECT pm.meta_value, GROUP_CONCAT(p.post_title ORDER BY p.post_title ASC SEPARATOR ', ') as class_names
                FROM {$wpdb->postmeta} pm 
                JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                WHERE pm.meta_key = '_ham_student_ids' AND p.post_type = '" . HAM_CPT_CLASS . "'
                GROUP BY pm.meta_value
            ) as class_data ON FIND_IN_SET({$wpdb->posts}.ID, REPLACE(REPLACE(class_data.meta_value, 'a:', ''), ';i:', ',')) > 0 ";
            
            $clauses['orderby'] = "class_data.class_names " . $query->get('order');
        }
        
        return $clauses;
    }
}

/**
 * Initialize the admin list table for students.
 * The initialization is now handled through the admin loader, which ensures
 * it runs at the appropriate time in the WordPress lifecycle (after 'init' hook).
 */
add_action('admin_init', function() {
    if (is_admin()) {
        new HAM_Student_Admin_List_Table();
    }
});

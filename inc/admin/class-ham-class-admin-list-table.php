<?php
/**
 * Customizes the admin list table for the Class CPT.
 *
 * @package HeadlessAccessManager
 */

if (!defined('ABSPATH')) {
    exit;
}

// Make sure the base class is loaded
require_once __DIR__ . '/class-ham-base-admin-list-table.php';

/**
 * Class HAM_Class_Admin_List_Table
 */
class HAM_Class_Admin_List_Table extends HAM_Base_Admin_List_Table {

    /**
     * The post type this class manages.
     *
     * @var string
     */
    protected $post_type = HAM_CPT_CLASS;
    
    /**
     * Stores the current teacher ID for filtering.
     *
     * @var int|null
     */
    protected $current_teacher_filter_id = null;

    /**
     * Constructor. Hooks into WordPress actions and filters.
     */
    public function __construct() {
        // Call parent constructor to setup hooks
        parent::__construct(HAM_CPT_CLASS);
        
        // Register the Class-specific hooks
        add_filter('the_posts', [$this, 'sort_classes_by_teachers'], 10, 2);
        add_filter('posts_where', [$this, 'filter_classes_by_teacher'], 10, 2);
        
        // Register filters for this CPT
        $this->register_class_filters();
    }
    
    /**
     * Register filters specific to the Class CPT.
     */
    protected function register_class_filters() {
        // School filter
        $this->register_filter('ham_filter_school_id', [
            'type' => 'meta',
            'meta_key' => '_ham_school_id',
            'label' => __('School', 'headless-access-manager'),
            'placeholder' => __('Filter by School', 'headless-access-manager'),
            'field_type' => 'select',
            'options_callback' => [$this, 'get_school_options']
        ]);
        
        // Teacher filter
        $this->register_filter('ham_filter_teacher_id', [
            'type' => 'meta',
            'meta_key' => '_ham_teacher_ids',
            'label' => __('Teacher', 'headless-access-manager'),
            'placeholder' => __('Filter by Teacher', 'headless-access-manager'),
            'field_type' => 'select',
            'options_callback' => [$this, 'get_teacher_options'],
            'meta_query_callback' => [$this, 'build_teacher_meta_query']
        ]);
        
        // Any other class-specific filters can be registered here
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
     * Get options for the teacher filter dropdown.
     *
     * @return array Array of teacher IDs and names.
     */
    public function get_teacher_options() {
        $teacher_options = [];
        
        // Get all teachers
        $teachers = get_posts([
            'post_type' => HAM_CPT_TEACHER,
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        
        foreach ($teachers as $teacher) {
            $teacher_options[$teacher->ID] = $teacher->post_title;
        }
        
        return $teacher_options;
    }
    
    /**
     * Build the meta query for filtering classes by teacher.
     *
     * @param string $filter_name The filter name.
     * @param string $value       The filter value (teacher ID).
     * @param array  $filters     All filter values.
     * @return array Empty array since we handle this via posts_where filter.
     */
    public function build_teacher_meta_query($filter_name, $value, $filters) {
        // Store the teacher ID for use in the posts_where filter
        $this->current_teacher_filter_id = intval($value);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            //error_log('Teacher filter active for teacher ID: ' . $this->current_teacher_filter_id);
        }
        
        // We don't use a regular meta query here since the relationship is stored in teacher posts
        // Instead, we'll filter using posts_where later
        
        // Return empty to avoid adding incorrect meta queries
        return [];
    }
    
    /**
     * Enqueues scripts and styles for AJAX filtering.
     *
     * @param string $hook The current admin page.
     */
    public function enqueue_scripts($hook) {
        global $typenow;
        
        if ('edit.php' === $hook && HAM_CPT_CLASS === $typenow) {
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
                'postType' => HAM_CPT_CLASS,
                'i18n' => [
                    'loading' => __('Loading...', 'headless-access-manager'),
                    'error' => __('Error loading content', 'headless-access-manager'),
                    'noResults' => __('No results found', 'headless-access-manager'),
                ],
            ]);
        }
    }
    
    /**
     * Renders a filtered table via AJAX.
     *
     * @param string $html    Empty string passed by apply_filters.
     * @param array  $filters The filter parameters.
     * @return string HTML for the filtered table.
     */
    public function render_filtered_table($html = '', $filters = []) {
        // Use the parent class implementation which has robust parameter handling
        // and consistent container wrapping for all CPTs
        return parent::render_filtered_table($html, $filters);
    }

    /**
     * Adds custom columns to the admin list table.
     * 
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    
    /**
     * Filter the WHERE clause of the query to only show classes assigned to a specific teacher.
     *
     * @param string   $where The WHERE clause of the query.
     * @param WP_Query $query The WP_Query instance.
     * @return string Modified WHERE clause.
     */
    public function filter_classes_by_teacher($where, $query) {
        global $wpdb;
        
        // Only apply this filter for our CPT and when a teacher filter is active
        if ($query->get('post_type') === $this->post_type && !empty($this->current_teacher_filter_id)) {
            $teacher_id = $this->current_teacher_filter_id;
            
            // Get the classes assigned to this teacher
            $teacher_class_ids = get_post_meta($teacher_id, '_ham_class_ids', true);
            
            if (is_array($teacher_class_ids) && !empty($teacher_class_ids)) {
                // Convert array to string of IDs for SQL
                $class_ids_string = implode(',', array_map('intval', $teacher_class_ids));
                
                // Modify the WHERE clause to only include these class IDs
                $where .= " AND {$wpdb->posts}.ID IN ($class_ids_string)";
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    //error_log("Filtered classes by teacher ID {$teacher_id}: Found " . count($teacher_class_ids) . " classes");
                }
            } else {
                // No classes assigned to this teacher, return no results
                $where .= " AND 1=0";
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    //error_log("Filtered classes by teacher ID {$teacher_id}: No classes found");
                }
            }
        }
        
        return $where;
    }
    
    /**
     * Adds custom columns to the admin list table.
     * 
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public function add_admin_columns($columns) {
        // Use standardized column helper for consistent naming
        return $this->standardize_columns($columns, [
            'school' => __('School', 'headless-access-manager'),
            'teachers' => __('Teachers', 'headless-access-manager'),
            'students' => __('Students', 'headless-access-manager')
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
                //error_log("HAM CLASS TABLE: Skipping duplicate render for {$column_name} cell on post {$post_id}");
            }
            return;
        }
        
        // Mark this cell as rendered
        $rendered_cells[$cell_key] = true;
        
        switch ($column_name) {
            case 'school':
                // Direct relationship to a single school
                $school_id = get_post_meta($post_id, '_ham_school_id', true);
                if (!empty($school_id)) {
                    $school = get_post($school_id);
                    if ($school) {
                        $edit_link = get_edit_post_link($school_id);
                        if ($edit_link) {
                            echo '<a href="' . esc_url($edit_link) . '">' . esc_html($school->post_title) . '</a>';
                        } else {
                            echo esc_html($school->post_title);
                        }
                    } else {
                        echo '&mdash;';
                    }
                } else {
                    echo '&mdash;';
                }
                break;
                
            case 'teachers':
                // Find teachers assigned to this class
                // Get all teachers and check if this class is in their _ham_class_ids array
                $args = [
                    'post_type' => HAM_CPT_TEACHER,
                    'posts_per_page' => -1,
                    'orderby' => 'title',
                    'order' => 'ASC'
                ];
                
                $teachers_query = new WP_Query($args);
                $teacher_links = [];
                $processed_ids = [];
                
                if ($teachers_query->have_posts()) {
                    while ($teachers_query->have_posts()) {
                        $teachers_query->the_post();
                        $teacher_id = get_the_ID();
                        
                        // Get class IDs for this teacher
                        $teacher_class_ids = get_post_meta($teacher_id, '_ham_class_ids', true);
                        
                        // Check if this class is assigned to this teacher
                        if (is_array($teacher_class_ids) && in_array($post_id, $teacher_class_ids)) {                            
                            // Skip if already processed
                            if (in_array($teacher_id, $processed_ids)) {
                                continue;
                            }
                            $processed_ids[] = $teacher_id;
                            
                            $edit_link = get_edit_post_link($teacher_id);
                            $teacher_links[] = $edit_link ? 
                                '<a href="' . esc_url($edit_link) . '">' . esc_html(get_the_title()) . '</a>' : 
                                esc_html(get_the_title());
                        }
                    }
                    wp_reset_postdata();
                    
                    if (!empty($teacher_links)) {
                        echo implode(', ', $teacher_links);
                    } else {
                        echo '&mdash;';
                    }
                } else {
                    echo '&mdash;';
                }
                break;
                
            case 'students':
                // Display number of students in this class
                $student_ids = get_post_meta($post_id, '_ham_student_ids', true);
                if (!empty($student_ids) && is_array($student_ids)) {
                    $unique_students = array_unique($student_ids);
                    $count = count($unique_students);
                    
                    if ($count === 1) {
                        echo '1 ' . esc_html__('Student', 'headless-access-manager');
                    } else {
                        echo esc_html($count) . ' ' . esc_html__('Students', 'headless-access-manager');
                    }
                } else {
                    echo '0 ' . esc_html__('Students', 'headless-access-manager');
                }
                break;
        }
    }

    /**
     * Makes columns sortable in the admin list table.
     *
     * @param array $columns The sortable columns.
     * @return array Modified sortable columns.
     */
    public function make_columns_sortable($columns) {
        // Use consistent column names
        $columns['school'] = 'school';
        $columns['teachers'] = 'teachers';
        $columns['students'] = 'students';
        
        return $columns;
    }

    /**
     * Handles the sorting logic for custom columns.
     *
     * @param WP_Query $query The WordPress query object.
     */
    public function sort_columns($query) {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== HAM_CPT_CLASS) {
            return;
        }
        
        $orderby = $query->get('orderby');
        
        switch ($orderby) {
            case 'school':
                // Simple meta query for school sorting
                $query->set('meta_key', '_ham_school_id');
                $query->set('orderby', 'meta_value_num');
                break;
                
            case 'teachers':
                // For teachers, we'll use PHP-based sorting after query (in sort_classes_by_teachers)
                break;
                
            case 'students':
                // Sort by student count
                break;
        }
    }
    
    /**
     * This is a manual PHP sort that runs after the query to avoid complex SQL.
     *
     * @param array    $posts Array of post objects.
     * @param WP_Query $query The current WP_Query object.
     * @return array Sorted posts.
     */
    public static function sort_classes_by_teachers($posts, $query) {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== HAM_CPT_CLASS || $query->get('orderby') !== 'teachers') {
            return $posts;
        }
        
        usort($posts, function($a, $b) {
            $teachers_a = self::get_teacher_names_for_class($a->ID);
            $teachers_b = self::get_teacher_names_for_class($b->ID);
            
            // Get first teacher name for each class (or empty string if none)
            $first_teacher_a = !empty($teachers_a) ? reset($teachers_a) : '';
            $first_teacher_b = !empty($teachers_b) ? reset($teachers_b) : '';
            
            // Compare teacher names (case-insensitive)
            $result = strcasecmp($first_teacher_a, $first_teacher_b);
            
            // If order is DESC, reverse the comparison
            if ($result !== 0 && isset($_GET['order']) && $_GET['order'] === 'desc') {
                $result = -$result;
            }
            
            return $result;
        });
        
        return $posts;
    }
    
    /**
     * Get teacher names for a class.
     *
     * @param int $class_id The class post ID.
     * @return array Array of teacher names.
     */
    private static function get_teacher_names_for_class($class_id) {
        $args = [
            'post_type' => HAM_CPT_TEACHER,
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_ham_class_ids',
                    'value' => '"' . $class_id . '"', // Match the exact ID in the serialized array
                    'compare' => 'LIKE'
                ]
            ],
            'orderby' => 'title',
            'order' => 'ASC'
        ];
        
        $teachers = get_posts($args);
        $teacher_names = [];
        
        foreach ($teachers as $teacher) {
            $teacher_names[] = $teacher->post_title;
        }
        
        return $teacher_names;
    }

    // add_table_filters method removed - using base class implementation

    /**
     * Modify the main query based on selected filters.
     *
     * @param WP_Query $query The WP_Query instance.
     */
    public function filter_query($query) {
        global $pagenow;
        $post_type = isset($_GET['post_type']) ? $_GET['post_type'] : '';
        
        if (is_admin() && $pagenow == 'edit.php' && $this->post_type === $post_type && $query->is_main_query()) {
            // Filter by school
            if (isset($_GET['ham_filter_school_id'])) {
                // Use the raw value to avoid treating -1 ("All schools") as 1 via absint
                $raw_school_id = $_GET['ham_filter_school_id'];
                $school_id = intval($raw_school_id);
                if ($school_id > 0) {
                    // Add meta query for school filtering
                    $query->set('meta_query', [
                        [
                            'key' => '_ham_school_id',
                            'value' => $school_id,
                            'compare' => '=',
                        ]
                    ]);
                }
            }
            
            // Filter by teacher
            if (isset($_GET['ham_filter_teacher_id']) && absint($_GET['ham_filter_teacher_id']) > 0) {
                $teacher_id = absint($_GET['ham_filter_teacher_id']);
                
                // Find classes this teacher is assigned to
                $teacher_classes = get_post_meta($teacher_id, '_ham_class_ids', true);
                if (!empty($teacher_classes) && is_array($teacher_classes)) {
                    $query->set('post__in', $teacher_classes);
                }
            }
        }
    }
    
    /**
     * Apply filters to query args for AJAX filtering.
     *
     * @param array $args    The query arguments.
     * @param array $filters The filter parameters.
     */
    public function apply_filters_to_query_args(&$args, $filters) {
        // Call the parent class method to handle standard filters
        parent::apply_filters_to_query_args($args, $filters);
        
        // Filter by teacher
        if (!empty($filters['ham_filter_teacher_id'])) {
            $teacher_id = absint($filters['ham_filter_teacher_id']);
            
            // Find classes this teacher is assigned to
            $teacher_classes = get_post_meta($teacher_id, '_ham_class_ids', true);
            if (!empty($teacher_classes) && is_array($teacher_classes)) {
                $args['post__in'] = $teacher_classes;
            }
        }
    }
}

/**
 * Initialize the admin list table for classes.
 * The initialization is now handled through the admin loader, which ensures
 * it runs at the appropriate time in the WordPress lifecycle (after 'init' hook).
 */
add_action('admin_init', function() {
    if (is_admin()) {
        new HAM_Class_Admin_List_Table();
    }
});

<?php
/**
 * Customizes the admin list table for the Principal CPT.
 *
 * @package HeadlessAccessManager
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class HAM_Principal_Admin_List_Table
 */
class HAM_Principal_Admin_List_Table extends HAM_Base_Admin_List_Table {

    /**
     * The post type this admin list table is for
     *
     * @var string
     */
    public $post_type;

    /**
     * Constructor. Hooks into WordPress actions and filters.
     */
    public function __construct() {
        // Call parent constructor to set up the post type and register core hooks
        parent::__construct(HAM_CPT_PRINCIPAL);
        
        // Register Principal-specific hooks
        add_filter('the_posts', [$this, 'sort_principals_by_schools'], 10, 2);
        
        // Register filters for this CPT
        $this->register_principal_filters();
        
        // Note: AJAX handler and scripts are registered in the parent class (HAM_Base_Admin_List_Table)
    }
    
    // Note: enqueue_scripts method is inherited from the parent class (HAM_Base_Admin_List_Table)
    
    /**
     * Renders a filtered table via AJAX.
     *
     * @param array $filters The filter parameters.
     * @return string HTML output of the filtered table.
     */
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
     * Add custom columns to the admin list table.
     *
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public function add_admin_columns($columns) {
        // Use standardized column helper for consistent naming without prefixes
        return $this->standardize_columns($columns, [
            'schools' => __('Assigned Schools', 'headless-access-manager')
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
                error_log("HAM PRINCIPAL TABLE: Skipping duplicate render for {$column_name} cell on post {$post_id}");
            }
            return;
        }
        
        // Mark this cell as rendered
        $rendered_cells[$cell_key] = true;
        switch ($column_name) {
            case 'schools':
                // Get the linked user ID for this principal
                $linked_user_id = get_post_meta($post_id, '_ham_user_id', true);
                
                if (empty($linked_user_id)) {
                    echo '&mdash;';
                    return;
                }
                
                // Get schools assigned to this principal
                $processed_schools = [];
                $school_links = [];
                
                // Get all schools and check each one for this principal
                $args = [
                    'post_type' => HAM_CPT_SCHOOL,
                    'posts_per_page' => -1,
                    'orderby' => 'title',
                    'order' => 'ASC'
                ];
                
                $schools_query = new WP_Query($args);
                
                if ($schools_query->have_posts()) {
                    while ($schools_query->have_posts()) {
                        $schools_query->the_post();
                        $school_id = get_the_ID();
                        
                        // Skip if we've already processed this school
                        if (in_array($school_id, $processed_schools)) {
                            continue;
                        }
                        
                        // Get principal IDs for this school
                        $principal_ids = get_post_meta($school_id, '_ham_principal_ids', true);
                        
                        // Check if this principal's user ID is in the school's principal IDs
                        if (is_array($principal_ids) && in_array($linked_user_id, $principal_ids)) {
                            $processed_schools[] = $school_id;
                            $edit_link = get_edit_post_link($school_id);
                            $school_links[] = $edit_link ? 
                                '<a href="' . esc_url($edit_link) . '">' . esc_html(get_the_title()) . '</a>' : 
                                esc_html(get_the_title());
                        }
                    }
                    wp_reset_postdata();
                }
                
                // Fallback: Check user's own _ham_school_id meta if we found nothing
                if (empty($school_links)) {
                    $single_school_id = get_user_meta($linked_user_id, HAM_USER_META_SCHOOL_ID, true);
                    if ($single_school_id && !in_array($single_school_id, $processed_schools)) {
                        $school_post = get_post($single_school_id);
                        if ($school_post) {
                            $processed_schools[] = $single_school_id;
                            $edit_link = get_edit_post_link($single_school_id);
                            $school_links[] = $edit_link ? 
                                '<a href="' . esc_url($edit_link) . '">' . esc_html($school_post->post_title) . '</a>' : 
                                esc_html($school_post->post_title);
                        }
                    }
                }
                
                if (!empty($school_links)) {
                    echo implode(', ', $school_links);
                } else {
                    echo '&mdash;';
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
        // Use consistent column naming
        $columns['schools'] = 'schools';
        
        return $columns;
    }

    /**
     * Handles the sorting logic for custom columns.
     *
     * @param WP_Query $query The WordPress query object.
     */
    public function sort_columns($query) {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== HAM_CPT_PRINCIPAL) {
            return;
        }
        
        $orderby = $query->get('orderby');
        
        if ('schools' === $orderby) {
            // Add filter to sort by schools after the query
            add_filter('the_posts', [$this, 'sort_principals_by_schools'], 10, 2);
        }
    }

    /**
     * Sort principals by their assigned schools' names.
     * This is a manual PHP sort that runs after the query to avoid complex SQL.
     *
     * @param array    $posts Array of post objects.
     * @param WP_Query $query The current WP_Query object.
     * @return array Sorted posts.
     */
    public function sort_principals_by_schools($posts, $query) {
        // Remove the filter to avoid infinite recursion
        remove_filter('the_posts', [$this, 'sort_principals_by_schools']);
        
        // Only process on the admin principals list with schools orderby
        global $pagenow;
        if (!is_admin() || $pagenow != 'edit.php' || !isset($_GET['post_type']) || $_GET['post_type'] != $this->post_type) {
            return $posts;
        }
        
        // Check if we're sorting by schools
        $orderby = $query->get('orderby');
        if ($orderby !== 'schools') {
            return $posts;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('HAM Principal sort_principals_by_schools running - Post count: ' . count($posts));
        }
        
        // Sort the posts
        $self = $this; // Create a reference to $this that can be used inside the anonymous function
        usort($posts, function($a, $b) use ($self) {
            // Get school names for each principal (using get_school_names_for_principal which now uses linked_user_id)
            $a_school_names = $self->get_school_names_for_principal($a->ID);
            $b_school_names = $self->get_school_names_for_principal($b->ID);
            
            // Debug sorting
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('HAM Principal sorting - Principal A ID: ' . $a->ID . ', Schools: ' . implode(', ', $a_school_names));
                error_log('HAM Principal sorting - Principal B ID: ' . $b->ID . ', Schools: ' . implode(', ', $b_school_names));
            }
            
            // If both have schools, compare first school name
            if (!empty($a_school_names) && !empty($b_school_names)) {
                $a_name = strtolower($a_school_names[0]);
                $b_name = strtolower($b_school_names[0]);
                $result = strcmp($a_name, $b_name);
            } 
            // If only one has schools, that one comes first
            else if (!empty($a_school_names)) {
                $result = -1;
            } else if (!empty($b_school_names)) {
                $result = 1;
            } 
            // If neither has schools, maintain original order
            else {
                $result = 0;
            }
            
            // Respect the requested order direction
            return isset($_GET['order']) && $_GET['order'] === 'desc' ? -$result : $result;
        });
        
        return $posts;
    }

    /**
     * Get school names for a principal.
     *
     * @param int $principal_id The principal post ID.
     * @return array Array of school names.
     */
    public function get_school_names_for_principal($principal_id) {
        // Get the linked user ID for this principal
        $linked_user_id = get_post_meta($principal_id, '_ham_user_id', true);
        
        if (empty($linked_user_id)) {
            return [];
        }
        
        // Get all schools and check each one for this principal
        $args = [
            'post_type' => HAM_CPT_SCHOOL,
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ];
        
        $schools_query = new WP_Query($args);
        $school_names = [];
        $processed_ids = [];
        
        if ($schools_query->have_posts()) {
            while ($schools_query->have_posts()) {
                $schools_query->the_post();
                $school_id = get_the_ID();
                
                // Skip if already processed
                if (in_array($school_id, $processed_ids)) {
                    continue;
                }
                
                // Get principal IDs for this school
                $principal_ids = get_post_meta($school_id, '_ham_principal_ids', true);
                
                // Check if this principal's user ID is in the school's principal IDs
                if (is_array($principal_ids) && in_array($linked_user_id, $principal_ids)) {
                    $processed_ids[] = $school_id;
                    $school_names[] = get_the_title();
                }
            }
            wp_reset_postdata();
        }
        
        // Fallback: Check user's own _ham_school_id meta if we found nothing
        if (empty($school_names)) {
            $single_school_id = get_user_meta($linked_user_id, HAM_USER_META_SCHOOL_ID, true);
            if ($single_school_id && !in_array($single_school_id, $processed_ids)) {
                $school = get_post($single_school_id);
                if ($school) {
                    $school_names[] = $school->post_title;
                }
            }
        }
        
        return $school_names;
    }

    /**
     * Register filters specific to the Principal CPT.
     */
    protected function register_principal_filters() {
        // School filter - ensure callback field names align with base class expectations
        $this->register_filter('ham_filter_school_id', [
            'type' => 'custom',
            'label' => __('School', 'headless-access-manager'),
            'placeholder' => __('Filter by School', 'headless-access-manager'),
            'field_type' => 'select',
            'options_callback' => [$this, 'get_school_options'],
            'callback' => [$this, 'apply_school_filter'],  // Add standard callback name
            'custom_query_callback' => [$this, 'apply_school_filter']  // Keep original for backwards compatibility
        ]);
        
        // Any other principal-specific filters can be registered here
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
     * Apply the school filter to the query.
     *
     * @param array  $args        Query arguments.
     * @param string $filter_name Filter parameter name.
     * @param string $value       Filter value.
     * @param array  $filters     All filter parameters.
     */
    public function apply_school_filter(&$args, $filter_name, $value, $filters) {
        if (empty($value) || $value === '-1') {
            // Skip if no filter value
            return;
        }
        
        // Only log detailed debugging if verbose mode is enabled
        if (defined('WP_DEBUG') && WP_DEBUG && defined('HAM_DEBUG_VERBOSE') && HAM_DEBUG_VERBOSE) {
            error_log('HAM Principal apply_school_filter - Filter applied with value: ' . $value);
        }
        
        // Get all principals assigned to this school - using both approaches
        $principal_ids = [];
        $linked_user_ids = [];
        
        // 1. First, get principal IDs from post meta on school
        $school_principals = get_post_meta($value, '_ham_principal_ids', true);
        
        if (!empty($school_principals)) {
            if (is_array($school_principals)) {
                $linked_user_ids = $school_principals;
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('HAM Principal apply_school_filter - Found school principals (array): ' . implode(', ', $linked_user_ids));
                }
            } else if (is_numeric($school_principals)) {
                $linked_user_ids = [$school_principals];
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('HAM Principal apply_school_filter - Found school principal (single): ' . $school_principals);
                }
            }
            
            // Find principal post IDs for these user IDs
            if (!empty($linked_user_ids)) {
                foreach ($linked_user_ids as $user_id) {
                    // Get principals with this user ID
                    $principal_posts = get_posts([
                        'post_type' => HAM_CPT_PRINCIPAL,
                        'posts_per_page' => -1,
                        'meta_query' => [
                            [
                                'key' => '_ham_user_id',
                                'value' => $user_id,
                                'compare' => '='
                            ]
                        ]
                    ]);
                    
                    foreach ($principal_posts as $post) {
                        $principal_ids[] = $post->ID;
                    }
                }
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('HAM Principal apply_school_filter - Found principal post IDs from user IDs: ' . implode(', ', $principal_ids));
                }
            }
        }
        
        // 2. Second approach: get all principals with this school ID in user meta
        $users_with_school = get_users([
            'meta_key' => HAM_USER_META_SCHOOL_ID,
            'meta_value' => $value,
            'fields' => 'ID'
        ]);
        
        if (!empty($users_with_school)) {
            foreach ($users_with_school as $user_id) {
                // Skip if we already found this user
                if (in_array($user_id, $linked_user_ids)) {
                    continue;
                }
                
                // Get principals with this user ID
                $principal_posts = get_posts([
                    'post_type' => HAM_CPT_PRINCIPAL,
                    'posts_per_page' => -1,
                    'meta_query' => [
                        [
                            'key' => '_ham_user_id',
                            'value' => $user_id,
                            'compare' => '='
                        ]
                    ]
                ]);
                
                foreach ($principal_posts as $post) {
                    if (!in_array($post->ID, $principal_ids)) {
                        $principal_ids[] = $post->ID;
                    }
                }
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('HAM Principal apply_school_filter - Additional principals from user meta: ' . implode(', ', $principal_ids));
            }
        }
        
        // Debug summary
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('HAM Principal apply_school_filter - Final principal IDs: ' . implode(', ', $principal_ids));
        }
        
        if (!empty($principal_ids)) {
            $args['post__in'] = empty($args['post__in']) ? $principal_ids : 
                array_intersect($args['post__in'], $principal_ids);
            
            // If no posts match all filters, return no results
            if (empty($args['post__in'])) {
                $args['post__in'] = [0]; // Force no results
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('HAM Principal apply_school_filter - Final query post__in: ' . implode(', ', $args['post__in']));
            }
        } else {
            // No principals found for this school, return no results
            $args['post__in'] = [0]; // Force no results
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('HAM Principal apply_school_filter - No principals found, forcing empty results');
            }
        }
    }
}

/**
 * Initialize the admin list table for principals.
 * The initialization is now handled through the admin loader, which ensures
 * it runs at the appropriate time in the WordPress lifecycle (after 'init' hook).
 */
add_action('admin_init', function() {
    if (is_admin()) {
        new HAM_Principal_Admin_List_Table();
    }
});

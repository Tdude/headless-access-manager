<?php
/**
 * Handles admin list table customizations for the School CPT.
 *
 * @package HeadlessAccessManager
 */

if (!defined('ABSPATH')) {
    exit;
}

// Make sure the base class is loaded
require_once __DIR__ . '/class-ham-base-admin-list-table.php';

/**
 * Class HAM_School_Admin_List_Table
 */
class HAM_School_Admin_List_Table extends HAM_Base_Admin_List_Table {

    /**
     * The post type this class manages.
     *
     * @var string
     */
    protected $post_type = HAM_CPT_SCHOOL;

    /**
     * Constructor. Hooks into WordPress actions and filters.
     */
    public function __construct() {
        // Call parent constructor to setup hooks
        parent::__construct(HAM_CPT_SCHOOL);
        
        // Register filters for this CPT
        $this->register_school_filters();
        
        // Note: AJAX handler is registered in the parent class (HAM_Base_Admin_List_Table)
    }
    
    /**
     * Register filters specific to the School CPT.
     */
    protected function register_school_filters() {
        // Principal filter - ensure consistency with base class field names
        $this->register_filter('ham_filter_principal_id', [
            'type' => 'meta',
            'meta_key' => '_ham_principal_ids',
            'label' => __('Principal', 'headless-access-manager'),
            'placeholder' => __('Filter by Principal', 'headless-access-manager'),
            'field_type' => 'select',
            'options_callback' => [$this, 'get_principal_options'],
            'meta_query_callback' => [$this, 'build_principal_meta_query'],
            'callback' => [$this, 'build_principal_meta_query_args'] // Add standard callback name for custom queries
        ]);
        
        // Any other school-specific filters can be registered here
    }
    
    /**
     * Get options for the principal filter dropdown.
     *
     * @return array Array of principal IDs and names.
     */
    public function get_principal_options() {
        $principal_options = [];
        
        // Get all principal users
        $principals = get_users([
            'role' => 'ham_principal',
            'orderby' => 'display_name',
            'order' => 'ASC'
        ]);
        
        foreach ($principals as $principal) {
            $principal_options[$principal->ID] = $principal->display_name;
        }
        
        return $principal_options;
    }
    
    /**
     * Build a meta query for the principal filter.
     *
     * @param string $filter_name The filter name.
     * @param string $value       The filter value (principal ID).
     * @param array  $filters     All filter values.
     * @return array Meta query array.
     */
    public function build_principal_meta_query($filter_name, $value, $filters) {
        // Enhanced debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            //error_log('HAM Schools - Building principal meta query');
            //error_log('HAM Schools - Filter name: ' . $filter_name);
            //error_log('HAM Schools - Filter value: ' . $value);
            //error_log('HAM Schools - All filters: ' . print_r($filters, true));
        }
        
        // Validate the value to prevent errors
        if (empty($value) || $value === '-1') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                //error_log('HAM Schools - Empty or default principal filter value, not applying filter');
            }
            return [];
        }
        
        // Sanitize and validate the value as an integer
        $value = intval($value);
        if (!$value) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                //error_log('HAM Schools - Invalid principal ID (not an integer): ' . $value);
            }
            return [];
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            //error_log('HAM Schools - Building principal meta query with validated value: ' . $value);
        }
        
        // The meta data is stored as a PHP serialized array, so we need to construct
        // different search patterns to make sure we capture all possible formats
        return [
            'relation' => 'OR',
            // Pattern 1: i:1;i:123; (when ID is at beginning of array)
            [
                'key' => '_ham_principal_ids',
                'value' => 'i:0;i:' . $value . ';', 
                'compare' => 'LIKE'
            ],
            // Pattern 2: i:123; (when ID is in middle/end of array)
            [
                'key' => '_ham_principal_ids',
                'value' => 'i:' . $value . ';', 
                'compare' => 'LIKE'
            ],
            // Pattern 3: Sometimes stored as quoted strings in serialized array
            [
                'key' => '_ham_principal_ids',
                'value' => '"' . $value . '"', 
                'compare' => 'LIKE'
            ],
            // Pattern 4: Direct match (if stored as single value)
            [
                'key' => '_ham_principal_ids',
                'value' => $value, 
                'compare' => '='
            ]
        ];
    }
    
    // The enqueue_scripts method is now handled by the base class
    
    // The render_filtered_table method is now handled by the base class
    
    /**
     * Custom callback for principal filter that integrates with the generic filter system.
     * This matches the standard callback format expected by the base class.
     *
     * @param array  $args        Query arguments (passed by reference).
     * @param string $filter_name Filter name.
     * @param mixed  $value       Filter value.
     * @param array  $filters     All active filters.
     */
    public function build_principal_meta_query_args(&$args, $filter_name, $value, $filters) {
        // Skip if empty value
        if (empty($value) || $value === '-1') {
            return;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            //error_log('================= HAM SCHOOLS PRINCIPAL FILTER =================');
            //error_log('HAM Schools - build_principal_meta_query_args running for principal ID: ' . $value);
            //error_log('HAM Schools - Filter name: ' . $filter_name);
            //error_log('HAM Schools - Original args: ' . print_r($args, true));
            
            // Get some actual schools and dump their principal IDs to understand the format
            $sample_schools = get_posts(["post_type" => HAM_CPT_SCHOOL, "numberposts" => 3]);
            //error_log('HAM Schools - Found ' . count($sample_schools) . ' sample schools');
            
            foreach ($sample_schools as $school) {
                $principal_ids = get_post_meta($school->ID, '_ham_principal_ids', true);
                //error_log('HAM Schools - School ID ' . $school->ID . ' (' . $school->post_title . ') has principal_ids: ' . print_r($principal_ids, true));
                //error_log('HAM Schools - principal_ids TYPE: ' . gettype($principal_ids));
                
                if (is_array($principal_ids)) {
                    //error_log('HAM Schools - principal_ids serialized: ' . serialize($principal_ids));
                    // Check if our target value is in this array
                    if (in_array($value, $principal_ids)) {
                        //error_log('HAM Schools - MATCH FOUND! Principal ID ' . $value . ' is assigned to school ' . $school->ID);
                    }
                } else if ($principal_ids == $value) {
                    //error_log('HAM Schools - MATCH FOUND! Principal ID ' . $value . ' is directly assigned to school ' . $school->ID);
                }
            }
            
            // Do a direct SQL query to see what's in the database
            global $wpdb;
            $sql = $wpdb->prepare(
                "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} 
                WHERE meta_key = '_ham_principal_ids' 
                AND (meta_value LIKE %s OR meta_value LIKE %s OR meta_value = %s)",
                '%i:' . $value . ';%',   // Pattern for serialized array with integer
                '%"' . $value . '"%',   // Pattern for serialized array with string
                $value                   // Direct match
            );
            //error_log('HAM Schools - Direct SQL query: ' . $sql);
            $results = $wpdb->get_results($sql);
            //error_log('HAM Schools - SQL query found ' . count($results) . ' matches');
            foreach ($results as $result) {
                //error_log('HAM Schools - Match in post_id ' . $result->post_id . ' with value: ' . $result->meta_value);
            }
        }
        
        // Add the meta query directly - this is simpler and more reliable than nesting arrays
        // The meta data is stored as a PHP serialized array, so we need to construct
        // different search patterns to make sure we capture all possible formats
        
        // Initialize meta_query if not set
        if (!isset($args['meta_query'])) {
            $args['meta_query'] = [];
        } else if (!is_array($args['meta_query'])) {
            // Convert to array if not already
            $args['meta_query'] = (array)$args['meta_query'];
        }
        
        // Add relation if multiple queries
        if (count($args['meta_query']) > 0) {
            // Preserve existing relation if set, otherwise use AND
            if (!isset($args['meta_query']['relation'])) {
                $args['meta_query']['relation'] = 'AND';
            }
        }
        
        // Create a subquery with OR relation for all principal patterns
        $principal_query = [
            'relation' => 'OR',
            // Pattern 1: i:0;i:123; (when ID is at array index 0)
            [
                'key' => '_ham_principal_ids',
                'value' => 'i:0;i:' . $value . ';', 
                'compare' => 'LIKE'
            ],
            // Pattern 2: i:123; (when ID is in middle/end of array)
            [
                'key' => '_ham_principal_ids',
                'value' => 'i:' . $value . ';', 
                'compare' => 'LIKE'
            ],
            // Pattern 3: Sometimes stored as quoted strings in serialized array
            [
                'key' => '_ham_principal_ids',
                'value' => '"' . $value . '"', 
                'compare' => 'LIKE'
            ],
            // Pattern 4: Direct match (if stored as single value)
            [
                'key' => '_ham_principal_ids',
                'value' => $value, 
                'compare' => '='
            ]
        ];
        
        // Add the principal query to the main meta_query
        $args['meta_query'][] = $principal_query;
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            //error_log('HAM Schools - Final meta query: ' . print_r($args['meta_query'], true));
            
            // Create a test WP_Query to see the SQL it generates
            $test_query = new \WP_Query();
            add_filter('posts_request', function($sql) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    //error_log('HAM Schools - SQL query generated: ' . $sql);
                }
                return $sql;
            });
            
            // Clone the args and add post_type
            $test_args = $args;
            $test_args['post_type'] = HAM_CPT_SCHOOL;
            $test_args['posts_per_page'] = 5;
            $test_args['fields'] = 'ids'; // Just get IDs for efficiency
            
            // Run the query - this will trigger our posts_request filter above
            $test_posts = $test_query->query($test_args);
            //error_log('HAM Schools - Test query found ' . count($test_posts) . ' posts');
            //error_log('HAM Schools - Found post IDs: ' . implode(', ', $test_posts));
            
            // Remove our filter
            remove_all_filters('posts_request');
        }
    }

    /**
     * Add custom columns to the admin list table.
     *
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public function add_admin_columns($columns) {
        // Use standardized column helper for consistent naming
        return $this->standardize_columns($columns, [
            'principals' => __('Principals', 'headless-access-manager')
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
                //error_log("HAM SCHOOL TABLE: Skipping duplicate render for {$column_name} cell on post {$post_id}");
            }
            return;
        }
        
        // Mark this cell as rendered
        $rendered_cells[$cell_key] = true;
        
        switch ($column_name) {
            case 'ham_principals':
                // For principals, we need a custom solution because principal IDs are user IDs, not post IDs
                $principal_ids = get_post_meta($post_id, '_ham_principal_ids', true);
                
                // Handle empty or non-array values
                if (empty($principal_ids)) {
                    echo '&mdash;';
                    break;
                }
                
                // Ensure we have an array of IDs
                if (!is_array($principal_ids)) {
                    $principal_ids = [$principal_ids]; // Convert single ID to array
                }
                
                // Remove duplicates
                $principal_ids = array_unique($principal_ids);
                
                // Get principal names with links
                $principal_links = [];
                $processed_ids = [];
                
                foreach ($principal_ids as $user_id) {
                    // Skip if already processed
                    if (in_array($user_id, $processed_ids)) {
                        continue;
                    }
                    
                    $processed_ids[] = $user_id;
                    $user = get_user_by('id', $user_id);
                    
                    if ($user) {
                        // Find the principal post for this user
                        $principal_posts = get_posts([
                            'post_type' => HAM_CPT_PRINCIPAL,
                            'meta_key' => '_ham_user_id',
                            'meta_value' => $user_id,
                            'posts_per_page' => 1
                        ]);
                        
                        if (!empty($principal_posts)) {
                            $edit_link = get_edit_post_link($principal_posts[0]->ID);
                            $principal_links[] = $edit_link ? 
                                '<a href="' . esc_url($edit_link) . '">' . esc_html($user->display_name) . '</a>' : 
                                esc_html($user->display_name);
                        } else {
                            $principal_links[] = esc_html($user->display_name);
                        }
                    }
                }
                
                if (!empty($principal_links)) {
                    echo implode(', ', $principal_links);
                } else {
                    echo '&mdash;';
                }
                break;
                
            // Add case for 'principals' to handle the transition period where some tables might still use old column name
            case 'principals':
                // Call the method again with the new column name
                $this->render_custom_columns('ham_principals', $post_id);
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
        // Use the standardized column names
        $columns['ham_principals'] = 'ham_principals';
        
        // Keep backward compatibility for any existing sort URLs
        $columns['principals'] = 'ham_principals';
        
        return $columns;
    }

    /**
     * Handles the sorting logic for custom columns.
     *
     * @param WP_Query $query The WordPress query object.
     */
    public function sort_columns($query) {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== $this->post_type) {
            return;
        }
        
        // Only target the school CPT list table
        $screen = get_current_screen();
        if (!$screen || 'edit-' . HAM_CPT_SCHOOL !== $screen->id) {
            return;
        }
        
        $orderby = $query->get('orderby');
        
        // Sort by principals - this is more complex as schools can have multiple principals
        if ('ham_principals' === $orderby || 'principals' === $orderby) {
            add_filter('posts_clauses', [$this, 'modify_principal_sorting_query'], 10, 2);
        }
    }

    /**
     * Modifies the query clauses for sorting by principals.
     *
     * @param array    $clauses Query clauses.
     * @param WP_Query $query   Current query object.
     * @return array Modified query clauses.
     */
    public function modify_principal_sorting_query($clauses, $query) {
        if (is_admin() && $query->is_main_query() && 
            isset($query->query['orderby']) && 
            ($query->query['orderby'] === 'ham_principals' || $query->query['orderby'] === 'principals')) {
            
            // Remove this filter to avoid infinite loops
            remove_filter('posts_clauses', [$this, 'modify_principal_sorting_query']);
            
            // Instead of complex SQL joins, use a manual post-processing sort
            add_action('pre_get_posts', function($q) use ($query) {
                if ($q === $query) {
                    // Add an action to sort results after the main query
                    add_filter('the_posts', [$this, 'sort_schools_by_principals'], 10, 2);
                }
            });
            
            // No need for special SQL clauses - we'll sort manually
        }
        
        return $clauses;
    }
    
    /**
     * Sort schools by their assigned principals' names.
     * This is a manual PHP sort that runs after the query to avoid complex SQL.
     *
     * @param array    $posts Array of post objects.
     * @param WP_Query $query The current WP_Query object.
     * @return array Sorted posts.
     */
    public function sort_schools_by_principals($posts, $query) {
        if (!is_admin() || !isset($query->query['orderby']) || $query->query['orderby'] !== 'principals') {
            return $posts;
        }
        
        // Remove the filter to avoid infinite recursion
        remove_filter('the_posts', [$this, 'sort_schools_by_principals']);
        
        // Get principal names for each school
        $school_principals = [];
        foreach ($posts as $post) {
            $principal_ids = get_post_meta($post->ID, '_ham_principal_ids', true);
            if (!empty($principal_ids) && is_array($principal_ids)) {
                $principal_names = [];
                foreach ($principal_ids as $principal_id) {
                    $user_data = get_userdata($principal_id);
                    if ($user_data) {
                        $principal_names[] = $user_data->display_name;
                    }
                }
                sort($principal_names); // Sort principal names alphabetically
                $school_principals[$post->ID] = implode(', ', $principal_names);
            } else {
                $school_principals[$post->ID] = ''; // Empty string for schools with no principals
            }
        }
        
        // Sort schools by principal names
        usort($posts, function($a, $b) use ($school_principals, $query) {
            $a_principals = isset($school_principals[$a->ID]) ? $school_principals[$a->ID] : '';
            $b_principals = isset($school_principals[$b->ID]) ? $school_principals[$b->ID] : '';
            
            if ($query->get('order') === 'DESC') {
                return strcasecmp($b_principals, $a_principals);
            } else {
                return strcasecmp($a_principals, $b_principals);
            }
        });
        
        return $posts;
    }

    // This method was duplicated - removed first implementation

    /**
     * Modify the main query based on selected filters.
     *
     * @param WP_Query $query The WP_Query instance.
     */
    public function filter_query($query) {
        global $pagenow;
        $post_type = isset($_GET['post_type']) ? $_GET['post_type'] : '';

        if (is_admin() && $pagenow == 'edit.php' && HAM_CPT_SCHOOL === $post_type && $query->is_main_query()) {
            // Filter by principal
            if (isset($_GET['ham_principal_filter']) && $_GET['ham_principal_filter'] !== '-1') {
                // Debug
                //error_log('GET filter: principal_id = ' . sanitize_text_field($_GET['ham_principal_filter']));
                //error_log('$_GET params: ' . print_r($_GET, true));
                
                $filtered_principal_id = sanitize_text_field($_GET['ham_principal_filter']);
                $meta_query = $query->get('meta_query') ?: [];
                
                // Use the same comprehensive meta query approach as in the AJAX filter
                $principal_meta_query = [
                    'relation' => 'OR',
                    // Pattern 1: i:0;i:123; (when ID is first in array)
                    [
                        'key' => '_ham_principal_ids',
                        'value' => 'i:0;i:' . $filtered_principal_id . ';', 
                        'compare' => 'LIKE'
                    ],
                    // Pattern 2: i:123; (when ID is in middle/end of array)
                    [
                        'key' => '_ham_principal_ids',
                        'value' => 'i:' . $filtered_principal_id . ';', 
                        'compare' => 'LIKE'
                    ],
                    // Pattern 3: Sometimes stored as quoted strings
                    [
                        'key' => '_ham_principal_ids',
                        'value' => '"' . $filtered_principal_id . '"', 
                        'compare' => 'LIKE'
                    ],
                    // Pattern 4: Direct match (if stored as single value)
                    [
                        'key' => '_ham_principal_ids',
                        'value' => $filtered_principal_id, 
                        'compare' => '='
                    ]
                ];
                
                // If meta_query is already an array, we need to check if it already has a relation
                if (empty($meta_query['relation'])) {
                    $meta_query['relation'] = 'AND';
                }
                
                // Add our principal filter query to the existing meta query
                $meta_query[] = $principal_meta_query;
                
                //error_log('Setting meta query: ' . print_r($meta_query, true));
                $query->set('meta_query', $meta_query);
            }
        }
    }

    // add_table_filters method removed - using base class implementation
}

/**
 * Initialize the admin list table for schools.
 * The initialization is now handled through the admin loader, which ensures
 * it runs at the appropriate time in the WordPress lifecycle (after 'init' hook).
 */
add_action('admin_init', function() {
    if (is_admin()) {
        new HAM_School_Admin_List_Table();
    }
});

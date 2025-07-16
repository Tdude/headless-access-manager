<?php
/**
 * Base class for admin list tables.
 *
 * Provides a generic, extensible filtering system for all CPT admin list tables.
 * This class handles both AJAX and standard (form submit) filtering consistently.
 *
 * How to use this class:
 * 1. Extend this class for your CPT admin list table
 * 2. Set the $post_type property
 * 3. Call parent::__construct($post_type) in your constructor
 * 4. Register your CPT-specific filters using $this->register_filter()
 * 5. Implement any CPT-specific callbacks for complex filters
 *
 * @package HeadlessAccessManager
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class HAM_Base_Admin_List_Table
 * Provides base functionality for all CPT admin list tables with a unified filtering system.
 *
 * This class implements:
 * - Generic filter registration with register_filter()
 * - Automatic filter rendering in admin UI
 * - Filter processing for both AJAX and standard filtering
 * - Support for meta queries, taxonomy queries and custom query callbacks
 * - Debug logging for filter processing when WP_DEBUG is enabled
 */
abstract class HAM_Base_Admin_List_Table {
    /**
     * The post type for this admin list table.
     *
     * @var string
     */
    protected $post_type;

    /**
     * Filter fields configuration.
     *
     * @var array
     */
    protected $filter_fields = [];
    
    /**
     * Static array to track already rendered cells during AJAX requests.
     * Format: [post_id][column_name] => true
     *
     * @var array
     */
    protected static $rendered_cells = [];
    
    /**
     * Constructor.
     * 
     * @param string $post_type The post type for this admin list table.
     */
    public function __construct($post_type) {
        $this->post_type = $post_type;
        
        // Common hooks for all admin list tables
        add_filter("manage_{$post_type}_posts_columns", [$this, 'add_admin_columns']);
        add_action("manage_{$post_type}_posts_custom_column", [$this, 'render_custom_columns'], 10, 2);
        add_filter("manage_edit-{$post_type}_sortable_columns", [$this, 'make_columns_sortable']);
        add_action('pre_get_posts', [$this, 'sort_columns']);
        add_action('restrict_manage_posts', [$this, 'add_table_filters']);
        add_filter('parse_query', [$this, 'filter_query']);
        
        // Register hooks
        add_action('admin_init', [$this, 'register_columns']);
        add_filter("manage_edit-{$this->post_type}_sortable_columns", [$this, 'make_columns_sortable']);
        add_action('pre_get_posts', [$this, 'sort_columns']);
        add_action('restrict_manage_posts', [$this, 'add_table_filters']);
        
        // Register filter for AJAX rendering
        add_filter("ham_render_filtered_{$this->post_type}_table", [$this, 'render_filtered_table'], 10, 2);
        
        // Add a static property to track rendered cells during AJAX requests
        // This prevents duplicate cell rendering in AJAX responses
        static::$rendered_cells = [];
        
        // AJAX filter rendering - register as both action and filter for maximum compatibility
        // Modern code uses apply_filters to get the returned HTML directly
        add_filter("ham_render_filtered_{$post_type}_table", [$this, 'render_filtered_table'], 10, 2);
        // Legacy code might expect do_action to echo the output
        add_action("ham_render_filtered_{$post_type}_table", [$this, 'render_filtered_table']);
        
        // Enqueue necessary scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        
        // CRITICAL FIX: Add hook to wrap the list table in a container on initial page load
        // This ensures the DOM structure matches between initial page load and AJAX updates
        add_action('admin_footer', [$this, 'wrap_list_table_in_container']);
    }
    
    /**
     * Register a filter field for this admin list table.
     *
     * This method is used by extending classes to register filters specific to their CPT.
     * Each filter will be automatically rendered in the admin UI and processed when filtering.
     *
     * @param string $filter_name The filter name (used in URL parameters).
     * @param array  $args        Filter arguments and configuration.
     *
     * @example
     * // Basic meta filter (school ID):
     * $this->register_filter('ham_filter_school_id', [
     *     'type' => 'meta',
     *     'meta_key' => '_ham_school_id',
     *     'label' => 'School',
     *     'placeholder' => 'Filter by School',
     *     'field_type' => 'select',
     *     'options_callback' => [$this, 'get_school_options']
     * ]);
     */
    protected function register_filter($filter_name, $args) {
        $this->filter_fields[$filter_name] = wp_parse_args($args, [
            'type' => 'meta', // meta, taxonomy, custom
            'meta_key' => '',
            'taxonomy' => '',
            'compare' => '=',
            'label' => '',
            'placeholder' => __('Filter by', 'headless-access-manager'),
            'field_type' => 'select',
            'options' => [],
            'options_callback' => null,
            'meta_query_callback' => null,
            'taxonomy_query_callback' => null,
            'custom_query_callback' => null,
        ]);
    }
    
    /**
     * Enqueues scripts and styles for AJAX filtering.
     *
     * @param string $hook The current admin page.
     */
    public function enqueue_scripts($hook) {
        global $typenow;
        
        // Only load on the edit.php page for our post type
        if ('edit.php' !== $hook || $this->post_type !== $typenow) {
            return;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("HAM: Enqueuing scripts for {$this->post_type} admin list table");
        }
        
        wp_enqueue_style(
            'ham-admin-list-table',
            HAM_PLUGIN_URL . 'assets/css/admin-list-table.css',
            [],
            HAM_VERSION
        );
        
        // Enqueue the script only once
        if (!wp_script_is('ham-ajax-filters', 'enqueued')) {
            wp_enqueue_script(
                'ham-ajax-filters',
                HAM_PLUGIN_URL . 'assets/js/ham-ajax-table-filters.js',
                ['jquery'],
                HAM_VERSION,
                true
            );
            
            // CRITICAL: Add localized script data that the AJAX filtering depends on
            wp_localize_script('ham-ajax-filters', 'hamAjaxFilters', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ham_ajax_filter_nonce'),
                'postType' => $this->post_type,
                'i18n' => [
                    'loading' => __('Loading...', 'headless-access-manager'),
                    'error' => __('Error loading content', 'headless-access-manager'),
                    'noResults' => __('No results found', 'headless-access-manager'),
                ],
            ]);
            
            // Add CSS for the loading indicator
            wp_add_inline_style('wp-admin', '
                .ham-ajax-loading {
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    width: 40px;
                    height: 40px;
                    background: url(' . admin_url('images/spinner-2x.gif') . ') no-repeat;
                    background-size: 40px 40px;
                    z-index: 1000;
                }
                .ham-reset-filters {
                    margin-left: 10px !important;
                }
            ');
        }
    }
    
    /**
     * Renders a filtered table via AJAX.
     *
     * @param string $html    Empty string passed by apply_filters.
     * @param array  $filters The filter parameters from AJAX.
     * @return string HTML for the filtered table.
     */
    public function render_filtered_table($html = '', $filters = []) {
        // CRITICAL FIX: WordPress passes filter parameters individually, not as an array
        // When called via apply_filters, $html is empty string and $filters is the filters array
        // When called directly, $filters might be the filters array and $html would be empty
        if (is_array($html) && empty($filters)) {
            $filters = $html;
            $html = '';
        }
        
        // Ensure $filters is always an array
        if (!is_array($filters)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('HAM DEBUG - CRITICAL ERROR: Filters is not an array in render_filtered_table: ' . gettype($filters) . ', converting to empty array');
            }
            $filters = [];
        }
        global $wp_query, $post_type, $wp_list_table;
        
        // Enhanced debugging - log class and post type information
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('HAM render_filtered_table CALLED for ' . $this->post_type . ' from class: ' . get_class($this));
            error_log('HAM render_filtered_table FILTERS for ' . $this->post_type . ': ' . print_r($filters, true));
            
            // CRITICAL FIX: Ensure filter_fields is an array before using array_keys
            if (!is_array($this->filter_fields)) {
                error_log('CRITICAL ERROR: filter_fields is not an array in ' . get_class($this) . '. Actual type: ' . gettype($this->filter_fields));
                $this->filter_fields = is_array($this->filter_fields) ? $this->filter_fields : [];
            }
            
            error_log('HAM registered filter fields for ' . $this->post_type . ': ' . print_r(is_array($this->filter_fields) ? array_keys($this->filter_fields) : [], true));
        }
        
        // CRITICAL FIX: Store original _GET values
        $original_get = $_GET;
        
        // CRITICAL FIX: Temporarily populate $_GET with filter values from POST
        // This ensures filter_query() will process the filters correctly
        foreach ($filters as $key => $value) {
            if (!empty($value) && $value !== '-1') {
                $_GET[$key] = $value;
            }
        }
        
        // Ensure post_type is set correctly for the query filtering
        $_GET['post_type'] = $this->post_type;
        
        // Set up a custom query with the filters
        $args = [
            'post_type' => $this->post_type,
            'posts_per_page' => 20,
            'paged' => isset($filters['paged']) ? (int) $filters['paged'] : 1,
        ];
        
        // Add ordering if present
        if (!empty($filters['orderby'])) {
            $args['orderby'] = sanitize_text_field($filters['orderby']);
            $args['order'] = !empty($filters['order']) ? sanitize_text_field($filters['order']) : 'ASC';
        }
        
        // DEBUG: Log original filters before processing
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('HAM DEBUG - AJAX FILTERS BEFORE PROCESSING: ' . print_r($filters, true));
            error_log('HAM DEBUG - QUERY ARGS BEFORE FILTERS: ' . print_r($args, true));
        }
        
        // Apply filters to query args using the generic filter system
        $this->apply_filters_to_query_args($args, $filters);
        
        // DEBUG: Log final query args after processing
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('HAM DEBUG - QUERY ARGS AFTER FILTERS: ' . print_r($args, true));
        }
        
        // Run the query
        $wp_query = new WP_Query($args);
        $post_type = $this->post_type;
        
        // Enhanced debugging - BEFORE list table creation
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('HAM render_filtered_table BEFORE LIST TABLE - Query found ' . $wp_query->found_posts . ' posts for ' . $this->post_type);
            // Print first post ID and title if available
            if ($wp_query->have_posts()) {
                $first_post = $wp_query->posts[0];
                error_log('HAM render_filtered_table FIRST POST - ID: ' . $first_post->ID . ', Title: ' . $first_post->post_title);
            }
        }
        
        // Start output buffering
        ob_start();
        
        // Output the list table
        require_once(ABSPATH . 'wp-admin/includes/class-wp-posts-list-table.php');
        $wp_list_table = _get_list_table('WP_Posts_List_Table');
        
        // CRITICAL FIX: Ensure columns are consistently defined before rendering
        // This prevents duplicate columns when rendering via AJAX
        add_filter("manage_{$this->post_type}_posts_columns", function($columns) {
            // Check if this is an AJAX request
            $is_ajax = defined('DOING_AJAX') && DOING_AJAX;
            
            if (defined('WP_DEBUG') && WP_DEBUG && $is_ajax) {
                error_log('HAM: Current columns structure before cleanup: ' . print_r($columns, true));
            }
            
            // First run the column customization through the child class's method
            $columns = $this->add_admin_columns($columns);
            
            // Aggressive column normalization for AJAX requests to prevent duplication
            if ($is_ajax) {
                // Keep track of columns we want to preserve with normalized keys
                $normalized_columns = [];
                
                // Special columns that should always be preserved
                $always_preserve = ['cb', 'title', 'date', 'author'];
                
                // Build a new column array with normalized keys
                foreach ($always_preserve as $key) {
                    if (isset($columns[$key])) {
                        $normalized_columns[$key] = $columns[$key];
                    }
                }
                
                // Handle custom columns with potential duplication
                $seen_labels = [];
                foreach ($columns as $key => $label) {
                    // Skip standard columns we already preserved
                    if (in_array($key, $always_preserve)) {
                        continue;
                    }
                    
                    // Get base name without prefix
                    $base_name = preg_replace('/^ham_/', '', $key);
                    
                    // Create a normalized key for consistent naming
                    $normalized_key = $base_name; // Use the non-prefixed version consistently
                    
                    // Check if we've seen this label before to prevent duplicates
                    if (!in_array($label, $seen_labels)) {
                        $normalized_columns[$normalized_key] = $label;
                        $seen_labels[] = $label;
                        
                        if (defined('WP_DEBUG') && WP_DEBUG && $is_ajax) {
                            error_log("HAM: Keeping column {$key} as {$normalized_key}");
                        }
                    } else {
                        if (defined('WP_DEBUG') && WP_DEBUG && $is_ajax) {
                            error_log("HAM: Skipping duplicate column {$key} with label {$label}");
                        }
                    }
                }
                
                $columns = $normalized_columns;
            } else {
                // Regular non-AJAX handling - still check for duplicates
                $clean_columns = [];
                $seen_names = [];
                
                foreach ($columns as $key => $label) {
                    // Check for ham_ prefix or standard name without prefix
                    $base_name = preg_replace('/^ham_/', '', $key);
                    
                    // If we've already seen this base name, skip adding this column
                    if (in_array($base_name, $seen_names)) {
                        continue;
                    }
                    
                    // Add to our cleaned columns and mark as seen
                    $clean_columns[$key] = $label;
                    $seen_names[] = $base_name;
                }
                
                $columns = $clean_columns;
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG && $is_ajax) {
                error_log('HAM: Final columns structure after cleanup: ' . print_r($columns, true));
            }
            
            return $columns;
        }, 99999); // Very high priority to override everything else
        
        // Debug list table object
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('HAM render_filtered_table LIST TABLE - Class: ' . get_class($wp_list_table));
            error_log('HAM render_filtered_table LIST TABLE - Methods: ' . implode(', ', get_class_methods($wp_list_table)));
        }
        
        // Prepare the list table items - this will run WP_Query with filters
        $wp_list_table->prepare_items();
        
        // Display the table with a specific class to make it easier for JavaScript to find
        echo '<div class="wp-list-table-container" data-post-type="' . esc_attr($this->post_type) . '">';
        $wp_list_table->display();
        echo '</div>';
        
        // Capture output
        $table_html = ob_get_clean();
        
        // CRITICAL FIX: Restore original $_GET values
        $_GET = $original_get;
        
        // Debug output
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('HAM AJAX Filter results HTML length: ' . strlen($table_html));
        }
        
        return $table_html;
    }
    
    /**
     * Adds custom columns to the admin list table.
     * 
     * @param array $columns Existing columns.
    
    error_log('HAM registered filter fields for ' . $this->post_type . ': ' . print_r(is_array($this->filter_fields) ? array_keys($this->filter_fields) : [], true));
}

// CRITICAL FIX: Store original _GET values
$original_get = $_GET;

// CRITICAL FIX: Temporarily populate $_GET with filter values from POST
// This ensures filter_query() will process the filters correctly
foreach ($filters as $key => $value) {
    if (!empty($value) && $value !== '-1') {
        $_GET[$key] = $value;
    }
}

// Ensure post_type is set correctly for the query filtering
$_GET['post_type'] = $this->post_type;

// Set up a custom query with the filters
$args = [
    'post_type' => $this->post_type,
    'posts_per_page' => 20,
    'paged' => isset($filters['paged']) ? (int) $filters['paged'] : 1,
];

// Add ordering if present
if (!empty($filters['orderby'])) {
    $args['orderby'] = sanitize_text_field($filters['orderby']);
    $args['order'] = !empty($filters['order']) ? sanitize_text_field($filters['order']) : 'ASC';
}

// DEBUG: Log original filters before processing
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('HAM DEBUG - AJAX FILTERS BEFORE PROCESSING: ' . print_r($filters, true));
    error_log('HAM DEBUG - QUERY ARGS BEFORE FILTERS: ' . print_r($args, true));
}

// Apply filters to query args using the generic filter system
$this->apply_filters_to_query_args($args, $filters);

// DEBUG: Log final query args after processing
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('HAM DEBUG - QUERY ARGS AFTER FILTERS: ' . print_r($args, true));
}

// Run the query
$wp_query = new WP_Query($args);
$post_type = $this->post_type;

// Enhanced debugging - BEFORE list table creation
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('HAM render_filtered_table BEFORE LIST TABLE - Query found ' . $wp_query->found_posts . ' posts for ' . $this->post_type);
    // Print first post ID and title if available
    if ($wp_query->have_posts()) {
        $first_post = $wp_query->posts[0];
        error_log('HAM render_filtered_table FIRST POST - ID: ' . $first_post->ID . ', Title: ' . $first_post->post_title);
    }
}

// Start output buffering
ob_start();

// Output the list table
require_once(ABSPATH . 'wp-admin/includes/class-wp-posts-list-table.php');
$wp_list_table = _get_list_table('WP_Posts_List_Table');

// CRITICAL FIX: Ensure columns are consistently defined before rendering
// This prevents duplicate columns when rendering via AJAX
add_filter("manage_{$this->post_type}_posts_columns", function($columns) {
    // Check if this is an AJAX request
    $is_ajax = defined('DOING_AJAX') && DOING_AJAX;
    
    if (defined('WP_DEBUG') && WP_DEBUG && $is_ajax) {
        error_log('HAM: Current columns structure before cleanup: ' . print_r($columns, true));
    }
    
    // First run the column customization through the child class's method
    $columns = $this->add_admin_columns($columns);
    
    // Aggressive column normalization for AJAX requests to prevent duplication
    if ($is_ajax) {
        // Keep track of columns we want to preserve with normalized keys
        $normalized_columns = [];
        
        // Special columns that should always be preserved
        $always_preserve = ['cb', 'title', 'date', 'author'];
        
        // Build a new column array with normalized keys
        foreach ($always_preserve as $key) {
            if (isset($columns[$key])) {
                $normalized_columns[$key] = $columns[$key];
            }
        }
        
        // Handle custom columns with potential duplication
        $seen_labels = [];
        foreach ($columns as $key => $label) {
            // Skip standard columns we already preserved
            if (in_array($key, $always_preserve)) {
                continue;
            }
            
            // Get base name without prefix
            $base_name = preg_replace('/^ham_/', '', $key);
            
            // Create a normalized key for consistent naming
            $normalized_key = $base_name; // Use the non-prefixed version consistently
            
            // Check if we've seen this label before to prevent duplicates
            if (!in_array($label, $seen_labels)) {
                $normalized_columns[$normalized_key] = $label;
                $seen_labels[] = $label;
                
                if (defined('WP_DEBUG') && WP_DEBUG && $is_ajax) {
                    error_log("HAM: Keeping column {$key} as {$normalized_key}");
                }
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG && $is_ajax) {
                    error_log("HAM: Skipping duplicate column {$key} with label {$label}");
                }
            }
        }
        
        $columns = $normalized_columns;
    } else {
        // Regular non-AJAX handling - still check for duplicates
        $clean_columns = [];
        $seen_names = [];
     * @return array             Modified columns array.
     */
    protected function standardize_columns($columns, $new_cols, $prefix = '') {
        $new_columns = [];
        
        foreach ($columns as $key => $value) {
            // Add the existing column
            $new_columns[$key] = $value;
            
            // After title, add our custom columns
            if ($key === 'title') {
                foreach ($new_cols as $col_key => $col_label) {
                    // Use the column key as is (no prefix for consistency)
                    $new_columns[$col_key] = $col_label;
                    
                    // Remove any prefixed duplicate columns (ham_school, etc)
                    $prefixed_key = 'ham_' . $col_key;
                    if (isset($new_columns[$prefixed_key])) {
                        unset($new_columns[$prefixed_key]);
                    }
                }
            }
        }
        
        // Additional cleanup for any prefixed columns that might exist elsewhere
        foreach ($new_cols as $col_key => $col_label) {
            $prefixed_key = 'ham_' . $col_key;
            
            // If both the prefixed and non-prefixed columns exist, keep only the non-prefixed one
            if (isset($new_columns[$col_key]) && isset($new_columns[$prefixed_key])) {
                unset($new_columns[$prefixed_key]);
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Helper method to retrieve relationship data for custom columns in a standardized way.
     * 
     * @param int    $post_id   The ID of the current post.
     * @param string $meta_key  The meta key to look up.
     * @param string $post_type The post type of related items.
     * @param string $output    Output format: 'links' (linked titles) or 'titles' (plain text).
     * @return string           Formatted relationship data.
     */
    protected function get_relationship_data($post_id, $meta_key, $post_type, $output = 'links') {
        // Get the relationship data
        $related_ids = get_post_meta($post_id, $meta_key, true);
        
        // Handle empty or invalid data
        if (empty($related_ids)) {
            return '&mdash;';
        }
        
        // Ensure we have an array of IDs
        if (!is_array($related_ids)) {
            $related_ids = [$related_ids]; // Convert single ID to array
        }
        
        // Remove duplicates
        $related_ids = array_unique($related_ids);
        
        // Get related items
        $related_items = [];
        
        foreach ($related_ids as $id) {
            if (!$id) continue;
            
            $title = get_the_title($id);
            
            if (!$title) continue;
            
            if ($output === 'links') {
                $edit_link = get_edit_post_link($id);
                $related_items[] = $edit_link ? 
                    '<a href="' . esc_url($edit_link) . '">' . esc_html($title) . '</a>' : 
                    esc_html($title);
            } else {
                $related_items[] = esc_html($title);
            }
        }
        
        if (empty($related_items)) {
            return '&mdash;';
        }
        
        return implode(', ', $related_items);
    }
    
    /**
     * Makes columns sortable in the admin list table.
     * 
     * @param array $columns The sortable columns.
     * @return array Modified sortable columns.
     */
    abstract public function make_columns_sortable($columns);
    
    /**
     * Handles the sorting logic for custom columns.
     * 
     * @param WP_Query $query The WordPress query object.
     */
    abstract public function sort_columns($query);
    
    // Note: add_table_filters is implemented as a concrete method in this base class
    
    /**
     * Modifies the main query based on selected filters.
     * 
     * @param WP_Query $query The WP_Query instance.
     */
    public function filter_query($query) {
        // Only apply filters to the main admin query for this post type
        if (!is_admin() || !$query->is_main_query() || empty($_GET['post_type']) || $_GET['post_type'] !== $this->post_type) {
            return;
        }
        
        // Get filter values from query string
        $filters = [];
        
        // CRITICAL FIX: Ensure filter_fields is an array before using array_keys
        $this->filter_fields = is_array($this->filter_fields) ? $this->filter_fields : [];
        
        foreach (array_keys($this->filter_fields) as $filter_name) {
            if (isset($_GET[$filter_name]) && $_GET[$filter_name] !== '-1' && $_GET[$filter_name] !== '') {
                $filters[$filter_name] = sanitize_text_field($_GET[$filter_name]);
            }
        }
        
        // Debug
        if (!empty($filters) && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('HAM filter_query for ' . $this->post_type . ' - GET params: ' . print_r($_GET, true));
            error_log('HAM filter_query for ' . $this->post_type . ' - Filters: ' . print_r($filters, true));
        }
        
        // Apply filters to the query
        if (!empty($filters)) {
            $args = $query->query_vars;
            $this->apply_filters_to_query_args($args, $filters);
            $query->query_vars = $args;
        }
    }
    
    /**
     * Register a filter field for this admin list table.
     * 
     * @param string $name       Filter parameter name (used in the URL/form).
     * @param array  $config     Filter configuration array.
     *     @type string $type    Filter type: 'meta', 'taxonomy', or 'custom'.
    /**
     * Get a registered filter config.
     * 
     * @param string $name Filter name.
     * @return array|null Filter config or null if not found.
     */
    protected function get_filter_config($name) {
        return isset($this->filter_fields[$name]) ? $this->filter_fields[$name] : null;
    }

    /**
     * Get all registered filter fields.
     * 
     * @return array Registered filter fields.
     */
    protected function get_filter_fields() {
        return $this->filter_fields;
    }

    /**
     * Render a filter field.
     * 
     * @param string $name   Filter name.
     * @param mixed  $value  Current filter value.
     */
    protected function render_filter_field($name, $value = null) {
        if (!isset($this->filter_fields[$name])) {
            return;
        }
        
        $config = $this->filter_fields[$name];
        $field_type = $config['field_type'];
        $label = $config['label'];
        $placeholder = $config['placeholder'];
        
        switch ($field_type) {
            case 'select':
                echo '<select name="' . esc_attr($name) . '" class="ham-ajax-filter">';
                echo '<option value="-1">' . esc_html($placeholder) . '</option>';
                
                foreach ($config['options'] as $option_value => $option_label) {
                    $selected = selected($value, $option_value, false);
                    echo '<option value="' . esc_attr($option_value) . '" ' . $selected . '>' . esc_html($option_label) . '</option>';
                }
                
                echo '</select>';
                break;
                
            case 'text':
                echo '<input type="text" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" placeholder="' . esc_attr($placeholder) . '" class="ham-ajax-filter" />';
                break;
                
            default:
                // For custom field types, use the callback if provided
                if (isset($config['render_callback']) && is_callable($config['render_callback'])) {
                    call_user_func($config['render_callback'], $name, $value, $config);
                }
        }
    }
    
    /**
     * Add generic table filters based on registered filter fields.
     * 
     * @param string $post_type Current post type.
     */
    public function add_table_filters($post_type) {
        if ($post_type !== $this->post_type) {
            return;
        }
        
        // Get filter values from query string
        $filter_values = [];
        foreach (array_keys($this->filter_fields) as $filter_name) {
            $filter_values[$filter_name] = isset($_GET[$filter_name]) ? sanitize_text_field($_GET[$filter_name]) : null;
        }
        
        // Render registered filters
        foreach ($this->filter_fields as $name => $config) {
            // For some filter fields, we may need to populate options dynamically
            if (isset($config['options_callback']) && is_callable($config['options_callback'])) {
                $this->filter_fields[$name]['options'] = call_user_func($config['options_callback']);
            }
            
            // Render the filter field
            $this->render_filter_field($name, $filter_values[$name] ?? null);
        }
        
        // Add a reset filters button if we have filters
        if (!empty($this->filter_fields)) {
            $list_url = admin_url('edit.php?post_type=' . $this->post_type);
            echo ' <a href="' . esc_url($list_url) . '" class="button ham-reset-filters">' . __('Reset Filters', 'headless-access-manager') . '</a>';
        }
    }

    /**
     * Applies filter values to a query args array.
     * 
     * @param array $args    Query args to modify.
     * @param array $filters Filter values to apply.
     */
    public function apply_filters_to_query_args(&$args, $filters) {
        // CRITICAL FIX: Ensure filters is an array
        $filters = is_array($filters) ? $filters : [];
        
        // CRITICAL FIX: Ensure filter_fields is an array
        $this->filter_fields = is_array($this->filter_fields) ? $this->filter_fields : [];
        // Process each registered filter
        $meta_queries = [];
        $tax_queries = [];
        
        // Enhanced debugging - Before filtering
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('HAM apply_filters_to_query_args BEFORE for ' . $this->post_type . ' - Args: ' . print_r($args, true));
            error_log('HAM apply_filters_to_query_args INPUT filters for ' . $this->post_type . ': ' . print_r($filters, true));
            error_log('HAM filter fields registered for ' . $this->post_type . ': ' . print_r($this->filter_fields, true));
            
            // Log filter fields and associated callbacks
            $registered_callbacks = [];
            foreach ($this->filter_fields as $field_name => $field_config) {
                $callback_info = [
                    'name' => $field_name,
                    'type' => $field_config['type'] ?? 'unknown',
                ];
                
                if (isset($field_config['meta_query_callback'])) {
                    $callback_info['has_meta_query_callback'] = true;
                }
                
                if (isset($field_config['callback'])) {
                    $callback_info['has_callback'] = true;
                }
                
                if (isset($field_config['custom_query_callback'])) {
                    $callback_info['has_custom_query_callback'] = true;
                }
                
                $registered_callbacks[] = $callback_info;
            }
            error_log('HAM filter callbacks for ' . $this->post_type . ': ' . print_r($registered_callbacks, true));
        }
        
        // Enhanced debug - trace all registered filter names vs. incoming filter parameters
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // Ensure filter_fields is an array
            $this->filter_fields = is_array($this->filter_fields) ? $this->filter_fields : [];
            error_log('HAM DEBUG - REGISTERED FILTER NAMES for ' . $this->post_type . ': ' . print_r(array_keys($this->filter_fields), true));
            // Ensure filters is an array
            $filters = is_array($filters) ? $filters : [];
            error_log('HAM DEBUG - INCOMING FILTER PARAMETERS for ' . $this->post_type . ': ' . print_r(array_keys($filters), true));
            
            // Check for filter name mismatches
            foreach ($filters as $filter_key => $filter_value) {
                if (!isset($this->filter_fields[$filter_key]) && strpos($filter_key, 'ham_filter_') !== false) {
                    error_log('HAM DEBUG - CRITICAL: Incoming filter parameter "' . $filter_key . '" not found in registered filters!');
                }
            }
        }
        
        foreach ($this->filter_fields as $name => $config) {
            // Skip if filter value is empty or default (-1)
            if (empty($filters[$name]) || $filters[$name] === '-1') {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('HAM DEBUG - Skipping filter "' . $name . '" because value is empty or -1: ' . (isset($filters[$name]) ? $filters[$name] : 'NOT SET'));
                }
                continue;
            }
            
            // CRITICAL ENHANCED DEBUGGING
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('HAM DEBUG - CONFIRMED PROCESSING filter "' . $name . '" with value: ' . $filters[$name]);
                error_log('HAM DEBUG - Filter config: ' . print_r($config, true));
            }
            
            // Log that we're processing this filter
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('HAM DEBUG - Processing filter "' . $name . '" with value: ' . $filters[$name]);
            }
            
            $value = sanitize_text_field($filters[$name]);
            
            switch ($config['type']) {
                case 'meta':
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('HAM DEBUG - Processing meta filter: ' . $name . ' with meta_key: ' . ($config['meta_key'] ?? 'NOT SET'));
                    }
                    
                    if (!empty($config['meta_query_callback']) && is_callable($config['meta_query_callback'])) {
                        // For complex meta queries, use the callback
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('HAM DEBUG - Using meta_query_callback for: ' . $name);
                        }
                        $meta_query = call_user_func($config['meta_query_callback'], $name, $value, $filters);
                        if (!empty($meta_query)) {
                            $meta_queries[] = $meta_query;
                            if (defined('WP_DEBUG') && WP_DEBUG) {
                                error_log('HAM DEBUG - Added meta query from callback: ' . print_r($meta_query, true));
                            }
                        } else {
                            if (defined('WP_DEBUG') && WP_DEBUG) {
                                error_log('HAM DEBUG - Empty meta query returned from callback');
                            }
                        }
                    } else {
                        // Simple meta query
                        $compare = isset($config['compare']) ? $config['compare'] : '=';
                        $meta_query = [
                            'key' => $config['meta_key'],
                            'value' => $value,
                            'compare' => $compare,
                        ];
                        $meta_queries[] = $meta_query;
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('HAM DEBUG - Added simple meta query: ' . print_r($meta_query, true));
                        }
                    }
                    break;
                    
                case 'taxonomy':
                    if (!empty($config['taxonomy'])) {
                        $tax_queries[] = [
                            'taxonomy' => $config['taxonomy'],
                            'field' => 'term_id',
                            'terms' => (int) $value,
                        ];
                    }
                    break;
                    
                case 'custom':
                    // For custom filter types, use the callback (check both callback and custom_query_callback)
                    $callback = null;
                    if (!empty($config['callback']) && is_callable($config['callback'])) {
                        $callback = $config['callback'];
                    } elseif (!empty($config['custom_query_callback']) && is_callable($config['custom_query_callback'])) {
                        $callback = $config['custom_query_callback'];
                    }
                    
                    if ($callback) {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('HAM custom filter executing callback for: ' . $name);
                        }
                        call_user_func_array($callback, [&$args, $name, $value, $filters]);
                    } else {
                        error_log('HAM ERROR: No valid callback found for custom filter: ' . $name);
                    }
                    break;
            }
        }
        
        // Add meta query if we have any
        if (!empty($meta_queries)) {
            // Initialize meta_query array if it doesn't exist
            if (!isset($args['meta_query'])) {
                $args['meta_query'] = [];
            }
            
            // Add relation clause if we have multiple queries
            if (count($meta_queries) > 1) {
                if (!isset($args['meta_query']['relation'])) {
                    $args['meta_query']['relation'] = 'AND';
                }
                // Add each meta query directly to the meta_query array
                foreach ($meta_queries as $meta_query) {
                    $args['meta_query'][] = $meta_query;
                }
            } else {
                // Just add the single meta query
                $args['meta_query'][] = $meta_queries[0];
            }
            
            // Debug the final meta query structure
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('HAM DEBUG - Final meta_query structure: ' . print_r($args['meta_query'], true));
            }
        }
        
        // Add taxonomy query if we have any
        if (!empty($tax_queries)) {
            // Initialize tax_query array if it doesn't exist
            if (!isset($args['tax_query'])) {
                $args['tax_query'] = [];
            }
            
            // Add relation clause if we have multiple queries
            if (count($tax_queries) > 1) {
                if (!isset($args['tax_query']['relation'])) {
                    $args['tax_query']['relation'] = 'AND';
                }
                // Add each taxonomy query directly to the tax_query array
                foreach ($tax_queries as $tax_query) {
                    $args['tax_query'][] = $tax_query;
                }
            } else {
                // Just add the single taxonomy query
                $args['tax_query'][] = $tax_queries[0];
            }
            
            // Debug the final taxonomy query structure
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('HAM DEBUG - Final tax_query structure: ' . print_r($args['tax_query'], true));
            }
        }
        
        // Enhanced debugging - log the final query args and applied filters
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('HAM filter debug - FINAL ARGS for post type: ' . $this->post_type);
            error_log('Final query args: ' . print_r($args, true));
            
            // Log which filters were applied
            $applied_filters = [];
            foreach ($filters as $filter_name => $value) {
                if (!empty($value) && $value !== '-1' && isset($this->filter_fields[$filter_name])) {
                    $applied_filters[$filter_name] = [
                        'value' => $value,
                        'config' => $this->filter_fields[$filter_name],
                    ];
                }
            }
            error_log('HAM applied filters for ' . $this->post_type . ': ' . print_r($applied_filters, true));
        }
    }
    
    /**
     * Wrap the WordPress admin list table in a container div using JavaScript.
     * This ensures that the DOM structure matches between initial page load and AJAX updates.
     */
    public function wrap_list_table_in_container() {
        global $pagenow, $post_type;
        
        // Only execute on the edit.php page for our post type
        if ($pagenow !== 'edit.php' || $post_type !== $this->post_type) {
            return;
        }
        
        // Output JavaScript to wrap the list table in a container div
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Check if we're on the right screen and if the table exists
                if ($('.wp-list-table').length > 0 && $('.wp-list-table-container').length === 0) {
                    // Wrap the list table in a container div
                    $('.wp-list-table').wrap('<div class="wp-list-table-container" data-post-type="<?php echo esc_attr($this->post_type); ?>"></div>');
                    
                    if (window.console && console.log) {
                        console.log('HAM: Wrapped list table in container div for post type: <?php echo esc_attr($this->post_type); ?>');
                    }
                }
            });
        </script>
        <?php
    }
}

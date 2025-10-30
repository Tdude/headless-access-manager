<?php
/**
 * AJAX handlers for the plugin.
 *
 * @package HeadlessAccessManager
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class HAM_Ajax_Handlers
 * 
 * Provides AJAX functionality for the plugin.
 */
class HAM_Ajax_Handlers {
    /**
     * Initialize the AJAX handlers.
     */
    public static function init() {
        // Existing AJAX handlers
        add_action('wp_ajax_ham_search_students', [__CLASS__, 'search_students']);
        
        // New AJAX handler for filtering admin list tables
        add_action('wp_ajax_ham_filter_admin_list_table', [__CLASS__, 'filter_admin_list_table']);
    }

    /**
     * AJAX handler for student search.
     */
    public static function search_students() {
        check_ajax_referer('ham_ajax_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Unauthorized');
        }

        $search = sanitize_text_field($_GET['q'] ?? '');
        $students = get_posts([
            'post_type' => HAM_CPT_STUDENT,
            's' => $search,
            'posts_per_page' => 20,
            'post_status' => 'publish'
        ]);

        $results = array_map(function($student) {
            return [
                'id' => $student->ID,
                'text' => $student->post_title
            ];
        }, $students);

        wp_send_json($results);
    }
    
    /**
     * AJAX handler for filtering admin list tables.
     * 
     * This provides a generic handler that works with any CPT admin list table
     * that extends the HAM_Base_Admin_List_Table class.
     */
    public static function filter_admin_list_table() {
        // Add comprehensive error handling
        try {
            // Start output buffering to catch any unexpected output
            ob_start();
            
            // Only log critical errors, not routine operations
            
            // Validate required parameters
            if (!isset($_POST['nonce']) || !isset($_POST['post_type'])) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('HAM AJAX FILTER ERROR: Missing required parameters');
                }
                wp_send_json_error(['message' => 'Missing required parameters']);
                return;
            }
            
            // Check nonce (must match the name used in admin-loader.php)
            if (!wp_verify_nonce($_POST['nonce'], 'ham_ajax_filter_nonce')) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('HAM AJAX FILTER ERROR: Invalid nonce');
                }
                wp_send_json_error(['message' => 'Security check failed']);
                return;
            }
            
            // Check permissions
            if (!current_user_can('edit_posts')) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('HAM AJAX FILTER ERROR: Unauthorized');
                }
                wp_send_json_error(['message' => 'Unauthorized']);
                return;
            }
            
            // Get and validate post type
            $post_type = sanitize_text_field($_POST['post_type']);
            if (empty($post_type)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('HAM AJAX FILTER ERROR: Empty post type');
                }
                wp_send_json_error(['message' => 'Invalid post type']);
                return;
            }
            
            // Verify the CPT handler is properly registered
            if (defined('WP_DEBUG') && WP_DEBUG) {
                self::verify_handler_registration($post_type);
            }
            
            // Execute both filter and action approaches for maximum compatibility
            $table_html = self::execute_filter_handlers($post_type);
            
            // Clear any unexpected output before checking the result
            $output = ob_get_clean();
            if (!empty($output) && defined('WP_DEBUG') && WP_DEBUG) {
                //error_log('HAM AJAX FILTER UNEXPECTED OUTPUT: ' . $output);
            }
            
            // Only proceed with response if we have valid HTML
            if (empty($table_html)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("HAM AJAX FILTER CRITICAL ERROR: Empty table HTML for {$post_type}");
                }
                wp_send_json_error(['message' => 'No content returned for ' . $post_type]);
                return;
            }
            
            // Success - send the HTML response
            if (defined('WP_DEBUG') && WP_DEBUG) {
                //error_log("HAM AJAX FILTER SUCCESS: Sending response for {$post_type} with table HTML length: " . strlen($table_html));
                //error_log("HAM AJAX FILTER HTML SAMPLE: " . substr($table_html, 0, 100) . '...');
                //error_log('====== HAM AJAX FILTER REQUEST END ======');
            }
            
            wp_send_json_success($table_html);
            
        } catch (Exception $e) {
            // Log the exception details
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('HAM AJAX FILTER EXCEPTION: ' . $e->getMessage());
                error_log('HAM AJAX FILTER EXCEPTION TRACE: ' . $e->getTraceAsString());
            }
            
            // Clean any output to prevent JSON corruption
            if (ob_get_length()) {
                ob_clean();
            }
            
            wp_send_json_error(['message' => 'Exception: ' . $e->getMessage()]);
            
        } catch (Error $e) {
            // Catch PHP 7+ fatal errors
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('HAM AJAX FILTER FATAL ERROR: ' . $e->getMessage());
                error_log('HAM AJAX FILTER FATAL ERROR TRACE: ' . $e->getTraceAsString());
            }
            
            // Clean any output
            if (ob_get_length()) {
                ob_clean();
            }
            
            wp_send_json_error(['message' => 'Fatal error: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Verifies that the handler is properly registered for the given post type.
     *
     * @param string $post_type The post type to check.
     */
    private static function verify_handler_registration($post_type) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        // Check if the action is registered for this CPT
        global $wp_filter;
        
        if (isset($wp_filter["ham_render_filtered_{$post_type}_table"])) {
            //error_log("HAM AJAX: Found registered action: ham_render_filtered_{$post_type}_table");
            
            // Get the actual callbacks registered
            $callbacks_found = 0;
            foreach ($wp_filter["ham_render_filtered_{$post_type}_table"]->callbacks as $priority => $hooks) {
                foreach ($hooks as $name => $callback) {
                    $callbacks_found++;
                    if ($callbacks_found === 1) { // Only log the first callback
                        if (is_array($callback['function'])) {
                            if (is_object($callback['function'][0])) {
                                //error_log("HAM AJAX: Callback registered: " . get_class($callback['function'][0]) . '->' . $callback['function'][1]);
                            } else {
                                //error_log("HAM AJAX: Callback registered: " . (is_string($callback['function'][0]) ? $callback['function'][0] : 'Unknown') . '::' . $callback['function'][1]);
                            }
                        } else {
                            //error_log("HAM AJAX: Callback registered: " . (is_string($callback['function']) ? $callback['function'] : 'Unknown function'));
                        }
                    }
                }
            }
        } else {
            error_log("HAM AJAX FILTER ERROR: No handler registered for {$post_type}");
        }
        
        // Check if the admin list table class exists
        $class_name = 'HAM_' . ucfirst($post_type) . '_Admin_List_Table';
        //error_log("HAM AJAX: Checking for class: {$class_name}");
        
        if (class_exists($class_name)) {
            //error_log("HAM AJAX: Class {$class_name} exists");
            
            // Check if render_filtered_table method exists
            if (method_exists($class_name, 'render_filtered_table')) {
                //error_log("HAM AJAX: render_filtered_table method exists in {$class_name}");
            } else {
                error_log("HAM AJAX FILTER ERROR: render_filtered_table method MISSING in {$class_name}");
            }
        } else {
            error_log("HAM AJAX FILTER ERROR: Class {$class_name} does NOT exist");
        }
    }
    
    /**
     * Executes both filter and action handlers for the given post type.
     * Ensures compatibility with both modern and legacy rendering patterns.
     *
     * @param string $post_type The post type to render filtered table for.
     * @return string The rendered HTML table content.
     */
    private static function execute_filter_handlers($post_type) {
        // Extract filter parameters properly from $_POST
        $filters = array();
        foreach ($_POST as $key => $value) {
            // Keep only the filter parameters and other required fields
            if (strpos($key, 'ham_filter_') === 0 || in_array($key, array('m', 'paged'))) {
                $filters[$key] = sanitize_text_field($value);
            }
        }
        
        // Only log errors when hook is missing
        if (defined('WP_DEBUG') && WP_DEBUG) {
            global $wp_filter;
            $hook_name = "ham_render_filtered_{$post_type}_table";
            if (!isset($wp_filter[$hook_name])) {
                error_log("HAM AJAX ERROR: Filter hook {$hook_name} does not exist!");
            }
        }
        
        // CRITICAL FIX: The issue is in how apply_filters passes parameters
        // WordPress apply_filters passes parameters individually, not as an array
        
        // Only validate critical aspects of the filters parameter
        if (!is_array($filters)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("HAM AJAX ERROR: Filters is not an array: " . gettype($filters));
            }
            $filters = []; // Convert to empty array to avoid PHP errors
        }
        
        // CRITICAL FIX: We need to pass the filters to the rendering function directly
        // WordPress hooks can have issues with array parameters
        // Let's try to get a class instance and call it directly first
        
        // Fix the class name construction to handle post types that already start with 'ham_'
        $base_post_type = str_replace('ham_', '', $post_type);
        $class_name = 'HAM_' . ucfirst($base_post_type) . '_Admin_List_Table';
        $table_html = '';
        
        // Try direct method invocation first, which will reliably pass filters
        if (class_exists($class_name)) {
            $instance = new $class_name();
            if (method_exists($instance, 'render_filtered_table')) {
                $table_html = $instance->render_filtered_table($filters);
                return $table_html;
            }
        }
        
        // Fallback to using apply_filters if direct method fails
        if (empty($table_html)) {
            $returned_table_html = apply_filters("ham_render_filtered_{$post_type}_table", '', $filters);
            
            // Check if we have a returned value from the filter
            if (!empty($returned_table_html)) {
                return $returned_table_html;
            }
        } 
        
        // Fallback to legacy output buffering approach
        ob_start();
        do_action("ham_render_filtered_{$post_type}_table", $filters);
        $table_html = ob_get_clean();
        
        // Log only critical errors like empty output
        if (defined('WP_DEBUG') && WP_DEBUG && empty($table_html)) {
            error_log("HAM AJAX FILTER ERROR: Empty output for {$post_type} after triggering action");
        }
        
        return $table_html;
    }
}

// Initialize
HAM_Ajax_Handlers::init();
<?php
/**
 * File: inc/api/class-ham-student-controller.php
 *
 * Handles student-related API endpoints.
 *
 * @package HeadlessAccessManager
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

// Include the teacher controller for get_teacher_by_user_id method
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'api/class-ham-teacher-controller.php';

/**
 * Class HAM_Student_Controller
 *
 * Manages student data API endpoints.
 */
class HAM_Student_Controller extends HAM_Base_Controller
{
    /**
     * Register routes for this controller.
     */
    public static function register_routes()
    {
        $controller = new self();
        $rest_base = 'students';
        
        // Route to get students assigned to a teacher
        register_rest_route(HAM_API_NAMESPACE, '/teachers/(?P<teacher_id>\d+)/students', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$controller, 'get_assigned_students'],
                'permission_callback' => [$controller, 'validate_jwt'],
                'args'                => [
                    'teacher_id' => [
                        'required'          => true,
                        'validate_callback' => function ($param) {
                            return is_numeric($param) && intval($param) > 0;
                        },
                        'sanitize_callback' => 'absint',
                        'description'       => __('Teacher ID to fetch assigned students for.', 'headless-access-manager'),
                    ],
                ],
            ]
        ]);

        register_rest_route(HAM_API_NAMESPACE, '/' . $rest_base . '/search/(?P<search_term>[a-zA-Z0-9%\\s.-]+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$controller, 'search_students'],
                'permission_callback' => [$controller, 'validate_jwt'],
                'args'                => [
                    'search_term' => [
                        'required'          => true,
                        'validate_callback' => function ($param) {
                            return is_string($param) && !empty(trim($param));
                        },
                        'sanitize_callback' => function ($param) {
                            return sanitize_text_field(urldecode($param));
                        },
                        'description'       => __('Search term for students.', 'headless-access-manager'),
                    ],
                ],
            ]
        ]);
    }


    /**
     * Handle student search requests.
     *
     * @param WP_REST_Request $request The request object.
     *
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error on failure.
     */
    public function search_students($request) {
        // Get the search term from URL path parameter
        $search_term = sanitize_text_field($request->get_param('search_term'));
        $class_id = $request->get_param('class_id') ? absint($request->get_param('class_id')) : null;
        
        //error_log("HAM Student Search - Request: search term = '{$search_term}', class filter = " . ($class_id ? $class_id : 'none'));
        
        $current_user_id = get_current_user_id();
        if (!$current_user_id) {
            // Only log actual security issues
            error_log("HAM Student Search - Error: User not logged in");
            return new WP_Error('ham_not_logged_in', __('You must be logged in to search students.', 'headless-access-manager'), ['status' => 401]);
        }
        
        // Look up teacher CPT associated with this WordPress user
        $teacher_cpt_id = HAM_Teacher_Controller::get_teacher_by_user_id($current_user_id);
        
        // Check if user is teacher or admin
        $is_admin = current_user_can('administrator');
        if (!$teacher_cpt_id && !$is_admin) {
            // Only log actual security issues
            error_log("HAM Student Search - Error: User is not a teacher or admin");
            return new WP_Error('ham_not_teacher', __('You must be a teacher or administrator to search students.', 'headless-access-manager'), ['status' => 403]);
        }
        
        // Initialize accessible student IDs
        $accessible_student_ids = [];
        $assigned_class_ids = [];
        $assigned_school_id = null;
        
        // Get teacher's assigned classes and school (if not admin)
        if ($teacher_cpt_id) {
            $assigned_class_ids = get_post_meta($teacher_cpt_id, '_ham_class_ids', true);
            $assigned_class_ids = is_array($assigned_class_ids) ? array_filter(array_map('intval', $assigned_class_ids)) : [];
            //error_log("HAM Student Search - Teacher assigned class IDs: " . json_encode($assigned_class_ids));
            
            $assigned_school_id = get_post_meta($teacher_cpt_id, '_ham_school_id', true);
            $assigned_school_id = !empty($assigned_school_id) ? intval($assigned_school_id) : null;
            //error_log("HAM Student Search - Teacher assigned school ID: " . ($assigned_school_id ? $assigned_school_id : 'none'));
            
            // If specific class filter is provided, check if teacher has access to it
            if ($class_id && !in_array($class_id, $assigned_class_ids) && !$is_admin) {
                // Only log actual security issues
                error_log("HAM Student Search - Error: Teacher does not have access to specified class ID: {$class_id}");
                return new WP_Error('ham_unauthorized_class', __('You do not have permission to access this class.', 'headless-access-manager'), ['status' => 403]);
            }
        } else {
            // Admin can see all students, no filter needed
            //error_log("HAM Student Search - Admin user, no class filters needed");
        }
        
        // Get student IDs based on class filter or teacher assignments
        if ($class_id) {
            // If specific class filter is used
            //error_log("HAM Student Search - Using specific class filter: {$class_id}");
            $students_in_class = get_post_meta($class_id, '_ham_student_ids', true);
            if (is_array($students_in_class) && !empty($students_in_class)) {
                $accessible_student_ids = $students_in_class;
                //error_log("HAM Student Search - Students in filtered class: " . json_encode($accessible_student_ids));
            } else {
                //error_log("HAM Student Search - No students found in filtered class or invalid meta");
                return new WP_REST_Response([], 200); // No students in this class
            }
        } else if (!$is_admin && !empty($assigned_class_ids)) {
            // Get all students from teacher's assigned classes
            //error_log("HAM Student Search - Looking for students in all assigned classes");
            foreach ($assigned_class_ids as $class_id) {
                $students_in_class = get_post_meta($class_id, '_ham_student_ids', true);
                //error_log("HAM Student Search - Class ID {$class_id} has students: " . json_encode($students_in_class));
                if (is_array($students_in_class) && !empty($students_in_class)) {
                    $accessible_student_ids = array_merge($accessible_student_ids, $students_in_class);
                }
            }
            //error_log("HAM Student Search - All accessible student IDs before filtering: " . json_encode($accessible_student_ids));
            
            // No students found in assigned classes
            if (empty($accessible_student_ids) && $assigned_school_id) {
                // Try to find students by school
                $school_students_query = new WP_Query([
                    'post_type' => HAM_CPT_STUDENT,
                    'posts_per_page' => -1,
                    'fields' => 'ids',
                    'meta_query' => [
                        [
                            'key' => '_ham_school_id',
                            'value' => $assigned_school_id,
                            'compare' => '=',
                        ],
                    ],
                ]);
                
                if ($school_students_query->have_posts()) {
                    $accessible_student_ids = $school_students_query->posts;
                    //error_log("HAM Student Search - Found students by school: " . json_encode($accessible_student_ids));
                }
                wp_reset_postdata();
            }
            
            // Still no students found
            if (empty($accessible_student_ids) && !$is_admin) {
                //error_log("HAM Student Search - No accessible students found for teacher");
                return new WP_REST_Response([], 200); // No accessible students
            }
        }
        
        // Clean up student IDs
        if (!empty($accessible_student_ids)) {
            $accessible_student_ids = array_unique(array_filter(array_map('intval', $accessible_student_ids)));
            //error_log("HAM Student Search - Filtered student IDs: " . json_encode($accessible_student_ids));
        }
        
        // Now perform the search
        $results = [];
        
        // Hard-code the student post type for absolute certainty
        $student_post_type = 'ham_student';
        //error_log("HAM Student Search - Hard-coded post type: {$student_post_type}");
        
        // Build the WP_Query with explicit post type
        $query_args = [
            'post_type' => $student_post_type,
            'post_status' => 'publish',
            'posts_per_page' => 20,
        ];
        
        // Force post type query modification to be absolutely certain
        add_filter('posts_where', function($where, $wp_query) use ($student_post_type) {
            global $wpdb;
            // Add an additional explicit check for post_type
            $where .= $wpdb->prepare(" AND {$wpdb->posts}.post_type = %s", $student_post_type);
            return $where;
        }, 5, 2);
        
        // Add student ID restriction if needed
        if (!empty($accessible_student_ids) && !$is_admin) {
            $query_args['post__in'] = $accessible_student_ids;
        }
        
        // Add title search if we have a search term
        if (!empty($search_term)) {
            // We'll use a custom filter to search only in titles
            add_filter('posts_where', function($where, $wp_query) use ($search_term) {
                global $wpdb;
                $search_like = '%' . $wpdb->esc_like($search_term) . '%';
                $where .= $wpdb->prepare(" AND {$wpdb->posts}.post_title LIKE %s", $search_like);
                return $where;
            }, 10, 2);
        }
        
        //error_log("HAM Student Search - Final query args: " . json_encode($query_args));
        $student_query = new WP_Query($query_args);
        
        // Remove the filter after the query
        if (!empty($search_term)) {
            remove_all_filters('posts_where');
        }
        
        //error_log("HAM Student Search - Found {$student_query->post_count} results");
        
        if ($student_query->have_posts()) {
            while ($student_query->have_posts()) {
                $student_query->the_post();
                $student_id = get_the_ID();
                $post_type = get_post_type($student_id);
                
                // Store the original student title BEFORE class info lookup
                $student_title = get_the_title();
                
                // Skip non-student posts (safety check)
                if ($post_type !== HAM_CPT_STUDENT) {
                    //error_log("HAM Student Search - WARNING: Skipping non-student post: ID {$student_id}, post_type {$post_type}");
                    continue;
                }
                
                // Get class information for this student
                $class_info = self::get_student_class_info($student_id);
                
                //error_log("HAM Student Search - Adding student: ID {$student_id}, name '{$student_title}', class name '" . ($class_info ? $class_info['name'] : 'Unknown') . "'");
                
                $results[] = [
                    'id'   => $student_id,
                    'name' => $student_title, // Always use the original student title
                    'className' => $class_info ? $class_info['name'] : 'Klass information saknas',
                    'classId' => $class_info ? $class_info['id'] : null,
                    'post_type' => $post_type,
                ];
            }
        }
        wp_reset_postdata();

        return new WP_REST_Response($results, 200);
    }
    
    /**
     * Get students assigned to a specific teacher.
     *
     * @param WP_REST_Request $request The request object.
     * 
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error on failure.
     */
    public function get_assigned_students(WP_REST_Request $request)
    {
        $teacher_id = $request->get_param('teacher_id');
        $current_user_id = get_current_user_id();

        if (!$current_user_id) {
            return new WP_Error('rest_not_logged_in', __('You are not currently logged in.', 'headless-access-manager'), ['status' => 401]);
        }

        // Verify permission - Admin can view any teacher's students, while teachers can only view their own
        if (!current_user_can('administrator')) {
            // Get the WP user ID associated with the requested teacher CPT
            $teacher_user_id = get_post_meta($teacher_id, '_ham_user_id', true);
            
            // If the requested teacher ID doesn't match the current user, deny access
            if (empty($teacher_user_id) || intval($teacher_user_id) !== $current_user_id) {
                return new WP_Error('ham_unauthorized', __('You are not authorized to access these students.', 'headless-access-manager'), ['status' => 403]);
            }
        }
        
        // Get the teacher's assigned classes
        $assigned_class_ids = get_post_meta($teacher_id, '_ham_class_ids', true);
        $assigned_class_ids = is_array($assigned_class_ids) ? array_filter(array_map('intval', $assigned_class_ids)) : [];

        // Get the teacher's assigned school
        $assigned_school_id = get_post_meta($teacher_id, '_ham_school_id', true);
        $assigned_school_id = !empty($assigned_school_id) ? intval($assigned_school_id) : null;
        
        $accessible_student_ids = [];
        
        // Priority 1: Get students from assigned classes
        if (!empty($assigned_class_ids)) {
            foreach ($assigned_class_ids as $class_id) {
                $students_in_class = get_post_meta($class_id, '_ham_student_ids', true);
                if (is_array($students_in_class)) {
                    $accessible_student_ids = array_merge($accessible_student_ids, $students_in_class);
                }
            }
            $accessible_student_ids = array_unique(array_filter(array_map('intval', $accessible_student_ids)));
        }
        
        $results = [];
        
        if (!empty($accessible_student_ids)) {
            // Query students by IDs from classes
            $student_query = new WP_Query([
                'post_type'      => HAM_CPT_STUDENT,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'post__in'       => $accessible_student_ids,
                'orderby'        => 'title',
                'order'          => 'ASC',
            ]);
        } elseif ($assigned_school_id) {
            // Priority 2: Get students from assigned school if no classes
            $student_query = new WP_Query([
                'post_type'      => HAM_CPT_STUDENT,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'meta_query'     => [
                    [
                        'key'     => '_ham_school_id',
                        'value'   => $assigned_school_id,
                        'compare' => '=',
                    ],
                ],
                'orderby'        => 'title',
                'order'          => 'ASC',
            ]);
        } else {
            // No assigned classes or school
            return new WP_REST_Response([], 200);
        }
        
        // Build the results array
        if ($student_query->have_posts()) {
            while ($student_query->have_posts()) {
                $student_query->the_post();
                $student_id = get_the_ID();
                
                // Get the WP User ID connected to this student CPT
                $user_id = get_post_meta($student_id, '_ham_user_id', true);
                $user_info = get_userdata($user_id);
                
                $results[] = [
                    'id'           => $student_id,
                    'name'         => get_the_title(),
                    'display_name' => $user_info ? $user_info->display_name : null,
                ];
            }
        }
        wp_reset_postdata();
        
        return new WP_REST_Response($results, 200);
    }
    
    /**
     * Get the class information for a specific student.
     * 
     * @param int $student_id The student ID to get class info for.
     * 
     * @return array|false Class information array with id and name, or false if not found.
     */
    public static function get_student_class_info($student_id) {
        // Debug log for troubleshooting
        //error_log("HAM Student Search - Getting class info for student ID: {$student_id}");
        
        // First try with _ham_class_ids meta (direct assignment)
        $assigned_class_ids = get_post_meta($student_id, '_ham_class_ids', true);
        
        // If no direct assignment, try to find if the student is in any class's _ham_student_ids meta
        if (empty($assigned_class_ids) || !is_array($assigned_class_ids) || empty(array_filter($assigned_class_ids))) {
            //error_log("HAM Student Search - No direct class assignments found for student ID: {$student_id}, trying reverse lookup");
            
            // Query classes that have this student in their _ham_student_ids meta
            $args = [
                'post_type' => HAM_CPT_CLASS,
                'post_status' => 'publish',
                'posts_per_page' => 1, // Just get the first one for now
                'meta_query' => [
                    [
                        'key' => '_ham_student_ids',
                        'value' => $student_id,
                        'compare' => 'LIKE'
                    ]
                ]
            ];
            
            $class_query = new WP_Query($args);
            
            if ($class_query->have_posts()) {
                $class_query->the_post();
                $class_id = get_the_ID();
                $class_title = get_the_title();
                
                //error_log("HAM Student Search - Found class via reverse lookup: {$class_id}, {$class_title}");
                
                wp_reset_postdata();
                
                return [
                    'id' => $class_id,
                    'name' => $class_title
                ];
            }
            
            wp_reset_postdata();
            //error_log("HAM Student Search - No classes found for student ID: {$student_id} via either method");
            return false;
        }
        
        // We have directly assigned classes, get the first one
        $class_id = reset($assigned_class_ids);
        
        if (!$class_id) {
            //error_log("HAM Student Search - Invalid class ID in _ham_class_ids for student ID: {$student_id}");
            return false;
        }
        
        $class_title = get_the_title($class_id);
        
        if (!$class_title) {
            //error_log("HAM Student Search - Could not get title for class ID: {$class_id}");
            return false;
        }
        
        //error_log("HAM Student Search - Found class directly: {$class_id}, {$class_title}");
        
        return [
            'id' => $class_id,
            'name' => $class_title
        ];
    }
}

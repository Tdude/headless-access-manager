<?php
/**
 * File: inc/api/class-ham-teacher-controller.php
 *
 * Handles teacher-related API endpoints.
 *
 * @package HeadlessAccessManager
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class HAM_Teacher_Controller
 *
 * Manages teacher data API endpoints.
 */
class HAM_Teacher_Controller extends HAM_Base_Controller
{
    /**
     * Get teacher CPT ID associated with a WordPress user ID.
     *
     * @param int $user_id WordPress user ID.
     * @return int|null Teacher CPT ID or null if not found.
     */
    public static function get_teacher_by_user_id($user_id)
    {
        if (!$user_id) {
            return null;
        }
        
        // Log the lookup attempt
        error_log("HAM Teacher Controller - Looking up teacher CPT for user ID: {$user_id}");
        
        // Query for teachers that have this user ID as meta
        $teacher_query = new WP_Query([
            'post_type' => HAM_CPT_TEACHER,
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => '_ham_user_id',
                    'value' => $user_id,
                    'compare' => '=',
                ],
            ],
        ]);
        
        if ($teacher_query->have_posts()) {
            $teacher_id = $teacher_query->posts[0];
            error_log("HAM Teacher Controller - Found teacher CPT ID: {$teacher_id} for user ID: {$user_id}");
            return $teacher_id;
        }
        
        error_log("HAM Teacher Controller - No teacher CPT found for user ID: {$user_id}");
        return null;
    }
    
    /**
     * Register routes for this controller.
     */
    public static function register_routes()
    {
        // This will be implemented as needed
        // Currently, we only need the get_teacher_by_user_id method
    }
}

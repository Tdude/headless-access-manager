<?php
/**
 * AJAX handlers for the plugin.
 */

if (!defined('ABSPATH')) {
    exit;
}

class HAM_Ajax_Handlers {
    public static function init() {
        add_action('wp_ajax_ham_search_students', [__CLASS__, 'search_students']);
    }

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
}

// Initialize
HAM_Ajax_Handlers::init();
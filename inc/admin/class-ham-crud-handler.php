<?php
/**
 * Generic AJAX CRUD handler for CPTs
 */
class HAM_CRUD_Handler {
    public static function register() {
        add_action('wp_ajax_ham_crud_post', [__CLASS__, 'handle_ajax']);
    }

    public static function handle_ajax() {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Insufficient permissions.']);
        }
        $post_type = sanitize_text_field($_POST['post_type'] ?? '');
        $action_type = sanitize_text_field($_POST['action_type'] ?? '');
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $data = $_POST['data'] ?? [];
        if (!post_type_exists($post_type)) {
            wp_send_json_error(['message' => 'Invalid post type.']);
        }
        $result = false;
        if ($action_type === 'create') {
            $data['post_type'] = $post_type;
            $result = wp_insert_post($data, true);
        } elseif ($action_type === 'update') {
            $data['ID'] = $post_id;
            $data['post_type'] = $post_type;
            $result = wp_update_post($data, true);
        } elseif ($action_type === 'delete') {
            $result = wp_delete_post($post_id, true);
        }
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        } else {
            wp_send_json_success(['result' => $result]);
        }
    }
}

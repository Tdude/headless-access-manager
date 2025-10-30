<?php
/**
 * Handles meta boxes for the Teacher CPT.
 *
 * @package HeadlessAccessManager
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class HAM_Teacher_Meta_Boxes
 */
class HAM_Teacher_Meta_Boxes {
    /**
     * Initialize hooks for AJAX, saving, and meta box registration.
     */
    public static function init_hooks() {
        // Hook for saving the meta boxes for the Teacher CPT
        add_action('save_post_' . HAM_CPT_TEACHER, [__CLASS__, 'save_meta_boxes']);

        // AJAX hook for dynamic class loading
        add_action('wp_ajax_get_classes_for_school', [__CLASS__, 'ajax_get_classes_for_school']);

        // Meta box registration hook
        // Note: 'add_meta_boxes_ham_teacher' is more specific if HAM_CPT_TEACHER is 'ham_teacher'
        // Using the general 'add_meta_boxes' and checking post type inside is also common.
        add_action('add_meta_boxes_' . HAM_CPT_TEACHER, [__CLASS__, 'register_meta_boxes']);
    }

    /**
     * Register meta boxes for the Teacher CPT.
     * This method is now primarily responsible for calling add_meta_box.
     * It's called by the hook set in init_hooks.
     */
    public static function register_meta_boxes() {
        // User capability check - e.g., only School Heads or Admins can assign classes
        $user = wp_get_current_user();
        // Allow admins and school heads to manage these links
        if (in_array(HAM_ROLE_SCHOOL_HEAD, $user->roles) || in_array('administrator', $user->roles)) {
            add_meta_box(
                'ham_teacher_classes_assignment',
                __('Assign Classes', 'headless-access-manager'),
                [__CLASS__, 'render_class_assignment_meta_box'],
                HAM_CPT_TEACHER,
                'advanced',
                'high'
            );

            add_meta_box(
                'ham_teacher_school_assignment',
                __('Assign School', 'headless-access-manager'),
                [__CLASS__, 'render_school_assignment_meta_box'],
                HAM_CPT_TEACHER,
                'side',
                'default'
            );

            add_meta_box(
                'ham_teacher_user_link',
                __('Link to WordPress User', 'headless-access-manager'),
                [__CLASS__, 'render_user_link_meta_box'],
                HAM_CPT_TEACHER,
                'side',
                'default'
            );

            add_meta_box(
                'ham_teacher_evaluation_stats',
                __('Evaluation Statistics', 'headless-access-manager'),
                [__CLASS__, 'render_evaluation_statistics_meta_box'],
                HAM_CPT_TEACHER,
                'normal',
                'low'
            );
        }
    }

    /**
     * Render the classes assignment meta box for teachers.
     *
     * @param WP_Post $post Current post object (teacher post).
     */
    public static function render_class_assignment_meta_box($post) {
        wp_nonce_field('ham_teacher_classes_meta_box_nonce', 'ham_teacher_classes_meta_box_nonce');

        // Get classes assigned to this teacher post
        $assigned_class_ids = get_post_meta($post->ID, '_ham_class_ids', true);
        $assigned_class_ids = is_array($assigned_class_ids) ? $assigned_class_ids : [];

        // Get the teacher's school ID
        $teacher_school_id = get_post_meta($post->ID, '_ham_school_id', true);

        $args = [
            'post_type' => HAM_CPT_CLASS,
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        ];

        if (!empty($teacher_school_id)) {
            $args['meta_query'] = [
                [
                    'key' => '_ham_school_id',
                    'value' => $teacher_school_id,
                    'compare' => '=',
                ],
            ];
        } else {
            // Optional: Add a message if teacher has no school, or handle as desired
            // For now, it will list all classes if no school is assigned to the teacher.
            echo '<p style="color: red;">' . esc_html__('This teacher is not assigned to any school. All classes are listed. Please assign a school to this teacher to see school-specific classes.', 'headless-access-manager') . '</p>';
        }

        // Get available classes to select from based on args
        $all_classes = get_posts($args);

        if (empty($all_classes) && !empty($teacher_school_id)) {
            echo '<p>' . esc_html__('No classes found for the assigned school.', 'headless-access-manager') . '</p>';
            // Optionally, hide the select dropdown or provide other feedback
        } elseif (empty($all_classes) && empty($teacher_school_id)) {
             // This case is partly handled by the message above, but if $args still returns no classes at all.
            echo '<p>' . esc_html__('No classes found in the system.', 'headless-access-manager') . '</p>';
        }

        ?>
        <p>
            <label for="ham_teacher_class_ids"><?php esc_html_e('Assign Classes to this Teacher:', 'headless-access-manager'); ?></label>
            <select name="ham_teacher_class_ids[]" id="ham_teacher_class_ids" class="widefat" multiple="multiple" style="min-height: 120px;">
                <?php foreach ($all_classes as $class_obj) : 
                    // Get the school name for this class
                    $class_school_id = get_post_meta($class_obj->ID, '_ham_school_id', true);
                    $school_name = '';
                    if ($class_school_id) {
                        $school_post = get_post($class_school_id);
                        if ($school_post) {
                            $school_name = ' (' . $school_post->post_title . ')';
                        }
                    }
                ?>
                    <option value="<?php echo esc_attr($class_obj->ID); ?>" <?php selected(in_array($class_obj->ID, $assigned_class_ids)); ?>>
                        <?php echo esc_html($class_obj->post_title . $school_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p class="description"><?php esc_html_e('Hold Ctrl (Windows) or Cmd (Mac) to select multiple Classes.', 'headless-access-manager'); ?></p>
        <?php
    }

    /**
     * Render the school assignment meta box for teachers.
     *
     * @param WP_Post $post Current post object (teacher post).
     */
    public static function render_school_assignment_meta_box($post) {
        wp_nonce_field('ham_teacher_school_meta_box_nonce', 'ham_teacher_school_meta_box_nonce');

        $assigned_school_id = get_post_meta($post->ID, '_ham_school_id', true);

        $all_schools = get_posts([
            'post_type' => HAM_CPT_SCHOOL,
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        ?>
        <p>
            <label for="ham_teacher_school_id"><?php esc_html_e('Assign School to this Teacher:', 'headless-access-manager'); ?></label>
            <select name="ham_teacher_school_id" id="ham_teacher_school_id" class="widefat">
                <option value=""><?php esc_html_e('-- Select a School --', 'headless-access-manager'); ?></option>
                <?php foreach ($all_schools as $school_obj) : ?>
                    <option value="<?php echo esc_attr($school_obj->ID); ?>" <?php selected($assigned_school_id, $school_obj->ID); ?>>
                        <?php echo esc_html($school_obj->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <?php
    }

    /**
     * Render the meta box for linking a Teacher CPT to a user.
     *
     * @param WP_Post $post The current post object.
     */
    public static function render_user_link_meta_box(WP_Post $post) {
        wp_nonce_field('ham_teacher_user_link_save', 'ham_teacher_user_link_nonce');
        $linked_user_id = get_post_meta($post->ID, '_ham_user_id', true);

        $users_query = new WP_User_Query([
            'role' => HAM_ROLE_TEACHER,
            'orderby' => 'display_name',
            'order' => 'ASC',
            'fields' => ['ID', 'display_name'],
        ]);
        $teachers = $users_query->get_results();

        echo '<label for="ham_teacher_user_id">' . esc_html__('Select User:', 'headless-access-manager') . '</label>';
        echo '<select name="ham_teacher_user_id" id="ham_teacher_user_id" class="widefat">';
        echo '<option value="">' . esc_html__('-- Select a User --', 'headless-access-manager') . '</option>';

        if (!empty($teachers)) {
            foreach ($teachers as $user) {
                printf(
                    '<option value="%d" %s>%s</option>',
                    esc_attr($user->ID),
                    selected($linked_user_id, $user->ID, false),
                    esc_html($user->display_name)
                );
            }
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('Select the WordPress user account for this teacher.', 'headless-access-manager') . '</p>';
    }

    /**
     * Render the evaluation statistics meta box for teachers.
     *
     * @param WP_Post $post Current post object (teacher post).
     */
    public static function render_evaluation_statistics_meta_box($post) {
        $linked_user_id = get_post_meta($post->ID, '_ham_user_id', true);

        if (empty($linked_user_id)) {
            echo '<p>' . esc_html__('This teacher is not linked to a WordPress user. No statistics can be shown.', 'headless-access-manager') . '</p>';
            return;
        }

        $stats_manager = new HAM_Statistics_Manager();
        $evaluations = $stats_manager->get_teacher_evaluations($linked_user_id);

        if (empty($evaluations)) {
            echo '<p>' . esc_html__('No evaluations found for this teacher.', 'headless-access-manager') . '</p>';
            return;
        }
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Date', 'headless-access-manager'); ?></th>
                    <th><?php esc_html_e('Student', 'headless-access-manager'); ?></th>
                    <th><?php esc_html_e('Grade', 'headless-access-manager'); ?></th>
                    <th><?php esc_html_e('Comments', 'headless-access-manager'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($evaluations as $eval) : ?>
                    <tr>
                        <td><?php echo esc_html($eval['date']); ?></td>
                        <td><?php echo esc_html($eval['student_name']); ?></td>
                        <td><?php echo esc_html($eval['grade']); ?></td>
                        <td><?php echo wp_kses_post($eval['comments']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Save meta box data for Teacher CPT.
     *
     * @param int $post_id The ID of the post being saved (teacher post).
     */
    public static function save_meta_boxes($post_id) {
        // Common checks: Autosave, Permission, Post Type
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        if (HAM_CPT_TEACHER !== get_post_type($post_id)) {
            return;
        }

        // Save assigned class IDs to teacher post meta
        if (isset($_POST['ham_teacher_classes_meta_box_nonce']) && wp_verify_nonce($_POST['ham_teacher_classes_meta_box_nonce'], 'ham_teacher_classes_meta_box_nonce')) {
            if (isset($_POST['ham_teacher_class_ids'])) {
                $class_ids = array_map('absint', (array)$_POST['ham_teacher_class_ids']);
                update_post_meta($post_id, '_ham_class_ids', $class_ids);
            } else {
                delete_post_meta($post_id, '_ham_class_ids');
            }
        }

        // Save assigned school ID
        if (isset($_POST['ham_teacher_school_meta_box_nonce']) && wp_verify_nonce($_POST['ham_teacher_school_meta_box_nonce'], 'ham_teacher_school_meta_box_nonce')) {
            if (isset($_POST['ham_teacher_school_id'])) {
                $school_id = sanitize_text_field($_POST['ham_teacher_school_id']);
                if (!empty($school_id)) {
                    update_post_meta($post_id, '_ham_school_id', absint($school_id));
                } else {
                    delete_post_meta($post_id, '_ham_school_id');
                }
            }
        }

        // Save linked user ID
        if (isset($_POST['ham_teacher_user_link_nonce']) && wp_verify_nonce($_POST['ham_teacher_user_link_nonce'], 'ham_teacher_user_link_save')) {
            if (isset($_POST['ham_teacher_user_id'])) {
                $user_id = sanitize_text_field($_POST['ham_teacher_user_id']);
                if (!empty($user_id)) {
                    update_post_meta($post_id, '_ham_user_id', absint($user_id));
                } else {
                    delete_post_meta($post_id, '_ham_user_id');
                }
            }
        }
    }

    /**
     * AJAX handler to get classes for a given school ID.
     */
    public static function ajax_get_classes_for_school() {
        check_ajax_referer('ham_get_classes_for_school_nonce', 'nonce');

        $school_id = isset($_POST['school_id']) ? intval($_POST['school_id']) : 0;
        $classes_data = [];

        if ($school_id > 0) {
            $args = [
                'post_type' => HAM_CPT_CLASS,
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'orderby' => 'title',
                'order' => 'ASC',
                'meta_query' => [
                    [
                        'key' => '_ham_school_id',
                        'value' => $school_id,
                        'compare' => '=',
                    ],
                ],
            ];
            $classes = get_posts($args);

            if (!empty($classes)) {
                foreach ($classes as $class_obj) {
                    $classes_data[] = [
                        'id' => $class_obj->ID,
                        'title' => esc_html($class_obj->post_title),
                    ];
                }
            }
        } // If school_id is 0 or not provided, $classes_data remains empty, client-side can handle this.

        wp_send_json_success($classes_data);
    }
}

// Ensure hooks are initialized. This should ideally be called from a central plugin loader.
// For now, adding it here ensures it runs if the file is included.
// If you have a main class like HAM_Plugin or similar with an init method, call HAM_Teacher_Meta_Boxes::init_hooks() from there.
add_action('init', ['HAM_Teacher_Meta_Boxes', 'init_hooks']);

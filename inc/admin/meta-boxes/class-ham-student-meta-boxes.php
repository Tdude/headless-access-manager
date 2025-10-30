<?php
/**
 * Handles meta boxes for the Student CPT.
 *
 * @package HeadlessAccessManager
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class HAM_Student_Meta_Boxes
 */
class HAM_Student_Meta_Boxes {

    /**
     * Initialize hooks for meta box registration and saving.
     */
    public static function init_hooks() {
        add_action('add_meta_boxes_' . HAM_CPT_STUDENT, [__CLASS__, 'register_meta_boxes']);
        add_action('save_post_' . HAM_CPT_STUDENT, [__CLASS__, 'save_meta_boxes']);
    }

    /**
     * Register meta boxes for the Student CPT.
     */
    public static function register_meta_boxes() {
        add_meta_box(
            'ham_student_evaluation_stats',
            __('Evaluation Statistics', 'headless-access-manager'),
            [__CLASS__, 'render_evaluation_statistics_meta_box'],
            HAM_CPT_STUDENT,
            'normal',
            'default'
        );

        // User capability check - e.g., only Admins or relevant roles can assign school
        // For now, let's assume admin or school head can do this.
        $user = wp_get_current_user();
        $can_edit_student_relations = false;
        if (current_user_can('manage_options') || 
            in_array(HAM_ROLE_SCHOOL_HEAD, $user->roles) || 
            in_array(HAM_ROLE_PRINCIPAL, $user->roles) || 
            in_array(HAM_ROLE_TEACHER, $user->roles)) {
            // In dev mode with permissive capabilities, these roles might be able to edit.
            // A more granular check like current_user_can('edit_post', $student_id) is in save_meta_boxes.
            // And current_user_can(get_post_type_object(HAM_CPT_STUDENT)->cap->edit_posts) for general list access.
            $can_edit_student_relations = true;
        }

        if (!$can_edit_student_relations) {
            // Fallback for dev mode if map_meta_cap is very open
            if (!current_user_can(get_post_type_object(HAM_CPT_STUDENT)->cap->edit_posts)) {
                 return;
            }
        }

        add_meta_box(
            'ham_student_school_assignment',
            __('Assign School', 'headless-access-manager'),
            [__CLASS__, 'render_school_assignment_meta_box'],
            HAM_CPT_STUDENT,
            'side',
            'default'
        );

        add_meta_box(
            'ham_student_class_assignment',
            __('Assign Class', 'headless-access-manager'),
            [__CLASS__, 'render_class_assignment_meta_box'],
            HAM_CPT_STUDENT,
            'side',
            'low' // Place below school assignment
        );
    }

    /**
     * Render the evaluation statistics meta box for students.
     *
     * @param WP_Post $post Current post object (student post).
     */
    public static function render_evaluation_statistics_meta_box($post) {
        $stats_manager = new HAM_Statistics_Manager();
        $evaluations = $stats_manager->get_student_evaluations($post->ID);

        if (empty($evaluations)) {
            echo '<p>' . esc_html__('No evaluations found for this student.', 'headless-access-manager') . '</p>';
            return;
        }
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Date', 'headless-access-manager'); ?></th>
                    <th><?php esc_html_e('Teacher', 'headless-access-manager'); ?></th>
                    <th><?php esc_html_e('Grade', 'headless-access-manager'); ?></th>
                    <th><?php esc_html_e('Comments', 'headless-access-manager'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($evaluations as $eval) : ?>
                    <tr>
                        <td><?php echo esc_html($eval['date']); ?></td>
                        <td><?php echo esc_html($eval['teacher']); ?></td>
                        <td><?php echo esc_html($eval['grade']); ?></td>
                        <td><?php echo wp_kses_post($eval['comments']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render the school assignment meta box for students.
     *
     * @param WP_Post $post Current post object (student post).
     */
    public static function render_school_assignment_meta_box($post) {
        wp_nonce_field('ham_student_school_assignment_meta_box_nonce', 'ham_student_school_assignment_meta_box_nonce');

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
            <label for="ham_student_school_id"><?php esc_html_e('Assign School to this Student:', 'headless-access-manager'); ?></label>
            <select name="ham_student_school_id" id="ham_student_school_id" class="widefat">
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
     * Render the class assignment meta box for students.
     *
     * @param WP_Post $post Current post object (student post).
     */
    public static function render_class_assignment_meta_box($post) {
        wp_nonce_field('ham_student_class_assignment_meta_box_nonce', 'ham_student_class_assignment_meta_box_nonce');

        $student_id = $post->ID;
        $assigned_class_ids = []; // This will store IDs of classes the student is currently in

        // Get the student's school ID for filtering selectable classes
        $student_school_id = get_post_meta($student_id, '_ham_school_id', true);

        // Get all classes and check if this student is in them (for pre-selecting options)
        $all_classes_query_args = [
            'post_type' => HAM_CPT_CLASS,
            'posts_per_page' => -1,
            // No school filter here, as a student might have been in a class from another school previously
            // Or, if strictness is desired, this could also be filtered by student_school_id
        ];
        $all_classes_for_membership_check = get_posts($all_classes_query_args);

        if (!empty($all_classes_for_membership_check)) {
            foreach ($all_classes_for_membership_check as $class_cpt) {
                $students_in_class = get_post_meta($class_cpt->ID, '_ham_student_ids', true);
                $students_in_class = is_array($students_in_class) ? $students_in_class : [];
                if (in_array($student_id, $students_in_class)) {
                    $assigned_class_ids[] = $class_cpt->ID;
                }
            }
        }

        // Arguments for fetching classes to populate the dropdown
        $selectable_classes_args = [
            'post_type' => HAM_CPT_CLASS,
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        ];

        if (!empty($student_school_id)) {
            $selectable_classes_args['meta_query'] = [
                [
                    'key' => '_ham_school_id',
                    'value' => $student_school_id,
                    'compare' => '=',
                ],
            ];
        } else {
            echo '<p style="color: red;">' . esc_html__('This student is not assigned to any school. All classes are listed. Please assign a school to this student to see school-specific classes.', 'headless-access-manager') . '</p>';
        }

        $selectable_classes = get_posts($selectable_classes_args);

        if (empty($selectable_classes) && !empty($student_school_id)) {
            echo '<p>' . esc_html__('No classes found for the student\'s assigned school.', 'headless-access-manager') . '</p>';
        } elseif (empty($selectable_classes) && empty($student_school_id)) {
            // This case is partly handled by the message above if no school is assigned.
            // If a school IS assigned but still no classes (e.g. school has no classes at all)
            // This message might be redundant if the one above already fired.
        }

        ?>
        <p>
            <label for="ham_student_class_ids"><?php esc_html_e('Assign Class(es) to this Student:', 'headless-access-manager'); ?></label>
            <select name="ham_student_class_ids[]" id="ham_student_class_ids" class="widefat" multiple="multiple" style="min-height: 100px;">
                <?php foreach ($selectable_classes as $class_obj) : 
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
             <p class="description"><?php esc_html_e('Hold Ctrl/Cmd to select multiple classes.', 'headless-access-manager'); ?></p>
        </p>
        <?php
    }

    /**
     * Save meta box data for Student CPT.
     *
     * @param int $post_id The ID of the post being saved (student post).
     */
    public static function save_meta_boxes($post_id) {
        // Check nonce
                if (!isset($_POST['ham_student_school_assignment_meta_box_nonce']) || !wp_verify_nonce($_POST['ham_student_school_assignment_meta_box_nonce'], 'ham_student_school_assignment_meta_box_nonce')) {
            return;
        }
        // Autosave check
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        // Permission check - ensure user can edit this specific student post
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        // Post type check
        if (HAM_CPT_STUDENT !== get_post_type($post_id)) {
            return;
        }

        // Save assigned school ID to student post meta
        if (isset($_POST['ham_student_school_id'])) {
            $school_id = absint($_POST['ham_student_school_id']);
            if ($school_id > 0) {
                update_post_meta($post_id, '_ham_school_id', $school_id);
            } else {
                delete_post_meta($post_id, '_ham_school_id');
            }
        } else {
            // If nothing is selected or field not present, remove the meta key
            delete_post_meta($post_id, '_ham_school_id');
        }

        // Save assigned class IDs by updating Class CPTs
        if (isset($_POST['ham_student_class_assignment_meta_box_nonce']) && wp_verify_nonce($_POST['ham_student_class_assignment_meta_box_nonce'], 'ham_student_class_assignment_meta_box_nonce')) {
            $student_id_to_update = $post_id;
            $newly_selected_class_ids = isset($_POST['ham_student_class_ids']) ? array_map('absint', (array)$_POST['ham_student_class_ids']) : [];

            // Get all class IDs to iterate for changes
            $all_class_cpt_ids_query = new WP_Query([
                'post_type' => HAM_CPT_CLASS,
                'posts_per_page' => -1,
                'fields' => 'ids',
            ]);
            $all_class_cpt_ids = $all_class_cpt_ids_query->posts;

            foreach ($all_class_cpt_ids as $class_cpt_id) {
                $current_students_in_class = get_post_meta($class_cpt_id, '_ham_student_ids', true);
                $current_students_in_class = is_array($current_students_in_class) ? $current_students_in_class : [];
                $student_was_in_class = in_array($student_id_to_update, $current_students_in_class);
                $student_is_now_selected_for_class = in_array($class_cpt_id, $newly_selected_class_ids);

                if ($student_is_now_selected_for_class && !$student_was_in_class) {
                    // Add student to class
                    $current_students_in_class[] = $student_id_to_update;
                    update_post_meta($class_cpt_id, '_ham_student_ids', array_unique($current_students_in_class));
                } elseif (!$student_is_now_selected_for_class && $student_was_in_class) {
                    // Remove student from class
                    $current_students_in_class = array_diff($current_students_in_class, [$student_id_to_update]);
                    update_post_meta($class_cpt_id, '_ham_student_ids', array_unique($current_students_in_class));
                }
            }
        }
    }
}

// Initialize hooks for this meta box class
add_action('init', ['HAM_Student_Meta_Boxes', 'init_hooks']);

<?php
/**
 * Handles meta boxes for the Class CPT.
 *
 * @package HeadlessAccessManager
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class HAM_Class_Meta_Boxes
 */
class HAM_Class_Meta_Boxes
{
    /**
     * Register meta boxes for the Class CPT.
     */
    public static function register_meta_boxes()
    {
        add_meta_box(
            'ham_class_school',
            __('School', 'headless-access-manager'),
            [__CLASS__, 'render_school_meta_box'],
            HAM_CPT_CLASS,
            'side',
            'high'
        );

        if (current_user_can('manage_options')) {
            add_meta_box(
                'ham_class_students',
                __('Assign Students', 'headless-access-manager'),
                [__CLASS__, 'render_students_meta_box'],
                HAM_CPT_CLASS,
                'side'
            );
        }

        // Meta box to display assigned teachers
        add_meta_box(
            'ham_class_assigned_teachers',
            __('Assigned Teachers', 'headless-access-manager'),
            [__CLASS__, 'render_assigned_teachers_meta_box'],
            HAM_CPT_CLASS,
            'normal',
            'default'
        );
    }

    /**
     * Render School meta box.
     */
    public static function render_school_meta_box($post)
    {
        wp_nonce_field('ham_class_school_meta_box', 'ham_class_school_meta_box_nonce');
        $school_id = get_post_meta($post->ID, '_ham_school_id', true);
        $schools = get_posts([
            'post_type' => HAM_CPT_SCHOOL,
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);
        ?>
        <select name="ham_school_id" class="widefat">
            <option value=""><?php esc_html_e('Select School', 'headless-access-manager'); ?></option>
            <?php foreach ($schools as $school) : ?>
                <option value="<?php echo esc_attr($school->ID); ?>" <?php selected($school_id, $school->ID); ?>>
                    <?php echo esc_html($school->post_title); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Render Students meta box with AJAX search.
     */
    public static function render_students_meta_box($post)
    {
        wp_nonce_field('ham_class_students_meta_box', 'ham_class_students_meta_box_nonce');
        $assigned_students = get_post_meta($post->ID, '_ham_student_ids', true) ?: [];
        $assigned_students = is_array($assigned_students) ? $assigned_students : [$assigned_students];
        $student_posts = !empty($assigned_students) ? get_posts([
            'post_type' => HAM_CPT_STUDENT,
            'post__in' => $assigned_students,
            'posts_per_page' => -1
        ]) : [];
        ?>
        <select name="ham_student_ids[]" id="ham_student_ids" class="widefat ham-select2" multiple>
            <?php foreach ($student_posts as $student) : ?>
                <option value="<?php echo esc_attr($student->ID); ?>" selected>
                    <?php echo esc_html($student->post_title); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <script>
            jQuery(document).ready(function($) {
                jQuery('#ham_student_ids').select2({
                    ajax: {
                        url: ajaxurl,
                        dataType: 'json',
                        delay: 250,
                        data: function(params) {
                            return {
                                action: 'ham_search_students',
                                q: params.term,
                                nonce: '<?php echo wp_create_nonce('ham_ajax_nonce'); ?>'
                            };
                        },
                        processResults: function(data) {
                            return { results: data };
                        },
                        cache: true
                    },
                    minimumInputLength: 2,
                    width: '100%',
                    placeholder: '<?php esc_html_e('Search students...', 'headless-access-manager'); ?>'
                });
            });
        </script>
        <?php if (!empty($student_posts)) : ?>
            <div style="margin-top: 15px;">
                <h4><?php esc_html_e('Assigned Students:', 'headless-access-manager'); ?></h4>
                <ul>
                    <?php foreach ($student_posts as $student_post) : ?>
                        <?php
                        $student_id = $student_post->ID;
                        $student_title = get_the_title($student_id);
                        $edit_link = get_edit_post_link($student_id);
                        ?>
                        <li>
                            <?php if ($edit_link) : ?>
                                <a href="<?php echo esc_url($edit_link); ?>"><?php echo esc_html($student_title); ?></a>
                            <?php else : ?>
                                <?php echo esc_html($student_title); ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php else : ?>
            <p style="margin-top: 15px;"><?php esc_html_e('No students are currently assigned to this class.', 'headless-access-manager'); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render Assigned Teachers meta box (display only).
     *
     * @param WP_Post $post The post object.
     */
    public static function render_assigned_teachers_meta_box($post) {
        $class_id = $post->ID;
        $args = [
            'post_type' => HAM_CPT_TEACHER,
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_ham_class_ids',
                    // Search for the class ID as an integer element in the serialized array.
                    // e.g., if class_id is 123, this will look for 'i:123;'.
                    'value' => 'i:' . $class_id . ';',
                    'compare' => 'LIKE'
                ]
            ]
        ];
        $teachers_query = new WP_Query($args);
        $assigned_teachers = [];

        if ($teachers_query->have_posts()) {
            while ($teachers_query->have_posts()) {
                $teachers_query->the_post();
                $teacher_id = get_the_ID();
                $teacher_classes = get_post_meta($teacher_id, '_ham_class_ids', true);
                if (is_array($teacher_classes) && in_array($class_id, $teacher_classes)) {
                    $assigned_teachers[] = esc_html(get_the_title($teacher_id));
                }
            }
            wp_reset_postdata();
        }

        if (!empty($assigned_teachers)) {
            echo '<ul>';
            foreach ($assigned_teachers as $teacher_name) {
                echo '<li>' . $teacher_name . '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>' . esc_html__('No teachers are currently assigned to this class.', 'headless-access-manager') . '</p>';
        }
    }

    /**
     * Save meta box data.
     */
    public static function save_meta_boxes($post_id)
    {
        // Check if this is an autosave. If so, our form has not been submitted, so we don't want to do anything.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check the user's permissions.
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save School: Verify the nonce and save if valid.
        if (isset($_POST['ham_class_school_meta_box_nonce']) &&
            wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ham_class_school_meta_box_nonce'])), 'ham_class_school_meta_box')) {
            
            $school_id = isset($_POST['ham_school_id']) ? (int) $_POST['ham_school_id'] : '';
            update_post_meta($post_id, '_ham_school_id', $school_id);
        }

        // Save Students: Verify the nonce and save/clear if valid.
        // Nonce field name in render_students_meta_box is 'ham_class_students_meta_box_nonce'
        // Nonce action in render_students_meta_box is 'ham_class_students_meta_box'
        if (isset($_POST['ham_class_students_meta_box_nonce']) &&
            wp_verify_nonce($_POST['ham_class_students_meta_box_nonce'], 'ham_class_students_meta_box')) {
            
            error_log('HAM DEBUG: Student save block reached for post ID: ' . $post_id);
            if (isset($_POST['ham_student_ids'])) {
                $student_ids = array_map('intval', (array) $_POST['ham_student_ids']);
                update_post_meta($post_id, '_ham_student_ids', $student_ids);
                error_log('HAM DEBUG: Saved student IDs: ' . print_r($student_ids, true));
            } else {
                update_post_meta($post_id, '_ham_student_ids', []);
                error_log('HAM DEBUG: Cleared student IDs for post ID: ' . $post_id);
            }
        } else {
            if (isset($_POST['ham_class_students_meta_box_nonce'])) {
                error_log('HAM DEBUG: Student save NONCE CHECK FAILED for post ID: ' . $post_id . '. Nonce value: ' . $_POST['ham_class_students_meta_box_nonce']);
            } else {
                error_log('HAM DEBUG: Student save NONCE NOT SET for post ID: ' . $post_id);
            }
        }
    }
}
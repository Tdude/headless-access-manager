<?php
/**
 * File: inc/admin/meta-boxes.php
 *
 * Handles meta boxes for custom post types.
 *
 * @package HeadlessAccessManager
 */

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class HAM_Meta_Boxes
 *
 * Handles meta boxes for custom post types.
 */
class HAM_Meta_Boxes
{
    /**
     * Register assessment meta boxes.
     *
     * @param WP_Post $post Current post object.
     */
    public static function register_assessment_meta_boxes($post) {
        add_meta_box(
            'ham_assessment_student',
            __('Student', 'headless-access-manager'),
            array( __CLASS__, 'render_assessment_student_meta_box' ),
            HAM_CPT_ASSESSMENT,
            'side',
            'high'
        );
        add_meta_box(
            'ham_assessment_data',
            __('Assessment Data', 'headless-access-manager'),
            array( __CLASS__, 'render_assessment_data_meta_box' ),
            HAM_CPT_ASSESSMENT,
            'normal',
            'high'
        );
    }

    /**
     * Render assessment student meta box.
     *
     * @param WP_Post $post Current post object.
     */
    public static function render_assessment_student_meta_box($post)
    {
        // Add nonce for security
        wp_nonce_field('ham_assessment_student_meta_box', 'ham_assessment_student_meta_box_nonce');

        // Get current value
        $student_id = get_post_meta($post->ID, HAM_ASSESSMENT_META_STUDENT_ID, true);

        // Get all students
        $students = get_users(array( 'role' => HAM_ROLE_STUDENT ));

        // Output field
        ?>
<p>
    <label for="ham_student_id"><?php echo esc_html__('Select Student:', 'headless-access-manager'); ?></label>
    <select name="ham_student_id" id="ham_student_id" class="widefat">
        <option value=""><?php echo esc_html__('— Select Student —', 'headless-access-manager'); ?></option>
        <?php foreach ($students as $student) : ?>
        <option value="<?php echo esc_attr($student->ID); ?>" <?php selected($student_id, $student->ID); ?>>
            <?php echo esc_html($student->display_name); ?>
        </option>
        <?php endforeach; ?>
    </select>
</p>
<?php
    }

    /**
     * Render assessment data meta box.
     *
     * @param WP_Post $post Current post object.
     */
    public static function render_assessment_data_meta_box($post)
    {
        // Add nonce for security
        wp_nonce_field('ham_assessment_data_meta_box', 'ham_assessment_data_meta_box_nonce');

        // Get current values
        $assessment_data = get_post_meta($post->ID, HAM_ASSESSMENT_META_DATA, true);
        $assessment_date = get_post_meta($post->ID, HAM_ASSESSMENT_META_DATE, true);

        // Format assessment data for display
        if (is_array($assessment_data)) {
            $assessment_data_json = json_encode($assessment_data, JSON_PRETTY_PRINT);
        } else {
            $assessment_data_json = '';
        }

        // Output fields
        ?>
<p>
    <label for="ham_assessment_date"><?php echo esc_html__('Assessment Date:', 'headless-access-manager'); ?></label>
    <input type="date" name="ham_assessment_date" id="ham_assessment_date" class="widefat"
        value="<?php echo esc_attr($assessment_date ? date('Y-m-d', strtotime($assessment_date)) : ''); ?>">
</p>

<p>
    <label
        for="ham_assessment_data"><?php echo esc_html__('Assessment Data (JSON):', 'headless-access-manager'); ?></label>
    <textarea name="ham_assessment_data" id="ham_assessment_data" class="widefat"
        rows="10"><?php echo esc_textarea($assessment_data_json); ?></textarea>
    <span
        class="description"><?php echo esc_html__('Enter assessment data in JSON format.', 'headless-access-manager'); ?></span>
</p>
<?php
    }

    /**
     * Save meta box data.
     *
     * @param int $post_id Post ID.
     */
    public static function save_meta_boxes($post_id)
    {
        // Check if we're doing an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check the post type
        if (get_post_type($post_id) === HAM_CPT_ASSESSMENT) {
            self::save_assessment_meta_boxes($post_id);
        }
    }

    /**
     * Save assessment meta box data.
     *
     * @param int $post_id Post ID.
     */
    private static function save_assessment_meta_boxes($post_id)
    {
        // Check nonce
        if (!isset($_POST['ham_assessment_meta_box_nonce']) || !wp_verify_nonce($_POST['ham_assessment_meta_box_nonce'], 'ham_assessment_meta_box')) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save student ID
        if (isset($_POST['ham_student_id'])) {
            $student_id = absint($_POST['ham_student_id']);

            if ($student_id > 0) {
                update_post_meta($post_id, HAM_ASSESSMENT_META_STUDENT_ID, $student_id);
            } else {
                delete_post_meta($post_id, HAM_ASSESSMENT_META_STUDENT_ID);
            }
        }

        // Save assessment date
        if (isset($_POST['ham_assessment_date'])) {
            $assessment_date = sanitize_text_field($_POST['ham_assessment_date']);

            if (!empty($assessment_date)) {
                update_post_meta($post_id, HAM_ASSESSMENT_META_DATE, $assessment_date);
            } else {
                delete_post_meta($post_id, HAM_ASSESSMENT_META_DATE);
            }
        }

        // Save assessment data
        if (isset($_POST['ham_assessment_data'])) {
            $assessment_data_json = wp_unslash($_POST['ham_assessment_data']);

            if (!empty($assessment_data_json)) {
                $assessment_data = json_decode($assessment_data_json, true);

                if ($assessment_data !== null) {
                    update_post_meta($post_id, HAM_ASSESSMENT_META_DATA, $assessment_data);
                }
            } else {
                delete_post_meta($post_id, HAM_ASSESSMENT_META_DATA);
            }
        }
    }

    /**
     * Initialize meta boxes for different CPTs.
     */
    public static function init() {
        // Hook for School CPT meta boxes
        add_action('add_meta_boxes_' . HAM_CPT_SCHOOL, ['HAM_School_Meta_Boxes', 'register_meta_boxes']);
        add_action('save_post_' . HAM_CPT_SCHOOL, ['HAM_School_Meta_Boxes', 'save_meta_boxes']);

        // Hook for Class CPT meta boxes
        add_action('add_meta_boxes_' . HAM_CPT_CLASS, ['HAM_Class_Meta_Boxes', 'register_meta_boxes']);
        add_action('save_post_' . HAM_CPT_CLASS, ['HAM_Class_Meta_Boxes', 'save_meta_boxes']);

        // Hook for Teacher CPT meta boxes
        add_action('add_meta_boxes_' . HAM_CPT_TEACHER, ['HAM_Teacher_Meta_Boxes', 'register_meta_boxes']);
        add_action('save_post_' . HAM_CPT_TEACHER, ['HAM_Teacher_Meta_Boxes', 'save_meta_boxes']);

        // Hook for Principal CPT meta boxes
        add_action('add_meta_boxes_' . HAM_CPT_PRINCIPAL, ['HAM_Principal_Meta_Boxes', 'register_meta_boxes']);
        add_action('save_post_' . HAM_CPT_PRINCIPAL, ['HAM_Principal_Meta_Boxes', 'save_meta_boxes']);

        // Hook for Student CPT meta boxes
        add_action('add_meta_boxes_' . HAM_CPT_STUDENT, ['HAM_Student_Meta_Boxes', 'register_meta_boxes']);
        add_action('save_post_' . HAM_CPT_STUDENT, ['HAM_Student_Meta_Boxes', 'save_meta_boxes']);

        // Add other CPT meta box hooks here
    }
}

require_once HAM_PLUGIN_DIR . 'inc/admin/meta-boxes/class-ham-school-meta-boxes.php';
require_once HAM_PLUGIN_DIR . 'inc/admin/meta-boxes/class-ham-class-meta-boxes.php';
require_once HAM_PLUGIN_DIR . 'inc/admin/meta-boxes/class-ham-teacher-meta-boxes.php';
require_once HAM_PLUGIN_DIR . 'inc/admin/meta-boxes/class-ham-principal-meta-boxes.php';
require_once HAM_PLUGIN_DIR . 'inc/admin/meta-boxes/class-ham-student-meta-boxes.php';

HAM_Meta_Boxes::init();

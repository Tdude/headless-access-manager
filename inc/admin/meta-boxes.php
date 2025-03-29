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
     * Register meta boxes.
     */
    public static function register_meta_boxes()
    {
        // Class meta box for school selection
        add_meta_box(
            'ham_class_school',
            __('School', 'headless-access-manager'),
            array( __CLASS__, 'render_class_school_meta_box' ),
            HAM_CPT_CLASS,
            'side',
            'high'
        );

        // Assessment meta box for student selection
        add_meta_box(
            'ham_assessment_student',
            __('Student', 'headless-access-manager'),
            array( __CLASS__, 'render_assessment_student_meta_box' ),
            HAM_CPT_ASSESSMENT,
            'side',
            'high'
        );

        // Assessment meta box for assessment data
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
     * Render class school meta box.
     *
     * @param WP_Post $post Current post object.
     */
    public static function render_class_school_meta_box($post)
    {
        // Add nonce for security
        wp_nonce_field('ham_class_school_meta_box', 'ham_class_school_meta_box_nonce');

        // Get current value
        $school_id = get_post_meta($post->ID, '_ham_school_id', true);

        // Get all schools
        $schools = ham_get_schools();

        // Output field
        ?>
<p>
    <label for="ham_school_id"><?php echo esc_html__('Select School:', 'headless-access-manager'); ?></label>
    <select name="ham_school_id" id="ham_school_id" class="widefat">
        <option value=""><?php echo esc_html__('— Select School —', 'headless-access-manager'); ?></option>
        <?php foreach ($schools as $school) : ?>
        <option value="<?php echo esc_attr($school->ID); ?>" <?php selected($school_id, $school->ID); ?>>
            <?php echo esc_html($school->post_title); ?>
        </option>
        <?php endforeach; ?>
    </select>
</p>
<?php
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
        if (get_post_type($post_id) === HAM_CPT_CLASS) {
            self::save_class_meta_box($post_id);
        } elseif (get_post_type($post_id) === HAM_CPT_ASSESSMENT) {
            self::save_assessment_meta_boxes($post_id);
        }
    }

    /**
     * Save class meta box data.
     *
     * @param int $post_id Post ID.
     */
    private static function save_class_meta_box($post_id)
    {
        // Check nonce
        if (! isset($_POST['ham_class_school_meta_box_nonce']) || ! wp_verify_nonce($_POST['ham_class_school_meta_box_nonce'], 'ham_class_school_meta_box')) {
            return;
        }

        // Check permissions
        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save school ID
        if (isset($_POST['ham_school_id'])) {
            $school_id = absint($_POST['ham_school_id']);

            if ($school_id > 0) {
                update_post_meta($post_id, '_ham_school_id', $school_id);
            } else {
                delete_post_meta($post_id, '_ham_school_id');
            }
        }
    }

    /**
     * Save assessment meta box data.
     *
     * @param int $post_id Post ID.
     */
    private static function save_assessment_meta_boxes($post_id)
    {
        // Check student meta box nonce
        if (isset($_POST['ham_assessment_student_meta_box_nonce']) && wp_verify_nonce($_POST['ham_assessment_student_meta_box_nonce'], 'ham_assessment_student_meta_box')) {
            // Check permissions
            if (current_user_can('edit_post', $post_id)) {
                // Save student ID
                if (isset($_POST['ham_student_id'])) {
                    $student_id = absint($_POST['ham_student_id']);

                    if ($student_id > 0) {
                        update_post_meta($post_id, HAM_ASSESSMENT_META_STUDENT_ID, $student_id);
                    } else {
                        delete_post_meta($post_id, HAM_ASSESSMENT_META_STUDENT_ID);
                    }
                }
            }
        }

        // Check assessment data meta box nonce
        if (isset($_POST['ham_assessment_data_meta_box_nonce']) && wp_verify_nonce($_POST['ham_assessment_data_meta_box_nonce'], 'ham_assessment_data_meta_box')) {
            // Check permissions
            if (current_user_can('edit_post', $post_id)) {
                // Save assessment date
                if (isset($_POST['ham_assessment_date'])) {
                    $assessment_date = sanitize_text_field($_POST['ham_assessment_date']);

                    if (! empty($assessment_date)) {
                        update_post_meta($post_id, HAM_ASSESSMENT_META_DATE, $assessment_date);
                    } else {
                        delete_post_meta($post_id, HAM_ASSESSMENT_META_DATE);
                    }
                }

                // Save assessment data
                if (isset($_POST['ham_assessment_data'])) {
                    $assessment_data_json = wp_unslash($_POST['ham_assessment_data']);

                    if (! empty($assessment_data_json)) {
                        $assessment_data = json_decode($assessment_data_json, true);

                        if ($assessment_data !== null) {
                            update_post_meta($post_id, HAM_ASSESSMENT_META_DATA, $assessment_data);
                        }
                    } else {
                        delete_post_meta($post_id, HAM_ASSESSMENT_META_DATA);
                    }
                }
            }
        }
    }
}

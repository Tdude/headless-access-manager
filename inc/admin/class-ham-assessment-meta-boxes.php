<?php
/**
 * File: inc/admin/class-ham-assessment-meta-boxes.php
 *
 * Handles meta boxes for assessment post types.
 *
 * @package HeadlessAccessManager
 */

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class HAM_Assessment_Meta_Boxes
 *
 * Adds and manages meta boxes for assessment-related post types.
 */
class HAM_Assessment_Meta_Boxes
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        // Add meta boxes
        add_action('add_meta_boxes', array( $this, 'add_meta_boxes' ));

        // Save meta box data
        add_action('save_post_' . HAM_CPT_ASSESSMENT, array( $this, 'save_meta_boxes' ));

        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ));
    }

    /**
     * Add meta boxes to assessment post type.
     */
    public function add_meta_boxes()
    {
        add_meta_box(
            'ham_assessment_questions',
            __('Assessment Questions', 'headless-access-manager'),
            array( $this, 'render_questions_meta_box' ),
            HAM_CPT_ASSESSMENT,
            'normal',
            'high'
        );

        add_meta_box(
            'ham_assessment_student',
            __('Student Information', 'headless-access-manager'),
            array( $this, 'render_student_meta_box' ),
            HAM_CPT_ASSESSMENT,
            'side',
            'high'
        );
    }

    /**
     * Render questions meta box.
     *
     * @param WP_Post $post Current post object.
     */
    public function render_questions_meta_box($post)
    {
        // Add nonce for security
        wp_nonce_field('ham_assessment_questions_meta_box', 'ham_assessment_questions_nonce');

        // Get current assessment data
        $assessment_data = get_post_meta($post->ID, HAM_ASSESSMENT_META_DATA, true);
        error_log('Loading data from meta key: ' . HAM_ASSESSMENT_META_DATA);
        error_log('Retrieved assessment data type: ' . gettype($assessment_data));
        error_log('Retrieved assessment data: ' . (empty($assessment_data) ? 'EMPTY' : 'NOT EMPTY'));

        // If no data exists, initialize with empty structure
        if (empty($assessment_data)) {
            $assessment_data = array(
                'anknytning' => array(
                    'questions' => array(),
                    'comments' => array(),
                ),
                'ansvar' => array(
                    'questions' => array(),
                    'comments' => array(),
                ),
            );
        }

        // Convert to JSON for JavaScript
        $assessment_data_json = wp_json_encode($assessment_data);
        error_log('Assessment data JSON for editor: ' . substr($assessment_data_json, 0, 100) . '...');
        ?>
<div id="ham-assessment-editor" class="ham-assessment-editor">
    <div class="ham-sections">
        <div class="ham-section-tabs">
            <button type="button" class="ham-section-tab active"
                data-section="anknytning"><?php _e('Anknytning', 'headless-access-manager'); ?></button>
            <button type="button" class="ham-section-tab"
                data-section="ansvar"><?php _e('Ansvar', 'headless-access-manager'); ?></button>
        </div>

        <div class="ham-section-content active" data-section="anknytning">
            <h3><?php _e('Anknytning Questions', 'headless-access-manager'); ?></h3>
            <div class="ham-questions-container" id="anknytning-questions"></div>
            <button type="button" class="button ham-add-question"
                data-section="anknytning"><?php _e('Add Question', 'headless-access-manager'); ?></button>
        </div>

        <div class="ham-section-content" data-section="ansvar">
            <h3><?php _e('Ansvar Questions', 'headless-access-manager'); ?></h3>
            <div class="ham-questions-container" id="ansvar-questions"></div>
            <button type="button" class="button ham-add-question"
                data-section="ansvar"><?php _e('Add Question', 'headless-access-manager'); ?></button>
        </div>
    </div>

    <textarea id="ham_assessment_data_json" name="ham_assessment_data"
        style="display: none;"><?php echo esc_textarea($assessment_data_json); ?></textarea>
</div>
<?php
    }

    /**
     * Render student meta box.
     *
     * @param WP_Post $post Current post object.
     */
    public function render_student_meta_box($post)
    {
        // Add nonce for security
        wp_nonce_field('ham_assessment_student_meta_box', 'ham_assessment_student_nonce');

        // Get current student ID
        $student_id = get_post_meta($post->ID, HAM_ASSESSMENT_META_STUDENT_ID, true);

        // Get all students
        $students = get_users(array( 'role' => HAM_ROLE_STUDENT ));

        ?>
<p>
    <label for="ham_student_id"><?php _e('Select Student:', 'headless-access-manager'); ?></label>
    <select name="ham_student_id" id="ham_student_id" class="widefat">
        <option value=""><?php _e('— Select Student —', 'headless-access-manager'); ?></option>
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
     * Save meta box data.
     *
     * @param int $post_id Post ID.
     */
    public function save_meta_boxes($post_id)
    {
        // Check nonce for questions meta box
        if (! isset($_POST['ham_assessment_questions_nonce']) ||
            ! wp_verify_nonce($_POST['ham_assessment_questions_nonce'], 'ham_assessment_questions_meta_box')) {
            return;
        }

        // Check nonce for student meta box
        if (! isset($_POST['ham_assessment_student_nonce']) ||
            ! wp_verify_nonce($_POST['ham_assessment_student_nonce'], 'ham_assessment_student_meta_box')) {
            return;
        }

        // Check if autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (! current_user_can('edit_post', $post_id)) {
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

        // Save assessment data
        if (isset($_POST['ham_assessment_data'])) {
            error_log('Processing ham_assessment_data');
            $assessment_data_json = wp_unslash($_POST['ham_assessment_data']);
            error_log('Assessment Data JSON: ' . substr($assessment_data_json, 0, 100) . '...');

            if (!empty($assessment_data_json)) {
                $assessment_data = json_decode($assessment_data_json, true);

                if ($assessment_data !== null) {
                    error_log('Successfully decoded JSON data');
                    update_post_meta($post_id, HAM_ASSESSMENT_META_DATA, $assessment_data);
                    error_log('Updated assessment data metadata');
                } else {
                    error_log('JSON decode error: ' . json_last_error_msg());
                }
            } else {
                error_log('ham_assessment_data is empty');
            }
        } else {
            error_log('ham_assessment_data not in POST data');
        }

        // Dump all POST data keys
        error_log('POST data keys: ' . implode(', ', array_keys($_POST)));
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @param string $hook Current admin page.
     */
    public function enqueue_admin_assets($hook)
    {
        global $post;

        // Only enqueue on assessment edit page
        if (! ($hook === 'post.php' || $hook === 'post-new.php') ||
             ! is_object($post) || $post->post_type !== HAM_CPT_ASSESSMENT) {
            return;
        }

        // Enqueue scripts
        wp_enqueue_script(
            'ham-assessment-editor',
            HAM_PLUGIN_URL . 'assets/js/assessment-editor.js',
            array( 'jquery' ),
            HAM_VERSION,
            true
        );

        // Enqueue styles
        wp_enqueue_style(
            'ham-assessment-editor',
            HAM_PLUGIN_URL . 'assets/css/assessment-editor.css',
            array(),
            HAM_VERSION
        );

        // Localize script
        wp_localize_script(
            'ham-assessment-editor',
            'hamAssessmentEditor',
            array(
                'texts' => array(
                    'question'     => __('Question', 'headless-access-manager'),
                    'option'       => __('Option', 'headless-access-manager'),
                    'deleteQuestion' => __('Delete Question', 'headless-access-manager'),
                    'deleteOption' => __('Delete Option', 'headless-access-manager'),
                    'addOption'    => __('Add Option', 'headless-access-manager'),
                    'stage'        => __('Stage', 'headless-access-manager'),
                    'value'        => __('Value', 'headless-access-manager'),
                    'label'        => __('Label', 'headless-access-manager'),
                ),
                'stages' => array(
                    'ej'    => __('Not Connected', 'headless-access-manager'),
                    'trans' => __('In Transition', 'headless-access-manager'),
                    'full'  => __('Fully Connected', 'headless-access-manager'),
                ),
                'defaultOptionsCount' => 5, // Number of options to create for new questions
            )
        );
    }
}

// Initialize the class
new HAM_Assessment_Meta_Boxes();

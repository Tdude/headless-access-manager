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
        add_action('add_meta_boxes_' . HAM_CPT_ASSESSMENT, array( $this, 'add_meta_boxes' ));

        // Save post handler
        add_action('save_post_' . HAM_CPT_ASSESSMENT, array( $this, 'save_post' ), 10, 2);

        // AJAX handlers
        add_action('wp_ajax_ham_save_assessment_data', array( $this, 'ajax_save_assessment_data' ));

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
    }

    /**
     * Render questions meta box.
     *
     * @param WP_Post $post Current post object.
     */
    public function render_questions_meta_box($post)
    {
        //error_log('HAM Debug - Rendering meta box for post ' . $post->ID);
        
        // Get current data
        $assessment_data = get_post_meta($post->ID, '_ham_assessment_data', true);
        //error_log('HAM Debug - Current data: ' . print_r($assessment_data, true));
        
        // Default empty structure
        if (empty($assessment_data)) {
            //error_log('HAM Debug - Using default structure');
            require_once dirname(__FILE__, 2) . '/assessment-constants.php';
            $assessment_data = HAM_ASSESSMENT_DEFAULT_STRUCTURE;
        }
            
        // Save default structure
        update_post_meta($post->ID, '_ham_assessment_data', $assessment_data);
        ?>

        <div id="ham-assessment-editor" class="ham-assessment-editor">
            <?php wp_nonce_field('ham_save_assessment', 'ham_assessment_nonce'); ?>
            
            <script>
                console.log('HAM: Meta box rendered');
                console.log('HAM: Assessment data:', <?php echo wp_json_encode($assessment_data); ?>);
            </script>

            <!-- Tabs -->
            <div class="ham-section-tabs">
                <button type="button" class="ham-section-tab active" data-section="anknytning">Anknytning</button>
                <button type="button" class="ham-section-tab" data-section="ansvar">Ansvar</button>
            </div>

            <!-- Sections -->
            <div class="ham-sections">
                <!-- Anknytning Section -->
                <div id="anknytning-section" class="ham-section-content" data-section="anknytning">
                    <div id="anknytning-questions" class="ham-questions"></div>
                    <button type="button" class="button ham-add-question" data-section="anknytning">Add Question</button>
                </div>

                <!-- Ansvar Section -->
                <div id="ansvar-section" class="ham-section-content" data-section="ansvar">
                    <div id="ansvar-questions" class="ham-questions"></div>
                    <button type="button" class="button ham-add-question" data-section="ansvar">Add Question</button>
                </div>
            </div>

            <!-- Hidden field for assessment data -->
            <input type="hidden" id="ham_assessment_data" name="_ham_assessment_data" value="<?php echo esc_attr(wp_json_encode($assessment_data)); ?>">
        </div>
        <?php
    }

    /**
     * Save post handler
     *
     * @param int $post_id The ID of the post being saved.
     * @param WP_Post $post The post object.
     */
    public function save_post($post_id, $post)
    {
        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check if our nonce is set and verify it.
        if (!isset($_POST['ham_assessment_nonce']) || !wp_verify_nonce($_POST['ham_assessment_nonce'], 'ham_save_assessment')) {
            return;
        }

        // Check the user's permissions.
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Get the posted data
        $assessment_data = isset($_POST['_ham_assessment_data']) ? wp_unslash($_POST['_ham_assessment_data']) : '';
        
        if (!empty($assessment_data)) {
            $data = json_decode($assessment_data, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                // Ensure we have the required structure
                if (!isset($data['anknytning'])) {
                    $data['anknytning'] = array('title' => 'Anknytning', 'questions' => array(), 'comments' => array());
                }
                if (!isset($data['ansvar'])) {
                    $data['ansvar'] = array('title' => 'Ansvar', 'questions' => array(), 'comments' => array());
                }
                
                // Save the data
                update_post_meta($post_id, '_ham_assessment_data', $data);
            }
        }
    }

    /**
     * Enqueue admin scripts and styles.
     */
    public function enqueue_admin_assets($hook)
    {
        global $post, $typenow;

        // Only enqueue on post edit/new screen for our post type
        // Use $typenow for new posts, get_post_type() for existing posts
        $current_post_type = $typenow ? $typenow : ($post ? get_post_type($post->ID) : '');
        
        if (($hook == 'post.php' || $hook == 'post-new.php') && $current_post_type === HAM_CPT_ASSESSMENT) {
            // Enqueue JS
            wp_enqueue_script(
                'ham-assessment-editor',
                plugins_url('assets/js/assessment-editor.js', HAM_PLUGIN_FILE),
                array('jquery'),
                '1.0.3', // Incremented to bust cache
                true
            );

            // Enqueue CSS
            wp_enqueue_style(
                'ham-assessment-editor',
                plugins_url('assets/css/assessment-editor.css', HAM_PLUGIN_FILE),
                array(),
                '1.0.2' // Incremented to bust cache
            );

            // Pass data to JavaScript
            wp_localize_script('ham-assessment-editor', 'hamAssessmentEditor', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ham_save_assessment'),
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
                'defaultOptionsCount' => 5,
                'debug' => true // Enable debug mode
            ));
            
            // Add inline script to verify enqueue
            wp_add_inline_script('ham-assessment-editor', 'console.log("HAM: assessment-editor.js enqueued successfully");', 'before');
        }
    }

    /**
     * AJAX handler for saving assessment data
     */
    public function ajax_save_assessment_data()
    {
        //error_log('HAM Debug - AJAX save called');
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ham_save_assessment')) {
            error_log('HAM Debug - Nonce verification failed');
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Verify post ID
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            error_log('HAM Debug - Invalid post ID or permissions');
            wp_send_json_error('Invalid post ID or permissions');
            return;
        }

        // Get and validate data
        $assessment_data = isset($_POST['assessment_data']) ? $_POST['assessment_data'] : '';
        if (empty($assessment_data)) {
            //error_log('HAM Debug - No assessment data');
            wp_send_json_error('No assessment data');
            return;
        }

        //error_log('HAM Debug - Raw assessment data: ' . $assessment_data);
        
        $data = json_decode(wp_unslash($assessment_data), true);
        //error_log('HAM Debug - Decoded data: ' . print_r($data, true));
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('HAM Debug - JSON decode error: ' . json_last_error_msg());
            wp_send_json_error('Invalid JSON data');
            return;
        }

        // Validate structure
        if (!isset($data['anknytning']) || !isset($data['ansvar']) ||
            !isset($data['anknytning']['questions']) || !isset($data['ansvar']['questions'])) {
            error_log('HAM Debug - Invalid data structure');
            wp_send_json_error('Invalid data structure');
            return;
        }

        // Save the data
        update_post_meta($post_id, '_ham_assessment_data', $data);
        //error_log('HAM Debug - Data saved successfully');
        
        wp_send_json_success(array(
            'message' => 'Assessment data saved successfully'
        ));
    }
}

// Initialize the class
new HAM_Assessment_Meta_Boxes();
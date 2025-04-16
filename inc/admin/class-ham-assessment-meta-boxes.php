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
        error_log('HAM Debug - Rendering meta box for post ' . $post->ID);
        
        // Get current data
        $assessment_data = get_post_meta($post->ID, '_ham_assessment_data', true);
        error_log('HAM Debug - Current data: ' . print_r($assessment_data, true));
        
        // Default empty structure
        if (empty($assessment_data)) {
            error_log('HAM Debug - Using default structure');
            $assessment_data = array(
                'anknytning' => array(
                    'title' => 'Anknytning',
                    'questions' => array(
                        'a1' => array(
                            'text' => 'Närvaro',
                            'options' => array(
                                array('value' => '1', 'label' => 'Kommer inte till skolan', 'stage' => 'ej'),
                                array('value' => '2', 'label' => 'Kommer till skolan, ej till lektion', 'stage' => 'ej'),
                                array('value' => '3', 'label' => 'Kommer till min lektion ibland', 'stage' => 'trans'),
                                array('value' => '4', 'label' => 'Kommer alltid till min lektion', 'stage' => 'trans'),
                                array('value' => '5', 'label' => 'Kommer till andras lektioner', 'stage' => 'full')
                            )
                        ),
                        'a2' => array(
                            'text' => 'Dialog 1 - introvert',
                            'options' => array(
                                array('value' => '1', 'label' => 'Helt tyst', 'stage' => 'ej'),
                                array('value' => '2', 'label' => 'Säger enstaka ord till mig', 'stage' => 'ej'),
                                array('value' => '3', 'label' => 'Vi pratar ibland', 'stage' => 'trans'),
                                array('value' => '4', 'label' => 'Har full dialog med mig', 'stage' => 'trans'),
                                array('value' => '5', 'label' => 'Har dialog med andra vuxna', 'stage' => 'full')
                            )
                        ),
                        'a3' => array(
                            'text' => 'Dialog 2 - extrovert',
                            'options' => array(
                                array('value' => '1', 'label' => 'Pratar oavbrutet', 'stage' => 'ej'),
                                array('value' => '2', 'label' => 'Är tyst vid tillsägelse', 'stage' => 'ej'),
                                array('value' => '3', 'label' => 'Lyssnar på mig', 'stage' => 'trans'),
                                array('value' => '4', 'label' => 'Har full dialog med mig', 'stage' => 'trans'),
                                array('value' => '5', 'label' => 'Dialog med vissa andra vuxna', 'stage' => 'full')
                            )
                        ),
                        'a4' => array(
                            'text' => 'Blick, kroppsspråk',
                            'options' => array(
                                array('value' => '1', 'label' => 'Möter inte min blick', 'stage' => 'ej'),
                                array('value' => '2', 'label' => 'Har gett mig ett ögonkast', 'stage' => 'ej'),
                                array('value' => '3', 'label' => 'Håller fast ögonkontakt ', 'stage' => 'trans'),
                                array('value' => '4', 'label' => 'Pratar” med ögonen', 'stage' => 'trans'),
                                array('value' => '5', 'label' => 'Möter andras blickar', 'stage' => 'full')
                            )
                        ),
                        'a5' => array(
                            'text' => 'Beröring',
                            'options' => array(
                                array('value' => '1', 'label' => 'Jag får inte närma mig', 'stage' => 'ej'),
                                array('value' => '2', 'label' => 'Jag får närma mig', 'stage' => 'ej'),
                                array('value' => '3', 'label' => 'Tillåter beröring av mig', 'stage' => 'trans'),
                                array('value' => '4', 'label' => 'Söker fysisk kontakt, ex. kramar', 'stage' => 'trans'),
                                array('value' => '5', 'label' => 'Tillåter beröring av andra vuxna', 'stage' => 'full')
                            )
                        ),
                        'a6' => array(
                            'text' => 'Vid konflikt',
                            'options' => array(
                                array('value' => '1', 'label' => 'Försvinner från skolan vid konflikt', 'stage' => 'ej'),
                                array('value' => '2', 'label' => 'Stannar kvar på skolan', 'stage' => 'ej'),
                                array('value' => '3', 'label' => 'Kommer tillbaka till mig', 'stage' => 'trans'),
                                array('value' => '4', 'label' => 'Förklarar för mig efter konikt', 'stage' => 'trans'),
                                array('value' => '5', 'label' => 'Kommer tillbaka till andra vuxna', 'stage' => 'full')
                            )
                        ),
                        'a7' => array(
                            'text' => 'Förtroende',
                            'options' => array(
                                array('value' => '1', 'label' => 'Delar inte med sig till mig', 'stage' => 'ej'),
                                array('value' => '2', 'label' => 'Delar med sig till mig ibland', 'stage' => 'ej'),
                                array('value' => '3', 'label' => 'Vill dela med sig till mig', 'stage' => 'trans'),
                                array('value' => '4', 'label' => 'Ger mig förtroenden', 'stage' => 'trans'),
                                array('value' => '5', 'label' => 'Ger även förtroenden till vissa andra', 'stage' => 'full')
                            )
                        ),
                    ),
                    'comments' => array()
                ),
                'ansvar' => array(
                    'title' => 'Ansvar',
                    'questions' => array(
                        'b1' => array(
                            'text' => 'Impulskontroll',
                            'options' => array(
                                array('value' => '1', 'label' => 'Helt impulsstyrd. Ex. kan inte sitta still, förstör, säger fula ord', 'stage' => 'ej'),
                                array('value' => '2', 'label' => 'Kan ibland hålla negativa känslor utan att agera på dem', 'stage' => 'ej'),
                                array('value' => '3', 'label' => 'Skäms över negativa beteenden', 'stage' => 'trans'),
                                array('value' => '4', 'label' => 'Kan ta emot tillsägelse', 'stage' => 'trans'),
                                array('value' => '5', 'label' => 'Kan prata om det som hänt', 'stage' => 'full')
                            )
                        ),
                        'b2' => array(
                            'text' => 'Förberedd',
                            'options' => array(
                                array('value' => '1', 'label' => 'Aldrig', 'stage' => 'ej'),
                                array('value' => '2', 'label' => 'Lyckas vara förberedd en första gång', 'stage' => 'ej'),
                                array('value' => '3', 'label' => 'Försöker vara förberedd som andra', 'stage' => 'trans'),
                                array('value' => '4', 'label' => 'Pratar om förberedelse', 'stage' => 'trans'),
                                array('value' => '5', 'label' => 'Planerar och har ordning', 'stage' => 'full')
                            )
                        ),
                        'b3' => array(
                            'text' => 'Fokus',
                            'options' => array(
                                array('value' => '1', 'label' => 'Kan inte fokusera', 'stage' => 'ej'),
                                array('value' => '2', 'label' => 'Kan fokusera en kort stund vid enskild tillsägelse', 'stage' => 'ej'),
                                array('value' => '3', 'label' => 'Kan fokusera självmant tillsammans med andra', 'stage' => 'trans'),
                                array('value' => '4', 'label' => 'Pratar om fokus och förbättrar sig', 'stage' => 'trans'),
                                array('value' => '5', 'label' => 'Kan fokusera och koncentrera sig', 'stage' => 'full')
                            )
                        ),
                        'b4' => array(
                            'text' => 'Turtagning',
                            'options' => array(
                                array('value' => '1', 'label' => 'Klarar ej', 'stage' => 'ej'),
                                array('value' => '2', 'label' => 'Klarar av att vänta vid tillsägelse', 'stage' => 'ej'),
                                array('value' => '3', 'label' => 'Gör som andra, räcker upp handen', 'stage' => 'trans'),
                                array('value' => '4', 'label' => 'Kan komma överens om hur turtagning fungerar', 'stage' => 'trans'),
                                array('value' => '5', 'label' => 'Full turtagning andra', 'stage' => 'full')
                            )
                        ),
                        'b5' => array(
                            'text' => 'Instruktion',
                            'options' => array(
                                array('value' => '1', 'label' => 'Tar inte/förstår inte instruktion', 'stage' => 'ej'),
                                array('value' => '2', 'label' => 'Tar/förstår instruktion i ett led men startar inte uppgift', 'stage' => 'ej'),
                                array('value' => '3', 'label' => 'Tar/förstår instruktion i flera led, kan lösa uppgift ibland', 'stage' => 'trans'),
                                array('value' => '4', 'label' => 'Kan prata om uppgiftslösning', 'stage' => 'trans'),
                                array('value' => '5', 'label' => 'Genomför uppgifter', 'stage' => 'full')
                            )
                        ),
                        'b6' => array(
                            'text' => 'Arbeta själv',
                            'options' => array(
                                array('value' => '1', 'label' => 'Klara inte', 'stage' => 'ej'),
                                array('value' => '2', 'label' => 'Löser en uppgift med stöd', 'stage' => 'ej'),
                                array('value' => '3', 'label' => 'Kan klara uppgifter självständigt i klassrummet', 'stage' => 'trans'),
                                array('value' => '4', 'label' => 'Gör ofta läxor och pratar om dem', 'stage' => 'trans'),
                                array('value' => '5', 'label' => 'Tar ansvar för självständigt arbete utanför skolan', 'stage' => 'full')
                            )
                        ),
                        'b7' => array(
                            'text' => 'Tid',
                            'options' => array(
                                array('value' => '1', 'label' => 'Ingen tidsuppfattning', 'stage' => 'ej'),
                                array('value' => '2', 'label' => 'Börjar använda andra konkreta referenser', 'stage' => 'ej'),
                                array('value' => '3', 'label' => 'Har begrepp för en kvart', 'stage' => 'trans'),
                                array('value' => '4', 'label' => 'Kan beskriva tidslängd och ordningsförlopp', 'stage' => 'trans'),
                                array('value' => '5', 'label' => 'God tidsuppfattning', 'stage' => 'full')
                            )
                        )
                    ),
                    'comments' => array()
                )
            );
            
            // Save default structure
            update_post_meta($post->ID, '_ham_assessment_data', $assessment_data);
        }

        ?>
        <div id="ham-assessment-editor" class="ham-assessment-editor">
            <?php wp_nonce_field('ham_save_assessment', 'ham_assessment_nonce'); ?>

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
        global $post;

        // Only enqueue on post edit screen for our post type
        if ($hook == 'post.php' && $post && get_post_type($post->ID) === HAM_CPT_ASSESSMENT) {
            // Enqueue JS
            wp_enqueue_script(
                'ham-assessment-editor',
                plugins_url('assets/js/assessment-editor.js', HAM_PLUGIN_FILE),
                array('jquery'),
                '1.0.0',
                true
            );

            // Enqueue CSS
            wp_enqueue_style(
                'ham-assessment-editor',
                plugins_url('assets/css/assessment-editor.css', HAM_PLUGIN_FILE),
                array(),
                '1.0.0'
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
                'defaultOptionsCount' => 5
            ));
        }
    }

    /**
     * AJAX handler for saving assessment data
     */
    public function ajax_save_assessment_data()
    {
        error_log('HAM Debug - AJAX save called');
        
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
            error_log('HAM Debug - No assessment data');
            wp_send_json_error('No assessment data');
            return;
        }

        error_log('HAM Debug - Raw assessment data: ' . $assessment_data);
        
        $data = json_decode(wp_unslash($assessment_data), true);
        error_log('HAM Debug - Decoded data: ' . print_r($data, true));
        
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
        error_log('HAM Debug - Data saved successfully');
        
        wp_send_json_success(array(
            'message' => 'Assessment data saved successfully'
        ));
    }
}

// Initialize the class
new HAM_Assessment_Meta_Boxes();
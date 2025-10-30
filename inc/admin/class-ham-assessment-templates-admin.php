<?php
/**
 * File: inc/admin/class-ham-assessment-templates-admin.php
 *
 * Handles admin functionality for assessment templates.
 *
 * @package HeadlessAccessManager
 */

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class HAM_Assessment_Templates_Admin
 *
 * Manages admin functionality for assessment templates.
 */
class HAM_Assessment_Templates_Admin
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        // Register the assessment template post type
        add_action('init', array( $this, 'register_assessment_template_post_type' ));

        // Add meta boxes for template structure
        add_action('add_meta_boxes_' . HAM_CPT_ASSESSMENT_TPL, array( $this, 'add_template_meta_boxes' ));

        // Save template meta data
        add_action('save_post_ham_assessment_tpl', array( $this, 'save_template_meta' ));

        // Enqueue admin scripts and styles
        add_action('admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ));
    }

    /**
     * Register assessment template post type.
     */
    public function register_assessment_template_post_type()
    {
        $labels = array(
            'name'                  => _x('Assessment Templates', 'Post type general name', 'headless-access-manager'),
            'singular_name'         => _x('Assessment Template', 'Post type singular name', 'headless-access-manager'),
            'menu_name'             => _x('Assessment Templates', 'Admin Menu text', 'headless-access-manager'),
            'name_admin_bar'        => _x('Assessment Template', 'Add New on Toolbar', 'headless-access-manager'),
            'add_new'               => __('Add New', 'headless-access-manager'),
            'add_new_item'          => __('Add New Template', 'headless-access-manager'),
            'new_item'              => __('New Template', 'headless-access-manager'),
            'edit_item'             => __('Edit Template', 'headless-access-manager'),
            'view_item'             => __('View Template', 'headless-access-manager'),
            'all_items'             => __('All Templates', 'headless-access-manager'),
            'search_items'          => __('Search Templates', 'headless-access-manager'),
            'not_found'             => __('No templates found.', 'headless-access-manager'),
            'not_found_in_trash'    => __('No templates found in Trash.', 'headless-access-manager'),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => 'headless-access-manager',
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'assessment-template' ),
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array( 'title', 'editor' ),
            'show_in_rest'       => false,
        );

        register_post_type('ham_assessment_tpl', $args);
    }

    /**
     * Add meta boxes for template structure.
     */
    public function add_template_meta_boxes()
    {
        add_meta_box(
            'ham_template_structure',
            __('Template Structure', 'headless-access-manager'),
            array( $this, 'render_template_structure_meta_box' ),
            'ham_assessment_tpl',
            'normal',
            'high'
        );
    }

    /**
     * Render template structure meta box.
     *
     * @param WP_Post $post Current post object.
     */
    public function render_template_structure_meta_box($post)
    {
        // Add nonce for security
        wp_nonce_field('ham_template_structure_meta_box', 'ham_template_structure_nonce');

        // Get current structure
        $structure = get_post_meta($post->ID, '_ham_template_structure', true);

        // If empty, use default structure based on the evaluation form
        if (empty($structure)) {
            $structure = $this->get_default_structure();
        }

        // Convert structure to JSON for JavaScript
        $structure_json = wp_json_encode($structure);
        ?>
<div id="ham-template-editor">
    <p><?php _e('Define the structure of the assessment template below. You can add sections and fields, and configure their options.', 'headless-access-manager'); ?>
    </p>

    <div id="ham-sections-container" class="ham-sections-container"></div>

    <div class="ham-template-actions">
        <button type="button"
            class="button button-secondary ham-add-section"><?php _e('Add Section', 'headless-access-manager'); ?></button>
    </div>

    <textarea id="ham_template_structure" name="ham_template_structure"
        style="display: none;"><?php echo esc_textarea($structure_json); ?></textarea>
</div>
<?php
    }

    /**
     * Get default template structure.
     *
     * @return array Default structure.
     */
    private function get_default_structure()
    {
        require_once dirname(__FILE__, 2) . '/assessment-constants.php';
        $canonical = HAM_ASSESSMENT_DEFAULT_STRUCTURE;
        // Map canonical to template format (sections/fields), prepend marker
        $section_map = function($section_id, $section, $context) {
            $fields = [];
            // Add context marker as first field
            $fields[] = [
                'id' => '__source__',
                'title' => '[admin/class-ham-assessment-templates-admin.php]',
                'options' => [],
            ];
            foreach ($section['questions'] as $qid => $q) {
                $fields[] = [
                    'id' => $qid,
                    'title' => $q['text'],
                    'options' => $q['options'],
                ];
            }
            return [
                'id' => $section_id,
                'title' => $section['title'],
                'fields' => $fields,
            ];
        };
        return [
            'sections' => [
                $section_map('anknytning', $canonical['anknytning'], 'anknytning'),
                $section_map('ansvar', $canonical['ansvar'], 'ansvar'),
            ],
        ];
    }

    /**
     * Save template meta.
     *
     * @param int $post_id Post ID.
     */
    public function save_template_meta($post_id)
    {
        // Check if nonce is set
        if (! isset($_POST['ham_template_structure_nonce'])) {
            return;
        }

        // Verify nonce
        if (! wp_verify_nonce($_POST['ham_template_structure_nonce'], 'ham_template_structure_meta_box')) {
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

        // Save template structure
        if (isset($_POST['ham_template_structure'])) {
            $structure_json = wp_unslash($_POST['ham_template_structure']);
            $structure = json_decode($structure_json, true);

            if ($structure !== null) {
                update_post_meta($post_id, '_ham_template_structure', $structure);
            }
        }
    }
}

// Initialize the class
new HAM_Assessment_Templates_Admin();

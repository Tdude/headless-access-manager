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
            [__CLASS__, 'render_assessment_student_meta_box'],
            HAM_CPT_ASSESSMENT,
            'side',
            'high'
        );
        add_meta_box(
            'ham_assessment_teacher',
            __('Teacher', 'headless-access-manager'),
            [__CLASS__, 'render_assessment_teacher_meta_box'],
            HAM_CPT_ASSESSMENT,
            'side',
            'high'
        );
        add_meta_box(
            'ham_assessment_data',
            __('Assessment Data', 'headless-access-manager'),
            [__CLASS__, 'render_assessment_data_meta_box'],
            HAM_CPT_ASSESSMENT,
            'normal',
            'high'
        );
    }

    /**
     * Render assessment student meta box with a searchable dropdown.
     *
     * @param WP_Post $post Current post object.
     */
    public static function render_assessment_student_meta_box($post)
    {
        wp_nonce_field('ham_assessment_student_meta_box', 'ham_assessment_student_meta_box_nonce');

        $student_id = get_post_meta($post->ID, HAM_ASSESSMENT_META_STUDENT_ID, true);
        $student_name = $student_id ? get_the_title($student_id) : '';
        ?>
        <p>
            <label for="ham_student_id"><?php echo esc_html__('Select Student:', 'headless-access-manager'); ?></label>
            <select name="ham_student_id" id="ham_student_id" class="widefat ham-student-search-select">
                <?php if ($student_id) : ?>
                    <option value="<?php echo esc_attr($student_id); ?>" selected="selected"><?php echo esc_html($student_name); ?></option>
                <?php endif; ?>
            </select>
            <span class="description"><?php echo esc_html__('Type to search for a student by name.', 'headless-access-manager'); ?></span>
        </p>
        <script>
            jQuery(function($) {
                if (!$.fn.select2) {
                    return;
                }

                $('#ham_student_id').select2({
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
                    allowClear: true
                });
            });
        </script>
        <?php
    }

    /**
     * Render assessment teacher meta box with a searchable dropdown.
     *
     * Teacher is stored as the post author for evaluations.
     *
     * @param WP_Post $post Current post object.
     */
    public static function render_assessment_teacher_meta_box($post)
    {
        wp_nonce_field('ham_assessment_teacher_meta_box', 'ham_assessment_teacher_meta_box_nonce');

        $teacher_id = isset($post->post_author) ? absint($post->post_author) : 0;
        $teacher_user = $teacher_id ? get_user_by('id', $teacher_id) : null;
        $teacher_name = $teacher_user ? $teacher_user->display_name : '';
        ?>
        <p>
            <label for="ham_teacher_id"><?php echo esc_html__('Select Teacher:', 'headless-access-manager'); ?></label>
            <select name="ham_teacher_id" id="ham_teacher_id" class="widefat ham-teacher-search-select">
                <?php if ($teacher_id && $teacher_user) : ?>
                    <option value="<?php echo esc_attr($teacher_id); ?>" selected="selected"><?php echo esc_html($teacher_name); ?></option>
                <?php endif; ?>
            </select>
            <span class="description"><?php echo esc_html__('Type to search for a teacher by name.', 'headless-access-manager'); ?></span>
        </p>
        <script>
            jQuery(function($) {
                if (!$.fn.select2) {
                    return;
                }

                $('#ham_teacher_id').select2({
                    ajax: {
                        url: ajaxurl,
                        dataType: 'json',
                        delay: 250,
                        data: function(params) {
                            return {
                                action: 'ham_search_teachers',
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
                    allowClear: true
                });
            });
        </script>
        <?php
    }

    /**
     * Render assessment data meta box.
     *
     * @param WP_Post $post Current post object.
     */
    public static function render_assessment_data_meta_box($post)
    {
        wp_nonce_field('ham_assessment_data_meta_box', 'ham_assessment_data_meta_box_nonce');

        $assessment_data = get_post_meta($post->ID, HAM_ASSESSMENT_META_DATA, true);
        $assessment_date = get_post_meta($post->ID, HAM_ASSESSMENT_META_DATE, true);

        $assessment_data_json = is_array($assessment_data) ? json_encode($assessment_data, JSON_PRETTY_PRINT) : '';
        ?>
        <p>
            <label for="ham_assessment_date"><?php echo esc_html__('Assessment Date:', 'headless-access-manager'); ?></label>
            <input type="date" name="ham_assessment_date" id="ham_assessment_date" class="widefat" value="<?php echo esc_attr($assessment_date ? date('Y-m-d', strtotime($assessment_date)) : ''); ?>">
        </p>
        <p>
            <label for="ham_assessment_data"><?php echo esc_html__('Assessment Data (JSON):', 'headless-access-manager'); ?></label>
            <textarea name="ham_assessment_data" id="ham_assessment_data" class="widefat" rows="10"><?php echo esc_textarea($assessment_data_json); ?></textarea>
            <span class="description"><?php echo esc_html__('Enter assessment data in JSON format.', 'headless-access-manager'); ?></span>
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
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (get_post_type($post_id) === HAM_CPT_ASSESSMENT) {
            // Get student ID before any changes are made.
            $old_student_id = (int) get_post_meta($post_id, HAM_ASSESSMENT_META_STUDENT_ID, true);

            // Save the meta data.
            self::save_assessment_meta_boxes($post_id);

            // Get student ID after changes.
            $new_student_id = (int) get_post_meta($post_id, HAM_ASSESSMENT_META_STUDENT_ID, true);
            
            // Clear cache for the old student's class(es) if they were set
            if ($old_student_id > 0) {
                self::clear_class_caches_for_student($old_student_id);
            }

            // If a new student was assigned and is different from the old one, clear their class caches too.
            if ($new_student_id > 0 && $new_student_id !== $old_student_id) {
                self::clear_class_caches_for_student($new_student_id);
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
        if (isset($_POST['ham_assessment_student_meta_box_nonce']) && wp_verify_nonce($_POST['ham_assessment_student_meta_box_nonce'], 'ham_assessment_student_meta_box')) {
            if (isset($_POST['ham_student_id'])) {
                $student_id = absint($_POST['ham_student_id']);
                if ($student_id > 0) {
                    update_post_meta($post_id, HAM_ASSESSMENT_META_STUDENT_ID, $student_id);
                } else {
                    delete_post_meta($post_id, HAM_ASSESSMENT_META_STUDENT_ID);
                }
            }
        }

        if (isset($_POST['ham_assessment_teacher_meta_box_nonce']) && wp_verify_nonce($_POST['ham_assessment_teacher_meta_box_nonce'], 'ham_assessment_teacher_meta_box')) {
            if (isset($_POST['ham_teacher_id'])) {
                $teacher_id = absint($_POST['ham_teacher_id']);
                $teacher_user = $teacher_id ? get_user_by('id', $teacher_id) : null;

                if ($teacher_id > 0 && $teacher_user) {
                    wp_update_post([
                        'ID' => $post_id,
                        'post_author' => $teacher_id,
                    ]);
                }
            }
        }

        if (isset($_POST['ham_assessment_data_meta_box_nonce']) && wp_verify_nonce($_POST['ham_assessment_data_meta_box_nonce'], 'ham_assessment_data_meta_box')) {
            if (isset($_POST['ham_assessment_date'])) {
                $assessment_date = sanitize_text_field($_POST['ham_assessment_date']);
                if (!empty($assessment_date)) {
                    update_post_meta($post_id, HAM_ASSESSMENT_META_DATE, $assessment_date);
                } else {
                    delete_post_meta($post_id, HAM_ASSESSMENT_META_DATE);
                }
            }

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
    }

    /**
     * Clear class statistics caches for all classes a student belongs to.
     *
     * @param int $student_id The ID of the student.
     */
    private static function clear_class_caches_for_student($student_id)
    {
        if (empty($student_id)) {
            return;
        }

        // Find all classes this student belongs to.
        $class_query = new WP_Query([
            'post_type'      => HAM_CPT_CLASS,
            'posts_per_page' => -1,
            'fields'         => 'ids', // We only need the IDs
            'meta_query'     => [
                [
                    'key'     => '_ham_student_ids',
                    'value'   => 'i:' . $student_id . ';', // Check for serialized integer
                    'compare' => 'LIKE'
                ]
            ]
        ]);

        if (!empty($class_query->posts)) {
            foreach ($class_query->posts as $class_id) {
                delete_transient("ham_class_evaluations_{$class_id}");
                delete_transient("ham_class_avg_score_{$class_id}");
            }
        }
    }

    /**
     * Enqueue scripts and styles for the assessment edit screen.
     */
    public static function enqueue_assessment_edit_scripts($hook)
    {
        // DEPRECATED: This function is no longer used.
        // Assessment editor assets are now enqueued in HAM_Assessment_Meta_Boxes::enqueue_admin_assets()
        // Keeping this function for backwards compatibility but it does nothing.
        return;
        
        /* OLD CODE - DO NOT USE
        global $post;
        if (($hook == 'post-new.php' || $hook == 'post.php') && isset($post->post_type) && $post->post_type === HAM_CPT_ASSESSMENT) {
            wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', [], '4.1.0-rc.0');
            wp_enqueue_script('select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], '4.1.0-rc.0', true);
            wp_enqueue_script('ham-assessment-editor', HAM_PLUGIN_URL . 'admin/js/assessment-editor.js', ['jquery', 'select2-js'], filemtime(HAM_PLUGIN_DIR . 'admin/js/assessment-editor.js'), true);
            wp_localize_script('ham-assessment-editor', 'ham_assessment_ajax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'search_nonce' => wp_create_nonce('ham_search_students_nonce'),
                'placeholder' => __('Search for a student...', 'headless-access-manager'),
            ]);
        }
        */
    }

    /**
     * AJAX handler for searching students.
     */
    /**
     * Handle tasks before an assessment post is deleted.
     *
     * @param int $post_id The post ID.
     */
    public static function on_delete_assessment($post_id)
    {
        if (get_post_type($post_id) !== HAM_CPT_ASSESSMENT) {
            return;
        }

        $student_id = (int) get_post_meta($post_id, HAM_ASSESSMENT_META_STUDENT_ID, true);

        if ($student_id > 0) {
            self::clear_class_caches_for_student($student_id);
        }
    }

    /**
     * AJAX handler for searching students.
     */
    public static function search_students_ajax_handler()
    {
        check_ajax_referer('ham_search_students_nonce', 'nonce');
        $search_term = isset($_GET['term']) ? sanitize_text_field($_GET['term']) : '';
        $results = [];

        if (empty($search_term)) {
            wp_send_json_success(['results' => $results]);
            return;
        }

        $student_query = new WP_Query([
            'post_type' => HAM_CPT_STUDENT,
            'post_status' => 'publish',
            'posts_per_page' => 20,
            's' => $search_term,
        ]);

        if ($student_query->have_posts()) {
            while ($student_query->have_posts()) {
                $student_query->the_post();
                $results[] = ['id' => get_the_ID(), 'text' => get_the_title()];
            }
        }
        wp_reset_postdata();

        wp_send_json_success(['results' => $results]);
    }

    /**
     * Initialize meta boxes for different CPTs.
     */
    public static function init() {
        add_action('add_meta_boxes_' . HAM_CPT_ASSESSMENT, [__CLASS__, 'register_assessment_meta_boxes']);
        add_action('save_post_' . HAM_CPT_ASSESSMENT, [__CLASS__, 'save_meta_boxes']);
        add_action('before_delete_post', [__CLASS__, 'on_delete_assessment']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assessment_edit_scripts']);
        // Note: student search is handled by HAM_Ajax_Handlers::search_students

        // Other CPTs
        add_action('add_meta_boxes_' . HAM_CPT_SCHOOL, ['HAM_School_Meta_Boxes', 'register_meta_boxes']);
        add_action('save_post_' . HAM_CPT_SCHOOL, ['HAM_School_Meta_Boxes', 'save_meta_boxes']);

        add_action('add_meta_boxes_' . HAM_CPT_CLASS, ['HAM_Class_Meta_Boxes', 'register_meta_boxes']);
        add_action('save_post_' . HAM_CPT_CLASS, ['HAM_Class_Meta_Boxes', 'save_meta_boxes']);

        add_action('add_meta_boxes_' . HAM_CPT_TEACHER, ['HAM_Teacher_Meta_Boxes', 'register_meta_boxes']);
        add_action('save_post_' . HAM_CPT_TEACHER, ['HAM_Teacher_Meta_Boxes', 'save_meta_boxes']);

        add_action('add_meta_boxes_' . HAM_CPT_PRINCIPAL, ['HAM_Principal_Meta_Boxes', 'register_meta_boxes']);
        add_action('save_post_' . HAM_CPT_PRINCIPAL, ['HAM_Principal_Meta_Boxes', 'save_meta_boxes']);

        add_action('add_meta_boxes_' . HAM_CPT_STUDENT, ['HAM_Student_Meta_Boxes', 'register_meta_boxes']);
        add_action('save_post_' . HAM_CPT_STUDENT, ['HAM_Student_Meta_Boxes', 'save_meta_boxes']);
    }
}

require_once HAM_PLUGIN_DIR . 'inc/admin/meta-boxes/class-ham-school-meta-boxes.php';
require_once HAM_PLUGIN_DIR . 'inc/admin/meta-boxes/class-ham-class-meta-boxes.php';
require_once HAM_PLUGIN_DIR . 'inc/admin/meta-boxes/class-ham-teacher-meta-boxes.php';
require_once HAM_PLUGIN_DIR . 'inc/admin/meta-boxes/class-ham-principal-meta-boxes.php';
require_once HAM_PLUGIN_DIR . 'inc/admin/meta-boxes/class-ham-student-meta-boxes.php';

HAM_Meta_Boxes::init();

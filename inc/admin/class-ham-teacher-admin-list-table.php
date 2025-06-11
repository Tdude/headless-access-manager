<?php
/**
 * Customizes the admin list table for the Teacher CPT.
 *
 * @package HeadlessAccessManager
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class HAM_Teacher_Admin_List_Table
 */
class HAM_Teacher_Admin_List_Table {

    /**
     * Initialize hooks.
     */
    public static function init() {
        add_filter('manage_ham_teacher_posts_columns', [__CLASS__, 'add_custom_columns']);
        add_action('manage_ham_teacher_posts_custom_column', [__CLASS__, 'render_custom_columns'], 10, 2);
        add_action('restrict_manage_posts', [__CLASS__, 'add_table_filters']);
        add_action('parse_query', [__CLASS__, 'filter_query']);
    }

    /**
     * Add custom columns to the teacher list table.
     *
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public static function add_custom_columns($columns) {
        // Add School column
        $new_columns = [];
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') { // Insert after Title
                $new_columns['school'] = __('School', 'headless-access-manager');
                $new_columns['classes'] = __('Class(es)', 'headless-access-manager');
            }
        }
        return $new_columns;
    }

    /**
     * Render content for custom columns.
     *
     * @param string $column_name The name of the column to render.
     * @param int    $post_id     The ID of the current post.
     */
    public static function render_custom_columns($column_name, $post_id) {
        switch ($column_name) {
            case 'school':
                $school_id = get_post_meta($post_id, '_ham_school_id', true);
                if ($school_id) {
                    $school_title = get_the_title($school_id);
                    echo esc_html($school_title);
                } else {
                    echo esc_html__('N/A', 'headless-access-manager');
                }
                break;

            case 'classes':
                // Original logic
                $class_ids = get_post_meta($post_id, '_ham_class_ids', true); 
                if (!empty($class_ids) && is_array($class_ids)) {
                    $class_names = array_map(function($class_id) {
                        return get_the_title($class_id);
                    }, $class_ids);
                    echo esc_html(implode(', ', $class_names));
                } else {
                    echo esc_html__('N/A', 'headless-access-manager');
                }
                break;
        }
    }

    /**
     * Add filter dropdowns to the list table.
     *
     * @param string $post_type The current post type.
     */
    public static function add_table_filters($post_type) {
        if (HAM_CPT_TEACHER !== $post_type) {
            return;
        }

        // School filter
        $schools = get_posts([
            'post_type' => HAM_CPT_SCHOOL,
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => 'publish'
        ]);

        $current_school_id = isset($_GET['ham_filter_school_id']) ? absint($_GET['ham_filter_school_id']) : 0;

        echo '<select name="ham_filter_school_id" class="ham-auto-filter">';
        echo '<option value="0">' . esc_html__('All Schools', 'headless-access-manager') . '</option>';
        foreach ($schools as $school) {
            printf('<option value="%s"%s>%s</option>',
                esc_attr($school->ID),
                selected($current_school_id, $school->ID, false),
                esc_html($school->post_title)
            );
        }
        echo '</select>';
    }

    /**
     * Modify the main query based on selected filters.
     *
     * @param WP_Query $query The WP_Query instance.
     */
    public static function filter_query($query) {
        global $pagenow;
        $post_type = isset($_GET['post_type']) ? $_GET['post_type'] : '';

        if (is_admin() && $pagenow == 'edit.php' && HAM_CPT_TEACHER === $post_type && $query->is_main_query()) {
            // Filter by school
            if (isset($_GET['ham_filter_school_id']) && absint($_GET['ham_filter_school_id']) > 0) {
                $school_id = absint($_GET['ham_filter_school_id']);
                $meta_query = $query->get('meta_query') ?: [];
                $meta_query[] = [
                    'key' => '_ham_school_id',
                    'value' => $school_id,
                    'compare' => '='
                ];
                $query->set('meta_query', $meta_query);
            }
        }
    }
}

HAM_Teacher_Admin_List_Table::init();

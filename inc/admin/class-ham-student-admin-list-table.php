<?php
/**
 * Handles admin list table customizations for the Student CPT.
 *
 * @package HeadlessAccessManager
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class HAM_Student_Admin_List_Table
 */
class HAM_Student_Admin_List_Table {

    /**
     * Constructor. Hooks into WordPress actions and filters.
     */
    public function __construct() {
        add_filter('manage_' . HAM_CPT_STUDENT . '_posts_columns', [$this, 'add_admin_columns']);
        add_action('manage_' . HAM_CPT_STUDENT . '_posts_custom_column', [$this, 'render_custom_columns'], 10, 2);
        add_action('restrict_manage_posts', [$this, 'add_table_filters']);
        add_filter('parse_query', [$this, 'filter_query']);
    }

    /**
     * Adds custom columns to the Student CPT list table.
     *
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public function add_admin_columns($columns) {
        // Add School column after title
        $new_columns = [];
        foreach ($columns as $key => $title) {
            $new_columns[$key] = $title;
            if ($key === 'title') {
                $new_columns['ham_school'] = __('School', 'headless-access-manager');
                // Ensure 'class' column (if it exists from CPT registration) is also present or add it
                if (!isset($columns['class'])) {
                     $new_columns['ham_class'] = __('Class', 'headless-access-manager');
                }
            }
        }
        // If 'class' was already there, make sure its title is translatable if we didn't add ham_class
        if (isset($new_columns['class']) && !isset($new_columns['ham_class'])) {
            $new_columns['class'] = __('ClassYY', 'headless-access-manager');
        }
         // If ham_class was added, and original 'class' existed, remove original 'class' to avoid duplicate semantic columns
        if (isset($new_columns['ham_class']) && isset($columns['class']) && $columns['class'] !== $new_columns['ham_class']) {
            unset($new_columns['class']);
        }

        return $new_columns;
    }

    /**
     * Renders content for custom columns.
     *
     * @param string $column_name The name of the column to render.
     * @param int    $post_id     The ID of the current post.
     */
    public function render_custom_columns($column_name, $post_id) {
        switch ($column_name) {
            case 'ham_school':
                $school_id = get_post_meta($post_id, '_ham_school_id', true);
                if ($school_id) {
                    echo esc_html(get_the_title($school_id));
                } else {
                    echo '&mdash;';
                }
                break;

            case 'class': // Handles the 'class' column if it was originally registered with CPT
            case 'ham_class': // Handles our added 'ham_class' column
                $student_classes = [];
                $all_classes_query = new WP_Query([
                    'post_type' => HAM_CPT_CLASS,
                    'posts_per_page' => -1,
                    'fields' => 'ids',
                ]);
                if ($all_classes_query->have_posts()) {
                    foreach ($all_classes_query->posts as $class_cpt_id) {
                        $students_in_class = get_post_meta($class_cpt_id, '_ham_student_ids', true);
                        $students_in_class = is_array($students_in_class) ? $students_in_class : [];
                        if (in_array($post_id, $students_in_class)) {
                            $student_classes[] = get_the_title($class_cpt_id);
                        }
                    }
                }
                if (!empty($student_classes)) {
                    echo esc_html(implode(', ', $student_classes));
                } else {
                    echo '&mdash;';
                }
                break;
        }
    }

    /**
     * Adds custom filters (dropdowns) to the list table.
     *
     * @param string $post_type The current post type.
     */
    public function add_table_filters($post_type) {
        if (HAM_CPT_STUDENT === $post_type) {
            // School Filter
            $schools = get_posts([
                'post_type' => HAM_CPT_SCHOOL,
                'posts_per_page' => -1,
                'orderby' => 'title',
                'order' => 'ASC',
                'post_status' => 'publish',
            ]);

            $current_school_filter = isset($_GET['ham_filter_school_id']) ? absint($_GET['ham_filter_school_id']) : 0;

            echo '<select name="ham_filter_school_id" class="ham-auto-filter">';
            echo '<option value="0">' . esc_html__('All Schools', 'headless-access-manager') . '</option>';
            if (!empty($schools)) {
                foreach ($schools as $school) {
                    printf(
                        '<option value="%d" %s>%s</option>',
                        esc_attr($school->ID),
                        selected($current_school_filter, $school->ID, false),
                        esc_html($school->post_title)
                    );
                }
            }
            echo '</select>';
        }
    }

    /**
     * Modifies the main query based on selected filters.
     *
     * @param WP_Query $query The WP_Query instance (passed by reference).
     */
    public function filter_query($query) {
        global $pagenow;
        // Ensure it's the main query, in the admin, for the Student CPT, and our filter is set.
        if ($query->is_main_query() && is_admin() && 'edit.php' === $pagenow && isset($_GET['post_type']) && HAM_CPT_STUDENT === $_GET['post_type'] && isset($_GET['ham_filter_school_id'])) {
            $school_id_filter = absint($_GET['ham_filter_school_id']);
            if ($school_id_filter > 0) {
                $meta_query = $query->get('meta_query') ?: [];
                $meta_query[] = [
                    'key' => '_ham_school_id',
                    'value' => $school_id_filter,
                    'compare' => '=',
                    'type' => 'NUMERIC',
                ];
                $query->set('meta_query', $meta_query);
            }
        }
    }
}

// Instantiate the class to hook everything up.
if (is_admin()) {
    new HAM_Student_Admin_List_Table();
}

<?php
/**
 * Handles meta boxes for the School CPT.
 *
 * @package HeadlessAccessManager
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class HAM_School_Meta_Boxes
 */
class HAM_School_Meta_Boxes {
    /**
     * Register meta boxes for the School CPT.
     */
    public static function register_meta_boxes() {
        // Allow anyone who can edit schools to see the meta boxes
        if (!current_user_can('edit_posts')) {
            return;
        }

        add_meta_box(
            'ham_school_principals',
            __('Assign Principals', 'headless-access-manager'),
            [__CLASS__, 'render_principal_assignment_meta_box'],
            HAM_CPT_SCHOOL, // Ensure HAM_CPT_SCHOOL constant is defined and correct
            'normal',
            'high'
        );

        // Meta box to display assigned teachers
        add_meta_box(
            'ham_school_assigned_teachers',
            __('Assigned Teachers', 'headless-access-manager'),
            [__CLASS__, 'render_assigned_teachers_display_meta_box'], // New render function
            HAM_CPT_SCHOOL,
            'normal', // Display in the main column
            'default'
        );
    }

    /**
     * Render the principal assignment meta box for schools.
     *
     * @param WP_Post $post Current post object.
     */
    public static function render_principal_assignment_meta_box($post) {
        wp_nonce_field('ham_school_principal_meta_box_nonce', 'ham_school_principal_meta_box_nonce');

        $assigned_principals = get_post_meta($post->ID, '_ham_principal_ids', true);
        $assigned_principals = is_array($assigned_principals) ? $assigned_principals : [];

        // Get users with the 'Principal' role
        $principals = get_users(['role__in' => [HAM_ROLE_PRINCIPAL]]); 
        ?>
        <p>
            <label for="ham_principal_ids"><?php esc_html_e('Select Principals for this School:', 'headless-access-manager'); ?></label>
            <select name="ham_principal_ids[]" id="ham_principal_ids" class="widefat" multiple="multiple" style="min-height: 90px;">
                <option value=""><?php esc_html_e('Select Principals', 'headless-access-manager'); ?></option>
                <?php foreach ($principals as $principal) : ?>
                    <option value="<?php echo esc_attr($principal->ID); ?>" <?php selected(in_array($principal->ID, $assigned_principals)); ?>>
                        <?php echo esc_html($principal->display_name); ?> (<?php echo esc_html($principal->user_email); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p class="description"><?php esc_html_e('Hold Ctrl (Windows) or Cmd (Mac) to select multiple Principals. These users will be associated with this school.', 'headless-access-manager'); ?></p>
        <?php
    }

    /**
     * Render the assigned teachers display meta box for schools.
     *
     * @param WP_Post $post Current post object (school).
     */
    public static function render_assigned_teachers_display_meta_box($post) {
        $school_id = $post->ID;

        $args = [
            'post_type' => HAM_CPT_TEACHER,
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_ham_school_id',
                    'value' => $school_id,
                    'compare' => '=',
                ],
            ],
            'orderby' => 'title',
            'order' => 'ASC',
        ];

        $teachers_query = new WP_Query($args);

        if ($teachers_query->have_posts()) {
            echo '<ul>';
            while ($teachers_query->have_posts()) {
                $teachers_query->the_post();
                echo '<li>' . esc_html(get_the_title()) . '</li>';
            }
            echo '</ul>';
            wp_reset_postdata();
        } else {
            echo '<p>' . esc_html__('No teachers are currently assigned to this school.', 'headless-access-manager') . '</p>';
        }
    }

    /**
     * Save meta box data for School CPT.
     *
     * @param int $post_id The ID of the post being saved.
     */
    public static function save_meta_boxes($post_id) {
        // Check if our nonce is set.
        if (!isset($_POST['ham_school_principal_meta_box_nonce'])) {
            return;
        }
        // Verify that the nonce is valid.
        if (!wp_verify_nonce($_POST['ham_school_principal_meta_box_nonce'], 'ham_school_principal_meta_box_nonce')) {
            return;
        }
        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        // Check the user's permissions.
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        // Check if it's the correct post type.
        if (HAM_CPT_SCHOOL !== get_post_type($post_id)) {
            return;
        }

        $old_principal_ids = get_post_meta($post_id, '_ham_principal_ids', true);
        $old_principal_ids = is_array($old_principal_ids) ? $old_principal_ids : [];

        $new_principal_ids = [];
        if (isset($_POST['ham_principal_ids']) && is_array($_POST['ham_principal_ids'])) {
            $new_principal_ids = array_map('absint', $_POST['ham_principal_ids']);
            update_post_meta($post_id, '_ham_principal_ids', $new_principal_ids);
        } else {
            delete_post_meta($post_id, '_ham_principal_ids');
        }

        // Update _ham_school_id for principals
        // Principals removed from this school
        $removed_principals = array_diff($old_principal_ids, $new_principal_ids);
        foreach ($removed_principals as $principal_id) {
            $current_principal_school = get_user_meta($principal_id, HAM_USER_META_SCHOOL_ID, true);
            if ((int) $current_principal_school === $post_id) {
                delete_user_meta($principal_id, HAM_USER_META_SCHOOL_ID);
            }
        }

        // Principals added to this school
        $added_principals = array_diff($new_principal_ids, $old_principal_ids);
        foreach ($added_principals as $principal_id) {
            update_user_meta($principal_id, HAM_USER_META_SCHOOL_ID, $post_id);
        }
    }

    /**
     * Add custom columns to the School CPT list table.
     *
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public static function add_custom_admin_columns($columns) {
        // Ensure this column is added after 'title' and before 'date' for better UX
        $new_columns = [];
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['ham_assigned_principals'] = __('Assigned Principals', 'headless-access-manager');
            }
        }
        // If 'title' wasn't found, add it at the end (less ideal)
        if (!isset($new_columns['ham_assigned_principals'])) {
             $new_columns['ham_assigned_principals'] = __('Assigned Principals', 'headless-access-manager');
        }
        return $new_columns;
    }

    /**
     * Render content for custom columns in the School CPT list table.
     *
     * @param string $column  The name of the custom column.
     * @param int    $post_id The ID of the current School CPT post.
     */
    public static function render_custom_admin_columns($column, $post_id) {
        if ($column === 'ham_assigned_principals') {
            $principal_ids = get_post_meta($post_id, '_ham_principal_ids', true);
            $principal_ids = is_array($principal_ids) ? $principal_ids : [];

            if (empty($principal_ids)) {
                echo '—';
                return;
            }

            $links = [];
            foreach ($principal_ids as $user_id) {
                $user = get_userdata($user_id);
                if ($user) {
                    // Optional: Link to the user's edit page
                    // $user_edit_link = get_edit_user_link($user_id);
                    // $links[] = $user_edit_link ? '<a href="' . esc_url($user_edit_link) . '">' . esc_html($user->display_name) . '</a>' : esc_html($user->display_name);
                    $links[] = esc_html($user->display_name);
                }
            }

            if (empty($links)) {
                echo '—';
            } else {
                echo implode(', ', $links);
            }
        }
    }
}

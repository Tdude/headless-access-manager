<?php
/**
 * Handles meta boxes and admin columns for the Principal CPT.
 *
 * @package HeadlessAccessManager
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class HAM_Principal_Meta_Boxes
 */
class HAM_Principal_Meta_Boxes {
    /**
     * Initialize hooks for the Principal CPT admin columns and sorting.
     */
    public static function init() {
        // Make columns sortable
        add_filter('manage_edit-' . HAM_CPT_PRINCIPAL . '_sortable_columns', [__CLASS__, 'make_columns_sortable']);
        
        // Handle sorting
        add_action('pre_get_posts', [__CLASS__, 'handle_admin_sorting']);
    }
    
    /**
     * Makes columns sortable in the admin list table.
     *
     * @param array $columns The sortable columns.
     * @return array Modified sortable columns.
     */
    public static function make_columns_sortable($columns) {
        $columns['ham_assigned_schools'] = 'assigned_schools';
        return $columns;
    }
    
    /**
     * Handle sorting in the admin list table.
     *
     * @param WP_Query $query The current query object.
     */
    public static function handle_admin_sorting($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        
        $orderby = $query->get('orderby');
        
        if ('assigned_schools' === $orderby) {
            // Add a filter to sort the results after the query
            add_filter('the_posts', [__CLASS__, 'sort_principals_by_schools'], 10, 2);
        }
    }
    
    /**
     * Sort principals by their assigned schools' names.
     * This is a manual PHP sort that runs after the query.
     *
     * @param array    $posts Array of post objects.
     * @param WP_Query $query The current WP_Query object.
     * @return array Sorted posts.
     */
    public static function sort_principals_by_schools($posts, $query) {
        if (!is_admin() || !isset($query->query['orderby']) || $query->query['orderby'] !== 'assigned_schools') {
            return $posts;
        }
        
        // Remove the filter to avoid infinite recursion
        remove_filter('the_posts', [__CLASS__, 'sort_principals_by_schools']);
        
        // Get school names for each principal
        $principal_schools = [];
        foreach ($posts as $post) {
            $linked_user_id = get_post_meta($post->ID, '_ham_user_id', true);
            $school_names = [];
            
            if (!empty($linked_user_id)) {
                // Query schools that have this principal (user) assigned
                $args = [
                    'post_type' => HAM_CPT_SCHOOL,
                    'posts_per_page' => -1,
                    'meta_query' => [
                        [
                            'key' => '_ham_principal_ids',
                            'value' => 'i:' . $linked_user_id . ';',
                            'compare' => 'LIKE',
                        ]
                    ]
                ];
                $schools_query = new WP_Query($args);
                
                if ($schools_query->have_posts()) {
                    while ($schools_query->have_posts()) {
                        $schools_query->the_post();
                        $school_names[] = get_the_title();
                    }
                    wp_reset_postdata();
                }
                
                // Fallback: Check user's own _ham_school_id meta
                if (empty($school_names)) {
                    $single_school_id = get_user_meta($linked_user_id, HAM_USER_META_SCHOOL_ID, true);
                    if ($single_school_id) {
                        $school_post = get_post($single_school_id);
                        if ($school_post) {
                            $school_names[] = $school_post->post_title;
                        }
                    }
                }
            }
            
            sort($school_names); // Sort school names alphabetically
            $principal_schools[$post->ID] = implode(', ', $school_names);
        }
        
        // Sort principals by school names
        usort($posts, function($a, $b) use ($principal_schools, $query) {
            $a_schools = isset($principal_schools[$a->ID]) ? $principal_schools[$a->ID] : '';
            $b_schools = isset($principal_schools[$b->ID]) ? $principal_schools[$b->ID] : '';
            
            if ($query->get('order') === 'DESC') {
                return strcasecmp($b_schools, $a_schools);
            } else {
                return strcasecmp($a_schools, $b_schools);
            }
        });
        
        return $posts;
    }
    /**
     * Add custom columns to the Principal CPT list table.
     *
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public static function add_principal_custom_columns($columns) {
        $columns['ham_assigned_schools'] = __('Assigned Schools', 'headless-access-manager');
        // Add other columns for Principal CPT if needed
        return $columns;
    }

    /**
     * Render content for custom columns in the Principal CPT list table.
     *
     * @param string $column  The name of the custom column.
     * @param int    $post_id The ID of the current Principal CPT post.
     */
    public static function render_principal_custom_columns($column, $post_id) {
        if ($column === 'ham_assigned_schools') {
            $linked_user_id = get_post_meta($post_id, '_ham_user_id', true);

            if (empty($linked_user_id)) {
                echo '—';
                return;
            }

            $user = get_userdata($linked_user_id);
            if (!$user || !in_array(HAM_ROLE_PRINCIPAL, $user->roles)) {
                echo __('User not found or not a principal.', 'headless-access-manager');
                return;
            }

            // Query schools that have this principal (user) assigned.
            $args = [
                'post_type' => HAM_CPT_SCHOOL,
                'posts_per_page' => -1,
                'meta_query' => [
                    [
                        'key' => '_ham_principal_ids', // Array of user IDs on the school post
                        'value' => 'i:' . $linked_user_id . ';', // Search for the integer user ID in the serialized array
                        'compare' => 'LIKE',
                    ]
                ]
            ];
            $schools_query = new WP_Query($args);
            $school_links = [];

            if ($schools_query->have_posts()) {
                while ($schools_query->have_posts()) {
                    $schools_query->the_post();
                    $school_post_id = get_the_ID();
                    $edit_link = get_edit_post_link($school_post_id);
                    $school_links[] = $edit_link ? '<a href="' . esc_url($edit_link) . '">' . esc_html(get_the_title()) . '</a>' : esc_html(get_the_title());
                }
                wp_reset_postdata();
            }

            // Fallback: Check the user's own _ham_school_id meta if the above yields nothing
            // This caters to the single school assignment stored on the user meta.
            if (empty($school_links)) {
                $single_school_id = get_user_meta($linked_user_id, HAM_USER_META_SCHOOL_ID, true);
                if ($single_school_id) {
                    $school_post = get_post($single_school_id);
                    if ($school_post) {
                        $edit_link = get_edit_post_link($single_school_id);
                        $school_links[] = $edit_link ? '<a href="' . esc_url($edit_link) . '">' . esc_html($school_post->post_title) . '</a>' : esc_html($school_post->post_title);
                    }
                }
            }

            if (!empty($school_links)) {
                echo implode(', ', $school_links);
            } else {
                echo '—';
            }
        }
        // Handle other custom columns for Principal CPT if any
    }

    /**
     * Register meta boxes for the Principal CPT.
     *
     * @param WP_Post $post The current post object.
     */
    public static function register_meta_boxes(WP_Post $post) {
        add_meta_box(
            'ham_principal_user_link',
            __('Link to User Account', 'headless-access-manager'),
            [__CLASS__, 'render_user_link_meta_box'],
            HAM_CPT_PRINCIPAL,
            'normal',
            'high'
        );

        // Meta box to assign schools
        add_meta_box(
            'ham_principal_assigned_schools',
            __('Assign Schools', 'headless-access-manager'),
            [__CLASS__, 'render_school_assignment_meta_box'],
            HAM_CPT_PRINCIPAL,
            'normal',
            'high'
        );
    }

    /**
     * Render the meta box for linking a Principal CPT to a user.
     *
     * @param WP_Post $post The current post object.
     */
    public static function render_user_link_meta_box(WP_Post $post) {
        wp_nonce_field('ham_principal_user_link_save', 'ham_principal_user_link_nonce');
        $linked_user_id = get_post_meta($post->ID, '_ham_user_id', true);

        $users_query = new WP_User_Query([
            'role' => HAM_ROLE_PRINCIPAL,
            'orderby' => 'display_name',
            'order' => 'ASC',
            'fields' => ['ID', 'display_name'],
        ]);
        $principals = $users_query->get_results();

        echo '<label for="ham_principal_user_id">' . esc_html__('Select User:', 'headless-access-manager') . '</label>';
        echo '<select name="ham_principal_user_id" id="ham_principal_user_id" class="widefat">';
        echo '<option value="">' . esc_html__('-- Select a User --', 'headless-access-manager') . '</option>';

        if (!empty($principals)) {
            foreach ($principals as $user) {
                printf(
                    '<option value="%d" %s>%s</option>',
                    esc_attr($user->ID),
                    selected($linked_user_id, $user->ID, false),
                    esc_html($user->display_name)
                );
            }
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('Select the WordPress user account for this principal.', 'headless-access-manager') . '</p>';
    }

    /**
     * Render the meta box to assign schools to this principal.
     *
     * @param WP_Post $post The current Principal CPT post object.
     */
    public static function render_school_assignment_meta_box(WP_Post $post) {
        wp_nonce_field('ham_principal_schools_save', 'ham_principal_schools_nonce');
        
        $linked_user_id = get_post_meta($post->ID, '_ham_user_id', true);

        if (empty($linked_user_id)) {
            echo '<p>' . esc_html__('Please link a user account first before assigning schools.', 'headless-access-manager') . '</p>';
            return;
        }

        // Get all schools
        $all_schools = get_posts([
            'post_type' => HAM_CPT_SCHOOL,
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC'
        ]);

        // Get schools where this principal is assigned
        $assigned_school_ids = [];
        foreach ($all_schools as $school) {
            $school_principal_ids = get_post_meta($school->ID, '_ham_principal_ids', true);
            if (is_array($school_principal_ids) && in_array($linked_user_id, $school_principal_ids)) {
                $assigned_school_ids[] = $school->ID;
            }
        }

        ?>
        <p>
            <label for="ham_principal_school_ids"><?php esc_html_e('Select Schools for this Principal:', 'headless-access-manager'); ?></label>
            <select name="ham_principal_school_ids[]" id="ham_principal_school_ids" class="widefat" multiple="multiple" style="min-height: 120px;">
                <?php foreach ($all_schools as $school) : ?>
                    <option value="<?php echo esc_attr($school->ID); ?>" <?php selected(in_array($school->ID, $assigned_school_ids)); ?>>
                        <?php echo esc_html($school->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p class="description"><?php esc_html_e('Hold Ctrl (Windows) or Cmd (Mac) to select multiple Schools.', 'headless-access-manager'); ?></p>
        <?php
    }

    /**
     * Render the meta box to display schools assigned to this principal (via their linked user account).
     * DEPRECATED - keeping for reference
     *
     * @param WP_Post $post The current Principal CPT post object.
     */
    public static function render_assigned_schools_display_meta_box(WP_Post $post) {
        $linked_user_id = get_post_meta($post->ID, '_ham_user_id', true);

        if (empty($linked_user_id)) {
            echo '<p>' . esc_html__('No user account is linked to this principal entry.', 'headless-access-manager') . '</p>';
            return;
        }

        $user = get_userdata($linked_user_id);
        if (!$user || !in_array(HAM_ROLE_PRINCIPAL, $user->roles)) {
            echo '<p>' . esc_html__('Linked user not found or is not a principal.', 'headless-access-manager') . '</p>';
            return;
        }

        echo '<p><strong>' . esc_html($user->display_name) . '</strong> (' . esc_html($user->user_email) . ')' . '</p>';

        // Query schools that have this principal (user) assigned.
        $args = [
            'post_type' => HAM_CPT_SCHOOL,
            'posts_per_page' => -1, // Get all schools
            'meta_query' => [
                [
                    'key' => '_ham_principal_ids', // This is an array of user IDs on the School CPT post meta
                    'value' => 'i:' . $linked_user_id . ';', // Search for the integer user ID in the serialized array
                    'compare' => 'LIKE',
                ],
            ],
            'orderby' => 'title',
            'order' => 'ASC',
        ];

        $schools_query = new WP_Query($args);
        $assigned_schools = [];

        if ($schools_query->have_posts()) {
            while ($schools_query->have_posts()) {
                $schools_query->the_post();
                $assigned_schools[get_the_ID()] = get_the_title();
            }
            wp_reset_postdata();
        }

        // Fallback or alternative: Check the principal's user meta HAM_USER_META_SCHOOL_ID
        // This reflects the direct assignment made from the School CPT's save routine to the user.
        $direct_school_id = get_user_meta($linked_user_id, HAM_USER_META_SCHOOL_ID, true);
        if ($direct_school_id && !isset($assigned_schools[$direct_school_id])) {
            $school_post = get_post($direct_school_id);
            if ($school_post) {
                $assigned_schools[$direct_school_id] = $school_post->post_title;
            }
        }

        if (!empty($assigned_schools)) {
            echo '<ul>';
            foreach ($assigned_schools as $school_id => $school_title) {
                $edit_link = get_edit_post_link($school_id);
                if ($edit_link) {
                    echo '<li><a href="' . esc_url($edit_link) . '">' . esc_html($school_title) . '</a></li>';
                } else {
                    echo '<li>' . esc_html($school_title) . '</li>';
                }
            }
            echo '</ul>';
        } else {
            echo '<p>' . esc_html__('This principal is not currently assigned to any schools.', 'headless-access-manager') . '</p>';
        }
        echo '<p class="description">' . esc_html__('School assignments are managed from the School edit screen.', 'headless-access-manager') . '</p>';
    }

    /**
     * Save meta box data for the Principal CPT.
     *
     * @param int $post_id The ID of the post being saved.
     */
    public static function save_meta_boxes(int $post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        if (get_post_type($post_id) !== HAM_CPT_PRINCIPAL) {
            return;
        }

        // Save user link
        if (isset($_POST['ham_principal_user_link_nonce']) && wp_verify_nonce($_POST['ham_principal_user_link_nonce'], 'ham_principal_user_link_save')) {
            if (isset($_POST['ham_principal_user_id'])) {
                $user_id = sanitize_text_field($_POST['ham_principal_user_id']);
                if (!empty($user_id)) {
                    update_post_meta($post_id, '_ham_user_id', absint($user_id));
                } else {
                    delete_post_meta($post_id, '_ham_user_id');
                }
            }
        }

        // Save school assignments
        if (isset($_POST['ham_principal_schools_nonce']) && wp_verify_nonce($_POST['ham_principal_schools_nonce'], 'ham_principal_schools_save')) {
            $linked_user_id = get_post_meta($post_id, '_ham_user_id', true);
            
            if (!empty($linked_user_id)) {
                $new_school_ids = isset($_POST['ham_principal_school_ids']) && is_array($_POST['ham_principal_school_ids']) 
                    ? array_map('absint', $_POST['ham_principal_school_ids']) 
                    : [];

                // Get all schools to update their principal lists
                $all_schools = get_posts([
                    'post_type' => HAM_CPT_SCHOOL,
                    'posts_per_page' => -1,
                    'post_status' => 'any'
                ]);

                foreach ($all_schools as $school) {
                    $school_principal_ids = get_post_meta($school->ID, '_ham_principal_ids', true);
                    $school_principal_ids = is_array($school_principal_ids) ? $school_principal_ids : [];
                    
                    $is_currently_assigned = in_array($linked_user_id, $school_principal_ids);
                    $should_be_assigned = in_array($school->ID, $new_school_ids);

                    if ($should_be_assigned && !$is_currently_assigned) {
                        // Add principal to school
                        $school_principal_ids[] = $linked_user_id;
                        update_post_meta($school->ID, '_ham_principal_ids', array_unique($school_principal_ids));
                    } elseif (!$should_be_assigned && $is_currently_assigned) {
                        // Remove principal from school
                        $school_principal_ids = array_diff($school_principal_ids, [$linked_user_id]);
                        if (empty($school_principal_ids)) {
                            delete_post_meta($school->ID, '_ham_principal_ids');
                        } else {
                            update_post_meta($school->ID, '_ham_principal_ids', array_values($school_principal_ids));
                        }
                    }
                }
            }
        }
    }
}

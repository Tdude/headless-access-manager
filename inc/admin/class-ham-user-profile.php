<?php
/**
 * File: inc/admin/class-ham-user-profile.php
 *
 * Handles user profile fields for HAM user data.
 *
 * @package HeadlessAccessManager
 */

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class HAM_User_Profile
 *
 * Handles user profile fields for HAM user data.
 */
class HAM_User_Profile
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        // Add user profile fields
        add_action('show_user_profile', array( $this, 'add_user_profile_fields' ));
        add_action('edit_user_profile', array( $this, 'add_user_profile_fields' ));

        // Save user profile fields
        add_action('personal_options_update', array( $this, 'save_user_profile_fields' ));
        add_action('edit_user_profile_update', array( $this, 'save_user_profile_fields' ));

        // Add filter to display HAM role in user list
        add_filter('manage_users_columns', array( $this, 'modify_user_table' ));
        add_filter('manage_users_custom_column', array( $this, 'modify_user_table_row' ), 10, 3);

        // Make HAM columns sortable
        add_filter('manage_users_sortable_columns', array( $this, 'make_ham_columns_sortable' ));
        add_action('pre_get_users', array( $this, 'sort_users_by_ham_columns' ));
    }

    /**
     * Make HAM columns sortable.
     *
     * @param array $columns The sortable columns.
     * @return array Modified sortable columns.
     */
    public function make_ham_columns_sortable($columns)
    {
        $columns['ham_role'] = 'ham_role';
        $columns['ham_school'] = 'ham_school';
        return $columns;
    }

    /**
     * Sort users by HAM columns.
     *
     * @param WP_User_Query $query The user query.
     */
    public function sort_users_by_ham_columns($query)
    {
        // Only apply in admin user list
        if (! is_admin() || ! function_exists('get_current_screen')) {
            return;
        }

        $screen = get_current_screen();
        if (! $screen || $screen->id !== 'users') {
            return;
        }

        // Check if we're sorting by one of our columns
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : '';

        if ($orderby === 'ham_role') {
            // Sort by HAM role
            // This requires using meta_query to find users with HAM roles
            $ham_roles = HAM_Roles::get_all_roles();

            // First, get users with HAM roles ordered by role weight
            $role_weights = array(
                HAM_ROLE_SCHOOL_HEAD => 1,
                HAM_ROLE_PRINCIPAL => 2,
                HAM_ROLE_TEACHER => 3,
                HAM_ROLE_STUDENT => 4,
            );

            // Create a meta_query to sort by role
            $meta_query = array( 'relation' => 'OR' );

            foreach ($ham_roles as $role) {
                $meta_query[] = array(
                    'key' => $wpdb->prefix . 'capabilities',
                    'value' => sprintf('"%s"', $role),
                    'compare' => 'LIKE',
                );
            }

            $query->set('meta_query', $meta_query);

            // Add meta_key and orderby params
            // Note: This is a simplified approach and might not produce perfect sorting
            // For perfect sorting, a custom SQL query would be needed
            $query->set('meta_key', $wpdb->prefix . 'capabilities');

            // Set the order
            $order = isset($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : 'ASC';
            $query->set('order', $order);
        } elseif ($orderby === 'ham_school') {
            // Sort by HAM school
            $query->set('meta_key', HAM_USER_META_SCHOOL_ID);

            // Set the order
            $order = isset($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : 'ASC';
            $query->set('order', $order);

            // For school heads, this won't perfectly sort by school name
            // since they can have multiple schools
        }
    }

    /**
     * Add custom fields to user profile.
     *
     * @param WP_User $user User object.
     */
    public function add_user_profile_fields($user)
    {
        // Check if user has HAM role
        if (! HAM_Roles::has_ham_role($user)) {
            return;
        }

        // Get current values
        $school_id = get_user_meta($user->ID, HAM_USER_META_SCHOOL_ID, true);
        $class_ids = get_user_meta($user->ID, HAM_USER_META_CLASS_IDS, true);
        $managed_school_ids = get_user_meta($user->ID, HAM_USER_META_MANAGED_SCHOOL_IDS, true);

        // Get schools and classes
        $schools = ham_get_schools();
        $classes = ham_get_classes();

        // Determine which fields to show based on role
        $show_school = in_array($user->roles[0], array( HAM_ROLE_STUDENT, HAM_ROLE_TEACHER, HAM_ROLE_PRINCIPAL ));
        $show_classes = in_array($user->roles[0], array( HAM_ROLE_STUDENT, HAM_ROLE_TEACHER ));
        $show_managed_schools = ($user->roles[0] === HAM_ROLE_SCHOOL_HEAD);

        ?>
<h2><?php echo esc_html__('Headless Access Manager', 'headless-access-manager'); ?></h2>
<table class="form-table">
    <?php if ($show_school) : ?>
    <tr>
        <th><label for="ham_school_id"><?php echo esc_html__('School', 'headless-access-manager'); ?></label></th>
        <td>
            <select name="ham_school_id" id="ham_school_id">
                <option value=""><?php echo esc_html__('— Select School —', 'headless-access-manager'); ?></option>
                <?php foreach ($schools as $school) : ?>
                <option value="<?php echo esc_attr($school->ID); ?>" <?php selected($school_id, $school->ID); ?>>
                    <?php echo esc_html($school->post_title); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </td>
    </tr>
    <?php endif; ?>

    <?php if ($show_classes) : ?>
    <tr>
        <th><label for="ham_class_ids"><?php echo esc_html__('Classes', 'headless-access-manager'); ?></label></th>
        <td>
            <select name="ham_class_ids[]" id="ham_class_ids" multiple style="min-width: 300px; height: 150px;">
                <?php foreach ($classes as $class) : ?>
                <?php
                                $selected = false;
                    if (is_array($class_ids) && in_array($class->ID, $class_ids)) {
                        $selected = true;
                    }

                    // Get school name for display
                    $class_school_id = get_post_meta($class->ID, '_ham_school_id', true);
                    $class_school = get_post($class_school_id);
                    $school_name = $class_school ? $class_school->post_title : __('No School', 'headless-access-manager');
                    ?>
                <option value="<?php echo esc_attr($class->ID); ?>" <?php selected($selected); ?>>
                    <?php echo esc_html($class->post_title); ?> (<?php echo esc_html($school_name); ?>)
                </option>
                <?php endforeach; ?>
            </select>
            <p class="description">
                <?php echo esc_html__('Hold Ctrl/Cmd key to select multiple classes', 'headless-access-manager'); ?>
            </p>
        </td>
    </tr>
    <?php endif; ?>

    <?php if ($show_managed_schools) : ?>
    <tr>
        <th><label
                for="ham_managed_school_ids"><?php echo esc_html__('Managed Schools', 'headless-access-manager'); ?></label>
        </th>
        <td>
            <select name="ham_managed_school_ids[]" id="ham_managed_school_ids" multiple
                style="min-width: 300px; height: 150px;">
                <?php foreach ($schools as $school) : ?>
                <?php
                    $selected = false;
                    if (is_array($managed_school_ids) && in_array($school->ID, $managed_school_ids)) {
                        $selected = true;
                    }
                    ?>
                <option value="<?php echo esc_attr($school->ID); ?>" <?php selected($selected); ?>>
                    <?php echo esc_html($school->post_title); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <p class="description">
                <?php echo esc_html__('Hold Ctrl/Cmd key to select multiple schools', 'headless-access-manager'); ?>
            </p>
        </td>
    </tr>
    <?php endif; ?>
</table>
<?php
    }

    /**
     * Save custom user profile fields.
     *
     * @param int $user_id User ID.
     * @return bool True on success, false on failure.
     */
    public function save_user_profile_fields($user_id)
    {
        // Check if current user can edit this user
        if (! current_user_can('edit_user', $user_id)) {
            return false;
        }

        // Get user
        $user = get_user_by('id', $user_id);

        // Check if user has HAM role
        if (! HAM_Roles::has_ham_role($user)) {
            return false;
        }

        // Update school ID
        if (isset($_POST['ham_school_id'])) {
            $school_id = absint($_POST['ham_school_id']);

            if ($school_id > 0) {
                update_user_meta($user_id, HAM_USER_META_SCHOOL_ID, $school_id);
            } else {
                delete_user_meta($user_id, HAM_USER_META_SCHOOL_ID);
            }
        }

        // Update class IDs
        if (isset($_POST['ham_class_ids']) && is_array($_POST['ham_class_ids'])) {
            $class_ids = array_map('absint', $_POST['ham_class_ids']);
            $class_ids = array_filter($class_ids);

            if (! empty($class_ids)) {
                update_user_meta($user_id, HAM_USER_META_CLASS_IDS, $class_ids);
            } else {
                delete_user_meta($user_id, HAM_USER_META_CLASS_IDS);
            }
        } else {
            delete_user_meta($user_id, HAM_USER_META_CLASS_IDS);
        }

        // Update managed school IDs
        if (isset($_POST['ham_managed_school_ids']) && is_array($_POST['ham_managed_school_ids'])) {
            $school_ids = array_map('absint', $_POST['ham_managed_school_ids']);
            $school_ids = array_filter($school_ids);

            if (! empty($school_ids)) {
                update_user_meta($user_id, HAM_USER_META_MANAGED_SCHOOL_IDS, $school_ids);
            } else {
                delete_user_meta($user_id, HAM_USER_META_MANAGED_SCHOOL_IDS);
            }
        } else {
            delete_user_meta($user_id, HAM_USER_META_MANAGED_SCHOOL_IDS);
        }

        return true;
    }

    /**
     * Modify user table to add HAM specific columns.
     *
     * @param array $columns User table columns.
     * @return array Modified columns.
     */
    public function modify_user_table($columns)
    {
        $columns['ham_role'] = __('HAM Role', 'headless-access-manager');
        $columns['ham_school'] = __('School', 'headless-access-manager');

        return $columns;
    }

    /**
     * Modify user table row to display HAM specific data.
     *
     * @param string $output      Custom column output.
     * @param string $column_name Column name.
     * @param int    $user_id     User ID.
     * @return string Modified output.
     */
    public function modify_user_table_row($output, $column_name, $user_id)
    {
        switch ($column_name) {
            case 'ham_role':
                $ham_role = HAM_Roles::get_ham_role($user_id);

                if ($ham_role) {
                    switch ($ham_role) {
                        case HAM_ROLE_STUDENT:
                            return __('Student', 'headless-access-manager');
                        case HAM_ROLE_TEACHER:
                            return __('Teacher', 'headless-access-manager');
                        case HAM_ROLE_PRINCIPAL:
                            return __('Principal', 'headless-access-manager');
                        case HAM_ROLE_SCHOOL_HEAD:
                            return __('School Head', 'headless-access-manager');
                        default:
                            return ucfirst(str_replace('ham_', '', $ham_role));
                    }
                }
                break;

            case 'ham_school':
                $ham_role = HAM_Roles::get_ham_role($user_id);

                if ($ham_role) {
                    switch ($ham_role) {
                        case HAM_ROLE_STUDENT:
                        case HAM_ROLE_TEACHER:
                        case HAM_ROLE_PRINCIPAL:
                            $school_id = get_user_meta($user_id, HAM_USER_META_SCHOOL_ID, true);

                            if ($school_id) {
                                $school = get_post($school_id);

                                if ($school) {
                                    return $school->post_title;
                                }
                            }
                            break;

                        case HAM_ROLE_SCHOOL_HEAD:
                            $school_ids = get_user_meta($user_id, HAM_USER_META_MANAGED_SCHOOL_IDS, true);

                            if (is_array($school_ids) && ! empty($school_ids)) {
                                $schools = array();

                                foreach ($school_ids as $school_id) {
                                    $school = get_post($school_id);

                                    if ($school) {
                                        $schools[] = $school->post_title;
                                    }
                                }

                                if (! empty($schools)) {
                                    return implode(', ', $schools);
                                }
                            }
                            break;
                    }
                }
                break;
        }

        return $output;
    }
}

// Initialize the user profile class
new HAM_User_Profile();

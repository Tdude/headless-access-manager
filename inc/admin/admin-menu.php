<?php
/**
 * File: inc/admin/admin-menu.php
 *
 * Creates and manages admin menus.
 *
 * @package HeadlessAccessManager
 */

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class HAM_Admin_Menu
 *
 * Creates and manages admin menus.
 */
class HAM_Admin_Menu
{
    /**
     * Setup admin menus.
     */
    public static function setup_menu()
    {
        // Main menu
        add_menu_page(
            __('Tryggve App', 'headless-access-manager'),
            __('Tryggve App', 'headless-access-manager'),
            'manage_options',
            'headless-access-manager',
            array( __CLASS__, 'render_dashboard_page' ),
            'dashicons-lock',
            30
        );

        // Overview submenu
        add_submenu_page(
            'headless-access-manager',
            __('Overview', 'headless-access-manager'), // Page title
            __('Overview', 'headless-access-manager'), // Menu title
            'manage_options',
            'headless-access-manager',
            array( __CLASS__, 'render_dashboard_page' )
        );

        // Evaluations submenu
        add_submenu_page(
            'headless-access-manager',
            __('Evaluations', 'headless-access-manager'), // Page title
            __('Evaluations', 'headless-access-manager'), // Menu title
            'manage_options',
            'ham-assessments',
            array( 'HAM_Assessment_Manager', 'render_assessments_page' )
        );

        // Question Bank submenu
        add_submenu_page(
            'headless-access-manager',
            __('Question Bank', 'headless-access-manager'), // Page title
            __('Question Bank', 'headless-access-manager'), // Menu title
            'manage_options',
            'edit.php?post_type=' . HAM_CPT_ASSESSMENT_TPL
        );

        // Statistics submenu
        add_submenu_page(
            'headless-access-manager',
            __('Statistics', 'headless-access-manager'), // Page title
            __('Statistics', 'headless-access-manager'), // Menu title
            'manage_options',
            'ham-assessment-stats',
            array( 'HAM_Assessment_Manager', 'render_statistics_page' )
        );

        // Settings submenu
        add_submenu_page(
            'headless-access-manager',
            __('Settings', 'headless-access-manager'), // Page title
            __('Settings', 'headless-access-manager'), // Menu title
            'manage_options',
            'ham-settings',
            array( __CLASS__, 'render_settings_page' )
        );
        // Register settings
        add_action('admin_init', array( __CLASS__, 'register_settings' ));
    }

    /**
     * Render dashboard page.
     */
    public static function render_dashboard_page()
    {
        // Get statistics
        $schools_count = wp_count_posts(HAM_CPT_SCHOOL)->publish;
        $classes_count = wp_count_posts(HAM_CPT_CLASS)->publish;
        $assessments_count = wp_count_posts(HAM_CPT_ASSESSMENT)->publish;

        $teachers_count = count(get_users(array( 'role' => HAM_ROLE_TEACHER )));
        $students_count = count(get_users(array( 'role' => HAM_ROLE_STUDENT )));
        $principals_count = count(get_users(array( 'role' => HAM_ROLE_PRINCIPAL )));
        $school_heads_count = count(get_users(array( 'role' => HAM_ROLE_SCHOOL_HEAD )));

        ?>
<div class="wrap">
        <h1><?php echo esc_html__('Handle Access Dashboard', 'headless-access-manager'); ?></h1>

    <div class="ham-dashboard-stats">
        <h2><?php echo esc_html__('System Overview', 'headless-access-manager'); ?></h2>

        <div class="ham-dashboard-stat-boxes">
            <div class="ham-stat-box">
                <h3><?php echo esc_html__('Evaluations', 'headless-access-manager'); ?></h3>
                <div class="ham-stat-value"><?php echo esc_html($assessments_count); ?></div>
            </div>

            <div class="ham-stat-box">
                <h3><?php echo esc_html__('Students', 'headless-access-manager'); ?></h3>
                <div class="ham-stat-value"><?php echo esc_html($students_count); ?></div>
            </div>

            <div class="ham-stat-box">
                <h3><?php echo esc_html__('Teachers', 'headless-access-manager'); ?></h3>
                <div class="ham-stat-value"><?php echo esc_html($teachers_count); ?></div>
            </div>

            <div class="ham-stat-box">
                <h3><?php echo esc_html__('Classes', 'headless-access-manager'); ?></h3>
                <div class="ham-stat-value"><?php echo esc_html($classes_count); ?></div>
            </div>

            <div class="ham-stat-box">
                <h3><?php echo esc_html__('Schools', 'headless-access-manager'); ?></h3>
                <div class="ham-stat-value"><?php echo esc_html($schools_count); ?></div>
            </div>

            <div class="ham-stat-box">
                <h3><?php echo esc_html__('Principals', 'headless-access-manager'); ?></h3>
                <div class="ham-stat-value"><?php echo esc_html($principals_count); ?></div>
            </div>

            <div class="ham-stat-box">
                <h3><?php echo esc_html__('School Heads', 'headless-access-manager'); ?></h3>
                <div class="ham-stat-value"><?php echo esc_html($school_heads_count); ?></div>
            </div>

        </div>
    </div>

    <div class="ham-dashboard-quick-links">
        <h2><?php echo esc_html__('Quick Links', 'headless-access-manager'); ?></h2>

        <div class="ham-quick-links">
            <a href="<?php echo esc_url(admin_url('admin.php?page=ham-assessments')); ?>"
                class="button button-primary">
                <?php echo esc_html__('View Evaluations', 'headless-access-manager'); ?>
            </a>

            <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . HAM_CPT_STUDENT)); ?>"
                class="button button-primary">
                <?php echo esc_html__('Manage Students', 'headless-access-manager'); ?>
            </a>

            <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . HAM_CPT_TEACHER)); ?>"
                class="button button-primary">
                <?php echo esc_html__('Manage Teachers', 'headless-access-manager'); ?>
            </a>    

            <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . HAM_CPT_CLASS)); ?>"
                class="button button-primary">
                <?php echo esc_html__('Manage Classes', 'headless-access-manager'); ?>
            </a>

            <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . HAM_CPT_SCHOOL)); ?>"
                class="button button-primary">
                <?php echo esc_html__('Manage Schools', 'headless-access-manager'); ?>
            </a>

            <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . HAM_CPT_PRINCIPAL)); ?>"
                class="button button-primary">
                <?php echo esc_html__('Manage Principals', 'headless-access-manager'); ?>
            </a>

            <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . HAM_CPT_SCHOOL_HEAD)); ?>"
                class="button button-primary">
                <?php echo esc_html__('Manage School Heads', 'headless-access-manager'); ?>
            </a>

            <a href="<?php echo esc_url(admin_url('users.php')); ?>" class="button button-primary">
                <?php echo esc_html__('Manage Users', 'headless-access-manager'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=ham-settings')); ?>" class="button button-secondary">
                <?php echo esc_html__('Settings', 'headless-access-manager'); ?>
            </a>
        </div>
    </div>
</div>
<style>
.ham-dashboard-stats {
    margin-top: 20px;
    background: #fff;
    padding: 20px;
    border-radius: 5px;
    box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
}

.ham-dashboard-stat-boxes {
    display: flex;
    flex-wrap: wrap;
    margin: 0 -10px;
}

.ham-stat-box {
    flex: 0 0 calc(25% - 20px);
    margin: 10px;
    background: #f9f9f9;
    padding: 15px;
    border-radius: 3px;
    text-align: center;
    box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
}

.ham-stat-box h3 {
    margin-top: 0;
    margin-bottom: 10px;
    color: #23282d;
}

.ham-stat-value {
    font-size: 24px;
    font-weight: bold;
    color: #0073aa;
}

.ham-dashboard-quick-links {
    margin-top: 20px;
    background: #fff;
    padding: 20px;
    border-radius: 5px;
    box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
}

.ham-quick-links {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}
</style>
<?php
    }

    /**
     * Render settings page.
     */
    public static function render_settings_page()
    {
        ?>
<div class="wrap">
    <h1><?php echo esc_html__('Headless Access Manager Settings', 'headless-access-manager'); ?></h1>

    <form method="post" action="options.php">
        <?php
                settings_fields('ham_settings');
        do_settings_sections('ham_settings');
        submit_button();
        ?>
    </form>
</div>
<?php
    }

    /**
     * Register settings.
     */
    public static function register_settings()
    {
        register_setting('ham_settings', 'ham_jwt_secret');
        register_setting('ham_settings', 'ham_jwt_expiration');
        register_setting('ham_settings', 'ham_cleanup_on_deactivation');
        register_setting('ham_settings', 'ham_active_question_bank_id');

        add_settings_section(
            'ham_settings_jwt',
            __('JSON Web Token Authentication Settings', 'headless-access-manager'),
            array( __CLASS__, 'render_jwt_section' ),
            'ham_settings'
        );

        add_settings_field(
            'ham_jwt_secret',
            __('JWT Secret Key', 'headless-access-manager'),
            array( __CLASS__, 'render_jwt_secret_field' ),
            'ham_settings',
            'ham_settings_jwt'
        );

        add_settings_field(
            'ham_jwt_expiration',
            __('JWT Expiration (days)', 'headless-access-manager'),
            array( __CLASS__, 'render_jwt_expiration_field' ),
            'ham_settings',
            'ham_settings_jwt'
        );

        add_settings_section(
            'ham_settings_general',
            __('General Settings', 'headless-access-manager'),
            array( __CLASS__, 'render_general_section' ),
            'ham_settings'
        );

        add_settings_field(
            'ham_cleanup_on_deactivation',
            __('Cleanup on Deactivation', 'headless-access-manager'),
            array( __CLASS__, 'render_cleanup_field' ),
            'ham_settings',
            'ham_settings_general'
        );

        add_settings_field(
            'ham_active_question_bank_id',
            __('Active Question Bank', 'headless-access-manager'),
            array( __CLASS__, 'render_active_question_bank_field' ),
            'ham_settings',
            'ham_settings_general'
        );
    }

    /**
     * Render JWT section.
     */
    public static function render_jwt_section()
    {
        echo '<p>' . esc_html__('Configure JWT authentication settings for the API.', 'headless-access-manager') . '</p>';
    }

    /**
     * Render JWT secret field.
     */
    public static function render_jwt_secret_field()
    {
        $jwt_secret = get_option('ham_jwt_secret', '');

        if (empty($jwt_secret)) {
            $jwt_secret = bin2hex(random_bytes(32));
            update_option('ham_jwt_secret', $jwt_secret);
        }

        ?>
<input type="text" name="ham_jwt_secret" value="<?php echo esc_attr($jwt_secret); ?>" class="regular-text">
<p class="description">
    <?php echo esc_html__('Secret key used to sign the JWT. This key should be kept secure and only shared with authorized personnel.', 'headless-access-manager'); ?>
</p>
<?php
    }

    /**
     * Render JWT expiration field.
     */
    public static function render_jwt_expiration_field()
    {
        $jwt_expiration = get_option('ham_jwt_expiration', 7);
        ?>
<input type="number" name="ham_jwt_expiration" value="<?php echo esc_attr($jwt_expiration); ?>" min="1" max="30"
    step="1">
<p class="description">
    <?php echo esc_html__('Number of days before the JWT expires and the admin needs to log in again.', 'headless-access-manager'); ?>
</p>
<?php
    }

    /**
     * Render general section.
     */
    public static function render_general_section()
    {
        echo '<p>' . esc_html__('General plugin settings.', 'headless-access-manager') . '</p>';
    }

    /**
     * Render cleanup field.
     */
    public static function render_cleanup_field()
    {
        $cleanup = get_option('ham_cleanup_on_deactivation', false);
        ?>
<label>
    <input type="checkbox" name="ham_cleanup_on_deactivation" value="1" <?php checked($cleanup, true); ?>>
    <?php echo esc_html__('Delete all plugin data upon deactivation', 'headless-access-manager'); ?>
</label>
<p class="description" style="color: red; font-weight: bold;">
    <?php echo esc_html__('DANGER: Checking this box will permanently delete ALL data associated with this plugin upon deactivation. This includes all Questions, Student Evaluations, Students, Teachers, Classes, Schools, and user roles. This action cannot be undone.', 'headless-access-manager'); ?>
</p>
<?php
    }

    public static function render_active_question_bank_field()
    {
        $active_id = absint(get_option('ham_active_question_bank_id', 0));

        $posts = get_posts(array(
            'post_type'      => HAM_CPT_ASSESSMENT_TPL,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => array(
                array(
                    'key'     => '_ham_assessment_data',
                    'compare' => 'EXISTS',
                ),
                array(
                    'relation' => 'OR',
                    array(
                        'key'     => HAM_ASSESSMENT_META_STUDENT_ID,
                        'compare' => 'NOT EXISTS',
                    ),
                    array(
                        'key'     => HAM_ASSESSMENT_META_STUDENT_ID,
                        'value'   => '',
                        'compare' => '=',
                    ),
                ),
            ),
        ));

        ?>
<select name="ham_active_question_bank_id" class="regular-text">
    <option value="0" <?php selected($active_id, 0); ?>><?php echo esc_html__('Auto (latest Question Bank)', 'headless-access-manager'); ?></option>
    <?php foreach ($posts as $post) : ?>
        <option value="<?php echo esc_attr($post->ID); ?>" <?php selected($active_id, $post->ID); ?>>
            <?php echo esc_html($post->post_title); ?> (<?php echo esc_html((string) $post->ID); ?>)
        </option>
    <?php endforeach; ?>
</select>
<p class="description">
    <?php echo esc_html__('Select which Assessment post acts as the Question Bank that powers the evaluation form and admin reporting. Choose Auto to use the newest available bank.', 'headless-access-manager'); ?>
</p>
<?php
    }
}

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
            __('Headless Access Manager', 'headless-access-manager'),
            __('HAM', 'headless-access-manager'),
            'manage_options',
            'headless-access-manager',
            array( __CLASS__, 'render_dashboard_page' ),
            'dashicons-lock',
            30
        );

        // Dashboard submenu
        add_submenu_page(
            'headless-access-manager',
            __('Dashboard', 'headless-access-manager'),
            __('Översikt', 'headless-access-manager'),
            'manage_options',
            'headless-access-manager',
            array( __CLASS__, 'render_dashboard_page' )
        );

        // Assessments submenu
        add_submenu_page(
            'headless-access-manager',
            __('Assessments', 'headless-access-manager'),
            __('Bedömningar', 'headless-access-manager'),
            'manage_options',
            'ham-assessments',
            array( 'HAM_Assessment_Manager', 'render_assessments_page' )
        );

        // Assessment Statistics submenu
        add_submenu_page(
            'headless-access-manager',
            __('Assessment Statistics', 'headless-access-manager'),
            __('Statistik', 'headless-access-manager'),
            'manage_options',
            'ham-assessment-stats',
            array( 'HAM_Assessment_Manager', 'render_statistics_page' )
        );

        // Settings submenu
        add_submenu_page(
            'headless-access-manager',
            __('Settings', 'headless-access-manager'),
            __('Inställningar', 'headless-access-manager'),
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
    <h1><?php echo esc_html__('Headless Access Manager Dashboard', 'headless-access-manager'); ?></h1>

    <div class="ham-dashboard-stats">
        <h2><?php echo esc_html__('System Overview', 'headless-access-manager'); ?></h2>

        <div class="ham-dashboard-stat-boxes">
            <div class="ham-stat-box">
                <h3><?php echo esc_html__('Schools', 'headless-access-manager'); ?></h3>
                <div class="ham-stat-value"><?php echo esc_html($schools_count); ?></div>
            </div>

            <div class="ham-stat-box">
                <h3><?php echo esc_html__('Classes', 'headless-access-manager'); ?></h3>
                <div class="ham-stat-value"><?php echo esc_html($classes_count); ?></div>
            </div>

            <div class="ham-stat-box">
                <h3><?php echo esc_html__('Teachers', 'headless-access-manager'); ?></h3>
                <div class="ham-stat-value"><?php echo esc_html($teachers_count); ?></div>
            </div>

            <div class="ham-stat-box">
                <h3><?php echo esc_html__('Students', 'headless-access-manager'); ?></h3>
                <div class="ham-stat-value"><?php echo esc_html($students_count); ?></div>
            </div>

            <div class="ham-stat-box">
                <h3><?php echo esc_html__('Principals', 'headless-access-manager'); ?></h3>
                <div class="ham-stat-value"><?php echo esc_html($principals_count); ?></div>
            </div>

            <div class="ham-stat-box">
                <h3><?php echo esc_html__('School Heads', 'headless-access-manager'); ?></h3>
                <div class="ham-stat-value"><?php echo esc_html($school_heads_count); ?></div>
            </div>

            <div class="ham-stat-box">
                <h3><?php echo esc_html__('Assessments', 'headless-access-manager'); ?></h3>
                <div class="ham-stat-value"><?php echo esc_html($assessments_count); ?></div>
            </div>
        </div>
    </div>

    <div class="ham-dashboard-quick-links">
        <h2><?php echo esc_html__('Quick Links', 'headless-access-manager'); ?></h2>

        <div class="ham-quick-links">
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . HAM_CPT_SCHOOL)); ?>"
                class="button button-primary">
                <?php echo esc_html__('Manage Schools', 'headless-access-manager'); ?>
            </a>

            <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . HAM_CPT_CLASS)); ?>"
                class="button button-primary">
                <?php echo esc_html__('Manage Classes', 'headless-access-manager'); ?>
            </a>

            <a href="<?php echo esc_url(admin_url('users.php')); ?>" class="button button-primary">
                <?php echo esc_html__('Manage Users', 'headless-access-manager'); ?>
            </a>

            <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . HAM_CPT_ASSESSMENT)); ?>"
                class="button button-primary">
                <?php echo esc_html__('View Assessments', 'headless-access-manager'); ?>
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
    <h1><?php echo esc_html__('Headless Access Manager Inställningar', 'headless-access-manager'); ?></h1>

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

        add_settings_section(
            'ham_settings_jwt',
            __('JWT Autentiseringsinställningar', 'headless-access-manager'),
            array( __CLASS__, 'render_jwt_section' ),
            'ham_settings'
        );

        add_settings_field(
            'ham_jwt_secret',
            __('JWT Hemlig Nyckel', 'headless-access-manager'),
            array( __CLASS__, 'render_jwt_secret_field' ),
            'ham_settings',
            'ham_settings_jwt'
        );

        add_settings_field(
            'ham_jwt_expiration',
            __('JWT Upphörande (dagar)', 'headless-access-manager'),
            array( __CLASS__, 'render_jwt_expiration_field' ),
            'ham_settings',
            'ham_settings_jwt'
        );

        add_settings_section(
            'ham_settings_general',
            __('Allmänna Inställningar', 'headless-access-manager'),
            array( __CLASS__, 'render_general_section' ),
            'ham_settings'
        );

        add_settings_field(
            'ham_cleanup_on_deactivation',
            __('Rensa vid inaktivering', 'headless-access-manager'),
            array( __CLASS__, 'render_cleanup_field' ),
            'ham_settings',
            'ham_settings_general'
        );
    }

    /**
     * Render JWT section.
     */
    public static function render_jwt_section()
    {
        echo '<p>' . esc_html__('Konfigurera JWT-autentiseringsinställningar för API:et.', 'headless-access-manager') . '</p>';
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
    <?php echo esc_html__('Hemlig nyckel som används för att signera JWT-token. Detta bör hållas säkert.', 'headless-access-manager'); ?>
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
    <?php echo esc_html__('Antal dagar innan JWT-token upphör.', 'headless-access-manager'); ?>
</p>
<?php
    }

    /**
     * Render general section.
     */
    public static function render_general_section()
    {
        echo '<p>' . esc_html__('Allmänna plugin-inställningar. GDPR-säkert', 'headless-access-manager') . '</p>';
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
    <?php echo esc_html__('Rensa all plugin-data vid inaktivering', 'headless-access-manager'); ?>
</label>
<p class="description">
    <?php echo esc_html__('VARNING: Detta kommer att rensa all anpassad roller, förmågor och plugin-inställningar när pluginen inaktiveras.', 'headless-access-manager'); ?>
</p>
<?php
    }
}

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
            __('Observationsschemat', 'headless-access-manager'),
            __('Observationsschemat', 'headless-access-manager'),
            'manage_options',
            'headless-access-manager',
            array( __CLASS__, 'render_dashboard_page' ),
            'dashicons-dashboard',
            30
        );

        // Overview submenu
        add_submenu_page(
            'headless-access-manager',
            __('Översikt', 'headless-access-manager'), // Page title
            __('Översikt', 'headless-access-manager'), // Menu title
            'manage_options',
            'headless-access-manager',
            array( __CLASS__, 'render_dashboard_page' )
        );

        // Evaluations submenu
        add_submenu_page(
            'headless-access-manager',
            __('Observationer', 'headless-access-manager'), // Page title
            __('Observationer', 'headless-access-manager'), // Menu title
            'manage_options',
            'ham-assessments',
            array( 'HAM_Assessment_Manager', 'render_assessments_page' )
        );

        // Question Bank submenu
        add_submenu_page(
            'headless-access-manager',
            __('Frågebank', 'headless-access-manager'), // Page title
            __('Frågebank', 'headless-access-manager'), // Menu title
            'manage_options',
            'edit.php?post_type=' . HAM_CPT_ASSESSMENT_TPL
        );

        // Statistics submenu
        add_submenu_page(
            'headless-access-manager',
            __('Statistik', 'headless-access-manager'), // Page title
            __('Statistik', 'headless-access-manager'), // Menu title
            'manage_options',
            'ham-assessment-stats',
            array( 'HAM_Assessment_Manager', 'render_statistics_page' )
        );

        // Settings submenu
        add_submenu_page(
            'headless-access-manager',
            __('Inställningar', 'headless-access-manager'), // Page title
            __('Inställningar', 'headless-access-manager'), // Menu title
            'manage_options',
            'ham-settings',
            array( __CLASS__, 'render_settings_page' )
        );

        add_action('admin_menu', array(__CLASS__, 'reorder_submenu'), 999);
        add_action('admin_head', array(__CLASS__, 'output_submenu_icons_css'));
        add_action('admin_footer', array(__CLASS__, 'output_submenu_groups_js'));
        // Register settings
        add_action('admin_init', array( __CLASS__, 'register_settings' ));
    }

    public static function reorder_submenu()
    {
        global $submenu;

        $parent = 'headless-access-manager';
        if (!isset($submenu[$parent]) || !is_array($submenu[$parent])) {
            return;
        }

        $items = $submenu[$parent];
        $by_slug = array();
        foreach ($items as $it) {
            if (!is_array($it) || !isset($it[2])) {
                continue;
            }
            $by_slug[(string) $it[2]] = $it;
        }

        $get = function($slug) use ($by_slug) {
            return isset($by_slug[$slug]) ? $by_slug[$slug] : null;
        };

        $out = array();

        // Översikt
        $overview = $get('headless-access-manager');
        if ($overview) {
            $overview[0] = __('Översikt', 'headless-access-manager');
            $overview[3] = __('Översikt', 'headless-access-manager');
            $out[] = $overview;
        }

        // Observationer
        $assessments = $get('ham-assessments');
        if ($assessments) {
            $assessments[0] = __('Observationer', 'headless-access-manager');
            $assessments[3] = __('Observationer', 'headless-access-manager');
            $out[] = $assessments;
        }

        // Statistik
        $stats = $get('ham-assessment-stats');
        if ($stats) {
            $stats[0] = __('Statistik', 'headless-access-manager');
            $stats[3] = __('Statistik', 'headless-access-manager');
            $out[] = $stats;
        }

        // Organisation group
        $out[] = array(
            __('Organisation', 'headless-access-manager'),
            'manage_options',
            '#ham-group-organisation',
            __('Organisation', 'headless-access-manager'),
        );

        $school = $get('edit.php?post_type=' . HAM_CPT_SCHOOL);
        if ($school) {
            $school[0] = __('Skolor', 'headless-access-manager');
            $school[3] = __('Skolor', 'headless-access-manager');
            $out[] = $school;
        }
        $class = $get('edit.php?post_type=' . HAM_CPT_CLASS);
        if ($class) {
            $class[0] = __('Klasser', 'headless-access-manager');
            $class[3] = __('Klasser', 'headless-access-manager');
            $out[] = $class;
        }

        // Personer group
        $out[] = array(
            __('Personer', 'headless-access-manager'),
            'manage_options',
            '#ham-group-people',
            __('Personer', 'headless-access-manager'),
        );

        $student = $get('edit.php?post_type=' . HAM_CPT_STUDENT);
        if ($student) {
            $student[0] = __('Elever', 'headless-access-manager');
            $student[3] = __('Elever', 'headless-access-manager');
            $out[] = $student;
        }
        $teacher = $get('edit.php?post_type=' . HAM_CPT_TEACHER);
        if ($teacher) {
            $teacher[0] = __('Lärare', 'headless-access-manager');
            $teacher[3] = __('Lärare', 'headless-access-manager');
            $out[] = $teacher;
        }
        $principal = $get('edit.php?post_type=' . HAM_CPT_PRINCIPAL);
        if ($principal) {
            $principal[0] = __('Rektorer', 'headless-access-manager');
            $principal[3] = __('Rektorer', 'headless-access-manager');
            $out[] = $principal;
        }
        $school_head = $get('edit.php?post_type=' . HAM_CPT_SCHOOL_HEAD);
        if ($school_head) {
            $school_head[0] = __('Skolledare', 'headless-access-manager');
            $school_head[3] = __('Skolledare', 'headless-access-manager');
            $out[] = $school_head;
        }

        // Frågebank
        $qb = $get('edit.php?post_type=' . HAM_CPT_ASSESSMENT_TPL);
        if ($qb) {
            $qb[0] = __('Frågebank', 'headless-access-manager');
            $qb[3] = __('Frågebank', 'headless-access-manager');
            $out[] = $qb;
        }

        // Inställningar
        $settings = $get('ham-settings');
        if ($settings) {
            $settings[0] = __('Inställningar', 'headless-access-manager');
            $settings[3] = __('Inställningar', 'headless-access-manager');
            $out[] = $settings;
        }

        $submenu[$parent] = $out;
    }

    public static function output_submenu_icons_css()
    {
        if (!is_admin()) {
            return;
        }

        echo '<style>';
        echo '#toplevel_page_headless-access-manager .wp-submenu a{display:flex;align-items:center;gap:8px;}';
        echo '#toplevel_page_headless-access-manager .wp-submenu a .dashicons{font-size:18px;line-height:18px;width:18px;height:18px;color:#646970;margin-top:-1px;}';
        echo '#toplevel_page_headless-access-manager .wp-submenu a[href="#ham-group-organisation"],#toplevel_page_headless-access-manager .wp-submenu a[href="#ham-group-people"]{font-weight:600;}';
        echo '</style>';
    }

    public static function output_submenu_groups_js()
    {
        if (!is_admin()) {
            return;
        }

        echo '<script>';
        echo '(function(){';
        echo 'var root=document.getElementById("toplevel_page_headless-access-manager");if(!root){return;}';
        echo 'var menu=root.querySelector(".wp-submenu");if(!menu){return;}';
        echo 'var links=Array.prototype.slice.call(menu.querySelectorAll("a"));';
        echo 'function findLi(a){while(a&&a.tagName&&a.tagName.toLowerCase()!=="li"){a=a.parentNode;}return a;}';
        echo 'function setCollapsed(key,collapsed){try{localStorage.setItem(key,collapsed?"1":"0");}catch(e){}}';
        echo 'function getCollapsed(key,def){try{var v=localStorage.getItem(key);if(v===null){return def;}return v==="1";}catch(e){return def;}}';

        echo 'function ensureIcon(link,iconClass){';
        echo 'if(!link||!iconClass){return;}';
        echo 'if(link.querySelector(".dashicons")){return;}';
        echo 'var s=document.createElement("span");s.className="dashicons "+iconClass;';
        echo 'link.insertBefore(s,link.firstChild);';
        echo '}';

        echo 'function applyGroup(groupHref,key){';
        echo 'var groupLink=menu.querySelector("a[href=\""+groupHref+"\"]");if(!groupLink){return;}';
        echo 'var li=findLi(groupLink);if(!li){return;}';
        echo 'var next=li.nextElementSibling;var members=[];';
        echo 'while(next){var a=next.querySelector("a");if(a&&(a.getAttribute("href")==="#ham-group-organisation"||a.getAttribute("href")==="#ham-group-people")){break;}members.push(next);next=next.nextElementSibling;}';
        echo 'var collapsed=getCollapsed(key,false);';
        echo 'var iconSpan=groupLink.querySelector(".dashicons");';
        echo 'if(!iconSpan){iconSpan=document.createElement("span");iconSpan.className="dashicons";groupLink.insertBefore(iconSpan,groupLink.firstChild);}';
        echo 'function render(){iconSpan.className="dashicons "+(collapsed?"dashicons-arrow-right":"dashicons-arrow-down");members.forEach(function(m){m.style.display=collapsed?"none":"";});}';
        echo 'groupLink.addEventListener("click",function(ev){ev.preventDefault();collapsed=!collapsed;setCollapsed(key,collapsed);render();});';
        echo 'render();';
        echo '}';

        echo 'links.forEach(function(a){';
        echo 'var href=(a.getAttribute("href")||"");';
        echo 'if(href==="admin.php?page=headless-access-manager"){ensureIcon(a,"dashicons-dashboard");}';
        echo 'else if(href==="admin.php?page=ham-assessments"){ensureIcon(a,"dashicons-visibility");}';
        echo 'else if(href==="admin.php?page=ham-assessment-stats"){ensureIcon(a,"dashicons-chart-bar");}';
        echo 'else if(href.indexOf("edit.php?post_type=ham_school")==0){ensureIcon(a,"dashicons-building");}';
        echo 'else if(href.indexOf("edit.php?post_type=ham_class")==0){ensureIcon(a,"dashicons-groups");}';
        echo 'else if(href.indexOf("edit.php?post_type=ham_student")==0){ensureIcon(a,"dashicons-id");}';
        echo 'else if(href.indexOf("edit.php?post_type=ham_teacher")==0){ensureIcon(a,"dashicons-welcome-learn-more");}';
        echo 'else if(href.indexOf("edit.php?post_type=ham_principal")==0){ensureIcon(a,"dashicons-businessperson");}';
        echo 'else if(href.indexOf("edit.php?post_type=ham_school_head")==0){ensureIcon(a,"dashicons-admin-users");}';
        echo 'else if(href.indexOf("edit.php?post_type=ham_assessment_tpl")==0){ensureIcon(a,"dashicons-editor-help");}';
        echo 'else if(href==="admin.php?page=ham-settings"){ensureIcon(a,"dashicons-admin-generic");}';
        echo '});';

        echo 'applyGroup("#ham-group-organisation","ham_menu_org_collapsed");';
        echo 'applyGroup("#ham-group-people","ham_menu_people_collapsed");';
        echo '})();';
        echo '</script>';
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

        $stats = HAM_Assessment_Manager::get_assessment_statistics('all');
        $ssc = isset($stats['student_stage_counts']) && is_array($stats['student_stage_counts']) ? $stats['student_stage_counts'] : array();
        $ssc_not = isset($ssc['not']) ? (int) $ssc['not'] : 0;
        $ssc_trans = isset($ssc['trans']) ? (int) $ssc['trans'] : 0;
        $ssc_full = isset($ssc['full']) ? (int) $ssc['full'] : 0;

        $time_bucket = isset($_GET['time_bucket']) ? sanitize_text_field(wp_unslash($_GET['time_bucket'])) : 'term';
        if ($time_bucket !== 'month' && $time_bucket !== 'school_year' && $time_bucket !== 'term') {
            $time_bucket = 'term';
        }

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

    <div class="ham-dashboard-assessment-overview">
        <h2><?php echo esc_html__('Assessment overview', 'headless-access-manager'); ?></h2>

        <div class="ham-stats-overview">
            <div class="ham-stats-card">
                <div class="ham-stats-icon">
                    <span class="dashicons dashicons-clipboard"></span>
                </div>
                <div class="ham-stats-data">
                    <div class="ham-stats-value"><?php echo esc_html(isset($stats['total_assessments']) ? (int) $stats['total_assessments'] : 0); ?></div>
                    <div class="ham-stats-label"><?php echo esc_html__('Total Evaluations', 'headless-access-manager'); ?></div>
                </div>
            </div>

            <div class="ham-stats-card">
                <div class="ham-stats-icon">
                    <span class="dashicons dashicons-yes"></span>
                </div>
                <div class="ham-stats-data">
                    <div class="ham-stats-value"><?php echo esc_html($ssc_full); ?></div>
                    <div class="ham-stats-label"><?php echo esc_html__('Established students', 'headless-access-manager'); ?></div>
                </div>
            </div>

            <div class="ham-stats-card">
                <div class="ham-stats-icon">
                    <span class="dashicons dashicons-minus"></span>
                </div>
                <div class="ham-stats-data">
                    <div class="ham-stats-value"><?php echo esc_html($ssc_trans); ?></div>
                    <div class="ham-stats-label"><?php echo esc_html__('Developing students', 'headless-access-manager'); ?></div>
                </div>
            </div>

            <div class="ham-stats-card">
                <div class="ham-stats-icon">
                    <span class="dashicons dashicons-no"></span>
                </div>
                <div class="ham-stats-data">
                    <div class="ham-stats-value"><?php echo esc_html($ssc_not); ?></div>
                    <div class="ham-stats-label"><?php echo esc_html__('Not established students', 'headless-access-manager'); ?></div>
                </div>
            </div>
        </div>

        <div class="ham-stats-row">
            <div class="ham-stats-column">
                <div class="postbox" style="margin-top: 0;">
                    <div class="postbox-header">
                        <h2 class="hndle">
                            <?php
                            if ($time_bucket === 'month') {
                                echo esc_html__('Monthly Evaluations', 'headless-access-manager');
                            } elseif ($time_bucket === 'school_year') {
                                echo esc_html__('School Year Evaluations', 'headless-access-manager');
                            } else {
                                echo esc_html__('Term Evaluations', 'headless-access-manager');
                            }
                            ?>
                        </h2>
                    </div>
                    <div class="inside">
                        <div style="margin-bottom: 12px;">
                            <?php
                            $base_args = $_GET;
                            $base_args = is_array($base_args) ? $base_args : array();
                            $base_args['page'] = 'headless-access-manager';

                            $make_link = function($bucket, $label) use ($base_args, $time_bucket) {
                                $args = $base_args;
                                $args['time_bucket'] = $bucket;
                                $url = add_query_arg(array_map('sanitize_text_field', $args), admin_url('admin.php'));
                                $is_active = $time_bucket === $bucket;

                                $style = 'display:inline-block; padding: 4px 10px; border-radius: 999px; text-decoration:none; margin-right: 6px; border: 1px solid #c3c4c7;';
                                if ($is_active) {
                                    $style .= ' background:#2271b1; color:#fff; border-color:#2271b1;';
                                } else {
                                    $style .= ' background:#fff; color:#1d2327;';
                                }

                                return '<a href="' . esc_url($url) . '" style="' . esc_attr($style) . '">' . esc_html($label) . '</a>';
                            };

                            echo $make_link('month', __('Month', 'headless-access-manager'));
                            echo $make_link('term', __('Term', 'headless-access-manager'));
                            echo $make_link('school_year', __('School year', 'headless-access-manager'));
                            ?>
                        </div>

                        <div class="ham-chart-container" style="padding: 20px; text-align: center;">
                            <?php
                            $chartData = array();
                            if ($time_bucket === 'month') {
                                $chartData = array_map(function($item) {
                                    return array(
                                        'label' => date_i18n('M Y', strtotime($item['month'] . '-01')),
                                        'count' => $item['count']
                                    );
                                }, isset($stats['monthly_submissions']) ? $stats['monthly_submissions'] : array());
                            } elseif ($time_bucket === 'school_year') {
                                $chartData = array_map(function($item) {
                                    return array(
                                        'label' => isset($item['label']) ? $item['label'] : '',
                                        'count' => $item['count']
                                    );
                                }, isset($stats['school_year_submissions']) ? $stats['school_year_submissions'] : array());
                            } else {
                                $chartData = array_map(function($item) {
                                    return array(
                                        'label' => isset($item['label']) ? $item['label'] : '',
                                        'count' => $item['count']
                                    );
                                }, isset($stats['term_submissions']) ? $stats['term_submissions'] : array());
                            }

                            if (empty($chartData)) {
                                echo '<p>' . esc_html__('No data to display', 'headless-access-manager') . '</p>';
                            } else {
                                $maxCount = 1;
                                $points = array();
                                $labels = array();
                                foreach ($chartData as $item) {
                                    $count = isset($item['count']) ? (int) $item['count'] : 0;
                                    if ($count > $maxCount) {
                                        $maxCount = $count;
                                    }
                                    $labels[] = isset($item['label']) ? (string) $item['label'] : '';
                                    $points[] = $count;
                                }

                                $n = count($points);
                                if ($n === 0) {
                                    echo '<p>' . esc_html__('No data to display', 'headless-access-manager') . '</p>';
                                } else {
                                    $w = 100;
                                    $h = 48;
                                    $pad_x = 6;
                                    $pad_y = 10;

                                    $svg_points = array();
                                    $circle_nodes = array();
                                    for ($i = 0; $i < $n; $i++) {
                                        $x = ($n === 1)
                                            ? ($w / 2)
                                            : ($pad_x + ($i * (($w - 2 * $pad_x) / ($n - 1))));
                                        $count = (int) $points[$i];
                                        $ratio = $maxCount > 0 ? ($count / $maxCount) : 0;
                                        $y = ($h - $pad_y) - ($ratio * ($h - 2 * $pad_y));
                                        $svg_points[] = $x . ',' . $y;
                                        $circle_nodes[] = array(
                                            'x' => $x,
                                            'y' => $y,
                                            'count' => $count,
                                            'label' => $labels[$i],
                                        );
                                    }

                                    echo '<div class="ham-mini-line" style="width: 100%;">';
                                    echo '<svg viewBox="0 0 ' . esc_attr($w) . ' ' . esc_attr($h) . '" preserveAspectRatio="xMidYMid meet" style="width: 100%; height: auto; aspect-ratio: ' . esc_attr($w) . ' / ' . esc_attr($h) . '; max-height: 62px; overflow: visible;">';
                                    echo '<line x1="' . esc_attr($pad_x) . '" y1="' . esc_attr($h - $pad_y) . '" x2="' . esc_attr($w - $pad_x) . '" y2="' . esc_attr($h - $pad_y) . '" stroke="#dcdcde" stroke-width="1" />';
                                    if ($n > 1) {
                                        echo '<polyline fill="none" stroke="#0073aa" stroke-width="2" points="' . esc_attr(implode(' ', $svg_points)) . '" />';
                                    }
                                    foreach ($circle_nodes as $node) {
                                        echo '<circle cx="' . esc_attr($node['x']) . '" cy="' . esc_attr($node['y']) . '" r="9" fill="#ffffff" stroke="#0073aa" stroke-width="2" />';
                                        echo '<text x="' . esc_attr($node['x']) . '" y="' . esc_attr($node['y']) . '" text-anchor="middle" dominant-baseline="middle" font-size="9" fill="#1d2327">' . esc_html((string) $node['count']) . '</text>';
                                    }
                                    echo '</svg>';

                                    echo '<div style="display: grid; grid-template-columns: repeat(' . esc_attr($n) . ', 1fr); gap: 6px; margin-top: 2px;">';
                                    for ($i = 0; $i < $n; $i++) {
                                        echo '<div style="text-align: center; font-size: 11px; color: #646970; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">' . esc_html($labels[$i]) . '</div>';
                                    }
                                    echo '</div>';

                                    echo '</div>';
                                }
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ham-stats-column">
                <div class="postbox" style="margin-top: 0;">
                    <div class="postbox-header"><h2 class="hndle"><?php echo esc_html__('Status Distribution', 'headless-access-manager'); ?></h2></div>
                    <div class="inside">
                        <div class="ham-chart-container" style="padding: 20px; text-align: center;">
                            <?php
                            $stageData = array(
                                array(
                                    'label' => esc_html__('Not Established', 'headless-access-manager'),
                                    'value' => $ssc_not,
                                    'color' => 'hsl(11, 97.00%, 87.10%)'
                                ),
                                array(
                                    'label' => esc_html__('Developing', 'headless-access-manager'),
                                    'value' => $ssc_trans,
                                    'color' => 'hsl(40, 97%, 87%)'
                                ),
                                array(
                                    'label' => esc_html__('Established', 'headless-access-manager'),
                                    'value' => $ssc_full,
                                    'color' => 'hsl(105, 97%, 87%)'
                                )
                            );

                            echo '<div class="ham-simple-pie" style="display: flex; justify-content: center; align-items: center; flex-wrap: wrap;">';
                            foreach ($stageData as $item) {
                                echo '<div style="margin: 10px; text-align: center; width: 120px;">';
                                echo '<div style="height: 80px; width: 80px; margin: 0 auto; background-color: ' . esc_attr($item['color']) . '; border-radius: 50%;"></div>';
                                echo '<div style="margin-top: 10px;"><strong>' . esc_html($item['label']) . '</strong>: ' . esc_html($item['value']) . '</div>';
                                echo '</div>';
                            }
                            echo '</div>';
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="clear"></div>
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

.ham-dashboard-assessment-overview {
    margin-top: 20px;
    background: #fff;
    padding: 20px;
    border-radius: 5px;
    box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
}

.ham-stats-overview {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-top: 20px;
    margin-bottom: 30px;
}

.ham-stats-card {
    flex: 1;
    min-width: 200px;
    background-color: #fff;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    padding: 20px;
    display: flex;
    align-items: center;
}

.ham-stats-icon {
    margin-right: 15px;
}

.ham-stats-icon .dashicons {
    font-size: 36px;
    width: 36px;
    height: 36px;
    color: #0073aa;
}

.ham-stats-value {
    font-size: 24px;
    font-weight: 600;
    color: #23282d;
}

.ham-stats-label {
    font-size: 14px;
    color: #646970;
}

.ham-stats-row {
    display: flex;
    gap: 20px;
}

.ham-stats-column {
    flex: 1;
    min-width: 300px;
}

@media (max-width: 1200px) {
    .ham-stats-row {
        flex-direction: column;
    }
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

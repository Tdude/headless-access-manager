<?php

/**
 * File: inc/admin/class-ham-assessment-manager.php
 *
 * Handles assessment management in the WordPress admin.
 *
 * @package HeadlessAccessManager
 */

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class HAM_Assessment_Manager
 *
 * Handles assessment management in the WordPress admin.
 */
class HAM_Assessment_Manager
{
    private const ACTIVE_QUESTION_BANK_OPTION = 'ham_active_question_bank_id';
    private const QUESTION_BANK_META_KEY = '_ham_assessment_data';
    private const QUESTION_BANK_MIGRATION_OPTION = 'ham_question_bank_migrated_to_tpl';

    private static function calculate_stage_from_assessment_data($assessment_data, $threshold = 3, $majority_factor = 0.7)
    {
        $steps = array();
        if (is_array($assessment_data)) {
            foreach (array('anknytning', 'ansvar') as $section_key) {
                if (!isset($assessment_data[$section_key]['questions']) || !is_array($assessment_data[$section_key]['questions'])) {
                    continue;
                }
                foreach ($assessment_data[$section_key]['questions'] as $answer) {
                    if ($answer === '' || $answer === null) {
                        continue;
                    }
                    if (!is_numeric($answer)) {
                        continue;
                    }
                    $steps[] = (float) $answer;
                }
            }
        }

        $n = count($steps);
        if ($n === 0) {
            return array(
                'stage' => 'not',
                'k' => 0,
                'n' => 0,
            );
        }

        $k = 0;
        foreach ($steps as $s) {
            if ($s >= $threshold) {
                $k++;
            }
        }

        $m = (int) ceil($majority_factor * $n);
        $half = (int) ceil($n / 2);

        if ($k >= $m) {
            $stage = 'full';
        } elseif ($k >= $half) {
            $stage = 'trans';
        } else {
            $stage = 'not';
        }

        return array(
            'stage' => $stage,
            'k' => $k,
            'n' => $n,
        );
    }

    private static function calculate_latest_stage_counts_for_students(array $student_ids)
    {
        $student_ids = array_values(array_filter(array_map('absint', $student_ids)));
        if (empty($student_ids)) {
            return array(
                'counts' => array(
                    'not' => 0,
                    'trans' => 0,
                    'full' => 0,
                ),
                'by_student' => array(),
            );
        }

        $posts = self::fetch_evaluation_posts($student_ids);
        $latest_by_student = array();

        foreach ($posts as $post) {
            $sid = (int) get_post_meta($post->ID, HAM_ASSESSMENT_META_STUDENT_ID, true);
            if ($sid <= 0 || isset($latest_by_student[$sid])) {
                continue;
            }

            $assessment_data = get_post_meta($post->ID, HAM_ASSESSMENT_META_DATA, true);
            $calc = self::calculate_stage_from_assessment_data($assessment_data);
            $stage = isset($calc['stage']) ? (string) $calc['stage'] : 'not';
            if ($stage !== 'full' && $stage !== 'trans' && $stage !== 'not') {
                $stage = 'not';
            }

            $latest_by_student[$sid] = $stage;
        }

        $counts = array(
            'not' => 0,
            'trans' => 0,
            'full' => 0,
        );

        foreach ($student_ids as $sid) {
            $s = isset($latest_by_student[$sid]) ? $latest_by_student[$sid] : 'not';
            if (isset($counts[$s])) {
                $counts[$s]++;
            }
        }

        return array(
            'counts' => $counts,
            'by_student' => $latest_by_student,
        );
    }

    /**
     * Constructor.
     */
    public function __construct()
    {
        // One-time migration: move Question Bank posts out of ham_assessment into ham_assessment_tpl.
        add_action('admin_init', array($this, 'migrate_question_banks_to_templates'));

        // Fix metadata on existing assessments (one-time operation)
        add_action('admin_init', array($this, 'fix_assessment_metadata'));

        // Register AJAX handlers
        add_action('wp_ajax_ham_get_assessment_details', array($this, 'ajax_get_assessment_details'));
        add_action('wp_ajax_ham_get_assessment_stats', array($this, 'ajax_get_assessment_stats'));

        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Filter out frontend assessments from the default post type listing
        add_action('pre_get_posts', array($this, 'filter_assessment_admin_listing'));

        // Warn when viewing the default CPT list (question bank) to avoid accidental edits.
        add_action('admin_notices', array($this, 'render_assessment_question_bank_notice'));

        // Delete button functionality in the modal list for assessments
        add_action('wp_ajax_ham_delete_assessment', array($this, 'ajax_delete_assessment'));
    }

    /**
     * Show a warning on the default assessment CPT list page.
     *
     * The assessment CPT contains both evaluation answers and the global question bank.
     * We want admins to use the custom evaluations list (admin.php?page=ham-assessments)
     * rather than navigating into question bank screens by accident.
     */
    public function render_assessment_question_bank_notice()
    {
        if (!is_admin()) {
            return;
        }

        global $pagenow, $typenow;

        if ($pagenow !== 'edit.php' || $typenow !== HAM_CPT_ASSESSMENT) {
            return;
        }

        $safe_list_url = admin_url('admin.php?page=ham-assessments');

        echo '<div class="notice notice-warning">';
        echo '<p><strong>' . esc_html__('Heads up:', 'headless-access-manager') . '</strong> ';
        echo esc_html__('This page contains the global evaluation question bank that the whole system depends on.', 'headless-access-manager') . ' ';
        echo esc_html__('For normal work with student evaluations, use the Evaluations list instead:', 'headless-access-manager') . ' ';
        echo '<a href="' . esc_url($safe_list_url) . '">' . esc_html__('Go to Evaluations', 'headless-access-manager') . '</a>';
        echo '</p>';
        echo '</div>';
    }

    /**
     * Fix metadata on existing assessments.
     * This ensures all frontend submissions have the correct source flag.
     */
    public function fix_assessment_metadata()
    {
        // Only run for administrators to avoid unnecessary DB operations
        if (!current_user_can('manage_options')) {
            return;
        }

        // Force re-run if explicitly requested via URL parameter
        $force_run = isset($_GET['fix_assessments']) && $_GET['fix_assessments'] === '1';

        // Check if we've already run this fix
        $fix_run = get_option('ham_assessment_metadata_fixed', false);
        if ($fix_run && !$force_run) {
            return;
        }

        global $wpdb;

        // Find ALL assessments, regardless of metadata
        $assessments = get_posts(array(
            'post_type' => HAM_CPT_ASSESSMENT,
            'posts_per_page' => -1,
            'post_status' => 'any',
            'meta_query' => array(
                array(
                    'key' => HAM_ASSESSMENT_META_STUDENT_ID,
                    'value' => '',
                    'compare' => '!=',
                ),
            ),
        ));

        $count = 0;

        // For each assessment, determine if it's frontend or admin
        foreach ($assessments as $assessment) {
            $is_frontend = false;

            // Strong indicators of frontend submission
            $frontend_indicators = array(
                // Check if created via API
                get_post_meta($assessment->ID, '_ham_api_created', true),
                // Check for zero author (common in API submissions)
                $assessment->post_author == 0,
                // Check for REST API creation
                strpos($assessment->post_content, 'rest-api') !== false,
                // Check submission metadata
                get_post_meta($assessment->ID, '_ham_frontend_submission', true)
            );

            // If any indicator is true, mark as frontend
            foreach ($frontend_indicators as $indicator) {
                if ($indicator) {
                    $is_frontend = true;
                    break;
                }
            }

            // Set the source metadata
            update_post_meta($assessment->ID, '_ham_assessment_source', $is_frontend ? 'frontend' : 'admin');
            $count++;
        }

        // Mark as completed to avoid running again
        update_option('ham_assessment_metadata_fixed', true);

        if ($count > 0) {
            // Add an admin notice that metadata was fixed
            add_action('admin_notices', function () use ($count) {
                echo '<div class="notice notice-success is-dismissible"><p>' .
                    sprintf(__('Updated source metadata for %d assessments.', 'headless-access-manager'), $count) .
                    '</p></div>';
            });
        }

        // If force run was triggered via URL, redirect back to assessment list
        if ($force_run) {
            // Remove the parameter to avoid looping
            wp_redirect(admin_url('edit.php?post_type=' . HAM_CPT_ASSESSMENT . '&metadata_fixed=' . $count));
            exit;
        }
    }

    /**
     * Render assessments list page.
     */
    public static function render_assessments_page()
    {
        // Get current screen to determine which source filter to use
        $screen = get_current_screen();
        $source = 'all'; // Show all assessments in the custom admin page

        // Check if explicitly requesting admin source only
        if (isset($_GET['source']) && $_GET['source'] === 'admin' && current_user_can('manage_options')) {
            $source = 'admin';
        }

        // Get assessments with the appropriate filter
        $assessments = self::get_assessments($source);

        // Double-check: filter out any assessments that might still be from frontend
        if ($source === 'admin') {
            foreach ($assessments as $key => $assessment) {
                // Check author (frontend often uses author 0)
                if ($assessment['author_id'] == 0) {
                    unset($assessments[$key]);
                    continue;
                }

                // Check source metadata
                if (isset($assessment['meta']['_ham_assessment_source']) && $assessment['meta']['_ham_assessment_source'] === 'frontend') {
                    unset($assessments[$key]);
                    continue;
                }

                // Check API created flag
                if (isset($assessment['meta']['_ham_api_created']) && $assessment['meta']['_ham_api_created']) {
                    unset($assessments[$key]);
                    continue;
                }
            }

            // Reset array keys
            $assessments = array_values($assessments);
        }

        // Include the template
        include HAM_PLUGIN_DIR . 'templates/admin/assessments-list.php';
    }

    /**
     * Render assessment statistics page.
     */
    public static function render_statistics_page()
    {
        // For statistics, we likely want all assessments to provide complete stats
        $source = 'all';

        // Get statistics data (pass the source filter)
        $stats = self::get_assessment_statistics($source);

        $school_id  = isset($_GET['school_id']) ? absint($_GET['school_id']) : 0;
        $class_id   = isset($_GET['class_id']) ? absint($_GET['class_id']) : 0;
        $student_id = isset($_GET['student_id']) ? absint($_GET['student_id']) : 0;

        $drilldown = self::get_assessment_drilldown_stats($school_id, $class_id, $student_id);

        // Include the template
        include HAM_PLUGIN_DIR . 'templates/admin/assessment-statistics.php';
    }

    private static function get_active_question_bank_post_id(): int
    {
        $configured = absint(get_option(self::ACTIVE_QUESTION_BANK_OPTION, 0));
        if ($configured > 0) {
            $pt = get_post_type($configured);
            if ($pt === HAM_CPT_ASSESSMENT_TPL) {
                $has = get_post_meta($configured, self::QUESTION_BANK_META_KEY, true);
                if (is_array($has) && !empty($has)) {
                    return $configured;
                }
            }
        }

        $posts = get_posts(array(
            'post_type'      => HAM_CPT_ASSESSMENT_TPL,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => array(
                array(
                    'key'     => self::QUESTION_BANK_META_KEY,
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

        if (empty($posts)) {
            return 0;
        }

        return absint($posts[0]->ID);
    }

    public function migrate_question_banks_to_templates()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $already = get_option(self::QUESTION_BANK_MIGRATION_OPTION, false);
        if ($already) {
            return;
        }

        $ids = get_posts(array(
            'post_type'      => HAM_CPT_ASSESSMENT,
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'     => self::QUESTION_BANK_META_KEY,
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

        if (empty($ids)) {
            update_option(self::QUESTION_BANK_MIGRATION_OPTION, 1, false);
            return;
        }

        global $wpdb;
        foreach ($ids as $id) {
            $id = absint($id);
            if ($id <= 0) {
                continue;
            }

            $wpdb->update(
                $wpdb->posts,
                array('post_type' => HAM_CPT_ASSESSMENT_TPL),
                array('ID' => $id),
                array('%s'),
                array('%d')
            );
            clean_post_cache($id);
        }

        update_option(self::QUESTION_BANK_MIGRATION_OPTION, 1, false);
    }

    private static function get_question_bank_structure_from_db(int $post_id): array
    {
        if ($post_id <= 0) {
            return array();
        }

        $raw = get_post_meta($post_id, self::QUESTION_BANK_META_KEY, true);
        if (!is_array($raw)) {
            return array();
        }

        $structure = array();
        foreach (array('anknytning', 'ansvar') as $section) {
            if (!isset($raw[$section]) || !is_array($raw[$section])) {
                continue;
            }
            $structure[$section] = array(
                'title'     => isset($raw[$section]['title']) ? $raw[$section]['title'] : ucfirst($section),
                'questions' => isset($raw[$section]['questions']) && is_array($raw[$section]['questions']) ? $raw[$section]['questions'] : array(),
            );
        }

        return $structure;
    }

    public static function get_question_bank_structure(): array
    {
        $bank_id = self::get_active_question_bank_post_id();
        $db_structure = self::get_question_bank_structure_from_db($bank_id);
        if (!empty($db_structure)) {
            return $db_structure;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            $message = 'HAM: Active Question Bank structure missing/invalid. Option=' . self::ACTIVE_QUESTION_BANK_OPTION . ' value=' . absint(get_option(self::ACTIVE_QUESTION_BANK_OPTION, 0)) . ' resolved_bank_id=' . $bank_id;
            wp_die(esc_html($message));
        }

        return self::get_canonical_questions_structure();
    }

    private static function get_assessment_effective_date(WP_Post $post)
    {
        $meta_date = get_post_meta($post->ID, HAM_ASSESSMENT_META_DATE, true);
        if (!empty($meta_date)) {
            $ts = strtotime($meta_date);
            if ($ts !== false) {
                return $ts;
            }
        }

        $ts = strtotime($post->post_date);
        return $ts !== false ? $ts : time();
    }

    private static function get_semester_key_from_timestamp($ts)
    {
        $year = (int) gmdate('Y', $ts);
        $month = (int) gmdate('n', $ts);
        $semester = ($month <= 6) ? 'spring' : 'fall';
        return $year . '-' . $semester;
    }

    private static function format_semester_label($semester_key)
    {
        if (!is_string($semester_key) || strpos($semester_key, '-') === false) {
            return $semester_key;
        }
        list($year, $semester) = explode('-', $semester_key, 2);
        $semester_label = ($semester === 'spring') ? __('Spring', 'headless-access-manager') : __('Fall', 'headless-access-manager');
        return $year . ' ' . $semester_label;
    }

    private static function sort_semester_keys_asc($a, $b)
    {
        $a_parts = explode('-', (string) $a, 2);
        $b_parts = explode('-', (string) $b, 2);
        $a_year = isset($a_parts[0]) ? (int) $a_parts[0] : 0;
        $b_year = isset($b_parts[0]) ? (int) $b_parts[0] : 0;
        if ($a_year !== $b_year) {
            return $a_year <=> $b_year;
        }

        $a_sem = isset($a_parts[1]) ? $a_parts[1] : '';
        $b_sem = isset($b_parts[1]) ? $b_parts[1] : '';
        $a_rank = ($a_sem === 'spring') ? 1 : 2;
        $b_rank = ($b_sem === 'spring') ? 1 : 2;
        return $a_rank <=> $b_rank;
    }

    private static function extract_scores_from_assessment_data($assessment_data)
    {
        $overall_values = array();
        $question_values = array();

        if (!is_array($assessment_data)) {
            return array(
                'overall_avg' => null,
                'question_values' => array(),
            );
        }

        foreach (array('anknytning', 'ansvar') as $section_key) {
            if (!isset($assessment_data[$section_key]['questions']) || !is_array($assessment_data[$section_key]['questions'])) {
                continue;
            }

            foreach ($assessment_data[$section_key]['questions'] as $question_id => $answer) {
                if ($answer === '' || $answer === null) {
                    continue;
                }

                if (!is_numeric($answer)) {
                    continue;
                }

                $value = (float) $answer;
                $overall_values[] = $value;

                $question_key = $section_key . '_' . $question_id;
                if (!isset($question_values[$question_key])) {
                    $question_values[$question_key] = array();
                }
                $question_values[$question_key][] = $value;
            }
        }

        $overall_avg = null;
        if (!empty($overall_values)) {
            $overall_avg = array_sum($overall_values) / count($overall_values);
        }

        return array(
            'overall_avg' => $overall_avg,
            'question_values' => $question_values,
        );
    }

    private static function get_question_labels_map()
    {
        $structure = self::get_questions_structure();
        if (empty($structure) || !is_array($structure)) {
            return array();
        }

        $map = array();
        foreach (array('anknytning', 'ansvar') as $section_key) {
            if (!isset($structure[$section_key]['questions']) || !is_array($structure[$section_key]['questions'])) {
                continue;
            }
            foreach ($structure[$section_key]['questions'] as $question_id => $question) {
                $key = $section_key . '_' . $question_id;
                $map[$key] = array(
                    'section' => isset($structure[$section_key]['title']) ? $structure[$section_key]['title'] : ucfirst($section_key),
                    'text' => isset($question['text']) ? $question['text'] : $question_id,
                );
            }
        }

        return $map;
    }

    private static function get_question_order()
    {
        $structure = self::get_questions_structure();
        if (empty($structure) || !is_array($structure)) {
            return array();
        }

        $order = array();
        foreach (array('anknytning', 'ansvar') as $section_key) {
            if (!isset($structure[$section_key]['questions']) || !is_array($structure[$section_key]['questions'])) {
                continue;
            }
            foreach ($structure[$section_key]['questions'] as $question_id => $question) {
                $order[] = $section_key . '_' . $question_id;
            }
        }

        return $order;
    }

    private static function fetch_evaluation_posts($student_ids = array())
    {
        $meta_query = array(
            array(
                'key'     => HAM_ASSESSMENT_META_STUDENT_ID,
                'value'   => '',
                'compare' => '!=',
            ),
        );

        if (!empty($student_ids)) {
            $meta_query[] = array(
                'key'     => HAM_ASSESSMENT_META_STUDENT_ID,
                'value'   => array_values(array_unique(array_map('absint', $student_ids))),
                'compare' => 'IN',
            );
        }

        $args = array(
            'post_type'      => HAM_CPT_ASSESSMENT,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'ASC',
            'meta_query'     => $meta_query,
        );

        return get_posts($args);
    }

    private static function aggregate_evaluations_by_semester($posts)
    {
        $buckets = array();

        $student_semester_sum = array();
        $student_semester_count = array();

        foreach ($posts as $post) {
            $student_id = (int) get_post_meta($post->ID, HAM_ASSESSMENT_META_STUDENT_ID, true);
            if ($student_id <= 0) {
                continue;
            }

            $assessment_data = get_post_meta($post->ID, HAM_ASSESSMENT_META_DATA, true);
            $scores = self::extract_scores_from_assessment_data($assessment_data);

            $ts = self::get_assessment_effective_date($post);
            $semester_key = self::get_semester_key_from_timestamp($ts);

            if (!isset($buckets[$semester_key])) {
                $buckets[$semester_key] = array(
                    'semester_key' => $semester_key,
                    'semester_label' => self::format_semester_label($semester_key),
                    'count' => 0,
                    'students' => array(),
                    'overall_sum' => 0.0,
                    'overall_count' => 0,
                    'questions' => array(),
                    'delta_sum' => 0.0,
                    'delta_count' => 0,
                    'delta_students' => array(),
                );
            }

            $buckets[$semester_key]['count']++;
            $buckets[$semester_key]['students'][$student_id] = true;

            if ($scores['overall_avg'] !== null) {
                $buckets[$semester_key]['overall_sum'] += (float) $scores['overall_avg'];
                $buckets[$semester_key]['overall_count']++;

                if (!isset($student_semester_sum[$student_id])) {
                    $student_semester_sum[$student_id] = array();
                    $student_semester_count[$student_id] = array();
                }
                if (!isset($student_semester_sum[$student_id][$semester_key])) {
                    $student_semester_sum[$student_id][$semester_key] = 0.0;
                    $student_semester_count[$student_id][$semester_key] = 0;
                }
                $student_semester_sum[$student_id][$semester_key] += (float) $scores['overall_avg'];
                $student_semester_count[$student_id][$semester_key]++;
            }

            foreach ($scores['question_values'] as $question_key => $values) {
                if (!isset($buckets[$semester_key]['questions'][$question_key])) {
                    $buckets[$semester_key]['questions'][$question_key] = array(
                        'sum' => 0.0,
                        'count' => 0,
                    );
                }
                $buckets[$semester_key]['questions'][$question_key]['sum'] += array_sum($values);
                $buckets[$semester_key]['questions'][$question_key]['count'] += count($values);
            }
        }

        uksort($buckets, array(__CLASS__, 'sort_semester_keys_asc'));

        // Compute per-student deltas across semesters.
        foreach ($student_semester_sum as $student_id => $by_semester) {
            $semester_keys = array_keys($by_semester);
            usort($semester_keys, array(__CLASS__, 'sort_semester_keys_asc'));

            $prev_avg = null;
            foreach ($semester_keys as $semester_key) {
                $cnt = isset($student_semester_count[$student_id][$semester_key])
                    ? (int) $student_semester_count[$student_id][$semester_key]
                    : 0;
                if ($cnt <= 0) {
                    continue;
                }
                $avg = (float) $student_semester_sum[$student_id][$semester_key] / $cnt;

                if ($prev_avg !== null && isset($buckets[$semester_key])) {
                    $delta = $avg - (float) $prev_avg;
                    $buckets[$semester_key]['delta_sum'] += $delta;
                    $buckets[$semester_key]['delta_count']++;
                    $buckets[$semester_key]['delta_students'][(int) $student_id] = true;
                }

                $prev_avg = $avg;
            }
        }

        $out = array();
        foreach ($buckets as $bucket) {
            $avg = null;
            if ($bucket['overall_count'] > 0) {
                $avg = $bucket['overall_sum'] / $bucket['overall_count'];
            }

            $delta_avg = null;
            if (!empty($bucket['delta_count'])) {
                $delta_avg = (float) $bucket['delta_sum'] / (int) $bucket['delta_count'];
            }

            $questions = array();
            foreach ($bucket['questions'] as $question_key => $agg) {
                if ($agg['count'] <= 0) {
                    continue;
                }
                $questions[$question_key] = $agg['sum'] / $agg['count'];
            }

            $out[] = array(
                'semester_key' => $bucket['semester_key'],
                'semester_label' => $bucket['semester_label'],
                'count' => $bucket['count'],
                'student_count' => count($bucket['students']),
                'overall_avg' => $avg,
                'delta_avg' => $delta_avg,
                'delta_student_count' => isset($bucket['delta_students']) && is_array($bucket['delta_students'])
                    ? count($bucket['delta_students'])
                    : 0,
                'question_avgs' => $questions,
            );
        }

        return $out;
    }

    private static function build_group_radar_bucket_avgs($posts, $bucket_type)
    {
        $groups = array();
        $radar = self::get_radar_question_labels_and_options();
        $order = isset($radar['order']) && is_array($radar['order']) ? $radar['order'] : array();

        // For each bucket, we want the latest assessment per student in that bucket.
        foreach ($posts as $post) {
            $ts = self::get_assessment_effective_date($post);

            $bucket_key = '';
            $bucket_label = '';
            if ($bucket_type === 'month') {
                $bucket_key = gmdate('Y-m', $ts);
                $bucket_label = date_i18n('M Y', strtotime($bucket_key . '-01'));
            } elseif ($bucket_type === 'school_year') {
                $bucket_key = (string) self::get_school_year_start_from_timestamp($ts);
                $bucket_label = self::format_school_year_label($bucket_key);
            } elseif ($bucket_type === 'hogstadium') {
                $bucket_key = self::get_hogstadium_key_from_timestamp($ts);
                $bucket_label = self::format_hogstadium_label($bucket_key);
            } else {
                $bucket_key = self::get_term_key_from_timestamp($ts);
                $bucket_label = self::format_term_label($bucket_key);
            }

            if (!isset($groups[$bucket_key])) {
                $groups[$bucket_key] = array(
                    'key' => $bucket_key,
                    'label' => $bucket_label,
                    'latest_by_student' => array(),
                );
            }

            $sid = (int) get_post_meta($post->ID, HAM_ASSESSMENT_META_STUDENT_ID, true);
            if ($sid <= 0) {
                continue;
            }

            $existing = isset($groups[$bucket_key]['latest_by_student'][$sid]) ? $groups[$bucket_key]['latest_by_student'][$sid] : null;
            if ($existing && isset($existing['ts']) && (int) $existing['ts'] >= (int) $ts) {
                continue;
            }

            $assessment_data = get_post_meta($post->ID, HAM_ASSESSMENT_META_DATA, true);
            $scores = self::extract_scores_from_assessment_data($assessment_data);

            $groups[$bucket_key]['latest_by_student'][$sid] = array(
                'ts' => $ts,
                'question_values' => isset($scores['question_values']) ? $scores['question_values'] : array(),
            );
        }

        ksort($groups);

        $out = array();
        foreach ($groups as $bucket) {
            $sums = array();
            $counts = array();
            foreach ($order as $qk) {
                $sums[$qk] = 0.0;
                $counts[$qk] = 0;
            }

            $student_count = 0;
            foreach ($bucket['latest_by_student'] as $sid => $item) {
                $student_count++;
                foreach ($order as $qk) {
                    $val = null;
                    if (isset($item['question_values'][$qk]) && is_array($item['question_values'][$qk]) && count($item['question_values'][$qk]) > 0) {
                        $val = $item['question_values'][$qk][0];
                    }
                    if ($val === '' || $val === null) {
                        continue;
                    }
                    if (!is_numeric($val)) {
                        continue;
                    }

                    $sums[$qk] += (float) $val;
                    $counts[$qk]++;
                }
            }

            $values = array();
            foreach ($order as $qk) {
                if (!empty($counts[$qk])) {
                    $values[] = $sums[$qk] / $counts[$qk];
                } else {
                    $values[] = null;
                }
            }

            $out[] = array(
                'key' => $bucket['key'],
                'label' => $bucket['label'],
                'student_count' => $student_count,
                'values' => $values,
            );
        }

        return $out;
    }

    private static function build_group_radar_bucket_counts($posts, $bucket_type, $threshold = 3)
    {
        $groups = array();
        $radar = self::get_radar_question_labels_and_options();
        $order = isset($radar['order']) && is_array($radar['order']) ? $radar['order'] : array();

        // For each bucket, we want the latest assessment per student in that bucket.
        foreach ($posts as $post) {
            $ts = self::get_assessment_effective_date($post);

            $bucket_key = '';
            $bucket_label = '';
            if ($bucket_type === 'month') {
                $bucket_key = gmdate('Y-m', $ts);
                $bucket_label = date_i18n('M Y', strtotime($bucket_key . '-01'));
            } elseif ($bucket_type === 'school_year') {
                $bucket_key = (string) self::get_school_year_start_from_timestamp($ts);
                $bucket_label = self::format_school_year_label($bucket_key);
            } elseif ($bucket_type === 'hogstadium') {
                $bucket_key = self::get_hogstadium_key_from_timestamp($ts);
                $bucket_label = self::format_hogstadium_label($bucket_key);
            } else {
                $bucket_key = self::get_term_key_from_timestamp($ts);
                $bucket_label = self::format_term_label($bucket_key);
            }

            if (!isset($groups[$bucket_key])) {
                $groups[$bucket_key] = array(
                    'key' => $bucket_key,
                    'label' => $bucket_label,
                    'latest_by_student' => array(),
                );
            }

            $sid = (int) get_post_meta($post->ID, HAM_ASSESSMENT_META_STUDENT_ID, true);
            if ($sid <= 0) {
                continue;
            }

            $existing = isset($groups[$bucket_key]['latest_by_student'][$sid]) ? $groups[$bucket_key]['latest_by_student'][$sid] : null;
            if ($existing && isset($existing['ts']) && (int) $existing['ts'] >= (int) $ts) {
                continue;
            }

            $assessment_data = get_post_meta($post->ID, HAM_ASSESSMENT_META_DATA, true);
            $scores = self::extract_scores_from_assessment_data($assessment_data);

            $groups[$bucket_key]['latest_by_student'][$sid] = array(
                'ts' => $ts,
                'question_values' => isset($scores['question_values']) ? $scores['question_values'] : array(),
            );
        }

        ksort($groups);

        $out = array();
        foreach ($groups as $bucket) {
            $counts = array();
            foreach ($order as $qk) {
                $counts[$qk] = 0;
            }

            $student_count = 0;
            foreach ($bucket['latest_by_student'] as $sid => $item) {
                $student_count++;
                foreach ($order as $qk) {
                    $val = null;
                    if (isset($item['question_values'][$qk]) && is_array($item['question_values'][$qk]) && count($item['question_values'][$qk]) > 0) {
                        $val = (float) $item['question_values'][$qk][0];
                    }
                    if ($val !== null && $val >= $threshold) {
                        $counts[$qk]++;
                    }
                }
            }

            $values = array();
            foreach ($order as $qk) {
                $values[] = isset($counts[$qk]) ? (int) $counts[$qk] : 0;
            }

            $out[] = array(
                'key' => $bucket['key'],
                'label' => $bucket['label'],
                'student_count' => $student_count,
                'values' => $values,
            );
        }

        return $out;
    }

    private static function get_school_year_start_from_timestamp($ts)
    {
        $month_num = (int) gmdate('n', $ts);
        $year_num = (int) gmdate('Y', $ts);

        if ($month_num >= 8) {
            return $year_num;
        }

        if ($month_num <= 6) {
            return $year_num - 1;
        }

        return $year_num;
    }

    private static function get_term_key_from_timestamp($ts)
    {
        $month_num = (int) gmdate('n', $ts);
        $school_year_start = self::get_school_year_start_from_timestamp($ts);
        $term_code = ($month_num >= 8 || $month_num === 7) ? 'HT' : 'VT';
        return sprintf('%04d-%s', $school_year_start, $term_code);
    }

    private static function format_school_year_label($school_year_start)
    {
        $school_year_start = (int) $school_year_start;
        return sprintf('%d/%02d', $school_year_start, ($school_year_start + 1) % 100);
    }

    private static function format_term_label($term_key)
    {
        if (!is_string($term_key) || strpos($term_key, '-') === false) {
            return (string) $term_key;
        }

        list($school_year_start, $term_code) = explode('-', $term_key, 2);
        $school_year_start = (int) $school_year_start;
        $school_year_label = self::format_school_year_label($school_year_start);
        $term_label = ($term_code === 'VT') ? __('Spring term', 'headless-access-manager') : __('Autumn term', 'headless-access-manager');
        return sprintf('%s %s', $term_label, $school_year_label);
    }

    private static function get_hogstadium_key_from_timestamp($ts)
    {
        $school_year_start = self::get_school_year_start_from_timestamp($ts);
        $base = (int) (floor($school_year_start / 3) * 3);
        return (string) $base;
    }

    private static function format_hogstadium_label($hog_key)
    {
        $start = (int) $hog_key;
        $end = $start + 2;
        return self::format_school_year_label($start) . '–' . self::format_school_year_label($end);
    }

    private static function aggregate_evaluations_overall_by_bucket($posts, $bucket_type)
    {
        $buckets = array();

        foreach ($posts as $post) {
            $student_id = (int) get_post_meta($post->ID, HAM_ASSESSMENT_META_STUDENT_ID, true);
            if ($student_id <= 0) {
                continue;
            }

            $assessment_data = get_post_meta($post->ID, HAM_ASSESSMENT_META_DATA, true);
            $scores = self::extract_scores_from_assessment_data($assessment_data);
            if ($scores['overall_avg'] === null) {
                continue;
            }

            $ts = self::get_assessment_effective_date($post);

            $key = '';
            $label = '';
            if ($bucket_type === 'month') {
                $key = gmdate('Y-m', $ts);
                $label = date_i18n('M Y', strtotime($key . '-01'));
            } elseif ($bucket_type === 'school_year') {
                $key = (string) self::get_school_year_start_from_timestamp($ts);
                $label = self::format_school_year_label($key);
            } elseif ($bucket_type === 'hogstadium') {
                $key = self::get_hogstadium_key_from_timestamp($ts);
                $label = self::format_hogstadium_label($key);
            } else {
                $key = self::get_term_key_from_timestamp($ts);
                $label = self::format_term_label($key);
            }

            if (!isset($buckets[$key])) {
                $buckets[$key] = array(
                    'key' => $key,
                    'label' => $label,
                    'sum' => 0.0,
                    'count' => 0,
                );
            }

            $buckets[$key]['sum'] += (float) $scores['overall_avg'];
            $buckets[$key]['count']++;
        }

        ksort($buckets);

        $out = array();
        foreach ($buckets as $bucket) {
            $avg = null;
            if (!empty($bucket['count'])) {
                $avg = $bucket['sum'] / $bucket['count'];
            }
            $out[] = array(
                'key' => $bucket['key'],
                'label' => $bucket['label'],
                'overall_avg' => $avg,
                'count' => (int) $bucket['count'],
            );
        }

        return $out;
    }

    private static function get_canonical_questions_structure()
    {
        if (!defined('HAM_ASSESSMENT_DEFAULT_STRUCTURE')) {
            $path = dirname(__FILE__, 2) . '/assessment-constants.php';
            if (file_exists($path)) {
                require_once $path;
            }
        }

        if (defined('HAM_ASSESSMENT_DEFAULT_STRUCTURE') && is_array(HAM_ASSESSMENT_DEFAULT_STRUCTURE)) {
            return HAM_ASSESSMENT_DEFAULT_STRUCTURE;
        }

        return self::get_questions_structure();
    }

    private static function get_radar_question_labels_and_options()
    {
        $source = 'db';
        $structure = self::get_question_bank_structure();
        if (empty($structure) || !is_array($structure)) {
            $source = 'fallback';
            $structure = self::get_canonical_questions_structure();
        }

        if (empty($structure) || !is_array($structure)) {
            return array(
                'order' => array(),
                'labels' => array(),
                'questions' => array(),
                'source' => $source,
            );
        }

        $order = array();
        $labels = array();
        $questions = array();

        foreach (array('anknytning', 'ansvar') as $section_key) {
            if (!isset($structure[$section_key]['questions']) || !is_array($structure[$section_key]['questions'])) {
                continue;
            }

            foreach ($structure[$section_key]['questions'] as $question_id => $question) {
                $qk = $section_key . '_' . $question_id;
                $order[] = $qk;
                $labels[] = isset($question['text']) ? $question['text'] : $qk;

                $opts = array();
                if (isset($question['options']) && is_array($question['options'])) {
                    foreach ($question['options'] as $opt) {
                        if (isset($opt['label'])) {
                            $opts[] = $opt['label'];
                        }
                    }
                }

                $questions[] = array(
                    'key' => $qk,
                    'section' => isset($structure[$section_key]['title']) ? $structure[$section_key]['title'] : ucfirst($section_key),
                    'text' => isset($question['text']) ? $question['text'] : $question_id,
                    'options' => $opts,
                );
            }
        }

        return array(
            'order' => $order,
            'labels' => $labels,
            'questions' => $questions,
            'source' => $source,
        );
    }

    private static function build_student_radar_bucket($posts, $bucket_type)
    {
        $groups = array();

        foreach ($posts as $post) {
            $assessment_data = get_post_meta($post->ID, HAM_ASSESSMENT_META_DATA, true);
            $scores = self::extract_scores_from_assessment_data($assessment_data);

            $ts = self::get_assessment_effective_date($post);

            $bucket_key = '';
            $bucket_label = '';
            if ($bucket_type === 'month') {
                $bucket_key = gmdate('Y-m', $ts);
                $bucket_label = date_i18n('M Y', strtotime($bucket_key . '-01'));
            } elseif ($bucket_type === 'school_year') {
                $bucket_key = (string) self::get_school_year_start_from_timestamp($ts);
                $bucket_label = self::format_school_year_label($bucket_key);
            } elseif ($bucket_type === 'hogstadium') {
                $bucket_key = self::get_hogstadium_key_from_timestamp($ts);
                $bucket_label = self::format_hogstadium_label($bucket_key);
            } else {
                $bucket_key = self::get_term_key_from_timestamp($ts);
                $bucket_label = self::format_term_label($bucket_key);
            }

            if (!isset($groups[$bucket_key])) {
                $groups[$bucket_key] = array(
                    'key' => $bucket_key,
                    'label' => $bucket_label,
                    'items' => array(),
                );
            }

            $groups[$bucket_key]['items'][] = array(
                'ts' => $ts,
                'post_id' => $post->ID,
                'overall_avg' => $scores['overall_avg'],
                'question_values' => $scores['question_values'],
            );
        }

        ksort($groups);

        $radar = self::get_radar_question_labels_and_options();
        $order = $radar['order'];

        $out = array();
        foreach ($groups as $bucket) {
            usort($bucket['items'], function($a, $b) {
                return ($a['ts'] ?? 0) <=> ($b['ts'] ?? 0);
            });

            // Keep only the most recent evaluations (max 4), while preserving chronological order.
            if (count($bucket['items']) > 4) {
                $bucket['items'] = array_slice($bucket['items'], -4);
            }

            $datasets = array();
            $index = 1;
            foreach ($bucket['items'] as $item) {
                if ($index > 4) {
                    break;
                }

                $dataset_label = sprintf(__('Evaluation %d', 'headless-access-manager'), $index);
                $post_obj = isset($item['post_id']) ? get_post((int) $item['post_id']) : null;
                if ($post_obj instanceof WP_Post) {
                    $author_name = '';
                    if (!empty($post_obj->post_author)) {
                        $author_user = get_user_by('id', (int) $post_obj->post_author);
                        if ($author_user) {
                            $author_name = $author_user->display_name;
                        }
                    }

                    $date_label = date_i18n(get_option('date_format'), (int) ($item['ts'] ?? 0));
                    if ($date_label && $author_name) {
                        $dataset_label = $date_label . ' — ' . $author_name;
                    } elseif ($date_label) {
                        $dataset_label = $date_label;
                    }
                }

                $values = array();
                foreach ($order as $qk) {
                    $val = null;
                    if (isset($item['question_values'][$qk]) && is_array($item['question_values'][$qk]) && count($item['question_values'][$qk]) > 0) {
                        $val = (float) $item['question_values'][$qk][0];
                    }
                    $values[] = $val;
                }

                $datasets[] = array(
                    'label' => $dataset_label,
                    'post_id' => (int) $item['post_id'],
                    'overall_avg' => $item['overall_avg'],
                    'values' => $values,
                );

                $index++;
            }

            $out[] = array(
                'key' => $bucket['key'],
                'label' => $bucket['label'],
                'datasets' => $datasets,
            );
        }

        return $out;
    }

    public static function get_assessment_drilldown_stats($school_id = 0, $class_id = 0, $student_id = 0)
    {
        $question_labels = self::get_question_labels_map();

        $view = array(
            'level' => 'schools',
            'breadcrumb' => array(),
            'question_labels' => $question_labels,
            'radar_questions' => array(),
            'student_radar' => array(),
            'group_radar' => array(),
            'avg_progress' => array(),
            'schools' => array(),
            'classes' => array(),
            'students' => array(),
            'student' => null,
            'series' => array(),
            'top_questions' => array(),
        );

        $radar_meta = self::get_radar_question_labels_and_options();
        $view['radar_questions'] = isset($radar_meta['questions']) ? $radar_meta['questions'] : array();
        $view['radar_questions_source'] = isset($radar_meta['source']) ? $radar_meta['source'] : 'unknown';

        if ($student_id > 0) {
            $view['level'] = 'student';
        } elseif ($class_id > 0) {
            $view['level'] = 'class';
        } elseif ($school_id > 0) {
            $view['level'] = 'school';
        }

        if ($school_id > 0) {
            $school_post = get_post($school_id);
            if ($school_post && $school_post->post_type === HAM_CPT_SCHOOL) {
                $view['breadcrumb'][] = array(
                    'label' => $school_post->post_title,
                    'url' => admin_url('admin.php?page=ham-assessment-stats&school_id=' . $school_id),
                );
            } else {
                $school_id = 0;
                $view['level'] = 'schools';
            }
        }

        if ($class_id > 0) {
            $class_post = get_post($class_id);
            if ($class_post && $class_post->post_type === HAM_CPT_CLASS) {
                if ($school_id > 0) {
                    $class_school = (int) get_post_meta($class_id, '_ham_school_id', true);
                    if ($class_school !== $school_id) {
                        $class_id = 0;
                    }
                }

                if ($class_id > 0) {
                    $view['breadcrumb'][] = array(
                        'label' => $class_post->post_title,
                        'url' => admin_url('admin.php?page=ham-assessment-stats&school_id=' . $school_id . '&class_id=' . $class_id),
                    );
                }
            } else {
                $class_id = 0;
            }
        }

        if ($student_id > 0) {
            $student_post = get_post($student_id);
            if ($student_post && $student_post->post_type === HAM_CPT_STUDENT) {
                if ($school_id > 0) {
                    $student_school = (int) get_post_meta($student_id, '_ham_school_id', true);
                    if ($student_school !== $school_id) {
                        $student_id = 0;
                    }
                }

                if ($class_id > 0) {
                    $student_ids_in_class = get_post_meta($class_id, '_ham_student_ids', true);
                    $student_ids_in_class = is_array($student_ids_in_class) ? $student_ids_in_class : array();
                    if (!in_array($student_id, $student_ids_in_class, true)) {
                        $student_id = 0;
                    }
                }

                if ($student_id > 0) {
                    $view['breadcrumb'][] = array(
                        'label' => $student_post->post_title,
                        'url' => admin_url('admin.php?page=ham-assessment-stats&school_id=' . $school_id . '&class_id=' . $class_id . '&student_id=' . $student_id),
                    );
                }
            } else {
                $student_id = 0;
            }
        }

        if ($view['level'] === 'schools') {
            $schools = get_posts(array(
                'post_type'      => HAM_CPT_SCHOOL,
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'orderby'        => 'title',
                'order'          => 'ASC',
            ));

            $all_evals = self::fetch_evaluation_posts();
            $school_to_students = array();

            foreach ($all_evals as $eval_post) {
                $sid = (int) get_post_meta($eval_post->ID, HAM_ASSESSMENT_META_STUDENT_ID, true);
                if ($sid <= 0) {
                    continue;
                }
                $student_school = (int) get_post_meta($sid, '_ham_school_id', true);
                if ($student_school <= 0) {
                    continue;
                }
                if (!isset($school_to_students[$student_school])) {
                    $school_to_students[$student_school] = array();
                }
                $school_to_students[$student_school][$sid] = true;
            }

            foreach ($schools as $school) {
                $students_map = isset($school_to_students[$school->ID]) ? $school_to_students[$school->ID] : array();
                $student_ids = array_keys($students_map);

                $stage_rollup = self::calculate_latest_stage_counts_for_students($student_ids);
                $stage_counts = isset($stage_rollup['counts']) ? $stage_rollup['counts'] : array('not' => 0, 'trans' => 0, 'full' => 0);

                $class_count = 0;
                $classes = get_posts(array(
                    'post_type'      => HAM_CPT_CLASS,
                    'posts_per_page' => -1,
                    'post_status'    => 'publish',
                    'fields'         => 'ids',
                    'meta_query'     => array(
                        array(
                            'key'     => '_ham_school_id',
                            'value'   => $school->ID,
                            'compare' => '=',
                        ),
                    ),
                ));
                $class_count = is_array($classes) ? count($classes) : 0;

                $series = empty($student_ids) ? array() : self::aggregate_evaluations_by_semester(self::fetch_evaluation_posts($student_ids));
                $total_evals = 0;
                $overall_sum = 0.0;
                $overall_count = 0;
                foreach ($series as $bucket) {
                    $total_evals += (int) $bucket['count'];
                    if ($bucket['overall_avg'] !== null) {
                        $overall_sum += (float) $bucket['overall_avg'];
                        $overall_count++;
                    }
                }
                $overall_avg = $overall_count > 0 ? ($overall_sum / $overall_count) : null;

                $view['schools'][] = array(
                    'id' => $school->ID,
                    'name' => $school->post_title,
                    'class_count' => $class_count,
                    'student_count' => count($student_ids),
                    'evaluation_count' => $total_evals,
                    'stage_counts' => $stage_counts,
                    'series' => $series,
                    'url' => admin_url('admin.php?page=ham-assessment-stats&school_id=' . $school->ID),
                );
            }

            // Overview radar (root view): overall average score per question (1-5) across all schools.
            $build_overview_group_bucket = function($bucket_type) use ($all_evals) {
                $series = self::build_group_radar_bucket_avgs($all_evals, $bucket_type);
                $out = array();

                $count = is_array($series) ? count($series) : 0;
                for ($i = 0; $i < $count; $i++) {
                    $b = $series[$i];
                    $prev = ($i > 0) ? $series[$i - 1] : null;

                    $n = isset($b['student_count']) ? (int) $b['student_count'] : 0;

                    $datasets = array(
                        array(
                            'label' => $n > 0
                                ? sprintf(__('%1$s (%2$d)', 'headless-access-manager'), __('All schools', 'headless-access-manager'), $n)
                                : __('All schools', 'headless-access-manager'),
                            'values' => isset($b['values']) ? $b['values'] : array(),
                            'student_count' => $n,
                        ),
                    );

                    if (is_array($prev)) {
                        $prev_n = isset($prev['student_count']) ? (int) $prev['student_count'] : 0;
                        $prev_label = isset($prev['label']) ? (string) $prev['label'] : '';
                        $datasets[] = array(
                            'label' => $prev_n > 0
                                ? sprintf(__('%1$s (%2$d)', 'headless-access-manager'), $prev_label ? sprintf(__('Previous: %s', 'headless-access-manager'), $prev_label) : __('Previous', 'headless-access-manager'), $prev_n)
                                : ($prev_label ? sprintf(__('Previous: %s', 'headless-access-manager'), $prev_label) : __('Previous', 'headless-access-manager')),
                            'values' => isset($prev['values']) ? $prev['values'] : array(),
                            'student_count' => $prev_n,
                        );
                    }

                    $out[] = array(
                        'key' => isset($b['key']) ? (string) $b['key'] : '',
                        'label' => isset($b['label']) ? $b['label'] : (isset($b['key']) ? (string) $b['key'] : ''),
                        'datasets' => $datasets,
                    );
                }

                return $out;
            };

            $view['group_radar'] = array(
                'mode' => 'avg',
                'labels' => isset($radar_meta['labels']) ? $radar_meta['labels'] : array(),
                'buckets' => array(
                    'month' => $build_overview_group_bucket('month'),
                    'term' => $build_overview_group_bucket('term'),
                    'school_year' => $build_overview_group_bucket('school_year'),
                    'hogstadium' => $build_overview_group_bucket('hogstadium'),
                ),
            );

            return $view;
        }

        if ($view['level'] === 'school') {
            $classes = get_posts(array(
                'post_type'      => HAM_CPT_CLASS,
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'orderby'        => 'title',
                'order'          => 'ASC',
                'meta_query'     => array(
                    array(
                        'key'     => '_ham_school_id',
                        'value'   => $school_id,
                        'compare' => '=',
                    ),
                ),
            ));

            $school_student_posts = get_posts(array(
                'post_type'      => HAM_CPT_STUDENT,
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'fields'         => 'ids',
                'meta_query'     => array(
                    array(
                        'key'     => '_ham_school_id',
                        'value'   => $school_id,
                        'compare' => '=',
                    ),
                ),
            ));
            $school_student_ids = is_array($school_student_posts) ? array_map('absint', $school_student_posts) : array();

            $view['series'] = empty($school_student_ids) ? array() : self::aggregate_evaluations_by_semester(self::fetch_evaluation_posts($school_student_ids));

            $school_posts = empty($school_student_ids) ? array() : self::fetch_evaluation_posts($school_student_ids);
            $view['avg_progress'] = array(
                'month' => self::aggregate_evaluations_overall_by_bucket($school_posts, 'month'),
                'term' => self::aggregate_evaluations_overall_by_bucket($school_posts, 'term'),
                'school_year' => self::aggregate_evaluations_overall_by_bucket($school_posts, 'school_year'),
                'hogstadium' => self::aggregate_evaluations_overall_by_bucket($school_posts, 'hogstadium'),
            );

            // Group radar: School vs All (baseline), average score per question (1-5) based on latest assessment per student per bucket.
            $all_posts = self::fetch_evaluation_posts();
            $build_group_bucket = function($bucket_type) use ($school_posts, $all_posts) {
                $school_series = self::build_group_radar_bucket_avgs($school_posts, $bucket_type);
                $all_series = self::build_group_radar_bucket_avgs($all_posts, $bucket_type);

                $school_by_key = array();
                foreach ($school_series as $b) {
                    $school_by_key[$b['key']] = $b;
                }
                $all_by_key = array();
                foreach ($all_series as $b) {
                    $all_by_key[$b['key']] = $b;
                }

                $keys = array_unique(array_merge(array_keys($school_by_key), array_keys($all_by_key)));
                sort($keys);

                $out = array();
                foreach ($keys as $k) {
                    $label = isset($school_by_key[$k]['label']) ? $school_by_key[$k]['label'] : (isset($all_by_key[$k]['label']) ? $all_by_key[$k]['label'] : $k);
                    $school_values = isset($school_by_key[$k]['values']) ? $school_by_key[$k]['values'] : array();
                    $all_values = isset($all_by_key[$k]['values']) ? $all_by_key[$k]['values'] : array();
                    $school_n = isset($school_by_key[$k]['student_count']) ? (int) $school_by_key[$k]['student_count'] : 0;
                    $all_n = isset($all_by_key[$k]['student_count']) ? (int) $all_by_key[$k]['student_count'] : 0;

                    $out[] = array(
                        'key' => $k,
                        'label' => $label,
                        'datasets' => array(
                            array(
                                'label' => __('This school', 'headless-access-manager'),
                                'values' => $school_values,
                                'student_count' => $school_n,
                            ),
                            array(
                                'label' => $all_n > 0
                                    ? sprintf(__('All schools (%d)', 'headless-access-manager'), $all_n)
                                    : __('All schools', 'headless-access-manager'),
                                'values' => $all_values,
                                'student_count' => $all_n,
                            ),
                        ),
                    );
                }

                return $out;
            };

            $view['group_radar'] = array(
                'mode' => 'avg',
                'labels' => isset($radar_meta['labels']) ? $radar_meta['labels'] : array(),
                'buckets' => array(
                    'month' => $build_group_bucket('month'),
                    'term' => $build_group_bucket('term'),
                    'school_year' => $build_group_bucket('school_year'),
                    'hogstadium' => $build_group_bucket('hogstadium'),
                ),
            );

            foreach ($classes as $class_post) {
                $class_student_ids = get_post_meta($class_post->ID, '_ham_student_ids', true);
                $class_student_ids = is_array($class_student_ids) ? array_map('absint', $class_student_ids) : array();

                $stage_rollup = self::calculate_latest_stage_counts_for_students($class_student_ids);
                $stage_counts = isset($stage_rollup['counts']) ? $stage_rollup['counts'] : array('not' => 0, 'trans' => 0, 'full' => 0);
                $series = empty($class_student_ids) ? array() : self::aggregate_evaluations_by_semester(self::fetch_evaluation_posts($class_student_ids));

                $total_evals = 0;
                $overall_sum = 0.0;
                $overall_count = 0;
                foreach ($series as $bucket) {
                    $total_evals += (int) $bucket['count'];
                    if ($bucket['overall_avg'] !== null) {
                        $overall_sum += (float) $bucket['overall_avg'];
                        $overall_count++;
                    }
                }
                $overall_avg = $overall_count > 0 ? ($overall_sum / $overall_count) : null;

                $view['classes'][] = array(
                    'id' => $class_post->ID,
                    'name' => $class_post->post_title,
                    'student_count' => count($class_student_ids),
                    'evaluation_count' => $total_evals,
                    'stage_counts' => $stage_counts,
                    'series' => $series,
                    'url' => admin_url('admin.php?page=ham-assessment-stats&school_id=' . $school_id . '&class_id=' . $class_post->ID),
                );
            }

            return $view;
        }

        if ($view['level'] === 'class') {
            $student_ids = get_post_meta($class_id, '_ham_student_ids', true);
            $student_ids = is_array($student_ids) ? array_map('absint', $student_ids) : array();

            $view['series'] = empty($student_ids) ? array() : self::aggregate_evaluations_by_semester(self::fetch_evaluation_posts($student_ids));

            $class_posts = empty($student_ids) ? array() : self::fetch_evaluation_posts($student_ids);
            $view['avg_progress'] = array(
                'month' => self::aggregate_evaluations_overall_by_bucket($class_posts, 'month'),
                'term' => self::aggregate_evaluations_overall_by_bucket($class_posts, 'term'),
                'school_year' => self::aggregate_evaluations_overall_by_bucket($class_posts, 'school_year'),
                'hogstadium' => self::aggregate_evaluations_overall_by_bucket($class_posts, 'hogstadium'),
            );

            // Group radar: Class vs School, average score per question (1-5) based on latest assessment per student per bucket.
            $school_posts = array();
            if ($school_id > 0) {
                $school_student_posts = get_posts(array(
                    'post_type'      => HAM_CPT_STUDENT,
                    'posts_per_page' => -1,
                    'post_status'    => 'publish',
                    'fields'         => 'ids',
                    'meta_query'     => array(
                        array(
                            'key'     => '_ham_school_id',
                            'value'   => $school_id,
                            'compare' => '=',
                        ),
                    ),
                ));
                $school_student_ids = is_array($school_student_posts) ? array_map('absint', $school_student_posts) : array();
                $school_posts = empty($school_student_ids) ? array() : self::fetch_evaluation_posts($school_student_ids);
            }

            $build_group_bucket = function($bucket_type) use ($class_posts, $school_posts) {
                $class_series = self::build_group_radar_bucket_avgs($class_posts, $bucket_type);
                $school_series = self::build_group_radar_bucket_avgs($school_posts, $bucket_type);

                $class_by_key = array();
                foreach ($class_series as $b) {
                    $class_by_key[$b['key']] = $b;
                }
                $school_by_key = array();
                foreach ($school_series as $b) {
                    $school_by_key[$b['key']] = $b;
                }

                $keys = array_unique(array_merge(array_keys($class_by_key), array_keys($school_by_key)));
                sort($keys);

                $out = array();
                foreach ($keys as $k) {
                    $label = isset($class_by_key[$k]['label']) ? $class_by_key[$k]['label'] : (isset($school_by_key[$k]['label']) ? $school_by_key[$k]['label'] : $k);
                    $class_values = isset($class_by_key[$k]['values']) ? $class_by_key[$k]['values'] : array();
                    $school_values = isset($school_by_key[$k]['values']) ? $school_by_key[$k]['values'] : array();
                    $class_n = isset($class_by_key[$k]['student_count']) ? (int) $class_by_key[$k]['student_count'] : 0;
                    $school_n = isset($school_by_key[$k]['student_count']) ? (int) $school_by_key[$k]['student_count'] : 0;

                    $out[] = array(
                        'key' => $k,
                        'label' => $label,
                        'datasets' => array(
                            array(
                                'label' => $class_n > 0
                                    ? sprintf(_n('Class: %d student', 'Class: %d students', $class_n, 'headless-access-manager'), $class_n)
                                    : __('Class', 'headless-access-manager'),
                                'values' => $class_values,
                                'student_count' => $class_n,
                            ),
                            array(
                                'label' => $school_n > 0
                                    ? sprintf(_n('School: %d student', 'School: %d students', $school_n, 'headless-access-manager'), $school_n)
                                    : __('School', 'headless-access-manager'),
                                'values' => $school_values,
                                'student_count' => $school_n,
                            ),
                        ),
                    );
                }

                return $out;
            };

            $view['group_radar'] = array(
                'mode' => 'avg',
                'labels' => isset($radar_meta['labels']) ? $radar_meta['labels'] : array(),
                'buckets' => array(
                    'month' => $build_group_bucket('month'),
                    'term' => $build_group_bucket('term'),
                    'school_year' => $build_group_bucket('school_year'),
                    'hogstadium' => $build_group_bucket('hogstadium'),
                ),
            );

            $students = empty($student_ids) ? array() : get_posts(array(
                'post_type'      => HAM_CPT_STUDENT,
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'post__in'       => $student_ids,
                'orderby'        => 'title',
                'order'          => 'ASC',
            ));

            foreach ($students as $student_post) {
                $series = self::aggregate_evaluations_by_semester(self::fetch_evaluation_posts(array($student_post->ID)));
                $total_evals = 0;
                $overall_sum = 0.0;
                $overall_count = 0;
                foreach ($series as $bucket) {
                    $total_evals += (int) $bucket['count'];
                    if ($bucket['overall_avg'] !== null) {
                        $overall_sum += (float) $bucket['overall_avg'];
                        $overall_count++;
                    }
                }
                $overall_avg = $overall_count > 0 ? ($overall_sum / $overall_count) : null;

                $stage_rollup = self::calculate_latest_stage_counts_for_students(array($student_post->ID));
                $by_student = isset($stage_rollup['by_student']) ? $stage_rollup['by_student'] : array();
                $latest_stage = isset($by_student[$student_post->ID]) ? $by_student[$student_post->ID] : 'not';

                $view['students'][] = array(
                    'id' => $student_post->ID,
                    'name' => $student_post->post_title,
                    'evaluation_count' => $total_evals,
                    'stage' => $latest_stage,
                    'series' => $series,
                    'url' => admin_url('admin.php?page=ham-assessment-stats&school_id=' . $school_id . '&class_id=' . $class_id . '&student_id=' . $student_post->ID),
                );
            }

            return $view;
        }

        if ($view['level'] === 'student') {
            $student_post = get_post($student_id);
            if ($student_post && $student_post->post_type === HAM_CPT_STUDENT) {
                $view['student'] = array(
                    'id' => $student_post->ID,
                    'name' => $student_post->post_title,
                );
            }

            $posts = self::fetch_evaluation_posts(array($student_id));
            $view['series'] = self::aggregate_evaluations_by_semester($posts);

            $view['avg_progress'] = array(
                'month' => self::aggregate_evaluations_overall_by_bucket($posts, 'month'),
                'term' => self::aggregate_evaluations_overall_by_bucket($posts, 'term'),
                'school_year' => self::aggregate_evaluations_overall_by_bucket($posts, 'school_year'),
                'hogstadium' => self::aggregate_evaluations_overall_by_bucket($posts, 'hogstadium'),
            );

            $view['student_radar'] = array(
                'labels' => isset($radar_meta['labels']) ? $radar_meta['labels'] : array(),
                'buckets' => array(
                    'month' => self::build_student_radar_bucket($posts, 'month'),
                    'term' => self::build_student_radar_bucket($posts, 'term'),
                    'school_year' => self::build_student_radar_bucket($posts, 'school_year'),
                    'hogstadium' => self::build_student_radar_bucket($posts, 'hogstadium'),
                ),
            );

            $question_order = self::get_question_order();
            $seen_questions = array();
            foreach ($view['series'] as $bucket) {
                foreach ($bucket['question_avgs'] as $qk => $avg) {
                    $seen_questions[$qk] = true;
                }
            }

            $ordered_questions = array();
            foreach ($question_order as $qk) {
                if (isset($seen_questions[$qk])) {
                    $ordered_questions[] = $qk;
                    unset($seen_questions[$qk]);
                }
            }

            if (!empty($seen_questions)) {
                $extra = array_keys($seen_questions);
                sort($extra);
                $ordered_questions = array_merge($ordered_questions, $extra);
            }

            $view['top_questions'] = $ordered_questions;

            return $view;
        }

        return $view;
    }

    /**
     * Helper function to resolve teacher name and ID from student ID and/or post author
     * This ensures consistent teacher resolution across list and modal views.
     *
     * @param int $student_id The student CPT ID
     * @param int $post_author The post author ID (fallback)
     * @return array Array with teacher_id and teacher_name keys
     */
    public static function resolve_teacher($student_id, $post_author = 0) {
        $teacher_name = esc_html__('Unknown Teacher', 'headless-access-manager');
        $teacher_id = $post_author; // Default to post_author for backward compatibility
        
        // Find all classes the student is part of
        $student_class_ids = array();
        $class_args = array(
            'post_type' => HAM_CPT_CLASS,
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_ham_student_ids',
                    // Search for the student ID as an integer element in the serialized array
                    'value' => 'i:' . $student_id . ';',
                    'compare' => 'LIKE'
                )
            )
        );
        
        $class_query = new WP_Query($class_args);
        
        if ($class_query->have_posts()) {
            while ($class_query->have_posts()) {
                $class_query->the_post();
                $student_class_ids[] = get_the_ID();
            }
            wp_reset_postdata();
        }
        
        // If student is in classes, find teachers assigned to those classes
        if ($teacher_name === esc_html__('Unknown Teacher', 'headless-access-manager') && !empty($student_class_ids)) {
            $teacher_args = array(
                'post_type' => HAM_CPT_TEACHER,
                'posts_per_page' => 1, // Just get the first matching teacher
                'meta_query' => array()
            );
            
            // Build the meta query to find teachers in the same classes as the student
            $meta_query_clauses = array('relation' => 'OR');
            foreach ($student_class_ids as $class_id) {
                $meta_query_clauses[] = array(
                    'key' => '_ham_class_ids',
                    'value' => 'i:' . $class_id . ';',
                    'compare' => 'LIKE'
                );
            }
            $teacher_args['meta_query'] = $meta_query_clauses;
            
            $teacher_query = new WP_Query($teacher_args);
            
            if ($teacher_query->have_posts()) {
                $teacher_query->the_post();
                $teacher_cpt_id = get_the_ID();
                $teacher_name = get_the_title();
                
                // Get the WP user ID associated with this teacher CPT
                $wp_user_id = get_post_meta($teacher_cpt_id, '_ham_user_id', true);
                if (!empty($wp_user_id)) {
                    $teacher_id = $wp_user_id;
                }
                
                wp_reset_postdata();
            }
        }
        
        // Fallback: If no teacher found via class relationship, use post_author
        if ($teacher_name === esc_html__('Unknown Teacher', 'headless-access-manager') && !empty($post_author)) {
            $teacher_name = get_the_author_meta('display_name', $post_author);
            
            // Additional fallback: If author name is empty, try to find teacher CPT by user ID
            if (empty($teacher_name)) {
                $teacher_cpt_args = array(
                    'post_type' => HAM_CPT_TEACHER,
                    'posts_per_page' => 1,
                    'meta_query' => array(
                        array(
                            'key' => '_ham_user_id',
                            'value' => $post_author,
                            'compare' => '='
                        )
                    )
                );
                
                $teacher_cpt_query = new WP_Query($teacher_cpt_args);
                if ($teacher_cpt_query->have_posts()) {
                    $teacher_cpt_query->the_post();
                    $teacher_name = get_the_title();
                    wp_reset_postdata();
                }
            }
        }
        
        return array(
            'teacher_id' => $teacher_id,
            'teacher_name' => $teacher_name
        );
    }
    
    /**
     * Get all assessments with student information.
     *
     * @param string $source Optional. Filter by assessment source ('admin', 'frontend', or 'all'). Default 'all'.
     * @return array Array of assessment data.
     */

    public static function get_assessments($source = 'all')
    {
        $args = array(
            'post_type'      => HAM_CPT_ASSESSMENT,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
            // Only get posts that have a student ID meta value - these are actual assessments
            'meta_query'     => array(
                array(
                    'key'     => HAM_ASSESSMENT_META_STUDENT_ID,
                    'value'   => '',
                    'compare' => '!=',
                ),
            ),
        );

        // Add source filter if specified
        if ($source !== 'all') {
            // Add a meta query to check for the source flag
            if ($source === 'admin') {
                // Admin templates either have the admin flag or don't have any source flag
                $args['meta_query'][] = array(
                    'relation' => 'OR',
                    array(
                        'key'     => '_ham_assessment_source',
                        'value'   => 'admin',
                        'compare' => '=',
                    ),
                    array(
                        'key'     => '_ham_assessment_source',
                        'compare' => 'NOT EXISTS',
                    ),
                );
            } elseif ($source === 'frontend') {
                // Frontend submissions have the frontend source flag
                $args['meta_query'][] = array(
                    'key'     => '_ham_assessment_source',
                    'value'   => 'frontend',
                    'compare' => '=',
                );
            }
        }

        // Debug: Log the query arguments
        //error_log('Assessment query args: ' . print_r($args, true));

        $assessments = array();
        $posts = get_posts($args);

        // Debug: Log the number of posts found
        //error_log('Number of assessment posts found: ' . count($posts));

        foreach ($posts as $post) {
            $student_id = get_post_meta($post->ID, HAM_ASSESSMENT_META_STUDENT_ID, true);

            // Debug: Log the post and student ID
            //error_log('Processing post ID: ' . $post->ID . ', Student ID: ' . $student_id);

            // Skip posts without a student ID (likely templates)
            if (empty($student_id)) {
                //error_log('Skipping post ' . $post->ID . ' - no student ID');
                continue;
            }
            
            // Student is a CPT, not a WordPress user - get the student name from the CPT
            $student_post = get_post($student_id);
            $student_name = $student_post ? $student_post->post_title : esc_html__('Unknown Student', 'headless-access-manager');

            // Class + School (stored on the student CPT).
            $class_ids = $student_post ? get_post_meta($student_post->ID, '_ham_class_ids', true) : array();
            $class_ids = is_array($class_ids) ? array_map('absint', $class_ids) : array();

            // Backward compatibility: older data may only have the reverse relation stored on Class CPTs.
            if (empty($class_ids) && $student_post) {
                $class_query = new WP_Query([
                    'post_type'      => HAM_CPT_CLASS,
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'fields'         => 'ids',
                    'meta_query'     => [
                        [
                            'key'     => '_ham_student_ids',
                            'value'   => 'i:' . $student_post->ID . ';',
                            'compare' => 'LIKE',
                        ],
                    ],
                ]);

                if (!empty($class_query->posts)) {
                    $class_ids = array_map('absint', (array)$class_query->posts);
                }
            }

            $class_names = array();
            foreach ($class_ids as $class_id) {
                if ($class_id > 0) {
                    $class_post = get_post($class_id);
                    if ($class_post) {
                        $class_names[] = $class_post->post_title;
                    }
                }
            }
            $class_name = !empty($class_names) ? implode(', ', $class_names) : '';

            $school_id = $student_post ? absint(get_post_meta($student_post->ID, '_ham_school_id', true)) : 0;
            if ($school_id === 0 && !empty($class_ids)) {
                $first_class_id = absint(reset($class_ids));
                if ($first_class_id > 0) {
                    $school_id = absint(get_post_meta($first_class_id, '_ham_school_id', true));
                }
            }
            $school_post = $school_id > 0 ? get_post($school_id) : null;
            $school_name = $school_post ? $school_post->post_title : '';
            
            // Debug information about the student CPT
            //error_log('Student CPT found: ' . ($student_post ? 'Yes' : 'No') . ', Name: ' . $student_name);

            $assessment_data = get_post_meta($post->ID, HAM_ASSESSMENT_META_DATA, true);

            // Debug: Log the assessment data structure
            //error_log('Assessment data structure for post ' . $post->ID . ': ' . (is_array($assessment_data) ? json_encode(array_keys($assessment_data)) : 'not an array'));

            $effective_ts = self::get_assessment_effective_date($post);
            $effective_date = wp_date('Y-m-d H:i:s', $effective_ts);

            $calc = self::calculate_stage_from_assessment_data($assessment_data);
            $summary_stage = isset($calc['stage']) ? $calc['stage'] : 'not';
            $k = isset($calc['k']) ? (int) $calc['k'] : 0;
            $n = isset($calc['n']) ? (int) $calc['n'] : 0;
            $stage_score = $n > 0 ? ($k / $n) : 0;

            $completion_percentage = self::calculate_completion_percentage($assessment_data);

            // Get teacher info - prefer explicitly saved teacher meta, fallback to class relationship and post_author
            $teacher_name = esc_html__('Unknown Teacher', 'headless-access-manager');
            $teacher_id = $post->post_author; // Default to post_author for backward compatibility

            $saved_teacher_user_id = absint(get_post_meta($post->ID, '_ham_teacher_user_id', true));
            if ($saved_teacher_user_id > 0) {
                $saved_teacher_user = get_user_by('id', $saved_teacher_user_id);
                if ($saved_teacher_user) {
                    $teacher_id = $saved_teacher_user_id;
                    $teacher_name = $saved_teacher_user->display_name;
                }
            }
            
            // Find all classes the student is part of
            $student_classes = array();
            $class_args = array(
                'post_type' => HAM_CPT_CLASS,
                'posts_per_page' => -1,
                'meta_query' => array(
                    array(
                        'key' => '_ham_student_ids',
                        // Search for the student ID as an integer element in the serialized array
                        'value' => 'i:' . $student_id . ';',
                        'compare' => 'LIKE'
                    )
                )
            );
            
            $class_query = new WP_Query($class_args);
            $student_class_ids = array();
            
            if ($class_query->have_posts()) {
                while ($class_query->have_posts()) {
                    $class_query->the_post();
                    $student_class_ids[] = get_the_ID();
                }
                wp_reset_postdata();
            }
            
            // If student is in classes, find teachers assigned to those classes
            if ($teacher_name === esc_html__('Unknown Teacher', 'headless-access-manager') && !empty($student_class_ids)) {
                $teacher_args = array(
                    'post_type' => HAM_CPT_TEACHER,
                    'posts_per_page' => 1, // Just get the first matching teacher
                    'meta_query' => array()
                );
                
                // Build the meta query to find teachers in the same classes as the student
                $meta_query_clauses = array('relation' => 'OR');
                foreach ($student_class_ids as $class_id) {
                    $meta_query_clauses[] = array(
                        'key' => '_ham_class_ids',
                        'value' => 'i:' . $class_id . ';',
                        'compare' => 'LIKE'
                    );
                }
                $teacher_args['meta_query'] = $meta_query_clauses;
                
                $teacher_query = new WP_Query($teacher_args);
                
                if ($teacher_query->have_posts()) {
                    $teacher_query->the_post();
                    $teacher_cpt_id = get_the_ID();
                    $teacher_name = get_the_title();
                    
                    // Get the WP user ID associated with this teacher CPT
                    $wp_user_id = get_post_meta($teacher_cpt_id, '_ham_user_id', true);
                    if (!empty($wp_user_id)) {
                        $teacher_id = $wp_user_id;
                    }
                    
                    wp_reset_postdata();
                }
            }
            
            // Fallback: If no teacher found via class relationship, use post_author
            if ($teacher_name === esc_html__('Unknown Teacher', 'headless-access-manager') && !empty($post->post_author)) {
                $teacher_name = get_the_author_meta('display_name', $post->post_author);
                
                // Additional fallback: If author name is empty, try to find teacher CPT by user ID
                if (empty($teacher_name)) {
                    $teacher_cpt_args = array(
                        'post_type' => HAM_CPT_TEACHER,
                        'posts_per_page' => 1,
                        'meta_query' => array(
                            array(
                                'key' => '_ham_user_id',
                                'value' => $post->post_author,
                                'compare' => '='
                            )
                        )
                    );
                    
                    $teacher_cpt_query = new WP_Query($teacher_cpt_args);
                    if ($teacher_cpt_query->have_posts()) {
                        $teacher_cpt_query->the_post();
                        $teacher_name = get_the_title();
                        wp_reset_postdata();
                    }
                }
            }
            
            $assessments[] = array(
                'id'           => $post->ID,
                'title'        => $post->post_title,
                'date'         => $effective_date,
                'modified'     => $post->post_modified,
                'student_id'   => $student_id,
                'student_name' => $student_name,
                'class_name'   => $class_name,
                'school_name'  => $school_name,
                'completion'   => $completion_percentage,
                'author_id'    => $teacher_id,
                'author_name'  => $teacher_name,
                'stage'        => $summary_stage,
                'stage_score'  => $stage_score,
            );
        }

        // Debug: Log the final assessments array
        //error_log('Final assessments count: ' . count($assessments));

        return $assessments;
    }

    /**
     * Calculate completion percentage of an assessment.
     *
     * @param array $assessment_data The assessment data.
     * @return int Completion percentage.
     */
    private static function calculate_completion_percentage($assessment_data)
    {
        if (empty($assessment_data) || !is_array($assessment_data)) {
            return 0;
        }

        $total_questions = 0;
        $answered_questions = 0;

        // Count questions in each section
        foreach (array('anknytning', 'ansvar') as $section_key) {
            if (isset($assessment_data[$section_key]['questions']) && is_array($assessment_data[$section_key]['questions'])) {
                foreach ($assessment_data[$section_key]['questions'] as $question_id => $answer) {
                    $total_questions++;
                    if (!empty($answer)) {
                        $answered_questions++;
                    }
                }
            }
        }

        if ($total_questions === 0) {
            return 0;
        }

        return round(($answered_questions / $total_questions) * 100);
    }

    /**
     * Get assessment statistics.
     *
     * @param string $source Optional. Filter by assessment source ('admin', 'frontend', or 'all'). Default 'all'.
     * @return array Statistics data.
     */
    public static function get_assessment_statistics($source = 'all')
    {
        $assessments = self::get_assessments($source);

        // Initialize statistics data
        $stats = array(
            'total_assessments' => count($assessments),
            'total_students' => 0,
            'average_completion' => 0,
            'student_stage_counts' => array(
                'not' => 0,
                'trans' => 0,
                'full' => 0,
            ),
            'section_averages' => array(
                'anknytning' => 0,
                'ansvar' => 0
            ),
            'question_averages' => array(),
            'stage_distribution' => array(
                'ej' => 0,
                'trans' => 0,
                'full' => 0
            ),
            'monthly_submissions' => array(),
            'term_submissions' => array(),
            'school_year_submissions' => array()
        );

        if (empty($assessments)) {
            return $stats;
        }

        // Process each assessment
        $student_ids = array();
        $completion_sum = 0;
        $latest_stage_by_student_id = array();
        $section_sums = array('anknytning' => 0, 'ansvar' => 0);
        $section_counts = array('anknytning' => 0, 'ansvar' => 0);
        $question_sums = array();
        $question_counts = array();
        $stage_counts = array('ej' => 0, 'trans' => 0, 'full' => 0);
        $monthly_data = array();
        $term_data = array();
        $school_year_data = array();

        foreach ($assessments as $assessment) {
            // Track unique students
            if (!in_array($assessment['student_id'], $student_ids)) {
                $student_ids[] = $assessment['student_id'];
            }

            // Capture the most recent stage per student (assessments are ordered newest -> oldest)
            $sid = isset($assessment['student_id']) ? (int) $assessment['student_id'] : 0;
            if ($sid > 0 && !isset($latest_stage_by_student_id[$sid])) {
                $s = isset($assessment['stage']) ? (string) $assessment['stage'] : 'not';
                if ($s !== 'full' && $s !== 'trans' && $s !== 'not') {
                    $s = 'not';
                }
                $latest_stage_by_student_id[$sid] = $s;
            }

            // Sum completion percentages
            $completion_sum += $assessment['completion'];

            // Get full assessment data
            $assessment_data = get_post_meta($assessment['id'], HAM_ASSESSMENT_META_DATA, true);

            if (!empty($assessment_data) && is_array($assessment_data)) {
                // Process section data
                foreach (array('anknytning', 'ansvar') as $section_key) {
                    if (isset($assessment_data[$section_key]['questions']) && is_array($assessment_data[$section_key]['questions'])) {
                        foreach ($assessment_data[$section_key]['questions'] as $question_id => $answer) {
                            if (!empty($answer)) {
                                // Track section averages
                                $section_sums[$section_key] += intval($answer);
                                $section_counts[$section_key]++;

                                // Track question averages
                                $question_key = $section_key . '_' . $question_id;
                                if (!isset($question_sums[$question_key])) {
                                    $question_sums[$question_key] = 0;
                                    $question_counts[$question_key] = 0;
                                }
                                $question_sums[$question_key] += intval($answer);
                                $question_counts[$question_key]++;

                                // Get stage information
                                $stage = self::get_answer_stage($section_key, $question_id, $answer);
                                if ($stage) {
                                    $stage_counts[$stage]++;
                                }
                            }
                        }
                    }
                }
            }

            // Track monthly submissions
            $month = date('Y-m', strtotime($assessment['date']));
            if (!isset($monthly_data[$month])) {
                $monthly_data[$month] = 0;
            }
            $monthly_data[$month]++;

            // Track term submissions (school year Aug-Jun)
            $timestamp = strtotime($assessment['date']);
            $month_num = (int) date('n', $timestamp);
            $year_num = (int) date('Y', $timestamp);

            // School year starts in August.
            if ($month_num >= 8) {
                $school_year_start = $year_num;
                $term_code = 'HT';
            } elseif ($month_num <= 6) {
                $school_year_start = $year_num - 1;
                $term_code = 'VT';
            } else {
                // July: treat as upcoming autumn term.
                $school_year_start = $year_num;
                $term_code = 'HT';
            }

            $term_key = sprintf('%04d-%s', $school_year_start, $term_code);
            if (!isset($term_data[$term_key])) {
                $term_data[$term_key] = 0;
            }
            $term_data[$term_key]++;

            if (!isset($school_year_data[$school_year_start])) {
                $school_year_data[$school_year_start] = 0;
            }
            $school_year_data[$school_year_start]++;
        }

        // Calculate final statistics
        $stats['total_students'] = count($student_ids);
        $stats['average_completion'] = $stats['total_assessments'] > 0 ? round($completion_sum / $stats['total_assessments']) : 0;

        foreach ($latest_stage_by_student_id as $s) {
            if (isset($stats['student_stage_counts'][$s])) {
                $stats['student_stage_counts'][$s]++;
            }
        }

        // Calculate section averages
        foreach ($section_sums as $section => $sum) {
            $stats['section_averages'][$section] = $section_counts[$section] > 0 ? round($sum / $section_counts[$section], 1) : 0;
        }

        // Calculate question averages
        foreach ($question_sums as $question => $sum) {
            $stats['question_averages'][$question] = $question_counts[$question] > 0 ? round($sum / $question_counts[$question], 1) : 0;
        }

        // Calculate stage distribution percentages
        $total_answers = array_sum($stage_counts);
        if ($total_answers > 0) {
            foreach ($stage_counts as $stage => $count) {
                $stats['stage_distribution'][$stage] = round(($count / $total_answers) * 100);
            }
        }

        // Sort and format monthly data
        ksort($monthly_data);
        foreach ($monthly_data as $month => $count) {
            $stats['monthly_submissions'][] = array(
                'month' => $month,
                'count' => $count,
            );
        }

        // Sort and format term data
        ksort($term_data);
        foreach ($term_data as $term_key => $count) {
            $school_year_start = (int) substr($term_key, 0, 4);
            $term_code = substr($term_key, 5);
            $school_year_label = sprintf('%d/%02d', $school_year_start, ($school_year_start + 1) % 100);
            $term_label = $term_code === 'VT' ? __('Spring term', 'headless-access-manager') : __('Autumn term', 'headless-access-manager');

            $stats['term_submissions'][] = array(
                'term' => $term_key,
                'label' => sprintf('%s %s', $term_label, $school_year_label),
                'count' => $count,
            );
        }

        // Sort and format school-year data
        ksort($school_year_data);
        foreach ($school_year_data as $school_year_start => $count) {
            $school_year_label = sprintf('%d/%02d', (int) $school_year_start, (((int) $school_year_start) + 1) % 100);
            $stats['school_year_submissions'][] = array(
                'school_year_start' => (int) $school_year_start,
                'label' => $school_year_label,
                'count' => $count,
            );
        }

        return $stats;
    }

    /**
     * Get the stage for a specific answer.
     *
     * @param string $section_key Section key.
     * @param string $question_id Question ID.
     * @param string $answer Answer value.
     * @return string|null Stage value or null if not found.
     */
    private static function get_answer_stage($section_key, $question_id, $answer)
    {
        // Get the questions structure
        $structure = self::get_questions_structure();

        if (empty($structure) || !isset($structure[$section_key]['questions'][$question_id]['options'])) {
            return null;
        }

        $options = $structure[$section_key]['questions'][$question_id]['options'];

        foreach ($options as $option) {
            if ($option['value'] === $answer && isset($option['stage'])) {
                return $option['stage'];
            }
        }

        return null;
    }

    /**
     * Get the questions structure from the most recent assessment.
     *
     * @return array Questions structure.
     */
    private static function get_questions_structure()
    {
        // Define default options for questions
        $default_options = array(
            array('value' => '1', 'label' => 'Inte alls', 'stage' => 'ej'),
            array('value' => '2', 'label' => 'Sällan', 'stage' => 'ej'),
            array('value' => '3', 'label' => 'Ibland', 'stage' => 'trans'),
            array('value' => '4', 'label' => 'Ofta', 'stage' => 'trans'),
            array('value' => '5', 'label' => 'Alltid', 'stage' => 'full')
        );

        // Hardcoded question texts - normalize all keys to lowercase for consistency
        $question_texts = array(
            'anknytning' => array(
                'a1' => 'Närvaro',
                'a2' => 'Dialog 1',
                'a3' => 'Dialog 2',
                'a4' => 'Kontakt',
                'a5' => 'Samarbete',
                'a6' => 'Vid konflikt', // Updated to match frontend
                'a7' => 'Engagemang'
            ),
            'ansvar' => array(
                'b1' => 'Uppgift',
                'b2' => 'Initiativ',
                'b3' => 'Material',
                'b4' => 'Tid',
                'b5' => 'Regler',
                'b6' => 'Vid konflikt',
                'b7' => 'Ansvar'
            )
        );

        // Get the most recent assessment that has question data
        $assessments = get_posts(array(
            'post_type'      => HAM_CPT_ASSESSMENT,
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => array(
                array(
                    'key'     => HAM_ASSESSMENT_META_DATA,
                    'compare' => 'EXISTS',
                )
            )
        ));

        if (empty($assessments)) {
            error_log('No assessments found with question data');
            return self::get_default_questions_structure();
        }

        $assessment_data = get_post_meta($assessments[0]->ID, HAM_ASSESSMENT_META_DATA, true);

        if (empty($assessment_data) || !is_array($assessment_data)) {
            error_log('Assessment data is empty or not an array');
            return self::get_default_questions_structure();
        }

        // Return only the questions part of the structure
        $structure = array();

        // Process each section
        foreach (array('anknytning', 'ansvar') as $section) {
            if (!isset($assessment_data[$section]) || !is_array($assessment_data[$section])) {
                $structure[$section] = array(
                    'title' => ucfirst($section),
                    'questions' => array()
                );
                continue;
            }

            $section_data = $assessment_data[$section];
            $section_structure = array(
                'title' => isset($section_data['title']) ? $section_data['title'] : ucfirst($section),
                'questions' => array()
            );

            // Process questions
            if (isset($section_data['questions']) && is_array($section_data['questions'])) {
                foreach ($section_data['questions'] as $question_id => $question_data) {
                    // Normalize question ID to lowercase
                    $question_id = strtolower($question_id);

                    // If question_data is an array and has a text property, use it
                    if (is_array($question_data) && isset($question_data['text'])) {
                        $section_structure['questions'][$question_id] = $question_data;
                    }
                    // Otherwise, create a structure with the question text
                    else {
                        $section_structure['questions'][$question_id] = array(
                            'text' => isset($question_texts[$section][$question_id]) ?
                                    $question_texts[$section][$question_id] :
                                    ucfirst($question_id)
                        );
                    }

                    // Ensure options are set for each question
                    if (!isset($section_structure['questions'][$question_id]['options']) || !is_array($section_structure['questions'][$question_id]['options']) || empty($section_structure['questions'][$question_id]['options'])) {
                        $section_structure['questions'][$question_id]['options'] = $default_options;
                    }
                }
            }

            $structure[$section] = $section_structure;
        }

        return $structure;
    }

    /**
     * Get default questions structure when no data is available.
     *
     * @return array Default questions structure.
     */
    private static function get_default_questions_structure()
    {
        return array(
            'anknytning' => array(
                'title' => 'Anknytningstecken',
                'questions' => array()
            ),
            'ansvar' => array(
                'title' => 'Ansvarstecken',
                'questions' => array()
            )
        );
    }



/**
 * Debug function to dump data to a file.
 *
 * @param mixed $data The data to dump.
 * @param string $prefix A prefix for the log file.
 */
private function debug_dump($data, $prefix = 'debug')
{
    $file = HAM_PLUGIN_DIR . 'logs/' . $prefix . '-' . date('Y-m-d-H-i-s') . '.log';

    // Create logs directory if it doesn't exist
    if (!file_exists(HAM_PLUGIN_DIR . 'logs/')) {
        mkdir(HAM_PLUGIN_DIR . 'logs/', 0755, true);
    }
    
    file_put_contents($file, print_r($data, true));
}


/**
 * AJAX handler for getting assessment details.
 */
public function ajax_get_assessment_details()
{
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ham_assessment_nonce')) {
        wp_send_json_error('Invalid nonce');
        return;
    }

    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }

    // Check assessment ID
    if (!isset($_POST['assessment_id']) || empty($_POST['assessment_id'])) {
        wp_send_json_error('Missing assessment ID');
        return;
    }

    $assessment_id = intval($_POST['assessment_id']);

    // Debug information
    //error_log('Processing assessment details request for ID: ' . $assessment_id);

    // Get assessment data
    $assessment = get_post($assessment_id);

    // Get student ID from assessment meta
    $student_id = get_post_meta($assessment_id, HAM_ASSESSMENT_META_STUDENT_ID, true);

    // Debug information
    //error_log("MODAL - All meta for assessment {$assessment_id}: " . print_r(get_post_meta($assessment_id), true));

    if (!empty($student_id)) {
        $student_post = get_post($student_id);
        if ($student_post) {
            $student_name = $student_post->post_title;
            //error_log("MODAL - Found student post: {$student_post->ID}, post_type: {$student_post->post_type}, title: {$student_post->post_title}");
        } else {
            //error_log("MODAL - Could not find student post with ID: {$student_id}");
            
            // Debug - try again with a different approach
            // Students are custom post types, not WordPress users
            // Check both potential meta keys for student ID
            $student_id = get_post_meta($post->ID, HAM_ASSESSMENT_META_STUDENT_ID, true);
            $alt_student_id = get_post_meta($post->ID, 'student_id', true);
            
            // Use whichever meta key has a value
            if (empty($student_id) && !empty($alt_student_id)) {
                $student_id = $alt_student_id;
            }
            
            $student_name = esc_html__("Unknown Student", "headless-access-manager");
            
            if (!empty($student_id)) {
                $student_post = get_post($student_id);
                $student_post = $wpdb->get_row(
                    $wpdb->prepare("SELECT ID, post_title, post_type FROM {$wpdb->posts} WHERE ID = %d", $student_id)
                );
                if ($student_post) {
                    error_log("MODAL - Direct DB query found: ID: {$student_post->ID}, title: {$student_post->post_title}, type: {$student_post->post_type}");
                } else {
                    error_log("MODAL - Direct DB query found nothing for ID: {$student_id}");
                }
            } else {
                error_log("MODAL - No student ID found for assessment: {$assessment_id}");
            }
        }
    } else {
        error_log("MODAL - No student ID found for assessment: {$assessment_id}");
    }
        
        // Debug
        //error_log('Student ID: ' . $student_id . ', Student name resolved: ' . $student_name);

        $assessment_data = get_post_meta($assessment_id, HAM_ASSESSMENT_META_DATA, true);

        // Get the source of this assessment (admin, frontend, or undefined)
        $source = get_post_meta($assessment_id, '_ham_assessment_source', true);
        if (empty($source)) {
            // If no source is set, assume it's an admin assessment for backward compatibility
            $source = 'admin';
        }

        // Dump raw data for debugging
        $this->debug_dump($assessment_data, 'raw-assessment-data-' . $assessment_id);

        // Process the assessment data to ensure it's in the right format
        $processed_assessment_data = $this->process_assessment_data($assessment_data);

        // Log processed data in a more readable way
        //error_log('PROCESSED ASSESSMENT DATA STRUCTURE:');
        /*
        if (!empty($processed_assessment_data['anknytning']['questions'])) {
            foreach ($processed_assessment_data['anknytning']['questions'] as $qkey => $qdata) {
                //error_log("Question key: $qkey");
                if (!empty($qdata) && is_array($qdata)) {
                    if (isset($qdata['text'])) {
                        error_log(" - text: {$qdata['text']}");
                    }

                    if (isset($qdata['answer'])) {
                        error_log(" - answer: {$qdata['answer']}");
                    }

                    if (isset($qdata['options']) && is_array($qdata['options'])) {
                        error_log(" - has options: " . count($qdata['options']));
                        foreach ($qdata['options'] as $i => $opt) {
                            if (is_array($opt)) {
                                error_log("   - option[$i]: value={$opt['value']}, label={$opt['label']}, stage={$opt['stage']}");
                            }
                        }
                    }
                }
            }
        } else {
            error_log("No processed anknytning questions found!");
        }
        */

        // Get the question structure from the active Question Bank (admin-managed), not from code.
        $questions_structure = self::get_question_bank_structure();

        // Dump processed data for debugging
        $this->debug_dump($processed_assessment_data, 'processed-assessment-data-' . $assessment_id);
        $this->debug_dump($questions_structure, 'questions-structure-' . $assessment_id);

        // Debug information
        //error_log('Raw assessment data: ' . print_r($assessment_data, true));
        //error_log('Processed assessment data: ' . print_r($processed_assessment_data, true));

        // Get teacher info - prefer explicitly saved teacher meta, fallback to class relationship and post_author
        $teacher_name = esc_html__('Unknown Teacher', 'headless-access-manager');
        $teacher_id = $assessment->post_author; // Default to post_author for backward compatibility

        $saved_teacher_user_id = absint(get_post_meta($assessment_id, '_ham_teacher_user_id', true));
        if ($saved_teacher_user_id > 0) {
            $saved_teacher_user = get_user_by('id', $saved_teacher_user_id);
            if ($saved_teacher_user) {
                $teacher_id = $saved_teacher_user_id;
                $teacher_name = $saved_teacher_user->display_name;
            }
        }
        
        // Find all classes the student is part of
        $student_classes = array();
        $class_args = array(
            'post_type' => HAM_CPT_CLASS,
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_ham_student_ids',
                    // Search for the student ID as an integer element in the serialized array
                    'value' => 'i:' . $student_id . ';',
                    'compare' => 'LIKE'
                )
            )
        );
        
        $class_query = new WP_Query($class_args);
        $student_class_ids = array();
        
        if ($class_query->have_posts()) {
            while ($class_query->have_posts()) {
                $class_query->the_post();
                $student_class_ids[] = get_the_ID();
            }
            wp_reset_postdata();
        }
        
        // If student is in classes, find teachers assigned to those classes
        if (!empty($student_class_ids)) {
            $teacher_args = array(
                'post_type' => HAM_CPT_TEACHER,
                'posts_per_page' => 1, // Just get the first matching teacher
                'meta_query' => array()
            );
            
            // Build the meta query to find teachers in the same classes as the student
            $meta_query_clauses = array('relation' => 'OR');
            foreach ($student_class_ids as $class_id) {
                $meta_query_clauses[] = array(
                    'key' => '_ham_class_ids',
                    'value' => 'i:' . $class_id . ';',
                    'compare' => 'LIKE'
                );
            }
            $teacher_args['meta_query'] = $meta_query_clauses;
            
            $teacher_query = new WP_Query($teacher_args);
            
            if ($teacher_query->have_posts()) {
                $teacher_query->the_post();
                $teacher_cpt_id = get_the_ID();
                $teacher_name = get_the_title();
                
                // Get the WP user ID associated with this teacher CPT
                $wp_user_id = get_post_meta($teacher_cpt_id, '_ham_user_id', true);
                if (!empty($wp_user_id)) {
                    $teacher_id = $wp_user_id;
                }
                
                wp_reset_postdata();
            }
        }
        
        // Fallback: If no teacher found via class relationship, use post_author
        if ($teacher_name === esc_html__('Unknown Teacher', 'headless-access-manager') && !empty($assessment->post_author)) {
            $teacher_name = get_the_author_meta('display_name', $assessment->post_author);
            
            // Additional fallback: If author name is empty, try to find teacher CPT by user ID
            if (empty($teacher_name)) {
                $teacher_cpt_args = array(
                    'post_type' => HAM_CPT_TEACHER,
                    'posts_per_page' => 1,
                    'meta_query' => array(
                        array(
                            'key' => '_ham_user_id',
                            'value' => $assessment->post_author,
                            'compare' => '='
                        )
                    )
                );
                
                $teacher_cpt_query = new WP_Query($teacher_cpt_args);
                if ($teacher_cpt_query->have_posts()) {
                    $teacher_cpt_query->the_post();
                    $teacher_name = get_the_title();
                    wp_reset_postdata();
                }
            }
        }
        
        //error_log('Modal display - Teacher resolution: Student ID: ' . $student_id . ', Classes: ' . implode(',', $student_class_ids) . ', Teacher name: ' . $teacher_name);

        $effective_ts = self::get_assessment_effective_date($assessment);
        $effective_date = wp_date('Y-m-d H:i:s', $effective_ts);

        $response = array(
            'id' => $assessment_id,
            'title' => $assessment->post_title,
            'date' => $effective_date,
            'modified' => $assessment->post_modified,
            'student_id' => $student_id,
            'student_name' => $student_name,
            'author_id' => $teacher_id,
            'author_name' => $teacher_name,
            'assessment_data' => $processed_assessment_data,
            'questions_structure' => $questions_structure,
            'source' => $source // Include the source in the response for reference
        );

        wp_send_json_success($response);
    }

    /**
     * Process assessment data to ensure it's in the right format for display.
     *
     * @param array $data Raw assessment data.
     * @return array Processed assessment data.
     */
    private function process_assessment_data($data)
    {
        if (!is_array($data)) {
            return array();
        }

        //error_log('Raw data structure: ' . json_encode($data));
        $this->debug_dump($data, 'raw-data-structure');

        $processed_data = array();

        // Process each section
        foreach (array('anknytning', 'ansvar') as $section) {
            if (!isset($data[$section]) || !is_array($data[$section])) {
                $processed_data[$section] = array(
                    'questions' => array(),
                    'comments' => ''
                );
                continue;
            }

            $section_data = $data[$section];
            $processed_section = array(
                'questions' => array(),
                'comments' => isset($section_data['comments']) ? $section_data['comments'] : ''
            );

            //error_log('Section ' . $section . ' data: ' . json_encode($section_data));

            // Get questions structure for reference
            $questions_structure = self::get_questions_structure();
            $section_structure = isset($questions_structure[$section]) && isset($questions_structure[$section]['questions'])
                ? $questions_structure[$section]['questions']
                : array();

            // Process questions
            if (isset($section_data['questions']) && is_array($section_data['questions'])) {
                foreach ($section_data['questions'] as $question_id => $answer) {
                    //error_log('Question ' . $question_id . ' answer: ' . json_encode($answer));

                    // If answer is an array, extract the relevant information
                    if (is_array($answer)) {
                        // Convert the array to a more readable format
                        $processed_answer = array();

                        // Extract text or value
                        if (isset($answer['text'])) {
                            $processed_answer['text'] = $answer['text'];
                        }

                        if (isset($answer['value'])) {
                            $processed_answer['value'] = $answer['value'];
                            //error_log("Found value in answer: " . $answer['value']);
                        } elseif (isset($answer['selected'])) {
                            $processed_answer['value'] = $answer['selected'];
                            //error_log("Found selected in answer: " . $answer['selected']);
                        }

                        // Extract stage if available
                        if (isset($answer['stage'])) {
                            $processed_answer['stage'] = $answer['stage'];
                            //error_log("Found stage directly in answer: " . $answer['stage']);
                        }

                        // Look for any numeric property that might be the value
                        if (!isset($processed_answer['value'])) {
                            foreach ($answer as $key => $value) {
                                if (is_numeric($value) && $key !== 'text') {
                                    $processed_answer['value'] = $value;
                                    //error_log("Found numeric value in answer: $key = $value");
                                    break;
                                }
                            }
                        }

                        // If we still don't have a value, use the first option value as a default
                        if (!isset($processed_answer['value']) && isset($section_structure[$question_id]) &&
                            isset($section_structure[$question_id]['options']) &&
                            is_array($section_structure[$question_id]['options']) &&
                            !empty($section_structure[$question_id]['options'])) {

                            $first_option = $section_structure[$question_id]['options'][0];
                            $processed_answer['value'] = $first_option['value'];
                            //error_log("Using first option value as default: " . $first_option['value']);

                            // Also include the stage if available and not already set
                            if (!isset($processed_answer['stage']) && isset($first_option['stage'])) {
                                $processed_answer['stage'] = $first_option['stage'];
                                //error_log("Using first option stage as default: " . $first_option['stage']);
                            }
                        }

                        // If we have a value but no stage, try to find the stage from the matching option
                        if (isset($processed_answer['value']) && !isset($processed_answer['stage']) &&
                            isset($section_structure[$question_id]) &&
                            isset($section_structure[$question_id]['options']) &&
                            is_array($section_structure[$question_id]['options'])) {

                            //error_log("Looking for option with value: " . $processed_answer['value']);
                            $found_matching_option = false;

                            foreach ($section_structure[$question_id]['options'] as $option) {
                                //error_log("Checking option: " . json_encode($option));

                                // Convert both values to strings for comparison
                                $option_value = (string) $option['value'];
                                $selected_value = (string) $processed_answer['value'];

                                if (isset($option['value']) && $option_value === $selected_value && isset($option['stage'])) {
                                    $processed_answer['stage'] = $option['stage'];
                                    //error_log("Found matching option with stage: " . $option['stage']);
                                    $found_matching_option = true;
                                    break;
                                }
                            }

                            if (!$found_matching_option) {
                                error_log("No matching option found for value: " . $processed_answer['value']);
                            }
                        }

                        //error_log("Final processed answer: " . json_encode($processed_answer));
                        $processed_section['questions'][$question_id] = $processed_answer;
                    } else {
                        // If answer is not an array, keep it as is
                        $processed_answer = array('value' => $answer);
                        //error_log("Answer is a primitive: $answer");

                        // Try to find the stage from the matching option
                        if (isset($section_structure[$question_id]) &&
                            isset($section_structure[$question_id]['options']) &&
                            is_array($section_structure[$question_id]['options'])) {

                            //error_log("Looking for option with value: $answer");
                            $found_matching_option = false;

                            foreach ($section_structure[$question_id]['options'] as $option) {
                                //error_log("Checking option: " . json_encode($option));

                                // Convert both values to strings for comparison
                                $option_value = (string) $option['value'];
                                $selected_value = (string) $answer;

                                if (isset($option['value']) && $option_value === $selected_value && isset($option['stage'])) {
                                    $processed_answer['stage'] = $option['stage'];
                                    //error_log("Found matching option with stage: " . $option['stage']);
                                    $found_matching_option = true;
                                    break;
                                }
                            }

                            if (!$found_matching_option) {
                                error_log("No matching option found for value: $answer");
                            }
                        }

                        //error_log("Final processed answer: " . json_encode($processed_answer));
                        $processed_section['questions'][$question_id] = $processed_answer;
                    }
                }
            }

            $processed_data[$section] = $processed_section;
        }

        $this->debug_dump($processed_data, 'processed-data-structure');
        return $processed_data;
    }

    /**
     * AJAX handler for getting assessment statistics.
     */
    public function ajax_get_assessment_stats()
    {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ham_assessment_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        // Get statistics
        $stats = self::get_assessment_statistics();

        wp_send_json_success($stats);
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @param string $hook Current admin page.
     */
    public function enqueue_admin_assets($hook)
    {
        // Debug information
        //error_log('Enqueuing assets for hook: ' . $hook);

        // Only enqueue on our plugin pages
        if (strpos($hook, 'ham-assessments') === false && strpos($hook, 'ham-assessment-stats') === false && strpos($hook, 'page_ham-assessment-stats') === false) {
            return;
        }

        // Enqueue Select2 only on the Student Evaluations list page where we need searchable student filtering
        if (strpos($hook, 'ham-assessments') !== false) {
            $select2_js_url = HAM_PLUGIN_URL . 'assets/vendor/select2/js/select2.min.js';
            $select2_css_url = HAM_PLUGIN_URL . 'assets/vendor/select2/css/select2.min.css';
            $select2_js_path = HAM_PLUGIN_DIR . 'assets/vendor/select2/js/select2.min.js';
            $select2_css_path = HAM_PLUGIN_DIR . 'assets/vendor/select2/css/select2.min.css';

            if (file_exists($select2_js_path) && file_exists($select2_css_path)) {
                wp_enqueue_script('ham-select2', $select2_js_url, array('jquery'), '4.1.0', true);
                wp_enqueue_style('ham-select2', $select2_css_url, array(), '4.1.0');
            }
        }

        // Enqueue CSS
        $ham_assessment_manager_css_path = HAM_PLUGIN_DIR . 'assets/css/assessment-manager.css';
        $ham_assessment_manager_css_ver = file_exists($ham_assessment_manager_css_path) ? filemtime($ham_assessment_manager_css_path) : HAM_VERSION;
        wp_enqueue_style(
            'ham-assessment-manager',
            plugins_url('assets/css/assessment-manager.css', HAM_PLUGIN_FILE),
            array(),
            $ham_assessment_manager_css_ver
        );

        // Enqueue JavaScript
        $ham_assessment_manager_js_path = HAM_PLUGIN_DIR . 'assets/js/assessment-manager.js';
        $ham_assessment_manager_js_ver = file_exists($ham_assessment_manager_js_path) ? filemtime($ham_assessment_manager_js_path) : HAM_VERSION;
        wp_enqueue_script(
            'ham-assessment-manager',
            plugins_url('assets/js/assessment-manager.js', HAM_PLUGIN_FILE),
            array('jquery', 'wp-util'),
            $ham_assessment_manager_js_ver,
            true
        );

        // Add Chart.js for statistics page
        if (strpos($hook, 'ham-assessment-stats') !== false || strpos($hook, 'page_ham-assessment-stats') !== false) {
            //error_log('Loading Chart.js on hook: ' . $hook);

            wp_enqueue_script('postbox');
            wp_enqueue_script('jquery-ui-sortable');

            wp_enqueue_script(
                'chart-js',
                'https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js',
                array(),
                '3.7.1',
                true
            );
        }

        // Localize script
        wp_localize_script('ham-assessment-manager', 'hamAssessment', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ham_assessment_nonce'),
            'studentSearchNonce' => wp_create_nonce('ham_ajax_nonce'),
            'texts' => array(
                'loading' => esc_html__('Loading...', 'headless-access-manager'),
                'error' => esc_html__('Error loading data', 'headless-access-manager'),
                'noData' => esc_html__('No data available', 'headless-access-manager'),
                'viewDetails' => esc_html__('View Details', 'headless-access-manager'),
                'close' => esc_html__('Close', 'headless-access-manager'),
                'student' => esc_html__('Student', 'headless-access-manager'),
                'date' => esc_html__('Date', 'headless-access-manager'),
                'author' => esc_html__('Author', 'headless-access-manager'),
                'question' => esc_html__('Question', 'headless-access-manager'),
                'option1' => esc_html__('Option 1', 'headless-access-manager'),
                'option2' => esc_html__('Option 2', 'headless-access-manager'),
                'option3' => esc_html__('Option 3', 'headless-access-manager'),
                'option4' => esc_html__('Option 4', 'headless-access-manager'),
                'option5' => esc_html__('Option 5', 'headless-access-manager'),
                'answer' => esc_html__('Answer', 'headless-access-manager'),
                'comments' => esc_html__('Comments', 'headless-access-manager'),
                'noComments' => esc_html__('No comments.', 'headless-access-manager'),
                'answerAlternatives' => esc_html__('Answer alternatives', 'headless-access-manager'),
                'month' => esc_html__('Month', 'headless-access-manager'),
                'term' => esc_html__('Term', 'headless-access-manager'),
                'schoolYear' => esc_html__('School year', 'headless-access-manager'),
                'hogstadium' => esc_html__('Låg-/Mellan-/Högstadiu', 'headless-access-manager'),
                'radar' => esc_html__('Radar', 'headless-access-manager'),
                'confirmDelete' => esc_html__('Are you sure you want to delete this assessment?', 'headless-access-manager'),
            )
        ));
    }

    /**
     * Filter the post type listing to exclude frontend assessments
     *
     * @param WP_Query $query The WP query object
     */
    public function filter_assessment_admin_listing($query)
    {
        global $pagenow, $typenow;

        // Only apply on the post type listing page for ham_assessment
        if (!is_admin() || $pagenow !== 'edit.php' || $typenow !== HAM_CPT_ASSESSMENT || !$query->is_main_query()) {
            return;
        }

        // Skip if explicitly requesting to see all assessments
        if (isset($_GET['view']) && $_GET['view'] === 'all') {
            return;
        }

        // Skip if we're viewing a single assessment
        if (isset($_GET['post'])) {
            return;
        }

        // IMPORTANT:
        // The Question Bank lives in the same CPT as evaluation answers.
        // To prevent evaluation answer posts from appearing in the Question Bank list,
        // we only show posts that do NOT have a linked student.
        // (Evaluation answers have HAM_ASSESSMENT_META_STUDENT_ID set.)
        $existing_meta_query = $query->get('meta_query');
        if (!is_array($existing_meta_query)) {
            $existing_meta_query = array();
        }

        $existing_meta_query[] = array(
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
        );

        $query->set('meta_query', $existing_meta_query);
    }

    /**
     * AJAX handler for deleting assessments
     *
     * Checks for proper authentication
     * and permissions before performing the deletion.
     *
     * @uses check_ajax_referer() For security verification
     * @uses current_user_can() To check user permissions
     * @uses wp_delete_post() To delete the assessment
     * @uses wp_send_json_error() To send error response
     * @uses wp_send_json_success() To send success response
     *
     * @return void Sends JSON response and exits
     */
    public function ajax_delete_assessment()
    {
        // Verify nonce
        if (!check_ajax_referer('ham_assessment_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Invalid security token.'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions.'));
        }

        $assessment_id = intval($_POST['assessment_id']);
        if (!$assessment_id) {
            wp_send_json_error(array('message' => 'Invalid assessment ID.'));
        }

        // Delete the assessment
        $result = wp_delete_post($assessment_id, true);

        if ($result) {
            wp_send_json_success(array('message' => 'Assessment deleted successfully.'));
        } else {
            wp_send_json_error(array('message' => 'Failed to delete assessment.'));
        }
    }

    /**
     * Map average score to a stage.
     *
     * @param float $average Average score.
     * @return string Stage value.
     */
    private static function map_average_to_stage($average) {
        // Evaluation grades
        if ($average < 0.4) {
            return 'ej'; // "not"
        } elseif ($average < 0.7) {
            return 'trans';
        } else {
            return 'full';
        }
    }
}

// Initialize the class
new HAM_Assessment_Manager();
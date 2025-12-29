<?php
/**
 * Template for displaying assessment statistics in the admin.
 *
 * @package HeadlessAccessManager
 */

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    exit;
}

$overview_radar = array(
    'title' => __('Overall question averages', 'headless-access-manager'),
    'labels' => array(),
    'values' => array(),
);

if (isset($stats) && is_array($stats) && isset($stats['question_averages']) && is_array($stats['question_averages'])) {
    $questions_structure = (new HAM_Assessment_Manager())->get_questions_structure();
    $labels = array();
    $values = array();

    if (is_array($questions_structure)) {
        foreach ($questions_structure as $section_key => $section) {
            if (!isset($section['questions']) || !is_array($section['questions'])) {
                continue;
            }

            $section_title = isset($section['title']) ? $section['title'] : $section_key;

            foreach ($section['questions'] as $question_id => $q) {
                $question_text = isset($q['text']) ? $q['text'] : $question_id;
                $labels[] = $section_title . ': ' . $question_text;

                $avg_key = $section_key . '_' . $question_id;
                $avg = isset($stats['question_averages'][$avg_key]) ? $stats['question_averages'][$avg_key] : null;
                $values[] = $avg === null ? null : (float) $avg;
            }
        }
    }

    $overview_radar['labels'] = $labels;
    $overview_radar['values'] = $values;
}
?>
<div class="wrap">
    <h1><?php echo esc_html__('Evaluations by Tryggve', 'headless-access-manager'); ?></h1>

    <script>
        window.hamAssessmentOverview = <?php
            echo wp_json_encode(array(
                'radar' => $overview_radar,
            ));
        ?>;
    </script>

    <?php if (isset($drilldown) && is_array($drilldown)) : ?>

        <script>
            window.hamAssessmentStats = <?php
                echo wp_json_encode(array(
                    'level' => isset($drilldown['level']) ? $drilldown['level'] : null,
                    'student' => isset($drilldown['student']) ? $drilldown['student'] : null,
                    'avg_progress' => isset($drilldown['avg_progress']) ? $drilldown['avg_progress'] : array(),
                    'student_radar' => isset($drilldown['student_radar']) ? $drilldown['student_radar'] : array(),
                    'radar_questions' => isset($drilldown['radar_questions']) ? $drilldown['radar_questions'] : array(),
                ));
            ?>;
        </script>

        <div class="ham-stats-panel" style="margin-top: 20px;">
            <h2><?php echo esc_html__('Evaluation Drilldown', 'headless-access-manager'); ?></h2>

            <?php if (!empty($drilldown['breadcrumb'])) : ?>
                <div style="margin-bottom: 10px;">
                    <?php
                    $crumbs = array();
                    foreach ($drilldown['breadcrumb'] as $crumb) {
                        $crumbs[] = '<a class="ham-pill-link" href="' . esc_url($crumb['url']) . '">' . esc_html($crumb['label']) . '</a>';
                    }
                    echo '<div class="ham-pill-group">' . wp_kses_post(implode('<span class="ham-pill-separator">&raquo;</span>', $crumbs)) . '</div>';
                    ?>
                </div>
            <?php endif; ?>

            <?php
            $render_semester_bars = function ($series, $max_height_pct = 100) {
                if (empty($series) || !is_array($series)) {
                    echo '<p>' . esc_html__('No evaluation data available.', 'headless-access-manager') . '</p>';
                    return;
                }

                $max_count = 1;
                foreach ($series as $bucket) {
                    $count = isset($bucket['count']) ? (int) $bucket['count'] : 0;
                    if ($count > $max_count) {
                        $max_count = $count;
                    }
                }

                echo '<div class="ham-simple-chart" style="height: 220px; display: flex; align-items: flex-end; justify-content: center; gap: 20px;">';
                foreach ($series as $bucket) {
                    $count = isset($bucket['count']) ? (int) $bucket['count'] : 0;
                    $avg = isset($bucket['overall_avg']) ? $bucket['overall_avg'] : null;
                    $heightPct = ($count / $max_count) * $max_height_pct;
                    $label = isset($bucket['semester_label']) ? $bucket['semester_label'] : (isset($bucket['semester_key']) ? $bucket['semester_key'] : '');
                    $avg_label = ($avg === null) ? '—' : number_format((float) $avg, 2);

                    echo '<div class="ham-bar-wrapper" style="height: 100%; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; text-align: center;">';
                    echo '<div class="ham-bar" style="display: block; height: ' . esc_attr($heightPct) . '%; width: 34px; background-color: #0073aa;"></div>';
                    echo '<div class="ham-bar-label" style="margin-top: 5px;">' . esc_html($label) . '</div>';
                    echo '<div class="ham-bar-value" style="font-weight: bold;">' . esc_html($count) . '</div>';
                    echo '<div class="ham-bar-value" style="font-size: 12px; color: #646970;">' . esc_html__('Avg', 'headless-access-manager') . ': ' . esc_html($avg_label) . '</div>';
                    echo '</div>';
                }
                echo '</div>';
            };
            ?>

            <?php if ($drilldown['level'] === 'schools') : ?>
                <p style="margin-top: 0; color: #646970;">
                    <?php echo esc_html__('Select a school to drill down into classes, students, and per-question evaluation progress by semester.', 'headless-access-manager'); ?>
                </p>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('School', 'headless-access-manager'); ?></th>
                            <th><?php echo esc_html__('# Classes', 'headless-access-manager'); ?></th>
                            <th><?php echo esc_html__('# Students evaluated', 'headless-access-manager'); ?></th>
                            <th><?php echo esc_html__('# Evaluations', 'headless-access-manager'); ?></th>
                            <th><?php echo esc_html__('Overall Avg', 'headless-access-manager'); ?></th>
                            <th><?php echo esc_html__('Progress (by semester)', 'headless-access-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($drilldown['schools'])) : ?>
                            <?php foreach ($drilldown['schools'] as $school) : ?>
                                <tr>
                                    <td>
                                        <a class="ham-pill-link" href="<?php echo esc_url($school['url']); ?>"><?php echo esc_html($school['name']); ?></a>
                                    </td>
                                    <td><?php echo esc_html((int) $school['class_count']); ?></td>
                                    <td><?php echo esc_html((int) $school['student_count']); ?></td>
                                    <td><?php echo esc_html((int) $school['evaluation_count']); ?></td>
                                    <td><?php echo $school['overall_avg'] === null ? '—' : esc_html(number_format((float) $school['overall_avg'], 2)); ?></td>
                                    <td style="min-width: 320px;">
                                        <?php $render_semester_bars($school['series'], 100); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr><td colspan="6"><?php echo esc_html__('No schools found.', 'headless-access-manager'); ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

            <?php elseif ($drilldown['level'] === 'school') : ?>

                <h3 style="margin-top: 10px;">
                    <?php echo esc_html__('School average progress', 'headless-access-manager'); ?>
                </h3>
                <div style="display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px;">
                    <div>
                        <strong><?php echo esc_html__('Month', 'headless-access-manager'); ?></strong>
                        <div class="ham-chart-wrapper ham-chart-wrapper--sm"><canvas id="ham-avg-progress-month"></canvas></div>
                    </div>
                    <div>
                        <strong><?php echo esc_html__('Term', 'headless-access-manager'); ?></strong>
                        <div class="ham-chart-wrapper ham-chart-wrapper--sm"><canvas id="ham-avg-progress-term"></canvas></div>
                    </div>
                    <div>
                        <strong><?php echo esc_html__('School year', 'headless-access-manager'); ?></strong>
                        <div class="ham-chart-wrapper ham-chart-wrapper--sm"><canvas id="ham-avg-progress-school-year"></canvas></div>
                    </div>
                    <div>
                        <strong><?php echo esc_html__('Högstadium (3 years)', 'headless-access-manager'); ?></strong>
                        <div class="ham-chart-wrapper ham-chart-wrapper--sm"><canvas id="ham-avg-progress-hogstadium"></canvas></div>
                    </div>
                </div>

                <h3 style="margin-top: 10px;">
                    <?php echo esc_html__('School Progress (by semester)', 'headless-access-manager'); ?>
                </h3>
                <?php $render_semester_bars($drilldown['series'], 100); ?>

                <h3 style="margin-top: 20px;">
                    <?php echo esc_html__('Classes', 'headless-access-manager'); ?>
                </h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Class', 'headless-access-manager'); ?></th>
                            <th><?php echo esc_html__('# Students', 'headless-access-manager'); ?></th>
                            <th><?php echo esc_html__('# Evaluations', 'headless-access-manager'); ?></th>
                            <th><?php echo esc_html__('Overall Avg', 'headless-access-manager'); ?></th>
                            <th><?php echo esc_html__('Progress (by semester)', 'headless-access-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($drilldown['classes'])) : ?>
                            <?php foreach ($drilldown['classes'] as $class) : ?>
                                <tr>
                                    <td>
                                        <a class="ham-pill-link" href="<?php echo esc_url($class['url']); ?>"><?php echo esc_html($class['name']); ?></a>
                                    </td>
                                    <td><?php echo esc_html((int) $class['student_count']); ?></td>
                                    <td><?php echo esc_html((int) $class['evaluation_count']); ?></td>
                                    <td><?php echo $class['overall_avg'] === null ? '—' : esc_html(number_format((float) $class['overall_avg'], 2)); ?></td>
                                    <td style="min-width: 320px;">
                                        <?php $render_semester_bars($class['series'], 100); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr><td colspan="5"><?php echo esc_html__('No classes found for this school.', 'headless-access-manager'); ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

            <?php elseif ($drilldown['level'] === 'class') : ?>

                <h3 style="margin-top: 10px;">
                    <?php echo esc_html__('Class average progress', 'headless-access-manager'); ?>
                </h3>
                <div style="display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px;">
                    <div>
                        <strong><?php echo esc_html__('Month', 'headless-access-manager'); ?></strong>
                        <div class="ham-chart-wrapper ham-chart-wrapper--sm"><canvas id="ham-avg-progress-month"></canvas></div>
                    </div>
                    <div>
                        <strong><?php echo esc_html__('Term', 'headless-access-manager'); ?></strong>
                        <div class="ham-chart-wrapper ham-chart-wrapper--sm"><canvas id="ham-avg-progress-term"></canvas></div>
                    </div>
                    <div>
                        <strong><?php echo esc_html__('School year', 'headless-access-manager'); ?></strong>
                        <div class="ham-chart-wrapper ham-chart-wrapper--sm"><canvas id="ham-avg-progress-school-year"></canvas></div>
                    </div>
                    <div>
                        <strong><?php echo esc_html__('Högstadium (3 years)', 'headless-access-manager'); ?></strong>
                        <div class="ham-chart-wrapper ham-chart-wrapper--sm"><canvas id="ham-avg-progress-hogstadium"></canvas></div>
                    </div>
                </div>

                <h3 style="margin-top: 10px;">
                    <?php echo esc_html__('Class Progress (by semester)', 'headless-access-manager'); ?>
                </h3>
                <?php $render_semester_bars($drilldown['series'], 100); ?>

                <h3 style="margin-top: 20px;">
                    <?php echo esc_html__('Students', 'headless-access-manager'); ?>
                </h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Student', 'headless-access-manager'); ?></th>
                            <th><?php echo esc_html__('# Evaluations', 'headless-access-manager'); ?></th>
                            <th><?php echo esc_html__('Overall Avg', 'headless-access-manager'); ?></th>
                            <th><?php echo esc_html__('Progress (by semester)', 'headless-access-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($drilldown['students'])) : ?>
                            <?php foreach ($drilldown['students'] as $student) : ?>
                                <tr>
                                    <td>
                                        <a class="ham-pill-link" href="<?php echo esc_url($student['url']); ?>"><?php echo esc_html($student['name']); ?></a>
                                    </td>
                                    <td><?php echo esc_html((int) $student['evaluation_count']); ?></td>
                                    <td><?php echo $student['overall_avg'] === null ? '—' : esc_html(number_format((float) $student['overall_avg'], 2)); ?></td>
                                    <td style="min-width: 320px;">
                                        <?php $render_semester_bars($student['series'], 100); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr><td colspan="4"><?php echo esc_html__('No students found for this class.', 'headless-access-manager'); ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

            <?php elseif ($drilldown['level'] === 'student') : ?>

                <h3 style="margin-top: 10px;">
                    <?php echo esc_html__('Student average progress', 'headless-access-manager'); ?>
                    <?php if (!empty($drilldown['student']['name'])) : ?>
                        <span style="color: #646970; font-weight: normal;">— <?php echo esc_html($drilldown['student']['name']); ?></span>
                    <?php endif; ?>
                </h3>
                <div style="display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px;">
                    <div>
                        <strong><?php echo esc_html__('Month', 'headless-access-manager'); ?></strong>
                        <div class="ham-chart-wrapper ham-chart-wrapper--sm"><canvas id="ham-avg-progress-month"></canvas></div>
                    </div>
                    <div>
                        <strong><?php echo esc_html__('Term', 'headless-access-manager'); ?></strong>
                        <div class="ham-chart-wrapper ham-chart-wrapper--sm"><canvas id="ham-avg-progress-term"></canvas></div>
                    </div>
                    <div>
                        <strong><?php echo esc_html__('School year', 'headless-access-manager'); ?></strong>
                        <div class="ham-chart-wrapper ham-chart-wrapper--sm"><canvas id="ham-avg-progress-school-year"></canvas></div>
                    </div>
                    <div>
                        <strong><?php echo esc_html__('Högstadium (3 years)', 'headless-access-manager'); ?></strong>
                        <div class="ham-chart-wrapper ham-chart-wrapper--sm"><canvas id="ham-avg-progress-hogstadium"></canvas></div>
                    </div>
                </div>

                <h3 style="margin-top: 20px;">
                    <?php echo esc_html__('Radar (per evaluation within bucket)', 'headless-access-manager'); ?>
                </h3>
                <div style="display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px;">
                    <div>
                        <strong><?php echo esc_html__('Month', 'headless-access-manager'); ?></strong>
                        <div class="ham-chart-wrapper ham-chart-wrapper--md"><canvas id="ham-student-radar-month"></canvas></div>
                    </div>
                    <div>
                        <strong><?php echo esc_html__('Term', 'headless-access-manager'); ?></strong>
                        <div class="ham-chart-wrapper ham-chart-wrapper--md"><canvas id="ham-student-radar-term"></canvas></div>
                    </div>
                    <div>
                        <strong><?php echo esc_html__('School year', 'headless-access-manager'); ?></strong>
                        <div class="ham-chart-wrapper ham-chart-wrapper--md"><canvas id="ham-student-radar-school-year"></canvas></div>
                    </div>
                    <div>
                        <strong><?php echo esc_html__('Högstadium (3 years)', 'headless-access-manager'); ?></strong>
                        <div class="ham-chart-wrapper ham-chart-wrapper--md"><canvas id="ham-student-radar-hogstadium"></canvas></div>
                    </div>
                </div>

                <h3 style="margin-top: 20px;">
                    <?php echo esc_html__('Questions and answer alternatives', 'headless-access-manager'); ?>
                </h3>
                <?php if (!empty($drilldown['radar_questions']) && is_array($drilldown['radar_questions'])) : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Question', 'headless-access-manager'); ?></th>
                                <th><?php echo esc_html__('Option 1', 'headless-access-manager'); ?></th>
                                <th><?php echo esc_html__('Option 2', 'headless-access-manager'); ?></th>
                                <th><?php echo esc_html__('Option 3', 'headless-access-manager'); ?></th>
                                <th><?php echo esc_html__('Option 4', 'headless-access-manager'); ?></th>
                                <th><?php echo esc_html__('Option 5', 'headless-access-manager'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($drilldown['radar_questions'] as $q) : ?>
                                <?php
                                $opts = isset($q['options']) && is_array($q['options']) ? $q['options'] : array();
                                $opt = function($i) use ($opts) {
                                    return isset($opts[$i]) ? $opts[$i] : '';
                                };
                                $label = '';
                                if (isset($q['section']) && isset($q['text'])) {
                                    $label = $q['section'] . ': ' . $q['text'];
                                } elseif (isset($q['text'])) {
                                    $label = $q['text'];
                                } elseif (isset($q['key'])) {
                                    $label = $q['key'];
                                }
                                ?>
                                <tr>
                                    <td><?php echo esc_html($label); ?></td>
                                    <td><?php echo esc_html($opt(0)); ?></td>
                                    <td><?php echo esc_html($opt(1)); ?></td>
                                    <td><?php echo esc_html($opt(2)); ?></td>
                                    <td><?php echo esc_html($opt(3)); ?></td>
                                    <td><?php echo esc_html($opt(4)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p><?php echo esc_html__('No question data available.', 'headless-access-manager'); ?></p>
                <?php endif; ?>

                <h3 style="margin-top: 10px;">
                    <?php echo esc_html__('Student Progress (by semester)', 'headless-access-manager'); ?>
                    <?php if (!empty($drilldown['student']['name'])) : ?>
                        <span style="color: #646970; font-weight: normal;">— <?php echo esc_html($drilldown['student']['name']); ?></span>
                    <?php endif; ?>
                </h3>
                <?php $render_semester_bars($drilldown['series'], 100); ?>

                <h3 style="margin-top: 20px;">
                    <?php echo esc_html__('Per-question averages (by semester)', 'headless-access-manager'); ?>
                </h3>

                <?php if (!empty($drilldown['series'])) : ?>
                    <?php
                    $questions = isset($drilldown['top_questions']) && is_array($drilldown['top_questions']) ? $drilldown['top_questions'] : array();
                    if (empty($questions)) {
                        $questions = array();
                    }
                    ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Question', 'headless-access-manager'); ?></th>
                                <?php foreach ($drilldown['series'] as $bucket) : ?>
                                    <th><?php echo esc_html($bucket['semester_label']); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($questions)) : ?>
                                <?php foreach ($questions as $qk) : ?>
                                    <?php
                                    $label = $qk;
                                    if (isset($drilldown['question_labels'][$qk])) {
                                        $label = $drilldown['question_labels'][$qk]['section'] . ': ' . $drilldown['question_labels'][$qk]['text'];
                                    }
                                    ?>
                                    <tr>
                                        <td><?php echo esc_html($label); ?></td>
                                        <?php foreach ($drilldown['series'] as $bucket) : ?>
                                            <?php
                                            $val = null;
                                            if (isset($bucket['question_avgs'][$qk])) {
                                                $val = $bucket['question_avgs'][$qk];
                                            }
                                            ?>
                                            <td><?php echo $val === null ? '—' : esc_html(number_format((float) $val, 2)); ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr><td colspan="<?php echo esc_attr(1 + count($drilldown['series'])); ?>"><?php echo esc_html__('No per-question data available.', 'headless-access-manager'); ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p><?php echo esc_html__('No evaluation data available for this student.', 'headless-access-manager'); ?></p>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <div class="ham-stats-overview">
        <div class="ham-stats-card">
            <div class="ham-stats-icon">
                <span class="dashicons dashicons-clipboard"></span>
            </div>
            <div class="ham-stats-data">
                <div class="ham-stats-value"><?php echo esc_html($stats['total_assessments']); ?></div>
                <div class="ham-stats-label"><?php echo esc_html__('Total Evaluations', 'headless-access-manager'); ?></div>
            </div>
        </div>
        
        <div class="ham-stats-card">
            <div class="ham-stats-icon">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <div class="ham-stats-data">
                <div class="ham-stats-value"><?php echo esc_html($stats['total_students']); ?></div>
                <div class="ham-stats-label"><?php echo esc_html__('Assessed Students', 'headless-access-manager'); ?></div>
            </div>
        </div>
        
        <div class="ham-stats-card">
            <div class="ham-stats-icon">
                <span class="dashicons dashicons-chart-bar"></span>
            </div>
            <div class="ham-stats-data">
                <div class="ham-stats-value"><?php echo esc_html($stats['average_completion']); ?>%</div>
                <div class="ham-stats-label"><?php echo esc_html__('Average Completion Rate', 'headless-access-manager'); ?></div>
            </div>
        </div>
        
        <div class="ham-stats-card">
            <div class="ham-stats-icon">
                <span class="dashicons dashicons-calendar-alt"></span>
            </div>
            <div class="ham-stats-data">
                <div class="ham-stats-value">
                    <?php 
                    $time_bucket = isset($_GET['time_bucket']) ? sanitize_key(wp_unslash($_GET['time_bucket'])) : 'term';
                    if (!in_array($time_bucket, array('month', 'term', 'school_year'), true)) {
                        $time_bucket = 'term';
                    }

                    $bucket_series = array();
                    if ($time_bucket === 'month') {
                        $bucket_series = isset($stats['monthly_submissions']) ? $stats['monthly_submissions'] : array();
                    } elseif ($time_bucket === 'school_year') {
                        $bucket_series = isset($stats['school_year_submissions']) ? $stats['school_year_submissions'] : array();
                    } else {
                        $bucket_series = isset($stats['term_submissions']) ? $stats['term_submissions'] : array();
                    }

                    if (!empty($bucket_series)) {
                        $latest_bucket = end($bucket_series);
                        echo esc_html(isset($latest_bucket['count']) ? $latest_bucket['count'] : 0);
                    } else {
                        echo '0';
                    }
                    ?>
                </div>
                <div class="ham-stats-label">
                    <?php
                    if ($time_bucket === 'month') {
                        echo esc_html__('Evaluations This Month', 'headless-access-manager');
                    } elseif ($time_bucket === 'school_year') {
                        echo esc_html__('Evaluations This School Year', 'headless-access-manager');
                    } else {
                        echo esc_html__('Evaluations This Term', 'headless-access-manager');
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="ham-stats-row">
        <div class="ham-stats-column">
            <div class="ham-stats-panel">
                <h2>
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

                <div style="margin-bottom: 12px;">
                    <?php
                    $base_args = $_GET;
                    $base_args = is_array($base_args) ? $base_args : array();
                    $base_args['page'] = 'ham-assessment-stats';

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

                <div id="monthlyChartSimple" class="ham-chart-container" style="padding: 20px; text-align: center;">
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
                        $maxCount = 0;
                        foreach ($chartData as $item) {
                            $count = isset($item['count']) ? (int) $item['count'] : 0;
                            if ($count > $maxCount) {
                                $maxCount = $count;
                            }
                        }
                        if ($maxCount < 1) {
                            $maxCount = 1;
                        }

                        echo '<div class="ham-simple-chart" style="height: 220px; display: flex; align-items: flex-end; justify-content: center; gap: 20px;">';
                        foreach ($chartData as $item) {
                            $count = isset($item['count']) ? (int) $item['count'] : 0;
                            $heightPct = ($count / $maxCount) * 100;
                            echo '<div class="ham-bar-wrapper" style="height: 100%; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; text-align: center;">';
                            echo '<div class="ham-bar" style="display: block; height: ' . esc_attr($heightPct) . '%; width: 30px; background-color: #0073aa;"></div>';
                            echo '<div class="ham-bar-label" style="margin-top: 5px;">' . esc_html($item['label']) . '</div>';
                            echo '<div class="ham-bar-value" style="font-weight: bold;">' . esc_html($item['count']) . '</div>';
                            echo '</div>';
                        }
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <div class="ham-stats-column">
            <div class="ham-stats-panel">
                <h2><?php echo esc_html__('Status Distribution', 'headless-access-manager'); ?></h2>
                <div id="stageChartSimple" class="ham-chart-container" style="padding: 20px; text-align: center;">
                    <?php
                    $stageData = array(
                        array(
                            'label' => esc_html__('Not Established', 'headless-access-manager'),
                            'value' => $stats['stage_distribution']['ej'],
                            'color' => 'hsl(11, 97.00%, 87.10%)'
                        ),
                        array(
                            'label' => esc_html__('Developing', 'headless-access-manager'),
                            'value' => $stats['stage_distribution']['trans'],
                            'color' => 'hsl(40, 97%, 87%)'
                        ),
                        array(
                            'label' => esc_html__('Established', 'headless-access-manager'),
                            'value' => $stats['stage_distribution']['full'],
                            'color' => 'hsl(105, 97%, 87%)'
                        )
                    );
                    
                    echo '<div class="ham-simple-pie" style="display: flex; justify-content: center; align-items: center; flex-wrap: wrap;">';
                    foreach ($stageData as $item) {
                        echo '<div style="margin: 10px; text-align: center; width: 120px;">';
                        echo '<div style="height: 80px; width: 80px; margin: 0 auto; background-color: ' . esc_attr($item['color']) . '; border-radius: 50%;"></div>';
                        echo '<div style="margin-top: 10px;"><strong>' . esc_html($item['label']) . '</strong>: ' . esc_html($item['value']) . '%</div>';
                        echo '</div>';
                    }
                    echo '</div>';
                    ?>
                </div>
            </div>
        </div>
    </div>

    <div class="ham-stats-row">
        <div class="ham-stats-column ham-stats-column--half">
            <div class="ham-stats-panel">
                <h2><?php echo esc_html__('Overall radar (question averages)', 'headless-access-manager'); ?></h2>
                <div class="ham-chart-wrapper ham-chart-wrapper--lg"><canvas id="ham-overview-radar"></canvas></div>
            </div>
        </div>
    </div>
    
    <div class="ham-stats-row">
        <div class="ham-stats-column">
            <div class="ham-stats-panel">
                <h2><?php echo esc_html__('Average per Assessment Section', 'headless-access-manager'); ?></h2>
                <div id="sectionChartSimple" class="ham-chart-container" style="padding: 20px; text-align: center;">
                    <?php
                    $sectionData = array(
                        array(
                            'label' => esc_html__('Connection', 'headless-access-manager'),
                            'value' => $stats['section_averages']['anknytning'],
                            'color' => '#0073aa'
                        ),
                        array(
                            'label' => esc_html__('Responsibility', 'headless-access-manager'),
                            'value' => $stats['section_averages']['ansvar'],
                            'color' => '#00a0d2'
                        )
                    );
                    
                    echo '<div class="ham-simple-bars" style="display: flex; justify-content: center; align-items: flex-end; height: 200px;">';
                    foreach ($sectionData as $item) {
                        $height = ($item['value'] / 5) * 150; // Scale to max height of 150px (5 is max value)
                        echo '<div style="margin: 0 20px; text-align: center;">';
                        echo '<div style="height: ' . esc_attr($height) . 'px; width: 60px; background-color: ' . esc_attr($item['color']) . ';"></div>';
                        echo '<div style="margin-top: 10px;"><strong>' . esc_html($item['label']) . '</strong>: ' . esc_html($item['value']) . '</div>';
                        echo '</div>';
                    }
                    echo '</div>';
                    ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="ham-stats-row">
        <div class="ham-stats-column">
            <div class="ham-stats-panel">
                <h2><?php echo esc_html__('Top-Rated Assessment Questions', 'headless-access-manager'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Question', 'headless-access-manager'); ?></th>
                            <th><?php echo esc_html__('Section', 'headless-access-manager'); ?></th>
                            <th><?php echo esc_html__('Average Score', 'headless-access-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Sort questions by average score
                        $question_averages = $stats['question_averages'];
                        arsort($question_averages);
                        
                        // Get questions structure
                        $questions_structure = (new HAM_Assessment_Manager())->get_questions_structure();
                        
                        // Display top 10 questions
                        $count = 0;
                        foreach ($question_averages as $question_key => $average) {
                            list($section, $question_id) = explode('_', $question_key, 2);
                            
                            $section_title = isset($questions_structure[$section]['title']) 
                                ? $questions_structure[$section]['title'] 
                                : ucfirst($section);
                                
                            $question_text = isset($questions_structure[$section]['questions'][$question_id]['text']) 
                                ? $questions_structure[$section]['questions'][$question_id]['text'] 
                                : $question_id;
                            
                            echo '<tr>';
                            echo '<td>' . esc_html($question_text) . '</td>';
                            echo '<td>' . esc_html($section_title) . '</td>';
                            echo '<td>' . esc_html($average) . '</td>';
                            echo '</tr>';
                            
                            $count++;
                            if ($count >= 10) {
                                break;
                            }
                        }
                        
                        if ($count === 0) {
                            echo '<tr><td colspan="3">' . esc_html__('No question data available.', 'headless-access-manager') . '</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
/* Statistics Page Styles */
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
    line-height: 1.2;
}

.ham-stats-label {
    color: #646970;
    font-size: 14px;
}

.ham-stats-row {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 30px;
}

.ham-stats-column {
    flex: 1;
    min-width: 300px;
}

.ham-stats-panel {
    background-color: #fff;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    padding: 20px;
}

.ham-stats-panel h2 {
    margin-top: 0;
    margin-bottom: 20px;
    font-size: 18px;
    color: #23282d;
}

.ham-chart-container {
    height: 300px;
    position: relative;
}

.ham-chart-legend {
    margin-top: 15px;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.ham-legend-item {
    display: flex;
    align-items: center;
    margin-right: 15px;
}

.ham-legend-color {
    width: 16px;
    height: 16px;
    border-radius: 3px;
    margin-right: 5px;
}

.ham-legend-label {
    font-size: 13px;
    color: #646970;
}

.ham-simple-chart {
    display: flex;
    justify-content: center;
    align-items: flex-end;
    height: 200px;
}

.ham-bar-wrapper {
    margin: 0 10px;
}

.ham-bar {
    width: 30px;
    background-color: #0073aa;
}

.ham-bar-label {
    margin-top: 5px;
    font-size: 13px;
    color: #646970;
}

.ham-bar-value {
    font-weight: bold;
    font-size: 14px;
    color: #23282d;
}

.ham-simple-pie {
    display: flex;
    justify-content: center;
    align-items: center;
    flex-wrap: wrap;
}

.ham-simple-pie > div {
    margin: 10px;
    text-align: center;
    width: 120px;
}

.ham-simple-pie > div > div:first-child {
    height: 80px;
    width: 80px;
    margin: 0 auto;
    border-radius: 50%;
}

.ham-simple-pie > div > div:last-child {
    margin-top: 10px;
    font-size: 14px;
    color: #23282d;
}

.ham-simple-bars {
    display: flex;
    justify-content: center;
    align-items: flex-end;
    height: 200px;
}

.ham-simple-bars > div {
    margin: 0 20px;
    text-align: center;
}

.ham-simple-bars > div > div:first-child {
    width: 60px;
}

@media screen and (max-width: 782px) {
    .ham-stats-row {
        flex-direction: column;
    }
    
    .ham-stats-column {
        width: 100%;
    }
}
</style>

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
    $questions_structure = HAM_Assessment_Manager::get_question_bank_structure();
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
                    'group_radar' => isset($drilldown['group_radar']) ? $drilldown['group_radar'] : array(),
                    'radar_questions' => isset($drilldown['radar_questions']) ? $drilldown['radar_questions'] : array(),
                ));
            ?>;
        </script>

        <div class="ham-stats-panel" style="margin-top: 20px;">
            <h2><?php echo esc_html__('Evaluation Drilldown', 'headless-access-manager'); ?></h2>

            <div id="ham-stats-postboxes-drilldown" class="metabox-holder ham-stats-postboxes" data-page="ham-assessment-stats">
                <?php wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false); ?>
                <?php wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false); ?>
                <div class="postbox-container">
                    <div class="meta-box-sortables ui-sortable">

            <?php if (!empty($drilldown['breadcrumb'])) : ?>
                <div style="margin-bottom: 10px;">
                    <?php
                    $crumbs = array();

                    if (isset($drilldown['level']) && $drilldown['level'] !== 'schools') {
                        $crumbs[] = '<a class="ham-pill-link" href="' . esc_url(admin_url('admin.php?page=ham-assessment-stats')) . '">' . esc_html__('All schools', 'headless-access-manager') . '</a>';
                    }

                    $breadcrumb_items = is_array($drilldown['breadcrumb']) ? array_values($drilldown['breadcrumb']) : array();
                    $last_index = count($breadcrumb_items) - 1;
                    foreach ($breadcrumb_items as $idx => $crumb) {
                        if ($idx === $last_index) {
                            $crumbs[] = '<span class="ham-pill-link" aria-current="page">' . esc_html($crumb['label']) . '</span>';
                        } else {
                            $crumbs[] = '<a class="ham-pill-link" href="' . esc_url($crumb['url']) . '">' . esc_html($crumb['label']) . '</a>';
                        }
                    }
                    echo '<div class="ham-pill-group">' . wp_kses_post(implode('<span class="ham-pill-separator">&raquo;</span>', $crumbs)) . '</div>';
                    ?>

            <?php
            // Keep radar + answer alternatives visible (required).
            // Hide aggregated avg-progress charts for school/class, but keep the student chart.
            $hide_avg_progress_charts = isset($drilldown['level']) ? ($drilldown['level'] !== 'student') : true;
            ?>
                </div>
            <?php endif; ?>

            <?php
            $render_semester_bars = function ($series, $max_height_pct = 100, $variant = 'detailed') {
                if (empty($series) || !is_array($series)) {
                    echo '<p>' . esc_html__('No evaluation data available.', 'headless-access-manager') . '</p>';
                    return;
                }

                $has_delta = false;
                foreach ($series as $bucket) {
                    if (isset($bucket['delta_avg']) && $bucket['delta_avg'] !== null) {
                        $has_delta = true;
                        break;
                    }
                }

                $max_count = 1;
                $min_val = null;
                $max_val = null;
                $points = array();
                $labels = array();

                $carry_score = null;
                foreach ($series as $bucket) {
                    $count = isset($bucket['count']) ? (int) $bucket['count'] : 0;
                    if ($count > $max_count) {
                        $max_count = $count;
                    }

                    $val = $count;
                    if ($has_delta) {
                        $overall = isset($bucket['overall_avg']) && $bucket['overall_avg'] !== null ? (float) $bucket['overall_avg'] : null;
                        $delta = isset($bucket['delta_avg']) && $bucket['delta_avg'] !== null ? (float) $bucket['delta_avg'] : null;

                        if ($carry_score === null) {
                            $carry_score = $overall;
                        } elseif ($delta !== null) {
                            $carry_score = (float) $carry_score + (float) $delta;
                        }

                        $val = $carry_score;
                        if ($val !== null) {
                            $min_val = ($min_val === null) ? (float) $val : min((float) $min_val, (float) $val);
                            $max_val = ($max_val === null) ? (float) $val : max((float) $max_val, (float) $val);
                        }
                    }
                    $label = isset($bucket['semester_label']) ? (string) $bucket['semester_label'] : (isset($bucket['semester_key']) ? (string) $bucket['semester_key'] : '');
                    $labels[] = $label;
                    $points[] = $val;
                }

                $n = count($points);
                if ($n === 0) {
                    echo '<p>' . esc_html__('No evaluation data available.', 'headless-access-manager') . '</p>';
                    return;
                }

                // Compact inline line chart
                $w = 100;
                $h = ($variant === 'sparkline') ? 30 : 40;
                $pad_x = 6;
                $pad_y = ($variant === 'sparkline') ? 8 : 9;

                $baseline_y = $h - $pad_y;

                $svg_points = array();
                $circle_nodes = array();
                for ($i = 0; $i < $n; $i++) {
                    $x = ($n === 1)
                        ? ($w / 2)
                        : ($pad_x + ($i * (($w - 2 * $pad_x) / ($n - 1))));

                    $raw_val = $points[$i];
                    if ($has_delta) {
                        $range = (($max_val ?? 0.0) - ($min_val ?? 0.0));
                        if ($range <= 0) {
                            $range = 1.0;
                        }
                        $ratio = ($raw_val === null || $min_val === null) ? 0.0 : ((((float) $raw_val) - (float) $min_val) / $range);
                        $ratio = max(0.0, min(1.0, $ratio));
                        $y = ($h - $pad_y) - ($ratio * ($h - 2 * $pad_y));
                    } else {
                        $count = (int) $raw_val;
                        $ratio = $max_count > 0 ? ($count / $max_count) : 0;
                        $y = ($h - $pad_y) - ($ratio * ($h - 2 * $pad_y));
                    }
                    $svg_points[] = $x . ',' . $y;

                    if ($variant !== 'sparkline') {
                        $value_label = '';
                        if ($has_delta) {
                            $value_label = $raw_val === null ? '—' : number_format((float) $raw_val, 1);
                        } else {
                            $value_label = (string) ((int) $raw_val);
                        }

                        $circle_nodes[] = array(
                            'x' => $x,
                            'y' => $y,
                            'count' => $value_label,
                            'label' => $labels[$i],
                        );
                    }
                }

                if ($variant === 'sparkline') {
                    echo '<div class="ham-mini-line" style="display: inline-block; width: 100px;">';
                    echo '<svg viewBox="0 0 ' . esc_attr($w) . ' ' . esc_attr($h) . '" preserveAspectRatio="none" style="width: 100px; height: 30px; max-height: 30px; overflow: visible;">';
                } else {
                    // Scale width based on number of points: 2 circles = 100%, 4 circles = 130%
                    // Center by shifting left half the extra width
                    $extra_pct = max(0, ($n - 2) * 15);
                    $width_pct = 100 + $extra_pct;
                    $margin_left = -($extra_pct / 2);
                    echo '<div class="ham-mini-line" style="display: inline-block; width: ' . esc_attr($width_pct) . '%; margin-left: ' . esc_attr($margin_left) . '%;">';
                    $max_h = 54;
                    echo '<svg viewBox="0 0 ' . esc_attr($w) . ' ' . esc_attr($h) . '" preserveAspectRatio="xMidYMid meet" style="width: 100%; height: auto; aspect-ratio: ' . esc_attr($w) . ' / ' . esc_attr($h) . '; max-height: ' . esc_attr($max_h) . 'px; overflow: visible;">';
                }

                if ($variant !== 'sparkline') {
                    // Baseline
                    echo '<line x1="' . esc_attr($pad_x) . '" y1="' . esc_attr($baseline_y) . '" x2="' . esc_attr($w - $pad_x) . '" y2="' . esc_attr($baseline_y) . '" stroke="#dcdcde" stroke-width="1" />';
                }

                if ($variant === 'sparkline' && $n > 1) {
                    $area_points = $svg_points;
                    $area_points[] = ($w - $pad_x) . ',' . $baseline_y;
                    $area_points[] = $pad_x . ',' . $baseline_y;
                    echo '<polygon fill="rgba(0, 115, 170, 0.18)" points="' . esc_attr(implode(' ', $area_points)) . '" />';
                }

                // Connecting line (only if > 1 point)
                if ($n > 1) {
                    echo '<polyline fill="none" stroke="#0073aa" stroke-width="1" points="' . esc_attr(implode(' ', $svg_points)) . '" />';
                }

                if ($variant !== 'sparkline') {
                    // Ring markers + counts
                    foreach ($circle_nodes as $node) {
                        echo '<circle cx="' . esc_attr($node['x']) . '" cy="' . esc_attr($node['y']) . '" r="11" fill="#ffffff" stroke="#0073aa" stroke-width="1" />';
                        echo '<text x="' . esc_attr($node['x']) . '" y="' . esc_attr($node['y']) . '" text-anchor="middle" dominant-baseline="middle" font-size="9" fill="#1d2327">' . esc_html((string) $node['count']) . '</text>';
                    }
                }
                echo '</svg>';

                if ($variant !== 'sparkline') {
                    // Labels row - use flexbox space-between to match SVG point distribution
                    // SVG points go from pad_x (6) to w-pad_x (94), distributed with space-between
                    echo '<div style="display: flex; justify-content: center; margin-top: 0;">';
                    for ($i = 0; $i < $n; $i++) {
                        echo '<div style="text-align: center; font-size: 11px; color: #646970; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 0 0 auto; padding: 0 2px;">' . esc_html($labels[$i]) . '</div>';
                    }
                    echo '</div>';
                }

                echo '</div>';
            };
            ?>

            <?php if ($drilldown['level'] === 'schools') : ?>
                <p style="margin-top: 0; color: #646970;">
                    <?php echo esc_html__('Select a school to drill down into classes, students, and per-question evaluation progress by semester.', 'headless-access-manager'); ?>
                </p>

                <?php if (!$hide_avg_progress_charts) : ?>

                    <div id="ham-postbox-schools-progress" class="postbox">
                        <div class="postbox-header"><h2 class="hndle"><?php echo esc_html__('All schools average progress', 'headless-access-manager'); ?></h2></div>
                        <div class="inside">
                    <div class="ham-radar-toggle ham-progress-toggle" role="group" aria-label="<?php echo esc_attr__('Time bucket', 'headless-access-manager'); ?>">
                        <button type="button" class="button ham-progress-toggle-btn" data-bucket="current_term"><?php echo esc_html__('Current term', 'headless-access-manager'); ?></button>
                        <button type="button" class="button ham-progress-toggle-btn" data-bucket="previous_term"><?php echo esc_html__('Previous term', 'headless-access-manager'); ?></button>
                        <button type="button" class="button ham-progress-toggle-btn" data-bucket="school_year"><?php echo esc_html__('School year', 'headless-access-manager'); ?></button>
                        <button type="button" class="button ham-progress-toggle-btn" data-bucket="hogstadium"><?php echo esc_html__('L/M/H-stadium', 'headless-access-manager'); ?></button>
                    </div>
                    <details class="ham-date-range" style="margin: 8px 0 0;">
                        <summary class="button" style="cursor: pointer; user-select: none;">
                            <?php echo esc_html__('Filter by date:', 'headless-access-manager'); ?>
                            <span class="ham-date-summary" data-all-dates="<?php echo esc_attr__('All Dates', 'headless-access-manager'); ?>" style="font-weight: 600; margin-left: 6px;"><?php echo esc_html__('All Dates', 'headless-access-manager'); ?></span>
                        </summary>
                        <div style="margin-top: 8px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <label style="display: inline-flex; gap: 6px; align-items: center;">
                                <span><?php echo esc_html__('From', 'headless-access-manager'); ?></span>
                                <input type="text" class="ham-date-from" inputmode="numeric" placeholder="YYYY-MM" pattern="^\d{4}-\d{2}$" />
                            </label>
                            <label style="display: inline-flex; gap: 6px; align-items: center;">
                                <span><?php echo esc_html__('To', 'headless-access-manager'); ?></span>
                                <input type="text" class="ham-date-to" inputmode="numeric" placeholder="YYYY-MM" pattern="^\d{4}-\d{2}$" />
                            </label>
                            <button type="button" class="button ham-date-clear"><?php echo esc_html__('Clear', 'headless-access-manager'); ?></button>
                        </div>
                    </details>
                    <div class="ham-chart-wrapper ham-chart-wrapper--xs"><canvas id="ham-avg-progress-drilldown"></canvas></div>

                        </div>
                    </div>
                <?php endif; ?>

                <div id="ham-postbox-schools-radar" class="postbox">
                    <div class="postbox-header"><h2 class="hndle"><?php echo esc_html__('Radar (avg per question)', 'headless-access-manager'); ?></h2></div>
                    <div class="inside">

                <div class="ham-radar-toggle" role="group" aria-label="<?php echo esc_attr__('Time bucket', 'headless-access-manager'); ?>">
                    <button type="button" class="button ham-group-radar-toggle-btn" data-bucket="current_term"><?php echo esc_html__('Current term', 'headless-access-manager'); ?></button>
                    <button type="button" class="button ham-group-radar-toggle-btn" data-bucket="previous_term"><?php echo esc_html__('Previous term', 'headless-access-manager'); ?></button>
                    <button type="button" class="button ham-group-radar-toggle-btn" data-bucket="school_year"><?php echo esc_html__('School year', 'headless-access-manager'); ?></button>
                    <button type="button" class="button ham-group-radar-toggle-btn" data-bucket="hogstadium"><?php echo esc_html__('L/M/H-stadium', 'headless-access-manager'); ?></button>
                </div>
                <details class="ham-date-range" style="margin: 8px 0 0;">
                    <summary class="button" style="cursor: pointer; user-select: none;">
                        <?php echo esc_html__('Filter by date:', 'headless-access-manager'); ?>
                        <span class="ham-date-summary" data-all-dates="<?php echo esc_attr__('All Dates', 'headless-access-manager'); ?>" style="font-weight: 600; margin-left: 6px;"><?php echo esc_html__('All Dates', 'headless-access-manager'); ?></span>
                    </summary>
                    <div style="margin-top: 8px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <label style="display: inline-flex; gap: 6px; align-items: center;">
                            <span><?php echo esc_html__('From', 'headless-access-manager'); ?></span>
                            <input type="text" class="ham-date-from" inputmode="numeric" placeholder="YYYY-MM" pattern="^\d{4}-\d{2}$" />
                        </label>
                        <label style="display: inline-flex; gap: 6px; align-items: center;">
                            <span><?php echo esc_html__('To', 'headless-access-manager'); ?></span>
                            <input type="text" class="ham-date-to" inputmode="numeric" placeholder="YYYY-MM" pattern="^\d{4}-\d{2}$" />
                        </label>
                        <button type="button" class="button ham-date-clear"><?php echo esc_html__('Clear', 'headless-access-manager'); ?></button>
                    </div>
                </details>
                <div class="ham-chart-wrapper ham-chart-wrapper--lg"><canvas id="ham-group-radar"></canvas></div>

                    </div>
                </div>

                <div id="ham-postbox-schools-radar-table" class="postbox">
                    <div class="postbox-header"><h2 class="hndle"><?php echo esc_html__('Radar values', 'headless-access-manager'); ?></h2></div>
                    <div class="inside">
                        <div id="ham-group-radar-table" class="ham-radar-values"></div>
                    </div>
                </div>

                <div id="ham-postbox-schools" class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle"><?php echo esc_html__('Schools', 'headless-access-manager'); ?></h2>
                    </div>
                    <div class="inside">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th class="ham-col-15"><?php echo esc_html__('School', 'headless-access-manager'); ?></th>
                                    <th class="ham-col-5"><?php echo esc_html__('# Classes', 'headless-access-manager'); ?></th>
                                    <th class="ham-col-5"><?php echo esc_html__('# Students', 'headless-access-manager'); ?></th>
                                    <th class="ham-col-5"><?php echo esc_html__('Observationer', 'headless-access-manager'); ?></th>
                                    <th class="ham-col-15"><?php echo esc_html__('Anknytning', 'headless-access-manager'); ?></th>
                                    <th class="ham-col-15"><?php echo esc_html__('Ansvar', 'headless-access-manager'); ?></th>
                                    <th class="ham-col-40"><?php echo esc_html__('Utveckling', 'headless-access-manager'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($drilldown['schools'])) : ?>
                                    <?php
                                    $total_ank = array('not' => 0, 'trans' => 0, 'full' => 0);
                                    $total_ans = array('not' => 0, 'trans' => 0, 'full' => 0);
                                    ?>
                                    <?php foreach ($drilldown['schools'] as $school) : ?>
                                        <?php
                                        $sec = isset($school['section_counts']) && is_array($school['section_counts']) ? $school['section_counts'] : array();
                                        $ank = isset($sec['anknytning']) ? $sec['anknytning'] : array('not' => 0, 'trans' => 0, 'full' => 0);
                                        $ans = isset($sec['ansvar']) ? $sec['ansvar'] : array('not' => 0, 'trans' => 0, 'full' => 0);
                                        $total_ank['not'] += (int) ($ank['not'] ?? 0);
                                        $total_ank['trans'] += (int) ($ank['trans'] ?? 0);
                                        $total_ank['full'] += (int) ($ank['full'] ?? 0);
                                        $total_ans['not'] += (int) ($ans['not'] ?? 0);
                                        $total_ans['trans'] += (int) ($ans['trans'] ?? 0);
                                        $total_ans['full'] += (int) ($ans['full'] ?? 0);
                                        ?>
                                        <tr>
                                            <td>
                                                <a class="ham-pill-link" href="<?php echo esc_url($school['url']); ?>"><?php echo esc_html($school['name']); ?></a>
                                            </td>
                                            <td><?php echo esc_html((int) $school['class_count']); ?></td>
                                            <td><?php echo esc_html((int) $school['student_count']); ?></td>
                                            <td><?php echo esc_html((int) $school['evaluation_count']); ?></td>
                                            <td>
                                                <span style="display: inline-block; margin-right: 8px;" title="<?php esc_attr_e('Anknytning', 'headless-access-manager'); ?>">
                                                    <strong>A:</strong>
                                                    <span class="ham-stage-badge ham-stage-not" title="<?php esc_attr_e('Ej', 'headless-access-manager'); ?>"><?php echo esc_html($ank['not'] ?? 0); ?></span>
                                                    <span class="ham-stage-badge ham-stage-trans" title="<?php esc_attr_e('Utv.', 'headless-access-manager'); ?>"><?php echo esc_html($ank['trans'] ?? 0); ?></span>
                                                    <span class="ham-stage-badge ham-stage-full" title="<?php esc_attr_e('Ok', 'headless-access-manager'); ?>"><?php echo esc_html($ank['full'] ?? 0); ?></span>
                                                </span>
                                            </td>
                                            <td>
                                                <span style="display: inline-block;" title="<?php esc_attr_e('Ansvar', 'headless-access-manager'); ?>">
                                                    <strong>B:</strong>
                                                    <span class="ham-stage-badge ham-stage-not" title="<?php esc_attr_e('Ej', 'headless-access-manager'); ?>"><?php echo esc_html($ans['not'] ?? 0); ?></span>
                                                    <span class="ham-stage-badge ham-stage-trans" title="<?php esc_attr_e('Utv.', 'headless-access-manager'); ?>"><?php echo esc_html($ans['trans'] ?? 0); ?></span>
                                                    <span class="ham-stage-badge ham-stage-full" title="<?php esc_attr_e('Ok', 'headless-access-manager'); ?>"><?php echo esc_html($ans['full'] ?? 0); ?></span>
                                                </span>
                                            </td>
                                            <td>
                                                <?php $render_semester_bars($school['series'], 100); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <tr>
                                        <td style="font-weight: 700;"><?php echo esc_html__('Total', 'headless-access-manager'); ?></td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td>
                                            <span style="display: inline-block; margin-right: 8px;">
                                                <strong>A:</strong>
                                                <span class="ham-stage-badge ham-stage-not"><?php echo esc_html($total_ank['not']); ?></span>
                                                <span class="ham-stage-badge ham-stage-trans"><?php echo esc_html($total_ank['trans']); ?></span>
                                                <span class="ham-stage-badge ham-stage-full"><?php echo esc_html($total_ank['full']); ?></span>
                                            </span>
                                        </td>
                                        <td>
                                            <span style="display: inline-block;">
                                                <strong>B:</strong>
                                                <span class="ham-stage-badge ham-stage-not"><?php echo esc_html($total_ans['not']); ?></span>
                                                <span class="ham-stage-badge ham-stage-trans"><?php echo esc_html($total_ans['trans']); ?></span>
                                                <span class="ham-stage-badge ham-stage-full"><?php echo esc_html($total_ans['full']); ?></span>
                                            </span>
                                        </td>
                                        <td></td>
                                    </tr>
                                <?php else : ?>
                                    <tr><td colspan="7"><?php echo esc_html__('No schools found.', 'headless-access-manager'); ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($drilldown['level'] === 'school') : ?>

                <?php if (!$hide_avg_progress_charts) : ?>

                    <div id="ham-postbox-school-progress" class="postbox">
                        <div class="postbox-header"><h2 class="hndle"><?php echo esc_html__('School average progress', 'headless-access-manager'); ?></h2></div>
                        <div class="inside">
                    <div class="ham-radar-toggle ham-progress-toggle" role="group" aria-label="<?php echo esc_attr__('Time bucket', 'headless-access-manager'); ?>">
                        <button type="button" class="button ham-progress-toggle-btn" data-bucket="current_term"><?php echo esc_html__('Current term', 'headless-access-manager'); ?></button>
                        <button type="button" class="button ham-progress-toggle-btn" data-bucket="previous_term"><?php echo esc_html__('Previous term', 'headless-access-manager'); ?></button>
                        <button type="button" class="button ham-progress-toggle-btn" data-bucket="school_year"><?php echo esc_html__('School year', 'headless-access-manager'); ?></button>
                        <button type="button" class="button ham-progress-toggle-btn" data-bucket="hogstadium"><?php echo esc_html__('L/M/H-stadium', 'headless-access-manager'); ?></button>
                    </div>
                    <details class="ham-date-range" style="margin: 8px 0 0;">
                        <summary class="button" style="cursor: pointer; user-select: none;">
                            <?php echo esc_html__('Filter by date:', 'headless-access-manager'); ?>
                            <span class="ham-date-summary" data-all-dates="<?php echo esc_attr__('All Dates', 'headless-access-manager'); ?>" style="font-weight: 600; margin-left: 6px;"><?php echo esc_html__('All Dates', 'headless-access-manager'); ?></span>
                        </summary>
                        <div style="margin-top: 8px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <label style="display: inline-flex; gap: 6px; align-items: center;">
                                <span><?php echo esc_html__('From', 'headless-access-manager'); ?></span>
                                <input type="text" class="ham-date-from" inputmode="numeric" placeholder="YYYY-MM" pattern="^\d{4}-\d{2}$" />
                            </label>
                            <label style="display: inline-flex; gap: 6px; align-items: center;">
                                <span><?php echo esc_html__('To', 'headless-access-manager'); ?></span>
                                <input type="text" class="ham-date-to" inputmode="numeric" placeholder="YYYY-MM" pattern="^\d{4}-\d{2}$" />
                            </label>
                            <button type="button" class="button ham-date-clear"><?php echo esc_html__('Clear', 'headless-access-manager'); ?></button>
                        </div>
                    </details>
                    <div class="ham-chart-wrapper ham-chart-wrapper--xs"><canvas id="ham-avg-progress-drilldown"></canvas></div>

                        </div>
                    </div>
                <?php endif; ?>

                <div id="ham-postbox-school-radar" class="postbox">
                    <div class="postbox-header"><h2 class="hndle"><?php echo esc_html__('Radar (avg per question)', 'headless-access-manager'); ?></h2></div>
                    <div class="inside">

                <div class="ham-radar-toggle" role="group" aria-label="<?php echo esc_attr__('Time bucket', 'headless-access-manager'); ?>">
                    <button type="button" class="button ham-group-radar-toggle-btn" data-bucket="current_term"><?php echo esc_html__('Current term', 'headless-access-manager'); ?></button>
                    <button type="button" class="button ham-group-radar-toggle-btn" data-bucket="previous_term"><?php echo esc_html__('Previous term', 'headless-access-manager'); ?></button>
                    <button type="button" class="button ham-group-radar-toggle-btn" data-bucket="school_year"><?php echo esc_html__('School year', 'headless-access-manager'); ?></button>
                    <button type="button" class="button ham-group-radar-toggle-btn" data-bucket="hogstadium"><?php echo esc_html__('L/M/H-stadium', 'headless-access-manager'); ?></button>
                </div>
                <details class="ham-date-range" style="margin: 8px 0 0;">
                    <summary class="button" style="cursor: pointer; user-select: none;">
                        <?php echo esc_html__('Filter by date:', 'headless-access-manager'); ?>
                        <span class="ham-date-summary" data-all-dates="<?php echo esc_attr__('All Dates', 'headless-access-manager'); ?>" style="font-weight: 600; margin-left: 6px;"><?php echo esc_html__('All Dates', 'headless-access-manager'); ?></span>
                    </summary>
                    <div style="margin-top: 8px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <label style="display: inline-flex; gap: 6px; align-items: center;">
                            <span><?php echo esc_html__('From', 'headless-access-manager'); ?></span>
                            <input type="text" class="ham-date-from" inputmode="numeric" placeholder="YYYY-MM" pattern="^\d{4}-\d{2}$" />
                        </label>
                        <label style="display: inline-flex; gap: 6px; align-items: center;">
                            <span><?php echo esc_html__('To', 'headless-access-manager'); ?></span>
                            <input type="text" class="ham-date-to" inputmode="numeric" placeholder="YYYY-MM" pattern="^\d{4}-\d{2}$" />
                        </label>
                        <button type="button" class="button ham-date-clear"><?php echo esc_html__('Clear', 'headless-access-manager'); ?></button>
                    </div>
                </details>
                <div class="ham-chart-wrapper ham-chart-wrapper--lg"><canvas id="ham-group-radar"></canvas></div>

                    </div>
                </div>

                <div id="ham-postbox-school-radar-table" class="postbox">
                    <div class="postbox-header"><h2 class="hndle"><?php echo esc_html__('Radar values', 'headless-access-manager'); ?></h2></div>
                    <div class="inside">
                        <div id="ham-group-radar-table" class="ham-radar-values"></div>
                    </div>
                </div>

                <div id="ham-postbox-classes" class="postbox">
                    <div class="postbox-header"><h2 class="hndle"><?php echo esc_html__('Classes', 'headless-access-manager'); ?></h2></div>
                    <div class="inside">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th class="ham-col-20"><?php echo esc_html__('Class', 'headless-access-manager'); ?></th>
                                    <th class="ham-col-8"><?php echo esc_html__('# Students', 'headless-access-manager'); ?></th>
                                    <th class="ham-col-8"><?php echo esc_html__('Observationer', 'headless-access-manager'); ?></th>
                                    <th class="ham-col-15"><?php echo esc_html__('Anknytning', 'headless-access-manager'); ?></th>
                                    <th class="ham-col-15"><?php echo esc_html__('Ansvar', 'headless-access-manager'); ?></th>
                                    <th class="ham-col-35"><?php echo esc_html__('Utveckling', 'headless-access-manager'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($drilldown['classes'])) : ?>
                                    <?php
                                    $total_ank = array('not' => 0, 'trans' => 0, 'full' => 0);
                                    $total_ans = array('not' => 0, 'trans' => 0, 'full' => 0);
                                    ?>
                                    <?php foreach ($drilldown['classes'] as $class) : ?>
                                        <?php
                                        $sec = isset($class['section_counts']) && is_array($class['section_counts']) ? $class['section_counts'] : array();
                                        $ank = isset($sec['anknytning']) ? $sec['anknytning'] : array('not' => 0, 'trans' => 0, 'full' => 0);
                                        $ans = isset($sec['ansvar']) ? $sec['ansvar'] : array('not' => 0, 'trans' => 0, 'full' => 0);
                                        $total_ank['not'] += (int) ($ank['not'] ?? 0);
                                        $total_ank['trans'] += (int) ($ank['trans'] ?? 0);
                                        $total_ank['full'] += (int) ($ank['full'] ?? 0);
                                        $total_ans['not'] += (int) ($ans['not'] ?? 0);
                                        $total_ans['trans'] += (int) ($ans['trans'] ?? 0);
                                        $total_ans['full'] += (int) ($ans['full'] ?? 0);
                                        ?>
                                        <tr>
                                            <td>
                                                <a class="ham-pill-link" href="<?php echo esc_url($class['url']); ?>"><?php echo esc_html($class['name']); ?></a>
                                            </td>
                                            <td><?php echo esc_html((int) $class['student_count']); ?></td>
                                            <td><?php echo esc_html((int) $class['evaluation_count']); ?></td>
                                            <td>
                                                <span style="display: inline-block; margin-right: 8px;" title="<?php esc_attr_e('Anknytning', 'headless-access-manager'); ?>">
                                                    <strong>A:</strong>
                                                    <span class="ham-stage-badge ham-stage-not" title="<?php esc_attr_e('Ej', 'headless-access-manager'); ?>"><?php echo esc_html($ank['not'] ?? 0); ?></span>
                                                    <span class="ham-stage-badge ham-stage-trans" title="<?php esc_attr_e('Utv.', 'headless-access-manager'); ?>"><?php echo esc_html($ank['trans'] ?? 0); ?></span>
                                                    <span class="ham-stage-badge ham-stage-full" title="<?php esc_attr_e('Ok', 'headless-access-manager'); ?>"><?php echo esc_html($ank['full'] ?? 0); ?></span>
                                                </span>
                                            </td>
                                            <td>
                                                <span style="display: inline-block;" title="<?php esc_attr_e('Ansvar', 'headless-access-manager'); ?>">
                                                    <strong>B:</strong>
                                                    <span class="ham-stage-badge ham-stage-not" title="<?php esc_attr_e('Ej', 'headless-access-manager'); ?>"><?php echo esc_html($ans['not'] ?? 0); ?></span>
                                                    <span class="ham-stage-badge ham-stage-trans" title="<?php esc_attr_e('Utv.', 'headless-access-manager'); ?>"><?php echo esc_html($ans['trans'] ?? 0); ?></span>
                                                    <span class="ham-stage-badge ham-stage-full" title="<?php esc_attr_e('Ok', 'headless-access-manager'); ?>"><?php echo esc_html($ans['full'] ?? 0); ?></span>
                                                </span>
                                            </td>
                                            <td>
                                                <?php $render_semester_bars($class['series'], 100); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <tr>
                                        <td style="font-weight: 700;"><?php echo esc_html__('Total', 'headless-access-manager'); ?></td>
                                        <td></td>
                                        <td></td>
                                        <td>
                                            <span style="display: inline-block; margin-right: 8px;">
                                                <strong>A:</strong>
                                                <span class="ham-stage-badge ham-stage-not"><?php echo esc_html($total_ank['not']); ?></span>
                                                <span class="ham-stage-badge ham-stage-trans"><?php echo esc_html($total_ank['trans']); ?></span>
                                                <span class="ham-stage-badge ham-stage-full"><?php echo esc_html($total_ank['full']); ?></span>
                                            </span>
                                            </td>
                                            <td>
                                            <span style="display: inline-block;">
                                                <strong>B:</strong>
                                                <span class="ham-stage-badge ham-stage-not"><?php echo esc_html($total_ans['not']); ?></span>
                                                <span class="ham-stage-badge ham-stage-trans"><?php echo esc_html($total_ans['trans']); ?></span>
                                                <span class="ham-stage-badge ham-stage-full"><?php echo esc_html($total_ans['full']); ?></span>
                                            </span>
                                        </td>
                                        <td></td>
                                    </tr>
                                <?php else : ?>
                                    <tr><td colspan="6"><?php echo esc_html__('No classes found for this school.', 'headless-access-manager'); ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($drilldown['level'] === 'class') : ?>

                <?php if (!$hide_avg_progress_charts) : ?>

                    <div id="ham-postbox-class-progress" class="postbox">
                        <div class="postbox-header"><h2 class="hndle"><?php echo esc_html__('Class average progress', 'headless-access-manager'); ?></h2></div>
                        <div class="inside">
                    <div class="ham-radar-toggle ham-progress-toggle" role="group" aria-label="<?php echo esc_attr__('Time bucket', 'headless-access-manager'); ?>">
                        <button type="button" class="button ham-progress-toggle-btn" data-bucket="current_term"><?php echo esc_html__('Current term', 'headless-access-manager'); ?></button>
                        <button type="button" class="button ham-progress-toggle-btn" data-bucket="previous_term"><?php echo esc_html__('Previous term', 'headless-access-manager'); ?></button>
                        <button type="button" class="button ham-progress-toggle-btn" data-bucket="school_year"><?php echo esc_html__('School year', 'headless-access-manager'); ?></button>
                        <button type="button" class="button ham-progress-toggle-btn" data-bucket="hogstadium"><?php echo esc_html__('L/M/H-stadium', 'headless-access-manager'); ?></button>
                    </div>
                    <details class="ham-date-range" style="margin: 8px 0 0;">
                        <summary class="button" style="cursor: pointer; user-select: none;">
                            <?php echo esc_html__('Filter by date:', 'headless-access-manager'); ?>
                            <span class="ham-date-summary" data-all-dates="<?php echo esc_attr__('All Dates', 'headless-access-manager'); ?>" style="font-weight: 600; margin-left: 6px;"><?php echo esc_html__('All Dates', 'headless-access-manager'); ?></span>
                        </summary>
                        <div style="margin-top: 8px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <label style="display: inline-flex; gap: 6px; align-items: center;">
                                <span><?php echo esc_html__('From', 'headless-access-manager'); ?></span>
                                <input type="text" class="ham-date-from" inputmode="numeric" placeholder="YYYY-MM" pattern="^\d{4}-\d{2}$" />
                            </label>
                            <label style="display: inline-flex; gap: 6px; align-items: center;">
                                <span><?php echo esc_html__('To', 'headless-access-manager'); ?></span>
                                <input type="text" class="ham-date-to" inputmode="numeric" placeholder="YYYY-MM" pattern="^\d{4}-\d{2}$" />
                            </label>
                            <button type="button" class="button ham-date-clear"><?php echo esc_html__('Clear', 'headless-access-manager'); ?></button>
                        </div>
                    </details>
                    <div class="ham-chart-wrapper ham-chart-wrapper--xs"><canvas id="ham-avg-progress-drilldown"></canvas></div>

                        </div>
                    </div>
                <?php endif; ?>

                <div id="ham-postbox-class-radar" class="postbox">
                    <div class="postbox-header"><h2 class="hndle"><?php echo esc_html__('Radar (avg per question)', 'headless-access-manager'); ?></h2></div>
                    <div class="inside">
                <div class="ham-radar-toggle" role="group" aria-label="<?php echo esc_attr__('Time bucket', 'headless-access-manager'); ?>">
                    <button type="button" class="button ham-group-radar-toggle-btn" data-bucket="current_term"><?php echo esc_html__('Current term', 'headless-access-manager'); ?></button>
                    <button type="button" class="button ham-group-radar-toggle-btn" data-bucket="previous_term"><?php echo esc_html__('Previous term', 'headless-access-manager'); ?></button>
                    <button type="button" class="button ham-group-radar-toggle-btn" data-bucket="school_year"><?php echo esc_html__('School year', 'headless-access-manager'); ?></button>
                    <button type="button" class="button ham-group-radar-toggle-btn" data-bucket="hogstadium"><?php echo esc_html__('L/M/H-stadium', 'headless-access-manager'); ?></button>
                </div>
                <details class="ham-date-range" style="margin: 8px 0 0;">
                    <summary class="button" style="cursor: pointer; user-select: none;">
                        <?php echo esc_html__('Filter by date:', 'headless-access-manager'); ?>
                        <span class="ham-date-summary" data-all-dates="<?php echo esc_attr__('All Dates', 'headless-access-manager'); ?>" style="font-weight: 600; margin-left: 6px;"><?php echo esc_html__('All Dates', 'headless-access-manager'); ?></span>
                    </summary>
                    <div style="margin-top: 8px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <label style="display: inline-flex; gap: 6px; align-items: center;">
                            <span><?php echo esc_html__('From', 'headless-access-manager'); ?></span>
                            <input type="text" class="ham-date-from" inputmode="numeric" placeholder="YYYY-MM" pattern="^\d{4}-\d{2}$" />
                        </label>
                        <label style="display: inline-flex; gap: 6px; align-items: center;">
                            <span><?php echo esc_html__('To', 'headless-access-manager'); ?></span>
                            <input type="text" class="ham-date-to" inputmode="numeric" placeholder="YYYY-MM" pattern="^\d{4}-\d{2}$" />
                        </label>
                        <button type="button" class="button ham-date-clear"><?php echo esc_html__('Clear', 'headless-access-manager'); ?></button>
                    </div>
                </details>
                <div class="ham-chart-wrapper ham-chart-wrapper--lg"><canvas id="ham-group-radar"></canvas></div>

                    </div>
                </div>

                <div id="ham-postbox-class-radar-table" class="postbox">
                    <div class="postbox-header"><h2 class="hndle"><?php echo esc_html__('Radar values', 'headless-access-manager'); ?></h2></div>
                    <div class="inside">
                        <div id="ham-group-radar-table" class="ham-radar-values"></div>
                    </div>
                </div>

                <div id="ham-postbox-class-students" class="postbox">
                    <div class="postbox-header"><h2 class="hndle"><?php echo esc_html__('Students', 'headless-access-manager'); ?></h2></div>
                    <div class="inside">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th class="ham-col-15"><?php echo esc_html__('Student', 'headless-access-manager'); ?></th>
                                    <th class="ham-col-8"><?php echo esc_html__('Observationer', 'headless-access-manager'); ?></th>
                                    <th class="ham-col-30"><?php echo esc_html__('Status Anknytning/Ansvar', 'headless-access-manager'); ?></th>
                                    <th class="ham-col-45"><?php echo esc_html__('Utveckling', 'headless-access-manager'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($drilldown['students'])) : ?>
                                    <?php
                                    // Helper function for stage badge display
                                    $get_stage_badge = function($stage) {
                                        $stage = (string) $stage;
                                        if ($stage === 'full') {
                                            return array('class' => 'ham-stage-full', 'text' => 'Ok');
                                        } elseif ($stage === 'trans') {
                                            return array('class' => 'ham-stage-trans', 'text' => 'Utv.');
                                        } else {
                                            return array('class' => 'ham-stage-not', 'text' => 'Ej');
                                        }
                                    };
                                    ?>
                                    <?php foreach ($drilldown['students'] as $student) : ?>
                                        <?php
                                        $stage_ank = isset($student['stage_anknytning']) ? (string) $student['stage_anknytning'] : 'not';
                                        $stage_ans = isset($student['stage_ansvar']) ? (string) $student['stage_ansvar'] : 'not';
                                        $badge_ank = $get_stage_badge($stage_ank);
                                        $badge_ans = $get_stage_badge($stage_ans);
                                        ?>
                                        <tr>
                                            <td>
                                                <a class="ham-pill-link" href="<?php echo esc_url($student['url']); ?>"><?php echo esc_html($student['name']); ?></a>
                                            </td>
                                            <td><?php echo esc_html((int) $student['evaluation_count']); ?></td>
                                            <td>
                                                <span class="ham-stage-badge <?php echo esc_attr($badge_ank['class']); ?>" title="<?php esc_attr_e('Anknytning', 'headless-access-manager'); ?>">A: <?php echo esc_html($badge_ank['text']); ?></span>
                                                <span class="ham-stage-badge <?php echo esc_attr($badge_ans['class']); ?>" title="<?php esc_attr_e('Ansvar', 'headless-access-manager'); ?>">B: <?php echo esc_html($badge_ans['text']); ?></span>
                                            </td>
                                            <td>
                                                <?php $render_semester_bars($student['series'], 100); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <tr>
                                        <td style="font-weight: 700;"><?php echo esc_html__('Total', 'headless-access-manager'); ?></td>
                                        <td></td>
                                        <td>
                                            <span style="font-size: 11px; color: #646970;"><?php esc_html_e('See individual rows', 'headless-access-manager'); ?></span>
                                        </td>
                                        <td></td>
                                    </tr>
                                <?php else : ?>
                                    <tr><td colspan="4"><?php echo esc_html__('No students found for this class.', 'headless-access-manager'); ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($drilldown['level'] === 'student') : ?>

                <?php if (!$hide_avg_progress_charts) : ?>
                    <div id="ham-postbox-student-progress" class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle">
                                <?php echo esc_html__('Student average progress', 'headless-access-manager'); ?>
                                <?php if (!empty($drilldown['student']['name'])) : ?>
                                    <span style="color: #646970; font-weight: normal;">— <?php echo esc_html($drilldown['student']['name']); ?></span>
                                <?php endif; ?>
                            </h2>
                        </div>
                        <div class="inside">
                    <div class="ham-radar-toggle ham-progress-toggle" role="group" aria-label="<?php echo esc_attr__('Time bucket', 'headless-access-manager'); ?>">
                        <button type="button" class="button ham-progress-toggle-btn" data-bucket="current_term"><?php echo esc_html__('Current term', 'headless-access-manager'); ?></button>
                        <button type="button" class="button ham-progress-toggle-btn" data-bucket="previous_term"><?php echo esc_html__('Previous term', 'headless-access-manager'); ?></button>
                        <button type="button" class="button ham-progress-toggle-btn" data-bucket="school_year"><?php echo esc_html__('School year', 'headless-access-manager'); ?></button>
                        <button type="button" class="button ham-progress-toggle-btn" data-bucket="hogstadium"><?php echo esc_html__('L/M/H-stadium', 'headless-access-manager'); ?></button>
                    </div>
                    <details class="ham-date-range" style="margin: 8px 0 0;">
                        <summary class="button" style="cursor: pointer; user-select: none;">
                            <?php echo esc_html__('Filter by date:', 'headless-access-manager'); ?>
                            <span class="ham-date-summary" data-all-dates="<?php echo esc_attr__('All Dates', 'headless-access-manager'); ?>" style="font-weight: 600; margin-left: 6px;"><?php echo esc_html__('All Dates', 'headless-access-manager'); ?></span>
                        </summary>
                        <div style="margin-top: 8px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <label style="display: inline-flex; gap: 6px; align-items: center;">
                                <span><?php echo esc_html__('From', 'headless-access-manager'); ?></span>
                                <input type="text" class="ham-date-from" inputmode="numeric" placeholder="YYYY-MM" pattern="^\d{4}-\d{2}$" />
                            </label>
                            <label style="display: inline-flex; gap: 6px; align-items: center;">
                                <span><?php echo esc_html__('To', 'headless-access-manager'); ?></span>
                                <input type="text" class="ham-date-to" inputmode="numeric" placeholder="YYYY-MM" pattern="^\d{4}-\d{2}$" />
                            </label>
                            <button type="button" class="button ham-date-clear"><?php echo esc_html__('Clear', 'headless-access-manager'); ?></button>
                        </div>
                    </details>
                    <div class="ham-chart-wrapper ham-chart-wrapper--xs"><canvas id="ham-avg-progress-student"></canvas></div>
                        </div>
                    </div>
                <?php endif; ?>

                <div id="ham-postbox-student-radar" class="postbox">
                    <div class="postbox-header"><h2 class="hndle"><?php echo esc_html__('Radar (per evaluation within bucket)', 'headless-access-manager'); ?></h2></div>
                    <div class="inside">
                <div class="ham-radar-toggle" role="group" aria-label="<?php echo esc_attr__('Time bucket', 'headless-access-manager'); ?>">
                    <button type="button" class="button ham-radar-toggle-btn" data-bucket="current_term"><?php echo esc_html__('Current term', 'headless-access-manager'); ?></button>
                    <button type="button" class="button ham-radar-toggle-btn" data-bucket="previous_term"><?php echo esc_html__('Previous term', 'headless-access-manager'); ?></button>
                    <button type="button" class="button ham-radar-toggle-btn" data-bucket="school_year"><?php echo esc_html__('School year', 'headless-access-manager'); ?></button>
                    <button type="button" class="button ham-radar-toggle-btn" data-bucket="hogstadium"><?php echo esc_html__('L/M/H-stadium', 'headless-access-manager'); ?></button>
                </div>
                <details class="ham-date-range" style="margin: 8px 0 0;">
                    <summary class="button" style="cursor: pointer; user-select: none;">
                        <?php echo esc_html__('Filter by date:', 'headless-access-manager'); ?>
                        <span class="ham-date-summary" data-all-dates="<?php echo esc_attr__('All Dates', 'headless-access-manager'); ?>" style="font-weight: 600; margin-left: 6px;"><?php echo esc_html__('All Dates', 'headless-access-manager'); ?></span>
                    </summary>
                    <div style="margin-top: 8px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <label style="display: inline-flex; gap: 6px; align-items: center;">
                            <span><?php echo esc_html__('From', 'headless-access-manager'); ?></span>
                            <input type="text" class="ham-date-from" inputmode="numeric" placeholder="YYYY-MM" pattern="^\d{4}-\d{2}$" />
                        </label>
                        <label style="display: inline-flex; gap: 6px; align-items: center;">
                            <span><?php echo esc_html__('To', 'headless-access-manager'); ?></span>
                            <input type="text" class="ham-date-to" inputmode="numeric" placeholder="YYYY-MM" pattern="^\d{4}-\d{2}$" />
                        </label>
                        <button type="button" class="button ham-date-clear"><?php echo esc_html__('Clear', 'headless-access-manager'); ?></button>
                    </div>
                </details>
                <div style="display: flex; gap: 20px; align-items: flex-start;">
                    <div id="ham-student-radar-legend" class="ham-chart-legend" style="flex: 0 0 auto; min-width: 180px; max-width: 250px;"></div>
                    <div class="ham-chart-wrapper ham-chart-wrapper--lg" style="flex: 1 1 auto;"><canvas id="ham-student-radar"></canvas></div>
                </div>

                    </div>
                </div>

                <div id="ham-postbox-student-radar-table" class="postbox">
                    <div class="postbox-header"><h2 class="hndle"><?php echo esc_html__('Radar values', 'headless-access-manager'); ?></h2></div>
                    <div class="inside">
                        <div id="ham-student-radar-table" class="ham-radar-values"></div>
                    </div>
                </div>

                <div id="ham-postbox-student-answers" class="postbox">
                    <div class="postbox-header"><h2 class="hndle"><?php echo esc_html__('Questions and answer alternatives', 'headless-access-manager'); ?></h2></div>
                    <div class="inside">
                <?php if (current_user_can('manage_options') && isset($drilldown['radar_questions_source']) && $drilldown['radar_questions_source'] === 'fallback') : ?>
                    <div style="margin-top: -10px; margin-bottom: 10px; color: #b32d2e; font-weight: 600;">
                        <?php echo esc_html__('Fallback questions', 'headless-access-manager'); ?>
                    </div>
                <?php endif; ?>
                				<div class="ham-radar-toggle ham-answer-toggle" role="group" aria-label="<?php echo esc_attr__('Time bucket', 'headless-access-manager'); ?>">
					<button type="button" class="button ham-answer-toggle-btn" data-bucket="current_term"><?php echo esc_html__('Current term', 'headless-access-manager'); ?></button>
					<button type="button" class="button ham-answer-toggle-btn" data-bucket="previous_term"><?php echo esc_html__('Previous term', 'headless-access-manager'); ?></button>
					<button type="button" class="button ham-answer-toggle-btn" data-bucket="school_year"><?php echo esc_html__('School year', 'headless-access-manager'); ?></button>
					<button type="button" class="button ham-answer-toggle-btn" data-bucket="hogstadium"><?php echo esc_html__('L/M/H-stadium', 'headless-access-manager'); ?></button>
				</div>
                </div>
                <details class="ham-date-range" style="margin: 8px 0 0;">
                    <summary class="button" style="cursor: pointer; user-select: none;">
                        <?php echo esc_html__('Filter by date:', 'headless-access-manager'); ?>
                        <span class="ham-date-summary" data-all-dates="<?php echo esc_attr__('All Dates', 'headless-access-manager'); ?>" style="font-weight: 600; margin-left: 6px;"><?php echo esc_html__('All Dates', 'headless-access-manager'); ?></span>
                    </summary>
                    <div style="margin-top: 8px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <label style="display: inline-flex; gap: 6px; align-items: center;">
                            <span><?php echo esc_html__('From', 'headless-access-manager'); ?></span>
                            <input type="text" class="ham-date-from" inputmode="numeric" placeholder="YYYY-MM" pattern="^\d{4}-\d{2}$" />
                        </label>
                        <label style="display: inline-flex; gap: 6px; align-items: center;">
                            <span><?php echo esc_html__('To', 'headless-access-manager'); ?></span>
                            <input type="text" class="ham-date-to" inputmode="numeric" placeholder="YYYY-MM" pattern="^\d{4}-\d{2}$" />
                        </label>
                        <button type="button" class="button ham-date-clear"><?php echo esc_html__('Clear', 'headless-access-manager'); ?></button>
                    </div>
                </details>
                <div id="ham-answer-alternatives" class="ham-answer-alternatives"></div>

                    </div>
                </div>

            <?php endif; ?>

                    </div>
                </div>
            </div>
            <div class="clear"></div>
        </div>
    <?php endif; ?>

    <?php
    $is_deep_drilldown = isset($drilldown) && is_array($drilldown) && isset($drilldown['level']) && $drilldown['level'] !== 'schools';
    ?>

<style>
/* Statistics Page Styles */

/* Table column widths for stats tables */
.ham-col-5 { width: 5%; }
.ham-col-8 { width: 8%; }
.ham-col-10 { width: 10%; }
.ham-col-12 { width: 12%; }
.ham-col-15 { width: 15%; }
.ham-col-20 { width: 20%; }
.ham-col-25 { width: 25%; }
.ham-col-30 { width: 30%; }
.ham-col-35 { width: 35%; }
.ham-col-40 { width: 40%; }
.ham-col-45 { width: 45%; }

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
    background-color: transparent;
    border-radius: 0;
    box-shadow: none;
    padding: 0;
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

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
?>
<div class="wrap">
    <h1><?php echo esc_html__('Assessment Statistics', 'headless-access-manager'); ?></h1>
    
    <div class="ham-stats-overview">
        <div class="ham-stats-card">
            <div class="ham-stats-icon">
                <span class="dashicons dashicons-clipboard"></span>
            </div>
            <div class="ham-stats-data">
                <div class="ham-stats-value"><?php echo esc_html($stats['total_assessments']); ?></div>
                <div class="ham-stats-label"><?php echo esc_html__('Total Assessments', 'headless-access-manager'); ?></div>
            </div>
        </div>
        
        <div class="ham-stats-card">
            <div class="ham-stats-icon">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <div class="ham-stats-data">
                <div class="ham-stats-value"><?php echo esc_html($stats['total_students']); ?></div>
                <div class="ham-stats-label"><?php echo esc_html__('Students Assessed', 'headless-access-manager'); ?></div>
            </div>
        </div>
        
        <div class="ham-stats-card">
            <div class="ham-stats-icon">
                <span class="dashicons dashicons-chart-bar"></span>
            </div>
            <div class="ham-stats-data">
                <div class="ham-stats-value"><?php echo esc_html($stats['average_completion']); ?>%</div>
                <div class="ham-stats-label"><?php echo esc_html__('Average Completion', 'headless-access-manager'); ?></div>
            </div>
        </div>
        
        <div class="ham-stats-card">
            <div class="ham-stats-icon">
                <span class="dashicons dashicons-calendar-alt"></span>
            </div>
            <div class="ham-stats-data">
                <div class="ham-stats-value">
                    <?php 
                    if (!empty($stats['monthly_submissions'])) {
                        $latest_month = end($stats['monthly_submissions']);
                        echo esc_html($latest_month['count']);
                    } else {
                        echo '0';
                    }
                    ?>
                </div>
                <div class="ham-stats-label"><?php echo esc_html__('Assessments This Month', 'headless-access-manager'); ?></div>
            </div>
        </div>
    </div>
    
    <div class="ham-stats-row">
        <div class="ham-stats-column">
            <div class="ham-stats-panel">
                <h2><?php echo esc_html__('Monthly Submissions', 'headless-access-manager'); ?></h2>
                <div class="ham-chart-container">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="ham-stats-column">
            <div class="ham-stats-panel">
                <h2><?php echo esc_html__('Stage Distribution', 'headless-access-manager'); ?></h2>
                <div class="ham-chart-container">
                    <canvas id="stageChart"></canvas>
                </div>
                <div class="ham-chart-legend">
                    <div class="ham-legend-item">
                        <span class="ham-legend-color" style="background-color: #ffecec;"></span>
                        <span class="ham-legend-label"><?php echo esc_html__('Not Achieved', 'headless-access-manager'); ?> (<?php echo esc_html($stats['stage_distribution']['ej']); ?>%)</span>
                    </div>
                    <div class="ham-legend-item">
                        <span class="ham-legend-color" style="background-color: #fcf8e3;"></span>
                        <span class="ham-legend-label"><?php echo esc_html__('Transitional', 'headless-access-manager'); ?> (<?php echo esc_html($stats['stage_distribution']['trans']); ?>%)</span>
                    </div>
                    <div class="ham-legend-item">
                        <span class="ham-legend-color" style="background-color: #ecf8ec;"></span>
                        <span class="ham-legend-label"><?php echo esc_html__('Fully Achieved', 'headless-access-manager'); ?> (<?php echo esc_html($stats['stage_distribution']['full']); ?>%)</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="ham-stats-row">
        <div class="ham-stats-column">
            <div class="ham-stats-panel">
                <h2><?php echo esc_html__('Section Averages', 'headless-access-manager'); ?></h2>
                <div class="ham-chart-container">
                    <canvas id="sectionChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="ham-stats-column">
            <div class="ham-stats-panel">
                <h2><?php echo esc_html__('Top Questions', 'headless-access-manager'); ?></h2>
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
                        $questions_structure = (new HAM_Evaluation_Manager())->get_questions_structure();
                        
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

<script>
jQuery(document).ready(function($) {
    // Chart.js configuration
    Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';
    Chart.defaults.font.size = 13;
    Chart.defaults.color = '#666';
    
    // Monthly Submissions Chart
    const monthlyData = <?php echo json_encode(array_map(function($item) {
        return array(
            'month' => date_i18n('M Y', strtotime($item['month'] . '-01')),
            'count' => $item['count']
        );
    }, $stats['monthly_submissions'])); ?>;
    
    new Chart(document.getElementById('monthlyChart'), {
        type: 'bar',
        data: {
            labels: monthlyData.map(item => item.month),
            datasets: [{
                label: '<?php echo esc_js(__('Assessments', 'headless-access-manager')); ?>',
                data: monthlyData.map(item => item.count),
                backgroundColor: '#0073aa',
                borderColor: '#0073aa',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
    
    // Stage Distribution Chart
    new Chart(document.getElementById('stageChart'), {
        type: 'pie',
        data: {
            labels: [
                '<?php echo esc_js(__('Not Achieved', 'headless-access-manager')); ?>',
                '<?php echo esc_js(__('Transitional', 'headless-access-manager')); ?>',
                '<?php echo esc_js(__('Fully Achieved', 'headless-access-manager')); ?>'
            ],
            datasets: [{
                data: [
                    <?php echo esc_js($stats['stage_distribution']['ej']); ?>,
                    <?php echo esc_js($stats['stage_distribution']['trans']); ?>,
                    <?php echo esc_js($stats['stage_distribution']['full']); ?>
                ],
                backgroundColor: ['#ffecec', '#fcf8e3', '#ecf8ec'],
                borderColor: ['#d63638', '#8a6d3b', '#2a9d2a'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.label + ': ' + context.raw + '%';
                        }
                    }
                }
            }
        }
    });
    
    // Section Averages Chart
    new Chart(document.getElementById('sectionChart'), {
        type: 'bar',
        data: {
            labels: [
                '<?php echo esc_js(__('Anknytning', 'headless-access-manager')); ?>',
                '<?php echo esc_js(__('Ansvar', 'headless-access-manager')); ?>'
            ],
            datasets: [{
                label: '<?php echo esc_js(__('Average Score', 'headless-access-manager')); ?>',
                data: [
                    <?php echo esc_js($stats['section_averages']['anknytning']); ?>,
                    <?php echo esc_js($stats['section_averages']['ansvar']); ?>
                ],
                backgroundColor: ['#0073aa', '#00a0d2'],
                borderColor: ['#0073aa', '#00a0d2'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 5,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
});
</script>

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

@media screen and (max-width: 782px) {
    .ham-stats-row {
        flex-direction: column;
    }
    
    .ham-stats-column {
        width: 100%;
    }
}
</style>

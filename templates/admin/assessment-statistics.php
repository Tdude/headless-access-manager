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
    <h1><?php echo esc_html__('Bedömningsstatistik', 'headless-access-manager'); ?></h1>
    
    <div class="ham-stats-overview">
        <div class="ham-stats-card">
            <div class="ham-stats-icon">
                <span class="dashicons dashicons-clipboard"></span>
            </div>
            <div class="ham-stats-data">
                <div class="ham-stats-value"><?php echo esc_html($stats['total_assessments']); ?></div>
                <div class="ham-stats-label"><?php echo esc_html__('Totalt antal bedömningar', 'headless-access-manager'); ?></div>
            </div>
        </div>
        
        <div class="ham-stats-card">
            <div class="ham-stats-icon">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <div class="ham-stats-data">
                <div class="ham-stats-value"><?php echo esc_html($stats['total_students']); ?></div>
                <div class="ham-stats-label"><?php echo esc_html__('Bedömda elever', 'headless-access-manager'); ?></div>
            </div>
        </div>
        
        <div class="ham-stats-card">
            <div class="ham-stats-icon">
                <span class="dashicons dashicons-chart-bar"></span>
            </div>
            <div class="ham-stats-data">
                <div class="ham-stats-value"><?php echo esc_html($stats['average_completion']); ?>%</div>
                <div class="ham-stats-label"><?php echo esc_html__('Genomsnittlig slutförandegrad', 'headless-access-manager'); ?></div>
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
                <div class="ham-stats-label"><?php echo esc_html__('Bedömningar denna månad', 'headless-access-manager'); ?></div>
            </div>
        </div>
    </div>
    
    <div class="ham-stats-row">
        <div class="ham-stats-column">
            <div class="ham-stats-panel">
                <h2><?php echo esc_html__('Månatliga inlämningar', 'headless-access-manager'); ?></h2>
                <div id="monthlyChartSimple" class="ham-chart-container" style="padding: 20px; text-align: center;">
                    <?php
                    $monthlyData = array_map(function($item) {
                        return array(
                            'month' => date_i18n('M Y', strtotime($item['month'] . '-01')),
                            'count' => $item['count']
                        );
                    }, $stats['monthly_submissions']);
                    
                    if (empty($monthlyData)) {
                        echo '<p>' . esc_html__('Inga data att visa', 'headless-access-manager') . '</p>';
                    } else {
                        echo '<div class="ham-simple-chart">';
                        foreach ($monthlyData as $item) {
                            $height = $item['count'] * 20; // 20px per unit
                            echo '<div class="ham-bar-wrapper" style="display: inline-block; margin: 0 10px; text-align: center;">';
                            echo '<div class="ham-bar" style="height: ' . esc_attr($height) . 'px; width: 30px; background-color: #0073aa; display: inline-block;"></div>';
                            echo '<div class="ham-bar-label" style="margin-top: 5px;">' . esc_html($item['month']) . '</div>';
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
                <h2><?php echo esc_html__('Fördelning av nuläge', 'headless-access-manager'); ?></h2>
                <div id="stageChartSimple" class="ham-chart-container" style="padding: 20px; text-align: center;">
                    <?php
                    $stageData = array(
                        array(
                            'label' => esc_html__('Ej anknuten', 'headless-access-manager'),
                            'value' => $stats['stage_distribution']['ej'],
                            'color' => '#ffecec'
                        ),
                        array(
                            'label' => esc_html__('Under utveckling', 'headless-access-manager'),
                            'value' => $stats['stage_distribution']['trans'],
                            'color' => '#fcf8e3'
                        ),
                        array(
                            'label' => esc_html__('Helt anknuten', 'headless-access-manager'),
                            'value' => $stats['stage_distribution']['full'],
                            'color' => '#ecf8ec'
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
        <div class="ham-stats-column">
            <div class="ham-stats-panel">
                <h2><?php echo esc_html__('Snitt per sektion', 'headless-access-manager'); ?></h2>
                <div id="sectionChartSimple" class="ham-chart-container" style="padding: 20px; text-align: center;">
                    <?php
                    $sectionData = array(
                        array(
                            'label' => esc_html__('Anknytning', 'headless-access-manager'),
                            'value' => $stats['section_averages']['anknytning'],
                            'color' => '#0073aa'
                        ),
                        array(
                            'label' => esc_html__('Ansvar', 'headless-access-manager'),
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
                <h2><?php echo esc_html__('Toppfrågor', 'headless-access-manager'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Fråga', 'headless-access-manager'); ?></th>
                            <th><?php echo esc_html__('Sektion', 'headless-access-manager'); ?></th>
                            <th><?php echo esc_html__('Snittbedömning', 'headless-access-manager'); ?></th>
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
                            echo '<tr><td colspan="3">' . esc_html__('Ingen frågedata tillgänglig.', 'headless-access-manager') . '</td></tr>';
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

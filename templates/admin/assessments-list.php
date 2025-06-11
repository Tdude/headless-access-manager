<?php
/**
 * Template for displaying the assessments list in the admin.
 *
 * @package HeadlessAccessManager
 */

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php echo esc_html__('Student Evaluations', 'headless-access-manager'); ?></h1>
    
    <div class="ham-assessments-filters">
        <div class="ham-filter-group">
            <label for="ham-filter-student"><?php echo esc_html__('Filter by student:', 'headless-access-manager'); ?></label>
            <select id="ham-filter-student">
                <option value=""><?php echo esc_html__('All Students', 'headless-access-manager'); ?></option>
                <?php
                $students = get_users(array('role' => HAM_ROLE_STUDENT));
                foreach ($students as $student) {
                    echo '<option value="' . esc_attr($student->ID) . '">' . esc_html($student->display_name) . '</option>';
                }
                ?>
            </select>
        </div>
        
        <div class="ham-filter-group">
            <label for="ham-filter-date"><?php echo esc_html__('Filter by date:', 'headless-access-manager'); ?></label>
            <select id="ham-filter-date">
                <option value=""><?php echo esc_html__('All Dates', 'headless-access-manager'); ?></option>
                <option value="today"><?php echo esc_html__('Today', 'headless-access-manager'); ?></option>
                <option value="yesterday"><?php echo esc_html__('Yesterday', 'headless-access-manager'); ?></option>
                <option value="week"><?php echo esc_html__('This Week', 'headless-access-manager'); ?></option>
                <option value="month"><?php echo esc_html__('This Month', 'headless-access-manager'); ?></option>
                <option value="semester"><?php echo esc_html__('This Semester', 'headless-access-manager'); ?></option>
                <option value="schoolyear"><?php echo esc_html__('This School Year', 'headless-access-manager'); ?></option>
            </select>
        </div>
        
        <div class="ham-filter-group">
            <label for="ham-filter-completion"><?php echo esc_html__('Filter by Status:', 'headless-access-manager'); ?></label>
            <select id="ham-filter-completion">
                <option value=""><?php echo esc_html__('All', 'headless-access-manager'); ?></option>
                <option value="full"><?php echo esc_html__('Fully Established', 'headless-access-manager'); ?></option>
                <option value="transition"><?php echo esc_html__('Developing', 'headless-access-manager'); ?></option>
                <option value="not"><?php echo esc_html__('Not Established', 'headless-access-manager'); ?></option>
            </select>
        </div>
        
        <button id="ham-reset-filters" class="button"><?php echo esc_html__('Reset Filters', 'headless-access-manager'); ?></button>
    </div>
    
    <div class="ham-assessments-container">
        <table class="wp-list-table widefat fixed striped ham-assessments-table">
            <thead>
                <tr>
                    <th class="column-id"><?php echo esc_html__('ID', 'headless-access-manager'); ?></th>
                    <th class="column-title"><?php echo esc_html__('Title', 'headless-access-manager'); ?></th>
                    <th class="column-student"><?php echo esc_html__('Student', 'headless-access-manager'); ?></th>
                    <th class="column-date"><?php echo esc_html__('Date', 'headless-access-manager'); ?></th>
                    <th class="column-author"><?php echo esc_html__('Author', 'headless-access-manager'); ?></th>
                    <th class="column-completion"><?php echo esc_html__('Status', 'headless-access-manager'); ?></th>
                    <th class="column-actions"><?php echo esc_html__('Actions', 'headless-access-manager'); ?></th>
                </tr>
            </thead>
            <tbody id="ham-assessments-list">
                <?php if (empty($assessments)) : ?>
                    <tr>
                        <td colspan="7"><?php echo esc_html__('No evaluations found.', 'headless-access-manager'); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($assessments as $assessment) : ?>
                        <tr data-id="<?php echo esc_attr($assessment['id']); ?>" 
                            data-student="<?php echo esc_attr($assessment['student_id']); ?>"
                            data-date="<?php echo esc_attr(date('Y-m-d', strtotime($assessment['date']))); ?>"
                            data-date-raw="<?php echo esc_attr($assessment['date']); ?>"
                            data-completion="<?php echo esc_attr($assessment['completion']); ?>"
                            data-stage="<?php echo esc_attr($assessment['stage'] ?? 'not'); ?>">
                            <td class="column-id"><?php echo esc_html($assessment['id']); ?></td>
                            <td class="column-title"><?php echo esc_html($assessment['title']); ?></td>
                            <td class="column-student"><?php echo esc_html($assessment['student_name']); ?></td>
                            <td class="column-date"><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($assessment['date']))); ?></td>
                            <td class="column-author" data-author-id="<?php echo esc_attr($assessment['author_id'] ?? 0); ?>"><?php echo esc_html($assessment['author_name']); ?></td>
                            <td class="column-completion">
                                <?php 
                                $stage = $assessment['stage'] ?? 'not';
                                $stage_class = '';
                                $stage_text = '';
                                
                                if ($stage === 'full') {
                                    $stage_class = 'ham-stage-full';
                                    $stage_text = esc_html__('Established', 'headless-access-manager');
                                } elseif ($stage === 'trans') {
                                    $stage_class = 'ham-stage-trans';
                                    $stage_text = esc_html__('Developing', 'headless-access-manager');
                                } else {
                                    $stage_class = 'ham-stage-not';
                                    $stage_text = esc_html__('Not Established', 'headless-access-manager');
                                }
                                ?>
                                <span class="ham-stage-badge <?php echo esc_attr($stage_class); ?>">
                                    <?php echo $stage_text; ?>
                                </span>
                            </td>
                            <td class="column-actions">
                                <button class="button ham-view-assessment" data-id="<?php echo esc_attr($assessment['id']); ?>">
                                    <?php echo esc_html__('View', 'headless-access-manager'); ?>
                                </button>
                                <button class="button ham-delete-assessment" data-id="<?php echo esc_attr($assessment['id']); ?>">
                                    <?php echo esc_html__('Delete', 'headless-access-manager'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Assessment Details Modal -->
<div id="ham-assessment-modal" class="ham-modal">
    <div class="ham-modal-content">
        <div class="ham-modal-header">
            <span class="ham-modal-close">&times;</span>
            <h2 id="ham-modal-title"><?php echo esc_html__('Evaluation Details', 'headless-access-manager'); ?></h2>
        </div>
        <div class="ham-modal-body">
            <div id="ham-assessment-loading"><?php echo esc_html__('Loading...', 'headless-access-manager'); ?></div>
            <div id="ham-assessment-error" style="display: none;"><?php echo esc_html__('Error loading evaluation data.', 'headless-access-manager'); ?></div>
            <div id="ham-assessment-details" style="display: none;">
                <div class="ham-assessment-meta">
                    <div class="ham-meta-item">
                        <strong><?php echo esc_html__('Student:', 'headless-access-manager'); ?></strong>
                        <span id="ham-assessment-student"></span>
                    </div>
                    <div class="ham-meta-item">
                        <strong><?php echo esc_html__('Date:', 'headless-access-manager'); ?></strong>
                        <span id="ham-assessment-date"></span>
                    </div>
                    <div class="ham-meta-item">
                        <strong><?php echo esc_html__('Author:', 'headless-access-manager'); ?></strong>
                        <span id="ham-assessment-author"></span>
                    </div>
                </div>
                
                <div class="ham-assessment-sections">
                    <div class="ham-section-tabs">
                        <button class="ham-section-tab active" data-section="anknytning"><?php echo esc_html__('Connection', 'headless-access-manager'); ?></button>
                        <button class="ham-section-tab" data-section="ansvar"><?php echo esc_html__('Responsibility', 'headless-access-manager'); ?></button>
                    </div>
                    
                    <div class="ham-section-content active" data-section="anknytning">
                        <h3 id="ham-anknytning-title"><?php echo esc_html__('Connection Points', 'headless-access-manager'); ?></h3>
                        <table class="wp-list-table widefat fixed">
                            <thead>
                                <tr>
                                    <th width="40%"><?php echo esc_html__('Question', 'headless-access-manager'); ?></th>
                                    <th width="40%"><?php echo esc_html__('Answer', 'headless-access-manager'); ?></th>
                                    <th width="20%"><?php echo esc_html__('Status', 'headless-access-manager'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="ham-anknytning-questions"></tbody>
                        </table>
                        
                        <div class="ham-section-comments">
                            <h3><?php echo esc_html__('Comments', 'headless-access-manager'); ?></h3>
                            <div id="ham-anknytning-comments"></div>
                        </div>
                    </div>
                    
                    <div class="ham-section-content" data-section="ansvar">
                        <h3 id="ham-ansvar-title"><?php echo esc_html__('Responsibility Points', 'headless-access-manager'); ?></h3>
                        <table class="wp-list-table widefat fixed">
                            <thead>
                                <tr>
                                    <th width="40%"><?php echo esc_html__('Question', 'headless-access-manager'); ?></th>
                                    <th width="40%"><?php echo esc_html__('Answer', 'headless-access-manager'); ?></th>
                                    <th width="20%"><?php echo esc_html__('Status', 'headless-access-manager'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="ham-ansvar-questions"></tbody>
                        </table>
                        
                        <div class="ham-section-comments">
                            <h3><?php echo esc_html__('Comments', 'headless-access-manager'); ?></h3>
                            <div id="ham-ansvar-comments"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="ham-modal-footer">
            <button class="button button-primary ham-modal-close"><?php echo esc_html__('Close', 'headless-access-manager'); ?></button>
        </div>
    </div>
</div>

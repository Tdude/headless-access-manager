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
    <h1><?php echo esc_html__('Student Assessments', 'headless-access-manager'); ?></h1>
    
    <div class="ham-assessments-filters">
        <div class="ham-filter-group">
            <label for="ham-filter-student"><?php echo esc_html__('Filter by Student:', 'headless-access-manager'); ?></label>
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
            <label for="ham-filter-date"><?php echo esc_html__('Filter by Date:', 'headless-access-manager'); ?></label>
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
            <label for="ham-filter-completion"><?php echo esc_html__('Filter by Stage:', 'headless-access-manager'); ?></label>
            <select id="ham-filter-completion">
                <option value=""><?php echo esc_html__('All', 'headless-access-manager'); ?></option>
                <option value="full"><?php echo esc_html__('Fully Established', 'headless-access-manager'); ?></option>
                <option value="transition"><?php echo esc_html__('In Transition', 'headless-access-manager'); ?></option>
                <option value="not"><?php echo esc_html__('Not Established', 'headless-access-manager'); ?></option>
            </select>
        </div>
        
        <button id="ham-filter-reset" class="button"><?php echo esc_html__('Reset Filters', 'headless-access-manager'); ?></button>
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
                    <th class="column-completion"><?php echo esc_html__('Completion', 'headless-access-manager'); ?></th>
                    <th class="column-actions"><?php echo esc_html__('Actions', 'headless-access-manager'); ?></th>
                </tr>
            </thead>
            <tbody id="ham-assessments-list">
                <?php if (empty($assessments)) : ?>
                    <tr>
                        <td colspan="7"><?php echo esc_html__('No assessments found.', 'headless-access-manager'); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($assessments as $assessment) : ?>
                        <tr data-id="<?php echo esc_attr($assessment['id']); ?>" 
                            data-student="<?php echo esc_attr($assessment['student_id']); ?>"
                            data-date="<?php echo esc_attr(date('Y-m-d', strtotime($assessment['date']))); ?>"
                            data-completion="<?php echo esc_attr($assessment['completion']); ?>">
                            <td class="column-id"><?php echo esc_html($assessment['id']); ?></td>
                            <td class="column-title"><?php echo esc_html($assessment['title']); ?></td>
                            <td class="column-student"><?php echo esc_html($assessment['student_name']); ?></td>
                            <td class="column-date"><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($assessment['date']))); ?></td>
                            <td class="column-author"><?php echo esc_html($assessment['author_name']); ?></td>
                            <td class="column-completion">
                                <div class="ham-progress-bar">
                                    <div class="ham-progress-bar-fill" style="width: <?php echo esc_attr($assessment['completion']); ?>%"></div>
                                    <span class="ham-progress-text"><?php echo esc_html($assessment['completion']); ?>%</span>
                                </div>
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
            <h2 id="ham-modal-title"><?php echo esc_html__('Assessment Details', 'headless-access-manager'); ?></h2>
        </div>
        <div class="ham-modal-body">
            <div id="ham-assessment-loading"><?php echo esc_html__('Loading...', 'headless-access-manager'); ?></div>
            <div id="ham-assessment-error" style="display: none;"><?php echo esc_html__('Error loading assessment data.', 'headless-access-manager'); ?></div>
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
                        <button class="ham-section-tab active" data-section="anknytning"><?php echo esc_html__('Anknytning', 'headless-access-manager'); ?></button>
                        <button class="ham-section-tab" data-section="ansvar"><?php echo esc_html__('Ansvar', 'headless-access-manager'); ?></button>
                    </div>
                    
                    <div class="ham-section-content active" data-section="anknytning">
                        <h3 id="ham-anknytning-title"><?php echo esc_html__('Anknytningstecken', 'headless-access-manager'); ?></h3>
                        <table class="wp-list-table widefat fixed">
                            <thead>
                                <tr>
                                    <th width="40%"><?php echo esc_html__('Question', 'headless-access-manager'); ?></th>
                                    <th width="40%"><?php echo esc_html__('Answer', 'headless-access-manager'); ?></th>
                                    <th width="20%"><?php echo esc_html__('Stage', 'headless-access-manager'); ?></th>
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
                        <h3 id="ham-ansvar-title"><?php echo esc_html__('Ansvarstecken', 'headless-access-manager'); ?></h3>
                        <table class="wp-list-table widefat fixed">
                            <thead>
                                <tr>
                                    <th width="40%"><?php echo esc_html__('Question', 'headless-access-manager'); ?></th>
                                    <th width="40%"><?php echo esc_html__('Answer', 'headless-access-manager'); ?></th>
                                    <th width="20%"><?php echo esc_html__('Stage', 'headless-access-manager'); ?></th>
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

<style>
/* Assessment List Styles */
.ham-assessments-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin: 20px 0;
    padding: 15px;
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}

.ham-filter-group {
    display: flex;
    flex-direction: column;
    min-width: 200px;
}

.ham-filter-group label {
    margin-bottom: 5px;
    font-weight: 600;
}

/* Modal Styles */
.ham-modal {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0, 0, 0, 0.5);
}

.ham-modal-content {
    position: relative;
    background-color: #fefefe;
    margin: 50px auto;
    padding: 0;
    border-radius: 5px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    width: 90%;
    max-width: 900px;
    max-height: 85vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.ham-modal-header {
    padding: 15px 20px;
    border-bottom: 1px solid #e5e5e5;
    background-color: #f8f8f8;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.ham-modal-header h2 {
    margin: 0;
    padding: 0;
    font-size: 1.4em;
}

.ham-modal-close {
    color: #aaa;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.ham-modal-close:hover {
    color: #555;
}

.ham-modal-body {
    padding: 20px;
    overflow-y: auto;
    flex: 1;
}

.ham-modal-footer {
    padding: 15px 20px;
    border-top: 1px solid #e5e5e5;
    background-color: #f8f8f8;
    text-align: right;
}

/* Assessment Details Styles */
.ham-assessment-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 20px;
    padding: 15px;
    background-color: #f9f9f9;
    border-radius: 4px;
}

.ham-meta-item {
    display: flex;
    flex-direction: column;
}

.ham-meta-item strong {
    margin-bottom: 5px;
}

.ham-section-tabs {
    display: flex;
    margin-bottom: 20px;
    border-bottom: 1px solid #ddd;
}

.ham-section-tab {
    padding: 10px 15px;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-weight: 600;
    color: #555;
}

.ham-section-tab.active {
    border-bottom-color: #2271b1;
    color: #2271b1;
}

.ham-section-content {
    display: none;
}

.ham-section-content.active {
    display: block;
}

.ham-section-content h3 {
    margin-top: 0;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
    color: #23282d;
    font-size: 1.2em;
}

.ham-section-comments {
    margin-top: 30px;
}

.ham-section-comments h3 {
    font-size: 1.1em;
    margin-bottom: 10px;
}

/* Progress Bar Styles */
.ham-progress-bar {
    height: 20px;
    background-color: #f0f0f0;
    border-radius: 10px;
    overflow: hidden;
    position: relative;
}

.ham-progress-bar-fill {
    height: 100%;
    background-color: #2271b1;
}

.ham-progress-text {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-weight: bold;
    text-shadow: 0 0 2px rgba(0, 0, 0, 0.5);
}

/* Enhanced table styles for better readability */
#ham-assessment-modal table {
    border-collapse: collapse;
    width: 100%;
    margin-bottom: 20px;
}

#ham-assessment-modal th {
    background-color: #f5f5f5;
    padding: 12px 15px;
    text-align: left;
    font-weight: bold;
    border-bottom: 2px solid #ddd;
}

#ham-assessment-modal td {
    padding: 10px 15px;
    border-bottom: 1px solid #eee;
    vertical-align: top;
}

#ham-assessment-modal tr:hover {
    background-color: #f9f9f9;
}

.ham-question-text strong {
    display: block;
    font-weight: 600;
    color: #23282d;
}

.ham-answer-text {
    color: #444;
}
</style>

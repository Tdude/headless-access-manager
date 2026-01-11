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
                // Use Student CPTs instead of WordPress users for consistency
                $students = get_posts(array(
                    'post_type'      => HAM_CPT_STUDENT,
                    'posts_per_page' => -1,
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                ));
                foreach ($students as $student) {
                    echo '<option value="' . esc_attr($student->ID) . '">' . esc_html($student->post_title) . '</option>';
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
            <label for="ham-filter-completion"><?php echo esc_html__('Filter by status:', 'headless-access-manager'); ?></label>
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
                    <th class="column-student"><a class="ham-sort" href="#" data-sort-key="student"><?php echo esc_html__('Student', 'headless-access-manager'); ?></a></th>
                    <th class="column-class"><a class="ham-sort" href="#" data-sort-key="class"><?php echo esc_html__('Class', 'headless-access-manager'); ?></a></th>
                    <th class="column-school"><a class="ham-sort" href="#" data-sort-key="school"><?php echo esc_html__('School', 'headless-access-manager'); ?></a></th>
                    <th class="column-date"><a class="ham-sort" href="#" data-sort-key="date"><?php echo esc_html__('Date', 'headless-access-manager'); ?></a></th>
                    <th class="column-author"><a class="ham-sort" href="#" data-sort-key="author"><?php echo esc_html__('Responsible teacher', 'headless-access-manager'); ?></a></th>
                    <th class="column-completion"><a class="ham-sort" href="#" data-sort-key="status"><?php echo esc_html__('Status', 'headless-access-manager'); ?></a></th>
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
                            data-stage="<?php echo esc_attr($assessment['stage'] ?? 'not'); ?>"
                            data-student-name="<?php echo esc_attr($assessment['student_name']); ?>"
                            data-class="<?php echo esc_attr($assessment['class_name'] ?? ''); ?>"
                            data-school="<?php echo esc_attr($assessment['school_name'] ?? ''); ?>"
                            data-author="<?php echo esc_attr($assessment['author_name']); ?>">
                            <td class="column-student"><?php echo esc_html($assessment['student_name']); ?></td>
                            <td class="column-class"><?php echo esc_html($assessment['class_name'] ?? ''); ?></td>
                            <td class="column-school">
                                <?php
                                $school_name = (string)($assessment['school_name'] ?? '');
                                $school_initial = '';
                                if ($school_name !== '') {
                                    $school_initial = mb_strtoupper(mb_substr($school_name, 0, 1));
                                }
                                ?>
                                <?php if ($school_name !== '' && $school_initial !== '') : ?>
                                    <span class="ham-school-avatar" data-school-name="<?php echo esc_attr($school_name); ?>" title="<?php echo esc_attr($school_name); ?>" aria-label="<?php echo esc_attr($school_name); ?>">
                                        <?php echo esc_html($school_initial); ?>
                                    </span>
                                <?php else : ?>
                                    <span class="ham-school-avatar ham-school-avatar--empty" aria-hidden="true"></span>
                                <?php endif; ?>
                            </td>
                            <td class="column-date"><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($assessment['date']))); ?></td>
                            <td class="column-author" data-author-id="<?php echo esc_attr($assessment['author_id'] ?? 0); ?>"><?php echo esc_html($assessment['author_name']); ?></td>
                            <td class="column-completion">
                                <?php
                                // Helper function for stage badge
                                $get_badge = function($stage) {
                                    $stage = (string) $stage;
                                    if ($stage === 'full') {
                                        return array('class' => 'ham-stage-full', 'text' => 'Ok');
                                    } elseif ($stage === 'trans') {
                                        return array('class' => 'ham-stage-trans', 'text' => 'Utv.');
                                    } else {
                                        return array('class' => 'ham-stage-not', 'text' => 'Ej');
                                    }
                                };
                                $stage_ank = $assessment['stage_anknytning'] ?? 'not';
                                $stage_ans = $assessment['stage_ansvar'] ?? 'not';
                                $badge_ank = $get_badge($stage_ank);
                                $badge_ans = $get_badge($stage_ans);
                                ?>
                                <span class="ham-stage-badge <?php echo esc_attr($badge_ank['class']); ?>" title="<?php esc_attr_e('Anknytning', 'headless-access-manager'); ?>">A: <?php echo esc_html($badge_ank['text']); ?></span>
                                <span class="ham-stage-badge <?php echo esc_attr($badge_ans['class']); ?>" title="<?php esc_attr_e('Ansvar', 'headless-access-manager'); ?>">B: <?php echo esc_html($badge_ans['text']); ?></span>
                            </td>
                            <td class="column-actions">
                                <button class="button ham-view-assessment" data-id="<?php echo esc_attr($assessment['id']); ?>">
                                    <?php echo esc_html__('View', 'headless-access-manager'); ?>
                                </button>
                                <?php if (current_user_can('manage_options')) : ?>
                                    <a class="button ham-icon-button ham-edit-assessment" href="<?php echo esc_url(add_query_arg('redirect_to', rawurlencode(admin_url('admin.php?page=ham-assessments')), admin_url('post.php?post=' . intval($assessment['id']) . '&action=edit'))); ?>" aria-label="<?php echo esc_attr__('Edit', 'headless-access-manager'); ?>">
                                        <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                                    </a>
                                <?php endif; ?>
                                <button class="button ham-icon-button ham-icon-button--danger ham-delete-assessment" data-id="<?php echo esc_attr($assessment['id']); ?>" aria-label="<?php echo esc_attr__('Delete', 'headless-access-manager'); ?>">
                                    <span class="dashicons dashicons-trash" aria-hidden="true"></span>
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

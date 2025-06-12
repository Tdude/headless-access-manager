/**
 * Assessment Manager JavaScript
 *
 * Handles the functionality for the assessment management interface.
 */
(function($) {
    'use strict';

    /**
     * Initialize the assessment manager.
     */
    function initAssessmentManager() {
        // Initialize filters
        initFilters();

        // Initialize modal
        initModal();

        // Initialize section tabs
        initSectionTabs();

        // Initialize delete buttons
        initDeleteButtons();
    }

    /**
     * Initialize the filters functionality.
     */
    function initFilters() {
        const $filterStudent = $('#ham-filter-student');
        const $filterDate = $('#ham-filter-date');
        const $filterCompletion = $('#ham-filter-completion');
        const $filterReset = $('#ham-filter-reset');
        const $assessmentRows = $('.ham-assessments-table tbody tr');

        // Handle student filter
        $filterStudent.on('change', applyFilters);

        // Handle date filter
        $filterDate.on('change', applyFilters);

        // Handle completion filter
        $filterCompletion.on('change', applyFilters);

        // Handle reset button
        $('#ham-reset-filters').on('click', function(e) {
            e.preventDefault();
            resetFilters();
        });

        /**
         * Apply all active filters.
         */
        function applyFilters() {
            const studentFilter = $filterStudent.val();
            const dateFilter = $filterDate.val();
            const completionFilter = $filterCompletion.val();

            $assessmentRows.each(function() {
                const $row = $(this);
                let show = true;

                // Apply student filter
                if (studentFilter && $row.data('student') !== studentFilter) {
                    show = false;
                }

                // Apply date filter
                if (dateFilter) {
                    const assessmentDate = new Date($row.data('date-raw'));
                    const today = new Date();
                    const yesterday = new Date();
                    yesterday.setDate(yesterday.getDate() - 1);

                    // Check if date matches filter
                    if (dateFilter === 'today') {
                        if (assessmentDate.toDateString() !== today.toDateString()) {
                            show = false;
                        }
                    } else if (dateFilter === 'yesterday') {
                        if (assessmentDate.toDateString() !== yesterday.toDateString()) {
                            show = false;
                        }
                    } else if (dateFilter === 'week') {
                        // Get start of week (Monday)
                        const startOfWeek = new Date();
                        const dayOfWeek = startOfWeek.getDay() || 7; // Convert Sunday (0) to 7
                        startOfWeek.setDate(startOfWeek.getDate() - dayOfWeek + 1);
                        startOfWeek.setHours(0, 0, 0, 0);

                        if (assessmentDate < startOfWeek) {
                            show = false;
                        }
                    } else if (dateFilter === 'month') {
                        // Start of current month
                        const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);

                        if (assessmentDate < startOfMonth) {
                            show = false;
                        }
                    } else if (dateFilter === 'semester') {
                        // Determine current semester (Jan-Jun or Jul-Dec)
                        const currentMonth = today.getMonth() + 1; // 1-12
                        let startOfSemester;

                        if (currentMonth >= 1 && currentMonth <= 6) {
                            // Spring semester (Jan-Jun)
                            startOfSemester = new Date(today.getFullYear(), 0, 1); // Jan 1
                        } else {
                            // Fall semester (Jul-Dec)
                            startOfSemester = new Date(today.getFullYear(), 6, 1); // Jul 1
                        }

                        if (assessmentDate < startOfSemester) {
                            show = false;
                        }
                    } else if (dateFilter === 'schoolyear') {
                        // School year starts in August and ends in June
                        const currentMonth = today.getMonth() + 1; // 1-12
                        let startOfSchoolYear;

                        if (currentMonth >= 8) {
                            // Current school year started this August
                            startOfSchoolYear = new Date(today.getFullYear(), 7, 1); // Aug 1
                        } else {
                            // Current school year started last August
                            startOfSchoolYear = new Date(today.getFullYear() - 1, 7, 1); // Aug 1 of last year
                        }

                        if (assessmentDate < startOfSchoolYear) {
                            show = false;
                        }
                    }
                }

                // Apply stage filter
                if (completionFilter) {
                    const stage = $row.data('stage');

                    if (completionFilter === 'full' && stage !== 'full') {
                        show = false;
                    } else if (completionFilter === 'transition' && stage !== 'transition') {
                        show = false;
                    } else if (completionFilter === 'not' && stage !== 'not') {
                        show = false;
                    }
                }

                // Show or hide the row
                $row.toggle(show);
            });

            // Show message if no results
            const $visibleRows = $assessmentRows.filter(':visible');
            const $noResults = $('#ham-no-results');

            if ($visibleRows.length === 0) {
                if ($noResults.length === 0) {
                    $('<tr id="ham-no-results"><td colspan="7">' + hamAssessment.texts.noData + '</td></tr>').appendTo('.ham-assessments-table tbody');
                }
            } else {
                $noResults.remove();
            }
        }

        /**
         * Reset all filters.
         */
        function resetFilters() {
            $filterStudent.val('');
            $filterDate.val('');
            $filterCompletion.val('');
            applyFilters();
        }
    }

    /**
     * Initialize the modal functionality.
     */
    function initModal() {
        const $modal = $('#ham-assessment-modal');
        const $modalClose = $('.ham-modal-close');
        const $viewButtons = $('.ham-view-assessment');

        // Open modal when view button is clicked
        $viewButtons.on('click', function(e) {
            e.preventDefault();
            // Check for both possible attribute names
            let assessmentId = $(this).data('assessment-id');
            if (assessmentId === undefined) {
                assessmentId = $(this).data('id');
            }
            console.log('Opening modal for assessment ID:', assessmentId);
            fetchAssessmentDetails(assessmentId);
        });

        // Close modal when close button or outside is clicked
        $modalClose.on('click', closeModal);

        $(window).on('click', function(event) {
            if ($(event.target).is($modal)) {
                closeModal();
            }
        });

        /**
         * Fetch and display assessment details.
         *
         * @param {number} assessmentId Assessment ID.
         */
        function fetchAssessmentDetails(assessmentId) {
            // Show modal
            $modal.css('display', 'block');

            $('#ham-assessment-loading').show();
            $('#ham-assessment-error, #ham-assessment-details').hide();

            const data = {
                action: 'ham_get_assessment_details',
                nonce: hamAssessment.nonce,
                assessment_id: assessmentId
            };

            console.log('Fetching assessment details with data:', data);

            $.post(hamAssessment.ajaxUrl, data, function(response) {
                $('#ham-assessment-loading').hide();

                if (response.success && response.data) {
                    // Log the full response for debugging
                    console.log('Assessment details response (FULL):', response.data);

                    // Display the assessment details
                    displayAssessmentDetails(response.data);

                    // Show the details container
                    $('#ham-assessment-details').show();
                } else {
                    $('#ham-assessment-error').show();
                    console.error('Error fetching assessment details:', response);
                }
            }).fail(function(xhr, status, error) {
                $('#ham-assessment-loading').hide();
                $('#ham-assessment-error').show();
                console.error('AJAX error:', status, error);
            });
        }

        /**
         * Close the modal.
         */
        function closeModal() {
            $modal.css('display', 'none');
        }

        /**
         * Display assessment details in the modal.
         *
         * @param {Object} data Assessment data.
         */
        function displayAssessmentDetails(data) {
            console.log('Displaying assessment details:', data);

            // =============================
            // COMPREHENSIVE DATA STRUCTURE LOG
            // =============================
            console.log('%c ========= ASSESSMENT DATA STRUCTURE ANALYSIS =========', 'background: #222; color: #bada55; font-size: 16px');

            // Log key data structure components
            console.log('Raw Question Structure:', JSON.stringify(data.questions_structure, null, 2));
            console.log('Raw Assessment Data:', JSON.stringify(data.assessment_data, null, 2));

            // Critical check: How are the question keys formatted?
            if (data.questions_structure && data.questions_structure.anknytning && data.questions_structure.anknytning.questions) {
                console.log('Question keys in structure:', Object.keys(data.questions_structure.anknytning.questions));
            }

            // Critical check: How are the answer keys formatted?
            if (data.assessment_data && data.assessment_data.anknytning && data.assessment_data.anknytning.questions) {
                console.log('Answer keys in data:', Object.keys(data.assessment_data.anknytning.questions));
            }

            console.log('%c =================================================', 'background: #222; color: #bada55; font-size: 16px');
            // =============================

            // Set student name and date
            $('#ham-student-name').text(data.student_name);
            $('#ham-assessment-date').text(data.date);
            $('#ham-author-name').text(data.author_name);

            // Clear existing questions
            $('#ham-anknytning-questions, #ham-ansvar-questions').empty();

            // DIRECT APPROACH: Create a simplified function to render the assessment
            renderAssessmentSection('anknytning', data);
            renderAssessmentSection('ansvar', data);

            // Set comments for each section (per-question)
            function renderSectionComments(sectionName, data) {
                const section = data.assessment_data[sectionName];
                const structure = data.questions_structure[sectionName];
                const $commentsContainer = $(`#ham-${sectionName}-comments`);
                $commentsContainer.empty();
                if (section && section.comments && typeof section.comments === 'object') {
                    // Comments keyed by question ID
                    Object.entries(section.comments).forEach(([qKey, comment]) => {
                        // Try to get question text from structure
                        let questionText = (structure && structure.questions && structure.questions[qKey] && structure.questions[qKey].text) || qKey;
                        if (comment && comment.trim() !== '') {
                            $commentsContainer.append(`<div class="ham-question-comment"><strong>${questionText}:</strong> ${comment}</div>`);
                        }
                    });
                } else if (typeof section?.comments === 'string' && section.comments.trim() !== '') {
                    // Fallback: single string
                    $commentsContainer.append(`<div class="ham-question-comment">${section.comments}</div>`);
                } else {
                    $commentsContainer.append(`<div class="ham-question-comment ham-no-comment">${hamAssessment.texts.noComments || 'Inga kommentarer.'}</div>`);
                }
            }
            renderSectionComments('anknytning', data);
            renderSectionComments('ansvar', data);

            // Set comments
            $('#ham-comments').text(data.assessment_data.comments || '');

            /**
             * Render an assessment section directly with minimal processing
             */
            function renderAssessmentSection(sectionName, data) {
                const $container = $(`#ham-${sectionName}-questions`);
                const sectionData = data.assessment_data[sectionName];
                const sectionStructure = data.questions_structure[sectionName];

                // Skip if no structure is available
                if (!sectionStructure || !sectionStructure.questions) {
                    console.log(`Missing structure for section: ${sectionName}`);
                    $container.html(`<tr><td colspan="3">${hamAssessment.texts.noQuestions || 'No questions configured.'}</td></tr>`);
                    return;
                }

                const answeredQuestions = (sectionData && sectionData.questions) ? sectionData.questions : {};
                const structureQuestions = sectionStructure.questions;
                
                // FIX: Iterate over the keys from the STRUCTURE to maintain the correct order
                const questionKeysInOrder = Object.keys(structureQuestions);
                console.log(`Processing ${sectionName} questions in order from structure:`, questionKeysInOrder);

                if (questionKeysInOrder.length === 0) {
                    $container.html(`<tr><td colspan="3">${hamAssessment.texts.noQuestions || 'No questions configured.'}</td></tr>`);
                    return;
                }

                // Process each question based on the structure's order
                questionKeysInOrder.forEach(qKey => {
                    // Find the corresponding answered key case-insensitively
                    const answeredKey = Object.keys(answeredQuestions).find(
                        key => key.toLowerCase() === qKey.toLowerCase()
                    );
                    const answerData = answeredKey ? answeredQuestions[answeredKey] : undefined;
                    
                    const questionStructure = structureQuestions[qKey];
                    const questionText = questionStructure.text || qKey;

                    let answerValue;
                    let stage;

                    if (typeof answerData === 'object' && answerData !== null) {
                        answerValue = answerData.value !== undefined ? answerData.value : answerData.selected;
                        stage = answerData.stage || '';
                    } else {
                        answerValue = answerData;
                        stage = '';
                    }

                    let answerLabel = '—'; // Default for unanswered

                    if (answerValue !== undefined && answerValue !== null) {
                        answerLabel = answerValue; // Fallback to raw value
                        if (questionStructure.options && Array.isArray(questionStructure.options)) {
                            const matchingOption = questionStructure.options.find(
                                opt => String(opt.value) === String(answerValue)
                            );

                            if (matchingOption) {
                                answerLabel = matchingOption.label || answerValue;
                                if (!stage && matchingOption.stage) {
                                    stage = matchingOption.stage;
                                }
                            }
                        }
                    }

                    // Set stage badge
                    let stageClass = '';
                    let stageText = '';

                    switch(stage) {
                        case 'ej':
                            stageClass = 'ham-stage-not';
                            stageText = 'Ej etablerad';
                            break;
                        case 'trans':
                            stageClass = 'ham-stage-trans';
                            stageText = 'Utvecklas';
                            break;
                        case 'full':
                            stageClass = 'ham-stage-full';
                            stageText = 'Etablerad';
                            break;
                    }

                    const stageBadge = stage ? `<span class="ham-stage-badge ${stageClass}">${stageText}</span>` : '';

                    // Create table row
                    const tableRow = `
                        <tr>
                            <td>${questionText}</td>
                            <td>${answerLabel}</td>
                            <td>${stageBadge}</td>
                        </tr>
                    `;

                    // Append to container
                    $container.append(tableRow);
                });


            }
        }
    }

    /**
     * Initialize the section tabs.
     */
    function initSectionTabs() {
        const $sectionTabs = $('.ham-section-tab');
        const $sectionContents = $('.ham-section-content');

        $sectionTabs.on('click', function() {
            const section = $(this).data('section');

            // Update active tab
            $sectionTabs.removeClass('active');
            $(this).addClass('active');

            // Update active content
            $sectionContents.removeClass('active');
            $('.ham-section-content[data-section="' + section + '"]').addClass('active');
        });
    }

    /**
     * Initialize delete butts, should you needem.
     */
    function initDeleteButtons() {
        $('.ham-delete-assessment').on('click', function(e) {
            e.preventDefault();

            const $button = $(this);
            const assessmentId = $button.data('id');

            if (!assessmentId) {
                console.error('No assessment ID found');
                return;
            }

            if (!confirm('Är du säker på att du vill ta bort denna bedömning? Detta går inte att ångra.')) {
                return;
            }

            $button.prop('disabled', true);

            $.ajax({
                url: hamAssessment.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ham_delete_assessment',
                    assessment_id: assessmentId,
                    nonce: hamAssessment.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Remove the table row with animation
                        $button.closest('tr').fadeOut(400, function() {
                            $(this).remove();
                        });
                    } else {
                        alert(response.data.message || 'Ett fel uppstod när bedömningen skulle tas bort.');
                        $button.prop('disabled', false);
                    }
                },
                error: function() {
                    alert('Ett fel uppstod när bedömningen skulle tas bort.');
                    $button.prop('disabled', false);
                }
            });
        });
    }
    // Initialize when document is ready
    $(document).ready(initAssessmentManager);

})(jQuery);

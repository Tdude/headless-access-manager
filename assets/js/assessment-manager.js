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
        $filterReset.on('click', function() {
            $filterStudent.val('');
            $filterDate.val('');
            $filterCompletion.val('');
            applyFilters();
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
    }

    /**
     * Initialize the modal functionality.
     */
    function initModal() {
        const $modal = $('#ham-assessment-modal');
        const $modalClose = $('.ham-modal-close');
        const $viewButtons = $('.ham-view-assessment');
        
        // Open modal when view button is clicked
        $viewButtons.on('click', function() {
            const assessmentId = $(this).data('id');
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
            
            $.post(hamAssessment.ajaxUrl, data, function(response) {
                $('#ham-assessment-loading').hide();
                
                if (response.success && response.data) {
                    // Log the full response for debugging
                    console.log('Assessment details response:', response.data);
                    
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
            
            // Set student name and date
            $('#ham-student-name').text(data.student_name);
            $('#ham-assessment-date').text(data.date);
            $('#ham-author-name').text(data.author_name);
            
            // Clear existing questions
            $('#ham-anknytning-questions, #ham-ansvar-questions').empty();
            $('#ham-anknytning-comments, #ham-ansvar-comments').empty();
            
            // Set section titles if available
            if (data.questions_structure && data.questions_structure.anknytning && data.questions_structure.anknytning.title) {
                $('#ham-anknytning-title').text(data.questions_structure.anknytning.title);
            }
            
            if (data.questions_structure && data.questions_structure.ansvar && data.questions_structure.ansvar.title) {
                $('#ham-ansvar-title').text(data.questions_structure.ansvar.title);
            }
            
            // Add questions and answers for each section
            const sections = ['anknytning', 'ansvar'];
            
            for (let i = 0; i < sections.length; i++) {
                const section = sections[i];
                
                // Skip if section doesn't exist in data
                if (!data.assessment_data[section]) {
                    continue;
                }
                
                const sectionData = data.assessment_data[section];
                const questions = sectionData.questions || {};
                const comments = sectionData.comments || '';
                
                console.log(`Processing ${section} section:`, sectionData);
                
                // Get questions structure for this section
                const sectionStructure = data.questions_structure && data.questions_structure[section] && 
                                        data.questions_structure[section].questions ? 
                                        data.questions_structure[section].questions : {};
                
                console.log(`Questions structure for ${section}:`, sectionStructure);
                
                let hasQuestions = false;
                
                // Process each question
                for (const questionId in questions) {
                    if (questions.hasOwnProperty(questionId)) {
                        hasQuestions = true;
                        const answer = questions[questionId];
                        
                        // Get question structure
                        const questionStructure = sectionStructure[questionId] || {};
                        
                        console.log(`Question Structure:`, JSON.stringify(questionStructure, null, 2));
                        
                        // Get question text - use text from question structure or fallback to ID
                        let questionText = '';
                        if (questionStructure && questionStructure.text) {
                            questionText = questionStructure.text;
                        } else {
                            // Try to make the question ID more readable
                            questionText = questionId.charAt(0).toUpperCase() + questionId.slice(1);
                            // Replace underscores with spaces
                            questionText = questionText.replace(/_/g, ' ');
                        }
                        
                        // Get answer text and stage
                        let answerText = '';
                        let stage = '';
                        let stageClass = '';
                        
                        // Get the selected value from the answer
                        let selectedValue = null;
                        
                        // Extract the selected value from the answer
                        if (typeof answer === 'object' && answer !== null) {
                            console.log(`Answer is an object:`, JSON.stringify(answer));
                            if (answer.value !== undefined) {
                                selectedValue = answer.value;
                                console.log(`Found value in answer: ${selectedValue}`);
                            } else if (answer.selected !== undefined) {
                                selectedValue = answer.selected;
                                console.log(`Found selected in answer: ${selectedValue}`);
                            }
                            
                            // Extract stage directly from the answer if available
                            if (answer.stage) {
                                stage = answer.stage;
                                console.log(`Found stage directly in answer: ${stage}`);
                            }
                        } else if (typeof answer === 'string' || typeof answer === 'number') {
                            selectedValue = answer;
                            console.log(`Answer is a primitive: ${selectedValue}`);
                        }
                        
                        console.log(`Selected value for ${questionId}: ${selectedValue}`);
                        console.log(`Question structure for ${questionId}:`, JSON.stringify(questionStructure));
                        
                        // If we have a selected value, find the corresponding label and stage in the options
                        if (selectedValue !== null) {
                            // Try to find the label in question structure options
                            if (questionStructure.options && Array.isArray(questionStructure.options)) {
                                console.log(`Looking for option with value: ${selectedValue}`);
                                let foundMatchingOption = false;
                                
                                for (let i = 0; i < questionStructure.options.length; i++) {
                                    const option = questionStructure.options[i];
                                    console.log(`Checking option:`, JSON.stringify(option));
                                    
                                    // Convert both values to strings for comparison
                                    const optionValue = String(option.value);
                                    const selectedValueStr = String(selectedValue);
                                    
                                    if (option && optionValue === selectedValueStr) {
                                        console.log(`Found matching option for value ${selectedValue}:`, JSON.stringify(option));
                                        foundMatchingOption = true;
                                        
                                        // Use the option label, text, or value in that order of preference
                                        if (option.label) {
                                            answerText = option.label;
                                        } else if (option.text) {
                                            answerText = option.text;
                                        } else {
                                            answerText = selectedValue;
                                        }
                                        
                                        // Get stage from the option if not already set
                                        if (!stage && option.stage) {
                                            stage = option.stage;
                                            console.log(`Setting stage from option: ${stage}`);
                                        }
                                        break;
                                    }
                                }
                                
                                if (!foundMatchingOption) {
                                    console.log(`No matching option found for value ${selectedValue}, trying to match by index`);
                                    
                                    // Try matching by index as a fallback (if the value is a number)
                                    const numericValue = parseInt(selectedValue, 10);
                                    if (!isNaN(numericValue) && numericValue > 0 && numericValue <= questionStructure.options.length) {
                                        // Adjust for 1-based indexing that might be used in the data
                                        const optionIndex = numericValue - 1;
                                        const option = questionStructure.options[optionIndex];
                                        console.log(`Found option by index ${optionIndex}:`, JSON.stringify(option));
                                        
                                        if (option.label) {
                                            answerText = option.label;
                                        } else if (option.text) {
                                            answerText = option.text;
                                        } else {
                                            answerText = selectedValue;
                                        }
                                        
                                        // Get stage from the option if not already set
                                        if (!stage && option.stage) {
                                            stage = option.stage;
                                            console.log(`Setting stage from option by index: ${stage}`);
                                        }
                                    } else {
                                        console.log(`Could not match by index either, using value directly: ${selectedValue}`);
                                        answerText = selectedValue;
                                    }
                                }
                            } else {
                                // If no options structure, use the value directly
                                answerText = selectedValue;
                                console.log(`No options structure, using value directly: ${answerText}`);
                            }
                        }
                        
                        // If we still don't have an answer text, use the first option's label as a fallback
                        if (!answerText && questionStructure.options && Array.isArray(questionStructure.options) && questionStructure.options.length > 0) {
                            const firstOption = questionStructure.options[0];
                            answerText = firstOption.label || firstOption.text || firstOption.value;
                            console.log(`Using first option as fallback for answer text: ${answerText}`);
                            
                            // Get stage from the first option if not already set
                            if (!stage && firstOption.stage) {
                                stage = firstOption.stage;
                                console.log(`Using first option stage as fallback: ${stage}`);
                            }
                        }
                        
                        // If we still don't have an answer text, use a fallback
                        if (!answerText) {
                            answerText = '(No value)';
                        }
                        
                        console.log(`Final stage for ${questionId}: ${stage}`);
                        
                        // Set stage class based on the stage value
                        if (stage === 'ej') {
                            stageClass = 'ham-stage-ej';
                        } else if (stage === 'trans') {
                            stageClass = 'ham-stage-trans';
                        } else if (stage === 'full') {
                            stageClass = 'ham-stage-full';
                        }
                        
                        // Create row
                        const $row = $('<tr></tr>');
                        
                        // Add question text with better formatting
                        $row.append('<td class="ham-question-text"><strong>' + questionText + '</strong></td>');
                        
                        // Add answer text with better formatting
                        $row.append('<td class="ham-answer-text">' + answerText + '</td>');
                        
                        if (stage) {
                            const stageText = stage === 'ej' ? 'Ej etablerat' : 
                                             (stage === 'trans' ? 'I transition' : 
                                             (stage === 'full' ? 'Fullt etablerat' : stage));
                            
                            $row.append('<td><span class="ham-stage-badge ' + stageClass + '">' + stageText + '</span></td>');
                        } else {
                            $row.append('<td>-</td>');
                        }
                        
                        $row.appendTo('#ham-' + section + '-questions');
                    }
                }
                
                // Show message if no questions
                if (!hasQuestions) {
                    const $row = $('<tr></tr>');
                    $row.append('<td colspan="3"><em>' + hamAssessment.texts.noData + '</em></td>');
                    $row.appendTo('#ham-' + section + '-questions');
                }
                
                // Add comments
                if (comments) {
                    // Check if comments is an object and convert it to string if needed
                    let commentsToDisplay = comments;
                    if (typeof commentsToDisplay === 'object' && commentsToDisplay !== null) {
                        console.log('Comments is an object:', JSON.stringify(commentsToDisplay));
                        
                        // Try to extract text from the object
                        if (commentsToDisplay.text) {
                            commentsToDisplay = commentsToDisplay.text;
                        } else if (commentsToDisplay.value) {
                            commentsToDisplay = commentsToDisplay.value;
                        } else {
                            // Extract all values from the object and display them as plain text
                            const commentValues = [];
                            for (const key in commentsToDisplay) {
                                if (commentsToDisplay.hasOwnProperty(key)) {
                                    commentValues.push(commentsToDisplay[key]);
                                }
                            }
                            commentsToDisplay = commentValues.join('<br>');
                        }
                    }
                    
                    $('#ham-' + section + '-comments').html('<p>' + commentsToDisplay + '</p>');
                } else {
                    $('#ham-' + section + '-comments').html('<p><em>' + hamAssessment.texts.noData + '</em></p>');
                }
            }
            
            // Add custom CSS for stage badges
            if (!$('#ham-stage-styles').length) {
                $('<style id="ham-stage-styles">')
                    .text(`
                        .ham-stage-badge {
                            display: inline-block;
                            padding: 3px 8px;
                            border-radius: 4px;
                            font-size: 12px;
                            font-weight: bold;
                            text-align: center;
                        }
                        
                        .ham-stage-ej {
                            background-color: #ffcccb;
                            color: #d32f2f;
                        }
                        
                        .ham-stage-trans {
                            background-color: #fff9c4;
                            color: #f57f17;
                        }
                        
                        .ham-stage-full {
                            background-color: #c8e6c9;
                            color: #2e7d32;
                        }
                        
                        /* Enhanced styles for questions and answers */
                        .ham-question-text {
                            font-weight: normal;
                            padding: 8px 12px;
                            width: 40%;
                        }
                        
                        .ham-answer-text {
                            font-weight: normal;
                            padding: 8px 12px;
                            width: 40%;
                        }
                        
                        #ham-assessment-modal table {
                            width: 100%;
                            border-collapse: collapse;
                            margin-bottom: 20px;
                        }
                        
                        #ham-assessment-modal th {
                            background-color: #f5f5f5;
                            padding: 10px;
                            text-align: left;
                            font-weight: bold;
                            border-bottom: 2px solid #ddd;
                        }
                        
                        #ham-assessment-modal td {
                            padding: 8px 10px;
                            border-bottom: 1px solid #eee;
                        }
                        
                        #ham-assessment-modal tr:hover {
                            background-color: #f9f9f9;
                        }
                    `)
                    .appendTo('head');
            }
            
            // Show details
            $('#ham-assessment-details').show();
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

    // Initialize when document is ready
    $(document).ready(initAssessmentManager);

})(jQuery);

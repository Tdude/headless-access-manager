/**
 * Assessment Editor JavaScript
 *
 * Handles the interactive editor for assessment questions in the admin
 */
(function($) {
    'use strict';

    // Main Assessment Editor class
    var AssessmentEditor = function() {
        // DOM elements
        this.$editor = $('#ham-assessment-editor');
        this.$form = this.$editor.closest('form');
        this.$assessmentData = $('[name="_ham_assessment_data"]');
        this.$sectionTabs = this.$editor.find('.ham-section-tab');
        this.$sectionContents = this.$editor.find('.ham-section-content');
        this.$ankQuestions = $('#anknytning-questions');
        this.$ansvarQuestions = $('#ansvar-questions');
        this.$addQuestionButtons = this.$editor.find('.ham-add-question');

        // Assessment data
        this.assessmentData = {
            anknytning: {
                title: 'Anknytning',
                questions: {},
                comments: []
            },
            ansvar: {
                title: 'Ansvar',
                questions: {},
                comments: []
            }
        };

        // Initialize
        this.init();
    };

    AssessmentEditor.prototype = {
        init: function() {
            // Try to parse existing data from textarea
            try {
                var jsonData = this.$assessmentData.val();

                if (jsonData && jsonData.trim() !== '') {
                    var parsed = JSON.parse(jsonData);
                    this.assessmentData = parsed;
                } else {
                    // Use fallback structure
                    this.assessmentData = {
                        anknytning: {
                            title: 'Anknytning',
                            questions: {},
                            comments: []
                        },
                        ansvar: {
                            title: 'Ansvar',
                            questions: {},
                            comments: []
                        }
                    };
                }
            } catch (e) {
                console.error('Error parsing JSON');
                // Use same fallback structure on error
                this.assessmentData = {
                    anknytning: {
                        title: 'Anknytning',
                        questions: {},
                        comments: []
                    },
                    ansvar: {
                        title: 'Ansvar',
                        questions: {},
                        comments: []
                    }
                };
            }

            // Show first tab
            this.switchTab('anknytning');

            // Render questions
            this.renderAllQuestions();

            // Bind events
            this.bindEvents();
        },

        bindEvents: function() {
            var self = this;

            // Tab switching
            this.$sectionTabs.on('click', function() {
                var section = $(this).data('section');
                self.switchTab(section);
            });

            // Add question button
            this.$addQuestionButtons.on('click', function() {
                var section = $(this).data('section');
                self.addQuestion(section);
                self.updateDataFromDOM(); // Update JSON after adding question
                self.saveData(); // Save data via AJAX
            });

            // Delete question button
            this.$editor.on('click', '.ham-delete-question', function(e) {
                e.preventDefault();
                var $question = $(this).closest('.ham-question');
                var section = $question.data('section');
                var questionId = $question.data('question-id');
                self.deleteQuestion(section, questionId);
                self.updateDataFromDOM(); // Update JSON after deleting question
                self.saveData(); // Save data via AJAX
            });

            // Add option button
            this.$editor.on('click', '.ham-add-option', function(e) {
                e.preventDefault();
                var $question = $(this).closest('.ham-question');
                var section = $question.data('section');
                var questionId = $question.data('question-id');
                self.addOption(section, questionId);
                self.updateDataFromDOM(); // Update JSON after adding option
                self.saveData(); // Save data via AJAX
            });

            // Delete option button
            this.$editor.on('click', '.ham-delete-option', function(e) {
                e.preventDefault();
                var $option = $(this).closest('.ham-option');
                var $question = $option.closest('.ham-question');
                var section = $question.data('section');
                var questionId = $question.data('question-id');
                var optionIndex = $option.data('option-index');
                self.deleteOption(section, questionId, optionIndex);
                self.updateDataFromDOM(); // Update JSON after deleting option
                self.saveData(); // Save data via AJAX
            });

            // Input change events - update JSON when form fields change
            this.$editor.on('input change', '.ham-question-input, .ham-option-value, .ham-option-label, .ham-option-stage', function() {
                self.updateDataFromDOM();
                // Debounce the save to avoid too many AJAX requests
                clearTimeout(self.saveTimeout);
                self.saveTimeout = setTimeout(function() {
                    self.saveData();
                }, 500);
            });

            // Form submission - ensure latest data is saved
            this.$form.on('submit', function(e) {
                self.updateDataFromDOM();
                // No need to call saveData here as the form will be submitted with the updated hidden field
                return true;
            });
        },

        renderAllQuestions: function() {
            this.$ankQuestions.empty();
            this.$ansvarQuestions.empty();

            // Render anknytning questions
            if (this.assessmentData.anknytning && this.assessmentData.anknytning.questions) {
                var questions = this.assessmentData.anknytning.questions;
                for (var questionId in questions) {
                    this.renderQuestion('anknytning', questionId, questions[questionId]);
                }
            }

            // Render ansvar questions
            if (this.assessmentData.ansvar && this.assessmentData.ansvar.questions) {
                var questions = this.assessmentData.ansvar.questions;
                for (var questionId in questions) {
                    this.renderQuestion('ansvar', questionId, questions[questionId]);
                }
            }

            // Update textarea with current data
            this.updateTextarea();
        },

        renderQuestion: function(section, questionId, questionData) {
            var self = this;
            var $container = section === 'anknytning' ? this.$ankQuestions : this.$ansvarQuestions;

            var $question = $('<div class="ham-question" data-section="' + section + '" data-question-id="' + questionId + '"></div>');

            // Question header
            var $header = $('<div class="ham-question-header"></div>');
            $header.append('<button type="button" class="ham-delete-question button button-secondary button-small" title="' + hamAssessmentEditor.texts.deleteQuestion + '">×</button>');
            $question.append($header);

            // Question content
            var $content = $('<div class="ham-question-content"></div>');

            // Question text field
            $content.append('<div class="ham-question-text"><label>' + hamAssessmentEditor.texts.question + ': <input type="text" class="ham-question-input" value="' + (questionData.text || '') + '"></label></div>');

            // Options container
            var $options = $('<div class="ham-options-container"></div>');

            // Render options
            if (questionData.options && questionData.options.length) {
                for (var i = 0; i < questionData.options.length; i++) {
                    var option = questionData.options[i];
                    self.renderOption($options, i, option);
                }
            }

            $content.append($options);

            // Add option button
            $content.append('<button type="button" class="button ham-add-option">' + hamAssessmentEditor.texts.addOption + '</button>');

            $question.append($content);
            $container.append($question);
        },

        renderOption: function($container, index, option) {
            var $option = $('<div class="ham-option" data-option-index="' + index + '"></div>');
            
            // Option number at the beginning of the option
            $option.append('<span class="ham-option-number">' + (index + 1) + '</span>');
            
            // Delete button in the header (positioned absolutely)
            var $header = $('<div class="ham-option-header"></div>');
            $header.append('<button type="button" class="ham-delete-option button button-secondary button-small" title="' + hamAssessmentEditor.texts.deleteOption + '">×</button>');
            $option.append($header);
            
            var $content = $('<div class="ham-option-content"></div>');
            $content.append('<label>' + hamAssessmentEditor.texts.value + ': <input type="text" class="ham-option-value" value="' + (option.value || '') + '"></label>');
            $content.append('<label>' + hamAssessmentEditor.texts.label + ': <input type="text" class="ham-option-label" value="' + (option.label || '') + '"></label>');
            
            var stageSelect = '<label>' + hamAssessmentEditor.texts.stage + ': <select class="ham-option-stage">';
            for (var stage in hamAssessmentEditor.stages) {
                stageSelect += '<option value="' + stage + '"' + (option.stage === stage ? ' selected' : '') + '>' + hamAssessmentEditor.stages[stage] + '</option>';
            }
            stageSelect += '</select></label>';
            $content.append(stageSelect);
            
            $option.append($content);
            $container.append($option);
        },

        switchTab: function(section) {
            // Update tab buttons
            this.$sectionTabs.removeClass('active');
            this.$sectionTabs.filter('[data-section="' + section + '"]').addClass('active');
            
            // Update content sections
            this.$sectionContents.hide();
            this.$sectionContents.filter('[data-section="' + section + '"]').show();
        },

        addQuestion: function(section) {
            // Generate a unique ID for the new question
            var questionId;
            var i = 1;
            
            // For anknytning, use a1, a2, etc.
            // For ansvar, use b1, b2, etc.
            var prefix = section === 'anknytning' ? 'a' : 'b';
            
            do {
                questionId = prefix + i;
                i++;
            } while (this.assessmentData[section].questions[questionId]);
            
            // Create new question with default options
            var newQuestion = {
                text: '',
                options: []
            };
            
            // Add default options
            for (var i = 1; i <= hamAssessmentEditor.defaultOptionsCount; i++) {
                newQuestion.options.push({
                    value: i.toString(),
                    label: i.toString(),
                    stage: i <= 2 ? 'ej' : (i <= 4 ? 'trans' : 'full')
                });
            }
            
            // Add to data
            this.assessmentData[section].questions[questionId] = newQuestion;
            
            // Render the new question
            this.renderQuestion(section, questionId, newQuestion);
            
            // Update textarea
            this.updateTextarea();
        },

        deleteQuestion: function(section, questionId) {
            // Remove from data
            if (this.assessmentData[section] && this.assessmentData[section].questions) {
                delete this.assessmentData[section].questions[questionId];
            }
            
            // Remove from DOM
            this.$editor.find('.ham-question[data-section="' + section + '"][data-question-id="' + questionId + '"]').remove();
            
            // Update textarea
            this.updateTextarea();
        },

        addOption: function(section, questionId) {
            // Get current options
            var question = this.assessmentData[section].questions[questionId];
            var options = question.options || [];
            var newIndex = options.length;
            
            // Create new option
            var newOption = {
                value: (newIndex + 1).toString(),
                label: (newIndex + 1).toString(),
                stage: 'ej'
            };
            
            // Add to data
            options.push(newOption);
            question.options = options;
            
            // Render the new option
            var $question = this.$editor.find('.ham-question[data-section="' + section + '"][data-question-id="' + questionId + '"]');
            var $container = $question.find('.ham-options-container');
            this.renderOption($container, newIndex, newOption);
            
            // Update textarea
            this.updateTextarea();
        },

        deleteOption: function(section, questionId, optionIndex) {
            // Get current options
            var question = this.assessmentData[section].questions[questionId];
            var options = question.options || [];
            
            // Remove from data
            if (optionIndex < options.length) {
                options.splice(optionIndex, 1);
                
                // Update data
                question.options = options;
                
                // Remove from DOM
                var $question = this.$editor.find('.ham-question[data-section="' + section + '"][data-question-id="' + questionId + '"]');
                $question.find('.ham-option[data-option-index="' + optionIndex + '"]').remove();
                
                // Re-render all options to update indices
                var $container = $question.find('.ham-options-container').empty();
                for (var i = 0; i < options.length; i++) {
                    this.renderOption($container, i, options[i]);
                }
                
                // Update textarea
                this.updateTextarea();
            }
        },

        updateDataFromDOM: function() {
            var self = this;
            var newData = {
                anknytning: {
                    title: 'Anknytning',
                    questions: {},
                    comments: this.assessmentData.anknytning ? (this.assessmentData.anknytning.comments || []) : []
                },
                ansvar: {
                    title: 'Ansvar',
                    questions: {},
                    comments: this.assessmentData.ansvar ? (this.assessmentData.ansvar.comments || []) : []
                }
            };

            // Loop through each question
            $('.ham-question').each(function() {
                var $question = $(this);
                var section = $question.data('section');
                var questionId = $question.data('question-id');

                // Get question text
                var questionText = $question.find('.ham-question-input').val();

                // Get options
                var options = [];
                $question.find('.ham-option').each(function() {
                    var $option = $(this);
                    options.push({
                        value: $option.find('.ham-option-value').val(),
                        label: $option.find('.ham-option-label').val(),
                        stage: $option.find('.ham-option-stage').val()
                    });
                });

                // Add to new data
                newData[section].questions[questionId] = {
                    text: questionText,
                    options: options
                };
            });

            // Update assessment data and field
            this.assessmentData = newData;
            this.updateTextarea();
            
            console.log('Updated data from DOM:', this.assessmentData);
        },

        updateTextarea: function() {
            var jsonString = JSON.stringify(this.assessmentData);
            this.$assessmentData.val(jsonString);
            console.log('Updated textarea with JSON data');
        },
        
        saveData: function() {
            var self = this;
            var postId = $('#post_ID').val();
            
            if (!postId) {
                console.error('Cannot save: No post ID found');
                return;
            }
            
            console.log('Saving assessment data via AJAX...');
            
            $.ajax({
                url: hamAssessmentEditor.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ham_save_assessment_data',
                    nonce: hamAssessmentEditor.nonce,
                    post_id: postId,
                    assessment_data: JSON.stringify(this.assessmentData)
                },
                success: function(response) {
                    if (response.success) {
                        console.log('Assessment data saved successfully:', response.data.message);
                    } else {
                        console.error('Error saving assessment data:', response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        console.log('HAM: Assessment editor initializing...');
        window.hamEditor = new AssessmentEditor();
        console.log('HAM: Assessment editor initialized');
    });

})(jQuery);

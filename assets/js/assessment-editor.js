/**
 * Assessment Editor JavaScript - FIXED VERSION
 *
 * Handles the interactive editor for assessment questions in the admin
 */
(function($) {
    'use strict';

    // Main Assessment Editor class
    var AssessmentEditor = function() {
        // DOM elements
        this.$editor = $('#ham-assessment-editor');
        this.$ankQuestions = $('#anknytning-questions');
        this.$ansvarQuestions = $('#ansvar-questions');
        this.$assessmentData = $('#ham_assessment_data_json');
        this.$sectionTabs = $('.ham-section-tab');
        this.$sectionContents = $('.ham-section-content');
        this.$addQuestionButtons = $('.ham-add-question');
        this.$form = $('#post');

        // Assessment data
        this.assessmentData = {
            anknytning: {
                questions: {},
                comments: {}
            },
            ansvar: {
                questions: {},
                comments: {}
            }
        };

        // Initialize
        this.init();
    };

    AssessmentEditor.prototype = {
        init: function() {
            console.log('Assessment Editor initializing...');

            // Try to parse existing data from textarea
            try {
                var jsonData = this.$assessmentData.val();
                console.log('Raw textarea value:', jsonData);

                if (jsonData && jsonData.trim() !== '') {
                    var parsed = JSON.parse(jsonData);
                    console.log('Successfully parsed JSON data', parsed);
                    this.assessmentData = parsed;
                } else {
                    console.log('Textarea is empty, using default structure');
                }
            } catch (e) {
                console.error('Error parsing JSON from textarea:', e);
                console.log('Using default structure due to parsing error');
            }

            // Render questions
            this.renderAllQuestions();

            // Bind events
            this.bindEvents();

            console.log('Assessment Editor initialized');
        },

        bindEvents: function() {
            var self = this;
            console.log('Binding events...');

            // Tab switching
            this.$sectionTabs.on('click', function() {
                var section = $(this).data('section');
                self.switchTab(section);
            });

            // Add question button
            this.$addQuestionButtons.on('click', function() {
                var section = $(this).data('section');
                self.addQuestion(section);
            });

            // Event delegation for dynamic elements
            this.$editor.on('click', '.ham-delete-question', function(e) {
                e.preventDefault();
                var $question = $(this).closest('.ham-question');
                var section = $question.data('section');
                var questionId = $question.data('question-id');
                self.deleteQuestion(section, questionId);
            });

            this.$editor.on('click', '.ham-add-option', function(e) {
                e.preventDefault();
                var $question = $(this).closest('.ham-question');
                var section = $question.data('section');
                var questionId = $question.data('question-id');
                self.addOption(section, questionId);
            });

            this.$editor.on('click', '.ham-delete-option', function(e) {
                e.preventDefault();
                var $option = $(this).closest('.ham-option');
                var $question = $option.closest('.ham-question');
                var section = $question.data('section');
                var questionId = $question.data('question-id');
                var optionIndex = $option.data('option-index');
                self.deleteOption(section, questionId, optionIndex);
            });

            // Input change events - handle all input types
            this.$editor.on('input change', 'input, select, textarea', function() {
                self.updateDataFromDOM();
            });

            // Critical: Form submission handling
            this.$form.on('submit', function(e) {
                console.log('Form is being submitted - updating data...');
                self.updateDataFromDOM();

                // Double check data is in the textarea
                var textareaValue = self.$assessmentData.val();
                console.log('Final textarea value length:', textareaValue ? textareaValue.length : 0);
                console.log('First 100 chars:', textareaValue ? textareaValue.substring(0, 100) : 'empty');

                return true; // Allow form submission to continue
            });

            console.log('Events bound');
        },

        renderAllQuestions: function() {
            console.log('Rendering all questions');
            this.$ankQuestions.empty();
            this.$ansvarQuestions.empty();

            // Render anknytning questions
            if (this.assessmentData.anknytning && this.assessmentData.anknytning.questions) {
                for (var questionId in this.assessmentData.anknytning.questions) {
                    this.renderQuestion('anknytning', questionId, this.assessmentData.anknytning.questions[questionId]);
                }
            }

            // Render ansvar questions
            if (this.assessmentData.ansvar && this.assessmentData.ansvar.questions) {
                for (var questionId in this.assessmentData.ansvar.questions) {
                    this.renderQuestion('ansvar', questionId, this.assessmentData.ansvar.questions[questionId]);
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
            $header.append('<button type="button" class="ham-delete-question button-link" title="' + hamAssessmentEditor.texts.deleteQuestion + '">×</button>');
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
            $content.append('<div class="ham-actions"><button type="button" class="button ham-add-option">' + hamAssessmentEditor.texts.addOption + '</button></div>');

            $question.append($content);
            $container.append($question);
        },

        renderOption: function($container, index, option) {
            var $option = $('<div class="ham-option" data-option-index="' + index + '"></div>');

            // Option fields
            $option.append('<label>' + hamAssessmentEditor.texts.value + ': <input type="text" class="ham-option-value" value="' + (option.value || '') + '"></label>');
            $option.append('<label>' + hamAssessmentEditor.texts.label + ': <input type="text" class="ham-option-label" value="' + (option.label || '') + '"></label>');

            // Stage dropdown
            var $stageSelect = $('<select class="ham-option-stage"></select>');
            for (var stage in hamAssessmentEditor.stages) {
                $stageSelect.append('<option value="' + stage + '"' + (option.stage === stage ? ' selected' : '') + '>' + hamAssessmentEditor.stages[stage] + '</option>');
            }
            $option.append('<label>' + hamAssessmentEditor.texts.stage + ': </label>').append($stageSelect);

            // Delete button
            $option.append('<button type="button" class="ham-delete-option button-link" title="' + hamAssessmentEditor.texts.deleteOption + '">×</button>');

            $container.append($option);
        },

        switchTab: function(section) {
            // Update tab buttons
            this.$sectionTabs.removeClass('active');
            this.$sectionTabs.filter('[data-section="' + section + '"]').addClass('active');

            // Update content sections
            this.$sectionContents.removeClass('active');
            this.$sectionContents.filter('[data-section="' + section + '"]').addClass('active');
        },

        addQuestion: function(section) {
            console.log('Adding new question to section:', section);

            // Generate a unique ID for the question
            var questionId = 'question_' + new Date().getTime();

            // Create default options
            var options = [];
            for (var i = 1; i <= hamAssessmentEditor.defaultOptionsCount; i++) {
                options.push({
                    value: String(i),
                    label: hamAssessmentEditor.texts.option + ' ' + i,
                    stage: i <= 2 ? 'ej' : (i <= 4 ? 'trans' : 'full')
                });
            }

            // Create question data
            var questionData = {
                text: hamAssessmentEditor.texts.question,
                options: options
            };

            // Add to assessment data
            if (!this.assessmentData[section]) {
                this.assessmentData[section] = { questions: {}, comments: {} };
            }
            if (!this.assessmentData[section].questions) {
                this.assessmentData[section].questions = {};
            }
            this.assessmentData[section].questions[questionId] = questionData;

            // Render the new question
            this.renderQuestion(section, questionId, questionData);

            // Update textarea
            this.updateTextarea();

            console.log('Question added with ID:', questionId);
        },

        deleteQuestion: function(section, questionId) {
            console.log('Deleting question:', section, questionId);

            // Remove from assessment data
            if (this.assessmentData[section] &&
                this.assessmentData[section].questions &&
                this.assessmentData[section].questions[questionId]) {
                delete this.assessmentData[section].questions[questionId];
            }

            // Remove from DOM
            $('.ham-question[data-section="' + section + '"][data-question-id="' + questionId + '"]').remove();

            // Update textarea
            this.updateTextarea();

            console.log('Question deleted');
        },

        addOption: function(section, questionId) {
            console.log('Adding option to question:', section, questionId);

            var self = this;
            var $question = $('.ham-question[data-section="' + section + '"][data-question-id="' + questionId + '"]');
            var $optionsContainer = $question.find('.ham-options-container');

            // Get current options count
            var optionIndex = $optionsContainer.children().length;

            // Create new option data
            var option = {
                value: String(optionIndex + 1),
                label: hamAssessmentEditor.texts.option + ' ' + (optionIndex + 1),
                stage: 'ej'
            };

            // Add to assessment data
            this.assessmentData[section].questions[questionId].options.push(option);

            // Render the new option
            self.renderOption($optionsContainer, optionIndex, option);

            // Update textarea
            this.updateTextarea();

            console.log('Option added at index:', optionIndex);
        },

        deleteOption: function(section, questionId, optionIndex) {
            console.log('Deleting option:', section, questionId, optionIndex);

            // Remove from assessment data
            if (this.assessmentData[section] &&
                this.assessmentData[section].questions &&
                this.assessmentData[section].questions[questionId] &&
                this.assessmentData[section].questions[questionId].options) {
                this.assessmentData[section].questions[questionId].options.splice(optionIndex, 1);
            }

            // Re-render all options for this question to update indices
            var $question = $('.ham-question[data-section="' + section + '"][data-question-id="' + questionId + '"]');
            var $optionsContainer = $question.find('.ham-options-container');
            $optionsContainer.empty();

            var options = this.assessmentData[section].questions[questionId].options;
            for (var i = 0; i < options.length; i++) {
                this.renderOption($optionsContainer, i, options[i]);
            }

            // Update textarea
            this.updateTextarea();

            console.log('Option deleted');
        },

        updateDataFromDOM: function() {
            console.log('Updating data from DOM');

            var self = this;

            // Initialize with empty structure but preserve comments
            var newData = {
                anknytning: {
                    questions: {},
                    comments: this.assessmentData.anknytning ? (this.assessmentData.anknytning.comments || {}) : {}
                },
                ansvar: {
                    questions: {},
                    comments: this.assessmentData.ansvar ? (this.assessmentData.ansvar.comments || {}) : {}
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
                    var option = {
                        value: $option.find('.ham-option-value').val(),
                        label: $option.find('.ham-option-label').val(),
                        stage: $option.find('.ham-option-stage').val()
                    };
                    options.push(option);
                });

                // Add to new data
                newData[section].questions[questionId] = {
                    text: questionText,
                    options: options
                };
            });

            // Update assessment data
            this.assessmentData = newData;

            // Update textarea
            this.updateTextarea();

            console.log('Data updated from DOM');
        },

        updateTextarea: function() {
            var jsonString = JSON.stringify(this.assessmentData);
            console.log('Updating textarea with JSON data, length:', jsonString.length);

            // Update the hidden textarea with the current data
            this.$assessmentData.val(jsonString);

            // Debug - show first part of the data
            if (jsonString.length > 0) {
                console.log('Data sample:', jsonString.substring(0, 100) + '...');
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        console.log('Document ready - initializing Assessment Editor');
        window.hamEditor = new AssessmentEditor();

        // Debugging - watch for form submission
        $('#post').on('submit', function() {
            console.log('Form submit detected!');
            console.log('Textarea value length:', $('#ham_assessment_data_json').val().length);
        });
    });

})(jQuery);

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
        this.$ankQuestions = $('#anknytning-questions');
        this.$ansvarQuestions = $('#ansvar-questions');
        this.$assessmentData = $('#ham_assessment_data_json');
        this.$sectionTabs = $('.ham-section-tab');
        this.$sectionContents = $('.ham-section-content');
        this.$addQuestionButtons = $('.ham-add-question');

        // Assessment data
        this.assessmentData = {};

        // Initialize
        this.init();
    };

    AssessmentEditor.prototype = {
        init: function() {
            // Try to parse existing data from textarea
            try {
                this.assessmentData = JSON.parse(this.$assessmentData.val());
            } catch (e) {
                // Initialize with empty structure if parsing fails
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
            }

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

            // Input change events
            this.$editor.on('change', 'input, select', function() {
                self.updateDataFromDOM();
            });
        },

        renderAllQuestions: function() {
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
        },

        deleteQuestion: function(section, questionId) {
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
        },

        addOption: function(section, questionId) {
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
        },

        deleteOption: function(section, questionId, optionIndex) {
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
        },

        updateDataFromDOM: function() {
            var self = this;

            // Initialize with empty structure
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
        },

        updateTextarea: function() {
            this.$assessmentData.val(JSON.stringify(this.assessmentData));
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        new AssessmentEditor();
    });

})(jQuery);

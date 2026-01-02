/**
 * Assessment Template Editor JavaScript
 *
 * Handles the interactive editor for assessment templates in the admin
 */
(function($) {
    'use strict';

    // Main Template Editor class
    var TemplateEditor = function() {
        // DOM elements
        this.$container = $('#ham-sections-container');
        this.$addSectionBtn = $('.ham-add-section');
        this.$templateStructure = $('#ham_template_structure');

        // Template data
        this.structure = {};

        // Initialize
        this.init();
    };

    TemplateEditor.prototype = {
        init: function() {
            // Try to parse existing structure from textarea
            try {
                this.structure = JSON.parse(this.$templateStructure.val());
            } catch (e) {
                // Initialize with empty structure if parsing fails
                this.structure = {
                    sections: []
                };
            }

            // Render template structure
            this.renderStructure();

            // Bind events
            this.bindEvents();
        },

        bindEvents: function() {
            var self = this;

            // Add section button
            this.$addSectionBtn.on('click', function(e) {
                e.preventDefault();
                self.addSection();
            });

            // Event delegation for dynamic elements
            this.$container.on('click', '.ham-add-field', function(e) {
                e.preventDefault();
                var sectionIndex = $(this).closest('.ham-section').data('index');
                self.addField(sectionIndex);
            });

            this.$container.on('click', '.ham-add-option', function(e) {
                e.preventDefault();
                var sectionIndex = $(this).closest('.ham-section').data('index');
                var fieldIndex = $(this).closest('.ham-field').data('index');
                self.addOption(sectionIndex, fieldIndex);
            });

            this.$container.on('click', '.ham-delete-section', function(e) {
                e.preventDefault();
                var sectionIndex = $(this).closest('.ham-section').data('index');
                self.deleteSection(sectionIndex);
            });

            this.$container.on('click', '.ham-delete-field', function(e) {
                e.preventDefault();
                var sectionIndex = $(this).closest('.ham-section').data('index');
                var fieldIndex = $(this).closest('.ham-field').data('index');
                self.deleteField(sectionIndex, fieldIndex);
            });

            this.$container.on('click', '.ham-delete-option', function(e) {
                e.preventDefault();
                var sectionIndex = $(this).closest('.ham-section').data('index');
                var fieldIndex = $(this).closest('.ham-field').data('index');
                var optionIndex = $(this).closest('.ham-option').data('index');
                self.deleteOption(sectionIndex, fieldIndex, optionIndex);
            });

            // Input change events
            // Use both 'input' and 'change' so text edits are captured even if the user
            // clicks "Update" without blurring the field.
            this.$container.on('input', 'input', function() {
                self.updateStructureFromDOM();
            });
            this.$container.on('change', 'input, select', function() {
                self.updateStructureFromDOM();
            });

            // Ensure the hidden textarea is up-to-date right before the post is saved.
            // WordPress saves via the #post form submit.
            $(document).on('submit', '#post', function() {
                self.updateStructureFromDOM();
            });

            // Make sections sortable
            this.$container.sortable({
                items: '.ham-section',
                handle: '.ham-section-header',
                update: function() {
                    self.updateStructureFromDOM();
                }
            });
        },

        renderStructure: function() {
            var self = this;
            this.$container.empty();

            // Render each section
            if (this.structure.sections && this.structure.sections.length) {
                $.each(this.structure.sections, function(index, section) {
                    self.renderSection(index, section);
                });
            }

            // Update the textarea with the current structure
            this.updateTextarea();
        },

        renderSection: function(index, section) {
            var self = this;
            var $section = $('<div class="ham-section" data-index="' + index + '"></div>');

            // Section header
            var $header = $('<div class="ham-section-header"></div>');
            $header.append('<h3>' + (section.title || hamTemplateEditor.texts.section) + '</h3>');
            $header.append('<button type="button" class="button ham-delete-section">' + hamTemplateEditor.texts.deleteSection + '</button>');
            $section.append($header);

            // Section properties
            var $props = $('<div class="ham-section-props"></div>');
            $props.append('<label>' + hamTemplateEditor.texts.id + ': <input type="text" class="ham-section-id" value="' + (section.id || '') + '"></label>');
            $props.append('<label>' + hamTemplateEditor.texts.title + ': <input type="text" class="ham-section-title" value="' + (section.title || '') + '"></label>');
            $section.append($props);

            // Fields container
            var $fields = $('<div class="ham-fields-container"></div>');

            // Render each field
            if (section.fields && section.fields.length) {
                $.each(section.fields, function(fieldIndex, field) {
                    self.renderField($fields, fieldIndex, field);
                });
            }

            $section.append($fields);

            // Add field button
            $section.append('<div class="ham-field-actions"><button type="button" class="button ham-add-field">' + hamTemplateEditor.texts.addField + '</button></div>');

            this.$container.append($section);
        },

        renderField: function($parent, index, field) {
            var self = this;
            var $field = $('<div class="ham-field" data-index="' + index + '"></div>');

            // Field header
            var $header = $('<div class="ham-field-header"></div>');
            $header.append('<h4>' + (field.title || hamTemplateEditor.texts.field) + '</h4>');
            $header.append('<button type="button" class="button ham-delete-field">' + hamTemplateEditor.texts.deleteField + '</button>');
            $field.append($header);

            // Field properties
            var $props = $('<div class="ham-field-props"></div>');
            $props.append('<label>' + hamTemplateEditor.texts.id + ': <input type="text" class="ham-field-id" value="' + (field.id || '') + '"></label>');
            $props.append('<label>' + hamTemplateEditor.texts.title + ': <input type="text" class="ham-field-title" value="' + (field.title || '') + '"></label>');
            $field.append($props);

            // Options container
            var $options = $('<div class="ham-options-container"></div>');
            $options.append('<h5>Options</h5>');

            // Render each option
            if (field.options && field.options.length) {
                $.each(field.options, function(optionIndex, option) {
                    self.renderOption($options, optionIndex, option);
                });
            }

            $field.append($options);

            // Add option button
            $field.append('<div class="ham-option-actions"><button type="button" class="button ham-add-option">' + hamTemplateEditor.texts.addOption + '</button></div>');

            $parent.append($field);
        },

        renderOption: function($parent, index, option) {
            var $option = $('<div class="ham-option" data-index="' + index + '"></div>');

            // Option properties
            $option.append('<label>' + hamTemplateEditor.texts.value + ': <input type="text" class="ham-option-value" value="' + (option.value || '') + '"></label>');
            $option.append('<label>' + hamTemplateEditor.texts.label + ': <input type="text" class="ham-option-label" value="' + (option.label || '') + '"></label>');

            // Stage dropdown
            var $stageSelect = $('<select class="ham-option-stage"></select>');
            $.each(hamTemplateEditor.stages, function(value, label) {
                $stageSelect.append('<option value="' + value + '"' + (option.stage === value ? ' selected' : '') + '>' + label + '</option>');
            });
            $option.append('<label>' + hamTemplateEditor.texts.stage + ': </label>').append($stageSelect);

            // Delete button
            $option.append('<button type="button" class="button ham-delete-option">' + hamTemplateEditor.texts.deleteOption + '</button>');

            $parent.append($option);
        },

        addSection: function() {
            // Add new empty section to structure
            this.structure.sections.push({
                id: '',
                title: hamTemplateEditor.texts.section,
                fields: []
            });

            // Re-render and update
            this.renderStructure();
        },

        addField: function(sectionIndex) {
            // Add new empty field to section
            this.structure.sections[sectionIndex].fields.push({
                id: '',
                title: hamTemplateEditor.texts.field,
                options: []
            });

            // Re-render and update
            this.renderStructure();
        },

        addOption: function(sectionIndex, fieldIndex) {
            // Add new empty option to field
            this.structure.sections[sectionIndex].fields[fieldIndex].options.push({
                value: '',
                label: hamTemplateEditor.texts.option,
                stage: 'ej'
            });

            // Re-render and update
            this.renderStructure();
        },

        deleteSection: function(sectionIndex) {
            // Remove section from structure
            this.structure.sections.splice(sectionIndex, 1);

            // Re-render and update
            this.renderStructure();
        },

        deleteField: function(sectionIndex, fieldIndex) {
            // Remove field from section
            this.structure.sections[sectionIndex].fields.splice(fieldIndex, 1);

            // Re-render and update
            this.renderStructure();
        },

        deleteOption: function(sectionIndex, fieldIndex, optionIndex) {
            // Remove option from field
            this.structure.sections[sectionIndex].fields[fieldIndex].options.splice(optionIndex, 1);

            // Re-render and update
            this.renderStructure();
        },

        updateStructureFromDOM: function() {
            var self = this;
            var newStructure = {
                sections: []
            };

            // Loop through each section
            $('.ham-section').each(function() {
                var $section = $(this);
                var section = {
                    id: $section.find('.ham-section-id').val(),
                    title: $section.find('.ham-section-title').val(),
                    fields: []
                };

                // Loop through each field in this section
                $section.find('.ham-field').each(function() {
                    var $field = $(this);
                    var field = {
                        id: $field.find('.ham-field-id').val(),
                        title: $field.find('.ham-field-title').val(),
                        options: []
                    };

                    // Loop through each option in this field
                    $field.find('.ham-option').each(function() {
                        var $option = $(this);
                        var option = {
                            value: $option.find('.ham-option-value').val(),
                            label: $option.find('.ham-option-label').val(),
                            stage: $option.find('.ham-option-stage').val()
                        };

                        field.options.push(option);
                    });

                    section.fields.push(field);
                });

                newStructure.sections.push(section);
            });

            // Update the structure
            this.structure = newStructure;

            // Update the textarea with the new structure
            this.updateTextarea();
        },

        updateTextarea: function() {
            this.$templateStructure.val(JSON.stringify(this.structure));
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        new TemplateEditor();
    });

})(jQuery);

jQuery(document).ready(function($) {
    window.HAM = window.HAM || {};

    window.HAM.dynamicSelectPopulator = function(config) {
        var $triggerElement = $(config.triggerSelector);
        var $targetElement = $(config.targetSelector);
        var $statusContainer = null;

        if (!$triggerElement.length) {
            console.error('HAM Populator: Trigger element "' + config.triggerSelector + '" not found.');
            return;
        }
        if (!$targetElement.length) {
            console.error('HAM Populator: Target element "' + config.targetSelector + '" not found.');
            return;
        }

        // Create a status message container after the target element
        var statusContainerId = config.targetSelector.replace(/[^a-zA-Z0-9-_]/g, '') + '-status';
        if ($('#' + statusContainerId).length === 0) {
            $targetElement.after('<p id="' + statusContainerId + '" class="description ham-populator-status"></p>');
        }
        $statusContainer = $('#' + statusContainerId);

        $triggerElement.on('change', function() {
            var triggerValue = $(this).val();

            $targetElement.empty();
            $statusContainer.empty();

            if (!triggerValue || triggerValue === "") {
                $targetElement.append($('<option/>', {
                    value: '',
                    text: config.messages.selectTriggerFirst || 'Please make a selection above first.'
                }));
                $statusContainer.text(config.messages.selectTriggerFirst || 'Please make a selection above to see options.');
                return;
            }

            $targetElement.append($('<option/>', {
                value: '',
                text: config.messages.loading || 'Loading...'
            }));
            $statusContainer.text(config.messages.loading || 'Loading...');

            var ajaxData = {
                action: config.ajaxAction,
                nonce: config.nonce
            };
            ajaxData[config.dataKey] = triggerValue; // e.g., school_id: triggerValue

            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: ajaxData,
                success: function(response) {
                    $targetElement.empty();
                    $statusContainer.empty();

                    if (response.success && response.data && response.data.length > 0) {
                        $.each(response.data, function(index, item) {
                            $targetElement.append($('<option/>', {
                                value: item.id,
                                text: item.title
                            }));
                        });
                    } else if (response.success && response.data && response.data.length === 0) {
                        $targetElement.append($('<option/>', {
                            value: '',
                            text: config.messages.noItemsFound || 'No items found.'
                        }));
                        $statusContainer.text(config.messages.noItemsFound || 'No items found for the current selection.');
                    } else {
                        var errorMsg = config.messages.errorLoading || 'Error loading items.';
                        if(response.data && response.data.message) errorMsg = response.data.message;
                        $targetElement.append($('<option/>', { value: '', text: errorMsg }));
                        $statusContainer.text(errorMsg);
                        console.error('Error fetching items for ' + config.targetSelector + ':', response);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    $targetElement.empty();
                    $statusContainer.empty();
                    $targetElement.append($('<option/>', {
                        value: '',
                        text: config.messages.errorLoading || 'Error loading items.'
                    }));
                    $statusContainer.text((config.messages.ajaxError || 'AJAX request failed: ') + textStatus);
                    console.error('AJAX error for ' + config.targetSelector + ':', textStatus, errorThrown);
                }
            });
        });

        // If a value is already selected in the trigger, and the target is empty (e.g. on page load after validation error)
        // OR if we want to force initial population (though PHP should handle the very first load)
        // For now, let PHP handle initial population to prevent clearing existing valid selections.
        // if ($triggerElement.val() && $targetElement.children('option').length <= 1) { 
        //    $triggerElement.trigger('change');
        // }
    };

    // Initialization for specific contexts will be done via wp_add_inline_script
    // Example (would be added by PHP localization):
    // if (typeof hamTeacherPopulatorConfig !== 'undefined') {
    //     HAM.dynamicSelectPopulator(hamTeacherPopulatorConfig);
    // }
});

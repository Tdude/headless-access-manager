/**
 * AJAX-based filtering for WordPress admin list tables
 */

(function($) {
    'use strict';

    // Main initialization function
    function initHamFilters() {

        const filters = getFilterElements();
        
        // Only proceed if we have filters and we're on a list table page
        if (filters.length && $('.wp-list-table').length) {
            bindFilterEvents(filters);
        } else {
            console.warn('HAM AJAX: No filters found or not on a list table page');
        }
    }

    // Get all filter elements (any selects within filter forms/containers)
    function getFilterElements() {
        // First try the standard naming pattern
        const standardFilters = $('select[name^="ham_filter_"]');
        
        if (standardFilters.length > 0) {
            return standardFilters;
        }
        
        // If no standard filters found, look for any selects in the filter area
        const filterAreaSelects = $('.tablenav-pages select, .alignleft.actions select').filter(function() {
            // Exclude pagination and bulk action selects
            return !$(this).attr('name').match(/^(action|action2|post_status|cat|paged|per_page|mode|filter_action)$/);
        });
        
        console.log('HAM AJAX: Alternative filter elements found:', filterAreaSelects.length);
        return filterAreaSelects;
    }

    // Bind change events to filter elements
    function bindFilterEvents(filters) {
        filters.on('change', function() {
            showLoadingBar();
            sendFilterRequest();
        });
    }

    // Show loading spinner overlay
    function showLoadingBar() {
        // Remove existing loading overlay if any
        $('.ham-ajax-loading-overlay').remove();
        
        // Add loading overlay with spinner
        $('body').append('<div class="ham-ajax-loading-overlay"><div class="ham-ajax-spinner"></div></div>');
    }

    // Hide loading spinner overlay
    function hideLoadingBar() {
        $('.ham-ajax-loading-overlay').fadeOut(200, function() {
            $(this).remove();
        });
    }

    // Send AJAX request with filter data
    function sendFilterRequest() {
        // Get all filter values
        const formData = collectFilterData();
        
        $.ajax({
            url: ajaxurl, // WordPress global
            type: 'POST',
            data: formData,
            success: handleFilterSuccess,
            error: handleFilterError
        });
    }

    // Collect all filter values
    function collectFilterData() {
        const filters = getFilterElements();
        let data = {
            action: 'ham_filter_admin_list_table',
            nonce: ham_ajax.nonce,
            post_type: ham_ajax.post_type
        };
        
        // Collect all filter values including non-ham_filter_ prefixed ones
        filters.each(function() {
            const name = $(this).attr('name');
            const value = $(this).val();
            
            if (name.startsWith('ham_filter_')) {
                data[name] = value;
            } 
            else {
                // Convert field name to ham_filter_ format for the AJAX handler
                const normalizedName = 'ham_filter_' + name.replace(/^filter_/, '');
                data[normalizedName] = value;
                console.log('HAM AJAX: Normalized filter name from', name, 'to', normalizedName);
            }
        });

        return data;
    }

    // Handle successful AJAX response
    function handleFilterSuccess(response) {
        if (response.success && response.data) {
            try {
                // Create a temporary div to hold the new table HTML
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = response.data.trim();
                
                replaceTableContent(tempDiv);
                
                updateUrlWithFilters();
            } catch (err) {
                console.error('HAM AJAX: Error processing response:', err);
            }
        } else {
            console.error('HAM AJAX: Invalid response format', response);
        }
        
        hideLoadingBar();
    }

    // Handle AJAX error
    function handleFilterError(xhr, status, error) {
        console.error('HAM AJAX: Error', status, error);
        hideLoadingBar();
        
        // Show error notification
        alert('Error filtering table. Please try again.');
    }

    // Replace the table content with new filtered content
    function replaceTableContent(tempDiv) {
        const $newContainer = $(tempDiv).find('.wp-list-table-container');
        const $newTable = $(tempDiv).find('.wp-list-table');
            
        // Also examine the current page structure
        const $container = $('.wp-list-table-container');
        const $currentTable = $('.wp-list-table');
        
        if ($container.length && $newContainer.length) {
            
            try {
                // Extract just the table from the new container
                const $extractedNewTable = $newContainer.find('.wp-list-table');
                
                if ($extractedNewTable.length && $currentTable.length) {
                    // Replace only the table, not the whole container
                    $currentTable.replaceWith($extractedNewTable);
                    
                    bindFilterEvents(getFilterElements());
                    
                    return true;
                } else {
                    console.error('HAM AJAX: Could not find table in response or on page');
                }
            } catch (err) {
                console.error('HAM AJAX: Error replacing table content:', err);
            }
        }
        
        // STRATEGY 2: Try replacing just the table
        if ($currentTable.length && $newTable.length) {
            
            try {
                // Use direct DOM replacement for more reliable update
                $currentTable[0].outerHTML = $newTable[0].outerHTML;

                // Re-bind events to the new elements
                bindFilterEvents(getFilterElements());
                
                return true;
            } catch (err) {
                console.error('HAM AJAX: Error replacing table:', err);
            }
        }
        
        console.error('HAM AJAX: Could not find elements to update');
        return false;
    }



    // Update the URL with filter parameters without page reload
    function updateUrlWithFilters() {
        const filters = getFilterElements();
        let url = new URL(window.location.href);
        
        // Clear existing filter params
        url.searchParams.forEach((value, key) => {
            if (key.startsWith('ham_filter_')) {
                url.searchParams.delete(key);
            }
        });
        
        // Add current filter params
        filters.each(function() {
            const name = $(this).attr('name');
            const value = $(this).val();
            
            if (value) {
                url.searchParams.set(name, value);
            }
        });
        
        // Update URL without reload
        window.history.pushState({}, '', url);
    }

    // Add CSS for loading spinner
    function addStyles() {
        $('head').append(`
            <style>
                .ham-ajax-loading-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background-color: rgba(255, 255, 255, 0.7);
                    z-index: 99998;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                }
                
                .ham-ajax-spinner {
                    display: inline-block;
                    width: 50px;
                    height: 50px;
                    border: 3px solid rgba(0, 113, 161, 0.2);
                    border-radius: 50%;
                    border-top-color: #2271b1;
                    animation: ham-spin 1s ease-in-out infinite;
                }
                
                @keyframes ham-spin {
                    to { transform: rotate(360deg); }
                }
            </style>
        `);
    }

    // Initialize everything when document is ready
    $(document).ready(function() {
        addStyles();
        initHamFilters();
    });

})(jQuery);
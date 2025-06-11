jQuery(document).ready(function($) {
    // Target select elements with the class 'ham-auto-filter'
    // These are typically found within the 'posts-filter' form on edit.php screens.
    // Ensure the form ID is correct; default WordPress admin list table filter form is 'posts-filter'.
    var $filterForm = $('form#posts-filter');

    if ($filterForm.length) {
        $filterForm.on('change', 'select.ham-auto-filter', function() {
            // The 'posts-filter' form is the main form for list table filters.
            // Submitting it will reload the page with the new filter parameters.
            $filterForm.submit();
        });
    } else {
        // Fallback or logging if the form isn't found, though it should be there on edit.php
        console.log('HAM Admin Enhancements: Could not find #posts-filter form.');
    }
});

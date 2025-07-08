jQuery(document).ready(function($) {
    if (typeof $.fn.select2 === 'function') {
        $('.ham-student-search-select').select2({
            ajax: {
                url: ham_assessment_ajax.ajax_url,
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        action: 'ham_search_students',
                        nonce: ham_assessment_ajax.search_nonce,
                        term: params.term, // search term
                    };
                },
                processResults: function(data) {
                    if (data.success) {
                        return {
                            results: data.data.results
                        };
                    } else {
                        return {
                            results: []
                        };
                    }
                },
                cache: true
            },
            placeholder: ham_assessment_ajax.placeholder,
            minimumInputLength: 2,
            allowClear: true
        });
    }
});

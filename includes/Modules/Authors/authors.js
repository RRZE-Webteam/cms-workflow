jQuery(document).ready(function($) {
    $('ul.workflow-post-authors-list').listFilterizer({
        filters: [{
            label: authors_vars.filters_label_1,
            selector: '*'
        }, {
            label: authors_vars.filters_label_2,
            selector: ':has(input:checked)'
        }],
        inputPlaceholder: authors_vars.placeholder
    });
	
});
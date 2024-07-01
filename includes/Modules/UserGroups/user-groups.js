jQuery(document).ready(function ($) {
    $('ul.workflow-groups-list').listFilterizer({
        filters: [{
            label: user_groups_vars.filters_label_1,
            selector: '*'
        }, {
            label: user_groups_vars.filters_label_2,
            selector: ':has(input:checked)'
        }],
        inputPlaceholder: user_groups_vars.placeholder
    });

    $('.delete-usergroup a').click(function () {
        return showNotice.warn();
    })
});
jQuery(document).ready(function ($) {
	$('ul#workflow-groups-users li').quicksearch({
		position: 'before',
		attached: 'ul#workflow-groups-users',
		loaderText: '',
		delay: 100
	})
    
	$('#workflow-usergroup-users ul').listFilterizer();
    
    $('.delete-usergroup a').click(function() {
        return showNotice.warn();
    })
});
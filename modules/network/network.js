(function( $ ) {
	$(document).ready( function() {
		var position = { offset: '0, -1' };
		if ( typeof selectSite.isRtl !== 'undefined' && selectSite.isRtl ) {
			position.my = 'right top';
			position.at = 'right bottom';
		}
		$( '#workflow-network-select' ).autocomplete({
			source:    selectSite.ajaxurl + '?action=workflow_network_select',
			delay:     500,
			minLength: 2,
			position:  position,
			open: function() {
				$( this ).addClass('open');
			},
			close: function() {
				$( this ).removeClass('open');
			}
		});
	});
})( jQuery );
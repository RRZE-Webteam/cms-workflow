(function( $ ) {
	$(document).ready( function() {
		var position = { offset: '0, -1' };
		if ( typeof suggest.isRtl !== 'undefined' && suggest.isRtl ) {
			position.my = 'right top';
			position.at = 'right bottom';
		}
		$( '.workflow-suggest-site' ).autocomplete({
			source:    suggest.ajaxurl + '?action=workflow_suggest_site',
			delay:     500,
			minLength: 2,
			position:  position,
			open: function() {
				$( this ).addClass( 'open' );
			},
			close: function() {
				$( this ).removeClass( 'open' );
			}
		});
	});
})( jQuery );
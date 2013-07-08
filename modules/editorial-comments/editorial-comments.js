jQuery(document).ready(function ($) {
	editorialCommentReply.init();

	if (location.hash == '#editorial-comments/add') {
		editorialCommentReply.open();
	} else if (location.hash.search(/#editorial-comments\/reply/) > -1) {
		var reply_id = location.hash.substring(location.hash.lastIndexOf('/')+1);
		editorialCommentReply.open(reply_id);
	}
});

editorialCommentReply = {

	init : function() {
		var row = jQuery('#workflow-replyrow');
		
		jQuery('a.workflow-replycancel', row).click(function() { return editorialCommentReply.revert(); });
		jQuery('a.workflow-replysave', row).click(function() { return editorialCommentReply.send(); });
	},

	revert : function() {
		jQuery('#workflow-replyrow').fadeOut('fast', function(){
			editorialCommentReply.close();
		});
		return false;
	},

	close : function() {
		
		jQuery('#workflow-comment_respond').show();

		jQuery('#workflow-post_comment').after( jQuery('#workflow-replyrow') );
		
		jQuery('#workflow-replycontent').val('');
		jQuery('#workflow-comment_parent').val('');

		jQuery('#workflow-replysubmit .error').html('').hide();
		jQuery('#workflow-comment_loading').hide();
	},

	open : function(id) {
		var parent;
		
		this.close();
		
		if(id) {
			jQuery('input#workflow-comment_parent').val(id);
			parent = '#comment-'+id;
		} else {
			parent = '#workflow-comments_wrapper';
		}
		
		jQuery('#workflow-comment_respond').hide();
		
		jQuery('#workflow-replyrow')
			.show()
			.appendTo(jQuery(parent))
			;
		
		jQuery('#workflow-replycontent').focus();

		return false;
	},

	send : function() {
		var post = {};
		
		jQuery('#workflow-replysubmit .error').html('').hide();
		
		post.content = jQuery.trim(jQuery('#workflow-replycontent').val());
		if(!post.content) {
			jQuery('#workflow-replyrow .error').text('Bitte geben Sie einen Kommentar.').show();
			return;
		}
		
		jQuery('#workflow-comment_loading').show();

		post.action = 'workflow_ajax_insert_comment';
		post.parent = (jQuery("#workflow-comment_parent").val()=='') ? 0 : jQuery("#workflow-comment_parent").val();
		post._nonce = jQuery("#workflow_comment_nonce").val();
		post.post_id = jQuery("#workflow-post_id").val();
		
		jQuery.ajax({
			type : 'POST',
			url : (ajaxurl) ? ajaxurl : wpListL10n.url,
			data : post,
			success : function(x) { editorialCommentReply.show(x); },
			error : function(r) { editorialCommentReply.error(r); }
		});

		return false;
	},

	show : function(xml) {
		var response, comment, supplemental, id, bg;
		
		if ( typeof(xml) == 'string' ) {
			this.error({'responseText': xml});
			return false;
		}
		
		response = wpAjax.parseAjaxResponse(xml);
		if ( response.errors ) {
			this.error({'responseText': wpAjax.broken});
			return false;
		}
		
		response = response.responses[0];
		comment = response.data;
		supplemental = response.supplemental;
		
		jQuery(comment).hide()
		
		if(response.action.indexOf('reply') == -1 || !workflow_thread_comments) {
			jQuery('#workflow-comments').append(comment);
            
		} else {          
			if(jQuery('#workflow-replyrow').parent().next().is('ul')) {
				jQuery('#workflow-replyrow').parent().next().append(comment);
                
			} else {
				var newUL = jQuery('<ul></ul>')
					.addClass('children')
					.append(comment)
					;
				jQuery('#workflow-replyrow').parent().after(newUL)
			}
		}
		 
		this.o = id = '#comment-'+response.id;

		this.revert();
		
		jQuery(id)
			.animate( { 'backgroundColor':'#CCEEBB' }, 600 )
			.animate( { 'backgroundColor':'#fff' }, 600 );
			
	},

	error : function(r) {
		jQuery('#workflow-comment_loading').hide();

		if ( r.responseText ) {
			er = r.responseText.replace( /<.[^<>]*?>/g, '' );
		}

		if ( er ) {
			jQuery('#workflow-replysubmit .error').html(er).show();
		}

	}
};
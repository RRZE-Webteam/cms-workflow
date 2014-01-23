<?php

class Workflow_Editorial_Comments extends Workflow_Module {

	const comment_type = 'editorial-comment';
    
    public $module;
	
	public function __construct() {
		global $cms_workflow;
		
		$this->module_url = $this->get_module_url( __FILE__ );

        $content_help_tab = array(
            '<p>'. __('Über die redaktionelle Diskussion können sich die Autoren eines Dokumentes über die weitere Bearbeitung austauschen. Sie können auf dieser Seite auswählen, in welchen Bereichen redaktionelle Kommentare freigegeben werden sollen.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>'. __('So fügen Sie einen redaktionellen Kommentar hinzu:', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<ol>',
            '<li>' . __('Gehen Sie auf ein Dokument in einem freigegebenen Bereich. Bevor Sie einen redaktionellen Kommentar hinzufügen können, muss das Dokument bereits einmal gespeichert worden sein.', CMS_WORKFLOW_TEXTDOMAIN) . '</li>',
            '<li>' . __('Wählen Sie "Kommentar hinzufügen" im Kästchen "Redaktionelle Kommentare" aus (wenn diese Box nicht erscheint, können Sie sie über die Lasche "Optionen einblenden" in der rechten oberen Ecke anzeigen lassen).', CMS_WORKFLOW_TEXTDOMAIN) . '</li>',
            '<li>' . __('Geben Sie Ihren Kommentar ein und speichern diesen mit "Senden".', CMS_WORKFLOW_TEXTDOMAIN) . '</li>',
            '</ol>',
            '<p>'. __('Abhängig von den eingestellten Rechten können die Autoren eines Beitrags auf einen Kommentar antworten oder einen neuen Kommentar hinzufügen.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>' 
        );    
        
        /*Kontexthilfe, einzubinden in der Kommentare-Hilfe über 
         * (nicht über load-, sondern über admin_head-, sonst erscheint der neue Hilfe-Tab als erstes!)    
         *  
         * add_action( 'admin_head-edit-comments.php', array( __CLASS__, 'add_comment_help_tab'));     
        * 
        * 
        */
        $context_help_tab = array(
            '<p>Editorial Comments</p>'
        );
        

        $args = array(
			'title' => __( 'Redaktionelle Diskussion', CMS_WORKFLOW_TEXTDOMAIN ),
			'description' => __( 'Redaktionelle Kommentare bzgl. einer Dokumentenbearbeitung.', CMS_WORKFLOW_TEXTDOMAIN ),
			'module_url' => $this->module_url,
			'slug' => 'editorial-comments',
			'default_options' => array(
				'post_types' => array(
					'post' => true,
					'page' => true,
				),
			),
			'configure_callback' => 'print_configure_view',
			'configure_link_text' => __( 'Wählen Sie &bdquo;Post Types&rdquo;', CMS_WORKFLOW_TEXTDOMAIN ),
			'settings_help_tab' => array(
				'id' => 'workflow-editorial-comments-overview',
				'title' => __('Übersicht', CMS_WORKFLOW_TEXTDOMAIN),
				'content' => implode(PHP_EOL, $content_help_tab),
				),
			'settings_help_sidebar' => __( '<p><strong>Für mehr Information:</strong></p><p><a href="http://blogs.fau.de/cms">Dokumentation</a></p><p><a href="http://blogs.fau.de/webworking">RRZE-Webworking</a></p><p><a href="https://github.com/RRZE-Webteam">RRZE-Webteam in Github</a></p>', CMS_WORKFLOW_TEXTDOMAIN ),
                        'context_page' => array('edit-comments'),
                        'context_help_tab' => array(
                            'id' => 'workflow-editorial-comments-context',
                            'title' => __('Redaktionelle Kommentare', CMS_WORKFLOW_TEXTDOMAIN),
                            'content' => implode(PHP_EOL, $context_help_tab),
                        ),     
		);
        
		$this->module = $cms_workflow->register_module( 'editorial_comments', $args );
	}

	public function init() {
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        
		add_action( 'add_meta_boxes', array ( $this, 'add_post_meta_box' ) );		
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'wp_ajax_workflow_ajax_insert_comment', array( $this, 'ajax_insert_comment' ) );
        
        add_filter( 'admin_comment_types_dropdown', array( $this, 'editorial_comments_dropdown' ) );
        
        add_filter( 'manage_edit-comments_columns', array( $this, 'comments_columns' ) );
        add_filter( 'manage_comments_custom_column', array( $this, 'comments_custom_column' ), 10 );
        add_filter( 'comment_row_actions', array( $this, 'comment_meta_row_action' ), 11, 1 );
	}

	public function admin_enqueue_scripts() {
        global $pagenow;

		if ( !in_array( $pagenow, array( 'post.php', 'page.php', 'post-new.php', 'page-new.php', 'edit-comments.php' ) ) )
			return;
		                
		$post_type = $this->get_current_post_type();
        
		if ( !$this->is_post_type_enabled($post_type))
			return;
			 
        wp_enqueue_style( 'workflow-editorial-comments', $this->module_url . 'editorial-comments.css', false, CMS_WORKFLOW_VERSION, 'all' );        
		wp_enqueue_script( 'workflow-editorial-comments', $this->module_url . 'editorial-comments.js', array( 'jquery','post' ), CMS_WORKFLOW_VERSION, true );
				
		$thread_comments = (int) get_option('thread_comments');
		?>
		<script type="text/javascript">
			var workflow_thread_comments = <?php echo ($thread_comments) ? $thread_comments : 0; ?>;
		</script>
		<?php
        
	}
	
    public function comments_columns( $columns ) {
        $position = array_search('response', array_keys($columns));
        if($position !== false)
            $columns = array_slice( $columns, 0, $position, true) + array( 'comment_type' => '') + array_slice($columns, $position, count($columns) - $position, true);
        
        $columns['comment_type'] = __( 'Art', CMS_WORKFLOW_VERSION );
        
        return $columns;
    }

    public function comments_custom_column( $column ) {
        global $comment;
        if ($column == 'comment_type') {
            switch ($comment->comment_type) {
                case self::comment_type: 
                    _e( 'Redaktionelle Diskussion', CMS_WORKFLOW_VERSION );
                    break;
                case 'pings':
                    _e( 'Pings', CMS_WORKFLOW_VERSION );
                    break;
                default:
                    _e( 'Standard', CMS_WORKFLOW_VERSION );
            }
        }        
    }

    public function comment_meta_row_action( $action ) {
        global $comment;
        
        if($comment->comment_type == self::comment_type) {
            unset($action['edit']);
            unset($action['reply']);
            unset($action['quickedit']);
            unset($action['approve']);
            unset($action['unapprove']);
            unset($action['spam']);
            unset($action['unspam']);
        }
        
        return $action;
    }
    
    public function editorial_comments_dropdown( $types ) {
        $position = array_search('comment', array_keys($types));
        if($position !== false) {
            $position++;
            $types = array_slice( $types, 0, $position, true) + array( 'editorial-comment' => '') + array_slice($types, $position, count($types) - $position, true);            
        }        
        $types['editorial-comment'] = __( 'Redaktionelle Diskussion', CMS_WORKFLOW_TEXTDOMAIN );
        return $types;
    }

	public function add_post_meta_box() {
        global $post;
        
		$post_type = $this->get_current_post_type();
        
		if ( !$this->is_post_type_enabled($post_type))
			return;
        
        add_action('pre_get_comments', array( $this, 'display_comments_only' ));
        
        add_meta_box('workflow-editorial-comments', __('Redaktionelle Kommentare', CMS_WORKFLOW_TEXTDOMAIN), array($this, 'editorial_comments_meta_box'), $post_type, 'normal' );
	}

    public function display_comments_only($query) {       
        $query->query_vars['type'] = 'comment';
    }
        
	public function get_editorial_comment_count( $id ) {
		global $wpdb; 
		$comment_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_type = %s", $id, self::comment_type));
		if ( !$comment_count )
			$comment_count = 0;
        
		return $comment_count;
	}
	
	public function editorial_comments_meta_box() {
		global $post;
		?>
		<div id="workflow-comments_wrapper">
			<a name="editorial-comments"></a>
			
			<?php
			if( ! in_array( $post->post_status, array( 'new', 'auto-draft' ) ) ) :
				
				$editorial_comments = self::workflow_get_comments_plus (
                    array(
                        'post_id' => $post->ID,
                        'comment_type' => self::comment_type,
                        'orderby' => 'comment_date',
                        'order' => 'ASC'
                    )
                );
				?>
					
				<ul id="workflow-comments">
					<?php
						wp_list_comments(
							array(
								'type' => self::comment_type,
								'callback' => array($this, 'the_comment'),
							), 
							$editorial_comments
						);
					?>
				</ul>
				
				<?php $this->the_comment_form(); ?>
				
			<?php
			else :
			?>
				<p><?php _e( 'Sie können redaktionelle Kommentare zu einem Beitrag hinzufügen, sobald Sie diesen gespeichert haben.', CMS_WORKFLOW_TEXTDOMAIN ); ?></p>
			<?php
			endif;
			?>
			<div class="clear"></div>
		</div>
		<div class="clear"></div>
		<?php
	}
	
	public function the_comment_form( ) {
		global $post;
		
		?>
		<a href="#" id="workflow-comment_respond" onclick="editorialCommentReply.open();return false;" class="button-primary alignright hide-if-no-js" title=" <?php _e( 'Kommentar hinzufügen', CMS_WORKFLOW_TEXTDOMAIN ); ?>"><span><?php _e( 'Kommentar hinzufügen', CMS_WORKFLOW_TEXTDOMAIN ); ?></span></a>
		
		<div id="workflow-replyrow" style="display: none;">
			<div id="workflow-replycontainer">
				<textarea id="workflow-replycontent" name="replycontent" cols="40" rows="5"></textarea>
			</div>
		
			<p id="workflow-replysubmit">
				<a class="workflow-replysave button-primary alignright" href="#comments-form">
					<span id="workflow-replybtn"><?php _e('Senden', CMS_WORKFLOW_TEXTDOMAIN) ?></span>
				</a>
				<a class="workflow-replycancel button-secondary alignright" href="#comments-form"><?php _e( 'Abbrechen', CMS_WORKFLOW_TEXTDOMAIN ); ?></a>
				<img alt="Kommentar hinzufügen" src="<?php echo admin_url('images/wpspin_light.gif') ?>" class="alignright" style="display: none;" id="workflow-comment_loading" />
				<br class="clear" style="margin-bottom:35px;" />
				<span style="display: none;" class="error"></span>
			</p>
		
			<input type="hidden" value="" id="workflow-comment_parent" name="workflow-comment_parent" />
			<input type="hidden" name="workflow-post_id" id="workflow-post_id" value="<?php echo esc_attr( $post->ID ); ?>" />
			
			<?php wp_nonce_field('comment', 'workflow_comment_nonce', false); ?>
			
			<br class="clear" />
		</div>

		<?php
	}
	
	public function the_comment($comment, $args, $depth) {
		global $current_user, $userdata;
		
		get_currentuserinfo() ;
		
		$GLOBALS['comment'] = $comment;
	
		$actions = array();
	
		$actions_string = '';

        if ( current_user_can('edit_post', $comment->comment_post_ID) ) {
			$actions['reply'] = '<a onclick="editorialCommentReply.open(\''.$comment->comment_ID.'\',\''.$comment->comment_post_ID.'\');return false;" class="vim-r hide-if-no-js" title="'.__( 'Antworten', CMS_WORKFLOW_TEXTDOMAIN ).'" href="#">' . __( 'Antworten', CMS_WORKFLOW_TEXTDOMAIN ) . '</a>';
			
			$sep = ' ';
			$i = 0;
			foreach ( $actions as $action => $link ) {
				++$i;
				if ( 'reply' == $action || 'quickedit' == $action )
					$action .= ' hide-if-no-js';
	
				$actions_string .= "<span class='$action'>$sep$link</span>";
			}
		}
	
	?>

		<li id="comment-<?php echo esc_attr( $comment->comment_ID ); ?>" <?php comment_class( array( 'comment-item', wp_get_comment_status($comment->comment_ID) ) ); ?>>
		
			<?php echo get_avatar( $comment->comment_author_email, 50 ); ?>

			<div class="post-comment-wrap">
				<h5 class="comment-meta">
					<?php printf( __('<span class="comment-author">%1$s</span><span class="meta"> am %2$s um %3$s Uhr</span>', CMS_WORKFLOW_TEXTDOMAIN),
							comment_author_email_link( $comment->comment_author ),
							get_comment_date( get_option( 'date_format' ) ),
							get_comment_time() ); ?>
				</h5>
	
				<div class="comment-content"><?php comment_text(); ?></div>
				<div class="row-actions"><?php echo $actions_string; ?></div>
	
			</div>
		</li>	
		<?php
	}
		
	public function ajax_insert_comment( ) {
		global $current_user, $user_ID, $wpdb;
		
		if ( !wp_verify_nonce( $_POST['_nonce'], 'comment') )
			die( __( "Bitte stellen Sie sicher, dass Sie Kommentare hinzufügen dürfen.", CMS_WORKFLOW_TEXTDOMAIN ) );
		
      	get_currentuserinfo();
      	
		$post_id = absint( $_POST['post_id'] );
		$parent = absint( $_POST['parent'] );
      	
		if ( ! current_user_can( 'edit_post', $post_id ) )
			die( __('Sie haben nicht die erforderlichen Rechte, um diese Aktion durchzuführen.', CMS_WORKFLOW_TEXTDOMAIN ) );
		
		$comment_content = trim($_POST['content']);
		if( !$comment_content )
			die( __( "Bitte geben Sie einen Kommentar ein.", CMS_WORKFLOW_TEXTDOMAIN ) );
		
		if( $post_id && $current_user ) {
			
			$time = current_time('mysql', $gmt = 0); 
			
			$data = array(
			    'comment_post_ID' => (int) $post_id,
			    'comment_author' => esc_sql($current_user->display_name),
			    'comment_author_email' => esc_sql($current_user->user_email),
			    'comment_author_url' => esc_sql($current_user->user_url),
			    'comment_content' => wp_kses($comment_content, array('a' => array('href' => array(),'title' => array()),'b' => array(),'i' => array(),'strong' => array(),'em' => array(),'u' => array(),'del' => array(), 'blockquote' => array(), 'sub' => array(), 'sup' => array() )),
			    'comment_type' => self::comment_type,
			    'comment_parent' => (int) $parent,
			    'user_id' => (int) $user_ID,
			    'comment_author_IP' => esc_sql($_SERVER['REMOTE_ADDR']),
			    'comment_agent' => esc_sql($_SERVER['HTTP_USER_AGENT']),
			    'comment_date' => $time,
			    'comment_date_gmt' => $time,
			    'comment_approved' => 1,
			);
			
			$comment_id = wp_insert_comment($data);
			$comment = get_comment($comment_id);
			
			if ( $comment_id )
				do_action( 'workflow_editorial_comments_new_comment', $comment );

			$response = new WP_Ajax_Response();
			
			ob_start();
            
			$this->the_comment( $comment, '', '' );
			$comment_list_item = ob_get_contents();
            
			ob_end_clean();
			
			$response->add( array(
				'what' => 'comment',
				'id' => $comment_id,
				'data' => $comment_list_item,
				'action' => ($parent) ? 'reply' : 'new'
			));
		
			$response->send();
						
		} else {
			die( __('Ein unerwarteter Fehler ist aufgetreten. Bitte versuchen Sie es erneut.', CMS_WORKFLOW_TEXTDOMAIN) );
		}
	}
	
	public function register_settings() {
			add_settings_section( $this->module->workflow_options_name . '_general', false, '__return_false', $this->module->workflow_options_name );
			add_settings_field( 'post_types', __( 'Freigabe', CMS_WORKFLOW_TEXTDOMAIN ), array( $this, 'settings_post_types_option' ), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general' );
	}

	public function settings_post_types_option() {
		global $cms_workflow;
		$cms_workflow->settings->custom_post_type_option( $this->module );	
	}

	public function settings_validate( $new_options ) {

		if ( !isset( $new_options['post_types'] ) )
			$new_options['post_types'] = array();
		$new_options['post_types'] = $this->clean_post_type_options( $new_options['post_types'], $this->module->post_type_support );
		
		return $new_options;

	}	

	public function print_configure_view() {
		?>

		<form class="basic-settings" action="<?php echo add_query_arg( 'page', $this->module->settings_slug, get_admin_url( null, 'admin.php' ) ); ?>" method="post">
			<?php settings_fields( $this->module->workflow_options_name ); ?>
			<?php do_settings_sections( $this->module->workflow_options_name ); ?>
			<?php
				echo '<input id="cms_workflow_module_name" name="cms_workflow_module_name" type="hidden" value="' . esc_attr( $this->module->name ) . '" />';				
			?>
			<p class="submit"><?php submit_button( null, 'primary', 'submit', false ); ?></p>

		</form>
		<?php
	}

    public static function workflow_get_comments_plus( $args = '' ) {
        global $wpdb;

        $defaults = array( 
            'author_email' => '', 
            'ID' => '', 
            'karma' => '', 
            'number' => '',  
            'offset' => '',  
            'orderby' => '',  
            'order' => 'DESC',  
            'parent' => '', 
            'post_ID' => '', 
            'post_id' => 0, 
            'status' => '',  
            'type' => '', 
            'user_id' => '', 
        ); 

        $args = wp_parse_args( $args, $defaults );
        extract( $args, EXTR_SKIP );

        $key = md5( serialize( compact(array_keys($defaults)) )  );
        $last_changed = wp_cache_get('last_changed', 'comment');
        if ( !$last_changed ) {
            $last_changed = time();
            wp_cache_set('last_changed', $last_changed, 'comment');
        }
        $cache_key = "get_comments:$key:$last_changed";

        if ( $cache = wp_cache_get( $cache_key, 'comment' ) ) {
            return $cache;
        }

        $post_id = absint($post_id);

        if ( 'hold' == $status )
            $approved = "comment_approved = '0'";
        elseif ( 'approve' == $status )
            $approved = "comment_approved = '1'";
        elseif ( 'spam' == $status )
            $approved = "comment_approved = 'spam'";
        elseif( ! empty( $status ) )
            $approved = $wpdb->prepare( "comment_approved = %s", $status );
        else
            $approved = "( comment_approved = '0' OR comment_approved = '1' )";

        $order = ( $order == 'ASC' ) ? 'ASC' : 'DESC';

        if ( ! empty( $orderby ) ) { 
            $ordersby = is_array($orderby) ? $orderby : preg_split('/[,\s]/', $orderby); 
            $ordersby = array_intersect( 
                $ordersby,  
                array( 
                    'comment_agent', 
                    'comment_approved', 
                    'comment_author', 
                    'comment_author_email', 
                    'comment_author_IP', 
                    'comment_author_url', 
                    'comment_content', 
                    'comment_date', 
                    'comment_date_gmt', 
                    'comment_ID', 
                    'comment_karma', 
                    'comment_parent', 
                    'comment_post_ID', 
                    'comment_type', 
                    'user_id', 
                ) 
            );
            
            $orderby = empty( $ordersby ) ? 'comment_date_gmt' : implode(', ', $ordersby); 
            
        } else { 
                $orderby = 'comment_date_gmt'; 
        } 

        $number = absint($number);
        $offset = absint($offset);

        if ( !empty($number) ) {
            if ( $offset )
                $number = 'LIMIT ' . $offset . ',' . $number;
            else
                $number = 'LIMIT ' . $number;

        } else {
            $number = '';
        }

        $post_where = '';

        if ( ! empty($post_id) )
            $post_where .= $wpdb->prepare( 'comment_post_ID = %d AND ', $post_id ); 
        
        if ( '' !== $author_email )  
                $post_where .= $wpdb->prepare( 'comment_author_email = %s AND ', $author_email ); 
        
        if ( '' !== $karma ) 
                $post_where .= $wpdb->prepare( 'comment_karma = %d AND ', $karma ); 
        
        if ( 'comment' == $type ) 
                $post_where .= "comment_type = '' AND "; 
        
        elseif ( ! empty( $type ) )  
                $post_where .= $wpdb->prepare( 'comment_type = %s AND ', $type ); 
        
        if ( '' !== $parent ) 
                $post_where .= $wpdb->prepare( 'comment_parent = %d AND ', $parent ); 
        
        if ( '' !== $user_id ) 
                $post_where .= $wpdb->prepare( 'user_id = %d AND ', $user_id ); 

        $comments = $wpdb->get_results( "SELECT * FROM $wpdb->comments WHERE $post_where $approved ORDER BY $orderby $order $number" );
        wp_cache_add( $cache_key, $comments, 'comment' );

        return $comments;
    }
    
}

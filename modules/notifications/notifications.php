<?php

class Workflow_Notifications extends Workflow_Module {
	
    public $schedule_notifications = false;
    
	public $module;
	
	public function __construct () {
		global $cms_workflow;
		
		$this->module_url = $this->get_module_url( __FILE__ );
        
		$args = array(
			'title' => __( 'Benachrichtigungen', CMS_WORKFLOW_TEXTDOMAIN ),
			'description' => __( 'Benachrichtigungen auf wichtige Änderungen eines Dokuments.', CMS_WORKFLOW_TEXTDOMAIN ),
			'module_url' => $this->module_url,
			'slug' => 'notifications',
			'default_options' => array(
				'post_types' => array(
					'post' => true,
					'page' => true,
				),
				'always_notify_admin' => false,
			),
			'configure_callback' => 'print_configure_view',
			'settings_help_tab' => array(
				'id' => 'workflow-notifications-overview',
				'title' => __('Übersicht', CMS_WORKFLOW_TEXTDOMAIN),
				'content' => __('<p></p>', CMS_WORKFLOW_TEXTDOMAIN),
				),
			'settings_help_sidebar' => __( '<p><strong>Für mehr Information:</strong></p><p><a href="http://blogs.fau.de/cms">Dokumentation</a></p><p><a href="http://blogs.fau.de/webworking">RRZE-Webworking</a></p><p><a href="https://github.com/RRZE-Webteam">RRZE-Webteam in Github</a></p>', CMS_WORKFLOW_TEXTDOMAIN ),
		);
        
		$this->module = $cms_workflow->register_module( 'notifications', $args );
		
	}
	
	public function init() {

		add_action( 'transition_post_status', array( $this, 'notification_status_change' ), 10, 3 );
		add_action( 'workflow_editorial_comments_new_comment', array( $this, 'notification_editorial_comments') );
        add_action( 'workflow_task_list_new_task', array( $this, 'notification_task_list') );

		add_action( 'workflow_send_scheduled_email', array( $this, 'send_single_email' ), 10, 4 );
		
		add_action( 'admin_init', array( $this, 'register_settings' ) );		
	}
	
	public function notification_status_change( $new_status, $old_status, $post ) {
		global $cms_workflow;

		if ( ! apply_filters( 'workflow_notification_status_change', $new_status, $old_status, $post ) || ! apply_filters( "workflow_notification_{$post->post_type}_status_change", $new_status, $old_status, $post ) )
			return false;
		
		$allowed_post_types = $this->get_post_types( $this->module );
		if ( !in_array( $post->post_type, $allowed_post_types ) )
			return;
		
		$ignored_statuses = apply_filters( 'workflow_notification_ignored_statuses', array( $old_status, 'inherit', 'auto-draft' ), $post->post_type );
		
		if ( !in_array( $new_status, $ignored_statuses ) ) {
			
			$post_id = $post->ID;
			$post_title = $this->draft_or_post_title( $post_id );
			$post_type = get_post_type_object( $post->post_type )->labels->singular_name;

            $current_user = wp_get_current_user();
            
			if( 0 != $current_user->ID ) {
				$current_user_display_name = $current_user->display_name;
				$current_user_email = sprintf( '(%s)', $current_user->user_email );
			} else {
				$current_user_display_name = __( 'CMS-Workflow', CMS_WORKFLOW_TEXTDOMAIN );
				$current_user_email = '';
			}
            
            $authors = array();
            
            if ( $this->module_activated( 'authors' ))
                $authors = $this->get_authors_details( $post_id );
            
            $post_author = get_userdata( $post->post_author );
            $authors[$post->post_author] = sprintf( '%1$s (%2$s)', $post_author->display_name, $post_author->user_email );
            $authors = array_unique( $authors );
            
			$blogname = get_option('blogname');
			
			$body  = '';
							
			if ( $old_status == 'new' || $old_status == 'auto-draft' ) {
				$subject = sprintf( __( '%1$s - Neues Dokument wurde erstellt: "%2$s"', CMS_WORKFLOW_TEXTDOMAIN ), $blogname, $post_title );
				$body .= sprintf( __( 'Das Dokument %1$s „%2$s“ wurde von %3$s %4$s erstellt.', CMS_WORKFLOW_TEXTDOMAIN ), $post_id, $post_title, $current_user->display_name, $current_user->user_email ) . "\r\n";
                
			} else if ( $new_status == 'trash' ) {
				$subject = sprintf( __( '%1$s - Das Dokument „%2$s“ wurde in den Papierkorb verschoben.', CMS_WORKFLOW_TEXTDOMAIN ), $blogname, $post_title );
				$body .= sprintf( __( 'Das Dokument %1$s „%2$s“ wurde von %3$s %4$s in den Papierkorb verschoben.', CMS_WORKFLOW_TEXTDOMAIN ), $post_id, $post_title, $current_user_display_name, $current_user_email ) . "\r\n";
                
			} else if ( $old_status == 'trash' ) {
				$subject = sprintf( __( '%1$s - Das Dokument „%2$s“ wurde aus dem Papierkorb wiederhergestellt.', CMS_WORKFLOW_TEXTDOMAIN ), $blogname, $post_title );
				$body .= sprintf( __( 'Das Dokument %1$s „%2$s“ wurde von %3$s %4$s aus dem Papierkorb wiederhergestellt.', CMS_WORKFLOW_TEXTDOMAIN ), $post_id, $post_title, $current_user_display_name, $current_user_email ) . "\r\n";
                
			} else if ( $new_status == 'future' ) {
				$subject = sprintf( __('%1$s - Das Dokument „%2$s“ wurde zeitlich geplant.'), $blogname, $post_title );
				$body .= sprintf( __( 'Das Dokument %1$s „%2$s“ wurde von %3$s %4$s zeitlich geplant.' ), $post_id, $post_title, $current_user_display_name, $current_user_email ) . "\r\n";
                
			} else if ( $new_status == 'publish' ) {
				$subject = sprintf( __( '%1$s - Das Dokument „%2$s“ wurde veröffentlicht.', CMS_WORKFLOW_TEXTDOMAIN ), $blogname, $post_title );
				$body .= sprintf( __( 'Das Dokument %1$s „%2$s“ wurde von %3$s %4$s veröffentlich.', CMS_WORKFLOW_TEXTDOMAIN ), $post_id, $post_title, $current_user_display_name, $current_user_email ) . "\r\n";
                
			} else if ( $old_status == 'publish' ) {
				$subject = sprintf( __( '%1$s - Das Dokument „%2$s“ wurde unveröffentlicht.', CMS_WORKFLOW_TEXTDOMAIN ), $blogname, $post_title );
				$body .= sprintf( __( 'Das Dokument %1$s „%2$s“ wurde von %3$s %4$s unveröffentlicht.', CMS_WORKFLOW_TEXTDOMAIN ), $post_id, $post_title, $current_user_display_name, $current_user_email ) . "\r\n";
                
			} else {
				$subject = sprintf( __( '%1$s - Der Status des Dokuments „%2$s“ hat sich geändert.', CMS_WORKFLOW_TEXTDOMAIN ), $blogname, $post_title );
				$body .= sprintf( __( 'Der Status des Dokuments %1$s „%2$s“ wurde von %3$s %4$s geändert.', CMS_WORKFLOW_TEXTDOMAIN), $post_id, $post_title, $current_user_display_name, $current_user_email ) . "\r\n";
			}
			
			$body .= sprintf( __( 'Diese Aktion wurde am %1$s um %2$s %3$s ausgeführt.', CMS_WORKFLOW_TEXTDOMAIN ), date_i18n( get_option( 'date_format' ) ), date_i18n( get_option( 'time_format' ) ), get_option( 'timezone_string' ) ) . "\r\n";
			
			$old_status_name = $this->get_post_status_name( $old_status );
			$new_status_name = $this->get_post_status_name( $new_status );
			
			$body .= "\r\n";

			$body .= sprintf( __( '%1$s  >>>  %2$s', CMS_WORKFLOW_TEXTDOMAIN ), $old_status_name, $new_status_name );
			$body .= "\r\n \r\n";
			
			$body .= __( 'Dokumenteinzelheiten', CMS_WORKFLOW_TEXTDOMAIN ) . "\r\n";
			$body .= sprintf( __( 'Titel: %s', CMS_WORKFLOW_TEXTDOMAIN ), $post_title ) . "\r\n";

			$body .= sprintf( _nx( 'Autor: %1$s', 'Autoren: %1$s', count($authors), 'notifications', CMS_WORKFLOW_TEXTDOMAIN ), implode(', ', $authors) ) . "\r\n";
			$body .= sprintf( __( 'Art: %1$s', CMS_WORKFLOW_TEXTDOMAIN ), $post_type ) . "\r\n";
			$body .= sprintf( __( 'Status: %1$s', CMS_WORKFLOW_TEXTDOMAIN ), $new_status_name ) . "\r\n";
            
			$edit_link = htmlspecialchars_decode( get_edit_post_link( $post_id ) );
			if ( $new_status != 'publish' ) {
				$preview_nonce = wp_create_nonce( 'post_preview_' . $post_id );
				$view_link = add_query_arg( array( 'preview' => true, 'preview_id' => $post_id, 'preview_nonce' => $preview_nonce ), get_permalink($post_id) );
			} else {
				$view_link = htmlspecialchars_decode( get_permalink( $post_id ) );
			}
            
			$body .= "\r\n \r\n";
			$body .= __( 'Weitere Aktionen', CMS_WORKFLOW_TEXTDOMAIN ) . "\r\n";
			$body .= sprintf( __( 'Redaktionelles Kommentar hinzufügen: %s', CMS_WORKFLOW_TEXTDOMAIN ), $edit_link . '#editorial-comments/add' ) . "\r\n";
			$body .= sprintf( __( 'Dokument bearbeiten: %s', CMS_WORKFLOW_TEXTDOMAIN ), $edit_link ) . "\r\n";
			$body .= sprintf( __( 'Dokument ansehen: %s', CMS_WORKFLOW_TEXTDOMAIN ), $view_link ) . "\r\n";
				
			$body .= $this->get_notification_footer($post);

			$this->send_email( 'status-change', $post, $subject, $body );
		}
		
	}
	
	public function notification_editorial_comments( $comment ) {
		
		$post = get_post($comment->comment_post_ID);
		
		$allowed_post_types = $this->get_post_types( $this->module );
		if ( !in_array( $post->post_type, $allowed_post_types ) )
			return;		
		
		if ( ! apply_filters( 'workflow_notification_editorial_comment', $comment, $post ) )
			return false;
		
		$user = get_userdata( $post->post_author );
		$current_user = wp_get_current_user();
	
		$post_id = $post->ID;
		$post_type = get_post_type_object( $post->post_type )->labels->singular_name;
		$post_title = $this->draft_or_post_title( $post_id );
	
		$blogname = get_option('blogname');
	
		$subject = sprintf( __( '%1$s - Neues redaktionelles Kommentar zum "%2$s"', CMS_WORKFLOW_TEXTDOMAIN ), $blogname, $post_title );

		$body  = sprintf( __( 'Ein neues redaktionelles Kommentar wurde zum Dokument %1$s „%2$s“ hinzugefügt.', CMS_WORKFLOW_TEXTDOMAIN ), $post_id, $post_title ) . "\r\n\r\n";
		$body .= sprintf( __( '%1$s (%2$s) kommentiert am %3$s um %4$s:', CMS_WORKFLOW_TEXTDOMAIN ), $current_user->display_name, $current_user->user_email, mysql2date(get_option('date_format'), $comment->comment_date), mysql2date(get_option('time_format'), $comment->comment_date) ) . "\r\n";
		$body .= "\r\n" . $comment->comment_content . "\r\n";

		$body .= "\r\n \r\n";
		
		$edit_link = htmlspecialchars_decode( get_edit_post_link( $post_id ) );
		$view_link = htmlspecialchars_decode( get_permalink( $post_id ) );
		
		$body .= "\r\n";
		$body .= __( 'Weitere Aktionen', CMS_WORKFLOW_TEXTDOMAIN ) . "\r\n";
		$body .= sprintf( __( 'Antworten: %s', CMS_WORKFLOW_TEXTDOMAIN ), $edit_link . '#editorial-comments/reply/' . $comment->comment_ID ) . "\r\n";
		$body .= sprintf( __( 'Redaktionelle Komentar hinzufügen: %s', CMS_WORKFLOW_TEXTDOMAIN ), $edit_link . '#editorial-comments/add' ) . "\r\n";
		$body .= sprintf( __( 'Dokument bearbeiten: %s', CMS_WORKFLOW_TEXTDOMAIN ), $edit_link ) . "\r\n";
		$body .= sprintf( __( 'Dokument ansehen: %s', CMS_WORKFLOW_TEXTDOMAIN ), $view_link ) . "\r\n";
		
		$body .= "\r\n" . __( 'Sie können alle redaktionellen Kommentare zu diesem Dukument hier finden: ', CMS_WORKFLOW_TEXTDOMAIN ). "\r\n";		
		$body .= $edit_link . "#editorial-comments" . "\r\n\r\n";
		
		$body .= $this->get_notification_footer($post);
		
		$this->send_email( 'comment', $post, $subject, $body );
	}
	
    public function notification_task_list( $task ) {
                
        $post = get_post( $task['post_id'] );
        
        $post_id = $post->ID;
        
        $post_title = ! empty( $post->post_title ) ? $post->post_title : __( '(Kein Titel)', CMS_WORKFLOW_TEXTDOMAIN );

        $current_user = wp_get_current_user();
        
        $task_author = $task['task_author'];
        if($task_author)
            add_filter('workflow_notification_only_this_user', function($task_author) { return $task_author; });
        
        $blogname = get_option('blogname');
        
        $body  = '';
        
        if( 0 != $current_user->ID ) {
            $current_user_display_name = $current_user->display_name;
            $current_user_email = sprintf( '(%s)', $current_user->user_email );
        } else {
            $current_user_display_name = __( 'CMS-Workflow', CMS_WORKFLOW_TEXTDOMAIN );
            $current_user_email = '';
        }

        $subject = sprintf( __( '%1$s - Neue Aufgabe für „%2$s“ wurde hinzugefügt.', CMS_WORKFLOW_TEXTDOMAIN ), $blogname, $post_title );
        $body .= sprintf( __( 'Eine neue Aufgabe wurde zum Dokument %1$s „%2$s“ hinzugefügt.', CMS_WORKFLOW_TEXTDOMAIN ), $post_id, $post_title, $task['task_title'], $current_user_display_name, $current_user_email ) . "\r\n";

        $body .= sprintf( __( 'Diese Aktion wurde von %1$s %2$s am %3$s um %4$s %5$s ausgeführt.', CMS_WORKFLOW_TEXTDOMAIN ), $current_user->display_name, $current_user->user_email, date_i18n( get_option( 'date_format' ) ), date_i18n( get_option( 'time_format' ) ), get_option( 'timezone_string' ) ) . "\r\n";

        $body .= "\r\n";

        $body .= sprintf( __( 'Aufgabe: %s', CMS_WORKFLOW_TEXTDOMAIN ), $task['task_title'] ) . "\r\n";
        $body .= sprintf( __( 'Priorität: %s', CMS_WORKFLOW_TEXTDOMAIN ), Workflow_Task_List::task_list_get_textual_priority( $task['task_priority'] ) ) . "\r\n";
        $body .= $task['task_description'] . "\r\n";
		
		$edit_link = htmlspecialchars_decode( get_edit_post_link( $post_id ) );
		$view_link = htmlspecialchars_decode( get_permalink( $post_id ) );
		
		$body .= "\r\n";
        $body .= __( 'Weitere Aktionen', CMS_WORKFLOW_TEXTDOMAIN ) . "\r\n";
        $body .= sprintf( __( 'Redaktionelles Kommentar hinzufügen: %s', CMS_WORKFLOW_TEXTDOMAIN ), $edit_link . '#editorial-comments/add' ) . "\r\n";
        $body .= sprintf( __( 'Dokument bearbeiten: %s', CMS_WORKFLOW_TEXTDOMAIN ), $edit_link ) . "\r\n";
        $body .= sprintf( __( 'Dokument ansehen: %s', CMS_WORKFLOW_TEXTDOMAIN ), $view_link ) . "\r\n";
        
		$body .= $this->get_notification_footer($post);
		
		$this->send_email( 'new_task', $post, $subject, $body );
        
    }
    
	public function get_notification_footer( $post ) {
		$body  = "";
		$body .= "\r\n \r\n";
		$body .= sprintf( __( 'Sie erhalten diese E-Mail, weil Sie Autor zum Dokument „%s“ sind.', CMS_WORKFLOW_TEXTDOMAIN ), $this->draft_or_post_title($post->ID ) );
		$body .= "\r\n \r\n";
		$body .= get_option('blogname') ."\r\n". get_bloginfo('url') . "\r\n" . admin_url('/') . "\r\n";
		return $body;
	} 
	
	public function send_email( $action, $post, $subject, $message, $headers = '' ) {
		
        if(empty($headers))
            $headers[] = sprintf('From: %1$s <%2$s>', get_option('blogname'), get_option('admin_email'));
		
        $recipients = $this->get_notification_recipients( $post, true );
		
		if( $recipients && ! is_array( $recipients ) )
			$recipients = explode( ',', $recipients );

		$subject = apply_filters( 'workflow_notification_send_email_subject', $subject, $action, $post );
		$message = apply_filters( 'workflow_notification_send_email_message', $message, $action, $post );
		$headers = apply_filters( 'workflow_notification_send_email_message_headers', $headers, $action, $post );
		
		if( $this->schedule_notifications ) {
			$this->schedule_emails( $recipients, $subject, $message, $headers );
		} else if ( !empty( $recipients ) ) {
			foreach( $recipients as $recipient ) {
				$this->send_single_email( $recipient, $subject, $message, $headers );
			}
		}
	}
	
	public function schedule_emails( $recipients, $subject, $message, $headers = '', $time_offset = 1 ) {
		$recipients = (array) $recipients;
		
		$send_time = time();
		
		foreach( $recipients as $recipient ) {
			wp_schedule_single_event( $send_time, 'workflow_send_scheduled_email', array( $recipient, $subject, $message, $headers ) );
			$send_time += $time_offset;
		}
		
	}
	
	public function send_single_email( $to, $subject, $message, $headers = '' ) {
		wp_mail( $to, $subject, $message, $headers );
	}
	
	private function get_notification_recipients( $post, $string = false ) {
        
		$post_id = $post->ID;
		
		$authors = array();
		$admins = array();
		$recipients = array();

		if( $this->module->options->always_notify_admin )
			$admins[] = get_option('admin_email');
		
        if ( $this->module_activated( 'authors' ))
            $authors = $this->get_authors_emails( $post_id );

        $post_author = get_userdata( $post->post_author );
        $authors[$post->post_author] = $post_author->user_email;
        
        $only_this_user = apply_filters( 'workflow_notification_only_this_user', 0 );
        if ( $only_this_user ) {
            $authors = array();
            $authors[$only_this_user] = $only_this_user;
        }
        
		$recipients = array_merge( $authors, $admins );
		$recipients = array_unique( $recipients );

		foreach( $recipients as $key => $user_email ) {

			if ( empty( $recipients[$key] ) )
				unset( $recipients[$key] );

			if ( apply_filters( 'workflow_notification_email_current_user', false ) === false && wp_get_current_user()->user_email == $user_email )
				unset( $recipients[$key] );
		}
		
		$recipients = apply_filters( 'workflow_notification_recipients', $recipients, $post, $string );

		if ( $string && is_array( $recipients ) ) {
			return implode( ',', $recipients );
		} else {
			return $recipients;
		}
	}
		
    private function get_authors_emails($post_id) {
        $users = Workflow_Authors::get_authors( $post_id, 'user_email' );
        if( !$users)
            return array();
                
        return $users;
        
    }
    
    private function get_authors_details($post_id) {
        $users = Workflow_Authors::get_authors( $post_id );
        if( !$users)
            return array();
        
		foreach($users as $key => $user ) {
            $users[$key] = sprintf( '%1$s (%2$s)', $user->display_name, $user->user_email );        
		}
        
        return $users;
        
    }
    			
	public function register_settings() {
			add_settings_section( $this->module->workflow_options_name . '_general', false, '__return_false', $this->module->workflow_options_name );
			add_settings_field( 'post_types', __( 'Freigabe', CMS_WORKFLOW_TEXTDOMAIN ), array( $this, 'settings_post_types_option' ), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general' );
			add_settings_field( 'always_notify_admin', __( 'Administrator benachrichtigen?', CMS_WORKFLOW_TEXTDOMAIN ), array( $this, 'settings_always_notify_admin_option'), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general' );
	}
	
	public function settings_post_types_option() {
		global $cms_workflow;
		$cms_workflow->settings->custom_post_type_option( $this->module );	
	}

	public function settings_always_notify_admin_option() {
		$options = array(
			false => __( 'Nein', CMS_WORKFLOW_TEXTDOMAIN ),			
			true => __( 'Ja', CMS_WORKFLOW_TEXTDOMAIN ),
		);
		echo '<select id="always_notify_admin" name="' . $this->module->workflow_options_name . '[always_notify_admin]">';
		foreach ( $options as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"';
			echo selected( $this->module->options->always_notify_admin, $value );			
			echo '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	public function settings_validate( $new_options ) {
		
		if ( !isset( $new_options['post_types'] ) )
			$new_options['post_types'] = array();
		$new_options['post_types'] = $this->clean_post_type_options( $new_options['post_types'], $this->module->post_type_support );

		if ( !isset( $new_options['always_notify_admin'] ) || !$new_options['always_notify_admin'] )
			$new_options['always_notify_admin'] = false;
		
		return $new_options;

	}	

	public function print_configure_view() {
		?>
		<form class="basic-settings" action="<?php echo esc_url( menu_page_url( $this->module->settings_slug, false ) ); ?>" method="post">
			<?php settings_fields( $this->module->workflow_options_name ); ?>
			<?php do_settings_sections( $this->module->workflow_options_name ); ?>
			<?php
				echo '<input id="cms_workflow_module_name" name="cms_workflow_module_name" type="hidden" value="' . esc_attr( $this->module->name ) . '" />';				
			?>
			<p class="submit"><?php submit_button( null, 'primary', 'submit', false ); ?></p>
		</form>
		<?php
	}	
	
	private function draft_or_post_title( $post_id = 0 ) {
		$post = get_post( $post_id );
		return ! empty( $post->post_title ) ? $post->post_title : __( '(Kein Titel)', CMS_WORKFLOW_TEXTDOMAIN );
	}
    
}

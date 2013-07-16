<?php

class Workflow_Dashboard extends Workflow_Module {

    public $module;
    
    public $allowed_post_type = array();
    
    public function __construct() {
		global $cms_workflow;
		
		$this->module_url = $this->get_module_url( __FILE__ );
        
		$args = array(
			'title' => __( 'Dashboard', CMS_WORKFLOW_TEXTDOMAIN ),
			'description' => __( 'Inhalte aus dem Dashboard verfolgen.', CMS_WORKFLOW_TEXTDOMAIN ),
			'module_url' => $this->module_url,
			'slug' => 'dashboard',
			'default_options' => array(
				'recent_drafts_widget' => true,
                'recent_pending_widget' => true,
                'task_list_widget' => true,
			),
			'configure_callback' => 'print_configure_view',
			'settings_help_tab' => array(
				'id' => 'workflow-dashboard-overview',
				'title' => __('Übersicht', CMS_WORKFLOW_TEXTDOMAIN),
				'content' => __('<p></p>', CMS_WORKFLOW_TEXTDOMAIN),
				),
			'settings_help_sidebar' => __( '<p><strong>Für mehr Information:</strong></p><p><a href="http://blogs.fau.de/cms">Dokumentation</a></p><p><a href="http://blogs.fau.de/webworking">RRZE-Webworking</a></p><p><a href="https://github.com/RRZE-Webteam">RRZE-Webteam in Github</a></p>', CMS_WORKFLOW_TEXTDOMAIN ),
		);
        
		$this->module = $cms_workflow->register_module( 'dashboard', $args );
        
	}
	
	public function init() {
        
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );        
        add_action( 'wp_dashboard_setup', array( $this, 'dashboard_setup') );       
		add_action( 'admin_init', array( $this, 'register_settings' ) );
        
	}

	public function admin_enqueue_scripts( ) {
		wp_enqueue_style( 'workflow-dashboard', $this->module_url . 'dashboard.css', false, CMS_WORKFLOW_VERSION, 'all' );
	}
    
	public function dashboard_setup() {
       
        $all_post_types = $this->get_available_post_types();
        foreach($all_post_types as $key => $post_type) {
            if ( current_user_can($post_type->cap->edit_posts) ) 
                $this->allowed_post_types[$key] = $post_type;
        }
        
        remove_meta_box('dashboard_recent_drafts', 'dashboard', 'side');
        remove_meta_box('dashboard_recent_drafts', 'dashboard', 'normal');
                
        if(empty($this->allowed_post_types))
            return;
        
		if ( $this->module->options->recent_drafts_widget )
			wp_add_dashboard_widget( 'workflow-recent-drafts', __( 'Aktuelle Entwürfe', CMS_WORKFLOW_TEXTDOMAIN ), array( $this, 'recent_drafts_widget' ) );

		if ( $this->module->options->recent_pending_widget )
			wp_add_dashboard_widget( 'workflow-pending-drafts', __( 'Aktuelle ausstehende Reviews', CMS_WORKFLOW_TEXTDOMAIN ), array( $this, 'recent_pending_widget' ) );
        
		if ( $this->module_activated( 'task_list' ) && $this->module->options->recent_pending_widget )
			wp_add_dashboard_widget( 'workflow-task-list', __( 'Aufgabenliste', CMS_WORKFLOW_TEXTDOMAIN ), array( $this, 'task_list_widget' ) );
        
	}
    
    public function recent_drafts_widget( $posts = false ) {
        if ( ! $posts ) {
            $posts_query = new WP_Query( array(
                'post_type' => array_keys($this->allowed_post_types),
                'post_status' => 'draft',
                'posts_per_page' => 50,
                'orderby' => 'modified',
                'order' => 'DESC'
            ) );
            
            $posts =& $posts_query->posts;
        }

        $current_user = wp_get_current_user();
        
        if ( $posts && is_array( $posts ) ) {
            $list = array();		
            foreach ( $posts as $post ) {
                
                $post_type = $this->allowed_post_types[$post->post_type];
                
                $authors = array();

                if ( $this->module_activated( 'authors' ))
                    $authors = Workflow_Authors::get_authors( $post->ID, 'id' );

                $authors[$post->post_author] = $post->post_author;
                $authors = array_unique( $authors );

                if( !current_user_can( 'manage_categories' ) && !in_array($current_user->ID, $authors))
                    continue;
                
                $url = get_edit_post_link( $post->ID );
                $title = _draft_or_post_title( $post->ID );
                $last_id = get_post_meta( $post->ID, '_edit_last', true);
                if($last_id)
                    $last_modified = esc_html( get_userdata($last_id)->display_name );
                
                $item  = sprintf('<h4><a href="%1$s">%2$s</a><abbr> (%3$s)</abbr></h4>', $url, esc_html($title), $post_type->labels->singular_name);
                if(isset($last_modified))
                    $item .= sprintf('<abbr>'.__('Zuletzt geändert von <i>%1$s</i> am %2$s um %3$s Uhr', CMS_WORKFLOW_TEXTDOMAIN).'</abbr>', $last_modified, mysql2date(get_option('date_format'), $post->post_modified), mysql2date(get_option('time_format'), $post->post_modified));
                
                $the_content = preg_split( '#\s#', strip_shortcodes(strip_tags( $post->post_content ), 11, PREG_SPLIT_NO_EMPTY ));
                if ( $the_content )
                    $item .= '<p>' . join( ' ', array_slice( $the_content, 0, 10 ) ) . ( 10 < count( $the_content ) ? '&hellip;' : '' ) . '</p>';
                
                $list[] = $item;
            }
    ?>
        <ul class="status-draft">
            <li><?php echo implode( "</li>\n<li>", $list ); ?></li>
        </ul>
    <?php
        } else {
            _e('Zurzeit gibt es keine Entwürfe.', CMS_WORKFLOW_TEXTDOMAIN);
        }
    }

    public function recent_pending_widget( $posts = false ) {
        if ( ! $posts ) {
            $posts_query = new WP_Query( array(
                'post_type' => array_keys($this->allowed_post_types),
                'post_status' => 'pending',
                'posts_per_page' => 50,
                'orderby' => 'modified',
                'order' => 'DESC'
            ) );
            
            $posts =& $posts_query->posts;
        }
        
        $current_user = wp_get_current_user();
        
        if ( $posts && is_array( $posts ) ) {
            $list = array();		
            foreach ( $posts as $post ) {
                
                $post_type = $this->allowed_post_types[$post->post_type];
                
                $authors = array();

                if ( $this->module_activated( 'authors' ))
                    $authors = Workflow_Authors::get_authors( $post->ID, 'id' );

                $authors[$post->post_author] = $post->post_author;
                $authors = array_unique( $authors );

                if( !current_user_can( 'manage_categories' ) && !in_array($current_user->ID, $authors))
                    continue;
                
                $url = get_edit_post_link( $post->ID );
                $title = _draft_or_post_title( $post->ID );
                $last_id = get_post_meta( $post->ID, '_edit_last', true);
                if($last_id)
                    $last_modified = esc_html( get_userdata($last_id)->display_name );
                
                $item  = sprintf('<h4><a href="%1$s">%2$s</a><abbr> (%3$s)</abbr></h4>', $url, esc_html($title), $post_type->labels->singular_name);
                if(isset($last_modified))
                    $item .= sprintf('<abbr>'.__('Zuletzt geändert von <i>%1$s</i> am %2$s um %3$s Uhr', CMS_WORKFLOW_TEXTDOMAIN).'</abbr>', $last_modified, mysql2date(get_option('date_format'), $post->post_modified), mysql2date(get_option('time_format'), $post->post_modified));
                
                $the_content = preg_split( '#\s#', strip_shortcodes(strip_tags( $post->post_content ), 11, PREG_SPLIT_NO_EMPTY ));
                if ( $the_content )
                    $item .= '<p>' . join( ' ', array_slice( $the_content, 0, 10 ) ) . ( 10 < count( $the_content ) ? '&hellip;' : '' ) . '</p>';
                
                $list[] = $item;
            }
    ?>
        <ul class="status-pending">
            <li><?php echo implode( "</li>\n<li>", $list ); ?></li>
        </ul>
    <?php
        } else {
            _e('Zurzeit gibt es keine ausstehenden Reviews.', CMS_WORKFLOW_TEXTDOMAIN);
        }
    }
    
    public function task_list_widget( $posts = false ) {
        if ( ! $posts ) {
            $posts_query = new WP_Query( array(
                'post_type' => array_keys($this->allowed_post_types),
                'meta_key' => Workflow_Task_List::postmeta_key,
                'posts_per_page' => 50
            ) );
            
            $posts =& $posts_query->posts;
        }
       
        $current_user = wp_get_current_user();
        
        if ( $posts && is_array( $posts ) ) {
            
            $tasks = $this->task_list_order($posts);

            $list = array();
            
            foreach($tasks as $value) {
                
                $task = (object)$value['task'];
                
                foreach ( $posts as $post ) {    
                    if($post->ID != $value['post_id'])
                        continue;
                    
                    $post_type = $this->allowed_post_types[$post->post_type];
                    
                    $authors = array();

                    if ( $this->module_activated( 'authors' ))
                        $authors = Workflow_Authors::get_authors( $post->ID, 'id' );

                    $authors[$post->post_author] = $post->post_author;
                    $authors = array_unique( $authors );

                    if( !current_user_can( 'manage_categories' )&& !in_array($current_user->ID, $authors))
                        continue;

                    $url = get_edit_post_link( $post->ID );
                    $title = _draft_or_post_title( $post->ID );
                    
                    $task_adder = get_userdata( $task->task_adder )->display_name;
                    
                    $item  = sprintf('<li class="priority-%s">', $task->task_priority);
                    $item .= sprintf('<h4><a href="%1$s">%2$s</a><abbr> (%3$s)</abbr></h4>', $url, esc_html($task->task_title), Workflow_Task_List::task_list_get_textual_priority( $task->task_priority ) );
                    $item .= sprintf('<abbr>'.__('Aufgabe hinzugefügt von <i>%1$s</i> am %2$s um %3$s Uhr', CMS_WORKFLOW_TEXTDOMAIN).'</abbr>', $task_adder, date_i18n(get_option('date_format'), $task->task_timestamp), date_i18n(get_option('time_format'), $task->task_timestamp));
                    $item .= sprintf('<p>%1$s: %2$s</p>', $post_type->labels->singular_name, esc_html($title));
                    $item .= '</li>';
                    
                    $list[] = $item;
                        

                }
            }
            
    ?>
        <ul>
            <?php echo implode( "\n", $list ); ?>
        </ul>
    <?php
        } else {
            _e('Zurzeit gibt es keine Aufgaben.', CMS_WORKFLOW_TEXTDOMAIN);
        }
    }
    
    private function task_list_order( &$posts ) {
                
        $priority = array();
        $timestamp = array();
        $task_id = array();
        $post_id = array();
        $task = array();
        
        foreach ( $posts as $post ) {
            
            $data = get_post_meta($post->ID, Workflow_Task_List::postmeta_key );
            $data = json_decode(json_encode($data), false);
            
            foreach( $data as $value ) {  
                if(empty($value->task_done)) {
                    $priority[] = $value->task_priority;
                    $timestamp[] = $value->task_timestamp; 
                    $task_id[] = $value->task_id;
                    $post_id[$value->task_id] = $post->ID;
                    $task[$value->task_id] = $value;
                }
            }

        }
        
        array_multisort($priority, SORT_DESC, $timestamp, SORT_ASC, $task_id, SORT_ASC);
        
        $tasks = array();
        foreach($task_id as $key => $value) {
            $tasks[$value] = array('priority' => $priority[$key], 'timestamp' => $timestamp[$key], 'post_id' => $post_id[$value], 'task' => $task[$value]);
        }
                
        return $tasks;
    }
    
	public function register_settings() {
			add_settings_section( $this->module->workflow_options_name . '_general', false, '__return_false', $this->module->workflow_options_name );
			add_settings_field( 'recent_drafts_widget', __( 'Aktuelle Entwürfe', CMS_WORKFLOW_TEXTDOMAIN ), array( $this, 'settings_recent_drafts_option' ), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general' );
			add_settings_field( 'recent_pending_widget', __( 'Aktuelle ausstehende Reviews', CMS_WORKFLOW_TEXTDOMAIN ), array( $this, 'settings_recent_pending_option' ), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general' );            
			
            if($this->module_activated( 'task_list' ))
                add_settings_field( 'task_list_widget', __( 'Aufgabenliste', CMS_WORKFLOW_TEXTDOMAIN ), array( $this, 'settings_task_list_option' ), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general' );                        
	}
	
	public function settings_recent_drafts_option() {
		$options = array(
			false => __( 'Deaktiviert', CMS_WORKFLOW_TEXTDOMAIN ),			
			true => __( 'Aktiviert', CMS_WORKFLOW_TEXTDOMAIN ),
		);
		echo '<select id="recent_drafts_widget" name="' . $this->module->workflow_options_name . '[recent_drafts_widget]">';
		foreach ( $options as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"';
			echo selected( $this->module->options->recent_drafts_widget, $value );			
			echo '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	public function settings_recent_pending_option() {
		$options = array(
			false => __( 'Deaktiviert', CMS_WORKFLOW_TEXTDOMAIN ),			
			true => __( 'Aktiviert', CMS_WORKFLOW_TEXTDOMAIN ),
		);
		echo '<select id="recent_pending_widget" name="' . $this->module->workflow_options_name . '[recent_pending_widget]">';
		foreach ( $options as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"';
			echo selected( $this->module->options->recent_pending_widget, $value );			
			echo '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}
    
	public function settings_task_list_option() {
		$options = array(
			false => __( 'Deaktiviert', CMS_WORKFLOW_TEXTDOMAIN ),			
			true => __( 'Aktiviert', CMS_WORKFLOW_TEXTDOMAIN ),
		);
		echo '<select id="task_list_widget" name="' . $this->module->workflow_options_name . '[task_list_widget]">';
		foreach ( $options as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"';
			echo selected( $this->module->options->task_list_widget, $value );			
			echo '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}
    
	public function settings_validate( $new_options ) {
		
		if ( array_key_exists( 'recent_drafts_widget', $new_options ) && !$new_options['recent_drafts_widget'] )
			$new_options['recent_drafts_widget'] = false;

		if ( array_key_exists( 'recent_pending_widget', $new_options ) && !$new_options['recent_pending_widget'] )
			$new_options['recent_pending_widget'] = false;

		if ( array_key_exists( 'task_list_widget', $new_options ) && !$new_options['task_list_widget'] )
			$new_options['task_list_widget'] = false;
        
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
    
    
}
    
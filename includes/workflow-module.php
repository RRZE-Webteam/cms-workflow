<?php
	
class Workflow_Module {
    
	public function module_activated( $slug ) {
        global $cms_workflow;
        
		return isset( $cms_workflow->$slug ) && $cms_workflow->$slug->module->options->activated;
	}

	public function clean_post_type_options( $module_post_types = array(), $post_type_support = null ) {
		$normalized_post_type_options = array();
		$custom_post_types = wp_list_pluck( $this->get_custom_post_types(), 'name' );
		
        array_push( $custom_post_types, 'post', 'page' );
		
        $all_post_types = array_merge( $custom_post_types, array_keys( $module_post_types ) );
		
        foreach( $all_post_types as $post_type ) {
            
			if ( ( isset( $module_post_types[$post_type] ) && $module_post_types[$post_type] ) || post_type_supports( $post_type, $post_type_support ) )
				$normalized_post_type_options[$post_type] = true;
            
			else
				$normalized_post_type_options[$post_type] = false;
		}
		return $normalized_post_type_options;
	}

	public function get_buildin_post_types() {

		$args = array(
            '_builtin' => true,
            'public' => true,
        );
        
		return get_post_types( $args, 'objects' );
	}
    
	public function get_custom_post_types() {

		$args = array(
            '_builtin' => false,
            'public' => true,
        );
        
		return get_post_types( $args, 'objects' );
	}
	
	public function get_post_types( $module ) {
		
		$post_types = array();
		if ( isset( $module->options->post_types ) && is_array( $module->options->post_types ) ) {
			foreach( $module->options->post_types as $post_type => $value )
				if ( $value )
					$post_types[] = $post_type;
		}
		return $post_types;
	}
		
	public function get_post_status_name( $status ) {
        global $cms_workflow;
        
		$status_friendly_name = '';
		
		$builtin_status = array(
			'publish' => __( 'Veröffentlicht', CMS_WORKFLOW_TEXTDOMAIN ),
			'draft' => __( 'Entwurf', CMS_WORKFLOW_TEXTDOMAIN ),
			'future' => __( 'Geplant', CMS_WORKFLOW_TEXTDOMAIN ),
			'private' => __( 'Privat', CMS_WORKFLOW_TEXTDOMAIN ),
			'pending' => __( 'Austehender Review', CMS_WORKFLOW_TEXTDOMAIN ),
			'trash' => __( 'Papierkorb', CMS_WORKFLOW_TEXTDOMAIN ),
		);
		
		if ( array_key_exists( $status, $builtin_status ) )
			$status_friendly_name = $builtin_status[$status];
        
		return $status_friendly_name;
	}
		
    public function get_available_post_types($post_type = null) {
        $all_post_types = array();
        
		$buildin_post_types = $this->get_buildin_post_types(); 
        $all_post_types['post'] = $buildin_post_types['post'];
        $all_post_types['page'] = $buildin_post_types['page'];
        
        $custom_post_types = $this->get_custom_post_types();
		if ( count( $custom_post_types ) ) {
			foreach( $custom_post_types as $custom_post_type => $args ) {
				$all_post_types[$custom_post_type] = $args;
			}
		}    
        
        if(is_null($post_type))
            return $all_post_types;
        
        else
            return $all_post_types[$post_type];
        
    }
        
    public function get_post_type_labels() {
        global $wp_post_types;
        
        $post_type_name = get_current_screen()->post_type;
        $labels = &$wp_post_types[$post_type_name]->labels;
        
        return $labels;
    }

	public function get_current_post_type() {
		global $post, $typenow, $pagenow, $current_screen;

		$post_int;
		if( isset( $_REQUEST['post'] ) )
			$post_int = (int)$_REQUEST['post'];

		if ( $post && $post->post_type )
			$post_type = $post->post_type;
        
		elseif ( $typenow )
			$post_type = $typenow;
        
		elseif ( $current_screen && isset( $current_screen->post_type ) )
			$post_type = $current_screen->post_type;
        
		elseif ( isset( $_REQUEST['post_type'] ) )
			$post_type = sanitize_key( $_REQUEST['post_type'] );
        
		elseif ( 'post.php' == $pagenow && isset( $_REQUEST['post'] ) && isset( $post_int ) && ! empty( get_post( $post_int )->post_type ) )
			$post_type = get_post( $post_int )->post_type;
        
		elseif ( 'edit.php' == $pagenow && empty( $_REQUEST['post_type'] ) )
			$post_type = 'post';
        
		else
			$post_type = null;

		return $post_type;
	}
			
	public function is_post_type_enabled( $post_type = null ) {

        $allowed_post_types = $this->get_post_types( $this->module );
        
		if ( ! $post_type )
			$post_type = get_post_type();

		return (bool) in_array( $post_type, $allowed_post_types );
	}
    
	public function get_encoded_description( $args = array() ) {
		return base64_encode( maybe_serialize( $args ) );
	}
	
	public function get_unencoded_description( $string_to_unencode ) {
		return maybe_unserialize( base64_decode( $string_to_unencode ) );
	}
	
	public function get_module_url( $file ) {
		$module_url = plugins_url( '/', $file );
		return trailingslashit( $module_url );
	}
		
	public function users_select_form( $selected = null, $args = null ) {

		$defaults = array(
			'list_class' => 'workflow-users-select', 
            'list_id' => 'workflow-users-select',
			'input_id' => 'workflow-selected-users',
            'input_name' => 'workflow_selected_users'
		);
		$parsed_args = wp_parse_args( $args, $defaults );
		extract($parsed_args, EXTR_SKIP);

		$args = array(
			'who' => 'contributors',
			'fields' => array(
				'ID',
				'display_name',
				'user_email'
			),
			'orderby' => 'display_name',
		);
        
		$users = get_users( $args );

		if ( !is_array($selected) ) 
            $selected = array();
		?>
		<?php if( count($users) ) : ?>
			<ul class="<?php echo esc_attr( $list_class ) ?>">
				<?php foreach( $users as $user ) : ?>
					<?php $checked = ( in_array($user->ID, $selected) ) ? 'checked="checked"' : ''; ?>
					<li>
						<label for="<?php echo esc_attr( $input_id .'-'. $user->ID ) ?>">
							<input type="checkbox" id="<?php echo esc_attr( $input_id .'-'. $user->ID ) ?>" name="<?php echo esc_attr( $input_name ) ?>[]" value="<?php echo esc_attr( $user->ID ); ?>" <?php echo $checked; ?> />
							<span class="workflow-user-displayname"><?php echo esc_html( $user->display_name ); ?></span>
							<span class="workflow-user-useremail"><?php echo esc_html( $user->user_email ); ?></span>
						</label>
					</li>
				<?php endforeach; ?>
			</ul>
        <?php else: ?>
            <p><?php _e('Kein Benutzer gefunden.', CMS_WORKFLOW_TEXTDOMAIN); ?></p>
        <?php endif; ?>
		
		<?php
	}
    
	public function action_settings_help_menu() {
        
		$screen = get_current_screen();

		if ( !method_exists( $screen, 'add_help_tab' ) ) 
			return;
        
		if ( $screen->id != 'workflow_page_' . $this->module->settings_slug ) 
			return;

		if ( isset( $this->module->settings_help_tab['id'], $this->module->settings_help_tab['title'], $this->module->settings_help_tab['content'] ) ) {
			$screen->add_help_tab( $this->module->settings_help_tab );
		
			if ( isset( $this->module->settings_help_sidebar ) ) {
				$screen->set_help_sidebar( $this->module->settings_help_sidebar );
			}
		}
	}
	
	public function is_settings_view( $module_name = null ) {
		global $pagenow, $cms_workflow;
		
		if ( $pagenow != 'admin.php' || !isset( $_GET['page'] ) )
			return false;
		
		foreach ( $cms_workflow->modules as $mod_name => $mod_data ) {
			if ( $mod_data->options->activated && $mod_data->configure_callback )
				$settings_view_slugs[] = $mod_data->settings_slug;
		}
	
		if ( !in_array( $_GET['page'], $settings_view_slugs ) )
			return false;
		
		if ( $module_name && $cms_workflow->modules->$module_name->settings_slug != $_GET['page'] )
			return false;
			
		return true;
	}
    
}

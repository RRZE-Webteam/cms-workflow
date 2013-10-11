<?php

class Workflow_User_Groups extends Workflow_Module {
		
	const taxonomy_key = 'workflow_usergroup';
    
	const term_prefix = 'workflow-usergroup-';

	public $module;
    
	public function __construct( ) {
		global $cms_workflow;
		
		$this->module_url = $this->get_module_url( __FILE__ );
		
		$args = array(
			'title' => __( 'Benutzergruppen', CMS_WORKFLOW_TEXTDOMAIN ),
			'description' => __( 'Benutzer nach Abteilung oder Funktion organisieren.', CMS_WORKFLOW_TEXTDOMAIN ),
			'module_url' => $this->module_url,
			'slug' => 'user-groups',
			'default_options' => array(
				'post_types' => array(
					'post' => true,
					'page' => true,
				),
			),
			'messages' => array(
				'usergroup-added' => __( 'Die Benutzergruppe wurde erstellt.', CMS_WORKFLOW_TEXTDOMAIN ),
				'usergroup-updated' => __( 'Die Benutzergruppe wurde aktualisiert.', CMS_WORKFLOW_TEXTDOMAIN ),
				'usergroup-missing' => __( 'Die Benutzergruppe existiert nicht.', CMS_WORKFLOW_TEXTDOMAIN ),
				'usergroup-deleted' => __( 'Die Benutzergruppe wurde gelöscht.', CMS_WORKFLOW_TEXTDOMAIN ),				
			),
			'configure_callback' => 'print_configure_view',
			'configure_link_text' => __( 'Gruppen verwalten', CMS_WORKFLOW_TEXTDOMAIN ),
			'settings_help_tab' => array(
				'id' => 'workflow-user-groups-overview',
				'title' => __('Übersicht', CMS_WORKFLOW_TEXTDOMAIN),
				'content' => __('<p></p>', CMS_WORKFLOW_TEXTDOMAIN),
				),
			'settings_help_sidebar' => __( '<p><strong>Für mehr Information:</strong></p><p><a href="http://blogs.fau.de/cms">Dokumentation</a></p><p><a href="http://blogs.fau.de/webworking">RRZE-Webworking</a></p><p><a href="https://github.com/RRZE-Webteam">RRZE-Webteam in Github</a></p>', CMS_WORKFLOW_TEXTDOMAIN ),
		);
        
		$this->module = $cms_workflow->register_module( 'user_groups', $args );
		
	}
	
	public function init() {
		
        $this->register_taxonomies();
        
		add_action( 'admin_init', array( $this, 'register_settings' ) );		
		
		add_action( 'admin_init', array( $this, 'handle_add_usergroup' ) );
		add_action( 'admin_init', array( $this, 'handle_edit_usergroup' ) );
		add_action( 'admin_init', array( $this, 'handle_delete_usergroup' ) );
		add_action( 'wp_ajax_inline_save_usergroup', array( $this, 'handle_ajax_inline_save_usergroup' ) );
	
		add_action( 'show_user_profile', array( $this, 'user_profile_page' ) );
		add_action( 'edit_user_profile', array( $this, 'user_profile_page' ) );
		add_action( 'user_profile_update_errors', array( $this, 'user_profile_update' ), 10, 3 );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );	
		
	}
	    
	public function register_taxonomies() {
		
		$allowed_post_types = $this->get_post_types( $this->module );
		
		$args = array(
			'public' => false,
			'rewrite' => false,
		);
        
		register_taxonomy( self::taxonomy_key, $allowed_post_types, $args );
	}
	
	public function enqueue_admin_scripts() {
        wp_enqueue_script( 'jquery-listfilterizer' );
        wp_enqueue_script( 'workflow-user-groups', $this->module_url . 'user-groups.js', array( 'jquery', 'jquery-listfilterizer' ), CMS_WORKFLOW_VERSION, true );
        
        if($this->is_settings_view())
            wp_enqueue_script( 'workflow-user-groups-inline-edit', $this->module_url . 'inline-edit.js', array( 'jquery' ), CMS_WORKFLOW_VERSION, true );
        
        wp_localize_script( 'workflow-user-groups', 'user_groups_vars', array(
            'filters_label_1'   => __('Alle', CMS_WORKFLOW_TEXTDOMAIN),
            'filters_label_2'   => __('Ausgewählt', CMS_WORKFLOW_TEXTDOMAIN),
            'placeholder'       => __('Suchen...', CMS_WORKFLOW_TEXTDOMAIN),
        ) );
        
	}
	
	public function enqueue_admin_styles() {
        wp_enqueue_style( 'jquery-listfilterizer' );
        wp_enqueue_style( 'workflow-user-groups', $this->module_url . 'user-groups.css', false, CMS_WORKFLOW_VERSION );
	}
	
	public function handle_add_usergroup() {

		if ( !isset( $_POST['submit'], $_POST['form_action'], $_GET['page'] ) 
			|| $_GET['page'] != $this->module->settings_slug || $_POST['form_action'] != 'add-usergroup' )
				return;
				
		if ( !wp_verify_nonce( $_POST['_wpnonce'], 'add-usergroup' ) )
			wp_die( $this->module->messages['nonce-failed'] );
			
		if ( !current_user_can( 'manage_categories' ) )
			wp_die( $this->module->messages['invalid-permissions'] );			
		
		$name = strip_tags( trim( $_POST['name'] ) );
		$description = strip_tags( trim( $_POST['description'] ) );
		
		$_REQUEST['form-errors'] = array();
		
		if ( empty( $name ) )
			$_REQUEST['form-errors']['name'] = __( 'Bitte geben Sie einen Namen für die Benutzergruppe.', CMS_WORKFLOW_TEXTDOMAIN );

		if ( $this->get_usergroup_by( 'name', $name ) )
			$_REQUEST['form-errors']['name'] = __( 'Name wird bereits verwendet. Bitte wählen Sie einen anderen.', CMS_WORKFLOW_TEXTDOMAIN );

		if ( $this->get_usergroup_by( 'slug', sanitize_title( $name ) ) )
			$_REQUEST['form-errors']['name'] = __( 'Name steht in Konflikt mit einem reservierten Namen. Bitte wählen Sie erneut.', CMS_WORKFLOW_TEXTDOMAIN );
		
        if ( strlen( $name ) > 40 )
			$_REQUEST['form-errors']['name'] = __( 'Benutzergruppe Name darf maximal 40 Zeichen lang sein. Bitte versuchen Sie einen kürzeren Namen.', CMS_WORKFLOW_TEXTDOMAIN );			

		if ( count( $_REQUEST['form-errors'] ) ) {
			$_REQUEST['error'] = 'form-error';
			return;
		}

		$args = array(
			'name' => $name,
			'description' => $description,
		);
        
		$usergroup = $this->add_usergroup( $args );
		if ( is_wp_error( $usergroup ) )
			wp_die( __( 'Fehler beim Hinzufügen einer Benutzergruppe.', CMS_WORKFLOW_TEXTDOMAIN ) );

		$args = array(
			'action' => 'edit-usergroup',
			'usergroup-id' => $usergroup->term_id,
			'message' => 'usergroup-added'
		);
		$redirect_url = $this->get_link( $args );
		wp_redirect( $redirect_url );
		exit;
	}
	
	public function handle_edit_usergroup() {
        
		if ( !isset( $_POST['submit'], $_POST['form_action'], $_GET['page'] ) 
			|| $_GET['page'] != $this->module->settings_slug || $_POST['form_action'] != 'edit-usergroup' )
				return;
				
		if ( !wp_verify_nonce( $_POST['_wpnonce'], 'edit-usergroup' ) )
			wp_die( $this->module->messages['nonce-failed'] );
			
		if ( !current_user_can( 'manage_categories' ) )
			wp_die( $this->module->messages['invalid-permissions'] );
			
		if ( !$existing_usergroup = $this->get_usergroup_by( 'id', (int)$_POST['usergroup_id'] ) )
			wp_die( $this->module->messsage['usergroup-error'] );			
		
		$name = strip_tags( trim( $_POST['name'] ) );
		$description = strip_tags( trim( $_POST['description'] ) );
		
		$_REQUEST['form-errors'] = array();
		
		if ( empty( $name ) )
			$_REQUEST['form-errors']['name'] = __( 'Bitte geben Sie einen Namen für die Benutzergruppe.', CMS_WORKFLOW_TEXTDOMAIN );

		$search_term = $this->get_usergroup_by( 'name', $name );
		if ( is_object( $search_term ) && $search_term->term_id != $existing_usergroup->term_id )
			$_REQUEST['form-errors']['name'] = __( 'Name wird bereits verwendet. Bitte wählen Sie einen anderen.', CMS_WORKFLOW_TEXTDOMAIN );

		$search_term = $this->get_usergroup_by( 'slug', sanitize_title( $name ) );
		if ( is_object( $search_term ) && $search_term->term_id != $existing_usergroup->term_id )
			$_REQUEST['form-errors']['name'] = __( 'Name steht in Konflikt mit einem reservierten Namen. Bitte wählen Sie erneut.', CMS_WORKFLOW_TEXTDOMAIN );
        
		if ( strlen( $name ) > 40 )
			$_REQUEST['form-errors']['name'] = __( 'Benutzergruppe Name darf maximal 40 Zeichen lang sein. Bitte versuchen Sie einen kürzeren Namen.', CMS_WORKFLOW_TEXTDOMAIN );			

		if ( count( $_REQUEST['form-errors'] ) ) {
			$_REQUEST['error'] = 'form-error';
			return;
		}

		$args = array(
			'name' => $name,
			'description' => $description,
		);

		$users = isset( $_POST['usergroup_users'] ) ? (array)$_POST['usergroup_users'] : array();
		$users = array_map( 'intval', $users );
        
		$usergroup = $this->update_usergroup( $existing_usergroup->term_id, $args, $users );
		
        if ( is_wp_error( $usergroup ) )
			wp_die( __( 'Fehler beim Aktualisierung der Benutzergruppe.', CMS_WORKFLOW_TEXTDOMAIN ) );

		$args = array(
			'message' => 'usergroup-updated',
		);
		$redirect_url = $this->get_link( $args );
		wp_redirect( $redirect_url );
		exit;
	}
	
	public function handle_delete_usergroup() {
		if ( !isset( $_GET['page'], $_GET['action'], $_GET['usergroup-id'] ) 
			|| $_GET['page'] != $this->module->settings_slug || $_GET['action'] != 'delete-usergroup' )
				return;
				
		if ( !wp_verify_nonce( $_GET['nonce'], 'delete-usergroup' ) )
			wp_die( $this->module->messages['nonce-failed'] );
			
		if ( !current_user_can( 'manage_categories' ) )
			wp_die( $this->module->messages['invalid-permissions'] );			
			
		$result = $this->delete_usergroup( (int)$_GET['usergroup-id'] );
		if ( !$result || is_wp_error( $result ) )
			wp_die( __( 'Fehler beim Löschen der Benutzergruppe.', CMS_WORKFLOW_TEXTDOMAIN ) );
			
		$redirect_url = $this->get_link( array( 'message' => 'usergroup-deleted' ) );
		wp_redirect( $redirect_url );
		exit;		
	}
	
	public function handle_ajax_inline_save_usergroup() {
		
		if ( !wp_verify_nonce( $_POST['inline_edit'], 'usergroups-inline-edit-nonce' ) )
			die( $this->module->messages['nonce-failed'] );
			
		if ( !current_user_can( 'manage_categories' ) )
			die( $this->module->messages['invalid-permissions'] );
		
		$usergroup_id = (int) $_POST['usergroup_id'];
		if ( !$existing_term = $this->get_usergroup_by( 'id', $usergroup_id ) )
			die( $this->module->messsage['usergroup-error'] );
		
		$name = strip_tags( trim( $_POST['name'] ) );
		$description = strip_tags( trim( $_POST['description'] ) );
		
		if ( empty( $name ) ) {
			$change_error = new WP_Error( 'invalid', __( 'Bitte geben Sie einen Namen für die Benutzergruppe.', CMS_WORKFLOW_TEXTDOMAIN ) );
			die( $change_error->get_error_message() );
		}

		$search_term = $this->get_usergroup_by( 'name', $name );
		if ( is_object( $search_term ) && $search_term->term_id != $existing_term->term_id ) {
			$change_error = new WP_Error( 'invalid', __( 'Name wird bereits verwendet. Bitte wählen Sie einen anderen.', CMS_WORKFLOW_TEXTDOMAIN ) );
			die( $change_error->get_error_message() );
		}

		$search_term = $this->get_usergroup_by( 'slug', sanitize_title( $name ) );
		if ( is_object( $search_term ) && $search_term->term_id != $existing_term->term_id ) {
			$change_error = new WP_Error( 'invalid', __( 'Name steht in Konflikt mit einem reservierten Namen. Bitte wählen Sie erneut.', CMS_WORKFLOW_TEXTDOMAIN ) );
			die( $change_error->get_error_message() );
		}
		
		if ( strlen( $name ) > 40 ) {
			$change_error = new WP_Error( 'invalid', __( 'Benutzergruppe Name darf maximal 40 Zeichen lang sein. Bitte versuchen Sie einen kürzeren Namen.', CMS_WORKFLOW_TEXTDOMAIN ) );
			die( $change_error->get_error_message() );
		}
        
		$args = array(
			'name' => $name,
			'description' => $description,
		);
        
		$return = $this->update_usergroup( $existing_term->term_id, $args );
		if( !is_wp_error( $return ) ) {	
			$wp_list_table = new Workflow_Usergroups_List_Table();
			$wp_list_table->prepare_items();
			echo $wp_list_table->single_row( $return );
			die();
		} else {
			$change_error = new WP_Error( 'invalid', sprintf( __( 'Die Benutzergruppe <strong>&bdquo;%s&rdquo;</strong> konnte nicht aktualisiert werden.', CMS_WORKFLOW_TEXTDOMAIN ), $name ) );
			die( $change_error->get_error_message() );
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
		global $cms_workflow;
		
		if ( isset( $_GET['action'], $_GET['usergroup-id'] ) && $_GET['action'] == 'edit-usergroup' ) :
			$usergroup_id = (int)$_GET['usergroup-id'];
			$usergroup = $this->get_usergroup_by( 'id', $usergroup_id );
            
			if ( !$usergroup ) {
				echo '<div class="error"><p>' . $this->module->messages['usergroup-missing'] . '</p></div>';
				return; 
			}
            
			$name = ( isset( $_POST['name'] ) ) ? stripslashes( $_POST['name'] ) : $usergroup->name;
			$description = ( isset( $_POST['description'] ) ) ? stripslashes( $_POST['description'] ) : $usergroup->description;
		?>
		<form method="post" action="<?php echo esc_url( $this->get_link( array( 'action' => 'edit-usergroup', 'usergroup-id' => $usergroup_id ) ) ); ?>">
		<div id="col-right">
            <div class="col-wrap">
                <div id="workflow-usergroup-users" class="wrap">
                    <h4><?php _e( 'Benutzer', CMS_WORKFLOW_TEXTDOMAIN ); ?></h4>
                    <?php 
                        $select_form_args = array(
                            'list_class' => 'workflow-groups-list',
                            'input_id' => 'usergroup-users',
                            'input_name' => 'usergroup_users'
                        );
                    ?>
                    <?php $this->users_select_form( $usergroup->user_ids , $select_form_args ); ?>
                </div>
            </div>
        </div>
		<div id="col-left"><div class="col-wrap"><div class="form-wrap">		
			<input type="hidden" name="form_action" value="edit-usergroup" />
			<input type="hidden" name="usergroup_id" value="<?php echo esc_attr( $usergroup_id ); ?>" />
			<?php
				wp_original_referer_field();
				wp_nonce_field( 'edit-usergroup' );
			?>
			<div class="form-field form-required">
				<label for="name"><?php _e( 'Name', CMS_WORKFLOW_TEXTDOMAIN ); ?></label>
				<input name="name" id="name" type="text" value="<?php echo esc_attr( $name ); ?>" size="40" maxlength="40" aria-required="true" />
				<?php $cms_workflow->settings->print_error_or_description( 'name', __( 'Der Name der Benutzergruppe.', CMS_WORKFLOW_TEXTDOMAIN ) ); ?>
			</div>
			<div class="form-field">
				<label for="description"><?php _e( 'Beschreibung', CMS_WORKFLOW_TEXTDOMAIN ); ?></label>
				<textarea name="description" id="description" rows="5" cols="40"><?php echo esc_html( $description ); ?></textarea>
				<?php $cms_workflow->settings->print_error_or_description( 'description', __( 'Die Beschreibung der Benutzergruppe.', CMS_WORKFLOW_TEXTDOMAIN ) ); ?>
			</div>
			<p class="submit">
    			<?php submit_button( __( 'Aktualisieren', CMS_WORKFLOW_TEXTDOMAIN ), 'primary', 'submit', false ); ?>
			</p>
		</div></div></div>
		</form>
		
		<?php else :
			$wp_list_table = new Workflow_Usergroups_List_Table();
			$wp_list_table->prepare_items();
		?>
			<div id="col-right">
                <div class="col-wrap">
                    <?php $wp_list_table->display(); ?>
                </div>
            </div>
			<div id="col-left"><div class="col-wrap"><div class="form-wrap">
				<h2 class="nav-tab-wrapper">
					<a href="<?php echo esc_url( $this->get_link() ); ?>" class="nav-tab<?php if ( !isset( $_GET['action'] ) || $_GET['action'] != 'change-options' ) echo ' nav-tab-active'; ?>"><?php _e( 'Neuer Benutzergruppe hinzufügen', CMS_WORKFLOW_TEXTDOMAIN ); ?></a>
					<a href="<?php echo esc_url( $this->get_link( array( 'action' => 'change-options' ) ) ); ?>" class="nav-tab<?php if ( isset( $_GET['action'] ) && $_GET['action'] == 'change-options' ) echo ' nav-tab-active'; ?>"><?php _e( 'Einstellungen', CMS_WORKFLOW_TEXTDOMAIN ); ?></a>
				</h2>
				<?php if ( isset( $_GET['action'] ) && $_GET['action'] == 'change-options' ): ?>
				<form class="basic-settings" action="<?php echo esc_url( $this->get_link( array( 'action' => 'change-options' ) ) ); ?>" method="post">
					<?php settings_fields( $this->module->workflow_options_name ); ?>
					<?php do_settings_sections( $this->module->workflow_options_name ); ?>	
					<?php echo '<input id="cms_workflow_module_name" name="cms_workflow_module_name" type="hidden" value="' . esc_attr( $this->module->name ) . '" />'; ?>
					<?php submit_button(); ?>
				</form>
				<?php else: ?>
					<form class="add:the-list:" action="<?php echo esc_url( $this->get_link() ); ?>" method="post" id="addusergroup" name="addusergroup">
					<div class="form-field form-required">
						<label for="name"><?php _e( 'Name', CMS_WORKFLOW_TEXTDOMAIN ); ?></label>
						<input type="text" aria-required="true" id="name" name="name" maxlength="40" value="<?php if ( !empty( $_POST['name'] ) ) echo esc_attr( $_POST['name'] ); ?>" />
						<?php $cms_workflow->settings->print_error_or_description( 'name', __( 'Der Name wird verwendet um die Benutzergruppe zu identifizieren.', CMS_WORKFLOW_TEXTDOMAIN ) ); ?>
					</div>
					<div class="form-field">
						<label for="description"><?php _e( 'Beschreibung', CMS_WORKFLOW_TEXTDOMAIN ); ?></label>
						<textarea cols="40" rows="5" id="description" name="description"><?php if ( !empty( $_POST['description'] ) ) echo esc_html( $_POST['description'] ) ?></textarea>
						<?php $cms_workflow->settings->print_error_or_description( 'description', __( 'Die Beschreibung ist für administrative Zwecke vorhanden.', CMS_WORKFLOW_TEXTDOMAIN ) ); ?>
					</div>
					<?php wp_nonce_field( 'add-usergroup' ); ?>
					<?php echo '<input id="form-action" name="form_action" type="hidden" value="add-usergroup" />'; ?>
					<p class="submit"><?php submit_button( __( 'Neuer Benutzergruppe hinzufügen', CMS_WORKFLOW_TEXTDOMAIN ), 'primary', 'submit', false ); ?></p>
					</form>
				<?php endif; ?>
			</div></div></div>
			<?php $wp_list_table->inline_edit(); ?>
		<?php endif;
	}
	
	public function user_profile_page() {
		global $user_id, $profileuser;
		
		if ( !$user_id || !current_user_can( 'manage_categories' ) )
			return;
		
		$usergroups = $this->get_usergroups();
		$selected_usergroups = $this->get_usergroups_for_user( $user_id );
		$usergroups_form_args = array( 'input_id' => 'workflow-usergroups' );
		?>
		<table id="workflow-user-usergroups" class="form-table"><tbody><tr>
			<th>
				<h3><?php _e( 'Benutzergruppen', CMS_WORKFLOW_TEXTDOMAIN ) ?></h3>
				<?php if ( $user_id === wp_get_current_user()->ID ) : ?>
				<p><?php _e( 'Wählen Sie die Benutzergruppen, die Sie gerne teilnehmen würde:', CMS_WORKFLOW_TEXTDOMAIN ) ?></p>
				<?php else : ?>
				<p><?php _e( 'Wählen Sie die Benutzergruppen, die dieser Benutzer teilnehmen sollte:', CMS_WORKFLOW_TEXTDOMAIN ) ?></p>
				<?php endif; ?>
			</th>
			<td>
				<?php $this->usergroups_select_form( $selected_usergroups, $usergroups_form_args ); ?>
			</td>
		</tr></tbody></table>
		<?php wp_nonce_field( 'workflow_edit_profile_usergroups_nonce', 'workflow_edit_profile_usergroups_nonce' ); ?>
	<?php 
	}
	
	public function user_profile_update( $errors, $update, $user ) {
		
		if ( !$update )
			return array( &$errors, $update, &$user );

		if ( current_user_can( 'manage_categories' ) && wp_verify_nonce( $_POST['workflow_edit_profile_usergroups_nonce'], 'workflow_edit_profile_usergroups_nonce' ) ) {
			$usergroups = isset( $_POST['groups_usergroups'] ) ? array_map( 'intval', (array)$_POST['groups_usergroups'] ) : array();
			$all_usergroups = $this->get_usergroups();
			foreach( $all_usergroups as $usergroup ) {
				if ( in_array( $usergroup->term_id, $usergroups ) )
					$this->add_user_to_usergroup( $user->ID, $usergroup->term_id );
				else
					$this->remove_user_from_usergroup( $user->ID, $usergroup->term_id );
			}
		}
			
		return array( &$errors, $update, &$user );
	}
	
	public function get_link( $args = array() ) {
		if ( !isset( $args['action'] ) )
			$args['action'] = '';
        
		if ( !isset( $args['page'] ) )
			$args['page'] = $this->module->settings_slug;

		switch( $args['action'] ) {
			case 'delete-usergroup':
				$args['nonce'] = wp_create_nonce( $args['action'] );
				break;
			default:
				break;
		}
		return add_query_arg( $args, get_admin_url( null, 'admin.php' ) );
	}
	
	public function usergroups_select_form( $selected = array(), $args = null ) {

		$defaults = array(
			'list_class' => 'workflow-groups-list',
			'input_id' => 'groups-usergroups',
            'input_name' => 'groups_usergroups'
		);

		$parsed_args = wp_parse_args( $args, $defaults );
		extract( $parsed_args, EXTR_SKIP );
		$usergroups = $this->get_usergroups();
		if ( empty($usergroups) ) {
			?>
			<p><?php _e('Keine Benutzergruppen gefunden.', CMS_WORKFLOW_TEXTDOMAIN) ?> <a href="<?php echo esc_url( $this->get_link() ); ?>" title="<?php _e('Neue Benutzergruppe hinzufügen', CMS_WORKFLOW_TEXTDOMAIN ) ?>" target="_blank"><?php _e( 'Neue Benutzergruppe hinzufügen', CMS_WORKFLOW_TEXTDOMAIN ); ?></a></p>
			<?php
		} else {

			?>
			<ul class="<?php echo $list_class ?>">
			<?php
			foreach( $usergroups as $usergroup ) {
				$checked = ( in_array( $usergroup->term_id, $selected ) ) ? ' checked="checked"' : '';
				?>
				<li>
					<label for="<?php echo $input_id .'-'. esc_attr( $usergroup->term_id ); ?>" title="<?php echo esc_attr($usergroup->description) ?>">
						<input type="checkbox" id="<?php echo $input_id .'-'. esc_attr( $usergroup->term_id ) ?>" name="<?php echo $input_name ?>[]" value="<?php echo esc_attr( $usergroup->term_id ) ?>"<?php echo $checked ?> />
						<span class="workflow-usergroup-name"><?php echo esc_html( $usergroup->name ); ?></span>
						<span class="workflow-usergroup-description" title="<?php echo esc_attr($usergroup->description) ?>">
							<?php echo (strlen($usergroup->description) >= 50) ? substr_replace(esc_html($usergroup->description), '...', 50) : esc_html($usergroup->description); ?>
						</span>
					</label>
				</li>
				<?php
			}
			?>
			</ul>
			<?php
		}
	}
	
	public function get_usergroups( $args = array() ) {
		
		if ( !isset( $args['hide_empty'] ) )
			$args['hide_empty'] = 0;
		
		$usergroup_terms = get_terms( self::taxonomy_key, $args );	
		if ( !$usergroup_terms )
			return false;
		
		$usergroups = array();
		foreach( $usergroup_terms as $usergroup_term ) {
			$usergroups[] = $this->get_usergroup_by( 'id', $usergroup_term->term_id );
		}
        
		return $usergroups;
	}
	
	public function get_usergroup_by( $field, $value ) {
		
		$usergroup = get_term_by( $field, $value, self::taxonomy_key );
		
		if ( !$usergroup || is_wp_error( $usergroup ) )
			return $usergroup;
		
		$usergroup->user_ids = array();
		$unencoded_description = $this->get_unencoded_description( $usergroup->description );
		if ( is_array( $unencoded_description ) ) {
			foreach( $unencoded_description as $key => $value ) {
				$usergroup->$key = $value;
			}
		}
        
		return $usergroup;
	}
	
	public function add_usergroup( $args = array(), $user_ids = array() ) {

		if ( !isset( $args['name'] ) )
			return new WP_Error( 'invalid', __( 'Eine neue Benutzergruppe muss einen Name haben.', CMS_WORKFLOW_TEXTDOMAIN ) );
			
		$name = $args['name'];			
		$default = array(
			'name' => '',
			'slug' => self::term_prefix . sanitize_title( $name ),
			'description' => '',
		);
		$args = array_merge( $default, $args );
		
		$args_to_encode = array(
			'description' => $args['description'],
			'user_ids' => array_unique( $user_ids ),
		);
		$encoded_description = $this->get_encoded_description( $args_to_encode );
		$args['description'] = $encoded_description;
		$usergroup = wp_insert_term( $name, self::taxonomy_key, $args );
		if ( is_wp_error( $usergroup ) )
			return $usergroup;
		
		return $this->get_usergroup_by( 'id', $usergroup['term_id'] );
	}
	
	public function update_usergroup( $id, $args = array(), $users = null ) {
		
		$existing_usergroup = $this->get_usergroup_by( 'id', $id );
		if ( is_wp_error( $existing_usergroup ) )
			return new WP_Error( 'invalid', __( 'Die Benutzergruppe existiert nicht.', CMS_WORKFLOW_TEXTDOMAIN ) );
		
		$args_to_encode = array();
		$args_to_encode['description'] = ( isset( $args['description'] ) ) ? $args['description'] : $existing_usergroup->description;
		$args_to_encode['user_ids'] = ( is_array( $users ) ) ? $users : $existing_usergroup->user_ids;
		$args_to_encode['user_ids'] = array_unique( $args_to_encode['user_ids'] );
		$encoded_description = $this->get_encoded_description( $args_to_encode );
		$args['description'] = $encoded_description;
		
		$usergroup = wp_update_term( $id, self::taxonomy_key, $args );
		if ( is_wp_error( $usergroup ) )
			return $usergroup;
		
		return $this->get_usergroup_by( 'id', $usergroup['term_id'] );
	}
	
	public function delete_usergroup( $id ) {
		
		$retval = wp_delete_term( $id, self::taxonomy_key );
		return $retval;
	}
	
	public function add_users_to_usergroup( $user_ids_or_logins, $id, $reset = true ) {
		
		if ( !is_array( $user_ids_or_logins ) )
			return new WP_Error( 'invalid', __( 'Ungültige Benutzervariabel.', CMS_WORKFLOW_TEXTDOMAIN ) );
		
		$usergroup = $this->get_usergroup_by( 'id', $id );		
		if ( $reset ) {
			$retval = $this->update_usergroup( $id, null, array() );
			if ( is_wp_error( $retval ) )
				return $retval;
		}
		
		$new_users = array();
		foreach ( (array)$user_ids_or_logins as $user_id_or_login ) {
			if ( !is_numeric( $user_id_or_login ) )
				$new_users[] = get_user_by( 'login', $user_id_or_login )->ID;
			else
				$new_users[] = (int)$user_id_or_login;
		}
        
		$retval = $this->update_usergroup( $id, null, $new_users );
		if ( is_wp_error( $retval ) )
			return $retval;
        
		return true;
	}
	
	public function add_user_to_usergroup( $user_id_or_login, $ids ) {
		
		if ( !is_numeric( $user_id_or_login ) )
			$user_id = get_user_by( 'login', $user_id_or_login )->ID;
		else
			$user_id = (int)$user_id_or_login;
		
		foreach( (array)$ids as $usergroup_id ) {
			$usergroup = $this->get_usergroup_by( 'id', $usergroup_id );
			$usergroup->user_ids[] = $user_id;
			$retval = $this->update_usergroup( $usergroup_id, null, $usergroup->user_ids );
			if ( is_wp_error( $retval ) )
				return $retval;
		}
		return true;
	}
	
	public function remove_user_from_usergroup( $user_id_or_login, $ids ) {
		
		if ( !is_numeric( $user_id_or_login ) )
			$user_id = get_user_by( 'login', $user_id_or_login )->ID;
		else
			$user_id = (int)$user_id_or_login;
		
		foreach( (array)$ids as $usergroup_id ) {
			$usergroup = $this->get_usergroup_by( 'id', $usergroup_id );
			foreach( $usergroup->user_ids as $key => $usergroup_user_id ) {
				if ( $usergroup_user_id == $user_id )
					unset( $usergroup->user_ids[$key] );
			}
			$retval = $this->update_usergroup( $usergroup_id, null, $usergroup->user_ids );
			if ( is_wp_error( $retval ) )
				return $retval;
		}
		return true;
		
	}
	
	public function get_usergroups_for_user( $user_id_or_login, $ids_or_objects = 'ids' ) {

		if ( !is_numeric( $user_id_or_login ) )
			$user_id = get_user_by( 'login', $user_id_or_login )->ID;
		else
			$user_id = (int)$user_id_or_login;
		
		$all_usergroups = $this->get_usergroups();
		if ( !empty( $all_usergroups) ) {
			$usergroup_objects_or_ids = array();
			foreach( $all_usergroups as $usergroup ) {
				if ( !in_array( $user_id, $usergroup->user_ids ) )
					continue;
                
				if ( $ids_or_objects == 'ids' )
					$usergroup_objects_or_ids[] = (int)$usergroup->term_id;
                
				else if ( $ids_or_objects == 'objects' )
					$usergroup_objects_or_ids[] = $usergroup;			
			}
			return $usergroup_objects_or_ids;
		} else {
			return false;
		}
	}
	
}

class Workflow_Usergroups_List_Table extends WP_List_Table {
	
	public $callback_args;
	
	public function __construct() {
		
		parent::__construct( array(
            'screen' => 'edit-usergroup',
			'plural' => 'user groups',
			'singular' => 'user group',
			'ajax' => true
		) );
		
	}
	
	public function prepare_items() {
		global $cms_workflow;		
		
		$columns = $this->get_columns();
		$hidden = array();
		$sortable = array();
		
		$this->_column_headers = array( $columns, $hidden, $sortable );
		
		$this->items = $cms_workflow->user_groups->get_usergroups();
		
		$this->set_pagination_args( array(
			'total_items' => count( $this->items ),
			'per_page' => count( $this->items ),
		) );
	}

	public function no_items() {
		_e( 'Keine Benutzergruppen gefunden.', CMS_WORKFLOW_TEXTDOMAIN );
	}
	
	public function get_columns() {

		$columns = array(
			'name' => __( 'Name', CMS_WORKFLOW_TEXTDOMAIN ),
			'description' => __( 'Beschreibung', CMS_WORKFLOW_TEXTDOMAIN ),
			'users' => __( 'Benutzer', CMS_WORKFLOW_TEXTDOMAIN ),			
		);
		
		return $columns;
	}
	
	public function column_default( $usergroup, $column_name ) {
		
	}
	
	public function column_name( $usergroup ) {
		global $cms_workflow;
		
		$output = '<strong><a href="' . esc_url( $cms_workflow->user_groups->get_link( array( 'action' => 'edit-usergroup', 'usergroup-id' => $usergroup->term_id ) ) ) . '">' . esc_html( $usergroup->name ) . '</a></strong>';
		
		$actions = array();
		$actions['edit edit-usergroup'] = sprintf( '<a href="%1$s">' . __( 'Bearbeiten', CMS_WORKFLOW_TEXTDOMAIN ) . '</a>', $cms_workflow->user_groups->get_link( array( 'action' => 'edit-usergroup', 'usergroup-id' => $usergroup->term_id ) ) );
		$actions['inline hide-if-no-js'] = '<a href="#" class="editinline">' . __( 'QuickEdit' ) . '</a>';
		$actions['delete delete-usergroup'] = sprintf( '<a href="%1$s">' . __( 'Löschen', CMS_WORKFLOW_TEXTDOMAIN ) . '</a>', $cms_workflow->user_groups->get_link( array( 'action' => 'delete-usergroup', 'usergroup-id' => $usergroup->term_id ) ) );
		
		$output .= $this->row_actions( $actions, false );
		$output .= '<div class="hidden" id="inline_' . $usergroup->term_id . '">';
		$output .= '<div class="name">' . esc_html( $usergroup->name ) . '</div>';
		$output .= '<div class="description">' . esc_html( $usergroup->description ) . '</div>';	
		$output .= '</div>';
		
		return $output;
			
	}
	
	public function column_description( $usergroup ) {
		return esc_html( $usergroup->description );
	}
	
	public function column_users( $usergroup ) {
		global $cms_workflow;
		return '<a href="' . esc_url( $cms_workflow->user_groups->get_link( array( 'action' => 'edit-usergroup', 'usergroup-id' => $usergroup->term_id ) ) ) . '">' . count( $usergroup->user_ids ) . '</a>';
	}
	
	public function single_row( $usergroup ) {
		static $row_class = '';
		$row_class = ( $row_class == '' ? ' class="alternate"' : '' );

		echo '<tr id="usergroup-' . $usergroup->term_id . '"' . $row_class . '>';
		echo $this->single_row_columns( $usergroup );
		echo '</tr>';
	}
	
	public function inline_edit() {
		global $cms_workflow;
        ?>
        <form method="get" action=""><table style="display: none"><tbody id="inlineedit">
            <tr id="inline-edit" class="inline-edit-row" style="display: none"><td colspan="<?php echo $this->get_column_count(); ?>" class="colspanchange">
                <fieldset><div class="inline-edit-col">
                    <h4><?php _e( 'QuickEdit', CMS_WORKFLOW_TEXTDOMAIN ); ?></h4>
                    <label>
                        <span class="title"><?php _e( 'Name', CMS_WORKFLOW_TEXTDOMAIN ); ?></span>
                        <span class="input-text-wrap"><input type="text" name="name" class="ptitle" value="" maxlength="40" /></span>
                    </label>
                    <label>
                        <span class="title"><?php _e( 'Beschreibung', CMS_WORKFLOW_TEXTDOMAIN ); ?></span>
                        <span class="input-text-wrap"><input type="text" name="description" class="pdescription" value="" /></span>
                    </label>
                </div></fieldset>
                <p class="inline-edit-save submit">
                    <a accesskey="c" href="#inline-edit" title="<?php _e( 'Abbrechen', CMS_WORKFLOW_TEXTDOMAIN ); ?>" class="cancel button-secondary alignleft"><?php _e( 'Abbrechen', CMS_WORKFLOW_TEXTDOMAIN ); ?></a>
                    <?php $update_text = __( 'Benutzergruppe aktualisieren', CMS_WORKFLOW_TEXTDOMAIN ); ?>
                    <a accesskey="s" href="#inline-edit" title="<?php echo esc_attr( $update_text ); ?>" class="save button-primary alignright"><?php echo $update_text; ?></a>
                    <img class="waiting" style="display:none;" src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" alt="" />
                    <span class="error" style="display:none;"></span>
                    <?php wp_nonce_field( 'usergroups-inline-edit-nonce', 'inline_edit', false ); ?>
                    <br class="clear" />
                </p>
            </td></tr>
            </tbody></table></form>
        <?php
	}
		
}

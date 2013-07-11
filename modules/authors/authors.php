<?php

class Workflow_Authors extends Workflow_Module {
	
	const taxonomy_key = 'workflow_author';

	const not_taxonomy_key = 'workflow_not_author';
	
    const taxonomy_pattern = '#^workflow\-#';
    
    const role = 'author';
    
    private $system_caps = array();             

    public $role_caps = array();
    
	public $module;
	
    public $having_terms = '';
    
	public function __construct () {
		global $cms_workflow;
        
		$this->module_url = $this->get_module_url( __FILE__ );
        
        $this->system_caps = array(
            'edit_published_posts' => __('Veröffentlichte Beiträge bearbeiten', CMS_WORKFLOW_TEXTDOMAIN), 
            'upload_files' => __('Dateien hochladen', CMS_WORKFLOW_TEXTDOMAIN),  
            'publish_posts' => __(' Beitrag veröffentlichen', CMS_WORKFLOW_TEXTDOMAIN), 
            'delete_published_posts' => __('Veröffentlichte Beiträge löschen', CMS_WORKFLOW_TEXTDOMAIN), 
            'edit_posts' => __('Beiträge bearbeiten', CMS_WORKFLOW_TEXTDOMAIN),
            'delete_posts' => __('Beiträge löschen', CMS_WORKFLOW_TEXTDOMAIN)
        );

		$args = array(
			'title' => __( 'Autoren', CMS_WORKFLOW_TEXTDOMAIN ),
			'description' => __( 'Verwaltung der Autoren.', CMS_WORKFLOW_TEXTDOMAIN ),
			'module_url' => $this->module_url,
			'slug' => 'authors',
			'default_options' => array(
				'post_types' => array(
					'post' => true,
					'page' => true,
				),
                'role_caps' => array()
			),
			'configure_callback' => 'print_configure_view',
			'settings_help_tab' => array(
				'id' => 'workflow-authors-overview',
				'title' => __('Übersicht', CMS_WORKFLOW_TEXTDOMAIN),
				'content' => __('<p></p>', CMS_WORKFLOW_TEXTDOMAIN),
				),
			'settings_help_sidebar' => __( '<p><strong>Für mehr Information:</strong></p><p><a href="http://blogs.fau.de/cms">Dokumentation</a></p><p><a href="http://blogs.fau.de/webworking">RRZE-Webworking</a></p><p><a href="https://github.com/RRZE-Webteam">RRZE-Webteam in Github</a></p>', CMS_WORKFLOW_TEXTDOMAIN ),
		);
        
		$this->module = $cms_workflow->register_module( 'authors', $args );       
 
	}
	
	public function init() {
		
        $this->set_role_caps();
        
        $this->register_taxonomies();
        
		add_action( 'add_meta_boxes', array( $this, 'add_post_meta_box' ) );
	
		add_action( 'transition_post_status', array( $this, 'save_post' ), 0, 3 );
        
		add_action( 'delete_user',  array($this, 'delete_user_action') );
        
		add_filter( 'get_usernumposts', array( $this, 'get_usernumposts_filter' ), 10, 2 );        
		
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init' , array( $this, 'custom_columns' ));
        
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );	
        
		add_filter( 'user_has_cap', array( $this, 'filter_user_has_cap' ), 10, 3 );
        
		add_action( 'admin_head', array( $this, 'remove_quick_edit_authors_box' ) );
		add_action( 'add_meta_boxes', array( $this, 'remove_authors_box' ) );
                
		add_filter( 'posts_where', array( $this, 'posts_where_filter' ), 10, 2 );
		add_filter( 'posts_join', array( $this, 'posts_join_filter' ) );
		add_filter( 'posts_groupby', array( $this, 'posts_groupby_filter' ) );
        
		add_action( 'load-edit.php', array( $this, 'load_edit' ) );
        
        add_filter( 'author_edit_pre', array( $this, 'author_edit_pre_filter'), 10, 2 );
        
	}
	
    public function deactivation($networkwide) {
        global $cms_workflow;
        
        $role =& get_role( self::role );

        foreach($this->module->options->role_caps as $key => $value) {
            $role->remove_cap( $key );
        }

        foreach($this->system_caps as $key => $value) {
            $role->add_cap( $key );
        }
        
    }
  
    public function activation() {
        global $cms_workflow;
        
        $this->set_role_caps();

        if (empty( $this->module->options->role_caps ) ) {
            
            $system_caps = array_map(function($item) { return true; }, $this->system_caps);
            $cms_workflow->update_module_option( $this->module->name, 'role_caps', $system_caps );
            $this->module->options->role_caps = $system_caps;
            
        } else {
            
            $role =& get_role( self::role );

            foreach($this->role_caps as $key => $value) {
                $role->remove_cap( $key );
            }

            foreach($this->module->options->role_caps as $key => $value) {
                $role->add_cap( $key );
            }
        }
        
    }
    
    public function set_role_caps() {
        
        $all_post_types = $this->get_available_post_types();

        $this->role_caps = $this->system_caps;
        
        foreach( $all_post_types as $post_type => $args ) {
            $this->role_caps[$args->cap->edit_posts] = sprintf('%s bearbeiten', $args->label);
            $this->role_caps[$args->cap->publish_posts] = sprintf('%s veröffentlich', $args->label);
            $this->role_caps[$args->cap->delete_posts] = sprintf('%s löschen', $args->label);
            $this->role_caps[$args->cap->edit_published_posts] = sprintf('Veröffentlichte %s bearbeiten', $args->label);
            $this->role_caps[$args->cap->delete_published_posts] = sprintf('Veröffentlichte %s löschen', $args->label);
        }
        
    }
    
	public function register_taxonomies() {
		
		$allowed_post_types = $this->get_post_types( $this->module );
		
		$args = array(
			'hierarchical'           => false,
			'update_count_callback'  => '_update_post_term_count',
			'label'                  => false,
			'query_var'              => false,
			'rewrite'                => false,
			'public'                 => false,
			'show_ui'                => false
		);
        
		foreach( array( self::taxonomy_key, self::not_taxonomy_key ) as $taxonomy ) {
			register_taxonomy( $taxonomy, $allowed_post_types, $args );
		}
	}
	
	public function enqueue_admin_scripts() {
        wp_enqueue_script( 'jquery-listfilterizer' );
        wp_enqueue_script( 'jquery-quicksearch' );
        wp_enqueue_script( 'workflow-authors', $this->module_url . 'authors.js', array( 'jquery', 'jquery-listfilterizer', 'jquery-quicksearch' ), CMS_WORKFLOW_VERSION, true );
	}
	
	public function enqueue_admin_styles() {
        wp_enqueue_style( 'jquery-listfilterizer' );
        wp_enqueue_style( 'workflow-authors', $this->module->module_url . 'authors.css', false, CMS_WORKFLOW_VERSION );
	}
	
	public function add_post_meta_box() {

		if ( !current_user_can( 'manage_categories' ) ) 
			return;		
		
		$allowed_post_types = $this->get_post_types( $this->module );
		foreach ( $allowed_post_types as $post_type ) {
			add_meta_box( 'workflow-authors', __( 'Autoren', CMS_WORKFLOW_TEXTDOMAIN), array( $this, 'authors_meta_box'), $post_type, 'advanced' );
		}
	}
	
	public function authors_meta_box() {
		global $post, $post_ID, $cms_workflow;

		?>
		<div id="workflow-post-authors-box">

			<p><?php _e( 'Wählen Sie die Autoren zum Dokument', CMS_WORKFLOW_TEXTDOMAIN ); ?></p>
			<div id="workflow-post-authors-inside">
				<h4><?php _e( 'Benutzer', CMS_WORKFLOW_TEXTDOMAIN ); ?></h4>
				<?php
				$authors = self::get_authors( $post->ID, 'id' );
				$args = array(
					'list_class' => 'workflow-post-authors-list',
                    'input_id' => 'workflow-selected-authors',
                    'input_name' => 'workflow_selected_authors',
				);
				$this->users_select_form( $authors, $args ); ?>
			</div>
			
			<?php if ( $this->module_activated( 'user_groups' ) && in_array( $this->get_current_post_type(), $this->get_post_types( $cms_workflow->user_groups->module ) ) ): ?>
			<div id="workflow-post-authors-usergroups-box">
				<h4><?php _e('Benutzergruppen', CMS_WORKFLOW_TEXTDOMAIN) ?></h4>
				<?php              
				$authors_usergroups = $this->get_authors_usergroups( $post->ID, 'ids' );
                $args = array(
                    'list_class' => 'workflow-post-authors-list',
                    'input_id' => 'authors-usergroups',
                    'input_name' => 'authors_usergroups'
                );              
				$cms_workflow->user_groups->usergroups_select_form( $authors_usergroups, $args ); ?>
			</div>
			<?php endif; ?>
			<div class="clear"></div>
			<input type="hidden" name="workflow_save_authors" value="1" />
		</div>
		
		<?php
	}
	
	public function save_post( $new_status, $old_status, $post ) {
		global $cms_workflow;
        
		if( ( !wp_is_post_revision($post) && !wp_is_post_autosave($post) ) && isset($_POST['workflow_save_authors']) && current_user_can( 'manage_categories' ) ) {
            $users = isset( $_POST['workflow_selected_authors'] ) ? $_POST['workflow_selected_authors'] : array();
			$usergroups = isset( $_POST['authors_usergroups'] ) ? $_POST['authors_usergroups'] : array();
			
            $this->save_post_authors( $post, $users );
            
			if ( $this->module_activated( 'user_groups' ) && in_array( $this->get_current_post_type(), $this->get_post_types( $cms_workflow->user_groups->module ) ) )
				$this->save_post_authors_usergroups( $post, $usergroups );
		}
		
	}
    
    public function author_edit_pre_filter($user_id, $post_id) {
        $user_data = get_userdata($user_id);

        if( $user_data ) {

            $name = $user_data->user_login;

            $term = $this->add_term_if_not_exists($name, self::taxonomy_key);
            
            if(!is_wp_error($term))
                wp_set_object_terms( $post_id, $name, self::taxonomy_key, true );
        }
        
    }
	
	public function save_post_authors( $post, $users = null ) {
		if( !is_array( $users ) )
			$users = array();
		
		$current_user = wp_get_current_user();
		$users[] = $current_user->ID;
		        
        $users[] = $post->post_author;
        
		$users = array_unique( array_map( 'intval', $users ) );
        
		$this->add_post_user( $post, $users, false );
		
	}
	
	public function save_post_authors_usergroups( $post, $usergroups = null ) {
		
		if( empty($usergroups) ) 
            $usergroups = array();
        
		$usergroups = array_unique( array_map( 'intval', $usergroups ));
        
		$this->add_post_usergroups($post, $usergroups, false);
	}	
			
	public function add_post_user( $post, $users, $append = true ) {

		$post_id = ( is_int($post) ) ? $post : $post->ID;
		if( !is_array($users) ) $users = array($users);

		$user_terms = array();
		foreach( $users as $user ) {
			if( is_int($user) ) 
				$user_data = get_userdata($user);
            
			else if( is_string($user) )
				$user_data = get_userdatabylogin($user);
            
			else
				$user_data = $user;
			
			if( $user_data ) {

				$name = $user_data->user_login;
				
				$term = $this->add_term_if_not_exists($name, self::taxonomy_key);
				
				if(!is_wp_error($term))
					$user_terms[] = $name;
                
			}
		}
		
        wp_set_object_terms( $post_id, $user_terms, self::taxonomy_key, $append );
		
		return;
	}

    public function delete_post_user( $post, $user = 0 ) {
		global $current_user;
		
		$post_id = $post->ID;

        if(!$post_id || !$user || $user->ID == 0)
			return;
			
		$name = $user->user_login;
		
		if( term_exists($name, self::taxonomy_key) ) {
			$set = wp_set_object_terms( $post->ID, $name, self::taxonomy_key, true );
			$old_term_ids =  wp_get_object_terms($post_id, self::taxonomy_key, array('fields' => 'ids', 'orderby' => 'none'));
	
		}
		
		$insert = $this->add_term_if_not_exists($name, self::not_taxonomy_key);
		
		if(!is_wp_error($insert)) {
			$exclude = wp_set_object_terms( $post_id, $name, self::not_taxonomy_key, true );
		}
		
		return;
	}

	private function add_post_usergroups( $post, $usergroups, $append = true ) {
		global $cms_workflow;
		
		if ( !$this->module_activated( 'user_groups' ) )
			return;

		$post_id = is_int($post) ? $post : $post->ID;
        
        if(!empty($usergroups)) {
            
            $authors = self::get_authors( $post->ID, 'id' );
		
            $users = array();	
            foreach( $usergroups as $usergroup ) {
                $usergroup_data = $cms_workflow->user_groups->get_usergroup_by( 'id', $usergroup );
                if($usergroup_data && !empty($usergroup_data->user_ids)) {
                    foreach($usergroup_data->user_ids as $key => $value) {
                        $users[] = $value;
                    }
                }
            }
            
            $users = array_merge( $users, $authors );
            $users = array_unique( array_map( 'intval', $users ) );
        
            $this->add_post_user( $post, $users, false );
        }
        
        $usergroups_taxonomy = Workflow_User_Groups::taxonomy_key; 
        
        wp_set_object_terms( $post_id, $usergroups, $usergroups_taxonomy, $append );
	}
	    
	public function delete_user_action( $id ) {
        global $wpdb;
        
		if( !$id ) return;
		
		$user = get_userdata($id);
		
		if( $user ) {

			$user_authors_term = get_term_by('name', $user->user_login, self::taxonomy_key);
			if( $user_authors_term ) 
                wp_delete_term($user_authors_term->term_id, self::taxonomy_key);

			$user_deleted_term = get_term_by('name', $user->user_login, self::not_taxonomy_key);
			if( $user_deleted_term ) 
                wp_delete_term($user_deleted_term->term_id, self::not_taxonomy_key);
		}

		$reassign_id = absint( $_POST['reassign_user'] );

		if($reassign_id) {
			$reassign_user = get_user_by( 'id', $reassign_id );
			if( is_object( $reassign_user ) ) {
				$post_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_author = %d", $delete_id ) );

				if ( $post_ids ) {
					foreach ( $post_ids as $post_id ) {
						$this->add_coauthors( $post_id, array( $reassign_user->user_login ), true );
					}
				}
			}
		}

	}
		
	function get_usernumposts_filter( $count, $user_id ) {
		$user = get_userdata( $user_id );

		$term = $this->get_author_term( $user );

		if( $term && !is_wp_error( $term ) ) {
			$count = $term->count;
		}

		return $count;
	}
    
	public function add_coauthors( $post_id, $coauthors, $append = false ) {
		global $current_user;

		$post_id = (int) $post_id;
		$insert = false;

		if ( !is_array( $coauthors ) || 0 == count( $coauthors ) || empty( $coauthors ) ) {
			$coauthors = array( $current_user->user_login );
		}

		foreach( array_unique( $coauthors ) as $key => $author_name ) {
			$author = $this->get_coauthor_by( 'user_nicename', $author_name );
			$term = $this->update_author_term( $author );
			$coauthors[$key] = $term->slug;
		}
        
		wp_set_post_terms( $post_id, $coauthors, self::taxonomy_key, $append );
	}
    
	public function get_coauthor_by( $key, $value, $force = false ) {

		switch( $key ) {
			case 'id':
                
			case 'login':
                
			case 'user_login':
                
			case 'email':
                
			case 'user_nicename':
                
			case 'user_email':
				if ( 'user_login' == $key )
					$key = 'login';
                
				if ( 'user_email' == $key )
					$key = 'email';
                
				if ( 'user_nicename' == $key )
					$key = 'slug';
				
				if ( 'login' == $key || 'slug' == $key )
					$value = preg_replace( self::taxonomy_pattern, '', $value );
                
				$user = get_user_by( $key, $value );
				if ( !$user || !is_user_member_of_blog( $user->ID ) )
					return false;
                
				$user->type = 'wpuser';
				return $user;
				break;
		}
        
		return false;

	}
    
	public function update_author_term( $coauthor ) {

		if ( ! is_object( $coauthor ) )
			return false;

		$search_values = array();
        $fields = array( 'display_name', 'first_name', 'last_name', 'user_login', 'ID', 'user_email' );
        
		foreach( $fields as $search_field ) {
			$search_values[] = $coauthor->$search_field;
		}
        
		$term_description = implode( ' ', $search_values );

        $term = $this->get_author_term( $coauthor );
		if ( $term ) {
			wp_update_term( $term->term_id, self::taxonomy_key, array( 'description' => $term_description ) );
            
		} else {
			$coauthor_slug = 'workflow-' . $coauthor->user_nicename;
			$args = array(
				'slug'          => $coauthor_slug,
				'description'   => $term_description,
			);
            
			$new_term = wp_insert_term( $coauthor->user_login, self::taxonomy_key, $args );
		}

		return $this->get_author_term( $coauthor );
	}

	public function get_author_term( $coauthor ) {

		if ( ! is_object( $coauthor ) )
			return;

		$term = get_term_by( 'slug', 'workflow-' . $coauthor->user_nicename, self::taxonomy_key );
		if ( ! $term ) {
			$term = get_term_by( 'slug', $coauthor->user_nicename, self::taxonomy_key );
		}

		return $term;
	}
    
	public function filter_user_has_cap( $allcaps, $caps, $args ) {
		$cap = $args[0];
		$user_id = isset( $args[1] ) ? $args[1] : 0;
		$post_id = isset( $args[2] ) ? $args[2] : 0;

		$obj = get_post_type_object( get_post_type( $post_id ) );
		if ( ! $obj )
			return $allcaps;

		$caps_to_modify = array(
				$obj->cap->edit_post,
				$obj->cap->edit_others_posts
			);
        
		if ( ! in_array( $cap, $caps_to_modify ) )
			return $allcaps;

		if( ! is_user_logged_in() || ! $this->is_coauthor_for_post( $user_id, $post_id ) )
			return $allcaps;

		$current_user = wp_get_current_user();
        
        if(isset($obj->cap->edit_published_posts)) {
            if ( 'publish' == get_post_status( $post_id ) && ! empty( $current_user->allcaps[$obj->cap->edit_published_posts] ) )
                $allcaps[$obj->cap->edit_published_posts] = true;

            elseif ( 'private' == get_post_status( $post_id ) && ! empty( $current_user->allcaps[$obj->cap->edit_private_posts] ) )
                $allcaps[$obj->cap->edit_private_posts] = true;
        }
        
		$allcaps[$obj->cap->edit_others_posts] = true;
		return $allcaps;
	}
    
	public function remove_quick_edit_authors_box() {
		global $pagenow;

		if ( 'edit.php' == $pagenow && $this->is_post_type_enabled() )
			remove_post_type_support( get_post_type(), 'author' );
	}

	public function remove_authors_box() {

		if ( $this->is_post_type_enabled() )
			remove_meta_box( 'authordiv', get_post_type(), 'normal' );
	}
    
    public function custom_columns() {
        
		foreach( get_post_types() as $post_type ) {
            if($this->is_post_type_enabled($post_type)) {
                add_action( "manage_edit-{$post_type}_columns", array( $this, 'manage_posts_columns' ));
                add_filter( "manage_{$post_type}_posts_custom_column", array( $this, 'manage_posts_custom_column'), 10, 2);            
                add_filter( "manage_edit-{$post_type}_sortable_columns", array( $this, 'manage_posts_sortable_columns' ));
            }
		}
                 
    }
    
	public function manage_posts_columns( $posts_columns ) {

		$new_columns = array();

		foreach ($posts_columns as $key => $value) {
			$new_columns[$key] = $value;
            
			if( $key == 'cb' )
				$new_columns['id'] = __( 'Nr.', CMS_WORKFLOW_TEXTDOMAIN );
            
			if( $key == 'title' )
				$new_columns['coauthors'] = __( 'Autoren', CMS_WORKFLOW_TEXTDOMAIN );

			if ( $key == 'author' )
				unset($new_columns[$key]);
		}
		return $new_columns;
	}

	public function manage_posts_custom_column( $column_name, $id ) {
        if ($column_name == 'id') {
            echo $id;
        }
        
		elseif ($column_name == 'coauthors') {
            global $post;
            
			$authors = $this->get_coauthors( $id );

			$count = 1;
			foreach( $authors as $author ) :
				$args = array();
            
				if ( 'post' != $post->post_type )
					$args['post_type'] = $post->post_type;
                
                $args['author'] = $author->ID;
                
				$author_filter_url = add_query_arg( $args, admin_url( 'edit.php' ) );
                $separator = $count < count( $authors ) ? ', ' : '';
                
                printf('<a href="%s">%s</a>%s', esc_url( $author_filter_url ), esc_html( $author->display_name ), $separator);

				$count++;
			endforeach;
		}
        
	}

    public function manage_posts_sortable_columns($columns) {
        $columns['id'] = 'id';
        return $columns;        
    }
    
	public function load_edit() {
		$screen = get_current_screen();
        
        $allowed_post_types = $this->get_post_types( $this->module );
		if ( in_array( $screen->post_type, $allowed_post_types ) )
			add_filter( 'views_' . $screen->id, array( $this, 'filter_views' ) );
	}
 
	public function is_post_type_enabled( $post_type = null ) {

        $allowed_post_types = $this->get_post_types( $this->module );
        
		if ( ! $post_type )
			$post_type = get_post_type();

		return (bool) in_array( $post_type, $allowed_post_types );
	}
    
	public function filter_views( $views ) {
        global $locked_post_status;
            
		if ( !empty($locked_post_status) )
			return $views;
        
        $post_type = get_post_type();

        $current_user_id = get_current_user_id();
        
        if ( empty( $_REQUEST['author'] ) )
            $user = wp_get_current_user();
        
        else
            $user = get_userdata( (int) $_REQUEST['author'] );
        
        if( !$user)
            return $views;
        
        $mine_args = array();
        if($post_type != 'post')
            $mine_args['post_type'] = $post_type;
        
        $mine_args['author'] = $user->ID;
	
		$post_args = array(
            'post_type' => $post_type,
            'tax_query' => array(
                array(
                    'taxonomy' => self::taxonomy_key,
                    'field' => 'slug',
                    'terms' => $user->user_login
                )
            )

		);
        
        $query = new WP_Query( $post_args );

		$count = $query->post_count;
 
        $match = array_filter($views, function($views) { return(strpos($views, 'class="current"')); });
        if ( !empty( $_REQUEST['author'] ) || array_key_exists('mine', $match) )
			$class = ' class="current"';		
        else
			$class = '';
        
        $labels = $this->get_post_type_labels();
        
        if($current_user_id == $user->ID)
            $mine = sprintf( _nx( 'Mein %1$s <span class="count">(%3$s)</span>', 'Meine %2$s <span class="count">(%3$s)</span>', $count, 'authors', CMS_WORKFLOW_TEXTDOMAIN ), $labels->singular_name, $labels->name, number_format_i18n( $count ) );
        else
            $mine = sprintf( __( '%1$s von %3$s <span class="count">(%2$s)</span>', $count, 'authors', CMS_WORKFLOW_TEXTDOMAIN ), $labels->name, number_format_i18n( $count ), $user->display_name );
        
		$mine_view['mine'] = '<a' . $class . ' href="' . add_query_arg( $mine_args, admin_url( 'edit.php' ) ) . '">' . $mine . '</a>';
        
        $views['all'] = str_replace( $class, '', $views['all'] );
        $views = $mine_view + $views;

		return $views;
	}
    
    public function get_coauthors( $post_id = 0, $args = array() ) {
        global $post, $post_ID, $wpdb;

        $coauthors = array();
        $post_id = (int)$post_id;
        
        if ( !$post_id && $post_ID )
            $post_id = $post_ID;
        
        if ( !$post_id && $post )
            $post_id = $post->ID;

        $defaults = array('orderby'=>'term_order', 'order'=>'ASC');
        $args = wp_parse_args( $args, $defaults );

        if ( $post_id ) {
            $coauthor_terms = get_the_terms( $post_id, self::taxonomy_key, $args );

            if ( is_array( $coauthor_terms ) && !empty( $coauthor_terms ) ) {
                foreach( $coauthor_terms as $coauthor ) {
                    $coauthor_slug = preg_replace( self::taxonomy_pattern, '', $coauthor->slug );
                    $post_author =  $this->get_coauthor_by( 'user_nicename', $coauthor_slug );

                    if ( !empty( $post_author ) )
                        $coauthors[] = $post_author;
                }
            } else {
                
                if ( $post )
                    $post_author = get_userdata( $post->post_author );
                    
                else
                    $post_author = get_userdata( $wpdb->get_var( $wpdb->prepare("SELECT post_author FROM $wpdb->posts WHERE ID = %d", $post_id ) ) );
                
                if ( !empty( $post_author ) )
                    $coauthors[] = $post_author;
            }
        }
        
        return $coauthors;
    }
    
	public function posts_where_filter( $where, &$wp_query ){
		global $wpdb;

		if ( $wp_query->is_author() ) {
            
			if ( !empty( $wp_query->query_vars['post_type'] ) && !is_object_in_taxonomy( $wp_query->query_vars['post_type'], self::taxonomy_key ) )
				return $where;

            $author = get_userdata( $wp_query->get( 'author' ) )->ID;
            
			$terms = array();
			$coauthor = $this->get_coauthor_by( 'id', $author );
            
            $author_term = $this->get_author_term( $coauthor );                    
			if ( $author_term )
				$terms[] = $author_term;

			$maybe_both = apply_filters( 'should_query_post_author_filter', true );

			$maybe_both_query = $maybe_both ? '$1 OR' : '';

			if ( !empty( $terms ) ) {
				$terms_implode = '';
				$this->having_terms = '';
				foreach( $terms as $term ) {
					$terms_implode .= '(' . $wpdb->term_taxonomy . '.taxonomy = \''. self::taxonomy_key.'\' AND '. $wpdb->term_taxonomy .'.term_id = \''. $term->term_id .'\') OR ';
					$this->having_terms .= ' ' . $wpdb->term_taxonomy .'.term_id = \''. $term->term_id .'\' OR ';
				}
				$terms_implode = rtrim( $terms_implode, ' OR' );
				$this->having_terms = rtrim( $this->having_terms, ' OR' );
				$where = preg_replace( '/(\b(?:' . $wpdb->posts . '\.)?post_author\s*=\s*(\d+))/', '(' . $maybe_both_query . ' ' . $terms_implode . ')', $where, 1 );
			}

		}
        
		return $where;
	}
    
	public function posts_join_filter( $join ){
		global $wp_query, $wpdb;

		if( $wp_query->is_author() ) {

			if ( !empty( $wp_query->query_vars['post_type'] ) && !is_object_in_taxonomy( $wp_query->query_vars['post_type'], self::taxonomy_key ) )
				return $join;

			if ( empty( $this->having_terms ) )
				return $join;

			$term_relationship_join = " INNER JOIN {$wpdb->term_relationships} ON ({$wpdb->posts}.ID = {$wpdb->term_relationships}.object_id)";
			$term_taxonomy_join = " INNER JOIN {$wpdb->term_taxonomy} ON ( {$wpdb->term_relationships}.term_taxonomy_id = {$wpdb->term_taxonomy}.term_taxonomy_id )";

			if( strpos( $join, trim( $term_relationship_join ) ) === false ) {
				$join .= str_replace( "INNER JOIN", "LEFT JOIN", $term_relationship_join );
			}
            
			if( strpos( $join, trim( $term_taxonomy_join ) ) === false ) {
				$join .= str_replace( "INNER JOIN", "LEFT JOIN", $term_taxonomy_join );
			}
		}
        
		return $join;
	}

	public function posts_groupby_filter( $groupby ) {
		global $wp_query, $wpdb;

		if( $wp_query->is_author() ) {

			if ( !empty( $wp_query->query_vars['post_type'] ) && !is_object_in_taxonomy( $wp_query->query_vars['post_type'], self::taxonomy_key ) )
				return $groupby;

			if ( $this->having_terms ) {
				$having = 'MAX( IF( ' . $wpdb->term_taxonomy . '.taxonomy = \''. self::taxonomy_key.'\', IF( ' . $this->having_terms . ',2,1 ),0 ) ) <> 1 ';
				$groupby = $wpdb->posts . '.ID HAVING ' . $having;
			}
		}

		return $groupby;
	}
    
	public function add_term_if_not_exists( $term, $taxonomy ) {
		if ( !term_exists($term, $taxonomy) ) {
			$args = array( 'slug' => sanitize_title($term) );		
			return wp_insert_term( $term, $taxonomy, $args );
		}
        
		return true;
	}
	
	public static function get_authors( $post_id, $return = null ) {
    
		$authors = wp_get_object_terms( $post_id, self::taxonomy_key, array('fields' => 'names') );

		if( !$authors || is_wp_error($authors) )
			return array();

        $users = array();
		foreach( (array)$authors as $user ) {
			$new_user = get_user_by( 'login', $user );
			if ( !$new_user )
				continue;
            
            switch( $return ) {
                case 'user_login':
                    $users[$new_user->ID] = $new_user->user_login;
                    break;

                case 'id':
                    $users[$new_user->ID] = $new_user->ID;
                    break;

                case 'user_email':
                    $users[$new_user->ID] = $new_user->user_email;
                    break;	
                
                default:
                    $users[$new_user->ID] = $new_user;
            }             

		}
        
		if( !$users || is_wp_error($users) )
			$users = array();
        
		return $users;

	}
	
	public function get_authors_usergroups( $post_id, $return = 'all' ) {
		global $cms_workflow;

		if( $return == 'slugs' )
			$fields = 'all';
        
		else
			$fields = $return;

        $usergroups_taxonomy = Workflow_User_Groups::taxonomy_key;
        
		$usergroups = wp_get_object_terms( $post_id, $usergroups_taxonomy, array( 'fields' => $fields ) );
        
		if( $return == 'slugs' ) {
			$slugs = array();
            
			foreach($usergroups as $usergroup) {
				$slugs[] = $usergroup->slug; 	
			}
            
			$usergroups = $slugs;
		}
        
		return $usergroups;
	}
	
	public function get_user_authors_posts( $user = 0, $args = null ) {
		if ( !$user )
			$user = (int) wp_get_current_user()->ID;

		if ( is_int($user) )
			$user = get_userdata($user)->user_login;

		$post_args = array(
			self::taxonomy_key => $user,
			'posts_per_page' => '10',
			'orderby' => 'modified',
			'order' => 'DESC',
		);
        
		$post_args = apply_filters( 'workflow_user_authors_posts_query_args', $post_args );
		$posts = get_posts( $post_args );
		return $posts;

	}
	    
	public function register_settings() {
			add_settings_section( $this->module->workflow_options_name . '_general', false, '__return_false', $this->module->workflow_options_name );
			add_settings_field( 'post_types', __( 'Freigabe', CMS_WORKFLOW_TEXTDOMAIN ), array( $this, 'settings_post_types_option' ), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general' );
            add_settings_field( 'role_caps', __( 'Autorenrechte', CMS_WORKFLOW_TEXTDOMAIN ), array( $this, 'settings_role_caps_option' ), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general' );
	}
	
	public function settings_post_types_option() {
		global $cms_workflow;
		$cms_workflow->settings->custom_post_type_option( $this->module );	
	}

	public function settings_role_caps_option() {
        natsort($this->role_caps);
        foreach($this->role_caps as $key => $value) {
            echo '<label for="' . esc_attr( $this->module->workflow_options_name ) . '_' . esc_attr( $key ) . '">';
            echo '<input id="' . esc_attr( $this->module->workflow_options_name ) . '_' . esc_attr( $key ) . '" name="'
                . $this->module->workflow_options_name . '[role_caps][' . esc_attr( $key ) . ']"';
            if ( isset( $this->module->options->role_caps[$key] ) )
                checked( true, true );

            echo ' type="checkbox" />&nbsp;&nbsp;&nbsp;' . esc_html( $value ) . '</label>';
            echo '<br />';
        }
	
	}
    
	public function settings_validate( $new_options ) {
		if ( !isset( $new_options['post_types'] ) )
			$new_options['post_types'] = array();
        
		$new_options['post_types'] = $this->clean_post_type_options( $new_options['post_types'], $this->module->post_type_support );

		if ( isset( $new_options['role_caps'])) {
            foreach($new_options['role_caps'] as $key => $value) {
                if(!array_key_exists($key, $this->role_caps))
                    unset($new_options['role_caps'][$key]);
            }
            
            if(empty($new_options['role_caps']))
                unset($new_options['role_caps']);
        
            if(isset( $new_options['role_caps'])) {
                $options = $this->module->options->role_caps;
                $role =& get_role( self::role );

                foreach($options as $key => $value) {
                    $role->remove_cap( $key );
                }
                
                foreach($new_options['role_caps'] as $key => $value) {
                    $role->add_cap( $key );
                }
            }
        }

		return $new_options;

	}

	public function print_configure_view() {
		?>
		<form class="basic-settings" action="<?php echo esc_url( menu_page_url( $this->module->settings_slug, false ) ); ?>" method="post">
			<?php settings_fields( $this->module->workflow_options_name ); ?>
			<?php do_settings_sections( $this->module->workflow_options_name ); ?>
			<?php
				echo '<input id="cms-workflow-module-name" name="cms_workflow_module_name" type="hidden" value="' . esc_attr( $this->module->name ) . '" />';				
			?>
			<p class="submit"><?php submit_button( null, 'primary', 'submit', false ); ?></p>
		</form>
		<?php
	}	
	
    public function is_coauthor_for_post( $user, $post_id = 0 ) {
        global $post;

        if( ! $post_id && $post )
            $post_id = $post->ID;
        
        if( ! $post_id )
            return false;

        if ( ! $user )
            return false;

        $coauthors = $this->get_coauthors( $post_id );
        if ( is_numeric( $user ) ) {
            $user = get_userdata( $user );
            $user = $user->user_login;
        }

        foreach( $coauthors as $coauthor ) {
            if ( $user == $coauthor->user_login || $user == $coauthor->linked_account )
                return true;
        }
        return false;
    }    
        
}

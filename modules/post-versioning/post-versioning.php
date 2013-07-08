<?php

class Workflow_Post_Versioning extends Workflow_Module {

    public $module;
    
    private static $source_blog = null;
    
    public function __construct() {
		global $cms_workflow;
		
		$this->module_url = $this->get_module_url( __FILE__ );
        
		$args = array(
			'title' => __( 'Versionierung', CMS_WORKFLOW_TEXTDOMAIN ),
			'description' => __( 'Neue Version bzw. eine Kopie aus einem vorhandenen Dokument erstellen.', CMS_WORKFLOW_TEXTDOMAIN ),
			'module_url' => $this->module_url,
			'slug' => 'post-versioning',
			'default_options' => array(
				'post_types' => array(
					'post' => true,
					'page' => true
				),
                'network_posts_types' => array(),
                'network_connections' => array()
			),
			'configure_callback' => 'print_configure_view',
			'settings_help_tab' => array(
				'id' => 'workflow-post-versioning-overview',
				'title' => __('Übersicht', CMS_WORKFLOW_TEXTDOMAIN),
				'content' => __('<p></p>', CMS_WORKFLOW_TEXTDOMAIN),
				),
			'settings_help_sidebar' => __( '<p><strong>Für mehr Information:</strong></p><p><a href="http://blogs.fau.de/cms">Dokumentation</a></p><p><a href="http://blogs.fau.de/webworking">RRZE-Webworking</a></p><p><a href="https://github.com/RRZE-Webteam">RRZE-Webteam in Github</a></p>', CMS_WORKFLOW_TEXTDOMAIN ),
		);
        
		$this->module = $cms_workflow->register_module( 'post_versioning', $args );
	}
	
	public function init() {
		
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
        
        add_filter( 'post_row_actions', array( $this, 'filter_post_row_actions'), 10, 2);
        add_filter( 'page_row_actions', array( $this, 'filter_post_row_actions'), 10, 2);
        add_action( 'admin_action_copy_as_new_post_draft', array( $this, 'copy_as_new_post_draft'));
        add_action( 'admin_action_version_as_new_post_draft', array( $this, 'version_as_new_post_draft'));
        add_action( 'publish_page', array( $this, 'version_save_post'), 999, 2 );
        add_action( 'publish_post', array( $this, 'version_save_post'), 999, 2 );        
        add_action( 'admin_notices', array( $this, 'version_admin_notice' ) );
        add_filter( 'post_class', array( $this, 'filter_post_class' ), 10, 3 );
        
        if (is_multisite()) {
            add_action( 'add_meta_boxes', array( $this, 'network_connections_meta_box' ), 1);
            add_action( 'save_post', array( $this, 'network_connections_save_postmeta' ));
            
            add_action( 'post_submitbox_start', array( $this, 'network_connections_version_input') );
            add_action( 'save_post', array( $this, 'network_connections_save_post') );                   
        }
                
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function admin_enqueue_scripts( ) {
		wp_enqueue_style( 'workflow-post-versioning', $this->module_url . 'post-versioning.css', false, CMS_WORKFLOW_VERSION, 'all' );
	}
    
	public function register_settings() {
        add_settings_section( $this->module->workflow_options_name . '_general', false, '__return_false', $this->module->workflow_options_name );
        
        if (is_multisite()) {
            add_settings_field( 'post_types', __( 'Lokale Freigabe', CMS_WORKFLOW_TEXTDOMAIN ), array( $this, 'settings_post_types_option' ), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general' );
            
            add_settings_field( 'network_posts_types', __( 'Netzwerkweite Freigabe', CMS_WORKFLOW_TEXTDOMAIN ), array( $this, 'settings_network_posts_types_option' ), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general' );

            $connections = get_site_option( 'cms_workflow_site_connections', array() );

            $current_blog_id = get_current_blog_id();

            if(isset($connections[$current_blog_id]))
                unset($connections[$current_blog_id]);                    

            if( !empty( $connections ) )
                add_settings_field( 'network_connections', __( 'Vorhandenen Webseiten', CMS_WORKFLOW_TEXTDOMAIN ), array( $this, 'settings_network_connections_option' ), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general' );
	
        }
        
        else {
            add_settings_field( 'post_types', __( 'Freigabe', CMS_WORKFLOW_TEXTDOMAIN ), array( $this, 'settings_post_types_option' ), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general' );            
        }
    }
	
	public function settings_post_types_option() {
		global $cms_workflow;
		$cms_workflow->settings->custom_post_type_option( $this->module );	
	}

	public function settings_network_posts_types_option() {
		global $cms_workflow;
		$cms_workflow->settings->custom_post_type_option( $this->module, 'network_posts_types' );	
	}
    
	public function settings_network_connections_option() {	
        $connections = get_site_option( 'cms_workflow_site_connections', array() );
        
        $current_blog_id = get_current_blog_id();
        
        if(isset($connections[$current_blog_id]))
            unset($connections[$current_blog_id]);                    
        
        if( empty( $connections ) )
            return;
        
        foreach ( $connections as $blog_id => $data ) {
            if ( $current_blog_id === $blog_id )
                continue;

            switch_to_blog( $blog_id );
            $blog_name = get_bloginfo( 'name' );
            restore_current_blog();

            $connected = is_array( $this->module->options->network_connections ) && in_array( $blog_id, $this->module->options->network_connections ) ? true : false;
             ?>
            <label for="network_connections_<?php echo $blog_id; ?>">
                <input id="network_connections_<?php echo $blog_id; ?>" type="checkbox" <?php checked( $connected, true ); ?> name="<?php printf('%s[network_connections][]', $this->module->workflow_options_name); ?>" value="<?php echo $blog_id ?>" /> <?php echo $blog_name; ?>
            </label><br />
            <?php
        }
        ?>
            <p class="description"><?php _e('Lokale Dokumente können in diesen Webseiten als neue Version (Entwurf) dupliziert werden.'); ?></p>
        <?php
	}
    
	public function settings_validate( $new_options ) {
		$current_blog_id = get_current_blog_id();
        
		if ( !isset( $new_options['post_types'] ) )
			$new_options['post_types'] = array();
        
		$new_options['post_types'] = $this->clean_post_type_options( $new_options['post_types'], $this->module->post_type_support );
        
        if (is_multisite()) {
            
            if ( !isset( $new_options['network_posts_types'] ) )
                $new_options['network_posts_types'] = array();

            if ( !isset( $new_options['network_connections'] ) )
                $new_options['network_connections'] = array();

            $new_options['network_posts_types'] = $this->clean_post_type_options( $new_options['network_posts_types'], $this->module->post_type_support );
        
            $all_blogs = get_site_option( 'cms_workflow_site_connections' );

            if ( ! $all_blogs )
                $all_blogs = array( );

            $current_connections = $this->module->options->network_connections;

            if ( ! is_array( $current_connections ) )
                $current_connections = array( );

            $new_connections = isset($new_options[ 'network_connections' ]) ? $new_options[ 'network_connections' ] : array();

            foreach ( $all_blogs as $blog_id => $blog_data ) {

                if ( $current_blog_id == $blog_id )
                    continue;

                $blog_details = get_blog_details( $blog_id );
                if ( empty($blog_details) )
                    continue;

                $key = array_search( $blog_id, $current_connections );

                if ( in_array( $blog_id, $new_connections ) ) {

                    if ( $key === false )
                        $current_connections[] = $blog_id;

                } else {

                    if ( $key !== false && isset( $current_connections[ $key ] ) )
                        unset( $current_connections[ $key ] );
                }

            }

            $new_options[ 'network_connections' ] = $current_connections;

            if(isset($all_blogs[$current_blog_id]))
                unset($all_blogs[$current_blog_id]);                    

            if(array_search( true, $new_options['network_posts_types'] ) !== false) {
                foreach($new_options['network_posts_types'] as $key => $value ) {
                    $all_blogs[$current_blog_id][ $key ] = $value;
                }
            }

            update_site_option( 'cms_workflow_site_connections', $all_blogs );

            self::update_site_connections();
            
        }
        
		return $new_options;
	}	

    private static function update_site_connections() {
        $all_blogs = get_site_option( 'cms_workflow_site_connections' );

        if ( ! $all_blogs )
            $all_blogs = array();

        $cleanup_blogs = array();

        foreach ( $all_blogs as $blog_id => $blog_data ) {
            
            $blog_details = get_blog_details( $blog_id );
            if ( empty( $blog_details ) )
                $cleanup_blogs[] = $blog_id;
        }

        if ( count( $cleanup_blogs ) > 0 ) {

            foreach ( $all_blogs as $blog_id => $blog_data ) {

                foreach ( $cleanup_blogs as $blog_to_clean ) {

                    $blog_options = get_blog_option( $blog_id, $this->module->workflow_options_name . '_options' );
                    $current_connections = $blog_options ? $blog_options->network_connections : array();
                    
                    if ( count( $current_connections ) > 1 ) {
                        $key = array_search( $blog_to_clean, $current_connections );

                        if ( $key !== false && isset( $current_connections[ $key ] ) )
                            unset( $current_connections[ $key ] );
                        
                        $blog_options->network_connections = $current_connections;
                        update_blog_option( $blog_id, $this->module->workflow_options_name . '_options', $blog_options );
                    }
                }
            }

            foreach ( $cleanup_blogs as $blog_to_clean ) {

                if ( array_key_exists( $blog_to_clean, $all_blogs ) )
                    unset( $all_blogs[ $blog_to_clean ] );

            }
            
            update_site_option( 'cms_workflow_site_connections', $all_blogs );
        }

    }
    
	public function print_configure_view() {        
		?>
		<form class="basic-settings" action="<?php echo esc_url( menu_page_url( $this->module->settings_slug, false ) ); ?>" method="post">
            <?php echo '<input id="cms_workflow_module_name" name="cms_workflow_module_name" type="hidden" value="' . esc_attr( $this->module->name ) . '" />'; ?>
			<?php settings_fields( $this->module->workflow_options_name ); ?>
			<?php do_settings_sections( $this->module->workflow_options_name ); ?>
			<p class="submit"><?php submit_button( null, 'primary', 'submit', false ); ?></p>
		</form>
		<?php
	}	
	
    public static function filter_post_row_actions($actions, $post) {
        
        if (current_user_can('edit_posts')) {
            
            $actions['edit_as_new_draft'] = '<a href="'. admin_url( 'admin.php?action=copy_as_new_post_draft&amp;post='.$post->ID ) .'" title="'
            . esc_attr(__('Dieses Element als neuer Entwurf kopieren', CMS_WORKFLOW_TEXTDOMAIN))
            . '">' .  __('Kopieren', CMS_WORKFLOW_TEXTDOMAIN) . '</a>';
            
            if( $post->post_status == 'publish' )
                $actions['edit_as_version'] = '<a href="'. admin_url( 'admin.php?action=version_as_new_post_draft&amp;post='.$post->ID ) .'" title="'
                . esc_attr(__('Dieses Element als neue Version duplizieren', CMS_WORKFLOW_TEXTDOMAIN))
                . '">' .  __('Neue Version', CMS_WORKFLOW_TEXTDOMAIN) . '</a>';
            
        }
        
        return $actions;
    }

    public static function version_as_new_post_draft() {
        self::version_as_new_post();
    }    
    
    private static function version_as_new_post() {
        if (!current_user_can('edit_posts'))
            wp_die(__('Sie haben nicht die erforderlichen Rechte, um eine neue Version zu erstellen.', CMS_WORKFLOW_TEXTDOMAIN));

        if (! ( isset( $_GET['post']) || isset( $_POST['post']) ) ) {
            wp_die(__('Es wurde kein Element geliefert.', CMS_WORKFLOW_TEXTDOMAIN));
        }
        
        $post_id = (int) isset($_GET['post']) ? $_GET['post'] : $_POST['post'];
        $post = get_post( $post_id, ARRAY_A );

        if (is_null($post))
            wp_die(__('Es wurde kein Element mit der angegebenen ID gefunden.', CMS_WORKFLOW_TEXTDOMAIN));
        
        if( $post['post_status'] != 'publish' )
            wp_die(__('Nur veröffentlichte Dokumente können als neue Version erstellt werden.', CMS_WORKFLOW_TEXTDOMAIN));
        
        unset( $post['ID'] );
        $post['post_status'] = 'draft';
        
        $draft_id = wp_insert_post( $post );

        $keys = get_post_custom_keys( $post_id );
        $custom_field = array();
        foreach ( (array) $keys as $key ) {
            if ( preg_match( '/_wp_old_slug/', $key ) )
                continue;

            $values = get_post_custom_values($key, $post_id );
            foreach ( $values as $value ) {
                add_post_meta( $draft_id, $key, $value );
            }
        }

        $args = array( 'post_type' => 'attachment', 'numberposts' => -1, 'post_status' => null, 'post_parent' => $post_id ); 
        $attachments = get_posts( $args );
        if ($attachments) {
            foreach ( $attachments as $attachment ) {
                $new = array(
                    'post_author' => $attachment->post_author,
                    'post_date' => $attachment->post_date,
                    'post_date_gmt' => $attachment->post_date_gmt,
                    'post_content' => $attachment->post_content,
                    'post_title' => $attachment->post_title,
                    'post_excerpt' => $attachment->post_excerpt,
                    'post_status' => $attachment->post_status,
                    'comment_status' => $attachment->comment_status,
                    'ping_status' => $attachment->ping_status,
                    'post_password' => $attachment->post_password,
                    'post_name' => $attachment->post_name,
                    'to_ping' => $attachment->to_ping,
                    'pinged' => $attachment->pinged,
                    'post_modified' => $attachment->post_modified,
                    'post_modified_gmt' => $attachment->post_modified_gmt,
                    'post_content_filtered' => $attachment->post_content_filtered,
                    'post_parent' => $draft_id,
                    'guid' => $attachment->guid,
                    'menu_order' => $attachment->menu_order,
                    'post_type' => $attachment->post_type,
                    'post_mime_type' => $attachment->post_mime_type,
                    'comment_count' => $attachment->comment_count
                );

                $attachment_newid = wp_insert_post( $new );
                $keys = get_post_custom_keys( $attachment->ID );

                $custom_field = array();
                foreach ( (array) $keys as $key ) {
                    $value = get_post_meta( $attachment->ID, $key, true );

                    add_post_meta( $attachment_newid, $key, $value );
                }
            }
        }

        $taxonomies = get_object_taxonomies( $post['post_type'] );
        foreach ($taxonomies as $taxonomy) {
            $post_terms = wp_get_object_terms($post_id, $taxonomy, array( 'orderby' => 'term_order' ));
            $terms = array();

            for ($i = 0; $i < count($post_terms); $i++) {
                $terms[] = $post_terms[$i]->slug;
            }

            wp_set_object_terms($draft_id, $terms, $taxonomy);
        }

        add_post_meta($draft_id, '_version_post_id', $post_id);

        wp_safe_redirect( admin_url( 'post.php?post=' . $draft_id . '&action=edit' ) );
        exit;      
    }
    
    public static function version_save_post( $post_id, $post ) {
        $org_id = get_post_meta( $post_id, '_version_post_id', true );
                
        if ( $org_id ) {
            $new = array(
                'ID' => $org_id,
                'post_author' => $post->post_author,
                'post_date' => $post->post_date,
                'post_date_gmt' => $post->post_date_gmt,
                'post_content' => $post->post_content,
                'post_title' => $post->post_title,
                'post_excerpt' => $post->post_excerpt,
                'post_status' => 'publish',
                'comment_status' => $post->comment_status,
                'ping_status' => $post->ping_status,
                'post_password' => $post->post_password,
                //'post_name' => $post->post_name,
                'to_ping' => $post->to_ping,
                'pinged' => $post->pinged,
                'post_modified' => $post->post_modified,
                'post_modified_gmt' => $post->post_modified_gmt,
                'post_content_filtered' => $post->post_content_filtered,
                'post_parent' => $post->post_parent,
                'guid' => $post->guid,
                'menu_order' => $post->menu_order,
                'post_type' => $post->post_type,
                'post_mime_type' => $post->post_mime_type
            );
            
            wp_update_post($new);

            $keys = get_post_custom_keys( $post_id );

            $custom_field = array();
            foreach ( (array) $keys as $key ) {
                if ( preg_match( '/_wp_old_slug/', $key ) )
                    continue;

                if ( preg_match( '/_version_post_id/', $key ) )
                    continue;
                                
                $key = apply_filters( 'draft_to_publish_postmeta_filter', $key );

                delete_post_meta( $org_id, $key );
                $values = get_post_custom_values($key, $post_id );
                foreach ( $values as $value ) {
                    add_post_meta( $org_id, $key, $value );
                }
            }

            $args = array( 'post_type' => 'attachment', 'numberposts' => -1, 'post_status' => null, 'post_parent' => $post_id ); 
            $attachments = get_posts( $args );
            if ($attachments) {
                foreach ( $attachments as $attachment ) {
                    $new = array(
                        'post_author' => $attachment->post_author,
                        'post_date' => $attachment->post_date,
                        'post_date_gmt' => $attachment->post_date_gmt,
                        'post_content' => $attachment->post_content,
                        'post_title' => $attachment->post_title,
                        'post_excerpt' => $attachment->post_excerpt,
                        'post_status' => $attachment->post_status,
                        'comment_status' => $attachment->comment_status,
                        'ping_status' => $attachment->ping_status,
                        'post_password' => $attachment->post_password,
                        'post_name' => $attachment->post_name,
                        'to_ping' => $attachment->to_ping,
                        'pinged' => $attachment->pinged,
                        'post_modified' => $attachment->post_modified,
                        'post_modified_gmt' => $attachment->post_modified_gmt,
                        'post_content_filtered' => $attachment->post_content_filtered,
                        'post_parent' => $draft_id,
                        'guid' => $attachment->guid,
                        'menu_order' => $attachment->menu_order,
                        'post_type' => $attachment->post_type,
                        'post_mime_type' => $attachment->post_mime_type,
                        'comment_count' => $attachment->comment_count
                    );
                    
                    $attachment_newid = wp_insert_post( $new );
                    $keys = get_post_custom_keys( $attachment->ID );

                    $custom_field = array();
                    foreach ( (array) $keys as $key ) {
                        $value = get_post_meta( $attachment->ID, $key, true );

                        delete_post_meta( $org_id, $key );
                        add_post_meta( $org_id, $key, $value );
                    }
                }
            }

            $taxonomies = get_object_taxonomies( $post->post_type );
            foreach ($taxonomies as $taxonomy) {
                $post_terms = wp_get_object_terms($post_id, $taxonomy, array( 'orderby' => 'term_order' ));
                $terms = array();
                for ($i=0; $i<count($post_terms); $i++) {
                    $terms[] = $post_terms[$i]->slug;
                }
                wp_set_object_terms($org_id, $terms, $taxonomy);
            }

            wp_delete_post( $post_id, true ); // Bypass trash and force deletion (default: false)
            wp_safe_redirect( admin_url( '/post.php?post=' . $org_id . '&action=edit&message=1' ) );
            exit;
        }
    }
    
    public static function version_admin_notice() {
        if ( isset($_REQUEST['post']) ) {           
            $post_id = $_REQUEST['post'];
            $old_post_id = get_post_meta( $post_id, '_version_post_id', true );                    
            if ( $old_post_id ) {
                echo '<div class="updated fade"><p>' . sprintf( __( "Neue Version vom Dokument <a href='%s' target='__blank'>%s</a>. Überschreiben Sie dem ursprünglichen Dokument, indem Sie auf &bdquo;Veröffentlichen&rdquo; klicken.", CMS_WORKFLOW_TEXTDOMAIN ),  get_permalink($old_post_id), $old_post_id ) . '</p></div>';
            }
        }
    }
    
    public static function copy_as_new_post_draft() {
        self::copy_as_new_post();
    }    
    
    private static function copy_as_new_post() {
        if (!current_user_can('edit_posts')) 
            wp_die(__('Sie haben nicht die erforderlichen Rechte, um eine neue Version zu erstellen.', CMS_WORKFLOW_TEXTDOMAIN));

        if (! ( isset( $_GET['post']) || isset( $_POST['post']) ) ) {
            wp_die(__('Es wurde kein Element geliefert.', CMS_WORKFLOW_TEXTDOMAIN));
        }
        
        $post_id = (int) isset($_GET['post']) ? $_GET['post'] : $_POST['post'];
        $post = get_post( $post_id );

        if (is_null($post))
            wp_die(__('Es wurde kein Element mit der angegebenen ID gefunden.', CMS_WORKFLOW_TEXTDOMAIN));

        if (in_array($post->post_type, array('revision', 'attachment')))
            wp_die(__('Sie haben versucht ein Element zu bearbeiten, das nicht erlaubt ist. Bitte kehren Sie zurück und versuchen Sie es erneut.', CMS_WORKFLOW_TEXTDOMAIN));
        
        $new_post_author = wp_get_current_user();

        $new_post = array(
            'menu_order' => $post->menu_order,
            'comment_status' => $post->comment_status,
            'ping_status' => $post->ping_status,
            'post_author' => $new_post_author->ID,
            'post_content' => $post->post_content,
            'post_excerpt' => $post->post_excerpt,
            'post_mime_type' => $post->post_mime_type,
            'post_parent' => $post->post_parent,
            'post_password' => $post->post_password,
            'post_status' => 'draft',
            'post_title' => $post->post_title,
            'post_type' => $post->post_type,
        );

        $draft_id = wp_insert_post($new_post);

        add_post_meta($draft_id, '_original_post_id', $post_id);

        wp_safe_redirect( admin_url( 'post.php?post=' . $draft_id . '&action=edit' ) );
        exit;
        
    }
    
    public function filter_post_class( $classes, $class, $post_id ) {
        if( get_post_meta($post_id, '_version_post_id', true) )
            $classes[] = 'version';

        return $classes;
    }    
    
    public function network_connections_meta_box() {
        $connections = $this->module->options->network_connections;
        
        $current_blog_id = get_current_blog_id();
        
        if( empty( $connections ) )
            return;      
        
		$allowed_post_types = $this->get_post_types( $this->module );
		foreach ( $allowed_post_types as $post_type ) {
            add_meta_box('network-connections', __( 'Netzwerkweit Versionierung', CMS_WORKFLOW_TEXTDOMAIN ), array( $this, 'network_connections_inner_box'), $post_type, 'side', 'high');
        }
    }

    public function network_connections_inner_box( $post ) {

        wp_nonce_field( plugin_basename( __FILE__ ), 'myplugin_noncename' );
        
        $network_connections = $this->module->options->network_connections;
        
        $connections = get_post_meta( $post->ID, $this->module->workflow_options_name . '_network_connections' );
        if( !empty($connections))
            $connections = (array) array_shift(array_values($connections));
        
        $current_blog_id = get_current_blog_id();
        
        if( empty( $network_connections ) )
            return;      
        ?>
        <ul id="page_connections_checklist" class="form-no-clear">
        <?php
        foreach ( $network_connections as $key => $blog_id ) :
            if ( $current_blog_id === $blog_id )
                continue;

            switch_to_blog( $blog_id );
            $blog_name = get_bloginfo( 'name' );
            restore_current_blog();

            $connected = in_array( $blog_id, $connections ) ? true : false;        
             ?>
            <li id="network_connections_<?php echo $blog_id; ?>"><label class="selectit"><input id="connected_blog_<?php echo $blog_id; ?>" type="checkbox" <?php checked( $connected, true ); ?> name="network_connections[]" value="<?php echo $blog_id ?>" /> <?php echo $blog_name; ?></label></li>
        <?php
        endforeach;
        ?>
        </ul>  
        <p class="howto"><?php _e( 'Das Dokument kann in diesen Webseiten als neue Version (Entwurf) dupliziert werden.', CMS_WORKFLOW_TEXTDOMAIN ); ?></p>
        <?php
    }
    
    public function network_connections_save_postmeta( $post_id ) {

        if ( isset($_POST['post_type']) && 'page' == $_POST['post_type'] ) {
            if ( ! current_user_can( 'edit_page', $post_id ) )
                return;
        } else {
            if ( ! current_user_can( 'edit_post', $post_id ) )
                return;
        }

        if ( ! isset($_POST['myplugin_noncename']) || ! wp_verify_nonce( $_POST['myplugin_noncename'], plugin_basename( __FILE__ ) ) )
            return;

        $connections = isset($_POST['network_connections']) ? $_POST['network_connections'] : array();        
        $network_connections = $this->module->options->network_connections;
        foreach($network_connections as $key => $value) {
            if(!in_array( $value, $connections ))
                 unset($network_connections[$key]);
        }
        
        update_post_meta($_POST['post_ID'], $this->module->workflow_options_name . '_network_connections', $network_connections);
    }    
    
    public function network_connections_version_input() {
        global $post;

        if( in_array( $post->post_status, array('publish', 'future', 'private') ) && 0 != $post->ID && !empty($this->module->options->network_connections) ): ?>      
        <p>
            <input type="checkbox" id="network_connections_version" name="network_connections_version" <?php checked( false, true ); ?> />
            <label for="network_connections_version"><?php _e( 'Netzwerkweit Versionierung', CMS_WORKFLOW_TEXTDOMAIN ); ?></label>
        </p>
        <?php endif;
    }
    
    public function network_connections_save_post( $post_id ) {

        $post_status = get_post_status( $post_id );
        if ( $post_status != 'publish' )
            return;

        if ( is_null(self::$source_blog) )
            self::$source_blog = get_current_blog_id();
        else
            return;

        if ( ! isset( $_POST[ 'network_connections_version' ] ) )
            return;

        $postdata = get_post( $post_id, ARRAY_A );

        if ( 'post' != $postdata[ 'post_type'] && 'page' != $postdata[ 'post_type'] ) 
            return;

        $file = '';

        if ( current_theme_supports( 'post-thumbnails' ) ) {
            $thumb_id = get_post_thumbnail_id( $post_id );
            if ( $thumb_id > 0 ) {
                $path = wp_upload_dir();
                $file = get_post_meta( $thumb_id, '_wp_attached_file', true );
                $fileinfo = pathinfo( $file );
            }
        }

        $newpost = array(
            'post_title'	=> $postdata[ 'post_title' ],
            'post_content'	=> $postdata[ 'post_content' ],
            'post_status'	=> 'draft',
            'post_author'	=> $postdata[ 'post_author' ],
            'post_excerpt'	=> $postdata[ 'post_excerpt' ],
            'post_date'		=> $postdata[ 'post_date' ],
            'post_type'		=> $postdata[ 'post_type' ]
        );

        $blogs = get_post_meta( $post_id, $this->module->workflow_options_name . '_network_connections' );
        if( !empty($blogs))
            $blogs = array_shift(array_values($blogs));

        if ( ! ( count( $blogs ) > 0 ) )
            return;

        foreach ( $blogs as $key => $blogid ) {

            if ( $blogid != self::$source_blog ) {

                switch_to_blog( $blogid );

                $remote_post_id = wp_insert_post( $newpost );

                if ( $file != '' ) {
                    include_once ( ABSPATH . 'wp-admin/includes/image.php' );
                    if ( count( $fileinfo ) > 0 ) {

                        $filedir = wp_upload_dir();
                        $filename = wp_unique_filename( $filedir[ 'path' ], $fileinfo[ 'basename' ] );
                        $copy = copy( $path[ 'basedir' ] . '/' . $file, $filedir[ 'path' ] . '/' . $filename );

                        if ( $copy ) {
                            unset( $postdata[ 'ID' ] );
                            $wp_filetype = wp_check_filetype( $filedir[ 'url' ] . '/' . $filename );
                            $attachment = array(
                                'post_mime_type'	=> $wp_filetype[ 'type' ],
                                'guid'				=> $filedir[ 'url' ] . '/' . $filename,
                                'post_parent'		=> $remote_post_id,
                                'post_title'		=> '',
                                'post_excerpt'		=> '',
                                'post_author'		=> $postdata[ 'post_author' ],
                                'post_content'		=> '',
                            );

                            $attach_id = wp_insert_attachment( $attachment, $filedir[ 'path' ] . '/' . $filename );
                            if ( ! is_wp_error( $attach_id ) ) {
                                wp_update_attachment_metadata(
                                    $attach_id, wp_generate_attachment_metadata( $attach_id, $filedir[ 'path' ] . '/' . $filename )
                                );
                                set_post_thumbnail( $remote_post_id, $attach_id );
                            }
                        }
                    }
                }

                restore_current_blog();
            }
        }
    }
    
}

<?php

class Workflow_Editors extends Workflow_Module {
	
    const role = 'editor';
    
    private $wp_role_caps = array(); 
    
    private $more_role_caps = array();

    public $role_caps = array();
    
	public $module;
    
	public function __construct () {
		global $cms_workflow;
        
		$this->module_url = $this->get_module_url( __FILE__ );
        
        $this->wp_role_caps = array( 
            'moderate_comments' => __('Kommentare moderieren', CMS_WORKFLOW_TEXTDOMAIN),
            'manage_categories' => __('Taxonomien verwalten', CMS_WORKFLOW_TEXTDOMAIN),
            'manage_links' => __('Links verwalten', CMS_WORKFLOW_TEXTDOMAIN),
            'edit_others_posts' => __('Andere Beiträge bearbeiten', CMS_WORKFLOW_TEXTDOMAIN),
            'edit_pages' => __('Seiten bearbeiten', CMS_WORKFLOW_TEXTDOMAIN),
            'edit_others_pages' => __('Andere Seiten bearbeiten', CMS_WORKFLOW_TEXTDOMAIN),
            'edit_published_pages' => __('Veröffentlichte Seiten bearbeiten', CMS_WORKFLOW_TEXTDOMAIN),
            'publish_pages' => __('Seiten veröffentlichen', CMS_WORKFLOW_TEXTDOMAIN),
            'delete_pages' => __('Seiten löschen', CMS_WORKFLOW_TEXTDOMAIN),
            'delete_others_pages' => __('Andere Seiten löschen', CMS_WORKFLOW_TEXTDOMAIN),
            'delete_published_pages' => __('Veröffentlichte Seiten löschen', CMS_WORKFLOW_TEXTDOMAIN),
            'delete_others_posts' => __('Andere Beiträge löschen', CMS_WORKFLOW_TEXTDOMAIN),
            'delete_private_posts' => __('Private Beiträge löschen', CMS_WORKFLOW_TEXTDOMAIN),
            'edit_private_posts' => __('Private Beiträge bearbeiten', CMS_WORKFLOW_TEXTDOMAIN),
            'read_private_posts' => __('Private Beiträge lesen', CMS_WORKFLOW_TEXTDOMAIN),
            'delete_private_pages' => __('Private Seiten löschen', CMS_WORKFLOW_TEXTDOMAIN),
            'edit_private_pages' => __('Private Seiten bearbeiten', CMS_WORKFLOW_TEXTDOMAIN),
            'read_private_pages' => __('Private Seiten lesen', CMS_WORKFLOW_TEXTDOMAIN),
            'edit_published_posts' => __('Veröffentlichte Beiträge bearbeiten', CMS_WORKFLOW_TEXTDOMAIN),
            'upload_files' => __('Dateien hochladen', CMS_WORKFLOW_TEXTDOMAIN),
            'publish_posts' => __('Beiträge veröffentlichen', CMS_WORKFLOW_TEXTDOMAIN),
            'delete_published_posts' => __('Veröffentlichte Beiträge löschen', CMS_WORKFLOW_TEXTDOMAIN),
            'edit_posts' => __('Beiträge bearbeiten', CMS_WORKFLOW_TEXTDOMAIN),
            'delete_posts' => __('Beiträge löschen', CMS_WORKFLOW_TEXTDOMAIN)
        );

        $this->more_role_caps = array( 
            'edit_theme_options' => __('Design bearbeiten', CMS_WORKFLOW_TEXTDOMAIN)
        );

        $content_help_tab = array(
            '<p>'. __('Verwenden Sie die Redakteureverwaltung, um die Rechte für Redakteure detaillierter vergeben zu könnnen.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>'. __('Ist die Redakteureverwaltung nicht aktiviert, erhalten Redakteure die standardmäßig von WordPress vorgegebenen Rechte.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>' 
        );
        
		$args = array(
			'title' => __( 'Redakteure', CMS_WORKFLOW_TEXTDOMAIN ),
			'description' => __( 'Verwaltung der Redakteure.', CMS_WORKFLOW_TEXTDOMAIN ),
			'module_url' => $this->module_url,
			'slug' => 'editors',
			'default_options' => array(
                'role_caps' => array(
                    'moderate_comments' => true,
                    'manage_categories' => true,
                    'manage_links' => true,                    
                    'edit_others_posts' => true,
                    'edit_pages' => true,
                    'edit_others_pages' => true,
                    'edit_published_pages' => true,
                    'publish_pages' => true,
                    'delete_pages' => true,
                    'delete_others_pages' => true,
                    'delete_published_pages' => true,
                    'delete_others_posts' => true,
                    'delete_private_posts' => true,
                    'edit_private_posts' => true,
                    'read_private_posts' => true,
                    'delete_private_pages' => true,
                    'edit_private_pages' => true,
                    'read_private_pages' => true,
                    'edit_published_posts' => true,
                    'upload_files' => true,
                    'publish_posts' => true,
                    'delete_published_posts' => true,
                    'edit_posts' => true,
                    'delete_posts' => true
                )
			),
			'configure_callback' => 'print_configure_view',
			'settings_help_tab' => array(
				'id' => 'workflow-editors-overview',
				'title' => __('Übersicht', CMS_WORKFLOW_TEXTDOMAIN),
				'content' => implode(PHP_EOL, $content_help_tab),
				),
			'settings_help_sidebar' => __( '<p><strong>Für mehr Information:</strong></p><p><a href="http://blogs.fau.de/cms">Dokumentation</a></p><p><a href="http://blogs.fau.de/webworking">RRZE-Webworking</a></p><p><a href="https://github.com/RRZE-Webteam">RRZE-Webteam in Github</a></p>', CMS_WORKFLOW_TEXTDOMAIN ),
		);
        
		$this->module = $cms_workflow->register_module( 'editors', $args );
	}
	
	public function init() {
        
        $this->set_role_caps();
        
		add_action( 'admin_init', array( $this, 'register_settings' ) );
        
	}
	
    public function deactivation() {
        $role = get_role( self::role );

        $role_caps = array_keys($this->module->options->role_caps);
        
        foreach($role_caps as $cap) {
            $role->remove_cap( $cap );
        }

        $wp_role_caps = array_keys($this->wp_role_caps);
        
        foreach($wp_role_caps as $cap) {
            $role->add_cap( $cap );
        }
        
    }
  
    public function activation() {
        global $cms_workflow;

        if (empty( $this->module->options->role_caps ) ) {
            
            $this->module->options->role_caps = array_map(function($item) { return true; }, $this->wp_role_caps);
            $cms_workflow->update_module_option( $this->module->name, 'role_caps', $this->module->options->role_caps );
            
        } else {
            
            $this->role_caps = array_merge($this->wp_role_caps, $this->more_role_caps);
            
            $role = get_role( self::role );

            $role_caps = array_keys($this->role_caps);
            
            foreach($role_caps as $cap) {
                $role->remove_cap( $cap );
            }

            $role_caps = array_keys($this->module->options->role_caps);
            
            foreach($role_caps as $cap) {
                $role->add_cap( $cap );
            }
        }
        
    }
    
    public function set_role_caps() {
        $this->role_caps = array_merge($this->wp_role_caps, $this->more_role_caps);

        $role = get_role( self::role );

        $role_caps = array_keys($this->role_caps);

        foreach($role_caps as $cap) {
            $role->remove_cap( $cap );
        }

        $role_caps = array_keys($this->module->options->role_caps);

        foreach($role_caps as $cap) {
            $role->add_cap( $cap );
        }
        
        $all_post_types = $this->get_available_post_types();
        
        $this->role_caps = array_merge($this->wp_role_caps, $this->more_role_caps);
        
        foreach( $all_post_types as $post_type => $args ) {
            
            $label = $args->label;
            
            if($post_type != $args->capability_type && isset($all_post_types[$args->capability_type]))
                $label = $all_post_types[$args->capability_type]->label;

            if(isset($args->cap->edit_posts))
                $this->role_caps[$args->cap->edit_posts] = sprintf(__('%s bearbeiten', CMS_WORKFLOW_TEXTDOMAIN), $label);

            if(isset($args->cap->publish_posts))
                $this->role_caps[$args->cap->publish_posts] = sprintf(__('%s veröffentlichen', CMS_WORKFLOW_TEXTDOMAIN), $label);

            if(isset($args->cap->delete_posts))
                $this->role_caps[$args->cap->delete_posts] = sprintf(__('%s löschen', CMS_WORKFLOW_TEXTDOMAIN), $label);

            if(isset($args->cap->edit_published_posts))
                $this->role_caps[$args->cap->edit_published_posts] = sprintf(__('Veröffentlichte %s bearbeiten', CMS_WORKFLOW_TEXTDOMAIN), $label);

            if(isset($args->cap->delete_published_posts))
                $this->role_caps[$args->cap->delete_published_posts] = sprintf(__('Veröffentlichte %s löschen', CMS_WORKFLOW_TEXTDOMAIN), $label);

            if(isset($args->cap->edit_others_posts))
                $this->role_caps[$args->cap->edit_others_posts] = sprintf(__('Andere %s bearbeiten', CMS_WORKFLOW_TEXTDOMAIN), $label);

            if(isset($args->cap->delete_others_posts))
                $this->role_caps[$args->cap->delete_others_posts] = sprintf(__('Andere %s löschen', CMS_WORKFLOW_TEXTDOMAIN), $label);

            if(isset($args->cap->edit_private_posts))
                $this->role_caps[$args->cap->edit_private_posts] = sprintf(__('Private %s bearbeiten', CMS_WORKFLOW_TEXTDOMAIN), $label);

            if(isset($args->cap->delete_private_posts))
                $this->role_caps[$args->cap->delete_private_posts] = sprintf(__('Private %s löschen', CMS_WORKFLOW_TEXTDOMAIN), $label);

            if(isset($args->cap->read_private_posts))
                $this->role_caps[$args->cap->read_private_posts] = sprintf(__('Private %s lesen', CMS_WORKFLOW_TEXTDOMAIN), $label);

        }
        
    }
            
	public function register_settings() {
			add_settings_section( $this->module->workflow_options_name . '_general', false, '__return_false', $this->module->workflow_options_name );
            add_settings_field( 'role_caps', __( 'Redakteurerechte', CMS_WORKFLOW_TEXTDOMAIN ), array( $this, 'settings_role_caps_option' ), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general' );
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
		if ( isset( $new_options['role_caps'])) {
            foreach($new_options['role_caps'] as $key => $value) {
                if(!array_key_exists($key, $this->role_caps))
                    unset($new_options['role_caps'][$key]);
            }
            
            if(empty($new_options['role_caps']))
                unset($new_options['role_caps']);
        
            if(isset( $new_options['role_caps'])) {
                $role = get_role( self::role );
                $role_caps = array_keys($this->module->options->role_caps);
                
                foreach($role_caps as $cap) {
                    $role->remove_cap( $cap );
                }
                
                $new_role_caps = array_keys($new_options['role_caps']);
                
                foreach($new_role_caps as $cap) {
                    $role->add_cap( $cap );
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
	        
}

<?php
/**
 * Plugin Name: CMS-Workflow
 * Description: Redaktioneller Workflow.
 * Version: 1.1
 * Author: Rolf v. d. Forst
 * Author URI: http://blogs.fau.de/webworking/
 * License: GPLv2 or later
 */

/*
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

add_action( 'plugins_loaded', array( 'CMS_Workflow', 'instance' ) );

register_activation_hook( __FILE__, array( 'CMS_Workflow', 'activation_hook' ) );

register_deactivation_hook( __FILE__, array( 'CMS_Workflow', 'deactivation_hook' ) );

class CMS_Workflow {
    
    const version = '1.1'; // Plugin-Version
        
    const textdomain = 'cms-workflow';
    
    const php_version = '5.3'; // Minimal erforderliche PHP-Version

    const wp_version = '3.6'; // Minimal erforderliche WordPress-Version
    
	public $workflow_options = '_cms_workflow_';
    
	public $workflow_options_name = '_cms_workflow_options';

	private static $instance;

	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new CMS_Workflow;
			self::$instance->init();

			global $cms_workflow;
			$cms_workflow = self::$instance;
		}
        
		return self::$instance;
	}

    public static function activation_hook($networkwide) {
        self::verify_requirements(); 
    }
    
    public static function deactivation_hook($networkwide) {
        self::register_hook('deactivation', $networkwide);
    }
    
    public static function verify_requirements() {
        $error = '';
        
        if ( version_compare( PHP_VERSION, self::php_version, '<' ) )
            $error = sprintf( __( 'Ihre PHP-Version %s ist veraltet. Bitte aktualisieren Sie mindestens auf die PHP-Version %s.', self::textdomain ), PHP_VERSION, self::php_version );

        if ( version_compare( $GLOBALS['wp_version'], self::wp_version, '<' ) )
            $error = sprintf( __( 'Ihre Wordpress-Version %s ist veraltet. Bitte aktualisieren Sie mindestens auf die Wordpress-Version %s.', self::textdomain ), $GLOBALS['wp_version'], self::wp_version );

        if( ! empty( $error ) ) {
            deactivate_plugins( plugin_basename( __FILE__ ), false, true );
            wp_die( $error );
        }
        
    }
        
    private static function register_hook($register, $networkwide) {
        global $wpdb, $cms_workflow;
        
        if (is_multisite() && $networkwide) {
 
            $old_blog = $wpdb->blogid;
            $blogids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}");

            foreach ($blogids as $blog_id) {
                switch_to_blog($blog_id);
                $cms_workflow->$register(true);
            }

            switch_to_blog($old_blog);

            return;
  
        } 
        
        $cms_workflow->$register(false);
    }

    private function deactivation($networkwide) {
        foreach ( $this->modules as $mod_name => $mod_data ) {
            if ( $mod_data->options->activated ) {
                foreach ( $this->modules as $mod_name => $mod_data ) {
                    if ( method_exists( $this->$mod_name, 'deactivation' ) )
                        $this->$mod_name->deactivation($networkwide);
                }
            } 
        }
    }
    
	private function init() {
        
        $this->modules = new stdClass();
        
        add_action( 'init', array( $this, 'set_plugin' ) );
		add_action( 'init', array( $this, 'set_modules' ) );
		add_action( 'init', array( $this, 'set_post_types' ) );

		add_action( 'admin_init', array( $this, 'admin_init' ) );

	}
    
    public function set_plugin() {
        
        define( 'CMS_WORKFLOW_VERSION' , self::version );
        define( 'CMS_WORKFLOW_TEXTDOMAIN' , self::textdomain );
        define( 'CMS_WORKFLOW_ROOT' , dirname(__FILE__) );
        define( 'CMS_WORKFLOW_FILE_PATH' , CMS_WORKFLOW_ROOT . '/' . basename(__FILE__) );
        define( 'CMS_WORKFLOW_URL' , plugins_url( '/', __FILE__ ) );
        define( 'CMS_WORKFLOW_SETTINGS_PAGE' , add_query_arg( 'page', 'workflow-settings', get_admin_url( null, 'admin.php' ) ) );

        load_plugin_textdomain( CMS_WORKFLOW_TEXTDOMAIN, false, sprintf( '%s/languages/', dirname( plugin_basename( __FILE__ ) ) ) );
    }
    
	public function set_modules() {

		$this->load_modules();
		
		$this->load_module_options();
		
		foreach ( $this->modules as $mod_name => $mod_data ) {
			if ( !isset( $mod_data->options->activated ) ) {
				if ( method_exists( $this->$mod_name, 'activation' ) )
					$this->$mod_name->activation();
                				
                $this->update_module_option( $mod_name, 'activated', true );
                $mod_data->options->activated = true;
			}
            
			if ( $mod_data->options->activated )
				$this->$mod_name->init();
        }
        
	}
    
	public function set_post_types() {
		foreach ( $this->modules as $mod_name => $mod_data ) {

			if ( isset( $this->modules->$mod_name->options->post_types ) )
				$this->modules->$mod_name->options->post_types = $this->workflow_module->clean_post_type_options( $this->modules->$mod_name->options->post_types, $mod_data->post_type_support );	
			
			$this->$mod_name->module = $this->modules->$mod_name;
		}
	}
    
	public function admin_init() {
	    	    
		$version = get_option( $this->workflow_options . 'version' );
        
		if ( $version && version_compare( $version, CMS_WORKFLOW_VERSION, '<' ) ) {
			foreach ( $this->modules as $mod_name => $mod_data ) {
				if ( method_exists( $this->$mod_name, 'update' ) )
						$this->$mod_name->upgrade();
			}
            
			update_option( $this->workflow_options . 'version', CMS_WORKFLOW_VERSION );
            
		} else if ( !$version ) {
			update_option( $this->workflow_options . 'version', CMS_WORKFLOW_VERSION );
            
		}

		wp_register_script( 'jquery-listfilterizer', CMS_WORKFLOW_URL . 'js/jquery.listfilterizer.js', array( 'jquery' ), CMS_WORKFLOW_VERSION, true );
		wp_register_style( 'jquery-listfilterizer', CMS_WORKFLOW_URL . 'css/jquery.listfilterizer.css', false, CMS_WORKFLOW_VERSION, 'all' );

        wp_register_script( 'sprintf', CMS_WORKFLOW_URL . 'js/sprintf.js', false, CMS_WORKFLOW_VERSION, true );
	}
	
	public function register_module( $name, $args = array() ) {
		
		if ( !isset( $args['title'], $name ) )
			return false;
		
		$defaults = array(
			'title' => '',
            'description' => '',
			'slug' => '',
			'post_type_support' => '',
			'default_options' => array(),
			'options' => false,
			'configure_callback' => false,
			'configure_link_text' => __( 'Konfigurieren', CMS_WORKFLOW_TEXTDOMAIN ),
			'messages' => array(
				'settings-updated' => __( 'Einstellungen gespeichert.', CMS_WORKFLOW_TEXTDOMAIN ),
				'form-error' => __( 'Bitte korrigieren Sie den Formularfehler unten und versuchen Sie es erneut.', CMS_WORKFLOW_TEXTDOMAIN ),
				'nonce-failed' => __( 'Schummeln, was?', CMS_WORKFLOW_TEXTDOMAIN ),
				'invalid-permissions' => __( 'Sie haben nicht die erforderlichen Rechte, um diese Aktion durchzuführen.', CMS_WORKFLOW_TEXTDOMAIN ),
				'missing-post' => __( 'Das Dokument existiert nicht.', CMS_WORKFLOW_TEXTDOMAIN ),
			),
			'autoload' => false,
		);
        
		if ( isset( $args['messages'] ) )
			$args['messages'] = array_merge( (array)$args['messages'], $defaults['messages'] );
        
		$args = array_merge( $defaults, $args );
		$args['name'] = $name;
		$args['workflow_options_name'] = sprintf('%s%s_options', $this->workflow_options, $name);
        
		if ( !isset( $args['settings_slug'] ) )
			$args['settings_slug'] = sprintf('workflow-%s-settings', $args['slug']);
        
		if ( empty( $args['post_type_support'] ) )
			$args['post_type_support'] = sprintf('workflow_%s', $name);
        
		if ( !empty( $args['settings_help_tab'] ) )
			add_action( sprintf('load-workflow_page_%s', $args['settings_slug']), array( &$this->$name, 'action_settings_help_menu' ) );
		
		$this->modules->$name = (object) $args;
        
        return $this->modules->$name;
	}
	
	private function load_modules() {

		if ( !class_exists( 'WP_List_Table' ) )
			require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );

		require_once( CMS_WORKFLOW_ROOT . '/includes/workflow-module.php' );
		
		$module_dirs = scandir( CMS_WORKFLOW_ROOT . '/modules/' );
		$class_names = array();
		foreach( $module_dirs as $module_dir ) {
            $filename = CMS_WORKFLOW_ROOT . "/modules/{$module_dir}/$module_dir.php";
			if ( file_exists( $filename ) ) {                
				include_once( $filename );

				$tmp = explode( '-', $module_dir );
				$class_name = '';
				$slug_name = '';
                
				foreach( $tmp as $word ) {
					$class_name .= ucfirst( $word ) . '_';
					$slug_name .= $word . '_';
				}
                
				$slug_name = rtrim( $slug_name, '_' );
				$class_names[$slug_name] = 'Workflow_' . rtrim( $class_name, '_' );
			}
		}

		$this->workflow_module = new Workflow_Module();
		        
		foreach( $class_names as $slug => $class_name ) {
			if ( class_exists( $class_name ) ) {
				$this->$slug = new $class_name();
			}
		}
		
	}
    
	private function load_module_options() {

		foreach ( $this->modules as $mod_name => $mod_data ) {

			$this->modules->$mod_name->options = get_option( $this->workflow_options . $mod_name . '_options', new stdClass );
			foreach ( $mod_data->default_options as $default_key => $default_value ) {
				if ( !isset( $this->modules->$mod_name->options->$default_key ) )
					$this->modules->$mod_name->options->$default_key = $default_value;
			}

			if ( isset( $this->modules->$mod_name->options->post_types ) )
				$this->modules->$mod_name->options->post_types = $this->workflow_module->clean_post_type_options( $this->modules->$mod_name->options->post_types, $mod_data->post_type_support );	
			
			$this->$mod_name->module = $this->modules->$mod_name;
		}

	}
	
	public function get_module_by( $key, $value ) {
		$module = false;
		foreach ( $this->modules as $mod_name => $mod_data ) {
			
			if ( $key == 'name' && $value == $mod_name ) {
				$module =  $this->modules->$mod_name;
			} else {
				foreach( $mod_data as $mod_data_key => $mod_data_value ) {
					if ( $mod_data_key == $key && $mod_data_value == $value )
						$module = $this->modules->$mod_name;
				}
			}
		}
		return $module;
	}
	
	public function update_module_option( $mod_name, $key, $value ) {
		$this->modules->$mod_name->options->$key = $value;
		$this->$mod_name->module = $this->modules->$mod_name;
        
		return update_option( $this->workflow_options . $mod_name . '_options', $this->modules->$mod_name->options );
	}
	
	public function update_all_module_options( $mod_name, $new_options ) {
		if ( is_array( $new_options ) )
			$new_options = (object)$new_options;
        
		$this->modules->$mod_name->options = $new_options;
		$this->$mod_name->module = $this->modules->$mod_name;
        
		return update_option( $this->workflow_options . $mod_name . '_options', $this->modules->$mod_name->options );
	}
    
}

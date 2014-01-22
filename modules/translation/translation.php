<?php
class Workflow_Translation extends Workflow_Module {

    const translate_from_lang_post_meta = '_translate_from_lang_post_meta';
    
    const translate_to_lang_post_meta = '_translate_to_lang_post_meta';
 
    public $module;
    
    public function __construct() {
		global $cms_workflow;
		
		$this->module_url = $this->get_module_url( __FILE__ );
        
                $content_help_tab = array(
                    '<p>'. __('Mit dem Übersetzungsmodul haben Sie die Möglichkeit, mehrsprachige Versionen Ihrer Seiten zu erstellen, indem Sie XLIFF-Dateien im- und exportieren. Sie können auf dieser Seite auswählen, für welche Bereiche die Verwendung von XLIFF-Dateien freigegeben werden soll.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
                    '<p>'. __('So erstellen Sie eine anderssprachige Version eines Dokumentes:', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
                    '<ol>',
                    '<li>' . __('Erstellen Sie ein neues Dokument oder gehen Sie auf ein bereits erstelltes Dokument in einem freigegebenen Bereich. Das Dokument muss gespeichert, darf aber noch nicht veröffentlicht sein (Status "Entwurf").', CMS_WORKFLOW_TEXTDOMAIN) . '</li>',
                    '<li>' . __('Wählen Sie im Kästchen "Übersetzung" aus, von welcher Sprache Sie in welche Sprache übersetzen wollen (wenn diese Box nicht erscheint, können Sie sie über die Lasche "Optionen einblenden" in der rechten oberen Ecke anzeigen lassen).', CMS_WORKFLOW_TEXTDOMAIN) . '</li>',
                    '<li>' . __('Über "XLIFF-Datei herunterladen" können Sie die XLIFF-Datei auf Ihrem Rechner speichern und mit einem externen Übersetzungsprogramm übersetzen lassen.', CMS_WORKFLOW_TEXTDOMAIN) . '</li>',
                    '<li>' . __('Die übersetzte XLIFF-Datei können Sie über die Schaltfläche "Durchsuchen..." hochladen.', CMS_WORKFLOW_TEXTDOMAIN) . '</li>',
                    '</ol>',
                    '<p>'. __('Wenn Sie die Versionierung aktiviert haben, können Sie auch ein bestehendes Dokument kopieren oder eine neue Version erstellen und hieraus ein anderssprachiges Dokument erstellen. Desweiteren ist es (sofern freigeschalten) möglich, Kopien von Dokumenten in parallelen, anderssprachigen Webauftritten zu erstellen und dort zu übersetzen.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>' 
                );
                
                /*Kontexthilfe, einzubinden in den Bearbeitungsseiten und neuen Seiten zu posts und pages (evtl. auch in Übersichtsseiten) über       
                 * (nicht über load-, sondern über admin_head-, sonst erscheint der neue Hilfe-Tab als erstes!)    
                 *  
                 * add_action( 'admin_head-post-new.php', array( __CLASS__, 'add_post_new_help_tab'));     
                 * add_action( 'admin_head-post.php', array( __CLASS__, 'add_post_new_help_tab'));    
                 * 
                 * 
                */
                $context_help_tab = array(
                    '<p></p>'
                );
        
                        
		$args = array(
			'title' => __( 'Übersetzung', CMS_WORKFLOW_TEXTDOMAIN ),
			'description' => __( 'Import und Export von XLIFF-Dateien.', CMS_WORKFLOW_TEXTDOMAIN ),
			'module_url' => $this->module_url,
			'slug' => 'translation',
			'default_options' => array(
				'post_types' => array(
					'post' => true,
					'page' => true
				),
			),
			'configure_callback' => 'print_configure_view',
			'settings_help_tab' => array(
				'id' => 'workflow-translation-overview',
				'title' => __('Übersicht', CMS_WORKFLOW_TEXTDOMAIN),
				'content' => implode(PHP_EOL, $content_help_tab),
				),
			'settings_help_sidebar' => __( '<p><strong>Für mehr Information:</strong></p><p><a href="http://blogs.fau.de/cms">Dokumentation</a></p><p><a href="http://blogs.fau.de/webworking">RRZE-Webworking</a></p><p><a href="https://github.com/RRZE-Webteam">RRZE-Webteam in Github</a></p>', CMS_WORKFLOW_TEXTDOMAIN ),
		);
        
		$this->module = $cms_workflow->register_module( 'translation', $args );
	}
	
	public function init() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
        
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );
        
        $post_type = $this->get_current_post_type();
        
        if ($this->is_post_type_enabled($post_type)) {
            add_filter( 'upload_mimes', array( $this, 'xliff_mime_type') );

            add_action('post_edit_form_tag', array( $this, 'update_edit_form'));

            add_action('add_meta_boxes', array( $this, 'translate_meta_box'), 10, 2);

            add_action('save_post', array( $this, 'save_translate_meta_data'));          
        }                               
	}

    public static function update_post_content( $post_id, $post ) {
        if ( ! wp_is_post_revision( $post_id ) ) {
            remove_action('save_post', array( $this, 'update_post_content' ));

            $args = array(
                'ID' => $post_id,
                'post_content' => $post->post_content,
            );

            wp_update_post($args);

            add_action( 'save_post', array( $this, 'update_post_content' ));
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

    public function update_edit_form() {  
        echo ' enctype="multipart/form-data"';  
    }

    public function xliff_mime_type( $mime_types ) {
        //XLIFF: XML Localisation Interchange File Format
        $mime_types['xliff'] = 'application/octet-stream';
        $mime_types['xlf'] = 'application/octet-stream';
        return $mime_types;
    }

    public function translate_meta_box( $post_type, $post ) {
		if ( !$this->is_post_type_enabled($post_type))
			return;

        if($this->module_activated( 'post_versioning' ) && in_array( $post->post_status, array('publish', 'future', 'private') ))
            return;
        
        add_meta_box(
            'translate',
            __('Übersetzung', CMS_WORKFLOW_TEXTDOMAIN),
            array( $this, 'translate_inner_box'),
            $post_type,
            'normal'
        );
        
    }

    public function translate_inner_box( $post ) {
        $post_id = $post->ID;
        
        $site_lang = get_option('WPLANG') ? get_option('WPLANG') : 'en_EN';
        $translate_from_lang = 0;
        
        if($this->module_activated( 'post_versioning' )) {
            
                $remote_post_meta = get_post_meta( $post_id, Workflow_Post_Versioning::version_remote_post_meta, true );

                if(isset($remote_post_meta['post_id']) && isset($remote_post_meta['blog_id'])) {
                    if(switch_to_blog( $remote_post_meta['blog_id'] )) {
                        $remote_post = get_post($remote_post_meta['post_id']);
                        if(!is_null($remote_post)) {
                            $translate_from_lang = get_option('WPLANG') ? get_option('WPLANG') : 'en_EN';
                        }                

                        restore_current_blog();

                    }
                }
            
        }
        
        wp_nonce_field(plugin_basename(__FILE__), 'translate_fields_nonce');
        
        if(!empty($translate_from_lang)) {
            
            $translate_to_lang = $site_lang;
            
            update_post_meta($post_id, self::translate_from_lang_post_meta, $translate_from_lang);
            update_post_meta($post_id, self::translate_to_lang_post_meta, $translate_to_lang);
            
            $html = '<input type="hidden" name="translate_from_lang" value="' . $translate_from_lang . '" />';
            $html .= '<input type="hidden" name="translate_to_lang" value="' . $translate_to_lang . '" />';
            $html .= '<p>' . sprintf(__('Übersetzung von <i>%1$s</i> nach <i>%2$s</i>.', CMS_WORKFLOW_TEXTDOMAIN), $this->get_lang_name($translate_from_lang), $this->get_lang_name($translate_to_lang)) . '</p>';
                   
        } else {
            
            $translate_from_lang_post_meta = get_post_meta($post_id, self::translate_from_lang_post_meta, true);
            $translate_from_lang = $translate_from_lang_post_meta != '' ? $translate_from_lang_post_meta : 0;
            
            $translate_to_lang_post_meta = get_post_meta($post_id, self::translate_to_lang_post_meta, true);
            $translate_to_lang = $translate_to_lang_post_meta == '' ? $site_lang : $translate_to_lang_post_meta;
            
            $html = '<label>' . __('Aus der Sprache:', CMS_WORKFLOW_TEXTDOMAIN) . '&nbsp;';
            $html .= '<select id="translate-from-lang" name="translate_from_lang">';
            $html .= '<option value="0">' . __('Wählen', CMS_WORKFLOW_TEXTDOMAIN) . '</option>';

            foreach($this->lang_codes() as $key => $value) {
                $html .= sprintf('<option value="%1$s"' . selected( $translate_from_lang, $key, false ) . '>%2$s</option>', $key, $value);
            }

            $html .= '</select></label>&nbsp;';

            $html .= '<label>' . __('In die Sprache:', CMS_WORKFLOW_TEXTDOMAIN) . '&nbsp;';
            $html .= '<select id="translate-to-lang" name="translate_to_lang">';
            $html .= '<option value="0">' . __('Wählen', CMS_WORKFLOW_TEXTDOMAIN) . '</option>';

            foreach($this->lang_codes() as $key => $value) {
                $html .= sprintf('<option value="%1$s"' . selected( $translate_to_lang, $key, false ) . '>%2$s</option>', $key, $value);
            }

            $html .= '</select></label>';
            
            $html .= '<p class="description">' . __('Bitte wählen Sie die Übersetzungssprachen aus', CMS_WORKFLOW_TEXTDOMAIN) . '</p>';
        }
        
        $cap = $this->get_available_post_types($post->post_type)->cap;
        
        if($translate_from_lang && current_user_can($cap->edit_posts)) {
            $download_link = $this->module_url . 'xliff-download.php';
            $download_link = add_query_arg('xliff-attachment', $post_id, $download_link);
            
            $html .= sprintf('<p><a href="%1$s">%2$s</a> ', $download_link, __('XLIFF-Datei herunterladen', CMS_WORKFLOW_TEXTDOMAIN));

            $html .= '<p class="label">' . __('XLIFF-Datei hochladen:', CMS_WORKFLOW_TEXTDOMAIN) . '&nbsp;';
            $html .= '<input type="file" id="translate_xliff_attachment" name="translate_xliff_attachment" value="" size="25">'; 
            $html .= '</p>';
        }
        
        echo $html;

    }
    
    public function save_translate_meta_data($post_id) {

        if(!isset($_POST['translate_fields_nonce']) || !wp_verify_nonce($_POST['translate_fields_nonce'], plugin_basename(__FILE__))) {
            return;
        }

        if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        $post = get_post($post_id);
        
        $cap = $this->get_available_post_types($post->post_type)->cap;
        
        if(!current_user_can($cap->edit_posts)) {
            return;
        }
        
        if(!$this->module_activated( 'post_versioning' ) || !in_array( $post->post_status, array('publish', 'future', 'private') )) {
            if(isset($_POST['translate_from_lang']) && !empty($_POST['translate_from_lang']) && isset( $_POST['translate_to_lang']) && !empty($_POST['translate_to_lang'])) {
                if($_POST['translate_from_lang'] != $_POST['translate_to_lang']) {
                    update_post_meta( $post_id, self::translate_from_lang_post_meta, esc_attr( $_POST['translate_from_lang'] ) );
                    update_post_meta( $post_id, self::translate_to_lang_post_meta, esc_attr( $_POST['translate_to_lang'] ) );
                }
            }
        }
        
        if(get_post_meta($post_id, self::translate_from_lang_post_meta, true) != '' && get_post_meta($post_id, self::translate_to_lang_post_meta, true) != '') {
            if(!empty($_FILES['translate_xliff_attachment']['name']) && !empty($_FILES['translate_xliff_attachment']['tmp_name'])) {
                $supported_types = array('application/octet-stream');

                $arr_file_type = wp_check_filetype(basename($_FILES['translate_xliff_attachment']['name']));

                if(in_array($arr_file_type['type'], $supported_types)) {

                    $error = $this->import_xliff($post_id, $_FILES['translate_xliff_attachment']);
                    if ( is_wp_error($error) )
                        $this->flash_admin_notice($error->get_error_message(), 'error');

                } else {
                    $this->flash_admin_notice(__('Der Dateityp, die Sie hochgeladen haben, ist nicht eine XLIFF.', CMS_WORKFLOW_TEXTDOMAIN), 'error');
                }
            }
        }
	
    }

	public function import_xliff($post_id, $file) {
		
        if ( wp_is_post_revision( $post_id ) )
            return;
        
        remove_action('save_post', array( $this, 'save_translate_meta_data'));
        
        $blog_id = get_current_blog_id();
		
        $fh = fopen($file['tmp_name'], 'r');
        
        $data = fread($fh, $file['size']);
        
        fclose($fh);
        clearstatcache();
        
        if ( ! function_exists('simplexml_load_string'))
            return new WP_Error('xml_missing', __('Die "Simple XML"-Bibliothek fehlt.', CMS_WORKFLOW_TEXTDOMAIN));

        $xml = simplexml_load_string($data);

        if (!$xml)
            return new WP_Error('not_xml_file', sprintf(__('Die XLIFF-Datei (%s) konnte nicht gelesen werden.', CMS_WORKFLOW_TEXTDOMAIN), $name));

        $file_attributes = $xml->file->attributes();
        if (!$file_attributes || !isset($file_attributes['original']))
            return new WP_Error('not_xml_file', sprintf(__('Die XLIFF-Datei (%s) konnte nicht gelesen werden.', CMS_WORKFLOW_TEXTDOMAIN), $name));

        $original = (string)$file_attributes['original'];

        if ($original != md5(sprintf('%d - %d', $blog_id, $post_id)))
            return new WP_Error('xliff_doesnt_match', __('Die hochgeladene XLIFF-Datei ist nicht für dieses Dokument geeignet.', CMS_WORKFLOW_TEXTDOMAIN));

        $post_array = array('ID' => $post_id);
        
        foreach ($xml->file->body->children() as $node) {
            $attr = $node->attributes();
            $type = (string)$attr['id'];
            if($type == 'title') {
                $post_array['post_title'] = (string)$node->target;                
            } elseif($type == 'body') {
                $target = (string)$node->target;
                $post_array['post_content'] = str_replace('<br class="xliff-newline" />', PHP_EOL, $target);
            } elseif($type == 'excerpt') {
                $post_array['post_excerpt'] = (string)$node->target;
            }
            
        }
		
        if(!wp_update_post( $post_array ))
            return new WP_Error('post_update_error', __('Ein unbekannter Fehler ist aufgetreten. Das Dokument konnte nicht gespeichert werden.', CMS_WORKFLOW_TEXTDOMAIN));               

	}
        
    public function admin_notices() {
        $this->show_flash_admin_notices();
    }
}
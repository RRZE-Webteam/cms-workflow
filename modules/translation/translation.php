<?php

class Workflow_Translation extends Workflow_Module {

    const translate_from_lang_post_meta = '_translate_from_lang_post_meta';
    const translate_to_lang_post_meta = '_translate_to_lang_post_meta';

    public static $alternate_posts;
    
    public $module;
    
    public function __construct() {
        global $cms_workflow;

        $this->module_url = $this->get_module_url(__FILE__);

        $content_help_tab = array(
            '<p>' . __('Mit dem Übersetzungsmodul haben Sie die Möglichkeit, mehrsprachige Versionen Ihrer Seiten zu erstellen, indem Sie XLIFF-Dateien im- und exportieren.', CMS_WORKFLOW_TEXTDOMAIN) . '<br>' . __('Sie können auf dieser Seite auswählen, für welche Bereiche die Verwendung von XLIFF-Dateien freigegeben werden soll.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>' . __('So erstellen Sie eine anderssprachige Version eines Dokumentes:', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<ol>',
            '<li>' . __('Erstellen Sie ein neues Dokument oder gehen Sie auf ein bereits erstelltes Dokument in einem freigegebenen Bereich.', CMS_WORKFLOW_TEXTDOMAIN) . ' ' . __('Das Dokument muss gespeichert, darf aber nicht veröffentlicht sein (Status <i>Entwurf</i> oder <i>Ausstehender Review</i>).', CMS_WORKFLOW_TEXTDOMAIN) . '</li>',
            '<li>' . __('Wählen Sie im Kästchen <i>Übersetzung</i> aus, von welcher Sprache Sie in welche Sprache übersetzen wollen (wenn diese Box nicht erscheint, überprüfen Sie den Status des Dokumentes oder lassen Sie sie über die Lasche <i>Optionen einblenden</i> in der rechten oberen Ecke anzeigen).', CMS_WORKFLOW_TEXTDOMAIN) . '</li>',
            '<li>' . __('Nach dem Speichern des Dokumentes können Sie über <i>XLIFF-Datei herunterladen</i> die XLIFF-Datei des Dokumentes auf Ihrem Rechner speichern und mit einem externen Übersetzungsprogramm übersetzen lassen.', CMS_WORKFLOW_TEXTDOMAIN) . '</li>',
            '<li>' . __('Die übersetzte XLIFF-Datei können Sie über die Schaltfläche <i>Durchsuchen...</i> hochladen.', CMS_WORKFLOW_TEXTDOMAIN) . '</li>',
            '</ol>',
            '<p>' . __('Wenn Sie die Versionierung aktiviert haben, können Sie auch ein bestehendes Dokument kopieren oder eine neue Version erstellen und hieraus ein anderssprachiges Dokument erstellen. Desweiteren ist es möglich, Kopien von Dokumenten in parallelen, anderssprachigen Webauftritten zu erstellen und dort zu übersetzen, sofern netzwerkweite Freigaben im Versionierungs-Modul angegeben sind.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>'
        );

        $context_help_tab = array(
            '<p>' . __('Mit dem Übersetzungsmodul haben Sie die Möglichkeit, mehrsprachige Versionen Ihrer Seiten zu erstellen, indem Sie XLIFF-Dateien im- und exportieren.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>' . __('So erstellen Sie eine anderssprachige Version eines Dokumentes:', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<ol>',
            '<li>' . __('Das Dokument muss gespeichert, darf aber nicht veröffentlicht sein (Status <i>Entwurf</i> oder <i>Ausstehender Review</i>).', CMS_WORKFLOW_TEXTDOMAIN) . '</li>',
            '<li>' . __('Wählen Sie im Kästchen <i>Übersetzung</i> aus, von welcher Sprache Sie in welche Sprache übersetzen wollen (wenn diese Box nicht erscheint, überprüfen Sie den Status des Dokumentes oder lassen Sie sie über die Lasche <i>Optionen einblenden</i> in der rechten oberen Ecke anzeigen).', CMS_WORKFLOW_TEXTDOMAIN) . '</li>',
            '<li>' . __('Nach dem Speichern des Dokumentes können Sie über <i>XLIFF-Datei herunterladen</i> die XLIFF-Datei des Dokumentes auf Ihrem Rechner speichern und mit einem externen Übersetzungsprogramm übersetzen lassen.', CMS_WORKFLOW_TEXTDOMAIN) . '</li>',
            '<li>' . __('Die übersetzte XLIFF-Datei können Sie über die Schaltfläche <i>Durchsuchen...</i> hochladen.', CMS_WORKFLOW_TEXTDOMAIN) . '</li>',
            '</ol>'
        );


        $args = array(
            'title' => __('Übersetzung', CMS_WORKFLOW_TEXTDOMAIN),
            'description' => __('Import und Export von XLIFF-Dateien.', CMS_WORKFLOW_TEXTDOMAIN),
            'module_url' => $this->module_url,
            'slug' => 'translation',
            'default_options' => array(
                'post_types' => array(
                    'post' => true,
                    'page' => true
                ),
                'related_sites' => array(),
            ),
            'configure_callback' => 'print_configure_view',
            'settings_help_tab' => array(
                'id' => 'workflow-translation-overview',
                'title' => __('Übersicht', CMS_WORKFLOW_TEXTDOMAIN),
                'content' => implode(PHP_EOL, $content_help_tab),
            ),
            'settings_help_sidebar' => __('<p><strong>Für mehr Information:</strong></p><p><a href="http://blogs.fau.de/cms">Dokumentation</a></p><p><a href="http://blogs.fau.de/webworking">RRZE-Webworking</a></p><p><a href="https://github.com/RRZE-Webteam">RRZE-Webteam in Github</a></p>', CMS_WORKFLOW_TEXTDOMAIN),
            'contextual_help' => array(
                '1' => array(
                    'screen_id' => array('post', 'page'),
                    'help_tab' => array(
                        'id' => 'workflow-translation-context',
                        'title' => __('Übersetzung', CMS_WORKFLOW_TEXTDOMAIN),
                        'content' => implode(PHP_EOL, $context_help_tab),
                    )
                )
            ),
        );

        $this->module = $cms_workflow->register_module('translation', $args);
    }

    public function init() {
        require_once( CMS_WORKFLOW_ROOT . '/modules/' . $this->module->name . '/widgets.php' );

        add_action('widgets_init', array($this, 'register_widgets'));

        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        add_action('wp_ajax_workflow_suggest_site', array($this, 'workflow_suggest_site'));

        add_action('admin_init', array($this, 'register_settings'));

        add_action('admin_notices', array($this, 'admin_notices'));
                
        $post_type = $this->get_current_post_type();

        if ($this->is_post_type_enabled($post_type)) {
            add_filter('upload_mimes', array($this, 'xliff_mime_type'));

            add_action('post_edit_form_tag', array($this, 'update_edit_form'));

            add_action('add_meta_boxes', array($this, 'translate_meta_box'), 10, 2);

            add_action('save_post', array($this, 'save_translate_meta_data'));
        }
        
        add_action('template_redirect', array($this, 'set_alternate_posts'));
    }

    public function enqueue_admin_scripts() {
        wp_enqueue_script('workflow-translation', $this->module_url . 'translation.js', array('jquery', 'jquery-ui-autocomplete'), CMS_WORKFLOW_VERSION, true);
        wp_localize_script('workflow-translation', 'suggest', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'isRtl' => (int) is_rtl(),
        ));
    }

    public function update_post_content($post_id, $post) {
        if (!wp_is_post_revision($post_id)) {
            remove_action('save_post', array($this, 'update_post_content'));

            $args = array(
                'ID' => $post_id,
                'post_content' => $post->post_content,
            );

            wp_update_post($args);

            add_action('save_post', array($this, 'update_post_content'));
        }
    }

    public function register_settings() {
        $related_sites = $this->module->options->related_sites;

        add_settings_section($this->module->workflow_options_name . '_general', false, '__return_false', $this->module->workflow_options_name);
        add_settings_field('post_types', __('Freigabe', CMS_WORKFLOW_TEXTDOMAIN), array($this, 'settings_post_types_option'), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general');
        
        if (!empty($related_sites)) {
            add_settings_field('related_sites', __('Bereits übersetzte Webseiten', CMS_WORKFLOW_TEXTDOMAIN), array($this, 'settings_related_sites_option'), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general');
        }
        
        add_settings_field('add_related_site', __('Bezogenen Webseiten hinzufügen', CMS_WORKFLOW_TEXTDOMAIN), array($this, 'settings_add_related_site_option'), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general');
    }

    public function settings_post_types_option() {
        global $cms_workflow;
        $cms_workflow->settings->custom_post_type_option($this->module);
    }

    public function settings_related_sites_option() {
        $related_sites = $this->module->options->related_sites;
        foreach ($related_sites as $site) {
            $language = self::get_language($site['sitelang']);
            $blog_id = $site['blog_id'];
            $label = sprintf(__('%2$s (%3$s) (%4$s)'), $blog_id, $site['blogname'], $site['siteurl'], $language['native_name']);
            ?>
            <label for="related_sites_<?php echo $blog_id; ?>">
                <input id="related-sites-<?php echo $blog_id; ?>" type="checkbox" checked name="<?php printf('%s[related_sites][]', $this->module->workflow_options_name); ?>" value="<?php echo $blog_id ?>"> <?php echo $label; ?>
            </label><br>
            <?php
        }
    }

    public function settings_add_related_site_option() {
        echo '<input type="text" class="regular-text workflow-suggest-site" name="' . $this->module->workflow_options_name . '[add_related_site]" id="add-site">';
    }

    public function settings_validate($new_options) {

        if (!isset($new_options['post_types'])) {
            $new_options['post_types'] = array();
        }

        $new_options['post_types'] = $this->clean_post_type_options($new_options['post_types'], $this->module->post_type_support);

        $add_related_site = isset($new_options['add_related_site']) ? explode('.', $new_options['add_related_site']) : array();
        $add_related_site_id = isset($add_related_site[0]) ? (int) $add_related_site[0] : '';

        if (!isset($new_options['related_sites'])) {
            $new_options['related_sites'] = array();
        }

        $current_blog_id = get_current_blog_id();
        $related_sites = array();
        $sites = get_blogs_of_user(get_current_user_id());

        foreach ($sites as $site) {
            $blog_id = $site->userblog_id;

            if ($blog_id == $current_blog_id) {
                continue;
            }

            if ($blog_id != $add_related_site_id && !in_array($blog_id, $new_options['related_sites'])) {
                continue;
            }

            switch_to_blog($blog_id);
            $sitelang = self::get_locale();
            restore_current_blog();

            $related_sites[] = array(
                'blog_id' => $blog_id,
                'blogname' => $site->blogname,
                'siteurl' => $site->siteurl,
                'sitelang' => $sitelang
            );
        }

        unset($new_options['add_related_site']);
        $new_options['related_sites'] = $related_sites;

        return $new_options;
    }

    public function print_configure_view() {
        ?>
        <form class="basic-settings" action="<?php echo esc_url(menu_page_url($this->module->settings_slug, false)); ?>" method="post">
            <?php settings_fields($this->module->workflow_options_name); ?>
            <?php do_settings_sections($this->module->workflow_options_name); ?>
            <?php
            echo '<input id="cms_workflow_module_name" name="cms_workflow_module_name" type="hidden" value="' . esc_attr($this->module->name) . '" />';
            ?>
            <p class="submit"><?php submit_button(null, 'primary', 'submit', false); ?></p>
        </form>
        <?php
    }

    public function update_edit_form() {
        echo ' enctype="multipart/form-data"';
    }

    public function xliff_mime_type($mime_types) {
        //XLIFF: XML Localisation Interchange File Format
        $mime_types['xliff'] = 'application/octet-stream';
        $mime_types['xlf'] = 'application/octet-stream';
        return $mime_types;
    }

    public function translate_meta_box($post_type, $post) {
        if (!$this->is_post_type_enabled($post_type)) {
            return;
        }

        if ($this->module_activated('post_versioning') && in_array($post->post_status, array('publish', 'future', 'private'))) {
            return;
        }

        add_meta_box('translate', __('Übersetzung', CMS_WORKFLOW_TEXTDOMAIN), array($this, 'translate_inner_box'), $post_type, 'normal');
    }

    public function translate_inner_box($post) {
        $post_id = $post->ID;

        $site_lang = self::get_locale();
        $translate_from_lang = 0;

        if ($this->module_activated('post_versioning')) {

            $remote_post_meta = get_post_meta($post_id, Workflow_Post_Versioning::version_remote_post_meta, true);

            if (isset($remote_post_meta['post_id']) && isset($remote_post_meta['blog_id'])) {
                if (switch_to_blog($remote_post_meta['blog_id'])) {
                    $remote_post = get_post($remote_post_meta['post_id']);
                    if (!is_null($remote_post)) {
                        $translate_from_lang = self::get_locale();
                    }

                    restore_current_blog();
                }
            }
        }

        wp_nonce_field(plugin_basename(__FILE__), 'translate_fields_nonce');

        if (!empty($translate_from_lang)) {
            $from_language = self::get_language($translate_from_lang);
            
            $translate_to_lang = $site_lang;
            $to_language = self::get_language($translate_to_lang);
            
            update_post_meta($post_id, self::translate_from_lang_post_meta, $translate_from_lang);
            update_post_meta($post_id, self::translate_to_lang_post_meta, $translate_to_lang);

            $html = '<input type="hidden" name="translate_from_lang" value="' . $translate_from_lang . '" />';
            $html .= '<input type="hidden" name="translate_to_lang" value="' . $translate_to_lang . '" />';
            $html .= '<p>' . sprintf(__('Übersetzung von <i>%1$s</i> nach <i>%2$s</i>.', CMS_WORKFLOW_TEXTDOMAIN), $from_language['native_name'], $to_language['native_name']) . '</p>';
        } else {
            $available_languages = self::get_available_languages();
            
            $translate_from_lang_post_meta = get_post_meta($post_id, self::translate_from_lang_post_meta, true);
            $translate_from_lang = $translate_from_lang_post_meta != '' ? $translate_from_lang_post_meta : 0;
            if($translate_from_lang == 'en_EN') {
                $translate_from_lang == 'en_US';
            }            

            $translate_to_lang_post_meta = get_post_meta($post_id, self::translate_to_lang_post_meta, true);
            $translate_to_lang = $translate_to_lang_post_meta == '' ? $site_lang : $translate_to_lang_post_meta;
            if($translate_to_lang == 'en_EN') {
                $translate_to_lang == 'en_US';
            }            

            $html = '<label>' . __('Aus der Sprache:', CMS_WORKFLOW_TEXTDOMAIN) . '&nbsp;';
            $html .= '<select id="translate-from-lang" name="translate_from_lang">';
            $html .= '<option value="0">' . __('Wählen', CMS_WORKFLOW_TEXTDOMAIN) . '</option>';

            foreach ($available_languages as $locale) {
                $language = self::get_language($locale);
                $html .= sprintf('<option value="%1$s"' . selected($translate_from_lang, $locale, false) . '>%2$s</option>', $locale, $language['native_name']);
            }

            $html .= '</select></label>&nbsp;';

            $html .= '<label>' . __('In die Sprache:', CMS_WORKFLOW_TEXTDOMAIN) . '&nbsp;';
            $html .= '<select id="translate-to-lang" name="translate_to_lang">';
            $html .= '<option value="0">' . __('Wählen', CMS_WORKFLOW_TEXTDOMAIN) . '</option>';

            foreach ($available_languages as $locale) {
                $language = self::get_language($locale);
                $html .= sprintf('<option value="%1$s"' . selected($translate_to_lang, $locale, false) . '>%2$s</option>', $locale, $language['native_name']);
            }

            $html .= '</select></label>';

            $html .= '<p class="description">' . __('Bitte wählen Sie die Übersetzungssprachen aus', CMS_WORKFLOW_TEXTDOMAIN) . '</p>';
        }

        $cap = $this->get_available_post_types($post->post_type)->cap;

        if ($translate_from_lang && current_user_can($cap->edit_posts)) {
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

        if (!isset($_POST['translate_fields_nonce']) || !wp_verify_nonce($_POST['translate_fields_nonce'], plugin_basename(__FILE__))) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if(wp_is_post_revision($post_id)) {
            return;
        }
        
        $post = get_post($post_id);

        if (!$this->is_post_type_enabled($post->post_type)) {
            return;
        }
        
        if ($this->module_activated('post_versioning') && in_array($post->post_status, array('publish', 'future', 'private'))) {
            return;
        }
        
        $cap = $this->get_available_post_types($post->post_type)->cap;

        if (!current_user_can($cap->edit_posts)) {
            return;
        }

        if (isset($_POST['translate_from_lang']) && !empty($_POST['translate_from_lang']) && isset($_POST['translate_to_lang']) && !empty($_POST['translate_to_lang'])) {
            if ($_POST['translate_from_lang'] != $_POST['translate_to_lang']) {
                update_post_meta($post_id, self::translate_from_lang_post_meta, esc_attr($_POST['translate_from_lang']));
                update_post_meta($post_id, self::translate_to_lang_post_meta, esc_attr($_POST['translate_to_lang']));
            }
        }

        if (get_post_meta($post_id, self::translate_from_lang_post_meta, true) && get_post_meta($post_id, self::translate_to_lang_post_meta, true)) {
            if (!empty($_FILES['translate_xliff_attachment']['name']) && !empty($_FILES['translate_xliff_attachment']['tmp_name'])) {
                $supported_types = array('application/octet-stream');

                $arr_file_type = wp_check_filetype(basename($_FILES['translate_xliff_attachment']['name']));

                if (in_array($arr_file_type['type'], $supported_types)) {

                    remove_action('save_post', array($this, 'save_translate_meta_data'));
                    $error = $this->import_xliff($post_id, $_FILES['translate_xliff_attachment']);
                    if (is_wp_error($error)) {
                        $this->flash_admin_notice($error->get_error_message(), 'error');
                    }
                    add_action('save_post', array($this, 'save_translate_meta_data'));
                    
                } else {
                    $this->flash_admin_notice(__('Der Dateityp, die Sie hochgeladen haben, ist nicht eine XLIFF.', CMS_WORKFLOW_TEXTDOMAIN), 'error');
                }
            }
        }
    }

    public function import_xliff($post_id, $file) {
        $post = get_post($post_id);

        $fh = fopen($file['tmp_name'], 'r');

        $data = fread($fh, $file['size']);

        fclose($fh);
        clearstatcache();

        if (!function_exists('simplexml_load_string')) {
            return new WP_Error('xml_missing', __('Die "Simple XML"-Bibliothek fehlt.', CMS_WORKFLOW_TEXTDOMAIN));
        }

        $xml = simplexml_load_string($data);

        if (!$xml) {
            return new WP_Error('not_xml_file', sprintf(__('Die XLIFF-Datei (%s) konnte nicht gelesen werden.', CMS_WORKFLOW_TEXTDOMAIN), $name));
        }

        $file_attributes = $xml->file->attributes();
        if (!$file_attributes || !isset($file_attributes['original'])) {
            return new WP_Error('not_xml_file', sprintf(__('Die XLIFF-Datei (%s) konnte nicht gelesen werden.', CMS_WORKFLOW_TEXTDOMAIN), $name));
        }

        $original = (string) $file_attributes['original'];

        if ($original != sanitize_file_name(sprintf('%1$s-%2$s', $post->post_title, $post->ID))) {
            $this->flash_admin_notice(__('Warnung. Die hochgeladene XLIFF-Datei stimmt nicht mit dem Dokument überein.', CMS_WORKFLOW_TEXTDOMAIN), 'error');
        }

        $post_array = array('ID' => $post_id);
        $post_meta_array = array();

        foreach ($xml->file->body->children() as $node) {
            $attr = $node->attributes();
            $type = (string) $attr['id'];
            if ($type == 'title') {
                $post_array['post_title'] = (string) $node->target;
            }
            
            elseif ($type == 'body') {
                $target = (string) $node->target;
                $post_array['post_content'] = str_replace('<br class="xliff-newline" />', PHP_EOL, $target);
            }
            
            elseif ($type == 'excerpt') {
                $post_array['post_excerpt'] = (string) $node->target;
            }
            
            elseif (strpos($type, '_meta_') === 0) {
                $meta_key = (string) substr($type, strlen('_meta_'));
                $meta_value = (string) $node->target;
                if (!empty($meta_value) && !is_numeric($meta_value)) {
                    $post_meta_array[$meta_key] = $meta_value;
                } 

            }
        }

        if (!wp_update_post($post_array)) {
            return new WP_Error('post_update_error', __('Ein unbekannter Fehler ist aufgetreten. Das Dokument konnte nicht gespeichert werden.', CMS_WORKFLOW_TEXTDOMAIN));
        }
        
        $post_meta = get_post_meta($post_id);

        foreach ($post_meta as $meta_key => $prev_value) {
            if (strpos($meta_key, '_') === 0) {
                continue;
            }

            if (empty($meta_value)) {
                continue;
            }        

            $prev_value = array_map('maybe_unserialize', $prev_value);
            $prev_value = $prev_value[0];

            if (empty($prev_value) || is_array($prev_value) || is_numeric($prev_value)) {
                continue;
            }
            
            if(isset($post_meta_array[$meta_key])) {
                update_post_meta($post_id, $meta_key, $post_meta_array[$meta_key], $prev_value);
            }
        }

    }

    public function admin_notices() {
        $this->show_flash_admin_notices();
    }

    public function workflow_suggest_site() {
        if (!is_multisite() || !current_user_can('manage_options') || wp_is_large_network()) {
            wp_die(-1);
        }

        $excluded_sites = array();
        $related_sites = $this->module->options->related_sites;
        foreach ($related_sites as $site) {
            $excluded_sites[] = $site['blog_id'];
        }
        $excluded_sites[] = get_current_blog_id();

        $return = array();
        $sites = get_blogs_of_user(get_current_user_id());

        foreach ($sites as $site) {
            $blog_id = $site->userblog_id;

            if (in_array($blog_id, $excluded_sites)) {
                continue;
            }

            if (!stristr($site->blogname, $_REQUEST['term']) && !stristr($site->siteurl, $_REQUEST['term'])) {
                continue;
            }

            switch_to_blog($blog_id);
            $sitelang = self::get_locale();
            restore_current_blog();

            $language = self::get_language($sitelang);
            
            $value = sprintf(__('%1$s. %2$s (%3$s) (%4$s)'), $blog_id, $site->blogname, $site->siteurl, $language['native_name']);
            $return[] = array(
                'label' => $value,
                'value' => $value,
            );
        }

        wp_die(json_encode($return));
    }

    public function register_widgets() {
        register_widget('Workflow_Translation_Lang_Switcher');
    }

    public static function get_dropdown_pages($args = '') {
        $defaults = array(
            'depth' => 0,
            'child_of' => 0,
            'selected' => 0,
            'name' => 'page_id',
            'id' => '',
            'show_option_none' => '',
            'show_option_no_change' => '',
            'option_none_value' => '',
            'class' => 'widefat'
        );

        $r = wp_parse_args( $args, $defaults );
        extract( $r, EXTR_SKIP );

        $pages = get_pages($r);
        $output = '';
        if (empty($id)) {
            $id = $name;
        }

        if (!empty($pages)) {
            $output = "<select name='" . esc_attr($name) . "' id='" . esc_attr($id) . "' class='" . esc_attr($class) . "'>\n";
            if ( $show_option_no_change ) {
                $output .= "\t<option value=\"-1\">$show_option_no_change</option>";
            }
            if ( $show_option_none ) {
                $output .= "\t<option value=\"" . esc_attr($option_none_value) . "\">$show_option_none</option>\n";
            }
            $output .= walk_page_dropdown_tree($pages, $depth, $r);
            $output .= "</select>\n";
        }
        
        return $output;
    }
        
    public static function get_related_posts($args = '') {
        global $cms_workflow;
        
        $defaults = array(
            'linktext' => 'text',
            'order' => 'blogid',
            'show_current_blog' => false,
            'echo' => false,
            'redirect_page_id' => 0
        );
        
        $r = wp_parse_args($args, $defaults);
        
        $alternate_posts = self::$alternate_posts;
        
        if (empty($alternate_posts)) {
            return '';
        }
        
        extract($r, EXTR_SKIP);
        
        extract($alternate_posts, EXTR_SKIP);
        
        if ($show_current_blog) {
            $current_blog = get_blog_details(get_current_blog_id());

            $related_sites[] = array(
                'blog_id' => get_current_blog_id(),
                'blogname' => $current_blog->blogname,
                'siteurl' => $current_blog->siteurl,
                'sitelang' => self::get_locale()
            );
        }

        if ($order == 'blogid') {
            $related_sites = self::array_orderby($related_sites, 'blog_id', SORT_ASC);
        } else {
            $related_sites = self::array_orderby($related_sites, 'blogname', SORT_ASC);
        }

        $related_posts = array();
        $related_posts_output = '<div class="workflow-language mlp_language_box"><ul>';
                
        foreach ($related_sites as $site) {
            $language = self::get_language($site['sitelang']);
            $hreflang = $language['iso'][1];

            if ('text' == $linktext) {
                $display = $language['native_name'];
            } else {
                $display = $hreflang;
            }

            $a_id = (get_current_blog_id() == $site['blog_id']) ? ' id="lang-current-locale"' : '';
            $li_class = (get_current_blog_id() == $site['blog_id']) ? ' class="lang-current current"' : '';

            if (isset($remote_permalink[$site['blog_id']])) {
                $href = $remote_permalink[$site['blog_id']];
            } elseif (is_singular() && get_current_blog_id() == $site['blog_id']) {
                $href = get_permalink($current_post_id);
            } elseif ($redirect_page_id > 0) {
                $href = get_permalink($redirect_page_id);
            } else {
                $href = get_site_url($site['blog_id']);
            }

            $related_posts[$href] = $hreflang;
            $related_posts_output .= sprintf('<li%1$s><a rel="alternate" hreflang="%2$s" href="%3$s"%4$s>%5$s</a></li>', $li_class, $hreflang, $href, $a_id, $display, PHP_EOL);
        }
                        
        $related_posts_output .= '</ul></div>';
        
        $output = apply_filters('workflow_translation_related_posts', $related_posts_output, $related_posts, $related_sites, $args);

        if ($echo === true) {
            echo $output;
        }

        else {
            return $output;
        }
    }
    
    public static function get_rel_alternate() {
        $alternate_posts = self::$alternate_posts;
        
        if (empty($alternate_posts)) {
            return '';
        }

        extract($alternate_posts, EXTR_SKIP);
        
        $related_sites = self::array_orderby($related_sites, 'blog_id', SORT_ASC);
        
        $rel_alternate = array();
        $rel_alternate_output = '';
                
        foreach ($related_sites as $site) {
            $language = self::get_language($site['sitelang']);
            $hreflang = $language['iso'][1];

            if (isset($remote_permalink[$site['blog_id']])) {
                $href = $remote_permalink[$site['blog_id']];
            } elseif (is_singular() && get_current_blog_id() == $site['blog_id']) {
                $href = get_permalink($current_post_id);
            } else {
                $href = '';
            }
            
            if(get_current_blog_id() != $site['blog_id'] && $href) {
                $rel_alternate[$href] = $hreflang;
                $rel_alternate_output .= sprintf('<link rel="alternate" hreflang="%1$s" href="%2$s">%3$s', $hreflang, $href, PHP_EOL);
            }           
        }
                
        return apply_filters('workflow_translation_rel_alternate', $rel_alternate_output, $rel_alternate, $related_sites);
    }
    
    public function set_alternate_posts() {
        self::$alternate_posts = $this->get_alternate_posts();
    }
    
    private function get_alternate_posts() {
        global $wp_query, $cms_workflow;

        $alternate_posts = array();
        
        $related_sites = $cms_workflow->translation->module->options->related_sites;

        if (!( 0 < count($related_sites) )) {
            return $alternate_posts;
        }

        $default_post = get_post();

        if ($default_post) {
            $current_post_id = $default_post->ID;
        } elseif (!empty($wp_query->queried_object) && !empty($wp_query->queried_object->ID)) {
            $current_post_id = $wp_query->queried_object->ID;
        } else {
            $current_post_id = 0;
        }

        $translate_from_lang = array();
        $remote_permalink = array();

        if ($current_post_id && isset($cms_workflow->post_versioning) && $cms_workflow->post_versioning->module->options->activated) {

            $post = get_post($current_post_id);

            $remote_post_metas = get_post_meta($post->ID, Workflow_Post_Versioning::version_remote_post_meta);

            if (empty($remote_post_metas)) {
                $remote_post_metas = get_post_meta($post->ID, Workflow_Post_Versioning::version_remote_parent_post_meta);
            }

            foreach ($remote_post_metas as $remote_post_meta) {

                if (isset($remote_post_meta['blog_id']) && isset($remote_post_meta['post_id'])) {

                    if (switch_to_blog($remote_post_meta['blog_id'])) {

                        $remote_post = get_post($remote_post_meta['post_id']);

                        if (!is_null($remote_post) && ( 'publish' === $remote_post->post_status || ( 'private' === $remote_post->post_status && is_super_admin() ))) {
                            $translate_from_lang[$remote_post_meta['blog_id']] = self::get_locale();
                            $remote_permalink[$remote_post_meta['blog_id']] = get_permalink($remote_post_meta['post_id']);
                        }

                        restore_current_blog();
                    }
                }
            }
        }

        $alternate_posts = array(
            'current_post_id' => $current_post_id,
            'related_sites' => $related_sites,
            'translate_from_lang' => $translate_from_lang,
            'remote_permalink' => $remote_permalink
        );
        
        return $alternate_posts;
    }
    
    private static function array_orderby() {
        $args = func_get_args();
        $data = array_shift($args);
        foreach ($args as $n => $field) {
            if (is_string($field)) {
                $tmp = array();
                foreach ($data as $key => $row) {
                    $tmp[$key] = $row[$field];
                }
                $args[$n] = $tmp;
            }
        }
        $args[] = &$data;
        call_user_func_array('array_multisort', $args);
        return array_pop($args);
    }
        
}

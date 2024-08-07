<?php

namespace RRZE\Workflow\Modules\Translation;

defined('ABSPATH') || exit;

use RRZE\Workflow\Main;
use RRZE\Workflow\Module;
use RRZE\Workflow\Modules\Versioning\Versioning;
use WP_Error;

class Translation extends Module
{
    const translate_from_lang_post_meta = '_translate_from_lang_post_meta';
    const translate_to_lang_post_meta = '_translate_to_lang_post_meta';

    public static $alternate_posts;

    public $module;
    public $module_url;

    protected static $rrzeTosEndpoints = null;

    public function __construct(Main $main)
    {
        parent::__construct($main);
        $this->module_url = $this->get_module_url(__FILE__);

        $args = array(
            'title' => __('Übersetzung', 'cms-workflow'),
            'description' => __('Import und Export von XLIFF-Dateien.', 'cms-workflow'),
            'module_url' => $this->module_url,
            'slug' => 'translation',
            'default_options' => array(
                'post_types' => array(
                    'post' => true,
                    'page' => true
                ),
                'related_sites' => array(),
            ),
            'configure_callback' => 'print_configure_view'
        );

        $this->module = $this->main->register_module('translation', $args);
    }

    public function init()
    {
        add_filter('rrze_tos_endpoints', [$this, 'rrzeTosEndpoints'], 10, 2);

        add_action('widgets_init', array($this, 'register_widgets'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_notices', array($this, 'admin_notices'));

        $post_type = $this->get_current_post_type();

        if ($this->is_post_type_enabled($post_type)) {
            add_filter('upload_mimes', array($this, 'xliff_mime_type'));
            add_action('post_edit_form_tag', array($this, 'update_edit_form'));
            add_action('add_meta_boxes', array($this, 'translate_meta_box'), 10, 2);
            add_action('save_post', array($this, 'save_translate_meta_data'), 999);
        }

        add_action('template_redirect', array($this, 'set_alternate_posts'));
    }

    public function rrzeTosEndpoints($endpoints, $endpointsMap)
    {
        global $wp_query;
        foreach ($endpoints as $key => $value) {
            if (isset($wp_query->query_vars[$value])) {
                self::$rrzeTosEndpoints = $endpointsMap[$key];
            }
        }
        return $endpoints;
    }

    public function update_post_content($post_id, $post)
    {
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

    public function register_settings()
    {
        add_settings_section($this->module->workflow_options_name . '_general', false, '__return_false', $this->module->workflow_options_name);
        add_settings_field('post_types', __('Freigabe', 'cms-workflow'), array($this, 'settings_post_types_option'), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general');

        if ($this->module_activated('network')) {
            add_settings_field('related_sites', __('Bezogenen Webseiten', 'cms-workflow'), array($this, 'settings_network_connections_option'), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general');
        }
    }

    public function settings_post_types_option()
    {

        $this->main->settings->custom_post_type_option($this->module);
    }

    public function settings_network_connections_option()
    {
        $current_blog_id = get_current_blog_id();
        $current_network_related_sites = $this->current_network_related_sites();
        $current_related_sites = $this->current_related_sites();

        if (!empty($current_network_related_sites)) :
            foreach ($current_network_related_sites as $blog) {
                if ($current_blog_id == $blog['blog_id']) {
                    continue;
                }

                $blog_id = $blog['blog_id'];
                $site_name = $blog['blogname'];
                $site_url = $blog['siteurl'];
                $site_lang = $blog['sitelang'];

                $language = self::get_language($site_lang);
                $label = !empty($site_name) ? sprintf('%1$s (%2$s) (%3$s)', $site_name, $site_url, $language['native_name']) : sprintf('%1$s (%2$s)', $site_url, $language['native_name']);
                $connected = isset($current_related_sites[$blog_id]) ? true : false; ?>
                <label for="related_sites_<?php echo $blog_id; ?>">
                    <input id="related_sites_<?php echo $blog_id; ?>" type="checkbox" <?php checked($connected, true); ?> name="<?php printf('%s[related_sites][]', $this->module->workflow_options_name); ?>" value="<?php echo $blog_id ?>" /> <?php echo $label; ?>
                </label><br>
            <?php
            } ?>
            <p class="description"><?php _e('Bezogenen Webseiten werden im Sprachwechsler angezeigt.', 'cms-workflow'); ?></p>
        <?php else : ?>
            <p><?php _e('Nicht verfügbar', 'cms-workflow'); ?></p>
        <?php endif;
    }

    public function settings_validate($new_options)
    {
        if (!isset($new_options['post_types'])) {
            $new_options['post_types'] = array();
        }

        $new_options['post_types'] = $this->clean_post_type_options($new_options['post_types'], $this->module->post_type_support);

        if ($this->module_activated('network')) {
            $new_options['related_sites'] = !empty($new_options['related_sites']) ? (array) $new_options['related_sites'] : array();

            $current_blog_id = get_current_blog_id();
            $current_network_related_sites = $this->current_network_related_sites();
            $related_sites = array();

            foreach ($current_network_related_sites as $blog) {
                $blog_id = $blog['blog_id'];
                if ($current_blog_id == $blog_id) {
                    continue;
                }

                if (!in_array($blog_id, $new_options['related_sites'])) {
                    continue;
                }

                $related_sites[$blog_id] = $blog;
            }

            $new_options['related_sites'] = $related_sites;
        }

        return $new_options;
    }

    private function current_related_sites()
    {
        $current_blog_id = get_current_blog_id();
        $current_network_related_sites = $this->current_network_related_sites();
        $current_related_sites = (array) $this->module->options->related_sites;
        $related_sites = array();

        foreach ($current_network_related_sites as $blog) {
            $blog_id = $blog['blog_id'];
            if ($current_blog_id == $blog_id) {
                continue;
            }

            if (!isset($current_related_sites[$blog_id])) {
                continue;
            }

            $related_sites[$blog_id] = $blog;
        }

        $this->main->update_module_option($this->module->name, 'related_sites', $related_sites);

        return $related_sites;
    }

    private function current_network_related_sites()
    {
        $current_network_connections = (array) $this->main->network->module->options->network_connections;
        $current_parent_site = $this->main->network->module->options->parent_site;

        $network_connections = array();
        $related_sites = array();

        if ($current_parent_site) {
            if (switch_to_blog($current_parent_site)) {
                $related_sites[$current_parent_site] = array(
                    'blog_id' => $current_parent_site,
                    'blogname' => get_bloginfo('name'),
                    'siteurl' => get_bloginfo('url'),
                    'sitelang' => self::get_locale()
                );

                $module_options = get_option($this->main->network->module->workflow_options_name);
                $network_connections = (array) $module_options->network_connections;
                restore_current_blog();
            }
        } elseif ($current_network_connections) {
            $network_connections = $current_network_connections;
        }

        foreach ($network_connections as $blog_id) {
            if (!switch_to_blog($blog_id)) {
                continue;
            }

            $related_sites[$blog_id] = array(
                'blog_id' => $blog_id,
                'blogname' => get_bloginfo('name'),
                'siteurl' => get_bloginfo('url'),
                'sitelang' => self::get_locale()
            );

            restore_current_blog();
        }

        return $related_sites;
    }

    public function print_configure_view()
    {
        ?>
        <form class="basic-settings" action="<?php echo esc_url(menu_page_url($this->module->settings_slug, false)); ?>" method="post">
            <?php settings_fields($this->module->workflow_options_name); ?>
            <?php do_settings_sections($this->module->workflow_options_name); ?>
            <?php
            echo '<input id="cms_workflow_module_name" name="cms_workflow_module_name" type="hidden" value="' . esc_attr($this->module->name) . '" />'; ?>
            <p class="submit"><?php submit_button(null, 'primary', 'submit', false); ?></p>
        </form>
<?php
    }

    public function update_edit_form()
    {
        echo ' enctype="multipart/form-data"';
    }

    public function xliff_mime_type($mime_types)
    {
        //XLIFF: XML Localisation Interchange File Format
        $mime_types['xliff'] = 'application/octet-stream';
        $mime_types['xlf'] = 'application/octet-stream';
        return $mime_types;
    }

    public function translate_meta_box($post_type, $post)
    {
        if (!$this->is_post_type_enabled($post_type)) {
            return;
        }

        if (!in_array($post->post_status, array('draft', 'pending'))) {
            return;
        }

        $cap = $this->get_available_post_types($post_type)->cap;

        if (!current_user_can($cap->edit_posts)) {
            return;
        }

        add_meta_box('workflow-translate', __('Übersetzung', 'cms-workflow'), array($this, 'translate_inner_box'), $post_type, 'normal');
    }

    public function translate_inner_box($post)
    {
        $post_id = $post->ID;

        $site_lang = self::get_locale();

        wp_nonce_field(plugin_basename(__FILE__), 'translate_fields_nonce');

        $available_languages = self::get_available_languages();
        $translate_from_lang_post_meta = get_post_meta($post_id, self::translate_from_lang_post_meta, true);
        $translate_from_lang = $translate_from_lang_post_meta != '' ? substr($translate_from_lang_post_meta, 0, 5) : $site_lang;
        if ($translate_from_lang == 'en_EN') {
            $translate_from_lang == 'en_US';
        }

        $translate_to_lang_post_meta = get_post_meta($post_id, self::translate_to_lang_post_meta, true);
        $translate_to_lang = $translate_to_lang_post_meta != '' ? substr($translate_to_lang_post_meta, 0, 5) : 0;
        if ($translate_to_lang == 'en_EN') {
            $translate_to_lang == 'en_US';
        }

        $html = '<label>' . __('Aus der Sprache:', 'cms-workflow') . '&nbsp;';
        $html .= '<select id="translate-from-lang" name="translate_from_lang">';
        $html .= '<option value="0">' . __('Wählen', 'cms-workflow') . '</option>';

        foreach ($available_languages as $locale) {
            $language = self::get_language($locale);
            $html .= sprintf('<option value="%1$s"' . selected($translate_from_lang, $locale, false) . '>%2$s</option>', $locale, $language['native_name']);
        }

        $html .= '</select></label>&nbsp;';

        $html .= '<label>' . __('In die Sprache:', 'cms-workflow') . '&nbsp;';
        $html .= '<select id="translate-to-lang" name="translate_to_lang">';
        $html .= '<option value="0">' . __('Wählen', 'cms-workflow') . '</option>';

        foreach ($available_languages as $locale) {
            $language = self::get_language($locale);
            $html .= sprintf('<option value="%1$s"' . selected($translate_to_lang, $locale, false) . '>%2$s</option>', $locale, $language['native_name']);
        }

        $html .= '</select></label>';

        if ($translate_from_lang && $translate_to_lang) {
            $download_link = $this->module_url . 'xliff-download.php';
            $download_link = add_query_arg('xliff-attachment', $post_id, $download_link);

            $html .= sprintf('<p><a href="%1$s">%2$s</a> ', $download_link, __('XLIFF-Datei herunterladen', 'cms-workflow'));

            $html .= '<p class="label">' . __('XLIFF-Datei hochladen:', 'cms-workflow') . '&nbsp;';
            $html .= '<input type="file" id="translate_xliff_attachment" name="translate_xliff_attachment" value="" size="25">';
            $html .= '</p>';
        } else {
            $html .= '<p class="description">' . __('Bitte wählen Sie die Übersetzungssprachen aus', 'cms-workflow') . '</p>';
        }

        echo $html;
    }

    public function save_translate_meta_data($post_id)
    {
        if (!isset($_POST['translate_fields_nonce']) || !wp_verify_nonce($_POST['translate_fields_nonce'], plugin_basename(__FILE__))) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($post_id)) {
            return;
        }

        $post = get_post($post_id);

        if (!$this->is_post_type_enabled($post->post_type)) {
            return;
        }

        if (!in_array($post->post_status, array('draft', 'pending'))) {
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
                    remove_action('save_post', array($this, 'save_translate_meta_data'), 999);
                    $error = $this->import_xliff($post_id, $_FILES['translate_xliff_attachment']);
                    if (is_wp_error($error)) {
                        $this->flash_admin_notice($error->get_error_message(), 'error');
                    }
                    add_action('save_post', array($this, 'save_translate_meta_data'), 999);
                } else {
                    $this->flash_admin_notice(__('Der Dateityp, die Sie hochgeladen haben, ist nicht eine XLIFF.', 'cms-workflow'), 'error');
                }
            }
        }
    }

    public function import_xliff($post_id, $file)
    {
        if (!function_exists('simplexml_load_string')) {
            return new WP_Error('xml_missing', __('Die "Simple XML"-Bibliothek fehlt.', 'cms-workflow'));
        }

        $post = get_post($post_id);
        $filename = $file['tmp_name'] ?? '';

        if (!$filename) {
            return new WP_Error('xml_file_does_not_exists', __('Die XLIFF-Datei existiert nicht.', 'cms-workflow'));
        }

        $filesize = $file['size'] ?? 0;
        $fh = fopen($filename, 'r');

        $data = fread($fh, $filesize);

        fclose($fh);
        clearstatcache();

        $xml = simplexml_load_string($data);

        if (!$xml) {
            return new WP_Error('not_xml_file', sprintf(__('Die XLIFF-Datei (%s) konnte nicht gelesen werden.', 'cms-workflow'), $filename));
        }

        $file_attributes = $xml->file->attributes();
        if (!$file_attributes || !isset($file_attributes['original'])) {
            return new WP_Error('not_xml_file', sprintf(__('Die XLIFF-Datei (%s) konnte nicht gelesen werden.', 'cms-workflow'), $filename));
        }

        $original = (string) $file_attributes['original'];

        if ($original != sanitize_file_name(sprintf('%1$s-%2$s', $post->post_title, $post->ID))) {
            $this->flash_admin_notice(__('Warnung. Die hochgeladene XLIFF-Datei stimmt nicht mit dem Dokument überein.', 'cms-workflow'), 'error');
        }

        $post_array = array('ID' => $post_id);
        $post_meta_array = array();

        foreach ($xml->file->body->children() as $node) {
            $attr = $node->attributes();
            $type = (string) $attr['id'];
            if ($type == 'title') {
                $post_array['post_title'] = (string) $node->target;
            } elseif ($type == 'body') {
                $target = (string) $node->target;
                $post_array['post_content'] = str_replace('<br class="xliff-newline" />', PHP_EOL, $target);
            } elseif ($type == 'excerpt') {
                $post_array['post_excerpt'] = (string) $node->target;
            } elseif (strpos($type, '_meta_') === 0) {
                $meta_key = (string) substr($type, strlen('_meta_'));
                $meta_value = (string) $node->target;
                if (!empty($meta_value) && !is_numeric($meta_value)) {
                    $post_meta_array[$meta_key] = $meta_value;
                }
            }
        }

        if (!wp_update_post($post_array)) {
            return new WP_Error('post_update_error', __('Ein unbekannter Fehler ist aufgetreten. Das Dokument konnte nicht gespeichert werden.', 'cms-workflow'));
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

            if (isset($post_meta_array[$meta_key])) {
                update_post_meta($post_id, $meta_key, $post_meta_array[$meta_key], $prev_value);
            }
        }
    }

    public function admin_notices()
    {
        $this->show_flash_admin_notices();
    }

    public function register_widgets()
    {
        if ($this->module_activated('network')) {
            register_widget(__NAMESPACE__ . '\Widget');
        }
    }

    public static function get_dropdown_pages($args = '')
    {
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

        $r = wp_parse_args($args, $defaults);
        extract($r, EXTR_SKIP);

        $pages = get_pages($r);
        $output = '';
        if (empty($id)) {
            $id = $name;
        }

        if (!empty($pages)) {
            $output = "<select name='" . esc_attr($name) . "' id='" . esc_attr($id) . "' class='" . esc_attr($class) . "'>\n";
            if ($show_option_no_change) {
                $output .= "\t<option value=\"-1\">$show_option_no_change</option>";
            }
            if ($show_option_none) {
                $output .= "\t<option value=\"" . esc_attr($option_none_value) . "\">$show_option_none</option>\n";
            }
            $output .= walk_page_dropdown_tree($pages, $depth, $r);
            $output .= "</select>\n";
        }

        return $output;
    }

    public static function get_related_posts($args = '', $arialabel = '')
    {
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

        $current_blog_id = get_current_blog_id();

        if ($show_current_blog) {
            $current_blog = get_blog_details($current_blog_id);

            $related_sites[] = array(
                'blog_id' => $current_blog_id,
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
        $related_posts_output = '<div class="workflow-language mlp_language_box"';
        if (!empty($arialabel)) {
            $related_posts_output .= ' aria-label="' . $arialabel . '" role="navigation"';
        }
        $related_posts_output .= '><ul>';

        foreach ($related_sites as $site) {
            $language = self::get_language($site['sitelang']);
            $hreflang = $language['iso'][1];

            if ('text' == $linktext) {
                $display = trim(preg_replace("/\([^)]+\)/", "", $language['native_name']));
            } else {
                $display = $hreflang;
            }

            $a_id = ($current_blog_id == $site['blog_id']) ? ' id="lang-current-locale"' : '';
            $li_class = ($current_blog_id == $site['blog_id']) ? ' class="lang-current current"' : '';

            $remoteSiteUrl = get_site_url($site['blog_id']);

            if (is_home() && !get_option('page_for_posts')) {
                $href = $remoteSiteUrl;
            } elseif (!empty(self::$rrzeTosEndpoints) && isset(self::$rrzeTosEndpoints[$hreflang])) {
                $href = $remoteSiteUrl . '/' . self::$rrzeTosEndpoints[$hreflang];
            } elseif (isset($remote_permalink[$site['blog_id']])) {
                $href = $remote_permalink[$site['blog_id']];
            } else {
                switch_to_blog($site['blog_id']);
                $href = $remoteSiteUrl;
                if ($widget = get_option('widget_workflow_translation_lang_switcher')) {
                    $widgetValues = array_values($widget);
                    $widgetShift = array_shift($widgetValues);
                    $href = empty($widgetShift['widget_redirect_page_id']) ? $remoteSiteUrl : get_permalink($widgetShift['widget_redirect_page_id']);
                }
                restore_current_blog();
            }

            $related_posts[$href] = $hreflang;
            $related_posts_output .= sprintf('<li%1$s><a rel="alternate" lang="%2$s" hreflang="%2$s" href="%3$s"%4$s>%5$s</a></li>', $li_class, $hreflang, $href, $a_id, $display, PHP_EOL);
        }

        $related_posts_output .= '</ul></div>';

        $output = apply_filters('workflow_translation_related_posts', $related_posts_output, $related_posts, $related_sites, $args);

        if ($echo === true) {
            echo $output;
        } else {
            return $output;
        }
    }

    // FAU-Theme
    public static function get_rel_alternate()
    {
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
            } else {
                $href = '';
            }

            if (get_current_blog_id() != $site['blog_id'] && $href) {
                $rel_alternate[$href] = $hreflang;
                $rel_alternate_output .= sprintf('<link rel="alternate" hreflang="%1$s" href="%2$s">%3$s', $hreflang, $href, PHP_EOL);
            }
        }

        return apply_filters('workflow_translation_rel_alternate', $rel_alternate_output, $rel_alternate, $related_sites);
    }

    public function set_alternate_posts()
    {
        self::$alternate_posts = $this->get_alternate_posts();
    }

    private function get_alternate_posts()
    {
        global $wp_query;

        if (!$this->module_activated('network') || !$this->module_activated('versioning')) {
            return false;
        }

        $related_sites = $this->current_related_sites();
        if (empty($related_sites)) {
            return false;
        }

        if (is_home()) {
            $post_id = get_option('page_for_posts');
            $default_post = get_post($post_id);
        } else {
            $default_post = get_post();
        }

        if ($default_post) {
            $current_post_id = $default_post->ID;
        } elseif (!empty($wp_query->queried_object) && !empty($wp_query->queried_object->ID)) {
            $current_post_id = $wp_query->queried_object->ID;
        } else {
            return false;
        }

        $current_blog_id = get_current_blog_id();

        $translate_from_lang = array();
        $remote_permalink = array();

        $remote_posts = array();
        $related_posts = array();

        $related_posts[$current_blog_id] = $current_post_id;

        if ($remote_parent_post_meta = get_post_meta($current_post_id, Versioning::version_remote_parent_post_meta)) {
            foreach ($remote_parent_post_meta as $parent_post_meta) {
                if (isset($parent_post_meta['blog_id']) && isset($parent_post_meta['post_id']) && $parent_post_meta['blog_id'] != $current_blog_id) {
                    $related_posts[$parent_post_meta['blog_id']] = (int) $parent_post_meta['post_id'];
                }
            }
        }

        if ($remote_post_meta = get_post_meta($current_post_id, Versioning::version_remote_post_meta)) {
            foreach ($remote_post_meta as $post_meta) {
                if (isset($post_meta['blog_id']) && isset($post_meta['post_id'])) {
                    $remote_posts[$post_meta['blog_id']] = (int) $post_meta['post_id'];
                    $related_posts[$post_meta['blog_id']] = (int) $post_meta['post_id'];
                }
            }


            foreach ($remote_posts as $blog_id => $post_id) {
                if (!switch_to_blog($blog_id)) {
                    continue;
                }

                $remote_parent_post_meta = get_post_meta($post_id, Versioning::version_remote_parent_post_meta);

                foreach ($remote_parent_post_meta as $parent_post_meta) {
                    if (isset($parent_post_meta['blog_id']) && isset($parent_post_meta['post_id'])) {
                        $related_posts[$parent_post_meta['blog_id']] = (int) $parent_post_meta['post_id'];
                    }
                }

                restore_current_blog();
            }
        }

        foreach ($related_posts as $blog_id => $post_id) {
            if (!switch_to_blog($blog_id)) {
                continue;
            }

            $remote_post = get_post($post_id);

            if (!is_null($remote_post) && ('publish' === $remote_post->post_status || ('private' === $remote_post->post_status && is_super_admin()))) {
                $translate_from_lang[$blog_id] = self::get_locale();
                $remote_permalink[$blog_id] = get_permalink($post_id);
            }

            restore_current_blog();
        }

        return array(
            'current_post_id' => $current_post_id,
            'related_sites' => $related_sites,
            'translate_from_lang' => $translate_from_lang,
            'remote_permalink' => $remote_permalink
        );
    }

    private static function array_orderby()
    {
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

    public static function get_locale_info()
    {
        $alternate_posts = self::$alternate_posts;

        if (empty($alternate_posts)) {
            return '';
        }

        extract($alternate_posts, EXTR_SKIP);

        $current_blog_id = get_current_blog_id();
        $locale = get_locale();
        $locale_info[$locale] = [
            'locale' => $locale,
            'href' => $remote_permalink[$current_blog_id],
        ];

        foreach ($related_sites as $site) {
            $remote_blog_id = $site['blog_id'];
            $remoteLocale = $site['sitelang'];
            $href = $remote_permalink[$remote_blog_id] ?? '';

            $locale_info[$remoteLocale] = [
                'locale' => $remoteLocale,
                'href' => $href,
            ];
        }

        ksort($locale_info);
        return $locale_info;
    }
}

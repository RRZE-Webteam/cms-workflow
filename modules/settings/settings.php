<?php

class Workflow_Settings extends Workflow_Module {

    public $module;

    public function __construct() {
        global $cms_workflow;

        $this->module_url = $this->get_module_url(__FILE__);

        $content_help_tab = array(
            '<p>' . __('Das Workflow-Plugin bietet Ihnen die Möglichkeit, eine bessere redaktionelle Kontrolle bei der Erstellung von Webseiten mit WordPress zu erhalten.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>' . __('Ihnen stehen hierzu verschiedene Module zur Verfügung:', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>' . __('<strong>Autoren</strong> - Detaillierte Vergabe der Rechte für Autoren.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>' . __('<strong>Dashboard</strong> - Anstehende Aufgaben und Dokumentenbearbeitungen werden im Dashboard angezeigt.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>' . __('<strong>Redaktionelle Diskussion</strong> - Bessere Kommunikation bei der Dokumentenbearbeitung durch redaktionelle Kommentare.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>' . __('<strong>Redakteure</strong> - Detaillierte Vergabe der Rechte für Redakteure.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>' . __('<strong>Benachrichtigungen</strong> - Änderungen werden per E-Mail an die zuständigen Benutzer verschickt.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>' . __('<strong>Versionierung</strong> - Einfachere Aktualisierung von Inhalten durch die Verwendung verschiedener Dokumentenversionen, zur Unterstützung mehrsprachiger Webauftritte auch netzwerkweit möglich.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>' . __('<strong>Aufgabenliste</strong> - Erstellen von Aufgaben.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>' . __('<strong>Übersetzung</strong> - Unterstützung von mehrsprachigen Webseiten.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>' . __('<strong>Benutzergruppen</strong> - Vereinfachte Verwaltung durch Gruppierung der Benutzer nach Abteilung oder Funktion. Kann nur bei aktiviertem Autorenmodul verwendet werden.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>'
        );

        $args = array(
            'title' => __('Einstellungen', CMS_WORKFLOW_TEXTDOMAIN),
            'description' => __('Auf dieser Seite können Sie grundlegende Einstellungen vornehmen.', CMS_WORKFLOW_TEXTDOMAIN),
            'module_url' => $this->module_url,
            'slug' => 'settings',
            'settings_slug' => 'workflow-settings',
            'configure_callback' => 'print_settings',
            'autoload' => true,
            'settings_help_tab' => array(
                'id' => 'workflow-settings-overview',
                'title' => __('Übersicht', CMS_WORKFLOW_TEXTDOMAIN),
                'content' => implode(PHP_EOL, $content_help_tab),
            ),
            'settings_help_sidebar' => __('<p><strong>Für mehr Information:</strong></p><p><a href="http://blogs.fau.de/cms">Dokumentation</a></p><p><a href="http://blogs.fau.de/webworking">RRZE-Webworking</a></p><p><a href="https://github.com/RRZE-Webteam">RRZE-Webteam in Github</a></p>', CMS_WORKFLOW_TEXTDOMAIN),
        );

        $this->module = $cms_workflow->register_module('settings', $args);
    }

    public function init() {

        add_action('admin_init', array($this, 'module_settings_save'), 100);

        add_action('admin_print_styles', array($this, 'action_admin_print_styles'));
        add_action('admin_print_scripts', array($this, 'action_admin_print_scripts'));

        add_action('admin_menu', array($this, 'action_admin_menu'));

        add_action('admin_init', array($this, 'register_settings'));

        add_action('contextual_help', array($this, 'action_contextual_help_menu'), 10, 3);
    }

    public function action_admin_menu() {
        global $cms_workflow, $settings_page;

        $settings_page = add_menu_page(__('Workflow', CMS_WORKFLOW_TEXTDOMAIN), __('Workflow', CMS_WORKFLOW_TEXTDOMAIN), 'manage_options', $this->module->settings_slug, array($this, 'settings_page_controller'), 'dashicons-share-alt');

        add_submenu_page($this->module->settings_slug, $this->module->title, $this->module->title, 'manage_options', $this->module->settings_slug, array($this, 'settings_page_controller'));

        foreach ($cms_workflow->modules as $mod_name => $mod_data) {
            if ($mod_data->options->activated && $mod_data->configure_callback && $mod_name != $this->module->name) {
                add_submenu_page($this->module->settings_slug, $mod_data->title, $mod_data->title, 'manage_options', $mod_data->settings_slug, array($this, 'settings_page_controller'));
            }
        }

        if (!empty($this->module->settings_help_tab)) {
            add_action(sprintf('load-%s', $settings_page), array($this, 'action_add_help_tab'));
        }
    }

    public function action_add_help_tab() {
        global $settings_page;

        $screen = get_current_screen();

        if (!method_exists($screen, 'add_help_tab')) {
            return;
        }

        if ($screen->id != $settings_page) {
            return;
        }

        if (isset($this->module->settings_help_tab['id'], $this->module->settings_help_tab['title'], $this->module->settings_help_tab['content'])) {
            $screen->add_help_tab($this->module->settings_help_tab);

            if (isset($this->module->settings_help_sidebar)) {
                $screen->set_help_sidebar($this->module->settings_help_sidebar);
            }
        }
    }

    public function action_contextual_help_menu($contextual_help, $screen_id, $screen) {
        if (!method_exists($screen, 'add_help_tab')) {
            return $contextual_help;
        }

        $post_type = $screen->post_type;
        $contextual_help_menu = $this->contextual_help_menu($post_type);

        foreach ($contextual_help_menu as $context_help) {
            foreach ($context_help as $context_page => $context_help_tab) {
                if ($screen_id == $context_page) {
                    $screen->add_help_tab(array(
                        'id' => $context_help_tab['id'],
                        'title' => $context_help_tab['title'],
                        'content' => $context_help_tab['content'],
                    ));
                }
            }
        }

        return $contextual_help;
    }

    private function contextual_help_menu($post_type) {
        global $cms_workflow;

        $contextual_help = array();

        foreach ($cms_workflow->modules as $mod_name => $mod_data) {

            $allowed_post_types = $this->get_post_types($mod_data);

            if ($mod_data->options->activated && (empty($post_type) || in_array($post_type, $allowed_post_types)) && !empty($mod_data->contextual_help)) {

                foreach ($mod_data->contextual_help as $data) {
                    $data = (object) $data;
                    if (!empty($data->screen_id) && is_array($data->screen_id) && isset($data->help_tab['id'], $data->help_tab['title'], $data->help_tab['content'])) {
                        foreach ($data->screen_id as $screen_id) {
                            $contextual_help[] = array($screen_id => $data->help_tab);
                        }
                    }
                }
            }
        }

        return $contextual_help;
    }

    public function action_admin_print_styles() {
        wp_enqueue_style('workflow-settings', $this->module_url . 'settings.css', false, CMS_WORKFLOW_VERSION);
    }

    public function action_admin_print_scripts() {
        ?>
        <script type="text/javascript">
            var workflow_admin_url = '<?php echo get_admin_url(); ?>';
        </script>
        <?php
    }

    public function settings_page_controller() {
        global $cms_workflow;

        $requested_module = $cms_workflow->get_module_by('settings_slug', $_GET['page']);
        if (!$requested_module) {
            wp_die(__('Kein aktiviertes Workflow-Modul', CMS_WORKFLOW_TEXTDOMAIN));
        }

        $requested_module_name = $requested_module->name;

        if (!$this->module_activated($requested_module_name)) {
            echo '<div class="message error"><p>' . sprintf(__('Modul nicht aktiviert. Bitte aktivieren Sie es in den <a href="%1$s">Einstellungen des Workflows</a>.', CMS_WORKFLOW_TEXTDOMAIN), menu_page_url($this->module->settings_slug, false)) . '</p></div>';
            return;
        }

        $this->print_default_view($requested_module);
    }

    public function print_default_view($current_module) {
        global $cms_workflow;
        
        if (isset($_GET['message'])) {
            $message = $_GET['message'];
        }

        elseif (isset($_REQUEST['message'])) {
            $message = $_REQUEST['message'];
        }

        elseif (isset($_POST['message'])) {
            $message = $_POST['message'];
        }
        
        else {
            $message = false;
        }

        if ($message && isset($current_module->messages[$message])) {
            $display_text = '<div class="updated"><p><strong>' . esc_html($current_module->messages[$message]) . '</strong></p></div>';
        }

        if (isset($_GET['error'])) {
            $error = $_GET['error'];
        }

        elseif (isset($_REQUEST['error'])) {
            $error = $_REQUEST['error'];
        }

        elseif (isset($_POST['error'])) {
            $error = $_POST['error'];
        }
        
        else {
            $error = false;
        }

        if ($error && isset($current_module->messages[$error])) {
            $display_text = '<div class="error"><p><strong>' . esc_html($current_module->messages[$error]) . '</strong></p></div>';
        }
        ?>
        <div class="wrap">
            <h2><?php echo esc_html(sprintf(__('Workflow &rsaquo; %s', CMS_WORKFLOW_TEXTDOMAIN), $current_module->title)); ?></h2>
            <?php if (isset($display_text)) :
                echo $display_text;
            endif; ?>
            <br class="clear">
            <?php
            $module_name = $current_module->name;
            $configure = $current_module->configure_callback;
            $cms_workflow->$module_name->$configure();
            ?>
        </div>
        <?php
    }

    public function register_settings() {
        global $cms_workflow;

        add_settings_section($this->module->workflow_options_name . '_general', false, '__return_false', $this->module->workflow_options_name);

        if (!count($cms_workflow->modules)) {
            echo '<div class="error">' . __('Es sind keine Module registriert', CMS_WORKFLOW_TEXTDOMAIN) . '</div>';
        } else {
            foreach ($cms_workflow->modules as $mod_name => $mod_data) {
                if ($mod_data->autoload) {
                    continue;
                }

                $args = array(
                    'label_for' => $mod_data->slug,
                    'id' => $mod_data->slug,
                    'name' => $this->module->workflow_options_name . "[$mod_data->slug]",
                    'activated' => $mod_data->options->activated,
                    'description' => $mod_data->description
                );

                add_settings_field($mod_data->slug, esc_html($mod_data->title), array($this, 'settings_fields'), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general', $args);
            }
        }
    }

    public function settings_fields($args) {
        $options = array(
            false => __('Deaktiviert', CMS_WORKFLOW_TEXTDOMAIN),
            true => __('Aktiviert', CMS_WORKFLOW_TEXTDOMAIN),
        );
        echo '<select id="' . $args['id'] . '" name="' . $args['name'] . '">';
        foreach ($options as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"';
            echo selected($args['activated'], $value);
            echo '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . $args['description'];
    }

    public function settings_validate($new_options) {
        global $cms_workflow;

        foreach ($new_options as $key => $value) {
            $slug = sanitize_key($key);
            $module = $cms_workflow->get_module_by('slug', $slug);

            if (!$module) {
                continue;
            }

            $mod_name = $module->name;

            if ($value) {
                if (method_exists($cms_workflow->$mod_name, 'activation')) {
                    $cms_workflow->$mod_name->activation();
                }

                $cms_workflow->update_module_option($mod_name, 'activated', true);
            }

            elseif (!$value) {
                if (method_exists($cms_workflow->$mod_name, 'deactivation')) {
                    $cms_workflow->$mod_name->deactivation();
                }

                $cms_workflow->update_module_option($mod_name, 'activated', false);
            }
        }

        return $this->module->options;
    }

    public function print_settings() {
        ?>
        <form class="basic-settings" action="<?php echo esc_url(menu_page_url($this->module->settings_slug, false)); ?>" method="post">
        <?php echo '<input id="cms_workflow_module_name" name="cms_workflow_module_name" type="hidden" value="' . esc_attr($this->module->name) . '" />'; ?>
        <?php settings_fields($this->module->workflow_options_name); ?>
        <?php do_settings_sections($this->module->workflow_options_name); ?>
            <p class="submit"><?php submit_button(null, 'primary', 'submit', false); ?></p>
        </form>
        <?php
    }

    public function print_error_or_description($field, $description) {
        if (isset($_REQUEST['form-errors'][$field])): ?>
        <div class="form-error">
            <p><?php echo esc_html($_REQUEST['form-errors'][$field]); ?></p>	
        </div>
        <?php else: ?>
        <p class="description"><?php echo esc_html($description); ?></p>
        <?php
        endif;
    }
    
    public function custom_post_type_option($module, $option_name = 'post_types') {

        $all_post_types = $this->get_available_post_types();
        
        foreach ($all_post_types as $post_type => $args) {
            if($post_type == 'attachment' && !in_array($module->name, array('authors', 'user_groups', 'task_list', 'editorial_comments'))) {
                continue;
            }
            echo '<label for="' . esc_attr($option_name) . '_' . esc_attr($post_type) . '">';
            echo '<input id="' . esc_attr($option_name) . '_' . esc_attr($post_type) . '" name="'
            . $module->workflow_options_name . '[' . $option_name . '][' . esc_attr($post_type) . ']"';
            if (!empty($module->options->{$option_name}[$post_type])) {
                checked($module->options->{$option_name}[$post_type], true);
            }
            
            disabled(post_type_supports($post_type, $module->post_type_support), true);
            echo ' type="checkbox" />&nbsp;&nbsp;&nbsp;' . esc_html($args->label) . '</label>';

            if (post_type_supports($post_type, $module->post_type_support)) {
                echo '&nbsp;<span class="description">' . sprintf(__('Deaktiviert, da die Funktion add_post_type_support( \'%1$s\', \'%2$s\' ) in einer geladenen Datei enthalten ist.', CMS_WORKFLOW_TEXTDOMAIN), $post_type, $module->post_type_support) . '</span>';
            }
            echo '<br />';
        }
    }

    public function module_settings_save() {
        global $cms_workflow;

        if (!isset($_POST['action'], $_POST['_wpnonce'], $_POST['option_page'], $_POST['_wp_http_referer'], $_POST['cms_workflow_module_name'], $_POST['submit']) || !is_admin()) {
            return false;
        }

        $module_name = sanitize_key($_POST['cms_workflow_module_name']);
        if (!isset($cms_workflow->$module_name->module->workflow_options_name)) {
            return false;
        }

        if ($_POST['action'] != 'update' || $_POST['option_page'] != $cms_workflow->$module_name->module->workflow_options_name) {
            return false;
        }

        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_wpnonce'], $cms_workflow->$module_name->module->workflow_options_name . '-options')) {
            wp_die(__('Schummeln, was?', CMS_WORKFLOW_TEXTDOMAIN));
        }

        if (isset($_POST[$cms_workflow->$module_name->module->workflow_options_name])) {
            $new_options = (array) $_POST[$cms_workflow->$module_name->module->workflow_options_name];
        } else {
            $new_options = array();
        }

        if (method_exists($cms_workflow->$module_name, 'settings_validate')) {
            $new_options = (array) $cms_workflow->$module_name->settings_validate($new_options);
        }

        $new_options = (object) array_merge((array) $cms_workflow->$module_name->module->options, $new_options);
        $cms_workflow->update_all_module_options($cms_workflow->$module_name->module->name, $new_options);

        $referer = add_query_arg('message', 'settings-updated', remove_query_arg(array('message'), wp_get_referer()));
        wp_redirect($referer);
        exit;
    }

}

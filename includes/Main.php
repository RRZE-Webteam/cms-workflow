<?php

namespace RRZE\Workflow;

defined('ABSPATH') || exit;

use RRZE\Workflow\Modules\{
    Authors\Authors,
    Dashboard\Dashboard,
    Editors\Editors,
    Network\Network,
    Notifications\Notifications,
    Settings\Settings,
    Translation\Translation,
    UserGroups\UserGroups,
    Versioning\Versioning
};

class Main
{
    public $module;

    public $modules;

    public $authors;

    public $dashboard;

    public $editors;

    public $network;

    public $notifications;

    public $settings;

    public $translation;

    public $user_groups;

    public $versioning;

    public $options = '_cms_workflow_';
    public $workflow_options_name = '_cms_workflow_options';

    public function __construct()
    {
        $this->module = new Module($this);
        $this->modules = new \stdClass();

        $this->set_modules();
        $this->set_post_types();

        add_action('admin_enqueue_scripts', [$this, 'adminEnqueueScripts']);

        add_filter('plugin_action_links_' . plugin()->getBaseName(), function ($links) {
            $settings_link = '<a href="' . menu_page_url('workflow-settings', false) . '">' . esc_html(__("Settings", 'rrze-ac')) . '</a>';
            array_unshift($links, $settings_link);
            return $links;
        });
    }

    public function set_modules()
    {

        $this->load_modules();

        $this->load_module_options();

        foreach ($this->modules as $mod_name => $mod_data) {
            if (!isset($mod_data->options->activated)) {
                if (method_exists($this->$mod_name, 'activation')) {
                    $this->$mod_name->activation();
                }

                $this->update_module_option($mod_name, 'activated', true);
                $mod_data->options->activated = true;
            }

            if ($mod_data->options->activated) {
                $this->$mod_name->init();
            }
        }
    }

    public function set_post_types()
    {
        foreach ($this->modules as $mod_name => $mod_data) {
            if (isset($this->modules->$mod_name->options->post_types)) {
                $this->modules->$mod_name->options->post_types = $this->module->clean_post_type_options($this->modules->$mod_name->options->post_types, $mod_data->post_type_support);
            }

            $this->$mod_name->module = $this->modules->$mod_name;
        }
    }

    public function adminEnqueueScripts()
    {
        wp_register_style(
            'jquery-multiselect',
            plugins_url('css/jquery.multiple.select.css', plugin()->getBasename()),
            false,
            '1.1.0',
            'all'
        );
        wp_register_script(
            'jquery-multiselect',
            plugins_url('js/jquery.multiple.select.js', plugin()->getBasename()),
            array('jquery'),
            '1.13',
            true
        );
        wp_register_style(
            'jquery-listfilterizer',
            plugins_url('css/jquery.listfilterizer.css', plugin()->getBasename()),
            false,
            plugin()->getVersion(),
            'all'
        );
        wp_register_script(
            'jquery-listfilterizer',
            plugins_url('js/jquery.listfilterizer.js', plugin()->getBasename()),
            array('jquery'),
            plugin()->getVersion(),
            true
        );
        wp_register_script(
            'sprintf',
            plugins_url('js/sprintf.js', plugin()->getBasename()),
            false,
            plugin()->getVersion(),
            true
        );

        wp_enqueue_style(
            'workflow-common',
            plugins_url('css/common.css', plugin()->getBasename()),
            false,
            plugin()->getVersion(),
            'all'
        );
    }

    public function register_module($name, $args = array())
    {
        if (!isset($args['title'], $name)) {
            return false;
        }

        $defaults = array(
            'title' => '',
            'description' => '',
            'slug' => '',
            'post_type_support' => '',
            'default_options' => array(),
            'options' => false,
            'configure_callback' => false,
            'configure_link_text' => __('Konfigurieren', 'cms-workflow'),
            'messages' => array(
                'settings-updated' => __('Einstellungen gespeichert.', 'cms-workflow'),
                'form-error' => __('Bitte korrigieren Sie den Formularfehler unten und versuchen Sie es erneut.', 'cms-workflow'),
                'nonce-failed' => __('Schummeln, was?', 'cms-workflow'),
                'invalid-permissions' => __('Sie haben nicht die erforderlichen Rechte, um diese Aktion durchzuführen.', 'cms-workflow'),
                'missing-post' => __('Das Dokument existiert nicht.', 'cms-workflow'),
            ),
            'autoload' => false,
        );

        if (isset($args['multisite']) && !is_multisite()) {
            return false;
        }

        if (isset($args['messages'])) {
            $args['messages'] = array_merge((array) $args['messages'], $defaults['messages']);
        }

        $args = array_merge($defaults, $args);
        $args['name'] = $name;
        $args['workflow_options_name'] = sprintf('%s%s_options', $this->options, $name);

        if (!isset($args['settings_slug'])) {
            $args['settings_slug'] = sprintf('workflow-%s-settings', $args['slug']);
        }

        if (empty($args['post_type_support'])) {
            $args['post_type_support'] = sprintf('workflow_%s', $name);
        }

        $this->modules->$name = (object) $args;

        return $this->modules->$name;
    }

    private function load_modules()
    {

        if (!class_exists('\WP_List_Table')) {
            require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
        }

        $this->settings = new Settings($this);
        $this->dashboard = new Dashboard($this);
        $this->authors = new Authors($this);
        $this->editors = new Editors($this);
        $this->user_groups = new UserGroups($this);
        $this->notifications = new Notifications($this);
        $this->versioning = new Versioning($this);
        if (Helper::isModuleActivated('network')) {
            $this->network = new Network($this);
        }
        if (Helper::isModuleActivated('translation')) {
            $this->translation = new Translation($this);
        }
    }

    private function load_module_options()
    {

        foreach ($this->modules as $mod_name => $mod_data) {

            $this->modules->$mod_name->options = get_option($this->options . $mod_name . '_options', new \stdClass);
            foreach ($mod_data->default_options as $default_key => $default_value) {
                if (!isset($this->modules->$mod_name->options->$default_key)) {
                    $this->modules->$mod_name->options->$default_key = $default_value;
                }
            }

            if (isset($this->modules->$mod_name->options->post_types)) {
                $this->modules->$mod_name->options->post_types = $this->module->clean_post_type_options($this->modules->$mod_name->options->post_types, $mod_data->post_type_support);
            }

            $this->$mod_name->module = $this->modules->$mod_name;
        }
    }

    public function get_module_by($key, $value)
    {
        $module = false;
        foreach ($this->modules as $mod_name => $mod_data) {

            if ($key == 'name' && $value == $mod_name) {
                $module = $this->modules->$mod_name;
            } else {
                foreach ($mod_data as $mod_data_key => $mod_data_value) {
                    if ($mod_data_key == $key && $mod_data_value == $value) {
                        $module = $this->modules->$mod_name;
                    }
                }
            }
        }
        return $module;
    }

    public function update_module_option($mod_name, $key, $value)
    {
        $this->modules->$mod_name->options->$key = $value;
        $this->$mod_name->module = $this->modules->$mod_name;

        return update_option($this->options . $mod_name . '_options', $this->modules->$mod_name->options);
    }

    public function update_all_module_options($mod_name, $new_options)
    {
        if (is_array($new_options)) {
            $new_options = (object) $new_options;
        }

        $this->modules->$mod_name->options = $new_options;
        $this->$mod_name->module = $this->modules->$mod_name;

        return update_option($this->options . $mod_name . '_options', $this->modules->$mod_name->options);
    }
}

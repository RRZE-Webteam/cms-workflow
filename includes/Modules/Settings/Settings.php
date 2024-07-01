<?php

namespace RRZE\Workflow\Modules\Settings;

defined('ABSPATH') || exit;

use RRZE\Workflow\Main;
use RRZE\Workflow\Module;
use function RRZE\Workflow\plugin;

class Settings extends Module
{

    public $module;
    public $module_url;

    public function __construct(Main $main)
    {
        parent::__construct($main);
        $this->module_url = $this->get_module_url(__FILE__);

        $args = array(
            'title' => __('Einstellungen', 'cms-workflow'),
            'description' => __('Auf dieser Seite können Sie grundlegende Einstellungen vornehmen.', 'cms-workflow'),
            'module_url' => $this->module_url,
            'slug' => 'settings',
            'settings_slug' => 'workflow-settings',
            'configure_callback' => 'print_settings',
            'autoload' => true
        );

        $this->module = $this->main->register_module('settings', $args);
    }

    public function init()
    {

        add_action('admin_init', array($this, 'module_settings_save'), 100);

        add_action('admin_print_styles', array($this, 'action_admin_print_styles'));
        add_action('admin_print_scripts', array($this, 'action_admin_print_scripts'));

        add_action('admin_menu', array($this, 'action_admin_menu'));

        add_action('admin_init', array($this, 'register_settings'));
    }

    public function action_admin_menu()
    {
        global $settings_page;

        $settings_page = add_menu_page(__('Workflow', 'cms-workflow'), __('Workflow', 'cms-workflow'), 'manage_options', $this->module->settings_slug, array($this, 'settings_page_controller'), 'dashicons-share-alt');

        add_submenu_page($this->module->settings_slug, $this->module->title, $this->module->title, 'manage_options', $this->module->settings_slug, array($this, 'settings_page_controller'));

        foreach ($this->main->modules as $mod_name => $mod_data) {
            if ($mod_data->options->activated && $mod_data->configure_callback && $mod_name != $this->module->name) {
                add_submenu_page($this->module->settings_slug, $mod_data->title, $mod_data->title, 'manage_options', $mod_data->settings_slug, array($this, 'settings_page_controller'));
            }
        }
    }

    public function action_admin_print_styles()
    {
        wp_enqueue_style(
            'workflow-settings', 
            $this->module_url . 'settings.css', 
            false, 
            plugin()->getVersion()
        );
    }

    public function action_admin_print_scripts()
    {
?>
        <script type="text/javascript">
            var workflow_admin_url = '<?php echo get_admin_url(); ?>';
        </script>
    <?php
    }

    public function settings_page_controller()
    {
        

        $requested_module = $this->main->get_module_by('settings_slug', $_GET['page']);
        if (!$requested_module) {
            wp_die(__('Kein aktiviertes Workflow-Modul', 'cms-workflow'));
        }

        $requested_module_name = $requested_module->name;

        if (!$this->module_activated($requested_module_name)) {
            echo '<div class="message error"><p>' . sprintf(__('Modul nicht aktiviert. Bitte aktivieren Sie es in den <a href="%1$s">Einstellungen des Workflows</a>.', 'cms-workflow'), menu_page_url($this->module->settings_slug, false)) . '</p></div>';
            return;
        }

        $this->print_default_view($requested_module);
    }

    public function print_default_view($current_module)
    {
        

        if (isset($_GET['message'])) {
            $message = $_GET['message'];
        } elseif (isset($_REQUEST['message'])) {
            $message = $_REQUEST['message'];
        } elseif (isset($_POST['message'])) {
            $message = $_POST['message'];
        } else {
            $message = false;
        }

        if ($message && isset($current_module->messages[$message])) {
            $display_text = '<div class="updated"><p><strong>' . esc_html($current_module->messages[$message]) . '</strong></p></div>';
        }

        if (isset($_GET['error'])) {
            $error = $_GET['error'];
        } elseif (isset($_REQUEST['error'])) {
            $error = $_REQUEST['error'];
        } elseif (isset($_POST['error'])) {
            $error = $_POST['error'];
        } else {
            $error = false;
        }

        if ($error && isset($current_module->messages[$error])) {
            $display_text = '<div class="error"><p><strong>' . esc_html($current_module->messages[$error]) . '</strong></p></div>';
        }
    ?>
        <div class="wrap">
            <h2><?php echo esc_html(sprintf(__('Workflow &rsaquo; %s', 'cms-workflow'), $current_module->title)); ?></h2>
            <?php if (isset($display_text)) :
                echo $display_text;
            endif; ?>
            <br class="clear">
            <?php
            $module_name = $current_module->name;
            $configure = $current_module->configure_callback;
            $this->main->$module_name->$configure();
            ?>
        </div>
    <?php
    }

    public function countValid($array_or_countable, $mode = \COUNT_NORMAL)
    {
        if (
            (\PHP_VERSION_ID >= 70300 && \is_countable($array_or_countable)) ||
            \is_array($array_or_countable) ||
            $array_or_countable instanceof \Countable
        ) {
            return \count($array_or_countable, $mode);
        }

        return null === $array_or_countable ? false : true;
    }

    public function register_settings()
    {
        

        add_settings_section($this->module->workflow_options_name . '_general', false, '__return_false', $this->module->workflow_options_name);

        if (!$this->countValid($this->main->modules)) {
            echo '<div class="error"><p>' . __('Es sind keine Module registriert', 'cms-workflow') . '</p></div>';
        } else {
            foreach ($this->main->modules as $mod_name => $mod_data) {
                if (
                    in_array($mod_name, apply_filters('cms_workflow_unregister_modules', []))
                    && !$this->main->$mod_name->module->options->activated
                ) {
                    if (method_exists($this->main->$mod_name, 'deactivation')) {
                        $this->main->$mod_name->deactivation();
                    }
                    $this->main->update_module_option($mod_name, 'activated', false);
                    continue;
                }
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

    public function settings_fields($args)
    {
        $options = array(
            false => __('Deaktiviert', 'cms-workflow'),
            true => __('Aktiviert', 'cms-workflow'),
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

    public function settings_validate($new_options)
    {
        foreach ($new_options as $key => $value) {
            $slug = sanitize_key($key);
            $module = $this->main->get_module_by('slug', $slug);

            if (!$module) {
                continue;
            }

            $mod_name = $module->name;

            if ($value) {
                if (method_exists($this->main->$mod_name, 'activation')) {
                    $this->main->$mod_name->activation();
                }

                $this->main->update_module_option($mod_name, 'activated', true);
            } elseif (!$value) {
                if (method_exists($this->main->$mod_name, 'deactivation')) {
                    $this->main->$mod_name->deactivation();
                }

                $this->main->update_module_option($mod_name, 'activated', false);
            }
        }

        return $this->module->options;
    }

    public function print_settings()
    {
        ?>
        <form class="basic-settings" action="<?php echo esc_url(menu_page_url($this->module->settings_slug, false)); ?>" method="post">
            <?php echo '<input id="cms_workflow_module_name" name="cms_workflow_module_name" type="hidden" value="' . esc_attr($this->module->name) . '" />'; ?>
            <?php settings_fields($this->module->workflow_options_name); ?>
            <?php do_settings_sections($this->module->workflow_options_name); ?>
            <p class="submit"><?php submit_button(null, 'primary', 'submit', false); ?></p>
        </form>
        <?php
    }

    public function print_error_or_description($field, $description)
    {
        if (isset($_REQUEST['form-errors'][$field])) : ?>
            <div class="form-error">
                <p><?php echo esc_html($_REQUEST['form-errors'][$field]); ?></p>
            </div>
        <?php else : ?>
            <p class="description"><?php echo esc_html($description); ?></p>
<?php
        endif;
    }

    public function custom_post_type_option($module, $option_name = 'post_types', $attachment = false)
    {
        $all_post_types = $this->get_available_post_types();

        $sorted_cap_types = array();

        foreach ($all_post_types as $post_type => $args) {
            $sorted_cap_types[$args->capability_type][$post_type] = $args;
        }

        foreach ($sorted_cap_types as $cap_type) {

            foreach ($cap_type as $post_type => $args) {

                if ($post_type == 'attachment' && !$attachment) {
                    continue;
                }

                $label = $args->label;

                echo '<label for="' . esc_attr($option_name) . '_' . esc_attr($post_type) . '">';
                echo '<input id="' . esc_attr($option_name) . '_' . esc_attr($post_type) . '" name="'
                    . $module->workflow_options_name . '[' . $option_name . '][' . esc_attr($post_type) . ']"';
                if (!empty($module->options->{$option_name}[$post_type])) {
                    checked($module->options->{$option_name}[$post_type], true);
                }

                disabled(post_type_supports($post_type, $module->post_type_support), true);
                echo ' type="checkbox">&nbsp;&nbsp;&nbsp;' . esc_html($label) . '</label>';

                if (post_type_supports($post_type, $module->post_type_support)) {
                    echo '&nbsp;<span class="description">' . sprintf(__('Deaktiviert, da die Funktion add_post_type_support( \'%1$s\', \'%2$s\' ) in einer geladenen Datei enthalten ist.', 'cms-workflow'), $post_type, $module->post_type_support) . '</span>';
                }
                echo '<br>';
            }
        }
    }

    public function module_settings_save()
    {
        if (!isset($_POST['action'], $_POST['_wpnonce'], $_POST['option_page'], $_POST['_wp_http_referer'], $_POST['cms_workflow_module_name'], $_POST['submit']) || !is_admin()) {
            return false;
        }

        $module_name = sanitize_key($_POST['cms_workflow_module_name']);
        if (!isset($this->main->$module_name->module->workflow_options_name)) {
            return false;
        }

        if ($_POST['action'] != 'update' || $_POST['option_page'] != $this->main->$module_name->module->workflow_options_name) {
            return false;
        }

        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_wpnonce'], $this->main->$module_name->module->workflow_options_name . '-options')) {
            wp_die(__('Schummeln, was?', 'cms-workflow'));
        }

        if (isset($_POST[$this->main->$module_name->module->workflow_options_name])) {
            $new_options = (array) $_POST[$this->main->$module_name->module->workflow_options_name];
        } else {
            $new_options = array();
        }

        if (method_exists($this->main->$module_name, 'settings_validate')) {
            $new_options = (array) $this->main->$module_name->settings_validate($new_options);
        }

        $new_options = (object) array_merge((array) $this->main->$module_name->module->options, $new_options);
        $this->main->update_all_module_options($this->main->$module_name->module->name, $new_options);

        $referer = add_query_arg('message', 'settings-updated', remove_query_arg(array('message'), wp_get_referer()));
        wp_redirect($referer);
        exit;
    }
}

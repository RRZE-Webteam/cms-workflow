<?php

namespace RRZE\Workflow\Modules\Network;

defined('ABSPATH') || exit;

use RRZE\Workflow\Main;
use RRZE\Workflow\Module;

class Network extends Module
{
    const site_connections = 'cms_workflow_site_connections';

    public $module;
    public $module_url;

    public function __construct(Main $main)
    {
        parent::__construct($main);
        $this->module_url = $this->get_module_url(__FILE__);

        $args = array(
            'title' => __('Netzwerk', 'cms-workflow'),
            'description' => __('Netzwerkweite Verbindungen zwischen Webseiten.', 'cms-workflow'),
            'multisite' => true,
            'module_url' => $this->module_url,
            'slug' => 'network',
            'default_options' => array(
                'post_types' => array(
                    'post' => false,
                    'page' => false
                ),
                'network_connections' => array(),
                'parent_site' => false
            ),
            'configure_callback' => 'print_configure_view'
        );

        $this->module = $this->main->register_module('network', $args);
    }

    public function init()
    {
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function deactivation()
    {
        $connections = get_site_option(self::site_connections, array());
        $current_blog_id = get_current_blog_id();

        if (($key = array_search($current_blog_id, $connections)) !== false) {
            unset($connections[$key]);
            update_site_option(self::site_connections, $connections);
        }

        $this->main->update_module_option($this->module->name, 'network_connections', array());
        $this->main->update_module_option($this->module->name, 'parent_site', array());
    }

    public function register_settings()
    {
        add_settings_section($this->module->workflow_options_name . '_general', false, '__return_false', $this->module->workflow_options_name);

        add_settings_field('posts_types', __('Netzwerkweite Freigabe', 'cms-workflow'), array($this, 'settings_posts_types_option'), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general');

        $post_types = $this->module->options->post_types;
        if (array_filter($post_types)) {
            if ($this->module->options->parent_site) {
                add_settings_field('parent_site', __('Autorisierte Webseite', 'cms-workflow'), array($this, 'settings_parent_site_option'), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general');
            } else {
                add_settings_field('add_parent_site', __('Autorisierte Webseite hinzufügen', 'cms-workflow'), array($this, 'settings_add_parent_site_option'), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general');
            }
        } else {
            add_settings_field('network_connections', __('Bestehende netzwerkweite Freigaben', 'cms-workflow'), array($this, 'settings_network_connections_option'), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general');
        }
    }

    public function settings_parent_site_option()
    {
        $blog_id = $this->module->options->parent_site;
        if (empty($blog_id)) : ?>
            <p><?php _e('Nicht verfügbar.', 'cms-workflow'); ?></p>
        <?php
        else :
            if (!switch_to_blog($blog_id)) {
                return;
            }

            $site_name = get_bloginfo('name');
            $site_url = get_bloginfo('url');
            $sitelang = self::get_locale();

            restore_current_blog();

            $language = self::get_language($sitelang);
            $label = sprintf(__('%2$s (%3$s) (%4$s)'), $blog_id, $site_name, $site_url, $language['native_name']);
        ?>
            <label for="parent-site">
                <input id="parent-site" type="checkbox" checked name="<?php printf('%s[parent_site]', $this->module->workflow_options_name); ?>" value="<?php echo $blog_id ?>"> <?php echo $label; ?>
            </label><br>
        <?php
        endif;
    }

    public function settings_add_parent_site_option()
    {
        $current_blog_id = get_current_blog_id();
        $current_user_id = get_current_user_id();
        $current_user_blogs = get_blogs_of_user($current_user_id);

        $user_blogs = array();

        foreach ($current_user_blogs as $blog) {
            $blog_id = $blog->userblog_id;

            if ($current_blog_id == $blog_id) {
                continue;
            }

            if ($blog->archived || $blog->deleted) {
                continue;
            }

            if (!current_user_can('manage_options')) {
                continue;
            }

            if (!switch_to_blog($blog_id)) {
                continue;
            }

            $sitelang = self::get_locale();

            restore_current_blog();

            $site_name = $blog->blogname;
            $site_url = $blog->siteurl;
            $language = self::get_language($sitelang);

            $user_blogs[$blog_id] = sprintf(__('%1$s (%2$s) (%3$s)', 'cms-workflow'), $site_name, $site_url, $language['native_name']);
        }

        if (!empty($user_blogs)) {
            $output = "<select name=\"" . $this->module->workflow_options_name . "[add_parent_site]\" id=\"workflow-network-select\">" . PHP_EOL;
            $output .= "\t<option value=\"-1\">" . __('Website auswählen', 'cms-workflow') . "</option>" . PHP_EOL;
            foreach ($user_blogs as $blog_id => $name) {
                $output .= "\t<option value=\"$blog_id\">$name</option>" . PHP_EOL;
            }
            $output .= "</select>" . PHP_EOL;
        } else {
            $output = __('Nicht verfügbar.', 'cms-workflow');
        }

        echo $output;
    }

    public function settings_posts_types_option()
    {
        $connections = $this->site_connections();
        $network_connections = $this->network_connections($connections);

        if (empty($network_connections)) {
            $this->main->settings->custom_post_type_option($this->module);
        } else {
        ?>
            <p><?php _e('Nicht verfügbar.', 'cms-workflow'); ?></p>
            <?php
        }
    }

    public function settings_network_connections_option()
    {
        $connections = $this->site_connections();
        $network_connections = $this->network_connections($connections);

        $current_blog_id = get_current_blog_id();

        $has_connection = false;
        foreach ($connections as $blog_id) {
            if ($current_blog_id == $blog_id) {
                continue;
            }

            if (!switch_to_blog($blog_id)) {
                continue;
            }

            $site_name = get_bloginfo('name');
            $site_url = get_bloginfo('url');
            $site_lang = self::get_locale();

            $module_options = get_option($this->module->workflow_options_name);
            $parent_site = $module_options->parent_site;

            restore_current_blog();

            if ($current_blog_id == $parent_site) {
                $has_connection = true;
                $language = self::get_language($site_lang);
                $label = !empty($site_name) ? sprintf('%1$s (%2$s) (%3$s)', $site_name, $site_url, $language['native_name']) : sprintf('%1$s (%2$s)', $site_url, $language['native_name']);
                $connected = in_array($blog_id, $network_connections) ? true : false;
            ?>
                <label for="network_connections_<?php echo $blog_id; ?>">
                    <input id="network_connections_<?php echo $blog_id; ?>" type="checkbox" <?php checked($connected, true); ?> name="<?php printf('%s[network_connections][]', $this->module->workflow_options_name); ?>" value="<?php echo $blog_id ?>" /> <?php echo $label; ?>
                </label><br>
            <?php
            }
        }

        if (!$has_connection) : ?>
            <p><?php _e('Nicht verfügbar.', 'cms-workflow'); ?></p>
        <?php endif;
    }

    public function settings_validate($new_options)
    {
        // Allowed post types
        if (!isset($new_options['post_types'])) {
            $new_options['post_types'] = array();
        } else {
            $new_options['post_types'] = $this->clean_post_type_options($new_options['post_types'], $this->module->post_type_support);
        }

        // Allowed parent site
        $parent_site = '';

        $connections = $this->site_connections();

        $current_blog_id = get_current_blog_id();
        $current_user_id = get_current_user_id();
        $current_user_blogs = get_blogs_of_user($current_user_id);

        $add_parent_site = isset($new_options['add_parent_site']) && is_numeric($new_options['add_parent_site']) ? $new_options['add_parent_site'] : -1;

        if (empty($new_options['parent_site'])) {
            $new_options['parent_site'] = -1;
        }

        foreach ($current_user_blogs as $blog) {
            $blog_id = $blog->userblog_id;

            if ($current_blog_id == $blog_id) {
                continue;
            }

            if ($blog->archived || $blog->deleted) {
                continue;
            }

            if ($blog_id == $add_parent_site || $blog_id == $new_options['parent_site']) {
                $parent_site = $blog_id;
                break;
            }
        }

        unset($new_options['add_parent_site']);
        $new_options['parent_site'] = $parent_site;

        // Site connections
        if (!empty($parent_site) && !in_array($current_blog_id, $connections)) {
            $connections[] = $current_blog_id;
        }

        if (empty($parent_site)) {
            if (($key = array_search($current_blog_id, $connections)) !== false) {
                unset($connections[$key]);
            }
        }

        update_site_option(self::site_connections, $connections);

        // Network connections
        $new_network_connections = !empty($new_options['network_connections']) ? $new_options['network_connections'] : array();
        $network_connections = array();

        if (!empty($new_network_connections)) {
            $new_options['post_types'] = array();
        }

        foreach ($connections as $blog_id) {
            if ($current_blog_id == $blog_id) {
                continue;
            }

            if (!switch_to_blog($blog_id)) {
                continue;
            }

            $module_options = get_option($this->module->workflow_options_name);
            $parent_site = $module_options->parent_site;

            restore_current_blog();

            if ($current_blog_id == $parent_site && in_array($blog_id, $new_network_connections)) {
                $network_connections[] = $blog_id;
            }
        }

        $new_options['network_connections'] = $network_connections;

        return $new_options;
    }

    public function print_configure_view()
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

    public function site_connections()
    {
        $current_blog_id = get_current_blog_id();
        $connections = (array) get_site_option(self::site_connections);

        $allowed_post_types = $this->get_post_types($this->module);
        if (empty($allowed_post_types)) {
            if (($key = array_search($current_blog_id, $connections)) !== false) {
                unset($connections[$key]);
            }
        }

        foreach ($connections as $blog_id) {
            $blog = get_blog_details($blog_id);
            if ($blog === false || $blog->archived || $blog->deleted) {
                if (($key = array_search($blog_id, $connections)) !== false) {
                    unset($connections[$key]);
                }
            }
        }

        update_site_option(self::site_connections, $connections);

        return $connections;
    }

    public function network_connections($connections = array())
    {
        $current_network_connections = (array) $this->module->options->network_connections;
        $network_connections = array();

        foreach ($current_network_connections as $blog_id) {
            if (in_array($blog_id, $connections)) {
                $network_connections[] = $blog_id;
            }
        }

        $this->main->update_module_option($this->module->name, 'network_connections', $network_connections);

        return $network_connections;
    }
}

<?php

class Workflow_Network extends Workflow_Module {

    const site_connections = 'cms_workflow_site_connections';

    public $module;

    public function __construct() {
        global $cms_workflow;

        $this->module_url = $this->get_module_url(__FILE__);

        $content_help_tab = array();
        
        $args = array(
            'title' => __('Netzwerk', CMS_WORKFLOW_TEXTDOMAIN),
            'description' => __('Netzwerkweite Verbindungen zwischen Webseiten.', CMS_WORKFLOW_TEXTDOMAIN),
            'multisite' => true,
            'module_url' => $this->module_url,
            'slug' => 'network',
            'default_options' => array(
                'post_types' => array(
                    'post' => false,
                    'page' => false
                ),
                'network_connections' => array(),
                'related_sites' => array()
            ),
            'configure_callback' => 'print_configure_view',
            'settings_help_tab' => array(
                'id' => 'workflow-network-overview',
                'title' => __('Übersicht', CMS_WORKFLOW_TEXTDOMAIN),
                'content' => implode(PHP_EOL, $content_help_tab),
            ),
            'settings_help_sidebar' => __('<p><strong>Für mehr Information:</strong></p><p><a href="http://blogs.fau.de/cms">Dokumentation</a></p><p><a href="http://blogs.fau.de/webworking">RRZE-Webworking</a></p><p><a href="https://github.com/RRZE-Webteam">RRZE-Webteam in Github</a></p>', CMS_WORKFLOW_TEXTDOMAIN),
        );

        $this->module = $cms_workflow->register_module('network', $args);
    }

    public function init() {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));

        add_action('wp_ajax_workflow_network_select', array($this, 'network_select'));
    }

    public function deactivation() {
        global $cms_workflow;

        $connections = get_site_option(self::site_connections, array());
        $current_blog_id = get_current_blog_id();

        if (($key = array_search($current_blog_id, $connections)) !== false) {
            unset($connections[$key]);
            update_site_option(self::site_connections, $connections);
        }

        $cms_workflow->update_module_option($this->module->name, 'network_connections', array());
        $cms_workflow->update_module_option($this->module->name, 'related_sites', array());
    }

    public function admin_enqueue_scripts() {
        wp_enqueue_script('workflow-network', $this->module_url . 'network.js', array('jquery', 'jquery-ui-autocomplete'), CMS_WORKFLOW_VERSION, true);
        wp_localize_script('workflow-network', 'selectSite', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'isRtl' => (int) is_rtl(),
        ));        
    }

    public function register_settings() {
        global $cms_workflow;
        
        add_settings_section($this->module->workflow_options_name . '_general', false, '__return_false', $this->module->workflow_options_name);

        add_settings_field('posts_types', __('Netzwerkweite Freigabe', CMS_WORKFLOW_TEXTDOMAIN), array($this, 'settings_posts_types_option'), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general');

        if ($cms_workflow->settings->has_custom_post_type_option($this->module)) {
            add_settings_field('related_sites', __('Autorisierte Webseiten', CMS_WORKFLOW_TEXTDOMAIN), array($this, 'settings_related_sites_option'), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general');             
            add_settings_field('add_related_site', __('Autorisierte Webseite hinzufügen', CMS_WORKFLOW_TEXTDOMAIN), array($this, 'settings_add_related_site_option'), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general');
        }

        add_settings_field('network_connections', __('Netzwerkweite Webseiten', CMS_WORKFLOW_TEXTDOMAIN), array($this, 'settings_network_connections_option'), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general');        
    }

    public function settings_related_sites_option() {
        $related_sites = $this->module->options->related_sites;
        if (empty($related_sites)): ?>
        <p><?php _e('Nicht verfügbar', CMS_WORKFLOW_TEXTDOMAIN); ?></p>
        <?php
        else:
        foreach ($related_sites as $blog_id) {
            if (!switch_to_blog($blog_id)) {
                continue;
            }

            $site_name = get_bloginfo('name');
            $site_url = get_bloginfo('url');            
            $sitelang = self::get_locale();
            
            restore_current_blog();

            $language = self::get_language($sitelang);
            $label = sprintf(__('%2$s (%3$s) (%4$s)'), $blog_id, $site_name, $site_url, $language['native_name']);
            ?>
            <label for="related_sites_<?php echo $blog_id; ?>">
                <input id="related-sites-<?php echo $blog_id; ?>" type="checkbox" checked name="<?php printf('%s[related_sites][]', $this->module->workflow_options_name); ?>" value="<?php echo $blog_id ?>"> <?php echo $label; ?>
            </label><br>
            <?php
        }
        endif;
    }
    
    public function settings_add_related_site_option() {
        echo '<input type="text" id="workflow-network-select" class="regular-text" name="' . $this->module->workflow_options_name . '[add_related_site]">';
    }
    
    public function network_select() {
        if (!is_multisite() || !current_user_can('manage_options') || wp_is_large_network()) {
            wp_die(-1);
        }

        $excluded_sites = array();
        $related_sites = $this->module->options->related_sites;
        foreach ($related_sites as $blog_id) {
            $excluded_sites[] = $blog_id;
        }
        $excluded_sites[] = get_current_blog_id();

        $return = array();
        $sites = wp_get_sites(array('public' => 1));

        foreach ($sites as $site) {
            $blog_id = $site['blog_id'];

            if (in_array($blog_id, $excluded_sites)) {
                continue;
            }

            if (!switch_to_blog($blog_id)) {
                continue;
            }

            $site_name = get_bloginfo('name');
            $site_url = get_bloginfo('url');                    

            $sitelang = self::get_locale();
            
            restore_current_blog();

            $language = self::get_language($sitelang);
            
            $value = sprintf(__('%1$s. %2$s (%3$s) (%4$s)'), $blog_id, $site_name, $site_url, $language['native_name']);
            $return[] = array(
                'label' => $value,
                'value' => $value,
            );
        }

        wp_die(json_encode($return));
    }
    
    public function settings_posts_types_option() {
        global $cms_workflow;
        $cms_workflow->settings->custom_post_type_option($this->module);
    }

    public function settings_network_connections_option() {
        $this->update_site_connections();
        
        $connections = get_site_option(self::site_connections, array());
        $network_connections = (array) $this->module->options->network_connections;
        $current_blog_id = get_current_blog_id();

        $has_relation = false;
        foreach ($connections as $blog_id) {
            if ($current_blog_id == $blog_id) {
                continue;
            }

            if (!switch_to_blog($blog_id)) {
                continue;
            }

            $site_name = get_bloginfo('name');
            $site_url = get_bloginfo('url');
            $sitelang = self::get_locale();
            $blog_options = get_option($this->module->workflow_options_name);

            restore_current_blog();

            $related_sites = $blog_options ? $blog_options->related_sites : array();

            if (in_array($current_blog_id, $related_sites)) {
                $has_relation = true;
                $language = self::get_language($sitelang);
                $label = ($site_name != '') ? sprintf('%1$s (%2$s) (%3$s)', $site_name, $site_url, $language['native_name']) : $site_url;
                $connected = in_array($blog_id, $network_connections) ? true : false;
                ?>
                <label for="network_connections_<?php echo $blog_id; ?>">
                    <input id="network_connections_<?php echo $blog_id; ?>" type="checkbox" <?php checked($connected, true); ?> name="<?php printf('%s[network_connections][]', $this->module->workflow_options_name); ?>" value="<?php echo $blog_id ?>" /> <?php echo $label; ?>
                </label><br>
                <?php    
            }
        }
        
        if (!$has_relation): ?>
        <p><?php _e('Nicht verfügbar', CMS_WORKFLOW_TEXTDOMAIN); ?></p>
        <?php endif;
    }

    public function settings_validate($new_options) {
        $current_blog_id = get_current_blog_id();

        if (!isset($new_options['post_types'])) {
            $new_options['post_types'] = array();
        } else {
            $new_options['post_types'] = $this->clean_post_type_options($new_options['post_types'], $this->module->post_type_support);
        }

        // Related sites
        $add_related_site = isset($new_options['add_related_site']) ? explode('.', $new_options['add_related_site']) : array();
        $add_related_site_id = isset($add_related_site[0]) ? (int) $add_related_site[0] : '';

        if (empty($new_options['related_sites'])) {
            $new_options['related_sites'] = array();
        }

        $current_blog_id = get_current_blog_id();
        $related_sites = array();
        $sites = wp_get_sites(array('public' => 1));

        foreach ($sites as $site) {
            $blog_id = $site['blog_id'];

            if ($blog_id == $current_blog_id) {
                continue;
            }

            if ($blog_id != $add_related_site_id && !in_array($blog_id, $new_options['related_sites'])) {
                continue;
            }

            $related_sites[] = $blog_id;
        }

        unset($new_options['add_related_site']);
        $new_options['related_sites'] = $related_sites;

        // Site connections
        $connections = (array) get_site_option(self::site_connections);

        if(!empty($related_sites) && !in_array($current_blog_id, $connections)) {
            $connections[] = $current_blog_id;
        }

        if(empty($related_sites)) {
            if (($key = array_search($current_blog_id, $connections)) !== false) {
                unset($connections[$key]);
            }
        }

        update_site_option(self::site_connections, $connections);

        // Network connections
        $new_connections = !empty($new_options['network_connections']) ? $new_options['network_connections'] : array();
        $current_connections = array();

        foreach ($connections as $blog_id) {
            if ($current_blog_id == $blog_id) {
                continue;
            }

            if (!switch_to_blog($blog_id)) {
                continue;
            }

            $blog_options = get_option($this->module->workflow_options_name);

            restore_current_blog();

            $related_sites = $blog_options ? $blog_options->related_sites : array();
            
            if (in_array($current_blog_id, $related_sites) && in_array($blog_id, $new_connections)) {
                $current_connections[] = $blog_id;
            }
            
        }
        
        $new_options['network_connections'] = $current_connections;

        return $new_options;
    }

    public function print_configure_view() {
        ?>
        <form class="basic-settings" action="<?php echo esc_url(menu_page_url($this->module->settings_slug, false)); ?>" method="post">
        <?php echo '<input id="cms_workflow_module_name" name="cms_workflow_module_name" type="hidden" value="' . esc_attr($this->module->name) . '" />'; ?>
        <?php settings_fields($this->module->workflow_options_name); ?>
        <?php do_settings_sections($this->module->workflow_options_name); ?>
            <p class="submit"><?php submit_button(null, 'primary', 'submit', false); ?></p>
        </form>
        <?php
    }

    public function update_site_connections() {
        $current_blog_id = get_current_blog_id();
        $connections = (array) get_site_option(self::site_connections);
        
        foreach ($connections as $blog_id) {
            if ($current_blog_id == $blog_id) {
                continue;
            }

            $blog_details = get_blog_details($blog_id);
            if (empty($blog_details->public)) {
                if (($key = array_search($blog_id, $connections)) !== false) {
                    unset($connections[$key]);
                }                    
            }
        }
        
        update_site_option(self::site_connections, $connections);
        
        $this->update_network_connections($connections);
    }
            
    private function update_network_connections($connections) {
        global $cms_workflow;

        $current_blog_id = get_current_blog_id();
        $network_connections = array();
                
        if (!empty($connections)) {
            $network_connections = (array) $this->module->options->network_connections;
        }
        
        foreach ($connections as $blog_id) {
            if ($current_blog_id == $blog_id) {
                continue;
            }

            if (!switch_to_blog($blog_id)) {
                continue;
            }

            $blog_options = get_option($this->module->workflow_options_name);

            $related_sites = $blog_options ? $blog_options->related_sites : array();
            
            if (!in_array($blog_id, $connections) && ($key = array_search($blog_id, $related_sites)) !== false) {
                unset($related_sites[$key]);
                $cms_workflow->update_module_option('network', 'related_sites', $related_sites);
            }

            restore_current_blog();

            if (!in_array($current_blog_id, $related_sites) && ($key = array_search($blog_id, $network_connections)) !== false) {
                unset($network_connections[$key]);
            }

        }

        $cms_workflow->update_module_option('network', 'network_connections', $network_connections);
    }
        
}

<?php

class Workflow_Editors extends Workflow_Module
{

    const role = 'editor';

    private $wp_post_caps = array();
    private $wp_role_caps = array();
    public $role_caps = array();
    public $module;
    public $module_url;

    public function __construct()
    {
        global $cms_workflow;

        $this->module_url = $this->get_module_url(__FILE__);

        $this->wp_post_caps = array(
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
            'publish_posts' => __('Beiträge veröffentlichen', CMS_WORKFLOW_TEXTDOMAIN),
            'delete_published_posts' => __('Veröffentlichte Beiträge löschen', CMS_WORKFLOW_TEXTDOMAIN),
            'edit_posts' => __('Beiträge bearbeiten', CMS_WORKFLOW_TEXTDOMAIN),
            'delete_posts' => __('Beiträge löschen', CMS_WORKFLOW_TEXTDOMAIN)
        );

        $this->wp_role_caps = array_keys($this->wp_post_caps);

        $args = array(
            'title' => __('Redakteure', CMS_WORKFLOW_TEXTDOMAIN),
            'description' => __('Verwaltung der Redakteure.', CMS_WORKFLOW_TEXTDOMAIN),
            'module_url' => $this->module_url,
            'slug' => 'editors',
            'default_options' => array(
                'post_types' => array(
                    'post' => true,
                    'page' => true
                ),
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
            'configure_callback' => 'print_configure_view'
        );

        $this->module = $cms_workflow->register_module('editors', $args);
    }

    public function init()
    {
        add_action('admin_init', array($this, 'set_role_caps'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
    }

    public function deactivation($network_wide = false)
    {
        $this->repopulate_role(self::role);
    }

    public function activation()
    {
        $all_role_caps = array_keys($this->wp_post_caps);
        $role_caps = array_keys($this->module->options->role_caps);

        $role = get_role(self::role);
        $new_role_caps = array();

        foreach ($all_role_caps as $cap) {
            if (in_array($cap, $role_caps)) {
                $new_role_caps[$cap] = true;
            }
            $role->remove_cap($cap);
        }

        $new_role_caps = array_keys($new_role_caps);

        foreach ($new_role_caps as $cap) {
            $role->add_cap($cap);
        }
    }

    public function set_role_caps()
    {
        $all_post_types = $this->get_available_post_types();

        $allowed_post_types = $this->get_post_types($this->module);

        foreach ($all_post_types as $post_type => $args) {

            if (!in_array($post_type, $allowed_post_types)) {
                continue;
            }

            $label = $args->label;

            if ($post_type != $args->capability_type && isset($all_post_types[$args->capability_type])) {
                $label = $all_post_types[$args->capability_type]->label;
            }

            if (isset($args->cap->edit_posts)) {
                $this->role_caps[$args->cap->edit_posts] = sprintf(__('%s bearbeiten', CMS_WORKFLOW_TEXTDOMAIN), $label);
            }

            if (isset($args->cap->publish_posts)) {
                $this->role_caps[$args->cap->publish_posts] = sprintf(__('%s veröffentlichen', CMS_WORKFLOW_TEXTDOMAIN), $label);
            }

            if (isset($args->cap->delete_posts)) {
                $this->role_caps[$args->cap->delete_posts] = sprintf(__('%s löschen', CMS_WORKFLOW_TEXTDOMAIN), $label);
            }

            if (isset($args->cap->edit_published_posts)) {
                $this->role_caps[$args->cap->edit_published_posts] = sprintf(__('Veröffentlichte %s bearbeiten', CMS_WORKFLOW_TEXTDOMAIN), $label);
            }

            if (isset($args->cap->delete_published_posts)) {
                $this->role_caps[$args->cap->delete_published_posts] = sprintf(__('Veröffentlichte %s löschen', CMS_WORKFLOW_TEXTDOMAIN), $label);
            }

            if (isset($args->cap->read_private_posts)) {
                $this->role_caps[$args->cap->read_private_posts] = sprintf(__('Private %s lesen', CMS_WORKFLOW_TEXTDOMAIN), $label);
            }

            if (isset($args->cap->edit_private_posts)) {
                $this->role_caps[$args->cap->edit_private_posts] = sprintf(__('Private %s bearbeiten', CMS_WORKFLOW_TEXTDOMAIN), $label);
            }

            if (isset($args->cap->delete_private_posts)) {
                $this->role_caps[$args->cap->delete_private_posts] = sprintf(__('Private %s löschen', CMS_WORKFLOW_TEXTDOMAIN), $label);
            }
        }
    }

    public function enqueue_admin_styles()
    {
        wp_enqueue_style('workflow-editors', $this->module->module_url . 'editors.css', false, CMS_WORKFLOW_VERSION);
    }

    public function register_settings()
    {
        add_settings_section($this->module->workflow_options_name . '_general', false, '__return_false', $this->module->workflow_options_name);
        add_settings_field('post_types', __('Freigabe', CMS_WORKFLOW_TEXTDOMAIN), array($this, 'settings_post_types_option'), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general');
        add_settings_field('role_caps', __('Redakteurerechte', CMS_WORKFLOW_TEXTDOMAIN), array($this, 'settings_role_caps_option'), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general');
    }

    public function settings_post_types_option()
    {
        $all_post_types = $this->get_available_post_types();

        $sorted_cap_types = array();

        foreach ($all_post_types as $post_type => $args) {
            $sorted_cap_types[$args->capability_type][$post_type] = $args;
        }

        foreach ($sorted_cap_types as $cap_type) {
            $labels = array();
            foreach ($cap_type as $post_type => $args) {
                if ($post_type == 'attachment') {
                    continue;
                }
                if ($post_type != $args->capability_type) {
                    $labels[] = $args->label;
                }
            }

            foreach ($cap_type as $post_type => $args) {
                if ($post_type == 'attachment') {
                    continue;
                }
                if ($post_type == $args->capability_type) {

                    if (!empty($labels)) {
                        sort($labels);
                        $labels = $args->label . ', ' . implode(', ', $labels);
                    } else {
                        $labels = $args->label;
                    }

                    echo '<label for="post_types' . '_' . esc_attr($post_type) . '">';
                    echo '<input id="post_types' . '_' . esc_attr($post_type) . '" name="'
                        . $this->module->workflow_options_name . '[post_types][' . esc_attr($post_type) . ']"';
                    if (!empty($this->module->options->post_types[$post_type])) {
                        checked($this->module->options->post_types[$post_type], true);
                    }

                    disabled(post_type_supports($post_type, $this->module->post_type_support), true);
                    echo ' type="checkbox" />&nbsp;&nbsp;&nbsp;' . esc_html($labels) . '</label>';

                    if (post_type_supports($post_type, $this->module->post_type_support)) {
                        echo '&nbsp;<span class="description">' . sprintf(__('Deaktiviert, da die Funktion add_post_type_support( \'%1$s\', \'%2$s\' ) in einer geladenen Datei enthalten ist.', CMS_WORKFLOW_TEXTDOMAIN), $post_type, $this->module->post_type_support) . '</span>';
                    }
                    echo '<br>';
                }
            }
        }
    }

    public function settings_role_caps_option()
    {
        $all_post_types = $this->get_available_post_types();

        $sorted_cap_types = array();

        foreach ($all_post_types as $post_type => $args) {
            $sorted_cap_types[$args->capability_type][$post_type] = $args;
        }

        echo '<dl class="workflow-authors">';
        foreach ($sorted_cap_types as $cap_type) {
            $labels = array();
            foreach ($cap_type as $post_type => $args) {
                if ($post_type == 'attachment') {
                    continue;
                }
                if ($post_type != $args->capability_type) {
                    $labels[] = $args->label;
                }
            }

            foreach ($cap_type as $post_type => $args) {
                if ($post_type == 'attachment') {
                    continue;
                }
                if ($post_type == $args->capability_type && !empty($this->module->options->post_types[$post_type])) {

                    if (!empty($labels)) {
                        sort($labels);
                        $labels = $args->label . ', ' . implode(', ', $labels);
                    } else {
                        $labels = $args->label;
                    }

                    $caps = array_flip((array) $args->cap);

                    echo '<dt>' . esc_html($labels) . '</dt>';
                    foreach ($this->role_caps as $key => $value) {
                        if (isset($caps[$key])) {
                            echo '<dd>';
                            echo '<label for="' . esc_attr($this->module->workflow_options_name) . '_' . esc_attr($key) . '">';
                            echo '<input id="' . esc_attr($this->module->workflow_options_name) . '_' . esc_attr($key) . '" name="'
                                . $this->module->workflow_options_name . '[role_caps][' . esc_attr($key) . ']"';
                            if (isset($this->module->options->role_caps[$key])) {
                                checked(true, true);
                            }
                            echo ' type="checkbox" />&nbsp;&nbsp;&nbsp;' . esc_html($value) . '</label>';
                            echo '</dd>';
                        }
                    }
                }
            }
        }
        echo '</dl>';
    }

    public function settings_validate($new_options)
    {

        if (empty($new_options['post_types'])) {
            $new_options['post_types'] = array();
        }

        if (empty($new_options['role_caps'])) {
            $new_options['role_caps'] = array();
        }

        $new_options['post_types'] = $this->clean_post_type_options($new_options['post_types'], $this->module->post_type_support);
        $new_role_caps = array();

        $all_post_types = $this->get_available_post_types();

        foreach ($all_post_types as $post_type => $args) {
            if (!empty($new_options['post_types'][$post_type]) && $post_type == $args->capability_type) {

                $edit_posts = isset($args->cap->edit_posts) && !empty($new_options['role_caps'][$args->cap->edit_posts]) ? 1 : 0;
                $new_role_caps["edit_{$args->capability_type}"] = $edit_posts;
                $new_role_caps["edit_{$args->capability_type}s"] = $edit_posts;
                $new_role_caps["edit_others_{$args->capability_type}s"] = $edit_posts;

                $delete_posts = isset($args->cap->delete_posts) && !empty($new_options['role_caps'][$args->cap->delete_posts]) ? 1 : 0;
                $new_role_caps["delete_{$args->capability_type}"] = $delete_posts;
                $new_role_caps["delete_{$args->capability_type}s"] = $delete_posts;
                $new_role_caps["delete_others_{$args->capability_type}s"] = $delete_posts;

                $publish_posts = isset($args->cap->publish_posts) && !empty($new_options['role_caps'][$args->cap->publish_posts]) ? 1 : 0;
                $new_role_caps["publish_{$args->capability_type}s"] = $publish_posts;

                $edit_published_posts = isset($args->cap->edit_published_posts) && !empty($new_options['role_caps'][$args->cap->edit_published_posts]) ? 1 : 0;
                $new_role_caps["edit_published_{$args->capability_type}s"] = $edit_published_posts;

                $delete_published_posts = isset($args->cap->delete_published_posts) && !empty($new_options['role_caps'][$args->cap->delete_published_posts]) ? 1 : 0;
                $new_role_caps["delete_published_{$args->capability_type}s"] = $delete_published_posts;

                $read_private_posts = isset($args->cap->read_private_posts) && !empty($new_options['role_caps'][$args->cap->read_private_posts]) ? 1 : 0;
                $new_role_caps["read_private_{$args->capability_type}s"] = $read_private_posts;

                $edit_private_posts = isset($args->cap->edit_private_posts) && !empty($new_options['role_caps'][$args->cap->edit_private_posts]) ? 1 : 0;
                $new_role_caps["edit_private_{$args->capability_type}s"] = $edit_private_posts;

                $delete_private_posts = isset($args->cap->delete_private_posts) && !empty($new_options['role_caps'][$args->cap->delete_private_posts]) ? 1 : 0;
                $new_role_caps["delete_private_{$args->capability_type}s"] = $delete_private_posts;
            }
        }

        $role = get_role(self::role);

        foreach ($new_role_caps as $cap => $val) {
            if ($val) {
                $role->add_cap($cap);
            } else {
                $role->remove_cap($cap);
                unset($new_role_caps[$cap]);
            }
        }

        $new_options['role_caps'] = $new_role_caps;

        return $new_options;
    }

    public function print_configure_view()
    {
?>
        <form class="basic-settings" action="<?php echo esc_url(menu_page_url($this->module->settings_slug, false)); ?>" method="post">
            <?php settings_fields($this->module->workflow_options_name); ?>
            <?php do_settings_sections($this->module->workflow_options_name); ?>
            <?php
            echo '<input id="cms-workflow-module-name" name="cms_workflow_module_name" type="hidden" value="' . esc_attr($this->module->name) . '" />';
            ?>
            <p class="submit"><?php submit_button(null, 'primary', 'submit', false); ?></p>
        </form>
<?php
    }
}

<?php

namespace RRZE\Workflow\Modules\Dashboard;

defined('ABSPATH') || exit;

use RRZE\Workflow\Main;
use RRZE\Workflow\Module;
use RRZE\Workflow\Modules\Authors\Authors;
use WP_Query;
use function RRZE\Workflow\plugin;

class Dashboard extends Module
{
    public $module;
    public $module_url;
    public $allowed_post_types = array();

    public function __construct(Main $main)
    {
        parent::__construct($main);
        $this->module_url = $this->get_module_url(__FILE__);

        $args = array(
            'title' => __('Dashboard', 'cms-workflow'),
            'description' => __('Inhalte im Dashboard verfolgen.', 'cms-workflow'),
            'module_url' => $this->module_url,
            'slug' => 'dashboard',
            'default_options' => array(
                'recent_drafts_widget' => true,
                'control_recent_drafts' => array(
                    'post_type' => array('post', 'page'),
                    'posts_per_page' => 10
                ),
                'recent_pending_widget' => true,
                'control_recent_pending' => array(
                    'post_type' => array('post', 'page'),
                    'posts_per_page' => 10
                ),
            ),
            'configure_callback' => 'print_configure_view'
        );

        $this->module = $this->main->register_module('dashboard', $args);
    }

    public function init()
    {
        add_action('admin_enqueue_scripts', array($this, 'admin_register_scripts'));
        add_action('wp_dashboard_setup', array($this, 'dashboard_setup'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function admin_register_scripts()
    {
        wp_register_style(
            'workflow-dashboard',
            $this->module_url . 'dashboard.css',
            array('jquery-multiselect'),
            plugin()->getVersion(),
            'all'
        );
        wp_register_script(
            'workflow-dashboard',
            $this->module_url . 'dashboard.js',
            array('jquery-multiselect'),
            plugin()->getVersion(),
            true
        );
        wp_localize_script('workflow-dashboard', 'workflow_dashboard_vars', array(
            'placeholder' => __('Wählen Sie beliebige Post-Types aus', 'cms-workflow'),
            'selectAllText' => __('Alle auswählen', 'cms-workflow'),
            'allSelected' => __('Alle ausgewählt', 'cms-workflow'),
            'countSelected' => __('# von % ausgewählt', 'cms-workflow')
        ));
    }

    public function admin_enqueue_scripts()
    {
        wp_enqueue_style('workflow-dashboard');
        wp_enqueue_script('workflow-dashboard');
    }

    public function dashboard_setup()
    {
        remove_meta_box('dashboard_incoming_links', 'dashboard', 'normal');

        remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
        remove_meta_box('dashboard_recent_drafts', 'dashboard', 'side');

        remove_meta_box('dashboard_plugins', 'dashboard', 'normal');

        $all_post_types = $this->get_available_post_types();
        foreach ($all_post_types as $key => $post_type) {
            if (current_user_can($post_type->cap->edit_posts)) {
                $this->allowed_post_types[$key] = $post_type;
            }
        }

        if (empty($this->allowed_post_types)) {
            return;
        }

        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));

        if ($this->module->options->recent_drafts_widget) {
            wp_add_dashboard_widget('workflow-recent-drafts', __('Aktuelle Entwürfe', 'cms-workflow'), array($this, 'recent_drafts_widget'), array($this, 'control_recent_drafts_widget'));
        }

        if ($this->module->options->recent_pending_widget) {
            wp_add_dashboard_widget('workflow-pending-drafts', __('Aktuelle ausstehende Reviews', 'cms-workflow'), array($this, 'recent_pending_widget'), array($this, 'control_recent_pending_widget'));
        }
    }

    public function recent_drafts_widget($posts = false)
    {
        if (!$posts) {
            $options = $this->module->options->control_recent_drafts;
            $posts_query = new WP_Query(
                array(
                    'post_type' => !empty($options['post_type']) ? (array) $options['post_type'] : array('post', 'page'),
                    'post_status' => 'draft',
                    'posts_per_page' => $options['posts_per_page'],
                    'orderby' => 'modified',
                    'order' => 'DESC'
                )
            );

            $posts = &$posts_query->posts;
        }

        $current_user = wp_get_current_user();

        if ($posts && is_array($posts)) {
            $list = array();
            foreach ($posts as $post) {
                if (!isset($this->allowed_post_types[$post->post_type])) {
                    continue;
                }

                if (!current_user_can('edit_post', $post->ID)) {
                    continue;
                }

                $post_type = $this->allowed_post_types[$post->post_type];

                $authors = array();

                if ($this->module_activated('authors')) {
                    $authors = Authors::get_authors($post->ID, 'id');
                }

                $authors[$post->post_author] = $post->post_author;
                $authors = array_unique($authors);

                if (!current_user_can($post_type->cap->publish_posts) && !in_array($current_user->ID, $authors)) {
                    continue;
                }

                $url = get_edit_post_link($post->ID);
                $title = _draft_or_post_title($post->ID);
                $last_id = get_post_meta($post->ID, '_edit_last', true);
                if ($last_id) {
                    $last_modified = esc_html(get_userdata($last_id)->display_name);
                }

                $item = sprintf('<li class="%s-draft">', $post->post_type);
                $item .= sprintf('<a href="%1$s">%2$s</a><abbr> &mdash;%3$s&mdash;</abbr>', $url, esc_html($title), $post_type->labels->singular_name);
                if (isset($last_modified)) {
                    $item .= sprintf('<abbr>' . __('Zuletzt geändert von <i>%1$s</i> am %2$s um %3$s Uhr', 'cms-workflow') . '</abbr>', $last_modified, mysql2date(get_option('date_format'), $post->post_modified), mysql2date(get_option('time_format'), $post->post_modified));
                }

                $item .= '</li>';
                $list[] = $item;
            }
            ?>
            <ul class="status-draft">
                <?php echo implode(PHP_EOL, $list); ?>
            </ul>
            <?php
        } else {
            printf('<div class="no-results-np">%s</div>', __('Zurzeit gibt es keine Entwürfe.', 'cms-workflow'));
        }
    }

    public function control_recent_drafts_widget()
    {


        if (!empty($_POST['recent_drafts_widget'])) {
            check_admin_referer('_recent_drafts_widget');
            $control_recent_drafts = array(
                'post_type' => (array) @$_POST['recent_drafts_widget']['post_type'],
                'posts_per_page' => (int) @$_POST['recent_drafts_widget']['posts_per_page'],
            );

            $this->main->update_module_option($this->module->name, 'control_recent_drafts', $control_recent_drafts);
        }

        $options = $this->module->options->control_recent_drafts;
        if (empty($options['post_type'])) {
            $options['post_type'] = array_keys($this->allowed_post_types);
        }
        wp_nonce_field('_recent_drafts_widget');
        ?>
        <table class="form-table">
            <tr>
                <td>
                    <label for="recent_drafts_widget_post_type"><?php _e('Post-Types:', 'cms-workflow'); ?></label>
                    <select name="recent_drafts_widget[post_type][]" multiple tabindex="3" id="recent-drafts-widget-post-type-select">
                        <?php foreach ($this->allowed_post_types as $post_type => $pt) : ?>
                            <?php if ($post_type == 'attachment') continue; ?>
                            <option value="<?php echo $post_type; ?>" <?php selected(in_array($post_type, (array) $options['post_type']) ? $post_type : null, $post_type); ?>><?php echo $pt->label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <td>
                    <select name="recent_drafts_widget[posts_per_page]" id="recent-drafts-widget-posts-per-page">
                        <?php foreach (array(10, 20, 30, 50) as $num) : ?>
                            <option value="<?php echo $num; ?>" <?php selected($options['posts_per_page'], $num); ?>><?php echo $num; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="recent-drafts-widget-posts-per-page"><?php _e('Anzahl der Einträge in Listen', 'cms-workflow'); ?></label>
                </td>
            </tr>
        </table>
        <?php
    }

    public function recent_pending_widget($posts = false)
    {
        if (!$posts) {
            $options = $this->module->options->control_recent_pending;
            $posts_query = new WP_Query(
                array(
                    'post_type' => !empty($options['post_type']) ? (array) $options['post_type'] : array('post', 'page'),
                    'post_status' => 'pending',
                    'posts_per_page' => $options['posts_per_page'],
                    'orderby' => 'modified',
                    'order' => 'DESC'
                )
            );

            $posts = &$posts_query->posts;
        }

        $current_user = wp_get_current_user();

        if ($posts && is_array($posts)) {
            $list = array();
            foreach ($posts as $post) {
                if (!isset($this->allowed_post_types[$post->post_type])) {
                    continue;
                }

                if (!current_user_can('edit_post', $post->ID)) {
                    continue;
                }

                $post_type = $this->allowed_post_types[$post->post_type];

                $authors = array();

                if ($this->module_activated('authors')) {
                    $authors = Authors::get_authors($post->ID, 'id');
                }

                $authors[$post->post_author] = $post->post_author;
                $authors = array_unique($authors);

                if (!current_user_can($post_type->cap->publish_posts) && !in_array($current_user->ID, $authors)) {
                    continue;
                }

                $url = get_edit_post_link($post->ID);
                $title = _draft_or_post_title($post->ID);
                $last_id = get_post_meta($post->ID, '_edit_last', true);

                if ($last_id) {
                    $last_modified = esc_html(get_userdata($last_id)->display_name);
                }

                $item = sprintf('<li class="%s-pending">', $post->post_type);
                $item .= sprintf('<a href="%1$s">%2$s</a><abbr> &mdash;%3$s&mdash;</abbr>', $url, esc_html($title), $post_type->labels->singular_name);
                if (isset($last_modified)) {
                    $item .= sprintf('<abbr>' . __('Zuletzt geändert von <i>%1$s</i> am %2$s um %3$s Uhr', 'cms-workflow') . '</abbr>', $last_modified, mysql2date(get_option('date_format'), $post->post_modified), mysql2date(get_option('time_format'), $post->post_modified));
                }

                $item .= '</li>';
                $list[] = $item;
            }
            ?>
            <ul class="status-pending">
                <?php echo implode(PHP_EOL, $list); ?>
            </ul>
            <?php
        } else {
            printf('<div class="no-results-np">%s</div>', __('Zurzeit gibt es keine ausstehenden Reviews.', 'cms-workflow'));
        }
    }

    public function control_recent_pending_widget()
    {


        if (!empty($_POST['recent_pending_widget'])) {
            check_admin_referer('_recent_pending_widget');
            $control_recent_pending = array(
                'post_type' => (array) @$_POST['recent_pending_widget']['post_type'],
                'posts_per_page' => (int) @$_POST['recent_pending_widget']['posts_per_page'],
            );

            $this->main->update_module_option($this->module->name, 'control_recent_pending', $control_recent_pending);
        }

        $options = $this->module->options->control_recent_pending;
        if (empty($options['post_type'])) {
            $options['post_type'] = array_keys($this->allowed_post_types);
        }
        wp_nonce_field('_recent_pending_widget');
        ?>
        <table class="form-table">
            <tr>
                <td>
                    <label for="recent-pending-widget-post-type"><?php _e('Post-Types:', 'cms-workflow'); ?></label>
                    <select name="recent_pending_widget[post_type][]" multiple tabindex="3" id="recent-pending-widget-post-type-select">
                        <?php foreach ($this->allowed_post_types as $post_type => $pt) : ?>
                            <?php if ($post_type == 'attachment') continue; ?>
                            <option value="<?php echo $post_type; ?>" <?php selected(in_array($post_type, (array) $options['post_type']) ? $post_type : null, $post_type); ?>><?php echo $pt->label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <td>
                    <select name="recent_pending_widget[posts_per_page]" id="recent-pending-widget-posts-per-page">
                        <?php foreach (array(10, 20, 30, 50) as $num) : ?>
                            <option value="<?php echo $num; ?>" <?php selected($options['posts_per_page'], $num); ?>><?php echo $num; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="recent-pending-widget-posts-per-page"><?php _e('Anzahl der Einträge in Listen', 'cms-workflow'); ?></label>
                </td>
            </tr>
        </table>
    <?php
    }

    public function register_settings()
    {
        add_settings_section($this->module->workflow_options_name . '_general', false, '__return_false', $this->module->workflow_options_name);
        add_settings_field('recent_drafts_widget', __('Aktuelle Entwürfe', 'cms-workflow'), array($this, 'settings_recent_drafts_option'), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general');
        add_settings_field('recent_pending_widget', __('Aktuelle ausstehende Reviews', 'cms-workflow'), array($this, 'settings_recent_pending_option'), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general');
    }

    public function settings_recent_drafts_option()
    {
        $options = array(
            false => __('Deaktiviert', 'cms-workflow'),
            true => __('Aktiviert', 'cms-workflow'),
        );
        echo '<select id="recent_drafts_widget" name="' . $this->module->workflow_options_name . '[recent_drafts_widget]">';
        foreach ($options as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"';
            echo selected($this->module->options->recent_drafts_widget, $value);
            echo '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }

    public function settings_recent_pending_option()
    {
        $options = array(
            false => __('Deaktiviert', 'cms-workflow'),
            true => __('Aktiviert', 'cms-workflow'),
        );
        echo '<select id="recent_pending_widget" name="' . $this->module->workflow_options_name . '[recent_pending_widget]">';
        foreach ($options as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"';
            echo selected($this->module->options->recent_pending_widget, $value);
            echo '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }

    public function settings_validate($new_options)
    {

        if (array_key_exists('recent_drafts_widget', $new_options) && !$new_options['recent_drafts_widget']) {
            $new_options['recent_drafts_widget'] = false;
        }

        if (array_key_exists('recent_pending_widget', $new_options) && !$new_options['recent_pending_widget']) {
            $new_options['recent_pending_widget'] = false;
        }

        return $new_options;
    }

    public function print_configure_view()
    {
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
}

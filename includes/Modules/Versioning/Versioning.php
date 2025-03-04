<?php

namespace RRZE\Workflow\Modules\Versioning;

defined('ABSPATH') || exit;

use RRZE\Workflow\Main;
use RRZE\Workflow\Module;
use function RRZE\Workflow\plugin;

class Versioning extends Module
{
    public $module_url;

    const source_post_id = '_source_post_id';
    const version_post_id = '_version_post_id';
    const version_remote_parent_post_meta = '_version_remote_parent_post_meta';
    const version_remote_post_meta = '_version_remote_post_meta';

    protected $not_filtered_post_meta = array();

    public $module;

    public function __construct(Main $main)
    {
        parent::__construct($main);
        $this->module_url = $this->get_module_url(__FILE__);

        $args = array(
            'title' => __('Versionierung', 'cms-workflow'),
            'description' => __('Neue Version bzw. eine Kopie aus einem vorhandenen Dokument erstellen.', 'cms-workflow'),
            'module_url' => $this->module_url,
            'slug' => 'versioning',
            'default_options' => array(
                'post_types' => array(
                    'post' => true,
                    'page' => true
                ),
                'related_sites' => array(),
            ),
            'configure_callback' => 'print_configure_view'
        );

        $this->module = $this->main->register_module('versioning', $args);
    }

    public function init()
    {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));

        add_action('admin_action_copy_as_new_post_draft', array($this, 'copy_as_new_post_draft'));
        add_action('admin_action_version_as_new_post_draft', array($this, 'version_as_new_post_draft'));
        add_action('admin_notices', array($this, 'admin_notices'));

        $allowed_post_types = $this->get_post_types($this->module);

        foreach ($allowed_post_types as $post_type) {
            add_action('publish_' . $post_type, array($this, 'version_post_replace_on_publish'), 99, 2);

            $filter_row_actions = is_post_type_hierarchical($post_type) ? 'page_row_actions' : 'post_row_actions';
            add_filter($filter_row_actions, array($this, 'filter_post_row_actions'), 10, 2);

            add_filter("manage_edit-{$post_type}_columns", array($this, 'custom_columns'));
            add_action("manage_{$post_type}_posts_custom_column", array($this, 'posts_custom_column'), 10, 2);
            //add_filter("manage_edit-{$post_type}_sortable_columns", array($this, 'posts_sortable_columns'));
        }

        if (is_multisite() && $this->module_activated('network')) {
            add_action('add_meta_boxes', array($this, 'network_connections_meta_box'), 10, 2);
            add_action('save_post', array($this, 'network_connections_save_post'));
        }

        //add_action('trash_' . $post_type, array($this, 'normalize_on_trash'), 10, 2);

        add_action('wp_before_admin_bar_render', array($this, 'admin_bar_submenu'), 99);
    }

    public function admin_enqueue_scripts()
    {
        wp_enqueue_style(
            'workflow-versioning',
            $this->module_url . 'versioning.css',
            false,
            plugin()->getVersion(),
            'all'
        );
    }

    public function register_settings()
    {
        add_settings_section($this->module->workflow_options_name . '_general', false, '__return_false', $this->module->workflow_options_name);
        add_settings_field('post_types', __('Freigabe', 'cms-workflow'), array($this, 'settings_post_types_option'), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general');

        if ($this->module_activated('network')) {
            add_settings_field('network_connections', __('Bezogenen Webseiten', 'cms-workflow'), array($this, 'settings_network_connections_option'), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general');
        }
    }

    public function settings_post_types_option()
    {
        $this->main->settings->custom_post_type_option($this->module);
    }

    public function settings_network_posts_types_option()
    {
        $this->main->settings->custom_post_type_option($this->module, 'network_posts_types');
    }

    public function settings_network_connections_option()
    {
        $current_network_related_sites = $this->current_network_related_sites();
        $current_related_sites = $this->current_related_sites();

        if (!empty($current_network_related_sites)) :
            foreach ($current_network_related_sites as $blog_id) {
                if (!switch_to_blog($blog_id)) {
                    continue;
                }

                $site_name = get_bloginfo('name');
                $site_url = get_bloginfo('url');
                $sitelang = self::get_locale();

                restore_current_blog();

                $language = self::get_language($sitelang);
                $label = ($site_name != '') ? sprintf('%1$s (%2$s) (%3$s)', $site_name, $site_url, $language['native_name']) : $site_url;
                $connected = in_array($blog_id, $current_related_sites) ? true : false;
?>
                <label for="related_sites_<?php echo $blog_id; ?>">
                    <input id="related_sites_<?php echo $blog_id; ?>" type="checkbox" <?php checked($connected, true); ?> name="<?php printf('%s[related_sites][]', $this->module->workflow_options_name); ?>" value="<?php echo $blog_id ?>"> <?php echo $label; ?>
                </label><br>
            <?php
            }
            ?>
            <p class="description"><?php _e('Lokale Dokumente können in diesen Webseiten als neue Version (Entwurf) angelegt werden.', 'cms-workflow'); ?></p>
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

        // Related sites
        $new_options['related_sites'] = !empty($new_options['related_sites']) ? (array) $new_options['related_sites'] : array();

        $current_blog_id = get_current_blog_id();
        $network_related_sites = $this->current_network_related_sites();
        $related_sites = array();

        foreach ($network_related_sites as $blog_id) {
            if ($current_blog_id == $blog_id) {
                continue;
            }

            if (!in_array($blog_id, $new_options['related_sites'])) {
                continue;
            }

            $related_sites[] = $blog_id;
        }

        $new_options['related_sites'] = $related_sites;

        return $new_options;
    }

    private function current_related_sites()
    {
        $network_related_sites = $this->current_network_related_sites();
        $current_related_sites = (array) $this->module->options->related_sites;
        $related_sites = array();

        foreach ($current_related_sites as $blog_id) {
            if (in_array($blog_id, $network_related_sites)) {
                $related_sites[] = $blog_id;
            }
        }

        $this->main->update_module_option($this->module->name, 'related_sites', $related_sites);

        return $related_sites;
    }

    private function current_network_related_sites()
    {
        $current_network_related_sites = array();

        if (isset($this->main->network)) {
            $connections = $this->main->network->site_connections();
            $network_connections = $this->main->network->network_connections($connections);

            foreach ($network_connections as $blog_id) {
                if (!switch_to_blog($blog_id)) {
                    continue;
                }

                $current_network_related_sites[] = $blog_id;
                restore_current_blog();
            }
        }

        return $current_network_related_sites;
    }

    public function print_configure_view()
    {
        ?>
        <form class="basic-settings" action="<?php echo esc_url(menu_page_url($this->module->settings_slug, false)); ?>" method="post">
            <?php echo '<input id="cms_workflow_module_name" name="cms_workflow_module_name" type="hidden" value="' . esc_attr($this->module->name) . '">'; ?>
            <?php settings_fields($this->module->workflow_options_name); ?>
            <?php do_settings_sections($this->module->workflow_options_name); ?>
            <p class="submit"><?php submit_button(null, 'primary', 'submit', false); ?></p>
        </form>
    <?php
    }

    private function is_author($user_id, $post_id)
    {
        if ($this->module_activated('authors') && $this->main->authors->is_post_author($user_id, $post_id)) {
            return true;
        }

        return false;
    }

    public function filter_post_row_actions($actions, $post)
    {
        if (!is_object($this->get_available_post_types($post->post_type)) || !in_array($post->post_type, $this->get_post_types($this->module))) {
            return $actions;
        }

        $cap = $this->get_available_post_types($post->post_type)->cap;

        if (current_user_can($cap->edit_posts) && !get_post_meta($post->ID, self::version_post_id, true) && $post->post_status != 'trash') {
            $actions['edit_as_new_draft'] = '<a href="' . admin_url('admin.php?action=copy_as_new_post_draft&post=' . $post->ID) . '" title="'
                . esc_attr(__('Dieses Element als neuen Entwurf kopieren', 'cms-workflow'))
                . '">' . __('Kopieren', 'cms-workflow') . '</a>';
        }

        if (current_user_can($cap->edit_posts) && $post->post_status == 'publish' && ($this->is_author(get_current_user_id(), $post->ID) || current_user_can('edit_published_posts'))) {
            $actions['edit_as_version'] = '<a href="' . admin_url('admin.php?action=version_as_new_post_draft&post=' . $post->ID) . '" title="'
                . esc_attr(__('Dieses Element als neue Version duplizieren', 'cms-workflow'))
                . '">' . __('Neue Version', 'cms-workflow') . '</a>';
        }

        return $actions;
    }

    private function has_version($post_id, $post_type)
    {
        global $wpdb;

        $query = $wpdb->prepare("
            SELECT $wpdb->posts.ID
            FROM $wpdb->posts, $wpdb->postmeta
            WHERE $wpdb->posts.ID = $wpdb->postmeta.post_id
                AND $wpdb->postmeta.meta_key = %s
                AND $wpdb->postmeta.meta_value = %d
                AND ($wpdb->posts.post_status = 'draft' OR $wpdb->posts.post_status = 'pending')
                AND $wpdb->posts.post_type = %s", self::version_post_id, $post_id, $post_type);

        $results = $wpdb->get_results($query);

        return $results;
    }

    public function version_as_new_post_draft()
    {
        if (!(isset($_GET['post']) || isset($_POST['post']))) {
            wp_die(__('Es wurde kein Element geliefert.', 'cms-workflow'));
        }

        $post_id = (int) isset($_GET['post']) ? $_GET['post'] : $_POST['post'];
        $post = get_post($post_id);

        if (is_null($post)) {
            wp_die(__('Es wurde kein Element mit der angegebenen ID gefunden.', 'cms-workflow'));
        }

        $cap = $this->get_available_post_types($post->post_type)->cap;

        if (!current_user_can($cap->edit_posts) || $post->post_status != 'publish' || (!$this->is_author(get_current_user_id(), $post->ID) && !current_user_can('edit_published_posts'))) {
            wp_die(__('Sie haben nicht die erforderlichen Rechte, um eine neue Version zu erstellen.', 'cms-workflow'));
        }

        if (!$this->is_post_type_enabled($post->post_type)) {
            wp_die(__('Diese Aktion ist nicht erlaubt.', 'cms-workflow'));
        }

        if ($post->post_status != 'publish') {
            wp_die(__('Nur veröffentlichte Dokumente können als neue Version erstellt werden.', 'cms-workflow'));
        }

        if ($results = $this->has_version($post->ID, $post->post_type)) {
            foreach ($results as $version) {
                $src_permalink = get_permalink($post->ID);
                $src_post_title = get_the_title($post->ID);

                $permalink = get_permalink($version->ID);
                $post_title = get_the_title($version->ID);

                $this->flash_admin_notice(sprintf(__('Lokale Version &bdquo;<a href="%1$s" target="__blank">%2$s</a>&ldquo; vom Dokument &bdquo;<a href="%3$s" target="__blank">%4$s</a>&ldquo; existiert bereits.', 'cms-workflow'), $permalink, $post_title, $src_permalink, $src_post_title), 'error');
            }

            wp_safe_redirect(admin_url('edit.php?post_type=' . $post->post_type));
            exit;
        } else {
            add_filter('workflow_notification_post_updated', '__return_false');
            add_filter('workflow_notification_status_change', '__return_false');

            $this->not_filtered_post_meta = array(self::version_remote_parent_post_meta, $this->module->workflow_options_name . '_network_connections');

            $new_post = array(
                'post_author' => get_current_user_id(),
                'post_content' => '',
                'post_title' => $post->post_title,
                'post_excerpt' => '',
                'post_status' => 'draft',
                'post_parent' => $post->post_parent,
                'menu_order' => $post->menu_order,
                'post_type' => $post->post_type
            );

            $draft_id = wp_insert_post($new_post);

            if ($draft_id) {
                do_action('workflow_version_as_new_post_draft', $draft_id, $post_id);

                add_post_meta($draft_id, self::version_post_id, $post_id);

                $post_meta = $this->get_post_meta($post_id);

                $thumbnail_id = get_post_meta($post_id, '_thumbnail_id', true);
                if ($thumbnail_id) {
                    add_post_meta($draft_id, '_thumbnail_id', $thumbnail_id);
                }

                $this->add_taxonomies($draft_id, $post);

                $this->add_post_meta($draft_id, $post_meta);

                // Generate first revision
                wp_update_post(array(
                    'ID' => $draft_id,
                    'post_content' => $post->post_content,
                    'post_title' => $post->post_title,
                    'post_excerpt' => $post->post_excerpt
                ));

                wp_safe_redirect(admin_url('post.php?post=' . $draft_id . '&action=edit'));
                exit;
            }
        }

        $this->flash_admin_notice(__('Ein unerwarteter Fehler ist aufgetreten. Bitte versuchen Sie es erneut.', 'cms-workflow'), 'error');
        wp_safe_redirect(admin_url('edit.php?post_type=' . $post->post_type));
        exit;
    }

    public function version_post_replace_on_publish($post_id, $post)
    {
        $version_post_id = get_post_meta($post_id, self::version_post_id, true);

        if (!$version_post_id) {
            return;
        }

        $cap = $this->get_available_post_types($post->post_type)->cap;

        if (!current_user_can($cap->edit_posts)) {
            wp_die(__('Sie haben nicht die erforderlichen Rechte, um eine neue Version zu erstellen.', 'cms-workflow'));
        }

        add_filter('workflow_version_post_replace_on_publish', '__return_true');

        $this->not_filtered_post_meta = array(self::version_remote_parent_post_meta, $this->module->workflow_options_name . '_network_connections');

        $post_meta = $this->get_post_meta($post_id);

        $thumbnail_id = get_post_meta($post_id, '_thumbnail_id', TRUE);

        $new_post = array(
            'ID' => $version_post_id,
            'post_content' => $post->post_content,
            'post_title' => $post->post_title,
            'post_excerpt' => $post->post_excerpt,
            'post_status' => 'publish'
        );

        wp_update_post($new_post, TRUE);
        if (is_wp_error($version_post_id)) {
            $this->flash_admin_notice(__('Ein unerwarteter Fehler ist aufgetreten. Bitte versuchen Sie es erneut.', 'cms-workflow'), 'error');
            wp_update_post(array(
                'ID' => $post_id,
                'post_status' => 'pending'
            ));
            wp_safe_redirect(admin_url('post.php?post=' . $post_id . '&action=edit'));
            exit;
        }

        // Update revision
        $post_revisions = wp_get_post_revisions($version_post_id);
        $latest_revision = array_shift($post_revisions);
        wp_update_post(array(
            'ID' => $latest_revision->ID,
            'post_author' => $post->post_author
        ));

        $this->add_taxonomies($version_post_id, $post);

        if ($thumbnail_id) {
            update_post_meta($version_post_id, '_thumbnail_id', $thumbnail_id);
        }

        $this->update_post_meta($version_post_id, $post_meta);

        wp_delete_post($post_id, TRUE);

        if (defined('DOING_AJAX') && DOING_AJAX) {
            if (isset($_POST['post_ID'])) {
                $_POST['post_ID'] = $version_post_id;
                $_POST['ID'] = $version_post_id;
                $_POST['post_type'] = $post->post_type;
            }
            return;
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            return;
        }

        wp_safe_redirect(admin_url('post.php?post=' . $version_post_id . '&action=edit&message=1'));
        exit;
    }

    public function normalize_on_trash($post_id, $post)
    {
        delete_post_meta($post_id, self::source_post_id);
        delete_post_meta($post_id, self::version_post_id);
        delete_post_meta($post_id, self::version_remote_parent_post_meta);
        delete_post_meta($post_id, self::version_remote_post_meta);
        delete_post_meta($post_id, $this->module->workflow_options_name . '_network_connections');
    }

    public function admin_notices()
    {
        global $post;

        if ($post && isset($_REQUEST['post'])) {

            $old_post_id = get_post_meta($post->ID, self::version_post_id, true);

            if ($old_post_id) {
                $permalink = get_permalink($old_post_id);
                $post_title = get_the_title($old_post_id);

                if (current_user_can('edit_published_posts')) {
                    $this->show_admin_notice(sprintf(__('Lokale Version vom Dokument &bdquo;<a href="%1$s" target="__blank">%2$s</a>&ldquo;. Überschreiben Sie das ursprüngliche Dokument, indem Sie auf &bdquo;Veröffentlichen&rdquo; klicken.', 'cms-workflow'), $permalink, $post_title));
                } else {
                    $this->show_admin_notice(sprintf(__('Lokale Version vom Dokument &bdquo;<a href="%1$s" target="__blank">%2$s</a>&ldquo;.', 'cms-workflow'), $permalink, $post_title));
                }
            } elseif (is_multisite() && $this->module_activated('network')) {
                $current_related_sites = $this->current_related_sites();

                if (!$current_related_sites) {

                    $remote_post_meta = (array) get_post_meta($post->ID, self::version_remote_post_meta);

                    foreach ($remote_post_meta as $post_meta) {
                        if (!isset($post_meta['blog_id']) || !isset($post_meta['post_id'])) {
                            continue;
                        }

                        if (!switch_to_blog($post_meta['blog_id'])) {
                            continue;
                        }

                        $permalink = get_permalink($post_meta['post_id']);
                        if ($permalink) {
                            $blog_name = get_bloginfo('name');
                            $blog_lang = self::get_language(self::get_locale());
                            $post_title = get_the_title($post_meta['post_id']);
                            echo $this->show_admin_notice(sprintf(__('Netzwerkweite Versionierung vom Dokument &bdquo;<a href="%1$s" target="__blank">%2$s</a>&ldquo; - %3$s - %4$s.', 'cms-workflow'), $permalink, $post_title, $blog_name, $blog_lang['native_name']));
                        }

                        restore_current_blog();
                    }
                }
            }
        }

        $this->show_flash_admin_notices();
    }

    public function copy_as_new_post_draft()
    {
        if (!(isset($_GET['post']) || isset($_POST['post']))) {
            wp_die(__('Es wurde kein Element geliefert.', 'cms-workflow'));
        }

        $post_id = (int) isset($_GET['post']) ? $_GET['post'] : $_POST['post'];
        $post = get_post($post_id);

        if (is_null($post)) {
            wp_die(__('Es wurde kein Element mit der angegebenen ID gefunden.', 'cms-workflow'));
        }

        $cap = $this->get_available_post_types($post->post_type)->cap;

        if (!current_user_can($cap->edit_posts) || get_post_meta($post_id, self::version_post_id, true) || $post->post_status == 'trash') {
            wp_die(__('Sie haben nicht die erforderlichen Rechte, um eine neue Kopie zu erstellen.', 'cms-workflow'));
        }

        if (in_array($post->post_type, array('revision', 'attachment'))) {
            wp_die(__('Sie haben versucht ein Element zu bearbeiten, das nicht erlaubt ist. Bitte kehren Sie zurück und versuchen Sie es erneut.', 'cms-workflow'));
        }

        $post_author = get_current_user_id();
        $post_status = 'draft';

        $new_post = array(
            'post_author' => $post_author,
            'post_content' => $post->post_content,
            'post_title' => $post->post_title,
            'post_excerpt' => $post->post_excerpt,
            'post_status' => $post_status,
            'post_type' => $post->post_type
        );

        $draft_id = wp_insert_post($new_post);

        if ($draft_id) {
            add_post_meta($draft_id, self::source_post_id, $post_id);

            $post_meta = $this->get_post_meta($post_id);

            $thumbnail_id = get_post_meta($post_id, '_thumbnail_id', true);
            if ($thumbnail_id) {
                add_post_meta($draft_id, '_thumbnail_id', $thumbnail_id);
            }

            $this->add_post_meta($draft_id, $post_meta);

            wp_safe_redirect(admin_url('post.php?post=' . $draft_id . '&action=edit'));
            exit;
        }

        $this->flash_admin_notice(__('Ein unerwarteter Fehler ist aufgetreten. Bitte versuchen Sie es erneut.', 'cms-workflow'), 'error');
        wp_safe_redirect(admin_url('edit.php?post_type=' . $post->post_type));
        exit;
    }

    public function network_connections_meta_box($post_type, $post)
    {
        if (!current_user_can('manage_categories')) {
            return;
        }

        if (!$this->is_post_type_enabled($post_type)) {
            return;
        }

        $related_sites = $this->current_related_sites();
        if (empty($related_sites)) {
            return;
        }

        if ($post->post_status != 'publish') {
            return;
        }

        $network_connections = (array) get_post_meta($post->ID, $this->module->workflow_options_name . '_network_connections', true);
        $current_blog_id = get_current_blog_id();
        $connected = false;

        foreach ($related_sites as $blog_id) {
            if ($current_blog_id == $blog_id) {
                continue;
            }

            if (!switch_to_blog($blog_id)) {
                continue;
            }

            $allowed_post_type = in_array($post_type, $this->get_post_types($this->module)) ? true : false;
            restore_current_blog();

            if ($allowed_post_type && in_array($blog_id, $network_connections)) {
                $connected = true;
                break;
            }
        }

        if ($connected) {
            add_action('post_submitbox_start', array($this, 'network_connections_post_submitbox'));
        }

        add_meta_box('network-connections', __('Netzwerkweite Webseiten', 'cms-workflow'), array($this, 'network_connections_post_meta'), $post_type, 'normal', 'high');
    }

    public function network_connections_post_submitbox()
    {
    ?>
        <p>
            <input type="checkbox" id="network_connections_version" name="network_connections_version" <?php checked(false, true); ?>>
            <label for="network_connections_version"><?php _e('Netzwerkweite Versionierung', 'cms-workflow'); ?></label>
        </p>
    <?php
    }

    public function network_connections_post_meta($post)
    {
        $post_id = $post->ID;
        $post_type = $post->post_type;

        $related_sites = $this->current_related_sites();
        $network_connections = (array) get_post_meta($post_id, $this->module->workflow_options_name . '_network_connections', true);
        $remote_parent_post_meta = get_post_meta($post_id, self::version_remote_parent_post_meta);
        $current_blog_id = get_current_blog_id();
    ?>
        <div id="network-connections-box">
            <div id="network-connections-inside">
                <ul class="network-connections-list" class="form-no-clear">
                    <?php
                    foreach ($related_sites as $blog_id) :
                        if ($current_blog_id == $blog_id) {
                            continue;
                        }

                        $connected = in_array($blog_id, $network_connections) ? true : false;

                        if (!switch_to_blog($blog_id)) {
                            continue;
                        }

                        $allowed_post_type = in_array($post_type, $this->get_post_types($this->module)) ? true : false;

                        $blog_name = get_bloginfo('name');
                        $blog_lang = self::get_language(self::get_locale());

                        $posts = get_posts(
                            array(
                                'post_type' => $post_type,
                                'post_status' => array('draft', 'publish'),
                                'orderby' => 'title',
                                'order' => 'ASC',
                                'nopaging' => true
                            )
                        );

                        if (!empty($posts)) {
                            $output = '';
                            $post_status = array('draft' => __('Entwurf', 'cms-workflow'), 'publish' => __('Veröffentlicht', 'cms-workflow'));
                            $selected_post = $this->is_remote_parent_post_selected($blog_id, $posts, $remote_parent_post_meta);
                            if (!empty($selected_post)) {
                                $output .= "<input type=\"hidden\" name=\"network_related_post_$blog_id\" value=\"" . $selected_post->ID . "\">" . PHP_EOL;
                                $output .= sprintf('<span class="related-postdetail">%1$d. <a href="%2$s" target="__blank">%3$s</a></span>', $selected_post->ID, get_permalink($selected_post->ID), esc_html($selected_post->post_title)) . PHP_EOL;
                            } elseif (in_array($blog_id, $network_connections)) {
                                $output = "<p><strong>" . __('Bezogenes Dokument', 'cms-workflow') . "</strong></p>" . PHP_EOL;
                                $output = "<select name=\"network_add_related_post_$blog_id\">" . PHP_EOL;
                                $output .= "<option value=\"0\">" . __('— Kein Dokument —', 'cms-workflow') . "</option>" . PHP_EOL;
                                foreach ($posts as $p) {
                                    $remote_post_meta = (array) get_post_meta($p->ID, self::version_remote_post_meta);
                                    if (!$this->in_remote_post_meta($current_blog_id, $remote_post_meta)) {
                                        $output .= sprintf('<option value="%1$d">%1$d. %2$s (%3$s)</option>', $p->ID, esc_html($p->post_title), $post_status[$p->post_status]) . PHP_EOL;
                                    } else {
                                        $output .= sprintf('<option value="" disabled="disbaled">%1$d. %2$s (%3$s)</option>', $p->ID, esc_html($p->post_title), $post_status[$p->post_status]) . PHP_EOL;
                                    }
                                }
                                $output .= "</select>" . PHP_EOL;
                            }
                        }

                        restore_current_blog();
                    ?>
                        <li>
                            <label class="selectit">
                                <?php if ($allowed_post_type) : ?>
                                    <input id="connected_blog_<?php echo $blog_id; ?>" type="checkbox" <?php checked($connected, true); ?> name="network_connections[]" value="<?php echo $blog_id ?>">
                                <?php endif; ?>
                                <span class="related-blogdetail"><?php printf('%1$s (%2$s)', $blog_name, $blog_lang['native_name']); ?></span>
                                <?php if ($connected && !empty($posts) && $allowed_post_type) : ?>
                                    <?php echo $output; ?>
                                <?php elseif (!$allowed_post_type) : ?>
                                    <span class="related-postdetail"><?php _e('Keine Versionierungsfreigabe.', 'cms-workflow'); ?></span>
                                <?php elseif ($connected) : ?>
                                    <span class="related-postdetail"><?php _e('Kein Dokument zur Auswahl.', 'cms-workflow'); ?></span>
                                <?php endif; ?>
                            </label>
                        </li>
                    <?php
                    endforeach;
                    ?>
                </ul>
            </div>
        </div>
<?php
    }

    private function is_remote_parent_post_selected($blog_id, $posts, $remote_parent_post_meta)
    {
        if (empty($blog_id) || empty($posts) || empty($remote_parent_post_meta)) {
            return;
        }

        foreach ($posts as $post) {

            foreach ($remote_parent_post_meta as $post_meta) {
                if (isset($post_meta['post_id']) && isset($post_meta['blog_id']) && $post_meta['post_id'] == $post->ID && $post_meta['blog_id'] == $blog_id) {
                    $selected_post = array(
                        'ID' => $post->ID,
                        'post_title' => esc_html($post->post_title),
                        'post_status' => $post->post_status
                    );
                    return (object) $selected_post;
                }
            }
        }
        return false;
    }

    private function in_remote_post_meta($blog_id, $remote_post_meta)
    {
        foreach ($remote_post_meta as $post_meta) {

            if (!isset($post_meta['blog_id']) || !isset($post_meta['post_id'])) {
                continue;
            }

            if ($post_meta['blog_id'] != $blog_id) {
                continue;
            }
            switch_to_blog($blog_id);
            $post_exist = get_post_status($post_meta['post_id']);
            restore_current_blog();
            if ($post_exist) {
                return true;
            }
        }

        return false;
    }

    public function network_connections_save_post($post_id)
    {
        $this->network_connections_save_post_meta($post_id);
        $this->network_connections_save_post_submitbox($post_id);
    }

    private function network_connections_save_post_meta($post_id)
    {
        if (wp_is_post_revision($post_id)) {
            return;
        }

        if (!current_user_can('manage_categories')) {
            return;
        }

        $post = get_post($post_id);

        if ($post->post_status != 'publish') {
            return;
        }

        $new_network_connections = isset($_POST['network_connections']) ? (array) $_POST['network_connections'] : array();
        $related_sites = $this->current_related_sites();
        $network_connections = array();

        foreach ($new_network_connections as $key => $blog_id) {
            if (in_array($blog_id, $related_sites)) {
                $network_connections[] = $blog_id;
            }
        }

        update_post_meta($post_id, $this->module->workflow_options_name . '_network_connections', $network_connections);

        $current_blog_id = get_current_blog_id();
        $related_sites = $this->current_related_sites();

        foreach ($related_sites as $blog_id) {

            if ($blog_id == $current_blog_id) {
                continue;
            }

            if (!in_array($post->post_type, $this->get_post_types($this->module))) {
                restore_current_blog();
                continue;
            }

            $connected = in_array($blog_id, $network_connections) ? true : false;
            $add_related_post_id = isset($_POST['network_add_related_post_' . $blog_id]) ? (int) $_POST['network_add_related_post_' . $blog_id] : null;
            $related_post_id = isset($_POST['network_related_post_' . $blog_id]) ? (int) $_POST['network_related_post_' . $blog_id] : null;

            $remote_post_id = null;

            if ($connected && $add_related_post_id) {
                $remote_post_id = $add_related_post_id;
                $add_post_meta = true;
            } elseif (!$connected && $related_post_id) {
                $remote_post_id = $related_post_id;
                $add_post_meta = false;
            }

            if (!$remote_post_id) {
                continue;
            }

            if (!switch_to_blog($blog_id)) {
                continue;
            }

            $blog_name = get_bloginfo('name');
            $blog_lang = self::get_language(self::get_locale());

            if (!current_user_can('manage_categories')) {
                restore_current_blog();
                $this->flash_admin_notice(sprintf(__('Sie verfügen nicht über ausreichende Berechtigungen, um eine netzwerkweite Versionierung durchführen zu können. (%1$s - %2$s)', 'cms-workflow'), $blog_name, $blog_lang['native_name']), 'error');
                continue;
            }

            $remote_post = get_post($remote_post_id);

            if (!isset($remote_post->post_status)) {
                restore_current_blog();
                continue;
            }

            if ($add_post_meta) {
                if ($remote_post->post_status != 'publish') {
                    restore_current_blog();
                    $this->flash_admin_notice(sprintf(__('Zieldokument ist nicht veröffentlicht. Netzwerkweite Versionierung fehlgeschlagen. (%1$s - %2$s)', 'cms-workflow'), $blog_name, $blog_lang['native_name']), 'error');
                    continue;
                }
                $permalink = get_permalink($remote_post_id);
                $post_title = get_the_title($remote_post_id);

                add_post_meta($remote_post_id, self::version_remote_post_meta, array('blog_id' => $current_blog_id, 'post_id' => $post_id));

                restore_current_blog();
                add_post_meta($post_id, self::version_remote_parent_post_meta, array('blog_id' => $blog_id, 'post_id' => $remote_post_id));
                $this->flash_admin_notice(sprintf(__('Das Zieldokument &bdquo;<a href="%1$s" target="__blank">%2$s</a>&ldquo; - %3$s - %4$s wurde erfolgreich erstellt.', 'cms-workflow'), $permalink, $post_title, $blog_name, $blog_lang['native_name']));
            } else {
                delete_post_meta($remote_post_id, self::version_remote_post_meta);

                restore_current_blog();
                delete_post_meta($post_id, self::version_remote_parent_post_meta);
                $this->flash_admin_notice(__('Netzwerkweite Beziehung des Dokumentes wurde entfernt.', 'cms-workflow'));
            }
        }
    }

    private function network_connections_save_post_submitbox($post_id)
    {
        if (wp_is_post_revision($post_id)) {
            return;
        }

        if (!isset($_POST['network_connections_version'])) {
            return;
        }

        if (!current_user_can('manage_categories')) {
            return;
        }

        $post = get_post($post_id);

        if (!in_array($post->post_status, array('publish'))) {
            return;
        }

        $related_sites = $this->current_related_sites();
        if (empty($related_sites)) {
            return;
        }

        $current_blog_id = get_current_blog_id();

        $network_connections = (array) get_post_meta($post->ID, $this->module->workflow_options_name . '_network_connections', true);

        $connected = false;

        foreach ($related_sites as $blog_id) {
            if ($current_blog_id == $blog_id) {
                continue;
            }

            if (in_array($blog_id, $network_connections)) {
                $connected = true;
                break;
            }
        }

        if (!$connected) {
            return;
        }

        $current_user_id = get_current_user_id();

        $post_meta = $this->get_post_meta($post_id);

        $post_attached_data = $this->get_post_attached_file($post_id);

        $remote_parent_post_meta = get_post_meta($post_id, self::version_remote_parent_post_meta);

        foreach ($network_connections as $blog_id) {

            if ($blog_id == $current_blog_id) {
                continue;
            }

            $remote_post_id = 0;
            foreach ($remote_parent_post_meta as $remote_parent_post) {
                if (isset($remote_parent_post['post_id']) && isset($remote_parent_post['blog_id']) && $blog_id == $remote_parent_post['blog_id']) {
                    $remote_post_id = $remote_parent_post['post_id'];
                }
            }

            if (!switch_to_blog($blog_id)) {
                continue;
            }

            if (!in_array($post->post_type, $this->get_post_types($this->module))) {
                restore_current_blog();
                continue;
            }

            $blog_name = get_bloginfo('name');
            $blog_lang = self::get_language(self::get_locale());

            if (!current_user_can('manage_categories')) {
                restore_current_blog();
                $this->flash_admin_notice(sprintf(__('Sie verfügen nicht über ausreichende Berechtigungen, um eine netzwerkweite Versionierung durchführen zu können. (%1$s - %2$s)', 'cms-workflow'), $blog_name, $blog_lang['native_name']), 'error');
                continue;
            }

            $exist_remote_post = false;
            $remote_post_meta = (array) get_post_meta($remote_post_id, self::version_remote_post_meta);
            foreach ($remote_post_meta as $remote_post) {
                if (isset($remote_post['post_id']) && isset($remote_post['blog_id']) && $post_id == $remote_post['post_id'] && $current_blog_id == $remote_post['blog_id']) {
                    $exist_remote_post = true;
                }
            }

            if ($exist_remote_post) {

                $remote_post = get_post($remote_post_id);

                if (empty($remote_post) || $remote_post->post_status != 'publish') {
                    restore_current_blog();
                    $this->flash_admin_notice(sprintf(__('Zieldokument ist nicht veröffentlicht. Netzwerkweite Versionierung fehlgeschlagen. (%1$s - %2$s)', 'cms-workflow'), $blog_name, $blog_lang['native_name']), 'error');
                    continue;
                }

                $new_post = array(
                    'post_author' => $current_user_id,
                    'post_content' => $post->post_content,
                    'post_title' => $post->post_title,
                    'post_excerpt' => $post->post_excerpt,
                    'post_status' => 'draft',
                    'post_parent' => $remote_post->post_parent,
                    'menu_order' => $remote_post->menu_order,
                    'post_type' => $remote_post->post_type
                );

                $draft_id = wp_insert_post($new_post);

                if ($draft_id) {
                    add_post_meta($draft_id, self::version_post_id, $remote_post_id);

                    $this->add_taxonomies($draft_id, $remote_post);

                    $this->add_post_meta($draft_id, $post_meta);

                    $this->add_post_attached_file($draft_id, $post_attached_data);

                    $permalink = get_permalink($draft_id);
                    $post_title = get_the_title($draft_id);

                    restore_current_blog();
                    $this->flash_admin_notice(sprintf(__('Neue Version vom Zieldokument &bdquo;<a href="%1$s" target="__blank">%2$s</a>&ldquo; - %3$s - %4$s wurde erfolgreich erstellt.', 'cms-workflow'), $permalink, $post_title, $blog_name, $blog_lang['native_name']));
                } else {
                    restore_current_blog();
                    $this->flash_admin_notice(sprintf(__('Netzwerkweite Versionierung fehlgeschlagen. (%1$s - %2$s)', 'cms-workflow'), $blog_name, $blog_lang['native_name']), 'error');
                }
            } else {

                $newpost = array(
                    'post_title' => $post->post_title,
                    'post_content' => $post->post_content,
                    'post_status' => 'draft',
                    'post_author' => $current_user_id,
                    'post_excerpt' => $post->post_excerpt,
                    'post_type' => $post->post_type
                );

                $new_remote_post_id = wp_insert_post($newpost);

                if ($new_remote_post_id) {
                    $this->add_post_meta($new_remote_post_id, $post_meta);

                    restore_current_blog();
                    $this->add_network_taxonomies($blog_id, $new_remote_post_id, $post);
                    switch_to_blog($blog_id);

                    $this->add_post_attached_file($new_remote_post_id, $post_attached_data);

                    $permalink = get_permalink($new_remote_post_id);
                    $post_title = get_the_title($new_remote_post_id);

                    restore_current_blog();
                    add_post_meta($post_id, self::version_remote_parent_post_meta, array('blog_id' => $blog_id, 'post_id' => $new_remote_post_id));
                    $this->flash_admin_notice(sprintf(__('Das Zieldokument &bdquo;<a href="%1$s" target="__blank">%2$s</a>&ldquo; (%3$s - %4$s) wurde erfolgreich erstellt.', 'cms-workflow'), $permalink, $post_title, $blog_name, $blog_lang['native_name']));

                    switch_to_blog($blog_id);
                    add_post_meta($new_remote_post_id, self::version_remote_post_meta, array('blog_id' => $current_blog_id, 'post_id' => $post_id));
                    restore_current_blog();
                } else {
                    restore_current_blog();
                    $this->flash_admin_notice(sprintf(__('Netzwerkweite Versionierung fehlgeschlagen. (%1$s - %2$s)', 'cms-workflow'), $blog_name, $blog_lang['native_name']), 'error');
                }
            }
        }
    }

    private function add_taxonomies($post_id, $post)
    {
        $taxonomies = get_object_taxonomies($post->post_type);
        $filtered_taxonomies = apply_filters('workflow_post_versioning_filtered_taxonomies', array());

        foreach ($taxonomies as $taxonomy) {
            if (in_array($taxonomy, $filtered_taxonomies)) {
                continue;
            }

            $post_terms = wp_get_object_terms($post->ID, $taxonomy);
            if (empty($post_terms) || is_wp_error($post_terms)) {
                continue;
            }

            $terms = array();

            for ($i = 0; $i < count($post_terms); $i++) {
                $terms[] = $post_terms[$i]->slug;
            }

            wp_set_object_terms($post_id, $terms, $taxonomy);
        }
    }

    private function add_network_taxonomies($blog_id, $post_id, $post)
    {
        $taxonomies = get_object_taxonomies($post->post_type);
        $filtered_taxonomies = apply_filters('workflow_post_versioning_filtered_network_taxonomies', array());

        foreach ($taxonomies as $taxonomy) {
            if (in_array($taxonomy, $filtered_taxonomies)) {
                continue;
            }

            $post_terms = wp_get_object_terms($post->ID, $taxonomy);
            if (empty($post_terms) || is_wp_error($post_terms)) {
                continue;
            }

            switch_to_blog($blog_id);

            for ($i = 0; $i < count($post_terms); $i++) {
                $term = get_term_by('slug', $post_terms[$i]->slug, $taxonomy);
                $term_id = wp_set_object_terms($post_id, $post_terms[$i]->slug, $taxonomy);
                if (isset($term_id[0]) && isset($term->slug) && $term->slug === $term->name) {
                    wp_update_term($term_id[0], $taxonomy, array('name' => $post_terms[$i]->name));
                }
            }

            restore_current_blog();
        }
    }

    private function filtered_post_meta($keys)
    {
        $filtered_post_meta = array();

        if (empty($this->not_filtered_post_meta) || !is_array($this->not_filtered_post_meta)) {
            $this->not_filtered_post_meta = array();
        }

        foreach ((array) $keys as $key) {
            if (strpos($key, '_') === 0 && !in_array($key, $this->not_filtered_post_meta)) {
                $filtered_post_meta[] = $key;
            }
        }

        return $filtered_post_meta;
    }

    private function get_post_meta($post_id)
    {
        $post_meta = array();
        $keys = get_post_custom_keys($post_id);

        $filtered_post_meta = $this->filtered_post_meta($keys);

        foreach ((array) $keys as $key) {
            if (in_array($key, $filtered_post_meta)) {
                continue;
            }

            $values = get_post_custom_values($key, $post_id);

            foreach ($values as $value) {
                $post_meta[] = array($key => maybe_unserialize($value));
            }
        }

        return $post_meta;
    }

    private function add_post_meta($post_id, $post_meta)
    {
        foreach ($post_meta as $meta) {
            $filtered_post_meta = $this->filtered_post_meta(array_keys($meta));

            foreach ($meta as $key => $value) {
                if (!in_array($key, $filtered_post_meta)) {
                    add_post_meta($post_id, $key, $value);
                }
            }
        }
    }

    private function update_post_meta($post_id, $post_meta)
    {
        foreach ($post_meta as $meta) {
            $filtered_post_meta = $this->filtered_post_meta(array_keys($meta));

            foreach ($meta as $key => $value) {
                if (!in_array($key, $filtered_post_meta)) {
                    update_post_meta($post_id, $key, $value);
                }
            }
        }
    }

    private function get_post_attached_file($post_id)
    {
        $attachment = array();

        if (current_theme_supports('post-thumbnails')) {
            $thumbnail_id = get_post_thumbnail_id($post_id);
            if (!empty($thumbnail_id)) {
                $attachment = get_post($thumbnail_id);
                $source_upload_dir = wp_upload_dir();
                $attached_file = get_post_meta($thumbnail_id, '_wp_attached_file', true);
                $attached_pathinfo = pathinfo($attached_file);

                $attachment = array(
                    'attachment' => $attachment,
                    'source_upload_dir' => $source_upload_dir,
                    'attached_file' => $attached_file,
                    'attached_pathinfo' => $attached_pathinfo
                );
            }
        }

        return $attachment;
    }

    private function add_post_attached_file($post_id, $post_attached_data)
    {
        if (empty($post_attached_data)) {
            return;
        }

        extract($post_attached_data);

        include_once(ABSPATH . 'wp-admin/includes/image.php');

        if (count($attached_pathinfo) > 0) {
            $target_upload_dir = wp_upload_dir();
            $filename = wp_unique_filename($target_upload_dir['path'], $attached_pathinfo['basename']);
            $copy = copy($source_upload_dir['basedir'] . '/' . $attached_file, $target_upload_dir['path'] . '/' . $filename);

            if ($copy) {
                $wp_filetype = wp_check_filetype($target_upload_dir['url'] . '/' . $filename);
                $attachment = array(
                    'post_mime_type' => $wp_filetype['type'],
                    'guid' => $target_upload_dir['url'] . '/' . $filename,
                    'post_parent' => $post_id,
                    'post_title' => $attachment->post_title,
                    'post_excerpt' => $attachment->post_excerpt,
                    'post_author' => get_current_user_id(),
                    'post_content' => $attachment->post_content,
                );

                $attach_id = wp_insert_attachment($attachment, $target_upload_dir['path'] . '/' . $filename);
                if ($attach_id && !is_wp_error($attach_id)) {
                    wp_update_attachment_metadata($attach_id, wp_generate_attachment_metadata($attach_id, $target_upload_dir['path'] . '/' . $filename));
                    set_post_thumbnail($post_id, $attach_id);
                }
            }
        }
    }

    public function custom_columns($columns)
    {
        $position = array_search('cb', array_keys($columns));
        if ($position !== false) {
            $columns = array_slice($columns, 0, $position + 1, true) + array('post-id' => '') + array_slice($columns, $position, count($columns) - $position, true);
        }

        $columns['post-id'] = __('Nr.', 'cms-workflow');

        $position = array_search('comments', array_keys($columns));
        if ($position === false) {
            $position = array_search('date', array_keys($columns));
            if ($position === false) {
                $position = array_search('last-modified', array_keys($columns));
            }
        }

        if ($position !== false) {
            $columns = array_slice($columns, 0, $position, true) + array('version' => '') + array_slice($columns, $position, count($columns) - $position, true);
        }

        $columns['version'] = __('Version', 'cms-workflow');

        return $columns;
    }

    public function posts_custom_column($column, $post_id)
    {
        if ($column == 'post-id') {
            echo $post_id;
        } elseif ($column == 'version') {
            echo $this->get_versions($post_id);
        }
    }

    public function posts_sortable_columns($columns)
    {
        $columns['post-id'] = 'post-id';
        return $columns;
    }

    private function get_versions($post_id)
    {
        $documents = array();

        $version_post_id = get_post_meta($post_id, self::version_post_id, true);

        if ($version_post_id) {
            $permalink = get_permalink($version_post_id);
            if ($permalink) {
                $post_title = get_the_title($version_post_id);
                $documents[] = sprintf('<a href="%1$s" target="__blank">%2$s</a>', $permalink, $post_title);
            }
        }

        if (is_multisite() && $this->module_activated('network')) {
            $documents = array_merge($documents, $this->get_network_versions($post_id));
        }

        if (empty($documents)) {
            $documents[] = '&#8212;';
        }

        return implode('<br>', $documents);
    }

    private function get_network_versions($post_id)
    {
        $documents = array();
        $current_blog_id = get_current_blog_id();
        $current_related_sites = $this->current_related_sites();

        if (!$current_related_sites) {

            $remote_post_meta = (array) get_post_meta($post_id, self::version_remote_post_meta);

            foreach ($remote_post_meta as $post_meta) {
                if (!isset($post_meta['blog_id']) || !isset($post_meta['post_id'])) {
                    continue;
                }

                if (!switch_to_blog($post_meta['blog_id'])) {
                    continue;
                }

                $permalink = get_permalink($post_meta['post_id']);
                if ($permalink) {

                    $translate_to_lang = get_post_meta($post_meta['post_id'], '_translate_to_lang_post_meta', true);
                    if (empty($translate_to_lang)) {
                        $translate_to_lang = self::get_locale();
                    }

                    $language = self::get_language($translate_to_lang);
                    $translate_to_lang = !empty($translate_to_lang) ? sprintf(' - <span class="translation">%s</span></a>', $language['native_name']) : '';

                    $post_title = get_the_title($post_meta['post_id']);
                    $documents[] = sprintf('<a class="import" href="%1$s" target="__blank">%2$s%3$s</a>', $permalink, $post_title, $translate_to_lang);
                }

                restore_current_blog();
            }
        } else {

            $network_connections = (array) get_post_meta($post_id, $this->module->workflow_options_name . '_network_connections', true);

            $remote_parent_post_meta = get_post_meta($post_id, self::version_remote_parent_post_meta);

            foreach ($remote_parent_post_meta as $post_meta) {
                if (!isset($post_meta['blog_id']) || !isset($post_meta['post_id']) || $post_meta['blog_id'] == $current_blog_id) {
                    continue;
                }

                if (!in_array($post_meta['blog_id'], $network_connections)) {
                    continue;
                }

                if (!switch_to_blog($post_meta['blog_id'])) {
                    continue;
                }

                $permalink = get_permalink($post_meta['post_id']);
                if ($permalink) {

                    $translate_to_lang = get_post_meta($post_meta['post_id'], '_translate_to_lang_post_meta', true);
                    if (empty($translate_to_lang)) {
                        $translate_to_lang = self::get_locale();
                    }

                    $language = self::get_language($translate_to_lang);
                    $translate_to_lang = !empty($language) ? sprintf(' - <span class="translation">%s</span></a>', $language['native_name']) : '';

                    $post_title = get_the_title($post_meta['post_id']);
                    $documents[] = sprintf('<a class="export" href="%1$s" target="__blank">%2$s%3$s</a>', $permalink, $post_title, $translate_to_lang);
                }
                restore_current_blog();
            }
        }

        return $documents;
    }

    public function admin_bar_submenu()
    {
        global $wp_admin_bar, $post;

        if (!is_admin_bar_showing() || !is_object($wp_admin_bar) || !is_object($post)) {
            return;
        }

        if (!is_object($this->get_available_post_types($post->post_type)) || !in_array($post->post_type, $this->get_post_types($this->module))) {
            return;
        }

        $cap = $this->get_available_post_types($post->post_type)->cap;
        if (current_user_can($cap->edit_posts) && $post->post_status == 'publish' && ($this->is_author(get_current_user_id(), $post->ID) || current_user_can('edit_published_posts'))) {
        } else {
            return;
        }

        $args = array(
            'parent' => 'new-content',
            'id'     => 'new-version',
            'title' => __('Version', 'cms-workflow'),
            'href'  => admin_url('admin.php?action=version_as_new_post_draft&amp;post=' . $post->ID),
            'meta'  => array(
                'class' => 'new-version',
                'title' => esc_attr(__('Dieses Element als neue Version duplizieren', 'cms-workflow'))
            )
        );

        $wp_admin_bar->add_node($args);
    }
}

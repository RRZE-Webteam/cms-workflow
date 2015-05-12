<?php

class Workflow_Module {

    public function module_activated($name) {
        global $cms_workflow;

        return isset($cms_workflow->$name) && $cms_workflow->$name->module->options->activated;
    }

    public function clean_post_type_options($module_post_types = array(), $post_type_support = null) {
        $normalized_post_type_options = array();
        $custom_post_types = wp_list_pluck($this->get_custom_post_types(), 'name');

        array_push($custom_post_types, 'post', 'page');

        $all_post_types = array_merge($custom_post_types, array_keys($module_post_types));

        foreach ($all_post_types as $post_type) {
            if (( isset($module_post_types[$post_type]) && $module_post_types[$post_type] ) || post_type_supports($post_type, $post_type_support)) {
                $normalized_post_type_options[$post_type] = true;
            } else {
                $normalized_post_type_options[$post_type] = false;
            }
        }
        return $normalized_post_type_options;
    }

    public function get_buildin_post_types() {

        $args = array(
            '_builtin' => true,
            'public' => true,
        );

        return get_post_types($args, 'objects');
    }

    public function get_custom_post_types() {

        $args = array(
            '_builtin' => false,
            'public' => true,
        );

        return get_post_types($args, 'objects');
    }

    public function get_post_types($module) {

        $post_types = array();
        if (isset($module->options->post_types) && is_array($module->options->post_types)) {
            foreach ($module->options->post_types as $post_type => $value) {
                if ($value) {
                    $post_types[] = $post_type;
                }
            }
        }
        return $post_types;
    }

    public function get_post_status_name($status) {
        $status_friendly_name = '';

        $builtin_status = array(
            'publish' => __('Veröffentlicht', CMS_WORKFLOW_TEXTDOMAIN),
            'draft' => __('Entwurf', CMS_WORKFLOW_TEXTDOMAIN),
            'future' => __('Geplant', CMS_WORKFLOW_TEXTDOMAIN),
            'private' => __('Privat', CMS_WORKFLOW_TEXTDOMAIN),
            'pending' => __('Ausstehender Review', CMS_WORKFLOW_TEXTDOMAIN),
            'trash' => __('Papierkorb', CMS_WORKFLOW_TEXTDOMAIN),
        );

        if (array_key_exists($status, $builtin_status)) {
            $status_friendly_name = $builtin_status[$status];
        }

        return $status_friendly_name;
    }

    public function get_available_post_types($post_type = null) {
        $all_post_types = array();

        $buildin_post_types = $this->get_buildin_post_types();
        $all_post_types['post'] = $buildin_post_types['post'];
        $all_post_types['page'] = $buildin_post_types['page'];
        $all_post_types['attachment'] = $buildin_post_types['attachment'];

        $custom_post_types = $this->get_custom_post_types();
        if (count($custom_post_types)) {
            foreach ($custom_post_types as $custom_post_type => $args) {
                $all_post_types[$custom_post_type] = $args;
            }
        }

        if (is_null($post_type) || !isset($all_post_types[$post_type])) {
            return $all_post_types;
        } else {
            return $all_post_types[$post_type];
        }
    }

    public function get_post_type_labels() {
        global $wp_post_types;

        $post_type_name = get_current_screen()->post_type;
        $labels = &$wp_post_types[$post_type_name]->labels;

        return $labels;
    }

    public function get_current_post_type() {
        global $post, $typenow, $pagenow, $current_screen;

        $post_int;
        if (isset($_REQUEST['post'])) {
            $post_int = (int) $_REQUEST['post'];
        }

        if ($post && $post->post_type) {
            $post_type = $post->post_type;
        } elseif ($typenow) {
            $post_type = $typenow;
        } elseif ($current_screen && isset($current_screen->post_type)) {
            $post_type = $current_screen->post_type;
        } elseif (isset($_REQUEST['post_type'])) {
            $post_type = sanitize_key($_REQUEST['post_type']);
        } elseif ('post.php' == $pagenow && isset($_REQUEST['post']) && isset($post_int) && !empty(get_post($post_int)->post_type)) {
            $post_type = get_post($post_int)->post_type;
        } elseif ('edit.php' == $pagenow && empty($_REQUEST['post_type'])) {
            $post_type = 'post';
        } else {
            $post_type = null;
        }

        return $post_type;
    }

    public function is_post_type_enabled($post_type = null, $module = null) {

        if(!$module) {
            $module = $this->module;
        }
        
        $allowed_post_types = $this->get_post_types($module);

        if (!$post_type) {
            $post_type = get_post_type();
        }
        
        if(in_array($module->name, array('authors', 'user_groups'))) {
            if($post_type == 'attachment') {
                $post_type = 'post';
            }
        }
        
        return (bool) in_array($post_type, $allowed_post_types);
    }

    public function get_encoded_description($args = array()) {
        return base64_encode(maybe_serialize($args));
    }

    public function get_unencoded_description($string_to_unencode) {
        return maybe_unserialize(base64_decode($string_to_unencode));
    }

    public function get_module_url($file) {
        $module_url = plugins_url('/', $file);
        return trailingslashit($module_url);
    }

    public function users_select_form($selected = null, $args = null) {

        $defaults = array(
            'list_class' => 'workflow-users-select',
            'list_id' => 'workflow-users-select',
            'input_id' => 'workflow-selected-users',
            'input_name' => 'workflow_selected_users'
        );
        $parsed_args = wp_parse_args($args, $defaults);
        extract($parsed_args, EXTR_SKIP);

        $args = array(
            'who' => 'contributors',
            'fields' => array(
                'ID',
                'display_name',
                'user_email'
            ),
            'orderby' => 'display_name',
        );

        $users = get_users($args);

        if (!is_array($selected)) {
            $selected = array();
        }
        ?>
        <?php if (count($users)) : ?>
            <ul class="<?php echo esc_attr($list_class) ?>">
            <?php foreach ($users as $user) : ?>
                <?php $checked = ( in_array($user->ID, $selected) ) ? 'checked="checked"' : ''; ?>
                    <li>
                        <label for="<?php echo esc_attr($input_id . '-' . $user->ID) ?>">
                            <input type="checkbox" id="<?php echo esc_attr($input_id . '-' . $user->ID) ?>" name="<?php echo esc_attr($input_name) ?>[]" value="<?php echo esc_attr($user->ID); ?>" <?php echo $checked; ?> />
                            <span class="workflow-user-displayname"><?php echo esc_html($user->display_name); ?></span>
                            <span class="workflow-user-useremail"><?php echo esc_html($user->user_email); ?></span>
                        </label>
                    </li>
            <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p><?php _e('Kein Benutzer gefunden.', CMS_WORKFLOW_TEXTDOMAIN); ?></p>
        <?php endif; ?>
        <?php
    }

    public function action_settings_help_menu() {
        $screen = get_current_screen();

        if (!method_exists($screen, 'add_help_tab')) {
            return;
        }

        if ($screen->id != 'workflow_page_' . $this->module->settings_slug) {
            return;
        }

        if (isset($this->module->settings_help_tab['id'], $this->module->settings_help_tab['title'], $this->module->settings_help_tab['content'])) {
            $screen->add_help_tab($this->module->settings_help_tab);

            if (isset($this->module->settings_help_sidebar)) {
                $screen->set_help_sidebar($this->module->settings_help_sidebar);
            }
        }
    }

    public function is_settings_view($module_name = null) {
        global $pagenow, $cms_workflow;

        if ($pagenow != 'admin.php' || !isset($_GET['page'])) {
            return false;
        }

        foreach ($cms_workflow->modules as $mod_name => $mod_data) {
            if ($mod_data->options->activated && $mod_data->configure_callback) {
                $settings_view_slugs[] = $mod_data->settings_slug;
            }
        }

        if (!in_array($_GET['page'], $settings_view_slugs)) {
            return false;
        }

        if ($module_name && $cms_workflow->modules->$module_name->settings_slug != $_GET['page']) {
            return false;
        }

        return true;
    }

    public function show_admin_notice($message, $class = '') {

        $default_allowed_classes = array('error', 'updated');
        $allowed_classes = apply_filters('admin_notices_allowed_classes', $default_allowed_classes);
        $default_class = apply_filters('admin_notices_default_class', 'updated');

        if (!in_array($class, $allowed_classes)) {
            $class = $default_class;
        }
        ?>
        <div class="<?php echo $class; ?>">
            <p><?php echo $message; ?></p>
        </div>
        <?php
    }

    public function flash_admin_notice($message, $class = '') {
        $default_allowed_classes = array('error', 'updated');
        $allowed_classes = apply_filters('admin_notices_allowed_classes', $default_allowed_classes);
        $default_class = apply_filters('admin_notices_default_class', 'updated');

        if (!in_array($class, $allowed_classes)) {
            $class = $default_class;
        }

        $transient = 'flash_admin_notices_' . get_current_user_id();
        $transient_value = get_transient($transient);
        $flash_notices = maybe_unserialize($transient_value ? $transient_value : array());
        $flash_notices[$class][] = $message;

        set_transient($transient, $flash_notices, 60);
    }

    public function show_flash_admin_notices() {
        $transient = 'flash_admin_notices_' . get_current_user_id();
        $transient_value = get_transient($transient);
        $flash_notices = maybe_unserialize($transient_value ? $transient_value : '');

        if (is_array($flash_notices)) {
            foreach ($flash_notices as $class => $messages) {
                foreach ($messages as $message) :
                    ?>
                    <div class="<?php echo $class; ?>">
                        <p><?php echo $message; ?></p>
                    </div>
                    <?php
                endforeach;
            }
        }

        delete_transient($transient);
    }

    private static function get_languages() {
        $languages = array(
            'en_US' => array(
                'language' => 'en_US',
                'english_name' => 'English',
                'native_name' => 'English',
                'iso' => array(
                    1 => 'en'
                )
            ),
            'en_EN' => array(
                'language' => 'en_EN',
                'english_name' => 'English',
                'native_name' => 'English',
                'iso' => array(
                    1 => 'en'
                )
            ),
            'de_DE' => array(
                'language' => 'de_DE',
                'english_name' => 'German',
                'native_name' => 'Deutsch',
                'iso' => array(
                    1 => 'de'
                ),
            )
        );

        return $languages;
    }

    public static function get_available_languages() {
        $languages = get_available_languages();
        return array_merge($languages, array('en_US'));
    }
    
    public static function get_language($locale) {
        $unknown_language = array(
            'language' => 'xx_XX',
            'english_name' => 'Unknown language',
            'native_name' => 'Unknown language',
            'iso' => array(
                1 => 'xx'
            )
        );

        $languages = self::get_languages();

        if (empty($locale)) {
            $locale = 'en_US';
        }
        
        if (!isset($languages[$locale])) {
            require_once( ABSPATH . 'wp-admin/includes/translation-install.php' );
            $wp_available_translations = wp_get_available_translations();
            $languages = array_merge($languages, $wp_available_translations);
            if (!isset($languages[$locale])) {
                return $unknown_language;
            }
        }

        return $languages[$locale];
    }

    public static function get_locale() {
        if (is_multisite()) {
            if (defined('WP_INSTALLING') || ( false === $ms_locale = get_option('WPLANG') )) {
                $ms_locale = get_site_option('WPLANG');
            }

            if ($ms_locale !== false) {
                $locale = $ms_locale;
            }
        } else {
            $db_locale = get_option('WPLANG');
            if ($db_locale !== false) {
                $locale = $db_locale;
            }
        }

        if (empty($locale)) {
            $locale = 'en_US';
        }

        return $locale;
    }

    public function repopulate_role($role = '') {
        $allowed_roles = array('editor', 'author');

        if (!in_array($role, $allowed_roles)) {
            return false;
        }

        remove_role($role);

        $populate_role = sprintf('populate_%s_role', $role);
        $this->$populate_role();
    }

    private function populate_editor_role() {
        // Dummy gettext calls to get strings in the catalog.
        /* translators: user role */
        _x('Editor', 'User role');

        add_role('editor', 'Editor');

        // Add caps for Editor role
        $role = get_role('editor');
        $role->add_cap('moderate_comments');
        $role->add_cap('manage_categories');
        $role->add_cap('manage_links');
        $role->add_cap('upload_files');
        $role->add_cap('unfiltered_html');
        $role->add_cap('edit_posts');
        $role->add_cap('edit_others_posts');
        $role->add_cap('edit_published_posts');
        $role->add_cap('publish_posts');
        $role->add_cap('edit_pages');
        $role->add_cap('read');
        $role->add_cap('level_7');
        $role->add_cap('level_6');
        $role->add_cap('level_5');
        $role->add_cap('level_4');
        $role->add_cap('level_3');
        $role->add_cap('level_2');
        $role->add_cap('level_1');
        $role->add_cap('level_0');

        $role->add_cap('edit_others_pages');
        $role->add_cap('edit_published_pages');
        $role->add_cap('publish_pages');
        $role->add_cap('delete_pages');
        $role->add_cap('delete_others_pages');
        $role->add_cap('delete_published_pages');
        $role->add_cap('delete_posts');
        $role->add_cap('delete_others_posts');
        $role->add_cap('delete_published_posts');
        $role->add_cap('delete_private_posts');
        $role->add_cap('edit_private_posts');
        $role->add_cap('read_private_posts');
        $role->add_cap('delete_private_pages');
        $role->add_cap('edit_private_pages');
        $role->add_cap('read_private_pages');
    }

    private function populate_author_role() {
        // Dummy gettext calls to get strings in the catalog.
        /* translators: user role */
        _x('Author', 'User role');

        add_role('author', 'Author');

        // Add caps for Author role
        $role = get_role('author');
        $role->add_cap('upload_files');
        $role->add_cap('edit_posts');
        $role->add_cap('edit_published_posts');
        $role->add_cap('publish_posts');
        $role->add_cap('read');
        $role->add_cap('level_2');
        $role->add_cap('level_1');
        $role->add_cap('level_0');

        $role->add_cap('delete_posts');
        $role->add_cap('delete_published_posts');
    }

}

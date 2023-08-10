<?php

class Workflow_Authors extends Workflow_Module
{
    const taxonomy_key = 'workflow_author';
    const role = 'author';

    private $wp_post_caps = array();
    private $wp_role_caps = array();
    public $role_caps = array();
    public $module;
    public $module_url;
    public $having_terms = '';

    public function __construct()
    {
        global $cms_workflow;

        $this->module_url = $this->get_module_url(__FILE__);

        $this->wp_post_caps = array(
            'publish_posts' => __('Beitrag veröffentlichen', CMS_WORKFLOW_TEXTDOMAIN),
            'edit_published_posts' => __('Veröffentlichte Beiträge bearbeiten', CMS_WORKFLOW_TEXTDOMAIN),
            'delete_published_posts' => __('Veröffentlichte Beiträge löschen', CMS_WORKFLOW_TEXTDOMAIN),
            'edit_posts' => __('Beiträge bearbeiten', CMS_WORKFLOW_TEXTDOMAIN),
            'delete_posts' => __('Beiträge löschen', CMS_WORKFLOW_TEXTDOMAIN)
        );

        $this->wp_role_caps = array_keys($this->wp_post_caps);

        $args = array(
            'title' => __('Autoren', CMS_WORKFLOW_TEXTDOMAIN),
            'description' => __('Verwaltung der Autoren.', CMS_WORKFLOW_TEXTDOMAIN),
            'module_url' => $this->module_url,
            'slug' => 'authors',
            'default_options' => array(
                'post_types' => array(
                    'post' => true,
                    'page' => true
                ),
                'role_caps' => array(
                    'upload_files' => true,
                    'publish_posts' => true,
                    'edit_published_posts' => true,
                    'delete_published_posts' => true,
                    'edit_posts' => true,
                    'delete_posts' => true
                ),
                'author_can_assign_others' => false,
            ),
            'configure_callback' => 'print_configure_view'
        );

        $this->module = $cms_workflow->register_module('authors', $args);
    }

    public function init()
    {
        add_action('admin_init', array($this, 'set_role_caps'));

        add_action('init', array($this, 'register_taxonomies'));

        add_action('add_meta_boxes', array($this, 'add_post_meta_box'), 9);

        add_action('save_post', array($this, 'save_post'), 10, 2);
        add_action('edit_attachment', array($this, 'edit_attachment'));

        add_action('delete_user', array($this, 'delete_user_action'));

        add_filter('get_usernumposts', array($this, 'get_usernumposts_filter'), 10, 2);

        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'custom_columns'));
        add_action('admin_init', array($this, 'custom_attachment_columns'));

        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));

        add_filter('user_has_cap', array($this, 'filter_user_has_cap'), 10, 3);

        add_action('admin_head', array($this, 'remove_quick_edit_authors_box'));

        add_filter('posts_where', array($this, 'posts_where_filter'), 10, 2);
        add_filter('posts_join', array($this, 'posts_join_filter'), 10, 2);
        add_filter('posts_groupby', array($this, 'posts_groupby_filter'), 10, 2);

        add_action('load-edit.php', array($this, 'load_edit'));

        add_filter('workflow_post_versioning_filtered_taxonomies', array($this, 'filtered_taxonomies'));
        add_filter('workflow_post_versioning_filtered_network_taxonomies', array($this, 'filtered_taxonomies'));
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

            if (isset($args->cap->delete_posts)) {
                $this->role_caps[$args->cap->delete_posts] = sprintf(__('%s löschen', CMS_WORKFLOW_TEXTDOMAIN), $label);
            }

            if (isset($args->cap->publish_posts)) {
                $this->role_caps[$args->cap->publish_posts] = sprintf(__('%s veröffentlichen', CMS_WORKFLOW_TEXTDOMAIN), $label);
            }

            if (isset($args->cap->edit_published_posts)) {
                $this->role_caps[$args->cap->edit_published_posts] = sprintf(__('Veröffentlichte %s bearbeiten', CMS_WORKFLOW_TEXTDOMAIN), $label);
            }

            if (isset($args->cap->delete_published_posts)) {
                $this->role_caps[$args->cap->delete_published_posts] = sprintf(__('Veröffentlichte %s löschen', CMS_WORKFLOW_TEXTDOMAIN), $label);
            }
        }
    }

    public function register_taxonomies()
    {
        $allowed_post_types = $this->get_post_types($this->module);

        $args = array(
            'hierarchical' => false,
            'update_count_callback' => '_update_post_term_count',
            'label' => false,
            'query_var' => false,
            'rewrite' => false,
            'public' => false,
            'show_ui' => false
        );

        register_taxonomy(self::taxonomy_key, $allowed_post_types, $args);
    }

    public function enqueue_admin_scripts()
    {
        wp_enqueue_script('jquery-listfilterizer');
        wp_enqueue_script('workflow-authors', $this->module_url . 'authors.js', array('jquery', 'jquery-listfilterizer'), CMS_WORKFLOW_VERSION, true);

        wp_localize_script('workflow-authors', 'authors_vars', array(
            'filters_label_1' => __('Alle', CMS_WORKFLOW_TEXTDOMAIN),
            'filters_label_2' => __('Ausgewählt', CMS_WORKFLOW_TEXTDOMAIN),
            'placeholder' => __('Suchen...', CMS_WORKFLOW_TEXTDOMAIN),
        ));
    }

    public function enqueue_admin_styles()
    {
        wp_enqueue_style('jquery-listfilterizer');
        wp_enqueue_style('workflow-authors', $this->module->module_url . 'authors.css', false, CMS_WORKFLOW_VERSION);
    }

    public function add_post_meta_box($post_type)
    {
        if (!$this->is_post_type_enabled($post_type)) {
            return;
        }

        if (!$this->module->options->author_can_assign_others && !current_user_can('manage_categories')) {
            return;
        }

        remove_meta_box('authordiv', get_post_type(), 'normal');

        add_meta_box('workflow-authors', __('Autoren', CMS_WORKFLOW_TEXTDOMAIN), [$this, 'authors_meta_box'], $post_type);
    }

    public function authors_meta_box($post)
    {
        global $cms_workflow; ?>
        <div id="workflow-post-authors-box">
            <p><?php _e('Wählen Sie die Autoren zum Dokument', CMS_WORKFLOW_TEXTDOMAIN); ?></p>
            <div id="workflow-post-authors-inside">
                <h4><?php _e('Benutzer', CMS_WORKFLOW_TEXTDOMAIN); ?></h4>
                <?php
                $authors = self::get_authors($post->ID, 'id');
                $authors[$post->post_author] = $post->post_author;
                $authors = array_unique($authors);

                $args = array(
                    'list_class' => 'workflow-post-authors-list',
                    'input_id' => 'workflow-selected-authors',
                    'input_name' => 'workflow_selected_authors',
                );
                $this->users_select_form($authors, $args); ?>
            </div>

            <?php if ($this->module_activated('user_groups') && $this->is_post_type_enabled($post->post_type, $cms_workflow->user_groups->module)) : ?>
                <div id="workflow-post-authors-usergroups-box">
                    <h4><?php _e('Benutzergruppe', CMS_WORKFLOW_TEXTDOMAIN) ?></h4>
                    <?php
                    $authors_usergroups = $this->get_authors_usergroups($post->ID, 'ids');
                    $args = array(
                        'list_class' => 'workflow-groups-list',
                        'input_id' => 'authors-usergroups',
                        'input_name' => 'authors_usergroups'
                    );
                    $cms_workflow->user_groups->usergroups_select_form($authors_usergroups, $args); ?>
                </div>
            <?php endif; ?>
            <div class="clear"></div>
            <input type="hidden" name="workflow_save_authors" value="1" />
        </div>
    <?php
    }

    public function save_post($post_id, $post)
    {
        global $cms_workflow;

        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || (defined('DOING_AJAX') && DOING_AJAX) || isset($_REQUEST['bulk_edit'])) {
            return;
        }

        if (is_multisite() && ms_is_switched()) {
            return;
        }

        if (!$this->module->options->author_can_assign_others && !current_user_can('manage_categories')) {
            return;
        }

        $version_post_replace_on_publish = apply_filters('workflow_version_post_replace_on_publish', false);

        if (!wp_is_post_revision($post) && !wp_is_post_autosave($post) && isset($_POST['workflow_save_authors']) && !$version_post_replace_on_publish) {
            $users = isset($_POST['workflow_selected_authors']) ? $_POST['workflow_selected_authors'] : array();
            $this->save_post_authors($post, $users);

            if ($this->module_activated('user_groups') && $this->is_post_type_enabled($post->post_type, $cms_workflow->user_groups->module)) {
                $usergroups = isset($_POST['authors_usergroups']) ? $_POST['authors_usergroups'] : array();
                $this->save_post_authors_usergroups($post, $usergroups);
            }
        }
    }

    public function edit_attachment($attachment_id)
    {
        global $cms_workflow;

        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || (defined('DOING_AJAX') && DOING_AJAX) || isset($_REQUEST['bulk_edit'])) {
            return;
        }

        if (!current_user_can('manage_categories')) {
            return;
        }

        $post = get_post($attachment_id);

        if (isset($_POST['workflow_save_authors'])) {
            $users = isset($_POST['workflow_selected_authors']) ? $_POST['workflow_selected_authors'] : array();
            $this->edit_attachment_authors($post, $users);

            if ($this->module_activated('user_groups') && $this->is_post_type_enabled($post->post_type, $cms_workflow->user_groups->module)) {
                $usergroups = isset($_POST['authors_usergroups']) ? $_POST['authors_usergroups'] : array();
                $this->edit_attachment_authors_usergroups($post, $usergroups);
            }
        }
    }

    private function save_post_authors($post, $users = null)
    {
        if (!is_array($users)) {
            $users = array();
        }

        $post_id = $post->ID;
        $current_user = wp_get_current_user();
        $current_user_id = $current_user->ID;
        $author_user_id = $post->post_author;

        $users[] = $current_user_id;

        $users = array_unique(array_map('intval', $users));

        if (!in_array($author_user_id, $users)) {
            remove_action('save_post', array($this, 'save_post'), 10, 2);
            wp_update_post(array('ID' => $post_id, 'post_author' => $current_user_id));
            add_action('save_post', array($this, 'save_post'), 10, 2);
        }

        $this->add_post_users($post, $users, false);
    }

    private function save_post_authors_usergroups($post, $usergroups = null)
    {
        if (empty($usergroups)) {
            $usergroups = array();
        }

        $usergroups = array_unique(array_map('intval', $usergroups));

        $this->add_post_usergroups($post, $usergroups, false);
    }

    private function edit_attachment_authors($post, $users = null)
    {
        if (!is_array($users)) {
            $users = array();
        }

        $attachment_id = $post->ID;
        $current_user = wp_get_current_user();
        $current_user_id = $current_user->ID;
        $author_user_id = $post->post_author;

        $users[] = $current_user->ID;

        $users = array_unique(array_map('intval', $users));

        if (!in_array($author_user_id, $users)) {
            remove_action('edit_attachment', array($this, 'save_post'), 10, 2);
            wp_update_post(array('ID' => $attachment_id, 'post_author' => $current_user_id));
            add_action('edit_attachment', array($this, 'save_post'), 10, 2);
        }

        $this->add_post_users($post, $users, false);
    }

    private function edit_attachment_authors_usergroups($post, $usergroups = null)
    {
        $this->save_post_authors_usergroups($post, $usergroups);
    }

    private function add_post_users($post, $users, $append = true)
    {
        $post_id = is_int($post) ? $post : $post->ID;
        if (!is_array($users)) {
            $users = array($users);
        }

        $user_terms = array();
        foreach ($users as $user) {
            if (is_int($user)) {
                $user_data = get_user_by('id', $user);
            } elseif (is_string($user)) {
                $user_data = get_user_by('login', $user);
            } elseif (is_object($user)) {
                $user_data = $user;
            } else {
                $user_data = null;
            }

            if ($user_data) {
                $name = $user_data->user_login;
                $term = $this->add_term_if_not_exists($name, self::taxonomy_key);

                if (!is_wp_error($term)) {
                    $user_terms[] = $name;
                }
            }
        }

        wp_set_object_terms($post_id, $user_terms, self::taxonomy_key, $append);

        return;
    }

    public function add_post_usergroups($post, $usergroups, $append = true)
    {
        global $cms_workflow;

        if (!$this->module_activated('user_groups')) {
            return;
        }

        $post_id = is_int($post) ? $post : $post->ID;

        if (!empty($usergroups)) {
            $authors = self::get_authors($post->ID, 'id');

            $users = array();
            foreach ($usergroups as $usergroup) {
                $usergroup_data = $cms_workflow->user_groups->get_usergroup_by('id', $usergroup);
                if ($usergroup_data && !empty($usergroup_data->user_ids)) {
                    foreach ($usergroup_data->user_ids as $key => $value) {
                        $users[] = $value;
                    }
                }
            }

            $users = array_merge($users, $authors);
            $users = array_unique(array_map('intval', $users));

            $this->add_post_users($post, $users, false);
        }

        $usergroups_taxonomy = Workflow_User_Groups::taxonomy_key;

        wp_set_object_terms($post_id, $usergroups, $usergroups_taxonomy, $append);
    }

    public function delete_user_action($user_id)
    {
        global $wpdb;

        if (!$user_id) {
            return;
        }

        $user = get_userdata($user_id);

        if ($user) {
            $user_authors_term = get_term_by('name', $user->user_login, self::taxonomy_key);
            if ($user_authors_term) {
                wp_delete_term($user_authors_term->term_id, self::taxonomy_key);
            }
        }

        $reassign_id = absint($_POST['reassign_user']);

        if ($reassign_id) {
            $reassign_user = get_user_by('id', $reassign_id);
            if (is_object($reassign_user)) {
                $post_ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_author = %d", $user_id));

                if ($post_ids) {
                    foreach ($post_ids as $post_id) {
                        $this->add_authors($post_id, array($reassign_user->user_login), true);
                    }
                }
            }
        }
    }

    public function get_usernumposts_filter($count, $user_id)
    {
        $user = get_userdata($user_id);

        $term = $this->get_author_term($user);

        if ($term && !is_wp_error($term)) {
            $count = $term->count;
        }

        return $count;
    }

    private function add_authors($post_id, $authors, $append = false)
    {
        global $current_user;

        $post_id = (int) $post_id;
        $insert = false;

        if (!is_array($authors) || empty($authors) || count($authors) == 0) {
            $authors = array($current_user->user_login);
        }

        foreach (array_unique($authors) as $key => $author_name) {
            $author = $this->get_author_by('user_nicename', $author_name);
            $term = $this->update_author_term($author);
            $authors[$key] = $term->slug;
        }

        wp_set_post_terms($post_id, $authors, self::taxonomy_key, $append);
    }

    private function get_author_by($key, $value, $force = false)
    {
        switch ($key) {
            case 'id':

            case 'login':

            case 'user_login':

            case 'email':

            case 'user_nicename':

            case 'user_email':
                if ('user_login' == $key) {
                    $key = 'login';
                }

                if ('user_email' == $key) {
                    $key = 'email';
                }

                if ('user_nicename' == $key) {
                    $key = 'slug';
                }

                $user = get_user_by($key, $value);
                if (!$user || !is_user_member_of_blog($user->ID)) {
                    return false;
                }

                $user->type = 'wpuser';
                return $user;
                break;
        }

        return false;
    }

    private function update_author_term($author)
    {
        if (!is_object($author)) {
            return false;
        }

        $search_values = array();
        $fields = array('display_name', 'first_name', 'last_name', 'user_login', 'ID', 'user_email');

        foreach ($fields as $search_field) {
            $search_values[] = $author->$search_field;
        }

        $term_description = implode(' ', $search_values);

        $term = $this->get_author_term($author);
        if ($term) {
            wp_update_term($term->term_id, self::taxonomy_key, array('description' => $term_description));
        } else {
            $author_slug = 'workflow-' . $author->user_nicename;
            $args = array(
                'slug' => $author_slug,
                'description' => $term_description,
            );

            $new_term = wp_insert_term($author->user_login, self::taxonomy_key, $args);
        }

        return $this->get_author_term($author);
    }

    private function get_author_term($author)
    {
        if (!is_object($author)) {
            return;
        }

        $term = get_term_by('slug', 'workflow-' . $author->user_nicename, self::taxonomy_key);
        if (!$term) {
            $term = get_term_by('slug', $author->user_nicename, self::taxonomy_key);
        }

        return $term;
    }

    public function filter_user_has_cap($allcaps, $caps, $args)
    {
        $cap = $args[0];
        $user_id = isset($args[1]) ? $args[1] : 0;
        $post_id = isset($args[2]) ? $args[2] : 0;

        if ($revision_parent_id = wp_is_post_revision($post_id)) {
            $post_id = $revision_parent_id;
        }

        $post_type = get_post_type($post_id);

        if (!$this->is_post_type_enabled($post_type, $this->module)) {
            return $allcaps;
        }

        $post_type_obj = get_post_type_object($post_type);

        if (!$post_type_obj) {
            return $allcaps;
        }

        if (!is_user_logged_in()) {
            return $allcaps;
        }

        $current_user = wp_get_current_user();

        if (!$this->is_post_author($current_user->user_login, $post_id)) {
            return $allcaps;
        }

        if (isset($post_type_obj->cap->edit_others_posts) && !empty($current_user->allcaps[$post_type_obj->cap->edit_posts])) {
            $allcaps[$post_type_obj->cap->edit_others_posts] = true;
        }

        if (isset($post_type_obj->cap->delete_others_posts) && !empty($current_user->allcaps[$post_type_obj->cap->delete_posts])) {
            $allcaps[$post_type_obj->cap->delete_others_posts] = true;
        }

        return $allcaps;
    }

    public function filtered_taxonomies($taxonomies)
    {
        $taxonomies[] = self::taxonomy_key;
        return array_unique($taxonomies);
    }

    public function remove_quick_edit_authors_box()
    {
        global $pagenow;

        $post_type = get_post_type();

        if ('edit.php' == $pagenow && $this->is_post_type_enabled($post_type, $this->module)) {
            remove_post_type_support($post_type, 'author');
        }
    }

    public function custom_columns()
    {
        foreach (get_post_types() as $post_type) {
            if ($this->is_post_type_enabled($post_type, $this->module)) {
                add_action("manage_edit-{$post_type}_columns", array($this, 'manage_authors_column'));
                add_filter("manage_{$post_type}_posts_custom_column", array($this, 'manage_authors_custom_column'), 10, 2);
            }
        }
    }

    public function custom_attachment_columns()
    {
        add_filter('manage_media_columns', array($this, 'manage_authors_column'));
        add_action("manage_media_custom_column", array($this, 'manage_authors_custom_column'), 10, 2);
    }

    public function manage_authors_column($columns)
    {
        $new_columns = array();

        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;

            if ($key == 'title') {
                $new_columns['coauthors'] = __('Autoren', CMS_WORKFLOW_TEXTDOMAIN);
            }

            if ($key == 'author') {
                unset($new_columns[$key]);
            }
        }

        return $new_columns;
    }

    public function manage_authors_custom_column($column_name, $post_id)
    {
        if ($column_name == 'coauthors') {
            $post = get_post($post_id);

            $authors_array = array();
            $authors = $this->get_post_authors($post_id);

            foreach ($authors as $author) {
                $args = array();

                if (!in_array($post->post_type, array('post', 'attachment'))) {
                    $args['post_type'] = $post->post_type;
                }

                $args['author'] = $author->ID;

                if ($post->post_type == 'attachment') {
                    $author_url = add_query_arg($args, admin_url('upload.php'));
                } else {
                    $author_url = add_query_arg($args, admin_url('edit.php'));
                }

                $authors_array[] = sprintf('<a href="%1$s">%2$s</a>', esc_url($author_url), esc_html($author->display_name));
            }

            echo implode('<br>', $authors_array);
        }
    }

    public function load_edit()
    {
        $screen = get_current_screen();

        if (!is_null($screen) && $this->is_post_type_enabled($screen->post_type, $this->module)) {
            add_filter('views_' . $screen->id, array($this, 'filter_views'));
        }
    }

    public function filter_views($views)
    {
        global $wpdb;

        if (empty($views)) {
            return $views;
        }

        $screen = get_current_screen();

        $post_type = !is_null($screen) ? $screen->post_type : '';

        if (!$this->is_post_type_enabled($post_type, $this->module)) {
            return $views;
        }

        $current_user_id = get_current_user_id();

        if (empty($_REQUEST['author'])) {
            $user = wp_get_current_user();
        } else {
            $user = get_userdata((int) $_REQUEST['author']);
        }

        if (!$user) {
            return $views;
        }

        $mine_args = array();

        $mine_args['post_type'] = $post_type;
        $mine_args['author'] = $current_user_id;

        $terms = array();
        $author = $this->get_author_by('id', $current_user_id);

        $author_term = $this->get_author_term($author);
        if ($author_term) {
            $terms[] = $author_term;
        }

        $join = '';
        $terms_implode = '';
        if (!empty($terms)) {
            $join = "LEFT JOIN $wpdb->term_relationships ON ($wpdb->posts.ID = $wpdb->term_relationships.object_id) 
                     LEFT JOIN $wpdb->term_taxonomy ON ( $wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id )";

            foreach ($terms as $term) {
                $terms_implode .= '(' . $wpdb->term_taxonomy . '.taxonomy = \'' . self::taxonomy_key . '\' AND ' . $wpdb->term_taxonomy . '.term_id = \'' . $term->term_id . '\') OR ';
            }

            $terms_implode = 'OR (' . rtrim($terms_implode, ' OR') . ')';
        }

        $post_count = $wpdb->get_var(
            "SELECT COUNT( DISTINCT $wpdb->posts.ID ) AS post_count
            FROM $wpdb->posts 
            $join
            WHERE 1=1 
            AND ($wpdb->posts.post_author = $current_user_id $terms_implode) 
            AND $wpdb->posts.post_type = '$post_type' 
            AND ($wpdb->posts.post_status = 'publish' OR $wpdb->posts.post_status = 'future' OR $wpdb->posts.post_status = 'draft' OR $wpdb->posts.post_status = 'pending' OR $wpdb->posts.post_status = 'private')"
        );

        if ((isset($_REQUEST['author']) && $current_user_id == $user->ID)) {
            $class = ' class="current"';
        } else {
            $class = '';
        }

        $mine = sprintf(__('Meine <span class="count">(%s)</span>', CMS_WORKFLOW_TEXTDOMAIN), number_format_i18n($post_count));

        $view['mine'] = '<a' . $class . ' href="' . add_query_arg($mine_args, admin_url('edit.php')) . '">' . $mine . '</a>';

        unset($views['mine']);
        array_splice($views, array_search('all', array_keys($views)) + 1, 0, $view['mine']);

        $views['all'] = str_replace($class, '', $views['all']);

        return $views;
    }

    private function get_post_authors($post_id = 0)
    {
        global $post, $post_ID, $wpdb;

        $post_id = (int) $post_id;

        if (!$post_id && $post_ID) {
            $post_id = $post_ID;
        }

        if (!$post_id && $post) {
            $post_id = $post->ID;
        }

        if ($post) {
            $post_author = $post->post_author;
        } else {
            $post_author = $wpdb->get_var($wpdb->prepare("SELECT post_author FROM $wpdb->posts WHERE ID = %d", $post_id));
        }

        $authors = self::get_authors($post_id);
        $authors[$post_author] = get_userdata($post_author);

        return $authors;
    }

    public function posts_where_filter($where, $wp_query)
    {
        global $wpdb;

        if (method_exists($wp_query, 'is_author') && $wp_query->is_author()) {
            if (!$this->is_post_type_enabled($wp_query->query_vars['post_type'], $this->module)) {
                return $where;
            }

            $author = get_userdata($wp_query->get('author'))->ID;

            $terms = array();
            $author = $this->get_author_by('id', $author);

            $author_term = $this->get_author_term($author);
            if ($author_term) {
                $terms[] = $author_term;
            }

            $maybe_both = apply_filters('should_query_post_author_filter', true);

            $maybe_both_query = $maybe_both ? '$1 OR' : '';

            if (!empty($terms)) {
                $terms_implode = '';
                $this->having_terms = '';
                foreach ($terms as $term) {
                    $terms_implode .= '(tt.taxonomy = \'' . self::taxonomy_key . '\' AND tt.term_id = \'' . $term->term_id . '\') OR ';
                    $this->having_terms .= ' tt.term_id = \'' . $term->term_id . '\' OR ';
                }
                $terms_implode = rtrim($terms_implode, ' OR');
                $this->having_terms = rtrim($this->having_terms, ' OR');
                $where = preg_replace('/(\b(?:' . $wpdb->posts . '\.)?post_author\s*IN\s*(\(\d+\)))/', '(' . $maybe_both_query . ' ' . $terms_implode . ')', $where, 1);
            }
        }

        return $where;
    }

    public function posts_join_filter($join, $wp_query)
    {
        global $wpdb;

        if (method_exists($wp_query, 'is_author') && $wp_query->is_author()) {
            if (!$this->is_post_type_enabled($wp_query->query_vars['post_type'], $this->module)) {
                return $join;
            }

            if (empty($this->having_terms)) {
                return $join;
            }

            $term_relationship_join = " INNER JOIN {$wpdb->term_relationships} AS tr ON ({$wpdb->posts}.ID = tr.object_id)";
            $term_taxonomy_join = " INNER JOIN {$wpdb->term_taxonomy} AS tt ON ( tr.term_taxonomy_id = tt.term_taxonomy_id )";

            if (strpos($join, trim($term_relationship_join)) === false) {
                $join .= str_replace("INNER JOIN", "LEFT JOIN", $term_relationship_join);
            }

            if (strpos($join, trim($term_taxonomy_join)) === false) {
                $join .= str_replace("INNER JOIN", "LEFT JOIN", $term_taxonomy_join);
            }
        }

        return $join;
    }

    public function posts_groupby_filter($groupby, $wp_query)
    {
        global $wpdb;

        if (method_exists($wp_query, 'is_author') && $wp_query->is_author()) {
            if (!$this->is_post_type_enabled($wp_query->query_vars['post_type'], $this->module)) {
                return $groupby;
            }

            $groupby = "{$wpdb->posts}.ID";
            /*
            if ($this->having_terms) {
                $having = 'MAX( IF( ' . $wpdb->term_taxonomy . '.taxonomy = \'' . self::taxonomy_key . '\', IF( ' . $this->having_terms . ',2,1 ),0 ) ) <> 1 ';
                $groupby = $wpdb->posts . '.ID HAVING ' . $having;
            }
            */
        }

        return $groupby;
    }

    private function add_term_if_not_exists($term, $taxonomy)
    {
        if (!term_exists($term, $taxonomy)) {
            $args = array('slug' => sanitize_title($term));
            return wp_insert_term($term, $taxonomy, $args);
        }

        return true;
    }

    public static function get_authors($post_id, $return = null)
    {
        $authors = wp_get_object_terms($post_id, self::taxonomy_key, array('fields' => 'names'));

        if (!$authors || is_wp_error($authors)) {
            return array();
        }

        $users = array();
        foreach ((array) $authors as $author) {
            $user = get_user_by('login', $author);
            if (!$user || !is_user_member_of_blog($user->ID)) {
                continue;
            }

            switch ($return) {
                case 'user_login':
                    $users[$user->ID] = $user->user_login;
                    break;

                case 'id':
                    $users[$user->ID] = $user->ID;
                    break;

                case 'user_email':
                    $users[$user->ID] = $user->user_email;
                    break;

                default:
                    $users[$user->ID] = $user;
            }
        }

        if (!$users || is_wp_error($users)) {
            $users = array();
        }

        return $users;
    }

    private function get_authors_usergroups($post_id, $return = 'all')
    {
        global $cms_workflow;

        if ($return == 'slugs') {
            $fields = 'all';
        } else {
            $fields = $return;
        }

        $usergroups_taxonomy = Workflow_User_Groups::taxonomy_key;

        $usergroups = wp_get_object_terms($post_id, $usergroups_taxonomy, array('fields' => $fields));

        if ($return == 'slugs') {
            $slugs = array();

            foreach ($usergroups as $usergroup) {
                $slugs[] = $usergroup->slug;
            }

            $usergroups = $slugs;
        }

        return $usergroups;
    }

    public function is_post_author($user, $post_id)
    {
        global $post;

        if (!$post_id && $post) {
            $post_id = $post->ID;
        }

        if (!$post_id) {
            return false;
        }

        if (!$user) {
            $user = wp_get_current_user()->ID;
        }

        if (is_int($user)) {
            $user_data = get_user_by('id', $user);
        } elseif (is_string($user)) {
            $user_data = get_user_by('login', $user);
        } else {
            return false;
        }

        $authors = $this->get_post_authors($post_id);

        if (isset($authors[$user_data->ID])) {
            return true;
        }

        return false;
    }

    public function register_settings()
    {
        add_settings_section($this->module->workflow_options_name . '_general', false, '__return_false', $this->module->workflow_options_name);
        add_settings_field('post_types', __('Freigabe', CMS_WORKFLOW_TEXTDOMAIN), array($this, 'settings_post_types_option'), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general');
        add_settings_field('role_caps', __('Autorenrechte', CMS_WORKFLOW_TEXTDOMAIN), array($this, 'settings_role_caps_option'), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general');
        add_settings_field('author_can_assign_others', __('Mitautoren', CMS_WORKFLOW_TEXTDOMAIN), array($this, 'settings_author_can_assign_others_option'), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general');
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

    public function settings_author_can_assign_others_option()
    {
    ?>
        <label for="author_can_assign_others">
            <input id="author_can_assign_others" name="_cms_workflow_authors_options[author_can_assign_others]" <?php checked($this->module->options->author_can_assign_others, 1); ?> type="checkbox">
            <?php _e('Mitautoren können dem Dokument weitere Autoren zuordnen.', CMS_WORKFLOW_TEXTDOMAIN); ?>
        </label>
    <?php
    }

    public function settings_validate($new_options)
    {
        if (empty($new_options['post_types'])) {
            $new_options['post_types'] = array();
        }

        if (empty($new_options['role_caps'])) {
            $new_options['role_caps'] = array();
        }

        if (empty($new_options['author_can_assign_others'])) {
            $new_options['author_can_assign_others'] = false;
        } else {
            $new_options['author_can_assign_others'] = true;
        }

        $new_options['post_types'] = $this->clean_post_type_options($new_options['post_types'], $this->module->post_type_support);

        $new_role_caps = array();

        $all_post_types = $this->get_available_post_types();

        foreach ($all_post_types as $post_type => $args) {
            if (!empty($new_options['post_types'][$post_type]) && $post_type == $args->capability_type) {
                $edit_posts = isset($args->cap->edit_posts) && !empty($new_options['role_caps'][$args->cap->edit_posts]) ? 1 : 0;
                $new_role_caps["edit_{$args->capability_type}"] = $edit_posts;
                $new_role_caps["edit_{$args->capability_type}s"] = $edit_posts;

                $delete_posts = isset($args->cap->delete_posts) && !empty($new_options['role_caps'][$args->cap->delete_posts]) ? 1 : 0;
                $new_role_caps["delete_{$args->capability_type}"] = $delete_posts;
                $new_role_caps["delete_{$args->capability_type}s"] = $delete_posts;

                $publish_posts = isset($args->cap->publish_posts) && !empty($new_options['role_caps'][$args->cap->publish_posts]) ? 1 : 0;
                $new_role_caps["publish_{$args->capability_type}s"] = $publish_posts;

                $edit_published_posts = isset($args->cap->edit_published_posts) && !empty($new_options['role_caps'][$args->cap->edit_published_posts]) ? 1 : 0;
                $new_role_caps["edit_published_{$args->capability_type}s"] = $edit_published_posts;

                $delete_published_posts = isset($args->cap->delete_published_posts) && !empty($new_options['role_caps'][$args->cap->delete_published_posts]) ? 1 : 0;
                $new_role_caps["delete_published_{$args->capability_type}s"] = $delete_published_posts;
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
            echo '<input id="cms-workflow-module-name" name="cms_workflow_module_name" type="hidden" value="' . esc_attr($this->module->name) . '" />'; ?>
            <p class="submit"><?php submit_button(null, 'primary', 'submit', false); ?></p>
        </form>
<?php
    }
}

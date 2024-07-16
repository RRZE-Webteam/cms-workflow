<?php

namespace RRZE\Workflow\Modules\UserGroups;

defined('ABSPATH') || exit;

use RRZE\Workflow\Main;
use RRZE\Workflow\Module;
use WP_Error;
use function RRZE\Workflow\plugin;

class UserGroups extends Module
{
    const taxonomy_key = 'workflow_usergroup';
    const term_prefix = 'workflow-usergroup-';

    public $module;
    public $module_url;

    public function __construct(Main $main)
    {
        parent::__construct($main);
        $this->module_url = $this->get_module_url(__FILE__);

        $args = array(
            'title' => __('Benutzergruppen', 'cms-workflow'),
            'description' => __('Benutzer nach Abteilung oder Funktion organisieren.', 'cms-workflow'),
            'module_url' => $this->module_url,
            'slug' => 'user-groups',
            'default_options' => array(
                'post_types' => array(
                    'post' => true,
                    'page' => true,
                ),
            ),
            'messages' => array(
                'usergroup-added' => __('Die Benutzergruppe wurde erstellt.', 'cms-workflow'),
                'usergroup-updated' => __('Die Benutzergruppe wurde aktualisiert.', 'cms-workflow'),
                'usergroup-missing' => __('Die Benutzergruppe existiert nicht.', 'cms-workflow'),
                'usergroup-deleted' => __('Die Benutzergruppe wurde gelöscht.', 'cms-workflow'),
            ),
            'configure_callback' => 'print_configure_view',
            'configure_link_text' => __('Gruppen verwalten', 'cms-workflow')
        );

        $this->module = $this->main->register_module('user_groups', $args);
    }

    public function init()
    {
        add_action('init', array($this, 'register_taxonomies'));

        add_action('admin_init', array($this, 'register_settings'));

        add_action('admin_init', array($this, 'handle_add_usergroup'));
        add_action('admin_init', array($this, 'handle_edit_usergroup'));
        add_action('admin_init', array($this, 'handle_delete_usergroup'));
        add_action('wp_ajax_inline_save_usergroup', array($this, 'handle_ajax_inline_save_usergroup'));

        add_action('wp_insert_post', [$this, 'insert_post']);

        add_action('show_user_profile', array($this, 'user_profile_page'));
        add_action('edit_user_profile', array($this, 'user_profile_page'));
        add_action('user_profile_update_errors', array($this, 'user_profile_update'), 10, 3);

        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));

        add_filter('workflow_post_versioning_filtered_taxonomies', array($this, 'filtered_taxonomies'));
        add_filter('workflow_post_versioning_filtered_network_taxonomies', array($this, 'filtered_taxonomies'));
    }

    public function filtered_taxonomies($taxonomies)
    {
        $taxonomies[] = self::taxonomy_key;
        return array_unique($taxonomies);
    }

    public function register_taxonomies()
    {
        $allowed_post_types = $this->get_post_types($this->module);

        $args = array(
            'public' => false,
            'rewrite' => false,
            'show_in_rest' => true
        );

        register_taxonomy(self::taxonomy_key, $allowed_post_types, $args);
    }

    public function enqueue_admin_scripts()
    {
        wp_enqueue_script('jquery-listfilterizer');
        wp_enqueue_script(
            'workflow-user-groups',
            $this->module_url . 'user-groups.js',
            array('jquery', 'jquery-listfilterizer'),
            plugin()->getVersion(),
            true
        );

        if ($this->is_settings_view()) {
            wp_enqueue_script(
                'workflow-user-groups-inline-edit',
                $this->module_url . 'inline-edit-min.js',
                array('jquery'),
                plugin()->getVersion(),
                true
            );
        }

        wp_localize_script('workflow-user-groups', 'user_groups_vars', array(
            'filters_label_1' => __('Alle', 'cms-workflow'),
            'filters_label_2' => __('Ausgewählt', 'cms-workflow'),
            'placeholder' => __('Suchen...', 'cms-workflow'),
        ));
    }

    public function enqueue_admin_styles()
    {
        wp_enqueue_style('jquery-listfilterizer');
        wp_enqueue_style(
            'workflow-user-groups',
            $this->module_url . 'user-groups.css',
            false,
            plugin()->getVersion(),
        );
    }

    public function handle_add_usergroup()
    {
        if (!isset($_POST['submit'], $_POST['form_action'], $_GET['page']) || $_GET['page'] != $this->module->settings_slug || $_POST['form_action'] != 'add-usergroup') {
            return;
        }

        if (!wp_verify_nonce($_POST['_wpnonce'], 'add-usergroup')) {
            wp_die($this->module->messages['nonce-failed']);
        }

        if (!current_user_can('manage_categories')) {
            wp_die($this->module->messages['invalid-permissions']);
        }

        $name = strip_tags(trim($_POST['name']));
        $description = strip_tags(trim($_POST['description']));

        $_REQUEST['form-errors'] = array();

        if (empty($name)) {
            $_REQUEST['form-errors']['name'] = __('Bitte geben Sie einen Namen für die Benutzergruppe an.', 'cms-workflow');
        }

        if ($this->get_usergroup_by('name', $name)) {
            $_REQUEST['form-errors']['name'] = __('Name wird bereits verwendet. Bitte wählen Sie einen anderen.', 'cms-workflow');
        }

        if ($this->get_usergroup_by('slug', sanitize_title($name))) {
            $_REQUEST['form-errors']['name'] = __('Name steht in Konflikt mit einem reservierten Namen. Bitte wählen Sie erneut.', 'cms-workflow');
        }

        if (strlen($name) > 40) {
            $_REQUEST['form-errors']['name'] = __('Der Name einer Benutzergruppe darf maximal 40 Zeichen lang sein. Bitte verwenden Sie einen kürzeren Namen.', 'cms-workflow');
        }

        if (count($_REQUEST['form-errors'])) {
            $_REQUEST['error'] = 'form-error';
            return;
        }

        $args = array(
            'name' => $name,
            'description' => $description,
        );

        $usergroup = $this->add_usergroup($args);
        if (is_wp_error($usergroup)) {
            wp_die(__('Fehler beim Hinzufügen einer Benutzergruppe.', 'cms-workflow'));
        }

        $args = array(
            'action' => 'edit-usergroup',
            'usergroup-id' => $usergroup->term_id,
            'message' => 'usergroup-added'
        );
        $redirect_url = $this->get_link($args);
        wp_redirect($redirect_url);
        exit;
    }

    public function handle_edit_usergroup()
    {
        if (!isset($_POST['submit'], $_POST['form_action'], $_GET['page']) || $_GET['page'] != $this->module->settings_slug || $_POST['form_action'] != 'edit-usergroup') {
            return;
        }

        if (!wp_verify_nonce($_POST['_wpnonce'], 'edit-usergroup')) {
            wp_die($this->module->messages['nonce-failed']);
        }

        if (!current_user_can('manage_categories')) {
            wp_die($this->module->messages['invalid-permissions']);
        }

        if (!$existing_usergroup = $this->get_usergroup_by('id', (int) $_POST['usergroup_id'])) {
            wp_die($this->module->messsage['usergroup-error']);
        }

        $name = strip_tags(trim($_POST['name']));
        $description = strip_tags(trim($_POST['description']));

        $_REQUEST['form-errors'] = array();

        if (empty($name)) {
            $_REQUEST['form-errors']['name'] = __('Bitte geben Sie einen Namen für die Benutzergruppe.', 'cms-workflow');
        }

        $search_term = $this->get_usergroup_by('name', $name);
        if (is_object($search_term) && $search_term->term_id != $existing_usergroup->term_id) {
            $_REQUEST['form-errors']['name'] = __('Name wird bereits verwendet. Bitte wählen Sie einen anderen.', 'cms-workflow');
        }

        $search_term = $this->get_usergroup_by('slug', sanitize_title($name));
        if (is_object($search_term) && $search_term->term_id != $existing_usergroup->term_id) {
            $_REQUEST['form-errors']['name'] = __('Name steht in Konflikt mit einem reservierten Namen. Bitte wählen Sie erneut.', 'cms-workflow');
        }

        if (strlen($name) > 40) {
            $_REQUEST['form-errors']['name'] = __('Benutzergruppe Name darf maximal 40 Zeichen lang sein. Bitte versuchen Sie einen kürzeren Namen.', 'cms-workflow');
        }

        if (count($_REQUEST['form-errors'])) {
            $_REQUEST['error'] = 'form-error';
            return;
        }

        $args = array(
            'name' => $name,
            'description' => $description,
        );

        $users = isset($_POST['usergroup_users']) ? (array) $_POST['usergroup_users'] : array();
        $users = array_map('intval', $users);

        $usergroup = $this->update_usergroup($existing_usergroup->term_id, $args, $users);

        if (is_wp_error($usergroup)) {
            wp_die(__('Fehler bei der Aktualisierung der Benutzergruppe.', 'cms-workflow'));
        }

        $args = array(
            'message' => 'usergroup-updated',
        );
        $redirect_url = $this->get_link($args);
        wp_redirect($redirect_url);
        exit;
    }

    public function handle_delete_usergroup()
    {
        if (!isset($_GET['page'], $_GET['action'], $_GET['usergroup-id']) || $_GET['page'] != $this->module->settings_slug || $_GET['action'] != 'delete-usergroup') {
            return;
        }

        if (!wp_verify_nonce($_GET['nonce'], 'delete-usergroup')) {
            wp_die($this->module->messages['nonce-failed']);
        }

        if (!current_user_can('manage_categories')) {
            wp_die($this->module->messages['invalid-permissions']);
        }

        $result = $this->delete_usergroup((int) $_GET['usergroup-id']);
        if (!$result || is_wp_error($result)) {
            wp_die(__('Fehler beim Löschen der Benutzergruppe.', 'cms-workflow'));
        }

        $redirect_url = $this->get_link(array('message' => 'usergroup-deleted'));
        wp_redirect($redirect_url);
        exit;
    }

    public function insert_post($post_id)
    {
        $post_type = get_post_type($post_id);
        if (in_array($post_type, $this->get_post_types($this->module))) {
            add_action("rest_insert_{$post_type}", [$this, 'rest_insert_post']);
        }
    }

    public function rest_insert_post($post)
    {
        $usergroups = get_terms(self::taxonomy_key);
        if (!empty($usergroups)) {
            $usergroup_ids = [];
            foreach ($usergroups as $group) {
                $usergroup_ids[] = $group->term_id;
            }
            $this->main->authors->add_post_usergroups($post, $usergroup_ids);
        }
    }

    public function handle_ajax_inline_save_usergroup()
    {
        if (!wp_verify_nonce($_POST['inline_edit'], 'usergroups-inline-edit-nonce')) {
            die($this->module->messages['nonce-failed']);
        }

        if (!current_user_can('manage_categories')) {
            die($this->module->messages['invalid-permissions']);
        }

        $usergroup_id = (int) $_POST['usergroup_id'];
        if (!$existing_term = $this->get_usergroup_by('id', $usergroup_id)) {
            die($this->module->messsage['usergroup-error']);
        }

        $name = strip_tags(trim($_POST['name']));
        $description = strip_tags(trim($_POST['description']));

        if (empty($name)) {
            $change_error = new WP_Error('invalid', __('Bitte geben Sie einen Namen für die Benutzergruppe.', 'cms-workflow'));
            die($change_error->get_error_message());
        }

        $search_term = $this->get_usergroup_by('name', $name);
        if (is_object($search_term) && $search_term->term_id != $existing_term->term_id) {
            $change_error = new WP_Error('invalid', __('Name wird bereits verwendet. Bitte wählen Sie einen anderen.', 'cms-workflow'));
            die($change_error->get_error_message());
        }

        $search_term = $this->get_usergroup_by('slug', sanitize_title($name));
        if (is_object($search_term) && $search_term->term_id != $existing_term->term_id) {
            $change_error = new WP_Error('invalid', __('Name steht in Konflikt mit einem reservierten Namen. Bitte wählen Sie erneut.', 'cms-workflow'));
            die($change_error->get_error_message());
        }

        if (strlen($name) > 40) {
            $change_error = new WP_Error('invalid', __('Benutzergruppe Name darf maximal 40 Zeichen lang sein. Bitte versuchen Sie einen kürzeren Namen.', 'cms-workflow'));
            die($change_error->get_error_message());
        }

        $args = array(
            'name' => $name,
            'description' => $description,
        );

        $return = $this->update_usergroup($existing_term->term_id, $args);
        if (!is_wp_error($return)) {
            $wp_list_table = new UsergroupsListTable($this->main);
            $wp_list_table->prepare_items();
            echo $wp_list_table->single_row($return);
            die();
        } else {
            $change_error = new WP_Error('invalid', sprintf(__('Die Benutzergruppe <strong>&bdquo;%s&rdquo;</strong> konnte nicht aktualisiert werden.', 'cms-workflow'), $name));
            die($change_error->get_error_message());
        }
    }

    public function register_settings()
    {
        add_settings_section($this->module->workflow_options_name . '_general', false, '__return_false', $this->module->workflow_options_name);
        add_settings_field('post_types', __('Freigabe', 'cms-workflow'), array($this, 'settings_post_types_option'), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general');
    }

    public function settings_post_types_option()
    {

        $this->main->settings->custom_post_type_option($this->module);
    }

    public function settings_validate($new_options)
    {
        if (!isset($new_options['post_types'])) {
            $new_options['post_types'] = array();
        }

        $new_options['post_types'] = $this->clean_post_type_options($new_options['post_types'], $this->module->post_type_support);

        return $new_options;
    }

    public function print_configure_view()
    {
        if (isset($_GET['action'], $_GET['usergroup-id']) && $_GET['action'] == 'edit-usergroup') :
            $usergroup_id = (int) $_GET['usergroup-id'];
            $usergroup = $this->get_usergroup_by('id', $usergroup_id);

            if (!$usergroup) {
                echo '<div class="error"><p>' . $this->module->messages['usergroup-missing'] . '</p></div>';
                return;
            }

            $name = (isset($_POST['name'])) ? stripslashes($_POST['name']) : $usergroup->name;
            $description = (isset($_POST['description'])) ? stripslashes($_POST['description']) : $usergroup->description;
?>
            <form method="post" action="<?php echo esc_url($this->get_link(array('action' => 'edit-usergroup', 'usergroup-id' => $usergroup_id))); ?>">
                <div id="col-right">
                    <div class="col-wrap">
                        <div id="workflow-usergroup-users" class="wrap">
                            <h4><?php _e('Benutzer', 'cms-workflow'); ?></h4>
                            <?php
                            $select_form_args = array(
                                'list_class' => 'workflow-groups-list',
                                'input_id' => 'usergroup-users',
                                'input_name' => 'usergroup_users'
                            );
                            ?>
                            <?php $this->users_select_form($usergroup->user_ids, $select_form_args); ?>
                        </div>
                    </div>
                </div>
                <div id="col-left">
                    <div class="col-wrap">
                        <div class="form-wrap">
                            <input type="hidden" name="form_action" value="edit-usergroup" />
                            <input type="hidden" name="usergroup_id" value="<?php echo esc_attr($usergroup_id); ?>" />
                            <?php
                            wp_original_referer_field();
                            wp_nonce_field('edit-usergroup');
                            ?>
                            <div class="form-field form-required">
                                <label for="name"><?php _e('Name', 'cms-workflow'); ?></label>
                                <input name="name" id="name" type="text" value="<?php echo esc_attr($name); ?>" size="40" maxlength="40" aria-required="true" />
                                <?php $this->main->settings->print_error_or_description('name', __('Der Name der Benutzergruppe.', 'cms-workflow')); ?>
                            </div>
                            <div class="form-field">
                                <label for="description"><?php _e('Beschreibung', 'cms-workflow'); ?></label>
                                <textarea name="description" id="description" rows="5" cols="40"><?php echo esc_html($description); ?></textarea>
                                <?php $this->main->settings->print_error_or_description('description', __('Die Beschreibung der Benutzergruppe.', 'cms-workflow')); ?>
                            </div>
                            <p class="submit">
                                <?php submit_button(__('Aktualisieren', 'cms-workflow'), 'primary', 'submit', false); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </form>
        <?php
        else :
            $wp_list_table = new UsergroupsListTable($this->main);
            $wp_list_table->prepare_items();
        ?>
            <div id="col-right">
                <div class="col-wrap">
                    <?php $wp_list_table->display(); ?>
                </div>
            </div>
            <div id="col-left">
                <div class="col-wrap">
                    <div class="form-wrap usergroups-wrap">
                        <h2 class="nav-tab-wrapper">
                            <a href="<?php echo esc_url($this->get_link()); ?>" class="nav-tab<?php if (!isset($_GET['action']) || $_GET['action'] != 'change-options') echo ' nav-tab-active'; ?>"><?php _e('Neue Benutzergruppe hinzufügen', 'cms-workflow'); ?></a>
                            <a href="<?php echo esc_url($this->get_link(array('action' => 'change-options'))); ?>" class="nav-tab<?php if (isset($_GET['action']) && $_GET['action'] == 'change-options') echo ' nav-tab-active'; ?>"><?php _e('Einstellungen', 'cms-workflow'); ?></a>
                        </h2>
                        <?php if (isset($_GET['action']) && $_GET['action'] == 'change-options') : ?>
                            <p class="description">Die Freigabeeinstellungen gelten für alle Benutzergruppen.</p>
                            <form class="basic-settings form-user-groups" action="<?php echo esc_url($this->get_link(array('action' => 'change-options'))); ?>" method="post">
                                <?php settings_fields($this->module->workflow_options_name); ?>
                                <?php do_settings_sections($this->module->workflow_options_name); ?>
                                <?php echo '<input id="cms_workflow_module_name" name="cms_workflow_module_name" type="hidden" value="' . esc_attr($this->module->name) . '" />'; ?>
                                <?php submit_button(); ?>
                            </form>
                        <?php else : ?>
                            <form class="add:the-list:" action="<?php echo esc_url($this->get_link()); ?>" method="post" id="addusergroup" name="addusergroup">
                                <div class="form-field form-required">
                                    <label for="name"><?php _e('Name', 'cms-workflow'); ?></label>
                                    <input type="text" aria-required="true" id="name" name="name" maxlength="40" value="<?php if (!empty($_POST['name'])) echo esc_attr($_POST['name']); ?>" />
                                    <?php $this->main->settings->print_error_or_description('name', __('Der Name wird verwendet um die Benutzergruppe zu identifizieren.', 'cms-workflow')); ?>
                                </div>
                                <div class="form-field">
                                    <label for="description"><?php _e('Beschreibung', 'cms-workflow'); ?></label>
                                    <textarea cols="40" rows="5" id="description" name="description"><?php if (!empty($_POST['description'])) echo esc_html($_POST['description']) ?></textarea>
                                    <?php $this->main->settings->print_error_or_description('description', __('Die Beschreibung ist für administrative Zwecke vorhanden.', 'cms-workflow')); ?>
                                </div>
                                <?php wp_nonce_field('add-usergroup'); ?>
                                <?php echo '<input id="form-action" name="form_action" type="hidden" value="add-usergroup" />'; ?>
                                <p class="submit"><?php submit_button(__('Neue Benutzergruppe hinzufügen', 'cms-workflow'), 'primary', 'submit', false); ?></p>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php $wp_list_table->inline_edit(); ?>
        <?php
        endif;
    }

    public function user_profile_page()
    {
        global $user_id;

        if (!$user_id || !current_user_can('manage_categories')) {
            return;
        }

        $selected_usergroups = $this->get_usergroups_for_user($user_id);
        $usergroups_form_args = array('input_id' => 'workflow-usergroups');
        ?>
        <table id="workflow-user-usergroups" class="form-table">
            <tbody>
                <tr>
                    <th>
                        <h3><?php _e('Benutzergruppen', 'cms-workflow') ?></h3>
                        <?php if ($user_id === wp_get_current_user()->ID) : ?>
                            <p><?php _e('Wählen Sie die Benutzergruppen, an denen Sie gerne teilnehmen würden:', 'cms-workflow') ?></p>
                        <?php else : ?>
                            <p><?php _e('Wählen Sie die Benutzergruppen, an denen dieser Benutzer teilnehmen sollte:', 'cms-workflow') ?></p>
                        <?php endif; ?>
                    </th>
                    <td>
                        <?php $this->usergroups_select_form($selected_usergroups, $usergroups_form_args); ?>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php wp_nonce_field('workflow_edit_profile_usergroups_nonce', 'workflow_edit_profile_usergroups_nonce'); ?>
    <?php
    }

    public function user_profile_update($errors, $update, $user)
    {
        if (!$update) {
            return array(&$errors, $update, &$user);
        }

        if (current_user_can('manage_categories') && wp_verify_nonce($_POST['workflow_edit_profile_usergroups_nonce'], 'workflow_edit_profile_usergroups_nonce')) {
            $usergroups = isset($_POST['groups_usergroups']) ? array_map('intval', (array) $_POST['groups_usergroups']) : array();
            $all_usergroups = $this->get_usergroups();
            foreach ($all_usergroups as $usergroup) {
                if (in_array($usergroup->term_id, $usergroups)) {
                    $this->add_user_to_usergroup($user->ID, $usergroup->term_id);
                } else {
                    $this->remove_user_from_usergroup($user->ID, $usergroup->term_id);
                }
            }
        }

        return array(&$errors, $update, &$user);
    }

    public function get_link($args = array())
    {
        if (!isset($args['action'])) {
            $args['action'] = '';
        }

        if (!isset($args['page'])) {
            $args['page'] = $this->module->settings_slug;
        }

        switch ($args['action']) {
            case 'delete-usergroup':
                $args['nonce'] = wp_create_nonce($args['action']);
                break;
            default:
                break;
        }
        return add_query_arg($args, get_admin_url(null, 'admin.php'));
    }

    public function usergroups_select_form($selected = array(), $args = null)
    {
        $defaults = array(
            'list_class' => 'workflow-groups-list',
            'input_id' => 'groups-usergroups',
            'input_name' => 'groups_usergroups'
        );

        $parsed_args = wp_parse_args($args, $defaults);
        extract($parsed_args, EXTR_SKIP);
        $usergroups = $this->get_usergroups();
    ?>
        <?php if (empty($usergroups)) : ?>
            <p><?php _e('Keine Benutzergruppen gefunden.', 'cms-workflow') ?> <a href="<?php echo esc_url($this->get_link()); ?>" title="<?php _e('Neue Benutzergruppe hinzufügen', 'cms-workflow') ?>" target="_blank"><?php _e('Neue Benutzergruppe hinzufügen', 'cms-workflow'); ?></a></p>
        <?php else : ?>
            <ul class="<?php echo $list_class ?>">
                <?php foreach ($usergroups as $usergroup) :
                    $checked = (in_array($usergroup->term_id, $selected)) ? ' checked="checked"' : '';
                ?>
                    <li>
                        <label for="<?php echo $input_id . '-' . esc_attr($usergroup->term_id); ?>" title="<?php echo esc_attr($usergroup->description) ?>">
                            <input type="checkbox" id="<?php echo $input_id . '-' . esc_attr($usergroup->term_id) ?>" name="<?php echo $input_name ?>[]" value="<?php echo esc_attr($usergroup->term_id) ?>" <?php echo $checked ?> />
                            <span class="workflow-usergroup-name"><?php echo esc_html($usergroup->name); ?></span>
                            <span class="workflow-usergroup-description" title="<?php echo esc_attr($usergroup->description) ?>"><?php echo (strlen($usergroup->description) >= 50) ? substr_replace(esc_html($usergroup->description), '...', 50) : esc_html($usergroup->description); ?></span>
                        </label>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif;
    }

    public function get_usergroups($args = array())
    {
        if (!isset($args['hide_empty'])) {
            $args['hide_empty'] = 0;
        }

        $usergroup_terms = get_terms(self::taxonomy_key, $args);
        if (!$usergroup_terms) {
            return false;
        }

        $usergroups = array();
        foreach ($usergroup_terms as $usergroup_term) {
            $usergroups[] = $this->get_usergroup_by('id', $usergroup_term->term_id);
        }

        return $usergroups;
    }

    public function get_usergroup_by($field, $value)
    {
        $usergroup = get_term_by($field, $value, self::taxonomy_key);

        if (!$usergroup || is_wp_error($usergroup)) {
            return $usergroup;
        }

        $usergroup->user_ids = array();
        $unencoded_description = $this->get_unencoded_description($usergroup->description);
        if (is_array($unencoded_description)) {
            foreach ($unencoded_description as $key => $value) {
                $usergroup->$key = $value;
            }
        }

        return $usergroup;
    }

    public function add_usergroup($args = array(), $user_ids = array())
    {
        if (!isset($args['name'])) {
            return new WP_Error('invalid', __('Eine neue Benutzergruppe muss einen Namen haben.', 'cms-workflow'));
        }

        $name = $args['name'];
        $default = array(
            'name' => '',
            'slug' => self::term_prefix . sanitize_title($name),
            'description' => '',
        );
        $args = array_merge($default, $args);

        $args_to_encode = array(
            'description' => $args['description'],
            'user_ids' => array_unique($user_ids),
        );
        $encoded_description = $this->get_encoded_description($args_to_encode);
        $args['description'] = $encoded_description;
        $usergroup = wp_insert_term($name, self::taxonomy_key, $args);
        if (is_wp_error($usergroup)) {
            return $usergroup;
        }

        return $this->get_usergroup_by('id', $usergroup['term_id']);
    }

    public function update_usergroup($id, $args = array(), $users = null)
    {
        $existing_usergroup = $this->get_usergroup_by('id', $id);
        if (is_wp_error($existing_usergroup)) {
            return new WP_Error('invalid', __('Die Benutzergruppe existiert nicht.', 'cms-workflow'));
        }

        $args_to_encode = array();
        $args_to_encode['description'] = (isset($args['description'])) ? $args['description'] : $existing_usergroup->description;
        $args_to_encode['user_ids'] = (is_array($users)) ? $users : $existing_usergroup->user_ids;
        $args_to_encode['user_ids'] = array_unique($args_to_encode['user_ids']);
        $encoded_description = $this->get_encoded_description($args_to_encode);
        $args['description'] = $encoded_description;

        $usergroup = wp_update_term($id, self::taxonomy_key, $args);
        if (is_wp_error($usergroup)) {
            return $usergroup;
        }

        return $this->get_usergroup_by('id', $usergroup['term_id']);
    }

    public function delete_usergroup($id)
    {

        $retval = wp_delete_term($id, self::taxonomy_key);
        return $retval;
    }

    public function add_users_to_usergroup($user_ids_or_logins, $id, $reset = true)
    {

        if (!is_array($user_ids_or_logins)) {
            return new WP_Error('invalid', __('Ungültige Benutzervariable.', 'cms-workflow'));
        }

        $usergroup = $this->get_usergroup_by('id', $id);
        if ($reset) {
            $retval = $this->update_usergroup($id, null, array());
            if (is_wp_error($retval)) {
                return $retval;
            }
        }

        $new_users = array();
        foreach ((array) $user_ids_or_logins as $user_id_or_login) {
            if (!is_numeric($user_id_or_login)) {
                $new_users[] = get_user_by('login', $user_id_or_login)->ID;
            } else {
                $new_users[] = (int) $user_id_or_login;
            }
        }

        $retval = $this->update_usergroup($id, null, $new_users);
        if (is_wp_error($retval)) {
            return $retval;
        }

        return true;
    }

    public function add_user_to_usergroup($user_id_or_login, $ids)
    {

        if (!is_numeric($user_id_or_login)) {
            $user_id = get_user_by('login', $user_id_or_login)->ID;
        } else {
            $user_id = (int) $user_id_or_login;
        }

        foreach ((array) $ids as $usergroup_id) {
            $usergroup = $this->get_usergroup_by('id', $usergroup_id);
            $usergroup->user_ids[] = $user_id;
            $retval = $this->update_usergroup($usergroup_id, null, $usergroup->user_ids);
            if (is_wp_error($retval)) {
                return $retval;
            }
        }
        return true;
    }

    public function remove_user_from_usergroup($user_id_or_login, $ids)
    {

        if (!is_numeric($user_id_or_login)) {
            $user_id = get_user_by('login', $user_id_or_login)->ID;
        } else {
            $user_id = (int) $user_id_or_login;
        }

        foreach ((array) $ids as $usergroup_id) {
            $usergroup = $this->get_usergroup_by('id', $usergroup_id);
            foreach ($usergroup->user_ids as $key => $usergroup_user_id) {
                if ($usergroup_user_id == $user_id) {
                    unset($usergroup->user_ids[$key]);
                }
            }
            $retval = $this->update_usergroup($usergroup_id, null, $usergroup->user_ids);
            if (is_wp_error($retval)) {
                return $retval;
            }
        }
        return true;
    }

    public function get_usergroups_for_user($user_id_or_login, $ids_or_objects = 'ids')
    {

        if (!is_numeric($user_id_or_login)) {
            $user_id = get_user_by('login', $user_id_or_login)->ID;
        } else {
            $user_id = (int) $user_id_or_login;
        }

        $all_usergroups = $this->get_usergroups();
        if (!empty($all_usergroups)) {
            $usergroup_objects_or_ids = array();
            foreach ($all_usergroups as $usergroup) {
                if (!in_array($user_id, $usergroup->user_ids)) {
                    continue;
                }

                if ($ids_or_objects == 'ids') {
                    $usergroup_objects_or_ids[] = (int) $usergroup->term_id;
                } elseif ($ids_or_objects == 'objects') {
                    $usergroup_objects_or_ids[] = $usergroup;
                }
            }
            return $usergroup_objects_or_ids;
        } else {
            return false;
        }
    }
}

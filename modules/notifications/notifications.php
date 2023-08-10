<?php

class Workflow_Notifications extends Workflow_Module
{

    public $schedule_notifications = false;
    public $module;
    public $module_url;
    private $alt_body;

    public function __construct()
    {
        global $cms_workflow;

        $this->module_url = $this->get_module_url(__FILE__);

        $args = array(
            'title' => __('Benachrichtigungen', CMS_WORKFLOW_TEXTDOMAIN),
            'description' => __('Benachrichtigungen über wichtige Änderungen an einem Dokument.', CMS_WORKFLOW_TEXTDOMAIN),
            'module_url' => $this->module_url,
            'slug' => 'notifications',
            'default_options' => array(
                'post_types' => array(
                    'post' => true,
                    'page' => true,
                ),
                'always_notify_admin' => false,
                'subject_prefix' => __('[Workflow]', CMS_WORKFLOW_TEXTDOMAIN),
                'post_status_notify' => true,
                'post_diff_notify' => true,
                'post_versioning_new_notify' => true,
            ),
            'configure_callback' => 'print_configure_view'
        );

        $this->module = $cms_workflow->register_module('notifications', $args);
    }

    public function init()
    {
        add_action('post_updated', array($this, 'notification_post_updated'), 10, 3);
        add_action('transition_post_status', array($this, 'notification_status_change'), 10, 3);
        add_action('workflow_version_as_new_post_draft', array($this, 'notification_post_versioning_new'), 10, 2);

        add_action('workflow_send_scheduled_email', array($this, 'send_single_email'), 10, 4);

        add_action('admin_init', array($this, 'register_settings'));
    }

    public function notification_post_updated($post_id, $post_after, $post_before)
    {
        if (!$this->module->options->post_diff_notify) {
            return;
        }

        if (!$this->is_post_type_enabled($post_before->post_type)) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!apply_filters('workflow_notification_post_updated', TRUE)) {
            return;
        }

        // If this is a new post, set an empty title for $post_before so that it appears in the diff.
        $child_posts = wp_get_post_revisions($post_id, array('numberposts' => 1));
        if (count($child_posts) == 0) {
            $post_before->post_title = '';
        }

        if (!$post_before || !$post_after) {
            return;
        }

        if (!class_exists('WP_Text_Diff_Renderer_Table')) {
            require(ABSPATH . WPINC . '/wp-diff.php');
        }

        require_once(CMS_WORKFLOW_PLUGIN_PATH . '/modules/' . $this->module->name . '/text-diff-render-table.php');
        require_once(CMS_WORKFLOW_PLUGIN_PATH . '/modules/' . $this->module->name . '/text-diff-renderer-unified.php');

        $html_diffs = array();
        $text_diffs = array();
        $identical = true;

        foreach (_wp_post_revision_fields() as $field => $field_title) {
            $left = $post_before->$field;
            $right = $post_after->$field;

            if (!$diff = $this->text_diff($left, $right)) {
                continue;
            }

            $html_diffs[$field_title] = $diff;

            $left = normalize_whitespace($left);
            $right = normalize_whitespace($right);

            $left_lines = explode(PHP_EOL, $left);
            $right_lines = explode(PHP_EOL, $right);

            $text_diff = new Text_Diff($left_lines, $right_lines);
            $renderer = new Text_Diff_Renderer_Unified();
            $text_diffs[$field_title] = $renderer->render($text_diff);

            $identical = false;
        }

        if ($identical) {
            $post_before = null;
            $post_after = null;
            return;
        }

        $left_title = __('Revision', CMS_WORKFLOW_TEXTDOMAIN);
        $right_title = __('Aktuelles Dokument', CMS_WORKFLOW_TEXTDOMAIN);

        $edit_link = htmlspecialchars_decode(get_edit_post_link($post_id));
        $view_link = $post_after->post_status == 'publish' ? htmlspecialchars_decode(get_permalink($post_id)) : '';

        $post_title = $this->draft_or_post_title($post_after->post_title);
        $post_type_name = get_post_type_object($post_after->post_type)->labels->singular_name;

        $current_user = wp_get_current_user();

        if ($current_user->ID) {
            $current_user_display_name = $current_user->display_name ? $current_user->display_name : $current_user->user_login;
            $current_user_email = sprintf('(%s)', $current_user->user_email);
        } else {
            $current_user_display_name = __('CMS-Workflow', CMS_WORKFLOW_TEXTDOMAIN);
            $current_user_email = '';
        }

        $authors = array();

        if ($this->module_activated('authors')) {
            $authors = $this->get_authors_details($post_id);
        }

        $post_status_name = $this->get_post_status_name($post_after->post_status);

        if (is_user_member_of_blog($post_after->post_author)) {
            $post_author = get_userdata($post_after->post_author);
            $authors[$post_after->post_author] = sprintf('%1$s (%2$s)', $post_author->display_name, $post_author->user_email);
            $authors = array_unique($authors);
        }

        $blogname = get_option('blogname');
        $admin_email = get_option('admin_email');

        $subject = sprintf(__('%1$s - Das Dokument „%2$s“ hat sich geändert.', CMS_WORKFLOW_TEXTDOMAIN), $blogname, $post_title);

        // PLAIN TEXT Body
        $body = '';

        $body .= sprintf(__('Das Dokument %1$s „%2$s“ wurde von %3$s %4$s geändert.', CMS_WORKFLOW_TEXTDOMAIN), $post_id, $post_title, $current_user_display_name, $current_user_email) . "\r\n";
        $body .= sprintf(__('Diese Aktion wurde am %1$s um %2$s %3$s ausgeführt.', CMS_WORKFLOW_TEXTDOMAIN), date_i18n(get_option('date_format')), date_i18n(get_option('time_format')), get_option('timezone_string')) . "\r\n";

        $body .= "\r\n";

        $date_format = sprintf('%1$s %2$s', get_option('date_format'), get_option('time_format'));
        $date_before = date_i18n($date_format, strtotime($post_before->post_modified));
        $date_after = date_i18n($date_format, strtotime($post_after->post_modified));

        $length = max(strlen($left_title), strlen($right_title));
        $left_title = str_pad($left_title, $length + 2);
        $right_title = str_pad($right_title, $length + 2);

        $text_diff = '';

        foreach ($text_diffs as $field_title => $diff) {
            $text_diff .= $field_title . PHP_EOL;
            $text_diff .= "===================================================================" . PHP_EOL;
            $text_diff .= "--- $left_title	($date_before)" . PHP_EOL;
            $text_diff .= "+++ $right_title	($date_after)" . PHP_EOL;
            $text_diff .= $diff . PHP_EOL . PHP_EOL;
        }

        $text_diff = rtrim($text_diff);

        $body .= $text_diff;

        $body .= "\r\n \r\n";

        $body .= __('Dokumenteinzelheiten', CMS_WORKFLOW_TEXTDOMAIN) . "\r\n";
        $body .= sprintf(__('Titel: %s', CMS_WORKFLOW_TEXTDOMAIN), $post_title) . "\r\n";

        $body .= sprintf(_nx('Autor: %1$s', 'Autoren: %1$s', count($authors), 'notifications', CMS_WORKFLOW_TEXTDOMAIN), implode(', ', $authors)) . "\r\n";
        $body .= sprintf(__('Art: %1$s', CMS_WORKFLOW_TEXTDOMAIN), $post_type_name) . "\r\n";
        $body .= sprintf(__('Status: %1$s', CMS_WORKFLOW_TEXTDOMAIN), $post_status_name) . "\r\n";

        $body .= "\r\n";

        $body .= __('Weitere Aktionen', CMS_WORKFLOW_TEXTDOMAIN) . "\r\n";
        $body .= sprintf(__('Dokument bearbeiten: %s', CMS_WORKFLOW_TEXTDOMAIN), $edit_link) . "\r\n";
        $body .= $view_link ? sprintf(__('Dokument ansehen: %s', CMS_WORKFLOW_TEXTDOMAIN), $view_link) . "\r\n" : '';

        $body .= "\r\n";

        $body .= $this->get_notification_footer($post_before);

        $this->alt_body = $body;

        // MIME Body
        $body = '';

        $body .= "<div>\r\n";
        $body .= sprintf(__('Das Dokument %1$s „%2$s“ wurde von %3$s %4$s geändert.', CMS_WORKFLOW_TEXTDOMAIN), $post_id, $post_title, $current_user_display_name, $current_user_email) . "\r\n";
        $body .= "</div><div>\r\n";
        $body .= sprintf(__('Diese Aktion wurde am %1$s um %2$s %3$s ausgeführt.', CMS_WORKFLOW_TEXTDOMAIN), date_i18n(get_option('date_format')), date_i18n(get_option('time_format')), get_option('timezone_string')) . "\r\n";
        $body .= "</div>\r\n";

        $body .= "<br>\r\n";

        $html_diff_head = '';
        $html_diff_head .= "<table style='width: 100%; border-collapse: collapse; border: none;'><tr>\n";
        $html_diff_head .= "<td style='width: 50%; padding: 0; margin: 0;'>" . esc_html($left_title) . ' @ ' . esc_html($date_before) . "</td>\n";
        $html_diff_head .= "<td style='width: 50%; padding: 0; margin: 0;'>" . esc_html($right_title) . ' @ ' . esc_html($date_after) . "</td>\n";
        $html_diff_head .= "</tr></table>\n\n";

        $html_diff = '';
        foreach ($html_diffs as $field_title => $diff) {
            $html_diff .= '<h3>' . esc_html($field_title) . "</h3>\n";
            $html_diff .= "$diff\n\n";
        }

        $html_diff = rtrim($html_diff);

        $html_diff = str_replace("class='diff'", 'style="width: 100%; border-collapse: collapse; border: none; white-space: pre-wrap; word-wrap: break-word; font-family: Consolas,Monaco,Courier,monospace;"', $html_diff);
        $html_diff = preg_replace('#<col[^>]+/?>#i', '', $html_diff);
        $html_diff = str_replace("class='diff-deletedline'", 'style="padding: 5px; width: 50%; background-color: #fdd;"', $html_diff);
        $html_diff = str_replace("class='diff-addedline'", 'style="padding: 5px; width: 50%; background-color: #dfd;"', $html_diff);
        $html_diff = str_replace("class='diff-context'", 'style="padding: 5px; width: 50%;"', $html_diff);
        $html_diff = str_replace('<td>', '<td style="padding: 5px;">', $html_diff);
        $html_diff = str_replace('<del>', '<del style="text-decoration: none; background-color: #f99;">', $html_diff);
        $html_diff = str_replace('<ins>', '<ins style="text-decoration: none; background-color: #9f9;">', $html_diff);
        $html_diff = str_replace(array('</td>', '</tr>', '</tbody>'), array("</td>\n", "</tr>\n", "</tbody>\n"), $html_diff);

        $html_diff = $html_diff_head . $html_diff;

        $body .= $html_diff;
        $body .= "<br>\r\n";

        $body .= "<div>\r\n";
        $body .= __('Dokumenteinzelheiten', CMS_WORKFLOW_TEXTDOMAIN) . "\r\n";
        $body .= "</div><div>\r\n";
        $body .= sprintf(__('Titel: %s', CMS_WORKFLOW_TEXTDOMAIN), $post_title) . "\r\n";
        $body .= "</div><div>\r\n";
        $body .= sprintf(_nx('Autor: %1$s', 'Autoren: %1$s', count($authors), 'notifications', CMS_WORKFLOW_TEXTDOMAIN), implode(', ', $authors)) . "\r\n";
        $body .= "</div><div>\r\n";
        $body .= sprintf(__('Art: %1$s', CMS_WORKFLOW_TEXTDOMAIN), $post_type_name) . "\r\n";
        $body .= "</div><div>\r\n";
        $body .= sprintf(__('Status: %1$s', CMS_WORKFLOW_TEXTDOMAIN), $post_status_name) . "\r\n";
        $body .= "</div>\r\n";

        $body .= "<br>\r\n";

        $body .= "<div>\r\n";
        $body .= __('Weitere Aktionen', CMS_WORKFLOW_TEXTDOMAIN) . "\r\n";
        $body .= "</div><div>\r\n";
        $body .= sprintf(__('Dokument bearbeiten: %s', CMS_WORKFLOW_TEXTDOMAIN), $edit_link) . "\r\n";
        $body .= $view_link ? "</div><div>\r\n" : '';
        $body .= $view_link ? sprintf(__('Dokument ansehen: %s', CMS_WORKFLOW_TEXTDOMAIN), $view_link) . "\r\n" : '';
        $body .= "</div>\r\n";

        $body .= "<br>\r\n";

        $body .= "<div>\r\n";
        $body .= sprintf(__('Sie erhalten diese E-Mail, weil Sie Mitautor zum Dokument „%s“ sind.', CMS_WORKFLOW_TEXTDOMAIN), $this->draft_or_post_title($post_before->post_title)) . "\r\n";
        $body .= "</div>\r\n";

        $body .= "<br>\r\n";

        $body .= "<div>\r\n";
        $body .= get_option('blogname') . "\r\n" . get_bloginfo('url') . "\r\n" . admin_url() . "\r\n";
        $body .= "</div>\r\n";

        add_action('phpmailer_init', array($this, 'phpmailer_init'));

        $this->send_email('post-updated', $post_before, $subject, $body);
    }

    public function notification_status_change($new_status, $old_status, $post)
    {
        global $cms_workflow;

        if (!$this->module->options->post_status_notify) {
            return;
        }

        if (!$this->is_post_type_enabled($post->post_type)) {
            return;
        }

        if (!apply_filters('workflow_notification_status_change', TRUE)) {
            return;
        }

        if (get_post_meta($post->ID, '_version_post_id', true) && $new_status == 'publish') {
            return;
        }

        $ignored_statuses = apply_filters('workflow_notification_ignored_statuses', array($old_status, 'inherit', 'auto-draft'), $post->post_type);

        if (in_array($new_status, $ignored_statuses)) {
            return;
        }

        $post_id = $post->ID;
        $post_title = $this->draft_or_post_title($post->post_title);
        $post_type = get_post_type_object($post->post_type)->labels->singular_name;

        $current_user = wp_get_current_user();

        if ($current_user->ID) {
            $current_user_display_name = $current_user->display_name ? $current_user->display_name : $current_user->user_login;
            $current_user_email = sprintf('(%s)', $current_user->user_email);
        } else {
            $current_user_display_name = __('CMS-Workflow', CMS_WORKFLOW_TEXTDOMAIN);
            $current_user_email = '';
        }

        $authors = array();

        if ($this->module_activated('authors')) {
            $authors = $this->get_authors_details($post_id);
        }

        if (is_user_member_of_blog($post->post_author)) {
            $post_author = get_userdata($post->post_author);
            $authors[$post->post_author] = sprintf('%1$s (%2$s)', $post_author->display_name, $post_author->user_email);
            $authors = array_unique($authors);
        }

        $blogname = get_option('blogname');

        $body = '';

        if ($old_status == 'new' || $old_status == 'auto-draft') {
            $subject = sprintf(__('%1$s - Neues Dokument wurde erstellt: "%2$s"', CMS_WORKFLOW_TEXTDOMAIN), $blogname, $post_title);
            $body .= sprintf(__('Das Dokument %1$s „%2$s“ wurde von %3$s %4$s erstellt.', CMS_WORKFLOW_TEXTDOMAIN), $post_id, $post_title, $current_user->display_name, $current_user->user_email) . "\r\n";
        } elseif ($new_status == 'trash') {
            $subject = sprintf(__('%1$s - Das Dokument „%2$s“ wurde in den Papierkorb verschoben.', CMS_WORKFLOW_TEXTDOMAIN), $blogname, $post_title);
            $body .= sprintf(__('Das Dokument %1$s „%2$s“ wurde von %3$s %4$s in den Papierkorb verschoben.', CMS_WORKFLOW_TEXTDOMAIN), $post_id, $post_title, $current_user_display_name, $current_user_email) . "\r\n";
        } elseif ($old_status == 'trash') {
            $subject = sprintf(__('%1$s - Das Dokument „%2$s“ wurde aus dem Papierkorb wiederhergestellt.', CMS_WORKFLOW_TEXTDOMAIN), $blogname, $post_title);
            $body .= sprintf(__('Das Dokument %1$s „%2$s“ wurde von %3$s %4$s aus dem Papierkorb wiederhergestellt.', CMS_WORKFLOW_TEXTDOMAIN), $post_id, $post_title, $current_user_display_name, $current_user_email) . "\r\n";
        } elseif ($new_status == 'future') {
            $subject = sprintf(__('%1$s - Das Dokument „%2$s“ wurde zeitlich geplant.'), $blogname, $post_title);
            $body .= sprintf(__('Das Dokument %1$s „%2$s“ wurde von %3$s %4$s zeitlich geplant.'), $post_id, $post_title, $current_user_display_name, $current_user_email) . "\r\n";
        } elseif ($new_status == 'publish') {
            $subject = sprintf(__('%1$s - Das Dokument „%2$s“ wurde veröffentlicht.', CMS_WORKFLOW_TEXTDOMAIN), $blogname, $post_title);
            $body .= sprintf(__('Das Dokument %1$s „%2$s“ wurde von %3$s %4$s veröffentlich.', CMS_WORKFLOW_TEXTDOMAIN), $post_id, $post_title, $current_user_display_name, $current_user_email) . "\r\n";
        } elseif ($old_status == 'publish') {
            $subject = sprintf(__('%1$s - Das Dokument „%2$s“ wurde unveröffentlicht.', CMS_WORKFLOW_TEXTDOMAIN), $blogname, $post_title);
            $body .= sprintf(__('Das Dokument %1$s „%2$s“ wurde von %3$s %4$s unveröffentlicht.', CMS_WORKFLOW_TEXTDOMAIN), $post_id, $post_title, $current_user_display_name, $current_user_email) . "\r\n";
        } else {
            $subject = sprintf(__('%1$s - Der Status des Dokuments „%2$s“ hat sich geändert.', CMS_WORKFLOW_TEXTDOMAIN), $blogname, $post_title);
            $body .= sprintf(__('Der Status des Dokuments %1$s „%2$s“ wurde von %3$s %4$s geändert.', CMS_WORKFLOW_TEXTDOMAIN), $post_id, $post_title, $current_user_display_name, $current_user_email) . "\r\n";
        }

        $body .= sprintf(__('Diese Aktion wurde am %1$s um %2$s %3$s ausgeführt.', CMS_WORKFLOW_TEXTDOMAIN), date_i18n(get_option('date_format')), date_i18n(get_option('time_format')), get_option('timezone_string')) . "\r\n";

        $old_status_name = $this->get_post_status_name($old_status);
        $new_status_name = $this->get_post_status_name($new_status);

        $body .= "\r\n";

        $body .= sprintf(__('%1$s  >>>  %2$s', CMS_WORKFLOW_TEXTDOMAIN), $old_status_name, $new_status_name);
        $body .= "\r\n \r\n";

        $body .= __('Dokumenteinzelheiten', CMS_WORKFLOW_TEXTDOMAIN) . "\r\n";
        $body .= sprintf(__('Titel: %s', CMS_WORKFLOW_TEXTDOMAIN), $post_title) . "\r\n";

        $body .= sprintf(_nx('Autor: %1$s', 'Autoren: %1$s', count($authors), 'notifications', CMS_WORKFLOW_TEXTDOMAIN), implode(', ', $authors)) . "\r\n";
        $body .= sprintf(__('Art: %1$s', CMS_WORKFLOW_TEXTDOMAIN), $post_type) . "\r\n";
        $body .= sprintf(__('Status: %1$s', CMS_WORKFLOW_TEXTDOMAIN), $new_status_name) . "\r\n";

        $edit_link = htmlspecialchars_decode(get_edit_post_link($post_id));

        $view_link = $new_status == 'publish' ? htmlspecialchars_decode(get_permalink($post_id)) : '';

        $body .= "\r\n";

        $body .= __('Weitere Aktionen', CMS_WORKFLOW_TEXTDOMAIN) . "\r\n";
        $body .= sprintf(__('Dokument bearbeiten: %s', CMS_WORKFLOW_TEXTDOMAIN), $edit_link) . "\r\n";
        $body .= $view_link ? sprintf(__('Dokument ansehen: %s', CMS_WORKFLOW_TEXTDOMAIN), $view_link) . "\r\n" : '';

        $body .= "\r\n";

        $body .= $this->get_notification_footer($post);

        $this->send_email('status-change', $post, $subject, $body);
    }

    public function notification_post_versioning_new($post_id, $original_post_id)
    {
        if (!$this->module->options->post_versioning_new_notify) {
            return;
        }

        $post = get_post($post_id);

        if (!$this->is_post_type_enabled($post->post_type)) {
            return;
        }

        $original_post = get_post($original_post_id);

        if (!$this->is_post_type_enabled($original_post->post_type)) {
            return;
        }

        $post_status = $post->post_status;
        $original_post_status = $original_post->post_status;

        $post_title = $this->draft_or_post_title($post->post_title);
        $original_post_title = $this->draft_or_post_title($original_post->post_title);

        $post_type = get_post_type_object($post->post_type)->labels->singular_name;

        $current_user = wp_get_current_user();

        if ($current_user->ID) {
            $current_user_display_name = $current_user->display_name ? $current_user->display_name : $current_user->user_login;
            $current_user_email = sprintf('(%s)', $current_user->user_email);
        } else {
            $current_user_display_name = __('CMS-Workflow', CMS_WORKFLOW_TEXTDOMAIN);
            $current_user_email = '';
        }

        $authors = array();

        if ($this->module_activated('authors')) {
            $authors = $this->get_authors_details($post_id);
        }

        if (is_user_member_of_blog($post->post_author)) {
            $post_author = get_userdata($post->post_author);
            $authors[$post->post_author] = sprintf('%1$s (%2$s)', $post_author->display_name, $post_author->user_email);
            $authors = array_unique($authors);
        }

        $blogname = get_option('blogname');

        $subject = sprintf(__('%1$s - Neue Version des Dokumentes %2$s wurde erstellt.', CMS_WORKFLOW_TEXTDOMAIN), $blogname, $original_post_title);
        $body = '';

        $body = sprintf(__('Das Dokument %1$s „%2$s“ wurde von %3$s %4$s erstellt.', CMS_WORKFLOW_TEXTDOMAIN), $post_id, $post_title, $current_user_display_name, $current_user_email) . "\r\n";

        $body .= sprintf(__('Diese Aktion wurde am %1$s um %2$s %3$s ausgeführt.', CMS_WORKFLOW_TEXTDOMAIN), date_i18n(get_option('date_format')), date_i18n(get_option('time_format')), get_option('timezone_string')) . "\r\n";

        $status_name = $this->get_post_status_name($post_status);

        $body .= "\r\n";

        $body .= __('Dokumenteinzelheiten', CMS_WORKFLOW_TEXTDOMAIN) . "\r\n";
        $body .= sprintf(__('Titel: %s', CMS_WORKFLOW_TEXTDOMAIN), $post_title) . "\r\n";

        $body .= sprintf(_nx('Autor: %1$s', 'Autoren: %1$s', count($authors), 'notifications', CMS_WORKFLOW_TEXTDOMAIN), implode(', ', $authors)) . "\r\n";
        $body .= sprintf(__('Art: %1$s', CMS_WORKFLOW_TEXTDOMAIN), $post_type) . "\r\n";
        $body .= sprintf(__('Status: %1$s', CMS_WORKFLOW_TEXTDOMAIN), $status_name) . "\r\n";

        $edit_link = htmlspecialchars_decode(get_edit_post_link($post_id));

        $view_link = $post_status == 'publish' ? htmlspecialchars_decode(get_permalink($post_id)) : '';
        $original_view_link = $original_post_status == 'publish' ? htmlspecialchars_decode(get_permalink($original_post_id)) : '';

        $body .= "\r\n";

        $body .= __('Weitere Aktionen', CMS_WORKFLOW_TEXTDOMAIN) . "\r\n";
        $body .= sprintf(__('Dokument bearbeiten: %s', CMS_WORKFLOW_TEXTDOMAIN), $edit_link) . "\r\n";
        $body .= $view_link ? sprintf(__('Dokument ansehen: %s', CMS_WORKFLOW_TEXTDOMAIN), $view_link) . "\r\n" : '';
        $body .= $original_view_link ? sprintf(__('Originaldokument ansehen: %s', CMS_WORKFLOW_TEXTDOMAIN), $original_view_link) . "\r\n" : '';

        $body .= "\r\n";

        $body .= $this->get_notification_footer($post);

        $this->send_email('post-versioning-new', $post, $subject, $body);
    }

    public function get_notification_footer($post)
    {
        $body = "";
        $body .= sprintf(__('Sie erhalten diese E-Mail, weil Sie Mitautor zum Dokument „%s“ sind.', CMS_WORKFLOW_TEXTDOMAIN), $this->draft_or_post_title($post->post_title));
        $body .= "\r\n \r\n";
        $body .= get_option('blogname') . "\r\n" . get_bloginfo('url') . "\r\n" . admin_url() . "\r\n";
        return $body;
    }

    public function phpmailer_init(&$phpmailer)
    {
        $phpmailer->AltBody = $this->alt_body;
    }

    public function send_email($action, $post, $subject, $message, $headers = '')
    {
        $subject = sprintf('%1$s %2$s', $this->module->options->subject_prefix, $subject);

        if (empty($headers)) {
            $headers = sprintf('From: %1$s <%2$s>', get_option('blogname'), get_option('admin_email'));
        }

        $recipients = $this->get_notification_recipients($post, true);

        if ($recipients && !is_array($recipients)) {
            $recipients = explode(',', $recipients);
        }

        $subject = apply_filters('workflow_notification_send_email_subject', $subject, $action, $post);
        $message = apply_filters('workflow_notification_send_email_message', $message, $action, $post);
        $headers = apply_filters('workflow_notification_send_email_message_headers', $headers, $action, $post);

        if ($this->schedule_notifications) {
            $this->schedule_emails($recipients, $subject, $message, $headers);
        } elseif (!empty($recipients)) {
            foreach ($recipients as $recipient) {
                $this->send_single_email($recipient, $subject, $message, $headers);
            }
        }
    }

    public function schedule_emails($recipients, $subject, $message, $headers = '', $time_offset = 1)
    {
        $recipients = (array) $recipients;

        $send_time = time();

        foreach ($recipients as $recipient) {
            wp_schedule_single_event($send_time, 'workflow_send_scheduled_email', array($recipient, $subject, $message, $headers));
            $send_time += $time_offset;
        }
    }

    public function send_single_email($to, $subject, $message, $headers = '')
    {
        wp_mail($to, $subject, $message, $headers);
    }

    private function get_notification_recipients($post, $string = false)
    {

        $post_id = $post->ID;

        $authors = array();
        $admins = array();
        $recipients = array();

        if ($this->module->options->always_notify_admin) {
            $admins[] = get_option('admin_email');
        }

        if ($this->module_activated('authors')) {
            $authors = $this->get_authors_emails($post_id);
        }

        if (is_user_member_of_blog($post->post_author)) {
            $post_author = get_userdata($post->post_author);
            $authors[$post->post_author] = $post_author->user_email;
            $authors = array_unique($authors);
        }

        $only_this_user = apply_filters('workflow_notification_only_this_user', 0);
        if ($only_this_user) {
            $authors = array();
            $authors[$only_this_user] = $only_this_user;
        }

        $recipients = array_merge($authors, $admins);
        $recipients = array_unique($recipients);

        foreach ($recipients as $key => $user_email) {
            if (empty($recipients[$key])) {
                unset($recipients[$key]);
            }

            if (apply_filters('workflow_notification_email_current_user', false) === false && wp_get_current_user()->user_email == $user_email) {
                unset($recipients[$key]);
            }
        }

        $recipients = apply_filters('workflow_notification_recipients', $recipients, $post, $string);

        if ($string && is_array($recipients)) {
            return implode(',', $recipients);
        } else {
            return $recipients;
        }
    }

    private function get_authors_emails($post_id)
    {
        $users = Workflow_Authors::get_authors($post_id, 'user_email');
        if (!$users) {
            return array();
        }

        return $users;
    }

    private function get_authors_details($post_id)
    {
        $users = Workflow_Authors::get_authors($post_id);
        if (!$users) {
            return array();
        }

        foreach ($users as $key => $user) {
            $users[$key] = sprintf('%1$s (%2$s)', $user->display_name, $user->user_email);
        }

        return $users;
    }

    public function register_settings()
    {
        add_settings_section($this->module->workflow_options_name . '_general', __('Allgemein', CMS_WORKFLOW_TEXTDOMAIN), '__return_false', $this->module->workflow_options_name);
        add_settings_field('post_types', __('Freigabe', CMS_WORKFLOW_TEXTDOMAIN), array($this, 'settings_post_types_option'), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general');
        add_settings_field('always_notify_admin', __('Administrator benachrichtigen?', CMS_WORKFLOW_TEXTDOMAIN), array($this, 'settings_always_notify_admin_option'), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general');
        add_settings_field('subject_prefix', __('Betreff-Präfix', CMS_WORKFLOW_TEXTDOMAIN), array($this, 'settings_subject_prefix_option'), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general');

        if ($this->module_activated('authors')) {
            add_settings_section($this->module->workflow_options_name . '_authors', __('Autoren', CMS_WORKFLOW_TEXTDOMAIN), '__return_false', $this->module->workflow_options_name);
            add_settings_field('post_status_notify', __('Änderungen des Dokumentstatus benachrichtigen?', CMS_WORKFLOW_TEXTDOMAIN), array($this, 'settings_post_status_notify_option'), $this->module->workflow_options_name, $this->module->workflow_options_name . '_authors');
            add_settings_field('post_diff_notify', __('Änderungen des Dokumentinhaltes benachrichtigen?', CMS_WORKFLOW_TEXTDOMAIN), array($this, 'settings_post_diff_notify_option'), $this->module->workflow_options_name, $this->module->workflow_options_name . '_authors');
        }

        if ($this->module_activated('post_versioning')) {
            add_settings_section($this->module->workflow_options_name . '_versioning', __('Versionierung', CMS_WORKFLOW_TEXTDOMAIN), '__return_false', $this->module->workflow_options_name);
            add_settings_field('post_versioning_new_notify', __('Neue Version eines Dokuments benachrichtigen?', CMS_WORKFLOW_TEXTDOMAIN), array($this, 'settings_post_versioning_new_notify_option'), $this->module->workflow_options_name, $this->module->workflow_options_name . '_versioning');
        }
    }

    public function settings_post_types_option()
    {
        global $cms_workflow;
        $cms_workflow->settings->custom_post_type_option($this->module);
    }

    public function settings_always_notify_admin_option()
    {
        $options = array(
            false => __('Nein', CMS_WORKFLOW_TEXTDOMAIN),
            true => __('Ja', CMS_WORKFLOW_TEXTDOMAIN),
        );
        echo '<select id="always_notify_admin" name="' . $this->module->workflow_options_name . '[always_notify_admin]">';
        foreach ($options as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"';
            echo selected($this->module->options->always_notify_admin, $value);
            echo '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }

    public function settings_subject_prefix_option()
    {
?>
        <input type="text" class="medium-text" value="<?php echo $this->module->options->subject_prefix; ?>" id="subject_prefix" name="<?php echo $this->module->workflow_options_name . '[subject_prefix]'; ?>">
        <p class="description"><?php _e('Dieser Text wird dem Betreff der Nachricht vorangestellt.', CMS_WORKFLOW_TEXTDOMAIN); ?></p>
    <?php
    }

    public function settings_validate($new_options)
    {

        if (!isset($new_options['post_types'])) {
            $new_options['post_types'] = array();
        }

        $new_options['post_types'] = $this->clean_post_type_options($new_options['post_types'], $this->module->post_type_support);

        if (!isset($new_options['always_notify_admin']) || !$new_options['always_notify_admin']) {
            $new_options['always_notify_admin'] = false;
        }

        $new_options['subject_prefix'] = !empty($new_options['subject_prefix']) ? mb_strimwidth($new_options['subject_prefix'], 0, 30) : $this->module->options->subject_prefix;

        return $new_options;
    }

    public function settings_post_status_notify_option()
    {
        $options = array(
            false => __('Nein', CMS_WORKFLOW_TEXTDOMAIN),
            true => __('Ja', CMS_WORKFLOW_TEXTDOMAIN),
        );
        echo '<select id="post_status_notify" name="' . $this->module->workflow_options_name . '[post_status_notify]">';
        foreach ($options as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"';
            echo selected($this->module->options->post_status_notify, $value);
            echo '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }

    public function settings_post_diff_notify_option()
    {
        $options = array(
            false => __('Nein', CMS_WORKFLOW_TEXTDOMAIN),
            true => __('Ja', CMS_WORKFLOW_TEXTDOMAIN),
        );
        echo '<select id="post_diff_notify" name="' . $this->module->workflow_options_name . '[post_diff_notify]">';
        foreach ($options as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"';
            echo selected($this->module->options->post_diff_notify, $value);
            echo '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }

    public function settings_post_versioning_new_notify_option()
    {
        $options = array(
            false => __('Nein', CMS_WORKFLOW_TEXTDOMAIN),
            true => __('Ja', CMS_WORKFLOW_TEXTDOMAIN),
        );
        echo '<select id="post_versioning_new_notify" name="' . $this->module->workflow_options_name . '[post_versioning_new_notify]">';
        foreach ($options as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"';
            echo selected($this->module->options->post_versioning_new_notify, $value);
            echo '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
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

    private function draft_or_post_title($post_title)
    {
        return !empty($post_title) ? $post_title : __('(Kein Titel)', CMS_WORKFLOW_TEXTDOMAIN);
    }

    private function text_diff($left_string, $right_string, $args = null)
    {
        $defaults = array(
            'title' => '',
            'title_left' => '',
            'title_right' => ''
        );

        $args = wp_parse_args($args, $defaults);

        $left_string = normalize_whitespace($left_string);
        $right_string = normalize_whitespace($right_string);
        $left_lines = explode("\n", $left_string);
        $right_lines = explode("\n", $right_string);

        $text_diff = new Text_Diff($left_lines, $right_lines);
        $renderer = new Workflow_Text_Diff_Renderer_Table();
        $diff = $renderer->render($text_diff);

        if (!$diff) {
            return '';
        }

        $r = "<table class='diff'>\n";
        $r .= "<col class='ltype' /><col class='content' /><col class='ltype' /><col class='content' />";

        if ($args['title'] || $args['title_left'] || $args['title_right']) {
            $r .= "<thead>";
        }

        if ($args['title']) {
            $r .= "<tr class='diff-title'><th colspan='4'>$args[title]</th></tr>\n";
        }

        if ($args['title_left'] || $args['title_right']) {
            $r .= "<tr class='diff-sub-title'>\n";
            $r .= "\t<td></td><th>$args[title_left]</th>\n";
            $r .= "\t<td></td><th>$args[title_right]</th>\n";
            $r .= "</tr>\n";
        }

        if ($args['title'] || $args['title_left'] || $args['title_right']) {
            $r .= "</thead>\n";
        }

        $r .= "<tbody>\n$diff\n</tbody>\n";
        $r .= "</table>";

        return $r;
    }
}

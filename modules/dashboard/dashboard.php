<?php

class Workflow_Dashboard extends Workflow_Module {

    public $module;
    public $allowed_post_type = array();

    public function __construct() {
        global $cms_workflow;

        $this->module_url = $this->get_module_url(__FILE__);

        $content_help_tab = array(
            '<p>' . __('Je nachdem, was Sie auf dieser Seite aktivieren, können Sie im Dashboard unterschiedliche Inhalte verfolgen:', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>' . __('<strong>Aktuelle Entwürfe</strong> - Übersicht über alle Dokumente mit Status <i>Entwurf</i>, bei denen Sie als Autor eingetragen sind.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>' . __('<strong>Aktuell ausstehende Reviews</strong> - Übersicht über alle Dokumente mit Status <i>Ausstehender Review</i>, bei denen Sie als Autor eingetragen sind.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>' . __('<strong>Aufgabenliste</strong> - Liste aller anstehenden Aufgaben zu Dokumenten, bei denen Sie als Autor eingetragen sind (sofern das Modul <i>Aufgabenliste</i> aktiviert ist).', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>' . __('Auf der Dashboard-Seite hat dann jeder Nutzer die Möglichkeit, über die Registerkarte <i>Optionen einblenden</i> in der rechten oberen Ecke die gewünschten Inhalte ein- oder auszublenden.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
        );

        $context_help_tab = array(
            '<p>' . __('Um anstehende Arbeiten schneller überblicken zu können, haben Sie die Möglichkeit, im Dashboard unterschiedliche Inhalte zu verfolgen:', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>' . __('<strong>Aktuelle Entwürfe</strong> - Übersicht über alle Dokumente mit Status <i>Entwurf</i>, bei denen Sie als Autor eingetragen sind.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>' . __('<strong>Aktuell ausstehende Reviews</strong> - Übersicht über alle Dokumente mit Status <i>Ausstehender Review</i>, bei denen Sie als Autor eingetragen sind.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>' . __('Sollten bei Ihnen die Boxen <i>Aktuelle Entwürfe</i> und <i>Aktuell ausstehende Reviews</i> nicht erscheinen, können Sie sie über die Lasche <i>Optionen einblenden</i> in der rechten oberen Ecke anzeigen lassen (sofern der Administrator diese freigegeben hat).', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
        );

        $args = array(
            'title' => __('Dashboard', CMS_WORKFLOW_TEXTDOMAIN),
            'description' => __('Inhalte im Dashboard verfolgen.', CMS_WORKFLOW_TEXTDOMAIN),
            'module_url' => $this->module_url,
            'slug' => 'dashboard',
            'default_options' => array(
                'right_now' => true,
                'recent_drafts_widget' => true,
                'recent_pending_widget' => true,
                'task_list_widget' => true,
            ),
            'configure_callback' => 'print_configure_view',
            'settings_help_tab' => array(
                'id' => 'workflow-dashboard-overview',
                'title' => __('Übersicht', CMS_WORKFLOW_TEXTDOMAIN),
                'content' => implode(PHP_EOL, $content_help_tab),
            ),
            'settings_help_sidebar' => __('<p><strong>Für mehr Information:</strong></p><p><a href="http://blogs.fau.de/cms">Dokumentation</a></p><p><a href="http://blogs.fau.de/webworking">RRZE-Webworking</a></p><p><a href="https://github.com/RRZE-Webteam">RRZE-Webteam in Github</a></p>', CMS_WORKFLOW_TEXTDOMAIN),
            'contextual_help' => array(
                '1' => array(
                    'screen_id' => array('dashboard'),
                    'help_tab' => array(
                        'id' => 'workflow-dashboard-context',
                        'title' => __('Workflow', CMS_WORKFLOW_TEXTDOMAIN),
                        'content' => implode(PHP_EOL, $context_help_tab),
                    )
                )
            ),
        );

        $this->module = $cms_workflow->register_module('dashboard', $args);
    }

    public function init() {
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('wp_dashboard_setup', array($this, 'dashboard_setup'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function admin_enqueue_scripts() {
        wp_enqueue_style('workflow-dashboard', $this->module_url . 'dashboard.css', false, CMS_WORKFLOW_VERSION, 'all');
    }

    public function dashboard_setup() {
        remove_meta_box('dashboard_incoming_links', 'dashboard', 'normal');

        remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
        remove_meta_box('dashboard_recent_drafts', 'dashboard', 'side');

        remove_meta_box('dashboard_plugins', 'dashboard', 'normal');

        if ($this->module->options->right_now) {
            remove_meta_box('dashboard_right_now', 'dashboard', 'normal');
            add_meta_box('dashboard-right-now', __('Auf einen Blick', CMS_WORKFLOW_TEXTDOMAIN), array($this, 'right_now'), 'dashboard', 'normal', 'high');

            remove_action('activity_box_end', 'wp_dashboard_quota');
            add_action('activity_box_end', array($this, 'dashboard_quota'));
        }

        $all_post_types = $this->get_available_post_types();
        foreach ($all_post_types as $key => $post_type) {
            if (current_user_can($post_type->cap->edit_posts)) {
                $this->allowed_post_types[$key] = $post_type;
            }
        }

        if (empty($this->allowed_post_types)) {
            return;
        }

        if ($this->module->options->recent_drafts_widget) {
            wp_add_dashboard_widget('workflow-recent-drafts', __('Aktuelle Entwürfe', CMS_WORKFLOW_TEXTDOMAIN), array($this, 'recent_drafts_widget'));
        }

        if ($this->module->options->recent_pending_widget) {
            wp_add_dashboard_widget('workflow-pending-drafts', __('Aktuelle ausstehende Reviews', CMS_WORKFLOW_TEXTDOMAIN), array($this, 'recent_pending_widget'));
        }

        if ($this->module_activated('task_list') && $this->module->options->task_list_widget) {
            wp_add_dashboard_widget('workflow-task-list', __('Aufgabenliste', CMS_WORKFLOW_TEXTDOMAIN), array($this, 'task_list_widget'));
        }
    }

    public function right_now() {
        $theme = wp_get_theme();
        if (current_user_can('switch_themes')) {
            $theme_name = sprintf('<a href="themes.php">%1$s</a>', $theme->display('Name'));
        }
        
        else {
            $theme_name = $theme->display('Name');
        }
        ?>
        <div class="main">
            <ul>
        <?php
        do_action('rightnow_list_start');
        $post_types = get_post_types(array('show_in_nav_menus' => true), 'objects');
        $post_types = (array) apply_filters('rightnow_post_types', $post_types);
        foreach ($post_types as $post_type => $post_type_obj) {
            $num_posts = wp_count_posts($post_type);
            if ($num_posts) {
                printf('<li class="%1$s-count"><a href="edit.php?post_type=%1$s">%2$s %3$s</a></li>', $post_type, number_format_i18n($num_posts->publish), $post_type_obj->label);
            }
        }
        $num_comm = $this->count_comments();
        if ($num_comm) {
            $text = _n('Kommentar', 'Kommentare', $num_comm->total_comments, CMS_WORKFLOW_TEXTDOMAIN);
            printf('<li class="comment-count"><a href="edit-comments.php?comment_type=comment">%1$s %2$s</a></li>', number_format_i18n($num_comm->total_comments), $text);
            if ($num_comm->moderated && current_user_can('moderate_comments')) {
                $text = _n('Offen', 'Offen', $num_comm->moderated);
                printf('<li class="comment-mod-count"><a href="edit-comments.php?comment_status=moderated">%1$s %2$s</a></li>', number_format_i18n($num_comm->moderated), $text);
            }
        }
        do_action('rightnow_list_end');
        ?>
            </ul>
            <p><?php printf(__('<b>WordPress Version</b> %1$s', CMS_WORKFLOW_TEXTDOMAIN), get_bloginfo('version', 'display')); ?></p>
            <p><?php printf(__('<b>Theme</b> %1$s</b>', CMS_WORKFLOW_TEXTDOMAIN), $theme_name); ?></p>
                <?php
                if (!is_network_admin() && !is_user_admin() && current_user_can('manage_options') && '1' != get_option('blog_public')) {
                    $title = apply_filters('privacy_on_link_title', __('Suchmaschinen werden angehalten, den Inhalt der Website nicht zu indexieren', CMS_WORKFLOW_TEXTDOMAIN));
                    $content = apply_filters('privacy_on_link_text', __('Suchmaschinen blockiert', CMS_WORKFLOW_TEXTDOMAIN));

                    echo "<p><a href='options-reading.php' title='$title'>$content</a></p>";
                }
                ?>
        </div>
        <?php
        ob_start();
        do_action('rightnow_end');
        do_action('activity_box_end');
        $actions = ob_get_clean();

        if (!empty($actions)) {
            echo $actions;
        }
    }

    public function dashboard_quota() {
        if (!is_multisite() || !current_user_can('upload_files') || get_site_option('upload_space_check_disabled')) {
            return true;
        }

        $quota = get_space_allowed();
        $used = get_space_used();

        if ($used > $quota) {
            $percentused = '100';
        }
        
        else {
            $percentused = ( $used / $quota ) * 100;
        }
        
        $used_class = ( $percentused >= 70 ) ? ' warning' : '';
        $used = round($used, 2);
        $percentused = number_format($percentused);
        ?>
        <div class="sub">
            <h4 class="mu-storage"><?php _e('Speicherplatz', CMS_WORKFLOW_TEXTDOMAIN); ?></h4>
            <div class="mu-storage">
                <ul>
                    <li class="storage-count">
                    <?php printf('<a href="%1$s" title="%3$s">%2$sMB %4$s</a>', esc_url(admin_url('upload.php')), number_format_i18n($quota), __('Uploads verwalten', CMS_WORKFLOW_TEXTDOMAIN), __('Speicherplatz erlaubt', CMS_WORKFLOW_TEXTDOMAIN)); ?>
                    </li>
                    <li class="storage-count <?php echo $used_class; ?>">
                    <?php printf('<a href="%1$s" title="%4$s" class="musublink">%2$sMB (%3$s%%) %5$s</a>', esc_url(admin_url('upload.php')), number_format_i18n($used, 2), $percentused, __('Uploads verwalten', CMS_WORKFLOW_TEXTDOMAIN), __('Speicherplatz verbraucht', CMS_WORKFLOW_TEXTDOMAIN)); ?>
                    </li>
                </ul>
            </div>
        </div>
        <?php
    }

    private function count_comments($comment_type = '') {
        global $wpdb;

        $where = $wpdb->prepare("WHERE comment_type = %s", $comment_type);

        $count = $wpdb->get_results("SELECT comment_approved, COUNT( * ) AS num_comments FROM {$wpdb->comments} {$where} GROUP BY comment_approved", ARRAY_A);

        $total = 0;
        $approved = array('0' => 'moderated', '1' => 'approved', 'spam' => 'spam', 'trash' => 'trash', 'post-trashed' => 'post-trashed');
        foreach ((array) $count as $row) {
            if ('post-trashed' != $row['comment_approved'] && 'trash' != $row['comment_approved']) {
                $total += $row['num_comments'];
            }
            
            if (isset($approved[$row['comment_approved']])) {
                $stats[$approved[$row['comment_approved']]] = $row['num_comments'];
            }
        }

        $stats['total_comments'] = $total;
        foreach ($approved as $key) {
            if (empty($stats[$key])) {
                $stats[$key] = 0;
            }
        }

        return (object) $stats;
    }

    public function recent_drafts_widget($posts = false) {
        if (!$posts) {
            $posts_query = new WP_Query(
                array(
                    'post_type' => array_keys($this->allowed_post_types),
                    'post_status' => 'draft',
                    'posts_per_page' => 50,
                    'orderby' => 'modified',
                    'order' => 'DESC'
                )
            );

            $posts = & $posts_query->posts;
        }

        $current_user = wp_get_current_user();

        if ($posts && is_array($posts)) {
            $list = array();
            foreach ($posts as $post) {
                $post_type = $this->allowed_post_types[$post->post_type];

                $authors = array();

                if ($this->module_activated('authors')) {
                    $authors = Workflow_Authors::get_authors($post->ID, 'id');
                }

                $authors[$post->post_author] = $post->post_author;
                $authors = array_unique($authors);

                if (!current_user_can('manage_categories') && !in_array($current_user->ID, $authors)) {
                    continue;
                }

                $url = get_edit_post_link($post->ID);
                $title = _draft_or_post_title($post->ID);
                $last_id = get_post_meta($post->ID, '_edit_last', true);
                if ($last_id) {
                    $last_modified = esc_html(get_userdata($last_id)->display_name);
                }

                $item = sprintf('<h4><a href="%1$s">%2$s</a><abbr> (%3$s)</abbr></h4>', $url, esc_html($title), $post_type->labels->singular_name);
                if (isset($last_modified)) {
                    $item .= sprintf('<abbr>' . __('Zuletzt geändert von <i>%1$s</i> am %2$s um %3$s Uhr', CMS_WORKFLOW_TEXTDOMAIN) . '</abbr>', $last_modified, mysql2date(get_option('date_format'), $post->post_modified), mysql2date(get_option('time_format'), $post->post_modified));
                }

                $the_content = preg_split('#\s#', strip_shortcodes(strip_tags($post->post_content), 11, PREG_SPLIT_NO_EMPTY));
                if ($the_content) {
                    $item .= '<p>' . join(' ', array_slice($the_content, 0, 10)) . ( 10 < count($the_content) ? '&hellip;' : '' ) . '</p>';
                }
                
                $list[] = $item;
            }
            ?>
            <ul class="status-draft">
                <li><?php echo implode("</li>\n<li>", $list); ?></li>
            </ul>
            <?php
        } else {
            _e('Zurzeit gibt es keine Entwürfe.', CMS_WORKFLOW_TEXTDOMAIN);
        }
    }

    public function recent_pending_widget($posts = false) {
        if (!$posts) {
            $posts_query = new WP_Query(
                array(
                    'post_type' => array_keys($this->allowed_post_types),
                    'post_status' => 'pending',
                    'posts_per_page' => 50,
                    'orderby' => 'modified',
                    'order' => 'DESC'
                )
            );

            $posts = & $posts_query->posts;
        }

        $current_user = wp_get_current_user();

        if ($posts && is_array($posts)) {
            $list = array();
            foreach ($posts as $post) {
                $post_type = $this->allowed_post_types[$post->post_type];

                $authors = array();

                if ($this->module_activated('authors')) {
                    $authors = Workflow_Authors::get_authors($post->ID, 'id');
                }

                $authors[$post->post_author] = $post->post_author;
                $authors = array_unique($authors);

                if (!current_user_can('manage_categories') && !in_array($current_user->ID, $authors)) {
                    continue;
                }

                $url = get_edit_post_link($post->ID);
                $title = _draft_or_post_title($post->ID);
                $last_id = get_post_meta($post->ID, '_edit_last', true);
                
                if ($last_id) {
                    $last_modified = esc_html(get_userdata($last_id)->display_name);
                }

                $item = sprintf('<h4><a href="%1$s">%2$s</a><abbr> (%3$s)</abbr></h4>', $url, esc_html($title), $post_type->labels->singular_name);
                if (isset($last_modified)) {
                    $item .= sprintf('<abbr>' . __('Zuletzt geändert von <i>%1$s</i> am %2$s um %3$s Uhr', CMS_WORKFLOW_TEXTDOMAIN) . '</abbr>', $last_modified, mysql2date(get_option('date_format'), $post->post_modified), mysql2date(get_option('time_format'), $post->post_modified));
                }

                $the_content = preg_split('#\s#', strip_shortcodes(strip_tags($post->post_content), 11, PREG_SPLIT_NO_EMPTY));
                if ($the_content) {
                    $item .= '<p>' . join(' ', array_slice($the_content, 0, 10)) . ( 10 < count($the_content) ? '&hellip;' : '' ) . '</p>';
                }
                
                $list[] = $item;
            }
            ?>
            <ul class="status-pending">
                <li><?php echo implode("</li>\n<li>", $list); ?></li>
            </ul>
            <?php
        } else {
            _e('Zurzeit gibt es keine ausstehenden Reviews.', CMS_WORKFLOW_TEXTDOMAIN);
        }
    }

    public function task_list_widget($posts = false) {
        if (!$posts) {
            $posts_query = new WP_Query(
                array(
                    'post_type' => array_keys($this->allowed_post_types),
                    'meta_key' => Workflow_Task_List::postmeta_key,
                    'post_status' => array('pending', 'draft'),
                    'posts_per_page' => 50
                )
            );

            $posts = & $posts_query->posts;
        }

        $current_user = wp_get_current_user();

        if ($posts && is_array($posts)) {
            $tasks = $this->task_list_order($posts);

            $list = array();

            foreach ($tasks as $value) {

                $task = (object) $value['task'];

                foreach ($posts as $post) {
                    if ($post->ID != $value['post_id']) {
                        continue;
                    }

                    $post_type = $this->allowed_post_types[$post->post_type];

                    $authors = array();

                    if ($this->module_activated('authors')) {
                        $authors = Workflow_Authors::get_authors($post->ID, 'id');
                    }

                    $authors[$post->post_author] = $post->post_author;
                    $authors = array_unique($authors);

                    if (!current_user_can('manage_categories') && !in_array($current_user->ID, $authors)) {
                        continue;
                    }

                    $url = get_edit_post_link($post->ID);
                    $title = _draft_or_post_title($post->ID);

                    $task_adder = get_userdata($task->task_adder)->display_name;

                    $item = sprintf('<li class="priority-%s">', $task->task_priority);
                    $item .= sprintf('<h4><a href="%1$s">%2$s</a><abbr> (%3$s)</abbr></h4>', $url, esc_html($task->task_title), Workflow_Task_List::task_list_get_textual_priority($task->task_priority));
                    $item .= sprintf('<abbr>' . __('Aufgabe hinzugefügt von <i>%1$s</i> am %2$s um %3$s Uhr', CMS_WORKFLOW_TEXTDOMAIN) . '</abbr>', $task_adder, date_i18n(get_option('date_format'), $task->task_timestamp), date_i18n(get_option('time_format'), $task->task_timestamp));
                    $item .= sprintf('<p>%1$s: %2$s</p>', $post_type->labels->singular_name, esc_html($title));
                    $item .= '</li>';

                    $list[] = $item;
                }
            }
            ?>
            <ul>
            <?php echo implode("\n", $list); ?>
            </ul>
            <?php
        } else {
            _e('Zurzeit gibt es keine Aufgaben.', CMS_WORKFLOW_TEXTDOMAIN);
        }
    }

    private function task_list_order(&$posts) {

        $priority = array();
        $timestamp = array();
        $task_id = array();
        $post_id = array();
        $task = array();

        foreach ($posts as $post) {

            $data = get_post_meta($post->ID, Workflow_Task_List::postmeta_key);
            $data = json_decode(json_encode($data), false);

            foreach ($data as $value) {
                if (empty($value->task_done)) {
                    $priority[] = $value->task_priority;
                    $timestamp[] = $value->task_timestamp;
                    $task_id[] = $value->task_id;
                    $post_id[$value->task_id] = $post->ID;
                    $task[$value->task_id] = $value;
                }
            }
        }

        array_multisort($priority, SORT_DESC, $timestamp, SORT_ASC, $task_id, SORT_ASC);

        $tasks = array();
        foreach ($task_id as $key => $value) {
            $tasks[$value] = array('priority' => $priority[$key], 'timestamp' => $timestamp[$key], 'post_id' => $post_id[$value], 'task' => $task[$value]);
        }

        return $tasks;
    }

    public function register_settings() {
        add_settings_section($this->module->workflow_options_name . '_general', false, '__return_false', $this->module->workflow_options_name);
        add_settings_field('recent_drafts_widget', __('Aktuelle Entwürfe', CMS_WORKFLOW_TEXTDOMAIN), array($this, 'settings_recent_drafts_option'), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general');
        add_settings_field('recent_pending_widget', __('Aktuelle ausstehende Reviews', CMS_WORKFLOW_TEXTDOMAIN), array($this, 'settings_recent_pending_option'), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general');

        if ($this->module_activated('task_list')) {
            add_settings_field('task_list_widget', __('Aufgabenliste', CMS_WORKFLOW_TEXTDOMAIN), array($this, 'settings_task_list_option'), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general');
        }
    }

    public function settings_recent_drafts_option() {
        $options = array(
            false => __('Deaktiviert', CMS_WORKFLOW_TEXTDOMAIN),
            true => __('Aktiviert', CMS_WORKFLOW_TEXTDOMAIN),
        );
        echo '<select id="recent_drafts_widget" name="' . $this->module->workflow_options_name . '[recent_drafts_widget]">';
        foreach ($options as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"';
            echo selected($this->module->options->recent_drafts_widget, $value);
            echo '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }

    public function settings_recent_pending_option() {
        $options = array(
            false => __('Deaktiviert', CMS_WORKFLOW_TEXTDOMAIN),
            true => __('Aktiviert', CMS_WORKFLOW_TEXTDOMAIN),
        );
        echo '<select id="recent_pending_widget" name="' . $this->module->workflow_options_name . '[recent_pending_widget]">';
        foreach ($options as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"';
            echo selected($this->module->options->recent_pending_widget, $value);
            echo '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }

    public function settings_task_list_option() {
        $options = array(
            false => __('Deaktiviert', CMS_WORKFLOW_TEXTDOMAIN),
            true => __('Aktiviert', CMS_WORKFLOW_TEXTDOMAIN),
        );
        echo '<select id="task_list_widget" name="' . $this->module->workflow_options_name . '[task_list_widget]">';
        foreach ($options as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"';
            echo selected($this->module->options->task_list_widget, $value);
            echo '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }

    public function settings_validate($new_options) {

        if (array_key_exists('recent_drafts_widget', $new_options) && !$new_options['recent_drafts_widget']) {
            $new_options['recent_drafts_widget'] = false;
        }

        if (array_key_exists('recent_pending_widget', $new_options) && !$new_options['recent_pending_widget']) {
            $new_options['recent_pending_widget'] = false;
        }

        if (array_key_exists('task_list_widget', $new_options) && !$new_options['task_list_widget']) {
            $new_options['task_list_widget'] = false;
        }

        return $new_options;
    }

    public function print_configure_view() {
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

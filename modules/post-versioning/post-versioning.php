<?php

class Workflow_Post_Versioning extends Workflow_Module {

    const source_post_id = '_source_post_id';
    const version_post_id = '_version_post_id';
    const version_remote_parent_post_meta = '_version_remote_parent_post_meta';
    const version_remote_post_meta = '_version_remote_post_meta';
    const site_connections = 'cms_workflow_site_connections';

    public $module;
    public $source_blog = null;

    public function __construct() {
        global $cms_workflow;

        $this->module_url = $this->get_module_url(__FILE__);

        $content_help_tab = array(
            '<p>' . __('Über die Versionierung können Sie einfach neue Versionen oder Kopien Ihrer Dokumente erstellen.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>' . __('Eine <strong>Kopie</strong> hat keinen Bezug mehr zum ursprünglichen Dokument:', CMS_WORKFLOW_TEXTDOMAIN) . '<br> ' . __('es wird ein neues Dokument mit gleichem Inhalt erstellt, das unabhängig vom ursprünglichen Dokument bearbeitet werden kann und unter einer neuen Adresse veröffentlicht wird.', CMS_WORKFLOW_TEXTDOMAIN) . '<br>',
            '<p>' . __('Eine <strong>neue Version</strong> hingegen steht mit dem Ursprungsdokument in Zusammenhang:', CMS_WORKFLOW_TEXTDOMAIN) . '<br>' . __('es wird wie bei der Kopie ein neues Dokument mit gleichem Inhalt erstellt, das unabhängig vom ursprünglichen Dokument bearbeitet und als Entwurf gespeichert werden kann. Mit dem Veröffentlichen wird jedoch das ursprüngliche Dokument überschrieben, die neue Version ist damit unter der Adresse des alten Dokumentes erreichbar.', CMS_WORKFLOW_TEXTDOMAIN) . '<br>' . __('Neue Versionen können nur von veröffentlichten Dokumenten erstellt werden.', CMS_WORKFLOW_TEXTDOMAIN) . '<br>' . __('Eine neue Version sollte erstellt werden, wenn ein bereits veröffentlichtes Dokument überarbeitet werden muss. Dadurch werden Änderungen an dem Dokument erst öffentlich sichtbar, wenn sie vollständig erledigt sind.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>' . __('Zudem kann für jedes veröffentlichte Dokument eine <strong>netzwerkweite Version</strong> erstellt werden:', CMS_WORKFLOW_TEXTDOMAIN) . '<br>' . __('es wird wie bei der Kopie oder der neuen Version ein neues Dokument mit gleichem Inhalt erstellt, allerdings nicht innerhalb des eigenen Webauftritts, sondern als Entwurf in einem anderen, meist anderssprachigen Webauftritt. Dort kann dann die Übersetzung vorgenommen und das Dokument veröffentlicht werden. Beide Dokumente können jetzt unabhängig voneinander bearbeitet und gespeichert werden, bleiben allerdings über den Link in der Spalte <i>Version</i> miteinander verbunden.', CMS_WORKFLOW_TEXTDOMAIN) . '<br>' . __('Wird ein bereits netzwerkweit versioniertes Dokument nochmals netzwerkweit gespeichert, wird im anderen Webauftritt eine neue Version als Entwurf erstellt, die dann übersetzt werden kann.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>' . __('Sie können auf dieser Seite angeben, für welche Bereiche die Erstellung von Kopien oder neuer Versionen freigegeben werden soll:', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>' . __('<strong>Lokale Freigabe</strong> - Kopien und neue Versionen innerhalb des gleichen Webauftritts erstellen.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>' . __('<strong>Netzwerkweite Freigabe</strong> - Neue Versionen in einem anderen, anderssprachigen Webauftritt erstellen.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>' . __('<strong>Vorhandene Webseiten</strong> - hier erscheinen alle Webauftritte, die netzwerkweite Freigaben enthalten. In diese können lokale Dokumente kopiert werden.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>'
        );

        $context_help_tab1 = array(
            '<p>' . __('Über die Versionierung können Sie einfach neue Versionen oder Kopien Ihrer Dokumente erstellen.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>' . __('Wenn Sie eine neue lokale Version eines Dokumentes bearbeiten, erhalten Sie den Hinweis, dass beim Veröffentlichen die Ursprungsversion überschrieben wird.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>' . __('Für jedes veröffentlichte Dokument kann auch eine <strong>netzwerkweite Version</strong> erstellt werden:', CMS_WORKFLOW_TEXTDOMAIN) . '<br>' . __('es wird wie ein neues Dokument mit gleichem Inhalt erstellt, allerdings nicht innerhalb des eigenen Webauftritts, sondern als Entwurf in einem anderen, meist anderssprachigen Webauftritt. Dort kann dann die Übersetzung vorgenommen und das Dokument veröffentlicht werden. ', CMS_WORKFLOW_TEXTDOMAIN) . '<br>' . __('Wird ein bereits netzwerkweit versioniertes Dokument nochmals netzwerkweit gespeichert, wird im anderen Webauftritt eine neue Version als Entwurf erstellt, die dann übersetzt werden kann.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>' . __('So erstellen Sie eine netzwerkweite Version eines Dokumentes:', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<ol>',
            '<li>' . __('Überprüfen Sie, ob im Kästchen <i>Netzwerkweite Versionierung</i> der Webauftritt ausgewählt ist, in dem eine neue Version erstellt werden soll', CMS_WORKFLOW_TEXTDOMAIN) . ' (' . __('wenn diese Box nicht erscheint, stellen Sie sicher, dass das Dokument bereits veröffentlicht ist bzw. lassen Sie sich diese über die Lasche <i>Optionen einblenden</i> in der rechten oberen Ecke anzeigen lassen', CMS_WORKFLOW_TEXTDOMAIN) . ').</li>',
            '<li>' . __('Aktivieren Sie im Kästchen <i>Veröffentlichen</i> die Schaltfläche <i>Netzwerkweite Versionierung</i>.', CMS_WORKFLOW_TEXTDOMAIN) . '</li>',
            '<li>' . __('Speichern Sie das Dokument durch <i>Aktualisieren</i>. Eine neue Version dieses Dokumentes wird im ausgewählten Webauftritt als Entwurf erstellt.', CMS_WORKFLOW_TEXTDOMAIN) . '</li>',
            '</ol>'
        );
        
        $context_help_tab2 = array(
            '<p>' . __('Über die Versionierung können Sie einfach neue Versionen oder Kopien Ihrer Dokumente erstellen.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>' . __('Eine <strong>Kopie</strong> hat keinen Bezug mehr zum ursprünglichen Dokument:', CMS_WORKFLOW_TEXTDOMAIN) . '<br> ' . __('es wird ein neues Dokument mit gleichem Inhalt erstellt, das unabhängig vom ursprünglichen Dokument bearbeitet werden kann und unter einer neuen Adresse veröffentlicht wird.', CMS_WORKFLOW_TEXTDOMAIN) . '<br>',
            '<p>' . __('Eine <strong>neue Version</strong> hingegen steht mit dem Ursprungsdokument in Zusammenhang:', CMS_WORKFLOW_TEXTDOMAIN) . '<br>' . __('es wird wie bei der Kopie ein neues Dokument mit gleichem Inhalt erstellt, das unabhängig vom ursprünglichen Dokument bearbeitet und als Entwurf gespeichert werden kann. Mit dem Veröffentlichen wird jedoch das ursprüngliche Dokument überschrieben, die neue Version ist damit unter der Adresse des alten Dokumentes erreichbar.', CMS_WORKFLOW_TEXTDOMAIN) . '<br>' . __('Neue Versionen können nur von veröffentlichten Dokumenten erstellt werden.', CMS_WORKFLOW_TEXTDOMAIN) . '<br>' . __('Eine neue Version sollte erstellt werden, wenn ein bereits veröffentlichtes Dokument überarbeitet werden muss. Dadurch werden Änderungen an dem Dokument erst öffentlich sichtbar, wenn sie vollständig erledigt sind.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>' . __('Zudem kann für jedes veröffentlichte Dokument eine <strong>netzwerkweite Version</strong> erstellt werden:', CMS_WORKFLOW_TEXTDOMAIN) . '<br>' . __('es wird wie bei der Kopie oder der neuen Version ein neues Dokument mit gleichem Inhalt erstellt, allerdings nicht innerhalb des eigenen Webauftritts, sondern als Entwurf in einem anderen, meist anderssprachigen Webauftritt. Dort kann dann die Übersetzung vorgenommen und das Dokument veröffentlicht werden. Beide Dokumente können jetzt unabhängig voneinander bearbeitet und gespeichert werden, bleiben allerdings über den Link in der Spalte <i>Version</i> miteinander verbunden.', CMS_WORKFLOW_TEXTDOMAIN) . '<br>' . __('Wird ein bereits netzwerkweit versioniertes Dokument nochmals netzwerkweit gespeichert, wird auch im anderen Webauftritt eine neue Version als Entwurf erstellt, die dann übersetzt werden kann.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<hr>',
            '<p>' . __('In der Spalte <i>Version</i> sehen Sie folgende Informationen:', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>' . __('<strong>Lokale Kopie</strong> (innerhalb des eigenen Webauftrittes) - kein Eintrag.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>' . __('<strong>Neue Version</strong> (innerhalb des eigenen Webauftrittes) - verlinkter Name des Quelldokumentes, Sprache des Webauftritts.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>' . __('<strong>Netzwerkweite Versionierung</strong> (zu einem anderen Webauftritt) - Pfeil (nach rechts im Quell-Webauftritt oder nach links im Ziel-Webauftritt), verlinkter Name des Zieldokumentes, Sprache des Ziel-Webauftritts.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>' . __('Außerdem werden die Dokumente am Anfang der Zeile je nach Veröffentlichungs- und Sichtbarkeitsstatus in unterschiedlichen Farben angezeigt:', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p class="cms-status-draft">' . __('<strong>Entwurf</strong> - Dokument ist noch nicht vollständig bearbeitet.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p class="cms-status-pending">' . __('<strong>Ausstehender Review</strong> - Dokument wurde vom Autor fertig bearbeitet und liegt zur Revision beim Redakteur vor.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p class="cms-status-publish">' . __('<strong>Veröffentlicht</strong> - Dokument wurde vom Redakteur veröffentlicht, wird nicht farblich hervorgehoben.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p class="cms-status-future">' . __('<strong>Geplant</strong> - Dokument wurde vom Redakteur mit zukünftigem Datum veröffentlicht.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p class="cms-status-private">' . __('<strong>Privat</strong> - private Dokumente können nicht im Entwurf vorliegen, sondern werden gleich veröffentlicht, sind aber nicht öffentlich sichtbar.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p class="cms-post-password-required">' . __('<strong>Passwortgeschützt</strong> - passwortgeschützte Dokumente können auch Entwürfe sein, farblich überschreibt der Passwortschutz den Entwurfsstatus.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p class="cms-status-trash">' . __('<strong>Papierkorb</strong> - gelöschte Dokumente befinden sich im Papierkorb.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<hr>',
            '<p>' . __('So erstellen Sie eine Kopie eines Dokumentes:', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<ol>',
            '<li>' . __('Fahren Sie mit der Maus in der Liste über das Dokument, das Sie kopieren wollen.', CMS_WORKFLOW_TEXTDOMAIN) . '</li>',
            '<li>' . __('Wählen Sie <i>Kopieren</i>. Eine Kopie des Dokumentes wird als Entwurf geöffnet.', CMS_WORKFLOW_TEXTDOMAIN) . '</li>',
            '<li>' . __('Ändern Sie das Dokument und speichern oder veröffentlichen Sie dieses.', CMS_WORKFLOW_TEXTDOMAIN) . '</li>',
            '</ol>',
            '<p>' . __('So erstellen Sie eine neue Version eines Dokumentes:', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<ol>',
            '<li>' . __('Fahren Sie in der Liste mit der Maus über das Dokument, von dem Sie eine neue Version erstellen wollen. Das Dokument muss bereits veröffentlicht sein.', CMS_WORKFLOW_TEXTDOMAIN) . '</li>',
            '<li>' . __('Wählen Sie <i>Neue Version</i>. Eine neue Version des Dokumentes wird als Entwurf geöffnet.', CMS_WORKFLOW_TEXTDOMAIN) . '</li>',
            '<li>' . __('Ändern Sie das Dokument und speichern Sie dieses.', CMS_WORKFLOW_TEXTDOMAIN) . '</li>',
            '<li>' . __('Beim Veröffentlichen wird das ursprüngliche Dokument durch die neue Version ersetzt.', CMS_WORKFLOW_TEXTDOMAIN) . '</li>',
            '</ol>'
        );
        
        $context_help_tab3 = array(
            '<p>' . __('Mit der Freigabe der Versionierung sehen Sie auf den entsprechenden Übersichtsseiten aller Dokumente die zusätzliche Spalte <i>Version</i> mit folgenden Informationen:', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>' . __('<strong>Lokale Kopie</strong> (innerhalb des eigenen Webauftrittes) - kein Eintrag.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>' . __('<strong>Neue Version</strong> (innerhalb des eigenen Webauftrittes) - verlinkter Name des Quelldokumentes, Sprache des Webauftritts.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>' . __('<strong>Netzwerkweite Versionierung</strong> (zu einem anderen Webauftritt) - Pfeil (nach rechts im Quell-Webauftritt oder nach links im Ziel-Webauftritt), verlinkter Name des Zieldokumentes, Sprache des Ziel-Webauftritts.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>' . __('Außerdem werden die Dokumente am Anfang der Zeile je nach Veröffentlichungs- und Sichtbarkeitsstatus in unterschiedlichen Farben angezeigt:', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p class="cms-status-draft">' . __('<strong>Entwurf</strong> - Dokument ist noch nicht vollständig bearbeitet.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p class="cms-status-pending">' . __('<strong>Ausstehender Review</strong> - Dokument wurde vom Autor fertig bearbeitet und liegt zur Revision beim Redakteur vor.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p class="cms-status-publish">' . __('<strong>Veröffentlicht</strong> - Dokument wurde vom Redakteur veröffentlicht, wird nicht farblich hervorgehoben.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p class="cms-status-future">' . __('<strong>Geplant</strong> - Dokument wurde vom Redakteur mit zukünftigem Datum veröffentlicht.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p class="cms-status-private">' . __('<strong>Privat</strong> - private Dokumente können nicht im Entwurf vorliegen, sondern werden gleich veröffentlicht, sind aber nicht öffentlich sichtbar.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p class="cms-post-password-required">' . __('<strong>Passwortgeschützt</strong> - passwortgeschützte Dokumente können auch Entwürfe sein, farblich überschreibt der Passwortschutz den Entwurfsstatus.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p class="cms-status-trash">' . __('<strong>Papierkorb</strong> - gelöschte Dokumente befinden sich im Papierkorb.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<p>' . __('So erstellen Sie eine Kopie eines Dokumentes:', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<ol>',
            '<li>' . __('Gehen Sie in einem freigegebenen Bereich auf die Übersichtsseite über alle Dokumente.', CMS_WORKFLOW_TEXTDOMAIN) . '</li>',
            '<li>' . __('Fahren Sie mit der Maus in der Liste über das Dokument, das Sie kopieren wollen.', CMS_WORKFLOW_TEXTDOMAIN) . '</li>',
            '<li>' . __('Wählen Sie <i>Kopieren</i>. Eine Kopie des Dokumentes wird als Entwurf geöffnet.', CMS_WORKFLOW_TEXTDOMAIN) . '</li>',
            '<li>' . __('Ändern Sie das Dokument und speichern oder veröffentlichen Sie dieses.', CMS_WORKFLOW_TEXTDOMAIN) . '</li>',
            '</ol>',
            '<p>' . __('So erstellen Sie eine neue Version eines Dokumentes:', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<ol>',
            '<li>' . __('Gehen Sie in einem freigegebenen Bereich auf die Übersichtsseite über alle Dokumente.', CMS_WORKFLOW_TEXTDOMAIN) . '</li>',
            '<li>' . __('Fahren Sie in der Liste mit der Maus über das Dokument, von dem Sie eine neue Version erstellen wollen. Das Dokument muss bereits veröffentlicht sein.', CMS_WORKFLOW_TEXTDOMAIN) . '</li>',
            '<li>' . __('Wählen Sie <i>Neue Version</i>. Eine neue Version des Dokumentes wird als Entwurf geöffnet.', CMS_WORKFLOW_TEXTDOMAIN) . '</li>',
            '<li>' . __('Ändern Sie das Dokument und speichern Sie dieses.', CMS_WORKFLOW_TEXTDOMAIN) . '</li>',
            '<li>' . __('Beim Veröffentlichen wird das ursprüngliche Dokument durch die neue Version ersetzt.', CMS_WORKFLOW_TEXTDOMAIN) . '</li>',
            '</ol>',
            '<p>' . __('So erstellen Sie eine netzwerkweite Version eines Dokumentes:', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
            '<ol>',
            '<li>' . __('Gehen Sie auf ein bereits veröffentlichtes Dokument in einem freigegebenen Bereich.', CMS_WORKFLOW_TEXTDOMAIN) . '</li>',
            '<li>' . __('Überprüfen Sie, ob im Kästchen <i>Netzwerkweite Versionierung</i> der Webauftritt ausgewählt ist, in dem eine neue Version erstellt werden soll', CMS_WORKFLOW_TEXTDOMAIN) . ' (' . __('wenn diese Box nicht erscheint, können Sie sie über die Lasche <i>Optionen einblenden</i> in der rechten oberen Ecke anzeigen lassen', CMS_WORKFLOW_TEXTDOMAIN) . ').</li>',
            '<li>' . __('Aktivieren Sie im Kästchen <i>Veröffentlichen</i> die Schaltfläche <i>Netzwerkweite Versionierung</i>.', CMS_WORKFLOW_TEXTDOMAIN) . '</li>',
            '<li>' . __('Speichern Sie das Dokument durch <i>Aktualisieren</i>. Eine neue Version dieses Dokumentes wird im ausgewählten Webauftritt als Entwurf erstellt.', CMS_WORKFLOW_TEXTDOMAIN) . '</li>',
            '</ol>'
        );

        $args = array(
            'title' => __('Versionierung', CMS_WORKFLOW_TEXTDOMAIN),
            'description' => __('Neue Version bzw. eine Kopie aus einem vorhandenen Dokument erstellen.', CMS_WORKFLOW_TEXTDOMAIN),
            'module_url' => $this->module_url,
            'slug' => 'post-versioning',
            'default_options' => array(
                'post_types' => array(
                    'post' => true,
                    'page' => true
                ),
                'network_posts_types' => array(),
                'network_connections' => array()
            ),
            'configure_callback' => 'print_configure_view',
            'settings_help_tab' => array(
                'id' => 'workflow-post-versioning-overview',
                'title' => __('Übersicht', CMS_WORKFLOW_TEXTDOMAIN),
                'content' => implode(PHP_EOL, $content_help_tab),
            ),
            'settings_help_sidebar' => __('<p><strong>Für mehr Information:</strong></p><p><a href="http://blogs.fau.de/cms">Dokumentation</a></p><p><a href="http://blogs.fau.de/webworking">RRZE-Webworking</a></p><p><a href="https://github.com/RRZE-Webteam">RRZE-Webteam in Github</a></p>', CMS_WORKFLOW_TEXTDOMAIN),
            'contextual_help' => array(
                '1' => array(
                    'screen_id' => array('post', 'page'),
                    'help_tab' => array(
                        'id' => 'workflow-post-versioning-context',
                        'title' => __('Versionierung', CMS_WORKFLOW_TEXTDOMAIN),
                        'content' => implode(PHP_EOL, $context_help_tab1),
                    )
                ),
                '2' => array(
                    'screen_id' => array('edit-post', 'edit-page'),
                    'help_tab' => array(
                        'id' => 'workflow-post-versioning-network-context',
                        'title' => __('Versionierung', CMS_WORKFLOW_TEXTDOMAIN),
                        'content' => implode(PHP_EOL, $context_help_tab2),
                    )
                ),
                '3' => array(
                    'screen_id' => array('workflow_page_workflow-post-versioning-settings'),
                    'help_tab' => array(
                        'id' => 'workflow-post-versioning-manage-context',
                        'title' => __('Versionen verwalten', CMS_WORKFLOW_TEXTDOMAIN),
                        'content' => implode(PHP_EOL, $context_help_tab3),
                    )
                )
            ),
        );

        $this->module = $cms_workflow->register_module('post_versioning', $args);
    }

    public function init() {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));

        add_action('admin_action_copy_as_new_post_draft', array($this, 'copy_as_new_post_draft'));
        add_action('admin_action_version_as_new_post_draft', array($this, 'version_as_new_post_draft'));
        add_action('admin_notices', array($this, 'admin_notices'));

        $allowed_post_types = $this->get_post_types($this->module);

        foreach ($allowed_post_types as $post_type) {
            add_action('publish_' . $post_type, array($this, 'version_save_post'), 999, 2);

            $filter_row_actions = is_post_type_hierarchical($post_type) ? 'page_row_actions' : 'post_row_actions';
            add_filter($filter_row_actions, array($this, 'filter_post_row_actions'), 10, 2);

            add_filter("manage_edit-{$post_type}_columns", array($this, 'custom_columns'));
            add_action("manage_{$post_type}_posts_custom_column", array($this, 'posts_custom_column'), 10, 2);
            add_filter("manage_edit-{$post_type}_sortable_columns", array($this, 'posts_sortable_columns'));

            add_filter('posts_where', array($this, 'posts_where'));
        }

        $post_type = $this->get_current_post_type();

        if (is_multisite() && $this->is_post_type_enabled($post_type)) {
            add_action('add_meta_boxes', array($this, 'network_connections_meta_box'), 10, 2);
            add_action('save_post', array($this, 'network_connections_save_postmeta'));
            add_action('save_post', array($this, 'network_connections_save_post'));
        }
    }

    public function deactivation() {
        global $cms_workflow;

        $connections = get_site_option(self::site_connections, array());

        $current_blog_id = get_current_blog_id();

        if (isset($connections[$current_blog_id])) {
            unset($connections[$current_blog_id]);
        }

        update_site_option(self::site_connections, $connections);

        $cms_workflow->update_module_option($this->module->name, 'network_posts_types', array());
        $cms_workflow->update_module_option($this->module->name, 'network_connections', array());
    }

    public function admin_enqueue_scripts() {
        wp_enqueue_style('workflow-post-versioning', $this->module_url . 'post-versioning.css', false, CMS_WORKFLOW_VERSION, 'all');
    }

    public function register_settings() {
        add_settings_section($this->module->workflow_options_name . '_general', false, '__return_false', $this->module->workflow_options_name);

        if (is_multisite()) {
            add_settings_field('post_types', __('Lokale Freigabe', CMS_WORKFLOW_TEXTDOMAIN), array($this, 'settings_post_types_option'), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general');

            add_settings_field('network_posts_types', __('Netzwerkweite Freigabe', CMS_WORKFLOW_TEXTDOMAIN), array($this, 'settings_network_posts_types_option'), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general');

            $connections = get_site_option(self::site_connections, array());

            $current_blog_id = get_current_blog_id();

            if (isset($connections[$current_blog_id])) {
                unset($connections[$current_blog_id]);
            }

            if (!empty($connections)) {
                add_settings_field('network_connections', __('Vorhandene Webseiten', CMS_WORKFLOW_TEXTDOMAIN), array($this, 'settings_network_connections_option'), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general');
            }
        }

        else {
            add_settings_field('post_types', __('Freigabe', CMS_WORKFLOW_TEXTDOMAIN), array($this, 'settings_post_types_option'), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general');
        }
    }

    public function settings_post_types_option() {
        global $cms_workflow;
        $cms_workflow->settings->custom_post_type_option($this->module);
    }

    public function settings_network_posts_types_option() {
        global $cms_workflow;
        $cms_workflow->settings->custom_post_type_option($this->module, 'network_posts_types');
    }

    public function settings_network_connections_option() {
        $connections = get_site_option(self::site_connections, array());

        $current_blog_id = get_current_blog_id();

        if (isset($connections[$current_blog_id])) {
            unset($connections[$current_blog_id]);
        }

        if (empty($connections)) {
            return;
        }

        foreach ($connections as $blog_id => $data) {
            if ($current_blog_id == $blog_id) {
                continue;
            }

            if (!switch_to_blog($blog_id)) {
                continue;
            }

            $blog_name = get_bloginfo('name');
            restore_current_blog();

            $connected = is_array($this->module->options->network_connections) && in_array($blog_id, $this->module->options->network_connections) ? true : false;
            ?>
            <label for="network_connections_<?php echo $blog_id; ?>">
                <input id="network_connections_<?php echo $blog_id; ?>" type="checkbox" <?php checked($connected, true); ?> name="<?php printf('%s[network_connections][]', $this->module->workflow_options_name); ?>" value="<?php echo $blog_id ?>" /> <?php echo $blog_name; ?>
            </label><br>
            <?php
        }
        ?>
        <p class="description"><?php _e('Lokale Dokumente können in diesen Webseiten als neue Version (Entwurf) dupliziert werden.'); ?></p>
        <?php
    }

    public function settings_validate($new_options) {
        $current_blog_id = get_current_blog_id();

        if (!isset($new_options['post_types'])) {
            $new_options['post_types'] = array();
        } 
        
        else {
            $new_options['post_types'] = $this->clean_post_type_options($new_options['post_types'], $this->module->post_type_support);
        }

        if (is_multisite()) {

            if (!isset($new_options['network_posts_types'])) {
                $new_options['network_posts_types'] = array();
            }

            if (!isset($new_options['network_connections'])) {
                $new_options['network_connections'] = array();
            }

            $new_options['network_posts_types'] = $this->clean_post_type_options($new_options['network_posts_types'], $this->module->post_type_support);

            $connections = get_site_option(self::site_connections);

            if (!$connections) {
                $connections = array();
            }

            $current_connections = $this->module->options->network_connections;

            if (!is_array($current_connections)) {
                $current_connections = array();
            }

            $new_connections = isset($new_options['network_connections']) ? $new_options['network_connections'] : array();

            foreach ($connections as $blog_id => $blog_data) {
                if ($current_blog_id == $blog_id) {
                    continue;
                }

                $blog_details = get_blog_details($blog_id);
                if (empty($blog_details)) {
                    continue;
                }

                $key = array_search($blog_id, $current_connections);

                if (in_array($blog_id, $new_connections)) {
                    if ($key === false) {
                        $current_connections[] = $blog_id;
                    }
                }
                
                else {
                    if ($key !== false && isset($current_connections[$key])) {
                        unset($current_connections[$key]);
                    }
                }
            }

            $new_options['network_connections'] = $current_connections;

            if (isset($connections[$current_blog_id])) {
                unset($connections[$current_blog_id]);
            }

            if (array_search(true, $new_options['network_posts_types']) !== false) {
                foreach ($new_options['network_posts_types'] as $key => $value) {
                    $connections[$current_blog_id][$key] = $value;
                }
            }

            update_site_option(self::site_connections, $connections);

            $this->update_site_connections();
        }

        return $new_options;
    }

    private function update_site_connections() {
        $connections = get_site_option(self::site_connections);

        if (!$connections) {
            $connections = array();
        }

        $cleanup_blogs = array();

        foreach ($connections as $blog_id => $blog_data) {

            $blog_details = get_blog_details($blog_id);
            if (empty($blog_details)) {
                $cleanup_blogs[] = $blog_id;
            }
        }

        if (count($cleanup_blogs) > 0) {

            foreach ($connections as $blog_id => $blog_data) {

                foreach ($cleanup_blogs as $blog_to_clean) {

                    $blog_options = get_blog_option($blog_id, $this->module->workflow_options_name . '_options');
                    $current_connections = $blog_options ? $blog_options->network_connections : array();

                    if (count($current_connections) > 1) {
                        $key = array_search($blog_to_clean, $current_connections);

                        if ($key !== false && isset($current_connections[$key])) {
                            unset($current_connections[$key]);
                        }

                        $blog_options->network_connections = $current_connections;
                        update_blog_option($blog_id, $this->module->workflow_options_name . '_options', $blog_options);
                    }
                }
            }

            foreach ($cleanup_blogs as $blog_to_clean) {
                if (array_key_exists($blog_to_clean, $connections)) {
                    unset($connections[$blog_to_clean]);
                }
            }

            update_site_option(self::site_connections, $connections);
        }
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

    public function filter_post_row_actions($actions, $post) {
        if (!is_object($this->get_available_post_types($post->post_type)) || !in_array($post->post_type, $this->get_post_types($this->module))) {
            return $actions;
        }

        $cap = $this->get_available_post_types($post->post_type)->cap;

        if (current_user_can($cap->edit_posts) && !get_post_meta($post->ID, self::version_post_id, true) && $post->post_status != 'trash') {
            $actions['edit_as_new_draft'] = '<a href="' . admin_url('admin.php?action=copy_as_new_post_draft&amp;post=' . $post->ID) . '" title="'
                    . esc_attr(__('Dieses Element als neuen Entwurf kopieren', CMS_WORKFLOW_TEXTDOMAIN))
                    . '">' . __('Kopieren', CMS_WORKFLOW_TEXTDOMAIN) . '</a>';
        }
        
        if (current_user_can($cap->edit_posts) && $post->post_status == 'publish') {
            $actions['edit_as_version'] = '<a href="' . admin_url('admin.php?action=version_as_new_post_draft&amp;post=' . $post->ID) . '" title="'
                    . esc_attr(__('Dieses Element als neue Version duplizieren', CMS_WORKFLOW_TEXTDOMAIN))
                    . '">' . __('Neue Version', CMS_WORKFLOW_TEXTDOMAIN) . '</a>';
        }

        return $actions;
    }

    public function version_as_new_post_draft() {
        if (!( isset($_GET['post']) || isset($_POST['post']) )) {
            wp_die(__('Es wurde kein Element geliefert.', CMS_WORKFLOW_TEXTDOMAIN));
        }

        $post_id = (int) isset($_GET['post']) ? $_GET['post'] : $_POST['post'];
        $post = get_post($post_id);

        if (is_null($post)) {
            wp_die(__('Es wurde kein Element mit der angegebenen ID gefunden.', CMS_WORKFLOW_TEXTDOMAIN));
        }

        $cap = $this->get_available_post_types($post->post_type)->cap;

        if (!current_user_can($cap->edit_posts) || $post->post_status != 'publish') {
            wp_die(__('Sie haben nicht die erforderlichen Rechte, um eine neue Version zu erstellen.', CMS_WORKFLOW_TEXTDOMAIN));
        }

        if (!$this->is_post_type_enabled($post->post_type)) {
            wp_die(__('Diese Aktion ist nicht erlaubt.', CMS_WORKFLOW_TEXTDOMAIN));
        }

        if ($post->post_status != 'publish') {
            wp_die(__('Nur veröffentlichte Dokumente können als neue Version erstellt werden.', CMS_WORKFLOW_TEXTDOMAIN));
        }
        
        $draft_id = $this->version_as_new_post($post_id, $post);

        if($draft_id) {
            add_post_meta($draft_id, self::version_post_id, $post_id);
            
            $post_meta = $this->get_post_meta($post_id);
            $post_attached_data = $this->get_post_attached_file($post_id);
            
            $this->add_taxonomies($draft_id, $post);
            
            $this->add_attachments($draft_id, $post_id);
            
            $this->add_post_meta($draft_id, $post_meta);

            $this->add_post_attached_file($draft_id, $post_attached_data);      
            
            wp_safe_redirect(admin_url('post.php?post=' . $draft_id . '&action=edit'));
            exit;
        }
        
    }

    private function version_as_new_post($post_id, $post) {

        $post_author = get_current_user_id();
        $post_status = 'draft';
        
        $new_post = array(
            'post_author' => $post_author,
            'post_content' => $post->post_content,
            'post_title' => $post->post_title,
            'post_excerpt' => $post->post_excerpt,
            'post_status' => $post_status,
            'post_parent' => $post->post_parent,
            'menu_order' => $post->menu_order,
            'post_type' => $post->post_type
        );

        return wp_insert_post($new_post);
    }

    public function version_save_post($post_id, $post) {

        $cap = $this->get_available_post_types($post->post_type)->cap;

        if (!current_user_can($cap->edit_posts)) {
            wp_die(__('Sie haben nicht die erforderlichen Rechte, um eine neue Version zu erstellen.', CMS_WORKFLOW_TEXTDOMAIN));
        }

        $post_attached_data = $this->get_post_attached_file($post_id);
        
        $version_post_id = get_post_meta($post_id, self::version_post_id, true);

        if ($version_post_id) {
            $post_status = 'publish';
            
            $new_post = array(
                'ID' => $version_post_id,
                'post_author' => $post->post_author,
                'post_content' => $post->post_content,
                'post_title' => $post->post_title,
                'post_excerpt' => $post->post_excerpt,
                'post_status' => $post_status
            );

            wp_update_post($new_post);
            
            $this->add_post_attached_file($version_post_id, $post_attached_data);     

            $this->add_attachments($version_post_id, $post_id);            

            $args = array(
                'post_type' => 'attachment',
                'numberposts' => -1,
                'post_status' => null,
                'post_parent' => $post_id
            );
            
            $attachments = get_posts($args);
            
            if ($attachments) {
                foreach ($attachments as $attachment) {
                    $new_post = array(
                        'post_author' => $attachment->post_author,
                        'post_content' => $attachment->post_content,
                        'post_title' => $attachment->post_title,
                        'post_status' => $attachment->post_status,
                        'post_type' => $attachment->post_type,
                        'post_mime_type' => $attachment->post_mime_type
                    );

                    $attachment_newid = wp_insert_post($new_post);
                    $keys = get_post_custom_keys($attachment->ID);

                    foreach ((array) $keys as $key) {
                        $value = get_post_meta($attachment->ID, $key, true);

                        delete_post_meta($version_post_id, $key);
                        add_post_meta($version_post_id, $key, $value);
                    }
                }
            }

            $taxonomies = get_object_taxonomies($post->post_type);
            foreach ($taxonomies as $taxonomy) {
                $post_terms = wp_get_object_terms($post_id, $taxonomy, array('orderby' => 'term_order'));
                $terms = array();
                for ($i = 0; $i < count($post_terms); $i++) {
                    $terms[] = $post_terms[$i]->slug;
                }
                wp_set_object_terms($version_post_id, $terms, $taxonomy);
            }

            $post->post_status = 'draft';
            wp_update_post($post);

            wp_delete_post($post_id);

            if (defined('DOING_AJAX') && DOING_AJAX) {
                return;
            }

            wp_safe_redirect(admin_url('post.php?post=' . $version_post_id . '&action=edit&message=1'));
            exit;
        }
    }

    public function admin_notices() {
        if (isset($_REQUEST['post'])) {
            global $post;

            $old_post_id = get_post_meta($post->ID, self::version_post_id, true);

            if ($old_post_id) {
                $permalink = get_permalink($old_post_id);
                $post_title = get_the_title($old_post_id);

                if (current_user_can('manage_categories')) {
                    $this->show_admin_notice(sprintf(__('Lokale Version vom Dokument &bdquo;<a href="%1$s" target="__blank">%2$s</a>&ldquo;. Überschreiben Sie das ursprüngliche Dokument, indem Sie auf &bdquo;Veröffentlichen&rdquo; klicken.', CMS_WORKFLOW_TEXTDOMAIN), $permalink, $post_title));
                }
                
                else {
                    $this->show_admin_notice(sprintf(__('Lokale Version vom Dokument &bdquo;<a href="%1$s" target="__blank">%2$s</a>&ldquo;.', CMS_WORKFLOW_TEXTDOMAIN), $permalink, $post_title));
                }
                
            }
            
            else {
                $remote_post_meta = get_post_meta($post->ID, self::version_remote_post_meta, true);

                if (isset($remote_post_meta['post_id']) && isset($remote_post_meta['blog_id'])) {
                    if (switch_to_blog($remote_post_meta['blog_id'])) {

                        $permalink = get_permalink($remote_post_meta['post_id']);
                        if ($permalink) {
                            $blog_name = get_bloginfo('name');
                            $blog_lang = get_option('WPLANG') ? get_option('WPLANG') : 'en_EN';
                            $blog_lang = $this->get_lang_name($blog_lang);
                            $post_title = get_the_title($remote_post_meta['post_id']);
                            echo $this->show_admin_notice(sprintf(__('Netzwerkweite Versionierung vom Dokument &bdquo;<a href="%1$s" target="__blank">%2$s</a>&ldquo; - %3$s - %4$s.', CMS_WORKFLOW_TEXTDOMAIN), $permalink, $post_title, $blog_name, $blog_lang));
                        }

                        restore_current_blog();
                    }
                }
            }
        }

        $this->show_flash_admin_notices();
    }

    public function copy_as_new_post_draft() {
        if (!( isset($_GET['post']) || isset($_POST['post']) )) {
            wp_die(__('Es wurde kein Element geliefert.', CMS_WORKFLOW_TEXTDOMAIN));
        }

        $post_id = (int) isset($_GET['post']) ? $_GET['post'] : $_POST['post'];
        $post = get_post($post_id);

        if (is_null($post)) {
            wp_die(__('Es wurde kein Element mit der angegebenen ID gefunden.', CMS_WORKFLOW_TEXTDOMAIN));
        }

        $cap = $this->get_available_post_types($post->post_type)->cap;

        if (!current_user_can($cap->edit_posts) || get_post_meta($post_id, self::version_post_id, true) || $post->post_status == 'trash') {
            wp_die(__('Sie haben nicht die erforderlichen Rechte, um eine neue Kopie zu erstellen.', CMS_WORKFLOW_TEXTDOMAIN));
        }

        if (in_array($post->post_type, array('revision', 'attachment'))) {
            wp_die(__('Sie haben versucht ein Element zu bearbeiten, das nicht erlaubt ist. Bitte kehren Sie zurück und versuchen Sie es erneut.', CMS_WORKFLOW_TEXTDOMAIN));
        }
        
        $draft_id = $this->copy_as_new_post($post_id, $post);
        
        if ($draft_id) {
            add_post_meta($draft_id, self::source_post_id, $post_id);
            
            $post_meta = $this->get_post_meta($post_id);        
            $post_attached_data = $this->get_post_attached_file($post_id);
            
            $this->add_post_meta($draft_id, $post_meta);
            
            $this->add_post_attached_file($draft_id, $post_attached_data);
            
            wp_safe_redirect(admin_url('post.php?post=' . $draft_id . '&action=edit'));
            exit;
        }
    }


    private function copy_as_new_post($post_id, $post) {
        
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

        return wp_insert_post($new_post);
    }

    public function network_connections_meta_box($post_type, $post) {
        global $cms_workflow;

        if (!$this->is_post_type_enabled($post_type) || !in_array($post->post_status, array('publish', 'future', 'private'))) {
            return;
        }

        $connections = get_site_option(self::site_connections, array());

        $network_connections = $this->module->options->network_connections;

        foreach ($network_connections as $key => $blog_id) {
            if (!isset($connections[$blog_id])) {
                unset($network_connections[$key]);
                $cms_workflow->update_module_option($this->module->name, 'network_connections', array());
            } 
            
            else {
                if (!switch_to_blog($blog_id)) {
                    continue;
                }

                $options = get_option($cms_workflow->workflow_options . $this->module->name . '_options', new stdClass);
                $network_posts_types = $options->network_posts_types;
                restore_current_blog();

                if (!isset($network_posts_types[$post_type]) || !$network_posts_types[$post_type]) {
                    return;
                }
            }
        }

        if (empty($network_connections)) {
            return;
        }

        $meta_key = $this->module->workflow_options_name . '_network_connections';
        $meta_data = get_post_custom($post->ID);

        if (!isset($meta_data[$meta_key])) {
            $meta_data = array();
            foreach ($network_connections as $connection) {
                $meta_data[] = $connection;
            }
            update_post_meta($post->ID, $meta_key, $meta_data);
        }

        add_action('post_submitbox_start', array($this, 'network_connections_version_input'));
        add_meta_box('network-connections', __('Netzwerkweite Versionierung', CMS_WORKFLOW_TEXTDOMAIN), array($this, 'network_connections_inner_box'), $post_type, 'normal', 'high');
    }

    public function network_connections_inner_box($post) {

        wp_nonce_field(plugin_basename(__FILE__), 'network_connections_noncename');

        $network_connections = $this->module->options->network_connections;

        if (empty($network_connections)) {
            return;
        }

        $meta_connections = get_post_meta($post->ID, $this->module->workflow_options_name . '_network_connections');
        if (!empty($meta_connections)) {
            $meta_connections = array_values($meta_connections);
            $meta_connections = (array) array_shift($meta_connections);
        }

        $current_blog_id = get_current_blog_id();
        ?>
        <ul id="page_connections_checklist" class="form-no-clear">
        <?php
        foreach ($network_connections as $blog_id) :
            if ($current_blog_id == $blog_id) {
                continue;
            }

            if (!switch_to_blog($blog_id)) {
                continue;
            }

            $blog_name = get_bloginfo('name');
            $blog_lang = get_option('WPLANG') ? get_option('WPLANG') : 'en_EN';
            $blog_lang = $this->get_lang_name($blog_lang);

            restore_current_blog();

            $connected = in_array($blog_id, $meta_connections) ? true : false;
            ?>
            <li id="network_connections_<?php echo $blog_id; ?>">
                <label class="selectit">
                    <input id="connected_blog_<?php echo $blog_id; ?>" type="checkbox" <?php checked($connected, true); ?> name="network_connections[]" value="<?php echo $blog_id ?>" />
            <?php printf('%1$s - %2$s', $blog_name, $blog_lang); ?>
                </label>
            </li>
            <?php
        endforeach;
        ?>
        </ul>  
        <p class="howto"><?php _e('Das Dokument kann in diesen Webseiten als neue Version (Entwurf) dupliziert werden.', CMS_WORKFLOW_TEXTDOMAIN); ?></p>
        <?php
    }

    public function network_connections_save_postmeta($post_id) {
        if (!isset($_POST['post_type'])) {
            return;
        }

        $cap = $this->get_available_post_types($_POST['post_type'])->cap;

        if (!empty($cap) && !current_user_can($cap->edit_posts, $post_id)) {
            return;
        }

        if (!isset($_POST['network_connections_noncename']) || !wp_verify_nonce($_POST['network_connections_noncename'], plugin_basename(__FILE__))) {
            return;
        }

        $connections = isset($_POST['network_connections']) ? (array) $_POST['network_connections'] : array();
        $network_connections = $this->module->options->network_connections;

        foreach ($connections as $key => $value) {
            if (!in_array($value, $network_connections)) {
                unset($connections[$key]);
            }
        }

        update_post_meta($post_id, $this->module->workflow_options_name . '_network_connections', $connections);
    }

    public function network_connections_version_input() {
        global $post;

        $network_connections = get_post_meta($post->ID, $this->module->workflow_options_name . '_network_connections');
        if (!empty($network_connections)) {
            $array_values = array_values($network_connections);
            $network_connections = (array) array_shift($array_values);
        }

        if (!empty($post->ID) && in_array($post->post_status, array('publish', 'future', 'private')) && !empty($network_connections)):
        ?>      
        <p>
            <input type="checkbox" id="network_connections_version" name="network_connections_version" <?php checked(false, true); ?> />
            <label for="network_connections_version"><?php _e('Netzwerkweite Versionierung', CMS_WORKFLOW_TEXTDOMAIN); ?></label>
        </p>
        <?php
        endif;
    }

    public function network_connections_save_post($post_id) {

        $post_status = get_post_status($post_id);
        if ($post_status != 'publish') {
            return;
        }

        if (is_null($this->source_blog)) {
            $this->source_blog = get_current_blog_id();
        }
        
        else {
            return;
        }

        if (!isset($_POST['network_connections_version'])) {
            return;
        }

        $post = get_post($post_id, ARRAY_A);
        
        $post_meta = $this->get_post_meta($post_id);
        
        $post_attached_data = $this->get_post_attached_file($post_id);
        
        if ('post' != $post['post_type'] && 'page' != $post['post_type']) {
            return;
        }

        $blogs = get_post_meta($post_id, $this->module->workflow_options_name . '_network_connections');
        
        if (!empty($blogs)) {
            $array_values = array_values($blogs);
            $blogs = array_shift($array_values);
        }

        $blogs = (array) $blogs;

        if (!( count($blogs) > 0 )) {
            return '';
        }

        $remote_parent_post_meta = get_post_meta($post_id, self::version_remote_parent_post_meta, true);

        foreach ($blogs as $blog_id) {

            if ($blog_id == $this->source_blog) {
                continue;
            }

            if (!switch_to_blog($blog_id)) {
                continue;
            }

            if (isset($remote_parent_post_meta['post_id']) && isset($remote_parent_post_meta['blog_id']) && $blog_id == $remote_parent_post_meta['blog_id']) {
                
                $remote_post = get_post($remote_parent_post_meta['post_id'], ARRAY_A);

                if (empty($remote_post)) {
                    restore_current_blog();
                    delete_post_meta($post_id, self::version_remote_parent_post_meta);
                    switch_to_blog($blog_id);
                } 
                
                else {
                    
                    if ($remote_post->post_status == 'publish') {
                        $draft_id = $this->version_as_new_post($remote_parent_post_meta['post_id'], $post);
                        
                        if($draft_id) {                            
                            $this->add_taxonomies($draft_id, $remote_post);
                            
                            $this->add_post_meta($draft_id, $post_meta);
                            
                            $this->add_post_attached_file($draft_id, $post_attached_data);
                            
                            $permalink = get_permalink($draft_id);

                            if ($permalink) {                            
                                $blog_name = get_bloginfo('name');
                                $blog_lang = get_option('WPLANG') ? get_option('WPLANG') : 'en_EN';
                                $blog_lang = $this->get_lang_name($blog_lang);
                                $post_title = get_the_title($draft_id);

                                restore_current_blog();
                                $this->flash_admin_notice(sprintf(__('Neue Version vom Zieldokument &bdquo;<a href="%1$s" target="__blank">%2$s</a>&ldquo; - %3$s - %4$s wurde erfolgreich erstellt.', CMS_WORKFLOW_TEXTDOMAIN), $permalink, $post_title, $blog_name, $blog_lang));
                            }
                        }
                    } 
                    
                    else {
                        restore_current_blog();
                        $this->flash_admin_notice(__('Zieldokument ist nicht veröffentlicht. Netzwerkweite Versionierung fehlgeschlagen.', CMS_WORKFLOW_TEXTDOMAIN), 'error');
                    }

                    return;
                }
            }

            $newpost = array(
                'post_title' => $post['post_title'],
                'post_content' => $post['post_content'],
                'post_status' => 'draft',
                'post_author' => get_current_user_id(),
                'post_excerpt' => $post['post_excerpt'],
                'post_date' => $post['post_date'],
                'post_type' => $post['post_type']
            );

            $remote_post_id = wp_insert_post($newpost);

            if ($remote_post_id) {               
                $this->add_post_meta($remote_post_id, $post_meta);
                
                $this->add_post_attached_file($remote_post_id, $post_attached_data);
                
                $permalink = get_permalink($remote_post_id);
                $blog_name = get_bloginfo('name');
                $blog_lang = get_option('WPLANG') ? get_option('WPLANG') : 'en_EN';
                $blog_lang = $this->get_lang_name($blog_lang);
                $post_title = get_the_title($remote_post_id);

                restore_current_blog();
                
                add_post_meta($post_id, self::version_remote_parent_post_meta, array('blog_id' => $blog_id, 'post_id' => $remote_post_id));
                $this->flash_admin_notice(sprintf(__('Das Zieldokument &bdquo;<a href="%1$s" target="__blank">%2$s</a>&ldquo; - %3$s - %4$s wurde erfolgreich erstellt.', CMS_WORKFLOW_TEXTDOMAIN), $permalink, $post_title, $blog_name, $blog_lang));

                switch_to_blog($blog_id);
                
                add_post_meta($remote_post_id, self::version_remote_post_meta, array('blog_id' => $this->source_blog, 'post_id' => $post_id));
            }

            restore_current_blog();
        }
    }
    
    private function add_taxonomies($post_id, $post) {
        $taxonomies = get_object_taxonomies($post->post_type);
        foreach ($taxonomies as $taxonomy) {
            $post_terms = wp_get_object_terms($post->ID, $taxonomy, array('orderby' => 'term_order'));
            $terms = array();

            for ($i = 0; $i < count($post_terms); $i++) {
                $terms[] = $post_terms[$i]->slug;
            }

            wp_set_object_terms($post_id, $terms, $taxonomy);
        }      
    }
    
    private function get_post_meta($post_id) {
        $post_meta = array();
        $keys = get_post_custom_keys($post_id);

        foreach ((array) $keys as $key) {
            if (strpos($key, '_') === 0) {
                continue;
            }
            
            $values = get_post_custom_values($key, $post_id);
            
            foreach ($values as $value) {
                $post_meta[] = array($key => maybe_unserialize($value));
            }
        }
        
        return $post_meta;
    }

    
    private function add_post_meta($post_id, $post_meta) {
        foreach ($post_meta as $meta) {
            foreach ($meta as $key => $value) {
                add_post_meta($post_id, $key, $value);
            }
        }
    }
        
    private function add_attachments($post_id, $source_post_id) {
        $args = array(
            'post_type' => 'attachment',
            'numberposts' => -1,
            'post_status' => null,
            'post_parent' => $source_post_id,
            'exclude' => get_post_thumbnail_id($source_post_id),
        );
        
        $attachments = get_posts($args);
        
        if ($attachments) {
            foreach ($attachments as $attachment) {
                $new = array(
                    'post_author' => $attachment->post_author,
                    'post_date' => $attachment->post_date,
                    'post_date_gmt' => $attachment->post_date_gmt,
                    'post_content' => $attachment->post_content,
                    'post_title' => $attachment->post_title,
                    'post_excerpt' => $attachment->post_excerpt,
                    'post_status' => $attachment->post_status,
                    'comment_status' => $attachment->comment_status,
                    'ping_status' => $attachment->ping_status,
                    'post_password' => $attachment->post_password,
                    'post_name' => $attachment->post_name,
                    'to_ping' => $attachment->to_ping,
                    'pinged' => $attachment->pinged,
                    'post_modified' => $attachment->post_modified,
                    'post_modified_gmt' => $attachment->post_modified_gmt,
                    'post_content_filtered' => $attachment->post_content_filtered,
                    'post_parent' => $post_id,
                    'guid' => $attachment->guid,
                    'menu_order' => $attachment->menu_order,
                    'post_type' => $attachment->post_type,
                    'post_mime_type' => $attachment->post_mime_type,
                    'comment_count' => $attachment->comment_count
                );

                $attachment_newid = wp_insert_post($new);
                if(!$attachment_newid) {
                    continue;
                }
                
                $keys = get_post_custom_keys($attachment->ID);

                foreach ((array) $keys as $key) {
                    $value = get_post_meta($attachment->ID, $key, true);
                    add_post_meta($attachment_newid, $key, $value);
                }
            }
        }
        
    }
    
    private function get_post_attached_file($post_id) {
        $attachment = array();
        
        if (current_theme_supports('post-thumbnails')) {
            $thumbnail_id = get_post_thumbnail_id($post_id);
            if ($thumbnail_id > 0) {
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
    
    private function add_post_attached_file($post_id, $post_attached_data) {
        if(empty($post_attached_data)) {
            return;
        }
        
        extract($post_attached_data);
        
        include_once ( ABSPATH . 'wp-admin/includes/image.php' );

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
    
    public function custom_columns($columns) {
        $position = array_search('cb', array_keys($columns));
        if ($position !== false) {
            $columns = array_slice($columns, 0, $position + 1, true) + array('id' => '') + array_slice($columns, $position, count($columns) - $position, true);
        }

        $columns['id'] = __('Nr.', CMS_WORKFLOW_TEXTDOMAIN);

        $position = array_search('comments', array_keys($columns));
        if ($position === false) {
            $position = array_search('date', array_keys($columns));
        }

        if ($position !== false) {
            $columns = array_slice($columns, 0, $position, true) + array('version' => '') + array_slice($columns, $position, count($columns) - $position, true);
        }

        $columns['version'] = __('Version', CMS_WORKFLOW_TEXTDOMAIN);

        return $columns;
    }

    public function posts_custom_column($column, $post_id) {
        if ($column == 'id') {
            echo $post_id;
        } 
        
        elseif ($column == 'version') {
            echo $this->get_versions($post_id);
        }
    }

    public function posts_sortable_columns($columns) {
        $columns['id'] = 'id';
        return $columns;
    }

    public function posts_where($where) {
        if (is_admin() && is_search()) {
            $s = isset($_GET['s']) ? $_GET['s'] : '';
            if (!empty($s)) {
                if (is_numeric($s)) {
                    global $wpdb;
                    $where .= ' OR ' . $wpdb->posts . '.ID = ' . $s;
                } 
                
                elseif (preg_match("/^(\d+)(,\s*\d+)*\$/", $s)) {
                    global $wpdb;
                    $where .= ' OR ' . $wpdb->posts . '.ID in (' . $s . ')';
                }
            }
        }

        return $where;
    }

    private function get_versions($post_id) {
        $documents = array();

        $version_post_id = get_post_meta($post_id, self::version_post_id, true);

        if ($version_post_id) {
            $permalink = get_permalink($version_post_id);
            $translate_to_lang = get_post_meta($version_post_id, '_translate_to_lang_post_meta', true);
            if (empty($translate_to_lang)) {
                $translate_to_lang = get_option('WPLANG') ? get_option('WPLANG') : 'en_EN';
            }

            $translate_to_lang = !empty($translate_to_lang) ? sprintf(' - <span class="translation">%s</span></a>', $this->get_lang_name($translate_to_lang)) : '';

            $post_title = get_the_title($version_post_id);
            $documents[] = sprintf('<a href="%1$s" target="__blank">%2$s%3$s</a>', $permalink, $post_title, $translate_to_lang);
        }

        $remote_post_meta = get_post_meta($post_id, self::version_remote_post_meta, true);

        if (isset($remote_post_meta['post_id']) && isset($remote_post_meta['blog_id'])) {
            if (switch_to_blog($remote_post_meta['blog_id'])) {
                $permalink = get_permalink($remote_post_meta['post_id']);
                if ($permalink) {
                    $translate_to_lang = get_post_meta($remote_post_meta['post_id'], '_translate_to_lang_post_meta', true);
                    if (empty($translate_to_lang)) {
                        $translate_to_lang = get_option('WPLANG') ? get_option('WPLANG') : 'en_EN';
                    }

                    $translate_to_lang = !empty($translate_to_lang) ? sprintf(' - <span class="translation">%s</span></a>', $this->get_lang_name($translate_to_lang)) : '';

                    $post_title = get_the_title($remote_post_meta['post_id']);
                    $documents[] = sprintf('<a class="import" href="%1$s" target="__blank">%2$s%3$s</a>', $permalink, $post_title, $translate_to_lang);
                }
                restore_current_blog();
            }
        }

        $remote_parent_post_meta = get_post_meta($post_id, self::version_remote_parent_post_meta, true);

        $current_blog = get_current_blog_id();
        $blogs = get_post_meta($post_id, $this->module->workflow_options_name . '_network_connections');

        if (!empty($blogs)) {
            $array_values = array_values($blogs);
            $blogs = array_shift($array_values);
        }

        $blogs = (array) $blogs;

        if (count($blogs) > 0 && isset($remote_parent_post_meta['post_id']) && isset($remote_parent_post_meta['blog_id'])) {

            foreach ($blogs as $blog_id) {
                if ($blog_id != $current_blog && $blog_id == $remote_parent_post_meta['blog_id']) {

                    if (switch_to_blog($blog_id)) {
                        $permalink = get_permalink($remote_parent_post_meta['post_id']);
                        if ($permalink) {
                            $translate_to_lang = get_post_meta($remote_parent_post_meta['post_id'], '_translate_to_lang_post_meta', true);
                            if (empty($translate_to_lang)) {
                                $translate_to_lang = get_option('WPLANG') ? get_option('WPLANG') : 'en_EN';
                            }

                            $translate_to_lang = !empty($translate_to_lang) ? sprintf(' - <span class="translation">%s</span></a>', $this->get_lang_name($translate_to_lang)) : '';

                            $post_title = get_the_title($remote_parent_post_meta['post_id']);
                            $documents[] = sprintf('<a class="export" href="%1$s" target="__blank">%2$s%3$s</a>', $permalink, $post_title, $translate_to_lang);
                        }
                        restore_current_blog();
                    }
                }
            }
        } elseif (isset($remote_parent_post_meta['post_id']) && isset($remote_parent_post_meta['blog_id'])) {

            if (switch_to_blog($remote_parent_post_meta['blog_id'])) {
                $permalink = get_permalink($remote_parent_post_meta['post_id']);
                if ($permalink) {
                    $translate_to_lang = get_post_meta($remote_parent_post_meta['post_id'], '_translate_to_lang_post_meta', true);
                    if (empty($translate_to_lang)) {
                        $translate_to_lang = get_option('WPLANG') ? get_option('WPLANG') : 'en_EN';
                    }

                    $translate_to_lang = !empty($translate_to_lang) ? sprintf(' - <span class="translation">%s</span></a>', $this->get_lang_name($translate_to_lang)) : '';

                    $post_title = get_the_title($remote_parent_post_meta['post_id']);
                    $documents[] = sprintf('<a class="export" href="%1$s" target="__blank">%2$s%3$s</a>', $permalink, $post_title, $translate_to_lang);
                }
                restore_current_blog();
            }
        }

        if (empty($documents)) {
            $documents[] = '&#8212;';
        }

        return implode('<br>', $documents);
    }

}

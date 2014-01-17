<?php

class Workflow_Task_List extends Workflow_Module {

    const postmeta_key = 'workflow_task_list';
    
    public $module;
        
    public static $priorities;
            
    public function __construct() {
		global $cms_workflow;
		
		$this->module_url = $this->get_module_url( __FILE__ );
        
                $content_help_tab = array(
                    '<p>' . __('In einer Aufgabenliste wird festgehalten, welche Aufgaben anstehen, wer dafür verantwortlich ist und bis wann sie erledigt sein müssen. Sie können auf dieser Seite auswählen, in welchen Bereichen die Erstellung von Aufgabenlisten freigegeben werden soll.', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
                    '<p>' . __('So fügen Sie eine neue Aufgabe hinzu:', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
                    '<ol>',
                    '<li>' . __('Gehen Sie auf ein Dokument in einem freigegebenen Bereich. ', CMS_WORKFLOW_TEXTDOMAIN) . '</li>',
                    '<li>' . __('Wählen Sie "+ Neue Aufgabe hinzufügen" im Kästchen "Aufgabenliste" aus (wenn diese Box nicht erscheint, können Sie sie über die Lasche "Optionen einblenden" in der rechten oberen Ecke anzeigen lassen).', CMS_WORKFLOW_TEXTDOMAIN) . '</li>',
                    '<li>' . __('Sie können jetzt folgende Informationen angeben oder auswählen:', CMS_WORKFLOW_TEXTDOMAIN) . '<br />' . __('<strong>Titel</strong> und <strong>Beschreibung</strong> der Aufgabe', CMS_WORKFLOW_TEXTDOMAIN) . '<br />' . __('<strong>Aufgabe zuweisen an</strong> - um den Autor auszuwählen, der diese Aufgabe erledigen soll. Erscheint nur bei aktivierter Autorenverwaltung und wenn mehrere Autoren dem Dokument zugeordnet sind. Aufgaben können keinen Benutzergruppen zugewiesen werden.', CMS_WORKFLOW_TEXTDOMAIN) . '<br />' . __('<strong>Priorität</strong> - auswählbar sind: hoch, normal oder niedrig. Je nach Priorität wird die Reihenfolge und Farbe der Aufgaben im Dashboard angezeigt.', CMS_WORKFLOW_TEXTDOMAIN) . '</li>',
                    '<li>' . __('Speichern Sie mit "Neue Aufgabe hinzufügen".', CMS_WORKFLOW_TEXTDOMAIN) . '</li>',
                    '</ol>',
                    '<p>' . __('Wenn Sie in den Workflow-Einstellungen das Dashboard-Modul und hierin die Aufgabenliste aktiviert haben, sehen Sie im Dashboard die Liste aller anstehenden Aufgaben (wenn die Box "Aufgabenliste" dort nicht erscheint, können Sie sie über die Lasche "Optionen einblenden" in der rechten oberen Ecke anzeigen lassen). <br />Je nach Priorität werden diese angeordnet und in unterschiedlichen Farben angezeigt:', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
                   
                    '<p class="priority-3">' . __('<strong>Priorität hoch</strong> - Aufgabe steht ganz oben in der Liste', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
                    '<p class="priority-2">'. __('<strong>Priorität normal</strong> - Aufgabe steht in der Mitte der Liste', CMS_WORKFLOW_TEXTDOMAIN) . '</p>',
                    '<p class="priority-1">'. __('<strong>Priorität niedrig</strong> - Aufgabe steht am Ende der Liste und wird nicht farblich hervorgehoben', CMS_WORKFLOW_TEXTDOMAIN) . '</p>'
                );
                
                /*Kontexthilfe, einzubinden in den Bearbeitungsseiten und neuen Seiten zu posts und pages über 
                 * (nicht über load-, sondern über admin_head-, sonst erscheint der neue Hilfe-Tab als erstes!)    
                 *  
                 * add_action( 'admin_head-post-new.php', array( __CLASS__, 'add_post_new_help_tab'));     
                 * add_action( 'admin_head-post.php', array( __CLASS__, 'add_post_new_help_tab'));    
                 *   
                */
                $context_help_tab = array(
                    '<p></p>'
                );
        
                        
		$args = array(
			'title' => __( 'Aufgabenliste', CMS_WORKFLOW_TEXTDOMAIN ),
			'description' => __( 'In einer Aufgabenliste wird festgehalten, welche Aufgaben anstehen, wer dafür verantwortlich ist und bis wann sie erledigt sein müssen.', CMS_WORKFLOW_TEXTDOMAIN ),
			'module_url' => $this->module_url,
			'slug' => 'taskl-list',
			'default_options' => array(
				'post_types' => array(
					'post' => true,
					'page' => true
				),
                'send_email_on_assignment' => true,
			),
			'configure_callback' => 'print_configure_view',
			'settings_help_tab' => array(
				'id' => 'workflow-task-list-overview',
				'title' => __('Übersicht', CMS_WORKFLOW_TEXTDOMAIN),
				'content' => implode(PHP_EOL, $content_help_tab),
				),
			'settings_help_sidebar' => __( '<p><strong>Für mehr Information:</strong></p><p><a href="http://blogs.fau.de/cms">Dokumentation</a></p><p><a href="http://blogs.fau.de/webworking">RRZE-Webworking</a></p><p><a href="https://github.com/RRZE-Webteam">RRZE-Webteam in Github</a></p>', CMS_WORKFLOW_TEXTDOMAIN ),
		);
        
        self::$priorities = array(
                '1' => __('Niedrig', CMS_WORKFLOW_TEXTDOMAIN),
                '2' => __('Normal', CMS_WORKFLOW_TEXTDOMAIN),
                '3' => __('Hoch', CMS_WORKFLOW_TEXTDOMAIN)
        );
        
		$this->module = $cms_workflow->register_module( 'task_list', $args );
	}
	
	public function init() {
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        
		add_action( 'add_meta_boxes', array ( $this, 'add_post_meta_box' ) );		
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
        
        add_action( 'wp_ajax_task_list_ajax_new_task_submit', array( $this, 'task_list_ajax_new_task' ) );
        add_action( 'wp_ajax_task_list_ajax_print_after_new_task', array( $this, 'task_list_ajax_print_after_new_task' ) );
        add_action( 'wp_ajax_task_list_ajax_mark_as_done', array( $this, 'task_list_ajax_mark_as_done' ) );
        add_action( 'wp_ajax_task_list_ajax_get_page', array( $this, 'task_list_ajax_get_page' ) );
        add_action( 'wp_ajax_task_list_ajax_delete_task', array( $this, 'task_list_ajax_delete_task' ) );
        add_action( 'wp_ajax_task_list_ajax_self_assignment', array( $this, 'task_list_ajax_self_assignment' ) );
        add_action( 'wp_ajax_task_list_ajax_self_unassignment', array( $this, 'task_list_ajax_self_unassignment' ) );
        
        add_filter( 'workflow_post_versioning_skip_add_post_meta', array( $this, 'post_versioning_skip_post_meta'));
	}
       
	public function admin_enqueue_scripts() {
        global $pagenow, $post;
        
		if ( !in_array( $pagenow, array( 'post.php', 'page.php', 'post-new.php', 'page-new.php' ) ) )
			return;
		
		$post_type = $this->get_current_post_type();
        
		if ( !$this->is_post_type_enabled($post_type))
			return;
			        
        $current_user = wp_get_current_user(); 
        
        wp_enqueue_style( 'workflow-task-list', $this->module->module_url . 'task-list.css', false, CMS_WORKFLOW_VERSION );
        
        wp_enqueue_script( 'sprintf' );
        
        wp_enqueue_script( 'workflow-task-list', $this->module_url . 'task-list.js', 'jquery' );
        wp_localize_script( 'workflow-task-list', 'task_list_vars', array(
            'ajax_url'                                  => admin_url( 'admin-ajax.php' ),
            'ajax_loader'                               => admin_url( 'images/wpspin_light.gif' ),
            'current_user_id'                           => $current_user->ID,
            'post_id'                                   => $post->ID,
            'current_user_display_name'                 => $current_user->display_name,
            'current_date'                              => date_i18n(get_option('date_format')),
            'message_1'                                 => __('Sie sind dabei, die ausgewählte Aufgabe dauerhaft zu löschen. Sind Sie sicher, dass Sie fortfahren möchten?', CMS_WORKFLOW_TEXTDOMAIN),
            'message_2'                                 => __('Diese Aufgabe wurde von %1$s am %2$s als erledigt markiert.', CMS_WORKFLOW_TEXTDOMAIN),
            'nonce_task_list_ajax_mark_as_done'         => wp_create_nonce( 'task_list_ajax_mark_as_done' ),
            'nonce_task_list_ajax_self_assignment'      => wp_create_nonce( 'task_list_ajax_self_assignment' ),
            'nonce_task_list_ajax_self_unassignment'    => wp_create_nonce( 'task_list_ajax_self_unassignment' ),
            'nonce_task_list_ajax_delete_task'          => wp_create_nonce( 'task_list_ajax_delete_task' ),
            'nonce_task_list_ajax_get_page'             => wp_create_nonce( 'task_list_ajax_get_page' ),
            'nonce_task_list_ajax_new_task'             => wp_create_nonce( 'task_list_ajax_new_task' ),
            'nonce_task_list_ajax_print_after_new_task' => wp_create_nonce( 'task_list_ajax_print_after_new_task' )
        ) );
	}
    
    public function add_post_meta_box( ) {
		$post_type = $this->get_current_post_type();
        
		if ( !$this->is_post_type_enabled($post_type))
			return;
        
        add_meta_box( 'workflow-task-list', __('Aufgabenliste', CMS_WORKFLOW_TEXTDOMAIN), array( $this, 'metabox_post' ), $post_type, 'side', 'high' );
    }
        
    public function post_versioning_skip_post_meta($key) {
        if( $key === self::postmeta_key)
            return true;
    }
    
    public function metabox_post() {
        global $post_id; 
        
        $post = get_post($post_id);
        $current_user = wp_get_current_user();
        ?>
        <div id="task-list-content">
            <?php $this->print_task_list($post_id);?>
        </div>
        <?php if ( current_user_can( 'manage_categories' ) )  : ?>
        <div id="task-list-content-error"></div>
        
        <h4>
            <a href="#" class="hide-if-no-js" id="task-list-new"><?php _e(' + Neue Aufgabe hinzufügen', CMS_WORKFLOW_TEXTDOMAIN);?></a>
        </h4>
        <div id="task-list-new-content">
            <div id="new-task-form">
                <form method="post">
                    <label for="new-task-title" id="new-task-title-label"><?php _e('Titel', CMS_WORKFLOW_TEXTDOMAIN); ?></label>
                    <br />
                    <input type="text" name="new-task-title" id="new-task-title" />
                    <br />
                    <label for="new-task-description"><?php _e('Beschreibung', CMS_WORKFLOW_TEXTDOMAIN); ?></label>
                    <br />
                    <textarea rows="2" name="new-task-description" id="new-task-description" /></textarea>
                    <br />
                <?php
                $authors = array();

                if ( $this->module_activated( 'authors' )) {
                    $authors = Workflow_Authors::get_authors( $post->ID );
                     if( !$authors)
                         $authors = array();                   
                }
                
                $authors[$post->post_author] = get_userdata( $post->post_author );
                unset($authors[$current_user->ID]);
                if(!empty($authors)):?>
                    <label for="task-list-author"><?php _e('Aufgabe zuweisen an', CMS_WORKFLOW_TEXTDOMAIN); ?></label>
                    <br />                    
                    <select id="task-list-author">
                        <option value="0"><?php _e('Alle Autoren', CMS_WORKFLOW_TEXTDOMAIN); ?></option>
                    <?php
                    foreach( $authors as $key => $user ) {
                        echo '<option value="' . esc_attr( $key ) . '"';			
                        echo '>' . esc_html( $user->display_name ) . '</option>';                
                    } 
                endif; ?>
                    </select>
                    <br />
                    <label for="task-priority"><?php _e('Priorität', CMS_WORKFLOW_TEXTDOMAIN); ?></label>
                    <br />
                    <?php
                    $priorities = array_keys(self::$priorities);
                    sort($priorities);
                    $size = sizeof($priorities);
                    if($size % 2 == 0)
                        $default_priority = $priorities[$size / 2 - 1];
                    else
                        $default_priority = $priorities[floor( $size / 2)];
                    
                    echo '<select id="task-priority">';
                    foreach ( self::$priorities as $key => $value ) {
                        echo '<option value="' . esc_attr( $key ) . '"';
                        echo selected( $default_priority, $key );			
                        echo '>' . esc_html( $value ) . '</option>';
                    }
                    echo '</select>';
                    ?>
                    <br />
                    <div id="new-task-loading">
                        <img src="<?php echo admin_url( 'images/wpspin_light.gif' ); ?>" alt="<?php _e('Laden...', CMS_WORKFLOW_TEXTDOMAIN);?>" title="<?php _e('Laden...', CMS_WORKFLOW_TEXTDOMAIN);?>" />
                    </div>
                    <div id="new-task-error" class="task-error"></div>
                    <div class="new-task-submit">
                        <input type="submit" name="task-list-new-submit" id="new-task-submit" class="button" value="<?php _e('Neue Aufgabe hinzufügen', CMS_WORKFLOW_TEXTDOMAIN);?>" disabled="disabled" />
                    </div>
                </form>
            </div>
        </div>
        <?php endif;?>
        <div class="clear"></div>    
    <?php 
    }
        
    public function print_task_list($post_id) {
        
        $data = get_post_meta($post_id, self::postmeta_key);
        
        if(!empty($data) ) {
            $data = json_decode(json_encode($data), false);
            $data = $this->task_list_order_page_result( $data );
            
            foreach( $data as $task ) {
                $this->task_list_print_task( $task );
            }
            
        } else { 
            echo '<p id="task-list-no-tasks-available">'.__('Keine Aufgaben gefunden.', CMS_WORKFLOW_TEXTDOMAIN).'</p>';
        }
    }
    
    private function task_list_order_page_result( $data ) {                
        $current_user = wp_get_current_user();
        
        $return_array = array();
                
        foreach( $data as $value ) {           
            if( $value->task_author == $current_user->ID && empty($value->task_done) )
                $return_array[] = $value;
        }
        
        foreach( $data as $value ) {
            if( $value->task_author != $current_user->ID && empty($value->task_done) )
                $return_array[] = $value;
        }
        
        foreach( $data as $value ) {
            if( !empty( $value->task_done ) )
                $return_array[] = $value;
        }
        
        return $return_array;
    }
    
    private function task_list_print_task( $task ) {
        $current_user = wp_get_current_user();
        
        $task_title         = stripslashes( $task->task_title );
        $task_title_display = stripslashes( $task->task_title );
        $task_title_style   = ' style="text-decoration: none;"';
        $task_date_added    = date_i18n(get_option('date_format'), $task->task_timestamp );
        $task_adder         = get_userdata( $task->task_adder )->display_name;
        $task_description   = stripslashes( $task->task_description );
        $task_author        = !empty($task->task_author) ? get_userdata( $task->task_author )->display_name : __('Alle Autoren', CMS_WORKFLOW_TEXTDOMAIN);
        $task_priority      = self::task_list_get_textual_priority( $task->task_priority );
        $task_done_details  = @unserialize( $task->task_done );
        
        if( $current_user->ID == $task->task_author )
            $task_title_display = '* '.$task_title;
               
        if( is_array( $task_done_details) ) {
            $done_checked       = ' checked="checked"';
            $task_title_style   = ' style="text-decoration: line-through;"';
        } 
        ?>        
        <div class="task-item">
            <a class="task-item-link" href="#" title="<?php echo $task_title; ?>"<?php echo @$task_title_style; ?>><?php echo $task_title_display; ?></a>
            <div class="task-item-content">
                <div class="task-content-body">
                    <span class="task-added"><?php printf('Von %1$s am %2$s', $task_adder, $task_date_added);?></span><br />
                    <?php _e('Besitzer', CMS_WORKFLOW_TEXTDOMAIN); ?>: <span class="task-assigned"><?php echo $task_author; ?></span><br />
                    <?php _e('Priorität', CMS_WORKFLOW_TEXTDOMAIN); ?>: <span class="task-priority"><?php echo $task_priority; ?></span><br />
                    <p class="task-description"><?php echo $task_description; ?></p>
                
                    <?php if( is_array( $task_done_details ) ): ?>
                    <p class="marked-as-done"><?php printf(__('Diese Aufgabe wurde von %1$s am %2$s als erledigt markiert.', CMS_WORKFLOW_TEXTDOMAIN), get_userdata( $task_done_details['marker'] )->display_name, date_i18n(get_option('date_format'), $task_done_details['date'] ));?></p>
                    <?php endif;?>
                </div>
                <div class="task-actions">
                    <div class="task-actions-left">
                    <?php if( $task->task_author != $current_user->ID ): ?>                        
                        <p>
                            <a href="#" class="task-self-assignment" rel="<?php echo $task->task_id; ?>"><?php _e('Aufgabe annehmen', CMS_WORKFLOW_TEXTDOMAIN); ?></a>
                        </p>                    
                    <?php elseif( $task->task_author == $current_user->ID ): ?>                        
                        <p>
                            <a href="#" class="task-self-unassignment" rel="<?php echo $task->task_id; ?>"><?php _e('Aufgabe ablehnen', CMS_WORKFLOW_TEXTDOMAIN); ?></a>
                        </p>                       
                    <?php endif; ?>                        
                    <?php if ( current_user_can( 'manage_categories' ) )  : ?>                        
                        <p>
                            <a href="#" class="task-delete" rel="<?php echo $task->task_id; ?>"><?php _e('Aufgabe löschen', CMS_WORKFLOW_TEXTDOMAIN); ?></a>
                        </p>                     
                    <?php endif; ?>                                                                          
                    </div>
                    <div class="task-actions-right">
                        <p>
                            <input type="checkbox" name="mark-as-done" class="mark-as-done" value="<?php echo $task->task_id; ?>"<?php echo @$done_checked; ?> /> <?php _e('Aufgabe erledigt', CMS_WORKFLOW_TEXTDOMAIN);?>
                        </p>                        
                    </div>
                    
                    <div style="clear: both;"></div>
                    
                </div>  
            </div>
            <div style="clear: both;"></div>
        </div>
        
        <?php
    }
        
    public function task_list_ajax_print_after_new_task() {
        
        check_ajax_referer( 'task_list_ajax_print_after_new_task', 'nonce' );
        
        $post_id = (int) $_REQUEST['post_id'];
        
        $post = get_post_meta($post_id, self::postmeta_key);
        
        if(empty($post)) {
            header( "Content-Type: application/json" );
            echo json_encode( array('error' => __('Ein unerwarteter Fehler ist aufgetreten. Bitte versuchen Sie es erneut.', CMS_WORKFLOW_TEXTDOMAIN)) );
            exit;
        }
             
        $task_title = trim( $_REQUEST['task_title'] );
        
        if(empty($task_title)) {
            header( "Content-Type: application/json" );
            echo json_encode( array('error' => '<p>'.__('Der Titel wird benötigt.', CMS_WORKFLOW_TEXTDOMAIN).'</p>') );
            exit;
        }
        
        $data = new stdClass;
        $data->ID = $post;
        $data->task_timestamp = time();
        
        foreach( $_REQUEST as $key => $value ) {
            $data->$key = $value;
        }

        echo $this->task_list_print_task( $data );
        exit;
    }
    
    public function task_list_ajax_mark_as_done() {
        
        check_ajax_referer( 'task_list_ajax_mark_as_done', 'nonce' );
        
        $post_id = (int) $_REQUEST['post_id'];
        $task_id = (int) $_REQUEST['task_id'];
                
        if( isset( $_REQUEST['checked'] ) && $_REQUEST['checked'] == 'checked' )
            $update_data = serialize( array( 'marker' => (int) $_REQUEST['marker'], 'date' => time()) );
            
        else
            $update_data = null;
        
        $data = get_post_meta($post_id, self::postmeta_key);
        
        foreach( $data as $task) {
            if($task['task_id'] == $task_id) {
                $old_task = $task;
                $task['task_done'] = $update_data;
                update_post_meta($post_id, self::postmeta_key, $task, $old_task);
            } 
        }
        
        exit;

    }
    
    public function task_list_ajax_delete_task() {

        check_ajax_referer( 'task_list_ajax_delete_task', 'nonce' );
        
        $post_id = (int) $_REQUEST['post_id'];
        $task_id = (int) $_REQUEST['task_id'];
        
        $data = get_post_meta($post_id, self::postmeta_key);
        
        foreach( $data as $task) {
            if($task['task_id'] == $task_id)
                delete_post_meta($post_id, self::postmeta_key, $task);
        }
        
        exit;
    }
    
    public function task_list_ajax_self_assignment() {

        check_ajax_referer( 'task_list_ajax_self_assignment', 'nonce' );
        
        $post_id = (int) $_REQUEST['post_id'];
        $task_id = (int) $_REQUEST['task_id'];

        $current_user = wp_get_current_user();
        
        $data = get_post_meta($post_id, self::postmeta_key);
        
        foreach( $data as $task) {
            if($task['task_id'] == $task_id) {
                $old_task = $task;
                $task['task_author'] = $current_user->ID;
                update_post_meta($post_id, self::postmeta_key, $task, $old_task);
            } 
        }
        
        exit;

    }
    
    public function task_list_ajax_self_unassignment() {

        check_ajax_referer( 'task_list_ajax_self_unassignment', 'nonce' );

        $post_id = (int) $_REQUEST['post_id'];
        $task_id = (int) $_REQUEST['task_id'];
        
        $data = get_post_meta($post_id, self::postmeta_key);
        
        foreach( $data as $task) {
            if($task['task_id'] == $task_id) {
                $old_task = $task;
                $task['task_author'] = 0;
                update_post_meta($post_id, self::postmeta_key, $task, $old_task);
            } 
        }

        exit;
    }
    
    public function task_list_ajax_new_task() {
        
        check_ajax_referer( 'task_list_ajax_new_task', 'nonce' );
        
        $post_id = (int) $_REQUEST['post_id'];
                
        $task_title = trim( $_REQUEST['task_title'] );
        
        if(empty($task_title)) {
            header( "Content-Type: application/json" );
            echo json_encode( array('error' => '<p>'.__('Der Titel wird benötigt.', CMS_WORKFLOW_TEXTDOMAIN).'</p>') );
            exit;
        }
        
        $data = array(
            'task_title'        => $task_title,
            'task_timestamp'    => time(),
            'task_adder'        => (int) $_REQUEST['task_adder'],
            'task_description'  => trim( $_REQUEST['task_description'] ),
            'task_author'       => (int) $_REQUEST['task_author'],
            'task_priority'     => (int) $_REQUEST['task_priority'],
            'task_done'         => null
        );
        
        $task_id = add_post_meta($post_id, self::postmeta_key, $data);
        
        if($task_id) {
            $old_data = $data;
            $data['task_id'] = $task_id;
            update_post_meta($post_id, self::postmeta_key, $data, $old_data);
            
            if( $this->module->options->send_email_on_assignment ) {
                $data['post_id'] = $post_id;
                do_action( 'workflow_task_list_new_task', $data );
            }
        } else {
            header( "Content-Type: application/json" );
            echo json_encode( array('error' => __('Ein unerwarteter Fehler ist aufgetreten. Bitte versuchen Sie es erneut.', CMS_WORKFLOW_TEXTDOMAIN)) );           
        }
        
        exit;
    }
    
    public function task_list_ajax_get_page() {
        check_ajax_referer( 'task_list_ajax_get_page', 'nonce' );
        
        $this->print_task_list( (int) $_REQUEST['post_id'] );
        exit;
    }
    
    public static function task_list_get_textual_priority( $numeric_priority ) {
        
        return self::$priorities[$numeric_priority];
    }
    
	public function register_settings() {
			add_settings_section( $this->module->workflow_options_name . '_general', false, '__return_false', $this->module->workflow_options_name );
			add_settings_field( 'post_types', __( 'Freigabe', CMS_WORKFLOW_TEXTDOMAIN ), array( $this, 'settings_post_types_option' ), $this->module->workflow_options_name, $this->module->workflow_options_name . '_general' );
	}
	
	public function settings_post_types_option() {
		global $cms_workflow;
		$cms_workflow->settings->custom_post_type_option( $this->module );	
	}

	public function settings_validate( $new_options ) {
		
		if ( !isset( $new_options['post_types'] ) )
			$new_options['post_types'] = array();
        
		$new_options['post_types'] = $this->clean_post_type_options( $new_options['post_types'], $this->module->post_type_support );
		
		return $new_options;

	}	

	public function print_configure_view() {
		?>
		<form class="basic-settings" action="<?php echo esc_url( menu_page_url( $this->module->settings_slug, false ) ); ?>" method="post">
			<?php settings_fields( $this->module->workflow_options_name ); ?>
			<?php do_settings_sections( $this->module->workflow_options_name ); ?>
			<?php
				echo '<input id="cms_workflow_module_name" name="cms_workflow_module_name" type="hidden" value="' . esc_attr( $this->module->name ) . '" />';				
			?>
			<p class="submit"><?php submit_button( null, 'primary', 'submit', false ); ?></p>
		</form>
		<?php
	}	
    
    
}

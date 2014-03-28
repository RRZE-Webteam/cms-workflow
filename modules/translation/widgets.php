<?php

class Workflow_Translation_Widget extends WP_Widget {

    public function __construct() {
        global $cms_workflow;

        parent::__construct(
            'workflow-translation', __('Sprachwechsler', CMS_WORKFLOW_TEXTDOMAIN), array(
            'classname' => 'workflow-translation-widget',
            'description' => __('Multisite-Sprachwechsler', CMS_WORKFLOW_TEXTDOMAIN)
            )
        );
    }

    public function widget($args, $instance) {

        extract($args, EXTR_SKIP);

        if (!isset($instance['widget_sort_order']))
            $instance['widget_sort_order'] = 'blogid';

        $output = workflow_get_translated_sites(
                array(
                    'linktext' => $instance['widget_link_type'],
                    'sort' => $instance['widget_sort_order'],
                    'show_current_blog' => $instance['widget_show_current_blog'] == '1' ? true : false,
                    'echo' => false
                )
        );

        if ('' == $output)
            return;

        echo $before_widget;

        if ($instance['widget_title'])
            echo $before_title . apply_filters('widget_title', $instance['widget_title']) . $after_title;

        echo $output . $after_widget;
    }

    public function update($new_instance, $old_instance) {

        $instance = $old_instance;
        $instance['widget_title'] = strip_tags($new_instance['workflow_translation_widget_title']);
        $instance['widget_link_type'] = esc_attr($new_instance['workflow_translation_widget_link_type']);
        $instance['widget_sort_order'] = esc_attr($new_instance['workflow_translation_widget_sort_order']);
        $instance['widget_show_current_blog'] = $new_instance['workflow_translation_widget_show_current_blog'] == 'on' ? true : false;

        return $instance;
    }

    public function form($instance) {
        $title = ( isset($instance['widget_title']) ) ? strip_tags($instance['widget_title']) : '';
        $sort_order = ( isset($instance['widget_sort_order']) ) ? strip_tags($instance['widget_sort_order']) : '';
        $link_type = ( isset($instance['widget_title']) ) ? esc_attr($instance['widget_link_type']) : '';
        $show_current_blog = ( isset($instance['widget_show_current_blog']) ) ? strip_tags($instance['widget_show_current_blog']) : '';
        ?>
        <p>
            <label for='<?php echo $this->get_field_id('workflow_translation_widget_title'); ?>'><?php _e('Titel:', CMS_WORKFLOW_TEXTDOMAIN); ?></label><br />
            <input class="widefat" type ='text' id='<?php echo $this->get_field_id("workflow_translation_widget_title"); ?>' name='<?php echo $this->get_field_name('workflow_translation_widget_title'); ?>' value='<?php echo $title; ?>'>
        </p>
        <p>
            <label for='<?php echo $this->get_field_id('workflow_translation_widget_sort_order'); ?>'><?php _e('Sortierung:', CMS_WORKFLOW_TEXTDOMAIN); ?></label><br />
            <select class="widefat" id='<?php echo $this->get_field_id('workflow_translation_widget_sort_order'); ?>' name='<?php echo $this->get_field_name('workflow_translation_widget_sort_order'); ?>' >
                <option <?php selected($sort_order, 'name'); ?> value="name"><?php _e('nach Webseitennamen', CMS_WORKFLOW_TEXTDOMAIN); ?></option>
                <option <?php selected($sort_order, 'blogid'); ?> value="blogid"><?php _e('nach Webseiten-ID', CMS_WORKFLOW_TEXTDOMAIN); ?></option>
            </select>
        </p>
        <p>
            <label for='<?php echo $this->get_field_id('workflow_translation_widget_link_type'); ?>'><?php _e('Linktyp:', CMS_WORKFLOW_TEXTDOMAIN); ?></label><br />
            <select class="widefat" id='<?php echo $this->get_field_id('workflow_translation_widget_link_type'); ?>' name='<?php echo $this->get_field_name('workflow_translation_widget_link_type'); ?>' >
                <option <?php selected($link_type, 'text'); ?> value="text"><?php _e('Text', CMS_WORKFLOW_TEXTDOMAIN); ?></option>
                <option <?php selected($link_type, 'lang_code'); ?> value="lang_code"><?php _e('Sprachcode', CMS_WORKFLOW_TEXTDOMAIN); ?></option>
            </select>
        </p>
        <p>
            <label for='<?php echo $this->get_field_id('workflow_translation_widget_show_current_blog'); ?>'><?php _e('Aktuelle Webseite zeigen:', CMS_WORKFLOW_TEXTDOMAIN); ?></label>
            <input <?php checked($show_current_blog, '1'); ?> type="checkbox" id="<?php echo $this->get_field_id('workflow_translation_widget_show_current_blog'); ?>" name="<?php echo $this->get_field_name('workflow_translation_widget_show_current_blog'); ?>" />
        </p>
        <?php
    }

}

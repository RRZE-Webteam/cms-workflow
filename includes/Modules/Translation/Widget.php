<?php

namespace RRZE\Workflow\Modules\Translation;

defined('ABSPATH') || exit;

use WP_Widget;

class Widget extends WP_Widget
{
    public function __construct()
    {
        parent::__construct(
            'workflow_translation_lang_switcher',
            __('Sprachwechsler', 'cms-workflow'),
            array(
                'classname' => 'workflow-translation-widget',
                'description' => __('Multisite-Sprachwechsler', 'cms-workflow')
            )
        );
    }

    public function widget($args, $instance)
    {
        extract($args, EXTR_SKIP);

        $data = array(
            'linktext' => !empty($instance['widget_link_type']) ? $instance['widget_link_type'] : 'text',
            'order' => !empty($instance['widget_sort_order']) ? $instance['widget_sort_order'] : 'blogid',
            'show_current_blog' => !empty($instance['widget_show_current_blog']) ? true : false,
            'echo' => isset($instance['widget_echo']) ? true : false,
            'redirect_page_id' => !empty($instance['widget_redirect_page_id']) ? $instance['widget_redirect_page_id'] : 0
        );

        $titlecontent = '';

        if (!empty($instance['widget_title'])) {
            $titlecontent = apply_filters('widget_title', $instance['widget_title']);
        } else {
            $titlecontent = __('Sprachwechsler', 'cms-workflow');
        }


        $output = Translation::get_related_posts($data, $titlecontent);

        if ('' == $output) {
            return;
        }

        echo $before_widget;
        echo $output . $after_widget;
    }

    public function update($new_instance, $old_instance)
    {
        $instance = $old_instance;
        $instance['widget_link_type'] = esc_attr($new_instance['workflow_translation_widget_link_type']);
        $instance['widget_sort_order'] = esc_attr($new_instance['workflow_translation_widget_sort_order']);
        $instance['widget_show_current_blog'] = !empty($new_instance['workflow_translation_widget_show_current_blog']) ? true : false;
        $instance['widget_redirect_page_id'] = intval($new_instance['workflow_translation_widget_redirect_page_id']);

        return $instance;
    }

    public function form($instance)
    {
        $sort_order = isset($instance['widget_sort_order']) ? esc_attr($instance['widget_sort_order']) : '';
        $link_type = isset($instance['widget_title']) ? esc_attr($instance['widget_link_type']) : '';
        $show_current_blog = !empty($instance['widget_show_current_blog']) ? true : false;
        $redirect_page_id = !empty($instance['widget_redirect_page_id']) ? intval($instance['widget_redirect_page_id']) : 0;
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('workflow_translation_widget_sort_order'); ?>"><?php _e('Sortierung:', 'cms-workflow'); ?></label><br>
            <select class="widefat" id='<?php echo $this->get_field_id('workflow_translation_widget_sort_order'); ?>' name='<?php echo $this->get_field_name('workflow_translation_widget_sort_order'); ?>'>
                <option <?php selected($sort_order, 'name'); ?> value="name"><?php _e('nach Webseitennamen', 'cms-workflow'); ?></option>
                <option <?php selected($sort_order, 'blogid'); ?> value="blogid"><?php _e('nach Webseiten-ID', 'cms-workflow'); ?></option>
            </select>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('workflow_translation_widget_link_type'); ?>"><?php _e('Linktyp:', 'cms-workflow'); ?></label><br>
            <select class="widefat" id='<?php echo $this->get_field_id('workflow_translation_widget_link_type'); ?>' name='<?php echo $this->get_field_name('workflow_translation_widget_link_type'); ?>'>
                <option <?php selected($link_type, 'text'); ?> value="text"><?php _e('Text', 'cms-workflow'); ?></option>
                <option <?php selected($link_type, 'lang_code'); ?> value="lang_code"><?php _e('Sprachcode', 'cms-workflow'); ?></option>
            </select>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('workflow_translation_widget_show_current_blog'); ?>"><?php _e('Aktuelle Webseite zeigen:', 'cms-workflow'); ?></label>
            <input <?php checked($show_current_blog, true); ?> type="checkbox" id="<?php echo $this->get_field_id('workflow_translation_widget_show_current_blog'); ?>" name="<?php echo $this->get_field_name('workflow_translation_widget_show_current_blog'); ?>" />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('workflow_translation_widget_redirect_page_id'); ?>"><?php _e('Weiterleitungsseite (statische Seite):', 'cms-workflow') ?></label>
            <?php
            $data = array(
                'id' => $this->get_field_id('workflow_translation_widget_redirect_page_id'),
                'name' => $this->get_field_name('workflow_translation_widget_redirect_page_id'),
                'selected' => $redirect_page_id,
                'show_option_none' => __('— Startseite der übersetzten Webseite —', 'cms-workflow'),
                'option_none_value' => 0,
                'depth' => 2
            );
            echo Translation::get_dropdown_pages($data);
            ?>
            <br>
            <small><?php _e('Lokale Standardseite, die im Falle eines nicht-übersetzten Dokument angezeigt soll (standardmäßig ist die Startseite der übersetzten Webseite).', 'cms-workflow') ?></small>
        </p>
    <?php
    }
}

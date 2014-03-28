<?php

function workflow_get_translated_sites($args = array()) {
    global $cms_workflow;

    $defaults = array(
        'linktext' => 'text',
        'order' => 'blogid',
        'show_current_blog' => false,
        'echo' => false
    );

    $args = wp_parse_args($args, $defaults);
    extract($args, EXTR_SKIP);

    if (!isset($cms_workflow->translation) || !$cms_workflow->translation->module->options->activated)
        return;

    $translation = Workflow_Translation_Helper::instance();
    $output = $translation->get_translated_sites($args);

    if ($echo === true)
        echo $output;
    else
        return $output;
}

class Workflow_Translation_Helper {

    protected static $instance = null;

    public static function instance() {

        if (is_null(self::$instance))
            self::$instance = new self;

        return self::$instance;
    }

    public function get_translated_sites($args) {
        global $wp_query, $cms_workflow;

        extract($args, EXTR_SKIP);

        $output = '';
        $related_sites = $cms_workflow->translation->module->options->related_sites;

        if (!( 0 < count($related_sites) ))
            return $output;

        $default_post = get_post();

        if ($default_post)
            $current_post_id = $default_post->ID;
        elseif (!empty($wp_query->queried_object) && !empty($wp_query->queried_object->ID))
            $current_post_id = $wp_query->queried_object->ID;
        else
            $current_post_id = 0;

        $translate_from_lang = array();
        $remote_permalink = array();

        if ($current_post_id && isset($cms_workflow->post_versioning) && $cms_workflow->post_versioning->module->options->activated) {

            $post = get_post($current_post_id);
            
            $remote_post_metas = get_post_meta($post->ID, Workflow_Post_Versioning::version_remote_post_meta);

            if (empty($remote_post_metas))
                $remote_post_metas = get_post_meta($post->ID, Workflow_Post_Versioning::version_remote_parent_post_meta);

            foreach ($remote_post_metas as $remote_post_meta) {

                if (isset($remote_post_meta['blog_id']) && isset($remote_post_meta['post_id'])) {

                    if (switch_to_blog($remote_post_meta['blog_id'])) {

                        $remote_post = get_post($remote_post_meta['post_id']);


                        if (!is_null($remote_post) && ( 'publish' === $remote_post->post_status || ( 'private' === $remote_post->post_status && is_super_admin() ))) {
                            $translate_from_lang[$remote_post_meta['blog_id']] = get_option('WPLANG') ? get_option('WPLANG') : 'en_EN';
                            $remote_permalink[$remote_post_meta['blog_id']] = get_permalink($remote_post_meta['post_id']);
                        }

                        restore_current_blog();
                    }
                }
            }
        }

        if ($show_current_blog) {
            $current_blog = get_blog_details(get_current_blog_id());
            $sitelang = get_option('WPLANG') ? get_option('WPLANG') : 'en_EN';

            $related_sites[] = array(
                'blog_id' => get_current_blog_id(),
                'blogname' => $current_blog->blogname,
                'siteurl' => $current_blog->siteurl,
                'sitelang' => $sitelang
            );
        }

        if ($order == 'blogid')
            $related_sites = $this->array_orderby($related_sites, 'blog_id', SORT_ASC);
        else
            $related_sites = $this->array_orderby($related_sites, 'blogname', SORT_ASC);

        $output .= '<div class="workflow-language"><ul>';

        foreach ($related_sites as $site) {

            if ('text' == $linktext) {
                $display = $cms_workflow->translation->get_lang_name($site['sitelang']);
            } else {
                $sitelang = explode('_', $site['sitelang']);
                $display = end($sitelang);
            }
            
            $a_id = ( get_current_blog_id() == $site['blog_id'] ) ? 'id="lang-current-locale"' : '';
            $li_class = ( get_current_blog_id() == $site['blog_id'] ) ? 'class="lang-current"' : '';

            if (isset($remote_permalink[$site['blog_id']]))
                $link = $remote_permalink[$site['blog_id']];
            elseif (get_current_blog_id() == $site['blog_id'])
                $link = get_permalink($current_post_id);
            else
                $link = get_site_url($site['blog_id']);

            $output .= '<li ' . $li_class . '><a rel="alternate" hreflang="' . $site['sitelang'] . '" ' . $a_id . ' href="' . $link . '">' . $display . '</a></li>';
        }
        $output .= '</ul></div>';
        return $output;
    }

    private function array_orderby() {
        $args = func_get_args();
        $data = array_shift($args);
        foreach ($args as $n => $field) {
            if (is_string($field)) {
                $tmp = array();
                foreach ($data as $key => $row)
                    $tmp[$key] = $row[$field];
                $args[$n] = $tmp;
            }
        }
        $args[] = &$data;
        call_user_func_array('array_multisort', $args);
        return array_pop($args);
    }

}

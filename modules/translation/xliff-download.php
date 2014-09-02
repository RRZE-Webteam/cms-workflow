<?php

if (empty($_GET['xliff-attachment'])) {
    exit;
}

$post_id = (int) $_GET['xliff-attachment'];

if (empty($post_id)) {
    exit;
}

define('SHORTINIT', true);

require_once( dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))) . '/wp-load.php' );

wp_not_installed();

require( ABSPATH . WPINC . '/formatting.php' );
require( ABSPATH . WPINC . '/capabilities.php' );
require( ABSPATH . WPINC . '/user.php' );
require( ABSPATH . WPINC . '/meta.php' );
require( ABSPATH . WPINC . '/post.php' );
require( ABSPATH . WPINC . '/kses.php' );
require( ABSPATH . WPINC . '/ms-functions.php' );
require_once( ABSPATH . WPINC . '/general-template.php' );

wp_plugin_directory_constants();

if (is_multisite()) {
    ms_cookie_constants();
}

wp_cookie_constants();

require( ABSPATH . WPINC . '/pluggable.php' );

if (!is_user_logged_in()) {
    exit;
}

xliff_attachment($post_id);

function xliff_attachment($post_id) {
    $xliff_file = get_xliff_file($post_id);
    if (!$xliff_file) {
        exit;
    }

    $sitename = sanitize_key(get_bloginfo('name'));
    if (!empty($sitename)) {
        $sitename .= '-';
    }

    $filename = sprintf('%1$s%2$s-%3$s.xliff', $sitename, $post_id, date('Y-m-d'));

    header('Content-Description: File Transfer');
    header('Content-Disposition: attachment; filename=' . $filename);
    header('Content-Type: text/xml; charset=' . get_option('blog_charset'), true);

    echo $xliff_file;
    exit;
}

function get_xliff_file($post_id) {
    $blog_id = get_current_blog_id();
    $post = get_post($post_id);

    if (is_null($post)) {
        return false;
    }

    $source_language_code = get_post_meta($post_id, '_translate_from_lang_post_meta', true);
    if ($source_language_code == '') {
        return false;
    }

    $source_language_code = explode('_', $source_language_code);
    $source_language_code = $source_language_code[0];

    $language_code = get_post_meta($post_id, '_translate_to_lang_post_meta', true);
    if ($language_code == '') {
        return false;
    }

    $language_code = explode('_', $language_code);
    $language_code = $language_code[0];

    $elements = array(
        (object) array(
            'field_type' => 'title',
            'field_data' => $post->post_title,
            'field_data_translated' => $post->post_title,
        ),
        (object) array(
            'field_type' => 'body',
            'field_data' => $post->post_content,
            'field_data_translated' => $post->post_content,
        ),
        (object) array(
            'field_type' => 'excerpt',
            'field_data' => $post->post_excerpt,
            'field_data_translated' => $post->post_excerpt,
    ));

    $post_meta = get_post_meta($post_id);

    foreach ($post_meta as $post_meta_key => $post_meta_value) {
        if (strpos($post_meta_key, '_') === 0) {
            continue;
        }
        
        if (empty($post_meta_value) || count($post_meta_value) == 1) {
            $post_meta_value = get_post_meta($post_id, $post_meta_key, true);
        } 
        
        else {
            $post_meta_value = maybe_serialize($post_meta_value);
        }

        $elements[] = (object) array(
            'field_type' => '_meta_' . $post_meta_key,
            'field_data' => $post_meta_value,
            'field_data_translated' => $post_meta_value,            
        );
    }
    exit;
    $translation = (object) array(
        'original' => md5(sprintf('%d - %d', $blog_id, $post_id)),
        'source_language_code' => $source_language_code,
        'language_code' => $language_code,
        'elements' => $elements
    );

    $xliff_file = '<?xml version="1.0" encoding="utf-8" standalone="no"?>' . PHP_EOL;
    $xliff_file .= '<!DOCTYPE xliff PUBLIC "-//XLIFF//DTD XLIFF//EN" "http://www.oasis-open.org/committees/xliff/documents/xliff.dtd">' . PHP_EOL;
    $xliff_file .= '<xliff version="1.0">' . PHP_EOL;
    $xliff_file .= '   <file original="' . $translation->original . '" source-language="' . $translation->source_language_code . '" target-language="' . $translation->language_code . '" datatype="plaintext">' . PHP_EOL;
    $xliff_file .= '      <header></header>' . PHP_EOL;
    $xliff_file .= '      <body>' . PHP_EOL;

    foreach ($translation->elements as $element) {
        $field_data = $element->field_data;
        $field_data_translated = $element->field_data_translated;

        if ($field_data != '') {
            $field_data = str_replace(PHP_EOL, '<br class="xliff-newline" />', $field_data);
            $field_data_translated = str_replace(PHP_EOL, '<br class="xliff-newline" />', $field_data_translated);

            $xliff_file .= '         <trans-unit resname="' . $element->field_type . '" restype="String" datatype="text|html" id="' . $element->field_type . '">' . PHP_EOL;

            $xliff_file .= '            <source><![CDATA[' . $field_data . ']]></source>' . PHP_EOL;

            $xliff_file .= '            <target><![CDATA[' . $field_data_translated . ']]></target>' . PHP_EOL;

            $xliff_file .= '         </trans-unit>' . PHP_EOL;
        }
    }

    $xliff_file .= '      </body>' . PHP_EOL;
    $xliff_file .= '   </file>' . PHP_EOL;
    $xliff_file .= '</xliff>' . PHP_EOL;

    return $xliff_file;
}

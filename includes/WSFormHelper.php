<?php

namespace RRZE\Workflow;

defined('ABSPATH') || exit;

class WSFormHelper
{

    /**
     * Determines, if the Plugin WS Form Pro is active on this WordPress Single or Multisite Installation.
     * 
     * @return bool
     */
    public static function is_ws_form_pro_active():bool
    {
        if (!function_exists('is_plugin_active')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if (is_plugin_active('ws-form-pro/ws-form.php')) {
            return true;
        }

        return function_exists('is_plugin_active_for_network') && is_plugin_active_for_network('ws-form-pro/ws-form.php');
    }

    /**
     * Returns the available options for extending user roles with additional WS Form Capabilities.
     * Returns an Array of WS Form Capabilities as documented in https://wsform.com/knowledgebase/user-capabilities/
     *
     * @return array - Array of WS Form Capabilities
     */
    public static function get_ws_form_capability_groups(): array
    {
        return [
            [
                'label' => __('WS Form Formulare', 'cms-workflow'),
                'caps' => [
                    'create_form' => [
                        'label' => __('Formular erstellen', 'cms-workflow'),
                        'description' => __('Benutzer kann neue Formulare erstellen.', 'cms-workflow'),
                    ],
                    'delete_form' => [
                        'label' => __('Formular löschen', 'cms-workflow'),
                        'description' => __('Benutzer kann Formulare löschen.', 'cms-workflow'),
                    ],
                    'edit_form' => [
                        'label' => __('Formular bearbeiten', 'cms-workflow'),
                        'description' => __('Benutzer kann den Layout-Editor zum Bearbeiten von Formularen verwenden.', 'cms-workflow'),
                    ],
                    'export_form' => [
                        'label' => __('Formular exportieren', 'cms-workflow'),
                        'description' => __('Benutzer kann Formulare als JSON-Dateien exportieren.', 'cms-workflow'),
                    ],
                    'import_form' => [
                        'label' => __('Formular importieren', 'cms-workflow'),
                        'description' => __('Benutzer kann Formular-JSON-Dateien importieren.', 'cms-workflow'),
                    ],
                    'publish_form' => [
                        'label' => __('Formular veröffentlichen', 'cms-workflow'),
                        'description' => __('Benutzer kann Formulare veröffentlichen.', 'cms-workflow'),
                    ],
                    'read_form' => [
                        'label' => __('Formular anzeigen', 'cms-workflow'),
                        'description' => __('Benutzer kann Formulardaten einsehen.', 'cms-workflow'),
                    ],
                ],
            ],
            [
                'label' => __('WS Form Stile', 'cms-workflow'),
                'caps' => [
                    'create_form_style' => [
                        'label' => __('Stil erstellen', 'cms-workflow'),
                        'description' => __('Benutzer kann neue Formularstile erstellen.', 'cms-workflow'),
                    ],
                    'delete_form_style' => [
                        'label' => __('Stil löschen', 'cms-workflow'),
                        'description' => __('Benutzer kann Formularstile löschen.', 'cms-workflow'),
                    ],
                    'edit_form_style' => [
                        'label' => __('Stil bearbeiten', 'cms-workflow'),
                        'description' => __('Benutzer kann den Layout-Editor zum Bearbeiten von Formularstilen verwenden.', 'cms-workflow'),
                    ],
                    'export_form_style' => [
                        'label' => __('Stil exportieren', 'cms-workflow'),
                        'description' => __('Benutzer kann Formularstile als JSON-Dateien exportieren.', 'cms-workflow'),
                    ],
                    'import_form_style' => [
                        'label' => __('Stil importieren', 'cms-workflow'),
                        'description' => __('Benutzer kann Formularstil-JSON-Dateien importieren.', 'cms-workflow'),
                    ],
                    'publish_form_style' => [
                        'label' => __('Stil veröffentlichen', 'cms-workflow'),
                        'description' => __('Benutzer kann Formularstile veröffentlichen.', 'cms-workflow'),
                    ],
                    'read_form_style' => [
                        'label' => __('Stil anzeigen', 'cms-workflow'),
                        'description' => __('Benutzer kann Formularstil-Daten einsehen.', 'cms-workflow'),
                    ],
                ],
            ],
            [
                'label' => __('WS Formulareinsendungen', 'cms-workflow'),
                'caps' => [
                    'delete_submission' => [
                        'label' => __('Einsendung löschen', 'cms-workflow'),
                        'description' => __('Benutzer kann Einsendungen löschen.', 'cms-workflow'),
                    ],
                    'edit_submission' => [
                        'label' => __('Einsendung bearbeiten', 'cms-workflow'),
                        'description' => __('Benutzer kann Einsendungen bearbeiten.', 'cms-workflow'),
                    ],
                    'export_submission' => [
                        'label' => __('Einsendungen exportieren', 'cms-workflow'),
                        'description' => __('Benutzer kann Einsendungen als CSV-Dateien exportieren.', 'cms-workflow'),
                    ],
                    'read_submission' => [
                        'label' => __('Einsendungen anzeigen', 'cms-workflow'),
                        'description' => __('Benutzer kann Einsendungen einsehen.', 'cms-workflow'),
                    ],
                ],
            ],
        ];
    }
}
<?php

namespace RRZE\Workflow\Modules\UserGroups;

defined('ABSPATH') || exit;

use RRZE\Workflow\Main;
use WP_List_Table;

class UserGroupsListTable extends WP_List_Table
{
    public $main;

    public $callback_args;

    public function __construct(Main $main)
    {
        parent::__construct(array(
            'screen' => 'edit-usergroup',
            'plural' => 'user groups',
            'singular' => 'user group',
            'ajax' => true
        ));

        $this->main = $main;
    }

    public function prepare_items()
    {
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = array();

        $this->_column_headers = array($columns, $hidden, $sortable);

        $this->items = $this->main->user_groups->get_usergroups() ?: [];

        $this->set_pagination_args(array(
            'total_items' => !empty($this->items) ? count($this->items) : 0,
            'per_page' => !empty($this->items) ? count($this->items) : 0,
        ));
    }

    public function no_items()
    {
        _e('Keine Benutzergruppen gefunden.', 'cms-workflow');
    }

    public function get_columns()
    {
        $columns = array(
            'name' => __('Name', 'cms-workflow'),
            'description' => __('Beschreibung', 'cms-workflow'),
            'users' => __('Benutzer', 'cms-workflow'),
        );

        return $columns;
    }

    public function column_default($usergroup, $column_name)
    {
    }

    public function column_name($usergroup)
    {
        $output = '<strong><a href="' . esc_url($this->main->user_groups->get_link(array('action' => 'edit-usergroup', 'usergroup-id' => $usergroup->term_id))) . '">' . esc_html($usergroup->name) . '</a></strong>';

        $actions = array();
        $actions['edit edit-usergroup'] = sprintf('<a href="%1$s">' . __('Bearbeiten', 'cms-workflow') . '</a>', $this->main->user_groups->get_link(array('action' => 'edit-usergroup', 'usergroup-id' => $usergroup->term_id)));
        $actions['inline hide-if-no-js'] = '<a href="#" class="editinline">' . __('QuickEdit') . '</a>';
        $actions['delete delete-usergroup'] = sprintf('<a href="%1$s">' . __('Löschen', 'cms-workflow') . '</a>', $this->main->user_groups->get_link(array('action' => 'delete-usergroup', 'usergroup-id' => $usergroup->term_id)));

        $output .= $this->row_actions($actions, false);
        $output .= '<div class="hidden" id="inline_' . $usergroup->term_id . '">';
        $output .= '<div class="name">' . esc_html($usergroup->name) . '</div>';
        $output .= '<div class="description">' . esc_html($usergroup->description) . '</div>';
        $output .= '</div>';

        return $output;
    }

    public function column_description($usergroup)
    {
        return esc_html($usergroup->description);
    }

    public function column_users($usergroup)
    {
        return '<a href="' . esc_url($this->main->user_groups->get_link(array('action' => 'edit-usergroup', 'usergroup-id' => $usergroup->term_id))) . '">' . count($usergroup->user_ids) . '</a>';
    }

    public function single_row($usergroup)
    {
        static $row_class = '';
        $row_class = ($row_class == '' ? ' class="alternate"' : '');

        echo '<tr id="usergroup-' . $usergroup->term_id . '"' . $row_class . '>';
        echo $this->single_row_columns($usergroup);
        echo '</tr>';
    }

    public function inline_edit()
    {
?>
        <form method="get" action="">
            <table style="display: none">
                <tbody id="inlineedit">
                    <tr id="inline-edit" class="inline-edit-row" style="display: none">
                        <td colspan="<?php echo $this->get_column_count(); ?>" class="colspanchange">
                            <fieldset>
                                <div class="inline-edit-col">
                                    <h4><?php _e('QuickEdit', 'cms-workflow'); ?></h4>
                                    <label>
                                        <span class="title"><?php _e('Name', 'cms-workflow'); ?></span>
                                        <span class="input-text-wrap"><input type="text" name="name" class="ptitle" value="" maxlength="40" /></span>
                                    </label>
                                    <label>
                                        <span class="title"><?php _e('Beschreibung', 'cms-workflow'); ?></span>
                                        <span class="input-text-wrap"><input type="text" name="description" class="pdescription" value="" /></span>
                                    </label>
                                </div>
                            </fieldset>
                            <p class="inline-edit-save submit">
                                <a accesskey="c" href="#inline-edit" title="<?php _e('Abbrechen', 'cms-workflow'); ?>" class="cancel button-secondary alignleft"><?php _e('Abbrechen', 'cms-workflow'); ?></a>
                                <?php $update_text = __('Benutzergruppe aktualisieren', 'cms-workflow'); ?>
                                <a accesskey="s" href="#inline-edit" title="<?php echo esc_attr($update_text); ?>" class="save button-primary alignright"><?php echo $update_text; ?></a>
                                <img class="waiting" style="display:none;" src="<?php echo esc_url(admin_url('images/wpspin_light.gif')); ?>" alt="" />
                                <span class="error" style="display:none;"></span>
                                <?php wp_nonce_field('usergroups-inline-edit-nonce', 'inline_edit', false); ?>
                                <br class="clear" />
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </form>
<?php
    }
}

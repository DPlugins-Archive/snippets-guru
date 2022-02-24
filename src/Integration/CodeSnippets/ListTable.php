<?php

namespace Dplugins\SnippetsGuru\Integration\CodeSnippets;

use Code_Snippets\Snippet;

use function Code_Snippets\code_snippets;

class ListTable
{
    public function __construct()
    {
        add_filter('code_snippets/list_table/bulk_actions', [$this, 'register_list_table_bulk_actions'], 50);
        add_filter('code_snippets/list_table/sortable_columns', [$this, 'register_list_table_sortable_columns'], 50);
        add_filter('code_snippets/list_table/columns', [$this, 'register_list_table_columns'], 50);
        add_filter('code_snippets/list_table/row_actions', [$this, 'register_list_table_row_actions'], 50, 2);
        add_filter('code_snippets/list_table/column_name', [$this, 'register_list_table_column_name'], 50, 2);
    }

    public function register_list_table_bulk_actions($actions)
    {
        if (isset($_GET['type']) && 'cloud' === $_GET['type']) {
            $actions = [];
        }

        return $actions;
    }

    public function register_list_table_sortable_columns($sortable_columns)
    {
        if (isset($_GET['type']) && 'cloud' === $_GET['type']) {
            $sortable_columns = [];
        }

        return $sortable_columns;
    }

    public function register_list_table_columns($columns)
    {
        if (isset($_GET['type']) && 'cloud' === $_GET['type']) {
            unset($columns['cb']);
            unset($columns['activate']);
        }

        return $columns;
    }

    public function register_list_table_row_actions($actions, $snippet)
    {
        if (!(isset($_GET['type']) && 'cloud' === $_GET['type'])) return $actions;

        $actions = [];

        if (0 === $snippet->id) {
            $actions['do_preview'] = sprintf('<a href="%s">%s</a>', esc_url($this->get_action_link('preview', $snippet)), esc_html__('Preview', 'code-snippets'));

            $actions['do_import'] = sprintf(
                '<a href="%2$s" onclick="%3$s">%1$s</a>',
                esc_html__('Import', 'code-snippets'),
                esc_url($this->get_action_link('do_import', $snippet)),
                esc_js(sprintf(
                    'return confirm("%s");',
                    esc_html__('You are about to import the selected item.', 'code-snippets') . "\n" .
                        esc_html__("'Cancel' to stop, 'OK' to import.", 'code-snippets')
                ))
            );

            // if (!$snippet->cloud_public || $snippet->cloud_owned) {
            if (
                (!array_key_exists('is_public', $snippet->cloud_config) || !$snippet->cloud_config['is_public'])
                || (array_key_exists('owned', $snippet->cloud_config) && $snippet->cloud_config['owned'])
            ) {
                $actions['do_import_and_link'] = sprintf(
                    '<a href="%2$s" onclick="%3$s">%1$s</a>',
                    esc_html__('Import+Link', 'code-snippets'),
                    esc_url($this->get_action_link('do_import_and_link', $snippet)),
                    esc_js(sprintf(
                        'return confirm("%s");',
                        esc_html__('You are about to import the selected item. The selected item will be linked to the cloud item.', 'code-snippets') . "\n" .
                            esc_html__("'Cancel' to stop, 'OK' to import.", 'code-snippets')
                    ))
                );
            }
        } else {
            $actions['edit'] = sprintf('<a href="%s">%s</a>', esc_url($this->get_action_link('edit', $snippet)), esc_html__('Edit', 'code-snippets'));
        }

        return $actions;
    }

    public function register_list_table_column_name($html, Snippet $snippet)
    {
        if (!(isset($_GET['type']) && 'cloud' === $_GET['type'])) {
            return $snippet->cloud_uuid
			? $html . sprintf(
                '<span class="badge"><span class="dashicons dashicons-cloud" title="%s"></span> %s </span>', 
                esc_attr(__('Cloud Storage', 'code-snippets')),
                esc_html__('Cloud', 'code-snippets')
                )
            : $html;
        }

        $out = sprintf(
            '<span title="[UUID %s]">%s</span>',
            $snippet->cloud_uuid,
            esc_html($snippet->display_name)
        );

        if (0 !== $snippet->id) {
            /* Add a link to the snippet if it isn't an unreadable network-only snippet */
            if (is_network_admin() || !$snippet->network || current_user_can(code_snippets()->get_network_cap_name())) {

                $out = sprintf(
                    '<a href="%s" class="snippet-name">%s</a>',
                    code_snippets()->get_snippet_edit_url($snippet->id, $snippet->network ? 'network' : 'admin'),
                    $out
                );
            }

            $out .= '<span class="badge" title="' . esc_html__('a local copy is available', 'code-snippets') . '"> <span class="dashicons dashicons-cloud-saved"></span> ' . esc_html__('a local copy is available', 'code-snippets') . '</span>';
        }

        if (array_key_exists('is_public', $snippet->cloud_config) && $snippet->cloud_config['is_public']) {
            $out .= '<span class="badge" title="' . esc_html__('public', 'code-snippets') . '"> <span class="dashicons dashicons-admin-site-alt2"></span> ' . esc_html__('public', 'code-snippets') . '</span>';
        }

        return $out;
    }

    /**
     * Retrieve a URL to perform an action on a snippet
     *
     * @param string  $action  Name of action to produce a link for.
     * @param Snippet $snippet Snippet object to produce link for.
     *
     * @return string URL to perform action.
     */
    public function get_action_link($action, $snippet)
    {
        // redirect actions to the network dashboard for shared network snippets
        $local_actions = array('activate', 'activate-shared', 'run-once', 'run-once-shared');
        $network_redirect = $snippet->shared_network && !$this->is_network && !in_array($action, $local_actions, true);

        // edit links go to a different menu
        if ('edit' === $action) {
            return code_snippets()->get_snippet_edit_url($snippet->id, $network_redirect ? 'network' : 'self');
        }

        if ('preview' === $action) {
            return add_query_arg(
                [
                    'cloud_uuid' => explode(':', $snippet->cloud_uuid)[0],
                    'preview' => true,
                ],
                code_snippets()->get_snippet_edit_url($snippet->id, $network_redirect ? 'network' : 'self')
            );
        }

        $query_args = array('action' => $action, 'cloud_uuid' => $snippet->cloud_uuid);

        $url = $network_redirect ?
            add_query_arg($query_args, code_snippets()->get_menu_url('manage', 'network')) :
            add_query_arg($query_args);

        // add a nonce to the URL for security purposes
        return wp_nonce_url($url, 'code_snippets_manage_snippet_' . $snippet->id);
    }
}

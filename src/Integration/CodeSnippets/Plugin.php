<?php

namespace Dplugins\SnippetsGuru\Integration\CodeSnippets;

use Code_Snippets\Snippet;
use Dplugins\SnippetsGuru\Api\Blob as ApiBlob;
use Dplugins\SnippetsGuru\Api\Snippet as ApiSnippet;
use Dplugins\SnippetsGuru\Guru;
use Exception;
use wpdb;

use function Code_Snippets\code_snippets;
use function Code_Snippets\get_snippet;
use function Code_Snippets\Settings\get_setting;

class Plugin
{
    public function __construct()
    {
        add_filter('code_snippets_settings_fields', [$this, 'register_setting_fields'], 50);

        add_filter('code_snippets/plugins/types', [$this, 'register_custom_types'], 50);
        add_filter('code_snippets/plugins/type_descriptions', [$this, 'register_custom_type_descriptions'], 50);

        add_filter('code_snippets/admin/manage/type_names', [$this, 'register_custom_type_names'], 50);
        add_filter('code_snippets/admin/manage/type_names', [$this, 'register_custom_type_names'], 50);

        add_filter('code_snippets/list_table/bulk_actions', [$this, 'register_list_table_bulk_actions'], 50);
        add_filter('code_snippets/list_table/sortable_columns', [$this, 'register_list_table_sortable_columns'], 50);
        add_filter('code_snippets/list_table/columns', [$this, 'register_list_table_columns'], 50);
        add_filter('code_snippets/list_table/row_actions', [$this, 'register_list_table_row_actions'], 50, 2);
        add_filter('code_snippets/list_table/column_name', [$this, 'register_list_table_column_name'], 50, 2);

        add_action('code_snippets/create_snippet', [$this, 'schedule_push'], 10, 2);
        add_action('code_snippets/update_snippet', [$this, 'schedule_push'], 10, 2);

        add_action('code_snippets_edit_snippet', [$this, 'render_cloud_setting'], 50, 1);

        add_filter('snippets_guru/retrieve_auth_token', [$this, 'retrieve_auth_token'], 50);
        add_action('snippets_guru/code_snippets/push', [$this, 'push_snippet'], 10, 3);
    }

    /**
     * Get the JWT Auth Token from the setting fields.
     * 
     * @return string|bool the JWT Auth Token or false if not found
     */
    public function retrieve_auth_token()
    {
        $auth_token = wp_cache_get('settings_cloud_auth_token', 'code_snippets');

        if ($auth_token) {
            return $auth_token;
        }

        if (!$auth_token) {
            $auth_token = get_setting('cloud', 'cloud_auth_token');
        }

        if ($auth_token) {
            wp_cache_set('settings_cloud_auth_token', $auth_token, 'code_snippets', HOUR_IN_SECONDS);
        }

        return $auth_token;
    }

    /**
     * Schedule cloud sync on snippet creation and update events.
     * 
     * @param int $id
     * @param string $table
     * @return void
     * @throws Exception 
     */
    public function schedule_push($id, $table)
    {
        $snippet = get_snippet($id);

        if (!$snippet->cloud_sync || !$this->retrieve_auth_token()) {
            return;
        }

        wp_schedule_single_event(time() + 5, 'snippets_guru/code_snippets/push', [
            'id' => $id,
            'table' => $table,
            'uniq' => bin2hex(random_bytes(8)),
        ]);
    }

    /**
     * Push a snippet to the cloud.
     * 
     * @param int $id
     * @param string $table
     * @param string $uniq
     * @return void
     * @throws Exception
     */
    public function push_snippet($id, $table, $uniq)
    {
        $snippet = get_snippet($id);

        $snippet_args = [
            'namespace' => 'code-snippets',
            'name' => $snippet->name,
            'description' => $snippet->desc,
            'isPublic' => false,
            'meta' => [
                'scope' => $snippet->scope,
                'priority' => $snippet->priority,
                'tags' => $snippet->tags,
                'description_format' => 'html',
            ]
        ];

        if (empty($snippet->cloud_uuid)) {
            $snippet_args['blobs'] = [
                [
                    'content' => $snippet->code,
                ],
            ];

            $snippet_resp = ApiSnippet::getInstance()
                ->save($snippet_args);

            $data = array(
                'cloud_uuid' => sprintf('%s:%s', $snippet_resp->uuid, $snippet_resp->blobs[0]->uuid),
            );

            /** @var wpdb $wpdb */
            global $wpdb;

            /* Update the snippet data */
            $wpdb->update($table, $data, ['id' => $snippet->id], null, ['%d']);
        } else {
            list($snippet_guid, $blob_guid) = explode(':', $snippet->cloud_uuid);

            $snippet_args['blobs'] = [
                [
                    '@id' => ApiBlob::getInstance()->getBlobIRI($blob_guid),
                    'content' => $snippet->code,
                ],
            ];

            $snippet_resp = ApiSnippet::getInstance()
                ->update($snippet_guid, $snippet_args);
        }

        wp_cache_set('resource_snippet_' . $snippet->id, $snippet_resp, 'snippets_guru/code_snippets');
    }

    /**
     * Register setting fields for the Cloud Sync setting.
     * 
     * @param array $fields
     * @return array
     */
    public function register_setting_fields($fields)
    {
        /* translators: %s: Hyperlink of Snippets Guru auth token retrieval page */
        $cloud_auth_token_desc = esc_html__('Get the authorization token from the %s site.', 'code-snippets');

        $anchor_text = sprintf('<a href="%s" target="_blank">Snippets Guru</a>', Guru::instance()->getBaseUrl() . '/auth/auth_token');

        $fields['cloud'] = [
            'cloud_auth_token' => [
                'name'       => __('Authorization Token', 'code-snippets'),
                'type'       => 'text',
                'desc'       => sprintf($cloud_auth_token_desc, $anchor_text),
                'default'    => '',
            ],
            'sync_new_snippet' => [
                'name'       => __('Sync New Snippet', 'code-snippets'),
                'type'       => 'checkbox',
                'desc'       => __('Sync new created snippets to Snippets Guru cloud service.', 'code-snippets'),
                'default'    => '',
            ],
        ];

        return $fields;
    }

    /**
     * Render the setting for cloud sync on Snippets Editor page.
     *
     * @param Snippet $snippet The snippet currently being edited.
     * @return void
     */
    public function render_cloud_setting(Snippet $snippet)
    {
        $authTokenConfigured = $this->retrieve_auth_token() ? true : false;

        if ($snippet->id === 0) {
            $active_sync = get_setting('cloud', 'sync_new_snippet') ? true : false;
        } else {
            $active_sync = $snippet->cloud_sync;
        }

        /* translators: %s: Hyperlink of Snippets Guru cloud */
        $input_label = esc_html__('Allow this snippet to be saved to %s cloud', 'code-snippets');

        $anchor_text = sprintf('<a href="%s" target="_blank">Snippets Guru</a>', Guru::instance()->getBaseUrl());
?>
        <h2 style="margin: 25px 0 10px;">
            <?php esc_html_e('Cloud Sync', 'code-snippets'); ?>
        </h2>

        <p class="html-shortcode-options">
            <label><input type="checkbox" value="1" name="snippet_cloud_sync" <?php checked($active_sync); ?> <?php echo !$authTokenConfigured ? 'disabled' : '' ?>> <span class="dashicons dashicons-cloud-upload"></span> <?php echo sprintf($input_label, $anchor_text); ?> </label>
        </p>

        <?php

        if (!$authTokenConfigured) {
            /* translators: %$1s: Anchor open tag, %$2s: Anchor close tag */
            $hint_text = esc_html__('You need to get an authorization token from the Snippets Guru site to be able to sync snippets to the cloud. Update your plugin %s settings page %s', 'code-snippets');

            $settings_anchor_open_tag = sprintf('<a href="%s" target="_blank">', esc_url(code_snippets()->get_menu_url('settings')));
        ?>
            <p class="html-shortcode-options">
                <?php echo sprintf($hint_text, $settings_anchor_open_tag, '</a>'); ?>
            </p>
<?php
        }
    }

    /**
     * @return array
     */
    public function register_custom_types($types)
    {
        $types['cloud'] = __('Snippets Guru', 'code-snippets');

        return $types;
    }

    /**
     * @param array $descriptions The description array.
     * @return array
     */
    public function register_custom_type_descriptions($descriptions)
    {
        $descriptions['cloud'] = __('The snippet stored on cloud storage. The cloud service is powered by Snippets Guru.', 'code-snippets');

        return $descriptions;
    }

    /**
     * @param array $type_names Snippet type name.
     * @return array
     */
    public function register_custom_type_names($type_names)
    {
        $type_names['cloud'] = __('Snippets Guru.', 'code-snippets');

        return $type_names;
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

            if (!$snippet->cloud_public || $snippet->cloud_owned) {
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

    public function register_list_table_column_name($html, $snippet)
    {
        if (!(isset($_GET['type']) && 'cloud' === $_GET['type'])) return $html;


        $out = esc_html($snippet->display_name);

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

        if ($snippet->cloud_public) {
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

        $query_args = array('action' => $action, 'cloud_uuid' => $snippet->cloud_uuid);

        $url = $network_redirect ?
            add_query_arg($query_args, code_snippets()->get_menu_url('manage', 'network')) :
            add_query_arg($query_args);

        // add a nonce to the URL for security purposes
        return wp_nonce_url($url, 'code_snippets_manage_snippet_' . $snippet->id);
    }
}

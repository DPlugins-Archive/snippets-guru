<?php

namespace Dplugins\SnippetsGuru\Integration;

use Code_Snippets\Snippet;
use Dplugins\SnippetsGuru\Api\Blob as ApiBlob;
use Dplugins\SnippetsGuru\Api\Snippet as ApiSnippet;
use Dplugins\SnippetsGuru\Guru;
use wpdb;

use function Code_Snippets\code_snippets;
use function Code_Snippets\get_snippet;
use function Code_Snippets\Settings\get_setting;

class CodeSnippets
{
    public function __construct()
    {
        add_filter('snippets_guru/retrieve_auth_token', [$this, 'retrieve_auth_token']);
        add_filter('code_snippets_settings_fields', [$this, 'register_setting_fields']);

        add_action('code_snippets/create_snippet', [$this, 'schedule_push'], 10, 2);
        add_action('code_snippets/update_snippet', [$this, 'schedule_push'], 10, 2);
        add_action('snippets_guru/code_snippets/push', [$this, 'push_snippet'], 10, 3);

        add_action('code_snippets_edit_snippet', [$this, 'render_cloud_setting'], 50, 1);
    }

    /**
     * Get JWT Auth Token.
     * 
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

        if (!$auth_token) {
            $auth_token = Guru::instance()->retrieveAuthToken();
        }

        wp_cache_set('settings_cloud_auth_token', $auth_token, 'code_snippets', HOUR_IN_SECONDS);

        return $auth_token;
    }

    public function register_setting_fields($fields)
    {
        /* translators: %s: Hyperlink of Snippets Guru auth token retrieval page */
        $cloud_auth_token_desc = esc_html__('Get the authorization token from the %s site.', 'code-snippets');

        $anchor_text = sprintf('<a href="%s" target="_blank">Snippets Guru</a>', Guru::instance()->getBaseUrl() . '/api/auth_token');

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

    public function schedule_push($id, $table)
    {
        $snippet = get_snippet($id);

        if (!$snippet->cloud_sync) {
            return;
        }

        wp_schedule_single_event(time() + 5, 'snippets_guru/code_snippets/push', [
            'id' => $id,
            'table' => $table,
            'uniq' => bin2hex(random_bytes(8)),
        ]);
    }

    public function push_snippet($id, $table, $uniq)
    {
        if (!$this->retrieve_auth_token()) {
            return;
        }

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
     * Render the setting for cloud sync on Snippets Editor page.
     *
     * @param Snippet $snippet The snippet currently being edited.
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
}

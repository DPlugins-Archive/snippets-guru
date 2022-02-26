<?php

namespace Dplugins\SnippetsGuru\Integration\CodeSnippets;

use Code_Snippets\Snippet;
use Dplugins\SnippetsGuru\Api\Blob as ApiBlob;
use Dplugins\SnippetsGuru\Api\Snippet as ApiSnippet;
use wpdb;

use function Code_Snippets\get_snippet;
use function Code_Snippets\Settings\get_setting;
use function Code_Snippets\snippets_guru;

class Operation
{
    public function __construct()
    {
        add_filter('code_snippets/save/post_set_fields', [$this, 'save_post_set_fields'], 50, 1);

        add_action('code_snippets/create_snippet', [$this, 'schedule_push'], 10, 2);
        add_action('code_snippets/update_snippet', [$this, 'schedule_push'], 10, 2);
        add_action('snippets_guru/code_snippets/push', [$this, 'push_snippet'], 10, 3);
    }

    /**
     * Schedule cloud sync on snippet creation and update events.
     * 
     * @param Snippet $snippet
     * @param string $table
     * @return void
     * @throws Exception 
     */
    public function schedule_push(Snippet $snippet, $table)
    {
        $user = snippets_guru()->getUser();

        if (
            !$user
            || !$user->billing->isActive
            || (!array_key_exists('push_change', $snippet->cloud_config) || !$snippet->cloud_config['push_change'])
        ) {
            return;
        }

        $async = get_setting('cloud', 'async_push') ? true : false;

        wp_cache_delete('snippet_' . $snippet->id, 'code_snippets');
        $snippet = get_snippet($snippet->id);

        if ($async) {
            wp_schedule_single_event(time() + 5, 'snippets_guru/code_snippets/push', [
                'snippet' => $snippet,
                'table' => $table,
                'uniq' => bin2hex(random_bytes(8)),
            ]);
        } else {
            do_action('snippets_guru/code_snippets/push', $snippet, $table);
        }
    }

    /**
     * Push a snippet to the cloud.
     * 
     * @param Snippet $snippet
     * @param string $table
     * @param string|null $uniq Unique token to prevent duplicate wp_schedule_single_event() calls
     * @return void
     * @throws Exception
     */
    public function push_snippet($snippet, $table, $uniq = null)
    {
        $snippet_args = [
            'namespace' => 'code-snippets',
            'name' => $snippet->name,
            'description' => $snippet->desc,
            'isPublic' => array_key_exists('is_public', $snippet->cloud_config) ? $snippet->cloud_config['is_public'] : false,
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

            $snippet_resp = ApiSnippet::instance()
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
                    '@id' => ApiBlob::instance()->getBlobIRI($blob_guid),
                    'content' => $snippet->code,
                ],
            ];

            $snippet_resp = ApiSnippet::instance()
                ->update($snippet_guid, $snippet_args);
        }

        wp_cache_set('resource_snippet_' . $snippet->id, $snippet_resp, 'snippets_guru/code_snippets');
    }

    /**
     * Set the snippet fields before saving.
     * 
     * @param Snippet $snippet
     * @return Snippet
     */
    public function save_post_set_fields(Snippet $snippet)
    {
        $snippet->cloud_config = [
            'push_change' => isset($_POST['cloud_push_change']) && $_POST['cloud_push_change'] === '1',
            'is_public' => isset($_POST['cloud_is_public']) && $_POST['cloud_is_public'] === '1',
        ];

        /* Reset the snippet's cloud UUID */
        if (isset($_POST['reset_cloud_uuid'])) {
            $snippet->cloud_uuid = '';
        }

        return $snippet;
    }
}

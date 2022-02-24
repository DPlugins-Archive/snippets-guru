<?php

namespace Dplugins\SnippetsGuru\Integration\CodeSnippets;

use Code_Snippets\Snippet;
use Dplugins\SnippetsGuru\Api\Snippet as ApiSnippet;

use function Code_Snippets\code_snippets;
use function Code_Snippets\get_snippet;
use function Code_Snippets\save_snippet;
use function Code_Snippets\Settings\get_setting;
use function Code_Snippets\snippets_guru;

class ManageSnippet
{
    public function __construct()
    {
        add_filter('code_snippets/plugins/types', [$this, 'snippet_types'], 50);
        add_filter('code_snippets/plugins/type_descriptions', [$this, 'snippet_type_descriptions'], 50);

        add_filter('code_snippets/admin/manage/type_names', [$this, 'views_manage_type_names'], 50);
        add_filter('code_snippets/admin/manage/type_names', [$this, 'views_manage_type_names'], 50);

        add_filter('code_snippets/admin/submit_actions', [$this, 'get_actions_list'], 50, 3);
        add_action('code_snippets/admin/process_actions', [$this, 'process_actions'], 50, 3);
        add_action('code_snippets_edit_snippet', [$this, 'render_cloud_setting'], 50, 1);

        add_filter('code_snippets_codemirror_atts', [$this, 'codemirror_atts'], 50);
        add_filter('code_snippets/admin/description_editor_settings', [$this, 'description_editor_settings'], 50);
        add_filter('code_snippets/admin/load_snippet_data', [$this, 'load_snippet_data'], 50);
        add_filter('code_snippets/extra_save_buttons', [$this, 'extra_save_buttons'], 50);

        add_action('code_snippets_process_cloud_action', [$this, 'process_cloud_action'], 50);
    }

    /**
     * Retrieve a list of available snippet types and their labels.
     * 
     * @return array
     */
    public function snippet_types($types)
    {
        $types['cloud'] = __('Snippets Guru', 'code-snippets');

        return $types;
    }

    /**
     * Retrieve the description for a particular snippet type.
     * 
     * @param array $descriptions The description array.
     * @return array
     */
    public function snippet_type_descriptions($descriptions)
    {
        $descriptions['cloud'] = __('The snippet stored on cloud storage. The cloud service is powered by Snippets Guru.', 'code-snippets');

        return $descriptions;
    }

    /**
     * @param array $type_names Snippet type name.
     * @return array
     */
    public function views_manage_type_names($type_names)
    {
        $type_names['cloud'] = __('Snippets Guru.', 'code-snippets');

        return $type_names;
    }

    public function load_snippet_data(Snippet $snippet)
    {
        if (isset($_REQUEST['preview']) && isset($_REQUEST['cloud_uuid'])) {
            $cloud_uuid = $_REQUEST['cloud_uuid'];

            $resp = wp_cache_get("cloud_snippet_{$cloud_uuid}", 'code_snippets');

            if (!$resp) {
                $resp = ApiSnippet::getInstance()->get($cloud_uuid);
                wp_cache_set("cloud_snippet_{$cloud_uuid}", $resp, 'code_snippets', 30);
            }

            $snippet = new Snippet([
                'id'             => null,
                'name'           => $resp->name,
                'desc'           => $resp->description,
                'code'           => $resp->blobs[0]->excerpt,
                'tags'           => $resp->meta->tags,
                'scope'          => $resp->meta->scope,
                'active'         => false,
                'priority'       => $resp->meta->priority,
                'network'        => null,
                'shared_network' => null,
                'modified'       => null,
                'cloud_uuid'     => sprintf('%s:%s', $resp->uuid, $resp->blobs[0]->uuid),
                'cloud_config'     => [
                    'push_change' => false,
                    'is_public' => $resp->isPublic,
                    'owned' => property_exists($resp, 'person'),
                ],
            ]);
        }

        return $snippet;
    }

    public function process_cloud_action()
    {
        /* If so, then perform the requested action and inform the user of the result */
        $result = $this->perform_action($_GET['cloud_uuid'], sanitize_key($_GET['action']));

        if ($result) {
            wp_redirect(esc_url_raw(add_query_arg('result', $result)));
            exit;
        }
    }

    /**
     * Perform an action on a single snippet.
     *
     * @param int    $id     Snippet resource uuid.
     * @param string $action Action to perform.
     *
     * @return bool|string Result of performing action
     */
    private function perform_action($id, $action)
    {
        switch ($action) {
            case 'import':
                $imported = $this->do_import($id, isset($_GET['link']));
                return $imported ? 'imported' : false;
            default:
                break;
        }

        return false;
    }

    private function do_import($id, $link = false)
    {
        try {
            $resp_snippet = wp_cache_get("cloud_snippet_{$id}", 'code_snippets');

            if (!$resp_snippet) {
                $resp_snippet = ApiSnippet::getInstance()->get($id);
                wp_cache_set("cloud_snippet_{$id}", $resp_snippet, 'code_snippets', 30);
            }

            $resp_blobs = wp_cache_get("cloud_blobs_{$id}", 'code_snippets');

            if (!$resp_blobs) {
                $resp_blobs = ApiSnippet::getInstance()->blobs($id);
                wp_cache_set("cloud_blobs_{$id}", $resp_blobs, 'code_snippets', 30);
            }

            $blob = $resp_blobs->{'hydra:member'}[0];

            $snippet = new Snippet([
                'id'             => null,
                'name'           => $resp_snippet->name,
                'desc'           => $resp_snippet->description,
                'code'           => $blob->content,
                'tags'           => $resp_snippet->meta->tags,
                'scope'          => $resp_snippet->meta->scope,
                'active'         => false,
                'priority'       => $resp_snippet->meta->priority,
                'network'        => null,
                'shared_network' => null,
                'modified'       => null,
                'cloud_config'   => [
                    'push_change' => false,
                    'is_public' => $resp_snippet->isPublic,
                    'owned' => property_exists($resp_snippet, 'person'),
                ],
            ]);

            if ($link) {
                $snippet->cloud_uuid = sprintf('%s:%s', $resp_snippet->uuid, $blob->uuid);
            }

            save_snippet($snippet);
        } catch (\Throwable $th) {
            //throw $th;
            return false;
        }

        return true;
    }

    public function codemirror_atts($atts)
    {
        if (isset($_REQUEST['preview']) && isset($_REQUEST['cloud_uuid'])) {
            $atts['readOnly'] = 'nocursor';
        }

        return $atts;
    }

    public function description_editor_settings($settings)
    {
        if (isset($_REQUEST['preview']) && isset($_REQUEST['cloud_uuid'])) {
            $settings['tinymce'] = false;
            $settings['quicktags'] = false;
        }

        return $settings;
    }

    /**
     * @param bool $condition
     * @return bool
     */
    public function extra_save_buttons($condition)
    {
        if (isset($_REQUEST['preview']) && isset($_REQUEST['cloud_uuid'])) {
            return false;
        }

        return $condition;
    }

    /**
     * Retrieve a list of submit actions for a given snippet
     *
     * @param array   $actions Two-dimensional array with action name keyed to description.
     * @param Snippet $snippet       The snippet currently being edited.
     * @param bool    $extra_actions Whether to include additional actions alongside save actions.
     *
     * @return array Two-dimensional array with action name keyed to description.
     */
    public function get_actions_list($actions, $snippet, $extra_actions)
    {
        if (isset($_REQUEST['preview']) && isset($_REQUEST['cloud_uuid'])) {
            return [];
        }

        if ($snippet->cloud_uuid) {
            $actions['reset_cloud_uuid'] = __('Reset Cloud UUID', 'code-snippets');
        }

        return $actions;
    }

    /**
     * Process the actions for a given snippet.
     *
     * @param int     $snippet_id The ID of the snippet currently being edited.
     */
    public function process_actions($snippet_id)
    {
        if (isset($_POST['reset_cloud_uuid'])) {
            $snippet = get_snippet($snippet_id);

            $snippet->cloud_uuid = null;

            save_snippet($snippet);

            /* Redirect to edit snippet page */
            $redirect_uri = add_query_arg(
                array('id' => $snippet_id, 'result' => 'updated'),
                code_snippets()->get_menu_url('edit')
            );

            wp_redirect(esc_url_raw($redirect_uri));
            exit;
        }
    }

    /**
     * Render the setting for cloud sync on Snippets Editor page.
     *
     * @param Snippet $snippet The snippet currently being edited.
     * @return void
     */
    public function render_cloud_setting(Snippet $snippet)
    {
        if (isset($_REQUEST['preview']) && isset($_REQUEST['cloud_uuid'])) {
            printf(
                '
                <script>
                    jQuery(document).ready(function(){
                        jQuery("#snippet-form :input").prop("disabled", true);
                        jQuery("h1").html(`%s %s`);
                        jQuery("[for=snippet_code]").html(`%s`);
                    });

                </script>',
                esc_html__('Preview', 'code-snippets'),
                sprintf(
                    '<span style="color:rgb(156 163 175);">[UUID %s]</span>',
                    explode(':', $snippet->cloud_uuid)[0]
                ),
                sprintf(
                    'Code <span style="color:rgb(156 163 175);">(%s)</span>',
                    esc_html__('The first few lines of the code for preview purposes', 'code-snippets')
                )
            );

            return;
        }

        $user = snippets_guru()->getUser();

        // section title
        printf(
            '<h2 style="margin: 25px 0 10px;">
                %s
            </h2>',
            esc_html__('Cloud Storage', 'code-snippets')
        );

        if (!$user) {
            printf(
                '<p class="html-shortcode-options">
                    %s
                </p>',
                sprintf(
                    esc_html__('Please activate this feature on your plugin %s settings page %s', 'code-snippets'),
                    '<a href="' . esc_url(code_snippets()->get_menu_url('settings')) . '" target="_blank">',
                    '</a>'
                ),
            );
        } else {
            if ($snippet->id === 0) {
                $push_change = get_setting('cloud', 'push_new_snippet') ? true : false;
                $is_public = false;
            } else {
                $push_change = array_key_exists('push_change', $snippet->cloud_config) ? $snippet->cloud_config['push_change'] : false;
                $is_public = array_key_exists('is_public', $snippet->cloud_config) ? $snippet->cloud_config['is_public'] : false;
            }

            // field: push_change
            printf(
                '<p class="html-shortcode-options" style="display: flex;flex-direction: column;">
                    <label>
                        <input type="checkbox" value="1" name="cloud_push_change" %s %s>
                        <span class="dashicons dashicons-cloud-upload"></span> %s
                    </label>
                    %s
                </p>',
                checked($push_change, true, false),
                !$user ? 'disabled' : '',
                sprintf(
                    /* translators: %s: Hyperlink of Snippets Guru cloud */
                    esc_html__('Allow this snippet to be saved to the %s cloud', 'code-snippets'),
                    '<a href="' . snippets_guru()->getBaseUrl() . '" target="_blank">Snippets Guru</a>'
                ),
                $snippet->cloud_uuid ? sprintf(
                    '<a style="color:rgb(156 163 175);">[UUID %s]</a>',
                    $snippet->cloud_uuid
                ) : ''
            );

            // field: is_public
            printf(
                '<p class="html-shortcode-options">
                    <label>
                        <input type="checkbox" value="1" name="cloud_is_public" %s %s > 
                        <span class="dashicons dashicons-admin-site-alt2"></span> %s 
                    </label>',
                checked($is_public, true, false),
                !$user ? 'disabled' : '',
                esc_html__('Make this snippet public', 'code-snippets')
            );

            // user info
            printf(
                '<table cellspacing="0" border="0">
                    <tbody>
                        <tr>
                            <th style="padding:0px;padding-right:20px;" scope="row" align="left">%s</th>
                            <td style="padding:0px;" align="left">
                                <p style="margin:0px;">%s (%s)</p>
                            </td>
                        </tr>
                        <tr>
                            <th style="padding:0px;padding-right:20px;" scope="row" align="left">%s</th>
                            <td style="padding:0px;" align="left">
                                <p style="margin:0px;">%s</p>
                            </td>
                        </tr>
                        <tr>
                            <th style="padding:0px;padding-right:20px;" scope="row" align="left">%s</th>
                            <td style="padding:0px;" align="left">
                                <p style="margin:0px;">%s</p>
                            </td>
                        </tr>
                    </tbody>
                </table>',
                __('Logged in As', 'code-snippets'),
                $user->username,
                $user->email,
                __('Subscription Status', 'code-snippets'),
                $user->billing->isActive ? '<a style="color:green;">' . __('Active', 'code-snippets') . '</a>' : '<a style="color:red;">' . __('Inactive', 'code-snippets') . '</a>',
                __('Subscription Expire Date', 'code-snippets'),
                date('Y-m-d', strtotime($user->billing->expiredAt))
            );
        }

        if (isset($_REQUEST['preview']) && isset($_REQUEST['cloud_uuid'])) {
            printf(
                '
                <script>
                    jQuery(document).ready(function(){
                        jQuery("#snippet-form :input").prop("disabled", true);
                        jQuery("h1").text("%s");
                    });

                </script>',
                esc_html__('Preview', 'code-snippets')
            );
        }
    }
}

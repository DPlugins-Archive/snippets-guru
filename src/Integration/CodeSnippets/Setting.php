<?php

namespace Dplugins\SnippetsGuru\Integration\CodeSnippets;

use function Code_Snippets\Settings\get_setting;
use function Code_Snippets\snippets_guru;

class Setting
{
    /**
     * The section of the settings page to which this setting belongs.
     * 
     * @var string
     */
    private static $section = 'cloud';

    public function __construct()
    {
        add_filter('code_snippets_settings_sections', [$this, 'settings_sections']);
        add_filter('code_snippets_settings_fields', [$this, 'settings_fields'], 50);

        add_filter('snippets_guru/retrieve_auth_token', [$this, 'retrieve_auth_token'], 50);
    }

    /**
     * Get the JWT Auth Token from the setting fields.
     * 
     * @return string|bool the JWT Auth Token or false if not found
     */
    public static function retrieve_auth_token()
    {
        $auth_token = wp_cache_get('settings_cloud_auth_token', 'code_snippets');

        if ($auth_token) {
            return $auth_token;
        }

        if (!$auth_token) {
            $auth_token = get_setting(self::$section, 'cloud_auth_token');
        }

        if (!$auth_token) {
            return false;
        }

        wp_cache_set('settings_cloud_auth_token', $auth_token, 'code_snippets', HOUR_IN_SECONDS);

        return $auth_token;
    }

    public function settings_sections($sections)
    {
        $sections[self::$section] = __('Cloud Storage', 'code-snippets');

        return $sections;
    }

    /**
     * Register setting fields for the Cloud Sync setting.
     * 
     * @param array $fields
     * @return array
     */
    public function settings_fields($fields)
    {
        $fields[self::$section] = [
            'cloud_auth_token' => [
                'name'       => __('Authorization Token', 'code-snippets'),
                'type'       => 'callback',
                'default'    => false,
                'render_callback'   => [$this, 'render_auth_token_field'],
                'sanitize_callback' => [$this, 'sanitize_auth_token_field'],
            ],
            'push_new_snippet' => [
                'name'       => __('Push New Snippet', 'code-snippets'),
                'type'       => 'checkbox',
                'desc'       => sprintf(__('Push new created snippets to the Snippets Guru cloud service.', 'code-snippets')),
                'default'    => '',
                'label' => __('Turn on Push')
            ],
            'async_push' => [
                'name'       => __('Async Push', 'code-snippets'),
                'type'       => 'checkbox',
                'desc'       => sprintf(__('Send the data to the Snippets Guru cloud service asynchronous.', 'code-snippets')),
                'default'    => '',
                'label' => __('Delayed Push')
            ],
        ];

        return $fields;
    }

    public function render_auth_token_field()
    {
        $field_id = 'cloud_auth_token';

        printf(
            '<input type="text" name="%s" value="%s" class="regular-text">',
            esc_attr(sprintf('code_snippets_settings[%s][%s]', self::$section, $field_id)),
            esc_attr(get_setting(self::$section, $field_id))
        );

        /* translators: %s: Hyperlink of Snippets Guru auth token retrieval page */
        $desc = esc_html__('Get the authorization token from the %s site.', 'code-snippets');

        $auth_link = sprintf('<a href="%s" target="_blank">Snippets Guru</a>', snippets_guru()->getUrl('/auth/auth_token'));

        printf(
            '<p class="description">%s</p>',
            sprintf($desc, $auth_link)
        );

        $user = snippets_guru()->getUser();

        if (!$user) {
            return;
        }

        printf(
            '<table cellspacing="0" border="0">
                <tbody>
                    <tr>
                        <th style="padding:0px;" scope="row" align="left">%s</th>
                        <td style="padding:0px;" align="left">
                            <p>%s (%s)</p>
                        </td>
                    </tr>
                    <tr>
                        <th style="padding:0px;" scope="row" align="left">%s</th>
                        <td style="padding:0px;" align="left">
                            <p>%s</p>
                        </td>
                    </tr>
                    <tr>
                        <th style="padding:0px;" scope="row" align="left">%s</th>
                        <td style="padding:0px;" align="left">
                            <p>%s</p>
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

    public function sanitize_auth_token_field($input_value)
    {
        $input_value = trim(sanitize_text_field($input_value));

        if (!$input_value) {
            delete_transient('code_snippets_cloud_user');
            return '';
        }

        try {
            $user = snippets_guru()->getUser($input_value, true);
        } catch (\Throwable $th) {
            if ($th->getCode() > 400) {
                $message = $th->getMessage();

                switch ($th->getCode()) {
                    case 401:
                        $message = __('Snippets Guru: Invalid authorization token.', 'code-snippets');
                        break;

                    default:
                        break;
                }

                add_settings_error(
                    'code-snippets-settings-notices',
                    'invalid-cloud-auth-token',
                    $message,
                    'warning'
                );
            }

            return '';
        }

        if ($user->billing->isActive === false) {
            /* translators: %s: Hyperlink of Snippets Guru site */
            $message = __('Snippets Guru: Your account has no active subscription. To extend your subscription, please purchase on %s', 'code-snippets');

            add_settings_error(
                'code-snippets-settings-notices',
                'expire-cloud-subscription',
                sprintf($message, '<a href="' . snippets_guru()->getUrl() . '" target="_blank">' . snippets_guru()->getUrl() . '</a>'),
                'warning'
            );
        }

        return $input_value;
    }
}

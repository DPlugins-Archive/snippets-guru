<?php

namespace Dplugins\SnippetsGuru;

use Dplugins\SnippetsGuru\Integration\CodeSnippets\Plugin as CodeSnippets;
use Exception;

/**
 * Snippets Guru
 * 
 * The cloud features for Code Snippets and Script Organizer plugin.
 *
 * @package             Snippets_Guru
 * @author              dplugins <mail@snippets.guru>
 * @link                https://snippets.guru
 * @since               1.0.0
 * @copyright           2022 snippets.guru
 * @version             1.0.0
 */
class Guru
{
    /**
     * The base URL of the API service.
     * 
     * @var string
     */
    protected $base_url = 'https://snippets.guru';

    /**
     * Main Guru Instance.
     *
     * Ensures only one instance of Guru is loaded or can be loaded.
     *
     * @return Guru
     */
    public static function instance()
    {
        static $instance = false;

        if (!$instance) {
            $instance = new Guru();
        }

        return $instance;
    }

    /**
     * Constructor.
     */
    public function __construct()
    {
        if (defined('SNIPPETS_GURU_BASE_URL')) {
            $this->base_url = SNIPPETS_GURU_BASE_URL;
        }
    }

    /**
     * Load the plugin integration.
     * 
     * @return void
     */
    public function integrate()
    {
        static $code_snippets = false;

        if (defined('CODE_SNIPPETS_FILE') && !$code_snippets) {
            $code_snippets = new CodeSnippets();
        }
    }

    /**
     * Get the base URL of the API service.
     * 
     * @return string
     */
    public function getBaseUrl()
    {
        return $this->base_url;
    }

    /**
     * Get the URL with the given path.
     * 
     * @param string $path
     * 
     * @return string
     */
    public function getUrl($path = '')
    {
        return $this->getBaseUrl() . $path;
    }

    /**
     * Set the base URL of the API service.
     * 
     * @param string $base_url The base URL of the API service.
     */
    public function setBaseUrl($base_url)
    {
        $this->base_url = $base_url;
    }

    /**
     * Get JWT Auth Token.
     * 
     * Get the JWT Auth Token from the options table.
     * 
     * @return string|bool the JWT Auth Token or false if not found
     */
    public function retrieveAuthToken()
    {
        if (defined('SNIPPETS_GURU_AUTH_TOKEN')) {
            $auth_token = SNIPPETS_GURU_AUTH_TOKEN;
        } else {
            $auth_token = get_option('snippets_guru_auth_token');
        }

        return apply_filters('snippets_guru/retrieve_auth_token', $auth_token);
    }

    public function getUser($auth_token = null, $force = false)
    {
        if (!$auth_token && ($auth_token = $this->retrieveAuthToken()) === false) {
            return false;
        }

        if (!$force) {
            $user = get_transient('code_snippets_cloud_user');

            if ($user) {
                return $user;
            }
        }

        $url = $this->getUrl('/api/account');

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $auth_token,
                'Content-Type' => 'application/json',
                'accept' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message(), $response->get_error_code());
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code >= 400) {
            throw new Exception(wp_remote_retrieve_response_message($response), $code);
        }

        $body = wp_remote_retrieve_body($response);

        $data = json_decode($body);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception(sprintf('json_decode error: [%s] %s', json_last_error(), json_last_error_msg()));
        }

        $user = $data;

        set_transient('code_snippets_cloud_user', $user, DAY_IN_SECONDS);

        return $data;
    }
}

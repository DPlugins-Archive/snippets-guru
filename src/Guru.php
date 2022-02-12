<?php

namespace Dplugins\SnippetsGuru;

use Dplugins\SnippetsGuru\Integration\CodeSnippets;
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

    protected $option_name_auth_token = 'snippets_guru_auth_token';

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
        if (defined('CODE_SNIPPETS_FILE')) {
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
     * Set the base URL of the API service.
     * 
     * @param string $base_url The base URL of the API service.
     */
    public function setBaseUrl($base_url)
    {
        $this->base_url = $base_url;
    }

    /**
     * Set the option name for the auth token.
     */
    public function setOptionNameAuthToken($name)
    {
        $this->option_name_auth_token = $name;
    }

    /**
     * Generate JWT Auth Token.
     * 
     * Get JWT Auth Token from Snippets Guru to use in future API calls.
     * The token will be saved in the options table with the key 'snippets_guru_auth_token'.
     * The token ttl is 2 years.
     * 
     * @param string $email the email address of the user on snippets.guru site.
     * @param string $password the password of the user on snippets.guru site.
     * @return bool|Exception true if the token is generated, otherwise Exception
     */
    public function generateAuthToken($email, $password)
    {
        $url = $this->base_url . '/api/login_check';

        $body = [
            'email' => $email,
            'password' => $password
        ];

        $response = wp_remote_request($url, [
            'method' => 'POST',
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'accept' => 'application/json',
            ],
            'body' => json_encode($body),
        ]);

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);

        if ($code !== 200) {
            throw new Exception('http_status_code', $code);
        }

        $token = $data['token'];

        update_option($this->option_name_auth_token, $token);

        return true;
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
        return apply_filters('snippets_guru/retrieve_auth_token', get_option($this->option_name_auth_token));
    }
}

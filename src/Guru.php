<?php

namespace Dplugins\SnippetsGuru;

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
     * The single instance of the class.
     *
     * @var Guru
     */
    protected static $_instance = null;

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
     * @return Guru Main instance.
     */
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
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
                'Content-Type' => 'application/json'
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

        update_option('snippets_guru_auth_token', $token);

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
        return get_option('snippets_guru_auth_token');
    }
}

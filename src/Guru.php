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
        $auth_token = get_option('snippets_guru_auth_token');

        if (defined('SNIPPETS_GURU_AUTH_TOKEN')) {
            $auth_token = SNIPPETS_GURU_AUTH_TOKEN;
        }

        return apply_filters('snippets_guru/retrieve_auth_token', $auth_token);
    }
}

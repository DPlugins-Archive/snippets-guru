<?php

namespace Dplugins\SnippetsGuru\Api\Abstract;

use Dplugins\SnippetsGuru\Guru;
use Exception;

abstract class Api
{
    /**
     * The instance of the Guru class.
     * 
     * @var Guru
     */
    protected $guru;

    /**
     * The endpoint of the resource.
     * 
     * @var string
     */
    public static $base_path = '/';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->guru = Guru::instance();
    }

    /**
     * Get the base URL of the API service.
     * 
     * @return string
     */
    protected function getBaseUrl()
    {
        return $this->guru->getBaseUrl();
    }

    /**
     * Get the JWT Auth Token to use in future API calls.
     * 
     * @return string|bool the JWT Auth Token or false if not found
     */
    protected function retrieveAuthToken()
    {
        return $this->guru->retrieveAuthToken();
    }

    /**
     * Performs an HTTP request and returns its response.
     * The function is a wrapper of the WordPress wp_remote_request().
     * 
     * @param string $method The HTTP method to use.
     * @param string $url The URL to which the request is sent.
     * @param array $args The arguments to pass to the request.
     * @param string|callable $validate_callback The callback function to validate the response.
     * @return array|object The response or WP_Error on failure.
     * @throws Exception
     */
    public function remote_request($method, $url, $args = [], $validate_callback = null)
    {
        $jwt_token = $this->retrieveAuthToken();

        if (!$jwt_token) {
            throw new Exception('No JWT Auth Token found.');
        }

        $defaults = [
            'method' => $method,
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'accept' => 'application/json',
                'Authorization' => $jwt_token
            ],
        ];

        $parsed_args = wp_parse_args($args, $defaults);

        $response = wp_remote_request($url, $parsed_args);

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message(), $response->get_error_code());
        }

        $code = wp_remote_retrieve_response_code($response);
        
        if ($code >= 400) {
            throw new Exception(wp_remote_retrieve_response_message($response, $code));
        }

        if ($validate_callback !== null && is_callable($validate_callback)) {
            try {
                call_user_func($validate_callback, $response);
            } catch (\Throwable $th) {
                throw $th;
            }
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('json_decode error: ' . json_last_error_msg());
        }

        return $data;
    }
}

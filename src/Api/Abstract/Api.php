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
                'Content-Type' => 'application/ld+json',
                'accept' => 'application/ld+json',
                'Authorization' => 'Bearer ' . $jwt_token
            ],
        ];

        $parsed_args = array_merge( $defaults, $args );

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

        $body = wp_remote_retrieve_body($response);

        $data = json_decode($body);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception(sprintf('json_decode error: [%s] %s', json_last_error(), json_last_error_msg()));
        }

        return $data;
    }
}

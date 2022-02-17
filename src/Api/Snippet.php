<?php

namespace Dplugins\SnippetsGuru\Api;

use Dplugins\SnippetsGuru\Api\Abstract\Api;
use Exception;

class Snippet extends Api
{
    private static $instances = [];

    /**
     * @inheritdoc
     */
    public static $base_path = '/api/snippets';

    public static function getInstance(): self
    {
        $cls = static::class;
        if (!isset(self::$instances[$cls])) {
            self::$instances[$cls] = new static();
        }

        return self::$instances[$cls];
    }

    /**
     * The IRI of the Snippet resource.
     * 
     * @return string
     */
    public function getSnippetIRI($id)
    {
        return Snippet::$base_path . '/' . $id;
    }

    /**
     * Retrieves the collection of Snippet resources.
     * 
     * @param array $query The query parameters for the request.
     * @return array the collection of Snippet resources
     * @throws Exception
     * 
     * @link https://snippets.guru/api/docs?ui=re_doc#operation/getSnippetCollection
     */
    public function list($query = [])
    {
        $args['body'] = $query;

        $url = $this->getBaseUrl() . self::$base_path;

        try {
            $data = $this->remote_request('GET', $url, $args);
        } catch (\Throwable $th) {
            throw $th;
        }

        return $data;
    }

    /**
     * Retrieves a Snippet resource.
     * 
     * @param string $id The unique identifier of the Snippet resource (uuid).
     * @return object the Snippet resource
     * @throws Exception
     * 
     * @link https://snippets.guru/api/docs?ui=re_doc#operation/getSnippetItem
     */
    public function get($id)
    {
        $url = $this->getBaseUrl() . self::$base_path . '/' . $id;

        try {
            $resp = $this->remote_request('GET', $url);
        } catch (\Throwable $th) {
            throw $th;
        }

        return $resp;
    }

    /**
     * Creates a Snippet resource.
     * 
     * @param array $data The data to create the Snippet resource.
     * @return object the Snippet resource
     * @throws Exception
     * 
     * @link https://snippets.guru/api/docs?ui=re_doc#operation/postSnippetCollection
     */
    public function save($data)
    {
        $url = $this->getBaseUrl() . self::$base_path;

        $args['body'] = json_encode($data);

        try {
            $resp = $this->remote_request('POST', $url, $args);
        } catch (\Throwable $th) {
            throw $th;
        }

        return $resp;
    }

    /**
     * Updates a Snippet resource.
     * 
     * @param string $id The unique identifier of the Snippet resource (uuid).
     * @param array $data The data to update the Snippet resource.
     * @return object the Snippet resource
     * @throws Exception
     * 
     * @link https://snippets.guru/api/docs?ui=re_doc#operation/putSnippetItem
     */
    public function update($id, $data)
    {
        $url = $this->getBaseUrl() . self::$base_path . '/' . $id;

        $args['body'] = json_encode($data);

        try {
            $resp = $this->remote_request('PUT', $url, $args);
        } catch (\Throwable $th) {
            throw $th;
        }

        return $resp;
    }

    /**
     * Deletes a Snippet resource.
     * 
     * @param string $id The unique identifier of the Snippet resource (uuid).
     * @return bool true if the Snippet resource was deleted
     * @throws Exception
     * 
     * @link https://snippets.guru/api/docs?ui=re_doc#operation/deleteSnippetItem
     */
    public function delete($id)
    {
        $url = $this->getBaseUrl() . self::$base_path . '/' . $id;

        try {
            $this->remote_request('DELETE', $url);
        } catch (\Throwable $th) {
            throw $th;
        }

        return true;
    }
}

<?php

namespace Dplugins\SnippetsGuru\Api;

use Dplugins\SnippetsGuru\Api\Abstract\Api;
use Exception;

class Snippet extends Api
{
    /**
     * @inheritdoc
     */
    public static $base_path = '/api/snippets';

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
        if (!empty($query)) {
            if ($query['page']) {
                $args['body']['page'] = $query['page'];
            }

            if ($query['namespace']) {
                $args['body']['namespace'] = $query['namespace'];
            }

            if ($query['name']) {
                $args['body']['name'] = $query['name'];
            }

            if ($query['description']) {
                $args['body']['description'] = $query['description'];
            }

            if ($query['isPublic']) {
                $args['body']['isPublic'] = $query['isPublic'];
            }
        }

        if (isset($args['body']) && !empty($args['body'])) {
            $args['body'] = json_encode($args['body']);
        }

        $url = $this->getBaseUrl() . self::$base_path;

        try {
            $data = $this->remote_request('POST', $url, $args);
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
            $data = $this->remote_request('GET', $url);
        } catch (\Throwable $th) {
            throw $th;
        }

        return $data;
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
    public function save($data){
        $url = $this->getBaseUrl() . self::$base_path;

        try {
            $data = $this->remote_request('POST', $url, $data);
        } catch (\Throwable $th) {
            throw $th;
        }

        return $data;
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
    public function update($id, $data){
        $url = $this->getBaseUrl() . self::$base_path . '/' . $id;

        try {
            $data = $this->remote_request('PUT', $url, $data);
        } catch (\Throwable $th) {
            throw $th;
        }

        return $data;
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
    public function delete($id){
        $url = $this->getBaseUrl() . self::$base_path . '/' . $id;

        try {
            $this->remote_request('DELETE', $url);
        } catch (\Throwable $th) {
            throw $th;
        }

        return true;
    }
}

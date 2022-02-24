<?php

namespace Dplugins\SnippetsGuru\Api;

use Dplugins\SnippetsGuru\Api\Abstract\Api;
use Exception;

class Blob extends Api
{
    private static $instances = [];

    /**
     * @inheritdoc
     */
    public static $base_path = '/api/blobs';

    /**
     * @var Snippet|null
     */
    public $snippet;

    /**
     * The Snippet resource id (uuid)
     * 
     * @var string
     */
    protected $snippet_id;

	public static function getInstance(Snippet $snippet = null): self
	{
		$cls = static::class;
		if (!isset(self::$instances[$cls])) {
			self::$instances[$cls] = new static($snippet);
		}

		return self::$instances[$cls];
	}

    public function __construct(Snippet $snippet = null) {
        $this->snippet = $snippet;
    }

    /**
     * The IRI of the Blob resource.
     * 
     * @return string
     */
    public function getBlobIRI($id) {
        return Blob::$base_path . '/' . $id;
    }

    /**
     * Set the Snippet resource id (uuid)
     * 
     * @param string $snippet_id The Snippet resource id (uuid)
     * @return Blob
     */
    public function setSnippetId($snippet_id) {
        $this->snippet_id = $snippet_id;

        return $this;
    }

    /**
     * Get the Snippet resource id (uuid)
     * 
     * @return string
     */
    public function getSnippetId() {
        return $this->snippet_id;
    }

    /**
     * Retrieves a Blob resource.
     * 
     * @param string $id The unique identifier of the Blob resource (uuid).
     * @return object the Blob resource
     * @throws Exception
     * 
     * @link https://snippets.guru/api/docs?ui=re_doc#operation/getBlobItem
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
     * Creates a Blob resource.
     * 
     * @param string $snippet_id The unique identifier of the Snippet resource (uuid).
     * @param array $data The data to create the Blob resource.
     * @return object the Blob resource
     * @throws Exception
     * 
     * @link https://snippets.guru/api/docs?ui=re_doc#operation/postBlobCollection
     */
    public function save($data){
        $url = $this->getBaseUrl() . self::$base_path;

        $data['snippet'] = Snippet::getInstance()->getSnippetIRI($this->getSnippetId());

        $args['body'] = json_encode($data);

        try {
            $data = $this->remote_request('POST', $url, $args);
        } catch (\Throwable $th) {
            throw $th;
        }

        return $data;
    }

    /**
     * Updates a Blob resource.
     * 
     * @param string $id The unique identifier of the Blob resource (uuid).
     * @param array $data The data to update the Blob resource.
     * @return object the Blob resource
     * @throws Exception
     * 
     * @link https://snippets.guru/api/docs?ui=re_doc#operation/putBlobItem
     */
    public function update($id, $data){
        $url = $this->getBaseUrl() . self::$base_path . '/' . $id;

        $data['snippet'] = Snippet::getInstance()->getSnippetIRI($this->getSnippetId());

        $args['body'] = json_encode($data);

        try {
            $data = $this->remote_request('PUT', $url, $args);
        } catch (\Throwable $th) {
            throw $th;
        }

        return $data;
    }

    /**
     * Deletes a Blob resource.
     * 
     * @param string $id The unique identifier of the Blob resource (uuid).
     * @return bool true if the Blob resource was deleted
     * @throws Exception
     * 
     * @link https://snippets.guru/api/docs?ui=re_doc#operation/deleteBlobItem
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

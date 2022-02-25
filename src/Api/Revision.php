<?php

namespace Dplugins\SnippetsGuru\Api;

use Dplugins\SnippetsGuru\Api\Api;
use Exception;

class Revision extends Api
{
    /**
     * @inheritdoc
     */
    public static $base_path = '/api/revisions';

    /**
     * @var Snippet|null
     */
    public $snippet;

    /**
     * @var Blob|null
     */
    public $blob;

    public function __construct(Blob $blob = null, Snippet $snippet = null)
    {
        $this->blob = $blob;
        $this->snippet = $snippet;
    }

    /**
     * Retrieves a Revision resource.
     * 
     * @param string $id The unique identifier of the Revision resource (uuid).
     * @return object the Revision resource
     * @throws Exception
     * 
     * @link https://snippets.guru/api/docs?ui=re_doc#operation/getRevisionItem
     */
    public function get($id)
    {
        $url = $this->getUrl(self::$base_path . '/' . $id);

        try {
            $data = $this->remote_request('GET', $url);
        } catch (\Throwable $th) {
            throw $th;
        }

        return $data;
    }
}

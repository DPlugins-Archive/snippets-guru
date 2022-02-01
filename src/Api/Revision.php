<?php

namespace Dplugins\SnippetsGuru\Api;

use Dplugins\SnippetsGuru\Api\Abstract\Api;
use Exception;

class Revision extends Api
{
    /**
     * @inheritdoc
     */
    public static $base_path = '/api/revisions';

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
        $url = $this->getBaseUrl() . self::$base_path . '/' . $id;

        try {
            $data = $this->remote_request('GET', $url);
        } catch (\Throwable $th) {
            throw $th;
        }

        return $data;
    }
}

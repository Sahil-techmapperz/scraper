<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;

class DocsController extends BaseController
{
    public function index()
    {
        return view('api/docs');
    }

    public function openApi()
    {
        $path = ROOTPATH . 'public/openapi.json';

        return $this->response
            ->setContentType('application/json')
            ->setBody((string) file_get_contents($path));
    }
}

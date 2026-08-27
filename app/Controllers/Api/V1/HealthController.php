<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use Config\Sources;

class HealthController extends BaseController
{
    public function index()
    {
        $config = config(Sources::class);

        return $this->response->setJSON([
            'success'   => true,
            'service'   => 'olx-india-data-api',
            'timestamp' => date(DATE_ATOM),
            'sources'   => [
                'olx' => [
                    'country'       => $config->country,
                    'adapter_mode'  => $config->olxMode,
                    'cache_enabled' => $config->olxCacheEnabled,
                    'configured'    => $config->olxMode === 'fixture' || ($config->olxMode === 'authorized_http' && $config->olxAuthorizedBaseUrl !== ''),
                ],
            ],
        ]);
    }
}

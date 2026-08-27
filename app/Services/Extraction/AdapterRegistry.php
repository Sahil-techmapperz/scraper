<?php

namespace App\Services\Extraction;

use App\Services\Extraction\Cardekho\CardekhoAdapter;
use App\Services\Extraction\Naukri\NaukriAdapter;
use App\Services\Extraction\Olx\OlxAdapter;
use App\Services\InvalidRequestException;

class AdapterRegistry
{
    public function adapter(string $source): SourceAdapterInterface
    {
        return match (strtolower($source)) {
            'olx'      => new OlxAdapter(),
            'cardekho' => new CardekhoAdapter(),
            'naukri'   => new NaukriAdapter(),
            default    => throw new InvalidRequestException(['source' => 'Unsupported source.']),
        };
    }
}

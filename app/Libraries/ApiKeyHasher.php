<?php

namespace App\Libraries;

use Config\Api;

class ApiKeyHasher
{
    public function __construct(private readonly ?Api $config = null)
    {
    }

    public function digest(string $plainTextKey): string
    {
        $pepper = ($this->config ?? config(Api::class))->apiKeyPepper;

        return hash_hmac('sha256', $plainTextKey, $pepper);
    }

    public function prefix(string $plainTextKey): string
    {
        return substr($this->digest($plainTextKey), 0, 12);
    }
}

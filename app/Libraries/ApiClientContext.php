<?php

namespace App\Libraries;

class ApiClientContext
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $client = null;

    /**
     * @param array<string, mixed> $client
     */
    public function setClient(array $client): void
    {
        $this->client = $client;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function client(): ?array
    {
        return $this->client;
    }

    public function clear(): void
    {
        $this->client = null;
    }
}

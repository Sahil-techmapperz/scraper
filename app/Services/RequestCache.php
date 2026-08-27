<?php

namespace App\Services;

use CodeIgniter\Cache\CacheInterface;
use Config\Services;

class RequestCache
{
    public function __construct(private readonly ?CacheInterface $cache = null)
    {
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function key(string $source, string $scope, array $parameters): string
    {
        unset($parameters['fresh']);
        $parameters = $this->recursiveSort($parameters);

        return $source . '_' . $scope . '_' . hash('sha256', json_encode($parameters, JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function get(string $source, string $scope, array $parameters): ?array
    {
        $value = ($this->cache ?? Services::cache())->get($this->key($source, $scope, $parameters));

        return is_array($value) ? $value : null;
    }

    /**
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $payload
     */
    public function save(string $source, string $scope, array $parameters, array $payload, int $ttl): void
    {
        ($this->cache ?? Services::cache())->save($this->key($source, $scope, $parameters), $payload, $ttl);
    }

    /**
     * @param array<string, mixed> $value
     *
     * @return array<string, mixed>
     */
    private function recursiveSort(array $value): array
    {
        ksort($value);

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->recursiveSort($item);
            }
        }

        return $value;
    }
}

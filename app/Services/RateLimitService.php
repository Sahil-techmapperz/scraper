<?php

namespace App\Services;

use CodeIgniter\Cache\CacheInterface;
use Config\Services;

class RateLimitService
{
    public function __construct(private readonly ?CacheInterface $cache = null)
    {
    }

    /**
     * @return array{allowed: bool, limit: int, remaining: int, retry_after: int}
     */
    public function hit(int $clientId, int $limitPerMinute): array
    {
        $limit = max(1, $limitPerMinute);
        $key = 'api_rate_' . $clientId . '_' . gmdate('YmdHi');
        $cache = $this->cache ?? Services::cache();
        $count = (int) ($cache->get($key) ?? 0);

        $count++;
        $cache->save($key, $count, 65);

        return [
            'allowed'     => $count <= $limit,
            'limit'       => $limit,
            'remaining'   => max(0, $limit - $count),
            'retry_after' => 60 - (int) gmdate('s'),
        ];
    }
}

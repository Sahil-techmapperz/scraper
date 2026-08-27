<?php

namespace App\Models;

use App\Libraries\ApiKeyHasher;
use CodeIgniter\Model;

class ApiKeyModel extends Model
{
    protected $table = 'api_keys';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'client_id',
        'key_hash',
        'key_prefix',
        'status',
        'last_used_at',
        'expires_at',
    ];

    /**
     * @return array<string, mixed>|null
     */
    public function findClientByPlainTextKey(string $plainTextKey): ?array
    {
        $digest = (new ApiKeyHasher())->digest($plainTextKey);
        $now = date('Y-m-d H:i:s');

        $row = $this->select([
            'api_clients.id',
            'api_clients.name',
            'api_clients.status',
            'api_clients.monthly_quota',
            'api_clients.requests_used',
            'api_clients.usage_period',
            'api_clients.rate_limit_per_minute',
            'api_clients.is_admin',
            'api_clients.expires_at',
            'api_keys.id AS api_key_id',
            'api_keys.key_prefix',
            'api_keys.status AS api_key_status',
            'api_keys.expires_at AS api_key_expires_at',
        ])
            ->join('api_clients', 'api_clients.id = api_keys.client_id')
            ->where('api_keys.key_hash', $digest)
            ->where('api_keys.status', 'active')
            ->where('api_clients.status', 'active')
            ->groupStart()
                ->where('api_keys.expires_at', null)
                ->orWhere('api_keys.expires_at >', $now)
            ->groupEnd()
            ->groupStart()
                ->where('api_clients.expires_at', null)
                ->orWhere('api_clients.expires_at >', $now)
            ->groupEnd()
            ->first();

        return $row ?: null;
    }

    public function markUsed(int $apiKeyId): void
    {
        $this->update($apiKeyId, [
            'last_used_at' => date('Y-m-d H:i:s'),
        ]);
    }
}

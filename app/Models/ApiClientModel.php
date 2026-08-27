<?php

namespace App\Models;

use CodeIgniter\Model;

class ApiClientModel extends Model
{
    protected $table = 'api_clients';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'name',
        'status',
        'monthly_quota',
        'requests_used',
        'usage_period',
        'rate_limit_per_minute',
        'is_admin',
        'expires_at',
    ];

    public function resetMonthlyUsageIfNeeded(int $clientId): void
    {
        $period = date('Y-m');
        $client = $this->find($clientId);

        if ($client === null || ($client['usage_period'] ?? null) === $period) {
            return;
        }

        $this->update($clientId, [
            'requests_used' => 0,
            'usage_period'  => $period,
        ]);
    }

    public function incrementMonthlyUsage(int $clientId): void
    {
        $this->builder()
            ->where('id', $clientId)
            ->set('requests_used', 'requests_used + 1', false)
            ->update();
    }
}

<?php

namespace App\Database\Seeds;

use App\Libraries\ApiKeyHasher;
use CodeIgniter\Database\Seeder;
use Config\Api;

class ApiClientSeeder extends Seeder
{
    public function run(): void
    {
        $plainTextKey = (string) env('BOOTSTRAP_API_KEY', ENVIRONMENT === 'production' ? '' : 'dev-local-api-key');

        if ($plainTextKey === '') {
            return;
        }

        $existing = $this->db->table('api_clients')
            ->where('name', 'Bootstrap Client')
            ->countAllResults();

        if ($existing > 0) {
            return;
        }

        $apiConfig = config(Api::class);
        $now = date('Y-m-d H:i:s');
        $this->db->table('api_clients')->insert([
            'name'                  => 'Bootstrap Client',
            'status'                => 'active',
            'monthly_quota'         => $apiConfig->defaultMonthlyQuota,
            'requests_used'         => 0,
            'usage_period'          => date('Y-m'),
            'rate_limit_per_minute' => $apiConfig->defaultRateLimit,
            'is_admin'              => true,
            'created_at'            => $now,
            'updated_at'            => $now,
        ]);
        $clientId = $this->db->insertID();

        $hasher = new ApiKeyHasher($apiConfig);
        $this->db->table('api_keys')->insert([
            'client_id'  => $clientId,
            'key_hash'   => $hasher->digest($plainTextKey),
            'key_prefix' => $hasher->prefix($plainTextKey),
            'status'     => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}

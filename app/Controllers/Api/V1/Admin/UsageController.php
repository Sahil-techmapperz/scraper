<?php

namespace App\Controllers\Api\V1\Admin;

use App\Controllers\BaseController;
use App\Models\ApiUsageLogModel;
use CodeIgniter\Database\BaseBuilder;
use Config\Database;

class UsageController extends BaseController
{
    public function index()
    {
        $limit = min(100, max(1, (int) ($this->request->getGet('limit') ?? 50)));
        $logs = (new ApiUsageLogModel())
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->find();

        return $this->response->setJSON([
            'success' => true,
            'data'    => [
                'recent'          => $logs,
                'monthly_summary' => $this->monthlySummary(),
            ],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function monthlySummary(): array
    {
        $db = Database::connect();
        $driver = strtolower($db->DBDriver);
        $periodExpression = str_contains($driver, 'sqlite')
            ? "strftime('%Y-%m', created_at)"
            : "DATE_FORMAT(created_at, '%Y-%m')";

        /** @var BaseBuilder $builder */
        $builder = $db->table('api_usage_logs');

        return $builder
            ->select($periodExpression . ' AS period', false)
            ->select('client_id')
            ->selectCount('id', 'requests')
            ->selectSum('records_returned', 'records_returned')
            ->groupBy(['period', 'client_id'])
            ->orderBy('period', 'DESC')
            ->limit(12)
            ->get()
            ->getResultArray();
    }
}

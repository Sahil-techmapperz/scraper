<?php

namespace App\Models;

use CodeIgniter\Model;

class ApiUsageLogModel extends Model
{
    protected $table = 'api_usage_logs';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'client_id',
        'endpoint',
        'request_method',
        'request_params',
        'http_status',
        'response_time_ms',
        'records_returned',
        'cache_hit',
        'source_extraction_attempted',
        'source_extraction_success',
        'ip_address',
        'user_agent',
        'created_at',
    ];
}

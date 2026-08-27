<?php

namespace App\Models;

use CodeIgniter\Model;

class ExtractionLogModel extends Model
{
    protected $table = 'extraction_logs';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'source_id',
        'source_name',
        'operation',
        'request_params',
        'status',
        'error_code',
        'error_message',
        'response_time_ms',
        'records_collected',
        'created_at',
    ];
}

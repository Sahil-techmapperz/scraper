<?php

namespace App\Services;

use App\Models\ExtractionLogModel;
use Throwable;

class ExtractionLogService
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function record(array $attributes): void
    {
        try {
            (new ExtractionLogModel())->insert([
                'source_id'         => $attributes['source_id'] ?? null,
                'source_name'       => (string) ($attributes['source_name'] ?? ''),
                'operation'         => (string) ($attributes['operation'] ?? ''),
                'request_params'    => json_encode($attributes['request_params'] ?? [], JSON_UNESCAPED_SLASHES),
                'status'            => (string) ($attributes['status'] ?? 'unknown'),
                'error_code'        => $attributes['error_code'] ?? null,
                'error_message'     => $attributes['error_message'] ?? null,
                'response_time_ms'  => (int) ($attributes['response_time_ms'] ?? 0),
                'records_collected' => (int) ($attributes['records_collected'] ?? 0),
                'created_at'        => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $exception) {
            log_message('error', 'Extraction logging failed: {message}', [
                'message' => $exception->getMessage(),
            ]);
        }
    }
}

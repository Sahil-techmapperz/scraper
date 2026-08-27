<?php

namespace App\Services;

use App\Models\ApiUsageLogModel;
use Config\Api;
use Config\Services;
use Throwable;

class UsageLogService
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function record(array $attributes): void
    {
        if (! (config(Api::class))->usageLoggingEnabled) {
            return;
        }

        $client = Services::apiClientContext()->client();

        if ($client === null) {
            return;
        }

        try {
            (new ApiUsageLogModel())->insert([
                'client_id'                    => (int) $client['id'],
                'endpoint'                     => (string) ($attributes['endpoint'] ?? ''),
                'request_method'               => (string) ($attributes['request_method'] ?? 'GET'),
                'request_params'               => json_encode($attributes['request_params'] ?? [], JSON_UNESCAPED_SLASHES),
                'http_status'                  => (int) ($attributes['http_status'] ?? 200),
                'response_time_ms'             => (int) ($attributes['response_time_ms'] ?? 0),
                'records_returned'             => (int) ($attributes['records_returned'] ?? 0),
                'cache_hit'                    => (bool) ($attributes['cache_hit'] ?? false),
                'source_extraction_attempted'  => (bool) ($attributes['source_extraction_attempted'] ?? false),
                'source_extraction_success'    => (bool) ($attributes['source_extraction_success'] ?? false),
                'ip_address'                   => (string) ($attributes['ip_address'] ?? ''),
                'user_agent'                   => (string) ($attributes['user_agent'] ?? ''),
                'created_at'                   => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $exception) {
            log_message('error', 'API usage logging failed: {message}', [
                'message' => $exception->getMessage(),
            ]);
        }
    }
}

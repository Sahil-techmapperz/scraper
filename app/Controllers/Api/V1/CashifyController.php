<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\Extraction\SourceUnavailableException;
use App\Services\InvalidRequestException;
use App\Services\ListingDetailService;
use App\Services\ListingNotFoundException;
use App\Services\ListingSearchService;
use App\Services\UsageLogService;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

class CashifyController extends BaseController
{
    public function listings(): ResponseInterface
    {
        $started = microtime(true);
        $metrics = [];

        try {
            $result = (new ListingSearchService())->search('cashify', $this->request->getGet());
            $metrics = $result['metrics'];

            return $this->json($result['payload'], 200, $started, $metrics);
        } catch (InvalidRequestException $exception) {
            return $this->json($this->error('INVALID_PARAMETER', $exception->getMessage(), $exception->errors()), 400, $started, $metrics);
        } catch (SourceUnavailableException $exception) {
            return $this->json($this->error($exception->sourceCode(), $exception->getMessage()), $this->statusFromException($exception), $started, [
                'source_extraction_attempted' => true,
                'source_extraction_success'   => false,
            ]);
        } catch (Throwable $exception) {
            log_message('error', 'Unhandled Cashify search error: {message}', [
                'message' => $exception->getMessage(),
            ]);

            return $this->json($this->error('INTERNAL_ERROR', 'The request could not be completed.'), 500, $started, $metrics);
        }
    }

    public function detail(string $listingId): ResponseInterface
    {
        $started = microtime(true);
        $metrics = [];

        try {
            $result = (new ListingDetailService())->byId('cashify', $listingId, $this->freshOption());
            $metrics = $result['metrics'];

            return $this->json($result['payload'], 200, $started, $metrics);
        } catch (InvalidRequestException $exception) {
            return $this->json($this->error('INVALID_PARAMETER', $exception->getMessage(), $exception->errors()), 400, $started, $metrics);
        } catch (ListingNotFoundException $exception) {
            return $this->json($this->error('LISTING_NOT_FOUND', $exception->getMessage()), 404, $started, $metrics);
        } catch (SourceUnavailableException $exception) {
            return $this->json($this->error($exception->sourceCode(), $exception->getMessage()), $this->statusFromException($exception), $started, [
                'source_extraction_attempted' => true,
                'source_extraction_success'   => false,
            ]);
        } catch (Throwable $exception) {
            log_message('error', 'Unhandled Cashify detail error: {message}', [
                'message' => $exception->getMessage(),
            ]);

            return $this->json($this->error('INTERNAL_ERROR', 'The request could not be completed.'), 500, $started, $metrics);
        }
    }

    public function detailByUrl(): ResponseInterface
    {
        $started = microtime(true);
        $metrics = [];
        $url = (string) $this->request->getGet('url');

        try {
            $result = (new ListingDetailService())->byUrl('cashify', $url, $this->freshOption());
            $metrics = $result['metrics'];

            return $this->json($result['payload'], 200, $started, $metrics);
        } catch (InvalidRequestException $exception) {
            return $this->json($this->error('INVALID_PARAMETER', $exception->getMessage(), $exception->errors()), 400, $started, $metrics);
        } catch (ListingNotFoundException $exception) {
            return $this->json($this->error('LISTING_NOT_FOUND', $exception->getMessage()), 404, $started, $metrics);
        } catch (SourceUnavailableException $exception) {
            return $this->json($this->error($exception->sourceCode(), $exception->getMessage()), $this->statusFromException($exception), $started, [
                'source_extraction_attempted' => true,
                'source_extraction_success'   => false,
            ]);
        } catch (Throwable $exception) {
            log_message('error', 'Unhandled Cashify URL lookup error: {message}', [
                'message' => $exception->getMessage(),
            ]);

            return $this->json($this->error('INTERNAL_ERROR', 'The request could not be completed.'), 500, $started, $metrics);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function freshOption(): array
    {
        return [
            'fresh' => filter_var($this->request->getGet('fresh'), FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $metrics
     */
    private function json(array $data, int $status, float $started, array $metrics = []): ResponseInterface
    {
        $durationMs = (int) round((microtime(true) - $started) * 1000);
        $quotaRemaining = (int) ($this->request->header('X-Quota-Remaining')?->getValue() ?? 0);

        (new UsageLogService())->record([
            'client_id'          => $this->request->header('X-Client-Id')?->getValue(),
            'key_id'             => $this->request->header('X-Key-Id')?->getValue(),
            'endpoint'           => (string) current_url(true),
            'http_method'        => $this->request->getMethod(),
            'status_code'        => $status,
            'response_time_ms'   => $durationMs,
            'cache_hit'          => (bool) ($metrics['cache_hit'] ?? false),
            'rate_limited'       => $status === 429,
            'quota_exhausted'    => false,
            'request_ip'         => $this->request->getIPAddress(),
            'user_agent'         => (string) $this->request->getUserAgent(),
            'query_params_hash'  => hash('sha256', (string) $this->request->getServer('QUERY_STRING')),
            'records_returned'   => (int) ($metrics['records_returned'] ?? count($data['data'] ?? [])),
            'source_attempted'   => (bool) ($metrics['source_extraction_attempted'] ?? false),
            'source_success'     => (bool) ($metrics['source_extraction_success'] ?? false),
            'error_code'         => $data['error']['code'] ?? null,
        ]);

        $response = $this->response
            ->setStatusCode($status)
            ->setContentType('application/json')
            ->setHeader('X-Response-Time-Ms', (string) $durationMs);

        if (isset($metrics['cache_hit'])) {
            $response->setHeader('X-Cache-Status', $metrics['cache_hit'] ? 'HIT' : 'MISS');
        }

        if ($quotaRemaining > 0) {
            $response->setHeader('X-Monthly-Quota-Remaining', (string) $quotaRemaining);
        }

        return $response->setJSON($data);
    }

    /**
     * @param array<string, mixed> $details
     *
     * @return array<string, mixed>
     */
    private function error(string $code, string $message, array $details = []): array
    {
        $payload = [
            'success' => false,
            'error'   => [
                'code'    => $code,
                'message' => $message,
            ],
        ];

        if (! empty($details)) {
            $payload['error']['details'] = $details;
        }

        return $payload;
    }

    private function statusFromException(SourceUnavailableException $exception): int
    {
        return match ($exception->sourceCode()) {
            'SOURCE_RATE_LIMITED' => 429,
            'LISTING_NOT_FOUND'   => 404,
            default               => 502,
        };
    }
}

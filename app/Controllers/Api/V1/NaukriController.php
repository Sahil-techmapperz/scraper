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

class NaukriController extends BaseController
{
    public function listings(): ResponseInterface
    {
        $started = microtime(true);
        $metrics = [];

        try {
            $result = (new ListingSearchService())->search('naukri', $this->request->getGet());
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
            log_message('error', 'Unhandled Naukri search error: {message}', [
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
            $result = (new ListingDetailService())->byId('naukri', $listingId, $this->freshOption());
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
            log_message('error', 'Unhandled Naukri detail error: {message}', [
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
            $result = (new ListingDetailService())->byUrl('naukri', $url, $this->freshOption());
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
            log_message('error', 'Unhandled Naukri URL lookup error: {message}', [
                'message' => $exception->getMessage(),
            ]);

            return $this->json($this->error('INTERNAL_ERROR', 'The request could not be completed.'), 500, $started, $metrics);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $metrics
     */
    private function json(array $payload, int $status, float $started, array $metrics = []): ResponseInterface
    {
        (new UsageLogService())->record([
            'endpoint'                    => $this->request->getUri()->getPath(),
            'request_method'              => $this->request->getMethod(),
            'request_params'              => $this->request->getGet(),
            'http_status'                 => $status,
            'response_time_ms'            => (int) round((microtime(true) - $started) * 1000),
            'records_returned'            => (int) ($metrics['records_returned'] ?? count($payload['data'] ?? [])),
            'cache_hit'                   => (bool) ($metrics['cache_hit'] ?? false),
            'source_extraction_attempted' => (bool) ($metrics['source_extraction_attempted'] ?? false),
            'source_extraction_success'   => (bool) ($metrics['source_extraction_success'] ?? false),
            'ip_address'                  => $this->request->getIPAddress(),
            'user_agent'                  => $this->request->getUserAgent()->getAgentString(),
        ]);

        return $this->response
            ->setStatusCode($status)
            ->setJSON($payload);
    }

    /**
     * @return array{fresh: bool}
     */
    private function freshOption(): array
    {
        return [
            'fresh' => filter_var($this->request->getGet('fresh') ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
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

        if ($details !== []) {
            $payload['error']['details'] = $details;
        }

        return $payload;
    }

    private function statusFromException(SourceUnavailableException $exception): int
    {
        $status = (int) $exception->getCode();

        return in_array($status, [409, 429, 500, 502, 504], true) ? $status : 502;
    }
}

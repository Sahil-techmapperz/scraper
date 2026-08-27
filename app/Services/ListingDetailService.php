<?php

namespace App\Services;

use App\Services\Extraction\AdapterRegistry;
use App\Services\Extraction\SourceUnavailableException;
use App\Services\Persistence\ListingPersistenceService;
use Config\Sources;
use Throwable;

class ListingDetailService
{
    public function __construct(
        private readonly ?AdapterRegistry $registry = null,
        private readonly ?ListingRecordValidator $recordValidator = null,
        private readonly ?RequestCache $cache = null,
        private readonly ?ListingPersistenceService $persistence = null,
        private readonly ?ExtractionLogService $extractionLogger = null,
        private readonly ?Sources $config = null,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array{payload: array<string, mixed>, metrics: array<string, mixed>}
     */
    public function byId(string $source, string $listingId, array $options = []): array
    {
        if ($listingId === '' || preg_match('/^[A-Za-z0-9._:-]+$/', $listingId) !== 1) {
            throw new InvalidRequestException(['listing_id' => 'listing_id contains unsupported characters.']);
        }

        return $this->detail($source, ['listing_id' => $listingId] + $options, fn () => ($this->registry ?? new AdapterRegistry())->adapter($source)->detail($listingId, $options));
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array{payload: array<string, mixed>, metrics: array<string, mixed>}
     */
    public function byUrl(string $source, string $listingUrl, array $options = []): array
    {
        if (filter_var($listingUrl, FILTER_VALIDATE_URL) === false) {
            throw new InvalidRequestException(['url' => 'url must be a valid URL.']);
        }

        return $this->detail($source, ['url' => $listingUrl] + $options, fn () => ($this->registry ?? new AdapterRegistry())->adapter($source)->detailByUrl($listingUrl, $options));
    }

    /**
     * @param array<string, mixed> $cacheParameters
     *
     * @return array{payload: array<string, mixed>, metrics: array<string, mixed>}
     */
    private function detail(string $source, array $cacheParameters, callable $callback): array
    {
        $config = $this->config ?? config(Sources::class);
        $fresh = filter_var($cacheParameters['fresh'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $cache = $this->cache ?? new RequestCache();

        if ($config->olxCacheEnabled && ! $fresh) {
            $cached = $cache->get($source, 'listing', $cacheParameters);

            if ($cached !== null) {
                return [
                    'payload' => $cached,
                    'metrics' => [
                        'cache_hit'                   => true,
                        'source_extraction_attempted' => false,
                        'source_extraction_success'   => false,
                        'records_returned'            => 1,
                    ],
                ];
            }
        }

        $started = microtime(true);

        try {
            $result = $callback();
            $records = ($this->recordValidator ?? new ListingRecordValidator())->validRecords($result->items);

            if ($records === []) {
                throw new ListingNotFoundException('Listing was not found.');
            }

            $payload = [
                'success' => true,
                'source'  => [
                    'platform' => $source,
                    'country'  => $config->country,
                ],
                'data'    => $records[0],
            ];

            ($this->persistence ?? new ListingPersistenceService())->persist($source, $records, $result->raw);

            if ($config->olxCacheEnabled) {
                $cache->save($source, 'listing', $cacheParameters, $payload, $config->olxCacheTtl);
            }

            $this->logExtraction($source, 'detail', $cacheParameters, 'success', null, null, $started, 1);

            return [
                'payload' => $payload,
                'metrics' => [
                    'cache_hit'                   => false,
                    'source_extraction_attempted' => $result->sourceExtractionAttempted,
                    'source_extraction_success'   => $result->sourceExtractionSuccess,
                    'records_returned'            => 1,
                ],
            ];
        } catch (SourceUnavailableException $exception) {
            $this->logExtraction($source, 'detail', $cacheParameters, 'failure', $exception->sourceCode(), $exception->getMessage(), $started, 0);

            throw $exception;
        } catch (Throwable $exception) {
            $this->logExtraction($source, 'detail', $cacheParameters, 'failure', $exception instanceof ListingNotFoundException ? 'NOT_FOUND' : 'INTERNAL_ERROR', $exception->getMessage(), $started, 0);

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function logExtraction(string $source, string $operation, array $parameters, string $status, ?string $errorCode, ?string $errorMessage, float $started, int $records): void
    {
        ($this->extractionLogger ?? new ExtractionLogService())->record([
            'source_name'       => $source,
            'operation'         => $operation,
            'request_params'    => $parameters,
            'status'            => $status,
            'error_code'        => $errorCode,
            'error_message'     => $errorMessage,
            'response_time_ms'  => (int) round((microtime(true) - $started) * 1000),
            'records_collected' => $records,
        ]);
    }
}

<?php

namespace App\Services;

use App\Services\Extraction\AdapterRegistry;
use App\Services\Extraction\SourceUnavailableException;
use App\Services\Persistence\ListingPersistenceService;
use Config\Sources;
use Throwable;

class ListingSearchService
{
    public function __construct(
        private readonly ?ListingQueryValidator $validator = null,
        private readonly ?AdapterRegistry $registry = null,
        private readonly ?ListingRecordValidator $recordValidator = null,
        private readonly ?RequestCache $cache = null,
        private readonly ?ListingPersistenceService $persistence = null,
        private readonly ?ExtractionLogService $extractionLogger = null,
        private readonly ?Sources $config = null,
    ) {
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array{payload: array<string, mixed>, metrics: array<string, mixed>}
     */
    public function search(string $source, array $parameters): array
    {
        $config = $this->config ?? config(Sources::class);
        $query = ($this->validator ?? new ListingQueryValidator())->validate($parameters);
        $cache = $this->cache ?? new RequestCache();

        if ($config->olxCacheEnabled && ! (bool) $query['fresh']) {
            $cached = $cache->get($source, 'listings', $query);

            if ($cached !== null) {
                return [
                    'payload' => $cached,
                    'metrics' => [
                        'cache_hit'                   => true,
                        'source_extraction_attempted' => false,
                        'source_extraction_success'   => false,
                        'records_returned'            => count($cached['data'] ?? []),
                    ],
                ];
            }
        }

        $started = microtime(true);

        try {
            $result = ($this->registry ?? new AdapterRegistry())->adapter($source)->search($query);
            $records = ($this->recordValidator ?? new ListingRecordValidator())->validRecords($result->items);
            if (isset($query['sort']) && is_string($query['sort']) && $query['sort'] !== '') {
                $records = $this->sortRecords($records, $query['sort']);
            }
            $payload = [
                'success'    => true,
                'source'     => [
                    'platform' => $source,
                    'country'  => $config->country,
                ],
                'request'    => $this->publicRequest($query),
                'pagination' => $result->pagination,
                'data'       => $records,
            ];

            ($this->persistence ?? new ListingPersistenceService())->persist($source, $records, $result->raw);

            if ($config->olxCacheEnabled) {
                $cache->save($source, 'listings', $query, $payload, $config->olxCacheTtl);
            }

            $this->logExtraction($source, 'search', $query, 'success', null, null, $started, count($records));

            return [
                'payload' => $payload,
                'metrics' => [
                    'cache_hit'                   => false,
                    'source_extraction_attempted' => $result->sourceExtractionAttempted,
                    'source_extraction_success'   => $result->sourceExtractionSuccess,
                    'records_returned'            => count($records),
                ],
            ];
        } catch (SourceUnavailableException $exception) {
            $this->logExtraction($source, 'search', $query, 'failure', $exception->sourceCode(), $exception->getMessage(), $started, 0);

            throw $exception;
        } catch (Throwable $exception) {
            $this->logExtraction($source, 'search', $query, 'failure', 'INTERNAL_ERROR', $exception->getMessage(), $started, 0);

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    private function publicRequest(array $query): array
    {
        unset($query['fresh']);

        return $query;
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

    /**
     * @param list<array<string, mixed>> $records
     *
     * @return list<array<string, mixed>>
     */
    private function sortRecords(array $records, string $sort): array
    {
        usort($records, static function (array $a, array $b) use ($sort): int {
            if ($sort === 'price_low_to_high') {
                $pA = isset($a['price']['amount']) && is_numeric($a['price']['amount']) ? (float) $a['price']['amount'] : PHP_INT_MAX;
                $pB = isset($b['price']['amount']) && is_numeric($b['price']['amount']) ? (float) $b['price']['amount'] : PHP_INT_MAX;

                return $pA <=> $pB;
            }

            if ($sort === 'price_high_to_low') {
                $pA = isset($a['price']['amount']) && is_numeric($a['price']['amount']) ? (float) $a['price']['amount'] : -1.0;
                $pB = isset($b['price']['amount']) && is_numeric($b['price']['amount']) ? (float) $b['price']['amount'] : -1.0;

                return $pB <=> $pA;
            }

            if ($sort === 'newest') {
                $dateA = $a['listing_date'] ?? $a['automobile']['year'] ?? $a['collected_at'] ?? '';
                $dateB = $b['listing_date'] ?? $b['automobile']['year'] ?? $b['collected_at'] ?? '';

                return strcmp((string) $dateB, (string) $dateA);
            }

            if ($sort === 'oldest') {
                $dateA = $a['listing_date'] ?? $a['automobile']['year'] ?? $a['collected_at'] ?? '';
                $dateB = $b['listing_date'] ?? $b['automobile']['year'] ?? $b['collected_at'] ?? '';

                return strcmp((string) $dateA, (string) $dateB);
            }

            return 0;
        });

        return array_values($records);
    }
}

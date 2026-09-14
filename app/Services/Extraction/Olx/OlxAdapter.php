<?php

namespace App\Services\Extraction\Olx;

use App\Services\Extraction\ExtractionResult;
use App\Services\Extraction\SourceAdapterInterface;
use App\Services\Extraction\SourceUnavailableException;
use App\Services\ListingNotFoundException;
use CodeIgniter\HTTP\CURLRequest;
use Config\Services;
use Config\Sources;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

class OlxAdapter implements SourceAdapterInterface
{
    public function __construct(
        private readonly ?Sources $config = null,
        private readonly ?OlxParser $parser = null,
        private readonly ?OlxNormalizer $normalizer = null,
    ) {
    }

    /**
     * @param array<string, mixed> $query
     */
    public function search(array $query): ExtractionResult
    {
        $config = $this->config();

        return match (strtolower($config->olxMode)) {
            'fixture'         => $this->fixtureSearch($query),
            'authorized_http' => $this->authorizedSearch($query),
            default           => throw new SourceUnavailableException(
                'OLX authorized data connector is not configured.',
                'SOURCE_NOT_CONFIGURED',
                502,
            ),
        };
    }

    /**
     * @param array<string, mixed> $options
     */
    public function detail(string $listingId, array $options = []): ExtractionResult
    {
        $config = $this->config();

        return match (strtolower($config->olxMode)) {
            'fixture'         => $this->fixtureDetail($listingId),
            'authorized_http' => $this->authorizedDetail($listingId, $options),
            default           => throw new SourceUnavailableException(
                'OLX authorized data connector is not configured.',
                'SOURCE_NOT_CONFIGURED',
                502,
            ),
        };
    }

    /**
     * @param array<string, mixed> $options
     */
    public function detailByUrl(string $listingUrl, array $options = []): ExtractionResult
    {
        $config = $this->config();

        if (filter_var($listingUrl, FILTER_VALIDATE_URL) === false) {
            throw new ListingNotFoundException('Listing URL is invalid.');
        }

        return match (strtolower($config->olxMode)) {
            'fixture'         => $this->fixtureDetailByUrl($listingUrl),
            'authorized_http' => $this->authorizedDetailByUrl($listingUrl, $options),
            default           => throw new SourceUnavailableException(
                'OLX authorized URL lookup is not configured.',
                'SOURCE_NOT_CONFIGURED',
                502,
            ),
        };
    }

    /**
     * @param array<string, mixed> $query
     */
    private function authorizedSearch(array $query): ExtractionResult
    {
        $config = $this->config();
        $payload = $this->requestJson('GET', $this->url($config->olxSearchEndpoint), $this->sourceQuery($query));
        $parsed = $this->parser()->parseSearch($payload);
        $items = $this->normalizer()->normalizeMany($parsed['items']);
        $pagination = $this->pagination($query, $parsed['pagination'], count($items));

        return new ExtractionResult($items, $pagination, $parsed['raw'], $this->now());
    }

    /**
     * @param array<string, mixed> $options
     */
    private function authorizedDetail(string $listingId, array $options): ExtractionResult
    {
        $config = $this->config();
        $endpoint = str_replace('{listing_id}', rawurlencode($listingId), $config->olxDetailEndpoint);
        $payload = $this->requestJson('GET', $this->url($endpoint), $this->sourceQuery($options));
        $parsed = $this->parser()->parseDetail($payload);
        $item = $this->normalizer()->normalizeListing($parsed['item']);

        return new ExtractionResult([$item], ['page' => 1, 'limit' => 1, 'has_next' => false], $parsed['raw'], $this->now());
    }

    /**
     * @param array<string, mixed> $options
     */
    private function authorizedDetailByUrl(string $listingUrl, array $options): ExtractionResult
    {
        $config = $this->config();
        $query = $this->sourceQuery($options);
        $query['url'] = $listingUrl;
        $payload = $this->requestJson('GET', $this->url($config->olxDetailEndpoint), $query);
        $parsed = $this->parser()->parseDetail($payload);
        $item = $this->normalizer()->normalizeListing($parsed['item']);

        return new ExtractionResult([$item], ['page' => 1, 'limit' => 1, 'has_next' => false], $parsed['raw'], $this->now());
    }

    /**
     * @param array<string, mixed> $query
     */
    private function fixtureSearch(array $query): ExtractionResult
    {
        $parsed = $this->parser()->parseSearch($this->fixturePayload($this->config()->fixtureSearchPath));
        $items = $this->normalizer()->normalizeMany($parsed['items']);
        $items = $this->filterFixtureItems($items, $query);
        $items = $this->sortFixtureItems($items, (string) ($query['sort'] ?? 'newest'));

        $page = (int) ($query['page'] ?? 1);
        $limit = (int) ($query['limit'] ?? 50);
        $offset = ($page - 1) * $limit;
        $pageItems = array_slice($items, $offset, $limit);

        return new ExtractionResult(
            $pageItems,
            [
                'page'     => $page,
                'limit'    => $limit,
                'has_next' => ($offset + $limit) < count($items),
            ],
            $parsed['raw'],
            $this->now(),
        );
    }

    private function fixtureDetail(string $listingId): ExtractionResult
    {
        $items = $this->fixtureDetailItems();

        foreach ($items as $item) {
            if ((string) ($item['listing_id'] ?? '') === $listingId) {
                return new ExtractionResult([$item], ['page' => 1, 'limit' => 1, 'has_next' => false], null, $this->now());
            }
        }

        throw new ListingNotFoundException('Listing was not found.');
    }

    private function fixtureDetailByUrl(string $listingUrl): ExtractionResult
    {
        $items = $this->fixtureDetailItems();

        foreach ($items as $item) {
            if ((string) ($item['listing_url'] ?? '') === $listingUrl) {
                return new ExtractionResult([$item], ['page' => 1, 'limit' => 1, 'has_next' => false], null, $this->now());
            }
        }

        throw new ListingNotFoundException('Listing was not found.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fixtureDetailItems(): array
    {
        $payload = $this->fixturePayload($this->config()->fixtureDetailPath);
        $parsed = $this->parser()->parseSearch($payload);

        return $this->normalizer()->normalizeMany($parsed['items']);
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param array<string, mixed>       $query
     *
     * @return list<array<string, mixed>>
     */
    private function filterFixtureItems(array $items, array $query): array
    {
        return array_values(array_filter($items, function (array $item) use ($query): bool {
            if (isset($query['category']) && ! $this->matchesCategory($item, (string) $query['category'])) {
                return false;
            }

            foreach (['city', 'state', 'locality', 'pincode'] as $field) {
                if (isset($query[$field]) && ! $this->equals($item['location'][$field] ?? null, $query[$field])) {
                    return false;
                }
            }

            if (isset($query['keyword']) && ! $this->contains($item['title'] ?? '', (string) $query['keyword']) && ! $this->contains($item['description'] ?? '', (string) $query['keyword'])) {
                return false;
            }

            $price = $item['price']['amount'] ?? null;

            if (isset($query['min_price']) && ($price === null || $price < (int) $query['min_price'])) {
                return false;
            }

            if (isset($query['max_price']) && ($price === null || $price > (int) $query['max_price'])) {
                return false;
            }

            foreach (['brand', 'model', 'fuel_type', 'transmission'] as $field) {
                if (isset($query[$field]) && ! $this->equals($item['automobile'][$field] ?? $item['mobile_phone'][$field] ?? null, $query[$field])) {
                    return false;
                }
            }

            if (isset($query['min_year']) && (($item['automobile']['manufacturing_year'] ?? null) < (int) $query['min_year'])) {
                return false;
            }

            if (isset($query['max_year']) && (($item['automobile']['manufacturing_year'] ?? null) > (int) $query['max_year'])) {
                return false;
            }

            if (isset($query['max_km']) && (($item['automobile']['kilometres_driven'] ?? null) > (int) $query['max_km'])) {
                return false;
            }

            if (isset($query['min_storage']) && (($item['mobile_phone']['storage_gb'] ?? null) < (int) $query['min_storage'])) {
                return false;
            }

            if (isset($query['max_storage']) && (($item['mobile_phone']['storage_gb'] ?? null) > (int) $query['max_storage'])) {
                return false;
            }

            if (isset($query['bhk']) && (($item['real_estate']['bhk'] ?? null) !== (int) $query['bhk'])) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return list<array<string, mixed>>
     */
    private function sortFixtureItems(array $items, string $sort): array
    {
        usort($items, static function (array $left, array $right) use ($sort): int {
            return match ($sort) {
                'oldest'             => strcmp((string) ($left['listing_date'] ?? ''), (string) ($right['listing_date'] ?? '')),
                'price_low_to_high'  => (($left['price']['amount'] ?? PHP_INT_MAX) <=> ($right['price']['amount'] ?? PHP_INT_MAX)),
                'price_high_to_low' => (($right['price']['amount'] ?? 0) <=> ($left['price']['amount'] ?? 0)),
                default              => strcmp((string) ($right['listing_date'] ?? ''), (string) ($left['listing_date'] ?? '')),
            };
        });

        return $items;
    }

    private function matchesCategory(array $item, string $category): bool
    {
        if ($category === 'automobile' || $category === 'automobiles') {
            return ($item['category'] ?? null) === 'automobile';
        }

        if ($category === 'cars') {
            return ($item['category'] ?? null) === 'automobile' && ($item['subcategory'] ?? null) === 'cars';
        }

        if ($category === 'bikes') {
            return ($item['category'] ?? null) === 'automobile' && ($item['subcategory'] ?? null) === 'bikes';
        }

        return $this->equals($item['category'] ?? null, $category) || $this->equals($item['subcategory'] ?? null, $category);
    }

    private function equals(mixed $left, mixed $right): bool
    {
        return strtolower(trim((string) $left)) === strtolower(trim((string) $right));
    }

    private function contains(mixed $haystack, string $needle): bool
    {
        return str_contains(strtolower((string) $haystack), strtolower($needle));
    }

    /**
     * @param array<string, mixed> $query
     */
    private function pagination(array $query, array $sourcePagination, int $itemCount): array
    {
        $page = (int) ($sourcePagination['page'] ?? $query['page'] ?? 1);
        $limit = (int) ($sourcePagination['limit'] ?? $sourcePagination['size'] ?? $query['limit'] ?? $itemCount);
        $hasNext = (bool) ($sourcePagination['has_next'] ?? $sourcePagination['hasNext'] ?? false);

        return [
            'page'     => max(1, $page),
            'limit'    => max(1, $limit),
            'has_next' => $hasNext,
        ];
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    private function sourceQuery(array $query): array
    {
        unset($query['fresh']);

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function fixturePayload(string $relativePath): array
    {
        $path = ROOTPATH . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);

        if (! is_file($path)) {
            throw new SourceUnavailableException('Fixture source data is missing.', 'FIXTURE_MISSING', 502);
        }

        $payload = json_decode((string) file_get_contents($path), true);

        if (! is_array($payload)) {
            throw new SourceUnavailableException('Fixture source data is invalid.', 'FIXTURE_INVALID', 502);
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    private function requestJson(string $method, string $url, array $query = []): array
    {
        $config = $this->config();

        if ($config->olxAuthorizedBaseUrl === '') {
            throw new SourceUnavailableException('OLX authorized API base URL is not configured.', 'SOURCE_NOT_CONFIGURED', 502);
        }

        $headers = [
            'Accept'     => 'application/json',
            'User-Agent' => 'olx-india-data-api/1.0',
        ];

        if ($config->olxBearerToken !== '') {
            $headers['Authorization'] = 'Bearer ' . $config->olxBearerToken;
        }

        $attempts = max(1, $config->olxRetryAttempts + 1);
        $lastError = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                /** @var CURLRequest $client */
                $client = Services::curlrequest([
                    'timeout'     => $config->olxRequestTimeout,
                    'http_errors' => false,
                ]);
                $response = $client->request($method, $url, [
                    'query'       => $query,
                    'headers'     => $headers,
                    'http_errors' => false,
                    'timeout'     => $config->olxRequestTimeout,
                ]);
                $statusCode = $response->getStatusCode();

                if ($statusCode >= 200 && $statusCode < 300) {
                    $payload = json_decode($response->getBody(), true);

                    if (! is_array($payload)) {
                        throw new SourceUnavailableException('Source returned invalid JSON.', 'PARSER_FAILURE', 502);
                    }

                    return $payload;
                }

                $lastError = 'Source returned HTTP ' . $statusCode . '.';

                if (! in_array($statusCode, [408, 429, 500, 502, 503, 504], true)) {
                    break;
                }
            } catch (Throwable $exception) {
                $lastError = $exception->getMessage();
            }

            if ($attempt < $attempts) {
                usleep((int) (100000 * (2 ** ($attempt - 1))));
            }
        }

        throw new SourceUnavailableException($lastError ?: 'Source request failed.', 'SOURCE_UNAVAILABLE', 502);
    }

    private function url(string $endpoint): string
    {
        $config = $this->config();

        return $config->olxAuthorizedBaseUrl . '/' . ltrim($endpoint, '/');
    }

    private function now(): string
    {
        $timezone = new DateTimeZone($this->config()->collectionTimezone);

        return (new DateTimeImmutable('now', $timezone))->format(DATE_ATOM);
    }

    private function config(): Sources
    {
        return $this->config ?? config(Sources::class);
    }

    private function parser(): OlxParser
    {
        return $this->parser ?? new OlxParser();
    }

    private function normalizer(): OlxNormalizer
    {
        return $this->normalizer ?? new OlxNormalizer($this->config());
    }
}

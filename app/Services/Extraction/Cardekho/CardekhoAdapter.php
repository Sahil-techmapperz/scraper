<?php

namespace App\Services\Extraction\Cardekho;

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

class CardekhoAdapter implements SourceAdapterInterface
{
    public function __construct(
        private readonly ?Sources $config = null,
        private readonly ?CardekhoParser $parser = null,
        private readonly ?CardekhoNormalizer $normalizer = null,
    ) {
    }

    /**
     * @param array<string, mixed> $query
     */
    public function search(array $query): ExtractionResult
    {
        $config = $this->config();

        return match (strtolower($config->cardekhoMode)) {
            'fixture'         => $this->fixtureSearch($query),
            'authorized_http' => $this->authorizedSearch($query),
            default           => throw new SourceUnavailableException(
                'CarDekho authorized data connector is not configured.',
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

        return match (strtolower($config->cardekhoMode)) {
            'fixture'         => $this->fixtureDetail($listingId),
            'authorized_http' => $this->authorizedDetail($listingId, $options),
            default           => throw new SourceUnavailableException(
                'CarDekho authorized data connector is not configured.',
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

        return match (strtolower($config->cardekhoMode)) {
            'fixture'         => $this->fixtureDetailByUrl($listingUrl),
            'authorized_http' => $this->authorizedDetailByUrl($listingUrl, $options),
            default           => throw new SourceUnavailableException(
                'CarDekho authorized URL lookup is not configured.',
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
        $payload = $this->requestJson('GET', $this->url($config->cardekhoSearchEndpoint), $this->sourceQuery($query));
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
        $endpoint = str_replace('{listing_id}', rawurlencode($listingId), $config->cardekhoDetailEndpoint);
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
        $payload = $this->requestJson('GET', $this->url($config->cardekhoSearchEndpoint), $query);
        $parsed = $this->parser()->parseDetail($payload);
        $item = $this->normalizer()->normalizeListing($parsed['item']);

        return new ExtractionResult([$item], ['page' => 1, 'limit' => 1, 'has_next' => false], $parsed['raw'], $this->now());
    }

    /**
     * @param array<string, mixed> $query
     */
    private function fixtureSearch(array $query): ExtractionResult
    {
        $parsed = $this->parser()->parseSearch($this->fixturePayload($this->config()->fixtureCardekhoSearchPath));
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
        $detailPath = $this->config()->fixtureCardekhoDetailPath;

        if (is_file(ROOTPATH . $detailPath)) {
            $parsed = $this->parser()->parseDetail($this->fixturePayload($detailPath));
            $item = $this->normalizer()->normalizeListing($parsed['item']);

            if ($listingId === '' || $item['listing_id'] === $listingId || str_contains($item['listing_id'], $listingId)) {
                return new ExtractionResult([$item], ['page' => 1, 'limit' => 1, 'has_next' => false], $parsed['raw'], $this->now());
            }
        }

        $search = $this->fixtureSearch(['limit' => 100]);
        foreach ($search->items as $item) {
            if ($item['listing_id'] === $listingId || str_contains((string) $item['listing_id'], $listingId)) {
                return new ExtractionResult([$item], ['page' => 1, 'limit' => 1, 'has_next' => false], $search->raw, $this->now());
            }
        }

        throw new ListingNotFoundException("Listing {$listingId} was not found.");
    }

    private function fixtureDetailByUrl(string $listingUrl): ExtractionResult
    {
        $detailPath = $this->config()->fixtureCardekhoDetailPath;

        if (is_file(ROOTPATH . $detailPath)) {
            $parsed = $this->parser()->parseDetail($this->fixturePayload($detailPath));
            $item = $this->normalizer()->normalizeListing($parsed['item']);

            return new ExtractionResult([$item], ['page' => 1, 'limit' => 1, 'has_next' => false], $parsed['raw'], $this->now());
        }

        $search = $this->fixtureSearch(['limit' => 100]);
        foreach ($search->items as $item) {
            if (($item['listing_url'] ?? '') === $listingUrl) {
                return new ExtractionResult([$item], ['page' => 1, 'limit' => 1, 'has_next' => false], $search->raw, $this->now());
            }
        }

        throw new ListingNotFoundException("Listing for URL {$listingUrl} was not found.");
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param array<string, mixed>       $query
     *
     * @return list<array<string, mixed>>
     */
    private function filterFixtureItems(array $items, array $query): array
    {
        return array_values(array_filter($items, static function (array $item) use ($query): bool {
            if (! empty($query['city']) && ! str_contains(strtolower((string) ($item['location']['city'] ?? '')), strtolower((string) $query['city']))) {
                return false;
            }

            if (! empty($query['brand']) && ! str_contains(strtolower((string) ($item['automobile']['brand'] ?? '')), strtolower((string) $query['brand']))) {
                return false;
            }

            if (! empty($query['model']) && ! str_contains(strtolower((string) ($item['automobile']['model'] ?? '')), strtolower((string) $query['model']))) {
                return false;
            }

            if (! empty($query['fuel_type']) && strtolower((string) ($item['automobile']['fuel_type'] ?? '')) !== strtolower((string) $query['fuel_type'])) {
                return false;
            }

            if (! empty($query['min_price']) && ((int) ($item['price']['amount'] ?? 0)) < (int) $query['min_price']) {
                return false;
            }

            if (! empty($query['max_price']) && ((int) ($item['price']['amount'] ?? 0)) > (int) $query['max_price']) {
                return false;
            }

            if (! empty($query['min_year']) && ((int) ($item['automobile']['year'] ?? 0)) < (int) $query['min_year']) {
                return false;
            }

            if (! empty($query['max_year']) && ((int) ($item['automobile']['year'] ?? 0)) > (int) $query['max_year']) {
                return false;
            }

            if (! empty($query['min_km']) && ((int) ($item['automobile']['kilometers'] ?? 0)) < (int) $query['min_km']) {
                return false;
            }

            if (! empty($query['max_km']) && ((int) ($item['automobile']['kilometers'] ?? 0)) > (int) $query['max_km']) {
                return false;
            }

            if (! empty($query['keyword'])) {
                $kw = strtolower((string) $query['keyword']);
                $title = strtolower((string) ($item['title'] ?? ''));
                $desc = strtolower((string) ($item['description'] ?? ''));
                if (! str_contains($title, $kw) && ! str_contains($desc, $kw)) {
                    return false;
                }
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
        if ($sort === 'price_asc' || $sort === 'price_low_to_high') {
            usort($items, static fn (array $a, array $b): int => ((int) ($a['price']['amount'] ?? 0)) <=> ((int) ($b['price']['amount'] ?? 0)));
        } elseif ($sort === 'price_desc' || $sort === 'price_high_to_low') {
            usort($items, static fn (array $a, array $b): int => ((int) ($b['price']['amount'] ?? 0)) <=> ((int) ($a['price']['amount'] ?? 0)));
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    private function sourceQuery(array $query): array
    {
        $clean = [];
        foreach ($query as $key => $value) {
            if ($value !== null && $value !== '') {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $sourcePagination
     *
     * @return array{page: int, limit: int, has_next: bool}
     */
    private function pagination(array $query, array $sourcePagination, int $itemCount): array
    {
        $page = (int) ($sourcePagination['page'] ?? $query['page'] ?? 1);
        $limit = (int) ($sourcePagination['limit'] ?? $query['limit'] ?? 50);
        $hasNext = (bool) ($sourcePagination['has_next'] ?? $sourcePagination['hasNext'] ?? ($itemCount >= $limit));

        return [
            'page'     => $page > 0 ? $page : 1,
            'limit'    => $limit > 0 ? $limit : 50,
            'has_next' => $hasNext,
        ];
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    private function requestJson(string $method, string $url, array $query = []): array
    {
        $config = $this->config();
        $attempts = max(1, $config->cardekhoRetryAttempts);
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $client = $this->client();
                $options = [
                    'query'       => $query,
                    'headers'     => $this->headers(),
                    'http_errors' => false,
                    'timeout'     => $config->cardekhoRequestTimeout,
                ];

                $response = $client->request($method, $url, $options);
                $status = $response->getStatusCode();
                $body = (string) $response->getBody();

                if ($status === 404) {
                    throw new ListingNotFoundException('CarDekho listing was not found.');
                }

                if ($status >= 200 && $status < 300) {
                    $decoded = json_decode($body, true);
                    if (is_array($decoded)) {
                        return $decoded;
                    }

                    throw new SourceUnavailableException('CarDekho response is not valid JSON.', 'PARSER_FAILURE', 502);
                }

                if ($status === 429) {
                    throw new SourceUnavailableException('CarDekho rate limit encountered.', 'SOURCE_RATE_LIMITED', 429);
                }

                if ($status >= 500) {
                    throw new SourceUnavailableException("CarDekho service returned {$status}.", 'SOURCE_HTTP_ERROR', 502);
                }

                throw new SourceUnavailableException("CarDekho request failed with HTTP {$status}.", 'SOURCE_HTTP_ERROR', $status);
            } catch (ListingNotFoundException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                $lastException = $exception;
                if ($attempt >= $attempts) {
                    break;
                }
                usleep(250000 * $attempt);
            }
        }

        if ($lastException instanceof SourceUnavailableException) {
            throw $lastException;
        }

        throw new SourceUnavailableException(
            'Unable to reach CarDekho extraction service: ' . ($lastException?->getMessage() ?? 'unknown network failure'),
            'SOURCE_UNAVAILABLE',
            502,
        );
    }

    private function url(string $endpoint): string
    {
        $base = $this->config()->cardekhoAuthorizedBaseUrl;
        if ($base === '') {
            throw new SourceUnavailableException('CarDekho authorized base URL is not configured.', 'SOURCE_NOT_CONFIGURED', 502);
        }

        return rtrim($base, '/') . '/' . ltrim($endpoint, '/');
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        $headers = [
            'Accept'     => 'application/json',
            'User-Agent' => 'ScraperPlatform/1.0',
        ];

        $token = $this->config()->cardekhoBearerToken;
        if ($token !== '') {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }

    private function fixturePayload(string $path): string
    {
        $fullPath = ROOTPATH . ltrim($path, '/\\');
        if (! is_file($fullPath)) {
            throw new SourceUnavailableException("CarDekho fixture file not found at {$path}", 'FIXTURE_NOT_FOUND', 500);
        }

        $content = file_get_contents($fullPath);
        if ($content === false) {
            throw new SourceUnavailableException("Unable to read CarDekho fixture at {$path}", 'FIXTURE_NOT_FOUND', 500);
        }

        return $content;
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone($this->config()->collectionTimezone)))->format(DATE_ATOM);
    }

    private function config(): Sources
    {
        return $this->config ?? config(Sources::class);
    }

    private function parser(): CardekhoParser
    {
        return $this->parser ?? new CardekhoParser();
    }

    private function normalizer(): CardekhoNormalizer
    {
        return $this->normalizer ?? new CardekhoNormalizer($this->config());
    }

    private function client(): CURLRequest
    {
        return Services::curlrequest();
    }
}

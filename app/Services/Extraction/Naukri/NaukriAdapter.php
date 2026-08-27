<?php

namespace App\Services\Extraction\Naukri;

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

class NaukriAdapter implements SourceAdapterInterface
{
    public function __construct(
        private readonly ?Sources $config = null,
        private readonly ?NaukriParser $parser = null,
        private readonly ?NaukriNormalizer $normalizer = null,
    ) {
    }

    /**
     * @param array<string, mixed> $query
     */
    public function search(array $query): ExtractionResult
    {
        $config = $this->config();

        return match (strtolower($config->naukriMode)) {
            'fixture'         => $this->fixtureSearch($query),
            'authorized_http' => $this->authorizedSearch($query),
            default           => throw new SourceUnavailableException(
                'Naukri authorized data connector is not configured.',
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

        return match (strtolower($config->naukriMode)) {
            'fixture'         => $this->fixtureDetail($listingId),
            'authorized_http' => $this->authorizedDetail($listingId, $options),
            default           => throw new SourceUnavailableException(
                'Naukri authorized data connector is not configured.',
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

        return match (strtolower($config->naukriMode)) {
            'fixture'         => $this->fixtureDetailByUrl($listingUrl),
            'authorized_http' => $this->authorizedDetailByUrl($listingUrl, $options),
            default           => throw new SourceUnavailableException(
                'Naukri authorized URL lookup is not configured.',
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
        $payload = $this->requestJson('GET', $this->url($config->naukriSearchEndpoint), $this->sourceQuery($query));
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
        $endpoint = str_replace('{listing_id}', rawurlencode($listingId), $config->naukriDetailEndpoint);
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
        $payload = $this->requestJson('GET', $this->url($config->naukriSearchEndpoint), $query);
        $parsed = $this->parser()->parseDetail($payload);
        $item = $this->normalizer()->normalizeListing($parsed['item']);

        return new ExtractionResult([$item], ['page' => 1, 'limit' => 1, 'has_next' => false], $parsed['raw'], $this->now());
    }

    /**
     * @param array<string, mixed> $query
     */
    private function fixtureSearch(array $query): ExtractionResult
    {
        $parsed = $this->parser()->parseSearch($this->fixturePayload($this->config()->fixtureNaukriSearchPath));
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
        $detailPath = $this->config()->fixtureNaukriDetailPath;

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

        throw new ListingNotFoundException("Job listing {$listingId} was not found.");
    }

    private function fixtureDetailByUrl(string $listingUrl): ExtractionResult
    {
        $detailPath = $this->config()->fixtureNaukriDetailPath;

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

        throw new ListingNotFoundException("Job listing for URL {$listingUrl} was not found.");
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

            if (! empty($query['company']) && ! str_contains(strtolower((string) ($item['job']['company_name'] ?? '')), strtolower((string) $query['company']))) {
                return false;
            }

            if (! empty($query['keyword'])) {
                $kw = strtolower((string) $query['keyword']);
                $title = strtolower((string) ($item['title'] ?? ''));
                $desc = strtolower((string) ($item['description'] ?? ''));
                $comp = strtolower((string) ($item['job']['company_name'] ?? ''));
                if (! str_contains($title, $kw) && ! str_contains($desc, $kw) && ! str_contains($comp, $kw)) {
                    return false;
                }
            }

            if (! empty($query['skills'])) {
                $kwSkill = strtolower((string) $query['skills']);
                $skills = array_map('strtolower', (array) ($item['job']['skills'] ?? []));
                if (! in_array($kwSkill, $skills, true) && ! str_contains(implode(' ', $skills), $kwSkill)) {
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
        $attempts = max(1, $config->naukriRetryAttempts);
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $client = $this->client();
                $options = [
                    'query'       => $query,
                    'headers'     => $this->headers(),
                    'http_errors' => false,
                    'timeout'     => $config->naukriRequestTimeout,
                ];

                $response = $client->request($method, $url, $options);
                $status = $response->getStatusCode();
                $body = (string) $response->getBody();

                if ($status === 404) {
                    throw new ListingNotFoundException('Naukri listing was not found.');
                }

                if ($status >= 200 && $status < 300) {
                    $decoded = json_decode($body, true);
                    if (is_array($decoded)) {
                        return $decoded;
                    }

                    throw new SourceUnavailableException('Naukri response is not valid JSON.', 'PARSER_FAILURE', 502);
                }

                if ($status === 429) {
                    throw new SourceUnavailableException('Naukri rate limit encountered.', 'SOURCE_RATE_LIMITED', 429);
                }

                if ($status >= 500) {
                    throw new SourceUnavailableException("Naukri service returned {$status}.", 'SOURCE_HTTP_ERROR', 502);
                }

                throw new SourceUnavailableException("Naukri request failed with HTTP {$status}.", 'SOURCE_HTTP_ERROR', $status);
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
            'Unable to reach Naukri extraction service: ' . ($lastException?->getMessage() ?? 'unknown network failure'),
            'SOURCE_UNAVAILABLE',
            502,
        );
    }

    private function url(string $endpoint): string
    {
        $base = $this->config()->naukriAuthorizedBaseUrl;
        if ($base === '') {
            throw new SourceUnavailableException('Naukri authorized base URL is not configured.', 'SOURCE_NOT_CONFIGURED', 502);
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

        $token = $this->config()->naukriBearerToken;
        if ($token !== '') {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }

    private function fixturePayload(string $path): string
    {
        $fullPath = ROOTPATH . ltrim($path, '/\\');
        if (! is_file($fullPath)) {
            throw new SourceUnavailableException("Naukri fixture file not found at {$path}", 'FIXTURE_NOT_FOUND', 500);
        }

        $content = file_get_contents($fullPath);
        if ($content === false) {
            throw new SourceUnavailableException("Unable to read Naukri fixture at {$path}", 'FIXTURE_NOT_FOUND', 500);
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

    private function parser(): NaukriParser
    {
        return $this->parser ?? new NaukriParser();
    }

    private function normalizer(): NaukriNormalizer
    {
        return $this->normalizer ?? new NaukriNormalizer($this->config());
    }

    private function client(): CURLRequest
    {
        return Services::curlrequest();
    }
}

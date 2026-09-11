<?php

namespace App\Services\Extraction\Cashify;

use App\Services\Extraction\SourceUnavailableException;

class CashifyParser
{
    /**
     * @return array{items: list<array<string, mixed>>, pagination: array<string, mixed>, raw: array<string, mixed>}
     */
    public function parseSearch(array|string $payload): array
    {
        $decoded = $this->decode($payload);
        $items = $this->extractList($decoded);
        $pagination = $decoded['pagination'] ?? $decoded['meta']['pagination'] ?? [];

        return [
            'items'      => $items,
            'pagination' => is_array($pagination) ? $pagination : [],
            'raw'        => $decoded,
        ];
    }

    /**
     * @return array{item: array<string, mixed>, raw: array<string, mixed>}
     */
    public function parseDetail(array|string $payload): array
    {
        $decoded = $this->decode($payload);
        $item = $decoded['data'] ?? $decoded['listing'] ?? $decoded['item'] ?? $decoded;

        if (! is_array($item) || array_is_list($item)) {
            throw new SourceUnavailableException('Listing detail payload did not contain a valid Cashify listing object.', 'PARSER_FAILURE');
        }

        return [
            'item' => $item,
            'raw'  => $decoded,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(array|string $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        $decoded = json_decode($payload, true);

        if (! is_array($decoded)) {
            throw new SourceUnavailableException('Source returned invalid JSON.', 'PARSER_FAILURE');
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $decoded
     *
     * @return list<array<string, mixed>>
     */
    private function extractList(array $decoded): array
    {
        $candidates = [
            $decoded['data'] ?? null,
            $decoded['items'] ?? null,
            $decoded['results'] ?? null,
            $decoded['productList'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_array($candidate) && array_is_list($candidate)) {
                return $candidate;
            }
        }

        return [];
    }
}

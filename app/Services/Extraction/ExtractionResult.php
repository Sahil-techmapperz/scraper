<?php

namespace App\Services\Extraction;

class ExtractionResult
{
    /**
     * @param list<array<string, mixed>> $items
     * @param array<string, mixed>       $pagination
     * @param array<string, mixed>|null  $raw
     */
    public function __construct(
        public readonly array $items,
        public readonly array $pagination,
        public readonly ?array $raw,
        public readonly string $collectedAt,
        public readonly bool $sourceExtractionAttempted = true,
        public readonly bool $sourceExtractionSuccess = true,
    ) {
    }
}

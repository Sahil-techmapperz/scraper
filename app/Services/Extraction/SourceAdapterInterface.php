<?php

namespace App\Services\Extraction;

interface SourceAdapterInterface
{
    /**
     * @param array<string, mixed> $query
     */
    public function search(array $query): ExtractionResult;

    /**
     * @param array<string, mixed> $options
     */
    public function detail(string $listingId, array $options = []): ExtractionResult;

    /**
     * @param array<string, mixed> $options
     */
    public function detailByUrl(string $listingUrl, array $options = []): ExtractionResult;
}

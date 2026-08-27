<?php

namespace App\Services\Extraction;

use RuntimeException;

class SourceUnavailableException extends RuntimeException
{
    public function __construct(string $message, private readonly string $sourceCode = 'SOURCE_UNAVAILABLE', int $code = 502)
    {
        parent::__construct($message, $code);
    }

    public function sourceCode(): string
    {
        return $this->sourceCode;
    }
}

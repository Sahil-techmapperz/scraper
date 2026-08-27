<?php

namespace App\Services;

class ListingRecordValidator
{
    /**
     * @param array<string, mixed> $record
     *
     * @return list<string>
     */
    public function errors(array $record): array
    {
        $errors = [];

        if (($record['listing_id'] ?? '') === '') {
            $errors[] = 'listing_id is required.';
        }

        $url = $record['listing_url'] ?? null;

        if ($url !== null && filter_var($url, FILTER_VALIDATE_URL) === false) {
            $errors[] = 'listing_url must be a valid URL when present.';
        }

        $price = $record['price']['amount'] ?? null;

        if ($price !== null && ! is_numeric($price)) {
            $errors[] = 'price.amount must be numeric when present.';
        }

        $year = $record['automobile']['manufacturing_year'] ?? null;

        if ($year !== null && (! is_int($year) || $year < 1900 || $year > ((int) date('Y') + 1))) {
            $errors[] = 'automobile.manufacturing_year is outside the supported range.';
        }

        $kilometres = $record['automobile']['kilometres_driven'] ?? null;

        if ($kilometres !== null && ! is_numeric($kilometres)) {
            $errors[] = 'automobile.kilometres_driven must be numeric when present.';
        }

        return $errors;
    }

    /**
     * @param list<array<string, mixed>> $records
     *
     * @return list<array<string, mixed>>
     */
    public function validRecords(array $records): array
    {
        $valid = [];

        foreach ($records as $record) {
            $errors = $this->errors($record);

            if ($errors !== []) {
                log_message('warning', 'Dropping invalid normalized listing: {errors}', [
                    'errors' => implode(' ', $errors),
                ]);

                continue;
            }

            $valid[] = $record;
        }

        return $valid;
    }
}

<?php

namespace App\Services\Extraction\Olx;

use Config\Sources;
use DateTimeImmutable;
use DateTimeZone;

class OlxNormalizer
{
    public function __construct(private readonly ?Sources $config = null)
    {
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    public function normalizeListing(array $row): array
    {
        $config = $this->config ?? config(Sources::class);
        $categoryParts = $this->normalizeCategory($row);
        $priceAmount = $this->parseInteger($this->first($row, ['price.amount', 'price.value', 'price', 'amount']));
        $sellerName = $this->first($row, ['seller.name', 'user.name', 'owner.name', 'seller_name']);
        $sourceStatus = strtolower((string) ($this->first($row, ['availability', 'status', 'state']) ?? 'available'));
        $availability = in_array($sourceStatus, ['available', 'active', 'open'], true) ? 'available' : $sourceStatus;

        $record = [
            'listing_id'   => (string) ($this->first($row, ['listing_id', 'source_listing_id', 'id', 'ad_id', 'offer_id']) ?? ''),
            'listing_url'  => $this->nullableUrl($this->first($row, ['listing_url', 'source_url', 'url', 'permalink', 'link'])),
            'title'        => $this->nullableString($this->first($row, ['title', 'name', 'subject'])),
            'description'  => $this->nullableString($this->first($row, ['description', 'body', 'details'])),
            'category'     => $categoryParts['category'],
            'subcategory'  => $categoryParts['subcategory'],
            'price'        => [
                'amount'     => $priceAmount,
                'currency'   => (string) ($this->first($row, ['price.currency', 'currency']) ?? 'INR'),
                'negotiable' => $this->nullableBoolean($this->first($row, ['price.negotiable', 'negotiable'])),
            ],
            'seller'       => [
                'name'              => $this->nullableString($sellerName),
                'type'              => $this->nullableString($this->first($row, ['seller.type', 'user.type', 'posted_by'])),
                'contact_available' => (bool) ($this->first($row, ['seller.contact_available', 'contact_available']) ?? false),
            ],
            'location'     => [
                'state'    => $this->nullableString($this->first($row, ['location.state', 'state'])),
                'city'     => $this->nullableString($this->first($row, ['location.city', 'city'])),
                'locality' => $this->nullableString($this->first($row, ['location.locality', 'location.neighborhood', 'locality', 'neighborhood'])),
                'pincode'  => $this->nullableString($this->first($row, ['location.pincode', 'pincode'])),
            ],
            'images'       => $this->normalizeImages($row),
            'listing_date' => $this->normalizeDate($this->first($row, ['listing_date', 'created_at', 'posted_at', 'date'])),
            'last_updated' => $this->normalizeDateTime($this->first($row, ['last_updated', 'updated_at', 'last_source_update'])),
            'availability' => $availability ?: 'available',
            'collected_at' => $this->now($config),
        ];

        if ($categoryParts['domain'] === 'automobile') {
            $record['automobile'] = $this->normalizeAutomobile($row, $categoryParts);
        }

        if ($categoryParts['domain'] === 'mobile_phone') {
            $record['mobile_phone'] = $this->normalizeMobilePhone($row);
        }

        if ($categoryParts['domain'] === 'real_estate') {
            $record['real_estate'] = $this->normalizeRealEstate($row);
        }

        if ($config->exposeRawData) {
            $record['source_metadata'] = [
                'source'            => 'olx',
                'source_listing_id' => $record['listing_id'],
                'source_url'        => $record['listing_url'],
            ];
        }

        return $record;
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    public function normalizeMany(array $rows): array
    {
        return array_map(fn (array $row): array => $this->normalizeListing($row), $rows);
    }

    public function parsePrice(mixed $value): ?int
    {
        return $this->parseInteger($value);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{category: string|null, subcategory: string|null, domain: string|null}
     */
    private function normalizeCategory(array $row): array
    {
        $category = $this->slug($this->first($row, ['category.slug', 'category.name', 'category', 'category_name']));
        $subcategory = $this->slug($this->first($row, ['subcategory.slug', 'subcategory.name', 'subcategory', 'subcategory_name']));
        $candidate = $subcategory ?: $category;

        if (in_array($candidate, ['cars', 'car', 'bikes', 'bike', 'motorcycles', 'scooters'], true)) {
            return [
                'category'    => 'automobile',
                'subcategory' => str_contains($candidate, 'bike') || str_contains($candidate, 'scooter') || str_contains($candidate, 'motorcycle') ? 'bikes' : 'cars',
                'domain'      => 'automobile',
            ];
        }

        if (in_array($candidate, ['mobile-phones', 'phones', 'mobiles'], true)) {
            return [
                'category'    => 'mobile-phones',
                'subcategory' => 'mobile-phones',
                'domain'      => 'mobile_phone',
            ];
        }

        if (in_array($candidate, ['real-estate', 'apartments', 'houses', 'commercial-property', 'land-plots'], true)) {
            return [
                'category'    => 'real-estate',
                'subcategory' => $candidate === 'real-estate' ? null : $candidate,
                'domain'      => 'real_estate',
            ];
        }

        return [
            'category'    => $category ?: null,
            'subcategory' => $subcategory ?: null,
            'domain'      => null,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $categoryParts
     *
     * @return array<string, mixed>
     */
    private function normalizeAutomobile(array $row, array $categoryParts): array
    {
        return [
            'vehicle_type'          => $categoryParts['subcategory'] === 'bikes' ? 'bike' : 'car',
            'brand'                 => $this->attribute($row, ['brand', 'make', 'manufacturer']),
            'model'                 => $this->attribute($row, ['model']),
            'variant'               => $this->attribute($row, ['variant']),
            'manufacturing_year'    => $this->parseInteger($this->attribute($row, ['manufacturing_year', 'year', 'model_year'])),
            'kilometres_driven'     => $this->parseInteger($this->attribute($row, ['kilometres_driven', 'kilometers_driven', 'km_driven', 'mileage'])),
            'fuel_type'             => $this->lowerNullable($this->attribute($row, ['fuel_type', 'fuel'])),
            'transmission'          => $this->lowerNullable($this->attribute($row, ['transmission'])),
            'number_of_owners'      => $this->parseInteger($this->attribute($row, ['number_of_owners', 'owner_count', 'owners'])),
            'registration_location' => $this->attribute($row, ['registration_location', 'registration_city']),
            'colour'                => $this->attribute($row, ['colour', 'color']),
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeMobilePhone(array $row): array
    {
        return [
            'brand'       => $this->attribute($row, ['brand', 'make', 'manufacturer']),
            'model'       => $this->attribute($row, ['model']),
            'condition'   => $this->lowerNullable($this->attribute($row, ['condition'])),
            'ram_gb'      => $this->parseInteger($this->attribute($row, ['ram', 'ram_gb'])),
            'storage_gb'  => $this->parseInteger($this->attribute($row, ['storage', 'storage_gb', 'memory'])),
            'warranty'    => $this->nullableBoolean($this->attribute($row, ['warranty'])),
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeRealEstate(array $row): array
    {
        return [
            'listing_type'      => $this->lowerNullable($this->attribute($row, ['listing_type', 'purpose'])),
            'property_type'     => $this->lowerNullable($this->attribute($row, ['property_type', 'type'])),
            'property_category' => $this->lowerNullable($this->attribute($row, ['property_category'])),
            'bhk'               => $this->parseInteger($this->attribute($row, ['bhk', 'bedrooms'])),
            'area'              => $this->parseInteger($this->attribute($row, ['area', 'super_builtup_area', 'carpet_area'])),
            'furnishing'        => $this->lowerNullable($this->attribute($row, ['furnishing'])),
            'posted_by'         => $this->lowerNullable($this->attribute($row, ['posted_by', 'seller_type'])),
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return list<string>
     */
    private function normalizeImages(array $row): array
    {
        $images = $this->first($row, ['images', 'photos', 'pictures']);

        if (! is_array($images)) {
            return [];
        }

        $normalized = [];

        foreach ($images as $image) {
            $url = is_array($image) ? ($image['url'] ?? $image['href'] ?? null) : $image;
            $url = $this->nullableUrl($url);

            if ($url !== null) {
                $normalized[] = $url;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param list<string> $keys
     */
    private function attribute(array $row, array $keys): ?string
    {
        $normalizedKeys = array_map(fn (string $key): string => $this->slug($key), $keys);

        foreach ($keys as $key) {
            $direct = $this->first($row, ['attributes.' . $key, 'parameters.' . $key, $key]);

            if ($direct !== null && $direct !== '') {
                return $this->nullableString($direct);
            }
        }

        $bags = [$row['attributes'] ?? null, $row['parameters'] ?? null];

        foreach ($bags as $bag) {
            if (! is_array($bag) || ! array_is_list($bag)) {
                continue;
            }

            foreach ($bag as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $name = $this->slug($item['key'] ?? $item['name'] ?? $item['label'] ?? '');

                if (in_array($name, $normalizedKeys, true)) {
                    return $this->nullableString($item['value'] ?? $item['values'][0] ?? null);
                }
            }
        }

        return null;
    }

    /**
     * @param list<string> $paths
     */
    private function first(array $row, array $paths): mixed
    {
        foreach ($paths as $path) {
            $value = $this->path($row, $path);

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function path(array $row, string $path): mixed
    {
        $current = $row;

        foreach (explode('.', $path) as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    private function parseInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) round($value);
        }

        if (is_array($value)) {
            return $this->parseInteger($value['value'] ?? $value['amount'] ?? $value['raw'] ?? null);
        }

        $string = trim((string) $value);
        $number = preg_replace('/[^\d]/', '', $string);

        if ($number === '' || $number === null) {
            return null;
        }

        return (int) $number;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            $value = $value['name'] ?? $value['value'] ?? null;
            if ($value === null) {
                return null;
            }
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function lowerNullable(mixed $value): ?string
    {
        $string = $this->nullableString($value);

        return $string === null ? null : strtolower($string);
    }

    private function nullableBoolean(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    private function nullableUrl(mixed $value): ?string
    {
        $string = $this->nullableString($value);

        if ($string === null || filter_var($string, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return $string;
    }

    private function normalizeDate(mixed $value): ?string
    {
        $dateTime = $this->dateTime($value);

        return $dateTime?->format('Y-m-d');
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        $dateTime = $this->dateTime($value);

        return $dateTime?->format(DATE_ATOM);
    }

    private function dateTime(mixed $value): ?DateTimeImmutable
    {
        $string = $this->nullableString($value);

        if ($string === null) {
            return null;
        }

        try {
            return new DateTimeImmutable($string);
        } catch (\Exception) {
            return null;
        }
    }

    private function slug(mixed $value): string
    {
        $string = strtolower((string) $value);
        $string = preg_replace('/[^a-z0-9]+/', '-', $string) ?? '';

        return trim($string, '-');
    }

    private function now(Sources $config): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone($config->collectionTimezone)))->format(DATE_ATOM);
    }
}

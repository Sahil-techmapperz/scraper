<?php

namespace App\Services\Extraction\Cardekho;

use Config\Sources;
use DateTimeImmutable;
use DateTimeZone;

class CardekhoNormalizer
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
        $priceAmount = $this->parseInteger($this->first($row, ['price.value', 'price.amount', 'price', 'amount']));
        $sellerName = $this->first($row, ['seller.name', 'franchiseName', 'dealer.name', 'owner.name', 'seller_name']);
        $sourceStatus = strtolower((string) ($this->first($row, ['availability', 'status', 'state', 'activeText']) ?? 'available'));
        $availability = in_array($sourceStatus, ['available', 'active', 'open'], true) ? 'available' : $sourceStatus;

        $record = [
            'listing_id'   => (string) ($this->first($row, ['listing_id', 'source_listing_id', 'id', 'usedCarId', 'usedCarSkuId']) ?? ''),
            'listing_url'  => $this->nullableUrl($this->first($row, ['listing_url', 'source_url', 'url', 'vlink', 'link'])),
            'title'        => $this->nullableString($this->first($row, ['title', 'name', 'vid'])),
            'description'  => $this->nullableString($this->first($row, ['description', 'body', 'details'])),
            'category'     => 'automobile',
            'subcategory'  => 'cars',
            'price'        => [
                'amount'     => $priceAmount,
                'currency'   => (string) ($this->first($row, ['price.currency', 'currency']) ?? 'INR'),
                'negotiable' => $this->nullableBoolean($this->first($row, ['price.negotiable', 'negotiable'])),
            ],
            'seller'       => [
                'name'              => $this->nullableString($sellerName) ?? 'CarDekho Verified Seller',
                'type'              => $this->nullableString($this->first($row, ['seller.type', 'user_type', 'type'])) ?? 'dealer',
                'contact_available' => (bool) ($this->first($row, ['seller.contact_available', 'contact_available', 'leadForm']) ?? true),
            ],
            'location'     => [
                'state'    => $this->nullableString($this->first($row, ['location.state', 'state'])),
                'city'     => $this->nullableString($this->first($row, ['location.city', 'cityName', 'city'])),
                'locality' => $this->nullableString($this->first($row, ['location.locality', 'locality', 'loc'])),
                'pincode'  => $this->nullableString($this->first($row, ['location.pincode', 'pincode'])),
            ],
            'images'       => $this->normalizeImages($row),
            'listing_date' => $this->normalizeDate($this->first($row, ['listing_date', 'created_at', 'posted_at', 'date'])),
            'last_updated' => $this->normalizeDateTime($this->first($row, ['last_updated', 'updated_at', 'last_source_update'])),
            'availability' => $availability ?: 'available',
            'collected_at' => $this->now($config),
            'automobile'   => $this->normalizeAutomobile($row),
        ];

        if ($config->exposeRawData) {
            $record['source_metadata'] = [
                'source'            => 'cardekho',
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

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeAutomobile(array $row): array
    {
        $brand = $this->first($row, [
            'raw_data.automobile.brand',
            'automobile.brand',
            'carDetails.oem',
            'oem',
            'brand',
            'make',
        ]);

        $model = $this->first($row, [
            'raw_data.automobile.model',
            'automobile.model',
            'carDetails.modelName',
            'carDetails.model',
            'modelName',
            'model',
        ]);

        $variant = $this->first($row, [
            'raw_data.automobile.variant',
            'automobile.variant',
            'carDetails.variantName',
            'variantName',
            'variant',
        ]);

        $year = $this->parseInteger($this->first($row, [
            'raw_data.automobile.year',
            'automobile.year',
            'carDetails.modelYear',
            'myear',
            'modelYear',
            'year',
        ]));

        $km = $this->parseInteger($this->first($row, [
            'raw_data.automobile.kilometers',
            'automobile.kilometers',
            'carDetails.km',
            'km',
            'kilometers',
        ]));

        $fuel = $this->first($row, [
            'raw_data.automobile.fuel_type',
            'automobile.fuel_type',
            'carDetails.ft',
            'ft',
            'fuelType',
            'fuel',
        ]);

        $transmission = $this->first($row, [
            'raw_data.automobile.transmission',
            'automobile.transmission',
            'carDetails.transmission',
            'carDetails.tt',
            'tt',
            'transmission',
        ]);

        $bodyType = $this->first($row, [
            'raw_data.automobile.body_type',
            'automobile.body_type',
            'carDetails.bt',
            'bt',
            'bodyType',
            'body_type',
        ]);

        $ownersRaw = $this->first($row, [
            'raw_data.automobile.number_of_owners',
            'automobile.number_of_owners',
            'carDetails.ownerNo',
            'carDetails.owner',
            'owner',
            'owners',
        ]);

        $owners = 1;
        if (is_numeric($ownersRaw)) {
            $owners = (int) $ownersRaw;
        } elseif (is_string($ownersRaw) && preg_match('/(\d+)/', $ownersRaw, $m)) {
            $owners = (int) $m[1];
        }

        return [
            'vehicle_type'      => 'car',
            'brand'             => $this->nullableString($brand),
            'model'             => $this->nullableString($model),
            'variant'           => $this->nullableString($variant),
            'year'              => $year,
            'kilometers'        => $km,
            'fuel_type'         => $this->nullableString($fuel),
            'transmission'      => $this->nullableString($transmission),
            'body_type'         => $this->nullableString($bodyType),
            'number_of_owners'  => $owners,
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeImages(array $row): array
    {
        $rawImages = $this->first($row, ['images', 'photos', 'galleryDto.tabs', 'gallery']) ?? [];
        $normalized = [];
        $position = 0;

        if (is_array($rawImages)) {
            foreach ($rawImages as $item) {
                if (is_string($item)) {
                    $url = $this->nullableUrl($item);
                    if ($url !== null) {
                        $normalized[] = ['url' => $url, 'position' => $position++];
                    }
                } elseif (is_array($item)) {
                    if (isset($item['url'])) {
                        $url = $this->nullableUrl($item['url']);
                        if ($url !== null) {
                            $normalized[] = ['url' => $url, 'position' => $position++];
                        }
                    } elseif (isset($item['list']) && is_array($item['list'])) {
                        foreach ($item['list'] as $subItem) {
                            $subUrl = is_string($subItem) ? $subItem : ($subItem['url'] ?? null);
                            $url = $this->nullableUrl($subUrl);
                            if ($url !== null) {
                                $normalized[] = ['url' => $url, 'position' => $position++];
                            }
                        }
                    }
                }
            }
        }

        if ($normalized === []) {
            $primary = $this->nullableUrl($this->first($row, ['pi', 'image', 'primary_image']));
            if ($primary !== null) {
                $normalized[] = ['url' => $primary, 'position' => 0];
            }
        }

        return $normalized;
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

        if (is_string($value)) {
            $digits = preg_replace('/[^\d]/', '', $value);
            return $digits !== '' ? (int) $digits : null;
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);
        return $trimmed !== '' ? $trimmed : null;
    }

    private function nullableBoolean(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    private function nullableUrl(mixed $value): ?string
    {
        $string = $this->nullableString($value);
        if ($string === null) {
            return null;
        }

        if (str_starts_with($string, '/')) {
            $string = 'https://www.cardekho.com' . $string;
        }

        return filter_var($string, FILTER_VALIDATE_URL) ? $string : null;
    }

    private function normalizeDate(mixed $value): ?string
    {
        $string = $this->nullableString($value);
        if ($string === null) {
            return null;
        }

        try {
            return (new DateTimeImmutable($string))->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        $string = $this->nullableString($value);
        if ($string === null) {
            return null;
        }

        try {
            return (new DateTimeImmutable($string))->format(DATE_ATOM);
        } catch (\Throwable) {
            return null;
        }
    }

    private function now(Sources $config): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone($config->collectionTimezone)))->format(DATE_ATOM);
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string>         $keys
     */
    private function first(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = $this->getDotValue($data, $key);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function getDotValue(array $data, string $path): mixed
    {
        if (array_key_exists($path, $data)) {
            return $data[$path];
        }

        $segments = explode('.', $path);
        $current = $data;

        foreach ($segments as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        return $current;
    }
}

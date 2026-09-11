<?php

namespace App\Services\Extraction\Cashify;

use Config\Sources;
use DateTimeImmutable;
use DateTimeZone;

class CashifyNormalizer
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
        $priceAmount = $this->parseInteger($this->first($row, ['price.value', 'price.amount', 'salePrice', 'sale_price', 'effectivePrice', 'price']));
        $mrpAmount = $this->parseInteger($this->first($row, ['original_price', 'originalPrice', 'mrp']));

        $title = (string) ($this->first($row, ['title', 'productName', 'name']) ?? 'Refurbished Device');
        $cleanId = (string) ($this->first($row, ['listing_id', 'id', 'productId', 'productDiscoveryId']) ?? '');
        $url = $this->nullableUrl($this->first($row, ['url', 'listing_url', 'slug']));
        if ($url && ! str_starts_with($url, 'http')) {
            $url = 'https://www.cashify.in' . (str_starts_with($url, '/') ? '' : '/') . $url;
        }

        $brand = (string) ($this->first($row, ['brand', 'parameters.brand', 'raw_data.brand']) ?? $this->detectBrand($title));
        $category = (string) ($this->first($row, ['category.name', 'category.slug', 'category']) ?? 'Mobiles');
        $subcategory = (string) ($this->first($row, ['subcategory.name', 'subcategory.slug', 'subcategory']) ?? 'Refurbished Smartphones');

        $sellerName = (string) ($this->first($row, ['seller.name', 'assuredBy']) ?? 'Cashify Verified Store');

        $record = [
            'listing_id'     => $cleanId,
            'listing_url'    => $url,
            'title'          => $title,
            'description'    => (string) ($this->first($row, ['description']) ?? "{$title} - Certified refurbished device with warranty from Cashify."),
            'category'       => strtolower($category),
            'subcategory'    => strtolower($subcategory),
            'condition'      => 'refurbished',
            'price'          => [
                'amount'     => $priceAmount,
                'currency'   => 'INR',
                'negotiable' => false,
            ],
            'original_price' => $mrpAmount,
            'seller'       => [
                'name'              => $sellerName,
                'type'              => 'verified_store',
                'contact_available' => true,
            ],
            'location'     => [
                'state'    => (string) ($this->first($row, ['location.state']) ?? 'All India'),
                'city'     => (string) ($this->first($row, ['location.city']) ?? 'Pan India'),
                'locality' => (string) ($this->first($row, ['location.locality']) ?? 'Cashify Store'),
                'pincode'  => null,
            ],
            'images'       => $this->normalizeImages($row),
            'listing_date' => $this->now($config),
            'last_updated' => $this->now($config),
            'availability' => 'available',
            'collected_at' => $this->now($config),
            'electronics'  => $this->normalizeElectronics($row, $title, $brand, $mrpAmount, $priceAmount),
        ];

        if ($config->exposeRawData) {
            $record['source_metadata'] = [
                'source' => 'cashify',
                'raw'    => $row,
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
        $normalized = [];

        foreach ($rows as $row) {
            if (is_array($row)) {
                $normalized[] = $this->normalizeListing($row);
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return list<array{url: string, position: int}>
     */
    private function normalizeImages(array $row): array
    {
        $images = [];
        $rawImages = $row['images'] ?? [];

        if (is_array($rawImages)) {
            foreach ($rawImages as $idx => $img) {
                if (is_string($img) && filter_var($img, FILTER_VALIDATE_URL)) {
                    $images[] = ['url' => $img, 'position' => count($images)];
                } elseif (is_array($img) && isset($img['url']) && filter_var($img['url'], FILTER_VALIDATE_URL)) {
                    $images[] = ['url' => (string) $img['url'], 'position' => count($images)];
                }
            }
        }

        if (empty($images)) {
            $singleImg = $this->first($row, ['image', 'defaultImage', 'defaultProductImg']);
            if (is_string($singleImg) && filter_var($singleImg, FILTER_VALIDATE_URL)) {
                $images[] = ['url' => $singleImg, 'position' => 0];
            }
        }

        return $images;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeElectronics(array $row, string $title, string $brand, ?int $mrp, ?int $salePrice): array
    {
        $params = $this->flattenParameters($row['parameters'] ?? []);

        $grade = $params['grade'] ?? $this->first($row, ['grade', 'condition']) ?? 'Superb';
        $warranty = $params['warranty'] ?? $this->first($row, ['warranty', 'warrantyType']) ?? '6 Months Cashify Warranty';
        $ram = $params['ram'] ?? $this->extractRam($title);
        $storage = $params['storage'] ?? $this->extractStorage($title);
        $rating = $params['rating'] ?? $this->first($row, ['averageRating', 'rating']) ?? '4.5';
        $emi = $params['emi_amount'] ?? $this->first($row, ['emiAmount']) ?? null;

        return [
            'brand'          => $brand,
            'condition'      => 'Refurbished',
            'grade'          => is_string($grade) ? $grade : 'Refurbished',
            'warranty'       => is_array($warranty) ? implode(', ', $warranty) : (string) $warranty,
            'ram'            => $ram,
            'storage'        => $storage,
            'rating'         => (string) $rating,
            'emi_amount'     => $emi ? (int) $emi : null,
            'original_price' => $mrp,
            'sale_price'     => $salePrice,
            'discount'       => ($mrp && $salePrice && $mrp > $salePrice) ? round((($mrp - $salePrice) / $mrp) * 100) : null,
        ];
    }

    /**
     * @param mixed $parameters
     *
     * @return array<string, mixed>
     */
    private function flattenParameters(mixed $parameters): array
    {
        if (! is_array($parameters)) {
            return [];
        }

        $result = [];
        foreach ($parameters as $key => $param) {
            if (is_array($param) && isset($param['key'], $param['value'])) {
                $result[$param['key']] = $param['value'];
            } elseif (is_string($key)) {
                $result[$key] = $param;
            }
        }

        return $result;
    }

    private function detectBrand(string $title): string
    {
        $brands = ['Apple', 'Samsung', 'Xiaomi', 'Redmi', 'OnePlus', 'Google', 'Oppo', 'Vivo', 'Realme', 'Motorola', 'Nokia', 'Poco', 'Dell', 'HP', 'Lenovo', 'Asus', 'Acer', 'Sony'];
        foreach ($brands as $b) {
            if (stripos($title, $b) !== false) {
                return $b;
            }
        }

        return 'Generic';
    }

    private function extractRam(string $text): ?string
    {
        if (preg_match('/\b(\d+)\s*GB\s*RAM\b/i', $text, $matches)) {
            return $matches[1] . ' GB';
        }

        return null;
    }

    private function extractStorage(string $text): ?string
    {
        if (preg_match('/\b(16|32|64|128|256|512)\s*(?:GB|TB)\b/i', $text, $matches)) {
            return strtoupper($matches[0]);
        }
        if (preg_match('/\b(1|2)\s*TB\b/i', $text, $matches)) {
            return strtoupper($matches[0]);
        }

        return null;
    }

    private function parseInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/-?\d+/', str_replace(',', '', $value), $match)) {
            return (int) $match[0];
        }

        return null;
    }

    private function nullableUrl(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    private function now(Sources $config): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone($config->collectionTimezone)))->format(DATE_ATOM);
    }

    private function first(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            $val = $this->getDot($data, $key);
            if ($val !== null && $val !== '') {
                return $val;
            }
        }

        return null;
    }

    private function getDot(array $array, string $key): mixed
    {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        $segments = explode('.', $key);
        $current = $array;

        foreach ($segments as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        return $current;
    }
}

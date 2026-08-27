<?php

namespace App\Services\Persistence;

use App\Models\CategoryModel;
use App\Models\ListingAttributeModel;
use App\Models\ListingImageModel;
use App\Models\ListingLocationModel;
use App\Models\ListingModel;
use App\Models\SellerModel;
use App\Models\SourceModel;
use Config\Database;
use Config\Sources;
use Throwable;

class ListingPersistenceService
{
    public function __construct(private readonly ?Sources $config = null)
    {
    }

    /**
     * @param list<array<string, mixed>>    $records
     * @param array<string, mixed>|null     $raw
     */
    public function persist(string $sourceName, array $records, ?array $raw = null): void
    {
        $config = $this->config ?? config(Sources::class);

        if (! $config->persistListings || $records === []) {
            return;
        }

        try {
            $db = Database::connect();
            $db->transStart();
            $sourceId = $this->sourceId($sourceName, $config->country);

            foreach ($records as $record) {
                $this->persistRecord($sourceId, $record, $raw);
            }

            $db->transComplete();
        } catch (Throwable $exception) {
            log_message('error', 'Listing persistence failed: {message}', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, mixed>       $record
     * @param array<string, mixed>|null  $raw
     */
    private function persistRecord(int $sourceId, array $record, ?array $raw = null): void
    {
        $categoryId = $this->categoryId($sourceId, (string) ($record['subcategory'] ?? $record['category'] ?? 'uncategorized'));
        $sellerId = $this->sellerId($sourceId, $record['seller'] ?? []);
        $locationId = $this->locationId($record['location'] ?? []);
        $listingModel = new ListingModel();
        $sourceListingId = (string) $record['listing_id'];
        $existing = $listingModel
            ->where('source_id', $sourceId)
            ->where('source_listing_id', $sourceListingId)
            ->first();

        $payload = [
            'source_id'           => $sourceId,
            'source_listing_id'   => $sourceListingId,
            'source_url'          => $record['listing_url'] ?? null,
            'category_id'         => $categoryId,
            'title'               => $record['title'] ?? null,
            'description'         => $record['description'] ?? null,
            'price'               => $record['price']['amount'] ?? null,
            'currency'            => $record['price']['currency'] ?? 'INR',
            'seller_id'           => $sellerId,
            'location_id'         => $locationId,
            'listing_date'        => $record['listing_date'] ?? null,
            'last_source_update'  => $record['last_updated'] ?? null,
            'first_collected_at'  => $existing['first_collected_at'] ?? date('Y-m-d H:i:s'),
            'last_collected_at'   => date('Y-m-d H:i:s'),
            'status'              => $record['availability'] ?? 'available',
            'raw_data'            => $raw === null ? null : json_encode($raw, JSON_UNESCAPED_SLASHES),
        ];

        if ($existing === null) {
            $listingId = (int) $listingModel->insert($payload, true);
        } else {
            $listingId = (int) $existing['id'];
            $listingModel->update($listingId, $payload);
        }

        $this->replaceImages($listingId, $record['images'] ?? []);
        $this->replaceAttributes($listingId, $record);
    }

    private function sourceId(string $sourceName, string $country): int
    {
        $model = new SourceModel();
        $source = $model->where('name', $sourceName)->where('country', $country)->first();

        if ($source !== null) {
            return (int) $source['id'];
        }

        return (int) $model->insert([
            'name'    => $sourceName,
            'country' => $country,
            'status'  => 'active',
        ], true);
    }

    private function categoryId(int $sourceId, string $slug): int
    {
        $model = new CategoryModel();
        $category = $model->where('source_id', $sourceId)->where('slug', $slug)->first();

        if ($category !== null) {
            return (int) $category['id'];
        }

        return (int) $model->insert([
            'source_id' => $sourceId,
            'slug'      => $slug,
            'name'      => ucwords(str_replace('-', ' ', $slug)),
        ], true);
    }

    /**
     * @param array<string, mixed> $seller
     */
    private function sellerId(int $sourceId, array $seller): ?int
    {
        $name = $seller['name'] ?? null;

        if ($name === null) {
            return null;
        }

        $model = new SellerModel();
        $existing = $model
            ->where('source_id', $sourceId)
            ->where('name', $name)
            ->where('type', $seller['type'] ?? null)
            ->first();

        if ($existing !== null) {
            return (int) $existing['id'];
        }

        return (int) $model->insert([
            'source_id'         => $sourceId,
            'source_seller_id'  => null,
            'name'              => $name,
            'type'              => $seller['type'] ?? null,
            'contact_available' => (bool) ($seller['contact_available'] ?? false),
        ], true);
    }

    /**
     * @param array<string, mixed> $location
     */
    private function locationId(array $location): ?int
    {
        if (array_filter($location, static fn ($value): bool => $value !== null && $value !== '') === []) {
            return null;
        }

        $model = new ListingLocationModel();
        $existing = $model
            ->where('state', $location['state'] ?? null)
            ->where('city', $location['city'] ?? null)
            ->where('locality', $location['locality'] ?? null)
            ->where('pincode', $location['pincode'] ?? null)
            ->first();

        if ($existing !== null) {
            return (int) $existing['id'];
        }

        return (int) $model->insert([
            'state'    => $location['state'] ?? null,
            'city'     => $location['city'] ?? null,
            'locality' => $location['locality'] ?? null,
            'pincode'  => $location['pincode'] ?? null,
        ], true);
    }

    /**
     * @param list<string> $images
     */
    private function replaceImages(int $listingId, array $images): void
    {
        $model = new ListingImageModel();
        $model->where('listing_id', $listingId)->delete();

        foreach (array_values($images) as $position => $url) {
            $model->insert([
                'listing_id' => $listingId,
                'url'        => $url,
                'position'   => $position,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $record
     */
    private function replaceAttributes(int $listingId, array $record): void
    {
        $model = new ListingAttributeModel();
        $model->where('listing_id', $listingId)->delete();

        foreach (['automobile', 'mobile_phone', 'real_estate', 'job'] as $group) {
            if (! isset($record[$group]) || ! is_array($record[$group])) {
                continue;
            }

            foreach ($record[$group] as $key => $value) {
                if ($value === null) {
                    continue;
                }

                $attrValue = is_array($value) ? implode(', ', $value) : (string) $value;

                $model->insert([
                    'listing_id'      => $listingId,
                    'attribute_group' => $group,
                    'attribute_key'   => $key,
                    'attribute_value' => $attrValue,
                ]);
            }
        }
    }
}

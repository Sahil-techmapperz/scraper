<?php

use App\Services\Extraction\Olx\OlxNormalizer;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class OlxNormalizerTest extends CIUnitTestCase
{
    public function testNormalizesAutomobileListing(): void
    {
        $record = (new OlxNormalizer())->normalizeListing([
            'id'          => '123456789',
            'url'         => 'https://www.olx.in/item/123456789',
            'title'       => '2022 Honda City',
            'category'    => 'cars',
            'price'       => 'Rs 850000',
            'seller'      => ['name' => 'Owner', 'type' => 'owner'],
            'location'    => ['state' => 'West Bengal', 'city' => 'Kolkata', 'locality' => 'New Town'],
            'parameters'  => [
                ['key' => 'brand', 'value' => 'Honda'],
                ['key' => 'model', 'value' => 'City'],
                ['key' => 'kilometres driven', 'value' => '25,000 km'],
                ['key' => 'fuel type', 'value' => 'petrol'],
                ['key' => 'transmission', 'value' => 'manual'],
            ],
            'posted_at'   => '2026-08-20',
        ]);

        $this->assertSame('123456789', $record['listing_id']);
        $this->assertSame(850000, $record['price']['amount']);
        $this->assertSame('automobile', $record['category']);
        $this->assertSame('cars', $record['subcategory']);
        $this->assertSame('Honda', $record['automobile']['brand']);
        $this->assertSame(25000, $record['automobile']['kilometres_driven']);
        $this->assertSame('petrol', $record['automobile']['fuel_type']);
        $this->assertSame('2026-08-20', $record['listing_date']);
    }

    public function testNormalizesMobilePhoneListing(): void
    {
        $record = (new OlxNormalizer())->normalizeListing([
            'id'         => 'phone-1',
            'url'        => 'https://www.olx.in/item/phone-1',
            'title'      => 'iPhone 15',
            'category'   => 'mobile-phones',
            'price'      => 'Rs 58000',
            'attributes' => [
                'brand'     => 'Apple',
                'model'     => 'iPhone 15',
                'condition' => 'used',
                'storage'   => '128 GB',
                'ram'       => '6 GB',
                'warranty'  => 'no',
            ],
        ]);

        $this->assertSame('mobile-phones', $record['category']);
        $this->assertSame('Apple', $record['mobile_phone']['brand']);
        $this->assertSame(128, $record['mobile_phone']['storage_gb']);
        $this->assertFalse($record['mobile_phone']['warranty']);
    }

    public function testNormalizesRealEstateListing(): void
    {
        $record = (new OlxNormalizer())->normalizeListing([
            'id'         => 'home-1',
            'url'        => 'https://www.olx.in/item/home-1',
            'title'      => '2 BHK apartment',
            'category'   => 'real-estate',
            'subcategory'=> 'apartments',
            'price'      => 'Rs 6500000',
            'attributes' => [
                'listing_type'  => 'sale',
                'property_type' => 'apartment',
                'bhk'           => '2',
                'area'          => '980 sq ft',
            ],
        ]);

        $this->assertSame('real-estate', $record['category']);
        $this->assertSame('apartments', $record['subcategory']);
        $this->assertSame('sale', $record['real_estate']['listing_type']);
        $this->assertSame(2, $record['real_estate']['bhk']);
        $this->assertSame(980, $record['real_estate']['area']);
    }
}

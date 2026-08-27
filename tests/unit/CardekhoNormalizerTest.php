<?php

use App\Services\Extraction\Cardekho\CardekhoNormalizer;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class CardekhoNormalizerTest extends CIUnitTestCase
{
    public function testNormalizesCardekhoListing(): void
    {
        $normalizer = new CardekhoNormalizer();
        $record = $normalizer->normalizeListing([
            'id'             => 'cardekho-5525529',
            'usedCarId'      => '5525529',
            'title'          => '2025 Maruti Suzuki Wagon R VXI',
            'url'            => 'https://www.cardekho.com/used-car-details/used-Maruti-wagon-r-vxi-cars-Faridabad_02bf1a86.htm',
            'price'          => ['value' => 540000, 'currency' => 'INR'],
            'seller'         => ['name' => 'Car Owner', 'type' => 'individual', 'contact_available' => true],
            'location'       => ['city' => 'Faridabad', 'locality' => 'Sector 9'],
            'images'         => [
                ['url' => 'https://images10.gaadi.com/usedcar_image/5525529/img1.jpg', 'position' => 0]
            ],
            'automobile'     => [
                'brand'             => 'Maruti Suzuki',
                'model'             => 'Wagon R',
                'variant'           => 'VXI',
                'year'              => 2025,
                'kilometers'        => 10000,
                'fuel_type'         => 'Petrol',
                'transmission'      => 'Manual',
                'body_type'         => 'Hatchback',
                'number_of_owners'  => 1,
            ],
        ]);

        $this->assertSame('cardekho-5525529', $record['listing_id']);
        $this->assertSame('2025 Maruti Suzuki Wagon R VXI', $record['title']);
        $this->assertSame('automobile', $record['category']);
        $this->assertSame('cars', $record['subcategory']);
        $this->assertSame(540000, $record['price']['amount']);
        $this->assertSame('INR', $record['price']['currency']);
        $this->assertSame('Faridabad', $record['location']['city']);
        $this->assertSame('Maruti Suzuki', $record['automobile']['brand']);
        $this->assertSame('Wagon R', $record['automobile']['model']);
        $this->assertSame(2025, $record['automobile']['year']);
        $this->assertSame(10000, $record['automobile']['kilometers']);
        $this->assertSame('Petrol', $record['automobile']['fuel_type']);
        $this->assertSame('Manual', $record['automobile']['transmission']);
        $this->assertSame(1, $record['automobile']['number_of_owners']);
        $this->assertCount(1, $record['images']);
    }
}

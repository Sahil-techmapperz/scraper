<?php

use App\Services\Extraction\Cashify\CashifyNormalizer;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class CashifyNormalizerTest extends CIUnitTestCase
{
    public function testNormalizesRefurbishedPhoneListing(): void
    {
        $normalizer = new CashifyNormalizer();
        $record = $normalizer->normalizeListing([
            'listing_id'       => '312891',
            'productName'      => 'Apple iPhone 14 Pro - Refurbished',
            'listing_url'      => 'https://www.cashify.in/buy-refurbished-mobile-phones/apple-iphone-14-pro-deep-purple-128-gb',
            'sale_price'       => 62999,
            'original_price'   => 129900,
            'category'         => 'mobile-phones',
            'grade'            => 'Superb',
            'warranty'         => '6 Months Warranty',
            'defaultProductImg'=> 'https://s3n.cashify.in/cashify/product/test.png',
            'parameters'       => [
                'brand'   => 'Apple',
                'storage' => '128 GB',
                'ram'     => '6 GB',
                'color'   => 'Deep Purple',
            ],
        ]);

        $this->assertSame('312891', $record['listing_id']);
        $this->assertSame(62999, $record['price']['amount']);
        $this->assertSame(129900, $record['original_price']);
        $this->assertSame('mobile-phones', $record['category']);
        $this->assertSame('Apple', $record['electronics']['brand']);
        $this->assertSame('128 GB', $record['electronics']['storage']);
        $this->assertSame('Superb', $record['electronics']['grade']);
        $this->assertSame('6 Months Warranty', $record['electronics']['warranty']);
        $this->assertSame('refurbished', $record['condition']);
    }

    public function testNormalizesLaptopListing(): void
    {
        $normalizer = new CashifyNormalizer();
        $record = $normalizer->normalizeListing([
            'id'          => 'lap-999',
            'name'        => 'Apple MacBook Pro M1 16GB 512GB SSD',
            'url'         => 'https://www.cashify.in/buy-refurbished-laptops/apple-macbook-pro-m1',
            'price'       => 'Rs 74,999',
            'category'    => 'laptops',
            'grade'       => 'Good',
            'warranty'    => '6 Months',
        ]);

        $this->assertSame('lap-999', $record['listing_id']);
        $this->assertSame(74999, $record['price']['amount']);
        $this->assertSame('laptops', $record['category']);
        $this->assertSame('Apple', $record['electronics']['brand']);
        $this->assertSame('Good', $record['electronics']['grade']);
    }

    public function testParsesAndNormalizesFixtureData(): void
    {
        $fixturePath = dirname(__DIR__) . '/fixtures/cashify/search.json';
        $this->assertFileExists($fixturePath);

        $json = file_get_contents($fixturePath);
        $payload = json_decode($json, true);
        $this->assertIsArray($payload);
        $this->assertNotEmpty($payload['data']);

        $parser = new \App\Services\Extraction\Cashify\CashifyParser();
        $parsed = $parser->parseSearch($payload);
        $this->assertNotEmpty($parsed['items']);

        $normalizer = new CashifyNormalizer();
        $normalized = $normalizer->normalizeMany($parsed['items']);
        $this->assertNotEmpty($normalized);

        $first = $normalized[0];
        $this->assertSame('cashify-prod-estore-refurbished-catalogue-10446', $first['listing_id']);
        $this->assertSame(89599, $first['price']['amount']);
        $this->assertSame('Apple', $first['electronics']['brand']);
        $this->assertSame('refurbished', $first['condition']);
    }
}


<?php

use App\Services\InvalidRequestException;
use App\Services\ListingQueryValidator;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ListingQueryValidatorTest extends CIUnitTestCase
{
    public function testDefaultsPaginationAndLimit(): void
    {
        $query = (new ListingQueryValidator())->validate([
            'category' => 'cars',
            'city'     => 'Kolkata',
        ]);

        $this->assertSame(1, $query['page']);
        $this->assertSame(50, $query['limit']);
        $this->assertFalse($query['fresh']);
    }

    public function testRejectsInvalidPriceRange(): void
    {
        $this->expectException(InvalidRequestException::class);

        (new ListingQueryValidator())->validate([
            'category'  => 'cars',
            'min_price' => '900000',
            'max_price' => '300000',
        ]);
    }

    public function testRejectsLimitOverConfiguredMax(): void
    {
        $this->expectException(InvalidRequestException::class);

        (new ListingQueryValidator())->validate([
            'category' => 'cars',
            'limit'    => '101',
        ]);
    }

    public function testRejectsUnsupportedParameter(): void
    {
        $this->expectException(InvalidRequestException::class);

        (new ListingQueryValidator())->validate([
            'category'      => 'cars',
            'private_token' => 'nope',
        ]);
    }
}

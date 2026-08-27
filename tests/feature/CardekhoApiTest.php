<?php

use App\Database\Seeds\DatabaseSeeder;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * @internal
 */
final class CardekhoApiTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh = true;
    protected $namespace = 'App';
    protected $seed = DatabaseSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        Services::cache()->clean();
        Services::apiClientContext()->clear();
    }

    public function testRejectsMissingApiKey(): void
    {
        $response = $this->get('/api/v1/cardekho/listings?city=delhi-ncr');

        $response->assertStatus(401);
        $json = $this->json($response);
        $this->assertSame(false, $json['success']);
        $this->assertSame('UNAUTHORIZED', $json['error']['code']);
    }

    public function testSearchReturnsNormalizedListings(): void
    {
        $response = $this->withHeaders($this->headers())
            ->get('/api/v1/cardekho/listings?city=Faridabad&brand=Maruti');

        $response->assertOK();
        $json = $this->json($response);

        $this->assertTrue($json['success']);
        $this->assertSame('cardekho', $json['source']['platform']);
        $this->assertSame(1, $json['pagination']['page']);
        $this->assertNotEmpty($json['data']);
        $this->assertSame('automobile', $json['data'][0]['category']);
        $this->assertSame('cars', $json['data'][0]['subcategory']);
        $this->assertArrayHasKey('automobile', $json['data'][0]);
        $this->assertArrayHasKey('brand', $json['data'][0]['automobile']);
        $this->assertArrayHasKey('price', $json['data'][0]);
        $this->assertArrayHasKey('collected_at', $json['data'][0]);
    }

    public function testDetailReturnsFixtureRecord(): void
    {
        $response = $this->withHeaders($this->headers())
            ->get('/api/v1/cardekho/listings/cardekho-5525529');

        $response->assertOK();
        $json = $this->json($response);

        $this->assertTrue($json['success']);
        $this->assertSame('cardekho-5525529', $json['data']['listing_id']);
        $this->assertSame('automobile', $json['data']['category']);
        $this->assertSame(540000, $json['data']['price']['amount']);
    }

    public function testDetailByUrlReturnsFixtureRecord(): void
    {
        $url = 'https://www.cardekho.com/used-car-details/used-Maruti-wagon-r-vxi-cars-Faridabad_02bf1a86-4a4c-4fb7-9a26-b5c79ef08c0a.htm?adId=33252&adType=41';
        $response = $this->withHeaders($this->headers())
            ->get('/api/v1/cardekho/listing?url=' . urlencode($url));

        $response->assertOK();
        $json = $this->json($response);

        $this->assertTrue($json['success']);
        $this->assertSame('cardekho-5525529', $json['data']['listing_id']);
    }

    public function testInvalidParameterReturns400(): void
    {
        $response = $this->withHeaders($this->headers())
            ->get('/api/v1/cardekho/listings?min_price=900000&max_price=100000');

        $response->assertStatus(400);
        $json = $this->json($response);

        $this->assertSame('INVALID_PARAMETER', $json['error']['code']);
        $this->assertArrayHasKey('max_price', $json['error']['details']);
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return ['X-API-Key' => 'dev-local-api-key'];
    }

    /**
     * @return array<string, mixed>
     */
    private function json(CodeIgniter\Test\TestResponse $response): array
    {
        $decoded = json_decode((string) $response->getJSON(), true);

        $this->assertIsArray($decoded);

        return $decoded;
    }
}

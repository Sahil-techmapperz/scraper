<?php

use App\Database\Seeds\DatabaseSeeder;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * @internal
 */
final class OlxApiTest extends CIUnitTestCase
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
        $response = $this->get('/api/v1/olx/listings?category=cars');

        $response->assertStatus(401);
        $json = $this->json($response);
        $this->assertSame(false, $json['success']);
        $this->assertSame('UNAUTHORIZED', $json['error']['code']);
    }

    public function testSearchReturnsNormalizedListings(): void
    {
        $response = $this->withHeaders($this->headers())
            ->get('/api/v1/olx/listings?category=cars&city=Kolkata&brand=Honda&min_year=2020&max_price=900000');

        $response->assertOK();
        $json = $this->json($response);

        $this->assertTrue($json['success']);
        $this->assertSame('olx', $json['source']['platform']);
        $this->assertSame(1, $json['pagination']['page']);
        $this->assertCount(1, $json['data']);
        $this->assertSame('olx-car-1001', $json['data'][0]['listing_id']);
        $this->assertSame('automobile', $json['data'][0]['category']);
        $this->assertSame('Honda', $json['data'][0]['automobile']['brand']);
        $this->assertSame(850000, $json['data'][0]['price']['amount']);
        $this->assertArrayHasKey('collected_at', $json['data'][0]);
    }

    public function testSearchAcceptsAutomobileCategory(): void
    {
        $response = $this->withHeaders($this->headers())
            ->get('/api/v1/olx/listings?category=automobile&city=Kolkata');

        $response->assertOK();
        $json = $this->json($response);

        $this->assertTrue($json['success']);
        $this->assertSame('automobile', $json['request']['category']);
        foreach ($json['data'] as $item) {
            $this->assertSame('automobile', $item['category']);
        }
    }

    public function testDetailReturnsCurrentFixtureRecord(): void
    {
        $response = $this->withHeaders($this->headers())
            ->get('/api/v1/olx/listings/olx-car-1001');

        $response->assertOK();
        $json = $this->json($response);

        $this->assertSame('olx-car-1001', $json['data']['listing_id']);
        $this->assertStringContainsString('Insurance valid', $json['data']['description']);
        $this->assertSame(1, $json['data']['automobile']['number_of_owners']);
    }

    public function testInvalidParameterReturns400(): void
    {
        $response = $this->withHeaders($this->headers())
            ->get('/api/v1/olx/listings?category=cars&min_price=900000&max_price=100000');

        $response->assertStatus(400);
        $json = $this->json($response);

        $this->assertSame('INVALID_PARAMETER', $json['error']['code']);
        $this->assertArrayHasKey('max_price', $json['error']['details']);
    }

    public function testRateLimitReturns429(): void
    {
        $this->db->table('api_clients')->update(['rate_limit_per_minute' => 1]);

        $this->withHeaders($this->headers())
            ->get('/api/v1/olx/listings?category=cars')
            ->assertOK();

        $response = $this->withHeaders($this->headers())
            ->get('/api/v1/olx/listings?category=cars&fresh=true');

        $response->assertStatus(429);
        $response->assertHeader('Retry-After');
        $json = $this->json($response);
        $this->assertSame('RATE_LIMIT_EXCEEDED', $json['error']['code']);
    }

    public function testAdminUsageEndpointReturnsLogs(): void
    {
        $this->withHeaders($this->headers())
            ->get('/api/v1/olx/listings?category=mobile-phones')
            ->assertOK();

        $response = $this->withHeaders($this->headers())
            ->get('/api/v1/admin/usage');

        $response->assertOK();
        $json = $this->json($response);

        $this->assertTrue($json['success']);
        $this->assertArrayHasKey('recent', $json['data']);
        $this->assertGreaterThanOrEqual(1, count($json['data']['recent']));
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

<?php

use App\Database\Seeds\DatabaseSeeder;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * @internal
 */
final class NaukriApiTest extends CIUnitTestCase
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
        $response = $this->get('/api/v1/naukri/listings?city=bengaluru');

        $response->assertStatus(401);
        $json = $this->json($response);
        $this->assertSame(false, $json['success']);
        $this->assertSame('UNAUTHORIZED', $json['error']['code']);
    }

    public function testSearchReturnsNormalizedListings(): void
    {
        $response = $this->withHeaders($this->headers())
            ->get('/api/v1/naukri/listings?city=Bengaluru&keyword=Python');

        $response->assertOK();
        $json = $this->json($response);

        $this->assertTrue($json['success']);
        $this->assertSame('naukri', $json['source']['platform']);
        $this->assertSame(1, $json['pagination']['page']);
        $this->assertNotEmpty($json['data']);
        $this->assertSame('jobs', $json['data'][0]['category']);
        $this->assertArrayHasKey('job', $json['data'][0]);
        $this->assertArrayHasKey('company_name', $json['data'][0]['job']);
        $this->assertArrayHasKey('experience_required', $json['data'][0]['job']);
        $this->assertArrayHasKey('skills', $json['data'][0]['job']);
        $this->assertArrayHasKey('price', $json['data'][0]);
    }

    public function testDetailReturnsFixtureRecord(): void
    {
        $response = $this->withHeaders($this->headers())
            ->get('/api/v1/naukri/listings/1001');

        $response->assertOK();
        $json = $this->json($response);

        $this->assertTrue($json['success']);
        $this->assertSame('1001', $json['data']['listing_id']);
        $this->assertSame('jobs', $json['data']['category']);
        $this->assertSame('TechCorp Solutions', $json['data']['job']['company_name']);
    }

    public function testDetailByUrlReturnsFixtureRecord(): void
    {
        $url = 'https://www.naukri.com/job-listings-senior-python-backend-developer-1001';
        $response = $this->withHeaders($this->headers())
            ->get('/api/v1/naukri/listing?url=' . urlencode($url));

        $response->assertOK();
        $json = $this->json($response);

        $this->assertTrue($json['success']);
        $this->assertSame('1001', $json['data']['listing_id']);
    }

    public function testInvalidParameterReturns400(): void
    {
        $response = $this->withHeaders($this->headers())
            ->get('/api/v1/naukri/listings?min_experience=10&max_experience=2');

        $response->assertStatus(400);
        $json = $this->json($response);

        $this->assertSame('INVALID_PARAMETER', $json['error']['code']);
        $this->assertArrayHasKey('max_experience', $json['error']['details']);
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

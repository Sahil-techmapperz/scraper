<?php

use App\Services\Extraction\Naukri\NaukriNormalizer;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class NaukriNormalizerTest extends CIUnitTestCase
{
    public function testNormalizesNaukriListing(): void
    {
        $normalizer = new NaukriNormalizer();
        $record = $normalizer->normalizeListing([
            'id'          => 'naukri-1001',
            'title'       => 'Senior Python Backend Developer',
            'url'         => 'https://www.naukri.com/job-listings-senior-python-backend-developer-1001',
            'description' => 'Looking for an experienced Python developer proficient in FastAPI and PostgreSQL.',
            'price'       => ['value' => 1800000, 'currency' => 'INR'],
            'seller'      => ['name' => 'TechCorp Solutions', 'type' => 'employer', 'contact_available' => true],
            'location'    => ['city' => 'Bengaluru', 'locality' => 'Whitefield'],
            'job'         => [
                'company_name'        => 'TechCorp Solutions',
                'experience_required' => '4-8 Yrs',
                'salary_text'         => '₹ 18-24 Lacs P.A.',
                'skills'              => ['Python', 'FastAPI', 'PostgreSQL', 'Docker'],
                'company_rating'      => 4.2,
                'review_count'        => '1.5k Reviews',
                'posted_age'          => '1 day ago',
                'apply_url'           => 'https://www.naukri.com/job-listings-senior-python-backend-developer-1001',
            ],
        ]);

        $this->assertSame('naukri-1001', $record['listing_id']);
        $this->assertSame('Senior Python Backend Developer', $record['title']);
        $this->assertSame('jobs', $record['category']);
        $this->assertSame(1800000, $record['price']['amount']);
        $this->assertSame('INR', $record['price']['currency']);
        $this->assertSame('Bengaluru', $record['location']['city']);
        $this->assertSame('TechCorp Solutions', $record['job']['company_name']);
        $this->assertSame('4-8 Yrs', $record['job']['experience_required']);
        $this->assertSame('₹ 18-24 Lacs P.A.', $record['job']['salary_text']);
        $this->assertSame(4.2, $record['job']['company_rating']);
        $this->assertContains('Python', $record['job']['skills']);
        $this->assertContains('FastAPI', $record['job']['skills']);
    }
}

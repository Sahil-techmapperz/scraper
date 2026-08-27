<?php

namespace App\Services\Extraction\Naukri;

use Config\Sources;
use DateTimeImmutable;
use DateTimeZone;

class NaukriNormalizer
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
        $priceAmount = $this->parsePrice($this->first($row, ['price.value', 'price.amount', 'price', 'salary', 'salary_num']));
        $companyName = $this->first($row, ['job.company_name', 'raw_data.job.company_name', 'seller.name', 'company', 'companyName', 'hiringOrganization.name']) ?? 'Hiring Company';
        $sourceStatus = strtolower((string) ($this->first($row, ['availability', 'status', 'state']) ?? 'active'));
        $availability = in_array($sourceStatus, ['active', 'open', 'available'], true) ? 'available' : $sourceStatus;

        $record = [
            'listing_id'   => (string) ($this->first($row, ['listing_id', 'source_listing_id', 'id', 'job_id', 'jobId']) ?? ''),
            'listing_url'  => $this->nullableUrl($this->first($row, ['listing_url', 'source_url', 'url', 'job_url', 'applyUrl', 'link'])),
            'title'        => $this->nullableString($this->first($row, ['title', 'jobTitle', 'name'])),
            'description'  => $this->nullableString($this->first($row, ['description', 'jobDescription', 'body', 'details'])),
            'category'     => 'jobs',
            'subcategory'  => 'tech-jobs',
            'price'        => [
                'amount'     => $priceAmount,
                'currency'   => (string) ($this->first($row, ['price.currency', 'currency']) ?? 'INR'),
                'negotiable' => false,
            ],
            'seller'       => [
                'name'              => $this->nullableString($companyName) ?? 'Hiring Company',
                'type'              => 'employer',
                'contact_available' => true,
            ],
            'location'     => [
                'state'    => $this->nullableString($this->first($row, ['location.state', 'state'])),
                'city'     => $this->nullableString($this->first($row, ['location.city', 'city', 'cityName'])),
                'locality' => $this->nullableString($this->first($row, ['location.locality', 'location', 'loc'])),
                'pincode'  => $this->nullableString($this->first($row, ['location.pincode', 'pincode'])),
            ],
            'images'       => $this->normalizeImages($row),
            'listing_date' => $this->normalizeDate($this->first($row, ['listing_date', 'created_at', 'posted_at', 'date', 'createdDate'])),
            'last_updated' => $this->normalizeDateTime($this->first($row, ['last_updated', 'updated_at', 'last_source_update'])),
            'availability' => $availability ?: 'available',
            'collected_at' => $this->now($config),
            'job'          => $this->normalizeJob($row),
        ];

        if ($config->exposeRawData) {
            $record['source_metadata'] = [
                'source'            => 'naukri',
                'source_listing_id' => $record['listing_id'],
                'source_url'        => $record['listing_url'],
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
        return array_map(fn (array $row): array => $this->normalizeListing($row), $rows);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeJob(array $row): array
    {
        $companyName = $this->first($row, [
            'raw_data.job.company_name',
            'job.company_name',
            'company',
            'companyName',
            'seller.name',
        ]) ?? 'Hiring Company';

        $expText = $this->first($row, [
            'raw_data.job.experience_required',
            'job.experience_required',
            'experience',
            'experienceText',
        ]) ?? '0-3 Yrs';

        $salaryText = $this->first($row, [
            'raw_data.job.salary_text',
            'job.salary_text',
            'salary_text',
            'salary',
            'salaryText',
        ]) ?? 'Not Disclosed';

        $skills = $this->first($row, [
            'raw_data.job.skills',
            'job.skills',
            'skills',
            'tags',
            'keySkills',
            'tagsAndSkills',
        ]) ?? [];

        if (is_string($skills)) {
            $skills = array_values(array_filter(array_map('trim', explode(',', $skills))));
        }

        $rating = $this->first($row, [
            'raw_data.job.company_rating',
            'raw_data.job.rating',
            'job.company_rating',
            'job.rating',
            'rating',
            'company_rating',
            'companyRating',
        ]);

        $reviews = $this->first($row, [
            'raw_data.job.review_count',
            'raw_data.job.reviews',
            'job.review_count',
            'job.reviews',
            'reviews',
            'review_count',
            'companyReviews',
        ]);

        $postedAge = $this->first($row, [
            'raw_data.job.posted_age',
            'job.posted_age',
            'posted_age',
            'posted_date',
            'createdDate',
        ]) ?? 'Recently';

        $applyUrl = $this->first($row, [
            'raw_data.job.apply_url',
            'job.apply_url',
            'apply_url',
            'job_url',
            'listing_url',
            'url',
        ]);

        return [
            'company_name'        => (string) $companyName,
            'experience_required' => (string) $expText,
            'salary_text'         => (string) $salaryText,
            'skills'              => is_array($skills) ? $skills : [],
            'company_rating'      => is_numeric($rating) ? (float) $rating : null,
            'review_count'        => $this->nullableString($reviews),
            'posted_age'          => (string) $postedAge,
            'apply_url'           => $this->nullableUrl($applyUrl),
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeImages(array $row): array
    {
        $images = $this->first($row, ['images', 'photos', 'company_logo', 'logo']) ?? [];
        $normalized = [];
        $position = 0;

        if (is_string($images)) {
            $url = $this->nullableUrl($images);
            if ($url !== null) {
                $normalized[] = ['url' => $url, 'position' => 0];
            }
        } elseif (is_array($images)) {
            foreach ($images as $item) {
                $url = is_array($item) ? ($item['url'] ?? null) : $item;
                $url = $this->nullableUrl($url);
                if ($url !== null) {
                    $normalized[] = ['url' => $url, 'position' => $position++];
                }
            }
        }

        return $normalized;
    }

    private function parsePrice(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) round($value);
        }

        if (is_string($value)) {
            if (preg_match('/(\d+(?:\.\d+)?)/', $value, $m)) {
                $num = (float) $m[1];
                if (stripos($value, 'lac') !== false || stripos($value, 'lpa') !== false || stripos($value, 'pa') !== false) {
                    return (int) ($num * 100000);
                }
                return (int) $num;
            }
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);
        return $trimmed !== '' ? $trimmed : null;
    }

    private function nullableUrl(mixed $value): ?string
    {
        $string = $this->nullableString($value);
        if ($string === null) {
            return null;
        }

        if (str_starts_with($string, '/')) {
            $string = 'https://www.naukri.com' . $string;
        }

        return filter_var($string, FILTER_VALIDATE_URL) ? $string : null;
    }

    private function normalizeDate(mixed $value): ?string
    {
        $string = $this->nullableString($value);
        if ($string === null) {
            return null;
        }

        try {
            return (new DateTimeImmutable($string))->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        $string = $this->nullableString($value);
        if ($string === null) {
            return null;
        }

        try {
            return (new DateTimeImmutable($string))->format(DATE_ATOM);
        } catch (\Throwable) {
            return null;
        }
    }

    private function now(Sources $config): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone($config->collectionTimezone)))->format(DATE_ATOM);
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string>         $keys
     */
    private function first(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = $this->getDotValue($data, $key);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function getDotValue(array $data, string $path): mixed
    {
        if (array_key_exists($path, $data)) {
            return $data[$path];
        }

        $segments = explode('.', $path);
        $current = $data;

        foreach ($segments as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        return $current;
    }
}

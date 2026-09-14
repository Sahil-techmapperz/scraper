<?php

namespace App\Services;

use Config\Api;
use DateTimeImmutable;

class ListingQueryValidator
{
    /**
     * @var list<string>
     */
    private array $allowedParameters = [
        'category',
        'subcategory',
        'keyword',
        'state',
        'city',
        'locality',
        'pincode',
        'min_price',
        'max_price',
        'sort',
        'page',
        'limit',
        'posted_from',
        'posted_to',
        'fresh',
        'vehicle_type',
        'brand',
        'model',
        'variant',
        'min_year',
        'max_year',
        'fuel_type',
        'transmission',
        'min_km',
        'max_km',
        'owner_count',
        'condition',
        'min_ram',
        'max_ram',
        'min_storage',
        'max_storage',
        'warranty',
        'listing_type',
        'property_type',
        'property_category',
        'bhk',
        'min_area',
        'max_area',
        'furnishing',
        'posted_by',
        'experience',
        'min_experience',
        'max_experience',
        'skills',
        'company',
        'salary_range',
        'job_type',
        'industry',
    ];

    /**
     * @var array<string, list<string>>
     */
    private array $allowedValues = [
        'category'      => [
            'automobile',
            'automobiles',
            'cars',
            'bikes',
            'properties',
            'real-estate',
            'electronics',
            'electronics-appliances',
            'mobiles',
            'mobile-phones',
            'jobs',
            'furniture',
            'fashion',
            'pets',
            'services',
            'laptops',
            'tablets',
            'smartwatches',
            'accessories',
            'gaming-consoles',
            'software-engineer',
            'data-scientist',
            'devops-engineer',
            'product-manager',
            'frontend-developer',
            'backend-developer',
            'full-stack-developer',
            'qa-engineer',
            'marketing',
            'sales',
            'hr',
            'finance',
        ],

        'sort'          => ['newest', 'oldest', 'price_low_to_high', 'price_high_to_low'],
        'fuel_type'     => ['petrol', 'diesel', 'cng', 'electric', 'hybrid', 'lpg'],
        'transmission'  => ['manual', 'automatic'],
        'condition'     => ['new', 'used', 'refurbished'],
        'warranty'      => ['yes', 'no'],
        'listing_type'  => ['sale', 'rent'],
        'furnishing'    => ['furnished', 'semi-furnished', 'unfurnished'],
        'posted_by'     => ['owner', 'dealer', 'builder', 'agent'],
    ];

    public function __construct(private readonly ?Api $config = null)
    {
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function validate(array $input): array
    {
        $config = $this->config ?? config(Api::class);
        $errors = [];
        $normalized = [];

        foreach ($input as $key => $value) {
            if (! in_array($key, $this->allowedParameters, true)) {
                $errors[$key] = 'Unsupported parameter.';
            }
        }

        foreach ([
            'category',
            'subcategory',
            'keyword',
            'state',
            'city',
            'locality',
            'brand',
            'model',
            'variant',
            'vehicle_type',
            'property_type',
            'property_category',
            'skills',
            'company',
            'salary_range',
            'job_type',
            'industry',
        ] as $key) {
            if (array_key_exists($key, $input)) {
                $normalized[$key] = $this->stringValue($input[$key], $key, $errors);
            }
        }

        foreach ($this->allowedValues as $key => $allowed) {
            if (array_key_exists($key, $input)) {
                $value = strtolower((string) $this->stringValue($input[$key], $key, $errors));

                if ($value !== '' && ! in_array($value, $allowed, true)) {
                    $errors[$key] = 'Unsupported value.';
                }

                $normalized[$key] = $value;
            }
        }

        if (array_key_exists('pincode', $input)) {
            $pincode = trim((string) $input['pincode']);

            if ($pincode !== '' && preg_match('/^\d{6}$/', $pincode) !== 1) {
                $errors['pincode'] = 'pincode must be a six digit Indian PIN code.';
            }

            $normalized['pincode'] = $pincode;
        }

        foreach ([
            'min_price',
            'max_price',
            'page',
            'limit',
            'min_year',
            'max_year',
            'min_km',
            'max_km',
            'owner_count',
            'min_ram',
            'max_ram',
            'min_storage',
            'max_storage',
            'bhk',
            'min_area',
            'max_area',
            'experience',
            'min_experience',
            'max_experience',
        ] as $key) {
            if (array_key_exists($key, $input)) {
                $normalized[$key] = $this->integerValue($input[$key], $key, $errors);
            }
        }

        $normalized['page'] = max(1, (int) ($normalized['page'] ?? 1));
        $limit = (int) ($normalized['limit'] ?? $config->defaultLimit);

        if ($limit < 1) {
            $errors['limit'] = 'limit must be at least 1.';
        }

        if ($limit > $config->maxLimit) {
            $errors['limit'] = 'limit must not exceed ' . $config->maxLimit . '.';
        }

        $normalized['limit'] = $limit;

        foreach (['posted_from', 'posted_to'] as $key) {
            if (array_key_exists($key, $input)) {
                $normalized[$key] = $this->dateValue($input[$key], $key, $errors);
            }
        }

        $normalized['fresh'] = filter_var($input['fresh'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $this->validateRange('min_price', 'max_price', $normalized, $errors);
        $this->validateRange('min_year', 'max_year', $normalized, $errors);
        $this->validateRange('min_km', 'max_km', $normalized, $errors);
        $this->validateRange('min_ram', 'max_ram', $normalized, $errors);
        $this->validateRange('min_storage', 'max_storage', $normalized, $errors);
        $this->validateRange('min_area', 'max_area', $normalized, $errors);
        $this->validateRange('min_experience', 'max_experience', $normalized, $errors);

        if (isset($normalized['posted_from'], $normalized['posted_to']) && $normalized['posted_from'] > $normalized['posted_to']) {
            $errors['posted_to'] = 'posted_to must be on or after posted_from.';
        }

        if (isset($normalized['min_year']) && ($normalized['min_year'] < 1900 || $normalized['min_year'] > ((int) date('Y') + 1))) {
            $errors['min_year'] = 'min_year is outside the supported range.';
        }

        if (isset($normalized['max_year']) && ($normalized['max_year'] < 1900 || $normalized['max_year'] > ((int) date('Y') + 1))) {
            $errors['max_year'] = 'max_year is outside the supported range.';
        }

        if ($errors !== []) {
            throw new InvalidRequestException($errors);
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * @param array<string, string> $errors
     */
    private function stringValue(mixed $value, string $key, array &$errors): string
    {
        $string = trim((string) $value);

        if (mb_strlen($string) > 120) {
            $errors[$key] = $key . ' must be 120 characters or fewer.';
        }

        if ($string !== '' && preg_match('/[\x00-\x1F\x7F]/', $string) === 1) {
            $errors[$key] = $key . ' contains invalid control characters.';
        }

        return $string;
    }

    /**
     * @param array<string, string> $errors
     */
    private function integerValue(mixed $value, string $key, array &$errors): int
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === '' || filter_var($value, FILTER_VALIDATE_INT) === false) {
            $errors[$key] = $key . ' must be an integer.';

            return 0;
        }

        $integer = (int) $value;

        if ($integer < 0) {
            $errors[$key] = $key . ' must be greater than or equal to 0.';
        }

        return $integer;
    }

    /**
     * @param array<string, string> $errors
     */
    private function dateValue(mixed $value, string $key, array &$errors): string
    {
        $string = trim((string) $value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $string);

        if ($date === false || $date->format('Y-m-d') !== $string) {
            $errors[$key] = $key . ' must use YYYY-MM-DD format.';
        }

        return $string;
    }

    /**
     * @param array<string, mixed>  $normalized
     * @param array<string, string> $errors
     */
    private function validateRange(string $minKey, string $maxKey, array $normalized, array &$errors): void
    {
        if (isset($normalized[$minKey], $normalized[$maxKey]) && $normalized[$maxKey] < $normalized[$minKey]) {
            $errors[$maxKey] = $maxKey . ' must be greater than or equal to ' . $minKey . '.';
        }
    }
}

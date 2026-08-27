<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Api extends BaseConfig
{
    public int $defaultLimit = 50;
    public int $maxLimit = 300;
    public int $defaultRateLimit = 100;
    public int $defaultMonthlyQuota = 10000;
    public bool $usageLoggingEnabled = true;
    public string $apiKeyPepper = 'change-this-api-key-pepper';

    public function __construct()
    {
        parent::__construct();

        $this->defaultLimit        = (int) env('API_DEFAULT_LIMIT', $this->defaultLimit);
        $this->maxLimit            = (int) env('API_MAX_LIMIT', $this->maxLimit);
        $this->defaultRateLimit    = (int) env('API_RATE_LIMIT', $this->defaultRateLimit);
        $this->defaultMonthlyQuota = (int) env('API_MONTHLY_QUOTA', $this->defaultMonthlyQuota);
        $this->usageLoggingEnabled = filter_var(env('API_USAGE_LOGGING', $this->usageLoggingEnabled), FILTER_VALIDATE_BOOLEAN);
        $this->apiKeyPepper        = (string) env('API_KEY_PEPPER', $this->apiKeyPepper);
    }
}

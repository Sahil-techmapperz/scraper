<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Sources extends BaseConfig
{
    public string $country = 'IN';

    // OLX Configuration
    public string $olxMode = 'disabled';
    public string $olxAuthorizedBaseUrl = '';
    public string $olxSearchEndpoint = '/listings';
    public string $olxDetailEndpoint = '/listings/{listing_id}';
    public string $olxBearerToken = '';
    public int $olxRequestTimeout = 30;
    public int $olxRetryAttempts = 2;
    public bool $olxCacheEnabled = true;
    public int $olxCacheTtl = 300;
    public string $fixtureSearchPath = 'tests/fixtures/olx/search.json';
    public string $fixtureDetailPath = 'tests/fixtures/olx/detail.json';

    // CarDekho Configuration
    public string $cardekhoMode = 'disabled';
    public string $cardekhoAuthorizedBaseUrl = '';
    public string $cardekhoSearchEndpoint = '/cardekho/listings';
    public string $cardekhoDetailEndpoint = '/cardekho/listings/{listing_id}';
    public string $cardekhoBearerToken = '';
    public int $cardekhoRequestTimeout = 30;
    public int $cardekhoRetryAttempts = 2;
    public bool $cardekhoCacheEnabled = true;
    public int $cardekhoCacheTtl = 300;
    public string $fixtureCardekhoSearchPath = 'tests/fixtures/cardekho/search.json';
    public string $fixtureCardekhoDetailPath = 'tests/fixtures/cardekho/detail.json';

    // Naukri Configuration
    public string $naukriMode = 'disabled';
    public string $naukriAuthorizedBaseUrl = '';
    public string $naukriSearchEndpoint = '/naukri/listings';
    public string $naukriDetailEndpoint = '/naukri/listings/{listing_id}';
    public string $naukriBearerToken = '';
    public int $naukriRequestTimeout = 30;
    public int $naukriRetryAttempts = 2;
    public bool $naukriCacheEnabled = true;
    public int $naukriCacheTtl = 300;
    public string $fixtureNaukriSearchPath = 'tests/fixtures/naukri/search.json';
    public string $fixtureNaukriDetailPath = 'tests/fixtures/naukri/detail.json';

    // Cashify Configuration
    public string $cashifyMode = 'disabled';
    public string $cashifyAuthorizedBaseUrl = '';
    public string $cashifySearchEndpoint = '/cashify/listings';
    public string $cashifyDetailEndpoint = '/cashify/listings/{listing_id}';
    public string $cashifyBearerToken = '';
    public int $cashifyRequestTimeout = 30;
    public int $cashifyRetryAttempts = 2;
    public bool $cashifyCacheEnabled = true;
    public int $cashifyCacheTtl = 300;
    public string $fixtureCashifySearchPath = 'tests/fixtures/cashify/search.json';
    public string $fixtureCashifyDetailPath = 'tests/fixtures/cashify/detail.json';

    // Global
    public bool $persistListings = false;

    public bool $exposeRawData = false;
    public string $collectionTimezone = 'Asia/Kolkata';

    public function __construct()
    {
        parent::__construct();

        $defaultMode = ENVIRONMENT === 'testing' ? 'fixture' : 'disabled';

        $this->country                   = (string) env('SOURCE_COUNTRY', $this->country);

        // OLX
        $this->olxMode                   = (string) env('OLX_CONNECTOR_MODE', $defaultMode);
        $this->olxAuthorizedBaseUrl      = rtrim((string) env('OLX_AUTHORIZED_API_BASE_URL', $this->olxAuthorizedBaseUrl), '/');
        $this->olxSearchEndpoint         = (string) env('OLX_SEARCH_ENDPOINT', $this->olxSearchEndpoint);
        $this->olxDetailEndpoint         = (string) env('OLX_DETAIL_ENDPOINT', $this->olxDetailEndpoint);
        $this->olxBearerToken            = (string) env('OLX_AUTHORIZED_API_TOKEN', $this->olxBearerToken);
        $this->olxRequestTimeout         = (int) env('OLX_REQUEST_TIMEOUT', $this->olxRequestTimeout);
        $this->olxRetryAttempts          = (int) env('OLX_RETRY_ATTEMPTS', $this->olxRetryAttempts);
        $this->olxCacheEnabled           = filter_var(env('OLX_CACHE_ENABLED', $this->olxCacheEnabled), FILTER_VALIDATE_BOOLEAN);
        $this->olxCacheTtl               = (int) env('OLX_CACHE_TTL', $this->olxCacheTtl);
        $this->fixtureSearchPath         = (string) env('OLX_FIXTURE_SEARCH_PATH', $this->fixtureSearchPath);
        $this->fixtureDetailPath         = (string) env('OLX_FIXTURE_DETAIL_PATH', $this->fixtureDetailPath);

        // CarDekho
        $this->cardekhoMode              = (string) env('CARDEKHO_CONNECTOR_MODE', $defaultMode);
        $this->cardekhoAuthorizedBaseUrl = rtrim((string) env('CARDEKHO_AUTHORIZED_API_BASE_URL', $this->cardekhoAuthorizedBaseUrl), '/');
        $this->cardekhoSearchEndpoint    = (string) env('CARDEKHO_SEARCH_ENDPOINT', $this->cardekhoSearchEndpoint);
        $this->cardekhoDetailEndpoint    = (string) env('CARDEKHO_DETAIL_ENDPOINT', $this->cardekhoDetailEndpoint);
        $this->cardekhoBearerToken       = (string) env('CARDEKHO_AUTHORIZED_API_TOKEN', $this->cardekhoBearerToken);
        $this->cardekhoRequestTimeout    = (int) env('CARDEKHO_REQUEST_TIMEOUT', $this->cardekhoRequestTimeout);
        $this->cardekhoRetryAttempts     = (int) env('CARDEKHO_RETRY_ATTEMPTS', $this->cardekhoRetryAttempts);
        $this->cardekhoCacheEnabled      = filter_var(env('CARDEKHO_CACHE_ENABLED', $this->cardekhoCacheEnabled), FILTER_VALIDATE_BOOLEAN);
        $this->cardekhoCacheTtl          = (int) env('CARDEKHO_CACHE_TTL', $this->cardekhoCacheTtl);
        $this->fixtureCardekhoSearchPath = (string) env('CARDEKHO_FIXTURE_SEARCH_PATH', $this->fixtureCardekhoSearchPath);
        $this->fixtureCardekhoDetailPath = (string) env('CARDEKHO_FIXTURE_DETAIL_PATH', $this->fixtureCardekhoDetailPath);

        // Naukri
        $this->naukriMode                = (string) env('NAUKRI_CONNECTOR_MODE', $defaultMode);
        $this->naukriAuthorizedBaseUrl   = rtrim((string) env('NAUKRI_AUTHORIZED_API_BASE_URL', $this->naukriAuthorizedBaseUrl), '/');
        $this->naukriSearchEndpoint      = (string) env('NAUKRI_SEARCH_ENDPOINT', $this->naukriSearchEndpoint);
        $this->naukriDetailEndpoint      = (string) env('NAUKRI_DETAIL_ENDPOINT', $this->naukriDetailEndpoint);
        $this->naukriBearerToken         = (string) env('NAUKRI_AUTHORIZED_API_TOKEN', $this->naukriBearerToken);
        $this->naukriRequestTimeout      = (int) env('NAUKRI_REQUEST_TIMEOUT', $this->naukriRequestTimeout);
        $this->naukriRetryAttempts       = (int) env('NAUKRI_RETRY_ATTEMPTS', $this->naukriRetryAttempts);
        $this->naukriCacheEnabled        = filter_var(env('NAUKRI_CACHE_ENABLED', $this->naukriCacheEnabled), FILTER_VALIDATE_BOOLEAN);
        $this->naukriCacheTtl            = (int) env('NAUKRI_CACHE_TTL', $this->naukriCacheTtl);
        $this->fixtureNaukriSearchPath   = (string) env('NAUKRI_FIXTURE_SEARCH_PATH', $this->fixtureNaukriSearchPath);
        $this->fixtureNaukriDetailPath   = (string) env('NAUKRI_FIXTURE_DETAIL_PATH', $this->fixtureNaukriDetailPath);

        // Cashify
        $this->cashifyMode               = (string) env('CASHIFY_CONNECTOR_MODE', $defaultMode);
        $this->cashifyAuthorizedBaseUrl  = rtrim((string) env('CASHIFY_AUTHORIZED_API_BASE_URL', $this->cashifyAuthorizedBaseUrl), '/');
        $this->cashifySearchEndpoint     = (string) env('CASHIFY_SEARCH_ENDPOINT', $this->cashifySearchEndpoint);
        $this->cashifyDetailEndpoint     = (string) env('CASHIFY_DETAIL_ENDPOINT', $this->cashifyDetailEndpoint);
        $this->cashifyBearerToken        = (string) env('CASHIFY_AUTHORIZED_API_TOKEN', $this->cashifyBearerToken);
        $this->cashifyRequestTimeout     = (int) env('CASHIFY_REQUEST_TIMEOUT', $this->cashifyRequestTimeout);
        $this->cashifyRetryAttempts      = (int) env('CASHIFY_RETRY_ATTEMPTS', $this->cashifyRetryAttempts);
        $this->cashifyCacheEnabled       = filter_var(env('CASHIFY_CACHE_ENABLED', $this->cashifyCacheEnabled), FILTER_VALIDATE_BOOLEAN);
        $this->cashifyCacheTtl           = (int) env('CASHIFY_CACHE_TTL', $this->cashifyCacheTtl);
        $this->fixtureCashifySearchPath  = (string) env('CASHIFY_FIXTURE_SEARCH_PATH', $this->fixtureCashifySearchPath);
        $this->fixtureCashifyDetailPath  = (string) env('CASHIFY_FIXTURE_DETAIL_PATH', $this->fixtureCashifyDetailPath);

        // Global
        $this->persistListings           = filter_var(env('LISTINGS_PERSIST_ENABLED', $this->persistListings), FILTER_VALIDATE_BOOLEAN);

        $this->exposeRawData             = filter_var(env('EXPOSE_SOURCE_RAW_DATA', $this->exposeRawData), FILTER_VALIDATE_BOOLEAN);
        $this->collectionTimezone        = (string) env('SOURCE_COLLECTION_TIMEZONE', $this->collectionTimezone);
    }
}

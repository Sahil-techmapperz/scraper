<?php

define('HOMEPATH', __DIR__ . '/../');
define('CONFIGPATH', __DIR__ . '/../app/Config/');
define('PUBLICPATH', __DIR__ . '/../public/');

require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

$cache = Config\Services::cache();
$handlerClass = get_class($cache);

// Test 1: Direct Cache Operations
$cache->save('ci_direct_test', ['time' => microtime(true), 'msg' => 'Direct CI Cache Works'], 60);
$directRead = $cache->get('ci_direct_test');

// Test 2: RequestCache Service
$requestCache = new App\Services\RequestCache($cache);
$params = ['keyword' => 'swift', 'city' => 'Delhi', 'limit' => 20];
$key = $requestCache->key('olx', 'listings', $params);
$payload = [
    'success' => true,
    'data' => [
        ['id' => '1001', 'title' => 'Maruti Suzuki Swift 2020', 'price' => 550000]
    ]
];

$requestCache->save('olx', 'listings', $params, $payload, 120);
$retrieved = $requestCache->get('olx', 'listings', $params);

// Test 3: RateLimitService
$rateLimiter = new App\Services\RateLimitService($cache);
$rate1 = $rateLimiter->hit(12345, 10);
$rate2 = $rateLimiter->hit(12345, 10);

$result = [
    'status' => 'OK',
    'active_cache_handler' => $handlerClass,
    'direct_cache_works' => ($directRead !== null),
    'request_cache_key' => $key,
    'request_cache_payload_match' => ($retrieved === $payload),
    'rate_limiter_hit1' => $rate1,
    'rate_limiter_hit2' => $rate2,
];

echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;

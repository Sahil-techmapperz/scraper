<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

$routes->group('api', ['namespace' => 'App\Controllers\Api'], static function (RouteCollection $routes): void {
    $routes->get('docs', 'DocsController::index');
    $routes->get('openapi.json', 'DocsController::openApi');

    $routes->group('v1', ['namespace' => 'App\Controllers\Api\V1'], static function (RouteCollection $routes): void {
        $routes->get('health', 'HealthController::index');

        $routes->group('olx', ['filter' => 'api-auth'], static function (RouteCollection $routes): void {
            $routes->get('listings', 'OlxController::listings');
            $routes->get('listings/(:segment)', 'OlxController::detail/$1');
            $routes->get('listing', 'OlxController::detailByUrl');
        });

        $routes->group('cardekho', ['filter' => 'api-auth'], static function (RouteCollection $routes): void {
            $routes->get('listings', 'CardekhoController::listings');
            $routes->get('listings/(:segment)', 'CardekhoController::detail/$1');
            $routes->get('listing', 'CardekhoController::detailByUrl');
        });

        $routes->group('naukri', ['filter' => 'api-auth'], static function (RouteCollection $routes): void {
            $routes->get('listings', 'NaukriController::listings');
            $routes->get('listings/(:segment)', 'NaukriController::detail/$1');
            $routes->get('listing', 'NaukriController::detailByUrl');
        });

        $routes->group('cashify', ['filter' => 'api-auth'], static function (RouteCollection $routes): void {
            $routes->get('listings', 'CashifyController::listings');
            $routes->get('listings/(:segment)', 'CashifyController::detail/$1');
            $routes->get('listing', 'CashifyController::detailByUrl');
        });

        $routes->group('admin', ['namespace' => 'App\Controllers\Api\V1\Admin', 'filter' => 'api-auth:admin'], static function (RouteCollection $routes): void {
            $routes->get('usage', 'UsageController::index');
        });

    });
});

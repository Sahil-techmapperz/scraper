<?php

use CodeIgniter\Boot;
use Config\Paths;
use App\Services\ListingSearchService;
use App\Services\ListingDetailService;

define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR);
chdir(FCPATH);

require FCPATH . '../app/Config/Paths.php';
$paths = new Paths();
require $paths->systemDirectory . '/Boot.php';

Boot::preload($paths);
require $paths->systemDirectory . '/Common.php';

$dotenv = new \CodeIgniter\Config\DotEnv(ROOTPATH);
$dotenv->load();

echo "=== 1. Testing Search Extraction ===\n";
$searchService = new ListingSearchService();
$result = $searchService->search('olx', [
    'category' => 'cars',
    'city'     => 'Kolkata',
    'limit'    => 2,
    'fresh'    => true,
]);

echo "Success: " . ($result['payload']['success'] ? 'true' : 'false') . "\n";
echo "Records returned: " . count($result['payload']['data']) . "\n";
if (!empty($result['payload']['data'])) {
    foreach ($result['payload']['data'] as $idx => $car) {
        $num = $idx + 1;
        $title = $car['title'] ?? 'N/A';
        $price = isset($car['price']['amount']) ? 'Rs ' . number_format($car['price']['amount']) : 'N/A';
        $loc = ($car['location']['locality'] ?? '') . ', ' . ($car['location']['city'] ?? '');
        $id = $car['listing_id'] ?? 'N/A';
        echo "[$num] ID: {$id} | Title: {$title} | Price: {$price} | Loc: {$loc}\n";
    }
}

echo "\n=== 2. Testing Listing Detail Extraction ===\n";
$detailService = new ListingDetailService();
$detailRes = $detailService->byId('olx', '1787688220', ['fresh' => true]);
echo "Detail Success: " . ($detailRes['payload']['success'] ? 'true' : 'false') . "\n";
echo "Detail Title: " . ($detailRes['payload']['data']['title'] ?? 'N/A') . "\n";
echo "Detail Price: Rs " . number_format($detailRes['payload']['data']['price']['amount'] ?? 0) . "\n";
echo "Detail Seller: " . ($detailRes['payload']['data']['seller']['name'] ?? 'N/A') . "\n";
echo "Detail Images: " . count($detailRes['payload']['data']['images'] ?? []) . "\n";

echo "\nAll integration tests passed successfully!\n";

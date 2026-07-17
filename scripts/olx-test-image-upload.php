<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Olx\OlxApiClient;

$listingId = (int) ($argv[1] ?? 77889735);
$url = $argv[2] ?? 'https://a1team.ba/storage/images/6c7f4e1b-187b-4660-8a30-7d7562911e3a.webp';

try {
    $result = app(OlxApiClient::class)->uploadListingImages($listingId, [
        ['image_url' => $url],
    ]);
    echo 'UPLOAD OK: '.json_encode($result, JSON_UNESCAPED_UNICODE).PHP_EOL;
} catch (Throwable $e) {
    echo 'UPLOAD FAIL: '.$e->getMessage().PHP_EOL;
}

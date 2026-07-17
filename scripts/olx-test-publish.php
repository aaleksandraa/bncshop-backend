<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Olx\OlxApiClient;

$listingId = (int) ($argv[1] ?? 77889762);

try {
    $result = app(OlxApiClient::class)->publishListing($listingId);
    echo 'PUBLISH OK: '.json_encode($result, JSON_UNESCAPED_UNICODE).PHP_EOL;
} catch (Throwable $e) {
    echo 'PUBLISH FAIL: '.$e->getMessage().PHP_EOL;
}

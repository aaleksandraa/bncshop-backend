<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;
use App\Services\Olx\OlxExportScope;
use App\Services\Olx\OlxListingExporter;

$productId = (int) ($argv[1] ?? 3);
$product = Product::query()->with(['category.parent', 'images', 'attributeValues.attributeDefinition', 'manufacturer'])->findOrFail($productId);

try {
    $result = app(OlxListingExporter::class)->export($product, 'create');
    echo 'EXPORT OK: '.json_encode($result, JSON_UNESCAPED_UNICODE).PHP_EOL;
    echo 'olx_listing_id: '.$product->fresh()->olx_listing_id.PHP_EOL;
} catch (Throwable $e) {
    echo 'EXPORT FAIL: '.$e->getMessage().PHP_EOL;
    echo 'olx_last_error: '.($product->fresh()->olx_last_error ?? 'null').PHP_EOL;
}

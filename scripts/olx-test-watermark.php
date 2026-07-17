<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;
use App\Services\Olx\OlxImageSyncService;
use App\Services\Olx\OlxListingExporter;
use App\Services\Olx\OlxProductImageDownloader;

$productId = isset($argv[1]) ? (int) $argv[1] : null;
$upload = in_array('--upload', $argv, true);
$outputPath = storage_path('app/olx-watermark-test.jpg');

if ($productId === null) {
    echo "Usage: php scripts/olx-test-watermark.php <product_id> [--upload]\n";
    exit(1);
}

$product = Product::query()
    ->with(['images'])
    ->whereNotNull('olx_listing_id')
    ->find($productId);

if ($product === null) {
    echo "Product #{$productId} not found or has no OLX listing.\n";
    exit(1);
}

$imageUrl = app(OlxImageSyncService::class)->imageUrls($product)[0] ?? null;

if ($imageUrl === null) {
    echo "Product #{$productId} has no active images.\n";
    exit(1);
}

echo "Source image: {$imageUrl}\n";

$file = app(OlxProductImageDownloader::class)->downloadForUpload($imageUrl, 0);

if ($file === null) {
    echo "Failed to prepare watermarked image.\n";
    exit(1);
}

file_put_contents($outputPath, $file['contents']);

echo "Saved preview: {$outputPath}\n";
echo 'Bytes: '.strlen($file['contents'])."\n";

if ($upload) {
    $product->update(['olx_image_map' => null]);
    app(OlxListingExporter::class)->export($product->fresh([
        'category.parent',
        'images',
        'attributeValues.attributeDefinition',
        'manufacturer',
    ]), 'update');

    echo "Uploaded to OLX listing {$product->olx_listing_id}\n";
}

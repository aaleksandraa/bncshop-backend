<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\OlxCategoryAttribute;
use App\Models\Product;
use App\Services\Olx\OlxExportScope;
use App\Services\Olx\OlxListingMapper;

$productId = (int) ($argv[1] ?? 3);
$product = Product::query()->with(['category', 'attributeValues.attributeDefinition'])->findOrFail($productId);
$scope = app(OlxExportScope::class);
$mapper = app(OlxListingMapper::class);
$mapping = $scope->resolveCategoryMapping($product);

if ($mapping === null) {
    echo "No mapping for product category\n";
    exit(1);
}

$payload = $mapper->map($product, $mapping);
$olxCatId = (int) $mapping->olx_category_id;
$required = OlxCategoryAttribute::query()
    ->where('olx_category_id', $olxCatId)
    ->where('required', true)
    ->pluck('display_name', 'olx_attribute_id');

$sentIds = collect($payload['attributes'] ?? [])->pluck('id')->all();
$missing = [];

foreach ($required as $attrId => $name) {
    if (! in_array((int) $attrId, $sentIds, true)) {
        $missing[(int) $attrId] = $name;
    }
}

echo json_encode([
    'product_id' => $product->id,
    'product_name' => $product->name,
    'bnc_category' => $product->category?->full_slug,
    'olx_category_id' => $olxCatId,
    'olx_category_path' => $mapping->olx_category_path,
    'sent_attributes' => $payload['attributes'] ?? [],
    'missing_required' => $missing,
    'title' => $payload['title'] ?? null,
    'price' => $payload['price'] ?? null,
    'state' => $payload['state'] ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;

if (! in_array('--create', $argv, true)) {
    exit($missing === [] ? 0 : 1);
}

try {
    $response = app(\App\Services\Olx\OlxApiClient::class)->createListing($payload);
    echo "CREATE OK: ".json_encode($response, JSON_UNESCAPED_UNICODE).PHP_EOL;
} catch (Throwable $e) {
    echo "CREATE ERROR: ".$e->getMessage().PHP_EOL;
}

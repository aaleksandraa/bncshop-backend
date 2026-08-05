<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;
use App\Services\Olx\OlxChangeDetector;
use App\Services\Olx\OlxExportScope;

$scope = app(OlxExportScope::class);
$detector = app(OlxChangeDetector::class);

$scopedIds = $scope->scopedCategoryIds();
$managedWithListing = Product::where('olx_managed', true)->whereNotNull('olx_listing_id')->count();
$totalManaged = Product::where('olx_managed', true)->count();

$eligibleInStockNoListing = Product::query()
    ->whereIn('category_id', $scopedIds ?: [-1])
    ->where('is_public', true)
    ->where('status', 'active')
    ->where('available_stock', '>', 0)
    ->whereNull('olx_listing_id')
    ->count();

$detection = $detector->detect(false);
$detectionFull = $detector->detect(true);

echo "scoped_categories=".count($scopedIds).PHP_EOL;
echo "managed_with_listing={$managedWithListing} total_managed={$totalManaged}".PHP_EOL;
echo "eligible_in_stock_no_listing={$eligibleInStockNoListing}".PHP_EOL;
echo "incremental: create=".count($detection['create'])." update=".count($detection['update'])." scanned={$detection['scanned']}".PHP_EOL;
echo "full: create=".count($detectionFull['create'])." update=".count($detectionFull['update']).PHP_EOL;

$sample = Product::query()
    ->with('category')
    ->whereIn('category_id', $scopedIds ?: [-1])
    ->where('is_public', true)
    ->where('status', 'active')
    ->where('available_stock', '>', 0)
    ->whereNull('olx_listing_id')
    ->limit(15)
    ->get(['id', 'name', 'category_id']);

foreach ($sample as $p) {
    echo "  missing #{$p->id} [{$p->category?->name}] {$p->name}".PHP_EOL;
}

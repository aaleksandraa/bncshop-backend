<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;
use App\Services\Olx\OlxExportScope;

$scope = app(OlxExportScope::class);
$scopedIds = $scope->scopedCategoryIds();

$managedWithListing = Product::where('olx_managed', true)->whereNotNull('olx_listing_id')->count();
$totalManaged = Product::where('olx_managed', true)->count();

$missing = Product::query()
    ->with('category')
    ->whereIn('category_id', $scopedIds ?: [-1])
    ->where('is_public', true)
    ->where('status', 'active')
    ->where('available_stock', '>', 0)
    ->whereNull('olx_listing_id')
    ->orderBy('id')
    ->get(['id', 'name', 'category_id']);

echo 'scoped_categories='.count($scopedIds).PHP_EOL;
echo "managed_with_listing={$managedWithListing} total_managed={$totalManaged}".PHP_EOL;
echo 'eligible_in_stock_no_listing='.$missing->count().PHP_EOL;

foreach ($missing->take(20) as $p) {
    echo "  missing #{$p->id} [{$p->category?->name}] ".mb_substr($p->name, 0, 60).PHP_EOL;
}

$byCat = $missing->groupBy(fn ($p) => $p->category?->name ?? '?');
foreach ($byCat->sortKeys() as $cat => $items) {
    echo "  {$cat}: {$items->count()}".PHP_EOL;
}

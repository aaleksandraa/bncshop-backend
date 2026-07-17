<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;
use App\Services\Olx\OlxExportScope;

$scope = app(OlxExportScope::class);
$ids = $scope->scopedCategoryIds();

$total = Product::query()
    ->whereIn('category_id', $ids ?: [-1])
    ->where('is_public', true)
    ->where('status', 'active')
    ->count();

echo "total_active_in_scoped={$total}".PHP_EOL;

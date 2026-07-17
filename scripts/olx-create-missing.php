<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;
use App\Services\Olx\OlxExportScope;
use App\Services\Olx\OlxListingExporter;
use App\Services\Olx\OlxSyncSettings;

$limit = isset($argv[1]) ? max(1, (int) $argv[1]) : null;

if (! app(OlxSyncSettings::class)->isEnabled()) {
    fwrite(STDERR, "OLX export disabled.\n");
    exit(1);
}

$scope = app(OlxExportScope::class);
$exporter = app(OlxListingExporter::class);
$scopedIds = $scope->scopedCategoryIds();

$query = Product::query()
    ->with(['category.parent', 'images', 'attributeValues.attributeDefinition', 'manufacturer'])
    ->whereIn('category_id', $scopedIds ?: [-1])
    ->where('is_public', true)
    ->where('status', 'active')
    ->where('available_stock', '>', 0)
    ->whereNull('olx_listing_id')
    ->orderBy('id');

if ($limit !== null) {
    $query->limit($limit);
}

$stats = ['created' => 0, 'skipped' => 0, 'errors' => 0];
$dailyLimitHit = false;

foreach ($query->cursor() as $product) {
    if ($dailyLimitHit) {
        break;
    }

    if (! $scope->isEligible($product)) {
        $stats['skipped']++;
        continue;
    }

    if ($scope->resolveCategoryMapping($product) === null) {
        $stats['skipped']++;
        continue;
    }

    try {
        $result = $exporter->export($product, 'create');
        $stats['created']++;
        echo sprintf(
            "CREATE OK #%d → OLX %s\n",
            $product->id,
            $result['listing_id'] ?? '?',
        );
    } catch (Throwable $e) {
        $stats['errors']++;
        $message = $e->getMessage();
        echo sprintf("FAIL #%d: %s\n", $product->id, $message);

        if (str_contains($message, 'limit objave oglasa')) {
            $dailyLimitHit = true;
            echo "STOP: dnevni limit OLX objava dostignut.\n";
        }
    }
}

echo sprintf(
    "Done: created=%d skipped=%d errors=%d\n",
    $stats['created'],
    $stats['skipped'],
    $stats['errors'],
);

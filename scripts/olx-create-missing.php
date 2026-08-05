<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;
use App\Services\Olx\OlxDailyCreateLimiter;
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
$createLimiter = app(OlxDailyCreateLimiter::class);
$scopedIds = $scope->scopedCategoryIds();

$quota = $limit !== null
    ? min($limit, $createLimiter->allowedThisRun())
    : $createLimiter->allowedThisRun();

if ($quota <= 0) {
    $snapshot = $createLimiter->snapshot();
    fwrite(STDERR, sprintf(
        "Dnevni limit dostignut (%d/%d).\n",
        $snapshot['creates_today'],
        $snapshot['daily_limit'],
    ));
    exit(1);
}

echo sprintf(
    "Quota: %d (danas %d/%d, max po run-u %d)\n",
    $quota,
    $createLimiter->createsToday(),
    $createLimiter->dailyLimit(),
    $createLimiter->maxPerRun(),
);

$query = Product::query()
    ->with(['category.parent', 'images', 'attributeValues.attributeDefinition', 'manufacturer'])
    ->whereIn('category_id', $scopedIds ?: [-1])
    ->where('is_public', true)
    ->where('status', 'active')
    ->where('available_stock', '>', 0)
    ->whereNull('olx_listing_id')
    ->orderBy('id');

if ($limit !== null) {
    $query->limit(min($limit, $quota));
}

$stats = ['created' => 0, 'skipped' => 0, 'errors' => 0];
$remaining = $quota;

foreach ($query->cursor() as $product) {
    if ($remaining <= 0) {
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
        $createLimiter->recordCreate();
        $stats['created']++;
        $remaining--;
        echo sprintf(
            "CREATE OK #%d → OLX %s\n",
            $product->id,
            $result['listing_id'] ?? '?',
        );
    } catch (Throwable $e) {
        $stats['errors']++;
        $message = $e->getMessage();
        echo sprintf("FAIL #%d: %s\n", $product->id, $message);

        if (OlxDailyCreateLimiter::isDailyLimitError($message)) {
            echo "STOP: dnevni limit OLX objava dostignut.\n";
            break;
        }
    }
}

echo sprintf(
    "Done: created=%d skipped=%d errors=%d\n",
    $stats['created'],
    $stats['skipped'],
    $stats['errors'],
);

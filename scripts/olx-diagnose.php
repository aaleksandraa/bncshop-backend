<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ApiImportJob;
use App\Models\ApiSource;
use App\Models\OlxCategoryMapping;
use App\Models\OlxListingRegistry;
use App\Models\Product;
use App\Services\Olx\OlxChangeDetector;
use App\Services\Olx\OlxExportScope;
use App\Services\Olx\OlxSyncSettings;

$settings = app(OlxSyncSettings::class);
$scope = app(OlxExportScope::class);
$detector = app(OlxChangeDetector::class);

$source = ApiSource::query()->where('target_system_code', 'olx')->first();
$latestJob = $source
    ? ApiImportJob::query()->where('api_source_id', $source->id)->latest()->first()
    : null;

$scopedIds = $scope->scopedCategoryIds();
$candidateCount = Product::query()
    ->where('is_public', true)
    ->where('status', 'active')
    ->whereIn('category_id', $scopedIds === [] ? [-1] : $scopedIds)
    ->whereNull('olx_listing_id')
    ->count();

$detection = $detector->detect(false);

echo json_encode([
    'export_enabled' => $settings->isEnabled(),
    'has_credentials' => $settings->hasCredentials(),
    'connection_status' => $source?->connection_status,
    'enabled_category_mappings' => OlxCategoryMapping::query()->where('is_enabled', true)->count(),
    'scoped_category_ids_count' => count($scopedIds),
    'legacy_registry_count' => OlxListingRegistry::query()->count(),
    'products_with_olx_listing' => Product::query()->whereNotNull('olx_listing_id')->count(),
    'candidates_without_listing_in_mapped_cats' => $candidateCount,
    'detection' => [
        'scanned' => $detection['scanned'],
        'unchanged' => $detection['unchanged'],
        'create' => $detection['create']->count(),
        'update' => $detection['update']->count(),
        'hide' => $detection['hide']->count(),
        'unhide' => $detection['unhide']->count(),
        'create_sample_ids' => $detection['create']->take(5)->pluck('id')->values()->all(),
    ],
    'latest_job' => $latestJob ? [
        'id' => $latestJob->id,
        'type' => $latestJob->type,
        'status' => $latestJob->status,
        'error_message' => $latestJob->error_message,
        'stats' => $latestJob->stats,
    ] : null,
    'queue_connection' => config('queue.default'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;

if (($argv[1] ?? '') === '--test-product') {
    $productId = (int) ($argv[2] ?? 3);
    try {
        $stats = app(\App\Services\Olx\OlxSyncOrchestrator::class)->run(false, $productId);
        echo "TEST OK: ".json_encode($stats, JSON_UNESCAPED_UNICODE).PHP_EOL;
    } catch (Throwable $e) {
        echo "TEST FAIL product {$productId}: ".$e->getMessage().PHP_EOL;
        $product = Product::query()->find($productId);
        if ($product) {
            echo 'olx_last_error: '.($product->fresh()->olx_last_error ?? 'null').PHP_EOL;
        }
    }
}

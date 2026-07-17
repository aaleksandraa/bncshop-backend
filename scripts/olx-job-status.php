<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ApiImportJob;
use App\Models\Product;

$job = ApiImportJob::query()->where('type', 'like', 'olx_%')->latest()->first();
$managed = Product::where('olx_managed', true)->whereNotNull('olx_listing_id')->count();

echo json_encode([
    'managed_with_listing' => $managed,
    'latest_job' => $job ? [
        'id' => $job->id,
        'type' => $job->type,
        'status' => $job->status,
        'started_at' => (string) $job->started_at,
        'stats' => $job->stats,
    ] : null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;

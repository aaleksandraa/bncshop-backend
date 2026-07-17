<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Olx\OlxChangeDetector;

$start = microtime(true);
$detection = app(OlxChangeDetector::class)->detect(false);
$elapsed = round(microtime(true) - $start, 1);

echo json_encode([
    'elapsed_sec' => $elapsed,
    'scanned' => $detection['scanned'],
    'create' => $detection['create']->count(),
    'update' => $detection['update']->count(),
    'hide' => $detection['hide']->count(),
    'unchanged' => $detection['unchanged'],
], JSON_PRETTY_PRINT).PHP_EOL;

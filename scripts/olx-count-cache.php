<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo 'olx_category_attributes: '.App\Models\OlxCategoryAttribute::query()->count().PHP_EOL;
echo 'olx_categories: '.App\Models\OlxCategory::query()->count().PHP_EOL;
echo 'cat 162 attrs: '.App\Models\OlxCategoryAttribute::query()->where('olx_category_id', 162)->count().PHP_EOL;

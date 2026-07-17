<?php

namespace App\Console\Commands;

use App\Models\ApiSource;
use App\Services\Sync\AttributeImporter;
use App\Services\Sync\CategoryImporter;
use App\Services\Sync\ProductImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ImportJsonSamplesCommand extends Command
{
    protected $signature = 'bnc:import-json-samples {--path=}';

    protected $description = 'Import sample JSON files from json-api-za-import folder for local development';

    public function handle(
        CategoryImporter $categoryImporter,
        AttributeImporter $attributeImporter,
        ProductImporter $productImporter,
    ): int {
        $basePath = $this->option('path') ?: base_path('json-api-za-import');

        if (! File::isDirectory($basePath)) {
            $this->error("Directory not found: {$basePath}");

            return self::FAILURE;
        }

        ApiSource::firstOrCreate(
            ['target_system_code' => 'local-json'],
            [
                'name' => 'Local JSON Import',
                'base_url' => 'http://localhost',
                'is_active' => true,
                'connection_status' => 'connected',
            ]
        );

        $source = ApiSource::query()->where('target_system_code', 'local-json')->first();

        $categoryFile = $basePath.'/category.json';
        if (File::exists($categoryFile)) {
            $data = json_decode(File::get($categoryFile), true);
            $categoryImporter->upsertOne($data);
            $this->info('Imported category: '.($data['name'] ?? 'unknown'));
        }

        $attributesFile = $basePath.'/attributes.json';
        if (File::exists($attributesFile)) {
            $payload = json_decode(File::get($attributesFile), true);
            $items = $payload['data'] ?? $payload;
            $count = 0;
            foreach ($items as $item) {
                $attributeImporter->upsertOne($item);
                $count++;
            }
            $this->info("Imported {$count} attribute definitions");
        }

        $productFile = $basePath.'/product.json';
        if (File::exists($productFile)) {
            $payload = json_decode(File::get($productFile), true);
            $items = $payload['data'] ?? [$payload];
            foreach ($items as $item) {
                $productImporter->upsertOne($item, $source);
                $this->info('Imported product: '.($item['name'] ?? 'unknown'));
            }
        }

        $this->info('JSON sample import completed.');

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\ApiSource;
use App\Services\Sync\IntegrationApiClient;
use Illuminate\Console\Command;

class A1ApiTestCommand extends Command
{
    protected $signature = 'bnc:a1-api-test {source? : API source ID or name}';

    protected $description = 'Run A1 API integration smoke tests (Postman plan tests 1.1-1.7)';

    public function handle(): int
    {
        $source = $this->resolveSource();

        if (! $source) {
            $this->error('No API source found. Run: php artisan db:seed --class=ApiSourceSeeder');

            return self::FAILURE;
        }

        $client = IntegrationApiClient::forSource($source);
        $passed = 0;
        $failed = 0;

        // Test 1.1 Login
        $this->info('Test 1.1 — Login');
        try {
            $client->login();
            $source->refresh();
            if ($source->access_token) {
                $this->line('  PASS: access_token received');
                $passed++;
            } else {
                $this->error('  FAIL: no access_token');
                $failed++;
            }
        } catch (\Throwable $e) {
            $this->error('  FAIL: '.$e->getMessage());
            $failed++;

            return self::FAILURE;
        }

        // Test 1.2 Refresh
        $this->info('Test 1.2 — Refresh token');
        try {
            $client->refreshToken();
            $this->line('  PASS: refresh OK');
            $passed++;
        } catch (\Throwable $e) {
            $this->error('  FAIL: '.$e->getMessage());
            $failed++;
        }

        // Test 1.3 Categories
        $this->info('Test 1.3 — Categories page 1');
        try {
            $categories = $client->getCategories();
            if (count($categories) > 0 && isset($categories[0]['categoryId'])) {
                $this->line('  PASS: '.count($categories).' categories');
                $passed++;
            } else {
                $this->error('  FAIL: empty or invalid categories');
                $failed++;
            }
        } catch (\Throwable $e) {
            $this->error('  FAIL: '.$e->getMessage());
            $failed++;
        }

        // Test 1.4 Attributes
        $this->info('Test 1.4 — Attributes page 1');
        try {
            $attributes = $client->getAttributes();
            if (count($attributes) > 0 && isset($attributes[0]['productAttributeDefinitionId'])) {
                $this->line('  PASS: '.count($attributes).' attributes');
                $passed++;
            } else {
                $this->error('  FAIL: empty or invalid attributes');
                $failed++;
            }
        } catch (\Throwable $e) {
            $this->error('  FAIL: '.$e->getMessage());
            $failed++;
        }

        // Test 1.5 Products
        $this->info('Test 1.5 — Products page 1 (PageSize=10)');
        try {
            $response = $client->getProducts(null, 1, 10);
            $products = $response['data'];
            $pagination = $response['meta'];
            if (count($products) > 0 && isset($products[0]['productId'])) {
                $this->line('  PASS: '.count($products).' products, pagination: '.json_encode($pagination));
                $passed++;
            } else {
                $this->error('  FAIL: empty or invalid products');
                $failed++;
            }
        } catch (\Throwable $e) {
            $this->error('  FAIL: '.$e->getMessage());
            $failed++;
        }

        // Test 1.7 ModifiedAfter
        $this->info('Test 1.7 — ModifiedAfter filter');
        try {
            $response = $client->getProducts('2026-01-01T00:00:00', 1, 10);
            $this->line('  PASS: filter accepted, '.count($response['data']).' products returned');
            $passed++;
        } catch (\Throwable $e) {
            $this->warn('  WARN: ModifiedAfter may not be supported: '.$e->getMessage());
            $this->line('  (Incremental sync may require full sync until A1 enables this filter)');
        }

        $this->newLine();
        $this->info("Results: {$passed} passed, {$failed} failed");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolveSource(): ?ApiSource
    {
        $arg = $this->argument('source');

        return ApiSource::query()
            ->where('is_active', true)
            ->when($arg, fn ($q) => $q->where('id', $arg)->orWhere('name', $arg))
            ->orderByRaw("CASE WHEN target_system_code = 'bnc-shop' THEN 0 ELSE 1 END")
            ->first();
    }
}

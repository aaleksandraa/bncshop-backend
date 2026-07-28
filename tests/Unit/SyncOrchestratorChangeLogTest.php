<?php

namespace Tests\Unit;

use App\Models\ApiImportJobChange;
use App\Models\ApiSource;
use App\Models\Product;
use App\Services\Sync\SyncOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class SyncOrchestratorChangeLogTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_incremental_sync_logs_product_changes_and_stats(): void
    {
        config(['bnc.a1_api_verify_ssl' => false]);

        $lastSync = now()->subHour();

        $source = ApiSource::query()->create([
            'name' => 'A1',
            'target_system_code' => 'bnc-shop',
            'base_url' => 'https://example.test',
            'username' => 'user',
            'password' => 'pass',
            'access_token' => 'token',
            'is_active' => true,
            'last_successful_sync_at' => $lastSync,
        ]);

        Product::query()->create([
            'external_product_id' => 'existing-product-id',
            'name' => 'Existing',
            'slug' => 'existing',
            'is_public' => true,
            'status' => 'active',
            'api_stock' => 1,
            'available_stock' => 1,
            'stock_status' => 'in_stock',
        ]);

        Http::fake([
            'https://example.test/api/integrations/bnc-shop/products*' => Http::response([
                'data' => [
                    [
                        'productId' => 'new-product-id',
                        'name' => 'New product',
                        'slug' => 'new-product',
                        'isPublic' => true,
                        'stock' => 1,
                        'price' => 10,
                    ],
                    [
                        'productId' => 'existing-product-id',
                        'name' => 'Existing updated',
                        'slug' => 'existing-updated',
                        'isPublic' => true,
                        'stock' => 2,
                        'price' => 20,
                    ],
                ],
                'pagination' => ['nextPage' => null],
            ], 200),
        ]);

        $stats = app(SyncOrchestrator::class)->run($source, fullSync: false, skipMetadata: true);

        $this->assertSame(1, $stats['products']['created']);
        $this->assertSame(1, $stats['products']['updated']);
        $this->assertSame(0, $stats['products']['deactivated']);
        $this->assertSame(2, $stats['products']['imported']);

        $changes = ApiImportJobChange::query()->orderBy('id')->get();
        $this->assertCount(2, $changes);
        $this->assertSame('inserted', $changes[0]->action);
        $this->assertSame('updated', $changes[1]->action);
        $this->assertContains('name', $changes[1]->changed_fields ?? []);
    }
}

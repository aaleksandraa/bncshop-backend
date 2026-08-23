<?php

namespace Tests\Unit;

use App\Models\ApiImportJob;
use App\Models\ApiSource;
use App\Models\Product;
use App\Services\Sync\AttributeImporter;
use App\Services\Sync\CategoryImporter;
use App\Services\Sync\IntegrationApiClient;
use App\Services\Sync\ProductImporter;
use App\Services\Sync\ProductUpsertResult;
use App\Services\Sync\SyncOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class SyncOrchestratorIncrementalTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_incremental_sync_skips_metadata_and_passes_date_filter(): void
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

        Http::fake([
            'https://example.test/api/integrations/bnc-shop/products*' => Http::response([
                'data' => [],
                'pagination' => ['nextPage' => null],
            ], 200),
        ]);

        $this->mock(CategoryImporter::class, function ($mock): void {
            $mock->shouldNotReceive('upsertMany');
        });

        $this->mock(AttributeImporter::class, function ($mock): void {
            $mock->shouldNotReceive('upsertMany');
        });

        $this->mock(ProductImporter::class, function ($mock): void {
            $mock->shouldNotReceive('upsertOne');
        });

        $stats = app(SyncOrchestrator::class)->run($source, fullSync: false, skipMetadata: true);

        Http::assertSent(function ($request) use ($lastSync): bool {
            return str_contains($request->url(), '/products')
                && ($request->data()['ModifiedAfter'] ?? null) === IntegrationApiClient::formatModifiedAfter($lastSync);
        });

        $source->refresh();
        $job = ApiImportJob::query()->first();
        $this->assertSame('incremental', $job->type);
        $this->assertSame('completed', $job->status);
        $this->assertTrue($source->last_successful_sync_at->greaterThan($lastSync));
        $this->assertSame(0, $stats['products']['imported']);
        $this->assertSame(0, $stats['products']['created']);
        $this->assertSame(0, $stats['products']['updated']);
        $this->assertSame(0, $stats['products']['deactivated']);
    }

    public function test_failed_sync_does_not_update_timestamp(): void
    {
        config(['bnc.a1_api_verify_ssl' => false]);

        $lastSync = now()->subDay();

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

        Http::fake([
            'https://example.test/api/integrations/bnc-shop/products*' => Http::response('Server error', 500),
        ]);

        $this->mock(CategoryImporter::class, function ($mock): void {
            $mock->shouldNotReceive('upsertMany');
        });
        $this->mock(AttributeImporter::class, function ($mock): void {
            $mock->shouldNotReceive('upsertMany');
        });
        $this->mock(ProductImporter::class, function ($mock): void {
            $mock->shouldNotReceive('upsertOne');
        });

        $source->refresh();
        $expectedTimestamp = $source->last_successful_sync_at->copy();

        try {
            app(SyncOrchestrator::class)->run($source, fullSync: false, skipMetadata: true);
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException) {
            // expected
        }

        $source->refresh();
        $this->assertTrue($source->last_successful_sync_at->equalTo($expectedTimestamp));
        $this->assertSame('failed', ApiImportJob::query()->first()->status);
    }

    public function test_chunked_sync_does_not_advance_watermark_until_all_pages_are_done(): void
    {
        config(['bnc.a1_api_verify_ssl' => false]);

        $lastSync = now()->subDay();

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

        Http::fake(function ($request) {
            $page = (int) ($request['Page'] ?? 1);

            if ($page === 1) {
                return Http::response([
                    'data' => [[
                        'productId' => 'p-1',
                        'name' => 'First',
                        'slug' => 'first',
                        'isPublic' => true,
                        'stock' => 1,
                        'price' => 10,
                    ]],
                    'pagination' => ['nextPage' => 2],
                ], 200);
            }

            return Http::response([
                'data' => [[
                    'productId' => 'p-2',
                    'name' => 'Second',
                    'slug' => 'second',
                    'isPublic' => true,
                    'stock' => 1,
                    'price' => 20,
                ]],
                'pagination' => ['nextPage' => null],
            ], 200);
        });

        $this->mock(CategoryImporter::class, function ($mock): void {
            $mock->shouldNotReceive('upsertMany');
        });
        $this->mock(AttributeImporter::class, function ($mock): void {
            $mock->shouldNotReceive('upsertMany');
        });
        $this->mock(ProductImporter::class, function ($mock) {
            $mock->shouldReceive('upsertOne')->andReturnUsing(function (array $payload) {
                $product = Product::query()->create([
                    'external_product_id' => $payload['productId'],
                    'name' => $payload['name'],
                    'slug' => $payload['slug'],
                    'is_public' => true,
                    'status' => 'active',
                    'api_stock' => 1,
                    'available_stock' => 1,
                    'stock_status' => 'in_stock',
                ]);

                return new ProductUpsertResult('inserted', $product);
            });
        });

        $source->refresh();
        $expectedTimestamp = $source->last_successful_sync_at->copy();

        $first = app(SyncOrchestrator::class)->run(
            $source,
            fullSync: false,
            maxProductPages: 1,
            skipMetadata: true,
            chunked: true,
        );

        $source->refresh();
        $job = ApiImportJob::query()->first();

        $this->assertSame(2, $first['products']['next_page']);
        $this->assertTrue($first['incomplete']);
        $this->assertSame('running', $job->status);
        $this->assertTrue($source->last_successful_sync_at->equalTo($expectedTimestamp));

        $second = app(SyncOrchestrator::class)->run(
            $source->fresh(),
            fullSync: false,
            maxProductPages: 1,
            startProductPage: 2,
            skipMetadata: true,
            importJobId: $job->id,
            modifiedAfter: $first['modified_after'],
            chunked: true,
        );

        $source->refresh();
        $job->refresh();

        $this->assertNull($second['products']['next_page']);
        $this->assertFalse($second['incomplete']);
        $this->assertSame('completed', $job->status);
        $this->assertTrue($source->last_successful_sync_at->greaterThan($lastSync));
        $this->assertSame(2, $second['products']['imported']);
    }
}

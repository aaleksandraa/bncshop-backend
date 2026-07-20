<?php

namespace Tests\Unit;

use App\Models\ApiImportJob;
use App\Models\ApiSource;
use App\Services\Sync\AttributeImporter;
use App\Services\Sync\CategoryImporter;
use App\Services\Sync\IntegrationApiClient;
use App\Services\Sync\ProductImporter;
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
}

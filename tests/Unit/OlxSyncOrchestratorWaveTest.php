<?php

namespace Tests\Unit;

use App\Jobs\RunOlxSyncJob;
use App\Models\ApiImportJob;
use App\Models\ApiSource;
use App\Models\Product;
use App\Services\Media\ImageOptimizer;
use App\Services\Media\MediaStorage;
use App\Services\Olx\OlxApiClient;
use App\Services\Olx\OlxChangeDetector;
use App\Services\Olx\OlxDailyCreateLimiter;
use App\Services\Olx\OlxListingExporter;
use App\Services\Olx\OlxSyncOrchestrator;
use App\Services\Olx\OlxSyncSettings;
use App\Services\Sync\SyncHealthChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class OlxSyncOrchestratorWaveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(ImageOptimizer::class);
        $this->mock(MediaStorage::class);
    }

    public function test_first_wave_heartbeats_and_dispatches_continuation(): void
    {
        Queue::fake();
        config(['bnc.olx_sync_wave_size' => 2]);

        $source = $this->makeOlxSource();
        $products = collect([
            $this->makeProduct(),
            $this->makeProduct(),
            $this->makeProduct(),
        ]);

        $settings = Mockery::mock(OlxSyncSettings::class);
        $settings->shouldReceive('isEnabled')->andReturn(true);
        $settings->shouldReceive('resolveSource')->andReturn($source);
        $settings->shouldReceive('hasRunningBulkSyncJob')->andReturn(false);
        $settings->shouldReceive('all')->andReturn(['batch_size' => 20, 'daily_create_limit' => 350, 'max_creates_per_run' => 175]);

        $client = Mockery::mock(OlxApiClient::class);
        $client->shouldReceive('authenticate')->andReturn('token');

        $detector = Mockery::mock(OlxChangeDetector::class);
        $detector->shouldReceive('detect')->andReturn([
            'create' => $products->pluck('id')->all(),
            'update' => [],
            'hide' => [],
            'unhide' => [],
            'unchanged' => 0,
            'scanned' => 3,
        ]);

        $exporter = Mockery::mock(OlxListingExporter::class);
        $exporter->shouldReceive('export')->twice()->andReturn(['action' => 'create', 'listing_id' => 99]);

        $this->app->instance(OlxSyncSettings::class, $settings);

        $orchestrator = new OlxSyncOrchestrator(
            $settings,
            $client,
            $detector,
            $exporter,
            app(OlxDailyCreateLimiter::class),
        );

        $stats = $orchestrator->run(false);

        $this->assertTrue($stats['continued']);
        $this->assertSame(2, $stats['actions']['created']);
        $this->assertCount(1, $stats['pending']['create']);

        $job = ApiImportJob::query()->latest('id')->first();
        $this->assertSame('running', $job?->status);
        $this->assertSame(2, data_get($job?->stats, 'actions.created'));

        Queue::assertPushed(RunOlxSyncJob::class, function (RunOlxSyncJob $queued) use ($job): bool {
            return $queued->continueJobId === $job->id
                && $queued->productId === null;
        });
    }

    public function test_successful_complete_clears_stale_timeout_error(): void
    {
        Queue::fake();
        config(['bnc.olx_sync_wave_size' => 40]);

        $source = $this->makeOlxSource();
        $product = $this->makeProduct();

        $settings = Mockery::mock(OlxSyncSettings::class);
        $settings->shouldReceive('isEnabled')->andReturn(true);
        $settings->shouldReceive('resolveSource')->andReturn($source);
        $settings->shouldReceive('hasRunningBulkSyncJob')->andReturn(false);
        $settings->shouldReceive('all')->andReturn(['batch_size' => 20, 'daily_create_limit' => 350, 'max_creates_per_run' => 175]);

        $client = Mockery::mock(OlxApiClient::class);
        $client->shouldReceive('authenticate')->andReturn('token');

        $detector = Mockery::mock(OlxChangeDetector::class);
        $detector->shouldReceive('detect')->andReturn([
            'create' => [],
            'update' => [$product->id],
            'hide' => [],
            'unhide' => [],
            'unchanged' => 0,
            'scanned' => 1,
        ]);

        $exporter = Mockery::mock(OlxListingExporter::class);
        $exporter->shouldReceive('export')->once()->andReturn(['action' => 'update', 'listing_id' => 44]);

        $this->app->instance(OlxSyncSettings::class, $settings);

        $orchestrator = new OlxSyncOrchestrator(
            $settings,
            $client,
            $detector,
            $exporter,
            app(OlxDailyCreateLimiter::class),
        );

        $orchestrator->run(false);

        $job = ApiImportJob::query()->latest('id')->first();
        $this->assertSame('completed', $job?->status);
        $this->assertNull($job?->error_message);
        $this->assertSame(1, data_get($job?->stats, 'actions.updated'));
        Queue::assertNotPushed(RunOlxSyncJob::class);
    }

    public function test_health_checker_resumes_idle_olx_job_with_pending_work(): void
    {
        Queue::fake();

        $source = $this->makeOlxSource();
        $job = ApiImportJob::query()->create([
            'api_source_id' => $source->id,
            'type' => 'olx_incremental',
            'status' => 'running',
            'sync_started_at' => now()->subHours(3),
            'started_at' => now()->subHours(3),
            'stats' => [
                'pending' => ['create' => [11, 12], 'update' => [], 'hide' => [], 'unhide' => []],
                'limits' => ['max_per_run' => 175],
            ],
        ]);
        $job->forceFill([
            'created_at' => now()->subHours(3),
            'updated_at' => now()->subHours(2),
        ])->save();

        $released = app(SyncHealthChecker::class)->releaseStaleRunningJobs(45);

        $this->assertSame(0, $released);
        $this->assertSame('running', $job->fresh()->status);
        Queue::assertPushed(RunOlxSyncJob::class, function (RunOlxSyncJob $queued) use ($job): bool {
            return $queued->continueJobId === $job->id;
        });
    }

    private function makeOlxSource(): ApiSource
    {
        return ApiSource::query()->create([
            'name' => 'OLX / PIK export',
            'target_system_code' => 'olx',
            'base_url' => 'https://api.olx.ba',
            'username' => 'shop',
            'password' => 'secret',
            'is_active' => true,
            'auto_sync_enabled' => true,
        ]);
    }

    private function makeProduct(): Product
    {
        return Product::query()->create([
            'external_product_id' => (string) Str::uuid(),
            'name' => 'OLX product '.Str::random(6),
            'slug' => 'olx-product-'.Str::random(8),
            'is_public' => true,
            'status' => 'active',
            'api_stock' => 2,
            'available_stock' => 2,
            'stock_status' => 'in_stock',
        ]);
    }
}

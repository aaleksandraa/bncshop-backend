<?php

namespace Tests\Unit;

use App\Jobs\RunApiSyncJob;
use App\Models\ApiImportJob;
use App\Models\ApiImportJobItem;
use App\Models\ApiSource;
use App\Services\Sync\IncrementalSyncScheduler;
use App\Services\Sync\SyncHealthChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SyncHealthCheckerTest extends TestCase
{
    use RefreshDatabase;

    public function test_detects_overdue_sync(): void
    {
        $source = ApiSource::query()->create([
            'name' => 'A1',
            'target_system_code' => 'bnc-shop',
            'base_url' => 'https://example.test',
            'username' => 'user',
            'password' => 'pass',
            'is_active' => true,
            'auto_sync_enabled' => true,
            'sync_interval_minutes' => 60,
            'last_successful_sync_at' => now()->subDays(3),
        ]);

        $report = app(SyncHealthChecker::class)->forSource($source);

        $this->assertTrue($report['is_overdue']);
        $this->assertNotEmpty($report['issues']);
    }

    public function test_not_overdue_when_job_running(): void
    {
        $source = ApiSource::query()->create([
            'name' => 'A1',
            'target_system_code' => 'bnc-shop',
            'base_url' => 'https://example.test',
            'username' => 'user',
            'password' => 'pass',
            'is_active' => true,
            'auto_sync_enabled' => true,
            'sync_interval_minutes' => 60,
            'last_successful_sync_at' => now()->subDays(3),
        ]);

        $this->mock(IncrementalSyncScheduler::class, function ($mock) use ($source): void {
            $mock->shouldReceive('hasRunningJob')->with($source)->andReturn(true);
            $mock->shouldReceive('isDue')->with($source)->andReturn(false);
            $mock->shouldReceive('hasRecentFailure')->with($source)->andReturn(false);
        });

        $report = app(SyncHealthChecker::class)->forSource($source);

        $this->assertFalse($report['is_overdue']);
    }

    public function test_release_stale_jobs_ignores_jobs_that_still_have_recent_activity(): void
    {
        $source = ApiSource::query()->create([
            'name' => 'A1',
            'target_system_code' => 'bnc-shop',
            'base_url' => 'https://example.test',
            'username' => 'user',
            'password' => 'pass',
            'is_active' => true,
            'auto_sync_enabled' => true,
            'sync_interval_minutes' => 60,
            'last_successful_sync_at' => now()->subDays(3),
        ]);

        $job = ApiImportJob::query()->create([
            'api_source_id' => $source->id,
            'type' => 'incremental',
            'status' => 'running',
            'sync_started_at' => now()->subHours(4),
            'started_at' => now()->subHours(4),
        ]);
        $job->forceFill([
            'created_at' => now()->subHours(4),
            'updated_at' => now()->subMinutes(5),
        ])->save();

        $released = app(SyncHealthChecker::class)->releaseStaleRunningJobs(180);

        $this->assertSame(0, $released);
        $this->assertSame('running', $job->fresh()->status);
    }

    public function test_release_stale_jobs_resumes_idle_job_with_progress(): void
    {
        Queue::fake();

        $source = ApiSource::query()->create([
            'name' => 'A1',
            'target_system_code' => 'bnc-shop',
            'base_url' => 'https://example.test',
            'username' => 'user',
            'password' => 'pass',
            'is_active' => true,
            'auto_sync_enabled' => true,
            'last_successful_sync_at' => now()->subDays(2),
        ]);

        $job = ApiImportJob::query()->create([
            'api_source_id' => $source->id,
            'type' => 'incremental',
            'status' => 'running',
            'sync_started_at' => now()->subHours(4),
            'started_at' => now()->subHours(4),
            'stats' => ['modified_after' => '2026-08-21T12:00:00Z'],
        ]);
        $job->forceFill([
            'created_at' => now()->subHours(4),
            'updated_at' => now()->subHours(2),
        ])->save();

        $item = ApiImportJobItem::query()->create([
            'api_import_job_id' => $job->id,
            'page' => 12,
            'records_count' => 25,
            'duration_ms' => 8000,
        ]);
        $item->forceFill([
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ])->save();

        $released = app(SyncHealthChecker::class)->releaseStaleRunningJobs(45);

        $this->assertSame(0, $released);
        $this->assertSame('running', $job->fresh()->status);
        Queue::assertPushed(RunApiSyncJob::class, function (RunApiSyncJob $queued) use ($job): bool {
            return $queued->importJobId === $job->id
                && $queued->startProductPage === 13
                && $queued->skipMetadata === true;
        });
    }
}

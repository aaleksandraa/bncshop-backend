<?php

namespace Tests\Unit;

use App\Models\ApiSource;
use App\Services\Sync\IncrementalSyncScheduler;
use App\Services\Sync\SyncHealthChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        });

        $report = app(SyncHealthChecker::class)->forSource($source);

        $this->assertFalse($report['is_overdue']);
    }
}

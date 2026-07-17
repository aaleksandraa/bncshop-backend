<?php

namespace Tests\Unit;

use App\Models\ApiImportJob;
use App\Models\ApiSource;
use App\Services\Sync\IncrementalSyncScheduler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncrementalSyncSchedulerTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_is_due_when_interval_elapsed(): void
    {
        $scheduler = new IncrementalSyncScheduler;

        $source = ApiSource::query()->create([
            'name' => 'A1',
            'target_system_code' => 'bnc-shop',
            'base_url' => 'https://example.test',
            'username' => 'user',
            'password' => 'pass',
            'is_active' => true,
            'auto_sync_enabled' => true,
            'sync_interval_minutes' => 30,
            'last_successful_sync_at' => now()->subMinutes(31),
        ]);

        $this->assertTrue($scheduler->isDue($source));
    }

    public function test_source_is_not_due_before_interval(): void
    {
        $scheduler = new IncrementalSyncScheduler;

        $source = ApiSource::query()->create([
            'name' => 'A1',
            'target_system_code' => 'bnc-shop',
            'base_url' => 'https://example.test',
            'username' => 'user',
            'password' => 'pass',
            'is_active' => true,
            'sync_interval_minutes' => 60,
            'last_successful_sync_at' => now()->subMinutes(10),
        ]);

        $this->assertFalse($scheduler->isDue($source));
    }

    public function test_source_without_successful_sync_is_never_due(): void
    {
        $scheduler = new IncrementalSyncScheduler;

        $source = ApiSource::query()->create([
            'name' => 'A1',
            'target_system_code' => 'bnc-shop',
            'base_url' => 'https://example.test',
            'username' => 'user',
            'password' => 'pass',
            'is_active' => true,
            'sync_interval_minutes' => 60,
        ]);

        $this->assertFalse($scheduler->isDue($source));
    }

    public function test_running_job_blocks_scheduled_sync(): void
    {
        $scheduler = new IncrementalSyncScheduler;

        $source = ApiSource::query()->create([
            'name' => 'A1',
            'target_system_code' => 'bnc-shop',
            'base_url' => 'https://example.test',
            'username' => 'user',
            'password' => 'pass',
            'is_active' => true,
            'sync_interval_minutes' => 15,
            'last_successful_sync_at' => now()->subHour(),
        ]);

        ApiImportJob::query()->create([
            'api_source_id' => $source->id,
            'type' => 'incremental',
            'status' => 'running',
            'sync_started_at' => now(),
            'started_at' => now(),
        ]);

        $this->assertFalse($scheduler->isDue($source));
    }

    public function test_next_sync_at_is_last_success_plus_interval(): void
    {
        $lastSync = now()->subMinutes(10);

        $source = ApiSource::query()->create([
            'name' => 'A1',
            'target_system_code' => 'bnc-shop',
            'base_url' => 'https://example.test',
            'username' => 'user',
            'password' => 'pass',
            'is_active' => true,
            'sync_interval_minutes' => 45,
            'last_successful_sync_at' => $lastSync,
        ]);

        $source->refresh();

        $this->assertTrue(
            $source->nextSyncAt()->equalTo($source->last_successful_sync_at->copy()->addMinutes(45))
        );
    }

    public function test_eline_source_is_never_due(): void
    {
        $scheduler = new IncrementalSyncScheduler;

        $source = ApiSource::query()->create([
            'name' => 'eLine',
            'target_system_code' => 'eline',
            'base_url' => 'https://eline.test',
            'username' => null,
            'password' => null,
            'is_active' => true,
            'auto_sync_enabled' => true,
            'sync_interval_minutes' => 15,
            'last_successful_sync_at' => now()->subHour(),
        ]);

        $this->assertFalse($scheduler->isDue($source));
        $this->assertTrue($scheduler->dueSources()->isEmpty());
    }

    public function test_auto_sync_disabled_source_is_not_due(): void
    {
        $scheduler = new IncrementalSyncScheduler;

        $source = ApiSource::query()->create([
            'name' => 'A1',
            'target_system_code' => 'bnc-shop',
            'base_url' => 'https://example.test',
            'username' => 'user',
            'password' => 'pass',
            'is_active' => true,
            'auto_sync_enabled' => false,
            'sync_interval_minutes' => 15,
            'last_successful_sync_at' => now()->subHour(),
        ]);

        $this->assertFalse($scheduler->isDue($source));
        $this->assertTrue($scheduler->dueSources()->isEmpty());
    }
}

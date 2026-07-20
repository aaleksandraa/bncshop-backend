<?php

namespace Tests\Unit;

use App\Models\ApiSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncIncrementalCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_incremental_command_skips_source_without_watermark(): void
    {
        $source = ApiSource::query()->create([
            'name' => 'A1',
            'target_system_code' => 'bnc-shop',
            'base_url' => 'https://example.test',
            'is_active' => true,
            'last_successful_sync_at' => null,
        ]);

        $this->artisan('bnc:sync-incremental', ['source' => (string) $source->id])
            ->expectsOutputToContain('last_successful_sync_at is empty')
            ->assertSuccessful();
    }
}

<?php

namespace Tests\Unit;

use App\Jobs\RunApiSyncJob;
use App\Models\ApiSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class RunApiSyncJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_rejects_olx_source(): void
    {
        $source = ApiSource::query()->create([
            'name' => 'OLX / PIK export',
            'target_system_code' => 'olx',
            'base_url' => 'https://api.olx.ba',
            'username' => 'shop',
            'password' => 'secret',
            'is_active' => true,
        ]);

        $this->expectException(InvalidArgumentException::class);

        (new RunApiSyncJob($source))->handle(app(\App\Services\Sync\SyncOrchestrator::class));
    }
}

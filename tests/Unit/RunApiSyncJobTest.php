<?php

namespace Tests\Unit;

use App\Jobs\RunApiSyncJob;
use App\Models\ApiSource;
use App\Services\Sync\SyncOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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

        (new RunApiSyncJob($source))->handle($this->mock(SyncOrchestrator::class));
    }

    public function test_job_dispatches_continuation_when_pages_remain(): void
    {
        Queue::fake();

        $source = ApiSource::query()->create([
            'name' => 'A1',
            'target_system_code' => 'bnc-shop',
            'base_url' => 'https://example.test',
            'username' => 'user',
            'password' => 'pass',
            'is_active' => true,
            'last_successful_sync_at' => now()->subDay(),
        ]);

        $this->mock(SyncOrchestrator::class, function ($mock) {
            $mock->shouldReceive('run')->once()->andReturn([
                'products' => ['next_page' => 41, 'imported' => 25],
                'next_page' => 41,
                'incomplete' => true,
                'import_job_id' => 99,
                'modified_after' => '2026-08-21T12:00:00Z',
            ]);
        });

        (new RunApiSyncJob($source))->handle(app(SyncOrchestrator::class));

        Queue::assertPushed(RunApiSyncJob::class, function (RunApiSyncJob $job): bool {
            return $job->startProductPage === 41
                && $job->importJobId === 99
                && $job->skipMetadata === true
                && $job->modifiedAfter === '2026-08-21T12:00:00Z';
        });
    }
}

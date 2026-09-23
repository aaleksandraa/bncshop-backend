<?php

namespace Tests\Unit;

use App\Models\ApiImportJob;
use App\Models\ApiSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiImportJobSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_olx_stock_job_summary_uses_action_counts_instead_of_dashes(): void
    {
        $job = $this->makeJob('olx_stock', [
            'actions' => [
                'created' => 0,
                'updated' => 0,
                'hidden' => 4,
                'unhidden' => 2,
                'deleted' => 1,
            ],
        ]);

        $this->assertTrue($job->isOlxExportJob());
        $this->assertSame('OLX zaliha', $job->typeLabel());
        $this->assertSame(0, $job->summaryCreated());
        $this->assertSame(2, $job->summaryUpdated());
        $this->assertSame(5, $job->summaryDeactivated());
    }

    public function test_import_job_summary_still_reads_products_stats(): void
    {
        $job = $this->makeJob('incremental', [
            'products' => [
                'created' => 10,
                'updated' => 3,
                'deactivated' => 1,
            ],
        ]);

        $this->assertFalse($job->isOlxExportJob());
        $this->assertSame(10, $job->summaryCreated());
        $this->assertSame(3, $job->summaryUpdated());
        $this->assertSame(1, $job->summaryDeactivated());
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function makeJob(string $type, array $stats): ApiImportJob
    {
        $source = ApiSource::query()->create([
            'name' => 'Test source',
            'target_system_code' => $type === 'incremental' ? 'a1' : 'olx',
            'base_url' => 'https://example.test',
            'username' => 'shop',
            'password' => 'secret',
            'is_active' => true,
        ]);

        return ApiImportJob::query()->create([
            'api_source_id' => $source->id,
            'type' => $type,
            'status' => 'completed',
            'started_at' => now(),
            'completed_at' => now(),
            'stats' => $stats,
        ]);
    }
}

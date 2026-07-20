<?php

namespace Tests\Feature;

use App\Services\Ops\PerfHealthChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PerfCheckCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_perf_check_command_runs_and_returns_valid_exit_code(): void
    {
        $exitCode = Artisan::call('bnc:perf-check');

        $this->assertGreaterThanOrEqual(0, $exitCode);
        $this->assertLessThanOrEqual(2, $exitCode);
        $this->assertStringContainsString('BNC Shop performance check', Artisan::output());
    }

    public function test_perf_check_json_output_contains_expected_sections(): void
    {
        $report = app(PerfHealthChecker::class)->report();

        $this->assertArrayHasKey('summary', $report);
        $this->assertArrayHasKey('system', $report);
        $this->assertArrayHasKey('services', $report);
        $this->assertArrayHasKey('horizon', $report);
        $this->assertArrayHasKey('queues', $report);
        $this->assertArrayHasKey('sync', $report);
        $this->assertArrayHasKey('analytics', $report);
        $this->assertArrayHasKey('issues', $report);
        $this->assertContains($report['summary']['status'], ['ok', 'warn', 'fail']);
    }
}

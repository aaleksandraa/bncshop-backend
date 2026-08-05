<?php

namespace Tests\Unit;

use App\Models\ApiImportJob;
use App\Models\ApiSource;
use App\Services\Olx\OlxDailyCreateLimiter;
use App\Services\Olx\OlxSyncSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OlxDailyCreateLimiterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_allowed_this_run_is_capped_by_daily_limit_and_max_per_run(): void
    {
        $this->mockSettings([
            'daily_create_limit' => 350,
            'max_creates_per_run' => 175,
        ]);

        $limiter = app(OlxDailyCreateLimiter::class);

        $this->assertSame(175, $limiter->allowedThisRun());
    }

    public function test_remaining_today_decreases_after_record_create(): void
    {
        $this->mockSettings([
            'daily_create_limit' => 350,
            'max_creates_per_run' => 175,
        ]);

        $limiter = app(OlxDailyCreateLimiter::class);
        $limiter->recordCreate();

        $this->assertSame(1, $limiter->createsToday());
        $this->assertSame(349, $limiter->remainingToday());
        $this->assertSame(175, $limiter->allowedThisRun());
    }

    public function test_seeds_creates_today_from_completed_jobs(): void
    {
        $source = ApiSource::query()->create([
            'name' => 'OLX / PIK export',
            'target_system_code' => 'olx',
            'base_url' => 'https://api.olx.ba',
            'username' => 'shop',
            'password' => 'secret',
            'is_active' => true,
            'auto_sync_enabled' => true,
        ]);

        ApiImportJob::query()->create([
            'api_source_id' => $source->id,
            'type' => 'olx_incremental',
            'status' => 'completed',
            'sync_started_at' => now(),
            'started_at' => now(),
            'completed_at' => now(),
            'stats' => ['actions' => ['created' => 320]],
        ]);

        $this->mockSettings([
            'daily_create_limit' => 350,
            'max_creates_per_run' => 175,
        ]);

        $limiter = app(OlxDailyCreateLimiter::class);

        $this->assertSame(320, $limiter->createsToday());
        $this->assertSame(30, $limiter->allowedThisRun());
        $this->assertSame(30, $limiter->allowedThisRun(350));
    }

    public function test_detects_daily_limit_error_message(): void
    {
        $this->assertTrue(OlxDailyCreateLimiter::isDailyLimitError(
            'Prekoračili ste limit objave oglasa od 350 po danu!',
        ));
        $this->assertFalse(OlxDailyCreateLimiter::isDailyLimitError('Network error'));
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function mockSettings(array $settings): void
    {
        $mock = $this->createMock(OlxSyncSettings::class);
        $mock->method('all')->willReturn($settings);

        $this->app->instance(OlxSyncSettings::class, $mock);
    }
}

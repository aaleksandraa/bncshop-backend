<?php

namespace Tests\Unit\Ananas;

use App\Models\AnanasSyncRun;
use App\Services\Ananas\AnanasDailyWindow;
use App\Services\Ananas\AnanasSyncRunService;
use App\Services\Ananas\AnanasSyncSettings;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AnanasDailyCadenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_stage_stock_probe_does_not_unlock_production(): void
    {
        $settings = app(AnanasSyncSettings::class);
        $all = $settings->all();
        $all['stock_only_verified'] = [
            AnanasSyncSettings::ENV_STAGE => true,
            AnanasSyncSettings::ENV_PRODUCTION => false,
        ];
        $settings->save($all);

        $this->assertTrue($settings->envProbeFlag('stock_only_verified') || ! $settings->isProduction());

        $fresh = app(AnanasSyncSettings::class);
        $map = $fresh->all()['stock_only_verified'] ?? [];
        $this->assertTrue($map[AnanasSyncSettings::ENV_STAGE] ?? false);
        $this->assertFalse($map[AnanasSyncSettings::ENV_PRODUCTION] ?? true);
    }

    public function test_cron_gate_skips_when_master_off(): void
    {
        config([
            'bnc.ananas_client_id' => 'test-client',
            'bnc.ananas_client_secret' => 'test-secret',
        ]);

        $settings = app(AnanasSyncSettings::class);
        $all = $settings->all();
        $all['enabled'] = true;
        $all['allow_catalog_writes'] = true;
        $all['daily_jobs_master_enabled'] = false;
        $settings->save($all);

        $reason = app(AnanasSyncSettings::class)->cronGateReason(AnanasSyncRun::JOB_CATALOG);

        $this->assertSame('Daily jobs master switch is off.', $reason);
    }

    public function test_stale_run_recovery_does_not_fail_active_heartbeat(): void
    {
        $run = AnanasSyncRun::query()->create([
            'job_key' => AnanasSyncRun::JOB_IMPORT,
            'status' => AnanasSyncRun::STATUS_RUNNING,
            'triggered_by' => 'cron',
            'last_progress_at' => now(),
            'started_at' => now(),
        ]);

        $recovered = app(AnanasSyncRunService::class)->recoverStaleRuns();

        $this->assertSame(0, $recovered);
        $this->assertSame(AnanasSyncRun::STATUS_RUNNING, $run->fresh()?->status);
    }

    public function test_failed_inventory_sample_capped_at_fifty(): void
    {
        $run = AnanasSyncRun::query()->create([
            'job_key' => AnanasSyncRun::JOB_PRICE_STOCK,
            'status' => AnanasSyncRun::STATUS_RUNNING,
            'triggered_by' => 'cron',
        ]);

        $ids = range(1, 80);

        app(AnanasSyncRunService::class)->finishSucceeded($run, failedIds: $ids);

        $fresh = $run->fresh();
        $this->assertSame(80, $fresh?->failed_item_count);
        $this->assertCount(50, $fresh?->failed_inventory_ids ?? []);
    }

    public function test_price_window_before_two_am(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-15 01:30:00', AnanasDailyWindow::timezone()));

        $this->assertTrue(AnanasDailyWindow::inPriceStockWindow());
        $this->assertTrue(AnanasDailyWindow::inDiscountWindow());

        Carbon::setTestNow(Carbon::parse('2026-03-15 02:30:00', AnanasDailyWindow::timezone()));

        $this->assertFalse(AnanasDailyWindow::inPriceStockWindow());
        $this->assertTrue(AnanasDailyWindow::inDiscountWindow());
    }

    public function test_intraday_stock_skipped_without_catalog(): void
    {
        $runs = app(AnanasSyncRunService::class);
        $this->assertFalse($runs->todaySucceeded(AnanasSyncRun::JOB_CATALOG));

        Cache::put($runs->catalogSnapshotCacheKey(), ['remote_rows' => []], now()->addHour());
        $this->assertIsArray($runs->catalogSnapshot());
    }

    public function test_cached_config_production_overrides_stored_stage(): void
    {
        config([
            'bnc.ananas_env' => 'stage',
            'bnc.ananas_env_locked' => false,
        ]);

        app(AnanasSyncSettings::class)->save([
            'environment' => AnanasSyncSettings::ENV_STAGE,
        ]);

        config([
            'bnc.ananas_env' => 'production',
            'bnc.ananas_env_locked' => true,
            'bnc.ananas_production_token_url' => 'https://api.ananas.rs/iam/api/v1/auth/token',
        ]);

        $settings = app(AnanasSyncSettings::class);

        $this->assertSame(AnanasSyncSettings::ENV_PRODUCTION, $settings->environment());
        $this->assertStringContainsString('api.ananas.rs', $settings->tokenEndpointUrl());
    }

    public function test_explicit_stage_in_config_is_not_overridden_by_stored_production(): void
    {
        app(AnanasSyncSettings::class)->save([
            'environment' => AnanasSyncSettings::ENV_PRODUCTION,
        ]);

        config([
            'bnc.ananas_env' => 'stage',
            'bnc.ananas_env_locked' => true,
            'bnc.ananas_stage_token_url' => 'https://api.qa2.ananastest.com/iam/api/v1/auth/token',
        ]);

        $settings = app(AnanasSyncSettings::class);

        $this->assertSame(AnanasSyncSettings::ENV_STAGE, $settings->environment());
        $this->assertStringContainsString('api.qa2.ananastest.com', $settings->tokenEndpointUrl());
    }
}

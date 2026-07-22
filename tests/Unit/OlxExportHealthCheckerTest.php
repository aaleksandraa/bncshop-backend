<?php

namespace Tests\Unit;

use App\Models\ApiSource;
use App\Services\Olx\OlxExportHealthChecker;
use App\Services\Olx\OlxSyncSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OlxExportHealthCheckerTest extends TestCase
{
    use RefreshDatabase;

    public function test_next_scheduled_at_after_evening_sync_is_next_morning(): void
    {
        ApiSource::query()->create([
            'name' => 'OLX / PIK export',
            'target_system_code' => 'olx',
            'base_url' => 'https://api.olx.ba',
            'username' => 'shop',
            'password' => 'secret',
            'is_active' => true,
            'auto_sync_enabled' => true,
            'last_successful_sync_at' => now()->setDate(2026, 7, 21)->setTime(18, 0, 52),
        ]);

        $checker = app(OlxExportHealthChecker::class);
        $next = $checker->nextScheduledAtAfter(now()->setDate(2026, 7, 21)->setTime(18, 0, 52));

        $this->assertSame('2026-07-22 06:00:00', $next?->format('Y-m-d H:i:s'));
    }

    public function test_olx_source_is_marked_overdue_after_missed_morning_slot(): void
    {
        $this->travelTo(now()->setDate(2026, 7, 22)->setTime(12, 0, 0));

        ApiSource::query()->create([
            'name' => 'OLX / PIK export',
            'target_system_code' => 'olx',
            'base_url' => 'https://api.olx.ba',
            'username' => 'shop',
            'password' => 'secret',
            'is_active' => true,
            'auto_sync_enabled' => true,
            'last_successful_sync_at' => now()->copy()->subHours(18),
        ]);

        $report = app(OlxExportHealthChecker::class)->report();

        $this->assertTrue($report['is_overdue']);
        $this->assertContains(
            'OLX export je zakasnio — provjerite da li cron pokreće bnc:sync-olx-scheduled i da li queue worker radi na sync redu.',
            $report['issues'],
        );
    }

    public function test_olx_report_lists_missing_credentials(): void
    {
        ApiSource::query()->create([
            'name' => 'OLX / PIK export',
            'target_system_code' => 'olx',
            'base_url' => 'https://api.olx.ba',
            'username' => null,
            'password' => null,
            'is_active' => true,
            'auto_sync_enabled' => true,
        ]);

        $report = app(OlxExportHealthChecker::class)->report();

        $this->assertFalse($report['has_credentials']);
        $this->assertContains('OLX kredencijali nisu konfigurirani.', $report['issues']);
    }
}

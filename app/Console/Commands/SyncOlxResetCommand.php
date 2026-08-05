<?php

namespace App\Console\Commands;

use App\Models\ApiImportJob;
use App\Services\Olx\OlxSyncSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SyncOlxResetCommand extends Command
{
    protected $signature = 'bnc:sync-olx-reset
                            {--release-stale-minutes=0 : Mark running OLX import jobs older than N minutes as failed (0 = skip)}';

    protected $description = 'Clear legacy OLX queue locks and failed RunOlxSyncJob entries before a fresh export run';

    public function handle(OlxSyncSettings $settings): int
    {
        $locksCleared = $this->clearLegacyUniqueLocks();
        $this->line("Legacy unique lock keys cleared: {$locksCleared}");

        $failedRemoved = DB::table('failed_jobs')
            ->where('payload', 'like', '%RunOlxSyncJob%')
            ->delete();
        $this->line("Removed failed RunOlxSyncJob entries: {$failedRemoved}");

        $releaseMinutes = (int) $this->option('release-stale-minutes');
        if ($releaseMinutes > 0) {
            $cutoff = now()->subMinutes($releaseMinutes);
            $released = ApiImportJob::query()
                ->where('api_source_id', $settings->resolveSource()->id)
                ->whereIn('type', ['olx_incremental', 'olx_full'])
                ->where('status', 'running')
                ->where('started_at', '<', $cutoff)
                ->update([
                    'status' => 'failed',
                    'completed_at' => now(),
                    'error_message' => 'Reset before fresh OLX sync run.',
                ]);
            $this->line("Released stale running OLX import jobs: {$released}");
        }

        $this->newLine();
        $this->info('Spremno za novi run. Preporučeno (foreground, bez queue-a):');
        $this->line('  php artisan bnc:sync-olx --sync --full-quota');
        $this->newLine();
        $this->line('Ili preko queue-a (nakon deploya bez ShouldBeUnique):');
        $this->line('  php artisan bnc:sync-olx --full-quota');

        return self::SUCCESS;
    }

    private function clearLegacyUniqueLocks(): int
    {
        $cleared = 0;
        $prefixes = [
            'laravel_unique_job:App\\Jobs\\RunOlxSyncJob:olx-sync-all-incremental',
            'laravel_unique_job:App\\Jobs\\RunOlxSyncJob:olx-sync-all-full',
        ];

        foreach ($prefixes as $key) {
            if (Cache::forget($key)) {
                $cleared++;
            }
        }

        $store = Cache::getStore();
        if (method_exists($store, 'connection')) {
            try {
                $redis = $store->connection();
                $cachePrefix = method_exists($store, 'getPrefix') ? $store->getPrefix() : '';
                $pattern = $cachePrefix.'laravel_unique_job:App\\Jobs\\RunOlxSyncJob:*';
                /** @var array<int, string> $keys */
                $keys = $redis->keys($pattern);

                foreach ($keys as $key) {
                    $logicalKey = $cachePrefix !== '' && str_starts_with($key, $cachePrefix)
                        ? substr($key, strlen($cachePrefix))
                        : $key;

                    if (Cache::forget($logicalKey)) {
                        $cleared++;
                    }
                }
            } catch (\Throwable) {
                // Redis scan optional — prefix forget above is enough on most setups.
            }
        }

        return $cleared;
    }
}

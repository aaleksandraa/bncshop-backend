<?php

namespace App\Console\Commands;

use App\Jobs\ReindexProductsJob;
use App\Services\Eline\ElineApiClient;
use App\Services\Eline\ElinePriceRestoreService;
use Illuminate\Console\Command;
use Throwable;

class RestoreElinePricesCommand extends Command
{
    protected $signature = 'bnc:eline-restore-prices
                            {--dry-run : Preview restored prices without saving}
                            {--keep-locked : Skip products whose price was manually locked}
                            {--all-eline : Also restore eLine products that are not marked refurbished}';

    protected $description = 'Restore eLine shelf prices to the live cjenovnik MPC';

    public function handle(ElinePriceRestoreService $service, ElineApiClient $client): int
    {
        set_time_limit(0);

        $dryRun = (bool) $this->option('dry-run');
        $keepLocked = (bool) $this->option('keep-locked');
        $refurbishedOnly = ! $this->option('all-eline');

        if ($dryRun) {
            $this->warn('Dry run — no prices will be saved.');
        }

        try {
            $priceChoices = $client->priceChoicesBySifra($client->fetchCjenovnici());
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $scope = $refurbishedOnly
            ? 'refurbished eLine products'
            : 'all eLine products';

        $this->info("Restoring live eLine MPC on {$scope}...");
        $this->line('Feed prices: '.count($priceChoices));

        $stats = $service->restore($dryRun, $keepLocked, $refurbishedOnly, $priceChoices);

        $this->line("Scanned: {$stats['scanned']}");
        $this->line('Updated: '.$stats['updated'].($dryRun ? ' (would update)' : ''));
        $this->line("Unchanged: {$stats['unchanged']}");
        $this->line("Skipped locked: {$stats['skipped_locked']}");
        $this->line("Skipped without price: {$stats['skipped_missing']}");

        foreach ($stats['samples'] as $sample) {
            $this->line(sprintf(
                '  [%s] %s — %s → %s',
                $sample['eline_sifra'],
                $sample['name'],
                $sample['from'],
                $sample['to'],
            ));
        }

        if (count($stats['samples']) < $stats['updated']) {
            $hidden = $stats['updated'] - count($stats['samples']);
            $this->line("  … and {$hidden} more.");
        }

        if (! $dryRun && $stats['updated_ids'] !== []) {
            foreach (array_chunk($stats['updated_ids'], 400) as $chunk) {
                ReindexProductsJob::dispatch($chunk);
            }

            $this->info('Dispatched search reindex for updated products.');
        }

        return self::SUCCESS;
    }
}

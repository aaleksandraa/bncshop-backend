<?php

namespace App\Console\Commands;

use App\Jobs\ReindexProductsJob;
use App\Services\Catalog\ProductReadCache;
use App\Services\Eline\ElineVisibilityReapplyService;
use Illuminate\Console\Command;

class ReapplyElineVisibilityCommand extends Command
{
    protected $signature = 'bnc:eline-fix-visibility
                            {--dry-run : Preview changes without saving}
                            {--from-db : Use BNC category only (less accurate when new/refurbished share a category)}';

    protected $description = 'Fix eLine storefront visibility flags without touching images, names, prices, or stock';

    public function handle(
        ElineVisibilityReapplyService $service,
        ProductReadCache $productReadCache,
    ): int {
        $dryRun = (bool) $this->option('dry-run');
        $fromDb = (bool) $this->option('from-db');

        if ($dryRun) {
            $this->warn('Dry run — no database changes will be saved.');
        }

        if ($fromDb) {
            $this->info('Reapplying eLine visibility from BNC categories (images and content stay untouched)...');
            $stats = $service->reapplyFromDatabase($dryRun);
        } else {
            $this->info('Reapplying eLine visibility from feed categories (images and content stay untouched)...');
            $stats = $service->reapplyFromFeed($dryRun);
        }

        $this->line("Scanned: {$stats['scanned']}");
        $this->line('Updated: '.$stats['updated'].($dryRun ? ' (would update)' : ''));
        $this->line("Skipped: {$stats['skipped']}");

        foreach ($stats['changes'] as $change) {
            $fields = collect($change['updates'])
                ->map(fn ($value, $key): string => "{$key}=".json_encode($value))
                ->implode(', ');

            $categoryHint = isset($change['eline_category'])
                ? ' ('.$change['eline_category'].')'
                : '';

            $this->line(sprintf(
                '  [%s]%s %s — %s',
                (string) $change['eline_sifra'],
                $categoryHint,
                (string) $change['name'],
                $fields,
            ));
        }

        if ($dryRun || $stats['updated'] === 0) {
            return self::SUCCESS;
        }

        $productReadCache->flushAll();

        $reindexIds = collect($stats['changes'])
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($reindexIds !== []) {
            ReindexProductsJob::dispatch($reindexIds);
            $this->info('Dispatched search reindex for updated products.');
        }

        return self::SUCCESS;
    }
}

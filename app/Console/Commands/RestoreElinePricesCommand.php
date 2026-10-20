<?php

namespace App\Console\Commands;

use App\Jobs\ReindexProductsJob;
use App\Models\Product;
use App\Services\Eline\ElineApiClient;
use App\Services\Eline\ElinePriceRestoreService;
use App\Services\Eline\ElineSupport;
use Illuminate\Console\Command;
use Throwable;

class RestoreElinePricesCommand extends Command
{
    protected $signature = 'bnc:eline-restore-prices
                            {--dry-run : Preview restored prices without saving}
                            {--keep-locked : Skip products whose price was manually locked}
                            {--refurbished-only : Only products marked refurbished}
                            {--sifra= : Limit to one eLine sifra and print every cjenovnik row}
                            {--all-eline : Accepted for compatibility; all eLine products are included unless --refurbished-only}';

    protected $description = 'Rewrite eLine shelf prices from the live cjenovnik MPC';

    public function handle(ElinePriceRestoreService $service, ElineApiClient $client): int
    {
        set_time_limit(0);

        $dryRun = (bool) $this->option('dry-run');
        $keepLocked = (bool) $this->option('keep-locked');
        $refurbishedOnly = (bool) $this->option('refurbished-only');
        $onlySifra = trim((string) $this->option('sifra'));

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

        $this->info("Upisuje se polje mpc iz eLine cjenovnika ({$scope})...");
        $feedCount = 0;

        foreach ($priceChoices as $key => $choice) {
            if (! is_string($key) || ! str_starts_with($key, 'external:')) {
                $feedCount++;
            }
        }

        $this->line('Feed prices: '.$feedCount);

        if ($onlySifra !== '') {
            $this->printSifra($onlySifra, $priceChoices);
        }

        $stats = $service->restore($dryRun, $keepLocked, $refurbishedOnly, $priceChoices, $onlySifra !== '' ? $onlySifra : null);

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

        if ($onlySifra !== '' && $stats['scanned'] === 0) {
            $this->printMissingProduct($onlySifra);
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

    /**
     * @param  array<string, array{mpc: float, candidates: list<float>, rows?: list<array<string, mixed>>}>  $priceChoices
     */
    private function printSifra(string $sifra, array $priceChoices): void
    {
        $choice = null;

        foreach (ElineSupport::sifraKeys($sifra) as $key) {
            if (isset($priceChoices[$key])) {
                $choice = $priceChoices[$key];
                break;
            }
        }

        $this->line('Cjenovnik za šifru '.$sifra.':');

        foreach ($choice['rows'] ?? [] as $row) {
            $this->line(sprintf(
                '  skladiste %s | mpc %s | stanje %s | aktivan %s',
                (string) ($row['skladiste'] ?? '—'),
                number_format((float) $row['mpc'], 2, '.', ''),
                (string) ($row['stanje'] ?? '—'),
                (string) ($row['aktivan'] ?? '—'),
            ));
        }

        if ($choice === null) {
            $this->warn('  Nema MPC za ovu šifru u cjenovniku.');
        }
    }

    private function printMissingProduct(string $sifra): void
    {
        $this->warn('Shop nema artikal sa šifrom '.$sifra.' (SKU, eline_sifra ni originalni eLine id).');

        $rows = Product::query()
            ->where(function ($query) use ($sifra): void {
                $like = '%'.$sifra.'%';
                $query->where('sku', 'like', $like)->orWhere('eline_sifra', 'like', $like);
            })
            ->orderBy('id')
            ->limit(8)
            ->get();

        if ($rows->isEmpty()) {
            $this->line('  Nema ni sličnog SKU ni šifre u bazi.');

            return;
        }

        foreach ($rows as $row) {
            $this->line(sprintf(
                '  id %d | izvor %s | sku %s | eline_sifra %s | cijena %s | %s',
                $row->id,
                (string) ($row->import_source ?: '—'),
                (string) $row->sku,
                (string) ($row->eline_sifra ?: '—'),
                number_format((float) $row->display_price, 2, '.', ''),
                (string) $row->name,
            ));
        }
    }
}

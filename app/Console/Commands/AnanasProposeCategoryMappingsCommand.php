<?php

namespace App\Console\Commands;

use App\Services\Ananas\AnanasCategoryMappingProposer;
use Illuminate\Console\Command;

class AnanasProposeCategoryMappingsCommand extends Command
{
    protected $signature = 'bnc:ananas-propose-category-mappings
                            {--refresh : Kept for compatibility (leaf catalog is local, not GET product-type)}
                            {--prune : Delete disabled auto-proposed mappings (keeps validated/enabled)}
                            {--min-products=1 : Ignore BNC categories with fewer active public products}
                            {--min-score=88 : Minimum name-match score (0–100)}
                            {--apply : Create disabled mappings for suggestions}
                            {--enable-exact : Also enable mappings with score ≥ 95}
                            {--product-type= : Fallback Ananas productType (default ITShop)}
                            {--unmatched=20 : How many unmatched BNC categories to print}';

    protected $description = 'Match BNC categories to Ananas leaf category catalog (not the 10 product-type templates)';

    public function handle(AnanasCategoryMappingProposer $proposer): int
    {
        if ((bool) $this->option('prune')) {
            $pruned = $proposer->pruneUnvalidatedProposals();
            $this->info('Obrisano predloženih (isključenih) mapiranja: '.$pruned['deleted']);
            if ($pruned['ids'] !== []) {
                $this->line('IDs: '.implode(', ', $pruned['ids']));
            }

            return self::SUCCESS;
        }

        $minScore = max(50, min(100, (int) $this->option('min-score')));
        $productType = trim((string) $this->option('product-type'));
        if ($productType === '') {
            $productType = (string) config('bnc.ananas_mapping_default_product_type', 'ITShop');
        }

        $result = $proposer->propose(
            minProducts: max(0, (int) $this->option('min-products')),
            minScore: $minScore,
        );

        $this->info(sprintf(
            'Leaf catalog: %d stringova. Već pokriveno uključenim mapiranjima: %d BNC kategorija.',
            $result['ananas_names'],
            $result['skipped_covered'],
        ));
        $this->comment('GET product-type su šabloni (ITShop, Moda, Sport…) — nisu leaf kategorije. Ne mapirati Šporeti→Sport.');

        if ($result['suggestions'] === []) {
            $this->warn('Nema prijedloga iznad score '.$minScore.'.');
        } else {
            $this->newLine();
            $this->info('Prijedlozi ('.count($result['suggestions']).'):');
            $this->table(
                ['BNC ID', 'BNC kategorija', 'SKU', 'Type', 'Ananas category', 'Score'],
                array_map(static fn (array $row): array => [
                    (string) $row['category_id'],
                    (string) $row['bnc_category'],
                    (string) $row['products'],
                    (string) ($row['product_type'] ?? $productType),
                    (string) $row['ananas_category'],
                    (string) $row['score'],
                ], $result['suggestions']),
            );
        }

        $unmatchedLimit = max(0, (int) $this->option('unmatched'));
        if ($unmatchedLimit > 0 && $result['unmatched'] !== []) {
            $this->newLine();
            $this->warn('Bez leaf stringa u katalogu (top '.$unmatchedLimit.'):');
            $this->table(
                ['BNC ID', 'BNC kategorija', 'SKU', 'Type', 'Najbliži leaf', 'Score'],
                array_map(static fn (array $row): array => [
                    (string) $row['category_id'],
                    (string) $row['bnc_category'],
                    (string) $row['products'],
                    (string) ($row['product_type'] ?? $productType),
                    (string) ($row['ananas_category'] ?? '—'),
                    (string) $row['score'],
                ], array_slice($result['unmatched'], 0, $unmatchedLimit)),
            );
        }

        if (! $this->option('apply')) {
            $this->newLine();
            $this->comment('Ako su ranije upisani krivi prijedlozi (Sport/Aparati):');
            $this->comment('  php artisan bnc:ananas-propose-category-mappings --prune');
            $this->comment('Zatim --apply, pa u Filamentu uključite samo tačne leaf stringove.');

            return self::SUCCESS;
        }

        $applied = $proposer->applySuggestions(
            $result['suggestions'],
            enableExact: (bool) $this->option('enable-exact'),
            productType: $productType,
        );

        $this->info(sprintf('Kreirano: %d  preskočeno: %d', $applied['created'], $applied['skipped']));
        $this->comment('Nova mapiranja su isključena. Provjerite Ananas category, pa uključite.');

        return self::SUCCESS;
    }
}

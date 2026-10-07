<?php

namespace App\Console\Commands;

use App\Services\Ananas\AnanasCategoryMappingProposer;
use Illuminate\Console\Command;

class AnanasProposeCategoryMappingsCommand extends Command
{
    protected $signature = 'bnc:ananas-propose-category-mappings
                            {--refresh : GET /product-type and refresh local cache first}
                            {--min-products=1 : Ignore BNC categories with fewer active public products}
                            {--min-score=82 : Minimum name-match score (0–100)}
                            {--apply : Create disabled mappings for suggestions (does not enable export)}
                            {--enable-exact : Also enable mappings with score ≥ 95 (still not GET-validated)}
                            {--product-type= : Ananas productType (default ITShop)}
                            {--unmatched=20 : How many unmatched BNC categories to print}';

    protected $description = 'Match BNC categories to Ananas GET product-type strings and optionally create mappings';

    public function handle(AnanasCategoryMappingProposer $proposer): int
    {
        $minScore = max(50, min(100, (int) $this->option('min-score')));
        $productType = trim((string) $this->option('product-type'));
        if ($productType === '') {
            $productType = (string) config('bnc.ananas_mapping_default_product_type', 'ITShop');
        }

        try {
            $result = $proposer->propose(
                minProducts: max(0, (int) $this->option('min-products')),
                minScore: $minScore,
                refreshTypes: (bool) $this->option('refresh'),
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            $this->line('Ako --refresh ne može: php artisan bnc:ananas-refresh-product-types');

            return self::FAILURE;
        }

        if ($result['ananas_names'] === 0) {
            $this->error('Nema keširanih Ananas naziva. Pokrenite: php artisan bnc:ananas-refresh-product-types');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Ananas naziva (GET product-type): %d. Već pokriveno uključenim mapiranjima: %d kategorija.',
            $result['ananas_names'],
            $result['skipped_covered'],
        ));
        $this->comment('productType ostaje '.$productType.'; category = predloženi Ananas string. Atributi i dalje idu flat, ne po kategoriji.');

        if ($result['suggestions'] === []) {
            $this->warn('Nema prijedloga iznad score '.$minScore.'.');
        } else {
            $this->newLine();
            $this->info('Prijedlozi ('.count($result['suggestions']).'):');
            $this->table(
                ['BNC ID', 'BNC kategorija', 'SKU', 'Ananas category', 'Score'],
                array_map(static fn (array $row): array => [
                    (string) $row['category_id'],
                    (string) $row['bnc_category'],
                    (string) $row['products'],
                    (string) $row['ananas_category'],
                    (string) $row['score'],
                ], $result['suggestions']),
            );
        }

        $unmatchedLimit = max(0, (int) $this->option('unmatched'));
        if ($unmatchedLimit > 0 && $result['unmatched'] !== []) {
            $this->newLine();
            $this->warn('Bez pouzdanog Ananas stringa (top '.$unmatchedLimit.' po broju proizvoda):');
            $this->table(
                ['BNC ID', 'BNC kategorija', 'SKU', 'Najbliži Ananas', 'Score'],
                array_map(static fn (array $row): array => [
                    (string) $row['category_id'],
                    (string) $row['bnc_category'],
                    (string) $row['products'],
                    (string) ($row['ananas_category'] ?? '—'),
                    (string) $row['score'],
                ], array_slice($result['unmatched'], 0, $unmatchedLimit)),
            );
        }

        if (! $this->option('apply')) {
            $this->newLine();
            $this->comment('Dry pregled. Da upiše mapiranja (isključena dok ih ne uključite):');
            $this->comment('  php artisan bnc:ananas-propose-category-mappings --refresh --apply');
            $this->comment('Zatim u Filamentu Ananas → Mapiranje kategorija uključite samo tačne redove.');

            return self::SUCCESS;
        }

        $applied = $proposer->applySuggestions(
            $result['suggestions'],
            enableExact: (bool) $this->option('enable-exact'),
            productType: $productType,
        );

        $this->info(sprintf('Kreirano: %d  preskočeno (već postoji): %d', $applied['created'], $applied['skipped']));
        $this->comment('Nova mapiranja su isključena za export (osim --enable-exact). Provjerite string u Filamentu, pa uključite.');

        return self::SUCCESS;
    }
}

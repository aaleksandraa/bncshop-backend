<?php

namespace App\Console\Commands;

use App\Services\Ananas\AnanasValidatedMappingService;
use Illuminate\Console\Command;

class AnanasApplyValidatedMappingsCommand extends Command
{
    protected $signature = 'bnc:ananas-apply-validated-mappings';

    protected $description = 'Upsert Stage-validated Ananas category mappings (Gaming laptopi, Nosači za televizor) and enable export';

    public function handle(AnanasValidatedMappingService $mappingService): int
    {
        $result = $mappingService->apply();

        if ($result['applied'] === [] && $result['skipped'] === [] && $result['disabled'] === []) {
            $this->warn('Nema definicija u config bnc.ananas_validated_mappings.');

            return self::FAILURE;
        }

        if ($result['applied'] !== []) {
            $this->info('Primijenjena mapiranja ('.$this->countApplied($result['applied']).')');
            $this->table(
                ['Action', 'Mapping ID', 'BNC cat', 'BNC', 'Ananas category', 'Enabled'],
                array_map(static fn (array $row): array => [
                    $row['action'],
                    (string) $row['mapping_id'],
                    (string) $row['category_id'],
                    $row['bnc_category'],
                    $row['ananas_category'],
                    $row['enabled'] ? 'yes' : 'no',
                ], $result['applied']),
            );
        }

        foreach ($result['skipped'] as $skip) {
            $this->warn($skip);
        }

        if ($result['disabled'] !== []) {
            $this->newLine();
            $this->comment('Isključena zastarjela mapiranja:');
            foreach ($result['disabled'] as $row) {
                $this->line(sprintf(
                    '  #%d (BNC %d, „%s“): %s',
                    $row['mapping_id'],
                    $row['category_id'],
                    $row['ananas_category'] !== '' ? $row['ananas_category'] : 'prazno',
                    $row['reason'],
                ));
            }
        }

        $this->newLine();
        $this->line('Sljedeće (dry-run, bez POST):');
        $this->line('  php artisan bnc:ananas-import-products --limit=50 --dry-run');
        $this->line('Zatim iz admina: Ananas → Postavke → Import, ili:');
        $this->line('  php artisan bnc:ananas-import-products --limit=50 --confirm');

        return $result['applied'] !== [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  list<array{action: string}>  $applied
     */
    private function countApplied(array $applied): string
    {
        $created = count(array_filter($applied, static fn (array $row): bool => $row['action'] === 'created'));
        $updated = count($applied) - $created;

        return $created.' new, '.$updated.' updated';
    }
}

<?php

namespace App\Console\Commands;

use App\Services\Ananas\AnanasBatchMappingEnabler;
use App\Services\Ananas\AnanasExportScope;
use Illuminate\Console\Command;

class AnanasEnableBatchMappingsCommand extends Command
{
    protected $signature = 'bnc:ananas-enable-batch-mappings
                            {--dry-run : Show which mappings would be enabled without writing}';

    protected $description = 'Enable 1:1 Stage batch categories (Monitori, Toneri, …) plus Gaming laptopi / Nosači; never Laptopi/Računari/Igrice/Fax';

    public function handle(AnanasBatchMappingEnabler $enabler, AnanasExportScope $exportScope): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = $enabler->enable($dryRun);

        if ($result['enabled'] === []) {
            $this->warn('Nijedno mapiranje nije u batch listi. Pokrenite propose --apply pa ponovite.');

            return self::FAILURE;
        }

        $this->info($dryRun ? 'Dry-run (ništa nije uključeno):' : 'Uključena mapiranja za batch 1000–2000:');
        $this->table(
            ['Mapping ID', 'BNC ID', 'BNC', 'Ananas', 'Was enabled'],
            array_map(static fn (array $row): array => [
                (string) $row['mapping_id'],
                (string) $row['category_id'],
                $row['bnc_category'],
                $row['ananas_category'],
                $row['already'] ? 'yes' : 'no',
            ], $result['enabled']),
        );

        foreach ($result['skipped'] as $skip) {
            $this->warn($skip);
        }

        $exportScope->flushCaches();
        $this->newLine();
        $this->comment('Zatim (Stage, prema Ananas 1000–2000 po POST):');
        $this->comment('  php artisan bnc:ananas-import-products --limit=1000 --dry-run');
        $this->comment('  php artisan bnc:ananas-import-products --limit=1000 --confirm');

        return self::SUCCESS;
    }
}

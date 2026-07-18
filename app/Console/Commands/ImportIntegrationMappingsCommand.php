<?php

namespace App\Console\Commands;

use App\Services\Integrations\IntegrationMappingTransfer;
use Illuminate\Console\Command;

class ImportIntegrationMappingsCommand extends Command
{
    protected $signature = 'bnc:import-integration-mappings
                            {--path= : JSON path (default database/seeders/data/integration_mappings.json)}
                            {--only-enabled : Import only enabled eLine/OLX category mappings}';

    protected $description = 'Import eLine and OLX admin mappings from portable JSON (after A1 category sync)';

    public function handle(IntegrationMappingTransfer $transfer): int
    {
        $result = $transfer->importFromFile(
            $this->option('path'),
            (bool) $this->option('only-enabled'),
        );

        $this->info('Imported integration mappings.');
        $this->line("eLine mappings: {$result['eline']}");
        $this->line("OLX category mappings: {$result['olx_categories']}");
        $this->line("OLX attribute mappings: {$result['olx_attributes']}");

        if ($result['warnings'] !== []) {
            $this->newLine();
            $this->warn('Warnings:');

            foreach ($result['warnings'] as $warning) {
                $this->line("- {$warning}");
            }
        }

        return self::SUCCESS;
    }
}

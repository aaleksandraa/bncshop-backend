<?php

namespace App\Console\Commands;

use App\Services\Integrations\IntegrationMappingTransfer;
use Illuminate\Console\Command;

class ExportIntegrationMappingsCommand extends Command
{
    protected $signature = 'bnc:export-integration-mappings
                            {--path= : Output JSON path (default database/seeders/data/integration_mappings.json)}';

    protected $description = 'Export eLine and OLX admin mappings to a portable JSON file';

    public function handle(IntegrationMappingTransfer $transfer): int
    {
        $path = $transfer->exportToFile($this->option('path'));

        $payload = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        $this->info("Exported integration mappings to {$path}");
        $this->line('eLine mappings: '.count($payload['eline_category_mappings'] ?? []));
        $this->line('OLX category mappings: '.count($payload['olx_category_mappings'] ?? []));
        $this->line('OLX attribute mappings: '.count($payload['olx_attribute_mappings'] ?? []));

        return self::SUCCESS;
    }
}

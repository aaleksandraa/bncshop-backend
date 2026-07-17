<?php

namespace App\Console\Commands;

use App\Services\Olx\OlxExistingListingImporter;
use Illuminate\Console\Command;

class OlxImportExistingListingsCommand extends Command
{
    protected $signature = 'bnc:olx-import-existing-listings {--username=bnc}';

    protected $description = 'Import existing OLX shop listings into legacy registry';

    public function handle(OlxExistingListingImporter $importer): int
    {
        $result = $importer->import((string) $this->option('username'));
        $this->info("Imported {$result['imported']} listings across {$result['pages']} pages.");
        $this->info("Matched {$result['matched']} listings to local products.");

        return self::SUCCESS;
    }
}

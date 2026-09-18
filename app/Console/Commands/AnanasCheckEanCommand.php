<?php

namespace App\Console\Commands;

use App\Services\Ananas\AnanasMasterEanCatalogService;
use App\Services\Ananas\AnanasSyncSettings;
use Illuminate\Console\Command;

class AnanasCheckEanCommand extends Command
{
    protected $signature = 'bnc:ananas-check-ean {ean : EAN/barcode to check against Ananas master catalog}';

    protected $description = 'POST ean/exists — whether Ananas master catalog already knows this EAN';

    public function handle(AnanasMasterEanCatalogService $catalog, AnanasSyncSettings $settings): int
    {
        if (! $settings->hasCredentials()) {
            $this->error('Ananas credentials are not configured.');

            return self::FAILURE;
        }

        $ean = trim((string) $this->argument('ean'));

        if ($ean === '') {
            $this->error('EAN is empty.');

            return self::FAILURE;
        }

        $exists = $catalog->isInMasterCatalog($ean);

        $this->info(sprintf('EAN %s in Ananas master catalog: %s', $ean, $exists ? 'yes' : 'no'));

        if (! $exists) {
            $this->line('New EANs are loaded manually by Ananas onboarding ('.config('bnc.ananas_onboarding_email').').');
        }

        return self::SUCCESS;
    }
}

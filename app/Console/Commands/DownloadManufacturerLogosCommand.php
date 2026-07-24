<?php

namespace App\Console\Commands;

use App\Services\Catalog\ManufacturerLogoDownloader;
use App\Services\Catalog\ProductReadCache;
use Illuminate\Console\Command;

class DownloadManufacturerLogosCommand extends Command
{
    protected $signature = 'manufacturers:download-logos
                            {--limit= : Max brands to download after URL resolve}
                            {--force : Re-download even when a local logo already exists}';

    protected $description = 'Resolve brand logos from A1 storefront and download them into local storage';

    public function handle(
        ManufacturerLogoDownloader $downloader,
        ProductReadCache $productReadCache,
    ): int {
        $limitOption = $this->option('limit');
        $limit = $limitOption !== null && $limitOption !== ''
            ? max(1, (int) $limitOption)
            : null;
        $force = (bool) $this->option('force');

        $this->info('Resolving logo URLs from A1 brand directory…');

        $result = $downloader->downloadMissing($limit, $force);
        $productReadCache->flushManufacturers();

        $this->info(sprintf(
            'Resolved URLs: %d | Downloaded: %d | Skipped: %d | Failed: %d | Still without logo: %d',
            $result['resolved'],
            $result['downloaded'],
            $result['skipped'],
            $result['failed'],
            $result['unmatched'],
        ));

        if ($result['downloaded'] === 0 && $result['resolved'] === 0) {
            $this->warn('Nijedan logo nije pronađen. Provjerite da li server može dohvatiti https://a1team.ba/brendovi');
        }

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}

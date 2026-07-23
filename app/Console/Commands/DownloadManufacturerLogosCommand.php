<?php

namespace App\Console\Commands;

use App\Services\Catalog\ManufacturerLogoDownloader;
use App\Services\Catalog\ProductReadCache;
use Illuminate\Console\Command;

class DownloadManufacturerLogosCommand extends Command
{
    protected $signature = 'manufacturers:download-logos
                            {--limit= : Max brands to process}
                            {--force : Re-download even when a local logo already exists}';

    protected $description = 'Download remote manufacturer logo_url files into local logo_path storage';

    public function handle(
        ManufacturerLogoDownloader $downloader,
        ProductReadCache $productReadCache,
    ): int {
        $limitOption = $this->option('limit');
        $limit = $limitOption !== null && $limitOption !== ''
            ? max(1, (int) $limitOption)
            : null;
        $force = (bool) $this->option('force');

        $result = $downloader->downloadMissing($limit, $force);
        $productReadCache->flushManufacturers();

        $this->info(sprintf(
            'Downloaded: %d, skipped: %d, failed: %d',
            $result['downloaded'],
            $result['skipped'],
            $result['failed'],
        ));

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}

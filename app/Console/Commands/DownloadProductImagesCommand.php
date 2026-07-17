<?php

namespace App\Console\Commands;

use App\Models\ProductImage;
use App\Services\Sync\ProductImageStorageService;
use Illuminate\Console\Command;

class DownloadProductImagesCommand extends Command
{
    protected $signature = 'bnc:download-product-images
                            {--limit=100 : Max images to process}
                            {--only-missing : Only images without a local file}
                            {--force : Re-download even when a local file already exists}';

    protected $description = 'Download remote product images into local public storage';

    public function handle(ProductImageStorageService $storage): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $onlyMissing = (bool) $this->option('only-missing');
        $force = (bool) $this->option('force');

        $query = ProductImage::query()
            ->where('status', 'active')
            ->with('product:id,external_product_id')
            ->orderBy('id');

        if ($onlyMissing) {
            $query->whereNull('local_path');
        }

        $images = $query->limit($limit)->get();

        if ($images->isEmpty()) {
            $this->info('No product images matched the criteria.');

            return self::SUCCESS;
        }

        $downloaded = 0;
        $failed = 0;

        foreach ($images as $image) {
            $product = $image->product;

            if ($product === null) {
                $failed++;
                continue;
            }

            if ($storage->storeFromRemote($image, $product, $force)) {
                $downloaded++;
                $this->line("Saved {$image->local_path}");
            } else {
                $failed++;
                $this->warn("Failed image #{$image->id} (product {$product->id})");
            }
        }

        $this->info("Downloaded: {$downloaded}, failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}

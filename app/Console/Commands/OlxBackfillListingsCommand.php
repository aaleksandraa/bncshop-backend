<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Olx\OlxListingExporter;
use App\Services\Olx\OlxListingImagesLockedException;
use Illuminate\Console\Command;
use Throwable;

class OlxBackfillListingsCommand extends Command
{
    protected $signature = 'bnc:olx-backfill
        {--product= : Single product ID}
        {--limit= : Max products to process}
        {--images : Re-upload images only}
        {--descriptions : Update descriptions only}
        {--force : Reprocess all managed listings (not only missing images)}';

    protected $description = 'Backfill OLX listings (images and/or formatted descriptions) for already exported products';

    public function handle(OlxListingExporter $exporter): int
    {
        $productId = $this->option('product') !== null ? (int) $this->option('product') : null;
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;
        $images = (bool) $this->option('images');
        $descriptions = (bool) $this->option('descriptions');
        $force = (bool) $this->option('force');

        if (! $images && ! $descriptions) {
            $images = true;
            $descriptions = true;
        }

        $query = Product::query()
            ->whereNotNull('olx_listing_id')
            ->where('olx_managed', true)
            ->with(['category.parent', 'images', 'attributeValues.attributeDefinition', 'manufacturer'])
            ->orderBy('id');

        if ($productId !== null) {
            $query->where('id', $productId);
        } elseif ($images && ! $descriptions && ! $force) {
            $query->where(function ($builder): void {
                $builder
                    ->whereNull('olx_image_map')
                    ->orWhereNull('olx_image_map->images')
                    ->orWhere('olx_image_map->images', '[]')
                    ->orWhere('olx_image_map->images', 'null')
                    ->orWhere('olx_last_error', 'like', 'Slike nisu učitane%');
            });
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        $products = $query->get();

        if ($products->isEmpty()) {
            $this->warn('Nema OLX oglasa za backfill.');

            return self::SUCCESS;
        }

        $stats = ['updated' => 0, 'skipped' => 0, 'errors' => 0];

        foreach ($products as $product) {
            try {
                if ($images && ! $descriptions) {
                    $result = $exporter->syncImages($product->fresh([
                        'category.parent',
                        'images',
                        'attributeValues.attributeDefinition',
                        'manufacturer',
                    ]), allowRecreate: false);

                    $stats['updated']++;
                    $this->line(sprintf(
                        'OK #%d → OLX %s',
                        $product->id,
                        $result['listing_id'],
                    ));

                    continue;
                }

                if ($descriptions) {
                    $product->update(['olx_export_hash' => null]);
                }

                $exporter->export($product->fresh([
                    'category.parent',
                    'images',
                    'attributeValues.attributeDefinition',
                    'manufacturer',
                ]), 'update');

                $stats['updated']++;
                $this->line("OK #{$product->id} → OLX {$product->olx_listing_id}");
            } catch (OlxListingImagesLockedException $e) {
                $stats['skipped']++;
                $this->warn("SKIP #{$product->id}: {$e->getMessage()}");
            } catch (Throwable $e) {
                $stats['errors']++;
                $this->error("FAIL #{$product->id}: {$e->getMessage()}");
            }
        }

        $this->info(sprintf(
            'Backfill završen: updated=%d, skipped=%d, errors=%d',
            $stats['updated'],
            $stats['skipped'],
            $stats['errors'],
        ));

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}

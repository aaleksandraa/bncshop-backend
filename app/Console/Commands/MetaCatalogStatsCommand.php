<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Integrations\MetaCatalogFeedPolicy;
use App\Services\Integrations\MetaCatalogFeedService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class MetaCatalogStatsCommand extends Command
{
    protected $signature = 'meta:catalog-stats
        {--sample=5 : Number of sample rows to print}
        {--check-images : HEAD-check sample image URLs}';

    protected $description = 'Show how many in-stock products match the Meta catalog feed filters';

    public function handle(
        MetaCatalogFeedPolicy $policy,
        MetaCatalogFeedService $feedService,
    ): int {
        $base = Product::query()
            ->public()
            ->active()
            ->where('display_price', '>', 0);

        $inStock = (clone $base)->where('available_stock', '>', 0);
        $filtered = $feedService->feedProductQuery();

        $this->info('Meta catalog feed');
        $this->line('Feed URL: '.$feedService->feedUrl());
        $this->line('Image CDN origin: '.$feedService->imageOrigin());
        $this->newLine();
        $this->line('Public active products: '.(clone $base)->count());
        $this->line('In stock: '.(clone $inStock)->count());
        $this->line('In feed (in stock + category filters + resolvable image): '.$this->countFeedRows($feedService));
        $this->newLine();

        $includes = $policy->includeCategorySlugs();
        $this->line('Include category slugs: '.($includes !== [] ? implode(', ', $includes) : '[all categories]'));

        $excludes = $policy->excludeNameKeywords();
        if ($excludes !== []) {
            $this->line('Exclude name keywords: '.implode(', ', array_slice($excludes, 0, 8)).(count($excludes) > 8 ? '…' : ''));
        }

        $sample = max(0, (int) $this->option('sample'));
        if ($sample > 0) {
            $this->newLine();
            $this->info('Sample products in feed:');

            $rows = (clone $filtered)
                ->with(['defaultImage', 'category'])
                ->orderBy('id')
                ->limit($sample * 3)
                ->get();

            $printed = 0;
            foreach ($rows as $product) {
                $image = $feedService->resolvePublicImageUrl($product);
                if ($image === null) {
                    continue;
                }

                $status = 'OK';
                if ($this->option('check-images')) {
                    $status = $this->checkImageUrl($image);
                }

                $this->line(sprintf(
                    '- #%d %s [%s]',
                    $product->id,
                    mb_substr((string) $product->name, 0, 50),
                    $product->category?->full_slug ?? 'no-category',
                ));
                $this->line('  '.$image.' ('.$status.')');

                $printed++;
                if ($printed >= $sample) {
                    break;
                }
            }
        }

        $this->newLine();
        $this->comment('Only in-stock products are exported. Out-of-stock items are omitted entirely.');
        $this->comment('Set BNC_MEDIA_ORIGIN=https://images.bnc.ba (or META_CATALOG_IMAGE_ORIGIN) on the server if images fail.');

        return self::SUCCESS;
    }

    private function countFeedRows(MetaCatalogFeedService $feedService): int
    {
        $count = 0;

        foreach (
            $feedService->feedProductQuery()
                ->with(['defaultImage'])
                ->orderBy('id')
                ->cursor() as $product
        ) {
            if ($feedService->resolvePublicImageUrl($product) !== null) {
                $count++;
            }
        }

        return $count;
    }

    private function checkImageUrl(string $url): string
    {
        try {
            $response = Http::timeout(6)->head($url);

            if ($response->successful()) {
                $type = $response->header('Content-Type');

                return $type !== null && $type !== '' ? 'HTTP '.$response->status().' '.$type : 'HTTP '.$response->status();
            }

            return 'HTTP '.$response->status();
        } catch (\Throwable $exception) {
            return 'FAIL '.$exception->getMessage();
        }
    }
}

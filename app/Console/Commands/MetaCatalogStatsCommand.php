<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Integrations\MetaCatalogFeedPolicy;
use App\Services\Integrations\MetaCatalogFeedService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class MetaCatalogStatsCommand extends Command
{
    protected $signature = 'meta:catalog-stats {--sample=5 : Number of sample rows to print}';

    protected $description = 'Show how many products match the Meta catalog feed filters';

    public function handle(
        MetaCatalogFeedPolicy $policy,
        MetaCatalogFeedService $feedService,
    ): int {
        $base = Product::query()
            ->public()
            ->active()
            ->where('display_price', '>', 0);

        $withImageMeta = (clone $base)->where(function (Builder $query): void {
            $query
                ->whereNotNull('default_image_id')
                ->orWhereNotNull('api_default_image_url');
        });

        $filtered = $policy->applyToQuery(clone $withImageMeta);

        $this->info('Meta catalog feed');
        $this->line('Feed URL: '.$feedService->feedUrl());
        $this->newLine();
        $this->line('Public active products: '.(clone $base)->count());
        $this->line('With image reference: '.(clone $withImageMeta)->count());
        $this->line('After category/name filters: '.(clone $filtered)->count());
        $this->newLine();

        $includes = $policy->includeCategorySlugs();
        $this->line('Include category slugs: '.($includes !== [] ? implode(', ', $includes) : '[all categories]'));

        $excludes = $policy->excludeNameKeywords();
        if ($excludes !== []) {
            $this->line('Exclude name keywords: '.implode(', ', array_slice($excludes, 0, 8)).(count($excludes) > 8 ? '…' : ''));
        }

        $sample = (int) $this->option('sample');
        if ($sample > 0) {
            $this->newLine();
            $this->info('Sample products in feed:');

            $rows = (clone $filtered)
                ->with(['defaultImage', 'category'])
                ->orderBy('id')
                ->limit($sample)
                ->get();

            foreach ($rows as $product) {
                $image = $feedService->resolvePublicImageUrl($product);
                $this->line(sprintf(
                    '- #%d %s [%s] image=%s',
                    $product->id,
                    mb_substr((string) $product->name, 0, 60),
                    $product->category?->full_slug ?? 'no-category',
                    $image !== null ? 'yes' : 'MISSING',
                ));
            }
        }

        $this->newLine();
        $this->comment('If Commerce Manager shows fewer items or no images, re-upload the feed and ensure image URLs are public HTTPS (images.bnc.ba or api.bnc.ba).');

        return self::SUCCESS;
    }
}

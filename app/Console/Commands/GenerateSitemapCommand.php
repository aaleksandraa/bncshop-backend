<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\SystemSetting;
use Illuminate\Console\Command;

class GenerateSitemapCommand extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Generate and cache sitemap entries for the storefront';

    public function handle(): int
    {
        $baseUrl = rtrim(config('bnc.frontend_url'), '/');

        $entries = [];

        Category::query()
            ->active()
            ->orderBy('full_slug')
            ->get(['full_slug', 'updated_at'])
            ->each(function (Category $category) use (&$entries, $baseUrl): void {
                $entries[] = [
                    'loc' => "{$baseUrl}/kategorije/{$category->full_slug}",
                    'lastmod' => $category->updated_at?->toAtomString(),
                    'type' => 'category',
                ];
            });

        Manufacturer::query()
            ->orderBy('slug')
            ->get(['slug', 'updated_at'])
            ->each(function (Manufacturer $manufacturer) use (&$entries, $baseUrl): void {
                $entries[] = [
                    'loc' => "{$baseUrl}/brendovi/{$manufacturer->slug}",
                    'lastmod' => $manufacturer->updated_at?->toAtomString(),
                    'type' => 'manufacturer',
                ];
            });

        Product::query()
            ->public()
            ->active()
            ->orderBy('slug')
            ->chunk(500, function ($products) use (&$entries, $baseUrl): void {
                foreach ($products as $product) {
                    $entries[] = [
                        'loc' => "{$baseUrl}/proizvodi/{$product->slug}",
                        'lastmod' => $product->updated_at?->toAtomString(),
                        'type' => 'product',
                    ];
                }
            });

        SystemSetting::query()->updateOrCreate(
            ['key' => 'sitemap_cache'],
            [
                'value' => [
                    'generated_at' => now()->toIso8601String(),
                    'entries' => $entries,
                ],
                'group' => 'seo',
            ]
        );

        $this->info('Sitemap generated with '.count($entries).' entries.');

        return self::SUCCESS;
    }
}

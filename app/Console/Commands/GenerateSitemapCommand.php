<?php

namespace App\Console\Commands;

use App\Models\BlogPost;
use App\Models\Category;
use App\Models\CmsPage;
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
        $baseUrl = rtrim((string) config('bnc.frontend_url'), '/');
        $now = now()->toAtomString();

        $entries = [
            $this->entry("{$baseUrl}/", $now, 'home', 'daily', 1.0),
            $this->entry("{$baseUrl}/kategorije", $now, 'static', 'weekly', 0.9),
            $this->entry("{$baseUrl}/brendovi", $now, 'static', 'weekly', 0.8),
            $this->entry("{$baseUrl}/akcija", $now, 'static', 'daily', 0.8),
            $this->entry("{$baseUrl}/novo", $now, 'static', 'daily', 0.8),
            $this->entry("{$baseUrl}/refurbished", $now, 'static', 'weekly', 0.7),
            $this->entry("{$baseUrl}/blog", $now, 'static', 'weekly', 0.7),
            $this->entry("{$baseUrl}/kupovina-na-rate", $now, 'static', 'monthly', 0.6),
        ];

        Category::query()
            ->active()
            ->orderBy('full_slug')
            ->get(['full_slug', 'updated_at'])
            ->each(function (Category $category) use (&$entries, $baseUrl): void {
                $entries[] = $this->entry(
                    "{$baseUrl}/kategorija/{$category->full_slug}",
                    $category->updated_at?->toAtomString(),
                    'category',
                    'weekly',
                    0.8,
                );
            });

        Manufacturer::query()
            ->orderBy('slug')
            ->get(['slug', 'updated_at'])
            ->each(function (Manufacturer $manufacturer) use (&$entries, $baseUrl): void {
                $entries[] = $this->entry(
                    "{$baseUrl}/brend/{$manufacturer->slug}",
                    $manufacturer->updated_at?->toAtomString(),
                    'manufacturer',
                    'weekly',
                    0.7,
                );
            });

        Product::query()
            ->public()
            ->active()
            ->orderBy('slug')
            ->chunk(500, function ($products) use (&$entries, $baseUrl): void {
                foreach ($products as $product) {
                    $entries[] = $this->entry(
                        "{$baseUrl}/proizvod/{$product->slug}",
                        $product->updated_at?->toAtomString(),
                        'product',
                        'weekly',
                        0.7,
                    );
                }
            });

        BlogPost::query()
            ->published()
            ->orderBy('slug')
            ->get(['slug', 'updated_at', 'published_at'])
            ->each(function (BlogPost $post) use (&$entries, $baseUrl): void {
                $entries[] = $this->entry(
                    "{$baseUrl}/blog/{$post->slug}",
                    ($post->updated_at ?? $post->published_at)?->toAtomString(),
                    'blog',
                    'weekly',
                    0.6,
                );
            });

        CmsPage::query()
            ->active()
            ->orderBy('slug')
            ->get(['slug', 'updated_at'])
            ->each(function (CmsPage $page) use (&$entries, $baseUrl): void {
                $entries[] = $this->entry(
                    "{$baseUrl}/{$page->slug}",
                    $page->updated_at?->toAtomString(),
                    'page',
                    'monthly',
                    0.5,
                );
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

    /**
     * @return array{loc: string, lastmod: string|null, type: string, changefreq: string, priority: float}
     */
    private function entry(
        string $loc,
        ?string $lastmod,
        string $type,
        string $changefreq,
        float $priority,
    ): array {
        return [
            'loc' => $loc,
            'lastmod' => $lastmod,
            'type' => $type,
            'changefreq' => $changefreq,
            'priority' => $priority,
        ];
    }
}

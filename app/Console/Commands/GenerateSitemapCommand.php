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
    private const PRODUCT_CHUNK_SIZE = 10_000;

    protected $signature = 'sitemap:generate';

    protected $description = 'Generate and cache sitemap entries for the storefront';

    public function handle(): int
    {
        $baseUrl = rtrim((string) config('bnc.frontend_url'), '/');

        $pages = $this->buildPagesSection($baseUrl);
        $categories = $this->buildCategoriesSection($baseUrl);
        $productEntries = $this->buildProductEntries($baseUrl);
        $savjeti = $this->buildSavjetiSection($baseUrl);

        $productChunks = array_chunk($productEntries, self::PRODUCT_CHUNK_SIZE);
        if ($productChunks === []) {
            $productChunks = [[]];
        }

        $productSection = [
            'chunks' => array_map(
                static fn (array $chunk, int $index): array => [
                    'index' => $index + 1,
                    'entries' => $chunk,
                    'count' => count($chunk),
                ],
                $productChunks,
                array_keys($productChunks),
            ),
            'count' => count($productEntries),
            'chunk_count' => count($productChunks),
        ];

        $sections = [
            'pages' => [
                'entries' => $pages,
                'count' => count($pages),
            ],
            'categories' => [
                'entries' => $categories,
                'count' => count($categories),
            ],
            'products' => $productSection,
            'savjeti' => [
                'entries' => $savjeti,
                'count' => count($savjeti),
            ],
        ];

        $allEntries = array_merge($pages, $categories, $productEntries, $savjeti);

        SystemSetting::query()->updateOrCreate(
            ['key' => 'sitemap_cache'],
            [
                'value' => [
                    'generated_at' => now()->toIso8601String(),
                    'sections' => $sections,
                    'counts' => [
                        'pages' => count($pages),
                        'categories' => count($categories),
                        'products' => count($productEntries),
                        'savjeti' => count($savjeti),
                        'total' => count($allEntries),
                    ],
                    'entries' => $allEntries,
                ],
                'group' => 'seo',
            ]
        );

        $this->info(sprintf(
            'Sitemap generated: %d pages, %d categories, %d products (%d chunk(s)), %d savjeti (%d total).',
            count($pages),
            count($categories),
            count($productEntries),
            count($productChunks),
            count($savjeti),
            count($allEntries),
        ));

        return self::SUCCESS;
    }

    /**
     * @return list<array{loc: string, lastmod: string|null, type: string, changefreq: string, priority: float}>
     */
    private function buildPagesSection(string $baseUrl): array
    {
        $now = now()->toAtomString();

        $entries = [
            $this->entry("{$baseUrl}/", $now, 'home', 'daily', 1.0),
            $this->entry("{$baseUrl}/kategorije", $now, 'static', 'weekly', 0.9),
            $this->entry("{$baseUrl}/brendovi", $now, 'static', 'weekly', 0.8),
            $this->entry("{$baseUrl}/akcija", $now, 'static', 'daily', 0.8),
            $this->entry("{$baseUrl}/novo", $now, 'static', 'daily', 0.8),
            $this->entry("{$baseUrl}/refurbished", $now, 'static', 'weekly', 0.7),
            $this->entry("{$baseUrl}/kupovina-na-rate", $now, 'static', 'monthly', 0.6),
            $this->entry("{$baseUrl}/servis", $now, 'static', 'monthly', 0.6),
            $this->entry("{$baseUrl}/servis/privatna-lica", $now, 'static', 'monthly', 0.6),
            $this->entry("{$baseUrl}/servis/pravna-lica", $now, 'static', 'monthly', 0.6),
        ];

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

        return $entries;
    }

    /**
     * @return list<array{loc: string, lastmod: string|null, type: string, changefreq: string, priority: float}>
     */
    private function buildCategoriesSection(string $baseUrl): array
    {
        $entries = [];

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

        return $entries;
    }

    /**
     * @return list<array{loc: string, lastmod: string|null, type: string, changefreq: string, priority: float}>
     */
    private function buildProductEntries(string $baseUrl): array
    {
        $entries = [];

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

        return $entries;
    }

    /**
     * @return list<array{loc: string, lastmod: string|null, type: string, changefreq: string, priority: float}>
     */
    private function buildSavjetiSection(string $baseUrl): array
    {
        $now = now()->toAtomString();

        $entries = [
            $this->entry("{$baseUrl}/blog", $now, 'savjeti', 'weekly', 0.7),
        ];

        BlogPost::query()
            ->published()
            ->orderBy('slug')
            ->get(['slug', 'updated_at', 'published_at'])
            ->each(function (BlogPost $post) use (&$entries, $baseUrl): void {
                $entries[] = $this->entry(
                    "{$baseUrl}/blog/{$post->slug}",
                    ($post->updated_at ?? $post->published_at)?->toAtomString(),
                    'savjeti',
                    'weekly',
                    0.6,
                );
            });

        return $entries;
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

<?php

namespace App\Services\Blog;

use App\Http\Resources\ProductCardResource;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;

class BlogPostBlockResolver
{
    /**
     * @return array<string, mixed>
     */
    public function present(BlogPost $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'featured_image_url' => $post->featuredImageUrl(),
            'intro' => $post->intro,
            'content_blocks' => $this->resolveBlocks($post->content_blocks),
            'published_at' => $post->published_at,
            'author_name' => $post->author?->name,
            'meta_title' => $post->meta_title,
            'meta_description' => $post->meta_description,
            'og_image_url' => $post->ogImageUrl(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentSummary(BlogPost $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'featured_image_url' => $post->featuredImageUrl(),
            'published_at' => $post->published_at,
            'author_name' => $post->author?->name,
            'status' => $post->status,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $blocks
     * @return array<int, array<string, mixed>>
     */
    public function resolveBlocks(?array $blocks): array
    {
        if ($blocks === null || $blocks === []) {
            return [];
        }

        $productIds = [];
        $categoryIds = [];
        $manufacturerIds = [];

        foreach ($blocks as $block) {
            $type = (string) ($block['type'] ?? '');
            $data = is_array($block['data'] ?? null) ? $block['data'] : [];

            match ($type) {
                'products_showcase' => $productIds = array_merge($productIds, $this->normalizeIds($data['product_ids'] ?? [])),
                'categories_showcase' => $categoryIds = array_merge($categoryIds, $this->normalizeIds($data['category_ids'] ?? [])),
                'brands_showcase' => $manufacturerIds = array_merge($manufacturerIds, $this->normalizeIds($data['manufacturer_ids'] ?? [])),
                default => null,
            };
        }

        $products = Product::query()
            ->public()
            ->active()
            ->whereIn('id', array_values(array_unique($productIds)))
            ->with(['manufacturer', 'category', 'defaultImage'])
            ->get()
            ->keyBy('id');

        $categories = Category::query()
            ->active()
            ->whereIn('id', array_values(array_unique($categoryIds)))
            ->get()
            ->keyBy('id');

        $manufacturers = Manufacturer::query()
            ->whereIn('id', array_values(array_unique($manufacturerIds)))
            ->get()
            ->keyBy('id');

        return collect($blocks)
            ->map(fn (array $block): array => $this->resolveBlock($block, $products, $categories, $manufacturers))
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Product>  $products
     * @param  \Illuminate\Support\Collection<int, Category>  $categories
     * @param  \Illuminate\Support\Collection<int, Manufacturer>  $manufacturers
     * @return array<string, mixed>
     */
    private function resolveBlock(
        array $block,
        $products,
        $categories,
        $manufacturers,
    ): array {
        $type = (string) ($block['type'] ?? 'rich_text');
        $data = is_array($block['data'] ?? null) ? $block['data'] : [];

        return match ($type) {
            'products_showcase' => [
                'type' => $type,
                'title' => $data['title'] ?? null,
                'layout' => $data['layout'] ?? 'carousel',
                'products' => collect($this->normalizeIds($data['product_ids'] ?? []))
                    ->map(fn (int $id): ?array => ($product = $products->get($id))
                        ? (new ProductCardResource($product))->resolve()
                        : null)
                    ->filter()
                    ->values()
                    ->all(),
            ],
            'categories_showcase' => [
                'type' => $type,
                'title' => $data['title'] ?? null,
                'layout' => $data['layout'] ?? 'carousel',
                'categories' => collect($this->normalizeIds($data['category_ids'] ?? []))
                    ->map(fn (int $id): ?array => ($category = $categories->get($id)) ? [
                        'id' => $category->id,
                        'name' => $category->publicName(),
                        'full_slug' => $category->full_slug,
                        'image_url' => $category->image_url,
                        'icon_url' => $category->icon_url,
                        'short_description' => $category->short_description,
                    ] : null)
                    ->filter()
                    ->values()
                    ->all(),
            ],
            'brands_showcase' => [
                'type' => $type,
                'title' => $data['title'] ?? null,
                'layout' => $data['layout'] ?? 'carousel',
                'brands' => collect($this->normalizeIds($data['manufacturer_ids'] ?? []))
                    ->map(fn (int $id): ?array => ($brand = $manufacturers->get($id)) ? [
                        'id' => $brand->id,
                        'name' => $brand->name,
                        'slug' => $brand->slug,
                        'logo_url' => $brand->logoUrl(),
                        'description' => $brand->description,
                    ] : null)
                    ->filter()
                    ->values()
                    ->all(),
            ],
            default => [
                'type' => 'rich_text',
                'body' => $data['body'] ?? null,
            ],
        };
    }

    /**
     * @param  mixed  $ids
     * @return array<int, int>
     */
    private function normalizeIds(mixed $ids): array
    {
        if (! is_array($ids)) {
            return [];
        }

        return collect($ids)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();
    }
}

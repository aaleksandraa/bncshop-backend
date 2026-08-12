<?php

namespace App\Services\Sync;

use App\Models\Category;
use App\Models\CategorySeo;
use Illuminate\Support\Str;

class CategoryImporter
{
    /**
     * @param  array<int, array<string, mixed>>  $categories
     * @return array{created: int, updated: int, pending_parent: int}
     */
    public function upsertMany(array $categories): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'pending_parent' => 0];

        usort($categories, function (array $a, array $b): int {
            $depthA = substr_count((string) ($a['slug'] ?? ''), '/');
            $depthB = substr_count((string) ($b['slug'] ?? ''), '/');

            return $depthA <=> $depthB;
        });

        foreach ($categories as $payload) {
            $result = $this->upsertOne($payload);
            if ($result === 'pending_parent') {
                $stats['pending_parent']++;
            } else {
                $stats[$result]++;
            }
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function upsertOne(array $payload): string
    {
        $externalId = (string) ($payload['categoryId'] ?? $payload['external_category_id'] ?? '');
        $existing = Category::query()->where('external_category_id', $externalId)->first();

        $parent = $this->resolveParent($payload);
        $pendingParent = $parent === null && ! empty($payload['parentId']);

        $attributes = [
            'external_category_id' => $externalId,
            'parent_id' => $parent?->id,
            'name' => trim((string) ($payload['name'] ?? '')),
            'full_slug' => (string) ($payload['slug'] ?? Str::slug((string) ($payload['name'] ?? 'category'))),
            'description' => $payload['description'] ?? null,
            'short_description' => $payload['shortDescription'] ?? $payload['short_description'] ?? null,
            'external_id' => $payload['externalId'] ?? $payload['external_id'] ?? null,
            'external_parent_id' => $payload['parentId'] ?? $payload['external_parent_id'] ?? null,
            'depth' => $parent ? ($parent->depth + 1) : 0,
            'path' => $this->buildPath($parent, $externalId),
            'margin_id' => $payload['marginId'] ?? null,
            'margin_name' => $payload['marginName'] ?? null,
            'olx_id' => $payload['olxId'] ?? null,
            'olx_name' => $payload['olxName'] ?? null,
            'system' => (bool) ($payload['system'] ?? true),
            'pending_parent' => $pendingParent,
            'status' => 'active',
            'image_url' => $payload['imageUrl'] ?? null,
            'icon_url' => $payload['iconUrl'] ?? null,
        ];

        if (! $existing?->margin_locked) {
            $attributes['margin_percentage'] = $payload['marginPercentage'] ?? null;
        }

        if ($existing) {
            $existing->update($attributes);
            $category = $existing;
            $action = 'updated';
        } else {
            $category = Category::query()->create($attributes);
            $action = 'created';
        }

        $this->syncSeo($category, $payload);

        return $pendingParent ? 'pending_parent' : $action;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveParent(array $payload): ?Category
    {
        $parentExternalId = $payload['parentId'] ?? $payload['external_parent_id'] ?? null;

        if (! $parentExternalId) {
            return null;
        }

        return Category::query()->where('external_category_id', (string) $parentExternalId)->first();
    }

    private function buildPath(?Category $parent, string $externalId): string
    {
        if (! $parent) {
            return $externalId;
        }

        return trim(($parent->path ?? $parent->external_category_id).'/'.$externalId, '/');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncSeo(Category $category, array $payload): void
    {
        $seoData = [
            'meta_title' => $payload['metaTitle'] ?? null,
            'meta_description' => $payload['metaDescription'] ?? null,
            'og_image_url' => $payload['ogImageUrl'] ?? null,
        ];

        if ($category->seo) {
            $category->seo->update($seoData);
        } else {
            CategorySeo::query()->create(array_merge(['category_id' => $category->id], $seoData));
        }
    }
}

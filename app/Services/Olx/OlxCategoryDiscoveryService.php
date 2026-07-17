<?php

namespace App\Services\Olx;

use App\Models\OlxCategory;
use App\Models\OlxCategoryAttribute;

class OlxCategoryDiscoveryService
{
    /** @var array<int, string> */
    public const SEED_CATEGORY_NAMES = [
        'Monitori',
        'Desktop Racunari',
        'Laptopi',
        'Klima uredaji',
        'Elektricni romobili',
        'Printer',
        'Video nadzor',
        'Tastature',
        'Misevi',
        'PC slusalice',
        'Projektori',
        'Televizori TV',
        'Ventilatori',
        'Smartwatch',
        'Preciscivac',
    ];

    public function __construct(
        private readonly OlxApiClient $client,
    ) {}

    /**
     * @return array{discovered: int, categories: array<int, string>}
     */
    public function discoverCategories(?array $names = null): array
    {
        $names ??= self::SEED_CATEGORY_NAMES;
        $discovered = 0;
        $paths = [];

        foreach ($names as $name) {
            $matches = $this->client->findCategories($name);

            foreach ($matches as $match) {
                $id = (int) ($match['id'] ?? 0);

                if ($id <= 0) {
                    continue;
                }

                $detail = $this->client->getCategory($id) ?? $match;

                OlxCategory::query()->updateOrCreate(
                    ['id' => $id],
                    [
                        'name' => (string) ($detail['name'] ?? $match['name'] ?? $name),
                        'slug' => $detail['slug'] ?? null,
                        'parent_id' => isset($detail['parent_id']) ? (int) $detail['parent_id'] : null,
                        'path' => (string) ($match['path'] ?? $detail['path'] ?? ''),
                        'brand_required' => (bool) ($detail['brand_required'] ?? false),
                        'show_condition' => (bool) ($detail['show_condition'] ?? true),
                        'fetched_at' => now(),
                    ],
                );

                $paths[$id] = (string) ($match['path'] ?? $detail['name'] ?? $name);
                $discovered++;
            }
        }

        return [
            'discovered' => $discovered,
            'categories' => $paths,
        ];
    }

    /**
     * @return array{category_id: int, attributes: int}
     */
    public function discoverAttributesForCategory(int $olxCategoryId): array
    {
        $attributes = $this->client->getCategoryAttributes($olxCategoryId);
        $count = 0;

        foreach ($attributes as $attribute) {
            $attrId = (int) ($attribute['id'] ?? 0);

            if ($attrId <= 0) {
                continue;
            }

            OlxCategoryAttribute::query()->updateOrCreate(
                [
                    'olx_category_id' => $olxCategoryId,
                    'olx_attribute_id' => $attrId,
                ],
                [
                    'name' => $attribute['name'] ?? null,
                    'display_name' => $attribute['display_name'] ?? null,
                    'input_type' => $attribute['input_type'] ?? null,
                    'required' => (bool) ($attribute['required'] ?? false),
                    'options_json' => $attribute['options'] ?? null,
                    'fetched_at' => now(),
                ],
            );

            $count++;
        }

        return [
            'category_id' => $olxCategoryId,
            'attributes' => $count,
        ];
    }

    /**
     * @param  array<int>|null  $categoryIds
     * @return array<int, array{category_id: int, attributes: int}>
     */
    public function discoverAllAttributes(?array $categoryIds = null): array
    {
        $categoryIds ??= OlxCategory::query()->pluck('id')->all();
        $results = [];

        foreach ($categoryIds as $categoryId) {
            $results[] = $this->discoverAttributesForCategory((int) $categoryId);
        }

        return $results;
    }
}

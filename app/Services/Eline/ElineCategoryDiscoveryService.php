<?php

namespace App\Services\Eline;

use App\Models\ElineCategory;
use App\Models\ElineCategoryMapping;
use Illuminate\Support\Collection;

class ElineCategoryDiscoveryService
{
    public function __construct(
        private readonly ElineApiClient $client,
    ) {}

    /**
     * @return array{categories: int, mappings_created: int}
     */
    public function discover(): array
    {
        $artikli = $this->client->fetchArtikli();
        $counts = $this->countCategories($artikli);
        $mappingsCreated = 0;
        $now = now();

        foreach ($counts as $name => $productCount) {
            $category = ElineCategory::query()->updateOrCreate(
                ['name' => $name],
                [
                    'product_count' => $productCount,
                    'last_seen_at' => $now,
                ],
            );

            $mapping = ElineCategoryMapping::query()->firstOrCreate(
                ['eline_category_id' => $category->id],
                [
                    'is_enabled' => false,
                    'product_condition' => ElineSupport::inferCondition($name)
                        ?? ElineCategoryMapping::CONDITION_REFURBISHED,
                ],
            );

            if ($mapping->wasRecentlyCreated) {
                $mappingsCreated++;
            }
        }

        return [
            'categories' => count($counts),
            'mappings_created' => $mappingsCreated,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $artikli
     * @return array<string, int>
     */
    private function countCategories(array $artikli): array
    {
        $counts = [];

        foreach ($artikli as $article) {
            $name = ElineSupport::resolveCategoryName($article);

            if ($name === '') {
                continue;
            }

            $counts[$name] = ($counts[$name] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }

    /**
     * @return Collection<string, ElineCategoryMapping>
     */
    public function enabledMappingsByCategoryName(): Collection
    {
        return ElineCategoryMapping::query()
            ->with(['elineCategory', 'category'])
            ->where('is_enabled', true)
            ->whereNotNull('category_id')
            ->get()
            ->keyBy(fn (ElineCategoryMapping $mapping): string => (string) $mapping->elineCategory?->name);
    }
}

<?php

namespace App\Services\Ananas;

use App\Models\AnanasCategoryMapping;
use App\Models\Category;
use App\Support\CategoryAdminSearch;

class AnanasCategoryMappingProposer
{
    public function __construct(
        private readonly AnanasExportScope $exportScope,
        private readonly AnanasValidatedMappingService $mappingService,
    ) {}

    /**
     * @return array{
     *     suggestions: list<array<string, mixed>>,
     *     unmatched: list<array<string, mixed>>,
     *     skipped_covered: int,
     *     ananas_names: int
     * }
     */
    public function propose(
        int $minProducts = 1,
        int $minScore = 88,
        bool $refreshTypes = false,
    ): array {
        unset($refreshTypes);

        $templates = array_fill_keys(
            array_map([$this, 'normalize'], config('bnc.ananas_product_type_templates', [])),
            true,
        );
        $catalog = config('bnc.ananas_category_catalog', []);
        $aliases = config('bnc.ananas_category_aliases', []);

        $leafNames = [];
        foreach ($catalog as $type => $names) {
            if (! is_array($names)) {
                continue;
            }
            foreach ($names as $name) {
                if (is_string($name) && trim($name) !== '') {
                    $leafNames[] = ['product_type' => (string) $type, 'category' => trim($name)];
                }
            }
        }

        $covered = array_fill_keys($this->exportScope->scopedCategoryIds(), true);
        $alreadyMappedSet = array_fill_keys(
            array_map('intval', AnanasCategoryMapping::query()->pluck('category_id')->all()),
            true,
        );

        $suggestions = [];
        $unmatched = [];
        $skippedCovered = 0;

        $categories = Category::query()
            ->with('parent')
            ->withCount([
                'products as active_public_count' => static function ($query): void {
                    $query->where('is_public', true)->where('status', 'active');
                },
            ])
            ->orderBy('path')
            ->get();

        foreach ($categories as $category) {
            if (! $category instanceof Category) {
                continue;
            }

            $id = (int) $category->id;
            $products = (int) ($category->active_public_count ?? 0);

            if (isset($covered[$id])) {
                $skippedCovered++;

                continue;
            }

            if (isset($alreadyMappedSet[$id])) {
                continue;
            }

            if ($products < $minProducts) {
                continue;
            }

            $productType = $this->guessProductType($category);
            $match = $this->bestLeafMatch($category, $leafNames, $aliases, $templates);

            $row = [
                'category_id' => $id,
                'bnc_category' => CategoryAdminSearch::formatOptionLabel($category),
                'products' => $products,
                'product_type' => $match['product_type'] ?? $productType,
                'ananas_category' => $match['category'],
                'score' => $match['score'],
            ];

            if ($match['category'] !== null && $match['score'] >= $minScore) {
                $suggestions[] = $row;
            } else {
                $unmatched[] = $row;
            }
        }

        usort($suggestions, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        usort($unmatched, static fn (array $a, array $b): int => $b['products'] <=> $a['products']);

        return [
            'suggestions' => $suggestions,
            'unmatched' => $unmatched,
            'skipped_covered' => $skippedCovered,
            'ananas_names' => count($leafNames),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $suggestions
     * @return array{created: int, skipped: int, mappings: list<AnanasCategoryMapping>}
     */
    public function applySuggestions(
        array $suggestions,
        bool $enableExact = false,
        string $productType = 'ITShop',
        int $exactScore = 95,
    ): array {
        $created = 0;
        $skipped = 0;
        $mappings = [];

        foreach ($suggestions as $row) {
            $categoryId = (int) ($row['category_id'] ?? 0);
            $ananasCategory = trim((string) ($row['ananas_category'] ?? ''));
            $type = trim((string) ($row['product_type'] ?? $productType));
            $score = (int) ($row['score'] ?? 0);

            if ($categoryId <= 0 || $ananasCategory === '' || $type === '') {
                $skipped++;

                continue;
            }

            if (AnanasCategoryMapping::query()->where('category_id', $categoryId)->exists()) {
                $skipped++;

                continue;
            }

            $enable = $enableExact && $score >= $exactScore;

            $mappings[] = $this->mappingService->upsert(
                categoryId: $categoryId,
                productType: $type,
                ananasCategory: $ananasCategory,
                enabled: $enable,
                includeDescendants: true,
                validationStatus: $enable
                    ? AnanasCategoryMapping::VALIDATION_PENDING
                    : AnanasCategoryMapping::VALIDATION_UNKNOWN,
                observedCategories: [$ananasCategory],
                notes: sprintf(
                    'Predloženo iz Ananas category catalog (score %d, productType %s). Uključiti tek kad je leaf string tačan.',
                    $score,
                    $type,
                ),
            );
            $created++;
        }

        $this->exportScope->flushCaches();

        return compact('created', 'skipped', 'mappings');
    }

    /**
     * @return array{deleted: int, ids: list<int>}
     */
    public function pruneUnvalidatedProposals(): array
    {
        $ids = AnanasCategoryMapping::query()
            ->where('is_enabled', false)
            ->where('category_validation_status', '!=', AnanasCategoryMapping::VALIDATION_VALIDATED)
            ->where('category_validation_notes', 'like', 'Predloženo iz%')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $deleted = AnanasCategoryMapping::query()->whereIn('id', $ids)->delete();
        $this->exportScope->flushCaches();

        return ['deleted' => (int) $deleted, 'ids' => $ids];
    }

    /**
     * @param  list<array{product_type: string, category: string}>  $leafNames
     * @param  array<string, string>  $aliases
     * @param  array<string, true>  $templates
     * @return array{category: string|null, product_type: string|null, score: int}
     */
    public function bestLeafMatch(Category $category, array $leafNames, array $aliases, array $templates): array
    {
        $bnc = $this->normalize((string) $category->publicName());
        $productType = $this->guessProductType($category);

        if ($bnc === '') {
            return ['category' => null, 'product_type' => $productType, 'score' => 0];
        }

        $aliasKey = $this->normalize((string) $category->publicName());
        foreach ($aliases as $from => $to) {
            if ($this->normalize((string) $from) === $aliasKey && is_string($to) && $to !== '') {
                return ['category' => $to, 'product_type' => $productType, 'score' => 96];
            }
        }

        $bestName = null;
        $bestType = $productType;
        $bestScore = 0;

        foreach ($leafNames as $leaf) {
            $name = $leaf['category'];
            $normalizedLeaf = $this->normalize($name);

            if ($normalizedLeaf === '' || isset($templates[$normalizedLeaf])) {
                continue;
            }

            $score = $this->score($bnc, $normalizedLeaf);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestName = $name;
                $bestType = $leaf['product_type'] !== '' ? $leaf['product_type'] : $productType;
            }
        }

        return ['category' => $bestName, 'product_type' => $bestType, 'score' => $bestScore];
    }

    public function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, [
            'š' => 's', 'đ' => 'd', 'č' => 'c', 'ć' => 'c', 'ž' => 'z',
            'ä' => 'a', 'ö' => 'o', 'ü' => 'u',
        ]);

        return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
    }

    public function guessProductType(Category $category): string
    {
        $haystack = $this->normalize(
            $category->publicName().' '.($category->parent?->publicName() ?? '').' '.($category->path ?? ''),
        );

        $default = (string) config('bnc.ananas_mapping_default_product_type', 'ITShop');

        if (str_contains($haystack, 'bijelatehnika') || str_contains($haystack, 'sporet')) {
            return 'Aparati';
        }

        return $default;
    }

    private function score(string $bnc, string $ananas): int
    {
        if ($bnc === '' || $ananas === '') {
            return 0;
        }

        if ($bnc === $ananas) {
            return 100;
        }

        similar_text($bnc, $ananas, $percent);

        return (int) round($percent);
    }
}

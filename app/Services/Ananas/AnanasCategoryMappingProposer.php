<?php

namespace App\Services\Ananas;

use App\Models\AnanasCategoryMapping;
use App\Models\AnanasProductType;
use App\Models\Category;
use App\Support\CategoryAdminSearch;

class AnanasCategoryMappingProposer
{
    public function __construct(
        private readonly AnanasExportScope $exportScope,
        private readonly AnanasValidatedMappingService $mappingService,
        private readonly AnanasProductTypeSyncService $productTypeSync,
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
        int $minScore = 82,
        bool $refreshTypes = false,
    ): array {
        if ($refreshTypes) {
            $this->productTypeSync->refreshFromApi();
        }

        $ananasNames = AnanasProductType::query()
            ->orderBy('name')
            ->pluck('name')
            ->all();

        $covered = array_fill_keys($this->exportScope->scopedCategoryIds(), true);
        $alreadyMapped = AnanasCategoryMapping::query()->pluck('category_id')->all();
        $alreadyMappedSet = array_fill_keys(array_map('intval', $alreadyMapped), true);

        $suggestions = [];
        $unmatched = [];
        $skippedCovered = 0;

        $categories = Category::query()
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

            $match = $this->bestMatch($category, $ananasNames);

            $row = [
                'category_id' => $id,
                'bnc_category' => CategoryAdminSearch::formatOptionLabel($category),
                'products' => $products,
                'ananas_category' => $match['name'],
                'score' => $match['score'],
            ];

            if ($match['name'] !== null && $match['score'] >= $minScore) {
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
            'ananas_names' => count($ananasNames),
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
            $score = (int) ($row['score'] ?? 0);

            if ($categoryId <= 0 || $ananasCategory === '') {
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
                productType: $productType,
                ananasCategory: $ananasCategory,
                enabled: $enable,
                includeDescendants: true,
                validationStatus: $enable
                    ? AnanasCategoryMapping::VALIDATION_PENDING
                    : AnanasCategoryMapping::VALIDATION_UNKNOWN,
                observedCategories: [$ananasCategory],
                notes: sprintf(
                    'Predloženo iz GET product-type (score %d). Uključiti tek nakon provjere stringa — ne slati nagađanje na live import.',
                    $score,
                ),
            );
            $created++;
        }

        $this->exportScope->flushCaches();

        return compact('created', 'skipped', 'mappings');
    }

    /**
     * @param  list<string>  $ananasNames
     * @return array{name: string|null, score: int}
     */
    public function bestMatch(Category $category, array $ananasNames): array
    {
        $bnc = $this->normalize((string) $category->publicName());

        if ($bnc === '' || $ananasNames === []) {
            return ['name' => null, 'score' => 0];
        }

        $bestName = null;
        $bestScore = 0;

        foreach ($ananasNames as $name) {
            if (! is_string($name) || trim($name) === '') {
                continue;
            }

            $score = $this->score($bnc, $this->normalize($name));

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestName = $name;
            }
        }

        return ['name' => $bestName, 'score' => $bestScore];
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

    private function score(string $bnc, string $ananas): int
    {
        if ($bnc === '' || $ananas === '') {
            return 0;
        }

        if ($bnc === $ananas) {
            return 100;
        }

        similar_text($bnc, $ananas, $percent);

        $percent = (int) round($percent);

        if (str_contains($ananas, $bnc) && strlen($bnc) >= 6) {
            $percent = max($percent, 88);
        }

        return $percent;
    }
}

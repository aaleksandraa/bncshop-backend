<?php

namespace App\Services\Search;

use App\Models\Category;
use App\Services\Catalog\ProductReadCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CategorySearchService
{
    /**
     * @var array<int, array<int, string>>
     */
    private const SYNONYM_GROUPS = [
        ['polovni', 'polovna', 'polovno', 'rabljeno', 'rabljena', 'refurbished', 'obnovljeni', 'obnovljena', 'obnovljeno'],
        ['novi', 'novo', 'nova', 'new'],
        ['monitor', 'monitori', 'monitore', 'monitors'],
        ['laptop', 'laptopi', 'notebook', 'notebooks'],
        ['telefon', 'telefoni', 'mobitel', 'mobiteli', 'smartphone'],
        ['racunar', 'racunari', 'pc', 'desktop'],
        ['stampac', 'stampaci', 'printer', 'printeri'],
        ['televizor', 'televizori', 'tv'],
        ['klima', 'klime', 'klimatizacija'],
    ];

    public function __construct(
        private readonly ProductReadCache $productReadCache,
    ) {}

    /**
     * @return array<int, array{id: int, name: string, full_slug: string, breadcrumb: string|null}>
     */
    public function search(string $query, int $limit = 5): array
    {
        $normalizedQuery = $this->normalize($query);

        if ($normalizedQuery === '') {
            return [];
        }

        $categories = $this->loadCategories();
        $byId = $categories->keyBy('id');
        $tokens = $this->expandTokens($this->tokenize($normalizedQuery));

        $scored = $categories
            ->map(function (Category $category) use ($normalizedQuery, $tokens): array {
                return [
                    'category' => $category,
                    'score' => $this->scoreCategory($category, $normalizedQuery, $tokens),
                ];
            })
            ->filter(fn (array $row): bool => $row['score'] > 0)
            ->sort(function (array $left, array $right): int {
                if ($left['score'] !== $right['score']) {
                    return $right['score'] <=> $left['score'];
                }

                $leftDepth = (int) ($left['category']->depth ?? 0);
                $rightDepth = (int) ($right['category']->depth ?? 0);

                if ($leftDepth !== $rightDepth) {
                    return $rightDepth <=> $leftDepth;
                }

                return strnatcasecmp($left['category']->publicName(), $right['category']->publicName());
            })
            ->take(max(1, min($limit, 12)))
            ->values();

        return $scored
            ->map(function (array $row) use ($byId): array {
                /** @var Category $category */
                $category = $row['category'];

                return [
                    'id' => (int) $category->id,
                    'name' => $category->publicName(),
                    'full_slug' => $category->full_slug,
                    'breadcrumb' => $this->buildBreadcrumb($category, $byId),
                ];
            })
            ->all();
    }

    /**
     * @return Collection<int, Category>
     */
    private function loadCategories(): Collection
    {
        return $this->productReadCache->rememberCategoryNav(300, function (): Collection {
            return Category::query()
                ->active()
                ->select([
                    'id',
                    'name',
                    'display_name',
                    'full_slug',
                    'parent_id',
                    'depth',
                    'path',
                ])
                ->orderBy('path')
                ->get();
        });
    }

    /**
     * @param  Collection<int, Category>  $byId
     */
    private function buildBreadcrumb(Category $category, Collection $byId): ?string
    {
        $parts = [];
        $parentId = $category->parent_id;

        while ($parentId !== null) {
            $parent = $byId->get((int) $parentId);

            if ($parent === null) {
                break;
            }

            array_unshift($parts, $parent->publicName());
            $parentId = $parent->parent_id;
        }

        if ($parts === []) {
            return null;
        }

        return implode(' › ', $parts);
    }

    /**
     * @param  array<int, string>  $tokens
     */
    private function scoreCategory(Category $category, string $normalizedQuery, array $tokens): int
    {
        $name = $this->normalize($category->publicName());
        $rawName = $this->normalize($category->name);
        $slug = $this->normalize(str_replace(['/', '-'], ' ', $category->full_slug));
        $haystack = trim($name.' '.$rawName.' '.$slug);

        if ($haystack === '') {
            return 0;
        }

        if ($name === $normalizedQuery) {
            return 1000;
        }

        if (str_contains($name, $normalizedQuery)) {
            return 900;
        }

        if (str_contains($slug, $normalizedQuery)) {
            return 850;
        }

        $matchedTokens = 0;

        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }

            if (
                str_contains($haystack, $token)
                || ($token !== $normalizedQuery && str_contains($normalizedQuery, $token) && str_contains($name, $token))
            ) {
                $matchedTokens++;
            }
        }

        if ($matchedTokens === 0) {
            return 0;
        }

        $score = 400 + ($matchedTokens * 80);

        if ($matchedTokens === count($tokens)) {
            $score += 200;
        }

        if (str_contains($name, $normalizedQuery) || str_contains($slug, str_replace(' ', '', $normalizedQuery))) {
            $score += 100;
        }

        return $score;
    }

    /**
     * @return array<int, string>
     */
    private function tokenize(string $normalizedQuery): array
    {
        $parts = preg_split('/[\s\-\/]+/u', $normalizedQuery, -1, PREG_SPLIT_NO_EMPTY);

        return is_array($parts) ? array_values(array_filter($parts)) : [];
    }

    /**
     * @param  array<int, string>  $tokens
     * @return array<int, string>
     */
    private function expandTokens(array $tokens): array
    {
        $expanded = [];

        foreach ($tokens as $token) {
            $expanded[] = $token;

            foreach (self::SYNONYM_GROUPS as $group) {
                $normalizedGroup = array_map(fn (string $term): string => $this->normalize($term), $group);

                if (! in_array($token, $normalizedGroup, true)) {
                    continue;
                }

                foreach ($normalizedGroup as $synonym) {
                    $expanded[] = $synonym;
                }
            }
        }

        return array_values(array_unique(array_filter($expanded)));
    }

    private function normalize(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9\s\-\/]+/', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
    }
}

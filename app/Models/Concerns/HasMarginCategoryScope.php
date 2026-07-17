<?php

namespace App\Models\Concerns;

use App\Models\Category;
use App\Support\Catalog\CategoryScopeResolver;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasMarginCategoryScope
{
    abstract protected function marginTargetPivotTable(): string;

    public function targetCategories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            $this->marginTargetPivotTable(),
            $this->getForeignKey(),
            'category_id',
        )->withTimestamps();
    }

    public function appliesToCategoryId(int $categoryId): bool
    {
        return in_array($categoryId, $this->resolvedTargetCategoryIds(), true);
    }

    /**
     * @return array<int, int>
     */
    public function resolvedTargetCategoryIds(): array
    {
        return match ($this->subcategory_scope) {
            'all_descendants' => CategoryScopeResolver::descendantIds((int) $this->category_id, includeSelf: true),
            'selected' => array_values(array_unique(array_filter(array_merge(
                $this->include_parent_category ? [(int) $this->category_id] : [],
                $this->targetCategoryIds(),
            )))),
            default => [(int) $this->category_id],
        };
    }

    public function scopeSummaryLabel(): string
    {
        return match ($this->subcategory_scope) {
            'all_descendants' => 'Sve podkategorije',
            'selected' => 'Odabrane podkategorije ('.count($this->targetCategoryIds()).')',
            default => 'Samo ova kategorija',
        };
    }

    /**
     * @return array<int, int>
     */
    private function targetCategoryIds(): array
    {
        if ($this->subcategory_scope !== 'selected') {
            return [];
        }

        if ($this->relationLoaded('targetCategories')) {
            return $this->targetCategories->pluck('id')->map(fn ($id): int => (int) $id)->all();
        }

        return $this->targetCategories()->pluck('categories.id')->map(fn ($id): int => (int) $id)->all();
    }
}

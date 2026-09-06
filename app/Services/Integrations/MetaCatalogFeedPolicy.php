<?php

namespace App\Services\Integrations;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

class MetaCatalogFeedPolicy
{
    public function __construct(
        private readonly TrackingSettings $trackingSettings,
    ) {}

    /**
     * @return list<string>
     */
    public function includeCategorySlugs(): array
    {
        $configured = $this->parseList(
            (string) ($this->trackingSettings->all()['fb_catalog_include_category_slugs'] ?? ''),
        );

        if ($configured !== []) {
            return $configured;
        }

        return $this->parseList((string) config('bnc.meta_catalog.default_include_category_slugs', ''));
    }

    /**
     * @return list<string>
     */
    public function excludeCategorySlugs(): array
    {
        return $this->parseList(
            (string) ($this->trackingSettings->all()['fb_catalog_exclude_category_slugs'] ?? ''),
        );
    }

    /**
     * @return list<string>
     */
    public function excludeNameKeywords(): array
    {
        $configured = $this->parseList(
            (string) ($this->trackingSettings->all()['fb_catalog_exclude_name_keywords'] ?? ''),
        );

        $defaults = $this->parseList((string) config('bnc.meta_catalog.default_exclude_name_keywords', ''));

        return array_values(array_unique([...$configured, ...$defaults]));
    }

    public function applyToQuery(Builder $query): Builder
    {
        $includes = $this->includeCategorySlugs();

        if ($includes !== []) {
            $query->whereHas('category', function (Builder $category) use ($includes): void {
                $category->where(function (Builder $group) use ($includes): void {
                    foreach ($includes as $slug) {
                        $group
                            ->orWhere('full_slug', $slug)
                            ->orWhere('full_slug', 'like', $slug.'/%');
                    }
                });
            });
        }

        $excludes = $this->excludeCategorySlugs();
        if ($excludes !== []) {
            $query->whereDoesntHave('category', function (Builder $category) use ($excludes): void {
                $category->where(function (Builder $group) use ($excludes): void {
                    foreach ($excludes as $slug) {
                        $group
                            ->orWhere('full_slug', $slug)
                            ->orWhere('full_slug', 'like', $slug.'/%');
                    }
                });
            });
        }

        foreach ($this->excludeNameKeywords() as $keyword) {
            $needle = mb_strtolower($keyword);
            $query->whereRaw('LOWER(name) NOT LIKE ?', ['%'.$needle.'%']);
        }

        return $query;
    }

    public function resolveCondition(Product $product): string
    {
        return $product->is_refurbished ? 'refurbished' : 'new';
    }

    /**
     * @return list<string>
     */
    private function parseList(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        $parts = preg_split('/[\r\n,]+/', $value) ?: [];

        return array_values(array_filter(array_map(
            static fn (string $part): string => trim($part),
            $parts,
        ), static fn (string $part): bool => $part !== ''));
    }
}

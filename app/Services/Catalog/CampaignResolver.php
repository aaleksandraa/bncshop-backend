<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ShopCampaign;
use App\Support\PublicStorageUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CampaignResolver
{
    private const CACHE_KEY = 'shop_campaigns:active:v1';

    private const CACHE_TTL = 60;

    /** @var Collection<int, ShopCampaign>|null */
    private ?Collection $activeCampaigns = null;

    public function __construct(
        private readonly CategoryScopeResolver $categoryScopeResolver,
    ) {}

    /**
     * @return Collection<int, ShopCampaign>
     */
    public function activeCampaigns(): Collection
    {
        if ($this->activeCampaigns !== null) {
            return $this->activeCampaigns;
        }

        $this->activeCampaigns = Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL,
            fn (): Collection => ShopCampaign::query()
                ->with(['categories:id', 'products:id', 'excludedProducts:id'])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->filter(fn (ShopCampaign $campaign): bool => $campaign->isCurrentlyActive())
                ->values(),
        );

        return $this->activeCampaigns;
    }

    public function invalidateCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->activeCampaigns = null;
    }

    public function findActiveLandingBySlug(string $slug): ?ShopCampaign
    {
        return $this->activeCampaigns()->first(
            fn (ShopCampaign $campaign): bool => $campaign->slug === $slug && $campaign->has_landing_page,
        );
    }

    public function findBySlug(string $slug): ?ShopCampaign
    {
        return ShopCampaign::query()
            ->with(['categories:id', 'products:id', 'excludedProducts:id'])
            ->where('slug', $slug)
            ->first();
    }

    public function matches(Product $product, ShopCampaign $campaign): bool
    {
        if ($campaign->excludedProducts->contains('id', $product->id)) {
            return false;
        }

        if ($campaign->targeting_mode === ShopCampaign::TARGETING_PRODUCTS) {
            return $campaign->products->contains('id', $product->id);
        }

        $categoryIds = $campaign->categories->pluck('id')->all();

        return $this->categoryScopeResolver->matchesAnyCategory(
            $product,
            $categoryIds,
            $campaign->include_subcategories,
        );
    }

    /**
     * @return list<array{slug: string, name: string, image_url: string|null, landing_path: string|null, alt: string}>
     */
    public function badgesForProduct(Product $product): array
    {
        return $this->activeCampaigns()
            ->filter(fn (ShopCampaign $campaign): bool => $this->matches($product, $campaign))
            ->take(2)
            ->map(fn (ShopCampaign $campaign): array => $this->badgePayload($campaign))
            ->values()
            ->all();
    }

    /**
     * @return array{slug: string, name: string, image_url: string|null, landing_path: string|null, alt: string}
     */
    public function badgePayload(ShopCampaign $campaign): array
    {
        return [
            'slug' => $campaign->slug,
            'name' => $campaign->name,
            'image_url' => PublicStorageUrl::url($campaign->badge_path),
            'landing_path' => $campaign->has_landing_page ? '/'.$campaign->slug : null,
            'alt' => $campaign->badge_alt ?: $campaign->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function landingPayload(ShopCampaign $campaign): array
    {
        return [
            'slug' => $campaign->slug,
            'name' => $campaign->name,
            'title' => $campaign->publicPageTitle(),
            'description' => $campaign->page_description,
            'hero_image_url' => PublicStorageUrl::url($campaign->hero_image_path),
            'meta_title' => $campaign->meta_title,
            'meta_description' => $campaign->meta_description,
        ];
    }

    public function applyListingFilter(Builder $query, string $slug): void
    {
        $campaign = $this->findBySlug($slug);

        if ($campaign === null || ! $campaign->isCurrentlyActive()) {
            $query->whereRaw('0 = 1');

            return;
        }

        $excludedIds = $campaign->excludedProducts->pluck('id')->all();

        if ($campaign->targeting_mode === ShopCampaign::TARGETING_PRODUCTS) {
            $productIds = $campaign->products->pluck('id')->all();

            if ($productIds === []) {
                $query->whereRaw('0 = 1');

                return;
            }

            $query->whereIn('products.id', $productIds);
        } else {
            $categoryIds = $campaign->categories->pluck('id')->all();

            if ($categoryIds === []) {
                $query->whereRaw('0 = 1');

                return;
            }

            $expandedIds = $campaign->include_subcategories
                ? $this->categoryScopeResolver->expandWithDescendants($categoryIds)
                : $categoryIds;

            $query->whereIn('products.category_id', $expandedIds);
        }

        if ($excludedIds !== []) {
            $query->whereNotIn('products.id', $excludedIds);
        }
    }
}

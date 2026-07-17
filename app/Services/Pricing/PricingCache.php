<?php

namespace App\Services\Pricing;

use Illuminate\Support\Facades\Cache;

class PricingCache
{
    private const TAG = 'pricing';

    private const ACTIVE_DISCOUNTS_KEY = 'pricing:active_discounts';

    private const CATEGORY_TREE_KEY = 'pricing:category_tree';

    private const TTL_SECONDS = 300;

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function rememberActiveDiscounts(callable $callback): mixed
    {
        return $this->tagged()->remember(self::ACTIVE_DISCOUNTS_KEY, self::TTL_SECONDS, $callback);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function rememberCategoryTree(callable $callback): mixed
    {
        return $this->tagged()->remember(self::CATEGORY_TREE_KEY, self::TTL_SECONDS, $callback);
    }

    public function flush(): void
    {
        if ($this->supportsTags()) {
            Cache::tags([self::TAG])->flush();

            return;
        }

        Cache::forget(self::ACTIVE_DISCOUNTS_KEY);
        Cache::forget(self::CATEGORY_TREE_KEY);
    }

    private function tagged(): \Illuminate\Cache\TaggedCache|\Illuminate\Contracts\Cache\Repository
    {
        if ($this->supportsTags()) {
            return Cache::tags([self::TAG]);
        }

        return Cache::store();
    }

    private function supportsTags(): bool
    {
        return method_exists(Cache::store(), 'tags');
    }
}

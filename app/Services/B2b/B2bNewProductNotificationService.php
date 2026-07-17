<?php

namespace App\Services\B2b;

use App\Jobs\SendB2bNewProductDigestJob;
use App\Models\B2bProduct;
use App\Models\B2bSetting;
use Illuminate\Support\Facades\Cache;

class B2bNewProductNotificationService
{
    public const DIGEST_CACHE_KEY = 'b2b_new_product_digest_ids';

    public const DIGEST_LOCK_KEY = 'b2b_new_product_digest_lock';

    public function handleProductSaved(B2bProduct $product): void
    {
        if (! $this->shouldNotify($product)) {
            return;
        }

        $this->appendToDigest($product->id);
        $this->dispatchDigestJobIfNeeded();
    }

    public function shouldNotify(B2bProduct $product): bool
    {
        if (! B2bSetting::instance()->notify_customers_on_new_product) {
            return false;
        }

        if (! $product->is_active) {
            return false;
        }

        if ($product->wasRecentlyCreated) {
            return true;
        }

        return array_key_exists('is_active', $product->getChanges()) && $product->is_active;
    }

    public function appendToDigest(int $productId): void
    {
        Cache::lock(self::DIGEST_LOCK_KEY, 10)->block(5, function () use ($productId): void {
            /** @var array<int, int> $ids */
            $ids = Cache::get(self::DIGEST_CACHE_KEY, []);
            $ids[] = $productId;

            Cache::put(
                self::DIGEST_CACHE_KEY,
                array_values(array_unique($ids)),
                now()->addHours(2),
            );
        });
    }

    public function dispatchDigestJobIfNeeded(): void
    {
        $delayMinutes = max((int) config('b2b.new_product_digest_minutes', 5), 1);

        SendB2bNewProductDigestJob::dispatch()->delay(now()->addMinutes($delayMinutes));
    }

    /**
     * @return array<int, int>
     */
    public function pullDigestProductIds(): array
    {
        /** @var array<int, int> $ids */
        $ids = Cache::pull(self::DIGEST_CACHE_KEY, []);

        return array_values(array_unique(array_map('intval', $ids)));
    }
}

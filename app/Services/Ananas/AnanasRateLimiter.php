<?php

namespace App\Services\Ananas;

use Illuminate\Support\Facades\Cache;

class AnanasRateLimiter
{
    public const CATEGORY_PRODUCTS = 'products';

    public const CATEGORY_WAREHOUSES = 'warehouses';

    /**
     * Enforce Ananas documented limits before an outbound request.
     *
     * Products: 5 rps, 60 rpm. Warehouses: 5 rps, 300 rpm.
     */
    public function throttle(string $category = self::CATEGORY_PRODUCTS): void
    {
        $limits = match ($category) {
            self::CATEGORY_WAREHOUSES => [
                'rps' => (int) config('bnc.ananas_rate_limit_warehouses_rps', 5),
                'rpm' => (int) config('bnc.ananas_rate_limit_warehouses_rpm', 300),
            ],
            default => [
                'rps' => (int) config('bnc.ananas_rate_limit_products_rps', 5),
                'rpm' => (int) config('bnc.ananas_rate_limit_products_rpm', 60),
            ],
        };

        $this->enforcePerSecond($category, max(1, $limits['rps']));
        $this->enforcePerMinute($category, max(1, $limits['rpm']));
    }

    private function enforcePerSecond(string $category, int $perSecondLimit): void
    {
        $key = sprintf('ananas_rate:%s:sec:%s', $category, now()->format('Y-m-d-H-i-s'));
        $count = (int) Cache::increment($key);

        if ($count === 1) {
            Cache::put($key, 1, now()->addSeconds(2));
        }

        if ($count > $perSecondLimit) {
            usleep(250_000);
        }
    }

    private function enforcePerMinute(string $category, int $perMinuteLimit): void
    {
        $key = sprintf('ananas_rate:%s:min:%s', $category, now()->format('Y-m-d-H-i'));
        $count = (int) Cache::increment($key);

        if ($count === 1) {
            Cache::put($key, 1, now()->addMinutes(2));
        }

        if ($count > $perMinuteLimit) {
            usleep(1_000_000);
        }
    }
}

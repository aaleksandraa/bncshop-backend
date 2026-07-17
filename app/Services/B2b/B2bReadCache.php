<?php

namespace App\Services\B2b;

use Illuminate\Support\Facades\Cache;

class B2bReadCache
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function rememberCategories(int $ttlSeconds, callable $callback): array
    {
        return Cache::remember('b2b:categories:active', $ttlSeconds, $callback);
    }

    public function flushCategories(): void
    {
        Cache::forget('b2b:categories:active');
    }
}

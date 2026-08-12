<?php

namespace App\Services\Sync;

use App\Models\Product;
use App\Models\ProductSyncLock;
use App\Models\SyncDiffLog;
use Illuminate\Support\Facades\Cache;

class FieldLockService
{
    public function lockField(Product $product, string $fieldName, ?int $userId = null): void
    {
        ProductSyncLock::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'field_name' => $fieldName,
            ],
            [
                'locked_by' => $userId,
                'locked_at' => now(),
            ],
        );

        $this->clearCache($product, $fieldName);
    }

    public function unlockField(Product $product, string $fieldName): void
    {
        ProductSyncLock::query()
            ->where('product_id', $product->id)
            ->where('field_name', $fieldName)
            ->delete();

        $this->clearCache($product, $fieldName);
    }

    public function isLocked(Product $product, string $fieldName): bool
    {
        $cacheKey = "product_sync_lock:{$product->id}:{$fieldName}";

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($product, $fieldName): bool {
            return $product->syncLocks()->where('field_name', $fieldName)->exists();
        });
    }

    public function shouldApply(Product $product, string $fieldName, mixed $apiValue, mixed $localValue): bool
    {
        if (! $this->isLocked($product, $fieldName)) {
            return true;
        }

        if ($this->valuesEqual($apiValue, $localValue)) {
            return false;
        }

        $this->logDiff($product, $fieldName, $apiValue, $localValue);

        return false;
    }

    public function logDiff(Product $product, string $fieldName, mixed $apiValue, mixed $localValue): void
    {
        SyncDiffLog::query()->create([
            'product_id' => $product->id,
            'field_name' => $fieldName,
            'api_value' => $this->stringify($apiValue),
            'local_value' => $this->stringify($localValue),
            'logged_at' => now(),
        ]);
    }

    public function clearCache(Product $product, ?string $fieldName = null): void
    {
        if ($fieldName) {
            Cache::forget("product_sync_lock:{$product->id}:{$fieldName}");

            return;
        }

        foreach ($product->syncLocks as $lock) {
            Cache::forget("product_sync_lock:{$product->id}:{$lock->field_name}");
        }
    }

    private function valuesEqual(mixed $apiValue, mixed $localValue): bool
    {
        return $this->stringify($apiValue) === $this->stringify($localValue);
    }

    private function stringify(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        return (string) $value;
    }
}

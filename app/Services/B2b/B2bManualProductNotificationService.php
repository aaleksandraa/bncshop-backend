<?php

namespace App\Services\B2b;

use App\Jobs\SendB2bManualProductNotificationJob;
use App\Models\B2bCustomer;
use App\Models\B2bProduct;
use Illuminate\Support\Collection;
use RuntimeException;

class B2bManualProductNotificationService
{
    /**
     * @param  array<int, int|string>  $productIds
     */
    public function send(array $productIds, ?string $customIntro = null): int
    {
        $products = $this->resolveProducts($productIds);

        if ($products->isEmpty()) {
            throw new RuntimeException('Odaberite barem jedan aktivan proizvod.');
        }

        $recipientCount = $this->activeCustomerCount();

        if ($recipientCount === 0) {
            throw new RuntimeException('Nema aktivnih B2B kupaca za slanje obavijesti.');
        }

        $intro = filled($customIntro) ? trim($customIntro) : null;

        SendB2bManualProductNotificationJob::dispatch(
            $products->pluck('id')->all(),
            $intro,
        );

        return $recipientCount;
    }

    public function activeCustomerCount(): int
    {
        return B2bCustomer::query()
            ->where('is_active', true)
            ->whereHas('user', fn ($query) => $query
                ->where('is_b2b_customer', true)
                ->whereNotNull('email'))
            ->count();
    }

    /**
     * @return Collection<int, B2bProduct>
     */
    public function resolveProducts(array $productIds): Collection
    {
        $ids = collect($productIds)
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return collect();
        }

        return B2bProduct::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<int, int>
     */
    public function defaultRecentProductIds(): array
    {
        $days = max((int) config('b2b.product_notification.new_product_days', 30), 1);

        return $this->activeProductIdsCreatedBetween(now()->subDays($days), limit: 50)['ids'];
    }

    /**
     * Active products created in [$from, $until). An open end includes everything since $from.
     *
     * @return array{ids: list<int>, total: int, returned: int, truncated: bool}
     */
    public function activeProductIdsCreatedBetween(
        \DateTimeInterface $from,
        ?\DateTimeInterface $until = null,
        int $limit = 200,
    ): array {
        $limit = max(1, min($limit, 200));

        $query = B2bProduct::query()
            ->where('is_active', true)
            ->where('created_at', '>=', $from);

        if ($until !== null) {
            $query->where('created_at', '<', $until);
        }

        $total = (clone $query)->count();

        $ids = $query
            ->orderByDesc('created_at')
            ->limit($limit)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return [
            'ids' => array_values($ids),
            'total' => $total,
            'returned' => count($ids),
            'truncated' => $total > count($ids),
        ];
    }
}

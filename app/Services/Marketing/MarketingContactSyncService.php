<?php

namespace App\Services\Marketing;

use App\Models\Customer;
use App\Models\MarketingContact;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MarketingContactSyncService
{
    public function syncAll(): int
    {
        $count = 0;

        Customer::query()
            ->with('user')
            ->whereHas('user', fn (Builder $query): Builder => $query
                ->whereNotNull('email')
                ->where('email', '!=', ''))
            ->orderBy('id')
            ->chunkById(200, function (Collection $customers) use (&$count): void {
                foreach ($customers as $customer) {
                    if ($this->syncCustomer($customer) !== null) {
                        $count++;
                    }
                }
            });

        Order::query()
            ->select('email')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereNull('customer_id')
            ->distinct()
            ->orderBy('email')
            ->pluck('email')
            ->each(function (string $email) use (&$count): void {
                if ($this->syncEmail($email) !== null) {
                    $count++;
                }
            });

        return $count;
    }

    public function syncEmail(?string $email): ?MarketingContact
    {
        $email = $this->normalizeEmail($email);

        if ($email === null) {
            return null;
        }

        $customer = Customer::query()
            ->with('user')
            ->whereHas('user', fn (Builder $query): Builder => $query->where('email', $email))
            ->first();

        if ($customer !== null) {
            return $this->syncCustomer($customer);
        }

        return $this->syncGuestEmail($email);
    }

    public function syncCustomer(Customer $customer): ?MarketingContact
    {
        $customer->loadMissing('user');
        $email = $this->normalizeEmail($customer->user?->email);

        if ($email === null) {
            return null;
        }

        $stats = $this->orderStatsForCustomer($customer, $email);

        return MarketingContact::query()->updateOrCreate(
            ['email' => $email],
            [
                'type' => MarketingContact::TYPE_REGISTERED,
                'customer_id' => $customer->id,
                'name' => $customer->user?->name,
                'phone' => $customer->phone ?: $customer->user?->phone,
                'company_name' => $customer->company_name,
                'orders_count' => $stats['count'],
                'orders_total' => $stats['total'],
                'last_order_at' => $stats['last_order_at'],
                'registered_at' => $customer->created_at,
            ],
        );
    }

    public function syncFromOrder(Order $order): ?MarketingContact
    {
        if ($order->customer_id) {
            $customer = Customer::query()->with('user')->find($order->customer_id);

            return $customer ? $this->syncCustomer($customer) : null;
        }

        return $this->syncEmail($order->email);
    }

    private function syncGuestEmail(string $email): MarketingContact
    {
        $stats = $this->orderStatsForGuestEmail($email);
        $latestOrder = Order::query()
            ->where('email', $email)
            ->whereNull('customer_id')
            ->latest('created_at')
            ->first();

        return MarketingContact::query()->updateOrCreate(
            ['email' => $email],
            [
                'type' => MarketingContact::TYPE_GUEST,
                'customer_id' => null,
                'name' => $latestOrder
                    ? trim($latestOrder->first_name.' '.$latestOrder->last_name)
                    : null,
                'phone' => $latestOrder?->phone,
                'company_name' => $latestOrder?->company_name,
                'orders_count' => $stats['count'],
                'orders_total' => $stats['total'],
                'last_order_at' => $stats['last_order_at'],
                'registered_at' => null,
            ],
        );
    }

    /**
     * @return array{count: int, total: float, last_order_at: ?\Illuminate\Support\Carbon}
     */
    private function orderStatsForCustomer(Customer $customer, string $email): array
    {
        $query = Order::query()->where(function (Builder $builder) use ($customer, $email): void {
            $builder->where('customer_id', $customer->id)
                ->orWhere('email', $email);
        });

        return $this->aggregateOrderStats($query);
    }

    /**
     * @return array{count: int, total: float, last_order_at: ?\Illuminate\Support\Carbon}
     */
    private function orderStatsForGuestEmail(string $email): array
    {
        return $this->aggregateOrderStats(
            Order::query()
                ->where('email', $email)
                ->whereNull('customer_id'),
        );
    }

    /**
     * @param  Builder<Order>  $query
     * @return array{count: int, total: float, last_order_at: ?\Illuminate\Support\Carbon}
     */
    private function aggregateOrderStats(Builder $query): array
    {
        $stats = (clone $query)
            ->selectRaw('COUNT(*) as orders_count, COALESCE(SUM(total), 0) as orders_total, MAX(created_at) as last_order_at')
            ->first();

        return [
            'count' => (int) ($stats->orders_count ?? 0),
            'total' => (float) ($stats->orders_total ?? 0),
            'last_order_at' => $stats->last_order_at,
        ];
    }

    private function normalizeEmail(?string $email): ?string
    {
        $email = strtolower(trim((string) $email));

        return $email !== '' ? $email : null;
    }
}

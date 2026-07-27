<?php

namespace App\Services\Shipping;

use App\Models\Cart;
use App\Models\ShippingRule;
use App\Services\Commerce\CartService;
use Illuminate\Support\Collection;

class ShippingCalculator
{
    public function __construct(
        private readonly CartService $cartService,
    ) {}

    public function calculate(Cart $cart, string $method = 'delivery'): ShippingResult
    {
        if ($method === 'pickup') {
            return new ShippingResult(
                fee: 0.0,
                isFree: true,
                snapshot: ['method' => 'pickup'],
            );
        }

        $this->refreshCartItems($cart);
        $orderMerchandiseTotal = $this->merchandiseTotalBeforeShipping($cart);
        $rules = $this->resolveApplicableRules($cart);

        $activeRule = $this->selectActiveRule($rules);
        $freeThreshold = $activeRule->free_threshold !== null
            ? (float) $activeRule->free_threshold
            : null;

        if ($freeThreshold !== null && $freeThreshold > 0 && $orderMerchandiseTotal >= $freeThreshold) {
            return $this->freeResult($activeRule, $method);
        }

        return new ShippingResult(
            fee: (float) $activeRule->fixed_fee,
            isFree: false,
            rule: $activeRule,
            snapshot: $this->buildSnapshot($activeRule, $method, (float) $activeRule->fixed_fee),
        );
    }

    private function refreshCartItems(Cart $cart): void
    {
        $cart->unsetRelation('items');
        $cart->load('items.product.category');
    }

    private function merchandiseTotalBeforeShipping(Cart $cart): float
    {
        return $this->cartService->total($cart, 0.0);
    }

    /**
     * @return Collection<int, ShippingRule>
     */
    private function resolveApplicableRules(Cart $cart): Collection
    {
        $rules = collect();

        foreach ($cart->items as $item) {
            if ($item->is_loyalty_reward) {
                continue;
            }

            $categoryId = $item->product?->category_id;
            $categoryRule = $categoryId
                ? ShippingRule::query()
                    ->where('type', 'category')
                    ->where('category_id', $categoryId)
                    ->where('is_active', true)
                    ->orderByDesc('priority')
                    ->first()
                : null;

            $rules->push($categoryRule ?? $this->globalRule());
        }

        if ($rules->isEmpty()) {
            $rules->push($this->globalRule());
        }

        return $rules;
    }

    /**
     * @param  Collection<int, ShippingRule>  $rules
     */
    private function selectActiveRule(Collection $rules): ShippingRule
    {
        if ($rules->isEmpty()) {
            return $this->globalRule();
        }

        $mode = config('bnc.shipping_multi_category_mode', 'max');

        if ($mode === 'sum') {
            $totalFee = $rules->sum(fn (ShippingRule $rule): float => (float) $rule->fixed_fee);
            $highestPriority = $rules->sortByDesc('priority')->first();
            $thresholds = $rules
                ->pluck('free_threshold')
                ->filter(fn ($threshold) => $threshold !== null)
                ->map(fn ($threshold) => (float) $threshold);

            return new ShippingRule([
                'id' => $highestPriority->id,
                'name' => $highestPriority->name,
                'type' => 'combined',
                'fixed_fee' => $totalFee,
                'free_threshold' => $thresholds->isEmpty() ? null : $thresholds->min(),
                'priority' => $highestPriority->priority,
            ]);
        }

        return $rules->sortByDesc(fn (ShippingRule $rule): float => (float) $rule->fixed_fee)->first()
            ?? $this->globalRule();
    }

    private function globalRule(): ShippingRule
    {
        return ShippingRule::query()
            ->where('type', 'global')
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->first()
            ?? new ShippingRule([
                'name' => 'Default',
                'type' => 'global',
                'fixed_fee' => 0,
                'free_threshold' => null,
                'pickup_enabled' => true,
                'is_active' => true,
                'priority' => 0,
            ]);
    }

    private function freeResult(ShippingRule $rule, string $method): ShippingResult
    {
        return new ShippingResult(
            fee: 0.0,
            isFree: true,
            rule: $rule,
            snapshot: $this->buildSnapshot($rule, $method, 0.0),
        );
    }

    private function buildSnapshot(ShippingRule $rule, string $method, float $fee): array
    {
        return [
            'rule_id' => $rule->id,
            'name' => $rule->name,
            'type' => $rule->type,
            'method' => $method,
            'fixed_fee' => (float) $rule->fixed_fee,
            'free_threshold' => $rule->free_threshold !== null ? (float) $rule->free_threshold : null,
            'applied_fee' => $fee,
        ];
    }
}

# 05 — Shipping Rules

## Rule types

### Global (type=global)
- `fixed_fee`: default delivery cost (e.g. 10.00 BAM)
- `free_threshold`: cart subtotal for free delivery (e.g. 100.00 BAM)
- `pickup_enabled`: allow in-store pickup at 0 cost

### Category override (type=category)
- `category_id`: e.g. Klima uređaji
- `fixed_fee`: e.g. 25.00 BAM
- `free_threshold`: e.g. 500.00 BAM
- `priority`: higher wins when multiple rules match

## Calculation algorithm

```
function calculateShipping(Cart $cart, string $method): ShippingResult
{
    if ($method === 'pickup') return free();

    $subtotal = $cart->subtotal();
    $rules = [];

    foreach ($cart->items as $item) {
        $categoryRule = ShippingRule::forCategory($item->product->category_id);
        $rules[] = $categoryRule ?? ShippingRule::global();
    }

    $activeRule = $rules->sortByDesc('priority')->first() ?? ShippingRule::global();

    if ($subtotal >= $activeRule->free_threshold) {
        return free($activeRule);
    }

    return fee($activeRule->fixed_fee, $activeRule);
}
```

## Multi-category carts

Default: **max fee** among applicable category rules (configurable to sum via `shipping_multi_category_mode`).

## Snapshot

Order stores: `shipping_fee`, `shipping_method`, `shipping_rule_snapshot` JSON with rule id, name, fixed_fee, free_threshold used.

## Admin UI

- CRUD shipping rules
- Preview calculator: simulate cart with products from category X
- Link from category edit to category shipping override

## Examples

| Scenario | Subtotal | Method | Result |
|----------|----------|--------|--------|
| 2x laptop, global 10KM/100KM free | 150 KM | delivery | 0 KM |
| 1x laptop | 80 KM | delivery | 10 KM |
| 1x klima | 400 KM | delivery | 25 KM (category) |
| Any | any | pickup | 0 KM |

# 04 — Pricing & Discounts

## Price calculation algorithm

```
function calculateDisplayPrice(Product $product, ?Coupon $coupon = null): PriceResult
{
    if ($product->price_locked && $product->manual_price !== null) {
        $base = $product->manual_price;
    } else {
        $localDiscount = DiscountEngine::bestForProduct($product);
        if ($localDiscount) {
            $base = $localDiscount->apply($product->regular_price);
        } elseif ($product->hasActiveApiRebate()) {
            $base = $product->api_final_price ?? applyRebate($product);
        } else {
            $base = $product->api_final_price ?? $product->api_price ?? $product->regular_price;
        }
    }
    if ($coupon) {
        $base = CouponEngine::apply($base, $coupon, $product);
    }
    return new PriceResult($base, ...);
}
```

## Discount types

| type | Scope | Exclusions |
|------|-------|------------|
| product | Single product_id | — |
| category | category_id + include_subcategories | excluded_products, excluded_brands |
| brand | manufacturer_id | excluded_products |
| attribute | conditions_json | excluded_products |
| tag | tag_id | excluded_products |

## Combination rules (system_settings)

- `discount_combination_mode`: best_single | stack | product_priority | category_priority
- `coupon_combines_with_sale`: boolean (default false)
- Default: **best_single** — najpovoljnija pojedinačna akcija

## API rebate vs local

- API rebate stored separately in api_rebate fields
- Local discounts in discounts table
- Analytics tracks discount_source: api | local | coupon

## Price history

Logged on: api_sync price change, manual edit, discount activation end

## Coupon scopes (admin)

| scope | applicable_to JSON |
|-------|-------------------|
| all | `null` |
| products | `{ "scope": "products", "product_ids": [1,2] }` |
| categories | `{ "scope": "categories", "category_ids": [...], "include_subcategories": true }` |
| brands | `{ "scope": "brands", "manufacturer_ids": [...] }` |
| tags | `{ "scope": "tags", "tag_ids": [...] }` |

## Coupon URL auto-apply (B2C)

- Query param: `?kupon=CODE` (alias: `?coupon=CODE`)
- Product preview: `GET /api/v1/products/{slug}?kupon=CODE` returns `coupon` object with preview price
- Cart auto-apply: frontend `CouponFromUrl` calls `POST /cart/coupon`; empty cart stores `pending_coupon_code` until first applicable item is added
- Marketing links generated in Filament coupon form

## Coupon validation modes

- **Preview** (`validateForPreview`): active/dates/max uses/single-use + product scope; ignores min cart and empty cart
- **Checkout** (`validate`): full rules including min cart amount and applicable cart items

## Test cases

1. Locked manual price overrides API and discounts
2. Category 10% + product 15% → best_single returns 15%
3. Expired rebateValidUntil → no API discount shown
4. Coupon min_cart_amount not met → coupon rejected at checkout
5. Product-scoped coupon URL shows reduced price on product page
6. Pending coupon activates when applicable product added to cart

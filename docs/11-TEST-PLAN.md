# 11 — Test Plan

## Unit tests

- `PriceCalculatorTest` — all price hierarchy scenarios
- `DiscountEngineTest` — product/category/brand/attribute/tag discounts
- `ShippingCalculatorTest` — global, category override, pickup, free threshold
- `AttributeNormalizerTest` — boolean/number/text normalization
- `SlugGeneratorTest` — uniqueness, suffix, redirect trigger

## Integration tests

- `ApiSyncTest` — mock HTTP, full product import, field locking
- `IncrementalSyncTest` — date-modified-after, timestamp only on success
- `CheckoutTest` — cart → order, stock reservation, snapshot
- `CouponTest` — apply, min amount, expiry
- `SearchIndexTest` — product indexed with filter attributes

## Feature tests (API)

- Product listing with filters
- Cart CRUD
- Checkout flow guest + registered
- Order status transitions and stock effects

## E2E (Playwright on frontend)

1. Browse category → filter → product detail → add to cart
2. Checkout as guest with delivery
3. Checkout with pickup (free shipping)
4. Register → login → view order history
5. Coupon application at checkout

## Load tests

- 17k products listing page < 500ms (cached)
- Search query < 200ms (Meilisearch)
- Sync 500 products/page job completes < 60s

## Production checklist

See section 18 in upustvo.md — all items must pass before go-live.

## A1 Live Integration (staging)

Run against https://a1team.ba with credentials in `.env`:

```bash
php artisan db:seed --class=ApiSourceSeeder
php artisan bnc:a1-api-test          # Postman tests 1.1-1.7
php artisan bnc:sync-full "A1 Technoshop" --sync --max-pages=3   # pilot
php artisan bnc:sync-full "A1 Technoshop" --sync                 # full
php artisan bnc:sync-incremental "A1 Technoshop" --sync
php artisan bnc:validate-import
php artisan scout:import "App\Models\Product"
```

Windows dev may require `A1_API_VERIFY_SSL=false` in `.env`.

| Test | Command | Expected |
|------|---------|----------|
| A1ApiLoginTest | `bnc:a1-api-test` | 6 passed |
| Pilot import | `--max-pages=3` | 30 products, 0 errors |
| Full import | full sync | ~18628 products |
| Incremental | `bnc:sync-incremental --sync` | uses date-modified-after |
| Data validation | `bnc:validate-import` | 0 duplicate external IDs |

Live PHPUnit: set `A1_API_LIVE_TEST=true` only on staging (optional).


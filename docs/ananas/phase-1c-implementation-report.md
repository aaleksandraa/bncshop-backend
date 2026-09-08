# Phase 1C — Controlled import + empirical category validation

**Date:** 2026-09-08  
**Scope:** POST import on Stage, category probe workflow, GET reconciliation, `ananas_product_mappings` lifecycle.

## Goal

Empirically confirm exact Ananas subcategory strings (e.g. `ITShop` + `Laptopi`) because Ananas documents:

- **POST import** accepts singular `category` (String)
- **GET products** returns plural `categories` (List<String>)
- No public API lists valid subcategory strings per product type

## New components

| Component | Purpose |
|-----------|---------|
| `AnanasCatalogWriteGuard` | Blocks catalog POST unless env/admin toggle enabled; production requires `--allow-production` |
| `AnanasApiClient::importProducts()` | POST `/merchant-integration/import` → progress UUID |
| `AnanasApiClient::checkEansExist()` | POST `/ean/exists` |
| `AnanasApiClient::findProductByEan()` | GET `/products?ean=` helper |
| `AnanasCategoryProbeService` | Import 1 product, poll GET, compare `categories[]` vs candidate |
| `AnanasProductImportService` | Controlled batch import with `--confirm` |
| `AnanasProductReconciliationService` | Link local mappings from GET; refresh category validation |
| `AnanasProductMappingService` | SUBMITTED → LINKED / FAILED lifecycle |

## Database

Migration `2026_09_08_140000_add_ananas_category_validation_and_probes.php`:

- `ananas_category_mappings`: validation status, observed categories, probe metadata
- `ananas_category_probes`: audit trail per probe attempt

## CLI workflow (Stage)

```bash
# 1. Enable writes (env or Filament Ananas settings)
ANANAS_ALLOW_CATALOG_WRITES=true

# 2. Refresh product types
php artisan bnc:ananas-refresh-product-types

# 3. Configure mapping in Filament (productType=ITShop, category=Laptopi)

# 4. Dry-run probe payload
php artisan bnc:ananas-probe-category {mapping_id} --dry-run

# 5. Live probe (imports 1 product, polls GET /products)
php artisan bnc:ananas-probe-category {mapping_id} --wait=60

# 6. If async pending
php artisan bnc:ananas-probe-category {mapping_id} --recheck={probe_id}
# or
php artisan bnc:ananas-reconcile-products

# 7. Batch import (after category validated)
php artisan bnc:ananas-import-products --limit=10 --dry-run
php artisan bnc:ananas-import-products --limit=10 --confirm
php artisan bnc:ananas-reconcile-products
```

## Validation logic

1. POST import with `productType` + `category` from Filament mapping
2. Poll GET `/products?ean=` (configurable interval/attempts)
3. **VALIDATED** if candidate appears in response `categories[]` (exact or case-insensitive) and `productType` matches
4. **FAILED** if product visible but categories differ → adjust free-text string and re-probe
5. **PENDING** if import accepted but GET still empty (async processing)

## Safety

- Default: `ANANAS_ALLOW_CATALOG_WRITES=false`
- Import command requires `--confirm`
- Production writes require `--allow-production`
- Phase 1A GET-only guard replaced with explicit POST allow-list + write guard
- No auto-sync jobs in 1C

## Config

| Key | Default |
|-----|---------|
| `ANANAS_IMPORT_BATCH_MAX_SIZE` | 25 |
| `ANANAS_IMPORT_POLL_INTERVAL_SECONDS` | 5 |
| `ANANAS_IMPORT_POLL_MAX_ATTEMPTS` | 12 |

## Filament

Category mapping table shows validation badge + observed categories from last probe/reconcile.

## Tests

- `AnanasApiClientWriteTest` — write guard, import UUID, EAN exists, find by EAN
- `AnanasCategoryProbeServiceTest` — dry-run, validated, failed paths

## Next (Phase 2 / production rollout)

- Publish/unpublish endpoints
- Scheduled reconciliation job
- Horizon batch orchestrator
- Attribute mapping (`attributes` map) for IT specs

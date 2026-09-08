# Ananas Phase 1B — Implementation Report

Date: 2026-09-08  
Scope: Mappings, weight resolver, extended eligibility, mapper (payload only), Filament admin, CLI reports. **No POST/PUT import, publish, or scheduled sync.**

## Delivered

### Database

- `ananas_product_types` — cached GET product-type names
- `ananas_category_mappings` — BNC category → Ananas product type + free-text category
- `ananas_product_mappings` — per-product export state (schema ready; writes not automated yet)

Migration: `database/migrations/2026_09_08_120000_create_ananas_integration_tables.php`

### Services

| Class | Role |
|-------|------|
| `AnanasPackageWeightResult` | Structured weight parse result |
| `AnanasPackageWeightResolver` | Approved attribute chain → kg; rejects ambiguous/unit-less |
| `AnanasExportScope` | Enabled category mappings + descendant scope |
| `AnanasEligibilityPolicy` | Full export reason codes (refurbished, set, EAN, image, weight, price, VAT, category) |
| `AnanasProductMapper` | Import payload builder; **only** `PriceCalculator::regularPrice` for `basePrice` |
| `AnanasProductTypeSyncService` | Refresh product types from API |
| `AnanasEligibilityReporter` | Count eligible / blocked products in scoped categories |

### Admin (Filament)

- **Ananas → Postavke** — credentials, environment, VAT gate, test connection, refresh types, eligibility summary
- **Ananas → Mapiranje kategorija** — BNC category → Ananas product type + category text

### CLI

```bash
php artisan bnc:ananas-refresh-product-types
php artisan bnc:ananas-eligibility-report
```

### Config

- `ANANAS_VAT_RATE` — must be `0`, `10`, or `20` once Ananas confirms BiH treatment; until set, eligibility returns `VAT_UNRESOLVED`
- `bnc.ananas_weight_attribute_names` — confirmed fallback chain from plan

### Tests

```
php artisan test tests/Unit/Ananas
```

37 tests (Phase 1A + 1B).

## Explicitly not in 1B

- POST `/import`, PUT bulk edit, publish/unpublish
- Horizon jobs / orchestrator / reconciliation
- Automatic product mapping row creation on sync
- Warehouse default persistence (svc host still timing out from some networks)
- Phase 2+ order integration

## Blockers before catalog writes

1. **BiH VAT** — set `ANANAS_VAT_RATE` or admin VAT only after Ananas confirms which of {0,10,20} to send with 17%-inclusive BAM `basePrice`
2. **Category mappings** — enable mappings for target BNC categories
3. **Live product payload** — merchant catalog still empty on QA2; confirm remote IDs after first import (Phase 3)
4. **`allow_catalog_writes`** — remains false by default

## Next phase (requires approval)

Phase 3: controlled POST import batch, progress UUID, GET reconciliation, `ananas_product_mappings` lifecycle updates — still gated on VAT + mappings + explicit write approval.

# Ananas Phase 1D — Full catalog API alignment

## Scope

Align BNC backend with `docs/ananas/ananasapi.md` beyond Phase 1C import/probe/reconcile:

- Master catalog EAN check (`POST ean/exists`) drives onboarding vs instant load semantics (FAQ).
- Mandatory import field **brand** (manufacturer name or `ANANAS_DEFAULT_BRAND`).
- Bulk update (`PUT product/bulk`), publish/unpublish progress jobs.
- CLI for EAN diagnostics, master-EAN product discovery, linked sync, publish/unpublish.
- Probe status `awaiting_onboarding` when import succeeds but EAN is not in master catalog.

## New / updated services

| Service | Role |
|---------|------|
| `AnanasMasterEanCatalogService` | Batch `ean/exists`, find eligible product with master EAN |
| `AnanasLinkedProductSyncService` | Stock/price bulk update; publish/unpublish for LINKED mappings |
| `AnanasApiClient` | `checkEansExist`, bulk PUT, publish/unpublish, single product PUT |

## CLI

| Command | Purpose |
|---------|---------|
| `bnc:ananas-check-ean {ean}` | Master catalog lookup |
| `bnc:ananas-find-master-ean-product` | Best probe candidate; `--all`, `--ean=`, seed EANs via `ANANAS_PROBE_SEED_EANS` |
| `bnc:ananas-sync-linked` | Bulk stock/price for LINKED rows |
| `bnc:ananas-publish` / `bnc:ananas-unpublish` | Visibility jobs per API |
| `bnc:ananas-probe-category … --master-ean-only` | Restrict probe to master-catalog EAN |

## QA2 operations

1. **Category validation (fast path):** `bnc:ananas-find-master-ean-product` → probe with `--product=<id> --master-ean-only`.
2. **New BNC EAN:** probe/import sets `awaiting_onboarding` / `PENDING_ONBOARDING`; email `onboarding@ananas.rs` (or `ANANAS_ONBOARDING_EMAIL`) with Progress UUID.
3. After `bnc:ananas-check-ean` returns yes → `--recheck=<probe_id>` or `bnc:ananas-reconcile-products`.

## Config

- `ANANAS_DEFAULT_BRAND` (default `BNC Shop`)
- `ANANAS_ONBOARDING_EMAIL` (default `onboarding@ananas.rs`)

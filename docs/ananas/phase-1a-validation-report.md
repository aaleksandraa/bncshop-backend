# Ananas Phase 1A — Validation Report

Date: 2026-08-31  
Scope: Read-only client, eligibility policy, tests. **No write endpoints invoked.**

## Implemented files

| File | Purpose |
|------|---------|
| `app/Services/Ananas/AnanasApiClient.php` | IAM token (CLIENT_CREDENTIALS), Redis cache, 401 re-auth once, GET product-types/products/basic-products, svc warehouses, 429 backoff, log redaction |
| `app/Services/Ananas/AnanasSyncSettings.php` | Stage/Prod hosts, env + ApiSource credentials, `allow_catalog_writes` default false |
| `app/Services/Ananas/AnanasRateLimiter.php` | Product 5 rps / 60 rpm, warehouse 5 rps / 300 rpm |
| `app/Services/Ananas/AnanasEligibilityPolicy.php` | Early `REFURBISHED_OR_USED`, `SET_PRODUCT`; eLine-new not excluded by source |
| `app/Services/Ananas/AnanasEligibilityResult.php` | Structured eligibility result |
| `app/Console/Commands/AnanasTestConnectionCommand.php` | `bnc:ananas-test-connection` |
| `config/bnc.php` | Ananas env placeholders + Stage/Prod base URLs |
| `.env.example` / `.env.production.example` | Empty credential placeholders |
| `app/Models/ApiSource.php` | `ananas` in `NON_INTEGRATION_IMPORT_TARGET_CODES` |
| `tests/Unit/Ananas/AnanasApiClientTest.php` | 11 Http::fake tests |
| `tests/Unit/Ananas/AnanasEligibilityPolicyTest.php` | 7 policy tests |

Mirrored to `backend-push/` with the same paths.

## Automated tests

```
php artisan test tests/Unit/Ananas
```

Result: **18 passed** (35 assertions).

Coverage includes: token cache + TTL margin, single 401 recovery, second 401 failure, JSON parse, 502 retry, log redaction, rate limiter hook, refurbished/set/eline-new eligibility.

## Stage (QA2) live test

Command (credentials supplied via environment only, not committed):

```bash
ANANAS_ENV=stage ANANAS_CLIENT_ID=... ANANAS_CLIENT_SECRET=... php artisan bnc:ananas-test-connection --force-auth
```

**Result: authentication successful** (retest 2026-09-08, new QA2 credential pair from Ananas)

| Step | Result |
|------|--------|
| POST token (`api.qa2.ananastest.com`) | HTTP 200, Bearer token received |
| GET product-type | **10 types** — Moda, BabyKidsToys, ITShop, Automotive, Super Market, KnjižaraOfficeSchool, BeautyHealth, Kuća i vrt, Sport, Aparati |
| GET products (page=0, size=1) | HTTP 200, **0 items** (empty merchant catalog) |
| GET basic-products (page=0, size=1) | HTTP 200, **0 items** |
| GET merchant-warehouses (`api.svc.qa2.ananastest.com`) | **Connection timeout** from dev network (~10–20s); not an auth failure |

Earlier retest (2026-09-07/08) with a different credential pair returned **401** on QA2 but **200** on Production — that pair was Production-only. The replacement QA2 credentials issued by Ananas authenticate correctly on Stage.

### Hosts used (Stage)

- IAM + Product: `https://api.qa2.ananastest.com`
- Warehouses (svc): `https://api.svc.qa2.ananastest.com`

## Production live test

Command:

```bash
ANANAS_ENV=production ANANAS_CLIENT_ID=... ANANAS_CLIENT_SECRET=... php artisan bnc:ananas-test-connection --force-auth
```

**Result: authentication successful** (retest 2026-09-07)

| Step | Result |
|------|--------|
| POST token (`api.ananas.rs`) | HTTP 200, Bearer token received |
| GET product-type | **10 types** — e.g. Automotive, Super Market, BabyKidsToys, KnjižaraOfficeSchool, Moda |
| GET products (page=0, size=1) | HTTP 200, **0 items** (empty merchant catalog) |
| GET basic-products (page=0, size=1) | HTTP 200, **0 items** |
| GET merchant-warehouses (`api.svc.ananas.rs`) | **Connection timeout** from dev network (443 connect timeout ~10–30s); not an auth failure |

### Hosts used (Production)

- IAM + Product: `https://api.ananas.rs`
- Warehouses (svc): `https://api.svc.ananas.rs`

**Important:** do not use Production credentials against QA2 URLs, and do not use QA2 credentials against Production in automated jobs without explicit `ANANAS_ENV` control.

## Identifier semantics

**Partially observed live (Stage QA2 + Production, empty catalog).** From docs + successful product-type GET on both environments:

- Product types are string labels returned as a JSON array from GET product-type (10 types on QA2 and Production).
- GET products returned HTTP 200 with an empty list — no live product payload yet to confirm `id` vs merchantInventoryId.
- Publish/discount docs reference merchant inventory id — **confirm from first imported product GET**.
- Do not freeze mapping-table schema until at least one live product payload is captured.

## Doc discrepancies noted

- Warehouse response field typo in docs: `defaultAddres` vs `defaultAddress`.
- Import progress UUID documented but not public yet.
- `packageWeightUnit` hardcoded KG on Ananas side.

## Remaining blockers (catalog writes — not 1A)

1. **BiH VAT** — which `vat` value (0/10/20) for 17%-inclusive BAM `basePrice`?
2. **Package weight** — map from A1 attributes via future `AnanasPackageWeightResolver`; sample ≥30 production `raw_value` strings before unit-less rules.
3. **Live product payload** — merchant catalog empty on QA2 and Production; identifier fields unconfirmed until first product exists on Ananas.
4. **Warehouses svc host** — `api.svc.qa2.ananastest.com` / `api.svc.ananas.rs` timed out from dev network; verify from production server/VPN.

## Proposed Phase 1B (requires explicit approval)

- After successful Stage auth: capture redacted GET samples, confirm `id` vs merchantInventoryId.
- `AnanasPackageWeightResolver` + tests (mapper must not parse weight).
- Migrations + category/product-type mapping UI.
- Mapper with `PriceCalculator::regularPrice` only.
- Still **no auto-publish** until mapping + VAT resolved.

## Security

- No secrets in git, plan, or this report.
- Token and clientSecret redacted in logs via `AnanasApiClient::redactSensitiveText()`.
- `ANANAS_ALLOW_CATALOG_WRITES=false` by default.

## Acceptance checklist

| Criterion | Status |
|-----------|--------|
| AnanasApiClient + cache + 401 retry | Done |
| Rate limiter + 429 handling | Done |
| `bnc:ananas-test-connection` | Done |
| AnanasEligibilityPolicy + tests | Done |
| Http::fake tests pass | Done |
| Zero write endpoints in code | Done |
| Stage auth + GET samples | **Done — QA2 auth OK; product-types OK; catalog empty; svc warehouses timeout from dev network** |
| Production auth + GET samples | **Auth OK; product-types OK; catalog empty; svc warehouses timeout from dev network** |
| Stop before 1B | Done |

**Phase 1A implementation complete. QA2 Stage IAM verified (2026-09-08). Use `ANANAS_ENV=stage` with QA2 credentials for sandbox; keep Production credentials separate for live.**

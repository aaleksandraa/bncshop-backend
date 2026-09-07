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

**Result: authentication failed HTTP 401** (last retest 2026-09-07)

`bnc:ananas-test-connection` now prefers `ANANAS_*` env vars without querying PostgreSQL, so local Stage checks no longer fail on missing DB role.

Direct `curl` POST to `https://api.qa2.ananastest.com/iam/api/v1/auth/token` with the same QA2 `clientId` / `clientSecret` and body:

```json
{
  "grantType": "CLIENT_CREDENTIALS",
  "clientId": "...",
  "clientSecret": "...",
  "scope": "public_api/full_access"
}
```

also returned **401** `{"statusCode":401,"messageKey":"unauthorized",...}`.

This indicates the Phase 1A client wiring is not the blocker; the QA2 credential pair is rejected at IAM (likely Public API not enabled on the merchant account, wrong/expired secret, or credentials not yet provisioned for QA2).

**No GET product-types / warehouses / products samples** could be collected until IAM returns a token.

### Hosts used (Stage)

- IAM + Product: `https://api.qa2.ananastest.com`
- Warehouses (svc): `https://api.svc.qa2.ananastest.com`

## Identifier semantics

**Not observed live** (auth blocked). From docs only:

- GET products returns `id`, `externalId`, `ean`, `ananasCode`, `groupId`, `sku`, `status`, etc.
- Publish/discount docs reference merchant inventory id — **confirm from live GET after auth works**.
- Do not freeze mapping-table schema until Stage payloads are captured.

## Doc discrepancies noted

- Warehouse response field typo in docs: `defaultAddres` vs `defaultAddress`.
- Import progress UUID documented but not public yet.
- `packageWeightUnit` hardcoded KG on Ananas side.

## Remaining blockers (catalog writes — not 1A)

1. **QA2 IAM 401** — ask Ananas to enable `public_api/full_access` for the QA2 client or re-issue credentials.
2. **BiH VAT** — which `vat` value (0/10/20) for 17%-inclusive BAM `basePrice`?
3. **Package weight** — map from A1 attributes via future `AnanasPackageWeightResolver`; sample ≥30 production `raw_value` strings before unit-less rules.
4. **Stage import semantics** — POST import/onboarding behavior after auth.
5. **Production credentials** — separate pair; never use QA2 against `api.ananas.rs`.

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
| Stage auth + GET samples | **Blocked — IAM 401** |
| Stop before 1B | Done |

**Phase 1A implementation complete. Waiting on Ananas IAM access before live GET validation and any 1B approval.**

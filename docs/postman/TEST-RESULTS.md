# A1 API Import — Test Results

Datum: 2026-06-27

## Konfiguracija

| Parametar | Vrijednost |
|-----------|------------|
| baseUrl | https://a1team.ba |
| targetSystemCode | bnc-shop |
| username | bnc |
| page_size (full) | 500 |

## Faza 0 — Code fixes

- [x] Login path: `/api/auth/login`
- [x] Refresh path: `/api/auth/refresh` + `refresh_token` body
- [x] Paginacija categories/attributes/products preko `pagination.nextPage`
- [x] `A1_API_VERIFY_SSL=false` za Windows dev

## Faza 1 — `php artisan bnc:a1-api-test`

| Test | Rezultat |
|------|----------|
| 1.1 Login | PASS |
| 1.2 Refresh | PASS |
| 1.3 Categories | PASS (313) |
| 1.4 Attributes | PASS (2290) |
| 1.5 Products | PASS (10, totalRecords: 18628) |
| 1.7 ModifiedAfter | PASS |

## Faza 3 — Pilot sync (--max-pages=3, page_size=10)

| Metrika | Vrijednost |
|---------|------------|
| products.imported | 30 |
| products.pages | 3 |
| products.errors | 0 |
| attributes | 2290 created |
| attribute mappings | 8920 |

## Faza 4 — Full sync

Status: vidi `api_import_jobs` u adminu / `php artisan bnc:validate-import`

API vraća ~300 proizvoda po stranici uprkos PageSize=500 (provjeriti sa A1 timom).

## Komande

```bash
php artisan bnc:a1-api-test
php artisan bnc:sync-full "A1 Technoshop" --sync --max-pages=3
php artisan bnc:sync-full "A1 Technoshop" --sync
php artisan bnc:sync-incremental "A1 Technoshop" --sync
php artisan bnc:validate-import
php artisan scout:import "App\Models\Product"
```

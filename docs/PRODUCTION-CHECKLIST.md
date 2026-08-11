# Produkcioni checklist

Pre go-live provjerite sve stavke:

## API integracija
- [ ] API login i refresh token rade
- [ ] Full import kategorija, atributa, proizvoda
- [ ] Inkrementalni sync sa `ModifiedAfter`
- [ ] Cron `schedule:run` + queue worker aktivni u produkciji
- [ ] Admin → A1 Technoshop sync: interval i auto sync podešeni
- [ ] Paginacija (500/stranica) za 17k proizvoda
- [ ] Field locking i sync diff log
- [ ] Sync timestamp se ažurira samo na uspješan završetak

## Katalog
- [ ] Mapiranje svih JSON polja (product, category, attribute, gallery, supplier)
- [ ] Meilisearch indeks i faceted filteri
- [ ] SEO: manual > API > template prioritet
- [ ] 301 redirecti za promjenu slugova

## Commerce
- [ ] Korpa (guest + registrovan)
- [ ] Checkout bez online plaćanja
- [ ] Shipping: global, free threshold, category override (klima)
- [ ] Order snapshot (cijene, atributi, popusti)
- [ ] Stock rezervacija i status workflow
- [ ] Email kupcu i adminu

## Admin
- [ ] Filament CRUD: proizvodi, kategorije, brendovi, atributi
- [ ] Popusti (proizvod/kategorija/brend/atribut/tag)
- [ ] Kuponi, shipping rules, narudžbe
- [ ] API sync monitoring
- [ ] Analitika dashboard i izvještaji

## Frontend
- [ ] Listing, filteri, search, product detail
- [ ] Cart, checkout, order tracking
- [ ] Customer account (opcionalno)
- [ ] JSON-LD i generateMetadata

## Sigurnosni i ops hardening (implementirano)

- [x] Uklonjen hardcoded eLine token — obavezan `ELINE_API_TOKEN` u env
- [x] Sanctum token expiration (`SANCTUM_TOKEN_EXPIRATION`)
- [x] Zaštićen `/customer/pending-loyalty` (auth only)
- [x] Health endpoint ne otkriva interne greške u produkciji
- [x] DiscountEngine + CategoryScopeResolver keširani (Redis)
- [x] Email i analytics preko queue (Horizon workers)
- [x] Sentry integracija (`SENTRY_LARAVEL_DSN`)
- [x] DB indeksi: `carts.user_id`, `orders.user_id`, `orders.customer_id`
- [x] Produkcijski env template: `docs/env.production.example.md`
- [x] Deploy skripta: `scripts/deploy-production.sh`
- [x] Load test: `scripts/load-test/k6-browse-checkout.js`

## Sigurnost i ops
- [ ] `APP_DEBUG=false` (health endpoint ne smije otkrivati DB/Redis detalje)
- [ ] `APP_ENV=production` (Horizon `/horizon` mora vraćati 401/403 bez prijave)
- [ ] `/horizon` zaključan — `curl` na `/horizon/api/stats` vraća 401 ili 403
- [ ] `TURNSTILE_ENABLED=true` + validni site/secret keys (login, register, B2B auth, checkout)
- [ ] `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`
- [ ] `TRUSTED_PROXIES` = eksplicitne IP adrese reverse proxyja (nikad `*`)
- [ ] Rate limiting na API
- [ ] Enkriptovani API credentials
- [ ] Audit log aktivnosti
- [ ] Daily backup baze
- [ ] Error monitoring (Sentry)
- [ ] Health endpoint `/api/v1/health`
- [ ] PHPUnit test suite prolazi
- [ ] CSP napomena: Next.js trenutno koristi `unsafe-inline`/`unsafe-eval` zbog Turnstile/Next — ne blokira deploy

## Performance
- [ ] Listing 17k kataloga < 500ms (cache/ISR)
- [ ] Search < 200ms (Meilisearch)
- [ ] Queue workers (Horizon) za sync i email
- [ ] `composer audit` (backend) — nema critical/high bez mitigation plana
- [ ] `npm audit --omit=dev` (frontend) — Next/postcss moderate: pratiti Next patch, ne forsirati `npm audit fix --force`
- [ ] k6 browse/checkout + B2B browse (`scripts/load-test/`)
- [ ] Filament `^3.3.54+` (CVE-2026-48500 / 55409 / 48067)

## Test komande

```bash
cd backend
php artisan test
php artisan route:list
php artisan bnc:import-json-samples
```

```bash
cd frontend
npm run build
```

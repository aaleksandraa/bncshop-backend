# Idle tab — sporа navigacija (dijagnostika)

Simptom: nakon ~10 min neaktivnog taba na `bncshop.ba`, klik na kategoriju (npr. Monitori) traje 1–2 min i često zahtijeva više klikova.

## Brza provjera u browseru

1. Otvori `https://bncshop.ba`, DevTools → **Network** (Preserve log).
2. Ostavi tab u pozadini **10+ minuta**.
3. Vrati se, klikni **Monitori** jednom (ne ponavljaj klik).
4. Sortiraj po **Duration** — traži requeste koji visе:

| Request | Tipičan uzrok |
|---------|---------------|
| `?_rsc=` / flight | Next.js RSC navigacija (server-side render) |
| `/sanctum/csrf-cookie` | CSRF bez timeouta (client) |
| `/backend-api/analytics/events` | Analytics POST (client) |
| `/backend-api/v1/cart` | Korpa pri mountu |
| Server-side API (nije u browseru) | Stale PostgreSQL/Redis u PHP-FPM |

**Očekivano pri bugu:** 1–2 requesta u statusu *Pending* 60–120 s; ostali čekaju zbog limita konekcija po hostu (~6).

## Provjera na serveru (SSH)

```bash
cd /var/www/vhosts/bncshop.ba/backend   # prilagodi putanju
php artisan bnc:perf-check
```

Ponovi nakon 10+ min bez HTTP prometa na API. Usporedi `services.database.latency_ms` i `services.redis.latency_ms` — skok s <5 ms na sekunde/minute ukazuje na stale konekcije u PHP-FPM workerima.

```bash
curl -fsS -o /dev/null -w "health_ttfb=%{time_starttransfer}s total=%{time_total}s\n" \
  https://api.bncshop.ba/api/v1/health
```

## Implementirani fixevi (kod)

- Frontend: timeout na CSRF/analytics, odgođeni analytics, prefetch kategorija, `ConnectionWarmup` na `visibilitychange`
- Backend: `EnsureFreshConnections` middleware (SELECT 1 + Redis ping, reconnect na fail)
- Infra: vidi [13-SERVER-DEPLOYMENT-PLESK.md](./13-SERVER-DEPLOYMENT-PLESK.md) — sekcija *Idle tab / stale connections*

## Auth nije uzrok

`SESSION_LIFETIME=120` minuta. Sanctum SPA koristi cookie sesiju, ne JWT expiry na 10 min.

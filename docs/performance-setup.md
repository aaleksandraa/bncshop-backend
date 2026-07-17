# BNC Shop — Performance setup

## Lokalno okruženje

### 1. Docker servisi

```bash
docker compose -f docker/docker-compose.yml up -d redis meilisearch postgres
```

| Servis | Port | Namjena |
|--------|------|---------|
| Redis | 6379 | Cache + queue (tag invalidation) |
| Meilisearch | 7700 | Pretraga i filtriranje |
| PostgreSQL | 5432 | Baza |

### 2. Backend `.env`

```env
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SCOUT_DRIVER=meilisearch
SCOUT_QUEUE=true
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=masterKey
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

### 3. Inicijalizacija

```bash
cd backend
php artisan config:clear
php artisan cache:clear
php artisan scout:import "App\Models\Product"
php artisan queue:work redis --queue=default,scout
```

### 4. Health check

```bash
php artisan bnc:health
```

## Produkcija — checklist

- [ ] Redis (managed ili self-hosted) sa `maxmemory-policy allkeys-lru`
- [ ] Meilisearch sa master key u secrets manageru
- [ ] `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`
- [ ] Nginx/Cloudflare gzip za `/api/*`
- [ ] Dedicated queue worker: `php artisan queue:work redis --queue=default,scout --tries=3`
- [ ] Scheduler cron: `* * * * * php artisan schedule:run`
- [ ] Scout reindex nakon velikog synca: `php artisan scout:import "App\Models\Product"`

## Fallback bez Redis-a

Ako Redis nije dostupan, postavite `CACHE_STORE=file`. **Tag invalidation neće raditi** — cache ističe po TTL-u (60–900s). Produkcija mora koristiti Redis.

## Metrije (cilj)

| Metrika | Cilj |
|---------|------|
| Product API (cache hit) | < 50ms |
| PLP API (24 proizvoda) | < 100ms |
| PDP TTFB (cached) | < 200ms |
| Filters API (cached) | < 80ms |

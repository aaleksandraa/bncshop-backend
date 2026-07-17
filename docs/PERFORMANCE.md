# Performance baseline — BNC Shop

Storefront reads products exclusively from local PostgreSQL (`bncshop`). A1 API is used only for background sync.

## Infrastructure

| Service | Purpose | Default URL |
|---------|---------|-------------|
| PostgreSQL | Primary data store | `127.0.0.1:5432` |
| Redis | Response cache (tags) | `127.0.0.1:6379` |
| Meilisearch | Full-text search + facets | `http://127.0.0.1:7700` |

Start local stack:

```bash
docker compose -f docker/docker-compose.yml up -d postgres redis meilisearch
```

## Environment (backend)

```env
CACHE_STORE=redis
REDIS_HOST=127.0.0.1
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=masterKey
SCOUT_QUEUE=true
QUEUE_CONNECTION=database
```

After changing env:

```bash
cd backend
php artisan config:cache
php artisan route:cache
php artisan scout:import "App\Models\Product"
```

## Baseline endpoints to measure

| Endpoint | Target (cached) | Target (cold DB) |
|----------|-----------------|------------------|
| `GET /api/v1/products?per_page=24` | < 20ms | < 500ms |
| `GET /api/v1/products/{slug}` | < 20ms | < 300ms |
| `GET /api/v1/search?q=laptop` | < 50ms | < 400ms |
| Homepage TTFB (Next.js) | < 200ms | < 800ms |

## Manual measurement

```bash
# Response time + size
curl -w "\nTime: %{time_total}s Size: %{size_download} bytes\n" -o NUL -s "http://127.0.0.1:8002/api/v1/products?per_page=24"

# Second hit (Redis warm)
curl -w "\nTime: %{time_total}s\n" -o NUL -s "http://127.0.0.1:8002/api/v1/products?per_page=24"
```

## Architecture

```
Next.js ISR → Laravel API → Redis → PostgreSQL / Meilisearch
Sync job → PostgreSQL → queue reindex → Meilisearch + cache bust
```

## Optimization layers implemented

1. **ProductCardResource** — slim list/search payload (no full description, no all images)
2. **Partial PostgreSQL indexes** — category/brand browse, attribute filters, pg_trgm
3. **ProductReadCache** — Redis tagged cache for list/detail/filters
4. **Meilisearch** — extended index document + filterable/sortable attributes
5. **Next.js** — loading skeletons, search ISR, lazy cart/filters, server ProductImage
6. **Sync decoupling** — batch writes without per-row Scout indexing; reindex at end
7. **B2B** — slim cart product DTO, order list summaries (no line items), checkout batch `lockForUpdate`, client cache invalidation on cart mutate
8. **Security hardening** — httpOnly cart cookies, stripped public product metadata, minimal order-tracking payloads

## Dependency audits

```bash
cd backend && composer audit
cd frontend && npm audit --omit=dev
```

Add both to CI or run before each production deploy (see `docs/PRODUCTION-CHECKLIST.md`).

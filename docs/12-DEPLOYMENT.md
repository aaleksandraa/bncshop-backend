# 12 — Deployment

Detaljno uputstvo za VPS + Plesk: **[13-SERVER-DEPLOYMENT-PLESK.md](./13-SERVER-DEPLOYMENT-PLESK.md)**

GitHub repozitoriji:
- Backend (ovaj repo): https://github.com/aaleksandraa/bncshop-backend
- Frontend: https://github.com/aaleksandraa/bncshop-frontend

## Requirements

- PHP 8.2+ (8.3 recommended)
- PostgreSQL 16
- Redis 7
- Meilisearch 1.6+
- Node 20+ (frontend build — odvojeni repo)
- Composer 2.x

## Environment variables

### Backend (.env)

```env
APP_NAME="BNC Webshop"
APP_ENV=production
APP_URL=https://api.bncshop.ba
FRONTEND_URL=https://bncshop.ba

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_DATABASE=bncshop
DB_USERNAME=bncshop
DB_PASSWORD=

REDIS_HOST=127.0.0.1
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_ENCRYPT=true

SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=

MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587

SANCTUM_STATEFUL_DOMAINS=bncshop.ba,localhost:3000
SANCTUM_TOKEN_EXPIRATION=43200
SESSION_DOMAIN=.bncshop.ba

SENTRY_LARAVEL_DSN=
SENTRY_TRACES_SAMPLE_RATE=0.1
```

See also [env.production.example.md](./env.production.example.md) for the full production template.

### Frontend (.env.local) — [bncshop-frontend](https://github.com/aaleksandraa/bncshop-frontend)

```env
NEXT_PUBLIC_API_URL=https://api.bncshop.ba/api/v1
NEXT_PUBLIC_SITE_URL=https://bncshop.ba
```

## Docker Compose (development)

```bash
cd docker && docker compose up -d
composer install
php artisan migrate --seed
php artisan horizon
```

Services: postgres, redis, meilisearch, mailpit

## Deploy script

Backend (ovaj repo):

```bash
bash scripts/deploy-production.sh
```

Frontend (odvojeni repo):

```bash
bash scripts/deploy-production.sh
```

## CI/CD

1. Run PHPUnit + Pint (backend repo)
2. Build frontend `npm run build` (frontend repo)
3. `php artisan migrate --force`
4. `php artisan scout:import "App\Models\Product"`
5. Restart Horizon

## Scheduled tasks (cron)

```
* * * * * php artisan schedule:run
```

Schedule:
- `horizon:snapshot` — every 5 minutes (Horizon metrics)
- `bnc:sync-scheduled` — every 5 minutes (A1 inkrementalni sync, ModifiedAfter)
- `bnc:sync-eline-scheduled` — daily at `ELINE_SYNC_TIMES` (default 06:00, 18:00)
- `bnc:sync-olx-scheduled` — daily at `OLX_SYNC_TIMES` (default 06:00, 18:00)
- `analytics:aggregate-daily` — daily 00:05
- `bnc:loyalty-expire-points` — daily 01:00
- `sitemap:generate` — daily 02:00

Sync/eLine/OLX scheduled commands **dispatch** jobs to the `sync` queue. Cron alone does not process them — Horizon must be running.

## Queue workers (Horizon)

Horizon (`php artisan horizon`) must supervise all queues used by the app:

| Queue | Purpose |
|-------|---------|
| `sync` | A1 / eLine / OLX sync jobs (timeout up to 7200s) |
| `default` | Emails, general jobs |
| `scout` | Meilisearch reindex |
| `analytics` | Analytics event tracking |

Configuration: [config/horizon.php](../config/horizon.php) — `supervisor-sync` + `supervisor-general`.

Supervisor template: [deploy/supervisor-horizon.conf](../deploy/supervisor-horizon.conf)

After deploy: `php artisan horizon:terminate`

Sync diagnostics:

```bash
php artisan bnc:sync-diagnose
```

## Monitoring

- `/api/v1/health` — DB, Redis, Meilisearch checks
- Horizon dashboard at `/horizon` (admin only)
- Sentry DSN in production .env

## Initial setup commands

See [13-SERVER-DEPLOYMENT-PLESK.md](./13-SERVER-DEPLOYMENT-PLESK.md) for the full first-deploy sequence.

```bash
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan make:filament-user
php artisan bnc:sync-full --source=1  # first import (requires Horizon on sync queue)
php artisan scout:import "App\Models\Product"
```

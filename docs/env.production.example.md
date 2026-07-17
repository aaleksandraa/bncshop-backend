# Production environment template

Copy these values to your production `.env`. Never commit real secrets.

```env
APP_NAME="BNC Webshop"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.bncshop.ba
FRONTEND_URL=https://bncshop.ba

LOG_CHANNEL=stack
LOG_LEVEL=warning

# Behind nginx/Cloudflare — list actual proxy IPs, never use * in production
TRUSTED_PROXIES=10.0.0.1

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=bncshop
DB_USERNAME=bncshop
DB_PASSWORD=

SESSION_DRIVER=redis
SESSION_ENCRYPT=true
SESSION_LIFETIME=120
SESSION_DOMAIN=.bncshop.ba
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

CACHE_STORE=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

SCOUT_DRIVER=meilisearch
SCOUT_QUEUE=true
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=

MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=narudzbe@bncshop.ba
MAIL_FROM_NAME="${APP_NAME}"

SANCTUM_STATEFUL_DOMAINS=bncshop.ba,www.bncshop.ba
SANCTUM_TOKEN_EXPIRATION=43200

CORS_ALLOWED_ORIGINS=https://bncshop.ba,https://www.bncshop.ba

# Bot protection — REQUIRED in production (login, register, B2B auth, checkout)
TURNSTILE_ENABLED=true
TURNSTILE_SITE_KEY=
TURNSTILE_SECRET_KEY=

A1_API_USERNAME=
A1_API_PASSWORD=
A1_API_VERIFY_SSL=true

ELINE_API_TOKEN=
ELINE_API_VERIFY_SSL=true

SENTRY_LARAVEL_DSN=
SENTRY_TRACES_SAMPLE_RATE=0.1
```

Run after deploy:

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan horizon:terminate
php artisan scout:import "App\Models\Product"
```

Verify Redis tag cache is active:

```bash
php artisan tinker --execute="echo app(\App\Services\Catalog\ProductReadCache::class)->supportsTags() ? 'tags ok' : 'tags disabled';"
```

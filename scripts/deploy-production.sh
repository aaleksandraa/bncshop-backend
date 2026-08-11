#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

echo "==> Deploying BNC Webshop backend"

cd "${ROOT_DIR}"

composer install --no-dev --optimize-autoloader --no-interaction

composer audit || echo "WARNING: composer audit reported vulnerabilities"

php artisan migrate --force
php artisan storage:link --force

if grep -q '^APP_ENV=local' .env 2>/dev/null; then
  echo "ERROR: APP_ENV=local on production — aborting deploy"
  exit 1
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

if [[ "${CACHE_STORE:-}" != "redis" ]]; then
  echo "WARNING: CACHE_STORE is not redis — tagged product cache invalidation will not work."
fi

php artisan scout:import "App\Models\Product" || echo "WARNING: scout:import failed"

php artisan test || echo "WARNING: test suite failed"

if php artisan list | grep -q "horizon:terminate"; then
  php artisan horizon:terminate || true
fi

if command -v k6 >/dev/null 2>&1; then
  k6 run "${ROOT_DIR}/scripts/load-test/k6-browse-checkout.js" || echo "WARNING: k6 load test failed"
else
  echo "NOTE: k6 not installed — skipping load test"
fi

echo "==> Health check"
curl -fsS "${APP_URL:-http://127.0.0.1:8000}/api/v1/health" >/dev/null || echo "WARNING: health check failed"

HORIZON_CODE="$(curl -s -o /dev/null -w "%{http_code}" "${APP_URL:-http://127.0.0.1:8000}/horizon/api/stats")"
if [[ "${HORIZON_CODE}" != "401" && "${HORIZON_CODE}" != "403" ]]; then
  echo "WARNING: Horizon is publicly accessible (HTTP ${HORIZON_CODE}) — check APP_ENV and HorizonServiceProvider"
fi

echo "==> Backend deploy complete"

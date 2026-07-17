#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

echo "==> Deploying BNC Webshop backend"

cd "${ROOT_DIR}"

composer install --no-dev --optimize-autoloader --no-interaction

composer audit || echo "WARNING: composer audit reported vulnerabilities"

php artisan migrate --force
php artisan storage:link --force
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

echo "==> Backend deploy complete"

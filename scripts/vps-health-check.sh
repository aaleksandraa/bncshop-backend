#!/usr/bin/env bash
# BNC Shop — production VPS health & performance snapshot
# Run as root on the server after deploy or when investigating slowness.
set -euo pipefail

BACKEND_ROOT="${BACKEND_ROOT:-/var/www/vhosts/bncshop.ba/api.bncshop.ba}"
FRONTEND_ROOT="${FRONTEND_ROOT:-/var/www/vhosts/bncshop.ba/httpdocs}"
PHP_BIN="${PHP_BIN:-/opt/plesk/php/8.3/bin/php}"
PRODUCT_SLUG="${PRODUCT_SLUG:-dell-se2425hm}"

section() {
  echo
  echo "=== $1 ==="
}

section "System"
uptime
free -h | head -2

section "Supervisor"
if command -v supervisorctl >/dev/null 2>&1; then
  supervisorctl status bncshop-horizon bncshop-scheduler 2>/dev/null || supervisorctl status
else
  echo "supervisorctl not found"
fi

section "Redis"
if command -v redis-cli >/dev/null 2>&1; then
  redis-cli ping
  echo -n "product cache keys: "
  redis-cli KEYS "*product:slug*" 2>/dev/null | wc -l
else
  echo "redis-cli not found"
fi

section "Backend (.env cache driver)"
if [[ -d "${BACKEND_ROOT}" ]]; then
  grep -E "^CACHE_STORE=|^QUEUE_CONNECTION=|^REDIS_" "${BACKEND_ROOT}/.env" 2>/dev/null || echo "Cannot read .env"
  cd "${BACKEND_ROOT}"
  "${PHP_BIN}" artisan bnc:perf-check || true
else
  echo "Backend not found: ${BACKEND_ROOT}"
fi

section "API latency (direct api.bncshop.ba)"
if command -v curl >/dev/null 2>&1; then
  curl -s -o /dev/null -w "layout/shell: %{time_total}s\n" \
    "https://api.bncshop.ba/api/v1/layout/shell"
  curl -s -o /dev/null -w "product 1st: %{time_total}s\n" \
    "https://api.bncshop.ba/api/v1/products/${PRODUCT_SLUG}"
  curl -s -o /dev/null -w "product 2nd: %{time_total}s\n" \
    "https://api.bncshop.ba/api/v1/products/${PRODUCT_SLUG}"
  curl -s -o /dev/null -w "storefront home: %{time_total}s\n" \
    "https://bncshop.ba/"
  curl -s -o /dev/null -w "storefront cart: %{time_total}s\n" \
    -H "Accept: application/json" \
    "https://bncshop.ba/backend-api/v1/cart"
fi

section "Process top CPU"
ps aux --sort=-%cpu | head -10

section "Done"
echo "Targets: load(1m) < 2 idle, product API 2nd curl < 0.1s, Redis PONG, Horizon+scheduler RUNNING"

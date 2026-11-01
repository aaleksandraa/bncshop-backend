#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT}"

PHP="${PHP:-php}"

if ! grep -q 'applyUnder100CharmPrice' app/Services/Pricing/PriceCalculator.php; then
  echo "FAIL: PriceCalculator.php on disk is missing A1 charm pricing (applyUnder100CharmPrice)."
  echo "      Expected ~460 lines; run: git pull origin main"
  exit 1
fi

LINES="$(wc -l < app/Services/Pricing/PriceCalculator.php | tr -d ' ')"
if [[ "${LINES}" -lt 400 ]]; then
  echo "FAIL: PriceCalculator.php looks too short (${LINES} lines). Deploy may be stale."
  exit 1
fi

UNDER="$("${PHP}" artisan tinker --execute="echo app(\App\Services\Pricing\PriceCalculator::class)->applyUnder100CharmPrice(37.40);" 2>/dev/null | tail -1)"
OVER="$("${PHP}" artisan tinker --execute="echo app(\App\Services\Pricing\PriceCalculator::class)->applyCharmPrice(1284);" 2>/dev/null | tail -1)"

if [[ "${UNDER}" != "36.9" && "${UNDER}" != "36.90" ]]; then
  echo "FAIL: applyUnder100CharmPrice(37.40) expected 36.9, got: ${UNDER}"
  exit 1
fi

if [[ "${OVER}" != "1279" ]]; then
  echo "FAIL: applyCharmPrice(1284) expected 1279, got: ${OVER}"
  exit 1
fi

echo "OK: A1 charm pricing is loaded (under-100 .90 and over-100 decade rule)."

# Load test — 200 concurrent korisnika

Zahtijeva [k6](https://k6.io/docs/get-started/installation/).

## Pokretanje

```bash
# API + frontend moraju biti pokrenuti
k6 run scripts/load-test/k6-browse-checkout.js

# Prilagođeni URL-ovi
API_URL=https://api.bncshop.ba/api/v1 FRONTEND_URL=https://bncshop.ba k6 run scripts/load-test/k6-browse-checkout.js
```

## Scenariji

| Scenarij | Opterecenje | Cilj p95 |
|----------|-------------|----------|
| `browseCatalog` | 200 VU, 2 min | PLP < 500ms, search < 200ms |
| `checkoutFlow` | 20 req/min | Cart add < 300ms |
| `browseB2bCatalog` | 20 VU, 1 min | B2B products < 400ms, cart < 300ms |

### B2B browse/cart

```bash
# Potreban laravel_session cookie od ulogovanog B2B kupca
B2B_SESSION=... B2B_XSRF=... k6 run scripts/load-test/k6-b2b-browse-cart.js
```

## Napomene

- Checkout scenarij koristi `pickup` shipping i ne završava punu narudžbu (izbjegava spam narudžbi u produkciji).
- Za puni checkout test koristite staging okruženje sa test proizvodima.
- B2B scenarij ne kreira narudžbe — samo čita katalog, korpu i shipping quote.

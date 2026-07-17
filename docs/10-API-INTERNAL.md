# 10 — Internal REST API (v1)

Base: `/api/v1`

## Public (no auth)

```
GET  /health
GET  /categories
GET  /categories/{slug}
GET  /products
GET  /products/{slug}
GET  /manufacturers
GET  /manufacturers/{slug}
GET  /search?q=&filters[]=
GET  /filters/{categorySlug}
GET  /sitemap
GET  /redirects
GET  /settings/public
POST /analytics/events
```

## Cart (session/token)

```
GET    /cart
POST   /cart/items
PATCH  /cart/items/{id}
DELETE /cart/items/{id}
POST   /cart/coupon
DELETE /cart/coupon
POST   /cart/validate-prices
```

## Checkout

```
POST /checkout/shipping-quote
POST /checkout
GET  /orders/track/{token}
```

## Customer auth (Sanctum)

```
POST /customer/register
POST /customer/login
POST /customer/logout
GET  /customer/me
GET  /customer/orders
PUT  /customer/profile
```

## Admin sync (Sanctum + permission)

```
GET  /admin/sync/status
POST /admin/sync/run
GET  /admin/sync/jobs
GET  /admin/sync/jobs/{id}
POST /admin/sync/test-connection
```

## Response format

```json
{
  "data": {},
  "meta": { "pagination": {} },
  "errors": []
}
```

## Products list query params

- `category`, `brand`, `q`, `sort`, `page`, `per_page`
- `filters[attribute_id]=value`
- `min_price`, `max_price`, `in_stock`, `on_sale`, `is_gaming`

OpenAPI spec: `backend/storage/api-docs/openapi.yaml`

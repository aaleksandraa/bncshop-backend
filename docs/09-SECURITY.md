# 09 — Security

## Authentication

- **Admin**: Filament session auth + optional 2FA
- **Customer API**: Sanctum token (SPA cookie or Bearer)
- **Public API**: rate limited, no auth for catalog read

## Authorization

Spatie permissions on all admin resources and sensitive API endpoints.

## Data protection

- API credentials: Laravel `encrypted` cast
- Passwords: bcrypt
- PII in orders: access logged

## Input validation

- Form Request classes on all API endpoints
- HTMLPurifier on product descriptions
- File upload: whitelist MIME, max 5MB

## Rate limiting

| Route | Limit |
|-------|-------|
| POST /auth/login | 5/min |
| POST /checkout | 10/min |
| GET /products | 120/min |
| Admin | 300/min |

## Headers (production nginx)

```
X-Frame-Options: SAMEORIGIN
X-Content-Type-Options: nosniff
Strict-Transport-Security: max-age=31536000
Content-Security-Policy: default-src 'self'; ...
```

## Audit

All admin mutations via spatie/laravel-activitylog.
Sync jobs and order status changes logged.

## Threat model

| Threat | Mitigation |
|--------|------------|
| SQL injection | Eloquent parameterized queries |
| XSS | Output escaping + HTMLPurifier |
| CSRF | Sanctum CSRF for SPA |
| Token theft | HTTP-only cookies, short-lived tokens |
| Mass assignment | $fillable guarded models |
| IDOR | Policy checks on orders/cart |

## Backup

- Daily pg_dump encrypted
- Media backup weekly
- Restore tested monthly

## Monitoring

- Sentry for exceptions
- Horizon for failed jobs alert
- Health endpoint `/api/health`

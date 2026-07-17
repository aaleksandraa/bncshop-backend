# 01 — Arhitektura BNC Webshop platforme

## Pregled

BNC Webshop je API-first ecommerce platforma koja zamjenjuje WordPress/WooCommerce. Sastoji se od tri glavne komponente:

1. **Laravel Backend** — REST API, business logika, queue jobovi, email
2. **Filament Admin** — interni panel za upravljanje katalogom, narudžbama, analitikom
3. **Next.js Storefront** — javni webshop (SSR/ISR za SEO)

## Tech stack

| Komponenta | Tehnologija | Verzija |
|------------|-------------|---------|
| Backend | Laravel | 11.x |
| Admin | Filament | 3.x |
| Frontend | Next.js App Router | 15.x |
| Baza | PostgreSQL | 16 |
| Cache/Queue | Redis | 7 |
| Search | Meilisearch + Scout | latest |
| Auth | Laravel Sanctum | 4.x |
| Permissions | spatie/laravel-permission | 6.x |
| Audit | spatie/laravel-activitylog | 4.x |

## GitHub repozitoriji

Platforma je podijeljena u dva repoa:

| Repo | URL | Sadržaj |
|------|-----|---------|
| **Backend** | https://github.com/aaleksandraa/bncshop-backend | Laravel API, Filament, docs, docker, deploy |
| **Frontend** | https://github.com/aaleksandraa/bncshop-frontend | Next.js storefront |

### Backend repo struktura

```
bncshop-backend/
├── app/                  # Laravel aplikacija
├── config/
├── docs/                 # Tehnička dokumentacija
├── docker/               # docker-compose.yml (dev servisi)
├── deploy/               # Supervisor/systemd templatei
├── json-api-za-import/   # JSON uzorci za lokalni import
├── scripts/              # Deploy i load test skripte
└── public/               # Document root (api.bncshop.ba)
```

### Server layout (produkcija)

```
/var/www/vhosts/bncshop.ba/
├── bncshop-backend/      → api.bncshop.ba (public/)
└── bncshop-frontend/     → bncshop.ba (Node.js)
```

## Data flow

```
Source API → Sync Jobs → PostgreSQL → Meilisearch
                              ↓
                    Laravel REST API
                         ↓         ↓
                   Next.js     Filament Admin
```

## Deployment

- **Backend**: PHP 8.3+, Nginx, PHP-FPM, Supervisor (Horizon)
- **Frontend**: Node 20+, standalone Next.js build (odvojeni repo)
- **Services**: PostgreSQL, Redis, Meilisearch (Docker ili managed)
- **Storage**: S3-compatible (Contabo/CDN URLs za API slike)

## Environment varijable

Vidi [12-DEPLOYMENT.md](./12-DEPLOYMENT.md) za kompletan popis.

# BNC Webshop — Backend

Laravel API + Filament admin za BNC Webshop platformu.

Frontend repo: https://github.com/aaleksandraa/bncshop-frontend

## Struktura

```
├── app/                  Laravel aplikacija
├── docs/                 Tehnička dokumentacija
├── docker/               PostgreSQL, Redis, Meilisearch (dev)
├── deploy/               Supervisor/systemd za Horizon
├── json-api-za-import/   JSON uzorci za lokalni import
├── scripts/              Deploy i load test
└── public/               Document root (api.bncshop.ba)
```

## Brzi start (development)

### 1. Docker servisi

```bash
cd docker
docker compose up -d
```

### 2. Backend setup

```bash
cp .env.example .env
# Podesite DB, Redis, Meilisearch
composer install
php artisan migrate --seed
php artisan bnc:import-json-samples
php artisan make:filament-user
php artisan horizon
```

Admin panel: http://localhost:8000/admin

API: http://localhost:8000/api/v1

### 3. Frontend (odvojeni repo)

```bash
git clone https://github.com/aaleksandraa/bncshop-frontend.git
cd bncshop-frontend
cp .env.example .env.local
npm ci
npm run dev
```

## Ključne komande

| Komanda | Opis |
|---------|------|
| `php artisan bnc:sync-full` | Pun API import |
| `php artisan bnc:sync-incremental` | Inkrementalni sync |
| `php artisan bnc:import-json-samples` | Import iz `json-api-za-import/` |
| `php artisan scout:import "App\Models\Product"` | Meilisearch indeks |
| `php artisan horizon` | Queue worker |

## Produkcija

- Deploy runbook: [docs/13-SERVER-DEPLOYMENT-PLESK.md](docs/13-SERVER-DEPLOYMENT-PLESK.md)
- Env template: [docs/env.production.example.md](docs/env.production.example.md)
- Checklist: [docs/PRODUCTION-CHECKLIST.md](docs/PRODUCTION-CHECKLIST.md)

```bash
bash scripts/deploy-production.sh
```

## Domene (produkcija)

| URL | Svrha |
|-----|-------|
| `https://api.bncshop.ba` | REST API |
| `https://api.bncshop.ba/admin` | Filament admin |
| `https://api.bncshop.ba/horizon` | Queue dashboard |
| `https://bncshop.ba` | Frontend (drugi repo) |

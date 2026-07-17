# 13 — Server deployment (VPS + Plesk)

Operativni runbook za prvi deploy i svaki naredni release BNC Webshop platforme na VPS-u sa Plesk-om.

Za kratak pregled vidi [12-DEPLOYMENT.md](./12-DEPLOYMENT.md). Za produkcijski `.env` template vidi [env.production.example.md](./env.production.example.md).

---

## Pregled arhitekture

```
Kupac / Admin
      │
      ▼
┌─────────────────────────────────────────────────────────┐
│  Plesk VPS                                              │
│  ┌─────────────────────────┐  ┌─────────────────────┐ │
│  │ api.bncshop.ba          │  │ bncshop.ba          │ │
│  │ Laravel (public/)       │  │ Next.js storefront  │ │
│  │ /api/v1/*  REST API     │  │ npm run start       │ │
│  │ /admin     Filament     │  │                     │ │
│  │ /horizon   queue dash   │  │                     │ │
│  └───────────┬─────────────┘  └──────────┬──────────┘ │
│              │                           │            │
│  ┌───────────┴───────────────────────────┴──────────┐ │
│  │  PostgreSQL 16  │  Redis 7  │  Meilisearch 1.6+  │ │
│  └──────────────────────────────────────────────────┘ │
│                                                         │
│  Plesk Scheduled Task → php artisan schedule:run (cron) │
│  Supervisor/systemd   → php artisan horizon (queues)    │
└─────────────────────────────────────────────────────────┘
```

**Domene (2 vhost-a):**

| Domena | Svrha | Document root / app |
|--------|-------|---------------------|
| `api.bncshop.ba` | Laravel backend: API (`/api/v1`), Filament admin (`/admin`), Horizon (`/horizon`) | `bncshop-backend/public` |
| `bncshop.ba` | Javni webshop | Plesk Node.js → `bncshop-frontend` |

---

## GitHub repozitoriji

Klonirati oba repoa na server (sibling folderi):

```bash
cd /var/www/vhosts/bncshop.ba
git clone https://github.com/aaleksandraa/bncshop-backend.git
git clone https://github.com/aaleksandraa/bncshop-frontend.git
```

| Repo | URL |
|------|-----|
| Backend | https://github.com/aaleksandraa/bncshop-backend |
| Frontend | https://github.com/aaleksandraa/bncshop-frontend |

---

## Preduvjeti

### Softver na VPS-u

| Komponenta | Verzija | Napomena |
|------------|---------|----------|
| PHP | 8.2+ (8.3 preporučeno) | Ekstenzije: `pgsql`, `redis`, `mbstring`, `xml`, `curl`, `zip`, `bcmath`, `intl` |
| Composer | 2.x | |
| Node.js | 20+ | Plesk Node.js extension |
| PostgreSQL | 16 | |
| Redis | 7 | Obavezan za cache, session, queue |
| Meilisearch | 1.6+ | Scout pretraga |
| SSH | | Za Horizon/Supervisor i deploy komande |

### Pristup

- Plesk panel za vhost, PHP, Node.js, Scheduled Tasks
- SSH terminal za `artisan`, Composer, Horizon, deploy skriptu

---

## Cron vs Horizon — obavezno razumjeti

Ovo su **dva odvojena procesa**. Horizon **ne može** zamijeniti cron.

| Proces | Šta radi | Kako se pokreće |
|--------|----------|-----------------|
| **Laravel Scheduler** | Pokreće zakazane Artisan komande | Plesk cron: `* * * * * php artisan schedule:run` |
| **Horizon** | Obrađuje jobove iz Redis queue redova | Supervisor/systemd: `php artisan horizon` |

### Scheduler komande (`routes/console.php`)

| Komanda | Raspored | Tip |
|---------|----------|-----|
| `bnc:sync-scheduled` | svakih 5 min | **Dispatch** job na `sync` red |
| `bnc:sync-eline-scheduled` | dnevno (`ELINE_SYNC_TIMES`, default 06:00, 18:00) | **Dispatch** job na `sync` red |
| `bnc:sync-olx-scheduled` | dnevno (`OLX_SYNC_TIMES`, default 06:00, 18:00) | **Dispatch** job na `sync` red (samo ako je OLX auto-sync uključen) |
| `horizon:snapshot` | svakih 5 min | Horizon metrike (sinhrono u cron procesu) |
| `analytics:aggregate-daily` | 00:05 | Sinhrono u cron procesu |
| `bnc:loyalty-expire-points` | 01:00 | Sinhrono u cron procesu |
| `sitemap:generate` | 02:00 | Sinhrono u cron procesu |

Sync komande **ne izvršavaju** import direktno — samo stavljaju job u Redis. Bez Horizon-a job ostaje neobrađen.

### Queue redovi (obrađuje Horizon)

| Red | Sadržaj | Timeout |
|-----|---------|---------|
| `sync` | A1 / eLine / OLX sync jobovi | do 7200 s (2 h) |
| `default` | Emailovi (narudžbe, B2B, loyalty), ostalo | 120 s |
| `scout` | Meilisearch reindex (`ReindexProductsJob`) | 120 s |
| `analytics` | `TrackAnalyticsEventJob` | 120 s |

Scout automatski sync (`SCOUT_QUEUE=true`) ide u queue pri promjeni proizvoda.

**Produkcija zahtijeva oba:**

1. Plesk Scheduled Task → `schedule:run` svake minute
2. Horizon daemon → obrađuje `sync`, `default`, `scout`, `analytics`

---

## Instalacija servisa (PostgreSQL, Redis, Meilisearch)

Referenca za dev: [docker/docker-compose.yml](../docker/docker-compose.yml)

### Varijanta A — ručna instalacija / Plesk ekstenzije

Instalirati PostgreSQL 16, Redis 7 i Meilisearch na istom VPS-u. Aplikacija u `.env` koristi `127.0.0.1` i standardne portove:

```env
DB_HOST=127.0.0.1
DB_PORT=5432
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=<jaki-master-key>
```

**Redis** — u `redis.conf` postaviti:

```
maxmemory-policy allkeys-lru
```

**Meilisearch** — pokrenuti sa master key-em (ne ostavljati prazan u produkciji):

```bash
export MEILI_MASTER_KEY="<jaki-key>"
meilisearch --db-path /var/lib/meilisearch/data
```

Isti key staviti u backend `.env` kao `MEILISEARCH_KEY`.

### Varijanta B — Docker samo za servise

Aplikacija ostaje u Plesk-u; servisi u Docker-u na localhost portovima:

```bash
cd docker
docker compose up -d postgres redis meilisearch
```

Backend `.env` ostaje na `127.0.0.1:5432`, `6379`, `7700`.

---

## Plesk — backend (Laravel)

### 1. Kreiranje vhost-a

1. Plesk → Domains → Add Subdomain `api.bncshop.ba`
2. **Document root:** `{abs_path}/bncshop-backend/public` — **ne** root repoa
3. PHP handler: 8.3
4. SSL: Let's Encrypt

Filament admin panel: `https://api.bncshop.ba/admin` (nema odvojene `admin.*` domene).

### 2. Permisije

SSH:

```bash
cd /var/www/vhosts/bncshop.ba/bncshop-backend
chown -R <plesk-user>:psacln storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache
```

### 3. Environment (`.env`)

**Plesk Laravel extension:** ne kopiraj `.env.example` direktno — sadrži dev varijable (`ADMIN_NAME`, `SELLER_NAME`) koje lome parser ako nisu u navodnicima.

Koristi produkcijski template:

```bash
cp .env.production.example .env
php artisan key:generate
```

Ili kopiraj vrijednosti iz [env.production.example.md](./env.production.example.md).

**Pravilo:** svaka vrijednost sa razmakom mora imati dvostruke navodnike:

```env
# ISPRAVNO
APP_NAME="BNC Webshop"
ADMIN_NAME="BNC Admin"

# POGREŠNO — Laravel dotenv parser pada
APP_NAME=BNC Webshop
ADMIN_NAME=BNC Admin
```

U produkciji **ukloni** dev-only linije ako postoje: `ADMIN_NAME`, `SELLER_NAME`, `ADMIN_PASSWORD`, `SELLER_PASSWORD` (admin kreira se sa `make:filament-user`).

**Obavezno u produkciji:**

```env
APP_NAME="BNC Webshop"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.bncshop.ba
FRONTEND_URL=https://bncshop.ba

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_ENCRYPT=true
SESSION_DOMAIN=.bncshop.ba
SESSION_SECURE_COOKIE=true

SCOUT_DRIVER=meilisearch
SCOUT_QUEUE=true
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=

TRUSTED_PROXIES=<IP reverse proxy-ja u Plesk-u>
SANCTUM_STATEFUL_DOMAINS=bncshop.ba,www.bncshop.ba
CORS_ALLOWED_ORIGINS=https://bncshop.ba,https://www.bncshop.ba

TURNSTILE_ENABLED=true
A1_API_USERNAME=
A1_API_PASSWORD=
ELINE_API_TOKEN=
```

Tagged product cache **ne radi** bez `CACHE_STORE=redis`.

---

## Plesk — frontend (Next.js)

1. Odvojeni vhost `bncshop.ba`
2. Plesk → Node.js → Enable Node.js
3. Application root: `{repo}/frontend`
4. Application startup file: `node_modules/next/dist/bin/next` ili npm script
5. Run script: `start` (pokreće `next start` nakon builda)

Kreirati `frontend/.env.local`:

```env
NEXT_PUBLIC_API_URL=https://api.bncshop.ba/api/v1
NEXT_PUBLIC_SITE_URL=https://bncshop.ba
```

Build prije starta:

```bash
cd frontend
npm ci
npm run build
```

---

## Prvi deploy — tačan redoslijed komandi

SSH u `bncshop-backend/` direktorij. Izvršiti **jednom** pri inicijalnom setupu.

### Korak 1: Dependencies i baza

```bash
composer install --no-dev --optimize-autoloader --no-interaction

# Ako .env ne postoji:
cp .env.example .env
# Ručno popuniti sve produkcijske vrijednosti, pa:
php artisan key:generate

php artisan migrate --force
php artisan storage:link --force
```

### Korak 2: Seed (produkcija — selektivno)

**Ne koristiti** `php artisan migrate --seed` u produkciji — `UsersSeeder` se preskače, ali ostali seeders treba pokrenuti eksplicitno:

```bash
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan db:seed --class=SystemSettingsSeeder
php artisan db:seed --class=EmailTemplatesSeeder
php artisan db:seed --class=ApiSourceSeeder
php artisan db:seed --class=MenuSeeder
php artisan db:seed --class=B2bSeeder
```

### Korak 3: Admin korisnik

```bash
php artisan make:filament-user
```

Interaktivno unijeti ime, email i lozinku za Filament admina.

### Korak 4: Cache

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Korak 5: Pokrenuti Horizon (prije sync-a)

Horizon mora raditi **prije** punog importa jer sync ide na queue.

```bash
# Privremeno u SSH (ili odmah postaviti Supervisor — vidi sekciju ispod)
php artisan horizon
```

### Korak 6: Prvi puni import kataloga

```bash
php artisan bnc:sync-full --source=1
```

Job ide na `sync` red. Prati Horizon dashboard (`/horizon`) ili:

```bash
php artisan bnc:sync-diagnose
```

Import 17k+ proizvoda može trajati satima.

### Korak 7: Meilisearch indeks

Nakon što sync završi (ili paralelno ako Scout queue radi):

```bash
php artisan scout:import "App\Models\Product"
```

### Korak 8: Frontend (odvojeni repo)

```bash
cd /var/www/vhosts/bncshop.ba/bncshop-frontend
npm ci
npm run build
# Plesk Node.js pokreće npm run start, ili ručno:
npm run start
```

---

## Horizon — pokretanje na Plesk VPS-u

Plesk **ne pokreće** Horizon automatski. Potreban je Supervisor ili systemd preko SSH.

### Supervisor (preporučeno)

Kopirati template iz [deploy/supervisor-horizon.conf](../deploy/supervisor-horizon.conf) i prilagoditi putanje:

```bash
sudo cp deploy/supervisor-horizon.conf /etc/supervisor/conf.d/bncshop-horizon.conf
# Uredi putanje: command, user, stdout_logfile
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start bncshop-horizon
```

Provjera:

```bash
sudo supervisorctl status bncshop-horizon
php artisan horizon:status
```

### Nakon svakog deploya

Deploy skripta automatski radi:

```bash
php artisan horizon:terminate
```

Supervisor će ponovo pokrenuti Horizon sa novim kodom.

### Horizon dashboard

URL: `https://api.bncshop.ba/horizon`

Pristup: samo autentificirani korisnici sa permisijom `manage_sync`.

---

## Cron — Plesk Scheduled Tasks

Plesk → Domains → Scheduled Tasks → Add Task:

| Polje | Vrijednost |
|-------|------------|
| Task type | Run a command |
| Run | Every minute |
| Command | `cd /var/www/vhosts/bncshop.ba/bncshop-backend && /usr/bin/php artisan schedule:run >> /dev/null 2>&1` |

Zamijeniti putanju stvarnom lokacijom backend repoa na serveru.

### Provjera schedulera

```bash
php artisan schedule:list
php artisan schedule:test --name="bnc:sync-scheduled"
```

---

## Svaki naredni deploy (release)

**Backend** (u `bncshop-backend/`):

```bash
git pull
bash scripts/deploy-production.sh
```

**Frontend** (u `bncshop-frontend/`):

```bash
git pull
bash scripts/deploy-production.sh
```

Backend skripta radi:

1. `composer install --no-dev`
2. `php artisan migrate --force`
3. `php artisan storage:link --force`
4. `php artisan config:cache`, `route:cache`, `view:cache`
5. `php artisan scout:import "App\Models\Product"`
6. `php artisan horizon:terminate`
7. Health check na `APP_URL/api/v1/health`

Frontend skripta radi: `npm ci`, `npm run build`, health check na `NEXT_PUBLIC_SITE_URL`.

**Skripte ne rade:** seed, full sync, kreiranje admin korisnika.

---

## Verifikacija nakon deploya

```bash
cd bncshop-backend

php artisan bnc:health
php artisan bnc:sync-diagnose
php artisan schedule:list
php artisan horizon:status

curl -fsS https://api.bncshop.ba/api/v1/health

php artisan tinker --execute="echo app(\App\Services\Catalog\ProductReadCache::class)->supportsTags() ? 'tags ok' : 'tags disabled';"
```

Health endpoint (`/api/v1/health`) provjerava PostgreSQL, Redis i Meilisearch.

---

## Troubleshooting

| Simptom | Uzrok | Rješenje |
|---------|-------|----------|
| `Failed to parse dotenv... unexpected whitespace at [BNC Admin]` | `.env` vrijednost sa razmakom bez navodnika | `APP_NAME="BNC Webshop"`, ukloni ili citiraj `ADMIN_NAME` |
| Plesk "Configuring Laravel" pada odmah | `.env` nevalidan ili nema `APP_KEY` | `cp .env.production.example .env` + `php artisan key:generate` |
| `Connection refused` Redis | Redis nije pokrenut | Instalirati/pokrenuti Redis prije `SESSION_DRIVER=redis` |
| `could not connect to server` PostgreSQL | DB servis ili pogrešni DB_* | Provjeri PostgreSQL i credentials |
| `Class "Redis" not found` | phpredis ekstenzija nedostaje | U Plesk PHP 8.3+ uključiti `redis` ekstenziju |
| Meilisearch health fail | Servis ne radi ili pogrešan key | `MEILISEARCH_HOST` + `MEILISEARCH_KEY` |
| Sync jobovi stoje u queue | Horizon ne sluša `sync` red | Provjeri `config/horizon.php` — mora imati `supervisor-sync` |
| Pretraga prazna / spora | Meilisearch nije indeksiran | `scout:import`, provjeri `MEILISEARCH_HOST` i `MEILISEARCH_KEY` |
| Cache se ne invalidira | `CACHE_STORE` nije redis | Postavi `CACHE_STORE=redis`, restart PHP-FPM |
| Email ne stiže | Queue ili SMTP | Provjeri Horizon na `default` redu, MAIL_* env, failed jobs u Horizon-u |
| Session / login ne radi | Cookie domena | `SESSION_DOMAIN=.bncshop.ba`, `SANCTUM_STATEFUL_DOMAINS` |
| CORS greške | Frontend origin | `CORS_ALLOWED_ORIGINS` mora uključiti frontend URL |
| OLX sync se ne pokreće | Auto sync isključen | Admin → OLX settings + `OLX_*` env varijable |
| Sync zakasnio | Cron ili worker | Provjeri Plesk Scheduled Task + `bnc:sync-diagnose` |
| `tags disabled` | Redis cache | `CACHE_STORE=redis`, phpredis ekstenzija |

---

## Brza referenca komandi

| Situacija | Komanda |
|-----------|---------|
| Prva migracija | `php artisan migrate --force` |
| Produkcijski seed | `php artisan db:seed --class=RolesAndPermissionsSeeder` (+ ostali seeders) |
| Admin nalog | `php artisan make:filament-user` |
| Prvi import | `php artisan bnc:sync-full --source=1` |
| Search indeks | `php artisan scout:import "App\Models\Product"` |
| Deploy release | `bash scripts/deploy-production.sh` |
| Restart workers | `php artisan horizon:terminate` |
| Sync dijagnostika | `php artisan bnc:sync-diagnose` |
| Health | `php artisan bnc:health` |

---

## Povezana dokumentacija

- [12-DEPLOYMENT.md](./12-DEPLOYMENT.md) — kratak pregled
- [PRODUCTION-CHECKLIST.md](./PRODUCTION-CHECKLIST.md) — go-live checklist
- [env.production.example.md](./env.production.example.md) — pun `.env` template
- [performance-setup.md](./performance-setup.md) — Redis / Meilisearch performance

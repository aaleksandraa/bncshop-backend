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
│  │ Laravel (backend/public)│  │ Next.js storefront  │ │
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
| `api.bncshop.ba` | Laravel backend: API (`/api/v1`), Filament admin (`/admin`), Horizon (`/horizon`) | `{repo}/backend/public` |
| `bncshop.ba` | Javni webshop | Plesk Node.js → `{repo}/frontend` |

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

### Scheduler komande (`backend/routes/console.php`)

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
2. **Document root:** `{abs_path}/bncshopweb/backend/public` — **ne** root repoa
3. PHP handler: 8.3
4. SSL: Let's Encrypt

Filament admin panel: `https://api.bncshop.ba/admin` (nema odvojene `admin.*` domene).

### 2. Permisije

SSH:

```bash
cd /var/www/vhosts/example.com/bncshopweb/backend
chown -R <plesk-user>:psacln storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache
```

### 3. Environment (`.env`)

**Plesk Laravel extension:** ne kopiraj `.env.example` direktno — sadrži dev varijable (`ADMIN_NAME`, `SELLER_NAME`) koje lome parser ako nisu u navodnicima.

**Ako deploy pada na `unexpected whitespace at [BNC Admin]`:** na serveru već postoji stari `.env` sa `ADMIN_NAME=BNC Admin` (bez navodnika). Plesk **ne prepisuje** `.env` automatski. Prije redeploya:

```bash
cd /path/to/bncshop-backend
rm -f .env
cp .env.production.example .env
# popuni DB_PASSWORD, MAIL_*, API keys...
php artisan key:generate
```

Ili u Plesk File Manager obriši `.env` pa ponovo pokreni deploy.

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
2. Plesk → Node.js → **Enable Node.js** (dugme mora nestati; app mora biti aktivan)
3. **Application root:** `/httpdocs` (root git repoa, npr. `bncshop-frontend`)
4. **Document root:** `/httpdocs/.next/static` — **samo ako** dodaš nginx rewrite ispod; inače koristi `/httpdocs` (vidi troubleshooting)
5. **Application startup file:** `start.js` (u rootu repoa — ne `index.html`)
6. **Application mode:** `production`
7. **Node.js version:** 24.x ili 26.x (Plesk panel mora odgovarati verziji pri SSH buildu)

Kreirati `.env.local` u application rootu:

```env
BACKEND_URL=https://api.bncshop.ba
NEXT_PUBLIC_API_URL=/backend-api/v1
NEXT_PUBLIC_SITE_URL=https://bncshop.ba
NEXT_PUBLIC_TURNSTILE_SITE_KEY=
```

`BACKEND_URL` + `/backend-api/v1` omogućava Next.js proxy u produkciji (live pretraga, korpa, CSRF bez CORS problema). U Plesk Node.js panelu dodati `BACKEND_URL` i u **Environment variables**.

Alternativa: `NEXT_PUBLIC_API_URL=https://api.bncshop.ba/api/v1` — tada **mora** raditi `CORS_ALLOWED_ORIGINS` na backendu.

Build prije starta — **preporučeno kroz Plesk panel**. SSH build samo kao rezerva.

#### Normalni Plesk deploy (kao prije)

**Domains → bncshop.ba → Node.js** — provjeri postavke:

| Polje | Vrijednost |
|-------|------------|
| Application root | `/httpdocs` |
| Document root | `/httpdocs` |
| Startup file | `start.js` |
| Mode | `production` |

**Svaki deploy:**

1. **Enable Node.js** mora biti aktivan (dugme “Disable” ne smije biti jedina opcija)
2. **Run script:** `build:clean`
3. Sačekaj završetak (3–10 min)
4. **Restart App**
5. Hard refresh u browseru (Ctrl+Shift+R)

**NE radi ovo:**

| Zabranjeno | Zašto |
|------------|-------|
| **Disable Node.js** prije/poslije builda | Gasí nginx proxy → **403 Forbidden** (prazan `#extension nodejs` u httpd.conf) |
| `npm run build` kao **root** preko SSH | `.next/` postane root-owned → Plesk build pada na EACCES |
| `plesk-reset-build-permissions.sh` bez `--clean` kad nema builda | Samo chown — ne pravi build; moraš `build:clean` u Plesk-u |
| `plesk-reset-build-permissions.sh --clean` bez rebuilda | Briše `.next/` → sajt ne može startati |

#### Vraćanje na Plesk nakon SSH eksperimenta

Jednom na serveru (root SSH):

```bash
cd /var/www/vhosts/bncshop.ba/httpdocs
git pull origin main
bash scripts/plesk-restore-frontend.sh
```

Skripta popravi vlasništvo, provjeri `start.js` / `.next/BUILD_ID` / nodejs extension, i ispiše tačan Plesk checklist.

Zatim u panelu: **Enable Node.js → build:clean → Restart App**.

BUILD OK ali curl 403 → Node.js disabled (nginx servira statiku, ne Next app):

```bash
cd /var/www/vhosts/bncshop.ba/httpdocs
git pull origin main
bash scripts/plesk-enable-node.sh
```

Ili u panelu: **Enable Node.js → Restart App** (ne Disable).

Provjera:

```bash
awk '/#extension nodejs begin/,/#extension nodejs end/' /var/www/vhosts/system/bncshop.ba/conf/httpd.conf
pgrep -af next-server
curl -I https://bncshop.ba/
```

Blok `#extension nodejs` ne smije biti prazan — inače je Node disabled.

#### Dozvole (samo ako Plesk build padne na EACCES)

```bash
bash scripts/plesk-reset-build-permissions.sh          # samo chown (build ostaje)
bash scripts/plesk-reset-build-permissions.sh --clean  # briše .next, zatim build:clean u Plesk-u
```

**Pravilo:** nikad `npm run build` kao root. Koristi Plesk **Run script: build:clean**.

#### Build preko SSH (samo rezerva)

Root SSH **nema** `npm` u PATH-u. Plesk Node je u `/opt/plesk/node/<verzija>/bin/`:

```bash
ls /opt/plesk/node/                    # npr. 24, 26 — ista verzija kao u Plesk panelu
export PATH="/opt/plesk/node/24/bin:$PATH"
node -v && npm -v

cd /var/www/vhosts/bncshop.ba/httpdocs
pkill -f "next-server" 2>/dev/null || true   # Stop App prije builda
rm -rf .next node_modules/.cache
export NODE_ENV=production
export NODE_OPTIONS="--max-old-space-size=4096"

time npm run build:clean 2>&1 | tee /var/www/vhosts/bncshop.ba/logs/frontend-build.log
test -f .next/BUILD_ID && echo "BUILD OK"
```

**Ne** `apt install npm` — to je stara sistemska Node verzija, ne Plesk app runtime.

Očekivano trajanje: **3–6 min** idle load; **15–20 min** kad je load &gt; 8 (webpack compile + typecheck).

Provjera: folder `.next/` mora postojati nakon builda. Next.js **ne kreira `dist/`**.

Zatim: **Restart App** u Node.js panelu.

### Uobičajeni problemi

| Simptom | Uzrok | Rješenje |
|---------|-------|----------|
| 403 Forbidden | Node.js **disabled** ili prazan `#extension nodejs` u httpd.conf | **Enable Node.js** (ne Disable), Restart App, `bash scripts/plesk-restore-frontend.sh` |
| 403 Forbidden | Nema `.next/BUILD_ID` (build obrisan reset skriptom) | Plesk → `build:clean` → Restart App |
| Prazna stranica / API ne radi | Pogrešan `NEXT_PUBLIC_API_URL` / nema `BACKEND_URL` | `BACKEND_URL=https://api.bncshop.ba`, `NEXT_PUBLIC_API_URL=/backend-api/v1`, **rebuild** (BACKEND_URL se ugrađuje u bundle) |
| Slike/API 404 na `/backend-api/v1/*` | Klijent koristi proxy putanju koja nginx ne prosljeđuje Node-u; slike su na `/storage`, ne `/api/v1/storage` | Postavi `BACKEND_URL=https://api.bncshop.ba`, `npm run build`, restart; ili nginx proxy za `/backend-api` na Node port |
| `npm: command not found` (root SSH) | Plesk Node nije u PATH-u | `export PATH="/opt/plesk/node/24/bin:$PATH"` prije builda |
| `EACCES` na `.next/trace` | `.next/` vlasništvo `root` (SSH build), Plesk user ne može pisati | `bash scripts/plesk-reset-build-permissions.sh` (root SSH), pa `build:clean` u Plesk-u |
| Build 15+ min / Plesk panel “ne završi” | Visok load + web timeout | SSH + `build:clean`, load &lt; 3 idealno |
| `ChunkLoadError`, JS 400/404, MIME `text/html` | Document root `.next/static` bez nginx rewrite-a | Vidi sekciju ispod |

### ChunkLoadError / `_next/static/*.js` vraća HTML (400/404)

Browser traži npr. `/_next/static/chunks/8094-xxx.js`, ali nginx traži fajl na pogrešnoj putanji (`/.next/static/_next/static/...`) i vraća HTML error stranicu umjesto JavaScript-a.

**Opcija A (najjednostavnije):** Document root = **isti kao Application root** (`/httpdocs`). Node.js (`start.js`) servira i stranice i `_next/static`. Restart App.

**Opcija B (brži static):** Document root = `/httpdocs/.next/static` + nginx rewrite u Plesk → **Apache & nginx Settings** → **Additional nginx directives**:

```nginx
location ^~ /_next/static/ {
    alias /var/www/vhosts/bncshop.ba/httpdocs/.next/static/;
}

location ^~ /_next/image {
    proxy_pass http://127.0.0.1:$plesk_nodejs_port;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
}
```

(Ako `$plesk_nodejs_port` nije dostupan, koristi Opciju A.)

**Uvijek nakon deploya:**

```bash
rm -rf .next
npm run build
# Plesk → Restart App
# Browser: hard refresh (Ctrl+Shift+R)
```

Provjera na serveru da chunk postoji:

```bash
ls -la .next/static/chunks/8094-*.js
```

---

## Prvi deploy — tačan redoslijed komandi

SSH u `backend/` direktorij. Izvršiti **jednom** pri inicijalnom setupu.

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

Na Plesk VPS-u koristi puni PHP path:

```bash
cd /var/www/vhosts/bncshop.ba/api.bncshop.ba
PHP=/opt/plesk/php/8.4/bin/php

$PHP artisan db:seed --class=RolesAndPermissionsSeeder --force
$PHP artisan make:filament-user
$PHP artisan bnc:grant-admin aleksandra92d@gmail.com
```

`make:filament-user` **ne dodaje ulogu** automatski. Admin panel dozvoljava samo korisnike sa ulogom **Super Admin** ili **Admin** — bez `bnc:grant-admin` login prijavljuje „Neispravna email adresa ili lozinka“ iako je lozinka tačna.

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
php artisan bnc:sync-full 1
```

Job ide na `sync` red. Prati Horizon dashboard (`/horizon`) ili:

```bash
php artisan bnc:sync-diagnose
```

Import 17k+ proizvoda može trajati satima.

### Korak 6b: Import eLine / OLX mapiranja (iz repoa)

Lokalna mapiranja kategorija i OLX atributa su u `database/seeders/data/integration_mappings.json`. **Pokrenuti nakon A1 sync-a kategorija** (korak 6):

```bash
php artisan bnc:import-integration-mappings
php artisan bnc:sync-eline --full --refresh-categories --sync
```

Ako export lokalno ažurirate: `php artisan bnc:export-integration-mappings` → commit → `git pull` na serveru → ponovo `bnc:import-integration-mappings`.

### Korak 7: Meilisearch indeks

Nakon što sync završi (ili paralelno ako Scout queue radi):

```bash
php artisan scout:import "App\Models\Product"
```

### Korak 8: Frontend

```bash
cd ../frontend
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

### ⚠️ Samo jedan process manager (Supervisor **ili** systemd)

**Nikad ne instalirati oba** za isti Horizon. Na produkciji (`api.bncshop.ba`) koristiti **Supervisor**; systemd unit (`bncshop-horizon.service`) mora biti **disabled**.

Ako su oba aktivna, `pkill` ne pomaže — systemd (`Restart=always`) odmah podigne novi PHP 8.4 Horizon i load ostaje visok.

```bash
# Prije Supervisor starta — systemd mora biti OFF
sudo systemctl stop bncshop-horizon
sudo systemctl disable bncshop-horizon
sudo pkill -9 -f "artisan horizon"
sleep 10
ps aux | grep "artisan horizon" | grep -v grep   # prazno

sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start bncshop-horizon
```

**Produkcijski PHP:** `/opt/plesk/php/8.3/bin/php` (ne 8.4). Cijeli Supervisor blok (`/etc/supervisor/conf.d/bncshop-horizon.conf`):

```ini
[program:bncshop-horizon]
process_name=%(program_name)s
directory=/var/www/vhosts/bncshop.ba/api.bncshop.ba
command=/opt/plesk/php/8.3/bin/php /var/www/vhosts/bncshop.ba/api.bncshop.ba/artisan horizon
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=bncshop.ba_itus4zie2k
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/vhosts/bncshop.ba/logs/horizon-supervisor.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=3
stopwaitsecs=3600
environment=HOME="/var/www/vhosts/bncshop.ba",APP_ENV="production"
```

Log **ne** stavljati u `/var/log/` na Plesku — često `spawn error` / log se ne kreira. Koristi vhost `logs/` direktorij.

**Ne stavljati `pkill` u `command=`** — `pkill -f "8.4/.../horizon"` pogodi i sam `bash -c` proces (pattern je u njegovoj cmdline) → Supervisor `BACKOFF Exited too quickly`. Prije starta ručno ugasi systemd/stari Horizon; u `command=` samo puni put do `artisan horizon`.

**Nikad ručno** `php artisan horizon` u SSH — samo Supervisor/systemd.

### Dijagnostika visokog load-a / duplog Horizon-a

```bash
uptime
ps aux | grep "artisan horizon" | grep -v grep
sudo supervisorctl status bncshop-horizon
systemctl is-active bncshop-horizon    # treba: inactive
crontab -u bncshop.ba_itus4zie2k -l | grep schedule
cd /var/www/vhosts/bncshop.ba/api.bncshop.ba && /opt/plesk/php/8.3/bin/php artisan bnc:perf-check
```

Očekivano: **jedan** Horizon master, **samo 8.3**, ~6–7 procesa, load &lt; 2 na idle serveru.

### Scheduler (Supervisor, ne Plesk cron)

**Ne koristiti** Plesk cron `schedule:run` svake minute — svaki poziv boota cijeli Laravel (~4–5 s) i diže load.

Umjesto toga, jedan `schedule:work` proces pod Supervisorom:

```bash
sudo cp deploy/supervisor-scheduler.conf /etc/supervisor/conf.d/bncshop-scheduler.conf
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start bncshop-scheduler
sudo supervisorctl status bncshop-scheduler   # RUNNING
```

Ukloni stari Plesk task za `schedule:run` ako postoji.

---

## Produkcijski health check (runbook)

Jedna skripta za snapshot stanja nakon deploya ili kad je sajt spor:

```bash
cd /var/www/vhosts/bncshop.ba/api.bncshop.ba
bash scripts/vps-health-check.sh
```

Provjerava: load, Supervisor (Horizon + scheduler), Redis, `bnc:perf-check`, curl latencije (layout, product 1×/2×, home, cart), top CPU procese.

**Artisan uvijek iz backend roota** — ne iz `/var/www/vhosts/bncshop.ba`:

```bash
cd /var/www/vhosts/bncshop.ba/api.bncshop.ba
/opt/plesk/php/8.3/bin/php artisan bnc:perf-check
```

### Ciljevi (idle server, 8 CPU)

| Metrika | Cilj |
|---------|------|
| `load average` (1m) | &lt; 2 |
| `supervisorctl status` | `bncshop-horizon` + `bncshop-scheduler` RUNNING |
| `systemctl is-active bncshop-horizon` | inactive |
| Redis | `PONG`, `CACHE_STORE=redis` |
| Product API (2. curl) | &lt; 0.1 s |
| Storefront home | &lt; 2 s |

### Redoslijed deploya (produkcija)

```bash
# 1) Backend
cd /var/www/vhosts/bncshop.ba/api.bncshop.ba
git pull origin main
/opt/plesk/php/8.3/bin/php /usr/local/bin/composer install --no-dev --optimize-autoloader --no-interaction
/opt/plesk/php/8.3/bin/php artisan migrate --force
/opt/plesk/php/8.3/bin/php artisan config:cache
/opt/plesk/php/8.3/bin/php artisan horizon:terminate

# 2) Frontend
cd /var/www/vhosts/bncshop.ba/httpdocs
git pull origin main
rm -rf .next node_modules/.cache
npm ci
npm run build:clean
# Plesk → Domains → bncshop.ba → Node.js → Restart App

# 3) Verifikacija
cd /var/www/vhosts/bncshop.ba/api.bncshop.ba
bash scripts/vps-health-check.sh
```

### Što ne raditi

| Zabranjeno | Zašto |
|------------|-------|
| `php artisan horizon` u SSH | Duplikat uz Supervisor |
| `pkill -f "artisan horizon"` dok Supervisor radi | Restart loop / BACKOFF |
| systemd + Supervisor Horizon zajedno | 14+ procesa, load 9+ |
| Plesk cron `schedule:run` + `schedule:work` | Dvostruki scheduler |
| `npm run build` bez brisanja `.next` nakon greške | Korumpiran webpack cache |
| Povećati PHP-FPM pool dok je load &gt; 3 | Još više konkurencije za CPU |

---

## Cron — Laravel scheduler

**Preporučeno:** Supervisor `schedule:work` (vidi [Scheduler (Supervisor)](#scheduler-supervisor-ne-plesk-cron) iznad). Template: [deploy/supervisor-scheduler.conf](../deploy/supervisor-scheduler.conf).

**Legacy (ne preporučeno na produkciji):** Plesk cron svake minute boota Laravel od nule:

| Polje | Vrijednost |
|-------|------------|
| Task type | Run a command |
| Run | Every minute |
| Command | `/opt/plesk/php/8.3/bin/php '/var/www/vhosts/bncshop.ba/api.bncshop.ba/artisan' 'schedule:run'` |

**Ne** stavljati `artisan horizon` u cron. Horizon pokreće Supervisor.

### Provjera schedulera

```bash
php artisan schedule:list
php artisan schedule:test --name="bnc:sync-scheduled"
```

---

## Svaki naredni deploy (release)

Iz root-a repoa:

```bash
bash scripts/deploy-production.sh
```

Skripta ([scripts/deploy-production.sh](../scripts/deploy-production.sh)) radi:

1. `composer install --no-dev`
2. `php artisan migrate --force`
3. `php artisan storage:link --force`
4. `php artisan config:cache`, `route:cache`, `view:cache`
5. `php artisan scout:import "App\Models\Product"`
6. `php artisan horizon:terminate`
7. Frontend: `npm ci`, `npm run build`
8. Health check na `APP_URL/api/v1/health`

**Skripta ne radi:** seed, full sync, kreiranje admin korisnika.

---

## Verifikacija nakon deploya

```bash
cd backend

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
| CORS greške / live pretraga ne radi | Browser fetch blokiran cross-origin | Postavi `BACKEND_URL=https://api.bncshop.ba`, rebuild (frontend zove `api.bncshop.ba` direktno); ili popravi nginx proxy za `/backend-api` |
| OLX sync se ne pokreće | Auto sync isključen | Admin → OLX settings + `OLX_*` env varijable |
| Sync zakasnio | Cron ili worker | Provjeri `bncshop-scheduler` RUNNING + `bnc:sync-diagnose` |
| Visok load / spor sajt | Dupli Horizon, cron schedule:run, prefetch | `bash scripts/vps-health-check.sh`, vidi runbook iznad |
| `tags disabled` | Redis cache | `CACHE_STORE=redis`, phpredis ekstenzija |

---

## Brza referenca komandi

| Situacija | Komanda |
|-----------|---------|
| Prva migracija | `php artisan migrate --force` |
| Produkcijski seed | `php artisan db:seed --class=RolesAndPermissionsSeeder` (+ ostali seeders) |
| Admin nalog | `php artisan make:filament-user` |
| Prvi import | `php artisan bnc:sync-full 1` |
| Search indeks | `php artisan scout:import "App\Models\Product"` |
| Deploy release | `bash scripts/deploy-production.sh` |
| Restart workers | `php artisan horizon:terminate` |
| Sync dijagnostika | `php artisan bnc:sync-diagnose` |
| Health | `php artisan bnc:health` |
| Load / Horizon dijagnostika | `php artisan bnc:perf-check` |
| VPS snapshot (load, Redis, curl) | `bash scripts/vps-health-check.sh` |
| Ugasi systemd Horizon | `sudo systemctl disable --now bncshop-horizon` |

---

## Povezana dokumentacija

- [12-DEPLOYMENT.md](./12-DEPLOYMENT.md) — kratak pregled
- [PRODUCTION-CHECKLIST.md](./PRODUCTION-CHECKLIST.md) — go-live checklist
- [env.production.example.md](./env.production.example.md) — pun `.env` template
- [performance-setup.md](./performance-setup.md) — Redis / Meilisearch performance

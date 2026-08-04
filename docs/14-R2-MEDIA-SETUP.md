# Cloudflare R2 + images.bnc.ba — setup vodič

Ovaj dokument opisuje ručne korake za Fazu 0 migracije medija na R2.

## 1. R2 bucket

1. Cloudflare Dashboard → **R2** → **Create bucket**
2. Ime: `bnc-media`
3. Location: **Automatic** (ili EU ako je dostupno)
4. Public access: **Disabled** (javni pristup ide preko Worker-a)

## 2. R2 API token (Laravel upload)

1. R2 → **Manage R2 API Tokens** → **Create API Token**
2. Permissions: **Object Read & Write** na bucket `bnc-media`
3. Sačuvaj:
   - Access Key ID → `R2_ACCESS_KEY_ID`
   - Secret Access Key → `R2_SECRET_ACCESS_KEY`
4. Endpoint: `https://<ACCOUNT_ID>.r2.cloudflarestorage.com` → `R2_ENDPOINT`

## 3. Cloudflare Worker (`bnc-media-router`)

Kod je u repou: [`cloudflare/bnc-media-router/`](../cloudflare/bnc-media-router/).

```bash
cd cloudflare/bnc-media-router
npm install
npx wrangler login
npx wrangler deploy
```

`wrangler.toml` već sadrži R2 binding `MEDIA` → bucket `bnc-media`.

Env varijable (Cloudflare Dashboard → Worker → Settings → Variables):

| Varijabla | Vrijednost |
|-----------|------------|
| `FALLBACK_ORIGINS` | `https://api.bnc.ba,https://api.bncshop.ba` |

## 4. DNS

1. Cloudflare DNS za zonu `bnc.ba`
2. CNAME: `images` → `<worker-subdomain>.workers.dev` **ili** Worker custom domain
3. **Proxied** (narančasti oblak) mora biti uključeno

Alternativa: Workers → `bnc-media-router` → **Triggers** → **Custom Domains** → dodaj `images.bnc.ba`.

## 5. Cache Rules

Cloudflare → **Caching** → **Cache Rules** (za `images.bnc.ba`):

- **Edge TTL**: Respect origin
- **Browser TTL**: 1 year (31536000s) za R2 objekte
- Uključi **Tiered Cache**

Fallback odgovori (legacy proxy) imaju kraći TTL (`max-age=3600`) postavljen u Worker-u.

## 6. Laravel `.env` (produkcija)

```env
BNC_MEDIA_ORIGIN=https://images.bnc.ba
BNC_MEDIA_DISK=r2

R2_ACCESS_KEY_ID=
R2_SECRET_ACCESS_KEY=
R2_BUCKET=bnc-media
R2_ENDPOINT=https://<ACCOUNT_ID>.r2.cloudflarestorage.com
R2_PUBLIC_URL=https://images.bnc.ba
```

Dok `BNC_MEDIA_ORIGIN` nije postavljen, API i dalje vraća stare hostove (`api.bnc.ba` / `api.bncshop.ba`).

## 7. Verifikacija prije cutover-a

Worker mora servirati postojeće slike **prije** uključivanja `BNC_MEDIA_ORIGIN`:

```bash
# Stara slika (još nije u R2) — Worker fallback na api.bncshop.ba
curl -I "https://images.bnc.ba/products/<slug>/<id>.webp"

# Varijanta
curl -I "https://images.bnc.ba/products/<slug>/<id>.webp?w=640"
```

Očekivano: `200 OK` čak i bez objekata u R2 (fallback).

## 8. Rollout redoslijed

1. Deploy Worker + DNS (Faza 0–2)
2. Test fallback URL-ova
3. Deploy Laravel s R2 credentials (novi uploadi idu u R2)
4. `php artisan bnc:migrate-media-to-r2 --type=all --limit=500` u serijama
5. `php artisan bnc:media-audit` dok nije 100%
6. Postavi `BNC_MEDIA_ORIGIN=https://images.bnc.ba`
7. Deploy frontend s `NEXT_PUBLIC_MEDIA_ORIGIN=https://images.bnc.ba`

## 9. Troškovi (orientaciono)

| Stavka | Cijena |
|--------|--------|
| R2 storage (~40 GB) | ~$0.60/mj |
| R2 egress | $0 |
| Workers Paid (preko 100k req/dan) | $5/mj |

## 10. Rollback

1. Ukloni `BNC_MEDIA_ORIGIN` iz `.env`
2. `php artisan config:clear`
3. Frontend ukloni `NEXT_PUBLIC_MEDIA_ORIGIN`

Worker fallback i dalje servira slike sa starih hostova.

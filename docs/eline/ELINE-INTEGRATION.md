# eLine ERP integracija

Modul za uvoz polovnih i novih artikala iz eLine ERP sistema u BNC webshop, sa admin mapiranjem kategorija i filterom refurbished/novo na storefrontu.

## API endpointi

| Feed | URL |
|------|-----|
| Artikli | `{ELINE_API_BASE_URL}/ArtikliZaWeb/{ELINE_API_TOKEN}/{ELINE_API_SHOP_CODE}` |
| Cjenovnici | `{ELINE_API_BASE_URL}/CjenovniciZaWeb/{ELINE_API_TOKEN}/{ELINE_API_SHOP_CODE}` |

Primjer produkcijskog base URL-a: `https://www8.eline.ba/bl/RestWebShop.svc/json`

## Env varijable

```env
ELINE_API_BASE_URL=https://www8.eline.ba/bl/RestWebShop.svc/json
ELINE_API_TOKEN=your-token-here
ELINE_API_SHOP_CODE=bncshop
ELINE_API_TIMEOUT=120
ELINE_API_RETRIES=3
ELINE_API_VERIFY_SSL=false
ELINE_SYNC_INTERVAL_MINUTES=60
```

## Mapiranje polja

| eLine polje | BNC polje | Napomena |
|-------------|-----------|----------|
| `sifra` | `sku`, `eline_sifra` | Jedinstveni identifikator |
| `sifra` | `external_product_id` | Deterministički UUID v5 (`eline:{sifra}`) |
| `naziv` | `name` | |
| `opis` / `htmlOpis` | `description` | HTML se stripa u plain text |
| `grupakategorija` / `grupanaziv` | mapiranje → `category_id` | Admin mapira na BNC kategoriju |
| `mpc` (cjenovnik) | `regular_price`, `display_price`, `api_price` | Direktno MPC, bez popusta |
| `stanje` | `api_stock`, `available_stock` | |
| mapping `product_condition` | `is_refurbished` / `is_new` | refurbished ili new |

## Admin workflow

1. **Integracije → eLine mapiranje kategorija** → klik **Osvježi kategorije iz eLine**
2. Za svaku eLine kategoriju:
   - odaberi BNC kategoriju (npr. Računari)
   - postavi stanje: Refurbished ili Novo
   - uključi import (`is_enabled`)
3. **Integracije → API izvori → eLine ERP** → **eLine sync**
4. Po potrebi u **Proizvodi** filtriraj `Izvor = eLine` i isključi pojedinačne artikle akcijom **Isključi iz eLine**

## Prenos mapiranja (lokal → produkcija)

Mapiranja eLine i OLX kategorija/atributa ne treba ručno ponavljati na serveru. Lokalna konfiguracija se čuva u repou:

`backend/database/seeders/data/integration_mappings.json`

Kategorije se na serveru matchaju preko A1 `external_category_id` (ne po lokalnom `category_id`), pa **prije importa mora postojati A1 full sync kategorija**.

```bash
# Lokalno — nakon izmjena u admin panelu
php artisan bnc:export-integration-mappings
git add database/seeders/data/integration_mappings.json
git commit -m "chore: sync integration mappings"
git push

# Produkcija — nakon git pull i A1 sync-a kategorija
php artisan bnc:import-integration-mappings
php artisan bnc:sync-eline --full --refresh-categories --sync
```

Opcija `--only-enabled` importuje samo uključena eLine/OLX mapiranja kategorija (OLX atributi se uvijek uvoze).

## Artisan komande

```bash
# Inkrementalni sync (default) — samo novi/izmijenjeni u mapiranim kategorijama
php artisan bnc:sync-eline
php artisan bnc:sync-eline --sync

# Puni sync (sve mapirane kategorije + opciono discovery)
php artisan bnc:sync-eline --full --refresh-categories --sync
```

## Automatski raspored

eLine se automatski provjerava **2 puta dnevno** (default **06:00** i **18:00**):

```env
ELINE_SYNC_TIMES=06:00,18:00
```

Scheduler pokreće `bnc:sync-eline-scheduled` → inkrementalni sync (bez discovery).

**Napomena:** eLine API nema `date-modified-after` filter kao A1. Sistem preuzima feed, ali u bazu upisuje **samo proizvode čiji se hash promijenio** (naziv, opis, cijena, stanje, kategorija, aktivan status). Feed se i dalje skida cijeli zbog ograničenja API-ja.

## Baza podataka

- `eline_categories` — cache kategorija iz feeda
- `eline_category_mappings` — mapiranje na `categories` + enable + condition
- `eline_product_overrides` — ručno isključivanje pojedinačnih `eline_sifra`
- `products.import_source` — `a1` | `eline` | `manual`
- `products.is_refurbished` — filter na storefrontu

## Storefront filteri

U kategorijskom sidebaru dostupni checkbox filteri:
- **Novo** (`is_new=1`)
- **Refurbished** (`is_refurbished=1`)

Badge se prikazuje na kartici proizvoda i na stranici proizvoda.

## Napomene

- eLine feed **nema slike** — slike se dodaju ručno u admin panelu
- Proizvodi bez mapirane/uključene kategorije se **ne uvode**
- Proizvodi koji nestanu iz feeda dobijaju `sync_status = missing_from_api` i `is_public = false`
- Export narudžbi u eLine ERP nije uključen u ovu fazu

## Legacy referenca

Stari WordPress PHP skript opisan u [`izeline.md`](izeline.md) (credentials uklonjeni iz sigurnosnih razloga).

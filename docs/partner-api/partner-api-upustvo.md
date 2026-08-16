# BNC Partner API — uputstvo

Ovaj API služi eksternim webshopovima da **preuzimaju katalog proizvoda** sa BNC-a (naziv, barkod, cijena, zaliha i, po dogovoru, potpuni opis).

Model je **pull**: vi zovete naš API, mi ne šaljemo podatke ka vama.

---

## 1. Šta dobijate od BNC-a

Nakon što vam otvorimo pristup, dobijate:

| Stavka | Primjer | Napomena |
|---|---|---|
| **Kod partnera** (`targetSystemCode`) | `webshop-foo` | Ide u URL |
| **API ključ** | `bncpe_...` | Tajna. Prikazuje se samo jednom. |
| **Tip API-ja** | osnovni ili puni | Određuje koja polja dobijate |
| **Endpoint** | vidi ispod | Koristite HTTPS |

Cijeli API ključ se prikaže **samo jednom** (prilikom kreiranja ili rotacije). Kasnije u adminu vidite samo zadnja 4 znaka (npr. `...LrPF`). Ako ključ izgubite, zatražite novi — stari prestaje da važi.

---

## 2. Endpointi

**Preporučeni (A1-stil):**

```
GET https://api.bnc.ba/api/integrations/{kod}/products
```

**Legacy (i dalje radi):**

```
GET https://api.bnc.ba/api/v1/partner/products
```

`{kod}` je vaš partner kod (npr. `test`). Na integracijskom URL-u ključ **mora** pripadati baš tom kodu. Tuđi kod + vaš ključ = `401`.

---

## 3. Autentifikacija

Ključ šaljite **samo u HTTP headeru**. Nikad u URL-u (`?api_key=...` se odbija).

**Opcija A — preporučeno:**

```http
Authorization: Bearer bncpe_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

**Opcija B:**

```http
X-API-Key: bncpe_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

Obavezno:

- HTTPS
- `Accept: application/json`

Ako vam je uključen IP allowlist, zahtjevi smiju dolaziti samo sa dogovorenih IP adresa.

---

## 4. Query parametri

| Parametar | Alias | Opis | Default |
|---|---|---|---|
| `ModifiedAfter` | `updated_since` | Samo proizvodi izmijenjeni **nakon** ovog vremena (ISO 8601) | svi proizvodi |
| `Page` | `page` | Broj stranice (od 1) | `1` |
| `PageSize` | `per_page` | Broj zapisa po stranici (max **200**) | `100` |

Format datuma:

```
2026-07-07T20:00:00Z
```

Prihvata se i offset, npr. `2026-07-07T22:00:00+02:00`.

Bez `ModifiedAfter` dobijate cijeli javni katalog, paginirano.

---

## 5. Primjeri poziva

### Prvi sync (cijeli katalog)

```http
GET https://api.bnc.ba/api/integrations/VAS-KOD/products?Page=1&PageSize=100
Authorization: Bearer bncpe_...
Accept: application/json
```

### Inkrementalni sync (samo izmjene)

```http
GET https://api.bnc.ba/api/integrations/VAS-KOD/products?ModifiedAfter=2026-07-07T20:00:00Z&Page=1&PageSize=100
Authorization: Bearer bncpe_...
Accept: application/json
```

### curl

```bash
curl -H "Authorization: Bearer bncpe_..." \
     -H "Accept: application/json" \
     "https://api.bnc.ba/api/integrations/VAS-KOD/products?ModifiedAfter=2026-07-07T20:00:00Z&Page=1&PageSize=100"
```

### Legacy URL

```bash
curl -H "X-API-Key: bncpe_..." \
     -H "Accept: application/json" \
     "https://api.bnc.ba/api/v1/partner/products?updated_since=2026-07-07T20:00:00+02:00&page=1&per_page=100"
```

---

## 6. Odgovor (omotnica)

Svi uspješni odgovori imaju isti oblik:

```json
{
  "data": [ ],
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 100,
      "total": 23799,
      "last_page": 238
    },
    "filters": {
      "ModifiedAfter": "2026-07-07T20:00:00Z"
    }
  },
  "errors": []
}
```

- `data` — lista proizvoda
- `meta.pagination` — koristite `last_page` da prođete sve stranice
- `meta.filters` — echo filtera koje ste poslali
- `errors` — prazan niz kad je sve u redu

---

## 7. Tipovi payload-a

Tip određuje BNC pri izdavanju ključa. **Ne možete** ga promijeniti query parametrom.

Vraćaju se samo **javni, aktivni i novi** proizvodi. eLine / polovni asortiman se ne šalje.

### 7.1 Osnovni (`basic`)

Za shopove kojima treba cijena i zaliha.

```json
{
  "id": 7812,
  "sifra": null,
  "ean": "194644229023",
  "naziv": "Anker, eufy Robot Vacuum Omni E28 Black",
  "cijena": 2069,
  "akcijska_cijena": null,
  "zaliha": 8,
  "updated_at": "2026-07-18T03:25:18+02:00"
}
```

| Polje | Tip | Opis |
|---|---|---|
| `id` | number | Interni ID proizvoda (stabilan identifikator) |
| `sifra` | string \| null | Šifra (SKU) |
| `ean` | string \| null | Barkod / EAN |
| `naziv` | string | Naziv proizvoda |
| `cijena` | number | Glavna prodajna cijena (KM) |
| `akcijska_cijena` | number \| null | Akcijska cijena ako je proizvod na akciji, inače `null` |
| `zaliha` | number | Dostupna količina (cijeli broj) |
| `updated_at` | string | Vrijeme zadnje izmjene (ISO 8601) |

### 7.2 Puni (`full`)

Sve iz osnovnog, plus katalog podaci.

```json
{
  "id": 123,
  "sifra": "SKU-001",
  "ean": "1234567890123",
  "naziv": "Dell XPS 15",
  "cijena": 1999.00,
  "akcijska_cijena": 1799.00,
  "zaliha": 4,
  "opis": "<p>...</p>",
  "kratki_opis": "...",
  "kategorija": {
    "id": 10,
    "naziv": "Laptopi",
    "putanja": "racunari/laptopi"
  },
  "proizvodjac": {
    "id": 5,
    "naziv": "Dell"
  },
  "atributi": [
    { "naziv": "Procesor", "vrijednost": "Intel Core i7" }
  ],
  "slike": [
    { "url": "https://...", "glavna": true, "redoslijed": 0 }
  ],
  "updated_at": "2026-08-14T00:00:00+02:00"
}
```

| Dodatno polje | Tip | Opis |
|---|---|---|
| `opis` | string \| null | Pun opis (može sadržavati HTML) |
| `kratki_opis` | string \| null | Kratki opis |
| `kategorija` | object \| null | `id`, `naziv`, `putanja` |
| `proizvodjac` | object \| null | `id`, `naziv` |
| `atributi` | array | Javni atributi: `naziv`, `vrijednost` |
| `slike` | array | `url` (apsolutni), `glavna` (bool), `redoslijed` (int) |

---

## 8. Cijene i zaliha

- `cijena` je **prodajna cijena** spremna za prikaz / preprodaju.
- `akcijska_cijena` postoji samo kad je proizvod na akciji; tada je to cijena koju treba prikazati kupcu, a `cijena` je precrtana (redovna).
- `zaliha` je dostupna količina. `0` znači nema na stanju.
- Interni podaci (marža, nabavna cijena, rebate) **nikad se ne šalju**.

---

## 9. Preporučeni sync

1. **Prvi put:** prolazite sve stranice (`Page = 1 … last_page`) bez `ModifiedAfter`.
2. Sačuvajte vrijeme početka synca (UTC).
3. **Sljedeći put:** šaljite `ModifiedAfter` = to sačuvano vrijeme.
4. Ponavljajte npr. svakih 15–60 minuta, ili 2× dnevno.
5. Proizvode mapirajte po `id` (najstabilnije). `ean` i `sifra` mogu biti prazni.

Paginacija: ako je `last_page` veći od trenutne, uzmite sljedeću `Page` dok ne dođete do kraja.

---

## 10. Greške

Odgovor uvijek ima `errors` niz.

| HTTP | Poruka (primjer) | Šta uraditi |
|---|---|---|
| `401` | Neispravan ili nedostaje API ključ | Provjerite header, kod u URL-u i da ključ nije rotiran |
| `403` | Partner export API je isključen | API je privremeno ugašen — kontaktirajte BNC |
| `403` | Partner export API zahtijeva HTTPS konekciju | Koristite `https://` |
| `403` | Pristup sa ove IP adrese nije dozvoljen | Pošaljite nam javnu IP adresu servera |
| `422` | Validacija (npr. loš datum) | Ispravite `ModifiedAfter` / `Page` / `PageSize` |
| `429` | Previše zahtjeva | Sačekajte 1 minutu; smanjite učestalost |
| `503` | IP allowlist nije definisan | Kontaktirajte BNC |

Ključ u query stringu (`?api_key=`) takođe vraća `401`.

---

## 11. Limiti i pravila

- Max **200** proizvoda po stranici (`PageSize`).
- Default rate limit: **60 zahtjeva / minutu** po ključu (može biti drugačije po dogovoru).
- Default dnevni limit: **2000 stranica / 24h** po ključu (jedan full sync ≈ 240 stranica pri `PageSize=100`).
- Šaljite samo `GET`.
- Ne dijelite ključ, ne stavljajte ga u javni frontend, git ili screenshot.
- Ako sumnjate da je ključ procurio, odmah zatražite rotaciju.

---

## 12. Brza checklista za integraciju

1. Sačuvajte API ključ na serveru (env / secret store).
2. Pozovite `GET /api/integrations/{kod}/products?Page=1&PageSize=100`.
3. Očekujte `200` i `data` niz.
4. Prođite sve stranice do `last_page`.
5. Uvezite `id`, `ean`, `naziv`, `cijena`, `akcijska_cijena`, `zaliha`.
6. Ako imate **puni** tip: uvezite i `opis`, `kategorija`, `atributi`, `slike`.
7. Sljedeći sync radite sa `ModifiedAfter`.

---

## 13. Kontakt

Za novi ključ, promjenu tipa (osnovni ↔ puni), IP allowlist ili probleme sa sync-om javite se BNC timu.

**Ne šaljite API ključ e-mailom u čistom tekstu ako nije neophodno.** Ako morate, pošaljite ga odvojenim kanalom od ostatka poruke.

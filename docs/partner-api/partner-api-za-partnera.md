# BNC Shop — pristup Partner API-ju

Poštovani,

u prilogu su vaši podaci za preuzimanje kataloga proizvoda. API je **pull** model: vi zovete naš server i preuzimate proizvode (cijeli katalog ili samo izmjene).

Popunite ili zamijenite polja u okviru **Vaši podaci** prije slanja. Ostalo je gotovo uputstvo.

---

## Vaši podaci

| | |
|---|---|
| **Partner** | `[NAZIV PARTNERA]` |
| **Kod (targetSystemCode)** | `[KOD]` |
| **Tip API-ja** | `[Osnovni / Puni]` |
| **API ključ** | `[bncpe_...]` |

**Vaš endpoint (preporučeno):**

```
https://api.bnc.ba/api/integrations/[KOD]/products
```

**Legacy endpoint (isti podaci, drugi URL):**

```
https://api.bnc.ba/api/v1/partner/products
```

Ključ je tajna. Prikazuje se samo jednom. Nemojte ga stavljati u URL, javni frontend, git ili screenshot. Ako ga izgubite, javite nam — izdaćemo novi, stari prestaje da važi.

---

## Autentifikacija

Ključ šaljite **samo u HTTP headeru**.

```http
Authorization: Bearer [bncpe_...]
```

ili

```http
X-API-Key: [bncpe_...]
```

Obavezno `https://` i `Accept: application/json`.  
`?api_key=...` u URL-u se **odbija**.

Ako je uključen IP allowlist, zahtjevi smiju dolaziti samo sa dogovorene IP adrese vašeg servera.

---

## Prvi poziv (provjera)

Zamijenite `[KOD]` i `[bncpe_...]` vašim podacima.

```bash
curl -H "Authorization: Bearer [bncpe_...]" \
     -H "Accept: application/json" \
     "https://api.bnc.ba/api/integrations/[KOD]/products?Page=1&PageSize=10"
```

Očekujte HTTP **200** i JSON sa `data` nizom proizvoda.

---

## Parametri

| Parametar | Alias | Šta radi | Default |
|---|---|---|---|
| `ModifiedAfter` | `updated_since` | Samo proizvodi izmijenjeni nakon ovog vremena | cijeli katalog |
| `Page` | `page` | Stranica (od 1) | `1` |
| `PageSize` | `per_page` | Zapisa po stranici, max **200** | `100` |

Datum: `2026-07-07T20:00:00Z` (prihvata se i `+02:00`).

### Inkrementalni sync

```
GET https://api.bnc.ba/api/integrations/[KOD]/products?ModifiedAfter=2026-07-07T20:00:00Z&Page=1&PageSize=100
Authorization: Bearer [bncpe_...]
```

**Preporuka:** prvi put prođite sve stranice bez `ModifiedAfter`. Sačuvajte UTC vrijeme početka. Sljedeće syncove radite sa tim `ModifiedAfter` (npr. svakih 15–60 min ili 2× dnevno). Proizvode mapirajte po `id`.

---

## Oblik odgovora

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

Ako je `last_page` veći od trenutne stranice, uzmite sljedeću `Page` dok ne dođete do kraja.

Vraćaju se samo **javni, aktivni i novi** proizvodi. eLine / polovni asortiman se ne šalje.

---

## Polja proizvoda

### Osnovni tip

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

| Polje | Opis |
|---|---|
| `id` | Stabilan interni ID — koristite za mapiranje |
| `sifra` | SKU (može biti `null`) |
| `ean` | Barkod (može biti `null`) |
| `naziv` | Naziv |
| `cijena` | Glavna prodajna cijena (KM) |
| `akcijska_cijena` | Akcijska cijena ako je na akciji, inače `null` |
| `zaliha` | Dostupna količina (`0` = nema na stanju) |
| `updated_at` | Zadnja izmjena (ISO 8601) |

Ako je `akcijska_cijena` popunjena, to je cijena za kupca, a `cijena` je redovna (precrtana).

### Puni tip (ako vam je dodijeljen)

Sve iz osnovnog, plus:

| Polje | Opis |
|---|---|
| `opis` | Pun opis (može HTML) |
| `kratki_opis` | Kratki opis |
| `kategorija` | `{ id, naziv, putanja }` |
| `proizvodjac` | `{ id, naziv }` |
| `atributi` | `[{ naziv, vrijednost }]` |
| `slike` | `[{ url, glavna, redoslijed }]` |

Marža, nabavna cijena i interni rabati **se ne šalju**.

---

## Greške

| HTTP | Značenje |
|---|---|
| `401` | Pogrešan / nedostaje ključ, ili kod u URL-u nije vaš |
| `403` | API ugašen, nije HTTPS, ili IP nije na allowlisti |
| `422` | Loš datum ili paginacija |
| `429` | Previše zahtjeva u minuti, ili dnevni limit stranica — sačekajte / javite BNC-u |
| `503` | Allowlist nije podešen — javite BNC-u |

Limiti (osim ako nije drugačije dogovoreno):

- **60 zahtjeva / minutu**
- **2000 stranica / 24h** (jedan full sync od ~24.000 proizvoda / 100 ≈ 240 stranica)
- Max **200** proizvoda po stranici

Koristite `ModifiedAfter` nakon prvog synca da ostanete daleko ispod dnevnog limita.

---

## Checklista

1. Sačuvajte ključ na serveru (env / secret), ne u kodu.
2. Pozovite endpoint sa `Page=1&PageSize=100`.
3. Očekujte `200` i `data`.
4. Prođite sve stranice do `last_page`.
5. Uvezite `id`, `ean`, `naziv`, `cijena`, `akcijska_cijena`, `zaliha`.
6. Ako imate **puni** tip: uvezite i opis, kategoriju, atribute, slike.
7. Dalje syncove radite sa `ModifiedAfter`.

Za novi ključ, promjenu tipa ili IP adresu servera javite se BNC timu.

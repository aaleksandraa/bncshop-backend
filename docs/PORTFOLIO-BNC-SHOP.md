# BNC Shop — Portfolio prezentacija projekta

> **Tip projekta:** Full-stack ecommerce platforma  
> **Uloga:** Web developer & dizajner (arhitektura, backend, frontend, integracije, deploy)  
> **Live:** [bncshop.ba](https://bncshop.ba) · API: [api.bncshop.ba](https://api.bncshop.ba)  
> **Repozitoriji:** [Backend](https://github.com/aaleksandraa/bncshop-backend) · [Frontend](https://github.com/aaleksandraa/bncshop-frontend)

---

## Kratka verzija (za portfolio karticu)

**BNC Shop** je moderna B2C/B2B ecommerce platforma za IT i gaming trgovinu u BiH — kompletna zamjena WordPress/WooCommerce rješenja.

Izgradio sam **API-first arhitekturu** (Laravel + Next.js) sa admin panelom, automatskom sinkronizacijom kataloga iz više izvora (A1, eLine ERP, OLX), loyalty programom, B2B portalom, naprednim cijenama i dostavom, te produkcijskim deployom na Plesk + Cloudflare R2 CDN.

**Tech:** Laravel 11 · Filament 3 · Next.js 15 · PostgreSQL · Redis · Meilisearch · TypeScript · Tailwind CSS

**Ključni rezultat:** Brz, SEO-optimizovan webshop sa jasno odvojenim kanalima (B2C, B2B, prodavač, admin), automatizovanim poslovnim procesima i skalabilnom infrastrukturom.

---

## Detaljna verzija (case study)

### 1. Kontekst i strategija

BNC Shop nije bio „još jedan webshop“. Cilj je bio **zamijeniti ograničavajući WooCommerce stack** platformom koja:

- drži **desetine hiljada proizvoda** sa brzim pretragom i filtriranjem,
- **automatski uvozi** cijene, zalihe i atribute iz postojećih ERP/marketplace sistema,
- podržava **više poslovnih kanala** (retail kupci, B2B partneri, prodavači u radnji),
- omogućava **marketing timu** samostalno upravljanje akcijama, kuponima i sadržajem,
- isporučuje **SEO i performanse** na nivou modernih SaaS shopova,
- ima **jasnu putanju rasta** — od lokalnog deploya do CDN medija i queue-based synca.

Strateški pristup: **jedan izvor istine u PostgreSQL bazi**, a svi kanali (webshop, admin, B2B, integracije) komuniciraju preko REST API-ja. To smanjuje duplikaciju logike, olakšava testiranje i omogućava nezavisni deploy frontenda i backenda.

```
┌─────────────────┐     ┌──────────────────┐     ┌─────────────────┐
│  A1 API         │     │  eLine ERP       │     │  OLX.ba API     │
│  (katalog)      │     │  (refurbished)   │     │  (oglasi)       │
└────────┬────────┘     └────────┬─────────┘     └────────┬────────┘
         │                       │                        │
         └───────────────────────┼────────────────────────┘
                                 ▼
                    ┌────────────────────────┐
                    │  Laravel Backend       │
                    │  Queue (Horizon)       │
                    │  PostgreSQL + Redis    │
                    │  Meilisearch           │
                    └───────────┬────────────┘
                                │
              ┌─────────────────┼─────────────────┐
              ▼                 ▼                 ▼
     ┌────────────────┐ ┌─────────────┐ ┌────────────────┐
     │ Next.js Shop   │ │ Filament    │ │ B2B Admin      │
     │ bncshop.ba     │ │ Admin Panel │ │ Panel          │
     └────────────────┘ └─────────────┘ └────────────────┘
```

---

### 2. Arhitektura i tech stack

| Sloj | Tehnologija | Uloga |
|------|-------------|-------|
| **Backend API** | Laravel 11, PHP 8.3 | Poslovna logika, REST API, queue jobovi, email |
| **Admin panel** | Filament 3 | Upravljanje katalogom, narudžbama, analitikom, integracijama |
| **Storefront** | Next.js 15 (App Router), React 19, TypeScript | Javni webshop sa SSR/ISR za SEO |
| **Styling** | Tailwind CSS 3 | Responzivan, konzistentan UI |
| **Baza** | PostgreSQL 16 | Relacioni model, JSONB snapshoti, partial indeksi |
| **Cache & queue** | Redis 7, Laravel Horizon | Brzi odgovori, background sync, email |
| **Pretraga** | Meilisearch + Laravel Scout | Full-text pretraga, faceted filteri |
| **Auth** | Laravel Sanctum | SPA cookie / Bearer token za kupce |
| **Permissions** | spatie/laravel-permission | Role-based pristup u adminu |
| **Audit** | spatie/laravel-activitylog | Praćenje promjena u adminu |
| **Mediji** | Cloudflare R2 + Worker | CDN (`images.bnc.ba`), WebP optimizacija |
| **Sigurnost** | Cloudflare Turnstile | Anti-bot na formama |
| **Deploy** | Plesk (PHP-FPM + Node.js), Supervisor | Produkcijski hosting |

**Git organizacija:** Dva odvojena repozitorija (backend i frontend) sa jasnom granicom odgovornosti i nezavisnim CI/deploy ciklusom.

---

### 3. B2C webshop — korisničko iskustvo

Javni storefront na **bncshop.ba** pokriva cijeli retail put kupca:

#### Stranice i tokovi

| Stranica | Funkcionalnost |
|----------|----------------|
| **Početna** | Hero banner (CMS), istaknute kategorije, akcijski i novi proizvodi, trust strip |
| **Kategorije** | Hijerarhijska navigacija, breadcrumb, paginacija, dinamički filteri po atributima |
| **Proizvod** | Galerija sa zoomom, cijene (regularna/akcijska), tehničke specifikacije, slični proizvodi |
| **Brendovi** | Listing po proizvođaču sa SEO meta podacima |
| **Pretraga** | Meilisearch rezultati, instant sugestije |
| **Refurbished / Novo / Akcija** | Posebni landingi za segmente asortimana |
| **Korpa** | Ažuriranje količine, kuponi, upozorenja na promjenu cijene |
| **Checkout** | Višekoračni proces: pregled → podaci → dostava → potvrda (guest ili login) |
| **Nalog** | Registracija, prijava, narudžbe, profil, **BNC bodovi** |
| **Praćenje narudžbe** | Token-based praćenje bez registracije |
| **Blog & CMS stranice** | Sadržajni marketing, servisne informacije |
| **Lista želja / Uporedi** | Pomoć pri odluci kupca |
| **Kupovina na rate** | Informativna stranica za rate plaćanje |

#### Dizajn i UX principi

- **Mobile-first** responzivni layout sa optimiziranim touch targetima.
- **Skeleton loading** i lazy učitavanje teških komponenti (korpa, filteri) za brži perceived performance.
- **Jasna hijerarhija cijena** — regularna, akcijska, badge, preview kupona.
- **Cart drawer** — brzo dodavanje bez napuštanja stranice proizvoda.
- **Coupon deep links** — marketing linkovi `?kupon=CODE` automatski primjenjuju popust.
- **Idle tab navigation** — pametno ponašanje pri povratku na tab (osvježavanje cijena/zaliha).

#### SEO i discoverability

- `robots.txt` i **dinamički XML sitemap** iz API-ja.
- `generateMetadata` po proizvodu, kategoriji, brendu i blog postu.
- **JSON-LD** (Organization, WebSite, Product schema).
- Open Graph i Twitter Card meta tagovi.
- Kanonski URL-ovi i 301 redirecti za legacy putanje.

---

### 4. Cijene, popusti i kuponi

Implementiran je **slojeviti pricing engine** koji balansira automatizaciju i ručnu kontrolu:

**Prioritet izračuna cijene:**
1. Ručno zaključana cijena (admin override)
2. Lokalna akcija (po proizvodu, kategoriji, brendu, tagu ili atributu)
3. API rebate iz izvornog sistema
4. Bazna API cijena
5. Kupon (checkout validacija)

**Tipovi popusta:** proizvod, kategorija (sa podkategorijama), brend, tag, atribut — sa exclusion listama.

**Kombinacija popusta:** konfigurabilni mod (`best_single`, stack, product_priority…) — default je najpovolnija pojedinačna akcija.

**Kuponi:** scope po proizvodima/kategorijama/brendovima/tagovima, min. iznos korpe, max korištenja, single-use, marketing URL generator u adminu.

**Historija cijena:** automatski log pri syncu, ručnoj izmjeni ili isteku akcije — transparentnost za admin tim.

---

### 5. Dostava i checkout

**Pravila dostave:**
- Globalna pravila (fiksna cijena, prag besplatne dostave, pickup u radnji).
- **Override po kategoriji** (npr. klima uređaji — viša cijena dostave).
- Prioritet pravila i snapshot u narudžbi za audit.

**Checkout:**
- Guest checkout po defaultu, opcionalna registracija.
- Pickup vs. dostava sa automatskim izračunom.
- Cloudflare Turnstile zaštita od botova.
- Email potvrde narudžbe (transakcijski mailovi).

---

### 6. Loyalty program — BNC bodovi

Kompletan loyalty sistem povezan webshop ↔ fizička radnja:

| Funkcija | Opis |
|----------|------|
| **Skupljanje bodova** | Na status „isporučeno“, formula `floor(osnovica × points_per_km)` |
| **Nagrade** | Postotni popust, fiksni popust (KM), besplatan proizvod |
| **Guest pending** | Gosti vide potencijalne bodove → poticaj na registraciju |
| **Fizičke kartice** | Format `BNC-00012345`, earn/redeem u radnji |
| **Clawback** | Povrat bodova pri otkazivanju/vraćanju |
| **Istek bodova** | Dnevni scheduler |

**Admin:** postavke programa, CRUD nagrada, ledger transakcija, ručne korekcije, štampa kartica.

---

### 7. B2B portal

Odvojeni poslovni kanal za pravna lica i partnere:

- **B2B kupac portal** (`/b2b`) — katalog, korpa, narudžbe, profil, reset lozinke.
- **Zahtjev za pristup** — novi B2B kupci podnose zahtjev, admin odobrava.
- **B2B admin panel** (Filament, `/b2b-admin`) — katalog, kupci, narudžbe, kampanje, postavke.
- **Odvojeni email identitet** (`b2b@bncshop.ba`) za B2B transakcijske mailove.
- **Optimizirani API** — slim DTO-ovi za korpu i narudžbe, batch lock na checkoutu.

---

### 8. Prodavački portal (in-store)

Portal za prodavače u fizičkoj radnji:

- Prijava na `/prodavac/prijava`.
- Pregled i upravljanje **eLine proizvodima** (refurbished/novo).
- Pregled narudžbi vezanih za prodavača.
- Obavijesti o novim proizvodima.

---

### 9. Admin panel (Filament)

Centralno mjesto za operativni tim — **7 navigacijskih grupa:**

1. **Dashboard** — KPI widgeti, graf prodaje, sync status
2. **Katalog** — proizvodi, kategorije, brendovi, atributi, tagovi
3. **Prodaja** — narudžbe, kupci, kuponi
4. **Marketing** — popusti/akcije, shipping rules, BNC bodovi, nagrade
5. **Integracije** — API sync, eLine mapiranje, OLX postavke, sync log
6. **Analitika** — izvještaji (prodaja, proizvodi, kategorije, brendovi, popusti)
7. **Sistem** — korisnici, email šablone, SEO redirecti, postavke, audit log

#### Product Resource — 10 tabova

Osnovno · Cijene · Zalihe · Kategorija/Brend · Atributi · Slike · SEO · Supplier · OLX · Historija · Analitika

Svako polje može biti **zaključano** (`locked`) — sync ne prepisuje ručne izmjene admin tima.

#### Role-based permissions

| Uloga | Tipičan pristup |
|-------|-----------------|
| Super Admin | Sve |
| Admin | Katalog, prodaja, marketing, integracije |
| Manager | Katalog, prodaja, marketing (bez marže synca) |
| Content | Proizvodi, SEO, blog |
| Warehouse | Narudžbe, zalihe |
| Analyst | Izvještaji, analitika |

---

### 10. Integracije i komunikacija između sistema

Platforma ne stoji izolirano — **automatski razgovara** sa eksternim servisima:

#### A1 API (primarni katalog)

- Full i inkrementalni sync (`ModifiedAfter` paginacija).
- Kategorije → atributi → proizvodi (redoslijed importa).
- Mapiranje 20+ polja (cijena, rebate, zalihe, galerija, SEO, supplier offers).
- **Field locking** — admin zaštita od prepisivanja.
- Timestamp pravilo — `last_successful_sync_at` samo na uspješan završetak.

#### eLine ERP (refurbished & novo)

- Feed artikala i cjenovnika preko REST endpointa.
- Admin mapiranje eLine kategorija → BNC kategorije + stanje (refurbished/novo).
- Odvojeni landing `/refurbished` na storefrontu.
- Export/import mapiranja kroz git (`integration_mappings.json`).

#### OLX.ba (marketplace oglasi)

- Automatski sync oglasa (scheduled 2× dnevno).
- Mapiranje kategorija i atributa.
- Status synca po proizvodu u admin tabu „OLX“.

#### Email sistem

- Transakcijski mailovi: potvrda narudžbe, status, loyalty, B2B, reset lozinke.
- Konfigurabilni **email šabloni** u adminu.
- Odvojeni From identiteti: `info@bncshop.ba` (B2C), `b2b@bncshop.ba` (B2B).

#### Cloudflare R2 + CDN

- Mediji na `images.bnc.ba` preko Cloudflare Worker-a.
- Fallback na legacy origin za postepenu migraciju.
- WebP konverzija, edge caching, tiered cache.
- Artisan komanda `bnc:migrate-media-to-r2` za batch migraciju.

#### WebMCP (AI-ready storefront)

- Registrovani alati za AI agente: pretraga, filteri, korpa, kupon, checkout.
- Sigurnosna odluka: forme sa Turnstile **namjerno bez** WebMCP anotacija.
- Origin Trial podrška za produkciju.

---

### 11. Performanse

Storefront **nikad ne zove A1 API direktno** — sve ide iz lokalne baze:

```
Next.js ISR → Laravel API → Redis cache → PostgreSQL / Meilisearch
Sync job → PostgreSQL → queue reindex → Meilisearch + cache bust
```

**Implementirane optimizacije:**

| Sloj | Tehnika |
|------|---------|
| API | Slim ProductCardResource, Redis tagged cache, partial PG indeksi |
| Pretraga | Meilisearch faceted filteri, async Scout indexing |
| Frontend | ISR (revalidate 60s), skeleton UI, lazy cart/filters |
| Sync | Batch writes, reindex na kraju (ne po redu) |
| Mediji | CDN + WebP, lazy loading slika |

**Ciljani benchmarki:** API list < 20ms (cached), homepage TTFB < 200ms.

---

### 12. Sigurnost

| Područje | Mjera |
|----------|-------|
| Auth | Sanctum (SPA), bcrypt, opcionalni 2FA u adminu |
| Autorizacija | Spatie permissions na svim resursima |
| Input | Form Request validacija, HTMLPurifier na opisima |
| Rate limiting | Login 5/min, checkout 10/min, catalog 120/min |
| Headers | HSTS, CSP, X-Frame-Options, nosniff |
| Podaci | Enkriptovani API credentials, audit log |
| Anti-bot | Cloudflare Turnstile na osjetljivim formama |
| Korpa | httpOnly cookies, minimalni public payload |

---

### 13. Analitika i izvještaji

**Event tracking** sa storefronta: pregled proizvoda, dodavanje u korpu, checkout koraci, kreirana narudžba.

**Dashboard KPI:** revenue danas/mjesec, broj narudžbi, AOV, top proizvodi/kategorije/brendovi, out-of-stock count, sync greške.

**Izvještaji:** prodaja po periodu, proizvodu, kategoriji, brendu, atributu, popustima, sync performansama.

**Export:** CSV i PDF (role-gated).

**Nightly agregacija:** `daily_sales_snapshots` za brze historijske grafove.

---

### 14. DevOps i produkcija

| Komponenta | Setup |
|------------|-------|
| Backend | Plesk, PHP 8.3, Nginx, PHP-FPM, Supervisor (Horizon) |
| Frontend | Plesk Node.js 20+, standalone Next.js build |
| Queue | Laravel Horizon dashboard (`/horizon`) |
| Scheduler | Cron: sync, loyalty expire, sitemap, daily sales |
| Deploy skripte | `scripts/deploy-production.sh` (backend + frontend) |
| Health check | `GET /api/health` |
| Monitoring | Sentry (exceptions), Horizon (failed jobs) |

**Produkcijske domene:**

| URL | Svrha |
|-----|-------|
| `bncshop.ba` | Javni webshop |
| `api.bncshop.ba` | REST API |
| `api.bncshop.ba/admin` | Filament admin |
| `api.bncshop.ba/b2b-admin` | B2B admin |
| `images.bnc.ba` | CDN mediji |

---

### 15. Šta ovo pokazuje o pristupu razvoju

**Arhitektura prije koda** — API-first dizajn sa jasnom granicom frontend/backend omogućava paralelan rad i nezavisne deploye.

**Poslovna logika u backendu** — cijene, dostava, loyalty i validacija kupona nisu u frontendu; storefront je tanak klijent.

**Integracije kao first-class citizen** — sync nije „skripta na kraju“, već queue-based sistem sa logovanjem, retry logikom, field lockingom i admin UI-jem.

**Operativna spremnost** — role permissions, audit log, email šabloni, deploy runbook, production checklist, load test skripte.

**Performanse by design** — Redis cache, Meilisearch, ISR, slim API payloadi, CDN mediji — ne naknadni patch.

**Sigurnost u dubini** — rate limiting, Turnstile, enkripcija credentials, minimalni public API surface.

**Budućnost** — WebMCP alati, R2 migracija, modularni B2B/B2C kanali, git-syncable integration mappings.

---

### 16. Tehnički highlighti (za developere)

```text
Backend:   Laravel 11 · Filament 3 · Sanctum · Horizon · Scout · Spatie Permission
Frontend:  Next.js 15 App Router · React 19 · TypeScript · Tailwind CSS 3
Data:      PostgreSQL 16 · Redis 7 · Meilisearch
Infra:     Plesk · Cloudflare R2 · Cloudflare Worker · Docker (dev)
Patterns:  API-first · Queue jobs · ISR/SSR · Repository/Service layer · DTO resources
Integracije: A1 REST API · eLine ERP · OLX.ba API · SMTP email
```

**Brojke (orientaciono):**
- 45+ frontend ruta (B2C, B2B, prodavač, servis, blog)
- 10+ admin navigacijskih grupa sa desetinama Filament resursa
- 3 eksterna API izvora sa automatizovanim syncom
- 15+ dokumentacijskih MD fajlova (arhitektura, deploy, security, performance)

---

### 17. Kako koristiti ovaj dokument na portfolio sajtu

#### Varijanta A — Kartica projekta (grid)

Koristi sekciju **„Kratka verzija“** + jedna hero slika + tech tagovi + link na live demo.

#### Varijanta B — Case study stranica

Struktura: Kontekst → Arhitektura (dijagram) → Features → Integracije → Performanse → Rezultat.

#### Varijanta C — Tehnički deep-dive

Fokus na sekcije 4–12 za recruiters/tech leadove koji žele vidjeti dubinu implementacije.

---

### 18. Preporučeni vizuali za portfolio

| Vizual | Sadržaj |
|--------|---------|
| Hero screenshot | Početna stranica — hero + kategorije + akcije |
| Product page | Galerija, cijene, specifikacije |
| Admin dashboard | KPI widgeti i graf prodaje |
| Mobile view | Responzivni checkout ili korpa |
| Architecture diagram | Data flow (gornji ASCII/mermaid) |
| Integracije | Sync log ekran iz admina |

---

## Kontakt / autor

Projekat razvijen kao **full-stack ecommerce platforma** — od database schema i API dizajna, preko admin panela i storefront UI-ja, do produkcijskog deploya i integracija sa ERP/marketplace sistemima.

Za demo pristup admin panelu ili tehnički razgovor, kontaktirajte autora portfolija.

---

*Posljednje ažuriranje: august 2026.*

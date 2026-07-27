# Uputstva za prijavu — Admin, Prodavač, B2B

Kratak vodič kroz sve login stranice u BNC Shop sistemu, sa linkovima i default podacima iz seedera (samo lokalno / test okruženje).

---

## Brzi pregled linkova

| Uloga | Lokalno (dev) | Produkcija |
|-------|---------------|------------|
| **Admin panel** | [http://localhost:8000/admin/login](http://localhost:8000/admin/login) | [https://api.bncshop.ba/admin/login](https://api.bncshop.ba/admin/login) |
| **B2B admin panel** | [http://localhost:8000/b2b-admin/login](http://localhost:8000/b2b-admin/login) | [https://api.bncshop.ba/b2b-admin/login](https://api.bncshop.ba/b2b-admin/login) |
| **Prodavač** | [http://localhost:3000/prodavac/prijava](http://localhost:3000/prodavac/prijava) | [https://bncshop.ba/prodavac/prijava](https://bncshop.ba/prodavac/prijava) |
| **B2B kupac (portal)** | [http://localhost:3000/b2b](http://localhost:3000/b2b) | [https://bncshop.ba/b2b](https://bncshop.ba/b2b) |

> **Napomena:** Filament paneli (admin i B2B admin) rade na **backendu** (`APP_URL`, port 8000 lokalno). Prodavač i B2B kupac koriste **frontend** (`FRONTEND_URL`, port 3000 lokalno).

---

## Pokretanje seedera (lokalno)

Default korisnici se kreiraju samo van produkcije:

```bash
cd backend
php artisan db:seed --class=UsersSeeder
php artisan db:seed --class=B2bSeeder
```

Ili cijeli seed:

```bash
php artisan migrate:fresh --seed
```

U **produkciji** `UsersSeeder` i B2B test korisnici se **preskaču**. Admina tada kreirajte ručno:

```bash
php artisan make:filament-user
```

---

## 1. Admin panel (Filament)

**URL:** `/admin/login`  
**Uloga u bazi:** `Super Admin` ili `Admin`  
**Seeder:** `UsersSeeder`

| Polje | Default vrijednost |
|-------|-------------------|
| Email | `admin@bncshop.test` |
| Lozinka | `Admin123!` |
| Ime | `BNC Admin` |

### ENV varijable (opciono)

| ENV | Default |
|-----|---------|
| `ADMIN_EMAIL` | `admin@bncshop.test` |
| `ADMIN_PASSWORD` | `Admin123!` (samo `local` / `testing`) |
| `ADMIN_NAME` | `BNC Admin` |

### Dodatna zaštita (produkcija)

Ako je postavljen `ADMIN_LOGIN_SECRET` u `.env`, na login formi traži se i **Sigurnosni kod**. Lokalno, ako varijabla nije postavljena, polje se ne prikazuje.

### Šta može admin

- Puni pristup shop admin panelu (katalog, narudžbe, marketing, integracije, sistem)
- **Super Admin** i **Admin** imaju pristup i **B2B admin panelu** (`/b2b-admin`)

---

## 2. Prodavač

**URL:** `/prodavac/prijava` (frontend)  
**Uloga u bazi:** `Prodavac`  
**Seeder:** `UsersSeeder`  
**Nakon prijave:** `/prodavac/narudzbe` i `/prodavac/proizvodi` (eLine proizvodi)

| Polje | Default vrijednost |
|-------|-------------------|
| Email | `prodavac@bncshop.test` |
| Lozinka | `Prodavac123!` |
| Ime | `BNC Prodavac` |

### ENV varijable (opciono)

| ENV | Default |
|-----|---------|
| `SELLER_EMAIL` | `prodavac@bncshop.test` |
| `SELLER_PASSWORD` | `Prodavac123!` (samo `local` / `testing`) |
| `SELLER_NAME` | `BNC Prodavac` |

### Šta može prodavač

- Pregled i upravljanje narudžbama (`view_orders` / `manage_orders`)
- Pregled eLine proizvoda i uređivanje **opisa**, **slika** i **akcijske cijene** (`seller.edit_eline_products`)
- Redovna ERP cijena ostaje read-only; sync iz eLine ne pregazi ručno uređen opis
- Nema pristup Filament admin panelu

---

## 3. B2B admin panel (Filament)

**URL:** `/b2b-admin/login`  
**Uloga u bazi:** `B2B Admin` (također: `Super Admin`, `Admin`)  
**Seeder:** `B2bSeeder`

| Polje | Default vrijednost |
|-------|-------------------|
| Email | `b2badmin@bncshop.test` |
| Lozinka | `B2bAdmin123!` |
| Ime | `BNC B2B Admin` |

### ENV varijable (opciono)

| ENV | Default |
|-----|---------|
| `B2B_ADMIN_EMAIL` | `b2badmin@bncshop.test` |
| `B2B_ADMIN_PASSWORD` | `B2bAdmin123!` (samo `local` / `testing`) |
| `B2B_ADMIN_NAME` | `BNC B2B Admin` |

### Alternativna prijava

Glavni admin (`admin@bncshop.test`) također može ući na B2B admin panel jer ima ulogu **Super Admin**.

### Šta može B2B admin

- Upravljanje B2B katalogom, kupcima, narudžbama, kampanjama i postavkama
- Odobravanje B2B zahtjeva za pristup

---

## 4. B2B kupac (portal)

**URL:** `/b2b` (frontend — tab **Prijava**)  
**Tip korisnika:** `is_b2b_customer = true` + zapis u `b2b_customers`  
**Seeder:** `B2bSeeder`  
**Nakon prijave:** `/b2b/katalog`

| Polje | Default vrijednost |
|-------|-------------------|
| Email | `b2bkupac@bncshop.test` |
| Lozinka | `B2bKupac123!` |
| Ime | `Test B2B Kupac` |
| Firma | `Test Firma d.o.o.` |
| Adresa | `Ulica Testnih Kupaca 1, Sarajevo` |
| JIB | `1234567890123` |
| PDV | `200000000` |
| Telefon | `061000000` |

### ENV varijable (opciono)

| ENV | Default |
|-----|---------|
| `B2B_CUSTOMER_EMAIL` | `b2bkupac@bncshop.test` |
| `B2B_CUSTOMER_PASSWORD` | `B2bKupac123!` (samo `local` / `testing`) |

### Povezane stranice

| Stranica | Link (lokalno) |
|----------|----------------|
| Katalog | [http://localhost:3000/b2b/katalog](http://localhost:3000/b2b/katalog) |
| Korpa | [http://localhost:3000/b2b/korpa](http://localhost:3000/b2b/korpa) |
| Narudžbe | [http://localhost:3000/b2b/narudzbe](http://localhost:3000/b2b/narudzbe) |
| Zaboravljena lozinka | [http://localhost:3000/b2b/zaboravljena-lozinka](http://localhost:3000/b2b/zaboravljena-lozinka) |

### Napomena

- B2B kupac **nema** pristup admin panelima (`/admin`, `/b2b-admin`).
- Novi B2B kupci mogu poslati **zahtjev za pristup** na istoj stranici (`/b2b`, tab *Zahtjev za pristup*).

---

## Tabela svih default naloga (seeder)

| Uloga | Email | Lozinka | Panel / URL |
|-------|-------|---------|-------------|
| Super Admin | `admin@bncshop.test` | `Admin123!` | `/admin` |
| Prodavač | `prodavac@bncshop.test` | `Prodavac123!` | `/prodavac/prijava` |
| B2B Admin | `b2badmin@bncshop.test` | `B2bAdmin123!` | `/b2b-admin` |
| B2B Kupac | `b2bkupac@bncshop.test` | `B2bKupac123!` | `/b2b` |

---

## Sigurnost

- Default lozinke su **samo za lokalni razvoj i testiranje**. Na produkciji koristite jake lozinke i ENV varijable.
- Lozinke se u seederu ne ispisuju u repozitorij — dolaze iz `.env` ili hardcodiranog defaulta za `local` / `testing`.
- Detaljnije o produkcijskoj konfiguraciji: [13-SERVER-DEPLOYMENT-PLESK.md](./13-SERVER-DEPLOYMENT-PLESK.md), [09-SECURITY.md](./09-SECURITY.md).

---

## Izvorni seeder fajlovi

- `backend/database/seeders/UsersSeeder.php` — admin + prodavač
- `backend/database/seeders/B2bSeeder.php` — B2B admin + B2B test kupac + demo katalog

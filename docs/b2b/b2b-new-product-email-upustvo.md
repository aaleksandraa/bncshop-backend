# B2B email obavijest o novim proizvodima — uputstvo

Automatsko slanje **plain-text** emaila registrovanim B2B kupcima kad admin doda novi proizvod ili aktivira postojeći. Poruke se **grupišu u digest** (jedan email po kupcu u kratkom vremenskom prozoru).

---

## Da li je spremno za Horizon?

**Da.** Nije potrebna dodatna konfiguracija u kodu.

B2B notifikacije koriste standardni Laravel queue stack koji Horizon već obrađuje:

| Komponenta | Queue | Napomena |
|------------|-------|----------|
| `SendB2bNewProductDigestJob` | `default` | `ShouldQueue` + `ShouldBeUnique`, delay ~5 min |
| `B2bNewProductsDigestMail` | `default` | `ShouldQueue`, dispatch preko `Mail::queue()` |

Horizon supervisor u [`config/horizon.php`](../../config/horizon.php) — `supervisor-sync` + `supervisor-general`.

**Preduvjet na serveru:**

```env
QUEUE_CONNECTION=redis
CACHE_STORE=redis   # digest lista proizvoda se drži u cache-u
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

Horizon mora biti pokrenut (Supervisor/systemd), npr.:

```bash
php artisan horizon
```

Nakon deploya:

```bash
php artisan horizon:terminate
```

---

## Kako uključiti (admin)

1. Prijavi se na **B2B admin panel**: `/b2b-admin`
2. Idi na **Postavke → B2B postavke**
3. Uključi toggle **„Email kupcima o novim proizvodima”**
4. Sačuvaj

**Default je isključeno** — mora se ručno uključiti i u produkciji.

---

## Kada se šalje email?

Email ide samo ako su ispunjena **sva** tri uslova:

1. Toggle u B2B postavkama je **uključen**
2. Proizvod je **aktivan** (`is_active = true`)
3. Dogodilo se jedno od:
   - admin **kreira novi** proizvod (aktivan)
   - admin **aktivira** postojeći proizvod (`is_active: false → true`)

**Ne šalje se** pri:

- izmjeni cijene, opisa, slike (bez promjene aktivnosti)
- kreiranju neaktivnog proizvoda (do aktivacije)
- deaktivaciji proizvoda
- `db:seed` (toggle je off)

---

## Digest (grupisanje)

Više proizvoda dodanih u kratkom periodu → **jedan email po kupcu** sa listom proizvoda.

| Postavka | `.env` ključ | Default |
|----------|--------------|---------|
| Digest prozor | `B2B_NEW_PRODUCT_DIGEST_MINUTES` | `5` |

**Tok:**

1. Admin sačuva proizvod → ID se dodaje u cache listu
2. U queue se stavlja `SendB2bNewProductDigestJob` (delay = digest minuta)
3. Ako admin doda još proizvoda prije isteka delay-a, ID-evi se akumuliraju; `ShouldBeUnique` drži **jedan** delayed job
4. Job se izvrši → za svakog **aktivnog** B2B kupca u queue ide jedan `B2bNewProductsDigestMail`

**Primalaci:** `B2bCustomer` gdje je `is_active = true` i `users.is_b2b_customer = true`. Email adresa je `users.email`.

---

## Sadržaj emaila

- **Format:** samo plain text (bez HTML) — manji rizik od spama
- **Sadrži:** naziv, šifra (SKU), cijena po kupcu (uključujući popust), link na proizvod, link na katalog
- **Ne sadrži:** HTML opis proizvoda

Primjer subjecta:

- `Novi proizvod u B2B katalogu`
- `Novi proizvodi u B2B katalogu (3)` — ako ih je više u digestu

---

## Env varijable (produkcija)

Dodaj u backend `.env` na serveru:

```env
# Queue — obavezno za Horizon
QUEUE_CONNECTION=redis
CACHE_STORE=redis

# Frontend linkovi u emailu
FRONTEND_URL=https://vasa-domena.ba

# Digest prozor (opciono)
B2B_NEW_PRODUCT_DIGEST_MINUTES=5

# Mail (kao i za ostale transactional emailove)
MAIL_MAILER=smtp
MAIL_FROM_ADDRESS=narudzbe@vasa-domena.ba
MAIL_FROM_NAME="BNC Shop"
```

Migracija (jednom):

```bash
cd backend
php artisan migrate --force
```

---

## Praćenje u Horizonu

Dashboard: **`/horizon`** (pristup: **Super Admin** rola, ulogovan na `/admin`).

Nakon dodavanja proizvoda (toggle uključen):

1. U **Pending Jobs** / **Delayed** vidi se `SendB2bNewProductDigestJob` (delay ~5 min)
2. Nakon izvršenja digest joba, u redu se pojavljuju `B2bNewProductsDigestMail` jobovi (po jedan po kupcu)
3. U **Completed Jobs** provjeri uspješno slanje; **Failed Jobs** za greške

Korisni artisan naredbe:

```bash
php artisan horizon:status
php artisan queue:failed          # lista neuspjelih
php artisan queue:retry all         # ponovni pokušaj
```

---

## Ručni test na serveru

1. U B2B postavkama uključi toggle
2. Provjeri da Horizon radi: `php artisan horizon:status`
3. Dodaj test proizvod u `/b2b-admin`
4. Sačekaj digest prozor (default 5 min) ili privremeno smanji:

   ```env
   B2B_NEW_PRODUCT_DIGEST_MINUTES=1
   ```

5. U Horizonu potvrdi izvršenje jobova
6. Provjeri inbox test B2B kupca

Lokalno (bez slanja na pravi SMTP):

```env
MAIL_MAILER=log
```

Log: `backend/storage/logs/laravel.log`

---

## Troubleshooting

| Problem | Provjera |
|---------|----------|
| Nema emaila | Da li je toggle uključen u B2B postavkama? |
| Nema emaila | Da li Horizon radi i `QUEUE_CONNECTION=redis`? |
| Nema emaila | Da li postoji barem jedan **aktivan** B2B kupac? |
| Job ostaje u Delayed | Sačekaj digest minuta; provjeri Redis |
| Job Failed | `storage/logs/laravel.log`, Horizon → Failed Jobs |
| Email u spamu | SPF/DKIM/DMARC za `MAIL_FROM_ADDRESS`; vidi [email-setup.md](./email-setup.md) |
| Digest prazan | Proizvod mora biti `is_active = true` u trenutku slanja joba |

---

## Povezani fajlovi u kodu

| Fajl | Uloga |
|------|-------|
| `app/Services/B2b/B2bNewProductNotificationService.php` | Logika digest-a i dispatch joba |
| `app/Jobs/SendB2bNewProductDigestJob.php` | Digest job (Horizon) |
| `app/Mail/B2b/B2bNewProductsDigestMail.php` | Plain-text mailable |
| `app/Observers/B2bProductObserver.php` | Trigger na create/activate |
| `app/Filament/B2b/Pages/B2bSettingsPage.php` | Admin toggle |
| `config/b2b.php` | `new_product_digest_minutes` |
| `config/horizon.php` | Supervisor za `default` red |

Testovi: `php artisan test --filter=B2bNewProductNotificationTest`

---

## Deploy checklist

- [ ] `php artisan migrate --force` (kolona `notify_customers_on_new_product`)
- [ ] `QUEUE_CONNECTION=redis` i Redis radi
- [ ] Horizon pokrenut i `horizon:terminate` nakon deploya
- [ ] `FRONTEND_URL` postavljen na produkcijsku domenu
- [ ] Mail SMTP podešen (SPF/DKIM)
- [ ] U B2B postavkama ručno uključen toggle (ako želite slanje)

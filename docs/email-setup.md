# Email na serveru — uputstvo (produkcija)

Laravel šalje transakcijske emailove **preko queue-a** (Redis → Horizon, red `default`). Kupac vidi pošiljaoca **`info@bncshop.ba`** (ili drugi `MAIL_FROM_ADDRESS`).

## Šta se šalje automatski

| Tip | Primatelj | Kada |
|-----|-----------|------|
| Potvrda narudžbe | kupac | checkout |
| Obavijest o narudžbi | prodavač (`SELLER_EMAIL`) | checkout |
| Promjena statusa | kupac + prodavač (`SELLER_EMAIL`) | admin / prodavač |
| B2B, loyalty, rate upiti | kupac / admin | prema flow-u |

Tekst poruka: **Admin → Email šabloni**.

---

## Profesionalni setup — `info@bncshop.ba` na Plesk VPS-u

Cilj: svi mailovi izlaze kao **`BNC Webshop <info@bncshop.ba>`**, sa DKIM/SPF/DMARC, pouzdano i bez PHP SMTP grešaka.

### Korak 1 — Mail nalog u Plesk-u

1. **Plesk → Mail → Create Email Address**
2. Adresa: `info@bncshop.ba`, jaka lozinka
3. Ovaj nalog koristite i za `MAIL_FROM_ADDRESS` i (ako idete SMTP putem) za `MAIL_USERNAME`

### Korak 2 — DKIM + DNS (obavezno za profesionalan izgled)

1. **Plesk → Domains → bncshop.ba → Mail → DKIM** → uključi potpisivanje
2. Plesk generiše TXT zapis — dodaj u DNS (ako nije automatski)
3. **SPF** (TXT na `@`):

   ```
   v=spf1 a mx ip4:VAŠA_SERVER_IP ~all
   ```

   Zamijeni `VAŠA_SERVER_IP` IP-om VPS-a (npr. iz `curl -4 ifconfig.me`).

4. **DMARC** (TXT na `_dmarc`):

   ```
   v=DMARC1; p=none; rua=mailto:info@bncshop.ba
   ```

5. Provjera nakon 1–24 h: [mail-tester.com](https://www.mail-tester.com) — cilj **8+/10**.

### Korak 3 — Laravel `.env` (preporučeno: sendmail)

Na Plesk VPS-u aplikacija i Postfix su **na istom serveru**. Profesionalna praksa je predati poruku lokalnom Postfix-u — **From adresa i dalje je `info@bncshop.ba`**, DKIM potpisuje Plesk/Postfix pri odlasku.

```env
MAIL_MAILER=sendmail
MAIL_FROM_ADDRESS=info@bncshop.ba
MAIL_FROM_NAME="${APP_NAME}"

SELLER_EMAIL=prodaja@bncshop.ba
ADMIN_EMAIL=info@bncshop.ba
```

**Ne trebaju** `MAIL_HOST`, `MAIL_PORT`, `MAIL_SCHEME`, `MAIL_USERNAME`, `MAIL_PASSWORD` za sendmail.

Primijeni:

```bash
cd /var/www/vhosts/bncshop.ba/api.bncshop.ba
php artisan config:cache
php artisan horizon:terminate
```

### Korak 4 — Test

```bash
php artisan tinker --execute="Illuminate\Support\Facades\Mail::raw('BNC test', function(\$m){ \$m->to('vas@email.com')->subject('BNC test info@'); }); echo 'OK';"
```

- Ispiše `OK` i mail stigne → setup gotov
- Provjeri **From:** u primljenom mailu — mora biti `info@bncshop.ba`
- Provjeri spam; ako je u spamu → doradi DKIM/SPF (korak 2)

---

## Alternativa: autentificirani SMTP (port 587)

Koristi samo ako **namjerno** želite SMTP auth umjesto sendmail-a.

### U Plesk-u uključi submission

**Tools & Settings → Mail Server Settings** → omogući **SMTP submission on port 587**.

Provjera:

```bash
ss -tlnp | grep :587
```

Mora pokazati da servis sluša na `:587`.

### `.env` za SMTP

```env
MAIL_MAILER=smtp
MAIL_HOST=mail.bncshop.ba
MAIL_PORT=587
MAIL_SCHEME=smtp
MAIL_USERNAME=info@bncshop.ba
MAIL_PASSWORD="..."
MAIL_FROM_ADDRESS=info@bncshop.ba
MAIL_FROM_NAME="${APP_NAME}"
MAIL_EHLO_DOMAIN=bncshop.ba
```

**Nikad** `MAIL_SCHEME=tls` — Laravel/Symfony podržava samo `smtp` ili `smtps`.

**Nikad** `MAIL_HOST=bncshop.ba` na javnu IP — port 587 tamo obično nije otvoren.

---

## Obavijesti prodavaču (`prodaja@bncshop.ba`)

Interne obavijesti o narudžbama idu na **`SELLER_EMAIL`** (From i dalje `info@bncshop.ba`):

| Događaj | Šablon |
|---------|--------|
| Nova narudžba | `order_notification_seller` |
| Promjena statusa | `order_status_changed_seller` (poslano/otkazano imaju posebne) |

```env
SELLER_EMAIL=prodaja@bncshop.ba
```

Nakon deploya koda za novi šablon (jednom na serveru):

```bash
php artisan db:seed --class=EmailTemplatesSeeder
php artisan config:cache
php artisan horizon:terminate
```

---

## Preduslov: Horizon

```bash
php artisan horizon:status
```

Email jobovi idu u red `default`. Bez Horizon-a queue stoji.

---

## Uobičajene greške

| Greška | Uzrok | Rješenje |
|--------|-------|----------|
| `UnsupportedSchemeException: tls` | `MAIL_SCHEME=tls` | `MAIL_SCHEME=smtp` ili obriši liniju |
| `Connection refused :587` | Port 587 ne sluša | Sendmail (preporučeno) ili uključi submission u Plesk-u |
| `certificate verify failed` | SMTP na `127.0.0.1` + STARTTLS | **Sendmail** ili `MAIL_HOST=mail.bncshop.ba:587` |
| Mail u spamu | Nema DKIM/SPF/DMARC | Korak 2 |
| Narudžba OK, mail ne | Stari `MAIL_*` u kešu | `config:cache` + `horizon:terminate` |
| `Mailer [localhost] is not defined` | `MAIL_MAILER=localhost` | `MAIL_MAILER=sendmail` ili `smtp` |

---

## Eksterni transactional provider (kasnije)

Za veći volumen: Postmark, Mailgun, Amazon SES. `MAIL_FROM_ADDRESS` ostaje `info@bncshop.ba` nakon verifikacije domene kod provajdera.

---

## Lokalno razvojno okruženje

```env
MAIL_MAILER=log
```

Ili Mailpit iz Docker Compose-a (`MAIL_HOST=mailpit`, port `1025`).

---

## Admin nalozi (odvojeno od maila)

Filament admin: `php artisan make:filament-user` + `php artisan bnc:grant-admin email@...`. Vidi [13-SERVER-DEPLOYMENT-PLESK.md](./13-SERVER-DEPLOYMENT-PLESK.md).

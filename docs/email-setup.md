# Admin i prodavač (UsersSeeder)

Seeder se **ne pokreće u produkciji**. Za produkciju koristite:

```bash
php artisan make:filament-user
```

Lokalno, nakon `php artisan db:seed`:

| Uloga | Email (default) | Panel |
|-------|-----------------|-------|
| Admin (Super Admin) | `admin@bncshop.test` | Filament `/admin` |
| Prodavač | `prodavac@bncshop.test` | Prodavački panel `/prodavac` |

Postavite lozinke u `.env` (nikad ne commitujte stvarne lozinke):

```env
ADMIN_EMAIL=admin@bncshop.test
ADMIN_PASSWORD=your-strong-local-password
ADMIN_NAME="BNC Admin"
SELLER_EMAIL=prodavac@bncshop.test
SELLER_PASSWORD=your-strong-local-password
SELLER_NAME="BNC Prodavac"
```

# Email za narudžbe

Transactional email (potvrda narudžbe, promjena statusa) treba slati sa domene koja ima ispravno podešene **SPF**, **DKIM** i **DMARC** zapise.

Preporuke:

- Koristite istu domenu u `MAIL_FROM_ADDRESS` kao i webshop (npr. `narudzbe@vasadomena.ba`).
- Izbjegavajte generičke subject linije i previše linkova u poruci.
- Za produkciju koristite pouzdan SMTP provider (Postmark, Mailgun, Amazon SES, itd.).

Lokalno testiranje: postavite `MAIL_MAILER=log` u `.env` i provjerite `storage/logs/laravel.log`.

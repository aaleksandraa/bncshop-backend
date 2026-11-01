# BNC produkcija — domene i SSH

**Javne domene (canonical):**

| Šta | URL |
|-----|-----|
| Webshop | https://bnc.ba |
| API, admin, Horizon | https://api.bnc.ba |
| Admin panel | https://api.bnc.ba/admin |

`bncshop.ba` / `api.bncshop.ba` su **legacy** (npr. stare slike preko `LEGACY_STORAGE_URL`), ne koristiti ih u novim uputstvima osim za tu svrhu.

---

## Backend (api.bnc.ba)

U Plesk: **Domains → api.bnc.ba → Hosting** — **Document root** mora biti folder gdje je `artisan`.

Tipično (provjeri u panelu):

```bash
cd /var/www/vhosts/bnc.ba/api.bnc.ba
/opt/plesk/php/8.3/bin/php artisan --version
```

Deploy / charm cijene:

```bash
cd /var/www/vhosts/bnc.ba/api.bnc.ba
git pull origin main
composer install --no-dev --optimize-autoloader --no-interaction
/opt/plesk/php/8.3/bin/php artisan migrate --force
/opt/plesk/php/8.3/bin/php artisan optimize:clear
chmod +x scripts/verify-a1-charm-pricing.sh
PHP=/opt/plesk/php/8.3/bin/php bash scripts/verify-a1-charm-pricing.sh
/opt/plesk/php/8.3/bin/php artisan horizon:terminate
/opt/plesk/php/8.3/bin/php artisan bnc:recalculate-prices -v
```

---

## Frontend (bnc.ba)

```bash
cd /var/www/vhosts/bnc.ba/httpdocs
```

`.env.local`: `BACKEND_URL=https://api.bnc.ba`, `NEXT_PUBLIC_SITE_URL=https://bnc.ba`

---

## Ako deploy “ne vuče” novi kod

`PriceCalculator.php` treba imati **~460** linija i `applyUnder100CharmPrice`. Ako ima ~203 linije, `git pull` nije u **istom** folderu koji servira **api.bnc.ba** — uskladi Document root u Plesk s git repom.

Detaljniji runbook: [13-SERVER-DEPLOYMENT-PLESK.md](./13-SERVER-DEPLOYMENT-PLESK.md)

# BNC bodovi — loyalty program

## Pregled

BNC bodovi je loyalty program za registrovane kupce. Bodovi se dodjeljuju kada narudžba pređe u status **isporučeno**, a nagrade se biraju ručno u korpi/checkoutu.

## Poslovna pravila

| Pravilo | Implementacija |
|---------|----------------|
| Dodjela bodova | `OrderService` → `LoyaltyService::awardForOrder()` na status `isporučeno` |
| Osnovica | `subtotal - discount_total` (bez dostave), formula `floor(osnovica × points_per_km)` |
| Gosti | `loyalty_pending_earnings` po e-mailu + email `loyalty_guest_register_prompt` |
| Registracija | `CustomerAuthController` → `claimPendingForCustomer()` |
| Nagrade | Postotni popust, fiksni popust (KM), besplatan proizvod |
| Checkout | Bodovi se oduzimaju u istoj DB transakciji (`redeemForCheckout`) |
| Clawback | Na `otkazano`/`vraćeno` nakon `isporučeno` |
| Istek | Scheduler `bnc:loyalty-expire-points` (dnevno u 01:00) |

## Admin (Filament)

- **Marketing → BNC bodovi** — postavke programa (`LoyaltySettingsPage`)
- **Marketing → Nagrade lojalnosti** — CRUD nagrada
- **Marketing → Transakcije bodova** — pregled ledgera + ručna korekcija

Dozvole: `loyalty.view`, `loyalty.update`, `loyalty_rewards.*`, `loyalty_cards.*`, `loyalty_in_store.operate`

### Fizičke kartice (radnja)

- **Marketing → Loyalty kartice** — pregled, štampa, zamjena, blokada
- **Marketing → Radnja — BNC bodovi** — pretraga kupca, evidentiranje kupovine, iskorištenje nagrade
- **Prodaja → Kupci** — izdavanje kartice, prikaz broja kartice i bodova

Dozvole kartica: `loyalty_cards.view`, `loyalty_cards.issue`, `loyalty_cards.block`, `loyalty_in_store.operate`

## API (storefront)

| Method | Route | Opis |
|--------|-------|------|
| GET | `/v1/loyalty/settings` | Javne postavke |
| GET | `/v1/customer/loyalty` | Stanje, nagrade, historija, **broj kartice** (Sanctum) |
| GET | `/v1/customer/pending-loyalty` | Pending bodovi (auth, samo vlastiti email) |
| GET | `/v1/orders/track/{token}` | Praćenje narudžbe uključuje `pending_loyalty_points` |
| POST | `/v1/cart/loyalty-reward` | Primijeni nagradu (Sanctum) |
| DELETE | `/v1/cart/loyalty-reward` | Ukloni nagradu (Sanctum) |

## Frontend

- `/nalog/bodovi` — stanje, nagrade, historija, **broj loyalty kartice**
- `/korpa` — odabir nagrade (ulogovani kupci)
- `/checkout` — prikaz loyalty popusta u sažetku
- `/nalog/registracija` — banner ako postoje pending bodovi
- `LoyaltyGuestPrompt` — komponenta za prikaz pending bodova gostima

## Email šablone

- `loyalty_points_earned`
- `loyalty_reward_unlocked`
- `loyalty_guest_register_prompt`
- `loyalty_card_issued`

## Fizičke kartice (in-store)

| Pravilo | Implementacija |
|---------|----------------|
| Izdavanje | Samo registrovan kupac (`LoyaltyCardService::issueCard`) |
| Format broja | `BNC-00012345` (8 cifara) |
| Saldo | Jedinstven s web shopom (`customers.loyalty_points_balance`) |
| Earn u radnji | `LoyaltyService::awardForInStoreSale` — obavezan broj računa |
| Redeem u radnji | `LoyaltyService::redeemInStore` — isti katalog nagrada |
| Lookup | Broj kartice, e-mail ili telefon (`LoyaltyCardService::findCustomerForInStore`) |
| Štampa kartice | `/admin/loyalty-cards/{id}/print` (browser print / PDF) |

Transakcije u radnji: tipovi `earn_in_store`, `redeem_in_store` s audit `meta` (račun, prodavač, lokacija).

## Baza

Tabele: `loyalty_rewards`, `loyalty_transactions`, `loyalty_redemptions`, `loyalty_pending_earnings`, **`loyalty_cards`**

Proširenja: `customers.loyalty_points_balance`, `orders` (points/loyalty polja), `carts.loyalty_reward_id`, `cart_items.is_loyalty_reward`

Postavke: `system_settings.key = loyalty`

## Testovi

```bash
php artisan test --filter=Loyalty
```

Pokriveno: kalkulator bodova, dodjela na isporuku, pending/claim, clawback, istek, **kartice i in-store transakcije**.

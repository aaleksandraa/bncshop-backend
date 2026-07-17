# 06 — Admin Screens (Filament)

## Navigation groups

1. **Dashboard** — KPI widgeti, graf prodaje
2. **Katalog** — Proizvodi, Kategorije, Brendovi, Atributi, Tagovi
3. **Prodaja** — Narudžbe, Kupci, Kuponi
4. **Marketing** — Popusti/Akcije, Shipping rules
5. **Integracije** — API Sync, Sync log
6. **Analitika** — Izvještaji (podgrupe)
7. **Sistem** — Korisnici, Email šabloni, SEO Redirects, Postavke, Audit log

## Product Resource — tabovi

| Tab | Polja | Akcije |
|-----|-------|--------|
| Osnovno | name, slug, descriptions, barcode, sku, flags, status | lock per field |
| Cijene | api/manual prices, rebate, margin, history table | lock price, revert API |
| Zalihe | api/reserved/available, override, status | allow backorder |
| Kategorija/Brend | category select, manufacturer select | |
| Atributi | repeater attribute values | lock value |
| Slike | gallery manager, primary select | lock gallery |
| SEO | meta, OG, canonical, robots, preview | lock SEO |
| Supplier | offers table read-only | |
| OLX | listing id, sync status | |
| Historija | activity log filtered | |
| Analitika | mini charts product metrics | |

## Permissions matrix

| Permission | Super Admin | Admin | Manager | Content | Warehouse | Analyst |
|------------|-------------|-------|---------|---------|-----------|---------|
| manage_products | ✓ | ✓ | ✓ | ✓ | view | view |
| view_margin | ✓ | ✓ | ✓ | ✗ | ✗ | ✓ |
| manage_discounts | ✓ | ✓ | ✓ | ✗ | ✗ | view |
| manage_orders | ✓ | ✓ | ✓ | ✗ | ✓ | view |
| manage_sync | ✓ | ✓ | ✗ | ✗ | ✗ | view |
| export_reports | ✓ | ✓ | ✓ | ✗ | ✗ | ✓ |

## API Sync screen

- Connection status badge
- Test connection button
- Manual full/incremental sync
- Jobs table: status, duration, created/updated/errors
- Job detail: per-page items log

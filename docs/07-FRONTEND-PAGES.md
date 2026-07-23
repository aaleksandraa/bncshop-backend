# 07 — Frontend Pages (Next.js)

## API base

`NEXT_PUBLIC_API_URL=http://localhost:8000/api/v1`

## Stranice

### `/` Home
- Hero banner (CMS setting)
- Featured categories grid
- On-sale products carousel
- New products section

### `/kategorija/[...slug]`
- Breadcrumb from category path
- Product grid with pagination
- Sidebar filters (dynamic per category)
- Sort dropdown
- SEO: category meta from API

### `/proizvod/[slug]`
- Image gallery with zoom
- Price (regular, sale, badge)
- Coupon preview when opened with `?kupon=CODE` (banner/deep link)
- Add to cart
- Technical specs (public attributes)
- Related products
- JSON-LD Product schema

### `/brend/[slug]`
- Brand header + logo
- Product listing

### `/pretraga?q=`
- Meilisearch results
- Instant search suggestions (optional)

### `/korpa`
- Line items, qty update, remove
- Subtotal, apply coupon, coupon discount line
- Auto-applied coupon from URL (`?kupon=CODE`) via global `CouponFromUrl`
- Price change warnings

### `/checkout`
Steps: Cart review → Customer info → Shipping method → Confirm
- Guest default, login link
- Shipping: pickup vs delivery with calculated fee

### `/narudzba/[token]`
- Order success + summary
- Guest order tracking by token

### `/nalog/*`
- `/nalog/prijava`, `/nalog/registracija`
- `/nalog/narudzbe`, `/nalog/profil`

## Components

- `ProductCard`, `ProductGrid`, `FilterSidebar`, `PriceDisplay`
- `CartDrawer`, `CheckoutSteps`, `BreadcrumbNav`
- `AnalyticsProvider` — tracks events to API

## SEO

- `app/robots.ts` — valid `robots.txt` on the storefront (`Sitemap:` + disallow private areas)
- `app/sitemap.ts` — XML sitemap from API `GET /sitemap` plus static routes
- `generateMetadata` per product/category/brand/blog/CMS (canonical, Open Graph, Twitter)
- Organization + WebSite JSON-LD in root layout; Product JSON-LD on product pages
- Backend `sitemap:generate` caches correct storefront paths (`/kategorija`, `/brend`, `/proizvod`)
- Legacy `/stranica/:slug` → `/:slug` permanent redirect in `next.config.ts`

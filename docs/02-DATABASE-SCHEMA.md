# 02 — Database Schema

Sve tabele koriste UUID primarne ključeve gdje je `external_*` ID iz API-ja, bigint auto-increment za interne entitete.

## users
| Kolona | Tip | Opis |
|--------|-----|------|
| id | bigint PK | |
| name | string | |
| email | string unique | |
| password | string | |
| is_customer | boolean | true = kupac, false = admin |
| email_verified_at | timestamp | |
| timestamps | | |

## roles / permissions (Spatie)
Standardne Spatie tabele: `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`.

## api_sources
| Kolona | Tip | Opis |
|--------|-----|------|
| id | bigint PK | |
| name | string | |
| target_system_code | string | |
| base_url | string | |
| username | text encrypted | |
| password | text encrypted | |
| access_token | text encrypted nullable | |
| refresh_token | text encrypted nullable | |
| token_expires_at | timestamp nullable | |
| last_successful_sync_at | timestamp nullable | |
| page_size | int default 500 | |
| sync_interval_minutes | int default 60 | |
| auto_sync_enabled | bool default true | A1 automatski inkrementalni sync |
| is_active | boolean | |
| connection_status | enum | connected, disconnected, error |
| last_error | text nullable | |

## manufacturers
| Kolona | Tip | external_manufacturer_id UUID unique |
| name, slug unique, featured, description, meta_title, meta_description, logo_url, system |

## categories
| Kolona | Tip | external_category_id UUID unique |
| parent_id FK nullable, full_slug unique, depth, path |
| margin_id, margin_name, margin_percentage | |
| olx_id, olx_name, system, pending_parent |
| image_url, icon_url, status |

## category_seo
category_id FK, meta_title, meta_description, og_image_url, h1, intro_text, footer_text

## products
| Kolona | Tip | |
| external_product_id UUID unique | |
| import_source string | a1, eline, manual |
| eline_sifra string nullable unique | eLine SKU |
| api_source_id FK nullable | veza na api_sources (eLine) |
| manufacturer_id, category_id FK | |
| name, slug unique, sku nullable, barcode | |
| description, short_description | |
| is_gaming, is_public, is_new, is_refurbished, status enum | active, draft, archived |
| margin_percentage decimal | |
| api_price, api_final_price, regular_price, display_price | |
| api_rebate, api_rebate_valid_until, api_rebate_type | |
| api_stock, reserved_stock, available_stock | |
| manual_stock_override nullable, stock_status | |
| price_locked, manual_price nullable | |
| default_image_id nullable | |
| api_views_count, first_imported_at | |
| sync_status enum | synced, missing_from_api, error |
| olx_listing_id, olx_synced_at, olx_last_error | |

## product_sync_locks
product_id FK, field_name, locked_by FK users, locked_at

## sync_diff_log
product_id, field_name, api_value, local_value, logged_at

## attribute_definitions
external_attribute_id UUID unique, name, api_type int, internal_type enum, is_public, is_filter, olx_id, olx_name, options_json jsonb

## attribute_category_mappings
attribute_definition_id, category_id, is_filter_enabled, is_public_enabled, sort_order

## product_attribute_values
product_id, attribute_definition_id, attribute_name_snapshot, raw_value, normalized_value, normalized_type, is_locked

## product_images / media_files
Spatie Media + product_images pivot sa external_image_id, is_primary, sort_order, source_url, status

## suppliers / product_supplier_offers
external_supplier_id, supplier_name, supplier_sku, supplier_price, supplier_stock, is_selected_price_source

## product_price_history
product_id, old_price, new_price, source enum, changed_by nullable, created_at

## tags / product_tags
Standard many-to-many

## discounts
type enum product|category|brand|attribute|tag, discount_type percent|fixed, value, starts_at, ends_at, is_active, badge_text, combines_with_coupons, include_subcategories, conditions_json jsonb

## discount_excluded_products / discount_excluded_brands
Pivot tabele

## coupons
code unique, type, value, min_cart_amount, max_uses, used_count, starts_at, ends_at, is_active, applicable_to jsonb

## coupon_usages
coupon_id, order_id, customer_id nullable, used_at

## shipping_rules
name, type enum global|category, category_id nullable, fixed_fee, free_threshold, pickup_enabled, is_active, priority

## carts / cart_items
session_id, user_id nullable, product_id, quantity, unit_price, discount_snapshot, price_confirmed

## customers / customer_addresses
user_id FK, phone, company_name, jib, default_address_id

## orders
order_number unique, customer_id nullable, guest fields, status enum, subtotal, discount_total, shipping_fee, total, shipping_method enum pickup|delivery, shipping_rule_snapshot jsonb, coupon_id nullable, payment_method, notes

## order_items
order_id, product_id nullable, external_product_id, snapshot fields (name, sku, barcode, brand, category, prices, qty, attributes_snapshot jsonb, supplier fields)

## order_status_history
order_id, old_status, new_status, changed_by, note, created_at

## seo_overrides
Polymorphic seoable, meta_title, meta_description, og_image_url, canonical, robots, is_locked

## redirects
from_path unique, to_path, status_code default 301

## analytics_events
event_type, session_id, user_id nullable, product_id nullable, category_id nullable, metadata jsonb, created_at

## daily_sales_snapshots
date, revenue, orders_count, items_sold, avg_order_value, metadata jsonb

## api_import_jobs / api_import_job_items
job status, started_at, completed_at, stats jsonb; items: page, records_count, duration_ms, errors

## eline_categories
name unique, product_count, last_seen_at

## eline_category_mappings
eline_category_id FK, category_id FK nullable, is_enabled, product_condition (refurbished|new), margin_percentage nullable

## eline_product_overrides
eline_sifra unique, is_enabled, category_id FK nullable, product_condition nullable

## audit_logs (activity_log Spatie)
Standard activity log

## email_templates
slug, subject, body_html, variables jsonb, is_active

## system_settings
key unique, value jsonb, group

## shop_campaigns
Kampanjski bedževi i landing stranice (npr. `/back-to-school`).

| Kolona | Tip | Opis |
|--------|-----|------|
| id | bigint PK | |
| name | string | Naziv kampanje |
| slug | string unique | URL `/{slug}` |
| badge_path | string | Putanja uploadane slike bedža |
| badge_alt | string nullable | Alt tekst |
| sort_order | smallint | Redoslijed bedževa na kartici |
| is_active | boolean | Ručni prekidač |
| starts_at / ends_at | timestamp nullable | Raspored prikaza |
| targeting_mode | enum | `categories` ili `products` |
| include_subcategories | boolean | Za category targeting |
| has_landing_page | boolean | Da li postoji listing stranica |
| page_title / page_description | string/text nullable | Sadržaj landing stranice |
| hero_image_path | string nullable | Hero slika |
| meta_title / meta_description | string/text nullable | SEO |

Pivot tabele: `shop_campaign_category`, `shop_campaign_product`, `shop_campaign_excluded_product`.

## report_cache
report_key, params_hash, data jsonb, expires_at

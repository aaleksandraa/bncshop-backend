# 03 — API Import Mapping

## Endpointi

```
POST /auth/login
GET  /api/integrations/{targetSystemCode}/categories
GET  /api/integrations/{targetSystemCode}/attributes
GET  /api/integrations/{targetSystemCode}/products?ModifiedAfter={ISO8601}&Page={n}&PageSize={size}
```

## Redoslijed synca

1. Auth → token
2. Categories (full ili incremental ako API podržava)
3. Attribute definitions
4. Products (paginated, ModifiedAfter)

## Product field mapping

| API | DB | Transform |
|-----|-----|-----------|
| productId | external_product_id | UUID string |
| name | name | trim; skip if locked |
| slug | slug | unique suffix on conflict |
| manufacturer | manufacturers + manufacturer_id | upsert by manufacturerId |
| category | categories + category_id | upsert by categoryId |
| isGaming | is_gaming | bool |
| isPublic | is_public | bool |
| description | description | HTMLPurifier |
| shortDescription | short_description | |
| barcode | barcode | |
| marginPercentage | margin_percentage | decimal |
| isNew | is_new | bool |
| price | api_price, regular_price | decimal 2 |
| finalPrice | api_final_price | decimal 2 |
| rebate | api_rebate | |
| rebateValidUntil | api_rebate_valid_until | date |
| rebateType | api_rebate_type | int raw |
| stock | api_stock | int; recalc available |
| viewsCount | api_views_count | |
| attributes[] | product_attribute_values | see below |
| gallery[] | product_images | soft delete missing |
| seoFields | seo_overrides polymorphic | if not locked |
| supplierOffers[] | product_supplier_offers | upsert by supplierId |

## Attribute value normalization

| raw value | normalized_type | normalized_value |
|-----------|-----------------|------------------|
| "true", "Da" | boolean | true |
| "false", "Ne" | boolean | false |
| numeric string | number | float |
| other | text | string |

## Timestamp rule

- `sync_started_at` snimljen na početku joba
- API koristi **stari** `last_successful_sync_at`
- Na uspješan završetak svih stranica: `last_successful_sync_at = sync_started_at`
- Na failure: timestamp ne mijenjati

## Edge cases

- **Missing parent category**: `pending_parent = true`, retry nakon category importa
- **Duplicate slug**: append `-2`, `-3`, create redirect if changed
- **Locked field**: write to sync_diff_log, skip update
- **Product missing from API**: sync_status = missing_from_api, ne brisati
- **Invalid optionsJson**: store raw, log warning

## Attribute type map (configurable)

```php
// config/bnc.php
'attribute_type_map' => [
    0 => 'text',
    1 => 'number',
    2 => 'boolean',
],
'rebate_type_map' => [], // pending API team confirmation
```

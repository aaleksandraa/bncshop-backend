# 08 — Analytics Reports

## Event types (analytics_events)

| event_type | Trigger | metadata |
|------------|---------|----------|
| product_view | Product page load | product_id, category_id |
| add_to_cart | Cart add | product_id, qty, price |
| remove_from_cart | Cart remove | product_id |
| checkout_start | Checkout step 1 | cart_total, item_count |
| checkout_step | Each step | step_number |
| order_created | Order confirmed | order_id, total |

## Dashboard KPIs

- Revenue today / yesterday / this month
- Orders count, AOV
- vs previous period % change
- Top 10 products, categories, brands
- Out of stock count
- Sync errors last 24h

## Reports

### Sales by period
- Filters: preset + custom range + compare
- Chart: revenue + orders dual axis
- Table: date, revenue, orders, items, AOV

### Sales by product
- From order_items snapshot
- Columns: product, qty, revenue, discount, margin (if permitted), conversion rate

### Sales by category
- Aggregate category_path from snapshot
- Share of total revenue %

### Sales by brand
- manufacturer_name from snapshot

### Sales by attribute
- Parse attributes_snapshot JSONB
- Group by attribute name + value (RAM, DPI, etc.)

### Discount performance
- Per discount_id: orders, revenue, discount_value

### Sync analytics
- From api_import_jobs: duration, counts, error rate

## daily_sales_snapshots

Nightly job `AggregateDailySales`:
```sql
INSERT INTO daily_sales_snapshots (date, revenue, orders_count, items_sold, avg_order_value)
SELECT DATE(created_at), SUM(total), COUNT(*), SUM(items_count), AVG(total)
FROM orders WHERE status NOT IN ('otkazano') GROUP BY DATE(created_at)
```

## Caching

Report results cached in `report_cache` 5 min TTL for dashboard, 1h for historical exports.

## Export

CSV and PDF via Filament export actions (requires export_reports permission).

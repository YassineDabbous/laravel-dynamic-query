# Dynamic Statistics & Metrics

[← Back to README](../README.md)

---

## Table of Contents

- [Overview](#overview)
- [Standard Aggregates](#standard-aggregates)
- [Custom SQL Metrics](#custom-sql-metrics)
- [Grouping with Stats](#grouping-with-stats)
- [Transforms](#transforms)
  - [Cumulative](#cumulative)
  - [Growth](#growth)
- [Period-over-Period Comparison](#period-over-period-comparison)
- [Stats API Response Format](#stats-api-response-format)
- [Caching](#caching)
- [Scope Signatures](#scope-signatures)

---

## Overview

The `HasDynamicStats` trait provides aggregate calculations (count, sum, avg, min, max), custom SQL metrics, data transforms, and period-over-period comparisons. It's designed for building dashboards and analytics endpoints.

**Pipeline:** `dynamicStats()` internally calls `dynamicGroupBy()` and `dynamicFilter()`, so you get grouping and filtering automatically.

---

## Standard Aggregates

Use `_metric` with the format `type` or `type:column`:

| Metric | URL | SQL |
|--------|-----|-----|
| Count | `?_metric=count` | `COUNT(*)` |
| Sum | `?_metric=sum:total` | `SUM(total)` |
| Average | `?_metric=avg:price` | `AVG(price)` |
| Min | `?_metric=min:price` | `MIN(price)` |
| Max | `?_metric=max:price` | `MAX(price)` |

The column must be listed in `dynamicColumns()` (if defined). Invalid types fall back to `count`.

**Driver-aware casting:** Results are automatically cast:
- MySQL: `SIGNED` / `DECIMAL(10,2)`
- PostgreSQL: `BIGINT` / `DECIMAL(10,2)`
- SQLite: `INTEGER` / `REAL`

---

## Custom SQL Metrics

For complex calculations like ratios or formulas, override `dynamicMetrics()`:

```php
public function dynamicMetrics(): array
{
    return [
        'aov'            => 'SUM(total_amount) / COUNT(id)',
        'conversion_rate' => 'SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) * 100.0 / COUNT(*)',
    ];
}
```

**Usage:**

```
GET /api/orders?_metric=aov&_group=created_at:month
```

> **⚠️ Security:** Custom metric SQL is developer-defined and injected via `selectRaw()`. **Never** construct these strings from user input.

---

## Grouping with Stats

Stats work seamlessly with [Dynamic Grouping](grouping.md):

```
# Revenue by month
GET /api/orders?_metric=sum:total&_group=created_at:month

# Average price by category
GET /api/products?_metric=avg:price&_group=category_id

# Count by status and month
GET /api/orders?_metric=count&_group=status,created_at:month
```

---

## Transforms

### Cumulative

Adds a running total to the result set:

```
GET /api/orders?_metric=sum:total&_group=created_at:month&_transform=cumulative
```

Each row gets a `cumulative_value` field:

```json
[
  { "created_at_month": "2024-01", "value": 1000, "cumulative_value": 1000 },
  { "created_at_month": "2024-02", "value": 1500, "cumulative_value": 2500 },
  { "created_at_month": "2024-03", "value": 800,  "cumulative_value": 3300 }
]
```

### Growth

Calculates the percentage change between consecutive rows:

```
GET /api/orders?_metric=sum:total&_group=created_at:month&_transform=growth
```

Each row gets a `growth_percentage` field:

```json
[
  { "created_at_month": "2024-01", "value": 1000, "growth_percentage": null },
  { "created_at_month": "2024-02", "value": 1500, "growth_percentage": 50.0 },
  { "created_at_month": "2024-03", "value": 800,  "growth_percentage": -46.67 }
]
```

---

## Period-over-Period Comparison

Compare current data with a previous time period:

```
GET /api/orders?_metric=sum:total&_group=created_at:month&_compare=previous_period&created_at[]=2024-01-01&created_at[]=2024-03-31
```

**Requirements:**
- `_compare=previous_period`
- A date range filter on the comparison column (default: `created_at`)
- Optionally set `_compare_on=delivery_date` to use a different column

The package:
1. Calculates the range duration in days.
2. Shifts the window backwards to get the previous period.
3. Runs both queries and returns them with a summary delta.

**Response:**

```json
{
  "current": [...],
  "previous": [...],
  "summary": {
    "value": 3300,
    "previous_value": 2800,
    "delta": 500,
    "percent": 17.86,
    "formatted": "3300"
  }
}
```

---

## Stats API Response Format

Use `dynamicStatsAPI()` for a structured response with metadata:

```php
// In your controller:
return Product::dynamicStatsAPI();
```

**Response structure:**

```json
{
  "meta": {
    "metric": {
      "raw": "sum:total",
      "type": "sum",
      "field": "total"
    },
    "currency": "USD",
    "timezone": "UTC",
    "granularity": "created_at:month"
  },
  "summary": {
    "value": 3300.0,
    "formatted": "3300",
    "type": "total"
  },
  "dataset": [
    {
      "label": "2024-01",
      "group": { "created_at_month": "2024-01" },
      "value": 1000.0,
      "previous_value": null,
      "transforms": {}
    }
  ]
}
```

The `StatsTransformer` class handles:
- Matching current/previous period data by group keys.
- Building labels from group values.
- Calculating summaries (total or unweighted average for `avg` metrics).
- Including transform values in the dataset.

---

## Caching

Enable stats caching in config:

```php
'settings' => [
    'enable_stats_cache' => true,
],
'defaults' => [
    'cache_ttl' => 600, // 10 minutes
],
```

The cache key includes:
- Table name
- MD5 hash of input params, SQL query, and bindings

This ensures different queries/filters produce different cache entries.

---

## Scope Signatures

```php
// Raw stats (returns Collection or array)
public function scopeDynamicStats(
    Builder $q,
    ?array $input = [],
    ?array $allowed = null,
    ?array $default = null,
    ?array $ignore = null
): mixed;

// Formatted stats API (returns structured array)
public function scopeDynamicStatsAPI(
    Builder $q,
    array $input = []
): array;
```

**Programmatic usage:**

```php
// Dashboard stats
$stats = Order::dynamicStats([
    '_metric' => 'sum:total',
    '_group' => 'created_at:month',
    'status' => 'completed',
]);

// API with metadata
$response = Order::dynamicStatsAPI([
    '_metric' => 'count',
    '_group' => 'status',
]);
```

# Dynamic Grouping

[← Back to README](../README.md)

---

## Table of Contents

- [Overview](#overview)
- [Defining Groupable Columns](#defining-groupable-columns)
- [Standard Grouping](#standard-grouping)
- [Date Macros](#date-macros)
- [Timezone Support](#timezone-support)
- [Multi-Column Grouping](#multi-column-grouping)
- [Grouping on Related Columns](#grouping-on-related-columns)
- [Default Grouping](#default-grouping)
- [SQL Generation](#sql-generation)
- [Scope Signature](#scope-signature)

---

## Overview

The `HasDynamicGroup` trait allows API consumers to group results by columns or date-based macros. Grouping is commonly used with [Dynamic Statistics](statistics.md) to aggregate data over categories or time periods.

---

## Defining Groupable Columns

Override `dynamicGroups()` to whitelist columns:

```php
public function dynamicGroups(): array
{
    return ['status', 'category_id', 'created_at', 'user.country'];
}
```

---

## Standard Grouping

```
GET /api/orders?_group=status
GET /api/orders?_group=category_id
```

The grouped column is automatically added to the SELECT clause so it appears in results.

---

## Date Macros

Append a macro to a date column using `column:macro` syntax:

```
GET /api/orders?_group=created_at:month
GET /api/orders?_group=created_at:year
GET /api/orders?_group=created_at:day
GET /api/orders?_group=created_at:hour
```

| Macro | Format | Example Output |
|-------|--------|----------------|
| `year` | `YYYY` | `2024` |
| `month` | `YYYY-MM` | `2024-03` |
| `day` | `YYYY-MM-DD` | `2024-03-15` |
| `hour` | `YYYY-MM-DD HH:00` | `2024-03-15 14:00` |

The selected alias follows the pattern `column_macro` (e.g., `created_at_month`).

---

## Timezone Support

Override the default UTC timezone for date grouping:

```
GET /api/orders?_group=created_at:month&_timezone=America/New_York
GET /api/orders?_group=created_at:day&_timezone=+03:00
```

The package validates timezone values against PHP's `DateTimeZone::listIdentifiers()` and offset format `±HH:MM`. Invalid values fall back to UTC.

MySQL uses `CONVERT_TZ()`, PostgreSQL uses `AT TIME ZONE`, and SQLite ignores timezone (uses stored value).

---

## Multi-Column Grouping

Combine multiple group columns:

```
GET /api/orders?_group=created_at:month,status
GET /api/orders?_group=category_id,user.country
```

---

## Grouping on Related Columns

Dot-notation triggers smart joins:

```
GET /api/orders?_group=user.country
```

The `users` table is automatically joined, and the query groups by `users.country`.

---

## Default Grouping

You can provide default group-by columns that are applied when the client doesn't specify any:

```php
Product::dynamicGroupBy([], null, ['category_id']);
```

Defaults are validated against the whitelist if one is defined.

---

## SQL Generation

The package generates database-specific SQL for date macros:

**MySQL:**
```sql
DATE_FORMAT(CONVERT_TZ(created_at, '+00:00', ?), '%Y-%m')
-- binding: 'America/New_York'
```

**PostgreSQL:**
```sql
TO_CHAR((created_at at time zone 'UTC' at time zone ?), 'YYYY-MM')
-- binding: 'America/New_York'
```

**SQLite:**
```sql
strftime('%Y-%m', created_at)
-- (no timezone support)
```

---

## Scope Signature

```php
public function scopeDynamicGroupBy(
    Builder $q,
    ?array $input = [],       // Custom input (defaults to request()->all())
    ?array $allowed = null,   // Override allowed group columns
    ?array $default = null,   // Default grouping if none requested
    ?array $ignore = null     // Columns to exclude
): Builder;
```

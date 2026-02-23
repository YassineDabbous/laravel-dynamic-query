# Dynamic Sorting

[← Back to README](../README.md)

---

## Table of Contents

- [Overview](#overview)
- [Defining Sortable Columns](#defining-sortable-columns)
- [Requesting Sorts via URL](#requesting-sorts-via-url)
- [Multi-Column Sorting](#multi-column-sorting)
- [Descending Order](#descending-order)
- [Default Sorting](#default-sorting)
- [Sorting on Related Columns](#sorting-on-related-columns)
- [Scope Signature](#scope-signature)

---

## Overview

The `HasDynamicSort` trait enables API consumers to control the result ordering via URL parameters. Only whitelisted columns can be sorted.

---

## Defining Sortable Columns

Override `dynamicSorts()` to whitelist columns that can be sorted:

```php
public function dynamicSorts(): array
{
    return ['id', 'name', 'price', 'created_at', 'user.name'];
}
```

If you return `[]` (default), **no sorting** is applied unless defaults are provided.

---

## Requesting Sorts via URL

Use the `_sort` parameter:

```
GET /api/products?_sort=price
GET /api/products?_sort=name
```

Multiple formats:

```
# Comma-separated
?_sort=price,name

# Array notation
?_sort[]=price&_sort[]=name
```

---

## Multi-Column Sorting

Multiple columns are sorted in the order specified:

```
GET /api/products?_sort=category_id,-price,name
```

Generates:
```sql
ORDER BY category_id ASC, price DESC, name ASC
```

---

## Descending Order

Prefix the column with `-` for descending:

```
GET /api/products?_sort=-price      # price DESC
GET /api/products?_sort=-created_at # newest first
```

---

## Default Sorting

Provide default sort columns that are used when the client doesn't specify any:

```php
// In your controller or scope call:
Product::dynamicSort([], ['created_at'], ['-created_at']);
```

Or override behavior in your model using the scope arguments.

---

## Sorting on Related Columns

Use dot-notation to sort by a column on a related table:

```
GET /api/posts?_sort=user.name
```

This automatically creates a `JOIN` with the `users` table and sorts by `users.name`. The relation must be whitelisted in `dynamicSorts()`.

Supported relation types for smart joins: `BelongsTo`, `HasOne`, `HasMany`.

---

## Scope Signature

```php
public function scopeDynamicSort(
    Builder $q,
    ?array $input = [],       // Custom input (defaults to request()->all())
    ?array $allowed = null,   // Override allowed sort columns
    ?array $default = null,   // Default sort if none requested
    ?array $ignore = null     // Columns to exclude from sorting
): Builder;
```

**Programmatic usage:**

```php
// Manual sort input
Product::dynamicSort(['_sort' => '-price,name']);

// Default sort
Product::dynamicSort([], null, ['-created_at']);
```

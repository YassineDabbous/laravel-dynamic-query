# Dynamic Pagination

[← Back to README](../README.md)

---

## Table of Contents

- [Overview](#overview)
- [Basic Pagination](#basic-pagination)
- [Custom Per-Page](#custom-per-page)
- [Simple Pagination](#simple-pagination)
- [Get All Mode](#get-all-mode)
- [Limits & Safety](#limits--safety)
- [Macro Signature](#macro-signature)

---

## Overview

The `dynamicPaginate()` macro is registered by the service provider on Eloquent Builder, Query Builder, `BelongsToMany`, and `HasManyThrough`. It provides configurable pagination with built-in safety limits.

---

## Basic Pagination

```
GET /api/products?page=1
```

Uses Laravel's standard `paginate()` with the configured default `per_page` (default: 15).

---

## Custom Per-Page

```
GET /api/products?per_page=25
```

The client can override items per page. The value is clamped:
- Minimum: 1 (defaults to `per_page` config if ≤ 0)
- Maximum: `max_per_page` config (default: 100)

---

## Simple Pagination

For better performance on large datasets, use `simplePaginate`:

```
GET /api/products?_simple=true
```

This skips the total count query, making it faster but without `total` and `last_page` in the response.

---

## Get All Mode

When enabled, clients can skip pagination entirely:

```
GET /api/products?_get_all=true
```

This returns a plain collection instead of a paginator. 

**Configuration:**

```php
'defaults' => [
    'allow_get_all' => true,   // Enable get-all mode (default: false)
    'max_get_all'   => 1000,   // Maximum records returned
],
```

The client can also set a custom limit:

```
GET /api/products?_get_all=true&_limit=500
```

The limit is clamped to `max_get_all`.

---

## Limits & Safety

| Config | Default | Purpose |
|--------|---------|---------|
| `defaults.per_page` | `15` | Default page size |
| `defaults.max_per_page` | `100` | Maximum `per_page` allowed |
| `defaults.allow_get_all` | `false` | Enable/disable `_get_all` |
| `defaults.max_get_all` | `1000` | Maximum records in get-all mode |

These defaults prevent accidental resource exhaustion from large queries.

---

## Macro Signature

```php
$query->dynamicPaginate(
    ?int $maxPerPage = null,   // Override max per-page
    ?array $input = null,      // Custom input (defaults to request()->all())
    ?bool $allowGet = null,    // Override allow_get_all
    $columns = ['*'],          // Columns to select
    $pageName = null,          // Custom page parameter name
    $page = null,              // Force specific page
    $total = null              // Custom total count
);
```

**Usage in controller:**

```php
// Default behavior
return Product::dynamicQuery()->dynamicPaginate();

// Custom max per-page
return Product::dynamicQuery()->dynamicPaginate(50);

// Allow get-all for this specific endpoint
return Product::dynamicQuery()->dynamicPaginate(null, null, true);
```

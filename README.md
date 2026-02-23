# Laravel Dynamic Query

[![Latest Version](https://img.shields.io/packagist/v/yassinedabbous/laravel-dynamic-query.svg?style=flat-square)](https://packagist.org/packages/yassinedabbous/laravel-dynamic-query)
[![License](https://img.shields.io/packagist/l/yassinedabbous/laravel-dynamic-query.svg?style=flat-square)](LICENSE)

**Build powerful, dynamic API queries from URL parameters.**  
Let your API consumers select fields, filter, sort, group, paginate, and compute statistics — all from a single query string.

---

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Usage Overview](#usage-overview)
  - [Dynamic Fields](#dynamic-fields)
  - [Dynamic Filtering](#dynamic-filtering)
  - [Dynamic Sorting](#dynamic-sorting)
  - [Dynamic Grouping](#dynamic-grouping)
  - [Dynamic Statistics](#dynamic-statistics)
  - [Dynamic Pagination](#dynamic-pagination)
- [All-in-One Scopes](#all-in-one-scopes)
- [Configuration](#configuration)
- [Detailed Documentation](#detailed-documentation)
- [Security](#security)
- [Testing](#testing)
- [License](#license)

---

## Features

| Feature | Description |
|---------|-------------|
| **Field Selection** | Clients choose which columns, relations, and appends to include in the response |
| **Filtering** | 30+ operators including `like`, `between`, `in`, `null`, JSON operators, full-text search, and relation existence checks |
| **Sorting** | Multi-column sorting with ascending/descending via `-` prefix |
| **Grouping** | Group by columns or date macros (`year`, `month`, `day`, `hour`) with timezone support |
| **Statistics** | Aggregate metrics (`count`, `sum`, `avg`, `min`, `max`), custom SQL metrics, cumulative/growth transforms, and period-over-period comparison |
| **Pagination** | Configurable `paginate` / `simplePaginate` / `get all` with per-page limits |
| **Smart Joins** | Automatic `JOIN` generation from dot-notation (`user.email`) on filters, sorts, and groups |
| **Date Presets** | Semantic date ranges like `today`, `this_week`, `last_30_days`, `ytd` |
| **Auto Relations** | Discover model relations via PHP Reflection — zero configuration |
| **Security** | Strict whitelist-based filtering, SQL alias sanitization, and configurable defaults |

---

## Requirements

- PHP >= 8.0
- Laravel 8.x / 9.x / 10.x / 11.x

---

## Installation

```bash
composer require yassinedabbous/laravel-dynamic-query
```

Publish the configuration file (optional):

```bash
php artisan vendor:publish --tag=dynamic-query-config
```

> The service provider is auto-discovered by Laravel.

---

## Quick Start

### 1. Add the trait to your model

```php
use YassineDabbous\DynamicQuery\HasDynamicQuery;

class Product extends Model
{
    use HasDynamicQuery;

    public function dynamicColumns(): array
    {
        return ['id', 'name', 'price', 'category_id', 'created_at'];
    }

    public function dynamicFilters(): array
    {
        return [
            'name'        => ['=', 'like%'],
            'price'       => ['=', '>', '<', '>=', '<=', 'between'],
            'category_id' => ['=', 'in'],
            'created_at'  => null, // all operators
        ];
    }

    public function dynamicSorts(): array
    {
        return ['id', 'name', 'price', 'created_at'];
    }
}
```

### 2. Use it in your controller

```php
class ProductController extends Controller
{
    public function index()
    {
        // All-in-one: select, filter, sort, group, paginate, append
        return Product::dynamicAPI();

        // Or step-by-step:
        return Product::dynamicSelect()
                      ->dynamicFilter()
                      ->dynamicSort()
                      ->dynamicPaginate();
    }
}
```

### 3. Call your API

```
GET /api/products?_fields=id,name,price&name=Widget&_sort=-price&per_page=10
```

Response includes only `id`, `name`, `price` — filtered by name, sorted by price descending, 10 per page.

---

## Usage Overview

### Dynamic Fields

Select specific columns, relations, and nested relation fields:

```
GET /api/products?_fields=id,name,category:id|name
```

Declare what's available on your model:

```php
public function dynamicColumns(): array {
    return ['id', 'name', 'price', 'category_id'];
}

public function dynamicRelations(): array {
    return [
        'category' => 'category_id',       // depends on category_id column
        'reviews'  => null,                 // no column dependency
    ];
}

public function dynamicAppends(): array {
    return [
        'full_name'   => ['first_name', 'last_name'],  // depends on columns
        'status_label' => 'status',                     // depends on status column
    ];
}

public function dynamicAggregates(): array {
    return [
        'reviews_count' => fn($q) => $q->withCount('reviews'),
    ];
}
```

📖 [**Full Fields Documentation →**](docs/fields.md)

---

### Dynamic Filtering

Filter with any of 30+ operators:

```
GET /api/products?price=>=100&name=Widget&_operators[name]=like%
```

Negate with `!` prefix:

```
GET /api/products?!category_id=5          # category_id != 5
GET /api/products?status=active&_logic=or  # OR logic across filters
```

Supports: `=`, `!=`, `<`, `>`, `<=`, `>=`, `like`, `%like%`, `in`, `between`, `null`, `has`, `full_text`, `json_contains`, `json_overlaps`, and more.

📖 [**Full Filtering Documentation →**](docs/filtering.md)

---

### Dynamic Sorting

Sort by one or more columns. Prefix with `-` for descending:

```
GET /api/products?_sort=price            # ASC
GET /api/products?_sort=-price,name      # price DESC, then name ASC
GET /api/products?_sort=user.name        # sort by related column (auto-join)
```

📖 [**Full Sorting Documentation →**](docs/sorting.md)

---

### Dynamic Grouping

Group results with optional date macros:

```
GET /api/products?_group=category_id
GET /api/orders?_group=created_at:month&_timezone=America/New_York
GET /api/orders?_group=created_at:year,status
```

Supported macros: `year`, `month`, `day`, `hour`.

📖 [**Full Grouping Documentation →**](docs/grouping.md)

---

### Dynamic Statistics

Compute metrics with grouping, transforms, comparisons, and caching:

```
GET /api/orders?_metric=sum:total&_group=created_at:month
GET /api/orders?_metric=avg:total&_transform=growth
GET /api/orders?_metric=count&_compare=previous_period&created_at[]=2024-01-01&created_at[]=2024-03-31
```

📖 [**Full Statistics Documentation →**](docs/statistics.md)

---

### Dynamic Pagination

```
GET /api/products?per_page=25            # 25 items per page
GET /api/products?_get_all=true          # return all (if enabled)
GET /api/products?_simple=true           # use simplePaginate
```

📖 [**Full Pagination Documentation →**](docs/pagination.md)

---

## All-in-One Scopes

| Scope | Description |
|-------|-------------|
| `dynamicQuery()` | Applies Select + Filter + Sort + Group |
| `dynamicAPI()` | Applies Select + Filter + Sort + Group + Paginate + Append |

```php
// In a controller:
return Product::dynamicAPI();

// With explicit input (e.g. in a job or test):
return Product::dynamicAPI(['_fields' => 'id,name', '_sort' => '-price']);
```

---

## Configuration

After publishing, edit `config/dynamic-query.php`:

```php
return [
    'defaults' => [
        'per_page'      => 15,
        'max_per_page'  => 100,
        'allow_get_all' => false,
        'max_get_all'   => 1000,
        'cache_ttl'     => 600,
        'timezone'      => 'UTC',
    ],
    'settings' => [
        'enable_stats_cache' => false,
        'relation_guess'     => true,     // Auto-discover relations via Reflection
        'strict_filtering'   => true,     // Whitelist-only filtering
        'clean_response'     => true,     // Limit response to requested fields
    ],
    'params' => [
        'fields'  => '_fields',
        'sort'    => '_sort',
        'logic'   => '_logic',
        // ... and more
    ],
];
```

📖 [**Full Configuration Reference →**](docs/configuration.md)

---

## Detailed Documentation

| Document | Topics |
|----------|--------|
| [Installation](docs/installation.md) | Install, publish config, service provider |
| [Configuration](docs/configuration.md) | All config options, parameter names, defaults |
| [Fields & Selection](docs/fields.md) | Columns, relations, appends, aggregates, deep fields, response cleaning |
| [Filtering](docs/filtering.md) | All operators, negation, OR logic, smart joins, named scopes, date presets, JSON operators |
| [Sorting](docs/sorting.md) | Multi-column, direction prefix, related-column sorting |
| [Grouping](docs/grouping.md) | Standard grouping, date macros, timezone, SQL generation |
| [Statistics & Metrics](docs/statistics.md) | Aggregates, custom metrics, transforms, period comparison, caching, Stats API |
| [Pagination](docs/pagination.md) | Per-page, get-all, simple paginate, limits |
| [Advanced](docs/advanced.md) | Smart joins, auto-relation discovery, DynamicQueryable contract, morph model resolution, programmatic input |

---

## Security

This package follows a **whitelist-first** security approach:

- **Strict Filtering** — Only filters defined in `dynamicFilters()` are applied (enabled by default).
- **Column Whitelisting** — Only columns listed in `dynamicColumns()` can be selected.
- **SQL Sanitization** — All dynamically generated aliases are sanitized via regex.
- **Custom Metrics Safety** — `dynamicMetrics()` values are developer-defined SQL, never user input.
- **Pagination Limits** — `max_per_page` and `max_get_all` prevent resource exhaustion.

---

## Testing

```bash
composer test
```

---

## License

The MIT License (MIT). See [LICENSE](LICENSE) for details.

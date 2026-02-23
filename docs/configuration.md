# Configuration Reference

[← Back to README](../README.md)

---

## Table of Contents

- [Overview](#overview)
- [Defaults](#defaults)
- [Settings](#settings)
- [URL Parameter Names](#url-parameter-names)
- [Example Config File](#example-config-file)

---

## Overview

After publishing the config with:

```bash
php artisan vendor:publish --tag=dynamic-query-config
```

You will find the configuration file at `config/dynamic-query.php`. All values have sensible defaults, so publishing is optional.

---

## Defaults

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `per_page` | `int` | `15` | Default items per page when paginating |
| `max_per_page` | `int` | `100` | Maximum allowed `per_page` value from client |
| `allow_get_all` | `bool` | `false` | Whether `_get_all=true` is allowed |
| `max_get_all` | `int` | `1000` | Maximum records returned when `_get_all` is enabled |
| `cache_ttl` | `int` | `600` | Cache duration in seconds for statistics (when caching is enabled) |
| `timezone` | `string` | `'UTC'` | Default timezone for date grouping |
| `currency` | `string` | `'USD'` | Currency label included in stats API metadata |

---

## Settings

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `enable_stats_cache` | `bool` | `false` | Enable/disable automatic caching for `dynamicStats()` results |
| `relation_guess` | `bool` | `true` | Auto-discover model relations via PHP Reflection (no manual `dynamicRelations()` needed) |
| `strict_filtering` | `bool` | `true` | When `true`, only columns listed in `dynamicFilters()` can be filtered on. **Recommended for production** |
| `clean_response` | `bool` | `true` | When `true`, model `setVisible()` is called to strip unrequested fields from the JSON response |

---

## URL Parameter Names

Every URL parameter name is configurable. This lets you avoid collisions with your own query parameters or conform to a naming convention.

### Core Parameters

| Config Key | Default | Purpose | Example |
|------------|---------|---------|---------|
| `model` | `_model` | Morph alias for `resolveDynamicModel()` | `?_model=post` |
| `fields` | `_fields` | Field selection | `?_fields=id,name` |
| `logic` | `_logic` | Filter logic (`and` / `or`) | `?_logic=or` |
| `operators` | `_operators` | Per-field operator overrides | `?_operators[name]=like%` |
| `sort` | `_sort` | Sort columns | `?_sort=-price` |
| `limit` | `_limit` | Limit for get-all mode | `?_limit=500` |
| `per_page` | `per_page` | Items per page | `?per_page=25` |
| `get_all` | `_get_all` | Toggle get-all mode | `?_get_all=true` |
| `clause` | `_clause` | Default clause (`where` / `having`) | `?_clause=having` |
| `clauses` | `_clauses` | Per-field clause overrides | `?_clauses[total]=having` |
| `simple` | `_simple` | Use `simplePaginate()` | `?_simple=true` |

### Stats & Grouping Parameters

| Config Key | Default | Purpose | Example |
|------------|---------|---------|---------|
| `metric` | `_metric` | Aggregate function | `?_metric=sum:total` |
| `col` | `_col` | Column for metric | `?_col=price` |
| `group` | `_group` | Group-by columns | `?_group=created_at:month` |
| `period` | `_period` | Date period macro | `?_period=month` |
| `alias` | `_alias` | Column alias | `?_alias=revenue` |
| `transform` | `_transform` | Post-processing transform | `?_transform=cumulative` |
| `compare` | `_compare` | Comparison mode | `?_compare=previous_period` |
| `compare_on` | `_compare_on` | Date column for comparison | `?_compare_on=created_at` |
| `timezone` | `_timezone` | Override timezone | `?_timezone=Asia/Tokyo` |
| `cache` | `_cache` | Cache control | `?_cache=false` |

---

## Example Config File

```php
<?php

return [

    'defaults' => [
        'per_page'      => 15,
        'max_per_page'  => 100,
        'allow_get_all' => false,
        'max_get_all'   => 1000,
        'cache_ttl'     => 600,
        'timezone'      => 'UTC',
        'currency'      => 'USD',
    ],

    'settings' => [
        'enable_stats_cache' => false,
        'relation_guess'     => true,
        'strict_filtering'   => true,
        'clean_response'     => true,
    ],

    'params' => [
        'model'      => '_model',
        'fields'     => '_fields',
        'logic'      => '_logic',
        'operators'  => '_operators',
        'sort'       => '_sort',
        'limit'      => '_limit',
        'per_page'   => 'per_page',
        'get_all'    => '_get_all',
        'clause'     => '_clause',
        'clauses'    => '_clauses',

        'metric'     => '_metric',
        'col'        => '_col',
        'group'      => '_group',
        'period'     => '_period',
        'alias'      => '_alias',
        'transform'  => '_transform',
        'compare'    => '_compare',
        'compare_on' => '_compare_on',
        'timezone'   => '_timezone',
        'cache'      => '_cache',
        'simple'     => '_simple',
    ],

];
```

# Dynamic Filtering

[← Back to README](../README.md)

---

## Table of Contents

- [Overview](#overview)
- [Defining Allowed Filters](#defining-allowed-filters)
- [Basic Filtering](#basic-filtering)
- [Operators](#operators)
  - [Standard Operators](#standard-operators)
  - [Wildcard / LIKE Operators](#wildcard--like-operators)
  - [Complex Operators](#complex-operators)
- [Specifying Operators via URL](#specifying-operators-via-url)
- [Smart Operator Parsing](#smart-operator-parsing)
- [Negation](#negation)
- [OR Logic](#or-logic)
- [HAVING Clause](#having-clause)
- [Filtering on Related Columns](#filtering-on-related-columns)
- [Named Scopes as Filters](#named-scopes-as-filters)
- [Date Presets](#date-presets)
- [Strict Filtering](#strict-filtering)
- [Scope Signature](#scope-signature)

---

## Overview

The `HasDynamicFilter` trait provides a powerful, whitelist-based filtering system. Clients pass filter values as URL parameters, and the package builds the appropriate query conditions.

**Resolution order for each filter:**
1. Check if the key matches a named scope → call it.
2. Otherwise, qualify the column (with smart joins if dot-notation).
3. Apply the operator logic (standard, wildcard, or complex).

---

## Defining Allowed Filters

Override `dynamicFilters()` to whitelist filterable columns and their allowed operators:

```php
public function dynamicFilters(): array
{
    return [
        'name'         => null,                                // all operators allowed
        'price'        => ['=', '!=', '<', '<=', '>', '>='],   // only comparison operators
        'status'       => ['=', 'in'],                         // exact match or IN
        'user.email'   => '=',                                 // auto-joins users table
        'description'  => ['like%', '%like%'],                 // search operators
        'tags'         => ['json_contains'],                   // JSON operator
        'with_trashed' => null,                                // named scope
    ];
}
```

---

## Basic Filtering

Pass filter values as query parameters using the column name as the key:

```
GET /api/products?status=active
GET /api/products?price=99.99
GET /api/products?name=Widget
```

---

## Operators

### Standard Operators

| Operator | Description | Example |
|----------|-------------|---------|
| `=` | Equal (default) | `?status=active` |
| `!=` or `<>` | Not equal | `?status=!=draft` |
| `<` | Less than | `?price=<100` |
| `>` | Greater than | `?price=>50` |
| `<=` | Less or equal | `?price=<=100` |
| `>=` | Greater or equal | `?price=>=50` |
| `<=>` | Null-safe equal | |
| `is` | IS | |
| `is not` | IS NOT | |

### Wildcard / LIKE Operators

| Operator | SQL Pattern | Example |
|----------|-------------|---------|
| `like%` | `value%` (starts with) | `?name=Wid&_operators[name]=like%` |
| `%like` | `%value` (ends with) | `?name=get&_operators[name]=%like` |
| `%like%` | `%value%` (contains) | `?name=idg&_operators[name]=%like%` |
| `like` | Raw LIKE (you provide wildcards) | `?name=%Widget%&_operators[name]=like` |
| `ilike` | Case-insensitive LIKE (PostgreSQL) | |
| `rlike` / `regexp` | Regular expression | |

### Complex Operators

| Operator | Value Type | Description | Example |
|----------|-----------|-------------|---------|
| `in` | `array` | WHERE IN | `?status[]=active&status[]=pending&_operators[status]=in` |
| `between` | `array[2]` | WHERE BETWEEN | `?price[]=10&price[]=100&_operators[price]=between` |
| `null` | — | WHERE NULL | `?deleted_at=1&_operators[deleted_at]=null` |
| `full_text` | `string` | Full-text search | `?body=search+term&_operators[body]=full_text` |
| `has` | — | Relation existence | `?comments=1&_operators[comments]=has` |
| `json_contains` | `mixed` | JSON_CONTAINS | `?tags=php&_operators[tags]=json_contains` |
| `json_contains_key` | — | JSON key exists | |
| `json_overlaps` | `mixed` | JSON_OVERLAPS | |
| `json_length` | `int` | JSON array length | |

---

## Specifying Operators via URL

Use the `_operators` parameter to set the operator for each filter:

```
GET /api/products?name=Widget&_operators[name]=like%
GET /api/products?price[]=10&price[]=100&_operators[price]=between
```

If no operator is specified, `=` is used by default.

---

## Smart Operator Parsing

When the operator is `=`, the package can detect operators embedded in the value:

```
GET /api/products?price=>=100
#                        ^^ automatically parsed as operator >= with value 100
```

This works for: `<`, `>`, `<=`, `>=`, `!=`, `<>`.

---

## Negation

### Prefix the parameter name with `!`

```
GET /api/products?!category_id=5
# → WHERE category_id != 5
```

### Prefix the operator with `!`

```
GET /api/products?tags=php&_operators[tags]=!json_contains
# → WHERE NOT JSON_CONTAINS(tags, 'php')
```

Both approaches toggle the `NOT` clause. If both are used, they cancel out (double negation).

---

## OR Logic

By default, multiple filters are combined with `AND`. Use `_logic=or` for `OR`:

```
GET /api/products?name=Widget&price=99.99&_logic=or
# → WHERE name = 'Widget' OR price = 99.99
```

---

## HAVING Clause

For filtering on aggregated values, use the `_clause` or `_clauses` parameters:

```
# Global HAVING for all filters
GET /api/orders?total=>=1000&_clause=having

# Per-field HAVING
GET /api/orders?total=>=1000&_clauses[total]=having&status=active
# → WHERE status = 'active' HAVING total >= 1000
```

The HAVING clause supports standard operators plus `in`, `between`, and `null`.

---

## Filtering on Related Columns

Use dot-notation to filter on a related model's column. The package automatically creates a `JOIN`:

```
GET /api/posts?user.email=john@example.com
```

This requires:
1. A `user` relationship method on your model.
2. `user.email` in your `dynamicFilters()` whitelist.

The package handles `BelongsTo` and `HasOne`/`HasMany` joins automatically.

---

## Named Scopes as Filters

If a filter key matches a scope on the model, the scope is called instead of a WHERE clause.

```php
// In dynamicFilters():
'with_trashed' => null,

// On the model:
public function scopeWithTrashed(Builder $q, $value, $operator, $logic, $not, $clause): Builder
{
    if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
        return $q->withTrashed();
    }
    return $q;
}
```

```
GET /api/products?with_trashed=true
```

Named scopes take **priority** over column filtering — if a scope exists for a key, it's always called first.

**Scope signature:**

```php
public function scope{StudlyKey}(
    Builder $q,
    mixed $value,
    ?string $operator,
    string $logic,      // 'and' or 'or'
    bool $not,          // was the filter negated?
    string $clause      // 'where' or 'having'
): Builder;
```

---

## Date Presets

The `HasDatePresets` helper trait (included via `HasDynamicQuery`) intercepts the `created_at` filter and supports semantic date range strings:

| Preset | Range |
|--------|-------|
| `today` | Start to end of today |
| `yesterday` | Start to end of yesterday |
| `this_week` | Start to end of this week |
| `last_week` | Start to end of last week |
| `this_month` | Start to end of this month |
| `last_month` | Start to end of last month |
| `this_year` | Start to end of this year |
| `last_year` | Start to end of last year |
| `last_7_days` | 7 days ago to now |
| `last_30_days` | 30 days ago to now |
| `ytd` | Year-to-date (start of year to now) |
| `qtd` | Quarter-to-date |
| `mtd` | Month-to-date |

**Usage:**

```
GET /api/orders?created_at=this_month
GET /api/orders?created_at=last_30_days
```

**Custom date columns:** Add your own date preset columns by overriding `datePresetColumns()` and creating corresponding scopes:

```php
protected function datePresetColumns(): array
{
    return ['created_at', 'delivery_date'];
}

public function scopeDeliveryDate(Builder $q, $value, $operator, $logic, $not, $clause): Builder
{
    return $this->applyDatePreset($q, $this->getTable() . '.delivery_date', $value, $operator, $logic, $not);
}
```

Date ranges can also be passed as arrays:

```
GET /api/orders?created_at[]=2024-01-01&created_at[]=2024-03-31
```

---

## Strict Filtering

When `strict_filtering` is enabled (default: `true`), **only** keys present in `dynamicFilters()` are processed. Any extra query parameters are silently ignored.

When disabled, any query parameter matching a column name could potentially be used as a filter — **not recommended for production**.

---

## Scope Signature

```php
public function scopeDynamicFilter(
    Builder $q,
    ?array $input = [],       // Custom input (defaults to request()->all())
    ?array $allowed = null,   // Override allowed filters
    ?array $ignore = null,    // Filters to exclude
    ?array $operators = null  // Override operators per field
): Builder;
```

**Programmatic usage:**

```php
// Filter with explicit input
Product::dynamicFilter([
    'status' => 'active',
    'price' => [10, 100],
    '_operators' => ['price' => 'between'],
]);

// Ignore certain filters
Product::dynamicFilter([], null, ['secret_column']);
```

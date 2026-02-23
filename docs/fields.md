# Dynamic Fields & Selection

[← Back to README](../README.md)

---

## Table of Contents

- [Overview](#overview)
- [Defining Selectable Columns](#defining-selectable-columns)
- [Required Columns](#required-columns)
- [Requesting Fields via URL](#requesting-fields-via-url)
- [Relations & Dependencies](#relations--dependencies)
- [Nested / Deep Field Selection](#nested--deep-field-selection)
- [Dynamic Appends](#dynamic-appends)
- [Dynamic Aggregates](#dynamic-aggregates)
- [Response Cleaning](#response-cleaning)
- [Auto-Relation Discovery](#auto-relation-discovery)
- [Scope Signature](#scope-signature)

---

## Overview

The `HasDynamicFields` trait (included via `HasDynamicQuery`) lets API consumers control **which columns, relations, appends, and aggregates** appear in the response. This reduces payload size and database load by selecting only what's needed.

**Resolution order inside `scopeDynamicSelect()`:**

1. Parse requested fields from URL (`_fields`) or the `$fields` array argument.
2. Resolve append dependencies (columns required by accessors).
3. Resolve relation dependencies (foreign keys needed for eager-loading).
4. Eager-load relations with optional deep field filtering.
5. Apply column selection (intersection with `dynamicColumns()` whitelist).
6. Call aggregate scopes if requested.

---

## Defining Selectable Columns

Override `dynamicColumns()` to declare which database columns can be selected:

```php
public function dynamicColumns(): array
{
    return ['id', 'name', 'email', 'price', 'category_id', 'created_at'];
}
```

If you return an empty array `[]` (the default), **all columns** are selectable. This is convenient for prototyping but less secure for production.

---

## Required Columns

Override `requiredColumns()` to declare columns that are **always** included, even if the client doesn't request them:

```php
public function requiredColumns(): array
{
    return ['id', 'owner_id', 'status'];
}
```

By default, only the model's primary key is required.

---

## Requesting Fields via URL

Clients use the `_fields` parameter (configurable) to request specific fields:

```
GET /api/users?_fields=id,name,email
```

Multiple formats are supported:

```
# Comma-separated string
?_fields=id,name,email

# Array notation
?_fields[]=id&_fields[]=name&_fields[]=email
```

To select **all** columns, use `*`:

```
?_fields=*
```

---

## Relations & Dependencies

Define available relations and their column dependencies:

```php
public function dynamicRelations(): array
{
    return [
        'user'        => 'user_id',                             // depends on user_id FK
        'commentable' => ['commentable_type', 'commentable_id'],// morph dependency
        'replies'     => null,                                  // no column dependency
    ];
}
```

When a client requests `_fields=user`, the package **automatically**:
1. Adds `user_id` to the SELECT (dependency resolution).
2. Eager-loads the `user` relation.

Dependencies resolve recursively — if relation A depends on column B, and column B is actually another relation, that gets resolved too.

---

## Nested / Deep Field Selection

For nested relation field filtering, use the `relation:field1|field2` syntax:

```
GET /api/posts?_fields=id,title,author:id|name
```

This will:
- Select `id` and `title` from the main model.
- Eager-load `author` with only `id` and `name` selected.

The nested model also uses its own `dynamicColumns()` and `requiredColumns()` for validation.

---

## Dynamic Appends

Appends are computed properties (Laravel accessors) that can be optionally included:

```php
public function dynamicAppends(): array
{
    return [
        'full_name'    => ['first_name', 'last_name'],  // depends on two columns
        'status_label' => 'status',                     // depends on one column
        'avatar_url',                                   // no dependency
    ];
}
```

When `_fields=full_name` is requested, the package automatically includes `first_name` and `last_name` in the SELECT .

**Important:** By default, `dynamicAppends()` returns `[]` for security — this means no accessors are exposed unless explicitly whitelisted.

Appends work on paginated results too. After pagination, the package calls `dynamicAppend()` on each model in the collection.

---

## Dynamic Aggregates

Aggregates add sub-queries like `withCount`, `withSum`, etc.:

```php
public function dynamicAggregates(): array
{
    return [
        'reviews_count'        => fn($q) => $q->withCount('reviews'),
        'reviews_avg_rating'   => fn($q) => $q->withAvg('reviews', 'rating'),
        'formatted_total'      => 'formattedTotal',     // calls scopeFormattedTotal()
        'custom_scope'         => null,                  // calls scopeCustomScope()
    ];
}
```

Values can be:
- A `Closure` — called with the query builder.
- A `string` — treated as a named scope name.
- `null` — calls a scope matching the key name (camelCase).

Request them like any field:

```
GET /api/products?_fields=id,name,reviews_count
```

---

## Response Cleaning

When `clean_response` is enabled in config (default: `true`), the package calls `$model->setVisible()` to limit the JSON output to **only** the requested fields.

This means even if the database query fetches dependency columns (like foreign keys), they won't appear in the API response unless explicitly requested.

---

## Auto-Relation Discovery

When `relation_guess` is enabled in config (default: `true`), the package uses PHP Reflection to scan your model for methods that return Eloquent `Relation` subclasses. This means you can skip defining `dynamicRelations()` entirely.

The auto-discovery:
- Finds all public, non-static, zero-argument methods with a `Relation` return type.
- Detects the foreign key or morph columns automatically.
- Caches results per model class for performance.

Supported relation types: `BelongsTo`, `HasOne`, `HasMany`, `HasOneThrough`, `HasManyThrough`, `BelongsToMany`, `MorphOne`, `MorphMany`, `MorphTo`, `MorphToMany`.

---

## Scope Signature

```php
public function scopeDynamicSelect(
    Builder $q,
    ?array $input = [],       // Custom input (defaults to request()->all())
    ?array $fields = null,    // Override field whitelist
    ?array $ignore = null     // Fields to exclude
): Builder;
```

**Programmatic usage:**

```php
// Override fields
Product::dynamicSelect(['id', 'name', 'category:id|name']);

// Ignore certain fields
Product::dynamicSelect([], null, ['secret_field']);
```

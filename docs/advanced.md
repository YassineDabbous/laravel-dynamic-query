# Advanced Topics

[← Back to README](../README.md)

---

## Table of Contents

- [Smart Joins](#smart-joins)
- [Auto-Relation Discovery](#auto-relation-discovery)
- [DynamicQueryable Contract](#dynamicqueryable-contract)
- [Morph Model Resolution](#morph-model-resolution)
- [Programmatic Input](#programmatic-input)
- [Using Individual Traits](#using-individual-traits)
- [Architecture Overview](#architecture-overview)

---

## Smart Joins

The `InteractsWithSmartJoins` trait automatically generates SQL JOINs when you use dot-notation in filters, sorts, or groups.

### How It Works

When the package encounters a key like `user.email`:

1. Splits into `relation = 'user'` and `column = 'email'`.
2. Checks if the model has a `user()` method.
3. Determines the relation type:
   - **BelongsTo:** `JOIN users ON products.user_id = users.id`
   - **HasOne/HasMany:** `JOIN phones ON users.id = phones.user_id`
4. Returns the qualified column: `users.email`.

### Duplicate Join Prevention

The trait tracks existing joins on the query builder and skips if the relation table is already joined. This allows the same relation to be used across filtering, sorting, and grouping without duplicate joins.

### Column Qualification

Even for non-dot columns, the trait automatically qualifies them with the table name:

```
price → products.price
```

This prevents ambiguous column errors when joins are present.

---

## Auto-Relation Discovery

The `RelationsFinder` trait uses PHP Reflection to discover available relations. It scans all public, non-static, zero-parameter methods that have a return type of `Relation` or any of its subclasses.

### Supported Detection Methods

- **Named return types:** `public function user(): BelongsTo`
- **Union return types:** `public function creator(): BelongsTo|MorphTo`

### Dependency Mapping

For each discovered relation, the trait identifies the relevant foreign key or morph columns:

| Relation Type | Detected Dependency |
|---------------|-------------------|
| `BelongsTo` | Foreign key (e.g., `user_id`) |
| `HasOne` / `HasMany` | Local key (e.g., `id`) |
| `HasOneThrough` / `HasManyThrough` | Local key |
| `BelongsToMany` | Parent key |
| `MorphTo` | Morph type + foreign key (e.g., `[commentable_type, commentable_id]`) |
| `MorphOne` / `MorphMany` | Local key |
| `MorphToMany` | Morph type + foreign key |

### Caching

Results are cached in a static property per model class, so Reflection runs only once per request lifecycle.

### Disabling

Set `relation_guess` to `false` in config:

```php
'settings' => [
    'relation_guess' => false,
],
```

Then define relations manually in `dynamicRelations()`.

---

## DynamicQueryable Contract

The package provides an interface for strict typing:

```php
use YassineDabbous\DynamicQuery\Contracts\DynamicQueryable;

class Product extends Model implements DynamicQueryable
{
    use HasDynamicQuery;

    public function dynamicColumns(): array { return [...]; }
    public function dynamicRelations(): array { return [...]; }
    public function dynamicAppends(): array { return [...]; }
    public function dynamicAggregates(): array { return [...]; }
    public function dynamicFilters(): array { return [...]; }
    public function dynamicSorts(): array { return [...]; }
    public function dynamicGroups(): array { return [...]; }
    public function dynamicMetrics(): array { return [...]; }
    public function requiredColumns(): array { return [...]; }
}
```

This is useful for IDE autocompletion and enforcing that all configuration methods are defined.

---

## Morph Model Resolution

The `resolveDynamicModel()` macro resolves a morph map alias to a model instance query:

```php
// In your controller:
$query = Model::query()->resolveDynamicModel('post', ['post', 'video', 'article']);
return $query->dynamicAPI();
```

**URL usage:**

```
GET /api/content?_model=post&_fields=id,title
```

**Parameters:**

| Param | Type | Description |
|-------|------|-------------|
| `$default` | `?string` | Default morph alias if none provided |
| `$whitelist` | `array` | Allowed aliases (required for security) |
| `$input` | `?array` | Custom input |

**Security:**
- At least one of `$default` or `$whitelist` must be provided.
- If a whitelist is set, only listed aliases are accepted (returns 403 otherwise).
- The alias must exist in Laravel's morph map (`Relation::morphMap()`).

> The older `dynamicModel()` macro is deprecated in favor of `resolveDynamicModel()`.

---

## Programmatic Input

All scopes accept an `$input` array to override URL parameters. This is useful for:

- **Testing:** Pass inputs directly without HTTP requests.
- **Jobs/Commands:** Compute stats in background jobs.
- **Internal APIs:** Use dynamic queries inside services.

```php
// In a test
$result = Product::dynamicAPI([
    '_fields' => 'id,name,price',
    '_sort' => '-price',
    'status' => 'active',
    'per_page' => '5',
]);

// In a job
$stats = Order::dynamicStats([
    '_metric' => 'sum:total',
    '_group' => 'created_at:month',
    'created_at' => ['2024-01-01', '2024-12-31'],
]);
```

When `$input` is provided and non-empty, it replaces `request()->all()` entirely.

---

## Using Individual Traits

You don't have to use all features. The `HasDynamicQuery` trait combines everything, but you can use individual traits:

```php
use YassineDabbous\DynamicQuery\HasDynamicFields;
use YassineDabbous\DynamicQuery\HasDynamicFilter;

class Product extends Model
{
    use HasDynamicFields, HasDynamicFilter;

    // Only field selection and filtering — no sorting, grouping, or stats
}
```

Available traits:

| Trait | Provides |
|-------|----------|
| `HasDynamicFields` | `scopeDynamicSelect()`, `dynamicAppend()` |
| `HasDynamicFilter` | `scopeDynamicFilter()` |
| `HasDynamicSort` | `scopeDynamicSort()` |
| `HasDynamicGroup` | `scopeDynamicGroupBy()` |
| `HasDynamicStats` | `scopeDynamicStats()`, `scopeDynamicStatsAPI()` |
| `Helpers\HasDatePresets` | `scopeCreatedAt()`, date preset handling |
| `HasDynamicQuery` | All of the above + `scopeDynamicQuery()` + `scopeDynamicAPI()` |

---

## Architecture Overview

```
HasDynamicQuery (main entry trait)
├── HasDynamicFields (selection, relations, appends, aggregates)
│   ├── HasDynamicCore (input resolution, helpers)
│   └── RelationsFinder (auto-discovery via Reflection)
├── HasDynamicFilter (operator-based filtering)
│   ├── HasDynamicCore
│   └── InteractsWithSmartJoins (auto JOIN, column qualification)
│       └── RelationsFinder
├── HasDynamicSort (ordering)
│   ├── HasDynamicCore
│   └── InteractsWithSmartJoins
├── HasDynamicGroup (grouping, date macros)
│   ├── HasDynamicCore
│   └── InteractsWithSmartJoins
├── HasDynamicStats (metrics, transforms, comparison)
│   ├── HasDynamicCore
│   └── InteractsWithSmartJoins
└── Helpers\HasDatePresets (semantic date ranges)

DynamicQueryServiceProvider (macros: dynamicPaginate, dynamicAppend, resolveDynamicModel)
DynamicQueryHelper (static utilities: toAssociative, sanitizeAlias, etc.)
StatsTransformer (formats stats API response with meta, summary, dataset)
Contracts\DynamicQueryable (interface for strict typing)
```

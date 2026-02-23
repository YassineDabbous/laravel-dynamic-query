# Installation

[← Back to README](../README.md)

---

## Table of Contents

- [Requirements](#requirements)
- [Install via Composer](#install-via-composer)
- [Service Provider](#service-provider)
- [Publish Configuration](#publish-configuration)

---

## Requirements

| Dependency | Version |
|------------|---------|
| PHP | >= 8.0 |
| Laravel (illuminate/support) | 8.x, 9.x, 10.x, 11.x |
| Laravel (illuminate/database) | 8.x, 9.x, 10.x, 11.x |

---

## Install via Composer

```bash
composer require yassinedabbous/laravel-dynamic-query
```

---

## Service Provider

The package uses Laravel's **auto-discovery**, so the service provider is registered automatically.

If you have disabled auto-discovery, add the provider manually in `config/app.php`:

```php
'providers' => [
    // ...
    YassineDabbous\DynamicQuery\DynamicQueryServiceProvider::class,
],
```

The service provider registers:

- The `dynamic-query` config file.
- The `resolveDynamicModel()` macro on Eloquent Builder.
- The `dynamicPaginate()` macro on Eloquent Builder, Query Builder, `BelongsToMany`, and `HasManyThrough`.
- The `dynamicAppend()` macro on Eloquent Collections and AbstractPaginator (when macroable).

---

## Publish Configuration

```bash
php artisan vendor:publish --tag=dynamic-query-config
```

This copies `config/dynamic-query.php` to your application's config directory.

See the [Configuration Reference](configuration.md) for all available options.

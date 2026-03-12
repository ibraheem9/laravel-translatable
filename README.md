# Laravel Translatable

<p align="center">
<a href="https://packagist.org/packages/ibraheem9/laravel-translatable"><img src="https://img.shields.io/badge/Laravel-10%20|%2011%20|%2012-red.svg" alt="Laravel Version"></a>
<a href="https://packagist.org/packages/ibraheem9/laravel-translatable"><img src="https://img.shields.io/badge/PHP-8.1+-blue.svg" alt="PHP Version"></a>
<a href="LICENSE"><img src="https://img.shields.io/badge/License-MIT-green.svg" alt="License"></a>
</p>

A lightweight, zero-dependency Laravel package for handling multilingual Eloquent models using a **column-based** approach. Instead of separate translation tables, each locale gets its own column (e.g., `name_en`, `name_ar`), and the package automatically resolves the correct value based on the current application locale.

> **Origin:** Extracted from the [BikeGalaxy](https://github.com/ibraheem9/bike) project and generalized for reuse. The demo app is at [laravel-translatable-demo](https://github.com/ibraheem9/laravel-translatable-demo).

---

## Table of Contents

- [Why Column-Based Translations?](#why-column-based-translations)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
  - [Database Migrations](#1-database-migrations)
  - [Model Setup](#2-model-setup)
  - [Accessing Translations](#3-accessing-translations)
  - [Setting Translations](#4-setting-translations)
  - [Query Scopes](#5-query-scopes)
  - [JSON / API Serialization](#6-json--api-serialization)
- [Advanced Usage](#advanced-usage)
  - [Explicit Translatable Fields](#explicit-translatable-fields)
  - [Model-Level Locale Override](#model-level-locale-override)
  - [Custom Fallback Chain](#custom-fallback-chain)
- [Full Example](#full-example)
- [Real-World Use Cases](#real-world-use-cases)
- [API Reference](#api-reference)
- [Testing](#testing)
- [License](#license)

---

## Why Column-Based Translations?

There are two common approaches to model translations in Laravel:

| Approach | How It Works | Best For |
|---|---|---|
| **Table-based** (e.g., `spatie/laravel-translatable`) | Stores translations as JSON or in a separate `translations` table | Apps with many locales (10+) |
| **Column-based** (this package) | Each locale has its own column: `name_en`, `name_ar` | Apps with 2–5 locales, maximum query performance |

The column-based approach offers several advantages for projects with a small number of locales. There is no need for JSON parsing or extra joins, which means queries are faster and simpler. Columns can be individually indexed for search performance, and the database schema is explicit and easy to understand.

---

## Installation

Install the package via Composer:

```bash
composer require ibraheem9/laravel-translatable
```

The service provider is auto-discovered by Laravel. To publish the configuration file, run:

```bash
php artisan vendor:publish --tag="translatable-config"
```

---

## Configuration

The published configuration file is located at `config/translatable.php`:

```php
return [

    // All locales your application supports
    'locales' => ['en', 'ar'],

    // The suffix used to detect translatable columns (e.g., name_en)
    'primary_locale' => 'en',

    // Fallback order when a translation is empty (null = use locales order)
    'fallback_chain' => null,

    // Auto-detect translatable columns from $fillable
    'auto_detect' => true,

    // Auto-append base attributes (e.g., 'name') to $appends
    'auto_append' => true,
];
```

| Option | Type | Default | Description |
|---|---|---|---|
| `locales` | `array` | `['en', 'ar']` | All supported locales in your application |
| `primary_locale` | `string` | `'en'` | The suffix used to detect translatable columns |
| `fallback_chain` | `array\|null` | `null` | Custom fallback order; `null` uses `locales` order |
| `auto_detect` | `bool` | `true` | Scan `$fillable` for columns ending with `_{primary_locale}` |
| `auto_append` | `bool` | `true` | Add base attribute names to the model's `$appends` |

---

## Usage

### 1. Database Migrations

The package registers Blueprint macros so you can create translatable columns cleanly:

```php
Schema::create('products', function (Blueprint $table) {
    $table->id();

    // Creates: name_en (VARCHAR 255), name_ar (VARCHAR 255)
    $table->translatableString('name');

    // Creates: description_en (TEXT), description_ar (TEXT)
    $table->translatableText('description');

    // Creates: content_en (LONGTEXT), content_ar (LONGTEXT)
    $table->translatableLongText('content');

    $table->decimal('price', 10, 2);
    $table->timestamps();
});
```

All macros accept optional parameters:

```php
// Custom length for string columns
$table->translatableString('name', 500);

// Non-nullable columns
$table->translatableString('title', 255, nullable: false);

// Override locales for a specific column only
$table->translatableString('name', locales: ['en', 'ar', 'fr']);
```

To drop translatable columns in a migration rollback:

```php
$table->dropTranslatable('name');
```

### 2. Model Setup

Add the `Translatable` trait to your Eloquent model and include the translated columns in `$fillable`. **That is all that is required** — no extra property, no configuration:

```php
<?php

namespace App\Models;

use Ibraheem9\Translatable\Traits\Translatable;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use Translatable;

    protected $fillable = [
        'name_en',
        'name_ar',
        'description_en',
        'description_ar',
        'price',
    ];
}
```

The trait automatically detects that `name_en` and `description_en` are translatable (they end with `_en`, the primary locale) and registers `name` and `description` as virtual attributes. No `$translatable` array is needed.

### 3. Accessing Translations

Once the trait is set up, accessing translations is seamless:

```php
// Current locale: 'en'
$product = Product::find(1);

echo $product->name;         // "Mountain Bike"      (from name_en)
echo $product->description;  // "A great bike"       (from description_en)

// Switch to Arabic
app()->setLocale('ar');

echo $product->name;         // "دراجة جبلية"         (from name_ar)
echo $product->description;  // "دراجة رائعة"     (from description_ar)
```

You can also get a translation for a specific locale without changing the app locale:

```php
$product->getTranslatedAttribute('name', 'ar');  // "دراجة جبلية"
$product->getTranslatedAttribute('name', 'en');  // "Mountain Bike"
```

To retrieve all translations at once:

```php
$product->getTranslations('name');
// Returns: ['en' => 'Mountain Bike', 'ar' => 'دراجة جبلية']
```

**Fallback Behavior:** If the current locale's value is empty or null, the package automatically falls back through the configured locale chain until it finds a non-empty value.

```php
app()->setLocale('ar');

// If name_ar is null, it falls back to name_en automatically
echo $product->name;  // "Mountain Bike" (fallback from en)
```

### 4. Setting Translations

Set a translation for a specific locale:

```php
$product->setTranslatedAttribute('name', 'New Name', 'en');
$product->setTranslatedAttribute('name', 'اسم جديد', 'ar');
$product->save();
```

Or set multiple translations at once:

```php
$product->setTranslations('name', [
    'en' => 'Mountain Bike Pro',
    'ar' => 'دراجة جبلية برو',
]);
$product->save();
```

### 5. Query Scopes

The package provides powerful query scopes for searching and ordering by translated attributes:

```php
// Filter by exact match in current locale
Product::whereTranslation('name', 'Mountain Bike')->get();

// Search (LIKE) in current locale
Product::whereTranslationLike('name', 'Mountain')->get();

// Search across ALL locales (finds Arabic matches even when app is in English)
Product::whereTranslationLikeAny('name', 'دراجة')->get();

// Order by translated attribute in current locale
Product::orderByTranslation('name', 'asc')->get();
```

These scopes can be chained with any other Eloquent query methods:

```php
Product::whereTranslationLike('name', 'Bike')
    ->where('price', '<', 1000)
    ->orderByTranslation('name')
    ->paginate(15);
```

### 6. JSON / API Serialization

When `auto_append` is enabled (default), the base attribute names are automatically included in JSON output:

```php
return Product::find(1);
```

```json
{
    "id": 1,
    "name_en": "Mountain Bike",
    "name_ar": "دراجة جبلية",
    "description_en": "A professional mountain bike",
    "description_ar": "دراجة جبلية احترافية",
    "price": 1299.99,
    "name": "Mountain Bike",
    "description": "A professional mountain bike"
}
```

The `name` and `description` fields resolve based on the current locale, making it easy to build multilingual APIs:

```php
// In your controller
public function index(Request $request)
{
    app()->setLocale($request->get('lang', 'en'));
    return Product::all();
}
```

```
GET /api/products?lang=ar  →  "name": "دراجة جبلية"
GET /api/products?lang=en  →  "name": "Mountain Bike"
```

---

## Advanced Usage

### Explicit Translatable Fields

Instead of auto-detection, you can explicitly define which attributes are translatable using the `$translatable` property. This is useful when `$fillable` contains columns that should not be treated as translatable:

```php
class Product extends Model
{
    use Translatable;

    // Only these attributes will be treated as translatable
    protected array $translatable = ['name', 'description'];

    protected $fillable = [
        'name_en', 'name_ar',
        'description_en', 'description_ar',
        'slug',   // Not translatable — stays as-is
        'price',  // Not translatable — stays as-is
    ];
}
```

### Model-Level Locale Override

Override the supported locales for a specific model without changing the global config:

```php
class Setting extends Model
{
    use Translatable;

    // This model supports 3 languages; global config only has 2
    protected array $translatableLocales = ['en', 'ar', 'fr'];

    protected $fillable = [
        'value_en', 'value_ar', 'value_fr',
        'title_en', 'title_ar', 'title_fr',
    ];
}
```

### Custom Fallback Chain

Configure the fallback order in `config/translatable.php`:

```php
'fallback_chain' => ['ar', 'en'],
```

This means: try Arabic first, then English. If both are empty, return `null`.

---

## Full Example

Here is a complete example showing a multilingual product catalog with English and Arabic:

**Migration:**

```php
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->translatableString('name');       // name_en, name_ar
    $table->translatableText('description');  // description_en, description_ar
    $table->decimal('price', 10, 2);
    $table->timestamps();
});
```

**Model:**

```php
class Product extends Model
{
    use Translatable;

    protected $fillable = [
        'name_en', 'name_ar',
        'description_en', 'description_ar',
        'price',
    ];
}
```

**Controller:**

```php
class ProductController extends Controller
{
    public function index(Request $request)
    {
        app()->setLocale($request->get('lang', 'en'));

        $products = Product::query()
            ->when($request->search, fn($q, $s) => $q->whereTranslationLikeAny('name', $s))
            ->orderByTranslation('name')
            ->paginate(20);

        return response()->json($products);
    }
}
```

**Creating a product:**

```php
Product::create([
    'name_en'        => 'Mountain Bike Pro',
    'name_ar'        => 'دراجة جبلية برو',
    'description_en' => 'Professional mountain bike with 21-speed gear system',
    'description_ar' => 'دراجة جبلية احترافية بنظام تروس 21 سرعة',
    'price'          => 1299.99,
]);
```

---

## Real-World Use Cases

### E-Commerce Product Catalog

```php
class Product extends Model
{
    use Translatable;

    protected $fillable = [
        'name_en', 'name_ar',
        'description_en', 'description_ar',
        'sku', 'price', 'stock',
    ];
}

// In a Blade view
<h1>{{ $product->name }}</h1>
<p>{{ $product->description }}</p>
```

### Blog / CMS Articles

```php
class Article extends Model
{
    use Translatable;

    protected $fillable = [
        'title_en', 'title_ar',
        'body_en', 'body_ar',
        'slug', 'published_at',
    ];
}

// Fetch articles in current locale, ordered by title
Article::orderByTranslation('title')->paginate(10);
```

### Application Settings

```php
class Setting extends Model
{
    use Translatable;

    protected array $translatable = ['value'];

    protected $fillable = [
        'key',       // Not translatable — unique setting key
        'value_en',
        'value_ar',
    ];
}

// Get the site title in current locale
Setting::where('key', 'site_title')->first()->value;
```

### REST API with Locale Header

```php
class ApiController extends Controller
{
    public function __construct()
    {
        // Set locale from Accept-Language header or query param
        $locale = request()->get('lang') ?? request()->header('Accept-Language', 'en');
        app()->setLocale(in_array($locale, ['en', 'ar']) ? $locale : 'en');
    }
}
```

---

## API Reference

### Instance Methods

| Method | Description |
|---|---|
| `$model->name` | Returns the translated value for the current locale |
| `getTranslatedAttribute($attr, $locale?)` | Get translation for a specific locale |
| `setTranslatedAttribute($attr, $value, $locale?)` | Set translation for a specific locale |
| `getTranslations($attr)` | Get all translations as `['en' => '...', 'ar' => '...']` |
| `setTranslations($attr, $translations)` | Set multiple translations at once |
| `getTranslatableAttributes()` | Get list of translatable attribute names |
| `isTranslatableAttribute($attr)` | Check if an attribute is translatable |

### Query Scopes

| Scope | Description |
|---|---|
| `whereTranslation($attr, $value, $op?)` | Filter by translation in current locale |
| `whereTranslationLike($attr, $search)` | LIKE search in current locale |
| `whereTranslationLikeAny($attr, $search)` | LIKE search across all locales |
| `orderByTranslation($attr, $dir?)` | Order by translated column |

### Migration Macros

| Macro | Creates | Description |
|---|---|---|
| `$table->translatableString('name')` | `name_en`, `name_ar` | VARCHAR columns per locale |
| `$table->translatableText('name')` | `name_en`, `name_ar` | TEXT columns per locale |
| `$table->translatableLongText('name')` | `name_en`, `name_ar` | LONGTEXT columns per locale |
| `$table->dropTranslatable('name')` | — | Drops all locale columns for attribute |

---

## Testing

Run the test suite with:

```bash
composer test
```

Or directly with PHPUnit:

```bash
vendor/bin/phpunit
```

The test suite covers:

- Auto-detection of translatable attributes from `$fillable`
- Translation resolution for current locale
- Locale switching (`en` ↔ `ar`)
- Fallback behavior when a locale value is empty
- `getTranslatedAttribute()`, `getTranslations()`, `setTranslations()`
- All four query scopes
- JSON/array serialization with `auto_append`
- Explicit `$translatable` property mode
- Model-level `$translatableLocales` override

---

## License

The MIT License (MIT). Please see the [License File](LICENSE) for more information.

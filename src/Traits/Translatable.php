<?php

namespace Ibraheem9\Translatable\Traits;

/**
 * Translatable Trait
 *
 * Provides automatic column-based multilingual support for Eloquent models.
 * Extracted and improved from the Vironna Pharmacy project.
 *
 * -------------------------------------------------------------------------
 * HOW IT WORKS
 * -------------------------------------------------------------------------
 *
 * 1. Add the trait to your model.
 * 2. Add locale columns to $fillable — e.g., 'title_en', 'title_ar'.
 * 3. The trait auto-detects columns ending with '_en' (primary locale)
 *    and registers 'title' as a virtual translatable attribute.
 * 4. Access $model->title — it resolves to title_en or title_ar
 *    based on app()->getLocale().
 * 5. Falls back automatically if the current locale value is empty.
 *
 * -------------------------------------------------------------------------
 * USAGE EXAMPLE (matches Vironna pattern)
 * -------------------------------------------------------------------------
 *
 *   class MedicineCategory extends Model
 *   {
 *       use Translatable;
 *
 *       protected $fillable = [
 *           'title_en',
 *           'title_ar',
 *           'is_active',
 *           'position',
 *       ];
 *   }
 *
 *   // Access:
 *   $category->title   // → title_en (locale: en) or title_ar (locale: ar)
 *
 * -------------------------------------------------------------------------
 * LOCALE DETECTION (two patterns supported)
 * -------------------------------------------------------------------------
 *
 * Pattern A — API (from Accept-Language header):
 *   setHeaderLang() in your base controller or middleware
 *   → reads Accept-Language header, defaults to 'ar'
 *
 * Pattern B — Web (from session):
 *   SwitchLang middleware reads Session::get('lang'), defaults to 'ar'
 *
 * -------------------------------------------------------------------------
 */
trait Translatable
{
    /**
     * Internal list of detected translatable base attribute names.
     * e.g., ['title', 'description'] (without locale suffix)
     *
     * @var array
     */
    private array $translatable_attributes = [];

    /**
     * Internal map of accessor method names to base attribute names.
     * e.g., ['getTitleAttribute' => 'title', 'getDescriptionAttribute' => 'description']
     *
     * This allows Eloquent's accessor system ($model->title) to work
     * alongside the trait's __get() magic method.
     *
     * @var array
     */
    private array $translatable_methods = [];

    /**
     * Called automatically by Eloquent when the model is initialized.
     * Detects translatable attributes and registers them in $appends
     * so they appear in JSON/array output.
     */
    protected function initializeTranslatable(): void
    {
        $this->detectTranslatableAttributes();

        foreach ($this->translatable_attributes as $attr) {
            if (!in_array($attr, $this->appends)) {
                $this->appends[] = $attr;
            }
        }
    }

    /**
     * Scan $fillable for columns ending with the primary locale suffix ('_en').
     * Each match registers the base name as a translatable attribute.
     *
     * Example: 'title_en' → base attribute 'title'
     *          'description_en' → base attribute 'description'
     *
     * If $translatable is explicitly defined on the model, use that instead.
     */
    public function detectTranslatableAttributes(): void
    {
        $this->translatable_attributes = [];
        $this->translatable_methods    = [];

        // If the model explicitly defines $translatable, use it directly
        if (property_exists($this, 'translatable') && is_array($this->translatable)) {
            foreach ($this->translatable as $attribute) {
                $this->translatable_attributes[] = $attribute;
                $this->translatable_methods[$this->toAccessorMethodName($attribute)] = $attribute;
            }
            return;
        }

        // Auto-detect from $fillable: find columns ending with '_en'
        $primaryLocale = config('translatable.primary_locale', 'en');
        $suffix        = '_' . $primaryLocale;
        $suffixLength  = strlen($suffix);

        foreach ($this->getFillable() as $column) {
            if (str_ends_with($column, $suffix)) {
                $attribute = substr($column, 0, -$suffixLength);
                $this->translatable_attributes[] = $attribute;
                $this->translatable_methods[$this->toAccessorMethodName($attribute)] = $attribute;
            }
        }
    }

    /**
     * Convert a snake_case attribute name to an Eloquent accessor method name.
     *
     * Example: 'title'            → 'getTitleAttribute'
     *          'long_description'  → 'getLongDescriptionAttribute'
     *          'medicine_name'     → 'getMedicineNameAttribute'
     *
     * This mirrors the snakToPascal() helper from the Vironna project.
     */
    private function toAccessorMethodName(string $attribute): string
    {
        $pascal = str_replace(' ', '', ucwords(str_replace('_', ' ', $attribute)));
        return 'get' . $pascal . 'Attribute';
    }

    /**
     * Magic property getter.
     *
     * When accessing $model->title, this checks if 'title' is a translatable
     * attribute and returns the locale-resolved value. Otherwise, delegates
     * to Eloquent's standard getAttribute().
     */
    public function __get($key)
    {
        if (in_array($key, $this->translatable_attributes)) {
            return $this->getTranslatedAttribute($key);
        }

        return $this->getAttribute($key);
    }

    /**
     * Magic method caller.
     *
     * Intercepts Eloquent accessor calls like getTitleAttribute() and
     * routes them through the translation resolver.
     *
     * Also handles 'increment' and 'decrement' to avoid infinite recursion,
     * and forwards all other calls to the Eloquent query builder.
     */
    public function __call($method, $parameters)
    {
        if (in_array($method, array_keys($this->translatable_methods))) {
            return $this->getTranslatedAttribute($this->translatable_methods[$method]);
        }

        if (in_array($method, ['increment', 'decrement'])) {
            return $this->$method(...$parameters);
        }

        return $this->forwardCallTo($this->newQuery(), $method, $parameters);
    }

    /**
     * Resolve the translated value for a given base attribute name.
     *
     * Resolution order:
     *   1. Current locale column (e.g., title_ar when locale is 'ar')
     *   2. Fallback through configured locale chain
     *   3. null if all locales are empty
     *
     * Example:
     *   app()->setLocale('ar');
     *   $model->getTranslatedAttribute('title')
     *   → tries title_ar first, falls back to title_en, then null
     *
     * @param  string  $attribute  Base attribute name (e.g., 'title', not 'title_en')
     * @param  string|null  $locale  Force a specific locale (optional)
     * @return mixed
     */
    public function getTranslatedAttribute(string $attribute, ?string $locale = null): mixed
    {
        $locale = $locale ?? app()->getLocale();

        // Try the requested locale first
        $value = $this->attributes[$attribute . '_' . $locale] ?? null;
        if (!empty($value)) {
            return $value;
        }

        // Fall back through the configured locale chain
        $fallbackChain = $this->getTranslatableLocales();

        foreach ($fallbackChain as $fallbackLocale) {
            if ($fallbackLocale === $locale) {
                continue; // Already tried this one
            }
            $fallbackValue = $this->attributes[$attribute . '_' . $fallbackLocale] ?? null;
            if (!empty($fallbackValue)) {
                return $fallbackValue;
            }
        }

        return null;
    }

    /**
     * Set a translation for a specific locale.
     *
     * Example:
     *   $model->setTranslatedAttribute('title', 'Mountain Bike', 'en');
     *   $model->setTranslatedAttribute('title', 'دراجة جبلية', 'ar');
     *   $model->save();
     *
     * @param  string  $attribute  Base attribute name (e.g., 'title')
     * @param  mixed   $value      The translation value
     * @param  string|null  $locale  Target locale (defaults to current)
     */
    public function setTranslatedAttribute(string $attribute, mixed $value, ?string $locale = null): void
    {
        $locale = $locale ?? app()->getLocale();
        $this->setAttribute($attribute . '_' . $locale, $value);
    }

    /**
     * Get all translations for a given attribute as an associative array.
     *
     * Example:
     *   $model->getTranslations('title')
     *   → ['en' => 'Mountain Bike', 'ar' => 'دراجة جبلية']
     *
     * @param  string  $attribute  Base attribute name
     * @return array<string, mixed>
     */
    public function getTranslations(string $attribute): array
    {
        $translations = [];

        foreach ($this->getTranslatableLocales() as $locale) {
            $translations[$locale] = $this->attributes[$attribute . '_' . $locale] ?? null;
        }

        return $translations;
    }

    /**
     * Set multiple translations at once.
     *
     * Example:
     *   $model->setTranslations('title', [
     *       'en' => 'Mountain Bike',
     *       'ar' => 'دراجة جبلية',
     *   ]);
     *   $model->save();
     *
     * @param  string  $attribute      Base attribute name
     * @param  array   $translations   Associative array of locale => value
     */
    public function setTranslations(string $attribute, array $translations): void
    {
        foreach ($translations as $locale => $value) {
            $this->setAttribute($attribute . '_' . $locale, $value);
        }
    }

    /**
     * Check whether a given attribute name is translatable.
     *
     * Example:
     *   $model->isTranslatableAttribute('title')       // true
     *   $model->isTranslatableAttribute('price')       // false
     *   $model->isTranslatableAttribute('is_active')   // false
     *
     * @param  string  $attribute  Base attribute name (without locale suffix)
     * @return bool
     */
    public function isTranslatableAttribute(string $attribute): bool
    {
        return in_array($attribute, $this->getTranslatableAttributes());
    }

    /**
     * Get the list of detected translatable base attribute names.
     *
     * @return array<string>
     */
    public function getTranslatableAttributes(): array
    {
        if (empty($this->translatable_attributes)) {
            $this->detectTranslatableAttributes();
        }

        return $this->translatable_attributes;
    }

    /**
     * Get the list of supported locales for this model.
     *
     * Checks (in order):
     *   1. Model-level $translatableLocales property (override for specific model)
     *   2. config('translatable.locales')
     *   3. Default: ['en', 'ar']
     *
     * Example model-level override:
     *   protected array $translatableLocales = ['en', 'ar', 'fr'];
     *
     * @return array<string>
     */
    public function getTranslatableLocales(): array
    {
        if (property_exists($this, 'translatableLocales') && is_array($this->translatableLocales)) {
            return $this->translatableLocales;
        }

        return config('translatable.locales', ['en', 'ar']);
    }

    /**
     * Query scope: filter by exact translation match in the current locale.
     *
     * Usage:
     *   MedicineCategory::whereTranslation('title', 'Vitamins')->get();
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $attribute   Base attribute name
     * @param  mixed   $value       Value to match
     * @param  string  $operator    SQL operator (default '=')
     */
    public function scopeWhereTranslation($query, string $attribute, mixed $value, string $operator = '='): void
    {
        $locale = app()->getLocale();
        $query->where($attribute . '_' . $locale, $operator, $value);
    }

    /**
     * Query scope: LIKE search in the current locale column.
     *
     * Usage:
     *   Medicine::whereTranslationLike('name', 'Aspirin')->get();
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $attribute  Base attribute name
     * @param  string  $search     Search term (% wildcards added automatically)
     */
    public function scopeWhereTranslationLike($query, string $attribute, string $search): void
    {
        $locale = app()->getLocale();
        $query->where($attribute . '_' . $locale, 'LIKE', '%' . $search . '%');
    }

    /**
     * Query scope: LIKE search across ALL locale columns simultaneously.
     *
     * This is the most powerful search scope — it finds records regardless
     * of which locale the search term is in.
     *
     * Usage:
     *   // Search for Arabic term while app is in English
     *   Medicine::whereTranslationLikeAny('name', 'أسبرين')->get();
     *
     *   // Search for English term while app is in Arabic
     *   Medicine::whereTranslationLikeAny('name', 'Aspirin')->get();
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $attribute  Base attribute name
     * @param  string  $search     Search term
     */
    public function scopeWhereTranslationLikeAny($query, string $attribute, string $search): void
    {
        $locales = $this->getTranslatableLocales();

        $query->where(function ($q) use ($attribute, $search, $locales) {
            foreach ($locales as $locale) {
                $q->orWhere($attribute . '_' . $locale, 'LIKE', '%' . $search . '%');
            }
        });
    }

    /**
     * Query scope: order results by the translated column in the current locale.
     *
     * Usage:
     *   MedicineCategory::orderByTranslation('title', 'asc')->get();
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $attribute   Base attribute name
     * @param  string  $direction   'asc' or 'desc'
     */
    public function scopeOrderByTranslation($query, string $attribute, string $direction = 'asc'): void
    {
        $locale = app()->getLocale();
        $query->orderBy($attribute . '_' . $locale, $direction);
    }
}

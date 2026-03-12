<?php

/**
 * Translatable Helper Functions
 *
 * These helpers are extracted from the Vironna Pharmacy project and provide
 * convenient global functions for locale management and translation resolution.
 *
 * Publish to your app with:
 *   php artisan vendor:publish --tag="translatable-helpers"
 *
 * Then require in your composer.json autoload.files:
 *   "autoload": {
 *       "files": ["app/Helpers/translatable_helpers.php"]
 *   }
 */

if (!function_exists('setHeaderLang')) {
    /**
     * Set the application locale from the Accept-Language HTTP header.
     *
     * Used in API controllers to automatically switch language based on
     * the client's Accept-Language header.
     *
     * Usage (in API base controller constructor or middleware):
     *   setHeaderLang();
     *
     * Example:
     *   Request header: Accept-Language: ar
     *   → app()->getLocale() === 'ar'
     *
     *   Request header: Accept-Language: en
     *   → app()->getLocale() === 'en'
     *
     *   No header present → defaults to 'ar'
     *
     * @return string  The resolved locale
     */
    function setHeaderLang(): string
    {
        $supportedLocales = config('translatable.locales', ['en', 'ar']);
        $defaultLocale    = config('translatable.primary_locale', 'ar');

        $lang = strtolower(request()->header('Accept-Language', ''));

        // Extract just the primary language tag (e.g., 'en-US' → 'en')
        $lang = explode(',', $lang)[0];
        $lang = explode('-', $lang)[0];
        $lang = trim($lang);

        if (!$lang || !in_array($lang, $supportedLocales)) {
            $lang = $defaultLocale;
        }

        app()->setLocale($lang);

        return $lang;
    }
}

if (!function_exists('localization')) {
    /**
     * Get the localized value of a model attribute.
     *
     * A standalone helper for cases where you need to resolve a translation
     * without using the Translatable trait (e.g., on non-Eloquent objects).
     *
     * Usage:
     *   localization($medicine, 'name')
     *   → returns $medicine->name_ar (if locale is 'ar')
     *   → falls back to $medicine->name_en if name_ar is empty
     *
     * @param  object  $model      Any object with locale-suffixed properties
     * @param  string  $attribute  Base attribute name (e.g., 'name', 'title')
     * @return mixed
     */
    function localization(object $model, string $attribute): mixed
    {
        $locale    = app()->getLocale();
        $locales   = config('translatable.locales', ['en', 'ar']);
        $key       = $attribute . '_' . $locale;

        if (!empty($model->$key)) {
            return $model->$key;
        }

        // Fall back through all configured locales
        foreach ($locales as $fallback) {
            if ($fallback === $locale) {
                continue;
            }
            $fallbackKey = $attribute . '_' . $fallback;
            if (!empty($model->$fallbackKey)) {
                return $model->$fallbackKey;
            }
        }

        return null;
    }
}

if (!function_exists('snakToPascal')) {
    /**
     * Convert a snake_case string to PascalCase.
     *
     * Used internally by the Translatable trait to build Eloquent accessor
     * method names from attribute names.
     *
     * Usage:
     *   snakToPascal('title')             → 'Title'
     *   snakToPascal('medicine_name')     → 'MedicineName'
     *   snakToPascal('long_description')  → 'LongDescription'
     *
     * @param  string  $value  snake_case string
     * @return string  PascalCase string
     */
    function snakToPascal(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $value)));
    }
}

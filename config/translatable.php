<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supported Locales
    |--------------------------------------------------------------------------
    |
    | Define all the locales your application supports. These are used to
    | auto-detect translatable columns and generate migration helpers.
    |
    | Example: ['en', 'ar'] creates name_en and name_ar columns.
    |
    */
    'locales' => ['en', 'ar'],

    /*
    |--------------------------------------------------------------------------
    | Primary Locale
    |--------------------------------------------------------------------------
    |
    | The primary locale is used as the suffix to detect translatable columns.
    | For example, if set to 'en', columns ending with '_en' will be detected
    | as translatable (e.g., 'name_en' -> base attribute 'name').
    |
    */
    'primary_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Fallback Locale Chain
    |--------------------------------------------------------------------------
    |
    | When the requested locale's value is empty, the system will try each
    | locale in this chain until a non-empty value is found. If all are empty,
    | null is returned.
    |
    | Set to null to use the 'locales' array order as the fallback chain.
    |
    */
    'fallback_chain' => null,

    /*
    |--------------------------------------------------------------------------
    | Auto-detect from Fillable
    |--------------------------------------------------------------------------
    |
    | When enabled, the trait will automatically scan the model's $fillable
    | array to detect translatable columns. When disabled, you must explicitly
    | define a $translatable property on your model.
    |
    | This is the recommended approach — just add locale columns to $fillable:
    |
    |   protected $fillable = ['name_en', 'name_ar', 'price'];
    |
    | The trait detects 'name_en' and registers 'name' as a virtual attribute.
    |
    */
    'auto_detect' => true,

    /*
    |--------------------------------------------------------------------------
    | Append Translated Attributes
    |--------------------------------------------------------------------------
    |
    | When enabled, the base attribute names (e.g., 'name') will be
    | automatically appended to the model's $appends array, so they
    | appear in JSON/array serialization.
    |
    */
    'auto_append' => true,

];

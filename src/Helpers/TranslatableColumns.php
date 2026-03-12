<?php

namespace Ibraheem9\Translatable\Helpers;

use Illuminate\Database\Schema\Blueprint;

class TranslatableColumns
{
    /**
     * Add translatable string columns to a Blueprint.
     *
     * Creates a column for each configured locale with the pattern: {name}_{locale}
     * Example: translatableString('name') creates 'name_en', 'name_ar', etc.
     *
     * @param Blueprint $table
     * @param string $name Base column name
     * @param int $length Column length (default: 255)
     * @param bool $nullable Whether columns should be nullable (default: true)
     * @param array|null $locales Override the configured locales
     * @return void
     */
    public static function string(Blueprint $table, string $name, int $length = 255, bool $nullable = true, ?array $locales = null): void
    {
        $locales = $locales ?? config('translatable.locales', ['en', 'ar']);

        foreach ($locales as $locale) {
            $column = $table->string($name . '_' . $locale, $length);
            if ($nullable) {
                $column->nullable();
            }
        }
    }

    /**
     * Add translatable text columns to a Blueprint.
     *
     * @param Blueprint $table
     * @param string $name Base column name
     * @param bool $nullable Whether columns should be nullable (default: true)
     * @param array|null $locales Override the configured locales
     * @return void
     */
    public static function text(Blueprint $table, string $name, bool $nullable = true, ?array $locales = null): void
    {
        $locales = $locales ?? config('translatable.locales', ['en', 'ar']);

        foreach ($locales as $locale) {
            $column = $table->text($name . '_' . $locale);
            if ($nullable) {
                $column->nullable();
            }
        }
    }

    /**
     * Add translatable longText columns to a Blueprint.
     *
     * @param Blueprint $table
     * @param string $name Base column name
     * @param bool $nullable Whether columns should be nullable (default: true)
     * @param array|null $locales Override the configured locales
     * @return void
     */
    public static function longText(Blueprint $table, string $name, bool $nullable = true, ?array $locales = null): void
    {
        $locales = $locales ?? config('translatable.locales', ['en', 'ar']);

        foreach ($locales as $locale) {
            $column = $table->longText($name . '_' . $locale);
            if ($nullable) {
                $column->nullable();
            }
        }
    }

    /**
     * Drop translatable columns from a Blueprint.
     *
     * @param Blueprint $table
     * @param string $name Base column name
     * @param array|null $locales Override the configured locales
     * @return void
     */
    public static function drop(Blueprint $table, string $name, ?array $locales = null): void
    {
        $locales = $locales ?? config('translatable.locales', ['en', 'ar']);

        foreach ($locales as $locale) {
            $table->dropColumn($name . '_' . $locale);
        }
    }
}

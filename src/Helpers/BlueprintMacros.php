<?php

namespace Ibraheem9\Translatable\Helpers;

use Illuminate\Database\Schema\Blueprint;

class BlueprintMacros
{
    /**
     * Register Blueprint macros for translatable columns.
     * Called automatically by the service provider.
     */
    public static function register(): void
    {
        /**
         * Add translatable string columns.
         * Usage: $table->translatableString('name');
         */
        Blueprint::macro('translatableString', function (string $name, int $length = 255, bool $nullable = true, ?array $locales = null) {
            TranslatableColumns::string($this, $name, $length, $nullable, $locales);
            return $this;
        });

        /**
         * Add translatable text columns.
         * Usage: $table->translatableText('description');
         */
        Blueprint::macro('translatableText', function (string $name, bool $nullable = true, ?array $locales = null) {
            TranslatableColumns::text($this, $name, $nullable, $locales);
            return $this;
        });

        /**
         * Add translatable longText columns.
         * Usage: $table->translatableLongText('content');
         */
        Blueprint::macro('translatableLongText', function (string $name, bool $nullable = true, ?array $locales = null) {
            TranslatableColumns::longText($this, $name, $nullable, $locales);
            return $this;
        });

        /**
         * Drop translatable columns.
         * Usage: $table->dropTranslatable('name');
         */
        Blueprint::macro('dropTranslatable', function (string $name, ?array $locales = null) {
            TranslatableColumns::drop($this, $name, $locales);
            return $this;
        });
    }
}

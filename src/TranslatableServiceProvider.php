<?php

namespace Ibraheem9\Translatable;

use Ibraheem9\Translatable\Helpers\BlueprintMacros;
use Illuminate\Support\ServiceProvider;

class TranslatableServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/translatable.php',
            'translatable'
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/translatable.php' => config_path('translatable.php'),
        ], 'translatable-config');

        // Publish helper functions (setHeaderLang, localization, snakToPascal)
        $this->publishes([
            __DIR__ . '/Helpers/translatable_helpers.php' => app_path('Helpers/translatable_helpers.php'),
        ], 'translatable-helpers');

        // Auto-load helpers if published to app/Helpers
        $helpersPath = app_path('Helpers/translatable_helpers.php');
        if (file_exists($helpersPath)) {
            require_once $helpersPath;
        }

        // Register Blueprint macros for migration helpers
        BlueprintMacros::register();
    }
}

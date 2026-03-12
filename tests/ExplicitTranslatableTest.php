<?php

namespace Ibraheem9\Translatable\Tests;

use Ibraheem9\Translatable\Traits\Translatable;
use Illuminate\Database\Eloquent\Model;
use Orchestra\Testbench\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tests for the explicit $translatable property mode and model-level locale overrides.
 *
 * Demonstrates two advanced patterns:
 *
 * 1. Explicit mode — define $translatable = ['name'] instead of relying on auto-detection.
 *    Useful when $fillable contains non-translatable columns with locale-like suffixes.
 *
 * 2. Model-level locale override — $translatableLocales = ['en', 'ar', 'fr'] to support
 *    more locales than the global config for a specific model.
 */
class ExplicitTranslatableTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            \Ibraheem9\Translatable\TranslatableServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Global config: only en + ar
        $app['config']->set('translatable.locales', ['en', 'ar']);
        $app['config']->set('translatable.primary_locale', 'en');
        $app['config']->set('translatable.auto_detect', true);
        $app['config']->set('translatable.auto_append', true);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('test_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name_en')->nullable();
            $table->string('name_ar')->nullable();
            $table->string('name_fr')->nullable();  // Extra locale via model override
            $table->string('slug')->nullable();      // Non-translatable
            $table->timestamps();
        });
    }

    /** @test */
    public function it_uses_explicit_translatable_property()
    {
        // When $translatable is defined, only those attributes are translatable.
        // 'slug' is in $fillable but NOT in $translatable — it stays non-translatable.
        $category = new TestCategory();
        $attributes = $category->getTranslatableAttributes();

        $this->assertContains('name', $attributes);
        $this->assertCount(1, $attributes);
    }

    /** @test */
    public function it_works_with_english_and_hebrew()
    {
        $category = TestCategory::create([
            'name_en' => 'Bikes',
            'name_ar' => 'دراجات',
            'name_fr' => 'Vélos',
            'slug'    => 'bikes',
        ]);

        app()->setLocale('en');
        $this->assertEquals('Bikes', $category->name);

        app()->setLocale('ar');
        $this->assertEquals('دراجات', $category->name);
    }

    /** @test */
    public function it_works_with_model_level_locale_override()
    {
        // TestCategory defines $translatableLocales = ['en', 'ar', 'fr']
        // This overrides the global config of ['en', 'ar'] for this model only.
        $category = TestCategory::create([
            'name_en' => 'Bikes',
            'name_ar' => 'دراجات',
            'name_fr' => 'Vélos',
            'slug'    => 'bikes',
        ]);

        app()->setLocale('fr');
        $this->assertEquals('Vélos', $category->name);
    }

    /** @test */
    public function it_falls_back_through_locale_chain()
    {
        // If name_ar is null, falls back through the chain until a value is found.
        $category = TestCategory::create([
            'name_en' => 'Bikes',
            'name_ar' => null,  // Arabic missing
            'name_fr' => null,  // French missing
            'slug'    => 'bikes',
        ]);

        app()->setLocale('ar');
        $this->assertEquals('Bikes', $category->name);  // Falls back to en
    }

    /** @test */
    public function non_translatable_fillable_attributes_are_not_detected()
    {
        // 'slug' is in $fillable but not in $translatable — should not be translatable.
        $category = new TestCategory();

        $this->assertFalse($category->isTranslatableAttribute('slug'));
    }

    /** @test */
    public function it_can_get_all_translations_for_three_locales()
    {
        $category = TestCategory::create([
            'name_en' => 'Bikes',
            'name_ar' => 'دراجات',
            'name_fr' => 'Vélos',
            'slug'    => 'bikes',
        ]);

        $translations = $category->getTranslations('name');

        $this->assertEquals([
            'en' => 'Bikes',
            'ar' => 'دراجات',
            'fr' => 'Vélos',
        ], $translations);
    }
}

/**
 * Test model using explicit $translatable property and model-level locale override.
 *
 * This pattern is useful when:
 * - You want to be explicit about which fields are translatable
 * - A specific model needs more locales than the global config
 *
 * Example real-world usage:
 *
 *   class Setting extends Model {
 *       use Translatable;
 *       protected array $translatable = ['value', 'title'];
 *       protected array $translatableLocales = ['en', 'ar', 'fr'];
 *   }
 */
class TestCategory extends Model
{
    use Translatable;

    protected $table = 'test_categories';

    /**
     * Explicitly define which attributes are translatable.
     * Only 'name' is translatable — 'slug' is not, even though it's in $fillable.
     */
    protected array $translatable = ['name'];

    /**
     * Override the global locales config for this model only.
     * Supports French in addition to the global en + ar.
     */
    protected array $translatableLocales = ['en', 'ar', 'fr'];

    protected $fillable = [
        'name_en',
        'name_ar',
        'name_fr',
        'slug',
    ];

    protected $appends = [];
}

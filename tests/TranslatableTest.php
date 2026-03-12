<?php

namespace Ibraheem9\Translatable\Tests;

use Ibraheem9\Translatable\Traits\Translatable;
use Illuminate\Database\Eloquent\Model;
use Orchestra\Testbench\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tests for the Translatable trait using the en/ar (English/Arabic) pattern.
 *
 * This mirrors the real-world usage pattern:
 *
 *   protected $fillable = ['name_en', 'name_ar', 'price'];
 *
 * The trait auto-detects 'name_en' and registers 'name' as a virtual attribute.
 * Access via $product->name resolves to name_en or name_ar based on app locale.
 */
class TranslatableTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            \Ibraheem9\Translatable\TranslatableServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('translatable.locales', ['en', 'ar']);
        $app['config']->set('translatable.primary_locale', 'en');
        $app['config']->set('translatable.auto_detect', true);
        $app['config']->set('translatable.auto_append', true);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('test_products', function (Blueprint $table) {
            $table->id();
            $table->string('name_en')->nullable();
            $table->string('name_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    /** @test */
    public function it_detects_translatable_attributes_from_fillable()
    {
        // The trait scans $fillable for columns ending with '_en' (primary locale)
        // and registers the base name as a virtual translatable attribute.
        $product = new TestProduct();
        $attributes = $product->getTranslatableAttributes();

        $this->assertContains('name', $attributes);
        $this->assertContains('description', $attributes);
        $this->assertNotContains('price', $attributes);  // non-translatable
    }

    /** @test */
    public function it_returns_translated_value_for_current_locale()
    {
        app()->setLocale('en');

        $product = TestProduct::create([
            'name_en'        => 'Mountain Bike',
            'name_ar'        => 'دراجة جبلية',
            'description_en' => 'A great mountain bike',
            'description_ar' => 'دراجة جبلية ممتازة',
            'price'          => 1299.99,
        ]);

        $this->assertEquals('Mountain Bike', $product->name);
        $this->assertEquals('A great mountain bike', $product->description);
    }

    /** @test */
    public function it_switches_language_based_on_locale()
    {
        // This is the core feature: $product->name resolves differently per locale.
        $product = TestProduct::create([
            'name_en' => 'Mountain Bike',
            'name_ar' => 'دراجة جبلية',
            'price'   => 1299.99,
        ]);

        app()->setLocale('en');
        $this->assertEquals('Mountain Bike', $product->name);

        app()->setLocale('ar');
        $this->assertEquals('دراجة جبلية', $product->name);
    }

    /** @test */
    public function it_falls_back_when_current_locale_is_empty()
    {
        // If name_ar is null, the package falls back to name_en automatically.
        app()->setLocale('ar');

        $product = TestProduct::create([
            'name_en' => 'Mountain Bike',
            'name_ar' => null,  // Arabic translation missing
            'price'   => 1299.99,
        ]);

        $this->assertEquals('Mountain Bike', $product->name);
    }

    /** @test */
    public function it_returns_null_when_all_locales_are_empty()
    {
        app()->setLocale('en');

        $product = TestProduct::create([
            'name_en' => null,
            'name_ar' => null,
            'price'   => 1299.99,
        ]);

        $this->assertNull($product->name);
    }

    /** @test */
    public function it_can_get_translation_for_specific_locale()
    {
        // getTranslatedAttribute() forces a specific locale regardless of app locale.
        $product = TestProduct::create([
            'name_en' => 'Mountain Bike',
            'name_ar' => 'دراجة جبلية',
            'price'   => 1299.99,
        ]);

        $this->assertEquals('Mountain Bike', $product->getTranslatedAttribute('name', 'en'));
        $this->assertEquals('دراجة جبلية', $product->getTranslatedAttribute('name', 'ar'));
    }

    /** @test */
    public function it_can_get_all_translations()
    {
        // getTranslations() returns all locale values as an associative array.
        $product = TestProduct::create([
            'name_en' => 'Mountain Bike',
            'name_ar' => 'دراجة جبلية',
            'price'   => 1299.99,
        ]);

        $translations = $product->getTranslations('name');

        $this->assertEquals([
            'en' => 'Mountain Bike',
            'ar' => 'دراجة جبلية',
        ], $translations);
    }

    /** @test */
    public function it_can_set_translations()
    {
        // setTranslations() sets multiple locale values at once.
        $product = new TestProduct(['price' => 100]);

        $product->setTranslations('name', [
            'en' => 'Helmet',
            'ar' => 'خوذة',
        ]);

        $this->assertEquals('Helmet', $product->getTranslatedAttribute('name', 'en'));
        $this->assertEquals('خوذة', $product->getTranslatedAttribute('name', 'ar'));
    }

    /** @test */
    public function it_checks_if_attribute_is_translatable()
    {
        $product = new TestProduct();

        $this->assertTrue($product->isTranslatableAttribute('name'));
        $this->assertTrue($product->isTranslatableAttribute('description'));
        $this->assertFalse($product->isTranslatableAttribute('price'));
    }

    /** @test */
    public function it_includes_translated_attributes_in_json()
    {
        // When auto_append is true, the base attribute ('name') is added to $appends
        // and appears in JSON/array output alongside the raw columns.
        app()->setLocale('en');

        $product = TestProduct::create([
            'name_en'        => 'Mountain Bike',
            'name_ar'        => 'دراجة جبلية',
            'description_en' => 'Great bike',
            'description_ar' => 'دراجة رائعة',
            'price'          => 1299.99,
        ]);

        $json = $product->toArray();

        $this->assertArrayHasKey('name', $json);
        $this->assertArrayHasKey('description', $json);
        $this->assertEquals('Mountain Bike', $json['name']);
        $this->assertEquals('Great bike', $json['description']);
    }

    /** @test */
    public function it_can_query_with_where_translation_scope()
    {
        app()->setLocale('en');

        TestProduct::create(['name_en' => 'Mountain Bike', 'name_ar' => 'دراجة جبلية', 'price' => 1299]);
        TestProduct::create(['name_en' => 'Helmet', 'name_ar' => 'خوذة', 'price' => 49]);

        $results = TestProduct::whereTranslation('name', 'Mountain Bike')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Mountain Bike', $results->first()->name);
    }

    /** @test */
    public function it_can_search_with_where_translation_like_scope()
    {
        app()->setLocale('en');

        TestProduct::create(['name_en' => 'Mountain Bike Pro', 'name_ar' => 'دراجة جبلية', 'price' => 1599]);
        TestProduct::create(['name_en' => 'Mountain Bike Lite', 'name_ar' => 'دراجة جبلية', 'price' => 899]);
        TestProduct::create(['name_en' => 'Helmet', 'name_ar' => 'خوذة', 'price' => 49]);

        $results = TestProduct::whereTranslationLike('name', 'Mountain')->get();

        $this->assertCount(2, $results);
    }

    /** @test */
    public function it_can_search_across_all_locales()
    {
        // whereTranslationLikeAny searches name_en AND name_ar simultaneously.
        // This allows finding Arabic records while the app locale is English.
        app()->setLocale('en');

        TestProduct::create(['name_en' => 'Mountain Bike', 'name_ar' => 'دراجة جبلية', 'price' => 1299]);
        TestProduct::create(['name_en' => 'Helmet', 'name_ar' => 'خوذة', 'price' => 49]);

        // Search for Arabic term while app is in English
        $results = TestProduct::whereTranslationLikeAny('name', 'دراجة')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Mountain Bike', $results->first()->name);
    }

    /** @test */
    public function it_can_order_by_translation()
    {
        app()->setLocale('en');

        TestProduct::create(['name_en' => 'Zebra Bike', 'name_ar' => 'ز', 'price' => 100]);
        TestProduct::create(['name_en' => 'Alpha Bike', 'name_ar' => 'أ', 'price' => 200]);

        $results = TestProduct::orderByTranslation('name', 'asc')->get();

        $this->assertEquals('Alpha Bike', $results->first()->name);
        $this->assertEquals('Zebra Bike', $results->last()->name);
    }

    /** @test */
    public function non_translatable_attributes_work_normally()
    {
        $product = TestProduct::create([
            'name_en' => 'Mountain Bike',
            'name_ar' => 'دراجة جبلية',
            'price'   => 1299.99,
        ]);

        $this->assertEquals(1299.99, $product->price);
    }
}

/**
 * Test model — mirrors the real-world Medicine/Product model pattern:
 *
 *   use Translatable;
 *   protected $fillable = ['name_en', 'name_ar', 'price'];
 *
 * No $translatable array needed — auto-detection from $fillable is the default.
 */
class TestProduct extends Model
{
    use Translatable;

    protected $table = 'test_products';

    protected $fillable = [
        'name_en',
        'name_ar',
        'description_en',
        'description_ar',
        'price',
    ];

    protected $appends = [];
}

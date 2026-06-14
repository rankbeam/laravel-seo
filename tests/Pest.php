<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(
    Rankbeam\Seo\Tests\TestCase::class,
)->in('Unit');

uses(
    Rankbeam\Seo\Tests\TestCase::class,
    Illuminate\Foundation\Testing\RefreshDatabase::class,
)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeValidSEOData', function () {
    return $this->toBeInstanceOf(\Rankbeam\Seo\Data\SEOData::class);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every test file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Create a mock model with specified attributes.
 */
function createMockModel(array $attributes = [], ?array $seoMeta = null): \Illuminate\Database\Eloquent\Model
{
    $model = new class extends \Illuminate\Database\Eloquent\Model
    {
        protected $guarded = [];

        public ?object $seoMeta = null;

        public function setSeoMeta(?array $meta): void
        {
            if ($meta === null) {
                $this->seoMeta = null;
                return;
            }

            // Ensure all expected SEO fields have defaults
            $defaults = [
                'title' => null,
                'description' => null,
                'canonical' => null,
                'robots' => null,
                'og_title' => null,
                'og_description' => null,
                'og_image' => null,
                'og_type' => 'website',
                'twitter_title' => null,
                'twitter_description' => null,
                'twitter_image' => null,
                'twitter_card' => 'summary_large_image',
                'focus_keywords' => null,
                'schema_jsonld' => null,
                'locale' => 'en',
            ];

            $this->seoMeta = (object) array_merge($defaults, $meta);
        }
    };

    foreach ($attributes as $key => $value) {
        $model->setAttribute($key, $value);
    }

    if ($seoMeta !== null) {
        $model->setSeoMeta($seoMeta);
    }

    return $model;
}

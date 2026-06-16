<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Rankbeam\Seo\Services\SEOResolver;

/*
 * Regression tests for manual seo_meta.og_image values that previously
 * rendered relative (`/images/x.jpg`) while computed image fallbacks were
 * absolutized. The OG spec requires absolute URLs, so the resolver now
 * normalizes every winning image value at the end of the chain.
 */

class ImageAbsolutizationModel extends Model
{
    protected $guarded = [];

    public ?object $seoMetaObject = null;

    public function seoMeta(): ?object
    {
        return $this->seoMetaObject;
    }

    public function getSeoMetaAttribute(): ?object
    {
        return $this->seoMetaObject;
    }
}

function makeImageModel(array $meta): ImageAbsolutizationModel
{
    $model = new ImageAbsolutizationModel(['title' => 'Post']);

    $model->seoMetaObject = (object) array_merge([
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
    ], $meta);

    return $model;
}

beforeEach(function () {
    config(['app.url' => 'https://example.test']);
    URL::forceRootUrl('https://example.test');
    URL::forceScheme('https');
});

it('absolutizes a relative manual og_image from seo_meta', function () {
    $seo = app(SEOResolver::class)->resolve(makeImageModel(['og_image' => '/images/share.jpg']));

    expect($seo->ogImage)->toBe('https://example.test/images/share.jpg');
});

it('absolutizes a relative manual twitter_image from seo_meta', function () {
    $seo = app(SEOResolver::class)->resolve(makeImageModel(['twitter_image' => 'images/tw.jpg']));

    expect($seo->twitterImage)->toBe('https://example.test/images/tw.jpg');
});

it('leaves absolute manual og_image values untouched', function () {
    $seo = app(SEOResolver::class)->resolve(makeImageModel(['og_image' => 'https://cdn.example.com/share.jpg']));

    expect($seo->ogImage)->toBe('https://cdn.example.com/share.jpg');
});

it('upgrades protocol-relative og_image values to https', function () {
    $seo = app(SEOResolver::class)->resolve(makeImageModel(['og_image' => '//cdn.example.com/share.jpg']));

    expect($seo->ogImage)->toBe('https://cdn.example.com/share.jpg');
});

it('absolutizes the config default og_image when no other layer wins', function () {
    // TestCase sets seo.default_og_image to /default-og.jpg
    $seo = app(SEOResolver::class)->resolve();

    expect($seo->ogImage)->toBe('https://example.test/default-og.jpg');
});

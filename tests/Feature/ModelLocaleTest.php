<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Rankbeam\Seo\I18n\ModelLocale;
use Rankbeam\Seo\Services\SEOComputedBuilder;
use Rankbeam\Seo\Traits\HasSEO;

class LocaleAwareSeoModel extends Model
{
    use HasSEO;

    public bool $throwInHook = false;

    public string $instanceLocale = 'en';

    public function getTranslatableAttributes(): array
    {
        return ['title'];
    }

    public function setLocale(string $locale): static
    {
        $this->instanceLocale = $locale;

        return $this;
    }

    public function getSEOTitle(): ?string
    {
        if ($this->throwInHook) {
            throw new RuntimeException('Hook failed');
        }

        return $this->instanceLocale === 'ja' ? '日本語のタイトル' : 'Titolo italiano';
    }

    public function getSEODescription(): ?string
    {
        return app()->getLocale().': '.($this->seoMeta->focus_keywords[0]['keyword'] ?? 'empty');
    }

    public function getUrlForSEO(): string
    {
        return 'https://example.test/'.app()->getLocale().'/article';
    }

    public function getSEOSchema(): array
    {
        return [['@type' => 'WebPage', 'name' => app()->getLocale(), 'inLanguage' => app()->getLocale()]];
    }
}

beforeEach(function () {
    config(['seo.title_suffix' => '', 'seo.features.auto_create_meta' => false]);
    app()->setLocale('en');
});

it('resolves metadata and all hooks in the explicit locale on an isolated model', function () {
    $model = new LocaleAwareSeoModel;
    $model->setAttribute('id', 42);
    $model->exists = true;
    $model->saveSEO(['title' => 'English manual', 'focus_keywords' => [['keyword' => 'coffee']]], 'en');
    $model->saveSEO(['title' => 'Manuale italiano', 'focus_keywords' => [['keyword' => 'caffè']]], 'it');
    $english = $model->seoMeta;

    $italian = $model->seoData('it');
    $japanese = $model->seoData('ja');
    expect($italian->title)->toBe('Manuale italiano')
        ->and($italian->description)->toBe('it: caffè')
        ->and($italian->canonical)->toBe('https://example.test/it/article')
        ->and(json_encode($italian->schemaJsonld))->toContain('"inLanguage":"it"')
        ->and($japanese->title)->toBe('日本語のタイトル')
        ->and($japanese->description)->toBe('ja: empty')
        ->and($model->seoMeta)->toBe($english)
        ->and($model->instanceLocale)->toBe('en')
        ->and(app()->getLocale())->toBe('en');
});

it('restores application and instance locales after a failing hook', function () {
    $model = new LocaleAwareSeoModel;
    $model->throwInHook = true;
    expect(fn () => app(SEOComputedBuilder::class)->fromModel($model, 'ja'))->toThrow(RuntimeException::class, 'Hook failed');
    expect(app()->getLocale())->toBe('en')->and($model->instanceLocale)->toBe('en');
});

it('restores nested locale scopes in order', function () {
    $model = new LocaleAwareSeoModel;
    ModelLocale::run($model, 'it', function ($localized) {
        expect(app()->getLocale())->toBe('it');
        ModelLocale::run($localized, 'ja', fn () => expect(app()->getLocale())->toBe('ja'));
        expect(app()->getLocale())->toBe('it');
    });
    expect(app()->getLocale())->toBe('en');
});

it('restores locale and returns the UI fallback when intrinsic locale discovery throws', function () {
    $model = new class extends Model
    {
        public function seoData(): never
        {
            app()->setLocale('ja');
            throw new RuntimeException('Custom resolver failure');
        }
    };
    expect(ModelLocale::forModel($model))->toBe('en')->and(app()->getLocale())->toBe('en');
});

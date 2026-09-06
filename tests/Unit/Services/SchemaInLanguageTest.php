<?php

declare(strict_types=1);

use Rankbeam\Seo\Data\SEOData;
use Rankbeam\Seo\Services\Schema\ArticleSchema;
use Rankbeam\Seo\Services\Schema\SchemaGraph;

/*
|--------------------------------------------------------------------------
| inLanguage on the schema graph (M2)
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    config([
        'app.url' => 'https://example.com',
        'seo.schema.in_language' => true,
        'seo.schema.website' => ['name' => 'Example'],
    ]);
});

describe('WebPage', function () {
    it('emits inLanguage from the resolved locale in BCP 47 form', function () {
        $page = (new SchemaGraph)->webPage(new SEOData(title: 'Pagina', canonical: 'https://example.com/it/pagina', locale: 'it_IT'));

        expect($page['inLanguage'])->toBe('it-IT');
    });

    it('keeps a bare language code as is', function () {
        $page = (new SchemaGraph)->webPage(new SEOData(title: 'Page', canonical: 'https://example.com/page', locale: 'en'));

        expect($page['inLanguage'])->toBe('en');
    });

    it('omits inLanguage when the locale is blank', function () {
        $page = (new SchemaGraph)->webPage(new SEOData(title: 'Page', canonical: 'https://example.com/page'));

        expect($page)->not->toHaveKey('inLanguage');
    });

    it('omits inLanguage everywhere when seo.schema.in_language is off', function () {
        config(['seo.schema.in_language' => false, 'seo.schema.website' => ['name' => 'Example', 'inLanguage' => 'it']]);

        $graph = new SchemaGraph;
        $page = $graph->webPage(new SEOData(title: 'Page', canonical: 'https://example.com/page', locale: 'it'));

        expect($page)->not->toHaveKey('inLanguage')
            ->and($graph->webSite())->not->toHaveKey('inLanguage')
            ->and($graph->inLanguage('it'))->toBeNull();
    });
});

describe('WebSite', function () {
    it('has no inLanguage unless configured', function () {
        expect((new SchemaGraph)->webSite())->not->toHaveKey('inLanguage');
    });

    it('emits one language as a string and several as a list, normalised', function () {
        config(['seo.schema.website' => ['name' => 'Example', 'inLanguage' => 'pt_BR']]);

        expect((new SchemaGraph)->webSite()['inLanguage'])->toBe('pt-BR');

        config(['seo.schema.website' => ['name' => 'Example', 'inLanguage' => ['it', 'en_US', 42]]]);

        expect((new SchemaGraph)->webSite()['inLanguage'])->toBe(['it', 'en-US']);
    });
});

describe('Article', function () {
    it('takes the language from the model seo_meta locale', function () {
        $model = createMockModel(['title' => 'Post'], ['locale' => 'pt_BR']);

        expect(ArticleSchema::fromModel($model)->toArray()['inLanguage'])->toBe('pt-BR');
    });

    it('can be set explicitly and cleared', function () {
        $schema = (new ArticleSchema)->setHeadline('Post');

        expect($schema->toArray())->not->toHaveKey('inLanguage')
            ->and($schema->setInLanguage('zh_Hans')->toArray()['inLanguage'])->toBe('zh-Hans')
            ->and($schema->setInLanguage(null)->toArray())->not->toHaveKey('inLanguage');
    });

    it('respects the config switch on the model path', function () {
        config(['seo.schema.in_language' => false]);

        $model = createMockModel(['title' => 'Post'], ['locale' => 'it']);

        expect(ArticleSchema::fromModel($model)->toArray())->not->toHaveKey('inLanguage');
    });
});

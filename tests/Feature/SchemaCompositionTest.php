<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Rankbeam\Seo\Services\SEOResolver;
use Rankbeam\Seo\Services\Schema\SchemaGraph;
use Rankbeam\Seo\Services\Schema\SchemaValidator;
use Rankbeam\Seo\Traits\HasSEO;

/*
|--------------------------------------------------------------------------
| Schema composition + resolver precedence (improvement plan T11)
|--------------------------------------------------------------------------
|
| - A model with no stored schema exposes a cross-linked WebPage +
|   BreadcrumbList graph through getSEOSchema(), with deterministic @ids.
| - A model WITH a stored seo_meta.schema_jsonld emits exactly that and the
|   hook is NOT invoked (asserted via a call counter).
| - The seo.schema.type_map config mapping is the fallback when a model does
|   not override getSEOSchema(); the per-model hook wins over the map.
| - getSEOSchema() composing a webPage() (which re-resolves seoData) does not
|   recurse forever.
|
*/

class SchemaCompositionPage extends Model
{
    use HasSEO;

    protected $table = 'schema_pages';

    protected $fillable = ['title', 'slug', 'parent_id', 'page_url'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function getUrlForSEO(): string
    {
        return $this->page_url ?? url('/' . ltrim((string) $this->slug, '/'));
    }

    public function getSEOSchema(): array
    {
        return SchemaGraph::for($this)
            ->organization()
            ->website()
            ->webPage()
            ->breadcrumbFromAncestors()
            ->toArray();
    }
}

// Records how often the hook runs, so "stored schema wins → hook NOT called"
// is directly assertable.
class SchemaHookSpyPage extends SchemaCompositionPage
{
    public static int $hookCalls = 0;

    public function getSEOSchema(): array
    {
        static::$hookCalls++;

        return SchemaGraph::for($this)->webPage()->toArray();
    }
}

// Uses the trait's default getSEOSchema() ([]), so only the type-map can
// produce a graph for it.
class PlainSchemaPage extends Model
{
    use HasSEO;

    protected $table = 'schema_pages';

    protected $fillable = ['title', 'slug', 'parent_id', 'page_url'];

    public function getUrlForSEO(): string
    {
        return $this->page_url ?? url('/' . ltrim((string) $this->slug, '/'));
    }
}

// An invokable type-map builder (the config:cache-safe canonical form).
class MappedSchemaBuilder
{
    public function __invoke(Model $model): array
    {
        return [[
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $model->title . ' (mapped)',
        ]];
    }
}

beforeEach(function () {
    $this->app['db']->connection()->getSchemaBuilder()->create('schema_pages', function ($table) {
        $table->id();
        $table->string('title')->nullable();
        $table->string('slug')->nullable();
        $table->unsignedBigInteger('parent_id')->nullable();
        $table->string('page_url')->nullable();
        $table->timestamps();
    });

    config(['seo.features.auto_create_meta' => false]);
    config(['seo.title_suffix' => null]);
    config(['app.url' => 'https://example.com']);
    config(['seo.site_name' => 'Example Co']);
    config(['seo.schema.organization' => ['name' => 'Example Co', 'logo' => '/images/logo.svg']]);
    config(['seo.schema.website' => ['name' => 'Example Co']]);
    config(['seo.schema.type_map' => []]);

    SchemaHookSpyPage::$hookCalls = 0;
});

afterEach(function () {
    $this->app['db']->connection()->getSchemaBuilder()->dropIfExists('schema_pages');
});

function resolveSchema(Model $model): ?array
{
    return app(SEOResolver::class)->resolve($model)->schemaJsonld;
}

describe('hook produces the graph when no schema is stored', function () {
    it('exposes a cross-linked WebPage + BreadcrumbList with stable @ids', function () {
        $home = SchemaCompositionPage::create(['title' => 'Home', 'slug' => '/', 'page_url' => 'https://example.com/']);
        $blog = SchemaCompositionPage::create(['title' => 'Blog', 'slug' => 'blog', 'parent_id' => $home->id, 'page_url' => 'https://example.com/blog']);
        $post = SchemaCompositionPage::create(['title' => 'Hello', 'slug' => 'blog/hello', 'parent_id' => $blog->id, 'page_url' => 'https://example.com/blog/hello']);

        $schema = resolveSchema($post);

        expect($schema)->toBeArray();

        $types = array_map(fn ($node) => $node['@type'], $schema);
        expect($types)->toBe(['Organization', 'WebSite', 'WebPage', 'BreadcrumbList']);

        $byType = collect($schema)->keyBy('@type');

        // Deterministic, cross-linked @ids.
        expect($byType['WebPage']['@id'])->toBe('https://example.com/blog/hello#webpage')
            ->and($byType['WebPage']['isPartOf'])->toBe(['@id' => 'https://example.com#website'])
            ->and($byType['WebPage']['about'])->toBe(['@id' => 'https://example.com#organization'])
            ->and($byType['WebSite']['publisher'])->toBe(['@id' => 'https://example.com#organization']);

        // Breadcrumb: Home → Blog → Hello.
        $crumbs = array_map(fn ($i) => $i['name'], $byType['BreadcrumbList']['itemListElement']);
        expect($crumbs)->toBe(['Home', 'Blog', 'Hello']);
    });

    it('produces a graph that validates', function () {
        $home = SchemaCompositionPage::create(['title' => 'Home', 'slug' => '/', 'page_url' => 'https://example.com/']);
        $post = SchemaCompositionPage::create(['title' => 'About', 'slug' => 'about', 'parent_id' => $home->id, 'page_url' => 'https://example.com/about']);

        $schema = resolveSchema($post);
        $validator = new SchemaValidator();

        foreach ($schema as $node) {
            expect($validator->validate($node)->isValid)
                ->toBeTrue("node @type {$node['@type']} should validate");
        }
    });

    it('renders the composed schema through the full resolve → render path', function () {
        $home = SchemaCompositionPage::create(['title' => 'Home', 'slug' => '/', 'page_url' => 'https://example.com/']);
        $post = SchemaCompositionPage::create(['title' => 'News', 'slug' => 'news', 'parent_id' => $home->id, 'page_url' => 'https://example.com/news']);

        $html = app(SEOResolver::class)->render($post);

        expect($html)->toContain('application/ld+json')
            ->and($html)->toContain('BreadcrumbList')
            ->and($html)->toContain('https://example.com/news#webpage');
    });
});

describe('stored schema is authoritative', function () {
    it('emits the stored schema_jsonld verbatim and never calls the hook', function () {
        $stored = [[
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => 'Hand-authored',
        ]];

        $page = SchemaHookSpyPage::create(['title' => 'Spy', 'slug' => 'spy', 'page_url' => 'https://example.com/spy']);
        $page->saveSEO(['schema_jsonld' => $stored]);

        SchemaHookSpyPage::$hookCalls = 0;

        $schema = resolveSchema($page->fresh());

        expect($schema)->toBe($stored)
            ->and(SchemaHookSpyPage::$hookCalls)->toBe(0);
    });

    it('calls the hook when no schema is stored', function () {
        $page = SchemaHookSpyPage::create(['title' => 'Spy', 'slug' => 'spy', 'page_url' => 'https://example.com/spy']);

        $schema = resolveSchema($page);

        expect(SchemaHookSpyPage::$hookCalls)->toBeGreaterThan(0)
            ->and($schema[0]['@type'])->toBe('WebPage');
    });
});

describe('config type_map fallback', function () {
    it('uses the mapped builder when the model does not override the hook', function () {
        config(['seo.schema.type_map' => [PlainSchemaPage::class => MappedSchemaBuilder::class]]);

        $page = PlainSchemaPage::create(['title' => 'Mapped', 'slug' => 'mapped', 'page_url' => 'https://example.com/mapped']);

        $schema = resolveSchema($page);

        expect($schema)->toBe([[
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => 'Mapped (mapped)',
        ]]);
    });

    it('accepts a Closure builder for runtime configuration', function () {
        config(['seo.schema.type_map' => [
            PlainSchemaPage::class => fn (Model $m) => [['@type' => 'Thing', 'name' => $m->title]],
        ]]);

        $page = PlainSchemaPage::create(['title' => 'Closure', 'slug' => 'c']);

        expect(resolveSchema($page))->toBe([['@type' => 'Thing', 'name' => 'Closure']]);
    });

    it('matches a base-class mapping for a subclass', function () {
        config(['seo.schema.type_map' => [
            PlainSchemaPage::class => fn (Model $m) => [['@type' => 'Thing', 'name' => 'base']],
        ]]);

        // A subclass of the mapped class still resolves via the base mapping.
        $sub = new class extends PlainSchemaPage {};
        $sub->forceFill(['title' => 'sub', 'slug' => 's'])->save();

        expect(resolveSchema($sub->fresh()))->toBe([['@type' => 'Thing', 'name' => 'base']]);
    });

    it('lets the per-model hook win over a type-map entry', function () {
        config(['seo.schema.type_map' => [SchemaCompositionPage::class => MappedSchemaBuilder::class]]);

        $home = SchemaCompositionPage::create(['title' => 'Home', 'slug' => '/', 'page_url' => 'https://example.com/']);
        $page = SchemaCompositionPage::create(['title' => 'Hooked', 'slug' => 'hooked', 'parent_id' => $home->id, 'page_url' => 'https://example.com/hooked']);

        $schema = resolveSchema($page);
        $types = array_map(fn ($node) => $node['@type'], $schema);

        // The hook graph, not the single "(mapped)" node.
        expect($types)->toContain('Organization')
            ->and(collect($schema)->pluck('name')->filter(fn ($n) => str_contains((string) $n, '(mapped)')))->toBeEmpty();
    });
});

describe('additive: unchanged for plain models', function () {
    it('produces no schema for a model with no hook override and no type-map', function () {
        $page = PlainSchemaPage::create(['title' => 'Plain', 'slug' => 'plain']);

        expect(resolveSchema($page))->toBeNull();
    });
});

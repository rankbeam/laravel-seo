<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Rankbeam\Seo\Models\SEOMeta;
use Rankbeam\Seo\Traits\HasSEO;

/**
 * A HasSEO model standing in for the host app's content during a migration
 * away from ralphjsmit/laravel-seo.
 */
class ImportPost extends Model
{
    use HasSEO;

    protected $table = 'import_posts';

    protected $fillable = ['title', 'slug'];

    public function getUrlForSEO(): string
    {
        return url("/posts/{$this->slug}");
    }
}

class ImportPage extends Model
{
    use HasSEO;

    protected $table = 'import_pages';

    protected $fillable = ['title', 'slug'];
}

/** A model WITHOUT the HasSEO trait — the importer should still write its row. */
class ImportTraitless extends Model
{
    protected $table = 'import_traitless';

    protected $fillable = ['title'];
}

beforeEach(function () {
    config(['seo.title_suffix' => '']);

    $schema = $this->app['db']->connection()->getSchemaBuilder();

    foreach (['import_posts', 'import_pages', 'import_traitless'] as $table) {
        $schema->create($table, function ($t) {
            $t->id();
            $t->string('title')->nullable();
            $t->string('slug')->nullable();
            $t->timestamps();
        });
    }

    // The ralphjsmit `seo` morph table, recreated exactly (its published shape).
    $schema->create('seo', function ($t) {
        $t->id();
        $t->morphs('model');
        $t->longText('description')->nullable();
        $t->string('title')->nullable();
        $t->string('image')->nullable();
        $t->string('author')->nullable();
        $t->string('robots')->nullable();
        $t->string('canonical_url')->nullable();
        $t->timestamps();
    });
});

afterEach(function () {
    $schema = $this->app['db']->connection()->getSchemaBuilder();

    foreach (['import_posts', 'import_pages', 'import_traitless', 'seo'] as $table) {
        $schema->dropIfExists($table);
    }

    // Clear any morph map a test registered so it cannot leak into others.
    Relation::morphMap([], false);
});

/**
 * @return array{0: int, 1: string}
 */
function runImport(array $parameters = []): array
{
    $exit = Artisan::call('seo:import-from', $parameters);

    return [$exit, Artisan::output()];
}

/**
 * Insert a ralphjsmit-shaped `seo` row.
 */
function seedRalphRow(string $type, int|string $id, array $attributes = []): void
{
    DB::table('seo')->insert(array_merge([
        'model_type' => $type,
        'model_id' => $id,
        'title' => null,
        'description' => null,
        'image' => null,
        'author' => null,
        'robots' => null,
        'canonical_url' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ], $attributes));
}

function metaFor(Model $model, string $locale = 'en'): ?SEOMeta
{
    return SEOMeta::query()
        ->where('seoable_type', $model->getMorphClass())
        ->where('seoable_id', $model->getKey())
        ->where('locale', $locale)
        ->first();
}

it('errors and lists available sources for an unknown source', function () {
    [$exit, $output] = runImport(['source' => 'wordpress']);

    expect($exit)->toBe(1)
        ->and($output)->toContain('Unknown import source [wordpress]')
        ->and($output)->toContain('ralphjsmit');
});

it('errors when the source table does not exist', function () {
    [$exit, $output] = runImport(['source' => 'ralphjsmit', '--table' => 'no_such_table']);

    expect($exit)->toBe(1)
        ->and($output)->toContain('no_such_table')
        ->and($output)->toContain('was not found');
});

it('maps ralphjsmit columns into seo_meta and reports the unmapped author', function () {
    config(['seo.features.auto_create_meta' => false]);

    $post = ImportPost::create(['title' => 'Internal Post', 'slug' => 'internal']);

    seedRalphRow(ImportPost::class, $post->id, [
        'title' => 'A Hand-Written SEO Title',
        'description' => 'A carefully written meta description from the old package.',
        'canonical_url' => 'https://example.com/posts/internal',
        'robots' => 'noindex, nofollow',
        'image' => 'https://example.com/og/internal.jpg',
        'author' => 'Jane Doe',
    ]);

    [$exit, $output] = runImport(['source' => 'ralphjsmit']);

    $meta = metaFor($post);

    expect($exit)->toBe(0)
        ->and($meta)->not->toBeNull()
        ->and($meta->title)->toBe('A Hand-Written SEO Title')
        ->and($meta->description)->toBe('A carefully written meta description from the old package.')
        ->and($meta->canonical)->toBe('https://example.com/posts/internal')
        ->and($meta->robots)->toBe('noindex, nofollow')
        ->and($meta->og_image)->toBe('https://example.com/og/internal.jpg')
        // author has no Core 3 column — it must be reported, never copied.
        ->and($output)->toContain('author')
        ->and($output)->toContain('1 created');
});

it('updates the empty seo_meta row that HasSEO auto-created, never duplicating it', function () {
    // auto_create_meta is on by default → an empty seo_meta row already exists.
    $post = ImportPost::create(['title' => 'Auto Created', 'slug' => 'auto']);

    expect(SEOMeta::query()->where('seoable_id', $post->id)->count())->toBe(1);

    seedRalphRow(ImportPost::class, $post->id, [
        'title' => 'Imported Over The Empty Row',
    ]);

    [, $output] = runImport(['source' => 'ralphjsmit']);

    expect(SEOMeta::query()->where('seoable_id', $post->id)->count())->toBe(1)
        ->and(metaFor($post)->title)->toBe('Imported Over The Empty Row')
        ->and($output)->toContain('1 updated');
});

it('is idempotent — a second run is a no-op with no duplicate rows', function () {
    config(['seo.features.auto_create_meta' => false]);

    $post = ImportPost::create(['title' => 'Repeatable', 'slug' => 'repeatable']);
    seedRalphRow(ImportPost::class, $post->id, ['title' => 'Stable Title']);

    runImport(['source' => 'ralphjsmit']);
    [, $secondOutput] = runImport(['source' => 'ralphjsmit']);

    expect(SEOMeta::query()->where('seoable_id', $post->id)->count())->toBe(1)
        ->and($secondOutput)->toContain('0 created')
        ->and($secondOutput)->toContain('1 unchanged');
});

it('writes nothing on a dry run but reports what would happen', function () {
    config(['seo.features.auto_create_meta' => false]);

    $post = ImportPost::create(['title' => 'Preview', 'slug' => 'preview']);
    seedRalphRow(ImportPost::class, $post->id, ['title' => 'Would Be Imported']);

    [, $output] = runImport(['source' => 'ralphjsmit', '--dry-run' => true]);

    expect(SEOMeta::query()->where('seoable_id', $post->id)->count())->toBe(0)
        ->and($output)->toContain('DRY RUN')
        ->and($output)->toContain('nothing was written')
        ->and($output)->toContain('1 created');
});

it('scopes the import to --model classes', function () {
    config(['seo.features.auto_create_meta' => false]);

    $post = ImportPost::create(['title' => 'Post', 'slug' => 'post']);
    $page = ImportPage::create(['title' => 'Page', 'slug' => 'page']);

    seedRalphRow(ImportPost::class, $post->id, ['title' => 'Post SEO']);
    seedRalphRow(ImportPage::class, $page->id, ['title' => 'Page SEO']);

    [, $output] = runImport(['source' => 'ralphjsmit', '--model' => [ImportPost::class]]);

    expect(metaFor($post))->not->toBeNull()
        ->and(metaFor($page))->toBeNull()
        // page row was filtered out at the query level, so only 1 row scanned.
        ->and($output)->toContain('1 source row(s) scanned');
});

it('resolves a morph-map alias to the real model so the relation reads back', function () {
    config(['seo.features.auto_create_meta' => false]);

    Relation::morphMap(['ralph_post' => ImportPost::class]);

    $post = ImportPost::create(['title' => 'Aliased', 'slug' => 'aliased']);

    // ralphjsmit stored the alias, not the FQCN.
    seedRalphRow('ralph_post', $post->id, ['title' => 'Aliased Title']);

    runImport(['source' => 'ralphjsmit']);

    // Reading back through the relation proves seoable_type matches getMorphClass().
    expect($post->fresh()->seoMeta->title)->toBe('Aliased Title')
        ->and(metaFor($post))->not->toBeNull();
});

it('skips a row whose model no longer exists', function () {
    seedRalphRow(ImportPost::class, 99999, ['title' => 'Orphaned']);

    [, $output] = runImport(['source' => 'ralphjsmit']);

    expect($output)->toContain('source model no longer exists')
        ->and($output)->toContain('0 created');
});

it('skips a row whose model type cannot be resolved', function () {
    seedRalphRow('App\\Ghost\\Missing', 1, ['title' => 'Ghost']);

    [, $output] = runImport(['source' => 'ralphjsmit']);

    expect($output)->toContain('unresolved model type')
        ->and($output)->toContain('0 created');
});

it('skips an empty source row instead of writing a blank record', function () {
    config(['seo.features.auto_create_meta' => false]);

    $post = ImportPost::create(['title' => 'Empty', 'slug' => 'empty']);
    seedRalphRow(ImportPost::class, $post->id); // all SEO columns null

    [, $output] = runImport(['source' => 'ralphjsmit']);

    expect(metaFor($post))->toBeNull()
        ->and($output)->toContain('empty source row');
});

it('truncates over-length values to fit the Core 3 columns and reports it', function () {
    config(['seo.features.auto_create_meta' => false]);

    $longTitle = str_repeat('a', 120); // ralphjsmit string(255) → core string(70)
    $post = ImportPost::create(['title' => 'Long', 'slug' => 'long']);
    seedRalphRow(ImportPost::class, $post->id, ['title' => $longTitle]);

    [, $output] = runImport(['source' => 'ralphjsmit']);

    expect(mb_strlen(metaFor($post)->title))->toBe(70)
        ->and($output)->toContain('Truncated')
        ->and($output)->toContain('title');
});

it('writes the row for a model without the HasSEO trait and warns', function () {
    seedRalphRow(ImportTraitless::class, 1, ['title' => 'Traitless Title']);

    // The traitless model has no auto-create hook; create the underlying row.
    DB::table('import_traitless')->insert(['id' => 1, 'title' => 'X', 'created_at' => now(), 'updated_at' => now()]);

    [, $output] = runImport(['source' => 'ralphjsmit']);

    $row = SEOMeta::query()
        ->where('seoable_type', (new ImportTraitless)->getMorphClass())
        ->where('seoable_id', 1)
        ->where('locale', 'en')
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->title)->toBe('Traitless Title')
        ->and($output)->toContain('does not use the Rankbeam HasSEO trait');
});

it('honours the --limit option', function () {
    config(['seo.features.auto_create_meta' => false]);

    foreach (range(1, 3) as $i) {
        $post = ImportPost::create(['title' => "Post {$i}", 'slug' => "post-{$i}"]);
        seedRalphRow(ImportPost::class, $post->id, ['title' => "Title {$i}"]);
    }

    [, $output] = runImport(['source' => 'ralphjsmit', '--limit' => 2]);

    expect($output)->toContain('2 source row(s) scanned');
});

it('emits structured JSON with --json', function () {
    config(['seo.features.auto_create_meta' => false]);

    $post = ImportPost::create(['title' => 'Json', 'slug' => 'json']);
    seedRalphRow(ImportPost::class, $post->id, ['title' => 'Json Title', 'author' => 'Someone']);

    [, $output] = runImport(['source' => 'ralphjsmit', '--json' => true]);

    $payload = json_decode($output, true);

    expect($payload)->toBeArray()
        ->and($payload['summary']['scanned'])->toBe(1)
        ->and($payload['summary']['created'])->toBe(1)
        ->and($payload['unmapped']['author'])->toBe(1)
        ->and($payload['dry_run'])->toBeFalse();
});

it('writes imported rows for the locale given by --locale', function () {
    config(['seo.features.auto_create_meta' => false]);

    $post = ImportPost::create(['title' => 'Localized', 'slug' => 'localized']);
    seedRalphRow(ImportPost::class, $post->id, ['title' => 'Titre Français']);

    runImport(['source' => 'ralphjsmit', '--locale' => 'fr']);

    expect(metaFor($post, 'fr'))->not->toBeNull()
        ->and(metaFor($post, 'fr')->title)->toBe('Titre Français')
        ->and(metaFor($post, 'en'))->toBeNull();
});

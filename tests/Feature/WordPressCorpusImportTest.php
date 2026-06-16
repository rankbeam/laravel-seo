<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Rankbeam\Seo\Models\SEOMeta;
use Rankbeam\Seo\Tests\Fixtures\WordPressCorpus;
use Rankbeam\Seo\Traits\HasSEO;

/**
 * End-to-end proof against an anonymized ~900-page WordPress corpus (the shape
 * of a real migration), exercising the Yoast and Rank Math importers at
 * scale: the verification report is asserted against the fixture's ground-truth
 * manifest, the import is idempotent, dry-runs are inert, and authors (which
 * have no Core 3 column) are surfaced verbatim rather than lost.
 *
 * @see WordPressCorpus
 */

/** The host-app content model the WordPress posts are migrated into. */
class CorpusPost extends Model
{
    use HasSEO;

    protected $table = 'corpus_posts';

    protected $fillable = ['title', 'slug'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}

beforeEach(function () {
    config([
        'seo.title_suffix' => '',
        'seo.features.auto_create_meta' => false,
        'database.connections.wordpress' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ],
    ]);

    $this->app['db']->connection()->getSchemaBuilder()->create('corpus_posts', function ($t) {
        $t->id();
        $t->string('title')->nullable();
        $t->string('slug')->nullable()->index();
        $t->timestamps();
    });

    WordPressCorpus::createSchema('wordpress');
    WordPressCorpus::seedBase('wordpress');
});

afterEach(function () {
    $this->app['db']->connection()->getSchemaBuilder()->dropIfExists('corpus_posts');
    WordPressCorpus::dropSchema('wordpress');
    DB::purge('wordpress');
    Relation::morphMap([], false);
});

/**
 * Seed the corpus on the `wordpress` connection and create host models for the
 * given slugs (defaults to every meta-bearing slug → all matched).
 *
 * @return array<string, mixed> the fixture manifest
 */
function corpusSeed(string $flavor, int $count, ?array $modelSlugs = null): array
{
    $manifest = WordPressCorpus::seedPosts('wordpress', $flavor, $count);

    $slugs = $modelSlugs ?? $manifest['meta_slugs'];
    $rows = array_map(static fn (string $slug): array => [
        'title' => 'Host '.$slug,
        'slug' => $slug,
    ], $slugs);

    foreach (array_chunk($rows, 500) as $chunk) {
        DB::table('corpus_posts')->insert($chunk);
    }

    return $manifest;
}

/**
 * @return array{0: int, 1: string}
 */
function corpusImport(array $parameters): array
{
    $exit = Artisan::call('seo:import-from', array_merge(['--connection' => 'wordpress'], $parameters));

    return [$exit, Artisan::output()];
}

function corpusJson(array $parameters): array
{
    [, $output] = corpusImport(array_merge($parameters, ['--json' => true]));

    return json_decode($output, true);
}

function corpusMeta(string $slug): ?SEOMeta
{
    $model = CorpusPost::query()->where('slug', $slug)->first();

    return $model?->seoMeta;
}

// ---------------------------------------------------------------------------
// Yoast — the ~900-page headline proof
// ---------------------------------------------------------------------------

it('imports a ~900-page Yoast corpus with an accurate verification report', function () {
    $manifest = corpusSeed('yoast', 900);

    $payload = corpusJson(['source' => 'yoast', '--model' => [CorpusPost::class]]);
    $v = $payload['verification'];

    // Every meta-bearing post attached to a model; nothing was URL-only.
    expect($v['matched'])->toBe($manifest['with_meta'])
        ->and($v['created'])->toBe($manifest['with_meta'])
        ->and($v['url_only'])->toBe(0)
        ->and($payload['summary']['scanned'])->toBe(900);

    // The posts with no SEO metadata were skipped, not written.
    expect(SEOMeta::query()->count())->toBe($manifest['with_meta']);

    // Over-length titles were truncated to fit; the count is exact.
    expect($v['truncated']['title'])->toBe($manifest['truncated_titles']);

    // Authors have no Core 3 column — every distinct value is surfaced verbatim.
    expect($v['unmapped']['author'])->toBe($manifest['with_meta'])
        ->and($v['unmapped_values']['author'])->toEqualCanonicalizing($manifest['authors']);

    // A genuinely-unmapped plugin key is reported (count only, no value dump).
    expect($v['unmapped']['schema_article_type'])->toBe($manifest['unmapped_extra'])
        ->and($v['unmapped_values'])->not->toHaveKey('schema_article_type');
});

it('is idempotent across the whole corpus — a second run writes nothing new', function () {
    $manifest = corpusSeed('yoast', 900);

    corpusImport(['source' => 'yoast', '--model' => [CorpusPost::class]]);
    $before = SEOMeta::query()->count();

    $payload = corpusJson(['source' => 'yoast', '--model' => [CorpusPost::class]]);

    expect($payload['verification']['unchanged'])->toBe($manifest['with_meta'])
        ->and($payload['verification']['created'])->toBe(0)
        ->and($payload['verification']['updated'])->toBe(0)
        ->and(SEOMeta::query()->count())->toBe($before);
});

it('resolves tokens, composes robots, and preserves multibyte text across the corpus', function () {
    corpusSeed('yoast', 120);

    corpusImport(['source' => 'yoast', '--model' => [CorpusPost::class]]);

    // Token title resolved against the post title + blog name.
    expect(corpusMeta('post-0001')->title)->toBe('Article 1 - '.WordPressCorpus::BLOGNAME);

    // noindex post (i=33) composed from the separate Yoast robots flag.
    expect(corpusMeta('post-0033')->robots)->toBe('noindex');

    // Multibyte description (i=25) preserved intact.
    expect(corpusMeta('post-0025')->description)->toContain('日本語');

    // Over-length title (i=20) truncated to the column limit.
    expect(mb_strlen(corpusMeta('post-0020')->title))->toBe(70);
});

it('writes nothing on a dry run but reports the full verification breakdown', function () {
    $manifest = corpusSeed('yoast', 120);

    $payload = corpusJson(['source' => 'yoast', '--model' => [CorpusPost::class], '--dry-run' => true]);

    expect(SEOMeta::query()->count())->toBe(0)
        ->and($payload['dry_run'])->toBeTrue()
        ->and($payload['verification']['matched'])->toBe($manifest['with_meta'])
        ->and($payload['verification']['created'])->toBe($manifest['with_meta']);
});

it('reports URL-only rows for posts whose slug matches no model', function () {
    // Create host models for only the first half of the meta slugs.
    $manifest = WordPressCorpus::seedPosts('wordpress', 'yoast', 120);
    $matched = array_slice($manifest['meta_slugs'], 0, 40);

    $rows = array_map(static fn (string $slug): array => ['title' => 'Host', 'slug' => $slug], $matched);
    DB::table('corpus_posts')->insert($rows);

    $payload = corpusJson(['source' => 'yoast', '--model' => [CorpusPost::class]]);

    expect($payload['verification']['matched'])->toBe(40)
        ->and($payload['verification']['url_only'])->toBe($manifest['with_meta'] - 40)
        ->and(SEOMeta::query()->count())->toBe(40)
        // Authors are surfaced for matched AND url-only pages alike.
        ->and($payload['verification']['unmapped']['author'])->toBe($manifest['with_meta']);
});

// ---------------------------------------------------------------------------
// fill-empty-only vs --overwrite
// ---------------------------------------------------------------------------

it('only fills empty fields by default, replacing them with --overwrite', function () {
    corpusSeed('yoast', 60);

    // Hand-edit one model's title in Rankbeam before importing.
    $post = CorpusPost::query()->where('slug', 'post-0001')->first();
    $post->saveSEO(['title' => 'Hand Edited Title'], 'en');

    corpusImport(['source' => 'yoast', '--model' => [CorpusPost::class]]);
    expect(corpusMeta('post-0001')->title)->toBe('Hand Edited Title');

    corpusImport(['source' => 'yoast', '--model' => [CorpusPost::class], '--overwrite' => true]);
    expect(corpusMeta('post-0001')->title)->toBe('Article 1 - '.WordPressCorpus::BLOGNAME);
});

// ---------------------------------------------------------------------------
// locale + morph map
// ---------------------------------------------------------------------------

it('writes the corpus into the requested locale', function () {
    corpusSeed('yoast', 60);

    corpusImport(['source' => 'yoast', '--model' => [CorpusPost::class], '--locale' => 'de']);

    $post = CorpusPost::query()->where('slug', 'post-0001')->first();

    expect(SEOMeta::query()->where('seoable_id', $post->id)->where('locale', 'de')->count())->toBe(1)
        ->and(SEOMeta::query()->where('seoable_id', $post->id)->where('locale', 'en')->count())->toBe(0);
});

it('resolves a --model given as a morph-map alias', function () {
    Relation::morphMap(['corpus-post' => CorpusPost::class]);

    corpusSeed('yoast', 60);

    // Pass the alias, not the FQCN — the importer must resolve it to the class.
    [$exit] = corpusImport(['source' => 'yoast', '--model' => ['corpus-post']]);

    $row = SEOMeta::query()->where('seoable_type', 'corpus-post')->first();

    expect($exit)->toBe(0)
        ->and($row)->not->toBeNull()
        ->and($row->title)->toBe('Article 1 - '.WordPressCorpus::BLOGNAME);
});

// ---------------------------------------------------------------------------
// Rank Math — same generator, different plugin surface
// ---------------------------------------------------------------------------

it('imports a Rank Math corpus with serialized robots and an accurate report', function () {
    $manifest = corpusSeed('rank-math', 300);

    $payload = corpusJson(['source' => 'rank-math', '--model' => [CorpusPost::class]]);
    $v = $payload['verification'];

    expect($v['matched'])->toBe($manifest['with_meta'])
        ->and($v['created'])->toBe($manifest['with_meta'])
        ->and($v['truncated']['title'])->toBe($manifest['truncated_titles'])
        ->and($v['unmapped_values']['author'])->toEqualCanonicalizing($manifest['authors']);

    // Serialized Rank Math robots → composed string, index/follow dropped.
    expect(corpusMeta('post-0033')->robots)->toBe('noindex');
});

it('imports Rank Math redirections from the corpus, exact rules only', function () {
    corpusSeed('rank-math', 30);

    Schema::connection('wordpress')->create('wp_rank_math_redirections', function ($t) {
        $t->bigIncrements('id');
        $t->text('sources')->nullable();
        $t->string('url_to')->nullable();
        $t->integer('header_code')->default(301);
        $t->string('status')->default('active');
    });

    DB::connection('wordpress')->table('wp_rank_math_redirections')->insert([
        ['sources' => serialize([['pattern' => 'old-a', 'comparison' => 'exact']]), 'url_to' => 'https://acme.test/new-a', 'header_code' => 301, 'status' => 'active'],
        ['sources' => serialize([['pattern' => '.*-promo', 'comparison' => 'regex']]), 'url_to' => 'https://acme.test/promo', 'header_code' => 301, 'status' => 'active'],
    ]);

    $redirects = sys_get_temp_dir().'/corpus-redirects-'.uniqid().'.csv';

    [, $output] = corpusImport(['source' => 'rank-math', '--model' => [CorpusPost::class], '--redirects-csv' => $redirects]);

    expect($output)->toContain('1 redirect candidate')
        ->and($output)->toContain('regex'); // non-exact reported, not emitted

    $rows = array_map(fn ($l) => str_getcsv($l, ',', '"', ''), array_filter(explode("\n", trim(file_get_contents($redirects)))));

    expect($rows[0])->toBe(['source_path', 'target_url', 'status_code', 'note'])
        ->and($rows[1])->toBe(['/old-a', 'https://acme.test/new-a', '301', 'Rank Math redirection']);

    @unlink($redirects);
});

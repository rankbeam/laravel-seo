<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Rankbeam\Seo\Models\SEOMeta;
use Rankbeam\Seo\Traits\HasSEO;

/**
 * The Laravel content model the WordPress posts were migrated into. It lives on
 * the default connection; the WordPress tables live on a separate `wordpress`
 * connection — exactly the real shape of a migration.
 */
class WpDbPost extends Model
{
    use HasSEO;

    protected $table = 'wp_db_posts';

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

    // The host app's content model (default connection).
    $this->app['db']->connection()->getSchemaBuilder()->create('wp_db_posts', function ($t) {
        $t->id();
        $t->string('title')->nullable();
        $t->string('slug')->nullable();
        $t->timestamps();
    });

    // A WordPress-shaped database on its own connection (fixture, not a dependency).
    $wp = Schema::connection('wordpress');

    $wp->create('wp_posts', function ($t) {
        $t->bigIncrements('ID');
        $t->text('post_title')->nullable();
        $t->string('post_name')->nullable();
        $t->string('post_status')->default('publish');
        $t->string('post_type')->default('post');
        $t->text('post_excerpt')->nullable();
        $t->text('post_content')->nullable();
    });

    $wp->create('wp_postmeta', function ($t) {
        $t->bigIncrements('meta_id');
        $t->unsignedBigInteger('post_id');
        $t->string('meta_key')->nullable();
        $t->longText('meta_value')->nullable();
    });

    $wp->create('wp_options', function ($t) {
        $t->bigIncrements('option_id');
        $t->string('option_name');
        $t->longText('option_value')->nullable();
    });

    DB::connection('wordpress')->table('wp_options')->insert([
        'option_name' => 'blogname',
        'option_value' => 'My Blog',
    ]);
});

afterEach(function () {
    $this->app['db']->connection()->getSchemaBuilder()->dropIfExists('wp_db_posts');
    DB::purge('wordpress');
});

/**
 * Seed a wp_posts row, returning its ID.
 */
function wpPost(array $attributes): int
{
    return (int) DB::connection('wordpress')->table('wp_posts')->insertGetId(array_merge([
        'post_title' => 'Untitled',
        'post_name' => 'untitled',
        'post_status' => 'publish',
        'post_type' => 'post',
        'post_excerpt' => null,
        'post_content' => null,
    ], $attributes));
}

/**
 * Seed wp_postmeta rows for a post from a [meta_key => meta_value] map.
 */
function wpMeta(int $postId, array $meta): void
{
    foreach ($meta as $key => $value) {
        DB::connection('wordpress')->table('wp_postmeta')->insert([
            'post_id' => $postId,
            'meta_key' => $key,
            'meta_value' => $value,
        ]);
    }
}

/**
 * @return array{0: int, 1: string}
 */
function wpDbImport(array $parameters): array
{
    $exit = Artisan::call('seo:import-from', array_merge(['--connection' => 'wordpress'], $parameters));

    return [$exit, Artisan::output()];
}

function wpDbMeta(Model $model): ?SEOMeta
{
    return SEOMeta::query()
        ->where('seoable_type', $model->getMorphClass())
        ->where('seoable_id', $model->getKey())
        ->where('locale', 'en')
        ->first();
}

// ---------------------------------------------------------------------------
// Availability
// ---------------------------------------------------------------------------

it('lists yoast and rank-math as available sources', function () {
    [, $output] = wpDbImport(['source' => 'unknown']);

    expect($output)->toContain('yoast')->toContain('rank-math');
});

it('errors when the WordPress tables are not on the connection', function () {
    [$exit, $output] = wpDbImport(['source' => 'yoast', '--table' => 'absent_']);

    expect($exit)->toBe(1)
        ->and($output)->toContain('absent_posts')
        ->and($output)->toContain('was not found');
});

// ---------------------------------------------------------------------------
// Yoast
// ---------------------------------------------------------------------------

it('imports Yoast metadata onto a matched model, including og/twitter overrides', function () {
    $post = WpDbPost::create(['title' => 'Hello', 'slug' => 'hello']);
    $id = wpPost(['post_title' => 'Hello', 'post_name' => 'hello']);

    wpMeta($id, [
        '_yoast_wpseo_title' => 'Plain SEO Title',
        '_yoast_wpseo_metadesc' => 'A Yoast meta description.',
        '_yoast_wpseo_canonical' => 'https://new.test/hello',
        '_yoast_wpseo_focuskw' => 'yoast keyword',
        '_yoast_wpseo_opengraph-title' => 'OG Title',
        '_yoast_wpseo_opengraph-description' => 'OG Description',
        '_yoast_wpseo_opengraph-image' => 'https://new.test/og.jpg',
        '_yoast_wpseo_twitter-title' => 'TW Title',
        '_yoast_wpseo_twitter-image' => 'https://new.test/tw.jpg',
        // structural / unmapped:
        '_yoast_wpseo_opengraph-image-id' => '123',           // structural — not reported
        '_yoast_wpseo_schema_article_type' => 'BlogPosting',  // genuinely unmapped — reported
    ]);

    [$exit, $output] = wpDbImport(['source' => 'yoast', '--model' => [WpDbPost::class]]);

    $meta = wpDbMeta($post);

    expect($exit)->toBe(0)
        ->and($meta->title)->toBe('Plain SEO Title')
        ->and($meta->description)->toBe('A Yoast meta description.')
        ->and($meta->canonical)->toBe('https://new.test/hello')
        ->and($meta->og_title)->toBe('OG Title')
        ->and($meta->og_description)->toBe('OG Description')
        ->and($meta->og_image)->toBe('https://new.test/og.jpg')
        ->and($meta->twitter_title)->toBe('TW Title')
        ->and($meta->twitter_image)->toBe('https://new.test/tw.jpg')
        ->and($meta->focus_keywords)->toBe([['keyword' => 'yoast keyword', 'is_primary' => true]])
        ->and($output)->toContain('schema_article_type')   // unmapped reported
        ->and($output)->not->toContain('opengraph-image-id'); // structural, silent
});

it('resolves Yoast template tokens and strips unknown ones', function () {
    $post = WpDbPost::create(['title' => 'Tokened', 'slug' => 'tokened']);
    $id = wpPost(['post_title' => 'My Post', 'post_name' => 'tokened']);

    wpMeta($id, [
        '_yoast_wpseo_title' => '%%title%% %%sep%% %%sitename%%',
        '_yoast_wpseo_metadesc' => 'Read %%title%% now %%page%%',
    ]);

    [, $output] = wpDbImport(['source' => 'yoast', '--model' => [WpDbPost::class]]);

    expect(wpDbMeta($post)->title)->toBe('My Post - My Blog')
        ->and(wpDbMeta($post)->description)->toBe('Read My Post now')
        ->and($output)->toContain('Resolved Yoast template tokens');
});

it('composes a Yoast robots string from the separate robots meta keys', function () {
    $post = WpDbPost::create(['title' => 'Robots', 'slug' => 'robots']);
    $id = wpPost(['post_name' => 'robots']);

    wpMeta($id, [
        '_yoast_wpseo_title' => 'Robots Page',
        '_yoast_wpseo_meta-robots-noindex' => '1',
        '_yoast_wpseo_meta-robots-nofollow' => '1',
        '_yoast_wpseo_meta-robots-adv' => 'noarchive,nosnippet',
    ]);

    wpDbImport(['source' => 'yoast', '--model' => [WpDbPost::class]]);

    expect(wpDbMeta($post)->robots)->toBe('noindex, nofollow, noarchive, nosnippet');
});

it('leaves robots null for an ordinary indexable Yoast page', function () {
    $post = WpDbPost::create(['title' => 'Index', 'slug' => 'index']);
    $id = wpPost(['post_name' => 'index']);
    wpMeta($id, ['_yoast_wpseo_title' => 'Indexable']);

    wpDbImport(['source' => 'yoast', '--model' => [WpDbPost::class]]);

    expect(wpDbMeta($post)->robots)->toBeNull();
});

it('skips a Yoast post that has no SEO metadata stored', function () {
    WpDbPost::create(['title' => 'Bare', 'slug' => 'bare']);
    wpPost(['post_name' => 'bare']); // no postmeta

    [, $output] = wpDbImport(['source' => 'yoast', '--model' => [WpDbPost::class]]);

    expect(SEOMeta::query()->count())->toBe(0)
        ->and($output)->toContain('no SEO metadata stored');
});

it('reports a Yoast post as url-only when no model matches its slug', function () {
    $id = wpPost(['post_name' => 'ghost']);
    wpMeta($id, ['_yoast_wpseo_title' => 'Ghost']);

    [, $output] = wpDbImport(['source' => 'yoast', '--model' => [WpDbPost::class]]);

    expect(SEOMeta::query()->count())->toBe(0)
        ->and($output)->toContain('url-only');
});

it('respects --post-type, ignoring other post types', function () {
    $post = WpDbPost::create(['title' => 'A Page', 'slug' => 'a-page']);
    $pageId = wpPost(['post_name' => 'a-page', 'post_type' => 'page']);
    wpMeta($pageId, ['_yoast_wpseo_title' => 'A Page']);

    $attachId = wpPost(['post_name' => 'a-page', 'post_type' => 'attachment']);
    wpMeta($attachId, ['_yoast_wpseo_title' => 'Attachment']);

    [, $output] = wpDbImport(['source' => 'yoast', '--model' => [WpDbPost::class], '--post-type' => ['page']]);

    expect(wpDbMeta($post)->title)->toBe('A Page')
        ->and($output)->toContain('1 source row(s) scanned');
});

it('is idempotent for the Yoast importer', function () {
    $post = WpDbPost::create(['title' => 'Repeat', 'slug' => 'repeat']);
    $id = wpPost(['post_name' => 'repeat']);
    wpMeta($id, ['_yoast_wpseo_title' => 'Stable', '_yoast_wpseo_focuskw' => 'kw']);

    wpDbImport(['source' => 'yoast', '--model' => [WpDbPost::class]]);
    [, $second] = wpDbImport(['source' => 'yoast', '--model' => [WpDbPost::class]]);

    expect(SEOMeta::query()->where('seoable_id', $post->id)->count())->toBe(1)
        ->and($second)->toContain('1 unchanged');
});

// ---------------------------------------------------------------------------
// Rank Math
// ---------------------------------------------------------------------------

it('imports Rank Math metadata including the twitter card and serialized robots', function () {
    $post = WpDbPost::create(['title' => 'RM', 'slug' => 'rm-post']);
    $id = wpPost(['post_title' => 'RM Post', 'post_name' => 'rm-post']);

    wpMeta($id, [
        'rank_math_title' => '%title% %sep% %sitename%',
        'rank_math_description' => 'Rank Math description.',
        'rank_math_canonical_url' => 'https://new.test/rm-post',
        'rank_math_focus_keyword' => 'primary, secondary',
        'rank_math_robots' => serialize(['noindex', 'nofollow', 'index', 'follow']),
        'rank_math_facebook_title' => 'RM OG',
        'rank_math_twitter_card_type' => 'summary_large_image',
        'rank_math_seo_score' => '88',         // structural
    ]);

    [, $output] = wpDbImport(['source' => 'rank-math', '--model' => [WpDbPost::class]]);

    $meta = wpDbMeta($post);

    expect($meta->title)->toBe('RM Post - My Blog')
        ->and($meta->description)->toBe('Rank Math description.')
        ->and($meta->canonical)->toBe('https://new.test/rm-post')
        ->and($meta->robots)->toBe('noindex, nofollow')
        ->and($meta->og_title)->toBe('RM OG')
        ->and($meta->twitter_card)->toBe('summary_large_image')
        ->and($meta->focus_keywords)->toBe([
            ['keyword' => 'primary', 'is_primary' => true],
            ['keyword' => 'secondary', 'is_primary' => false],
        ])
        ->and($output)->not->toContain('seo_score');
});

it('imports Rank Math redirections to a CSV, exact rules only', function () {
    WpDbPost::create(['title' => 'X', 'slug' => 'x']);
    $redirects = sys_get_temp_dir().'/rm-redirects-'.uniqid().'.csv';

    Schema::connection('wordpress')->create('wp_rank_math_redirections', function ($t) {
        $t->bigIncrements('id');
        $t->text('sources')->nullable();
        $t->string('url_to')->nullable();
        $t->integer('header_code')->default(301);
        $t->string('status')->default('active');
    });

    DB::connection('wordpress')->table('wp_rank_math_redirections')->insert([
        ['sources' => serialize([['pattern' => 'old-page', 'comparison' => 'exact']]), 'url_to' => 'https://new.test/new-page', 'header_code' => 301, 'status' => 'active'],
        ['sources' => serialize([['pattern' => 'gone', 'comparison' => 'exact']]), 'url_to' => 'https://new.test/', 'header_code' => 410, 'status' => 'active'],
        ['sources' => serialize([['pattern' => '.*-promo', 'comparison' => 'regex']]), 'url_to' => 'https://new.test/promo', 'header_code' => 301, 'status' => 'active'],
        ['sources' => serialize([['pattern' => 'inactive', 'comparison' => 'exact']]), 'url_to' => 'https://new.test/inactive', 'header_code' => 301, 'status' => 'inactive'],
    ]);

    [, $output] = wpDbImport(['source' => 'rank-math', '--model' => [WpDbPost::class], '--redirects-csv' => $redirects]);

    expect(file_exists($redirects))->toBeTrue()
        ->and($output)->toContain('2 redirect candidate')
        ->and($output)->toContain('regex')          // non-exact reported as skipped
        ->and($output)->toContain('only exact rules');

    $rows = array_map(fn ($l) => str_getcsv($l, ',', '"', ''), array_filter(explode("\n", trim(file_get_contents($redirects)))));

    expect($rows[1])->toBe(['/old-page', 'https://new.test/new-page', '301', 'Rank Math redirection'])
        ->and($rows[2])->toBe(['/gone', 'https://new.test/', '410', 'Rank Math redirection']);

    @unlink($redirects);

    Schema::connection('wordpress')->dropIfExists('wp_rank_math_redirections');
});

it('emits structured JSON for the Yoast importer', function () {
    $post = WpDbPost::create(['title' => 'Json', 'slug' => 'json']);
    $id = wpPost(['post_name' => 'json']);
    wpMeta($id, ['_yoast_wpseo_title' => 'Json Title']);

    [, $output] = wpDbImport(['source' => 'yoast', '--model' => [WpDbPost::class], '--json' => true]);

    $payload = json_decode($output, true);

    expect($payload['summary']['scanned'])->toBe(1)
        ->and($payload['summary']['created'])->toBe(1);
});

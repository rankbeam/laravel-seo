<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Rankbeam\Seo\Models\SEOMeta;
use Rankbeam\Seo\Traits\HasSEO;

/**
 * A content model the host app migrated WordPress posts into. It is keyed by a
 * `slug` route key, so a WordPress URL's last path segment matches it.
 */
class WpCsvPost extends Model
{
    use HasSEO;

    protected $table = 'wp_csv_posts';

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
    ]);

    $this->app['db']->connection()->getSchemaBuilder()->create('wp_csv_posts', function ($t) {
        $t->id();
        $t->string('title')->nullable();
        $t->string('slug')->nullable();
        $t->timestamps();
    });

    $this->wpTmp = sys_get_temp_dir().'/rankbeam-wp-csv-'.uniqid();
    mkdir($this->wpTmp, 0775, true);
});

afterEach(function () {
    $this->app['db']->connection()->getSchemaBuilder()->dropIfExists('wp_csv_posts');

    if (is_dir($this->wpTmp)) {
        foreach (glob($this->wpTmp.'/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->wpTmp);
    }
});

/**
 * Write a CSV file and return its path.
 */
function wpCsv(string $content): string
{
    $path = test()->wpTmp.'/export-'.uniqid().'.csv';
    file_put_contents($path, $content);

    return $path;
}

/**
 * @return array{0: int, 1: string}
 */
function wpImport(array $parameters): array
{
    $exit = Artisan::call('seo:import-from', $parameters);

    return [$exit, Artisan::output()];
}

function wpCsvMeta(Model $model, string $locale = 'en'): ?SEOMeta
{
    return SEOMeta::query()
        ->where('seoable_type', $model->getMorphClass())
        ->where('seoable_id', $model->getKey())
        ->where('locale', $locale)
        ->first();
}

it('is listed as an available import source', function () {
    [$exit, $output] = wpImport(['source' => 'nope']);

    expect($exit)->toBe(1)
        ->and($output)->toContain('wordpress-csv');
});

it('errors when no --file is given', function () {
    [$exit, $output] = wpImport(['source' => 'wordpress-csv']);

    expect($exit)->toBe(1)
        ->and($output)->toContain('no source file given');
});

it('maps a CSV row onto a matched model and stores the structured focus keyword', function () {
    $post = WpCsvPost::create(['title' => 'Hello', 'slug' => 'hello-world']);

    $csv = wpCsv(
        "url,title,description,canonical,robots,focus_keyword\n"
        ."https://old.test/blog/hello-world/,\"My SEO Title\",\"A meta description.\",https://new.test/blog/hello-world,\"index, follow\",\"laravel seo\"\n"
    );

    [$exit, $output] = wpImport(['source' => 'wordpress-csv', '--file' => $csv, '--model' => [WpCsvPost::class]]);

    $meta = wpCsvMeta($post);

    expect($exit)->toBe(0)
        ->and($meta)->not->toBeNull()
        ->and($meta->title)->toBe('My SEO Title')
        ->and($meta->description)->toBe('A meta description.')
        ->and($meta->canonical)->toBe('https://new.test/blog/hello-world')
        ->and($meta->robots)->toBe('index, follow')
        ->and($meta->focus_keywords)->toBe([['keyword' => 'laravel seo', 'is_primary' => true]])
        ->and($output)->toContain('1 created');
});

it('splits a comma-separated focus_keyword, first is primary', function () {
    $post = WpCsvPost::create(['title' => 'Multi', 'slug' => 'multi']);

    $csv = wpCsv(
        "url,focus_keyword\n"
        ."https://old.test/multi,\"primary kw, second kw\"\n"
    );

    wpImport(['source' => 'wordpress-csv', '--file' => $csv, '--model' => [WpCsvPost::class]]);

    expect(wpCsvMeta($post)->focus_keywords)->toBe([
        ['keyword' => 'primary kw', 'is_primary' => true],
        ['keyword' => 'second kw', 'is_primary' => false],
    ]);
});

it('skips malformed rows: missing url and wrong column count', function () {
    $post = WpCsvPost::create(['title' => 'Good', 'slug' => 'good']);

    $csv = wpCsv(
        "url,title\n"
        .",\"No URL here\"\n"                                  // missing url
        ."https://old.test/good,\"Good\",extra,columns\n"      // column count mismatch
        ."https://old.test/good,\"Good Title\"\n"              // valid
    );

    [, $output] = wpImport(['source' => 'wordpress-csv', '--file' => $csv, '--model' => [WpCsvPost::class]]);

    expect(wpCsvMeta($post)->title)->toBe('Good Title')
        ->and($output)->toContain('missing url')
        ->and($output)->toContain('column count')
        ->and($output)->toContain('3 source row(s) scanned')
        ->and($output)->toContain('1 created');
});

it('reports url-only rows that match no model and writes no seo_meta', function () {
    $csv = wpCsv(
        "url,title\n"
        ."https://old.test/no-such-post,\"Orphan\"\n"
    );

    [, $output] = wpImport(['source' => 'wordpress-csv', '--file' => $csv, '--model' => [WpCsvPost::class]]);

    expect(SEOMeta::query()->count())->toBe(0)
        ->and($output)->toContain('url-only')
        ->and($output)->toContain('0 created');
});

it('treats every row as url-only when no --model is given', function () {
    WpCsvPost::create(['title' => 'X', 'slug' => 'x']);

    $csv = wpCsv("url,title\nhttps://old.test/x,\"X\"\n");

    [, $output] = wpImport(['source' => 'wordpress-csv', '--file' => $csv]);

    expect(SEOMeta::query()->count())->toBe(0)
        ->and($output)->toContain('no --model given');
});

it('matches on a custom column via --match-by', function () {
    $post = WpCsvPost::create(['title' => 'Custom', 'slug' => 'custom-slug']);

    // The URL slug is the title, but we match on the `slug` column explicitly.
    $csv = wpCsv("url,title\nhttps://old.test/custom-slug,\"Matched By Slug\"\n");

    wpImport(['source' => 'wordpress-csv', '--file' => $csv, '--model' => [WpCsvPost::class], '--match-by' => 'slug']);

    expect(wpCsvMeta($post)->title)->toBe('Matched By Slug');
});

it('writes nothing on a dry run but reports what would happen', function () {
    WpCsvPost::create(['title' => 'Preview', 'slug' => 'preview']);

    $csv = wpCsv("url,title\nhttps://old.test/preview,\"Would import\"\n");

    [, $output] = wpImport(['source' => 'wordpress-csv', '--file' => $csv, '--model' => [WpCsvPost::class], '--dry-run' => true]);

    expect(SEOMeta::query()->count())->toBe(0)
        ->and($output)->toContain('DRY RUN')
        ->and($output)->toContain('1 created');
});

it('is idempotent — a second run is a no-op with no duplicate rows', function () {
    $post = WpCsvPost::create(['title' => 'Repeat', 'slug' => 'repeat']);

    $csv = wpCsv("url,title,focus_keyword\nhttps://old.test/repeat,\"Stable\",\"kw\"\n");

    wpImport(['source' => 'wordpress-csv', '--file' => $csv, '--model' => [WpCsvPost::class]]);
    [, $second] = wpImport(['source' => 'wordpress-csv', '--file' => $csv, '--model' => [WpCsvPost::class]]);

    expect(SEOMeta::query()->where('seoable_id', $post->id)->count())->toBe(1)
        ->and($second)->toContain('0 created')
        ->and($second)->toContain('1 unchanged');
});

it('truncates over-length values to fit the Core 3 columns', function () {
    $post = WpCsvPost::create(['title' => 'Long', 'slug' => 'long']);
    $longTitle = str_repeat('a', 120);

    $csv = wpCsv("url,title\nhttps://old.test/long,\"{$longTitle}\"\n");

    [, $output] = wpImport(['source' => 'wordpress-csv', '--file' => $csv, '--model' => [WpCsvPost::class]]);

    expect(mb_strlen(wpCsvMeta($post)->title))->toBe(70)
        ->and($output)->toContain('Truncated');
});

it('warns about unrecognised CSV columns', function () {
    WpCsvPost::create(['title' => 'X', 'slug' => 'x']);

    $csv = wpCsv("url,title,wp_post_id\nhttps://old.test/x,\"X\",42\n");

    [, $output] = wpImport(['source' => 'wordpress-csv', '--file' => $csv, '--model' => [WpCsvPost::class]]);

    expect($output)->toContain('unrecognised CSV column')
        ->and($output)->toContain('wp_post_id');
});

it('emits a redirects CSV for canonicals that point elsewhere, skipping self-canonicals', function () {
    $post = WpCsvPost::create(['title' => 'Canon', 'slug' => 'moved']);
    $redirects = test()->wpTmp.'/redirects.csv';

    $csv = wpCsv(
        "url,title,canonical\n"
        ."https://old.test/moved,\"Moved\",https://new.test/new-home\n"      // different path → redirect
        ."https://old.test/stay,\"Stay\",https://new.test/stay\n"            // same path → self-canonical, no redirect
    );

    [, $output] = wpImport([
        'source' => 'wordpress-csv',
        '--file' => $csv,
        '--model' => [WpCsvPost::class],
        '--redirects-csv' => $redirects,
    ]);

    expect($output)->toContain('1 redirect candidate')
        ->and(file_exists($redirects))->toBeTrue();

    $rows = array_map(fn ($line) => str_getcsv($line, ',', '"', ''), array_filter(explode("\n", trim(file_get_contents($redirects)))));

    expect($rows[0])->toBe(['source_path', 'target_url', 'status_code', 'note'])
        ->and($rows[1][0])->toBe('/moved')
        ->and($rows[1][1])->toBe('https://new.test/new-home')
        ->and($rows[1][2])->toBe('301');
});

it('does not write the redirects file on a dry run', function () {
    WpCsvPost::create(['title' => 'Canon', 'slug' => 'moved']);
    $redirects = test()->wpTmp.'/redirects-dry.csv';

    $csv = wpCsv("url,title,canonical\nhttps://old.test/moved,\"Moved\",https://new.test/elsewhere\n");

    [, $output] = wpImport([
        'source' => 'wordpress-csv',
        '--file' => $csv,
        '--model' => [WpCsvPost::class],
        '--redirects-csv' => $redirects,
        '--dry-run' => true,
    ]);

    expect(file_exists($redirects))->toBeFalse()
        ->and($output)->toContain('1 redirect candidate')
        ->and($output)->toContain('would be written to');
});

it('emits structured JSON with --json including the redirects block', function () {
    $post = WpCsvPost::create(['title' => 'Json', 'slug' => 'json']);
    $redirects = test()->wpTmp.'/redirects-json.csv';

    $csv = wpCsv("url,title,canonical\nhttps://old.test/json,\"Json\",https://new.test/json-new\n");

    [, $output] = wpImport([
        'source' => 'wordpress-csv',
        '--file' => $csv,
        '--model' => [WpCsvPost::class],
        '--redirects-csv' => $redirects,
        '--json' => true,
    ]);

    $payload = json_decode($output, true);

    expect($payload['summary']['scanned'])->toBe(1)
        ->and($payload['summary']['created'])->toBe(1)
        ->and($payload['redirects']['emitted'])->toBe(1)
        ->and($payload['redirects']['file'])->toBe($redirects);
});

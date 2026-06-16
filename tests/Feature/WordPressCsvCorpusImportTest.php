<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Rankbeam\Seo\Models\SEOMeta;
use Rankbeam\Seo\Traits\HasSEO;

/**
 * Drives the `wordpress-csv` importer against the committed, anonymized export
 * excerpt (`tests/Fixtures/wordpress/anonymized-export.csv`) — a realistic
 * sample covering self/cross canonicals, an over-length title, multibyte text,
 * multi-keyword rows, blank fields, a malformed missing-url row, a wrong-column
 * -count row, a blank line, and a URL-only page with no model.
 */

/** The host-app content model the CSV rows are migrated into. */
class CsvCorpusPost extends Model
{
    use HasSEO;

    protected $table = 'csv_corpus_posts';

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

    $this->app['db']->connection()->getSchemaBuilder()->create('csv_corpus_posts', function ($t) {
        $t->id();
        $t->string('title')->nullable();
        $t->string('slug')->nullable()->index();
        $t->timestamps();
    });

    // Host models for post-0001 … post-0010 (old-promo deliberately has none).
    foreach (range(1, 10) as $n) {
        CsvCorpusPost::create(['title' => 'Host '.$n, 'slug' => sprintf('post-%04d', $n)]);
    }

    $this->corpusCsv = __DIR__.'/../Fixtures/wordpress/anonymized-export.csv';
});

afterEach(function () {
    $this->app['db']->connection()->getSchemaBuilder()->dropIfExists('csv_corpus_posts');
});

function csvCorpusImport(array $parameters): array
{
    $exit = Artisan::call('seo:import-from', $parameters);

    return [$exit, Artisan::output()];
}

function csvCorpusMeta(string $slug): ?SEOMeta
{
    $model = CsvCorpusPost::query()->where('slug', $slug)->first();

    return $model?->seoMeta;
}

it('imports the anonymized CSV excerpt with an accurate verification report', function () {
    [, $output] = csvCorpusImport([
        'source' => 'wordpress-csv',
        '--file' => $this->corpusCsv,
        '--model' => [CsvCorpusPost::class],
        '--json' => true,
    ]);

    $payload = json_decode($output, true);
    $v = $payload['verification'];

    expect($payload['summary']['scanned'])->toBe(11)   // blank line not scanned
        ->and($v['matched'])->toBe(8)
        ->and($v['created'])->toBe(8)
        ->and($v['url_only'])->toBe(1)                  // old-promo matches no model
        ->and($v['truncated']['title'])->toBe(1);       // the long widgets headline

    // The malformed rows are skipped with stable reasons.
    $reasons = implode(' ', array_column($payload['skipped'], 'reason'));
    expect($reasons)->toContain('missing url')
        ->toContain('column count');

    // Only matched, non-empty rows became seo_meta.
    expect(SEOMeta::query()->count())->toBe(8);
});

it('preserves multibyte text and splits multi-keyword rows', function () {
    csvCorpusImport([
        'source' => 'wordpress-csv',
        '--file' => $this->corpusCsv,
        '--model' => [CsvCorpusPost::class],
    ]);

    expect(csvCorpusMeta('post-0004')->description)->toContain('日本語')
        ->and(csvCorpusMeta('post-0004')->description)->toContain('ñ');

    expect(csvCorpusMeta('post-0005')->focus_keywords)->toBe([
        ['keyword' => 'acme', 'is_primary' => true],
        ['keyword' => 'roundup', 'is_primary' => false],
        ['keyword' => 'best of', 'is_primary' => false],
    ]);

    // The over-length title was trimmed to the column limit.
    expect(mb_strlen(csvCorpusMeta('post-0003')->title))->toBe(70);
});

it('emits redirect candidates only for cross-path canonicals', function () {
    $redirects = sys_get_temp_dir().'/csv-corpus-redirects-'.uniqid().'.csv';

    [, $output] = csvCorpusImport([
        'source' => 'wordpress-csv',
        '--file' => $this->corpusCsv,
        '--model' => [CsvCorpusPost::class],
        '--redirects-csv' => $redirects,
    ]);

    // /pricing, /promo (from the url-only old-promo), /status — three crosses.
    expect($output)->toContain('3 redirect candidate')
        ->and(file_exists($redirects))->toBeTrue();

    $rows = array_map(
        fn ($l) => str_getcsv($l, ',', '"', ''),
        array_filter(explode("\n", trim(file_get_contents($redirects))))
    );

    expect($rows[0])->toBe(['source_path', 'target_url', 'status_code', 'note']);

    $sources = array_map(static fn ($r) => $r[0], array_slice($rows, 1));
    expect($sources)->toContain('/post-0002')
        ->toContain('/old-promo')
        ->toContain('/post-0010');

    @unlink($redirects);
});

it('is idempotent and writes nothing on a dry run', function () {
    $args = [
        'source' => 'wordpress-csv',
        '--file' => $this->corpusCsv,
        '--model' => [CsvCorpusPost::class],
    ];

    // Dry run first: inert.
    csvCorpusImport(array_merge($args, ['--dry-run' => true]));
    expect(SEOMeta::query()->count())->toBe(0);

    // Real run, then a second run that changes nothing.
    csvCorpusImport($args);
    [, $second] = csvCorpusImport(array_merge($args, ['--json' => true]));
    $payload = json_decode($second, true);

    expect($payload['verification']['created'])->toBe(0)
        ->and($payload['verification']['unchanged'])->toBe(8)
        ->and(SEOMeta::query()->count())->toBe(8);
});

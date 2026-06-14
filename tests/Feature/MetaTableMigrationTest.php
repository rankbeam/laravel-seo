<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Locks the Core 3 `seo_meta` schema contract:
 * - a fresh install lands the correct v3 columns (no dead analyzer
 *   columns/indexes), proving the immutable create migration + the new
 *   cleanup migration net to the right schema;
 * - the cleanup migration upgrades a released-v2 schema by dropping ONLY the
 *   dead columns/indexes while `focus_keywords` + existing data survive;
 * - it is idempotent and safe on a partially-migrated schema.
 */
$deadColumns = ['seo_score', 'analysis_report', 'analyzed_at', 'content_snapshot', 'content_hash', 'snapshot_at'];
$deadIndexes = ['seo_meta_score_index', 'seo_meta_analyzed_index'];
$keptColumns = ['title', 'description', 'canonical', 'robots', 'og_title', 'og_image', 'twitter_card', 'focus_keywords', 'schema_jsonld', 'schema_type', 'locale'];

// require (not require_once) so the idempotency test can run up() twice.
$runCleanup = function (): void {
    $migration = require __DIR__.'/../../database/migrations/2026_06_14_000001_drop_dead_analyzer_columns_from_seo_meta.php';
    $migration->up();
};

$runNullableSocialCards = function (): void {
    $migration = require __DIR__.'/../../database/migrations/2026_06_14_000002_make_seo_meta_og_type_nullable.php';
    $migration->up();
};

// Rebuild a released-v2 seo_meta, optionally including each dead column/index,
// to simulate a fresh-from-v2 schema or a partially-migrated one.
$buildV2Schema = function (array $withColumns, bool $withIndexes): void {
    Schema::dropIfExists('seo_meta');
    Schema::create('seo_meta', function ($table) use ($withColumns, $withIndexes) {
        $table->id();
        $table->morphs('seoable');
        $table->string('locale', 10)->default('en');
        $table->string('title', 70)->nullable();
        $table->string('description', 160)->nullable();
        $table->string('canonical')->nullable();
        $table->string('robots', 50)->nullable();
        $table->string('og_title', 70)->nullable();
        $table->string('og_description', 200)->nullable();
        $table->string('og_image')->nullable();
        $table->string('og_type', 30)->default('website');
        $table->string('twitter_title', 70)->nullable();
        $table->string('twitter_description', 200)->nullable();
        $table->string('twitter_image')->nullable();
        $table->string('twitter_card', 30)->default('summary_large_image');
        $table->json('focus_keywords')->nullable();
        $table->json('schema_jsonld')->nullable();
        $table->string('schema_type', 50)->nullable();

        if (in_array('seo_score', $withColumns, true)) {
            $table->unsignedTinyInteger('seo_score')->nullable();
        }
        if (in_array('analysis_report', $withColumns, true)) {
            $table->json('analysis_report')->nullable();
        }
        if (in_array('analyzed_at', $withColumns, true)) {
            $table->timestamp('analyzed_at')->nullable();
        }
        if (in_array('content_snapshot', $withColumns, true)) {
            $table->longText('content_snapshot')->nullable();
        }
        if (in_array('content_hash', $withColumns, true)) {
            $table->string('content_hash', 64)->nullable();
        }
        if (in_array('snapshot_at', $withColumns, true)) {
            $table->timestamp('snapshot_at')->nullable();
        }

        $table->timestamps();
        $table->unique(['seoable_type', 'seoable_id', 'locale'], 'seo_meta_unique');

        if ($withIndexes && in_array('seo_score', $withColumns, true)) {
            $table->index('seo_score', 'seo_meta_score_index');
        }
        if ($withIndexes && in_array('analyzed_at', $withColumns, true)) {
            $table->index('analyzed_at', 'seo_meta_analyzed_index');
        }
    });
};

it('lands the correct v3 schema on a fresh install', function () use ($deadColumns, $deadIndexes, $keptColumns) {
    // RefreshDatabase has already run the package migrations (create + cleanup).
    expect(Schema::hasTable('seo_meta'))->toBeTrue();

    foreach ($keptColumns as $column) {
        expect(Schema::hasColumn('seo_meta', $column))->toBeTrue("v3 seo_meta must keep [{$column}]");
    }

    foreach ($deadColumns as $column) {
        expect(Schema::hasColumn('seo_meta', $column))->toBeFalse("v3 seo_meta must not ship [{$column}]");
    }

    foreach ($deadIndexes as $index) {
        expect(Schema::hasIndex('seo_meta', $index))->toBeFalse("v3 seo_meta must not ship index [{$index}]");
    }
});

it('upgrades a released-v2 schema, dropping only the dead surface and keeping focus_keywords + data', function () use ($deadColumns, $deadIndexes, $buildV2Schema, $runCleanup) {
    $buildV2Schema($deadColumns, true);

    foreach ($deadColumns as $column) {
        expect(Schema::hasColumn('seo_meta', $column))->toBeTrue("v2 fixture should start with [{$column}]");
    }
    foreach ($deadIndexes as $index) {
        expect(Schema::hasIndex('seo_meta', $index))->toBeTrue("v2 fixture should start with index [{$index}]");
    }

    // A real row carrying focus_keywords + metadata + a legacy score value.
    DB::table('seo_meta')->insert([
        'seoable_type' => 'App\\Models\\Post',
        'seoable_id' => 1,
        'locale' => 'en',
        'title' => 'Kept title',
        'focus_keywords' => json_encode([['keyword' => 'laravel seo', 'is_primary' => true]]),
        'seo_score' => 42,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $runCleanup();

    foreach ($deadColumns as $column) {
        expect(Schema::hasColumn('seo_meta', $column))->toBeFalse("[{$column}] should be dropped");
    }
    foreach ($deadIndexes as $index) {
        expect(Schema::hasIndex('seo_meta', $index))->toBeFalse("index [{$index}] should be dropped");
    }

    // focus_keywords + the row's data survive.
    expect(Schema::hasColumn('seo_meta', 'focus_keywords'))->toBeTrue();

    $row = DB::table('seo_meta')->where('seoable_id', 1)->first();
    expect($row->title)->toBe('Kept title')
        ->and(json_decode($row->focus_keywords, true))->toBe([['keyword' => 'laravel seo', 'is_primary' => true]]);
});

it('is idempotent and safe on a partially-migrated schema', function () use ($deadColumns, $buildV2Schema, $runCleanup) {
    // Only some dead columns present, and NO dead indexes at all.
    $buildV2Schema(['seo_score', 'content_hash'], false);

    expect(Schema::hasColumn('seo_meta', 'seo_score'))->toBeTrue()
        ->and(Schema::hasColumn('seo_meta', 'content_hash'))->toBeTrue()
        ->and(Schema::hasColumn('seo_meta', 'analyzed_at'))->toBeFalse();

    // First run drops what is present without erroring on the absent ones.
    $runCleanup();

    foreach ($deadColumns as $column) {
        expect(Schema::hasColumn('seo_meta', $column))->toBeFalse();
    }

    // Second run is a no-op - must not throw.
    $runCleanup();

    expect(Schema::hasColumn('seo_meta', 'focus_keywords'))->toBeTrue();
});

it('warns (but does not abort) when a dropped column holds data', function () use ($buildV2Schema, $runCleanup) {
    $buildV2Schema(['seo_score', 'analysis_report'], true);

    DB::table('seo_meta')->insert([
        'seoable_type' => 'App\\Models\\Post',
        'seoable_id' => 1,
        'locale' => 'en',
        'title' => 'Has a legacy score',
        'seo_score' => 88,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Log::spy();

    // Must still complete the drop (non-fatal) ...
    $runCleanup();

    expect(Schema::hasColumn('seo_meta', 'seo_score'))->toBeFalse();

    // ... and must have surfaced the data loss with the column + count.
    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => str_contains($message, 'seo_score (1 row(s))'))
        ->once();
});

it('does not warn when the dead columns are all null', function () use ($buildV2Schema, $runCleanup) {
    $buildV2Schema(['seo_score', 'analysis_report'], true);

    DB::table('seo_meta')->insert([
        'seoable_type' => 'App\\Models\\Post',
        'seoable_id' => 1,
        'locale' => 'en',
        'title' => 'No legacy score',
        'seo_score' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Log::spy();

    $runCleanup();

    Log::shouldNotHaveReceived('warning');
});

it('makes social card columns nullable without rewriting legacy values', function () use ($buildV2Schema, $runNullableSocialCards) {
    $buildV2Schema([], false);

    DB::table('seo_meta')->insert([
        'seoable_type' => 'App\\Models\\Post',
        'seoable_id' => 1,
        'locale' => 'en',
        'title' => 'Legacy row',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $runNullableSocialCards();

    DB::table('seo_meta')->insert([
        'seoable_type' => 'App\\Models\\Post',
        'seoable_id' => 2,
        'locale' => 'en',
        'title' => 'New row',
        'og_type' => null,
        'twitter_card' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $legacy = DB::table('seo_meta')->where('seoable_id', 1)->first();
    $new = DB::table('seo_meta')->where('seoable_id', 2)->first();

    expect($legacy->og_type)->toBe('website')
        ->and($legacy->twitter_card)->toBe('summary_large_image')
        ->and($new->og_type)->toBeNull()
        ->and($new->twitter_card)->toBeNull();
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Rankbeam\Seo\Data\SEOData;
use Rankbeam\Seo\Models\SEOMeta;

it('upgrades existing metadata and keeps ordinary saves working after an additive rollback', function () {
    $migration = require __DIR__.'/../../database/migrations/2026_09_11_000001_add_ai_provenance_to_seo_meta_table.php';
    $migration->down();
    $meta = SEOMeta::create(['seoable_type' => 'App\\Models\\Article', 'seoable_id' => 42, 'title' => 'Existing title']);
    expect(SEOData::fromMeta($meta->fresh())->aiProvenance)->toBe([]);
    $migration->up();
    expect(Schema::hasColumn('seo_meta', 'ai_provenance'))->toBeTrue()
        ->and($meta->fresh()->title)->toBe('Existing title')
        ->and($meta->fresh()->ai_provenance)->toBeNull();
    $migration->down();
    $meta->fresh()->update(['title' => 'Ordinary edit']);
    expect($meta->fresh()->title)->toBe('Ordinary edit');
    $migration->up();
});

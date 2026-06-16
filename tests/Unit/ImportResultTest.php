<?php

declare(strict_types=1);

use Rankbeam\Seo\Importing\ImportResult;

/**
 * Unit coverage for the verification-report accounting added in T9: the
 * first-class url-only counter, distinct unmapped-value capture (author), and
 * the assembled {@see ImportResult::verification()} structure.
 */
it('counts matched rows as created + updated + unchanged', function () {
    $result = new ImportResult;
    $result->recordStatus('created');
    $result->recordStatus('updated');
    $result->recordStatus('unchanged');
    $result->recordStatus('created');

    expect($result->matched())->toBe(4)
        ->and($result->writes())->toBe(3); // unchanged is not a write
});

it('records url-only rows as both a counter and a skip', function () {
    $result = new ImportResult;
    $result->urlOnly('/a', 'App\\Post', 'url-only (no model matched)');

    expect($result->urlOnly)->toBe(1)
        ->and($result->skippedCount())->toBe(1)
        ->and($result->skipped[0]['reason'])->toContain('url-only');
});

it('captures distinct unmapped values, ignoring blanks and duplicates', function () {
    $result = new ImportResult;
    $result->unmapped('author', 'Jane Doe');
    $result->unmapped('author', 'Jane Doe');   // duplicate — not stored twice
    $result->unmapped('author', '  John Roe '); // trimmed
    $result->unmapped('author', '   ');         // blank value — counts, no value
    $result->unmapped('schema_type');           // count only, no value

    expect($result->unmapped['author'])->toBe(4)
        ->and($result->unmappedValues['author'])->toBe(['Jane Doe', 'John Roe'])
        ->and($result->unmapped['schema_type'])->toBe(1)
        ->and($result->unmappedValues)->not->toHaveKey('schema_type');
});

it('bounds captured values at MAX_UNMAPPED_VALUES', function () {
    $result = new ImportResult;

    for ($i = 0; $i < ImportResult::MAX_UNMAPPED_VALUES + 50; $i++) {
        $result->unmapped('author', 'Author '.$i);
    }

    expect($result->unmapped['author'])->toBe(ImportResult::MAX_UNMAPPED_VALUES + 50)
        ->and($result->unmappedValues['author'])->toHaveCount(ImportResult::MAX_UNMAPPED_VALUES);
});

it('assembles a verification structure and exposes it in toArray', function () {
    $result = new ImportResult(dryRun: true);
    $result->recordScanned();
    $result->recordStatus('created');
    $result->recordStatus('unchanged');
    $result->urlOnly('/x', null, 'url-only');
    $result->truncated('title');
    $result->unmapped('author', 'Ada Lovelace');

    $v = $result->verification();

    expect($v)->toMatchArray([
        'matched' => 2,
        'created' => 1,
        'updated' => 0,
        'unchanged' => 1,
        'url_only' => 1,
        'skipped' => 1,
    ]);
    expect($v['truncated'])->toBe(['title' => 1])
        ->and($v['unmapped'])->toBe(['author' => 1])
        ->and($v['unmapped_values'])->toBe(['author' => ['Ada Lovelace']]);

    $array = $result->toArray();
    expect($array['verification'])->toBe($v)
        ->and($array['unmapped_values'])->toBe(['author' => ['Ada Lovelace']]);
});

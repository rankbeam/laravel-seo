<?php

declare(strict_types=1);

use Rankbeam\Seo\Auditing\AuditIssue;
use Rankbeam\Seo\Auditing\MetadataIssues;
use Rankbeam\Seo\Services\SEOWarningEvaluator;

it('pins the metadata code contract shared with the Pro registry', function () {
    // These are EXACTLY the EXEC_METADATA codes in
    // Rankbeam\Seo\Pro\Scanning\IssueRegistry. The two lists MUST stay
    // identical so the free audit, the Pro scan, Filament, and exports agree.
    expect(MetadataIssues::metadataCodes())->toBe([
        'missing_title',
        'missing_description',
        'missing_og_image',
        'missing_focus_keyword',
        'duplicate_title',
        'duplicate_description',
        'title_too_long',
        'title_too_short',
        'description_too_long',
        'description_too_short',
        'robots_conflict_indexing',
        'robots_conflict_following',
        'noindex_warning',
        'invalid_canonical',
        'cross_domain_canonical',
        'shared_canonical',
        'insecure_canonical',
    ]);
});

it('gives every code a valid severity and field', function () {
    $severities = [
        AuditIssue::SEVERITY_CRITICAL,
        AuditIssue::SEVERITY_WARNING,
        AuditIssue::SEVERITY_NOTICE,
    ];

    foreach (MetadataIssues::definitions() as $code => $definition) {
        expect($definition['severity'])->toBeIn($severities, "severity for {$code}")
            ->and($definition['field'])->toBeString();
    }
});

it('stamps severity and field from the catalogue when building an issue', function () {
    $issue = MetadataIssues::make('missing_title', 'No title.');

    expect($issue)->toBeInstanceOf(AuditIssue::class)
        ->and($issue->code)->toBe('missing_title')
        ->and($issue->severity)->toBe(AuditIssue::SEVERITY_CRITICAL)
        ->and($issue->field)->toBe('title')
        ->and($issue->message)->toBe('No title.');
});

it('rejects an undefined code', function () {
    MetadataIssues::make('not_a_real_code', 'x');
})->throws(InvalidArgumentException::class);

it('reuses the editor length thresholds and declares the audit floors', function () {
    // Upper bounds are the core editor constants (so the audit never
    // contradicts the editor's counters); lower bounds are the audit floors,
    // identical to the Pro IssueRegistry's.
    expect(SEOWarningEvaluator::TITLE_MAX_LENGTH)->toBe(60)
        ->and(SEOWarningEvaluator::DESCRIPTION_MAX_LENGTH)->toBe(160)
        ->and(MetadataIssues::TITLE_MIN_LENGTH)->toBe(30)
        ->and(MetadataIssues::DESCRIPTION_MIN_LENGTH)->toBe(70);
});

it('omits an empty context from the array form', function () {
    $issue = MetadataIssues::make('missing_title', 'No title.');
    expect($issue->toArray())->not->toHaveKey('context');

    $withContext = MetadataIssues::make('title_too_long', 'Long.', ['length' => 80, 'max' => 60]);
    expect($withContext->toArray()['context'])->toBe(['length' => 80, 'max' => 60]);
});

<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| rankbeam/laravel-seo — English strings
|--------------------------------------------------------------------------
|
| Every user-facing message the core package emits. Issue and warning CODES
| (the array keys) are part of the package contract and never change; only
| these messages are translated. Publish with:
|
|     php artisan vendor:publish --tag=seo-lang
|
| Placeholders (:length, :max, …) are filled by the package. See
| TRANSLATING.md in the repository root for the glossary and the rules.
|
*/

return [

    // Findings emitted by `php artisan seo:audit` (MetadataAuditor). Keyed by
    // the issue code, which is what the Pro scan, the MCP tools and CI reports
    // all use to identify a finding.
    'audit' => [
        'missing_title' => 'Page is missing a title tag.',
        'missing_description' => 'Page is missing a meta description.',
        'missing_og_image' => 'Page is missing an Open Graph image.',
        'missing_focus_keyword' => 'No focus keyword set for this page.',
        'title_too_long' => 'Title is :length characters (recommended max :max); it may be truncated on Google.',
        'title_too_short' => 'Title is only :length characters (recommended min :min).',
        'description_too_long' => 'Description is :length characters (recommended max :max); it may be truncated.',
        'description_too_short' => 'Description is only :length characters (recommended min :min).',
        'duplicate_title' => 'Title ":title" is used on :count other page(s).',
        'duplicate_description' => 'Meta description is duplicated on :count other page(s).',
        'robots_conflict_indexing' => 'Robots meta has conflicting index/noindex directives.',
        'robots_conflict_following' => 'Robots meta has conflicting follow/nofollow directives.',
        'noindex_warning' => 'Page has noindex but appears to be important content.',
        'invalid_canonical' => 'Canonical URL is not a valid URL format.',
        'cross_domain_canonical' => 'Canonical URL points to a different domain.',
        'insecure_canonical' => 'Canonical URL uses http:// on an https site.',
        'shared_canonical' => ':count pages share the same canonical URL.',
        'aeo_missing_author' => 'An article on this page has no author in its structured data. Declaring an author makes the article\'s authorship and provenance explicit in the schema.',
        'aeo_article_missing_date' => 'An article on this page has no publish date in its structured data. A datePublished or dateModified makes the article\'s timeline explicit in the schema.',
        'hreflang_invalid_code' => 'hreflang alternates carry a code search engines will ignore (:codes). Use language[-Script][-REGION], e.g. en, pt-BR, zh-Hant.',
        'hreflang_duplicate_code' => 'hreflang alternates list the same code more than once (:codes).',
        'hreflang_missing_self' => 'hreflang alternates do not include this page itself. Google requires each language version to list its own URL.',
    ],

    // Live editor warnings (SEOWarningEvaluator) — the character counters and
    // image hints shown under the fields in the Filament editor.
    'warnings' => [
        'title_too_long' => 'The title is :length characters long (recommended max: :max). It may be truncated on Google.',
        'title_is_fallback' => 'No SEO title set — the content title will be used as a fallback.',
        'description_too_long' => 'The description is :length characters long (recommended max: :max). It may be truncated.',
        'description_is_fallback' => 'No SEO description set — one will be generated automatically from the content.',
        'no_image' => 'No image available for social previews. Add an SEO image or a content image.',
        'image_is_fallback' => 'No specific SEO image — the content image will be used as a fallback.',
        'image_too_small' => 'Image too small (:widthx:height). Social platforms require at least :min_widthx:min_height px.',
        'image_not_ideal' => 'Image is :widthx:height px. The ideal size for social platforms is :ideal_widthx:ideal_height px.',
    ],

    // Severity / status words, reused by reports and UIs that render findings.
    'status' => [
        'pass' => 'Pass',
        'warn' => 'Warn',
        'fail' => 'Fail',
        'skipped' => 'Skipped',
    ],

    'severity' => [
        'critical' => 'Critical',
        'warning' => 'Warning',
        'notice' => 'Notice',
    ],
];

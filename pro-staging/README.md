# pro-staging — NOT part of the shipped package

This directory holds everything carved out of the open-source core during the
Phase-1 core carve (2026-06). **Nothing in here is autoloaded, published, or
tested by the core suite.** It is a staging area for the future
`fibonoir/laravel-seo-pro` package and will be moved to that repository.

## Contents

| Area | What's here |
|---|---|
| `src/Rules/` | All 32 analyzer rule classes + `AbstractRule` |
| `src/Services/Analyzer/` | `ContentAnalyzer` |
| `src/Services/Scanner/` | `PageScanner`, `SitewideScanner`, `BrokenLinkChecker` |
| `src/Services/Analytics/` | GA4 integration (`GA4Service`, `AnalyticsCache`, `Period`) |
| `src/Services/InternalLinks/` | TF-IDF link index/suggester |
| `src/Services/` | `CacheManager`, `RedirectManager` |
| `src/Http/Middleware/` | `RedirectMiddleware`, `Log404Middleware` |
| `src/Jobs/` | All queued jobs except `GenerateSitemapJob` (stays in core) |
| `src/Models/` | `SEORedirect`, `SEO404Log`, `SEOScanRun`, `SEOScanIssue`, `SEOAnalyticsCache`, `SEOInternalLinksIndex` |
| `src/Support/` | `Stemmer` (wamania), `Tokenizer`, `StopWords`, `TransitionWords`, `ReadabilityCalculator`, `PackageChecker` |
| `src/Data/` | `AnalysisContext`, `AnalysisReport`, `RuleResult`, `ReadabilityResult` |
| `src/Contracts/` | `RuleInterface`, `Analyzable` |
| `src/Console/Commands/` | `seo:install`, `seo:scan`, `seo:health`, `seo:cache`, `seo:sync-analytics` |
| `database/migrations/` | Migrations 000003–000009 (redirects, 404 logs, scan runs/issues, analytics cache, links index, performance indexes) |
| `stubs/`, `resources-js/` | Filament 3 / Livewire stubs, Vue/React components (stub-publishing approach is retired; the Filament UI will be a real package) |
| `tests/` | The test files for everything above, plus the original `Pest.php` analyzer helpers and the original `HasSEOTraitTest` (`.orig`) |

## Known caveats to resolve when building Pro

1. **Migration 000009** (`add_performance_indexes`) also adds indexes on the
   *core* tables `seo_meta` and `seo_defaults`. When Pro is split into its own
   repo, those index blocks should move back to a core migration. (The
   `seo_defaults` unique constraint is already covered by core migration
   000002, so nothing is functionally broken in core.)
2. **Dependencies removed from core composer.json** that Pro needs:
   `wamania/php-stemmer ^3.0` (analyzer), and suggests
   `spatie/browsershot` (SPA scanning), `google/apiclient` (GA4).
3. The core `HasSEO` trait no longer dispatches `AnalyzeContentJob` /
   updates the link index on save (and `analyzeForSEO()` /
   `dispatchAnalysis()` were removed). Pro should re-add this via its own
   observer or a trait extension — see `tests/Feature/HasSEOTraitTest.php.orig`
   for the original expected behavior (configs `seo.features.auto_analyze`,
   `seo.features.internal_links_index`).
4. The original config sections removed from `config/seo.php` (analyzer,
   scanner, analytics, redirects, 404_monitor, stack) live in git history:
   `git show 408fd6f^:config/seo.php` — Pro should own them in its own config.
5. **Opening backlog**: the 21 failing tests recorded in the Phase-0 report
   (ScanPageJob ×6, InvalidHeadElementsRule ×8, middleware ×2, job-dispatch
   mocks, rules ×5) plus the security items: open-redirect validation,
   SSRF guard for the link checker, 404-log IP hashing default + row caps,
   redirect hit-count batching.

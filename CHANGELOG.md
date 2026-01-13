# Changelog

All notable changes to `laravel-seo` will be documented in this file.

## [Unreleased]

### Added

-   Initial package structure
-   SEOServiceProvider with full configuration
-   SEO Facade with resolve(), forRoute(), render(), toArray() methods
-   HasSEO trait for Eloquent models
-   SEOData value object with merge support
-   SEOResolver with 5-layer precedence chain
-   TagRenderer for HTML and array output
-   Content Analyzer foundation
-   Stemmer, Tokenizer, StopWords, TransitionWords support classes
-   ReadabilityCalculator (Flesch-Kincaid + Gulpease)
-   RedirectMiddleware for URL redirects
-   Log404Middleware for 404 monitoring
-   Interactive InstallCommand
-   Database migrations for 8 tables
-   Blade directives: @seo, @seoForRoute, @seoTitle, @seoMeta, @seoSchema

### Coming Soon

-   32 SEO analysis rules
-   Sitewide scanner
-   Sitemap generation (with spatie/laravel-sitemap)
-   Schema markup builders
-   GA4 analytics integration
-   Internal linking suggestions
-   Filament/Livewire/Vue/React frontend components

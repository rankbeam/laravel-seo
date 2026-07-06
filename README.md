# Laravel SEO (core)

[![Tests](https://github.com/rankbeam/laravel-seo/actions/workflows/tests.yml/badge.svg)](https://github.com/rankbeam/laravel-seo/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/rankbeam/laravel-seo.svg?style=flat-square)](https://packagist.org/packages/rankbeam/laravel-seo)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE.md)

SEO core for Laravel: meta tag resolution with a layered precedence chain, Open Graph / Twitter Cards, JSON-LD schema markup with a linked `@id` graph, and XML sitemap generation.

> **Upgrading from `fibonoir/laravel-seo` v1?** See [UPGRADING.md](UPGRADING.md) — v2 renames the vendor and carves the old "full suite" down to this core; the analyzer, scanner, redirect manager, 404 monitor, and admin UI live on as separate packages ([`laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament), free; `laravel-seo-pro`, commercial).

## What this package does

| Area | Details |
|---|---|
| **Meta resolution** | `SEOResolver` merges six layers — config → global DB defaults → model-type defaults → route defaults → computed model values → explicit `seo_meta` values. Null never overwrites a lower layer. |
| **Computed fallbacks** | Title/description/image/robots derived from model attributes. Description candidates are configurable (`seo.computed.description_fields`), normalized (HTML stripped, entities decoded), and truncated at a word boundary (default 160 chars, no ellipsis). Per-model robots/`noindex` is built in — a `getSEORobots()` hook or an `is_indexable` attribute, overridable per page via `saveSEO(['robots' => …])`. |
| **Rendering** | `TagRenderer` outputs HTML (`@seo` Blade directives), structured arrays (Vue/React), or Inertia Head format. JSON-LD is emitted with `JSON_HEX_*` escaping so `</script>` in content cannot break out of the script element. |
| **Canonical policy** | Derived canonicals (model URL / current URL) get the query string stripped; explicitly set canonicals are preserved verbatim. |
| **Schema (JSON-LD)** | Builders for Article, Breadcrumb, FAQ, LocalBusiness, Organization, Product; `SchemaGraph` for Organization/WebSite/WebPage nodes cross-linked via stable `@id`s; breadcrumbs from a page's ancestor chain with a loop guard. |
| **Sitemaps** | `SitemapBuilder` (wraps spatie/laravel-sitemap) with config-driven model sources, programmatic named sources via `SEO::sitemaps()->register(...)`, sitemap index support, `seo:sitemap` command, and `/sitemap.xml` routes that can be disabled. |
| **llms.txt** | `seo:llms-txt` writes a markdown [`llms.txt`](https://llmstxt.org) index for AI crawlers (GPTBot / ClaudeBot / PerplexityBot / Google-Extended) from the **same sources as the sitemap** (the registry + `seo.sitemap.models`), so the two never disagree. Served at `/llms.txt`, gated by config. |
| **AI crawler control** | `seo:robots-txt` renders a managed `robots.txt` (and `ai.txt`) from a doc-verified catalog of AI crawlers tagged by purpose — **allow the bots that cite you (`ai_search`/`ai_assistant`), gate the ones that train on you (`ai_training`)** by default. `SEO::robotsTxt()->aiDirectives()` for a paste-able block; `SEO::aiCrawlers()` for the catalog + policy. Bots that ignore robots.txt are flagged advisory. |
| **Markdown for bots** | Content negotiation that serves clean **markdown** to AI crawlers instead of HTML — on `Accept: text/markdown`, `?format=md`, or (opt-in) a known AI crawler — from a model's `toSeoMarkdown()`, a `SEO::markdown()->register()` source, or a built title+description+content fallback. Off by default; never touches a normal visitor's response. |
| **Generated OG images** | Optional 1200×630 Open Graph images rendered by a real headless browser (`spatie/browsershot`) — correct multi-line wrapping, CJK/accents and truncation. Three publishable templates (default / article / product, selectable per model) + bundled OFL font; `seo:og-images` pre-generates and caches each card (content-hashed); the resolver serves it as a computed `og:image` fallback (existence-gated — never renders on a web request). **Off by default**, browser is a suggested dependency, static pre-generation only. See [Generated OG images](docs/guide/og-image.md). |
| **Warnings** | `SEOWarningEvaluator` for admin UIs: title > 60 / description > 160 warnings, manual-vs-fallback indicators, social-image dimension checks (min 200x200, ideal 1200x630, local files only). |
| **Free audit** | `seo:audit` — an in-process "what's wrong with my SEO right now" command (no queue, license, or network). Runs the metadata-class checks (missing / over- / under-length title & description, OG image, robots conflicts, canonical format/cross-domain/shared/insecure, focus keyword) and prints a per-page pass/warn/fail table with an explicit capability matrix. `--strict` for CI, `--json` for tooling. No numerical score (that's Pro). |
| **Migration importer** | `seo:import-from ralphjsmit` — bulk-import SEO data from a competing Laravel package's storage into `seo_meta`. Idempotent, `--dry-run`, `--model=` scoping, explicit field mapping, morph rows re-resolved to the live model. See [Migrating from other packages](docs/guide/migrate-from-other-packages.md). |
| **WordPress importer** | `seo:import-from wordpress-csv` (a CSV export) and `seo:import-from yoast` / `rank-math` (the live WordPress DB via `--connection=`). Maps Yoast/Rank Math keys explicitly (incl. OG/Twitter overrides), resolves `%%title%%`-style template tokens, matches posts to your models by slug, and emits a redirects CSV for Pro. See [Migrating from WordPress](docs/guide/migrate-from-wordpress.md). |

**Database tables:** `seo_meta` (per-model explicit values, morph + locale) and `seo_defaults` (global/model-type/route defaults). Nothing else.

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13 (CI runs the full matrix; Laravel 13 requires PHP 8.3+)
- `spatie/laravel-sitemap` ^7.0 or ^8.0 (suggested, required for sitemap generation)

## Installation

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan migrate
```

## Quick start

```php
use Rankbeam\Seo\Traits\HasSEO;

class Post extends Model
{
    use HasSEO;

    public function getUrlForSEO(): string
    {
        return route('posts.show', $this);
    }
}
```

```blade
<head>
    @seo($post)
</head>
```

Explicit values from the admin side:

```php
$post->saveSEO([
    'title' => 'Custom SEO Title',
    'description' => 'Custom meta description',
]);
```

Headless / Inertia:

```php
use Rankbeam\Seo\Facades\SEO;

return Inertia::render('Post', [
    'seo' => SEO::forInertia($post),
]);
```

Sitemap sources:

```php
// config/seo.php
'sitemap' => ['models' => [Post::class => ['priority' => 0.8]]],

// or programmatically (e.g. in a service provider)
SEO::sitemaps()->register('pages', fn () => ['/about', '/contact']);
```

Then generate the files (requires `spatie/laravel-sitemap`) — the package's
`/sitemap.xml` route serves what this command writes:

```bash
composer require spatie/laravel-sitemap
php artisan seo:sitemap
```

Serving your own static `/sitemap.xml`? Disable the package routes:

```php
// config/seo.php
'routes' => ['enabled' => false],
```

### Audit your SEO

```bash
# Audit the models under seo.audit.models / seo.sitemap.models
php artisan seo:audit

# Or target specific models, CI-fail on any issue, or emit JSON
php artisan seo:audit --model="App\Models\Post" --strict
php artisan seo:audit --json
```

A free, in-process pass/warn/fail report over the metadata-class checks. The
rendered-HTML and live-canonical checks, and the numerical score, are part of
the Pro scan — the command prints that boundary every run.

### Generate llms.txt

```bash
php artisan seo:llms-txt            # writes public/llms.txt
php artisan seo:llms-txt --print    # print to stdout (dry run)
```

A markdown index of your site for AI crawlers ([llms.txt](https://llmstxt.org)),
built from the **same sources as your sitemap** — registered sources plus
`seo.sitemap.models`, with the same noindex/unpublished exclusions — so the two
never disagree. It is served at `/llms.txt` (disable that route via
`seo.llms_txt.route`), and you can schedule it alongside the sitemap:

```php
Schedule::command('seo:llms-txt')->daily();
```

### AI crawler control (robots.txt / ai.txt)

```bash
php artisan seo:robots-txt          # writes public/robots.txt
php artisan seo:robots-txt --print  # print to stdout (dry run)
php artisan seo:robots-txt --ai-txt # also write public/ai.txt
```

A managed `robots.txt` for the AI era, rendered from a doc-verified catalog of
AI crawlers tagged by purpose. The default policy **allows the bots that cite you
(`ai_search` / `ai_assistant`) and disallows the ones that train on your content
(`ai_training`)**; override per purpose or per bot in `seo.ai_crawlers`. Grab
just the managed block for an existing file with `SEO::robotsTxt()->aiDirectives()`,
or serve `/robots.txt` dynamically (off by default — it won't shadow a static
file). Bots documented not to honour robots.txt are flagged advisory. See
[AI crawler control](docs/guide/ai-crawlers.md).

## Test status

`vendor/bin/pest` on `master`: **548 passed (1632 assertions), 0 failed** (plus 3 Chrome-dependent OG-image smoke tests skipped by default) under PHP 8.4 / Laravel 13 (CI matrix: PHP 8.2–8.4 × Laravel 11/12/13).

```bash
git clone https://github.com/rankbeam/laravel-seo.git
cd laravel-seo
composer install
vendor/bin/pest
```

## What is *not* in this package

Queued site scans, content analysis, redirect manager, 404 monitor, and the SEO dashboard ship in `laravel-seo-pro` (commercial); the Filament admin form fields ship in `laravel-seo-filament` (free). The old `seo:install` stub-publishing flow is gone.

## License

MIT — see [LICENSE.md](LICENSE.md).

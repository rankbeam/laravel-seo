---
title: What is Rankbeam? Laravel SEO infrastructure explained
description: "What Rankbeam is: open-core SEO infrastructure for Laravel — a free MIT core for metadata, canonical URLs, JSON-LD, sitemaps and crawler controls, plus a commercial Pro monitoring engine and an optional Filament UI."
---

# What is Rankbeam?

**Rankbeam is open-core SEO infrastructure for Laravel: a free MIT core for
metadata, canonical URLs, social cards, linked JSON-LD, sitemaps and crawler
controls, plus optional commercial Pro monitoring and workflows.** It isn't a
runtime tag helper bolted onto the side of an app. It resolves SEO from your own
models and config, renders the same typed data as Blade, an Inertia head or a
JSON API, and — with Pro — keeps watching it after the deploy.

## The package family

Rankbeam is three packages that share one support matrix:

| Package | License | What it is |
|---|---|---|
| [`rankbeam/laravel-seo`](https://github.com/rankbeam/laravel-seo) | **MIT, free** | the core — meta resolution, the linked JSON-LD schema graph, XML sitemaps, crawler controls, the free `seo:audit`, and the importers |
| [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament) | **MIT, free** | Filament 4/5 form fields and live previews that write to the core's `seo_meta` |
| `rankbeam/laravel-seo-pro` | **commercial** | the operations engine — queued scans with a 0–100 score, a redirect manager, a no-IP 404 monitor, a broken-link crawler, Search Console insights, and bring-your-own-key AI assist |

The boundary is deliberate. Everything the rendered page outputs is MIT and free
forever; what you pay for is the production **audit and monitoring** layer.
Commercial Pro is its own package — it never ships inside the free core.

## Who it's for

Rankbeam earns its keep when SEO is **stored, model-attached, multi-locale,
headless, and audited** — a production Laravel application with dynamic or
model-backed content. For a handful of static pages that need one title and
description, a small runtime meta helper is the better fit; the note below on
[when the assembled stack is still fine](#what-is-honestly-not-in-the-free-core)
says so plainly.

## Supported versions

One matrix for the whole family:

- **PHP** 8.2 – 8.4
- **Laravel** 11 / 12 / 13 (Laravel 13 requires PHP 8.3+)
- **Filament** 4 / 5 (optional)

## What Rankbeam doesn't replace

Rankbeam coordinates a Laravel app's own SEO output. It is not a hosted rank
tracker, a keyword-research suite, or an analytics product, and it promises
nothing about rankings, indexing, or AI citations. For XML sitemap generation it
wraps [`spatie/laravel-sitemap`](https://packagist.org/packages/spatie/laravel-sitemap)
rather than reinventing it, and it leaves your content, routing, and analytics
where they are.

New here? [Install the free core](/guide/installation), or read on for the
receipts from a real production swap. Pro and the founding waitlist live at
[rankbeam.dev](https://rankbeam.dev/).

## Why not three packages + glue

Most Laravel apps don't have an "SEO package." They have an **SEO stack**: a
package that stores meta per model, a second package that adds the fields to
Filament, a third that scans pages, and then a layer of app-specific glue that
makes the three agree with each other. Each piece is fine on its own. The cost
is the seams between them — and the glue is yours to maintain forever.

This page is the receipts from one real production swap, where exactly that
assembled stack was removed and replaced with the Rankbeam family. The numbers
below are measured, not marketed.

## The reference app

A real, in-production Laravel content site (anonymized here):

- A **hospital / institutional content site**, ~3 months in production.
- **Migrated off WordPress**, ~900 pages per the sitemap.
- ~**20,000 visits/day**.
- **Laravel 12**, **Filament 4** admin, Blade frontend, MySQL.

The SEO stack it ran before the swap:

| Layer | Package |
|---|---|
| Meta storage (per-model `seo` table) | [`ralphjsmit/laravel-seo`](https://github.com/ralphjsmit/laravel-seo) |
| Filament SEO fields | `ralphjsmit/laravel-filament-seo` |
| Page scanner | `backstage/laravel-seo-scanner` |
| Everything in between | **~30 bespoke app classes** |

We removed the three packages, installed Rankbeam **core + Pro + Filament**, ran
the SEO test suite, and the app booted with **zero SEO regressions**. What
follows is what the glue layer actually cost — and what disappeared.

## What the swap deleted

Replacing the scanner stack with Rankbeam deleted **12 bespoke classes outright**
— work the app no longer owns because the package family owns the equivalent:

| Deleted app class | What it was | Now provided by |
|---|---|---|
| `Services/SeoService.php` | the app's SEO entry-point wrapper | core resolver + `SEO` facade |
| `Services/SeoWarningEvaluator.php` | title/description length + image-size thresholds | core `SEOWarningEvaluator` (shared by audit, preview, scan) |
| `Services/Seo/SeoAssetInspector.php` | local image dimension inspection | core `LocalImageInspector` |
| `Jobs/ScanAllPagesSeo.php` | queued sitewide scan dispatch | Pro queued [scan pipeline](/pro/scan-issues) |
| `Jobs/ScanPageSeo.php` | per-page scan | Pro `PageScanner` |
| `Jobs/ScanPublicPageSeo.php` | per-public-page scan | Pro scan pipeline |
| `Models/SeoScanBatch.php` | scan-run bookkeeping | Pro `seo_scan_runs` |
| `Filament/Pages/SeoDashboard.php` | the SEO admin dashboard | Pro `SeoDashboard` plugin |
| `Filament/Widgets/SeoScanProgressWidget.php` | scan-progress widget | Pro scan widgets |
| `Filament/Widgets/SeoTrendChartWidget.php` | scan-trend widget | Pro scan widgets |
| `Facades/Seo.php` | app facade over the storage package | core `SEO` facade |
| `Console/Commands/RecoverLegacySeoMetadata.php` | one-off metadata recovery | core [importers](/guide/migrate-from-wordpress) (`seo:import-from`) |

::: info Honest accounting of the rest
The swap deliberately **kept** the app's hand-rolled broken-link crawler
(~17 classes: the scan job, checker, seed builder, source resolver, two models,
two enums, two events, the Filament resource + three widgets, two commands) and
a few meta/schema helpers (`CustomSEO`, `EntitySeoSection`,
`DynamicSeoDataResolver`, `SitewideSchema`, `SeoKeywords`) — about **22 more
classes**. Those weren't deleted on day one because Rankbeam's replacements
landed afterward: the [Pro broken-link crawler](/pro/production) for the
bespoke crawler, the Filament **related-model target** + **SERP/social preview**
for `CustomSEO`/`EntitySeoSection`, and the core **schema graph** for
`SitewideSchema`. Adopt the full family and that custom surface — roughly
**three dozen classes in total** — becomes the package's job, not yours.
:::

The point isn't that any one of those packages is bad. It's that the *integration*
— the dozen-plus classes wiring them together so a meta change reflects in the
scanner, the dashboard, and the rendered head — is bespoke code with no upstream,
no tests but yours, and no one else's bug reports.

## Side by side

| Capability | Assembled stack (3 packages + glue) | Rankbeam family |
|---|---|---|
| Per-model meta storage | meta package | **core** (`seo_meta`, MIT) |
| **Locale-aware** storage | usually a glue concern | **core** — `seo_meta` is locale-scoped by column |
| Filament SEO fields | Filament-SEO package | **`laravel-seo-filament`** (MIT) |
| Edit SEO for a **related** model | wrap the field component yourself | first-class `target:` resolver |
| Live **SERP + social** preview | hand-built Blade/Alpine | built-in tabbed editorial preview |
| Headless rendering (Inertia / Livewire / JSON) | each package assumes Blade | **one resolver** → Blade, Inertia, Livewire, JSON ([contract-tested](/contributing/rendering-contract)) |
| Page scanner + ranked issues | scanner package | **Pro** [scan pipeline](/pro/scan-issues) + `IssueRegistry` |
| 0–100 score | glue / none | **Pro** transparent, [versioned rubric](/pro/scoring) |
| Redirects + 404 recovery | another package / bespoke | **Pro** redirect manager + no-IP 404 monitor |
| Broken-link crawler | bespoke (the app built its own) | **Pro** bounded, resumable crawler |
| JSON-LD schema **graph** | a builder + your own `@id` wiring | **core** cross-linked Organization/WebSite/WebPage graph |
| XML sitemaps | sitemap package | **core** sitemap registry (wraps `spatie/laravel-sitemap`) |
| WordPress / Yoast / Rank Math import | one-off scripts | **core** `seo:import-from` + a [runbook](/guide/wordpress-migration-runbook) |
| **Who maintains the seams** | **you** | the package family, one release line |

## The three things glue can't do well

**1 — One cohesive family, one release line.** Three packages have three
maintainers, three changelogs, and three upgrade cadences; the glue exists to
absorb the drift between them. Rankbeam core, Pro, and Filament are versioned
together with a single [support matrix](#tested-where-it-runs) and documented
[upgrade boundaries](/reference/configuration) — a behavior change is announced
in one place, not discovered when two packages disagree.

**2 — Locale-aware storage as a column, not a convention.** `seo_meta` is
polymorphic **and** locale-scoped at the storage layer. Multi-locale SEO is a
row per `(model, locale)`, not a serialized blob or a glue table you remembered
to add. The [resolver precedence](/concepts/resolver-precedence) reads the active
locale natively.

**3 — Headless rendering from one resolver.** Most SEO packages emit a Blade
partial. Rankbeam resolves typed `SEOData` and renders the *same* data as HTML,
an Inertia `Head` payload, or a JSON array — proven against a shared
[rendering contract](/contributing/rendering-contract) for
[Blade](/guide/blade), [Inertia](/guide/inertia-json) (Vue/React/Svelte), and
[Livewire](/guide/livewire). No admin panel is required: every Pro feature also
runs [headless from artisan](/pro/headless).

## What is honestly *not* in the free core

Rankbeam is open-core, and the boundary is deliberate — so you know exactly what
you're getting before you `composer require`:

| Package | License | What's in it |
|---|---|---|
| `rankbeam/laravel-seo` | **MIT, free** | meta resolution, JSON-LD schema graph, sitemaps, the free `seo:audit`, importers |
| `rankbeam/laravel-seo-filament` | **MIT, free** | Filament form fields/sections that write to `seo_meta` |
| `rankbeam/laravel-seo-pro` | **commercial** | queued scans + ranked issues + 0–100 score, redirects, 404 monitor, broken-link crawler, Search Console, AI assist, the Filament dashboard |

So the things you'd pay for are the **technical-SEO audit** and the **site
monitoring** suite — scans, score, redirects, 404 recovery, and the crawler. The
metadata engine, schema graph, sitemaps, and the free in-process audit are MIT
and stay free.

Two properties that survive scrutiny:

- **No runtime license check.** Pro is licensed per project at install time;
  nothing phones home and no kill-switch can take your app down. (Pro emits
  *local* operational telemetry for your own logs — opt-out, never to us.)
- **Bring-your-own-key AI.** The optional [AI assist](/pro/ai-assist) uses *your*
  Anthropic, OpenAI, Google, or local-model key. Nothing is proxied, metered, or
  resold, and it's off by default.

::: tip When the assembled stack is still fine
If you need a single `<title>` and description on a handful of static pages, a
runtime tag builder is plenty. Rankbeam earns its keep when SEO is **stored**,
**multi-locale**, **model-attached**, **headless**, and **audited** — the moment
the glue between packages starts being real code you maintain.
:::

## The lowest-risk switch: off WordPress

The reference app was a ~900-page WordPress migration, which is the audience with
the most to lose — years of Yoast/Rank Math optimization. Rankbeam treats that as
the safe path, not the scary one:

1. **Coexist.** Stand Rankbeam up next to the live site; nothing is removed yet.
2. **Import (dry-run first).** `seo:import-from yoast` / `rank-math` /
   `wordpress-csv` reads your titles, descriptions, canonicals, robots, focus
   keywords, and social overrides. The importers are **idempotent** and
   **fill-empty-only** — they can never clobber metadata you've already set, and
   `--dry-run` writes nothing.
3. **Hand off redirects.** Core emits a versioned redirect CSV; Pro's
   `seo-pro:redirects-import` validates every row (rejecting loops, unsafe
   targets, and duplicates) before writing.
4. **Verify before you delete anything.** `seo:audit --strict` is a CI/cutover
   gate that exits non-zero on any issue. The legacy WordPress database stays
   untouched until you choose to drop it.

The full procedure is the [WordPress migration runbook](/guide/wordpress-migration-runbook);
the field-by-field mapping and token handling are in
[Migrating from WordPress](/guide/migrate-from-wordpress). Switching from a
**Laravel** SEO package instead (ralphjsmit, artesaos, Spatie)?
[That path is one command too](/guide/migrate-from-other-packages).

## Does it hold up at scale?

The reference app's two hardest constraints — a resolver on every one of ~20k
daily requests, and a ~900-page link crawl — each have a benchmark in the test
suite. These assert **deterministic** wins (query counts and job boundedness),
not hand-tuned wall-clock figures:

**Resolver caching — a warm hit touches the database zero times.** With the
opt-in resolution cache on, a cached resolve skips the *entire* precedence chain.
The benchmark runs 25 resolves of the same model:

| | DB queries |
|---|---|
| Uncached (each resolve re-reads `seo_meta`) | **≥ 25** |
| Warm cache hit | **0** |

The cache is **off by default** and documented as the scale lever; invalidation
busts the right entries when `seo_meta`, a content field, or your defaults change.
See [Configuration → caching](/reference/configuration).

**Broken-link crawler — bounded at 900 pages.** The crawler benchmark drives a
generated ~900-page corpus through the real job:

- Completes across **≥ 18 bounded jobs** (page cap 50/job).
- **No single job** visits more than its 50-page cap.
- **1,800 links** checked; every dead target becomes a durable, confirmed-broken
  finding.

It's bounded by finite per-run caps and a hard per-job time budget, with SSRF
validation on the seed fetch **and every redirect hop**, and a DB lease so only
one run per scope is active. Operations are covered in the
[production setup guide](/pro/production).

## Tested where it runs

One support matrix for the whole family, not three:

- **PHP** 8.2 – 8.4
- **Laravel** 11 / 12 / 13
- **Filament** 4 / 5

## So — why glue three packages together?

When the assembled stack costs you a dozen-plus bespoke classes to integrate, a
release cadence you don't control, Blade-only rendering, and locale handling you
bolt on by hand — and a cohesive, headless, locale-native family deletes that
glue and is proven on a real 900-page / 20k-a-day production app — the assembled
stack stops being the safe default.

Start with the [Quickstart](/guide/quickstart): `composer require` to a fully
rendered `<head>` in five minutes.

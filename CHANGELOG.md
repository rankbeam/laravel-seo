# Changelog

All notable changes to `rankbeam/laravel-seo` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.11.0] - 2026-07-10

### Added

- **Styled sitemaps — a readable, branded browser view.** Every generated sitemap now references an XSL stylesheet (via an `<?xml-stylesheet?>` instruction), so opening one in a browser shows a readable table — URL, `lastmod`, change frequency, priority, and image/alternate counts — with inline validation notes (URLs missing a `lastmod`; non-absolute `http(s)` URLs) instead of raw XML. Search engines **ignore** the instruction, so the sitemap stays a normal machine-readable XML document; the index and every child sitemap are styled alike. The stylesheet is served from the package's own `/sitemap.xsl` route — browsers only apply a same-origin XSLT — or self-host it behind a CDN by publishing `--tag=seo-assets` and setting `seo.sitemap.stylesheet.url`. Every value it renders is XSLT-escaped and a `<loc>` only becomes a clickable link when it is an `http(s)` URL, so hostile URL content can't inject markup or a `javascript:` link. **On by default** — unlike the image/hreflang extensions it adds no data and does no per-record work (just one instruction line crawlers skip); set `seo.sitemap.stylesheet.enabled = false` (env `SEO_SITEMAP_STYLESHEET`) to emit plain XML. Docs [/guide/sitemaps](https://rankbeam.dev/guide/sitemaps#styled-sitemap-in-the-browser).

## [3.10.0] - 2026-07-07

### Added

- **OG-image renderer sandbox + Chromium-flag config.** Two new `seo.og_image` keys let you pass launch flags to the headless Chrome that renders cards. `no_sandbox` (env `SEO_OG_IMAGE_NO_SANDBOX`) runs Chrome with `--no-sandbox` — the standard fix for the **`No usable sandbox!` launch failure on default Ubuntu 22.04+/24.04 servers**, where AppArmor's unprivileged-user-namespace restriction otherwise stops Chrome starting and `php artisan seo:og-images` fails out of the box. `browsershot_args` (a list, or a map for value-bearing flags) forwards arbitrary flags to `Browsershot::addChromiumArguments()` — e.g. `['disable-dev-shm-usage']` on a low-shared-memory container — with a leading `--` optional. Since only package-generated HTML is ever rendered (never untrusted pages), `--no-sandbox` here does not carry the risk it would on a general-purpose scraper; the docs also cover the hardened AppArmor-profile alternative. Both **default off / empty** (byte-identical until you opt in). Docs [/guide/og-image](https://rankbeam.dev/guide/og-image#running-on-linux-the-sandbox).

## [3.9.0] - 2026-07-07

### Added

- **Content signals in `robots.txt`.** Opt in with `seo.ai_crawlers.content_signals` (env `SEO_AI_CONTENT_SIGNALS`) and the managed `robots.txt` emits a [Content-Signal](https://contentsignals.org) usage-preference line — the standard championed by Cloudflare — in the `User-agent: *` group, derived from your existing `ai_crawlers.policy`: `ai_search → search`, `ai_assistant → ai-input`, `ai_training → ai-train`, each `allow → yes` / `disallow → no`. A purpose you remove from the policy omits its signal (the spec's "no preference"). It is folded into the default general group (a single, Cloudflare-shaped group) or emitted as a standalone `User-agent: *` group when `general` is a verbatim string or off. Content signals state how content may be **used** and are advisory — distinct from the `Allow`/`Disallow` crawl-access rules. **Off by default** (byte-identical until you opt in). Docs [/guide/ai-crawlers](https://rankbeam.dev/guide/ai-crawlers).
- **`X-Robots-Tag` header for the indexing guard.** While the [indexing guard](https://rankbeam.dev/guide/indexing-guard) is active it now also sends `X-Robots-Tag: noindex,nofollow` (via a global middleware) on every response routed through the app — so **PDFs, feeds and images**, which carry no `<meta robots>`, are held out of the index too. The header mirrors the resolver meta via one shared directive constant, so they can never disagree. The middleware is registered **only when the guard is enabled**, so a package with the guard off adds nothing. On by default within the guard; disable with `seo.indexing_guard.send_header` (env `SEO_INDEXING_GUARD_HEADER`). A static file served directly from `public/` bypasses PHP and must be protected at the edge.
- **`seo.canonical.query_whitelist` — keep chosen query params in derived canonicals.** A canonical the resolver *derives* (from the request URL or a model's `getUrlForSEO()`) still strips its query string by default, but keys listed here (e.g. `page` for paginated archives — `/blog?page=2` is genuinely not `/blog`) are **kept**, in whitelist order (a stable order regardless of the request's param order). An **explicitly set** canonical stays verbatim — the whitelist governs only the derived fallback. The resolver result-cache key varies by whitelisted params, so `?page=1` and `?page=2` never collide on one entry. Default `[]` preserves the strip-everything behaviour. Docs [/reference/configuration](https://rankbeam.dev/reference/configuration#canonical-urls).

## [3.8.1] - 2026-07-06

### Fixed

- **Meta-less `og:type` no longer clobbered to `website`.** When `seo.features.auto_create_meta` is off and a model has no stored `seo_meta` row, the explicit layer's `SEOData::fromModel()` returned a bare `new SEOData()` whose non-null `ogType='website'` / `twitterCard='summary_large_image'` constructor defaults rode along and overrode the computed layer — so an article-like page (computed `og:type='article'`) resolved to `og:type='website'`. `fromModel()` now returns an all-null DTO for a meta-less model (those two defaults suppressed), so it contributes nothing and the computed value survives. Only bit with `auto_create_meta=false` + a computed `og:type` ≠ `website` + no stored row; the default `auto_create_meta=true` path (a row with nullable `og_type=null`) was never affected. `seo:explain` now attributes such fields to their real lower layer.

## [3.8.0] - 2026-07-06

### Added

- **`seo:explain` — the resolver precedence trace.** `php artisan seo:explain {model} {id?} {--route=} {--locale=} {--json}` shows, for every field, the **winning layer and value**, each **losing layer's value** (overridden by "last non-null wins"), and any **post-processing** that changed the final value (title suffix, canonical query-strip, og:url derivation, image absolutization, the indexing guard forcing `noindex`). It also prints the **site-level resolution ledger** — site name, default locale, and canonical host — naming which source set each (env / config / programmatic / request), the class of bug canonical-host resolution is notorious for. Human output à la `route:list`, plus `--json`. Implemented as a read-only `SEOResolver::layerContributions()` hook + a `ResolutionExplainer`: attribution reuses the resolver's own layer sources and the final values come from the real `resolve()`, so the explanation can never drift from what renders — and the resolve hot path is untouched. Docs [/guide/explain](https://rankbeam.dev/guide/explain).

## [3.7.0] - 2026-07-06

### Added

- **Indexing guard — an env-based non-production safety net.** A staging or
  local copy leaking into a search index is one of the most damaging SEO
  mistakes; this ties indexability to the Laravel *environment*. When the app
  runs outside `seo.indexing_guard.allowed_environments` (default
  `['production']`), the resolver forces `noindex,nofollow` on every page
  **above the whole precedence chain** — so it overrides even an explicit stored
  per-page `robots` value (a staging DB is usually a production clone; wrongly
  indexing it is a disaster, wrongly noindexing it a no-op) — `SEO::robotsTxt()`
  emits a disallow-all `robots.txt` / `ai.txt`, and `seo:audit` prints a banner
  (with an `indexing_guard` block in `--json`). Ships **off** and byte-identical
  until you opt in; arm it in one line with `SEO_INDEXING_GUARD=true` (and
  disable with `=false`). Override the allow-list via `SEO_INDEXING_GUARD_ALLOWED`
  (comma-separated, `Str::is()` wildcards, empty = guard everywhere). Inert on
  production. Strongly recommended; a candidate to default on in Core 4. See
  [the guide](https://rankbeam.dev/guide/indexing-guard).

## [3.6.2] - 2026-07-06

### Changed

- **OG-image cards no longer print the site name twice.** The bundled templates
  render the site name as their own element, so a card title that still carried
  the `seo.title_suffix` (e.g. "My Post | Acme") duplicated the brand. The card
  title now has that suffix trimmed off by default; opt out with
  `seo.og_image.strip_title_suffix => false`.

## [3.6.1] - 2026-07-06

### Added

- **OG-image templates `article` and `product`, plus per-model selection.**
  Completing the v3.6.0 feature: alongside the default card, `seo::og.article`
  (section eyebrow + author · date byline) and `seo::og.product` (brand lockup +
  category chip + description) now ship. Map templates per model class via the
  new `seo.og_image.templates` config, or override per instance with a
  `getOgImageTemplate()` method — so an article and a product get different
  cards automatically. The generator's `urlFor()`/`generate()`/`cacheKey()` gain
  an optional leading `$template` argument (existing calls are unaffected), and
  the template name is part of the content hash.

## [3.6.0] - 2026-07-06

### Added

- **Generated OG images** — automatic 1200×630 Open Graph / Twitter-card images
  for pages with no explicit `og:image`, rendered by a real headless browser via
  the optional `spatie/browsershot` package, so multi-line wrapping, non-Latin
  scripts (CJK) and accents come out correct. A driver-based renderer (an
  `OgImageRenderer` contract + `OgImageManager`, with `BrowsershotRenderer` built
  in), a publishable Blade template (`seo::og.default`) with a bundled OFL font,
  and an `OgImageGenerator` that stores each card keyed by a content hash (busted
  on a template/brand change via `cache_version` **and** on a package upgrade).
  The `seo:og-images` command pre-generates cards for your models (`--force`,
  `--prune`), and the resolver serves the generated card as a computed `og:image`
  fallback — existence-gated, so a web request never renders a browser and a page
  never links a missing image (it fails open to `default_og_image`). **Off by
  default**; the free core stays zero-dependency (`spatie/browsershot` is a
  `suggest`). Static pre-generation only — no dynamic render endpoint. See
  [Generated OG images](docs/guide/og-image.md).

## [3.5.0] - 2026-06-30

### Added

- **Markdown for bots (content negotiation)** — serve a clean markdown
  representation of a page to AI crawlers instead of HTML. A content-negotiation
  middleware swaps in markdown ONLY when the request asks for it (an explicit
  `Accept: text/markdown`, a `?format=md` query, or — opt-in — a known AI crawler
  by user-agent, reusing the v3.3.0 `AiCrawlerRegistry`) **and** a markdown
  source resolves for the route; otherwise the normal HTML response passes
  through untouched, and only successful HTML responses are ever replaced
  (never JSON, redirects, or downloads). Sources, in order: a route registered
  via `SEO::markdown()->register()`, a model's own `toSeoMarkdown()`
  (`ProvidesSeoMarkdown`), or a built title + description + `getContentForSEO()`
  fallback. **Off by default** (`seo.markdown_for_bots.enabled`) — the
  middleware isn't even registered until you opt in, so there's zero footprint.
  No new dependencies. Full reference: docs `/guide/markdown-for-bots`.

## [3.4.0] - 2026-06-30

### Added

- **Answer-readiness (AEO) checks in the free `seo:audit`** — the first
  AI-answer-engine optimisation signals, surfaced free. Two metadata-class
  checks read the page's resolved JSON-LD graph and fire only when it declares
  article-type structured data (`Article`, `BlogPosting`, `NewsArticle`, …) that
  is missing a signal AI answer engines use: `aeo_missing_author` (no author
  entity for attribution / E-E-A-T) and `aeo_article_missing_date` (no
  `datePublished` / `dateModified` for recency). A page without an article is
  never flagged, so the audit stays quiet where AEO doesn't apply. Both are
  advisory (notice-level) and mirror the Pro scan's new `aeo_*` codes; they are
  held out of the Pro 0–100 score. No new dependencies. Docs: `/guide/audit`.

## [3.3.0] - 2026-06-30

### Added

- **AI crawler control (robots.txt / ai.txt)** — a managed `robots.txt` for the
  AI era. Ships a doc-verified catalog of the major AI crawlers (OpenAI's GPTBot
  / OAI-SearchBot / ChatGPT-User, Anthropic's ClaudeBot / Claude-SearchBot /
  Claude-User, Google-Extended, PerplexityBot, Applebot-Extended, CCBot, Meta,
  Amazon, ByteDance and more), each tagged by purpose — `ai_search`,
  `ai_assistant`, or `ai_training`. A configurable policy renders robots.txt
  directives that **allow the bots that cite you and gate the ones that train on
  you** by default. Includes: the `AiCrawlerRegistry` (catalog + policy +
  `match()` for identifying a request user-agent), a `RobotsTxtBuilder` reachable
  via `SEO::robotsTxt()` (`build()`, `aiDirectives()` for a paste-able block,
  `generate()`, and an `ai.txt` variant), a `SEO::aiCrawlers()` accessor, the
  `seo:robots-txt` command (`--print`, `--output`, `--ai-txt`), a config-gated
  dynamic `/robots.txt` route (**off by default** so it never shadows a static
  `public/robots.txt`), and a `seo.ai_crawlers` config block. Bots that are
  documented not to honour robots.txt (e.g. `ChatGPT-User`, `Perplexity-User`,
  `Bytespider`) are marked **advisory** in the output rather than implying a
  block that won't hold. No new dependencies. Full reference: docs
  `/guide/ai-crawlers`.

## [3.2.0] - 2026-06-29

### Added

- **llms.txt generator** — the AEO/GEO counterpart to the XML sitemap: a
  markdown index of the site's key content for AI crawlers (GPTBot, ClaudeBot,
  PerplexityBot, Google-Extended), built from the **same sources as the
  sitemap** (the shared `SitemapRegistry` named sources + `seo.sitemap.models`)
  with the same noindex/unpublished exclusions, so the two artifacts never
  disagree about what's on the site. Ships `LlmsTxtBuilder`, the `seo:llms-txt`
  command (`--print`, `--output=`), a config-gated `/llms.txt` route, a
  `SEO::llmsTxt()` facade accessor, and a `seo.llms_txt` config block. No new
  dependencies.

### Documentation

- Documented the Pro on-page checklist's new **readability check**
  (Flesch-Kincaid / Gulpease) in `docs/pro/on-page-checklist.md`.

## [3.1.1] - 2026-06-16

### Changed

- **Migration docs.** Documented that an explicit canonical is imported
  verbatim (the WordPress importer never rewrites the host), that custom
  WordPress post types must be named with `--post-type=`, and the MySQL 8
  `sql_mode` gotcha when reading SEO data from a restored dump.

### Fixed

- Patched the `esbuild` advisory (GHSA-gv7w-rqvm-qjhr) in the documentation
  toolchain. Dev-only — `esbuild` is not part of the distributed package.

### Removed

- Internal working documents that were never meant to be part of the package
  no longer appear in the distributed archive.

## [3.1.0] - 2026-06-16

### Resolver result cache for hot frontends

An opt-in cache for the resolver's output. The `SEOResolver` runs the full
precedence chain on every frontend render; on a high-traffic site (the reference
app does ~20k req/day) that is several DB reads per page. Enable this and a
model's fully-resolved SEO is cached, so a **cache hit skips the precedence chain
entirely** — in the package benchmark, a warm hit issues **zero** database
queries where each uncached resolve re-reads the model's `seo_meta`. Additive and
**off by default**; correctness with caching on is identical to off.

#### Added

- **`seo.cache.resolver.enabled`** (`SEO_RESOLVER_CACHE`, default `false`) and
  **`seo.cache.resolver.ttl`** (`SEO_RESOLVER_CACHE_TTL`, default `3600`) — the
  resolver result cache, using the existing `seo.cache.store`.
- **`Rankbeam\Seo\Services\SEOResolutionCache`** — caches a resolved model's SEO
  as a plain array (rehydrated with `SEOData::fromArray()`, **never an object** —
  Laravel 13 ships `cache.serializable_classes = false`, so a cached object
  returns as a `__PHP_Incomplete_Class`; this is the same reason
  `SEODefaultsRepository` caches arrays). Entries are keyed by
  `(model class, id, locale, route, request URL)`. On a **taggable** store
  (`redis`, `memcached`, `array`) a model's entries clear via cache **tags**; on
  a **non-taggable** store (`file`, `database`) it falls back to a per-model
  **version stamp** — both clear one model without key-scanning.

#### Invalidation (new — none existed before)

The cache busts automatically, so a hit never serves stale data:

- **New `SEOMeta` `saved`/`deleted` model-event listener** (on the `SEOMeta`
  model) — busts the owning model's entries whenever its `seo_meta` row changes,
  via any path (`saveSEO()`, Filament, a direct `SEOMeta` write); a morph alias
  is normalized back to the FQCN the resolver keys by. Inert when caching is off.
- **New `HasSEO` `saved` listener** — busts a model's entries when one of its
  `getSEOContentFields()` columns changes (those feed the *computed* layer). The
  default field list now covers every built-in computed fallback field: title /
  heading fields, description/content fields, and the common social-image fields
  (`featured_image`, `thumbnail`, `cover_image`, `og_image`, `photo`, `banner`,
  `hero_image`, etc.). The `deleted` hook also busts. Inert when caching is off.
- **`SEODefault` save/delete** now also flushes the whole resolution cache (a
  default can feed any model's resolution).

#### Notes

- Only **model-backed** resolves are cached: a hand-built `SEOData` rendered
  through `SEO::render()`/`@seo()` and a model-less `@seoForRoute()` still resolve
  live (they have no stable model identity to key/invalidate by).
- The published `SEOData::toArray()` is a *nested, lossy* render shape and is
  **not** the inverse of `fromArray()`; the cache serializes via the flat
  `toFlatArray()` (with the two DateTime fields stored as ISO-8601 so
  published/modified times round-trip to the second across timezones).
- The cached `article:modified_time` reflects the last **content-field** change
  (or the TTL): a bare `touch()` that moves only `updated_at` without changing a
  `getSEOContentFields()` column does not force a re-resolve. Widen
  `getSEOContentFields()` if you need it busted eagerly.
- Internally, `SEOResolver::resolve()` is split into a cache wrapper over a
  `buildResolved()` body and uses a re-entrancy depth guard so the schema layer's
  nested `webPage()` resolve neither reads nor writes the cache. With caching off,
  behaviour is byte-identical to before.

This is an **additive, opt-in minor** (no UPGRADING). The new `SEOMeta`/`HasSEO`
model-event listeners are inert while `seo.cache.resolver.enabled` is `false`
(the default), so no behaviour changes for existing apps.

### Schema composition: @id-linked graph + getSEOSchema() hook

A composition layer on top of the existing `SchemaGraph` and loop-guarded
`BreadcrumbSchema::fromModelAncestors()` primitives, so an app can assemble a
cross-linked Organization / WebSite / WebPage + breadcrumb graph without
hand-rolling a sitewide-schema class. Additive — models that do not opt in
render no schema, exactly as before.

#### Added

- **`SchemaGraph::for($model)` fluent builder** (`Rankbeam\Seo\Services\Schema\SchemaGraphBuilder`)
  — chain `->organization()->website()->webPage()->breadcrumbFromAncestors()`
  then `->toArray()` to compose the @id-linked graph. It only glues the existing
  primitives together: it adds no schema logic and introduces no parallel
  breadcrumb API. `webPage()` resolves the subject's `SEOData` (or takes one
  explicitly); `breadcrumbFromAncestors()` delegates to the existing
  loop-guarded `BreadcrumbSchema::fromModelAncestors()`; `add()` accepts any
  pre-built node. The nodes carry deterministic, stable @ids
  (`{site_url}#organization`, `{site_url}#website`, `{canonical}#webpage`).
- **`getSEOSchema(): array` model hook** (on the `HasSEO` trait) — return one or
  more schema.org nodes. The default is an empty list. Compose the return value
  with `SchemaGraph::for($this)`.
- **`seo.schema.type_map` config mapping** — an optional `model class => builder`
  fallback used only when a model has no stored schema and does not override
  `getSEOSchema()`. The builder is an invokable class-string (resolved through
  the container; the config:cache-safe canonical form), a Closure, or any
  callable, invoked with the model. An exact class match wins; otherwise the
  first mapped class the model is an instance of (so a base mapping covers
  subclasses).

#### Precedence (explicit over computed)

- An **explicit stored `seo_meta.schema_jsonld`** (or a schema supplied by a
  default layer) is **authoritative**: it is emitted as-is and the hook /
  type-map is **not** invoked for that model — no silent merge. The hook and
  type-map produce the graph only when no schema is otherwise present. The
  computed-schema step runs as a final resolver layer and is re-entrancy-guarded
  so a `getSEOSchema()` that composes `webPage()` (which re-resolves the model's
  `seoData()`) terminates instead of recursing.

### Optional dimension-aware social-image selection

An opt-in computed-image strategy that picks the social / Open Graph image
whose pixel dimensions sit closest to the ideal, instead of just the first
match. Additive — the default behaviour is byte-identical to before.

#### Added

- **`getSEOImages(): iterable` model hook** (on the `HasSEO` trait) — return an
  ordered list of `Rankbeam\Seo\Data\SEOImageCandidate` objects (or plain URL
  strings, treated as priority 0) to expose multiple social-image candidates.
  The default is an empty list, so models that do not override it are
  unaffected.
- **`SEOImageCandidate` value object** — `SEOImageCandidate::make($url)->priority(100)`;
  immutable, carries a URL and a relative priority that breaks ties.
- **`seo.computed.image_selection` config block** — `strategy` (`first` default,
  or `best`), `minimum_width` / `minimum_height` (200×200), `ideal_width` /
  `ideal_height` (1200×630). Under the `best` strategy the builder scores every
  candidate (`getSEOImage()` first — it stays the highest-priority candidate —
  then the `getSEOImages()` hook, then common fields, content, and the
  configured default) by closeness to the ideal and **skips any below the
  minimum**. The default `first` strategy is unchanged: the highest-priority
  non-empty source wins and no image is opened or measured.
- New `Rankbeam\Seo\Services\LocalImageInspector`, extracted from
  `SEOWarningEvaluator`, is now the single source of truth for measuring a
  local image's dimensions — so the editorial preview's "too small" and the
  selector's "skip undersized" can never diverge.

#### Notes

- **Local images only.** Core never fetches a remote image (SSRF / latency /
  cache); a remote URL is never measured and can only act as a fallback. Remote
  dimension checks remain the Filament preview's client-side job. When no local
  candidate clears the minimum, selection falls back to first-match, so `best`
  never returns less than `first` would.
- The `best`-strategy default thresholds track `SEOWarningEvaluator`'s
  constants (200×200 minimum, 1200×630 ideal), keeping audit, preview, and
  selection in agreement.

### WordPress / legacy migration hardening + import verification report

Hardening for the WordPress / legacy migration path, plus an import
**verification report** so an operator can confirm exactly what an import did
before decommissioning the old stack. Additive — no command option changes.

#### Added

- **Import verification report.** `seo:import-from` now prints — and exposes in
  `--json` under a `verification` key — a matched / url-only / truncated /
  unmapped breakdown. Rows that matched no model are counted as **url-only** as a
  first-class number, and unmapped fields with no Core 3 home now capture their
  distinct **values** (`unmapped_values`) — above all every `author` name. Author
  has no column (it is a `getSEOAuthor()` concern), so the report lets you re-home
  it deliberately instead of losing it silently.
- Anonymized ~900-page WordPress corpus fixtures (Yoast + Rank Math + a CSV
  excerpt) and expanded token / malformed-data / morph-map / locale / idempotency
  tests across all three WordPress importers.

#### Notes

- The redirect candidates the WordPress importers emit use a stable, versioned
  shape — **redirect CSV format v1** (`source_path,target_url,status_code,note`) —
  consumed by Pro's `seo-pro:redirects-import`. The format version is recorded
  here so the open-core contract is traceable.

#### Documentation

- New [WordPress migration runbook](docs/guide/wordpress-migration-runbook.md):
  the ordered, low-risk cutover procedure — coexist → import → verify with
  `seo:audit --strict` → explicit verification before removing the legacy
  package/table.

### Documented robots / `is_indexable` support

Documentation and tests only — **no behaviour or API change.** Per-model robots
control already shipped (`SEOComputedBuilder::computeRobots()` honours a model's
`getSEORobots()` hook and derives `noindex, nofollow` from a falsy `is_indexable`
attribute), but the `HasSEO` trait doesn't declare the hook, so it read as
missing.

#### Documentation

- The `HasSEO` trait docblock, the [resolver-precedence](docs/concepts/resolver-precedence.md)
  guide, and the README now document the `getSEORobots()` hook, the
  `is_indexable` derivation, and how the resolved directive flows through the
  robots emit policy (a directive equal to `seo.default_robots` is suppressed; a
  deviating one is emitted verbatim).

#### Tests

- Added end-to-end characterization tests proving a model with
  `is_indexable=false`, an explicit `seo_meta.robots`, or a `getSEORobots()` hook
  renders the expected `<meta name="robots">` tag through the full
  resolve → render path — and that an indexable model emits no tag (its
  `index, follow` equals the site default).

### Render surface accepts `Model | SEOData | null`

Model-less pages (listings, search, controller-composed views) can now render
through the facade and the `@seo` directive with a hand-built `SEOData` instead
of reaching for `app(TagRenderer::class)->render($seoData)` by hand — the #1
integration papercut.

#### Added

- **`SEO::render()`, `SEO::toArray()`, `SEO::forInertia()` accept
  `Model|SEOData|null`.** A `Model` (or `null`) runs the full precedence chain as
  before; a hand-built `SEOData` is rendered directly. `SEO::forInertia()` is also
  now a first-class facade method (previously available on the resolver root but
  undeclared on the facade).
- **`@seo($seoData)`** — the Blade directive accepts a `SEOData` as well as a
  `Model`/`null`. `@seo($model)` and `@seo($seoData)` produce an equivalent tag
  set for equivalent data.
- **`seo.title_suffix_skip_when_contains`** (default `[]`) — a brand-aware skip
  list. When the resolved title already contains one of these tokens as a whole
  word (case-insensitive), the `title_suffix` is not appended, avoiding a
  redundant double-brand title. Applies to both the model and `SEOData` paths.

#### Behaviour

- A supplied `SEOData` is treated as **explicit intent**: every value you set is
  preserved, and only **absent** fields are filled before rendering —
  `canonical`/`og:url` derived from the current URL, the `title_suffix` applied
  when missing (honoring the new skip list), relative `og:image`/`twitter:image`
  absolutized via `url()` (not `secure_url()`, which would force HTTPS and break
  non-HTTPS/dev), and `og:site_name`/`locale` filled from config/app. The DB
  precedence chain (global/model-type/route/`seo_meta` defaults) is **not** merged
  into a hand-built `SEOData`.
- **`TagRenderer` is unchanged.** Preparation of a `SEOData` happens before the
  renderer, never inside it, so direct `TagRenderer::render($data)` callers are
  unaffected.

This is an **additive minor** (the new config key defaults to current behavior).
The facade/directive now accept `SEOData` as **new public API** — sibling
packages (`rankbeam/laravel-seo-filament`, `rankbeam/laravel-seo-pro`) that want
to pass a hand-built `SEOData` through the facade/`@seo` should require the core
version that ships this change once it is released.

### Blank explicit-value policy

A persisted blank (`''` / `'   '`) value in a `seo_meta` string column is an
*explicit* value, so the resolver's "last non-null wins" merge lets it override
every lower layer — silently blanking a tag or suppressing the computed
fallback / configured default. This adds an opt-in policy to let those blanks
fall through instead, plus an audit code so the condition is visible even before
you opt in.

#### Added

- **`seo.resolver.blank_is_unset`** (`SEO_BLANK_IS_UNSET`, default `false`) —
  when enabled, the resolver normalizes blank/whitespace **string** fields on
  the stored (explicit) layer to `null` during explicit-value extraction, so
  they fall through to the computed value / default. Only string fields are
  affected: arrays (`tags`, `focus_keywords`, `alternates`), the JSON-LD schema,
  and the literal string `"0"` are never touched. Persistence-layer only — it is
  applied to the freshly-extracted stored `SEOData`, never to the general DTO or
  a higher layer's intentional value. Clearing a field to `null` (e.g. from
  Filament) keeps falling through exactly as before, with the flag on or off.
- **`seo:audit` issue code `blank_explicit_override`** (warning) — the free
  audit reports when a stored `seo_meta` string is blank and would override the
  computed/default value, naming the blank columns. It is surfaced only while
  `blank_is_unset` is **off** (once on, the blanks already fall through), so the
  otherwise-silent condition is observable before you opt in. This is a
  **core-only** code — it is intentionally NOT part of the Pro-mirror
  `MetadataIssues::metadataCodes()` list shared with the Pro scan registry.

#### Behaviour

- With the flag **off** (the default) behaviour is **byte-identical** to today:
  blank explicit strings still override lower layers.
- The default **flips to `true` in the next major (Core 4)**; the
  `SEO_BLANK_IS_UNSET` env var is the escape hatch in either direction. See
  [UPGRADING.md](UPGRADING.md) (“Planned for Core 4”).

This is an **opt-in additive minor**; no public API was added that the sibling
packages need to call, so no minimum-core-version bump is required for them.

## [3.0.0]

The third major. The headline breaking changes are the **contract reset**
(dead score/analyzer API removed — see below) and two output-shape changes that
are breaking for programmatic consumers:

- **`SEO::toArray()` / `SEO::forInertia()` shape change.** The rendered output
  no longer includes a redundant `robots` entry (when the resolved directive
  equals `seo.default_robots`), an empty `canonical`, or empty/null meta
  entries. Any consumer that read `toArray()` and relied on those keys/elements
  always being present must guard for their absence. The new
  `seo.robots.emit_default = true` flag restores the always-emit robots
  behaviour. See [UPGRADING.md](UPGRADING.md).
- The `seo:import-from` importer now **only fills empty fields by default** (it
  never overwrites hand-edited `seo_meta`); pass `--overwrite` to replace
  existing values. See [UPGRADING.md](UPGRADING.md).

### Sitemap image & hreflang extensions

Optional, first-class image and hreflang entries in the generated sitemap,
derived from the data the package already resolves for each record.

#### Added

- **`seo.sitemap.images`** (env `SEO_SITEMAP_IMAGES`, default `false`) — adds a
  Google image-sitemap `<image:image><image:loc>` entry to each `HasSEO` model
  URL, built from the model's resolved og/content image (the same value
  rendered as `og:image`). When a record has no image of its own this resolves
  to the site-wide `default_og_image`.
- **`seo.sitemap.alternates`** (env `SEO_SITEMAP_ALTERNATES`, default `false`) —
  adds `<xhtml:link rel="alternate" hreflang="…">` entries to each `HasSEO`
  model URL from the model's `getSEOAlternates()`. Malformed entries (missing or
  non-string `hreflang`/`href`) are skipped.
- Both extensions degrade gracefully: a hand-built `Spatie\Sitemap\Tags\Url`
  (from `Sitemapable::toSitemapTag()` or a registered source) is passed through
  **verbatim** and never enriched, and a resolver error for one record leaves
  that URL without extensions instead of failing the whole sitemap.

#### Notes

- Both flags are **off by default** and change no existing output when off.
- The package config is now **deep-merged** under the published `config/seo.php`
  (see below), so apps that published the config before this release receive
  these `sitemap` keys (and any future nested defaults) automatically — the env
  vars `SEO_SITEMAP_IMAGES` / `SEO_SITEMAP_ALTERNATES` work without re-publishing.
- Enabling an extension resolves each record's full `seoData()` once per URL —
  built for the scheduled `seo:sitemap` command; benchmark on very large sites.

#### Fixed

- **Published config is now deep-merged.** `SEOServiceProvider` replaced
  Laravel's shallow `mergeConfigFrom()` with a recursive merge that fills
  package defaults UNDER the published `config/seo.php` at every depth. A config
  published before a release that adds nested keys (e.g. `sitemap.images`,
  `sitemap.alternates`, `robots.emit_default`) now receives those defaults — and
  their env vars take effect — without re-publishing. Every value the app set
  (including falsey leaves and replaced lists) is preserved.
- **Defaults resolution no longer re-queries on every call.**
  `SEODefaultsRepository` now memoizes resolved defaults — **including null
  misses** — for the request, keyed by scope/locale. Laravel's
  `Cache::remember()` treats a cached `null` as a miss and re-runs the loader,
  so on the common install with no `seo_defaults` rows every `seoData()`
  resolution was issuing repeated DB/cache lookups that always returned null.
  This is an app-wide per-page cost, amplified by the sitemap extensions above
  (one `seoData()` per record — ~100k redundant lookups on a 50k-URL sitemap).
  `clearCache()`/`refreshCache()` reset the memo so admin updates still take
  effect. The positive-only `tableExists()` memo is unchanged.
- **Editing or deleting an `SEODefault` now invalidates the resolved-defaults
  cache.** The model's save/delete hook previously only forgot the
  `getForScope()` model cache (`default:…`), leaving the repository's separate
  resolved cache (`defaults:…`, 1-hour TTL) stale — so an admin edit could take
  up to an hour to appear. The hook now also calls
  `SEODefaultsRepository::clearCache()`; saving or deleting the `en` row clears
  the **whole scope**, since other locales cache the `en` row as their fallback.

### Importer from competing Laravel SEO packages

A migration path off other Laravel SEO packages, so switching to Rankbeam is a
day's work, not a rewrite.

#### Added

- **`seo:import-from` command** with a pluggable importer registry
  (`Rankbeam\Seo\Importing\ImporterRegistry` + `Contracts\Importer`). New
  sources register a key without touching the command.
- **`ralphjsmit` importer** — reads `ralphjsmit/laravel-seo`'s `seo` morph
  table into `seo_meta`. Idempotent (re-run updates the same row, never
  duplicates), `--dry-run` (report-only), `--model=` scoping, `--locale=`,
  `--table=` / `--connection=` for renamed/legacy sources, `--limit=`, and
  `--json`. Each row is **re-resolved to its live model** so the `seoable`
  relation is correct under the app's current morph map; deleted models are
  skipped, never written as orphans.
- Fields are mapped **explicitly** (`title`, `description`,
  `canonical_url`→`canonical`, `robots`, `image`→`og_image`), with over-length
  values trimmed-and-reported and the unsupported `author` column counted
  rather than copied (Core 3 has no author column). The importer only ever
  fills empty fields — it never overwrites existing `seo_meta` values.
- New guide: [Migrating from other packages](docs/guide/migrate-from-other-packages.md)
  mapping `ralphjsmit`, `artesaos/seotools`, and the Spatie builders onto
  `HasSEO` + `saveSEO()`.

### WordPress importer — Yoast / Rank Math

A migration path for content sites leaving WordPress, reusing the same Laravel-package importer
scaffolding (no command change — new sources just register keys).

#### Added

- **`wordpress-csv` importer** — reads a `url,title,description,canonical,robots,focus_keyword`
  CSV export into `seo_meta`. Columns in any order; unrecognised columns and
  malformed rows (missing `url`, wrong column count) are reported and skipped.
- **`yoast` and `rank-math` importers** — read the live WordPress database
  (`{prefix}posts` + `{prefix}postmeta`) behind `--connection=`, mapping each
  plugin's keys **explicitly** to `seo_meta` (title, description, canonical,
  robots, focus keyword, **and** the OpenGraph/Twitter overrides). Keys with no
  Core 3 home (image IDs, scores, primary-category) are reported as *unmapped*,
  never invented.
- **Model matching.** A WordPress row's slug (URL last segment / `post_name`) is
  matched to a content model via `--model=` on its route key or `--match-by=`.
  Rows matching nothing are reported as **url-only** (honest about what attached
  to a model vs. didn't). New options: `--file=`, `--match-by=`, `--post-type=*`,
  `--redirects-csv=`, `--site-url=`.
- **Template tokens resolved.** Yoast (`%%title%%`) and Rank Math (`%title%`)
  title/description tokens are resolved where derivable (`title`, `sitename`
  from `wp_options`, `sep`) and stripped otherwise — a stored value is never a
  raw token string. Resolution is flagged in the report.
- **Redirects via CSV, not the Pro table.** `--redirects-csv=` emits a
  `source_path,target_url,status_code,note` CSV for import into Rankbeam Pro
  (core never writes the Pro `seo_redirects` table). Sources: a CSV row whose
  `canonical` points to a different path; and Rank Math's redirections table
  (active, exact-match rules only — non-exact rules reported).
- New guide: [Migrating from WordPress](docs/guide/migrate-from-wordpress.md).

### Rendering contract & framework correctness

The single canonical [Rendering Contract](docs/contributing/rendering-contract.md)
every front-end stack's `<head>` must satisfy, plus the renderer fixes and
fast, framework-free unit tests that prove it.

#### Changed

- **Robots is now a configurable policy.** `@seo` / `SEO::render()` /
  `SEO::toArray()` / `SEO::forInertia()` emit `<meta name="robots">` **only when
  the resolved directive deviates from the site default** (`seo.default_robots`).
  A redundant `index,follow` is no longer rendered — its absence already means
  index,follow to a crawler. A deviating directive (`noindex`, `nofollow`,
  `max-snippet:-1`, …) is emitted **verbatim**; the comparison is
  whitespace-insensitive. This is a **rendered-output change** (minor): set the
  new `seo.robots.emit_default = true` to restore the always-emit behaviour. The
  granular `@seoRobots` directive is unaffected (explicit opt-in, always renders).
- **JSON-LD `<script>` tags now carry `data-seo-schema` + `data-seo-url`** so
  client-side navigation (Livewire `wire:navigate`) can find and remove stale
  schema instead of accumulating it. See the new Livewire guide for the
  `livewire:navigated` cleanup snippet.
- `toArray()` / `render()` no longer emit empty/null tags (empty canonical,
  empty-content meta).

#### Added

- **Stable Inertia `head-key`** on every `SEO::forInertia()` / `toInertiaHead()`
  meta and link entry (`name ?? property`, repeatables disambiguated; canonical
  and hreflang keyed). Bind it as `:head-key` so page meta dedupes/replaces
  layout meta across client visits instead of stacking duplicates.
- **`seo.robots.emit_default`** config flag (default `false`).
- Framework guides: a new **Livewire** guide (`@seo` on initial render, the
  `wire:navigate` caveat, the JSON-LD cleanup snippet), an Inertia **Svelte**
  recipe (`<svelte:head>`), a **working JSON-LD-in-Inertia** recipe (replacing
  the broken `@seoSchema($post)`-in-root-Blade doc), and an explicit
  "crawler-visible meta requires Inertia SSR/prerendering" note.
- Renderer-shape contract tests (`tests/Unit/Services/RenderingContractTest.php`)
  proving title/meta-order/robots/dedup-head-keys/canonical≡og:url/script
  presence-absence and cross-renderer semantic parity — fast, required CI.

### Free SEO audit & focus-keyword workflow (additive)

The instant free-tier payoff: a one-command "what's wrong
with my SEO right now" audit and the focus-keyword workflow gate.

#### Added

- **`php artisan seo:audit`** — a free, in-process SEO audit. It runs only the
  **metadata-class** checks (resolvable from the model + the resolver, with no
  page fetch, no queue, and no license): missing / over- / under-length title &
  description, missing OG image, robots conflicts, canonical format /
  cross-domain / shared / insecure, and the optional missing-focus-keyword
  notice. It prints a per-page **pass / warn / fail** table, a summary, and an
  explicit **capability matrix** every run (so its coverage is never mistaken
  for the Pro scan), and it never produces a numerical score. Options:
  `--model` (repeatable), `--locale`, `--limit`, `--issues-only`, `--strict`
  (non-zero exit when any issue is found, for CI), `--json`. Models default to
  `seo.audit.models`, falling back to `seo.sitemap.models`.
  See [the audit guide](docs/guide/audit.md).
- **`Rankbeam\Seo\Auditing\MetadataAuditor`** + `MetadataIssues` (a tiny
  core-side mirror of the Pro registry's metadata codes) + `AuditIssue`. The
  code strings and severities are kept identical to the Pro
  `Rankbeam\Seo\Pro\Scanning\IssueRegistry`, so the free audit, the Pro scan,
  the Filament editor, and exports all agree on what each code means — without
  the free core depending on Pro.
- **`seo.keywords.enabled`** config flag (default `false`) — the focus-keyword
  workflow gate. While off, a page with no focus keyword is **not** flagged.
  Turn it on once you adopt focus keywords and the `missing_focus_keyword`
  notice fires in the free audit, the Pro scan, and the Pro editor — all three
  read this same core flag, so they always agree.
- **`seo.audit.models`** config — the models `seo:audit` audits by default.

### Contract reset

The open-core ownership reset. Core keeps the metadata contract; the numerical
SEO score becomes a Pro-owned scan-result field.

#### Fixed

- Partial resolver overrides no longer reset a computed `og:type=article` to
  `website` or replace an existing `twitter:card`. Stored metadata with no
  explicit social-card values now preserves computed model inference.

#### Added

- `HasSEO::getSEOAlternates(): ?array`, a first-class hreflang resolver hook
  consumed by Blade, Inertia, and JSON rendering.

#### Changed

- `seo_meta.og_type` and `seo_meta.twitter_card` are nullable with no database
  defaults. The renderer still defaults an unset `og:type` to `website`, and
  the resolver base configuration still supplies the default Twitter Card.

#### Removed

- **Breaking:** the dead content-analyzer columns on `seo_meta` —
  `seo_score`, `analysis_report`, `analyzed_at`, `content_snapshot`,
  `content_hash`, `snapshot_at` — and their `seo_meta_score_index` /
  `seo_meta_analyzed_index` indexes. Nothing in the shipped core or Pro ever
  wrote to them. Dropped by a new, idempotent, **irreversible** cleanup
  migration that runs safely on fresh and upgraded (incl. partially-migrated)
  schemas. **`focus_keywords` is kept.** The published `create_seo_meta_table`
  migration is left immutable.
- **Breaking:** the always-null score/analysis public API —
  `HasSEO::getSEOScore()`, `getSEOAnalysisReport()`, `needsSEOAnalysis()`,
  `scopeWithLowSEOScore()`, `scopeNeedingSEOAnalysis()`;
  `SEOMeta::updateScore()`, `scopeLowScore()`, `scopeNeedsAnalysis()`; and the
  `SEOData::$seoScore` / `$analysisReport` properties (with their
  `fromModel`/`fromArray`/`merge`/`toArray`/`toFlatArray` plumbing). These
  read columns that were always NULL.

#### Notes

- The numerical 0–100 score moves to Pro, persisted on a Pro scan-result
  record with a stored `rubric_version` (implemented in a later thread) — it
  will never live in `seo_meta`. See [UPGRADING.md](UPGRADING.md).

## [2.0.1] - 2026-06-13

### Fixed

- SEO defaults are cached as plain arrays instead of objects.
  Laravel 13 ships `cache.serializable_classes => false`, under which
  cached objects come back as `__PHP_Incomplete_Class` — any app using
  SEO defaults with a persistent cache store (database is the Laravel 13
  default) got a 500 once a defaults row existed. Stale object-format
  cache entries are detected and dropped automatically.

## [2.0.0] - 2026-06-12

First release under the `rankbeam` vendor. v2 is the focused "core": meta
resolution, rendering, JSON-LD, and sitemaps. Everything else moved to
dedicated packages — see [UPGRADING.md](UPGRADING.md) for the full map.

### Changed

- **Breaking:** package renamed from `fibonoir/laravel-seo` to
  `rankbeam/laravel-seo`; PHP namespace renamed from `Fibonoir\LaravelSEO`
  to `Rankbeam\Seo`. No class names changed — update imports and re-publish
  the config.
- **Breaking:** derived canonicals (model URL / current URL) now strip the
  query string; explicitly set canonicals are preserved verbatim.
- **Breaking:** `og:image` and `twitter:image` are always emitted as absolute
  URLs, regardless of which resolver layer produced the value (the OG spec
  requires absolute URLs; v1 emitted manual values verbatim).
- Description fallbacks are normalized (HTML stripped, entities decoded) and
  truncated at a word boundary; candidate attributes are configurable via
  `seo.computed.description_fields` and `seo.computed.description_max_length`.
- Sitemap model auto-discovery is skipped for models already covered by a
  named registered source, so registering `'posts'` no longer produces a
  duplicate `sitemap-post.xml` alongside `sitemap-posts.xml`.

### Added

- Laravel 13 support (`illuminate/* ^11.0|^12.0|^13.0`; Laravel 13 requires
  PHP 8.3+).
- `SEO::sitemaps()->register($name, $source)` — programmatic named sitemap
  sources (model classes, closures, or URL arrays) rendered as
  `sitemap-{name}.xml` plus a sitemap index.
- `config('seo.routes.enabled')` — disable the package's `/sitemap.xml`
  routes when serving your own.
- `SEOWarningEvaluator` — title/description length warnings (60/160),
  manual-vs-fallback indicators, and social-image dimension checks for
  admin UIs.
- `SchemaGraph` — Organization/WebSite/WebPage JSON-LD nodes cross-linked
  via stable `@id`s.
- `BreadcrumbSchema::fromModelAncestors()` with an ancestor-loop guard.
- Robots meta can derive from an `is_indexable` model attribute.

### Removed

- **Breaking:** content analyzer (32 rules), readability/stemmer/tokenizer
  support classes, and the `wamania/php-stemmer` dependency.
- **Breaking:** sitewide scanner, redirect manager, 404 monitor, GA4
  analytics, and internal-link suggestions — these live in
  `rankbeam/laravel-seo-pro` (commercial).
- **Breaking:** the `seo:install` stub-publishing flow and all Filament 3 /
  Livewire / Vue / React stubs — Filament admin fields live in
  `rankbeam/laravel-seo-filament` (free).
- **Breaking:** migrations for `seo_redirects`, `seo_404_logs`,
  `seo_scan_runs`, `seo_scan_issues`, `seo_analytics_cache`, and
  `seo_internal_links_index`. The core ships only `seo_meta` and
  `seo_defaults`.

### Fixed

- Sitemap index no longer lists duplicate files when named sources cover a
  model that auto-discovery would also pick up.
- The defaults repository no longer disables database defaults for the whole
  PHP process if it ever ran before the `seo_defaults` table existed (the
  table check latched `false` in a process-wide static — long-running
  queue/Octane workers could permanently lose DB defaults).

### Security

- JSON-LD output (`TagRenderer` and `SchemaCollection::toScript()/toJson()`)
  is encoded with `JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP`,
  so `</script>` in user content cannot break out of the script element.

## 1.0.0 - 2026-01-13

Initial release as `fibonoir/laravel-seo` (the old "full suite" package:
core + analyzer + scanner + redirects + 404 monitor + analytics + UI stubs;
since removed from distribution).

[Unreleased]: https://github.com/rankbeam/laravel-seo/compare/v3.0.0...HEAD
[3.0.0]: https://github.com/rankbeam/laravel-seo/compare/v2.0.1...v3.0.0
[2.0.1]: https://github.com/rankbeam/laravel-seo/compare/v2.0.0...v2.0.1
[2.0.0]: https://github.com/rankbeam/laravel-seo/releases/tag/v2.0.0

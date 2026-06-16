# Rankbeam Improvement Plan (Master)

> **Provenance.** Synthesized from two independent plans (Claude "Plan A",
> Codex "Plan B") plus an adversarial cross-review by each, all grounded in a
> full production swap: the legacy SEO stack of **idi-it** (a ~3-month-old,
> ~20k-visits/day, ~900-page WordPress migration) was removed and rankbeam
> (core + pro + filament) installed in its place. The friction we hit during that
> swap is the backbone of this plan. Working artifacts live in
> `../rankbeam-improvement-plan/` (EVIDENCE.md, plan-claude.md, plan-codex.md,
> review-claude.md, review-codex.md, final-pass-codex.md).
>
> **Validated.** A final Codex adversarial pass on this synthesis surfaced 8 issues
> (cache arrays not objects on Laravel 13; a crawler `max_pages_per_run` default
> contradiction; an undeclared T1→T10 suffix dependency; missing `target` on the
> schema-field APIs; an underspecified schema thread; finding-identity vs cross-run
> failure-confirmation; stale thread numbers) — **all folded in.**

## Thesis

Adoption is won in the **first hour** (composer require → first rendered `<head>`)
and the **first screen** (the Filament SEO panel). idi proves rankbeam can replace
a battle-tested multi-package stack — so this plan front-loads DX papercuts and the
admin UI, then ships the flagship Pro differentiator (the crawler) with real
production hardening, then depth. The goal is not "more features" — it's making the
common path so smooth, and the first impression so good, that reaching for three
packages + glue (what idi originally did) feels obviously worse.

## Highest-ROI moves (ranked)

1. **Render a hand-built `SEOData` everywhere** (T1) — the single most frequent
   papercut; model-less pages are everywhere.
2. **Editorial Filament preview** (T5) — the visible "wow" on the first resource a
   dev opens.
3. **Broken-link crawler in Pro** (T6/T7) — a concrete reason to pay, battle-tested
   at 20k/day; the requested bonus.
4. **Production / background-jobs guide + `seo-pro:install`** (T8) — removes the
   "can I run this at scale?" doubt.
5. **WordPress→rankbeam runbook + importer hardening** (T9) — the largest switching
   audience; idi is a 900-page proof.
6. **Correctness + related-model + fallback/schema depth** (T2, T3, T4, T10, T11) —
   the moat that survives scrutiny by SEO-serious teams.
7. **Resolver caching + positioning** (T12, T13) — scale credibility and the
   narrative that makes it *non-negotiable for other devs*.

## Release roadmap (so behavior changes don't surprise anyone)

- **Additive minors (Core 3.x / Pro 2.x / Filament 1.x):** T1, T3, T4, T5, T6, T7,
  T8, T9, T10, T11, T12, T13, and the *opt-in* half of T2. New config keys default
  to **current** behavior.
- **Next major (Core 4 / Pro 3):** flip T2's `blank_is_unset` default to `true`;
  reconsider an optional stored `author` field; any other default flips. Ship with
  an UPGRADING entry and package compatibility constraints.
- Every behavior-affecting thread names its release boundary below.

---

## Sequence & threads

Order is dependency-driven: rendering → correctness → related-target → preview →
crawler engine → crawler UI → install/ops → migration → depth → caching/positioning.

### T1 — Accept `Model | SEOData | null` across the render surface · core · S
> ✅ **DONE** — core commit `976cb23` (not pushed). Render surface + `@seo` accept
> `Model|SEOData|null` via an internal `prepare()`; `url()`-not-`secure_url()`;
> brand-token suffix skip; 20 tests; suite green. Release-ready, not tagged.

**Goal.** Model-less pages (listings, static, search, controller-composed) should
use the facade/directive instead of `app(TagRenderer::class)->render($seoData)`.
This was the #1 friction in the idi swap.
**Scope.**
- `SEO::render()`, `SEO::toArray()`, `SEO::forInertia()` accept `Model|SEOData|null`.
- `@seo($x)` delegates to the facade and accepts a `SEOData`.
- Add an **internal** `prepare(SEOData): SEOData` in the facade/resolver path (do
  NOT make it public API yet — once public its transforms become a compat contract).
- **`TagRenderer` stays verbatim** — preparation happens before it, never inside it.
  Direct `TagRenderer::render($data)` callers are unaffected.
**Preparation rules (per field, explicit — resolves the A/B inconsistency).** For a
supplied `SEOData`, *preserve every supplied value*; only **fill absent** fields:
derive `canonical`/`ogUrl` from the current URL if null; apply `title_suffix` only
when the title lacks it, honoring a brand-aware skip list introduced **here** —
the additive key `seo.title_suffix_skip_when_contains` (token-aware, word-boundary,
case-insensitive), so T1 has no dependency on T10; absolutize relative `ogImage`/
`twitterImage` using **`url()`/the app's configured URL generator — NOT
`secure_url()`** (secure_url forces HTTPS and breaks non-HTTPS/dev). Apply
site-level identity (`og:site_name` from config) if absent. Do **not** merge the
DB precedence chain (route/model-type/`seo_meta`) into a hand-built `SEOData` — it is
explicit intent.
**API.**
```php
@seo($post)  @seo($seoData)  @seo()
SEO::render($modelOrSeoDataOrNull, ?string $route = null, ?string $locale = null): string
SEO::toArray(...); SEO::forInertia(...);
```
**Acceptance.** `@seo($seoData)` renders the same tag set as `@seo($model)` for
equivalent data; existing model/null calls semantically unchanged (Rendering
Contract tests cover model, route, and SEOData paths); relative images become
absolute via the configured URL generator; suffix never duplicated; direct
`TagRenderer` calls remain verbatim.
**Release.** Additive minor. **Open-core:** core.

### T2 — Blank explicit-value policy (opt-in now, default-flip later) · core · S
> ✅ **DONE** — core commit `5e18ec7` (not pushed). `seo.resolver.blank_is_unset`
> (default `false`, flips to `true` in Core 4) normalizes blank explicit STRING fields
> on the stored layer; `blank_explicit_override` audit code (core-only, kept out of the
> Pro-mirror) makes it observable with the flag off; 15 tests; suite 367 green.
> Release-ready (CHANGELOG + UPGRADING §12), not tagged.

**Goal.** A persisted `''`/`'   '` currently overrides lower layers (it bit the
swap). Make blank explicit strings fall through — without changing published
semantics in a minor.
**Scope.** Normalize blank/whitespace **string** fields (never arrays/schema) to
`null` during explicit-value extraction in the resolver (persistence-layer
extraction, not on the general DTO). Add `seo:audit` issue code
`blank_explicit_override` so the condition is observable now.
```php
'resolver' => ['blank_is_unset' => env('SEO_BLANK_IS_UNSET', false)], // flip to true in Core 4
```
**Acceptance.** Flag off → blanks still override (current behavior); flag on →
blanks fall through; `"0"`, non-empty, arrays, schema untouched; Filament's
clear-to-`null` still works; UPGRADING documents the Core 4 default flip.
**Release.** Opt-in in 3.x; default `true` in Core 4. **Open-core:** core.

### T3 — robots/`is_indexable`: verify + document (already implemented) · core · S
> ✅ **DONE** — core, branch `feat/rankbeam-improvements`, **not committed**
> (working-tree only). Docs + tests only, **no code/API/behaviour change**. Verified
> `computeRobots()` (getSEORobots hook > `is_indexable`) against `TagRenderer`'s
> emit policy; added 5 end-to-end characterization tests
> (`ResolverPrecedenceCharacterizationTest`) proving resolve→render emits/suppresses
> the right `<meta robots>` (incl. indexable⇒suppressed, getSEORobots verbatim).
> Documented the hook + `is_indexable` derivation + emit policy in the HasSEO trait
> docblock, `docs/concepts/resolver-precedence.md`, README, CHANGELOG. Suite 372
> green (was 367). Not tagged/published.

**Goal.** Close the README/perception gap. **Correction from review:**
`SEOComputedBuilder::computeRobots()` ALREADY honors a `getSEORobots()` hook and
derives `noindex,nofollow` from an `is_indexable` attribute (with tests). This is
*documentation/verification*, not implementation.
**Scope.** Verify the existing behavior against the robots-emit policy; document
the `getSEORobots()` hook + `is_indexable` derivation prominently in the HasSEO/
recipes docs (the trait doesn't expose it, so it reads as missing). Add a
characterization test if any path is uncovered.
**Acceptance.** Docs and code agree; a non-indexable model demonstrably renders
`noindex,nofollow`; hook documented.
**Release.** Additive minor (docs). **Open-core:** core.

### T4 — Filament target abstraction (edit SEO for a related model) · filament · M
> ✅ **DONE** — laravel-seo-filament, branch `feat/rankbeam-improvements`, **not committed**
> (working-tree only). Optional `target` resolver `Closure(?Model $formRecord): ?Model` on
> `SEOFields::make()`/`seoSection()` **and** `SEOSchemaFields::make()`/`seoSchemaSection()`,
> shared via a new `Support\ResolvesSeoTarget` trait (evaluated through Filament's closure
> evaluator; null ⇒ the form's own record; null-resolved ⇒ tolerated, no write, no
> placeholder; a non-null target lacking `seoMeta()` ⇒ clear `RuntimeException`). The same
> resolved target drives hydration, save, source indicators, the snippet preview, AND the
> structured-data editor (schema persistence honors the same target — it no longer writes
> to the wrong model). Additive (no `target` = today's behavior; no new core requirement).
> 17 tests (`tests/Unit/ResolvesSeoTargetTest.php` 7 + `tests/Feature/TargetResolutionTest.php`
> 10: create/edit/clear/locale/missing-target/schema-on-target/auto-breadcrumb-from-target/
> sources-reflect-target). Full suite **green on BOTH Filament 4 (94) and Filament 5 (95)**
> via the CI composer toggle. CHANGELOG Unreleased. Release-ready, not tagged.

**Goal.** idi stores an entity's SEO on a related `PublicPage` and shares it; it had
to wrap `SEOFields` with a `->model(closure)`. Make one nullable related-target
resolver the shared spine for hydration, save, source indicators, schema fields,
**and** the preview (T5). Sequenced **before** the preview because preview
correctness depends on resolving the same target used for persistence.
**Scope.** Add a `target` resolver to `SEOFields::make()` / `seoSection()` **and**
`SEOSchemaFields::make()` / `seoSchemaSection()` — schema persistence is currently
independent (see `SEOSchemaFields`/`HasSEOFields`), so it must honor the same
resolved target or schema would write to the wrong model. Signature must tolerate
create-form nullness; evaluate via Filament's closure evaluator. Reject a non-null
target lacking `seoMeta()` with a clear exception. Never auto-create a placeholder
related model.
**API.**
```php
SEOFields::make(only: [...], target: fn (?Model $record): ?Model => $record?->publicPage);
static::seoSection(target: fn (?Model $record): ?Model => $record?->publicPage);
SEOSchemaFields::make(target: fn (?Model $record): ?Model => $record?->publicPage);
static::seoSchemaSection(target: fn (?Model $record): ?Model => $record?->publicPage);
```
**Acceptance.** A resource for model A edits model B's locale-scoped `seo_meta`; A
gets no `seo_meta` row; create forms tolerate a not-yet-existing relation; sources/
preview reflect the target; F4 + F5 tests cover create/edit/clear/locale/missing.
**Release.** Additive minor. **Open-core:** filament (free adoption driver).

### T5 — Editorial Filament preview (SERP + social) · filament · M
> ✅ **DONE** — laravel-seo-filament, branch `feat/rankbeam-improvements`, **not committed**
> (working-tree only). Tabbed **Google SERP / social card** live preview replaces the
> search-only view. New `Support\SEOPreviewData` reuses `SEOFieldSources` so the social
> image follows resolver order, maps Filament-upload paths to URLs, and detects known-local
> dimensions (`getimagesize`); explicit image-dimension states (known-local / browser-
> measured / unavailable) — a failed remote image never breaks the form. Warnings reuse the
> core `SEOWarningEvaluator` constants (60/160/200×200/1200×630); live source labels reflect
> the unsaved form. Default-on; `SEOFields::make(showPreview: false)` opts out. Scoped CSS
> (test-asserted no global selectors), dark-mode. +21 tests → suite **116 green on BOTH
> Filament 4 (4.11.7) and 5 (5.6.7)**. CHANGELOG Unreleased + new UPGRADING (published-view
> shadowing note). Release-ready, not tagged.

**Goal.** Replace the search-only preview with idi's production-proven tabbed
(Google / social) **live** editor: char counters, title>60 / desc>160 warnings,
social-image dimension checks (min 200×200, ideal 1200×630), explicit-vs-fallback
labels — the first-screen "wow."
**Scope.** First **replace the existing preview view** (extract a standalone
component only after T4's shared target/state resolution exists). Reuse core
threshold constants (`SEOWarningEvaluator`) so audit/preview/scan agree. Image
dimensions have **explicit states**: *known-local* (server `getimagesize`),
*browser-measured* (client-side, tolerant of CORS/signed-URL/private-disk/temp-upload
failure), *unavailable* — never block the form on a failed remote image. Source
labels must overlay **unsaved form state** without claiming it came from the DB or
the computed resolver. Default-on; `SEOFields::make(showPreview: false)` to opt out.
Dark-mode + scoped CSS (no global selectors) + F4/5.
**Acceptance.** Live updates on edit; social image order matches the resolver;
warnings use shared constants; failed remote image doesn't break the form; labels
distinguish unsaved/explicit/computed; tests cover tabs, fallback/manual state,
warnings.
**Release.** Additive minor. **Open-core:** filament.

### T6 — Pro broken-link crawler ENGINE (headless) · pro · L
> ✅ **DONE** — laravel-seo-pro commit `2ad51f9` (branch `feat/rankbeam-improvements`,
> **not pushed**). Headless bounded/resumable crawler complete and correct with NO
> Filament: `SeoPro::brokenLinks()->scan(BrokenLinkScope)/cancel()/latestRun()/
> unresolvedFindings()` + all five `seo-pro:broken-links-{scan,status,cancel,recover,
> prune}` commands (the recover/prune commands + the `seo-pro.broken_links` config
> block + command registration were the gaps in the prior work-tree; added here).
> Dedicated **publish-only** tables `seo_broken_link_scan_runs` /
> `seo_broken_link_findings` (hash-indexed canonical URLs, never overloads
> `seo_scan_runs`); finding identity is canonical `(source,target)` durable across
> runs; **cross-scan** N-consecutive failure confirmation (reset on success); stale
> resolution only after a successful source fetch; one-active-run-per-scope via a DB
> lease+heartbeat with dead-worker recovery + idempotent continuation; conservative
> finite caps + hard per-job time budget (null = explicit unlimited opt-in); SSRF on
> the seed fetch and **every redirect hop** (reuses Pro's hardened `SsrfGuard`; added
> an additive optional redirect-chain out-param so hops are recorded). Disabled by
> default. **Found + fixed a real latent bug:** the engine used
> `symfony/dom-crawler` without declaring it (absent in the package's own env → link
> parsing silently returned nothing); added the dependency + switched the parser to
> XPath. Also fixed: relative `--url` seeds were silently dropped (now absolutized);
> three `broken_links.*` config keys needed `?? `-inherit (a published `null` was
> disabling the body cap / blanking the UA). **94 new Pest tests** (engine suite)
> incl. the full SSRF matrix (DNS rebinding, IPv6, redirect-to-internal,
> credentials-in-URL, oversized bodies, decompression bombs, app-host exception) +
> a separate **~900-page benchmark suite** (excluded from the default run). Full
> suite **637 passed / 3 skipped** (baseline was 543/3 — no regressions); Pint-clean.
> T7 (the Filament monitoring UI) builds on this.

**Goal.** The flagship Pro feature + requested bonus. Port idi's bounded, resumable
crawler. **Headless and correct without Filament.**
**Scope / hardening (the bar both reviews set).**
- Dedicated tables **`seo_broken_link_scan_runs`** + **`seo_broken_link_findings`**
  (do NOT overload `seo_scan_runs`). Index unresolved-findings, status, last-seen,
  run-status, and **a hash of source/target URLs** (don't index full URLs on
  constrained MySQL).
- **Finding identity / URL canonicalization:** normalize scheme, host casing,
  default ports, fragments, trailing slashes, query policy, and redirect targets
  before dedup. Separate `fromModel()`, `toUrl()`, `forSourceUrl()` queries (don't
  conflate "links from" vs "links to").
- **Concurrency:** one active run per site/scope via a DB lease/uniqueness;
  atomic cursor claims; idempotent continuation dispatch; cancellation tokens;
  recovery leases for dead workers.
- **Bounded jobs:** batch page/link caps with **conservative finite defaults**
  (`max_pages_per_run => 2000`, etc.); `null` is an explicit operator opt-in to
  unlimited, **never the default**. A hard time budget means *no NEW fetch after the
  budget*; in-flight requests are bounded by request/connect timeouts (you can't
  kill mid-request).
- **Failure confirmation is cross-scan, not per-run:** `mark_broken_after_failures`
  needs a **durable per-(canonical source,target) failure counter + last-status
  history** that survives across runs; a link is marked broken only after N
  *consecutive* failed checks and the counter resets on success. (Define explicitly
  that confirmation is consecutive scans, not retries within one run.)
- **HTTP governance:** request+connect timeouts, response-size cap, accepted
  content-types, user agent, per-host delay/throttle, retry classes,
  external-domain rate limits, redirect-hop following with `mark_broken_after_N`.
- **Security:** reuse Pro's `SsrfGuard` on every request **and every redirect hop**;
  test matrix must include DNS rebinding, IPv6, redirect-to-internal,
  credentials-in-URL, oversized bodies, decompression bombs, and the app-host
  exception.
- Retention pruning, broken-count alert threshold, stale-finding resolution **only
  when the source page was fetched successfully**.
- Seed from registered `SeoPro::targets()` + sitemap registry; allow extra sources.
- Commands: `seo-pro:broken-links-{scan,status,cancel,recover,prune}`.
```php
'broken_links' => ['enabled' => false, 'queue' => ['connection' => null, 'name' => 'seo-broken-links'],
  'scope' => 'internal_only', 'max_pages_per_run' => 2000, 'max_links_per_page' => 200, // null = explicit unlimited opt-in
  'batch' => ['max_pages_per_job' => 50, 'max_links_per_job' => 1500, 'dispatch_delay_seconds' => 1, 'hard_time_budget_seconds' => 180],
  'mark_broken_after_failures' => 3, 'retention' => ['scan_runs_days' => 90, 'resolved_findings_days' => 30],
  'recovery' => ['stuck_scan_timeout_hours' => 2], 'alerts' => ['broken_count_threshold' => null]];
SeoPro::brokenLinks()->scan(BrokenLinkScope::InternalOnly); ->cancel($run); ->latestRun(); ->unresolvedFindings();
```
**Acceptance.** A ~900-page generated fixture completes across bounded jobs; no job
exceeds page/link caps or starts a fetch past the budget; every hop SSRF-validated;
findings upserted by **canonical (source,target) identity, durable across runs**,
with per-run last-seen + a persistent failure counter; transient failures need N
consecutive-scan confirmations; cancellation + dead-worker recovery reach coherent
terminal states;
re-scan resolves stale findings only after a successful source fetch; **works with
no Filament installed**.
**Release.** Additive minor; feature `enabled=false` by default. **Open-core:** pro.

### T7 — Pro broken-link MONITORING UI · pro · L
> ✅ **DONE** — laravel-seo-pro commit `e425192` (branch `feat/rankbeam-improvements`,
> not pushed). `BrokenLinkFindingResource` + live-**progress** / cached-**stats** /
> bounded-**trend** widgets registered via `SeoProPlugin`; the whole surface is hidden
> when `broken_links` is disabled (resource `canViewAny`/`shouldRegisterNavigation` +
> each widget's `canView`). Start/cancel/scope scan actions + resolve/reopen. A
> Filament-free `BrokenLinks\SourceEditor\SourceEditorResolver` registry (request-scoped
> singleton) maps a finding → the owning model's Filament edit URL — apps register
> closures or `registerModelResource(Model, Resource)` (generic port of idi's
> `BrokenLinkSourceResolver`); "Open source editor" appears only when a resolver
> matches. **"Create redirect" only for an internal broken target** (`target_internal`)
> — an outbound dead link gets the source-editor action instead — reusing
> `SEORedirect::validateTargetUrl()` + cycle detection, then resolving the finding.
> View gated on `view_dashboard`, every management action on `manage_seo` (new shared
> `Support\SeoProGate`). Widget aggregates are cached + bounded (grouped COUNT, one
> latest-run row, last-20-run trend, 15s-cached nav badge). Findings surface on the SEO
> dashboard as a **summary panel only** — never written into `seo_scan_issues` or the
> `ScoreRubric` (score semantics unchanged). +33 tests (8 engine + 25 Filament on F5;
> permissions, actions, polling, empty states, nav gating); full suite **670/3 green**
> (was 637/3 after T6). CHANGELOG Unreleased; Pint-clean. Release-ready, not tagged.

**Goal.** Expose findings as actionable work. Requires T6.
**Scope.** `BrokenLinkFindingResource` + progress/stats/trend widgets registered via
`SeoProPlugin` (nav hidden when disabled); start/cancel/scope actions; a
source-editor registry resolving a finding → owning model's edit URL; **"Create
redirect" only when the broken target is an internal path the app owns** (a broken
*outbound* link normally needs the source page edited — don't offer a redirect
there); redirect creation uses Pro's `SEORedirect` + open-redirect/path validation;
Pro authorization gates; **cached aggregates + bounded queries** for widgets.
Surface findings in the SEO dashboard as a **summary panel** — but do NOT write them
into `seo_scan_issues` or the 0–100 `ScoreRubric` (different lifecycle/uniqueness;
score semantics unchanged).
**Acceptance.** Nav hidden when disabled; operators can scan/cancel/filter/resolve/
open-source-editor/create-eligible-redirects; widgets don't run unbounded queries;
F4/5 tests cover permissions, actions, polling, empty states.
**Release.** Additive minor. **Open-core:** pro.

### T8 — Install & operations: `seo-pro:install`, doctor, production guide · pro + docs · M
> ✅ **DONE** — laravel-seo-pro commit `7baca2c` + docs commit `b6c68a9` (branch
> `feat/rankbeam-improvements`, **not pushed**). Four deliverables, all additive:
> (1) **`seo-pro:install`** publishes config + the publish-only migrations and runs
> `migrate`, then prints next steps; idempotent (`--force` / `--no-migrate`).
> Migrations stay **publish-only by design** — auto-loading would duplicate
> histories for anyone who already published (documented prominently). (2) Extended
> the existing **Pro-owned `seo:doctor`** (no competing Core command): per-workload
> queues (scan + crawler, `sync` warning), the crawler's tables/cap-scope sanity/cURL
> when an outbound feature is on, a leftover legacy `seo` table, a non-rankbeam
> `config/seo.php` collision, and **recent heartbeat evidence** (stale scan/crawl
> runs — explicit that it validates config + run history and CANNOT prove a cron
> runs). **`--json` now exposes a stable `id` per check**; every prior message kept
> verbatim (existing tests untouched). (3) **Operational telemetry** (`seo-pro.telemetry`,
> default on, channel/level configurable, best-effort): one structured completion
> line per finished run for BOTH pipelines with counts + timings only (pages fetched,
> links checked, **blocked URLs**, **transient failures**, issues, duration, **queue
> lag**) — new `Telemetry\ScanMetrics` + `ScanTelemetry` + subscriber, new
> `Events\SeoScanCompleted` (the on-page mirror of `BrokenLinkScanCompleted`), and a
> `metrics()` accessor on both completion events; the crawler job now accumulates
> per-run `blocked`/`transient` in `meta.metrics`. (4) **Production Setup guide**
> (`docs/pro/production`, ONE authoritative page): dedicated queues per workload
> (Redis + Supervisor/Horizon), scheduler with a cadence for **every** recurring
> command, batch tuning, retry/recovery, retention, safe rollout, telemetry,
> Filament-independent deployment — modeled on the idi topology. **+28 Pest tests**
> (install, doctor ops checks, telemetry, crawler metrics); suite **698/3** (was
> 670/3 — no regressions); Pint-clean (`--dirty`); VitePress builds clean. CHANGELOG
> Unreleased + UPGRADING + Pro README + installation/headless docs updated.
> `seo-pro:install` is the documented install path. GOTCHA: a 1-target run's
> `failed()` now finishes the run → fires `SeoScanCompleted` telemetry, so two
> strict `Log::shouldReceive('error')` scan tests gained a tolerant
> `Log::shouldReceive('log')`. Nothing pushed; next thread per the table.

**Goal.** The author's explicit ask; removes scale doubt and the publish-then-migrate
footgun.
**Scope.**
- **`seo-pro:install`** — publishes config + migrations and prints next steps.
  **Do NOT auto-load Pro migrations** (users who already published would get
  duplicate histories). Document the publish-only ownership prominently.
- Extend the **Pro-owned `seo:doctor`** (it already lives in Pro — do not add a
  competing Core command): checks for enabled-feature tables, queue connection/names,
  cURL availability + broken-links config, a suspicious **legacy `seo` table after a
  completed import**, and `config/seo.php` collision/coexistence. Doctor validates
  *configuration + recent heartbeat evidence*, not that an external cron actually
  runs (it can't prove that). `--json` exposes stable check IDs.
- **Production Setup guide** (one authoritative page, not duplicated per package):
  dedicated queues per workload (separate `QUEUE_SEO` and broken-links workers; Redis
  example), scheduler entries (sitemap, scan, broken-links-scan, prune, redirect-hit
  flush) for Laravel 11/12/13, batch tuning, Horizon/Supervisor, retry/failure,
  recovery, retention, safe rollout order, and Filament-independent deployment.
- **Operational telemetry:** structured completion events + metrics (pages fetched,
  links checked, blocked URLs, retries, duration, queue lag, stale runs).
**Acceptance.** `composer require` → `seo-pro:install` → `migrate` works with no
silent no-op; every recurring command has a recommended cadence; doctor reports
stable IDs; guide reproduces idi-scale operation.
**Release.** Additive minor. **Open-core:** pro (install/doctor) + shared docs.

### T9 — WordPress / legacy migration hardening + redirect handoff · core + pro + docs · M
> ✅ **DONE** — core commit `5316a39` + pro commit `5705921` (branch
> `feat/rankbeam-improvements`, **not pushed**). Three legs, all additive:
> **(Core)** an import **verification report** — `seo:import-from` now prints, and
> exposes in `--json` under a `verification` key, a matched / url-only / truncated /
> unmapped breakdown; **url-only is a first-class count** and unmapped fields now
> capture their distinct **values** (`unmapped_values`) — above all **every `author`
> value** (ralphjsmit's author column + WordPress `post_author` resolved to a display
> name via a best-effort `{prefix}users` lookup), so authors are re-homed via
> `getSEOAuthor()` rather than lost silently. New `ImportResult::urlOnly()`/`matched()`/
> `verification()`; `unmapped($field, ?$value)` captures bounded distinct values
> (`MAX_UNMAPPED_VALUES = 500`). **No command-option change** (verified the surface;
> the report is additive output). Anonymized **~900-page WordPress corpus** generator
> (`tests/Fixtures/WordPressCorpus.php`, deterministic Yoast/Rank Math, returns a
> ground-truth manifest) + committed `tests/Fixtures/wordpress/anonymized-export.csv`;
> +19 core tests (unit `ImportResultTest`, `WordPressCorpusImportTest` 900-scale
> idempotency/verification/locale/morph-alias/overwrite, `WordPressCsvCorpusImportTest`)
> — full core suite **391 green** (was 372 with T3 in tree; no regressions).
> **(Pro)** new **`seo-pro:redirects-import {file}`** consuming the core writer's
> **redirect CSV format v1** (`source_path,target_url,status_code,note`); reuses
> `SEORedirect::validateTargetUrl()` + the model's saving-hook cycle detection, and
> gets dry-run parity (loops + CSV-internal duplicates included) by running the real
> inserts inside a transaction it rolls back. Rejects malformed rows, invalid status
> codes, unsafe external targets, duplicate sources, and loops — with a reason each;
> idempotent, fill-new-only (`--overwrite` to replace), `--dry-run`/`--json`. Registered
> in `SeoProServiceProvider`; +15 Pro tests incl. a **round-trip from core's
> `RedirectCsvWriter`** — full Pro suite **713/3 green** (was 698/3). **(Docs)** new
> [WordPress migration runbook](docs/guide/wordpress-migration-runbook.md) — coexist →
> import meta → import redirects → verify with `seo:audit --strict` → explicit
> verification BEFORE removing the legacy package/table; sidebar + cross-links; VitePress
> builds clean. CHANGELOG `[Unreleased]` in **both** repos notes redirect CSV format v1
> so the open-core contract is traceable. Pint-clean on changed files. Release-ready, not
> tagged. (T3's prior uncommitted core working-tree was left fully intact — the T9
> CHANGELOG entry was precise-staged so the commit carries only T9.)

**Goal.** Make rankbeam the lowest-risk replacement for a legacy SEO stack; idi is a
real ~900-page WP proof.
**Scope.**
- **Core:** anonymized fixtures derived from idi's WP corpus; expand token /
  malformed-data / morph-map / locale / idempotency tests for yoast/rank-math/csv
  importers; an import **verification report** (matched / url-only / truncated /
  unmapped — incl. every unmapped author value); importers stay idempotent /
  fill-empty-only unless `--overwrite`; dry-runs write nothing. **Name actual
  supported command options** (verify against the importer surface) or include the
  command-API change explicitly.
- **Pro:** a validated **redirect CSV importer** (`seo-pro:redirects-import`)
  matching Core's `RedirectCsvWriter` output; reject loops, malformed rows, unsafe
  external targets, duplicate sources. **CSV is the open-core boundary contract.**
- **Docs:** a WP→rankbeam runbook (coexistence → import meta → import redirects →
  verify with `seo:audit --strict` → explicit verification before removing the
  legacy package/table).
**Acceptance.** Importing a real Yoast/Rank Math export populates `seo_meta` + emits
a CSV Pro consumes; dry-runs are inert; verification report is accurate; runbook
reproducible end-to-end.
**Release.** Additive minor. **Open-core:** core (import) + pro (redirect import) + docs.

### T10 — Optional computed-fallback depth · core · M
> ✅ **DONE** — core, branch `feat/rankbeam-improvements`, **working-tree only**
> (not committed — left alongside the still-uncommitted T3 core tree, which it
> overlaps in `HasSEO.php` + `CHANGELOG.md`, to avoid mixing scope; releases are
> batched per the runbook). Opt-in, additive, default behaviour byte-identical.
> New `getSEOImages(): iterable` hook on `HasSEO` (returns `SEOImageCandidate`
> objects or plain URL strings, default `[]`) + immutable `Data\SEOImageCandidate`
> (`make($url)->priority(100)`) + `seo.computed.image_selection` config
> (`strategy` `first`|`best`, `minimum_width|height` 200, `ideal_width|height`
> 1200×630). The default `first` strategy is untouched — `firstComputedImage()`
> is the verbatim old body, no image opened/measured. The opt-in `best` strategy
> scores an ordered candidate list (getSEOImage() **stays highest-priority**, then
> getSEOImages(), then common fields / content / default), **skips undersized
> (<min)**, and picks the candidate with the smallest squared-Euclidean distance
> to the ideal (ties → higher priority → earlier). **LOCAL images only** — extracted
> the established resolver into `Services\LocalImageInspector` (one source of truth,
> shared by `SEOWarningEvaluator` which now delegates to it), so a remote URL is
> never fetched and only acts as a fallback; when nothing local qualifies it falls
> back to first-match (never blanks). `best` default thresholds reference
> `SEOWarningEvaluator`'s constants so audit/preview/selection agree. og:type /
> article-metadata gating unaffected (asserted). +15 Pest tests
> (`tests/Unit/Services/ImageSelectionTest.php`); full core suite **406 green**
> (was 391; no regressions). CHANGELOG `[Unreleased]`; additive minor (no
> UPGRADING). Release-ready, not tagged.

**Goal.** Close the remaining gap vs idi's resolver — **without** rebuilding what
exists (`getSEOOgType()`, dates, `getSEOAuthor()` already ship).
**Scope (opt-in, additive).**
- Multi-candidate **social-image selection by dimension closeness** to a configurable
  ideal (1200×630), skipping undersized (<200×200), over an ordered candidate list.
  **Local images only** in Core (no remote fetch — SSRF/latency/cache); remote checks
  are the preview's client-side job (T5). Default strategy stays first-match.
- (Brand-aware title-suffix suppression ships in **T1**, which owns suffix
  application and the `seo.title_suffix_skip_when_contains` key — not duplicated here.)
```php
public function getSEOImages(): iterable { return [SEOImageCandidate::make($this->hero)->priority(100), ...]; }
'computed' => ['image_selection' => ['strategy' => 'first', 'minimum_width' => 200, 'ideal_width' => 1200, ...]];
```
**Acceptance.** `getSEOImage()` stays highest-priority/unchanged; opt-in scoring
prefers best-sized + skips undersized; brand titles don't double-suffix; article
metadata still only for `og:type=article`.
**Release.** Additive minor. **Open-core:** core.

### T11 — Schema composition polish (build only the missing layer) · core · S/M
> ✅ **DONE** — core, branch `feat/rankbeam-improvements`, **working-tree only**
> (not committed — left alongside the still-uncommitted T3 + T10 core tree, which
> it overlaps in `HasSEO.php` / `config/seo.php` / `CHANGELOG.md`, to avoid mixing
> scope; releases are batched per the runbook). Additive minor, no UPGRADING.
> Built ONLY the missing composition layer on top of the existing `SchemaGraph` +
> loop-guarded `BreadcrumbSchema::fromModelAncestors()` (neither rebuilt):
> **(1)** new `Services\Schema\SchemaGraphBuilder` + a `SchemaGraph::for($model)`
> static factory — fluent `->organization()->website()->webPage()
> ->breadcrumbFromAncestors()->toArray()`; `webPage()` resolves the subject's
> `seoData()` (or a passed `SEOData`), `breadcrumbFromAncestors()` DELEGATES to the
> existing breadcrumb (no parallel API), `add()` accepts any pre-built node; empty
> nodes skipped; deterministic stable @ids. **(2)** `HasSEO::getSEOSchema(): array`
> hook (default `[]`). **(3)** `seo.schema.type_map` config (model class → builder:
> invokable class-string / Closure / callable; exact match then `instanceof` for
> subclasses). **Precedence wired in `SEOResolver` as a final Layer 6
> (`applyModelSchema`)**: an explicit stored `seo_meta.schema_jsonld` (or a
> default-layer schema — defaults map `schema_defaults`→`schema_jsonld`) is
> AUTHORITATIVE, emitted as-is, and the hook/type-map is NOT invoked (asserted via
> a call-counter spy); only when absent does the hook (preferred) or type-map
> produce the graph. A **static re-entrancy guard** keyed by model+locale breaks
> the cycle when `getSEOSchema()` composes `webPage()` (which re-resolves
> `seoData()`). Validates (every composed node passes `SchemaValidator`). +18 Pest
> (`Unit/Services/SchemaGraphBuilderTest` 8 + `Feature/SchemaCompositionTest` 10:
> cross-linked WebPage+BreadcrumbList graph, stored-schema-wins/hook-not-called,
> type-map fallback + hook-over-map + subclass match, plain-model-unchanged).
> Full core suite **424 green** (was 406 with T10 in tree; no regressions). Pint
> NOT run (repo has no pint.json — hand-matched style per the standing gotcha).
> CHANGELOG `[Unreleased]`. Nothing tagged/published.

**Goal.** **Correction from both reviews:** Core already has `SchemaGraph` and a
loop-guarded `BreadcrumbSchema::fromModelAncestors()`. Do NOT rebuild them. The real
gap is *composition ergonomics* — assembling an `@id`-linked Organization/WebSite/
WebPage graph + per-entity-type schema mapping with minimal app glue (idi hand-rolled
`SitewideSchema`).
**Scope.** A `SchemaGraph` composition helper + a per-model `getSEOSchema(): array`
hook + an optional config type-map (model class → builder), all ON TOP of the
existing `SchemaGraph` and `BreadcrumbSchema::fromModelAncestors()`. No new
breadcrumb engine.
**Precedence (decided, mirrors explicit-over-computed):** explicit stored
`seo_meta.schema_jsonld` is **authoritative** — when present it is emitted as-is and
the hook/assembler is NOT invoked for that model (no silent merge). When absent,
`getSEOSchema()` (or the config type-map) produces the graph.
**API.**
```php
public function getSEOSchema(): array; // one or more schema.org nodes
SchemaGraph::for($model)->organization()->website()->webPage()->breadcrumbFromAncestors()->toArray();
```
**Acceptance.** A model with no stored schema exposes a cross-linked WebPage +
BreadcrumbList via the hook with deterministic, stable `@id`s; a model WITH stored
`schema_jsonld` emits exactly that and the hook is NOT called (asserted); validates;
no parallel breadcrumb API.
**Release.** Additive minor. **Open-core:** core.

### T12 — Resolver caching for hot frontends · core · M
> ✅ **DONE** — core, branch `feat/rankbeam-improvements`, **working-tree only**
> (not committed — overlaps the uncommitted T3/T10/T11 tree in `SEOResolver.php`,
> `HasSEO.php`, `config/seo.php`, `CHANGELOG.md`; batched per the runbook). Opt-in,
> additive, **off by default** (behaviour byte-identical with caching off). New
> `Services\SEOResolutionCache` caches a resolved model's SEO as a plain **flat**
> array via `toFlatArray()` (the prompt named `toArray()`, but that is the lossy
> *nested* render shape and is NOT the inverse of `fromArray()` — flagged; DateTimes
> stored ISO-8601 to round-trip across TZ), rehydrated with `SEOData::fromArray()`,
> keyed by `(model class, id, locale, route, request URL)`. A hit **skips the whole
> precedence chain** (benchmark: 25 resolves → 25 DB queries uncached vs **0** cached).
> `resolve()` split into a cache wrapper over `buildResolved()` with a re-entrancy depth
> guard so the schema layer's nested `webPage()` resolve never touches the cache.
> **Invalidation added** (none existed): `SEOMeta` saved/deleted listener (morph alias
> → FQCN), `HasSEO` saved-on-`getSEOContentFields()`-change + deleted, and `SEODefault`
> change flushes the whole resolution cache — all inert when off. **Taggable** stores
> use cache **tags**; **non-taggable** stores use a per-model **version stamp** (both
> tested). Only model-backed resolves are cached. +24 Pest tests (incl. ON==OFF parity,
> hit-skips-chain, all bust paths, re-entrant-schema parity, morph-alias bust, both
> store types, the benchmark) + `Fixtures\NonTaggableArrayStore`; full core suite
> **448 green** (was 424). CHANGELOG `[Unreleased]` + configuration-docs scale-lever
> section; VitePress clean. Nothing tagged/published.

**Goal.** idi runs ~20k req/day; the resolver is on every frontend request. Neither
source plan addressed frontend caching — it's central to the "works at scale" claim.
**Scope.** Cache the **array form** (`SEOData::toArray()`), NOT the object — Laravel
13 can rehydrate cached objects as `__PHP_Incomplete_Class` (the codebase already
caches arrays in `SEODefaultsRepository` for this reason); rehydrate via
`SEOData::fromArray()` on read. Key by `(model class, id, locale, route)`.
**Invalidation (must be ADDED — it does not exist today):** `HasSEO` currently hooks
only `created`/`deleted` and `SEOMeta` has no cache hooks. Add busting on **`SEOMeta`
saved/deleted**, on model saves that change `getSEOContentFields()`, and on
`seo_defaults` change. For route-key fan-out use **cache tags** (or a versioned
namespace / explicit index) so one model's entries clear without key-scanning; fall
back to a version-stamp strategy on non-taggable stores. Respect `seo.cache.store`.
Off by default; documented as the scale lever.
**Acceptance.** Cache hit skips the precedence chain; saving `seo_meta`, mutating a
content field, or editing defaults busts the right entries; correctness identical to
uncached (parity test); works on both taggable and non-taggable stores; benchmark
shows the win at idi-scale.
**Release.** Additive minor (opt-in). **Open-core:** core.

### T13 — Positioning / "non-negotiable" narrative · docs/marketing · S/M
> ✅ **DONE** — laravel-seo (VitePress docs) commit `376da55` (branch
> `feat/rankbeam-improvements`, **not pushed**). New comparison page
> `docs/guide/why-rankbeam.md` ("Why Rankbeam, not three packages + glue"),
> wired into the nav (top-level **Why Rankbeam**) + the Getting-started sidebar;
> VitePress builds clean, all internal links resolve. **Grounded in the real
> swap, not the plan's round number:** diffing the legacy app tree (`idi-it`,
> ralphjsmit + backstage) against the rankbeam sandbox (`idi-it-sandbox`) shows
> the swap **deleted 12 bespoke glue classes outright** (table maps each →
> rankbeam equivalent); honest follow-on note that the swap *kept* the
> hand-rolled crawler + meta/schema helpers (~22 more) that the Pro crawler /
> Filament target+preview / core schema graph now replace (~34 total). Page has:
> the anonymized reference app (hospital site, ~900 pages, ~20k/day, L12/Filament
> 4), a side-by-side capability table, "three things glue can't do well"
> (one release line / locale-aware storage / headless rendering), an honest
> **"what is NOT in free core"** open-core table (Pro = scans/score/redirects/404/
> crawler/GSC/AI/dashboard), the lowest-risk WordPress-migration story (links the
> existing runbook, no duplication), and **two real benchmarks** cited only on
> their deterministic asserts — resolver cache **0 DB queries** on a warm hit vs
> **≥25** uncached (`ResolverCacheBenchmarkTest`, opt-in/off-by-default), crawler
> ~900-page corpus across **≥18 bounded jobs** / 1,800 links / no job over the
> 50-page cap (`BrokenLinkScaleBenchmarkTest`). No invented wall-clock numbers;
> no version number baked in (sidesteps the docs nav v2.0-vs-Core-3 mismatch).
> **Docs-only — no package code, version bump, tag, or CHANGELOG entry.** Commit
> is precise-staged (only the 2 docs files); the uncommitted T3/T10/T11/T12 core
> working tree was left fully intact. Rollback: `git reset --soft HEAD~1`.
> **All RT/T threads through T13 complete.** Remaining is Valentin's call:
> push the branch + deploy the docs site.

**Goal.** The ask is non-negotiable **for other devs** — partly a narrative problem,
not just code. Convert the idi evidence into adoption.
**Scope.** A comparison page (rankbeam vs the multi-package + glue approach, using the
real idi swap: ~20 custom files deleted, one cohesive family, locale-aware storage,
headless rendering); the WP-migration story; honest "what's NOT in core" framing;
benchmark numbers from T12. Anonymize idi as needed.
**Acceptance.** A landing/doc section a skeptical Laravel dev reads and concludes
"why would I glue three packages together?"
**Release.** Anytime. **Open-core:** docs/site.

---

## Cross-cutting concerns

- **Testing / CI:** PHP 8.2–8.4 × Laravel 11/12/13 × Filament 4/5. Crawler: a
  **deterministic generated corpus** for integration + a **separate benchmark/soak
  suite** (900 pages is too big for every test run). Security: the SSRF matrix in T6.
- **Multi-locale policy (explicit, applies across threads):** related targets (T4),
  previews (T5), imports/sitemap seeds (T9), and crawler findings (T6) must each
  state how they handle the active/`seo_meta` locale.
- **Docs structure:** ONE installation path, ONE production-operations guide, ONE
  migration runbook, plus feature reference pages. Don't duplicate queue instructions
  across packages.
- **Telemetry:** structured events/metrics (T8) for crawler + scans.

## Risks & backward-compat

- **T1 preparation** must stay before a verbatim `TagRenderer`; keep `prepare()`
  internal until its transform contract is stable; use `url()` not `secure_url()`.
- **T2** is the one default-flip — opt-in now, Core 4 later, loud UPGRADING note.
- **T6 crawler** is the large lift and the only outbound-HTTP-at-scale surface:
  conservative finite defaults, internal-only scope, SSRF on every hop, run-locking,
  bounded fetches. A misconfig must not be able to hammer a site.
- **Pro migrations** stay publish-only + `seo-pro:install`; never switch to
  auto-loading (duplicate histories for users who published).
- **Preview** vendor-view shadowing: document refreshing/removing stale overrides.

## Explicitly descoped (with rationale)

- **A stored `seo_meta.author` column** — `getSEOAuthor()` + `article:author` already
  cover it; a column expands migrations/fillables/locale/import-precedence/Filament
  for a niche override. Revisit in Core 4 *only if real demand appears* (idi's case
  is servable via `getSEOAuthor()` reading the entity).
- **Merging crawler findings into `seo_scan_issues` / the score** — different
  lifecycle/uniqueness/resolution; surface as a dashboard summary instead.
- **Remote image-dimension fetching in Core** — SSRF/latency/cache; preview does it
  client-side.
- **Auto-loading Pro migrations / renaming the `seo` config namespace** — both create
  more breakage than they solve in the current major.
- **Generic "create redirect" for outbound broken links** — only eligible internal
  targets.
- **Privacy-first page-visit analytics & medical-vertical schema types** (idi has
  them) — out of SEO scope / make custom types easy instead. Possible *separate*
  package, not folded in.

# Rankbeam Improvement — Execution Prompts

Each thread below is a **standalone, copy-paste prompt** for a fresh coding session
(Claude Code or Codex). Paste one block into a new session and it has enough context
to execute without this conversation. Every prompt assumes the repos are siblings
under `C:\Users\Valentin\Desktop\projects\`:

- `laravel-seo` — core (MIT, Packagist)
- `laravel-seo-pro` — pro (proprietary, PRIVATE repo, Anystack — never public Packagist)
- `laravel-seo-filament` — filament (MIT, Packagist)
- `idi-it-sandbox` — the production reference app the harvest came from

Full design rationale for any thread is in `laravel-seo/RANKBEAM-IMPROVEMENT-PLAN.md`
(the matching `T<n>`) and `rankbeam-improvement-plan/EVIDENCE.md`.

**Suggested order:** T1 → T2 → T3 → T4 → T5 → T6 → T7 → T8 → T9 → T10 → T11 → T12 → T13.
T1, T5, T6/T7 are the highest-ROI. Each prompt is independently runnable; later threads
note their real dependencies.

**Releases are NOT per-thread.** Each thread leaves its repo *release-ready* (tests +
CHANGELOG + UPGRADING + cross-package version notes — see each block's "Release
hygiene" line) but does NOT tag or publish. Cut releases in **batches** with the
**Release runbook** (last block in this file), which handles version bumps, the
Filament → Core → Pro tag order, cross-package constraint bumps, and Packagist/Anystack
publishing with a green-gate and human confirmation.

**Thread status (read before starting a thread — don't redo finished work).** Each
thread below is annotated with a `> ✅ DONE` / `> ⏳ TODO` line carrying the commit and
a one-line outcome. A `DONE` thread's code is already committed (not pushed); verify it
rather than re-implementing. Keep these markers current as threads land.

| Thread | Status | Commit | Notes |
|---|---|---|---|
| T1 | ✅ DONE | `976cb23` (core) | Render surface accepts `Model\|SEOData\|null` |
| T2 | ✅ DONE | `5e18ec7` (core) | Blank explicit-value policy + `blank_explicit_override` audit |
| T3 | ✅ DONE | working-tree (core) | Docs + 5 e2e tests for existing robots/`is_indexable`; no code change |
| T4 | ✅ DONE | working-tree (filament) | `target` resolver edits a related model's `seo_meta` (+ schema); green on Filament 4 & 5 |
| T5 | ✅ DONE | working-tree (filament) | Tabbed Google-SERP + social-card live preview replaces the search-only view; +21 tests, suite 116 green on Filament 4 & 5 |
| T6 | ✅ DONE | `2ad51f9` (pro) | Headless bounded/resumable broken-link crawler + facade + 5 commands; durable cross-scan findings, SSRF on every hop; +94 tests + benchmark, suite 637/3 green |
| T7 | ✅ DONE | `e425192` (pro) | Broken-link Filament monitoring UI: `BrokenLinkFindingResource` + progress/stats/trend widgets + dashboard summary; source-editor registry; internal-only Create-redirect; `SeoProGate`; +33 tests, suite 670/3 green |
| T8 | ✅ DONE | `7baca2c` + docs `b6c68a9` (pro) | `seo-pro:install` + doctor ops checks + telemetry + production guide; +28 tests, suite 698/3 green |
| T9 | ✅ DONE | `5316a39` (core) + `5705921` (pro) | Import verification report (matched/url-only/truncated/unmapped + every author value) + anonymized ~900-page WP corpus (+19 core tests, 391 green); Pro `seo-pro:redirects-import` validated CSV importer reading core's redirect CSV format v1 (+15 tests, 713/3 green); WP migration runbook |
| T10 | ✅ DONE | working-tree (core) | Opt-in dimension-aware social-image selection: `getSEOImages()` hook + `SEOImageCandidate` + `seo.computed.image_selection` (`best` strategy scores local candidates by closeness to 1200×630, skips <200×200; default `first` unchanged); shared `LocalImageInspector`; +15 tests, suite 406 green |
| T11 | ✅ DONE | working-tree (core) | Schema composition layer on top of existing `SchemaGraph`/`BreadcrumbSchema`: `SchemaGraph::for($model)` fluent builder (`SchemaGraphBuilder`) + `HasSEO::getSEOSchema()` hook + `seo.schema.type_map` config; resolver Layer 6 (`applyModelSchema`) — stored `schema_jsonld` authoritative & hook NOT called (asserted), else hook/type-map produces cross-linked WebPage+BreadcrumbList with stable @ids; re-entrancy-guarded; +18 tests, suite 424 green |
| T12 | ✅ DONE | working-tree (core) | Opt-in resolver result cache (`seo.cache.resolver.enabled`, off by default): a hit skips the precedence chain (benchmark: 25→0 DB queries, ~8× faster); `SEOResolutionCache` keyed by (class,id,locale,route,url), cached as a flat array via `fromArray()`; NEW invalidation — `SEOMeta` saved/deleted + `HasSEO` content-field saved/deleted + `SEODefault` change; tags on taggable stores, version-stamp on non-taggable; parity ON==OFF; +24 tests, suite 448 green |
| T13 | ✅ DONE | docs `376da55` (core repo) | "Why Rankbeam, not three packages + glue" comparison page + nav/sidebar; swap deleted 12 glue classes (verified diff) vs ~34 family-owned; honest open-core + WP-migration story + 2 real benchmarks (resolver cache 0 DB queries warm; crawler ~900-page corpus / ≥18 bounded jobs); docs-only, not pushed |

---

## T1 — Accept `Model | SEOData | null` across the render surface (core)

> ✅ **DONE** — core commit `976cb23` (branch `feat/rankbeam-improvements`, not pushed).
> `SEO::render/toArray/forInertia` + the `@seo` directive accept `Model|SEOData|null`;
> `SEOResolver::resolveSource()` dispatches to an internal `prepare(SEOData)` that
> preserves supplied values and fills only absent ones (canonical/og:url from the URL,
> `title_suffix` with the new `title_suffix_skip_when_contains` brand-skip, relative
> images absolutized via `url()` not `secure_url()`, og:site_name from config).
> `TagRenderer` is verbatim. 20 tests in `tests/Unit/Services/SeoDataRenderSurfaceTest.php`;
> full core suite green. Release-ready (CHANGELOG Unreleased + docs); not tagged.

```text
You are implementing thread T1 of the Rankbeam SEO roadmap in the rankbeam/laravel-seo
(core) package at C:\Users\Valentin\Desktop\projects\laravel-seo. Read
RANKBEAM-IMPROVEMENT-PLAN.md (thread T1) and EVIDENCE.md there first for context.

Goal: let model-less pages render SEO without calling TagRenderer manually. Today
@seo($x), SEO::render/toArray/forInertia only accept a Model (they call
SEOResolver::resolve(?Model ...)); a hand-built SEOData requires
app(TagRenderer::class)->render($seoData). This is the #1 integration papercut.

Build:
- SEO::render(), SEO::toArray(), SEO::forInertia() accept Model|SEOData|null.
- The @seo($x) Blade directive accepts a SEOData as well as a Model/null.
- Add an INTERNAL prepare(SEOData): SEOData step in the facade/resolver path. Do NOT
  make prepare() public yet (its transforms would become a compat contract).
- TagRenderer stays VERBATIM — preparation happens before it, never inside it.
  Direct TagRenderer::render($data) callers must be unaffected.
- Preparation rules for a supplied SEOData (per field): PRESERVE every supplied
  value; only FILL ABSENT fields — derive canonical/ogUrl from the current URL if
  null; apply title_suffix only when the title lacks it, honoring a brand-aware skip
  list via a NEW additive config key seo.title_suffix_skip_when_contains (token-aware,
  word-boundary, case-insensitive, default []); absolutize relative ogImage/
  twitterImage using the app's configured URL generator (url()) — NOT secure_url()
  (it forces HTTPS and breaks non-HTTPS/dev). Fill og:site_name from config if absent.
  Do NOT merge the DB precedence chain (route/model-type/seo_meta defaults) into a
  hand-built SEOData — it is explicit intent.

Read first: src/Facades/SEO.php, src/Services/SEOResolver.php,
src/Services/TagRenderer.php, src/SEOServiceProvider.php (the @seo directive),
src/Data/SEOData.php, config/seo.php.

Constraints: additive only (new config key defaults to current behavior); respect the
Rendering Contract (semantic, not byte-for-byte) in docs/contributing.

Acceptance: @seo($seoData) renders the same tag set as @seo($model) for equivalent
data; existing model/null calls unchanged; relative images become absolute via url();
suffix never duplicated; brand-token titles skip the suffix; direct TagRenderer calls
remain verbatim. Write Pest tests for the SEOData path (render, toArray, forInertia)
and run them (vendor/bin/pest). Match existing code style; touch only laravel-seo.

Release hygiene (leave the repo release-ready; do NOT tag/publish): add a CHANGELOG
entry under "Unreleased"; this is an additive minor (new config key defaults to
current behavior), so no UPGRADING note unless you change a default. You added public
API (SEOData accepted by the facade/directive) that laravel-seo-filament and
laravel-seo-pro may want to call — note the minimum core version in the CHANGELOG so
those siblings can bump their `rankbeam/laravel-seo` constraint when they adopt it.
Releases are cut in batches via the Release runbook at the end of RANKBEAM-EXECUTION-
PROMPTS.md — do NOT git tag or publish here.
```

---

## T2 — Blank explicit-value policy (core)

> ✅ **DONE** — core commit `5e18ec7` (branch `feat/rankbeam-improvements`, not pushed).
> `seo.resolver.blank_is_unset` (`SEO_BLANK_IS_UNSET`, default `false`) normalizes
> blank/whitespace STRING fields on the stored (explicit) layer to null during
> explicit-value extraction so they fall through; arrays, `schema_jsonld`, and `"0"`
> are untouched; null clear-to-fall-through is unchanged. Flag-off behavior is
> byte-identical to today; default flips to `true` in Core 4 (UPGRADING §12). Free-audit
> code `blank_explicit_override` (warning, **core-only** — deliberately kept OUT of the
> Pro-mirror `MetadataIssues::metadataCodes()`) surfaces the condition while the flag is
> off. 15 tests (`tests/Feature/BlankExplicitValueTest.php` + MetadataIssues additions);
> full core suite 367 green. Release-ready (CHANGELOG Unreleased + UPGRADING); not tagged.

```text
You are implementing thread T2 of the Rankbeam SEO roadmap in rankbeam/laravel-seo
(core) at C:\Users\Valentin\Desktop\projects\laravel-seo. Read
RANKBEAM-IMPROVEMENT-PLAN.md (T2) and EVIDENCE.md first.

Goal: a persisted '' / '   ' SEO value currently OVERRIDES lower layers because the
resolver merges with ?? — silently suppressing computed/default values. Make blank
explicit strings fall through, WITHOUT changing published v3 semantics by default.

Build:
- During the resolver's explicit-value extraction (persistence layer, NOT on the
  general SEOData DTO), normalize blank/whitespace STRING fields to null. Never touch
  arrays, "0", or schema_jsonld.
- Gate behind config: 'resolver' => ['blank_is_unset' => env('SEO_BLANK_IS_UNSET',
  false)]. Default false now; the default flips to true in Core 4 (note in UPGRADING).
- Add a free-audit issue code `blank_explicit_override` so the condition is observable
  even with the flag off (see the seo:audit command + the metadata issue registry).

Read first: src/Services/SEOResolver.php (explicit-value application), src/Models/
SEOMeta.php, the seo:audit command + AuditIssue/MetadataIssues classes, config/seo.php.

Constraints: additive; flag-off behavior must be byte-identical to today.

Acceptance: flag off → blanks still override (current behavior); flag on → blanks
fall through to computed/defaults; "0", non-empty strings, arrays, and schema_jsonld
unchanged; Filament clear-to-null still works; audit reports blank_explicit_override.
Write Pest tests for both flag states; run them. Touch only laravel-seo.

Release hygiene (leave the repo release-ready; do NOT tag/publish): CHANGELOG entry
under "Unreleased". This ships as an opt-in additive minor in 3.x but the DEFAULT FLIP
to true is a behavior change reserved for Core 4 — add an UPGRADING.md entry now under
a "Planned for Core 4" / "Behavior changes" section documenting the flip and the
SEO_BLANK_IS_UNSET escape hatch. Releases are batched via the Release runbook at the
end of this file — do NOT tag or publish here.
```

---

## T3 — robots/`is_indexable`: verify + document (core)

> ✅ **DONE** — core, branch `feat/rankbeam-improvements`, working-tree only (not
> committed). Docs + 5 end-to-end characterization tests for the already-shipped
> robots/`is_indexable` support; **no code/API/behaviour change**. Suite 372 green
> (was 367). Not tagged/published.

```text
You are implementing thread T3 of the Rankbeam SEO roadmap in rankbeam/laravel-seo
(core) at C:\Users\Valentin\Desktop\projects\laravel-seo. Read
RANKBEAM-IMPROVEMENT-PLAN.md (T3) first.

Context/correction: this feature ALREADY EXISTS — SEOComputedBuilder::computeRobots()
honors a getSEORobots() model hook and derives noindex,nofollow from an is_indexable
attribute. But the HasSEO trait doesn't surface it, so devs think it's missing. This
thread is documentation + verification, NOT new implementation.

Build:
- Verify computeRobots() against the robots-emit policy in TagRenderer (robots is
  emitted only when it deviates from seo.default_robots unless seo.robots.emit_default).
  Add a characterization test if any path (getSEORobots present / is_indexable=false /
  explicit seo_meta robots winning) is uncovered.
- Document the getSEORobots() hook and the is_indexable derivation prominently in the
  HasSEO docs / recipes (and mention it in the trait's docblock).

Read first: src/Services/SEOComputedBuilder.php (computeRobots, ~line 253),
src/Traits/HasSEO.php, src/Services/TagRenderer.php (robotsContent), the docs dir.

Acceptance: docs and code agree; a model with is_indexable=false demonstrably renders
noindex,nofollow (respecting emit policy); getSEORobots() documented; tests green.
Touch only laravel-seo.

Release hygiene (leave the repo release-ready; do NOT tag/publish): docs-and-test
only — add a brief CHANGELOG note ("documented existing robots/is_indexable support");
no UPGRADING entry, no API change, no version-constraint impact. Do NOT tag or publish;
see the Release runbook at the end of this file.
```

---

## T4 — Filament target abstraction: edit SEO for a related model (filament)

> ✅ **DONE** — laravel-seo-filament, branch `feat/rankbeam-improvements`, working-tree
> only (not committed). Optional `target` resolver `Closure(?Model $formRecord): ?Model`
> on `SEOFields::make()`/`seoSection()` AND `SEOSchemaFields::make()`/`seoSchemaSection()`,
> shared via a new `src/Support/ResolvesSeoTarget.php` trait, so a resource for model A
> edits the locale-scoped `seo_meta` of a RELATED model B; the same resolver drives
> hydration, save, source indicators, the snippet preview, and the schema editor. Null
> target (create form / not-yet-existing relation) writes nothing and never auto-creates a
> placeholder; a non-null target lacking `seoMeta()` throws a clear exception. Additive
> (no `target` = today's behavior; no new core requirement). 17 tests
> (`tests/Unit/ResolvesSeoTargetTest.php` 7 + `tests/Feature/TargetResolutionTest.php` 10,
> covering create/edit/clear/locale/missing-target/schema/auto-breadcrumb/sources-reflect-
> target). Full suite **green on BOTH Filament 4 (94) and Filament 5 (95)** via the CI
> composer toggle. Release-ready (CHANGELOG Unreleased); not tagged.

```text
You are implementing thread T4 of the Rankbeam SEO roadmap in rankbeam/laravel-seo-
filament at C:\Users\Valentin\Desktop\projects\laravel-seo-filament. Read
RANKBEAM-IMPROVEMENT-PLAN.md (T4) in the laravel-seo repo first.

Goal: let a Filament resource edit SEO stored on a RELATED model (e.g. an entity whose
SEO lives on a related PublicPage) instead of only the form's own record. Today
SEOFields binds the form's model; the reference app had to wrap it. This is the shared
spine for hydration, save, source indicators, schema fields, AND the preview (T5) — do
it BEFORE the preview.

Build:
- Add a `target` resolver closure to SEOFields::make() / seoSection() AND
  SEOSchemaFields::make() / seoSchemaSection() (schema persistence is currently
  independent — it must honor the same resolved target or schema writes to the wrong
  model). All SEO read/write callbacks operate on the resolved target.
- Signature tolerates create-form nullness: Closure(?Model): ?Model, evaluated via
  Filament's closure evaluator.
- Reject a non-null target lacking a seoMeta() relation with a clear developer
  exception. Never auto-create a placeholder related model.

API:
  SEOFields::make(only: [...], target: fn (?Model $r): ?Model => $r?->publicPage);
  static::seoSection(target: fn (?Model $r): ?Model => $r?->publicPage);
  SEOSchemaFields::make(target: fn (?Model $r): ?Model => $r?->publicPage);
  static::seoSchemaSection(target: fn (?Model $r): ?Model => $r?->publicPage);

Read first: src/Forms/SEOFields.php, src/Forms/SEOSchemaFields.php,
src/Concerns/HasSEOFields.php.

Constraints: additive (no `target` = today's behavior, binds the form's own model);
must work on Filament 4 AND 5.

Acceptance: a resource for model A edits model B's locale-scoped seo_meta; A gets no
seo_meta row; create forms tolerate a not-yet-existing relation; sources/preview/schema
reflect the target. Write Pest+Filament tests (F4 and F5) covering create/edit/clear/
locale/missing-target. Touch only laravel-seo-filament.

Release hygiene (leave the repo release-ready; do NOT tag/publish): CHANGELOG entry
under "Unreleased"; additive minor (no UPGRADING needed). No new core requirement (uses
existing seoMeta()). Releases are batched via the Release runbook at the end of this
file — do NOT tag or publish here.
```

---

## T5 — Editorial Filament preview: SERP + social (filament)

> ✅ **DONE** — laravel-seo-filament, branch `feat/rankbeam-improvements`, working-tree
> only (not committed). Replaced the search-only `seo-snippet-preview.blade.php` with a
> tabbed **Google SERP / social card** live editor (Alpine): title/description/URL update
> as you type. New `src/Support/SEOPreviewData.php` reuses `SEOFieldSources` so the social
> image follows resolver order (manual `seo_meta` → content/config fallback), maps a bare
> Filament-upload og_image path to a loadable URL via the upload disk, and detects
> **known-local** dimensions with `getimagesize`. Explicit image-dimension states —
> *known-local* (server) / *browser-measured* (client, tolerant of CORS/signed-URL/
> private-disk/temp-upload, `onerror`→placeholder) / *unavailable* — a failed remote image
> never breaks the form. Warnings reuse the core `SEOWarningEvaluator` constants
> (60/160/200×200/1200×630); live source labels reflect the unsaved form without claiming
> DB/resolver provenance. Default-on; `SEOFields::make(showPreview: false)` /
> `static::seoSection(showPreview: false)` opts out. Scoped CSS (a test asserts no global
> selectors), dark-mode. +21 tests → suite **116 green on BOTH Filament 4 (4.11.7) and 5
> (5.6.7)**. CHANGELOG Unreleased + new `UPGRADING.md` (replacing the published preview
> view SHADOWS user `resources/views/vendor/seo-filament` overrides → refresh/remove).
> Release-ready, not tagged.

```text
You are implementing thread T5 of the Rankbeam SEO roadmap in rankbeam/laravel-seo-
filament at C:\Users\Valentin\Desktop\projects\laravel-seo-filament. Read
RANKBEAM-IMPROVEMENT-PLAN.md (T5) first. DEPENDS ON T4 (shared target/state resolution).

Goal: replace the current search-only preview with a production-proven tabbed
(Google SERP / social card) LIVE editor — the first-screen "wow". A reference
implementation to port from is at C:\Users\Valentin\Desktop\projects\idi-it-sandbox\
resources\views\components\filament-fabricator\seo\seo-snippet-preview.blade.php.

Build:
- Tabs: Google SERP + social card. Live title/description/URL/image as fields change.
- Character counters; title>60 and description>160 warnings; social-image dimension
  checks (min 200x200, ideal 1200x630) — REUSE the core threshold constants
  (SEOWarningEvaluator) so audit/preview/scan agree.
- Image-dimension states are EXPLICIT: known-local (server getimagesize), browser-
  measured (client-side, tolerant of CORS/signed-URL/private-disk/temp-upload
  failure), unavailable. A failed remote image must NOT break the form.
- Source labels (explicit vs computed/fallback) must reflect UNSAVED form state
  without claiming it came from the DB or the resolver.
- First REPLACE the existing preview view; only extract a standalone component once
  T4's shared target/state resolution exists. Default-on; SEOFields::make(showPreview:
  false) opts out.
- Scoped CSS only (no global selectors); dark-mode; Filament 4 AND 5.

Read first: the current preview view + SEOFields in src/, the idi reference blade
above, the core SEOWarningEvaluator for thresholds, T4's target resolution.

Acceptance: live updates on edit; social image order matches the resolver; warnings
use the shared 60/160/200x200/1200x630 constants; failed remote image doesn't break
the form; labels distinguish unsaved/explicit/computed. Pest+Filament tests for tabs,
fallback/manual state, warning payloads, F4+F5. Touch only laravel-seo-filament.

Release hygiene (leave the repo release-ready; do NOT tag/publish): CHANGELOG entry
under "Unreleased". Replacing the published preview view can SHADOW user overrides in
resources/views/vendor — add an UPGRADING note telling users to refresh/remove stale
published views. Additive minor otherwise. Depends on T4 shipping in the same or an
earlier filament release. Releases are batched via the Release runbook at the end of
this file — do NOT tag or publish here.
```

---

## T6 — Pro broken-link crawler ENGINE, headless (pro)

> ✅ **DONE** — laravel-seo-pro commit `2ad51f9` (branch `feat/rankbeam-improvements`,
> **not pushed**). Headless bounded/resumable crawler, correct with NO Filament:
> `SeoPro::brokenLinks()->scan(BrokenLinkScope)/cancel()/latestRun()/unresolvedFindings()`
> + all five `seo-pro:broken-links-{scan,status,cancel,recover,prune}`. Dedicated
> publish-only tables `seo_broken_link_scan_runs`/`seo_broken_link_findings`
> (hash-indexed canonical URLs); canonical `(source,target)` finding identity durable
> across runs; **cross-scan** N-consecutive failure confirmation (reset on success);
> stale resolution only after a successful source fetch; one-active-run-per-scope DB
> lease + heartbeat + dead-worker recovery; finite caps + hard per-job time budget;
> SSRF on the seed fetch and **every redirect hop**. Disabled by default. A prior
> work-tree had the engine but was missing the config block, command registration, and
> the recover/prune commands (added here). **Fixed a real latent bug:** the engine used
> `symfony/dom-crawler` without declaring it (absent in the package env → link parsing
> silently returned nothing) — declared it + switched the parser to XPath. Also fixed
> dropped relative `--url` seeds and three `null`-inherit config keys. **+94 Pest tests**
> incl. the full SSRF matrix + a separate ~900-page benchmark suite (excluded from the
> default run); full suite **637 passed / 3 skipped** (baseline 543/3, no regressions);
> Pint-clean. CHANGELOG/UPGRADING/README updated. Not tagged/published.

```text
You are implementing thread T6 of the Rankbeam SEO roadmap in rankbeam/laravel-seo-pro
at C:\Users\Valentin\Desktop\projects\laravel-seo-pro. Read RANKBEAM-IMPROVEMENT-PLAN.md
(T6) and EVIDENCE.md in the laravel-seo repo first. This is the flagship Pro feature.
It must be HEADLESS and correct with NO Filament installed (UI is T7).

Reference implementation to port (battle-tested at ~20k visits/day, ~900 pages) lives
in C:\Users\Valentin\Desktop\projects\idi-it-sandbox under app/Jobs/RunBrokenLinkScanJob.php,
app/Services/BrokenLinkChecker.php, BrokenLinkSeedBuilder.php, BrokenLinkSourceResolver.php,
app/Models/BrokenLink*.php, app/Enums/BrokenLinkScan*.php, app/Events/BrokenLinkScan*.php.

Build (a bounded, resumable crawler):
- Dedicated tables seo_broken_link_scan_runs + seo_broken_link_findings (do NOT
  overload seo_scan_runs). Index unresolved-findings, status, last-seen, run-status,
  and a HASH of source/target URLs (don't index full URLs on MySQL).
- Finding identity / URL canonicalization: normalize scheme, host casing, default
  ports, fragments, trailing slashes, query policy, and redirect targets BEFORE dedup.
  Separate fromModel() / toUrl() / forSourceUrl() queries (don't conflate links-from
  vs links-to).
- Concurrency: ONE active run per site/scope via a DB lease/uniqueness; atomic cursor
  claims; idempotent continuation dispatch; cancellation tokens; recovery leases for
  dead workers.
- Bounded jobs: batch page/link caps with CONSERVATIVE FINITE defaults
  (max_pages_per_run => 2000, etc.); null is an explicit operator opt-in to unlimited,
  NEVER the default. Hard time budget = no NEW fetch after the budget; in-flight
  requests bounded by request/connect timeouts.
- HTTP governance: request+connect timeouts, response-size cap, accepted content-types,
  user agent, per-host delay/throttle, retry classes, external-domain rate limits,
  redirect-hop following.
- Failure confirmation is CROSS-SCAN, not per-run: a durable per-(canonical source,
  target) failure counter + last-status history; a link is marked broken only after N
  CONSECUTIVE failed checks; the counter resets on success.
- Security: reuse Pro's SsrfGuard on every request AND every redirect hop. Tests must
  include DNS rebinding, IPv6, redirect-to-internal, credentials-in-URL, oversized
  bodies, decompression bombs, and the app-host exception.
- Retention pruning, broken-count alert threshold, stale-finding resolution ONLY when
  the source page was fetched successfully.
- Seed from registered SeoPro::targets() + the core sitemap registry; allow extra
  sources.
- Commands: seo-pro:broken-links-{scan,status,cancel,recover,prune}.
- Config block under seo-pro.broken_links, enabled=false by default (see T6 in the
  plan for the exact keys).
- Facade: SeoPro::brokenLinks()->scan(BrokenLinkScope::InternalOnly); ->cancel($run);
  ->latestRun(); ->unresolvedFindings().

Read first: the idi reference files above, src/SsrfGuard*, src/SeoProManager.php +
Facades/SeoPro.php, the ScanTargetRegistry, the existing Pro migrations/config.

Constraints: feature disabled by default; additive; do not require Filament.

Acceptance: a ~900-page GENERATED fixture completes across bounded jobs (don't ship a
900-page fixture in the unit suite — use a deterministic generator + a separate
benchmark suite); no job exceeds caps or fetches past the budget; every hop SSRF-
validated; findings upserted by canonical (source,target) identity durable across runs
with per-run last-seen + persistent failure counter; N consecutive-scan confirmations;
cancellation + dead-worker recovery reach coherent terminal states; re-scan resolves
stale findings only after a successful source fetch; WORKS WITH NO FILAMENT. Write
Pest tests incl. the SSRF matrix; run them. Touch only laravel-seo-pro.

Release hygiene (leave the repo release-ready; do NOT tag/publish): CHANGELOG entry
under "Unreleased"; additive minor (feature disabled by default). NEW MIGRATIONS — Pro
migrations are publish-only, so document that `php artisan vendor:publish
--tag=seo-pro-migrations && php artisan migrate` (or seo-pro:install once T8 lands) is
required to enable the crawler; add this to UPGRADING. Releases are batched via the
Release runbook at the end of this file — do NOT tag or publish here.

When you finish this thread, write its status and mark it DONE: add a `> ✅ DONE` note
(commit + a one-line outcome) at the top of this thread's section and flip its row in the
thread-status table at the top of this file to ✅ DONE, matching how the earlier threads
are annotated, so the next session doesn't redo it.
```

---

## T7 — Pro broken-link MONITORING UI (pro)

> ✅ **DONE** — laravel-seo-pro commit `e425192` (branch `feat/rankbeam-improvements`,
> not pushed). The crawler's findings as actionable Filament work, layered on T6's
> engine. New `BrokenLinkFindingResource` (registered via `SeoProPlugin`) lists
> confirmed-broken findings with start/cancel/scope scan actions, resolve/reopen,
> filters defaulting to the work list (unresolved + confirmed-broken), and a bounded
> cached nav badge. **"Create redirect"** is offered ONLY for an internal broken
> target (an outbound dead link gets **"Open source editor"** instead) and reuses
> `SEORedirect`'s open-redirect / cycle validation, then resolves the finding (next
> crawl re-confirms). A pluggable `BrokenLinks\SourceEditor\SourceEditorResolver`
> (request-scoped singleton, Filament-free) maps a finding back to the editor for the
> page the link sits on — the generic form of idi-it's `BrokenLinkSourceResolver`
> (apps register closures or `registerModelResource(Model, Resource)`). Three widgets
> (live **progress**, cached **stats**, bounded **trend** chart) + a read-only
> **summary panel** on the SEO dashboard. Whole surface hidden when the crawler is
> disabled; viewing follows `view_dashboard`, every management action follows
> `manage_seo` (shared new `Support\SeoProGate`). Widget aggregates cached + bounded
> (no unbounded queries). Findings are deliberately NOT written into `seo_scan_issues`
> or the 0–100 score (separate lifecycle) — score semantics unchanged. +33 tests
> (8 engine resolver on `TestCase` + 25 Filament on F5; permissions, actions, polling,
> empty states, gating); full suite **670 passed / 3 skipped** (was 637/3 after T6 —
> no regressions). CHANGELOG Unreleased; Pint-clean (new files). Release-ready, not tagged.

```text
You are implementing thread T7 of the Rankbeam SEO roadmap in rankbeam/laravel-seo-pro
at C:\Users\Valentin\Desktop\projects\laravel-seo-pro. Read RANKBEAM-IMPROVEMENT-PLAN.md
(T7) first. REQUIRES T6 (the headless crawler engine) to be done.

Goal: expose crawler findings as actionable work in the Filament SEO admin.

Build:
- BrokenLinkFindingResource + progress/stats/trend widgets, registered via SeoProPlugin
  (nav hidden when broken_links disabled).
- Start/cancel/scope actions.
- A source-editor registry resolving a finding -> the owning model's Filament edit URL
  (port idi's BrokenLinkSourceResolver concept).
- "Create redirect" action ONLY when the broken target is an internal path the app
  owns (a broken OUTBOUND link normally needs the source page edited — do not offer a
  redirect there). Use Pro's SEORedirect model + its open-redirect/path validation.
- Apply Pro's existing authorization gates.
- Cached aggregates + bounded queries for widgets (no unbounded queries).
- Surface findings in the SEO dashboard as a SUMMARY PANEL only — do NOT write them
  into seo_scan_issues or the 0-100 ScoreRubric (different lifecycle; score unchanged).

Read first: src/Filament/SeoProPlugin.php, the existing Pro Filament resources/widgets
(RedirectResource, NotFoundLogResource, SeoDashboard), src/...SEORedirect + its
validation, T6's engine + facade.

Constraints: additive; nav hidden when disabled; Filament 4 AND 5.

Acceptance: nav hidden when disabled; operators can scan/cancel/filter/resolve/open-
source-editor/create-eligible-redirects; widgets don't run unbounded queries; score
semantics unchanged. Pest+Filament tests for permissions, actions, polling, empty
states (F4+F5). Touch only laravel-seo-pro.

Release hygiene (leave the repo release-ready; do NOT tag/publish): CHANGELOG entry
under "Unreleased"; additive minor. Ships with or after T6 in the same Pro release
(it depends on the engine). No new core constraint. Releases are batched via the
Release runbook at the end of this file — do NOT tag or publish here.

When you finish this thread, write its status and mark it DONE: add a `> ✅ DONE` note
(commit + a one-line outcome) at the top of this thread's section and flip its row in the
thread-status table at the top of this file to ✅ DONE, matching how the earlier threads
are annotated, so the next session doesn't redo it.
```

---

## T8 — Install & operations: `seo-pro:install`, doctor, production guide (pro + docs)

```text
You are implementing thread T8 of the Rankbeam SEO roadmap. Work in rankbeam/laravel-
seo-pro (C:\Users\Valentin\Desktop\projects\laravel-seo-pro) plus docs. Read
RANKBEAM-IMPROVEMENT-PLAN.md (T8) first.

Goal: remove the publish-then-migrate footgun and the "can I run this at scale?" doubt.

Build:
- seo-pro:install command — publishes config + migrations and prints next steps. Do
  NOT switch Pro migrations to auto-load (users who already published would get
  duplicate histories). Document publish-only ownership prominently.
- Extend the EXISTING Pro-owned seo:doctor command (do NOT add a competing Core
  command): checks for enabled-feature tables, queue connection/names, cURL +
  broken-links config, a suspicious leftover legacy `seo` table after a completed
  import, and a config/seo.php collision. Doctor validates configuration + recent
  heartbeat evidence — it cannot prove an external cron/worker is running. --json
  exposes stable check IDs.
- A "Production Setup" guide (ONE authoritative page, not duplicated per package):
  dedicated queues per workload (separate scan + broken-links workers; Redis example),
  scheduler entries for Laravel 11/12/13 (sitemap, scan, broken-links-scan, prune,
  redirect-hit flush), batch tuning, Horizon/Supervisor, retry/failure, recovery,
  retention, safe rollout order, Filament-independent deployment. Use the idi reference
  app's queue topology (idi-it-sandbox .env + routes/console.php) as the template.
- Operational telemetry: structured completion events + metrics (pages fetched, links
  checked, blocked URLs, retries, duration, queue lag, stale runs).

Read first: the existing seo:doctor command (DoctorCommand in laravel-seo-pro), the
Pro service provider's publishes(), idi-it-sandbox/.env + routes/console.php.

Acceptance: composer require -> seo-pro:install -> migrate works with no silent no-op;
every recurring command has a recommended cadence in docs; doctor reports stable
check IDs via --json. Write Pest tests for the install command + doctor checks.

Release hygiene (leave the repo release-ready; do NOT tag/publish): CHANGELOG entry
under "Unreleased"; additive minor. seo-pro:install becomes the documented install
path — update the Pro README + docs install steps accordingly. Releases are batched via
the Release runbook at the end of this file — do NOT tag or publish here.

When you finish this thread, write its status and mark it DONE: add a `> ✅ DONE` note
(commit + a one-line outcome) at the top of this thread's section and flip its row in the
thread-status table at the top of this file to ✅ DONE, matching how the earlier threads
are annotated, so the next session doesn't redo it.
```

---

## T9 — WordPress / legacy migration hardening + redirect handoff (core + pro + docs)

> ✅ **DONE** — core commit `5316a39` + pro commit `5705921` (branch
> `feat/rankbeam-improvements`, **not pushed**). Import verification report
> (matched / url-only / truncated / unmapped + **every author value**) on the
> existing importers; anonymized **~900-page WordPress corpus** fixtures (+19 core
> tests, suite 391 green, no regressions); Pro **`seo-pro:redirects-import`**
> consuming the core writer's **redirect CSV format v1** with loop / unsafe-target /
> duplicate / malformed rejection (+15 tests incl. a core-writer round-trip, suite
> 713/3 green); **WordPress migration runbook**. CHANGELOG `[Unreleased]` in both
> repos notes format v1. No command-option change (no UPGRADING). See the matching
> `> ✅ DONE` block in `RANKBEAM-IMPROVEMENT-PLAN.md` for the full outcome.

```text
You are implementing thread T9 of the Rankbeam SEO roadmap across rankbeam/laravel-seo
(core) and rankbeam/laravel-seo-pro plus docs. Read RANKBEAM-IMPROVEMENT-PLAN.md (T9).

Goal: make rankbeam the lowest-risk replacement for a legacy SEO stack / WordPress.

Build:
- Core: anonymized test fixtures derived from a real ~900-page WP migration; expand
  token / malformed-data / morph-map / locale / idempotency tests for the existing
  yoast / rank-math / wordpress-csv importers; add an import VERIFICATION REPORT
  (matched / url-only / truncated / unmapped, incl. every unmapped author value);
  importers stay idempotent + fill-empty-only unless --overwrite; dry-runs write
  nothing. NAME the actual supported command options (verify against the importer
  surface) or include the command-API change explicitly.
- Pro: a validated redirect CSV importer (seo-pro:redirects-import) matching core's
  RedirectCsvWriter output; reject loops, malformed rows, unsafe external targets,
  duplicate sources. CSV is the open-core boundary contract.
- Docs: a WordPress->rankbeam runbook (coexistence -> import meta -> import redirects
  -> verify with seo:audit --strict -> explicit verification BEFORE removing the legacy
  package/table).

Read first: src/Importing/* (core importers, ImportFromCommand), the RedirectCsvWriter,
Pro's SEORedirect + validation, the seo:audit command.

Acceptance: importing a real Yoast/Rank Math export populates seo_meta + emits a CSV
Pro consumes; dry-runs inert; verification report accurate; runbook reproducible.
Write Pest tests for the importers + the Pro CSV importer.

Release hygiene (leave the repo release-ready; do NOT tag/publish): this spans TWO
repos — add a CHANGELOG entry under "Unreleased" in BOTH laravel-seo (importer
hardening) and laravel-seo-pro (the redirect CSV importer). Additive minors; no
UPGRADING needed unless you change a command's option surface (then document it). The
Pro redirect importer reads the core RedirectCsvWriter format — note the format
version in both CHANGELOGs so the contract is traceable. Releases are batched via the
Release runbook at the end of this file — do NOT tag or publish here.

When you finish this thread, write its status and mark it DONE: add a `> ✅ DONE` note
(commit + a one-line outcome) at the top of this thread's section and flip its row in the
thread-status table at the top of this file to ✅ DONE, matching how the earlier threads
are annotated, so the next session doesn't redo it.
```

---

## T10 — Optional computed-fallback depth (core)

> ✅ **DONE** — core, branch `feat/rankbeam-improvements`, **working-tree only**
> (not committed — left beside the still-uncommitted T3 tree it overlaps in
> `HasSEO.php` + `CHANGELOG.md` to avoid mixing scope; batched per the runbook).
> Opt-in, additive, default behaviour byte-identical. New `getSEOImages(): iterable`
> hook on `HasSEO` + immutable `Data\SEOImageCandidate` (`make($url)->priority(100)`)
> + `seo.computed.image_selection` config (`strategy` `first`|`best`, min 200×200,
> ideal 1200×630). `best` scores an ordered candidate list (getSEOImage() stays
> highest-priority, then getSEOImages(), then fields/content/default) by closeness
> to the ideal, skips undersized, **local images only** via the new shared
> `Services\LocalImageInspector` (extracted from `SEOWarningEvaluator`, which now
> delegates to it); falls back to first-match when nothing local qualifies. Default
> `first` strategy untouched (verbatim old body). +15 tests, full core suite **406
> green** (was 391). See the `> ✅ DONE` block in `RANKBEAM-IMPROVEMENT-PLAN.md` for
> the full outcome.

```text
You are implementing thread T10 of the Rankbeam SEO roadmap in rankbeam/laravel-seo
(core) at C:\Users\Valentin\Desktop\projects\laravel-seo. Read RANKBEAM-IMPROVEMENT-
PLAN.md (T10). NOTE: getSEOOgType(), dates, and getSEOAuthor() ALREADY exist — do not
rebuild them. Brand-aware title-suffix suppression is owned by T1, not here.

Goal: close the remaining fallback-quality gap (idi's resolver was smarter).

Build (opt-in, additive):
- Multi-candidate social-image selection by dimension closeness to a configurable
  ideal (1200x630), skipping undersized (<200x200), over an ordered candidate list
  exposed via an optional getSEOImages(): iterable model hook. LOCAL images only in
  Core (no remote fetch — SSRF/latency/cache; remote checks are the Filament preview's
  client-side job). Default strategy stays first-match (current getSEOImage() behavior
  unchanged + highest priority).
- Config: seo.computed.image_selection => ['strategy' => 'first', 'minimum_width' =>
  200, 'minimum_height' => 200, 'ideal_width' => 1200, 'ideal_height' => 630].

Read first: src/Services/SEOComputedBuilder.php, src/Traits/HasSEO.php (getSEOImage),
config/seo.php.

Constraints: additive; default behavior unchanged; getSEOImage() stays highest-priority.

Acceptance: opt-in scoring prefers best-sized + skips undersized local images; default
strategy unchanged; article metadata still only for og:type=article. Pest tests; run
them. Touch only laravel-seo.

Release hygiene (leave the repo release-ready; do NOT tag/publish): CHANGELOG entry
under "Unreleased"; additive minor (new opt-in config + getSEOImages() hook; defaults
preserve current behavior, no UPGRADING). Releases are batched via the Release runbook
at the end of this file — do NOT tag or publish here.

When you finish this thread, write its status and mark it DONE: add a `> ✅ DONE` note
(commit + a one-line outcome) at the top of this thread's section and flip its row in the
thread-status table at the top of this file to ✅ DONE, matching how the earlier threads
are annotated, so the next session doesn't redo it.
```

---

## T11 — Schema composition polish (core)

> ✅ **DONE** — core, branch `feat/rankbeam-improvements`, **working-tree only**
> (not committed — left beside the uncommitted T3 + T10 tree it overlaps in
> `HasSEO.php` / `config/seo.php` / `CHANGELOG.md` to avoid mixing scope; batched
> per the runbook). Additive minor, no UPGRADING. Built ONLY the missing
> composition layer on top of the existing `SchemaGraph` +
> `BreadcrumbSchema::fromModelAncestors()` (neither rebuilt): new
> `Services\Schema\SchemaGraphBuilder` + a `SchemaGraph::for($model)` factory —
> fluent `->organization()->website()->webPage()->breadcrumbFromAncestors()
> ->toArray()` (webPage() resolves `seoData()` or a passed `SEOData`;
> breadcrumb DELEGATES, no parallel API; deterministic stable @ids);
> `HasSEO::getSEOSchema(): array` hook (default `[]`); `seo.schema.type_map`
> config (model class → invokable/Closure/callable builder, exact then
> `instanceof`). Precedence wired in `SEOResolver` as a final Layer 6
> (`applyModelSchema`): an explicit stored `seo_meta.schema_jsonld` (or a
> default-layer schema) is AUTHORITATIVE — emitted as-is, hook/type-map NOT
> invoked (asserted via a call-counter spy); a static re-entrancy guard breaks
> the `getSEOSchema()`→`webPage()`→`seoData()` cycle. Every composed node passes
> `SchemaValidator`. +18 Pest (8 unit + 10 feature); core suite **424 green**
> (was 406; no regressions). Pint not run (no pint.json — hand-matched style).
> CHANGELOG `[Unreleased]`. Nothing tagged/published.

```text
You are implementing thread T11 of the Rankbeam SEO roadmap in rankbeam/laravel-seo
(core) at C:\Users\Valentin\Desktop\projects\laravel-seo. Read RANKBEAM-IMPROVEMENT-
PLAN.md (T11). NOTE: SchemaGraph and a loop-guarded BreadcrumbSchema::fromModelAncestors()
ALREADY EXIST — do NOT rebuild them. Build only the missing composition layer.

Goal: make an @id-linked Organization/WebSite/WebPage + breadcrumb graph easy to
assemble with minimal app glue (the reference app hand-rolled SitewideSchema).

Build:
- A SchemaGraph composition helper + a per-model getSEOSchema(): array hook + an
  optional config type-map (model class -> builder), all ON TOP of existing primitives.
- Precedence (decided, mirrors explicit-over-computed): explicit stored
  seo_meta.schema_jsonld is AUTHORITATIVE — when present it's emitted as-is and the
  hook/assembler is NOT invoked for that model (no silent merge). When absent,
  getSEOSchema() (or the config type-map) produces the graph.

API:
  public function getSEOSchema(): array; // one or more schema.org nodes
  SchemaGraph::for($model)->organization()->website()->webPage()->breadcrumbFromAncestors()->toArray();

Read first: src/.../SchemaGraph, src/.../BreadcrumbSchema (fromModelAncestors), the
schema builders dir, src/Traits/HasSEO.php, the reference SitewideSchema at
idi-it-sandbox/app/Support/SitewideSchema.php.

Constraints: additive; no parallel breadcrumb API.

Acceptance: a model with no stored schema exposes a cross-linked WebPage +
BreadcrumbList via the hook with deterministic, stable @ids; a model WITH stored
schema_jsonld emits exactly that and the hook is NOT called (assert this); validates.
Pest tests; run them. Touch only laravel-seo.

Release hygiene (leave the repo release-ready; do NOT tag/publish): CHANGELOG entry
under "Unreleased"; additive minor (new getSEOSchema() hook + composition API; no
behavior change for models that don't implement it, no UPGRADING). Releases are batched
via the Release runbook at the end of this file — do NOT tag or publish here.

When you finish this thread, write its status and mark it DONE: add a `> ✅ DONE` note
(commit + a one-line outcome) at the top of this thread's section and flip its row in the
thread-status table at the top of this file to ✅ DONE, matching how the earlier threads
are annotated, so the next session doesn't redo it.
```

---

## T12 — Resolver caching for hot frontends (core)

> ✅ **DONE** — core, branch `feat/rankbeam-improvements`, **working-tree only**
> (not committed — left beside the uncommitted T3 + T10 + T11 tree it overlaps in
> `SEOResolver.php` / `HasSEO.php` / `config/seo.php` / `CHANGELOG.md`; batched per
> the runbook). Additive, opt-in, **off by default**; behaviour byte-identical with
> caching off. New `Services\SEOResolutionCache` caches a resolved model's SEO as a
> plain **flat** array (`toFlatArray()`, NOT the lossy nested `toArray()` named in the
> prompt — `toArray()` is not the inverse of `fromArray()`; the two DateTime fields are
> stored ISO-8601 so they round-trip to the second across timezones) rehydrated via
> `SEOData::fromArray()`; keyed by `(model class, id, locale, route, request URL)`.
> `SEOResolver::resolve()` split into a cache wrapper over a `buildResolved()` body with
> a re-entrancy depth guard (the schema layer's nested `webPage()` resolve neither
> reads nor writes the cache; on a hit the whole chain — incl. schema — is skipped).
> **Invalidation added (none existed):** new `SEOMeta` `saved`/`deleted` model listener
> (morph alias → FQCN), new `HasSEO` `saved` (busts on a `getSEOContentFields()` change)
> + `deleted`, and `SEODefault` change now flushes the whole resolution cache — all
> inert when caching is off. **Taggable** stores (redis/memcached/array) clear a model
> via cache **tags**; **non-taggable** stores (file/database) fall back to a per-model
> **version stamp** — both proven. Only model-backed resolves are cached (hand-built
> `SEOData` / `@seoForRoute` resolve live). **Benchmark:** 25 resolves → uncached 25
> queries, cached **0** queries (~8× faster wall-clock). +24 Pest tests
> (`Unit/Services/SEOResolutionCacheTest` 11 ×{array,non-taggable}; `Feature/
> ResolverCacheTest` 12 incl. hit-skips-chain via raw DB write, all 3 bust paths,
> ON==OFF parity, re-entrant-schema parity, morph-alias bust; `Feature/
> ResolverCacheBenchmarkTest` 1) + a hermetic `Fixtures\NonTaggableArrayStore`; full
> core suite **448 green** (was 424; no regressions). CHANGELOG `[Unreleased]` +
> `docs/reference/configuration.md` scale-lever section; VitePress builds clean. Pint
> not run (no pint.json — hand-matched style). Nothing tagged/published.

```text
You are implementing thread T12 of the Rankbeam SEO roadmap in rankbeam/laravel-seo
(core) at C:\Users\Valentin\Desktop\projects\laravel-seo. Read RANKBEAM-IMPROVEMENT-
PLAN.md (T12).

Goal: the resolver runs on every frontend request; at high traffic (the reference app
does ~20k/day) it should be cacheable.

Build:
- Cache the ARRAY form (SEOData::toArray()), NOT the object — Laravel 13 can rehydrate
  cached objects as __PHP_Incomplete_Class (the codebase already caches arrays in
  SEODefaultsRepository for exactly this reason). Rehydrate via SEOData::fromArray() on
  read. Key by (model class, id, locale, route).
- Invalidation MUST BE ADDED (it does not exist today — HasSEO only hooks
  created/deleted, and SEOMeta has no cache hooks): bust on SEOMeta saved/deleted, on
  model saves that change getSEOContentFields(), and on seo_defaults change. For
  route-key fan-out use cache TAGS (or a versioned namespace / explicit index) so one
  model's entries clear without key-scanning; fall back to a version-stamp strategy on
  non-taggable stores. Respect seo.cache.store. OFF by default.

Read first: src/Services/SEOResolver.php, src/Services/SEODefaultsRepository.php (~line
169 — the array-caching precedent), src/Traits/HasSEO.php (event hooks), src/Models/
SEOMeta.php, src/Data/SEOData.php (toArray/fromArray), config/seo.php (cache).

Constraints: additive, off by default; correctness with caching ON must equal caching
OFF.

Acceptance: cache hit skips the precedence chain; saving seo_meta, mutating a content
field, or editing defaults busts the right entries; parity test (cached == uncached);
works on BOTH taggable and non-taggable cache stores; a benchmark shows the win. Pest
tests; run them. Touch only laravel-seo.

Release hygiene (leave the repo release-ready; do NOT tag/publish): CHANGELOG entry
under "Unreleased"; additive minor (caching off by default, no UPGRADING). If you add a
SEOMeta `saved`/`deleted` cache hook in HasSEO, note it in the CHANGELOG (it's a new
model-event listener, but inert when caching is off). Releases are batched via the
Release runbook at the end of this file — do NOT tag or publish here.

When you finish this thread, write its status and mark it DONE: add a `> ✅ DONE` note
(commit + a one-line outcome) at the top of this thread's section and flip its row in the
thread-status table at the top of this file to ✅ DONE, matching how the earlier threads
are annotated, so the next session doesn't redo it.
```

---

## T13 — Positioning / "non-negotiable" narrative (docs / site)

> ✅ **DONE** — laravel-seo VitePress docs commit `376da55` (branch
> `feat/rankbeam-improvements`, **not pushed**). New `docs/guide/why-rankbeam.md`
> ("Why Rankbeam, not three packages + glue") + nav/sidebar wiring; VitePress builds
> clean. **Swap number grounded in a real diff, not the prompt's ~20:** `idi-it`
> (ralphjsmit + backstage) vs `idi-it-sandbox` (rankbeam) shows the swap **deleted 12
> bespoke glue classes outright** (table → rankbeam equivalents); honest note that the
> kept crawler + meta/schema helpers (~22 more) are what the Pro crawler / Filament
> target+preview / core schema graph now replace (~34 total). Page: anonymized
> reference app (hospital/institutional — idi NOT named), side-by-side capability table,
> "three things glue can't do well", honest **"what is NOT in free core"** open-core
> table, lowest-risk WP-migration story (links the existing runbook, no dup), and 2
> benchmarks cited only on deterministic asserts (resolver cache **0 DB queries** warm
> vs ≥25 uncached; crawler **~900-page corpus / ≥18 bounded jobs** / 1,800 links / no
> job >50-page cap). No invented wall-clock; no version baked in. Docs-only — no package
> code/version-bump/tag/CHANGELOG. Plan/prompts `.md` stay UNTRACKED (public repo) —
> markers edited in the working tree only. **RT0–RT15 + T1–T13 plan complete.**

```text
You are implementing thread T13 of the Rankbeam SEO roadmap — a docs/marketing thread.
Read RANKBEAM-IMPROVEMENT-PLAN.md (T13) and EVIDENCE.md in the laravel-seo repo, plus
rankbeam-improvement-plan/review-claude.md (it has the legacy-vs-rankbeam comparison).

Goal: convert the real production evidence into adoption — make choosing rankbeam over
"three packages + glue" obvious to other Laravel devs.

Build (docs/site content, anonymize the client as needed):
- A comparison page: rankbeam vs the multi-package + glue approach, using the real
  swap (the reference app removed ~20 custom glue files; gained one cohesive family,
  locale-aware storage, headless rendering). Be honest about what's NOT in core.
- The WordPress-migration story (lowest-risk switch from Yoast/Rank Math).
- Benchmark numbers from T12 once available.

Read first: rankbeam-improvement-plan/review-claude.md and EVIDENCE.md (the legacy-vs-
rankbeam analysis + harvest), the existing docs site structure.

Acceptance: a landing/doc section a skeptical Laravel dev reads and concludes "why
would I glue three packages together?" No code changes required.

Release hygiene: docs/site only — no package version bump, no tag, no CHANGELOG entry
needed (unless the docs site has its own changelog). Ship with the docs deploy, not a
package release.

When you finish this thread, write its status and mark it DONE: add a `> ✅ DONE` note
(commit + a one-line outcome) at the top of this thread's section and flip its row in the
thread-status table at the top of this file to ✅ DONE, matching how the earlier threads
are annotated, so the next session doesn't redo it.
```

---

## RELEASE RUNBOOK — paste when cutting a coordinated release (NOT per thread)

```text
You are cutting a coordinated release of the rankbeam SEO package family after a batch
of improvement threads has landed. Repos are siblings under
C:\Users\Valentin\Desktop\projects\: laravel-seo (core, MIT, Packagist),
laravel-seo-filament (MIT, Packagist), laravel-seo-pro (PROPRIETARY, PRIVATE repo,
Anystack — NEVER public Packagist). Read RANKBEAM-IMPROVEMENT-PLAN.md (the "Release
roadmap" section) first.

This is an OUTWARD-FACING, hard-to-reverse operation (tags + published packages).
DO NOT push tags or publish without showing me the version plan and getting explicit
confirmation. Verify facts against the repos — do NOT trust hardcoded versions.

Step 1 — Determine current state (read-only):
  - For each repo: current latest git tag, the "Unreleased" CHANGELOG section, and the
    cross-package constraints in composer.json (pro & filament require
    `rankbeam/laravel-seo`; confirm the exact constraint, e.g. ^2.0||^3.0).
  - List which threads/commits since the last tag touched each repo.

Step 2 — Decide versions (SemVer + the roadmap's release boundaries):
  - Additive-only changes (new opt-in config/API, no default flips) => MINOR bump.
  - Any behavior change / default flip (e.g. T2's blank_is_unset default, or a BC
    break) => MAJOR (Core 4 / Pro 3) — these must NOT ride a minor.
  - Present the proposed version per repo (e.g. core 3.1.0, filament 1.3.0, pro 2.1.0)
    with the one-line reason, and STOP for confirmation.

Step 3 — Finalize docs per repo:
  - Move "Unreleased" CHANGELOG entries under the new version + date.
  - Ensure UPGRADING.md covers any documented behavior change / required migration
    publish (e.g. the T6 crawler migrations, the planned Core 4 blank-default flip).

Step 4 — Cross-package version constraints:
  - If core gained public API that filament/pro now CALL, bump those siblings'
    `rankbeam/laravel-seo` constraint to require the new minimum (e.g. ^3.1) and make
    sure the consuming change ships only in a sibling release tagged AFTER core.
  - If core goes to a new MAJOR (4.x), widen pro/filament constraints (e.g.
    ^2.0||^3.0||^4.0) and re-test resolution.

Step 5 — Green gate (do this BEFORE any tag):
  - Run the FULL test suite in each repo and confirm green:
      Set-Location <repo>; & php artisan test   (or vendor/bin/pest in the package dir)
  - Run a clean-room install resolution check: in a throwaway dir, `composer require`
    each package at the about-to-be-tagged version constraint and confirm it resolves
    (path repos or the private repo as configured). Do NOT proceed if anything fails.

Step 6 — Tag + push in DEPENDENCY ORDER (after confirmation):
  Order: laravel-seo-filament -> laravel-seo (core) -> laravel-seo-pro.
  (Each is tagged independently; Packagist/Anystack resolve constraints at install
  time. Confirm this order against any prior release notes before pushing.)
  For each: `git tag vX.Y.Z` (signed if the repo convention uses it — do NOT add
  -s/--no-gpg-sign contrary to repo convention), `git push origin vX.Y.Z`.

Step 7 — Publish:
  - laravel-seo + laravel-seo-filament: PUBLIC Packagist auto-syncs from the GitHub tag
    (confirm the Packagist webhook/auto-update fired; trigger an update if not).
  - laravel-seo-pro: it is PRIVATE/PROPRIETARY — publish via Anystack ONLY. NEVER push
    it to public Packagist.
  - Create a GitHub Release per repo with notes generated from the new CHANGELOG
    section (`gh release create vX.Y.Z --notes-file ...` or from the CHANGELOG).

Step 8 — Post-release smoke test:
  - In a clean throwaway project, `composer require` each package at the new published
    version and confirm install + a trivial boot (e.g. `php artisan vendor:publish
    --tag=seo-config`) works. For pro, use the Anystack/private-repo auth.

Report: the version plan, what was tagged/published, the green-gate results, and the
smoke-test outcome. STOP and ask before Step 6 (tagging) and Step 7 (publishing).
```

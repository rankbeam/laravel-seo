# The SEO score — transparent, versioned, Pro-owned

The Pro scan gives every page a **0-100 SEO score** — the single number a
RankMath or Yoast migrant looks for. Unlike a black-box grade, this one is
**fully auditable**: every point it deducts traces to exactly one
[scan issue](/pro/scan-issues), and the same set of issues always produces the
same number.

```
score = 100 − Σ penalty(issue) for each scored issue   (floored at 0)
```

::: tip One number, one owner
The numeric score is a **Pro** feature. It lives on the Pro `seo_scan_results`
record, never back in core's `seo_meta` (the old `seo_score` column was removed
in Core 3). The free core [`seo:audit`](/guide/audit) reports per-page
**pass / warn / fail** with **no number** — the score is the paid value-add.
:::

## The rubric

The score is computed from a **published, versioned rubric** —
`Rankbeam\Seo\Pro\Scanning\ScoreRubric`. Two things define it: an explicit
**allowlist** of issue codes that count, and a fixed **penalty per severity**.

| Severity | Penalty | Meaning |
|---|---|---|
| `critical` | **−40** | Blocks indexing or breaks the page. |
| `warning` | **−15** | A real defect to fix soon. |
| `notice` | **−5** | A nice-to-have. |

Each code's severity is read straight from the [issue registry](/pro/scan-issues)
(the single source of truth) — the rubric never re-derives it. Severity is
1:1 per code precisely so the score stays deterministic.

### What the score counts

Every code below is a deterministic, objective defect of the page itself. A
critical costs 40, a warning 15, a notice 5.

| Code | Severity | Penalty |
|---|---|---|
| `missing_title` | critical | −40 |
| `missing_description` | warning | −15 |
| `missing_og_image` | notice | −5 |
| `duplicate_title` | warning | −15 |
| `duplicate_description` | warning | −15 |
| `title_too_long` | warning | −15 |
| `title_too_short` | notice | −5 |
| `description_too_long` | warning | −15 |
| `description_too_short` | notice | −5 |
| `robots_conflict_indexing` | critical | −40 |
| `robots_conflict_following` | warning | −15 |
| `noindex_warning` | warning | −15 |
| `invalid_canonical` | critical | −40 |
| `cross_domain_canonical` | warning | −15 |
| `shared_canonical` | notice | −5 |
| `insecure_canonical` | warning | −15 |
| `http_error` | critical | −40 |
| `empty_response` | critical | −40 |
| `missing_canonical` | notice | −5 |
| `missing_h1` | notice | −5 |
| `missing_image_alt` | warning | −15 |
| `thin_content` | notice | −5 |
| `mixed_content` | warning | −15 |
| `canonical_target_broken` | critical | −40 |
| `canonical_target_redirect` | warning | −15 |
| `canonical_target_noindex` | warning | −15 |

Because the metadata codes are detected on a model scan and the rendered/network
codes only on a URL scan (see the [execution classes](/pro/scan-issues#execution-classes)),
a **model** target's score reflects the metadata checks and a **URL** target's
score reflects the rendered page. A model scan scoring 100 means "no metadata
defects", not "the rendered page is perfect" — scan the URL for that.

### What the score deliberately does NOT count

These registry codes are excluded on purpose. The exclusions are part of the
contract (a test asserts every registry code is either scored or listed here):

| Code | Why it's excluded |
|---|---|
| `missing_focus_keyword` | **Advisory.** Gated behind the opt-in `seo.keywords.enabled` workflow — a page must not score lower for not adopting focus keywords, and the score must not depend on a config flag. |
| `noindex_page` | **Informational.** Being `noindex` is a deliberate state, not a meta-quality defect. The "noindex on an apparently important page" contradiction is scored via `noindex_warning` instead. |
| `multiple_h1` | **Informational.** Google tolerates multiple H1s — no multi-H1 penalty. |
| `blocked_url` | **Absence of evidence.** The SsrfGuard refused the fetch, so the page was never checked — not a defect of the page. |
| `canonical_target_blocked` | **Absence of evidence.** The canonical target could not be verified — not a defect of the page. |
| `hreflang_invalid_code`, `hreflang_missing_self_reference`, `hreflang_duplicate_code`, `hreflang_missing_x_default` | **Advisory (for now).** The hreflang validator surfaces in the scan + free audit but does not yet move the score — adding it would require a `VERSION` bump. |
| `aeo_missing_author`, `aeo_article_missing_date` | **Advisory.** Answer-readiness (AEO) signals — they flag an article missing an author entity or a publish date in the scan + free audit, but do not move the score (would require a `VERSION` bump). |

Keyword density, power words, and the rest of the
[on-page checklist](/pro/on-page-checklist) never enter the score at all — they
are advisory checks (a separate, never-gameable pass/warn/fail list), not
registry codes.

## Versioning — historical scores never silently change

Every persisted score is stamped with the `ScoreRubric::VERSION` that produced
it (`rubric_version`). Two consequences:

- A **new** issue code scores **nothing** until it is deliberately added to the
  allowlist — so shipping a new check can never retroactively change a stored
  score. (An allowlist or weight change is itself a rubric change and bumps the
  version.)
- The score is **stored, not recomputed on read.** The number you saw last
  week is the number you see today, alongside the rubric that explains it.

## Where it's stored

Each scan upserts one row per target into `seo_scan_results`:

| Column | What it holds |
|---|---|
| `scannable_type` / `scannable_id` | The scored model (null for URL targets). |
| `url` | The scored URL. |
| `score` | The 0-100 number. |
| `rubric_version` | The rubric that produced it. |
| `penalty_total` | Raw penalty sum **before** the 0-floor. |
| `scored_issues` | How many issues moved the number. |
| `breakdown` | `[{code, severity, penalty}, …]` — the full trace. |
| `keywords_enabled` | The `seo.keywords.enabled` state at scan time (recorded for transparency; the score does not depend on it). |
| `scan_run_id` | The run that scored it (set null, not deleted, when a run is pruned — scores are current state, not run history). |
| `scored_at` | When it was scored. |

## Reading the score

**Headless** — the latest score for a model:

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$result = SeoPro::resultFor($post);

$result?->score;     // e.g. 85
$result?->grade();   // 'A'..'F'
$result?->breakdown; // [['code' => 'cross_domain_canonical', 'severity' => 'warning', 'penalty' => 15]]
```

`php artisan seo-pro:scan-status` prints the **average site score** in its
summary. The Filament dashboard shows it as the headline "Avg. SEO score" stat,
coloured by grade.

### Grade bands

A presentational letter grade derived from the number (the number is the
contract):

| Score | Grade |
|---|---|
| 90-100 | A |
| 75-89 | B |
| 50-74 | C |
| 25-49 | D |
| 0-24 | F |

## The shipping signal (`noindex_warning`)

`noindex_warning` fires when a page says `noindex` **and** signals it is meant
to ship (be indexed). The signal is metadata-resolvable: a **self-canonical**
page (its canonical names its own URL) declares itself the indexable version,
so `noindex` contradicts it. A page with a *cross-domain* canonical is
deliberately delegating indexation elsewhere — not a contradiction, so it does
not fire. The emitted issue carries `context.shipping_signal` (e.g.
`self_canonical`), plus the `canonical` and `page_url` it compared.

Both scanners apply this: the model scan (`PageScanner`) compares the stored
canonical to the model's URL, and the rendered URL scan (`UrlScanner`) escalates
a self-canonical `noindex` page from the informational `noindex_page` to the
scored `noindex_warning`. That symmetry is why `noindex_page` itself is excluded
from the score — the contradiction is always caught by `noindex_warning`, on
either scan path.

## Configuration

```php
// config/seo-pro.php → 'scan'
'score' => [
    'enabled' => true, // turn the scoring pass + its persistence on/off
],
```

The allowlist and the weights are **not** configurable: the score must be
deterministic for a given `rubric_version` across every install, so changing
how it is computed is a code-level rubric change, not a setting.

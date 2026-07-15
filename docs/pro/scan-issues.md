---
description: "The stable issue-code registry behind every problem the Pro scan reports — each code carries a fixed severity and field, so dashboards and exports read codes, not messages."
---

# Scan issues — the issue-code registry

Every problem the Pro scan reports is a **stable issue code** drawn from a
single registry, `Rankbeam\Seo\Pro\Scanning\IssueRegistry`. The scanners never
invent a code inline — they build each issue through `IssueRegistry::make()`,
which stamps the severity and field from the registry and **rejects any code
that isn't defined**. That makes the catalogue below a contract you can build
on: dashboards, exports, the free [`seo:audit`](/guide/audit) command, and the
[Pro score](/pro/scoring) all read these codes rather than parsing messages.

Each code carries:

- **id** — the stable string stored as `seo_scan_issues.issue_type`.
- **severity** — `critical`, `warning`, or `notice`; **fixed per code** (we
  split a code rather than vary its severity).
- **field** — the `seo_meta` field it concerns, or _page_ for page-level
  findings.
- **execution class** — what a consumer needs to detect it (see below).
- **evidence** — the keys the issue's `context` array carries.

## Execution classes

A check falls into exactly one of three classes, by what it needs to run:

| Class | Needs | Who can run it |
|---|---|---|
| **metadata** | the model + the core resolver — no page fetch | the model scan (`PageScanner`); the free [`seo:audit`](/guide/audit) command |
| **rendered** | the page's served HTML (in-process kernel request, or an external fetch) | the URL scan (`UrlScanner`) |
| **network** | an **outbound** fetch to validate a _separate_ target (a canonical pointing elsewhere) | the URL scan, **always through the `SsrfGuard`** |

This is why a free, in-process audit can never equal the full Pro scan: only
the **metadata** codes are computable without rendering a page, and only the
Pro pipeline fetches rendered HTML and validates canonical targets over the
network. Filter the registry by class with
`IssueRegistry::byClass(IssueRegistry::EXEC_METADATA)`.

## Metadata codes

Detected from the model + resolver by `PageScanner`. (`missing_title`,
`missing_description` and the length codes are also emitted by the rendered URL
scan, measuring the served `<head>` — same codes, same meaning.)

| Code | Severity | Field | Evidence | Meaning |
|---|---|---|---|---|
| `missing_title` | critical | title | — | No title and no computable fallback. |
| `missing_description` | warning | description | — | No meta description and no computable fallback. |
| `missing_og_image` | notice | og_image | — | No Open Graph image and no computable fallback. |
| `missing_focus_keyword` | notice | focus_keywords | — | No focus keyword set. |
| `duplicate_title` | warning | title | `title`, `duplicate_urls` | Title reused on other pages in the same locale. |
| `duplicate_description` | warning | description | `description`, `duplicate_urls` | Description reused on other pages in the same locale. |
| `title_too_long` | warning | title | `length`, `max` | Resolved title over the 60-char recommendation. |
| `title_too_short` | notice | title | `length`, `min` | Resolved title under the 30-char floor. |
| `description_too_long` | warning | description | `length`, `max` | Resolved description over the 160-char recommendation. |
| `description_too_short` | notice | description | `length`, `min` | Resolved description under the 70-char floor. |
| `robots_conflict_indexing` | critical | robots | `robots` | Robots directive has both `index` and `noindex`. |
| `robots_conflict_following` | warning | robots | `robots` | Robots directive has both `follow` and `nofollow`. |
| `noindex_warning` | warning | robots | `robots`, `canonical`, `page_url`, `shipping_signal` | A self-canonical (apparently important) page is `noindex`. Emitted on both model and rendered URL scans. |
| `invalid_canonical` | critical | canonical | `canonical` | Canonical value is not a valid URL. |
| `cross_domain_canonical` | warning | canonical | `canonical`, `page_url` | Canonical points to a different host than the page. |
| `shared_canonical` | notice | canonical | `canonical` | Several pages declare the same canonical. |
| `insecure_canonical` | warning | canonical | `canonical` | `http://` canonical on an `https` site (mixed content). |
| `hreflang_invalid_code` | warning | alternates | `invalid_codes` | An hreflang alternate uses a value that is not `x-default` nor a valid BCP-47 language code. |
| `hreflang_missing_self_reference` | warning | alternates | `locale`, `page_url` | Alternates are declared but none references the page's own locale (a self-referencing hreflang). |
| `hreflang_duplicate_code` | warning | alternates | `duplicate_codes` | The same hreflang code maps to more than one URL (an ambiguous cluster). |
| `hreflang_missing_x_default` | notice | alternates | `languages` | A multi-language hreflang cluster has no `x-default` fallback. |
| `aeo_missing_author` | notice | schema | — | An article in the page's structured data has no author entity (no attribution for AI answer engines). |
| `aeo_article_missing_date` | notice | schema | — | An article in the page's structured data has no published/modified date (a recency signal for AI answer engines). |

The length thresholds reuse the core `SEOWarningEvaluator` constants (60/160),
so a scan never contradicts the editor's character counters; the lower bounds
(title 30, description 70) are the Pro scan's under-optimised floor and live on
`IssueRegistry::TITLE_MIN_LENGTH` / `DESCRIPTION_MIN_LENGTH`. Length is measured
against the **resolved** title/description — the value that actually renders,
including any fallback and title suffix.

The `hreflang_*` codes validate a page's declared hreflang alternates (read from
the resolver's `alternates`): invalid/duplicate codes, a missing self-reference,
and a missing `x-default` on a multi-language cluster. They run only when the
page declares alternates. Cross-page **reciprocity** ("return tags") is not yet
validated.

The `aeo_*` codes are **answer-readiness (AEO)** signals — is the page's article
content extractable and attributable by AI answer engines? They read the
resolved JSON-LD graph and fire **only** when it declares article-type
structured data (`Article`, `BlogPosting`, `NewsArticle`, …) missing an `author`
entity (attribution / E-E-A-T) or a `datePublished` / `dateModified` (recency).
A page without an article is never flagged. They are gated by
`seo-pro.scan.checks.aeo` (on by default) and mirror the free
[`seo:audit`](/guide/audit).

::: tip `missing_focus_keyword` is gated
The focus-keyword notice only fires when the **core** focus-keyword workflow is
enabled (`seo.keywords.enabled`, default `false`). While off, the scan does not
flag a page for having no focus keyword. The free [`seo:audit`](/guide/audit)
command and the Filament editor read the **same** core flag, so the scan, the
audit, and the editor nag always agree — there is only ever one gate.
:::

## Rendered codes

Detected by `UrlScanner` from the served HTML — for same-host targets via an
in-process kernel request (no outbound traffic), for external targets via a
guarded fetch.

| Code | Severity | Field | Evidence | Meaning |
|---|---|---|---|---|
| `http_error` | critical | page | `status` | URL responded with a 4xx/5xx status. |
| `empty_response` | critical | page | — | URL returned an empty body. |
| `missing_canonical` | notice | canonical | — | No `<link rel="canonical">` in the rendered head. |
| `noindex_page` | notice | robots | `robots` | Rendered page is `noindex` (informational). A `noindex` page that is also **self-canonical** is escalated to the scored `noindex_warning` instead. |
| `missing_h1` | notice | page | — | No `<h1>` heading. |
| `multiple_h1` | notice | page | `count` | More than one `<h1>` (informational). |
| `missing_image_alt` | warning | page | `count`, `total`, `sample` | Content images missing an `alt` attribute (an explicit `alt=""` is treated as decorative, not flagged). |
| `thin_content` | notice | page | `word_count`, `threshold` | Body text below the configured word count. |
| `mixed_content` | warning | page | `count`, `sample` | `http://` sub-resources on an `https` page. |

## Network codes

Detected by `UrlScanner` only when
`seo-pro.scan.url_checks.check_canonical_target` is enabled. The canonical
target is fetched **through the `SsrfGuard`** (scheme allowlist, host scope,
private-IP rejection, redirect/time/size budgets) and **not** followed through
redirects, so a redirecting canonical is visible. A self-referencing canonical
is skipped — the page itself was just fetched.

| Code | Severity | Field | Evidence | Meaning |
|---|---|---|---|---|
| `blocked_url` | notice | page | `reason` | A target was refused by the `SsrfGuard` before any HTTP happened. |
| `canonical_target_broken` | critical | canonical | `canonical`, `status` | Canonical points to a page returning an HTTP error. |
| `canonical_target_redirect` | warning | canonical | `canonical`, `status`, `location` | Canonical points to a page that redirects; point it at the final URL. |
| `canonical_target_noindex` | warning | canonical | `canonical` | Canonical points to a page that is itself `noindex`. |
| `canonical_target_blocked` | notice | canonical | `canonical`, `reason` | Canonical target could not be verified (guard refusal / unresolvable). |

Every network path here reuses the shared `SsrfGuard`; see
[SECURITY.md](https://github.com/rankbeam/laravel-seo-pro/blob/master/SECURITY.md)
for the threat model and the residual TOCTOU note.

## How codes feed the score

The [Pro SEO score](/pro/scoring) is `100 −` a fixed penalty per scored issue,
weighted by the severities above. Most codes count; a few are deliberately
excluded — `missing_focus_keyword` (advisory), `noindex_page` and `multiple_h1`
(informational), `blocked_url` / `canonical_target_blocked` ("we couldn't
check" ≠ a defect), and the `hreflang_*` and `aeo_*` codes (advisory signals
held out of the score for now).
The [scoring page](/pro/scoring) has the full allowlist and the penalty for
every code.

## Issue lifecycle

An issue is not just a row that exists while a problem is present — it has a
lifecycle, and a scan **reconciles** the target's issues rather than wiping and
re-creating them. Each issue has a stable identity: the target
(`scannable_type` + `scannable_id` for a model, or the `url` for a route/sitemap
target) plus its `issue_type`. Every code is emitted at most once per target per
scan — the "N offenders" codes (`missing_image_alt`, `mixed_content`,
`hreflang_*`, …) fold their instances into a single row with a `count` / `sample`
— so that identity is unique.

On each scan, for every target:

- a finding with **no existing row** is created `open`, stamped `detected_at`;
- a finding that **matches an existing open row** refreshes its evidence and
  keeps its original `detected_at` — a stable *first-seen*, no longer reset on
  every scan;
- an open issue the scan **no longer finds** is marked **`fixed`**
  (`resolved_at` stamped) — the row is **kept, not deleted**, so a genuine fix
  is recorded;
- a **`fixed`** issue that **returns** is **reopened** in place (a regression),
  re-stamping `detected_at`;
- an issue a user marked **`ignored`** in the dashboard is left untouched.

| Status | Meaning | Set by |
|---|---|---|
| `open` | Currently present. | the scan (new or still-found) |
| `fixed` | Was present, no longer found. | the scan (auto), on the next run that doesn't re-find it |
| `ignored` | Muted by a user; excluded from open counts and the score. | the dashboard Ignore action |

Because fixes are now recorded rather than discarded, the white-label
[report](/pro/reports) can show **real fixed / new counts** over a period
instead of a report-to-report snapshot diff. Open-count consumers — the
dashboard, the [`seo-pro:scan-status`](/pro/headless) command, the
[score](/pro/scoring) — all filter to `open`, so persisted `fixed` rows never
inflate them. Fixed rows are attributed to the run that resolved them and age
out with the normal scan-run [retention](/pro/production) window.

## Configuration

```php
// config/seo-pro.php → 'scan'
'url_checks' => [
    'enabled' => true,
    'crawl_external' => false,          // fetch external URL targets (guarded)
    'check_canonical_target' => false,  // EXEC_NETWORK canonical validation (guarded)
],
'checks' => [
    'length' => true,            // title/description length (metadata + rendered)
    'rendered_content' => true,  // H1 / alt / thin content / mixed content
],
'content' => [
    'min_word_count' => 200,     // thin_content threshold
    'evidence_sample' => 5,      // max example URLs stored per issue
],
```

The response-size budget for guarded fetches is `seo-pro.http.max_response_bytes`
(default 2 MB); the in-process same-host scan is not capped.

## Compatibility note (issue-code rename)

The previous single `robots_conflict` code (which carried two severities) was
split so every code maps to exactly one severity:

| Old code | New code | Severity |
|---|---|---|
| `robots_conflict` (index + noindex) | `robots_conflict_indexing` | critical |
| `robots_conflict` (follow + nofollow) | `robots_conflict_following` | warning |

If you stored or filtered on `robots_conflict`, update to the two new codes.

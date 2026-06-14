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
| `noindex_warning` | warning | robots | `robots` | A self-canonical (apparently important) page is `noindex`. |
| `invalid_canonical` | critical | canonical | `canonical` | Canonical value is not a valid URL. |
| `cross_domain_canonical` | warning | canonical | `canonical`, `page_url` | Canonical points to a different host than the page. |
| `shared_canonical` | notice | canonical | `canonical` | Several pages declare the same canonical. |
| `insecure_canonical` | warning | canonical | `canonical` | `http://` canonical on an `https` site (mixed content). |

The length thresholds reuse the core `SEOWarningEvaluator` constants (60/160),
so a scan never contradicts the editor's character counters; the lower bounds
(title 30, description 70) are the Pro scan's under-optimised floor and live on
`IssueRegistry::TITLE_MIN_LENGTH` / `DESCRIPTION_MIN_LENGTH`. Length is measured
against the **resolved** title/description — the value that actually renders,
including any fallback and title suffix.

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
| `noindex_page` | notice | robots | `robots` | Rendered page is `noindex`. |
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
(informational), and `blocked_url` / `canonical_target_blocked` ("we couldn't
check" ≠ a defect). The [scoring page](/pro/scoring) has the full allowlist and
the penalty for every code.

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

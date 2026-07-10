# The AI-Readiness score — a second, deterministic axis

The Pro scan gives every page a second number alongside the
[SEO score](/pro/scoring): a **0-100 AI-Readiness score**. It answers a
different question — *can AI crawlers and answer engines reach, read, and
attribute this content?* — and is **never blended into the organic SEO number**.
They are two separate axes, each with its own rubric, its own version, and its
own column.

Like the SEO score it is **fully deterministic and reproducible**: every point
traces to one named, crawl-based check, and the same signals always produce the
same number. **There are zero AI calls anywhere in scoring.** That is the whole
point — it is a transparent, auditable measurement, not an LLM sampling the way
the "AI visibility" SaaS products do.

```
score = round( Σ credit(check) × weight(check) )   for every rubric check
```

::: tip Two axes, never blended
`AI-readiness: 74/100` sits next to `SEO: 82/100`; neither moves the other. The
AI-Readiness number lives in its own `ai_readiness_*` columns on the Pro
`seo_scan_results` row. As with the SEO score, the **number is a Pro feature** —
the free core [`seo:audit`](/guide/audit) prints no number.
:::

## Additive credit, not penalty

The [SEO score](/pro/scoring) starts at 100 and *deducts* penalties. AI-Readiness
does the opposite: it starts at **0** and **awards** each check's weight, in full
or in part. "Readiness" is something a site accumulates, so a site with no AI
signals honestly scores near 0 rather than "100 minus a few". The weights sum to
exactly **100**.

## The rubric

The score is computed from a **published, versioned rubric** —
`Rankbeam\Seo\Pro\Scanning\AiReadiness\AiReadinessRubric` — of ten checks across
four categories:

### A · Bot Access & Control — 30 points

Can the crawlers that *cite* you actually reach you? Evaluated against the
site's **served `/robots.txt`**, resolved for the **scanned page's own path**
(a page under a `Disallow: /section` is genuinely unreachable even when the root
is open), using the [AI-crawler catalogue](/guide/ai-crawlers)'s purpose
taxonomy (training / search / assistant).

| Check | Weight | Credit |
|---|---|---|
| `air_robots_reachable` — a `robots.txt` is served and readable | 6 | present / absent |
| `air_ai_search_access` — AI-**search** crawlers (the referral channel) may reach the site | 10 | fraction allowed |
| `air_ai_assistant_access` — AI-**assistant** crawlers may reach the site | 8 | fraction allowed |
| `air_explicit_ai_policy` — an explicit `robots.txt` rule for a known AI bot | 6 | present / absent |

::: tip Blocking trainers is not "less ready"
Disallowing training bots (GPTBot, CCBot…) is a legitimate choice, so it is
**never** a penalty. Training is credited only through `air_explicit_ai_policy` —
having an intentional, explicit stance. A site that blocks trainers but allows
search and assistant crawlers can score full marks in this category.
:::

### B · Discoverability — 20 points

| Check | Weight | Credit |
|---|---|---|
| `air_sitemap_discoverable` — an XML sitemap is reachable **and** referenced by a `Sitemap:` directive | 12 | both / one / neither |
| `air_llms_txt` — a valid `/llms.txt` (heading + links) is served | 8 | valid / present / absent |

### C · Machine-Readable Content — 22 points

| Check | Weight | Credit |
|---|---|---|
| `air_server_rendered_content` — substantial text is present in the server-rendered HTML (content exists without executing JS) | 14 | by word count |
| `air_markdown_twin` — a Markdown twin of the page is served under content negotiation | 8 | present / absent |

### D · Structured Data & Answer Readiness — 28 points

| Check | Weight | Credit |
|---|---|---|
| `air_schema_completeness` — JSON-LD present, primary entity typed, attribution complete (author + date for articles) | 18 | complete / partial / none |
| `air_answer_structure` — answer-extraction affordances: FAQ/QA/HowTo schema, heading hierarchy, lists, a concise lead | 10 | by affordance count |

Every check returns **full**, **partial**, or **no** credit — or **skipped**
when the signal it needs could not be gathered (a page-level check on a target
scanned without a page fetch). A skipped check scores 0 but is flagged, so a
signal that *could not be checked* is never presented as a confirmed absence.

### Free-audit reach

Schema completeness (`air_schema_completeness`) is resolvable from a model's
structured data with no fetch — the same path the free audit already uses for
its answer-readiness findings. The other nine checks need a crawl, so the full
number is a **Pro scan** concern.

## Honest scope — what this axis excludes

This axis scores only **deterministic, crawl-based signals a content site can
satisfy**. It deliberately excludes the agent-infrastructure checks that
general "agent readiness" scanners (e.g. Cloudflare's isitagentready.com)
include — because those are properties of a running application or of DNS, not
of served content:

| Excluded | Why |
|---|---|
| **DNS-AID** (DNS agent-discovery records) | DNS / DNSSEC infrastructure, not a served-page property. |
| **Web Bot Auth** (per-request signing) | An interactive cryptographic handshake, not static content. |
| **Protocol Discovery** (API Catalog, OAuth/OIDC, MCP Server Card, Agent Skills, WebMCP…) | Require a running app / API / MCP server. |
| **Commerce** (x402, MPP, UCP, ACP) | Agent-payment rails — a content site has nothing to charge for. |

Conversely, this axis **adds** two checks such scanners do not have — schema
entity completeness and answer-block structure — the content-SEO signals that
decide whether an answer engine can actually extract and attribute your page.

## Versioning — historical scores never silently change

Every persisted AI-Readiness score is stamped with the
`AiReadinessRubric::VERSION` that produced it (`ai_readiness_version`). Any change
to the check set, a weight, or a credit model is a rubric change and **bumps the
version**, so a stored number always records which rubric explains it and
historical numbers stay comparable. The score is **stored, not recomputed on
read.** The score-affecting thresholds (word counts, affordance counts) are
code-level constants keyed to the version — never config, so a setting can never
silently move a published number.

::: warning One input the version does not pin
The bot-access checks read the **current** [AI-crawler catalogue](/guide/ai-crawlers)
from core. A catalogue update — a new bot, or a reclassified purpose — is a
de-facto change to the inputs and can shift the two bot-access sub-scores without
bumping `AiReadinessRubric::VERSION` (the version tracks the *rubric*, not the
catalogue). This is deliberate: the check is more useful reading today's real bot
list than a frozen one. For exact historical comparability, pin the core package
version alongside the rubric version.
:::

## Where it's stored

Each scan upserts the AI-Readiness columns onto the **same** `seo_scan_results`
row as the SEO score:

| Column | What it holds |
|---|---|
| `ai_readiness_score` | The 0-100 number (null until the target is scanned with the axis enabled). |
| `ai_readiness_version` | The rubric that produced it. |
| `ai_readiness_breakdown` | `[{code, category, credit, weight, points, status, message, evidence}, …]` — the full trace. |

The per-run average is stamped on `seo_scan_runs.avg_ai_readiness` when a run
finishes, mirroring `avg_score` — the AI-Readiness trend.

## Reading the score

**Headless** — the latest result for a model carries both axes:

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$result = SeoPro::resultFor($post);

$result?->score;                    // organic SEO score, e.g. 82
$result?->ai_readiness_score;       // AI-Readiness, e.g. 74
$result?->aiReadinessGrade();       // 'A'..'F' (null if never scored)
$result?->aiReadinessByCategory();  // ['bot_access' => ['awarded' => 24.0, 'max' => 30], …]
```

**Filament** — drop the companion column next to the SEO score column in any
resource table:

```php
use Rankbeam\Seo\Pro\Filament\Tables\Columns\AiReadinessScoreColumn;
use Rankbeam\Seo\Pro\Filament\Tables\Columns\SeoScoreColumn;

SeoScoreColumn::make(),
AiReadinessScoreColumn::make(),
```

It also shows as a companion badge on the inline on-page score card (above the
SEO title field), and as its own section — number, grade, change vs the previous
report, and a per-scan trend — in the [white-label report](/pro/reports) (PDF and
e-mail). It is always presented next to the organic score, never blended in.

### Grade bands

A presentational letter grade derived from the number (the number is the
contract), using the same bands as the SEO score for consistency:

| Score | Grade |
|---|---|
| 90-100 | A |
| 75-89 | B |
| 50-74 | C |
| 25-49 | D |
| 0-24 | F |

## Configuration

```php
// config/seo-pro.php → 'scan'
'ai_readiness' => [
    'enabled' => true,             // turn the AI-Readiness pass + its persistence on/off
    'fetch_site_signals' => true,  // fetch /robots.txt, /llms.txt, /sitemap.xml (per host)
    'probe_markdown_twin' => true, // probe Accept: text/markdown on the page
],
```

The checks and weights are **not** configurable: the score must be deterministic
for a given `ai_readiness_version` across every install, so changing how it is
computed is a code-level rubric change, not a setting.

::: warning Site-signal detection uses the in-process request path
For a same-host target the scan resolves `/robots.txt`, `/llms.txt`, and the
page through Laravel's in-process HTTP kernel — the same path the rest of the
scan uses. A `robots.txt` or `llms.txt` served as a **static file** (bypassing
Laravel routing) is not seen; serve them through the package's routes (the
recommended setup) to be scored.
:::

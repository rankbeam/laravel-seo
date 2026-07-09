# Search Console insights

Moz/Semrush-style **keyword intelligence**, computed entirely on the Search
Console data you already own — no third-party keyword tool, no per-query API
bill. Five reports turn your synced history and one live pull into the
questions those tools charge for: *what's about to rank, what's under-clicked,
what's competing with itself, what each page ranks for, and what moved.*

It builds on the [read-only Search Console integration](/pro/search-console) and
its history sync. If that page's `seo-pro:gsc-sync` has been running, these
insights work with **zero extra API cost** for three of the five surfaces.

::: tip Prerequisite
The three *snapshot* surfaces read the persisted `seo_gsc_metrics` history, so
schedule `seo-pro:gsc-sync` (see [Search Console → history](/pro/search-console))
first. The more days you have synced, the deeper the trend comparison goes.
:::

## The five surfaces

### 1. Striking-distance keywords

Queries whose **impression-weighted average position sits in 5–20** — page-one
adjacent — ranked by impressions. These are the "one push to page one" wins: a
little more relevance or internal linking often moves them into the clicks.

### 2. CTR opportunities

Queries that **rank well but are clicked below the expected rate** for their
position. Each query's actual CTR is compared to a blended industry
CTR-by-position curve; the ones far below their expected rate — with real
impressions — are **title/description rewrite candidates**, sorted by the
estimated *missed clicks* they're leaving on the table. This list is the
natural input to the [AI meta suggester](/pro/ai-assist): it hands you the
exact queries worth rewriting for.

### 3. Cannibalization

Queries where **two or more of your URLs compete** for the same term. Splitting
one query across several pages divides your authority and confuses Google about
which page to rank — this surfaces the overlaps so you can consolidate or
differentiate them.

### 4. Query clusters

The **queries each page actually ranks for**, grouped by page — the page's real
topic footprint in Google's eyes. Useful for spotting a page that's drifting
off its intended topic, or one that's quietly ranking for a valuable term you
never targeted.

### 5. Trend vs previous period

The **biggest movers** — clicks, impressions, position and CTR — for the current
window versus the equal span immediately before it. Position is only compared
when a query had traffic in both periods (there's nothing to compare a brand-new
or fully-dropped query against).

## Where the numbers come from: live vs. snapshot

Each surface reads whichever source answers it correctly at the lowest cost.
The persisted history can't reconstruct which **query** paired with which
**page** (it stores each dimension separately), so the two surfaces that need
that pair are the only ones that go live — and they **share a single cached
request**.

| Surface | Source | Why |
|---|---|---|
| Striking-distance | **Local snapshot** | needs per-query position + impressions, already in your synced history — no API cost |
| CTR opportunities | **Local snapshot** | same owned data; the expected-CTR curve is a static benchmark, not a lookup |
| Trend deltas | **Local snapshot** | needs genuine day-by-day history, which is exactly what the sync stores |
| Cannibalization | **Live** (query × page) | the query→page pairing isn't stored, and persisting every pair would multiply your storage |
| Query clusters | **Live** — *shares surface 3's fetch* | same pair data, grouped by page instead of by query |

So a visit to the Insights page costs **at most one** Search Console request,
cached for `search_console.cache_ttl` seconds. The pair surfaces are live on
purpose: cannibalization and clustering are *point-in-time* questions where you
want the current picture, and Search Console's quota is generous for one cached
pull per view. The snapshot surfaces never touch the network.

## In the dashboard

With the Filament plugin installed, **Search Console Insights** appears under the
*SEO* nav group (only when the integration is enabled). It's strictly read-only:
every surface renders as a section, the snapshot surfaces show a "sync your
history" hint when empty, and a failed live pull for the pair surfaces renders a
sanitized inline notice — never a blocked page.

## Configuration

Everything lives under `search_console.insights` in `config/seo-pro.php`. The
defaults are sensible; tune the thresholds to your site's scale.

```php
'search_console' => [
    // ...
    'insights' => [
        // Rolling window (days) the snapshot surfaces aggregate over,
        // anchored to the latest synced day.
        'window_days' => 28,

        // Max rows a surface returns to its panel section.
        'max_rows' => 50,

        // (1) Striking distance: impression-weighted position in [min,max]
        // with at least this many impressions.
        'striking_distance' => [
            'min_position' => 5.0,
            'max_position' => 20.0,
            'min_impressions' => 30,
        ],

        // (2) CTR opportunity: queries ranking at/above max_position, with at
        // least min_impressions, whose CTR is at least min_gap_ratio below the
        // expected curve.
        'ctr_opportunity' => [
            'max_position' => 10.0,
            'min_impressions' => 50,
            'min_gap_ratio' => 0.30,
        ],

        // Optional override of the expected CTR-by-position curve
        // (position => percent). null uses the built-in blended curve.
        'ctr_curve' => null,

        // (3) Cannibalization: a query with this many URLs each drawing at
        // least min_impressions.
        'cannibalization' => [
            'min_urls' => 2,
            'min_impressions' => 10,
        ],

        // (4) Query clustering: queries per page above min_impressions.
        'clustering' => [
            'min_impressions' => 10,
            'max_queries_per_page' => 15,
        ],

        // (5) Trend deltas: surface queries with at least this many
        // impressions in either period.
        'trend' => [
            'min_impressions' => 20,
        ],

        // The shared live (query,page) fetch for surfaces 3 + 4.
        'pair_days' => 28,
        'pair_row_limit' => 5000,
    ],
],
```

::: info Expected-CTR curve
The CTR-opportunity curve is a **heuristic** blended from published organic
CTR-by-position averages — it's a yardstick, not a claim about your specific
site. A query flagged here is a *candidate to review*, not a proven defect. If
you have your own measured curve, drop it into `insights.ctr_curve` as a
`position => percent` map.
:::

## See also

- [Search Console](/pro/search-console) — the read-only integration + history sync these insights read
- [White-label reports](/pro/reports) — period-over-period movers in the branded PDF
- [AI assist](/pro/ai-assist) — rewrite the titles/descriptions the CTR surface flags

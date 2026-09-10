---
description: "A keyword-aware pass/warn/fail on-page checklist: pick a focus keyword and get traffic-light checks for the title, URL, opening paragraph, meta, length, images and readability."
---

# The on-page checklist — keyword-aware, pass/warn/fail

The on-page checklist is the live editorial loop a RankMath or Yoast user
expects: pick a focus keyword, and get a traffic-light list of "is this page
optimised for it?" checks — keyword in the title, URL, opening paragraph and
meta description, plus length, images, internal links, and **readability**.

It runs **in-request** (no queue, no network) from the model, the
[resolver](/concepts/resolver-precedence), and the page's own copy, and it is
deliberately **not a number**.

::: tip Checklist ≠ score
The checklist is **pass / warn / fail only** and is completely separate from
the [Pro SEO score](/pro/scoring). It shares no codes with the score's rubric
and can never move it — the editorial hints remain separate from the numeric rubric. Keyword density and readability in
particular are **advisory** (see below).
:::

## What it checks

| Check | Group | What it looks for |
|---|---|---|
| `keyword_in_title` | keyword | The focus keyword appears in the SEO title. |
| `keyword_in_description` | keyword | The focus keyword appears in the meta description. |
| `keyword_in_url` | keyword | The focus keyword appears in the URL slug. |
| `keyword_in_first_paragraph` | keyword | The focus keyword appears in the opening paragraph. |
| `keyword_density` | keyword | **Advisory.** Density reads naturally (no target — see below). |
| `title_length` | meta | Title is within the same window as the editor and the scan — 30–60 for Latin text, ~15–30 for CJK, from the core [length policy](/guide/multilingual#title-and-description-budgets-per-script) (Pro 2.33). |
| `description_length` | meta | Description is within the same window — 70–160 for Latin, ~35–80 for CJK. |
| `content_length` | content | Enough body copy (config-driven word-count bands). |
| `readability` | content | **Advisory.** An estimated readability level using the selected formula (ten languages), LIX fallback or a labelled, unscored heuristic for Japanese, Chinese and Korean. |
| `has_image` | media | The content includes at least one image. |
| `internal_links` | links | The content links to related internal pages. |

The keyword checks **skip** (they neither pass nor fail) when no focus keyword
is set — the checklist tells you to add one. Add it with the
[focus-keyword field](/guide/filament) or `saveSEO(['focus_keywords' => …])`.

### Keyword matching

Keyword and copy are compared after **case folding and stemming**, so
"espresso grinder" still matches "espresso grinders", and — with the analysis
locale — Turkish "İstanbul" matches "istanbul", Greek "ΟΔΟΣ" matches "οδος"
and German "Straße" matches "STRASSE" (the core `CaseFolder`). Pass the locale
to analyze in: `SeoPro::checklistFor($post, 'it')` or `--locale=it`.

From Pro 2.36.1, keywords, synonyms and field text use the same tokenizer before
stemming. Matches require consecutive **whole tokens**: `cat` does not match
`education`, and Japanese phrases use the same ICU word boundaries as the body.
Apostrophes and hyphens separate tokens, so `meta-tag` matches `meta tag` and
straight/curly apostrophes behave alike. Combining marks remain attached to their
letters. Case folding preserves accents; a particular language stemmer may apply
additional reductions.

Occurrence counts select the longest matching keyword/synonym at each position
and count that span once. Duplicate synonyms and overlapping shorter alternatives
do not inflate density. For example, keyword `seo` with synonym `seo tools` has
two occurrences in `seo tools seo`. ICU remains necessary for dictionary word
boundaries in scripts without spaces; regex fallback cannot provide those boundaries.

From Pro 2.37, stemming uses a **bundled Snowball 3.1.1 subset**. It needs no
extra Composer package and downloads nothing at runtime. PHP 8.2 remains supported.

| Engine | When | Languages |
| --- | --- | --- |
| `snowball` | Default; existing `auto` settings select the same bundled engine | en, it, de, fr, es, pt, nl, ru, tr, el, pl, cs |
| `builtin` | Explicit `seo-pro.checklist.analysis.stemmer = builtin` | English only, using the older light inflectional stemmer; other languages use identity |
| `identity` | Unsupported language, or explicit `none` mode | Ukrainian, Japanese, Chinese, Korean, Thai and other languages outside the bundled subset |

Both sides of a comparison use the same engine. Stemming is a suffix-reduction
algorithm, not a synonym dictionary or a guarantee of linguistic equivalence.
For example, the Greek algorithm can match accented and unaccented forms that
identity matching keeps separate. Whole-token boundaries still prevent `cat`
from matching `education`.

#### Upgrading from Pro 2.36

Existing `auto` configuration now consistently uses the bundled algorithms,
whether or not `wamania/php-stemmer` is installed. Recheck editorial suggestions
after upgrading: updated algorithms can change matches, and Turkish, Greek,
Polish and Czech now have stemming. The optional wrapper's additional Catalan,
Danish, Finnish, Norwegian, Romanian and Swedish algorithms are outside this
subset and now use identity matching.

Set `SEO_PRO_CHECKLIST_STEMMER=builtin` for the previous English-only fallback,
or `none` for case-folded identity matching in every language. Rebuild cached
configuration after changing this setting. These controls do not reproduce the
old optional wrapper's multilingual algorithms; retaining those exact results
requires retaining the previous Pro release. No stored SEO metadata is rewritten.

The bundled adapter passes 600,395 pinned official vocabulary/output pairs on
PHP 8.2, 8.3 and 8.4. This establishes algorithm conformance, not native editorial
approval. Source hashes, the syntax-only PHP 8.2 adaptation and upstream licenses
ship with the package. See `THIRD-PARTY-NOTICES.md` in the source distribution.

### Word segmentation

Word counts, keyword density and the readability stats need words. For spaced
scripts a regex uses stable letter/digit token boundaries. Chinese, Japanese
and Thai need dictionary segmentation, so a regex can see a paragraph as
one "word". When **ext-intl** is loaded the tokenizer hands those runs to ICU's
dictionary-based break iterator (`IntlBreakIterator::createWordInstance`), which
segments 東京タワーは東京のランドマークです into words. If ICU is missing,
disabled or cannot initialize, Pro skips affected content-length, readability
and keyword checks with an install/configuration message. It does not turn an
unreliable count into a failure. Unrelated checks, including title length and
spaced-script matching, still run. `seo-pro.checklist.analysis.segmenter = regex`
forces the same unavailable state for text that needs dictionary segmentation.

The `analysis` block includes `word_count_status` (`available` or `unavailable`)
and `segmentation_reason` (`null`, `missing_intl`, `disabled`, or
`initialization_failed`). The low-level tokenizer retains fallback tokens for
compatibility; inspect this status before interpreting them as words.

The rendered-page scan emits an unscored `word_segmentation_unavailable` notice
instead of a thin-content verdict. A previously confirmed thin-content finding
stays open until it can be checked again. This incomplete scan does not refresh
the page score: an existing score keeps its original `scored_at`, and a first
scan has no score until segmentation works. Install PHP `ext-intl`, enable the
`auto` segmenter and rescan to resume those checks.

### Which engines analysed the page

Every checklist carries an `analysis` block — the dominant script of the copy,
the tokenizer (`intl` / `regex`), the stemmer (`snowball` / `builtin` /
`identity`) and the readability method (`formula` / `heuristic` / `lix`) — in
`toArray()` / `--json`, as a footer line in the Filament modal and as the last
line of `seo-pro:checklist`:

```
Analysis: locale ja · script cjk · tokenizer intl (ICU dictionary) · stemmer identity · readability heuristic
```

The footer identifies the engine actually used, including regex segmentation
when ext-intl is absent and identity matching when stemming is disabled.

### Keyword density is advisory

The checklist does not define an ideal keyword density for ranking. This check
is **advisory**: it shows the count for awareness, never fails, and **never
drives the overall page status**. Review whether repetition reads naturally,
rather than aiming for a percentage.

### Readability is advisory

The checklist estimates readability with the method selected for the analysis
locale. The formulas and fallbacks currently implemented are:

| Locale | Formula | Source |
| --- | --- | --- |
| English (`en`) | Flesch Reading Ease | Flesch 1948 |
| Italian (`it`) | Gulpease Index | Lucisano & Piemontese 1988 |
| Spanish (`es`) | Fernández-Huerta | Fernández Huerta 1959 |
| French (`fr`) | Kandel-Moles | Kandel & Moles 1958 |
| German (`de`) | erste Wiener Sachtextformel | Bamberger & Vanecek 1984 |
| Portuguese (`pt`, `pt_BR`) | Flesch adapted to Brazilian Portuguese | Martins et al. 1996 |
| Dutch (`nl`) | Flesch-Douma | Douma 1960 |
| Russian (`ru`) | Oborneva's Flesch adaptation | Оборнева 2006 |
| Turkish (`tr`) | Ateşman | Ateşman 1997 |
| Polish (`pl`) | Pisarek (years-of-schooling index, normalised) | Pisarek 1969 |
| Japanese, Chinese, Korean (`ja`, `zh`, `ko`) | **heuristic, no score** — see below | — |
| Greek, Ukrainian, Czech (`el`, `uk`, `cs`) | LIX — a fallback because no dedicated formula is implemented here; not calibrated for these languages | Björnsson 1968 |
| anything else | LIX (Läsbarhetsindex) — uncalibrated fallback | Björnsson 1968 |

The displayed **0–100 score (higher = easier)** is a package convention. Flesch-family
and Gulpease results are clamped; Wiener grade, Pisarek and LIX indexes are mapped
onto that scale. Equal scores in different languages do **not** imply equal reading
difficulty. The coefficient formulas come from published work; Rankbeam's token,
sentence and syllable estimates have not been validated as a complete instrument.
They do not predict comprehension or search rankings.

From Pro 2.37.1, Turkish and Russian syllables count adjacent vowels separately
(`saat`: 2; `поэт`: 2). Other syllable estimators still have limitations: vowel
groups miss some hiatuses and silent vowels. English has a small exception map;
it is not a pronunciation dictionary. For example, Spanish `país` and French
`monde` can be miscounted. Review unfamiliar words and proper names manually.

#### Text statistics and API limits

HTML block tags and `br` elements separate text; inline emphasis stays attached to
its word. Source wrapping inside ordinary HTML collapses to spaces, while plain
text and `pre` retain line boundaries. Script, style and noscript contents are
excluded. Extraction does not
evaluate CSS visibility or the rendered page. Entities are decoded once.
For formula statistics, letter/number runs are words; punctuation alone is not.
Hyphens and apostrophes separate words. Digits count as tokens but have no inferred
syllables. Letters are counted from the original text, without stemming or the
German `ß` → `ss` case fold changing their length.

Sentence estimates split terminal `. ! ? 。 ！ ？` and block/line boundaries, include
an unterminated final fragment, and protect decimals plus a small list of common
abbreviations (`Dr.`, `Prof.`, `e.g.` and similar English forms). Headings and list
items can therefore count as sentences. Other abbreviations, quotations, numbers,
mixed scripts and text with little punctuation need particular care. The selected
locale routes the method; it does not detect whether every sentence is in that language.

The direct calculator's `toArray()` adds an `assessment` block:

```json
{
  "status": "computed",
  "method": "formula",
  "formula": "flesch_reading_ease",
  "inputs_estimated": true,
  "score_scale": "normalized_0_100",
  "grade_level_estimated": true
}
```

`method` distinguishes `formula`, `lix`, `heuristic` and `unavailable` (`unspecified`
for manually constructed results without formula metadata). Empty/punctuation-only
input is `insufficient` and `isValid()` is false; its legacy `score: 0` is an
unavailable sentinel, not a difficulty score. Existing English/Italian grade labels
are approximate; other languages and heuristic/LIX results no longer receive
those school-grade labels. `calculateFleschKincaid()` retains its public method name
for compatibility but computes **Flesch Reading Ease**, not Flesch-Kincaid grade.

The formula tests pin independently counted inputs and expected arithmetic for
all ten named formulas plus LIX. They verify calculation behavior, not native
editorial quality. Readability remains separate from the Pro SEO score.

::: warning Japanese, Chinese and Korean: a labelled heuristic, never a number
Rankbeam implements an unscored method for these languages. The calculator
returns a **level** from package-specific rules of thumb — average sentence length in characters (ja ≤ 40/60/80, zh ≤ 30/45/60)
or words (ko ≤ 12/18/25), and for Japanese the kanji share (above ~45 % reads
one step higher in the package's difficulty bands) — flagged `heuristic: true` with a **null score**. The
checklist message says "heuristic", and the check stays **advisory for these
languages whatever `readability.advisory` says**: a rule of thumb informs, it
never gates the overall checklist status. Checklist word counts for `ja`/`zh`
require working ICU segmentation; these checks are skipped when it is unavailable.
:::

Like keyword density, readability is **advisory by default**: it informs the
writer but does **not** gate the overall page status — the same separation Yoast
draws between its Readability and SEO analyses. It **skips** below a minimum word
count (thin pages are the `content_length` check's job, not readability's). Flip
it to authoritative if you want a hard-to-read page to fail:

```php
// config/seo-pro.php → 'checklist'
'readability' => [
    'min_words' => 50,     // below this → skipped (too little copy to judge)
    'advisory'  => true,   // false → a 'difficult' page fails the checklist
],
```

## Reading the checklist

### Headless

Pro 2.36 reads resolved metadata, `getContentForSEO()` and focus keywords in the
requested content locale, while checklist labels remain in the operator's language.
Without an explicit locale, a translation model's `seoData()` default is respected.
The Filament action follows its field's language tab or the page locale switcher.

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$checklist = SeoPro::checklistFor($post);          // or ($post, 'it') for a locale

$checklist->status();        // 'pass' | 'warn' | 'fail' (advisory + skips ignored)
$checklist->summary();       // ['passed' => 6, 'warnings' => 2, 'failures' => 1, 'skipped' => 0]
$checklist->failures();      // CheckResult[]
$checklist->recommendations(); // failures first, then warnings
$checklist->toArray();       // JSON-ready payload (no score key)
```

Each `CheckResult` carries `id`, `group`, `label`, `status`, `message`, an
optional `recommendation`, and an `advisory` flag.

### Command

```bash
php artisan seo-pro:checklist "App\Models\Post" 42
php artisan seo-pro:checklist "App\Models\Post" 42 --json     # machine-readable
php artisan seo-pro:checklist "App\Models\Post" 42 --strict   # non-zero exit on any failure (CI)
php artisan seo-pro:checklist "App\Models\Post" 42 --locale=it
```

### In the editor (Filament, optional)

With [`rankbeam/laravel-seo-filament`](/guide/filament) installed, an **On-page
checklist** action appears on the focus-keyword field. Clicking it opens a modal
of the same pass/warn/fail checks for the record's saved content. The Filament
package never depends on Pro — the action attaches through the same one-way
extension hook the AI suggestions use, so headless installs are untouched.

## Configuration

```php
// config/seo-pro.php → 'checklist'
'checklist' => [
    'enabled' => true,             // shows the Filament action; headless API always works

    'content' => [
        'min_words' => 200,        // below this → fail (too thin)
        'good_words' => 600,       // below this → warn; at/above → pass
    ],

    'internal_links' => [
        'min' => 2,                // internal links needed to pass
    ],

    'readability' => [
        'min_words' => 50,         // below this → skipped
        'advisory' => true,        // false → a hard-to-read page fails the checklist
    ],

    // The check registry. Each entry implements
    // Rankbeam\Seo\Pro\OnPage\Rules\Check and is resolved from the container
    // (custom checks get the Stemmer/StopWords toolkit injected). Remove a
    // line to drop a check, reorder to reorder, or append your own.
    'rules' => [
        \Rankbeam\Seo\Pro\OnPage\Rules\Keyword\KeywordInTitleCheck::class,
        // …
    ],
],
```

### Writing a custom check

```php
use Rankbeam\Seo\Pro\OnPage\ChecklistContext;
use Rankbeam\Seo\Pro\OnPage\CheckResult;
use Rankbeam\Seo\Pro\OnPage\Rules\AbstractCheck;

class KeywordInSubheadingCheck extends AbstractCheck
{
    public function id(): string { return 'keyword_in_subheading'; }
    public function group(): string { return 'keyword'; }
    public function label(): string { return 'Focus keyword in a subheading'; }

    public function run(ChecklistContext $context): CheckResult
    {
        $keyword = $context->primaryKeyword();

        if ($keyword === null) {
            return $this->skipNoKeyword();
        }

        return str_contains($context->htmlContent, '<h2')
            ? $this->pass('Found a subheading.')
            : $this->warn('No subheading found.', 'Add an H2 that includes the keyword.');
    }
}
```

Register it by adding its class to `seo-pro.checklist.rules`. A check **must
not** reuse a [scan issue code](/pro/scan-issues) id — the checklist is a
separate, non-scoring namespace.

## How the content is read

`SeoPro::checklistFor($model)` analyzes:

- **Title / description** — the *resolved* (effective) values, the same ones
  the editor counters and the scan measure, so the checklist never contradicts
  them.
- **Content** — `$model->getContentForSEO()` (the core `HasSEO` accessor;
  defaults to `content` / `body` / `text`). Override it on your model to point
  at the real body copy.
- **URL** — `$model->getUrlForSEO()`.
- **Focus keywords** — the stored `seo_meta.focus_keywords`.

It is a pure analysis: no page is fetched and nothing is written.

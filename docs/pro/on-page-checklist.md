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
and can never move it — by design, so the editorial hints stay honest and the
one headline number stays gameable-proof. Keyword density and readability in
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
| `readability` | content | **Advisory.** How easy the body copy is to read, scored with the formula validated for the analysis locale (ten languages + a language-agnostic fallback; a labelled, unscored heuristic for Japanese, Chinese and Korean). |
| `has_image` | media | The content includes at least one image. |
| `internal_links` | links | The content links to related internal pages. |

The keyword checks **skip** (they neither pass nor fail) when no focus keyword
is set — the checklist tells you to add one. Add it with the
[focus-keyword field](/guide/filament) or `saveSEO(['focus_keywords' => …])`.

### Keyword matching

Keyword and copy are compared after **case folding and stemming**, so
"espresso grinder" still matches "espresso grinders", and — with the analysis
locale — Turkish "İstanbul" matches "istanbul", Greek "ΟΔΟΣ" matches "οδός"
and German "Straße" matches "STRASSE" (the core `CaseFolder`). Pass the locale
to analyze in: `SeoPro::checklistFor($post, 'it')` or `--locale=it`.

Three stemming engines, chosen per locale (Pro 2.34):

| Engine | When | Languages |
| --- | --- | --- |
| `snowball` | the optional `wamania/php-stemmer` package is installed (`composer require wamania/php-stemmer`) | en, fr, de, it, es, pt, nl, ru (+ ca, da, fi, no, ro, sv) |
| `builtin` | Snowball absent | English only — a light inflectional stemmer (-s, -ing, -ed), deliberately without Porter's derivational steps, which over-stem and cause false matches |
| `identity` | everything else | Turkish, Polish, Greek, Ukrainian, Czech, Japanese, Chinese, Korean, Thai … — an exact case-folded match, never wrong, only less forgiving. Turkish is agglutinative and would need its own rules; none are invented |

`seo-pro.checklist.analysis.stemmer` forces `builtin` or `none`. Both sides
of a comparison are stemmed with the same engine, so what matters is
consistency, not linguistic perfection.

### Word segmentation

Word counts, keyword density and the readability stats need words. For spaced
scripts a regex splits on letters and digits — exact, and byte-identical across
hosts. Chinese, Japanese and Thai have no spaces, so a regex sees a paragraph as
one "word". When **ext-intl** is loaded the tokenizer hands those runs to ICU's
dictionary-based break iterator (`IntlBreakIterator::createWordInstance`), which
segments 東京タワーは東京のランドマークです into seven words; without it the
regex path stays (the pre-2.34 behaviour) and the checklist says so.
`seo-pro.checklist.analysis.segmenter = regex` forces the fallback.

### Which engines analysed the page

Every checklist carries an `analysis` block — the dominant script of the copy,
the tokenizer (`intl` / `regex`), the stemmer (`snowball` / `builtin` /
`identity`) and the readability method (`formula` / `heuristic` / `lix`) — in
`toArray()` / `--json`, as a footer line in the Filament modal and as the last
line of `seo-pro:checklist`:

```
Analysis: locale ja · script cjk · tokenizer intl (ICU dictionary) · stemmer identity · readability heuristic
```

So a Japanese page on a server without ext-intl, or a Turkish page without
Snowball, never looks better analysed than it was.

### Keyword density is advisory

There is no keyword density that is a ranking factor — Google has said so for
years, and over-optimisation is what gets penalised, not a number. So the
density check is marked **advisory**: it is shown for awareness, it never
fails, and it **never drives the overall page status**. Treat it as "does this
read naturally?", not a target to hit.

### Readability is advisory

The checklist also scores how easy the body copy is to read, using the
readability formula **validated for the analysis locale** rather than forcing
English-Flesch on every language (the word- and syllable-length constants that
make Flesch work for English are wrong for other languages):

| Locale | Formula | Source |
| --- | --- | --- |
| English (`en`) | Flesch-Kincaid Reading Ease | Flesch 1948 |
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
| Greek, Ukrainian, Czech (`el`, `uk`, `cs`) | LIX — no validated syllable formula exists for these languages and none is invented; `ReadabilityCalculator::LIX_LANGUAGES` says so and the level description is in the content language (Pro 2.35) | Björnsson 1968 |
| anything else | LIX (Läsbarhetsindex) — language-agnostic | Björnsson 1968 |

Every formula result is normalised to the same **0–100 scale (higher = easier)**
with a pass / warn / fail level and concrete suggestions, so the checklist
treats all locales uniformly. It is computed in-package with **no extra
dependency** (syllables are estimated by a vowel-group count over a vowel class
chosen per script — Latin with diacritics, Polish ą ę ó, Turkish dotless ı,
Cyrillic for Russian). Route the analysis with `SeoPro::checklistFor($post, 'es')`
or `--locale=fr`.

::: tip Romance languages score lower — by design
Spanish and French have more syllables per word than English, and their
Flesch-derived formulas still weight syllables heavily, so ordinary `es`/`fr`
prose lands lower on the 0–100 scale than equivalent English (the `es`/`fr`
target band is 60–70, aspirational). The signal is most useful **relatively** —
simpler copy always scores higher than denser copy in the same language.
:::

::: warning Japanese, Chinese and Korean: a labelled heuristic, never a number
There is no validated syllable-based readability formula for these languages,
and Rankbeam does not invent one. The calculator returns a **level** from rules
of thumb — average sentence length in characters (ja ≤ 40/60/80, zh ≤ 30/45/60)
or words (ko ≤ 12/18/25), and for Japanese the kanji share (above ~45 % reads
one step harder) — flagged `heuristic: true` with a **null score**. The
checklist message says "heuristic", and the check stays **advisory for these
languages whatever `readability.advisory` says**: a rule of thumb informs, it
never fails a page. Word counts for `ja`/`zh` come from ICU segmentation when
ext-intl is present, else from a characters-per-word estimate.
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

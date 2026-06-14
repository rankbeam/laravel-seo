# The on-page checklist — keyword-aware, pass/warn/fail

The on-page checklist is the live editorial loop a RankMath or Yoast user
expects: pick a focus keyword, and get a traffic-light list of "is this page
optimised for it?" checks — keyword in the title, URL, opening paragraph and
meta description, plus length, images, and internal links.

It runs **in-request** (no queue, no network) from the model, the
[resolver](/concepts/resolver-precedence), and the page's own copy, and it is
deliberately **not a number**.

::: tip Checklist ≠ score
The checklist is **pass / warn / fail only** and is completely separate from
the [Pro SEO score](/pro/scoring). It shares no codes with the score's rubric
and can never move it — by design, so the editorial hints stay honest and the
one headline number stays gameable-proof. Keyword density in particular is
**advisory** (see below).
:::

## What it checks

| Check | Group | What it looks for |
|---|---|---|
| `keyword_in_title` | keyword | The focus keyword appears in the SEO title. |
| `keyword_in_description` | keyword | The focus keyword appears in the meta description. |
| `keyword_in_url` | keyword | The focus keyword appears in the URL slug. |
| `keyword_in_first_paragraph` | keyword | The focus keyword appears in the opening paragraph. |
| `keyword_density` | keyword | **Advisory.** Density reads naturally (no target — see below). |
| `title_length` | meta | Title is within the same 30–60 window as the editor and the scan. |
| `description_length` | meta | Description is within the same 70–160 window. |
| `content_length` | content | Enough body copy (config-driven word-count bands). |
| `has_image` | media | The content includes at least one image. |
| `internal_links` | links | The content links to related internal pages. |

The keyword checks **skip** (they neither pass nor fail) when no focus keyword
is set — the checklist tells you to add one. Add it with the
[focus-keyword field](/guide/filament) or `saveSEO(['focus_keywords' => …])`.

### Keyword matching

Keyword and copy are compared after **stemming**, so "espresso grinder" still
matches "espresso grinders". The stemmer is a small, dependency-free English
inflectional stemmer; for every other locale it falls back to an exact,
lowercase match — which is correct for non-Latin scripts (CJK, etc.) and safe
for other languages. Pass a locale to analyze in: `SeoPro::checklistFor($post, 'it')`.

### Keyword density is advisory

There is no keyword density that is a ranking factor — Google has said so for
years, and over-optimisation is what gets penalised, not a number. So the
density check is marked **advisory**: it is shown for awareness, it never
fails, and it **never drives the overall page status**. Treat it as "does this
read naturally?", not a target to hit.

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

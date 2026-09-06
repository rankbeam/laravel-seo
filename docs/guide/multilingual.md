---
description: "How Rankbeam handles content that is not English: per-script title and description budgets, grapheme-safe truncation, locale-aware casing, hreflang normalisation and policies, inLanguage, regional search engines, site verification, OG-image fonts and Unicode URLs."
---

# Multilingual content

[Translations](/guide/translations) make the *package* speak your language. This
page is about the other half: making the package **understand the language of
your content**. A 60-character title limit is wrong for Japanese, a word-boundary
cut breaks Thai, `İstanbul` and `istanbul` are the same word in Turkish, an
`it_IT` hreflang is invalid, and a Korean site cares about Naver's crawler, not
only Google's. None of that is translation — it is correctness, and it is
decided in the core so every surface agrees.

Nothing here needs configuring to work. Every rule below has a sensible built-in
value and a config knob under `config/seo.php`.

## Title and description budgets per script

Google shows roughly 600 px of title and 920 px of description on a desktop
result. "60 / 160 characters" is that budget for Latin text. A full-width CJK
glyph is about twice as wide, so the same budget is ~30 / ~80 characters of
Japanese, Chinese or Korean — a 45-character Japanese title is already cut
where a 45-character English one is fine.

`Rankbeam\Seo\I18n\LengthPolicy` decides the budget for the text in front of it:

```php
use Rankbeam\Seo\I18n\LengthPolicy;

$policy = LengthPolicy::for($title, $locale);   // detects the dominant script
$policy->script;          // 'cjk'
$policy->titleMax;        // 30
$policy->descriptionMax;  // 80
$policy->length($title);  // user-perceived characters (graphemes)
$policy->titleTooLong($title);
```

It is what the editor warnings (`SEOWarningEvaluator`), the free `seo:audit`,
the computed-description truncation, the Pro scan and the Filament counters read,
so the counter under the field, the audit finding and the scan issue can never
disagree. Lengths are counted in **graphemes** — a Thai syllable with its vowel
marks, a Devanagari conjunct or an emoji with a skin tone each count as one, the
unit an editor sees and search engines effectively budget.

The rows live in `seo.length_policy`, keyed by script bucket (`latin`,
`cyrillic`, `greek`, `cjk`, `thai`, `arabic`, `hebrew`, `devanagari`) with
`default` for every bucket not listed. A row may set only some keys and inherit
the rest:

```php
'length_policy' => [
    'default' => ['title_min' => 30, 'title_max' => 60, 'description_min' => 70, 'description_max' => 160],
    'cjk'     => ['title_min' => 15, 'title_max' => 30, 'description_min' => 35, 'description_max' => 80],
    'thai'    => ['title_max' => 55],   // everything else from `default`
],
```

Only `cjk` differs by default. An upgraded install with an older published config
gets the built-in `cjk` row without touching anything.

::: tip Mixed titles
Detection is a weighted letter count: a CJK glyph counts double, so
"Laravel SEO の完全ガイド" resolves to CJK while "Laravel SEO for the 東京
developer" stays Latin. A value with no letters at all (a year, a price) takes
the script of the page locale.
:::

The `SEOWarningEvaluator::TITLE_MAX_LENGTH` / `DESCRIPTION_MAX_LENGTH` constants
still exist as the Latin defaults, for code that reads them.

## Grapheme-safe, script-aware truncation

A computed description (`seo.computed.description_max_length`, a Latin budget)
is scaled by the policy — a CJK description gets half — and cut by
`Rankbeam\Seo\I18n\Truncator`:

- text with word spaces keeps the historical rule: the last word boundary within
  the limit (if it is at least 60 % into the limit), no ellipsis, trailing
  punctuation trimmed — byte-identical to before for Latin text;
- Han, Kana and Thai have no word spaces, so the cut prefers the last sentence or
  clause mark (。！？、，…) inside the limit, then a space if the text has any
  (Korean), then a hard cut;
- slicing happens on grapheme clusters, so a cut can never land inside a
  combining sequence — a Thai vowel mark or an emoji modifier is never separated
  from its base.

## Locale-aware casing

`mb_strtolower()` is locale-blind. `Rankbeam\Seo\I18n\CaseFolder` is not:

```php
use Rankbeam\Seo\I18n\CaseFolder;

CaseFolder::lower('İSTANBUL', 'tr');            // "istanbul" — dotted İ → i under Turkish rules
CaseFolder::equals('ΟΔΟΣ', 'οδός', 'el');       // true — final sigma folded
CaseFolder::equals('Straße', 'STRASSE', 'de');  // true — ß folded to ss
CaseFolder::containsWord('Notizie dalla Città', 'città'); // true — Unicode word boundaries
```

`lower()` is the display form; `fold()`, `equals()`, `contains()` and
`containsWord()` are for comparisons. The core uses it for the brand-aware title
suffix skip (`seo.title_suffix_skip_when_contains`), so a Turkish brand matches
in either `i` form and an accented brand gets a real word boundary; the Pro
keyword checks build on the same helper.

## hreflang

Google reads `language[-Script][-REGION]` — ISO 639-1 language, optional
ISO 15924 script, optional ISO 3166-1 (or UN M.49) region — plus `x-default`.
Laravel apps tend to hand over their *locale* instead (`it_IT`, `pt_br`), and an
underscore is not valid there. Three policies in `seo.hreflang` apply to a
model's `getSEOAlternates()` list **before** it becomes `<link rel="alternate">`
tags, sitemap `<xhtml:link>` entries, `llms.txt` links and audit input — one
list, seen identically everywhere:

```php
'hreflang' => [
    'normalize'    => true,   // it_IT → it-IT, zh_hans_cn → zh-Hans-CN
    'include_self' => false,  // append the page itself when the list omits it
    'x_default'    => null,   // e.g. 'en': duplicate that language's alternate as x-default
],
```

- **`normalize`** (on by default) rewrites every code to its BCP 47 form. Turn it
  off to emit codes exactly as returned.
- **`include_self`** appends the page's own locale + canonical when neither its
  URL nor its code is in the list. Google requires each language version to list
  itself; turn this on when your hook returns only the *other* languages.
- **`x_default`** names the language whose alternate is duplicated as
  `x-default` when the list has none.

An empty list stays empty — a page with no translations gets neither a
self-reference nor an `x-default`.

The free audit adds three checks on the policy-applied list:

| Code | Severity | Meaning |
|---|---|---|
| `hreflang_invalid_code` | warning | A code search engines ignore (`en-UK`, `jp`, `english`). |
| `hreflang_duplicate_code` | notice | The same code listed twice. |
| `hreflang_missing_self` | warning | The page's own URL is not in its list. |

Reciprocity (does the other page point back?) needs a crawl; that is the Pro
scan's job. The helper is public if you need it:

```php
use Rankbeam\Seo\I18n\Hreflang;

Hreflang::fromLocale(app()->getLocale()); // 'it_IT' → 'it-IT'
Hreflang::isValid('pt-BR');               // true
Hreflang::isValid('en-UK');               // false
```

## `inLanguage` in the schema graph

The `WebPage` node carries `inLanguage` from the page's resolved locale
(`it_IT` → `it-IT`), and `ArticleSchema::fromModel()` takes it from the stored
`seo_meta` locale. The `WebSite` node takes its languages from config:

```php
'schema' => [
    'in_language' => true,                       // off = no inLanguage anywhere
    'website' => ['inLanguage' => ['it', 'en']], // one code or a list
],
```

## Regional search engines

The crawler catalog behind `seo:robots-txt` now carries the classic web search
crawlers that matter outside the Google/Bing world — Yandex, Baidu, Naver
(`Yeti`), Seznam, Sogou, 360, Cốc Cốc and DuckDuckGo — tagged with the purpose
`search_engine`, allowed by default. They take part in the policy and the
per-bot overrides, so a shop that does not serve China can keep two crawlers off
its bandwidth:

```php
'ai_crawlers' => [
    'policy'    => ['search_engine' => 'allow', /* … */],
    'overrides' => ['baiduspider' => 'disallow', 'sogou' => 'disallow'],
],
```

`SEO::aiCrawlers()->all()` and `match()` stay AI-only (the Pro AI-bot log and
every "N AI crawlers" count are unchanged); ask for the engines with
`searchEngines()`, `all(true)` or `match($ua, true)`. See
[AI crawler control](/guide/ai-crawlers#regional-search-engines).

::: warning Baidu
Support for the crawler and the verification tag says nothing about ranking:
real Baidu visibility needs hosting in China and an ICP licence.
:::

## Site verification

Ownership tokens render as one meta tag per configured engine, on every page
(Google accepts the tag anywhere; Yandex, Baidu and Naver look on the root page,
which is covered). Nothing is emitted for a key you leave empty:

```php
'verification' => [
    'google'    => env('SEO_VERIFY_GOOGLE'),    // google-site-verification
    'bing'      => env('SEO_VERIFY_BING'),      // msvalidate.01
    'yandex'    => env('SEO_VERIFY_YANDEX'),    // yandex-verification
    'baidu'     => env('SEO_VERIFY_BAIDU'),     // baidu-site-verification
    'naver'     => env('SEO_VERIFY_NAVER'),     // naver-site-verification
    'seznam'    => env('SEO_VERIFY_SEZNAM'),    // seznam-wmt
    'pinterest' => env('SEO_VERIFY_PINTEREST'), // p:domain_verify
    'facebook'  => env('SEO_VERIFY_FACEBOOK'),  // facebook-domain-verification
],
```

A value may be a list of tokens (Google issues one per property owner).

## OG images in every script

The bundled card font covers Latin, Cyrillic and Greek. Every other script
depends on a font installed on the machine that runs `seo:og-images` — nothing
else is bundled, because a CJK font is 16 MB+. The templates now carry a
per-script fallback stack (`seo.og_image.font_stack`, with the page language's
Noto CJK family moved to the front so Han characters take the right national
glyph forms), and the command warns, once per script, when the host has no font
for a title it is about to render:

```
No installed font covers cjk text — its cards will render as boxes. Install one: apt-get install fonts-noto-cjk
```

On Debian/Ubuntu: `apt-get install fonts-noto-cjk fonts-noto-core fonts-noto-color-emoji`.
Details in [Generated OG images](/guide/og-image#fonts-and-non-latin-scripts).

## `llms.txt` in several languages

With `seo.llms_txt.alternates` on, a page that exists in other languages ends
its bullet with `Also in: [it](…), [de](…)` — the policy-applied alternates
minus `x-default` and the page itself. Off by default.

## Unicode URLs

Rankbeam never slugs or rewrites your URLs, so a path such as `/città/` or
`/検索` survives every artifact untouched. Canonicals with an IDN host
(`https://münchen.example/`) or a Unicode or percent-encoded path are accepted
by the audit (`Rankbeam\Seo\I18n\Url::isValid()` replaces PHP's ASCII-only
`FILTER_VALIDATE_URL`). Keep one form per URL — raw Unicode *or*
percent-encoded, not both — so canonical, hreflang and sitemap entries compare
equal byte for byte.

## What the other packages add

- **laravel-seo-filament** reads the same length policy for the live counters
  and the SERP preview.
- **laravel-seo-pro** reads it for the scan's `title_length` /
  `description_length` checks and the AI-assist prompts; per-language analysis
  (segmentation, stemming, readability formulas, keyword matching) is the next
  step of the program.

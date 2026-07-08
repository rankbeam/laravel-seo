---
title: "hreflang done right in Laravel (and the four mistakes that quietly break it)"
description: "hreflang is the most error-prone tag in SEO. The four mistakes that silently break it, the fifth harder one (reciprocity), and how to generate and validate a correct cluster in Laravel."
---

# hreflang done right in Laravel (and the four mistakes that quietly break it)

`hreflang` is the most error-prone tag in SEO, and the failure mode is quiet: get it
wrong and the damage often isn't contained to the broken tag. An invalid code just gets
that annotation dropped — but a missing return link can make Google ignore or misinterpret
the annotations across the set (its own wording is that they "may be ignored or not
interpreted correctly"). And it's widespread: Ahrefs' study of 374,756 domains using
hreflang found **67%** had at least one issue, and Semrush found **58%** of 20,000
multilingual sites had hreflang conflicts in their source
([Ahrefs](https://ahrefs.com/blog/hreflang-study/) ·
[Semrush](https://www.semrush.com/blog/the-most-common-hreflang-mistakes-infographic/)).
If you run a multi-language Laravel app, the odds are you're in that majority and don't
know it, because nothing in your app *tells* you.

Here's how hreflang actually works, the four mistakes that silently break it (plus the
fifth, harder one), and how to get it right — and verified — in Laravel.

## What hreflang is, and why it's fragile

`hreflang` tells search engines "this page has equivalent versions in other languages, and
here they are." A page declares its alternates as `<link rel="alternate" hreflang="…">`
tags (or sitemap entries):

```html
<link rel="alternate" hreflang="en-US" href="https://acme.test/en/about">
<link rel="alternate" hreflang="it-IT" href="https://acme.test/it/chi-siamo">
<link rel="alternate" hreflang="x-default" href="https://acme.test/about">
```

It's fragile because it's a **graph that must agree with itself**. Every page in the set
has to point to every other page *and to itself*, with valid codes, consistently. Break
one edge and engines may ignore or misinterpret the annotations. That's why it fails so
often: it's not one tag, it's an invariant across many pages.

## The four mistakes that silently break it

These are the ones you can detect from a single page — and the ones I made Rankbeam's
hreflang validator check, because they're the common killers.

**1. Invalid language codes.** `hreflang="english"`, `hreflang="en_US"` (underscore),
`hreflang="gb"` (that's a region, not a language) — all invalid. The value must be a
BCP-47 language tag (`en`, `en-US`, `pt-BR`, `zh-Hant-TW`) or the literal `x-default`. An
invalid code isn't "mostly fine" — engines drop it.

**2. Missing self-reference.** One of the most common mistakes (Ahrefs found it on ~18% of
hreflang domains; Semrush saw it in 96% of pages that had any conflict). **Each page must
include an hreflang pointing at *itself*** — Google requires every version to "list itself
as well as all other language versions." The English page's tag set must list the
English URL too, not just the Italian and French ones. Omit the self-reference and the
cluster is considered malformed.

**3. The same code mapping to two different URLs.** If a page declares `hreflang="fr"` for
both `/fr/a` and `/fr-ca/a`, the cluster is ambiguous and engines can't resolve it. One
code, one URL.

**4. No `x-default`.** When you cover several languages, declare an `x-default` — the
fallback for users whose language isn't in your set. It's a recommendation, not a hard
requirement, but on a real multi-language site its absence is a smell.

## The fifth, harder one: broken reciprocity ("return tags")

**Non-reciprocal** hreflang is one of the most common link-invalidating errors: page A
links to page B, but page B doesn't link back to A. Google is explicit that the
annotations must be **bidirectional** — "if page X links to page Y, page Y must link back
to page X," or they "may be ignored or not interpreted correctly." (Ahrefs found missing
return tags on ~15% of domains using hreflang; the single-page mistakes above — a missing
`x-default` or self-reference — are actually even more widespread.) Reciprocity is harder
to catch because it isn't visible from a single page; you have to look at the whole cluster
at once. We'll handle it *structurally* below rather than by checking after the fact.

## How to get it right in Laravel

The trick is to **stop hand-writing per-page tag sets** and instead generate the whole
cluster from one source of truth. If every page in the set builds the *same* cluster — and
includes itself — reciprocity and self-reference fall out for free. You can't forget a
return tag if both pages are emitting the identical set.

In Rankbeam's free core, a model exposes its alternates through the `getSEOAlternates()`
hook; the resolver renders them to `<link rel="alternate">`, into the headless payload,
and into the sitemap. Build the cluster once, from a locale map:

```php
use Rankbeam\Seo\Traits\HasSEO;

class Post extends Model
{
    use HasSEO;

    /** Build the FULL reciprocal cluster (every locale + self + x-default). */
    public function getSEOAlternates(): ?array
    {
        // route segment => hreflang code
        $locales = ['en' => 'en-US', 'it' => 'it-IT', 'fr' => 'fr-FR'];

        $alternates = [];
        foreach ($locales as $segment => $hreflang) {
            $alternates[] = [
                'hreflang' => $hreflang,
                'href' => url("/{$segment}/posts/{$this->translatedSlug($segment)}"),
            ];
        }

        // x-default — the fallback for everyone else.
        $alternates[] = ['hreflang' => 'x-default', 'href' => url("/posts/{$this->slug}")];

        return $alternates;
    }
}
```

Because the `en`, `it`, and `fr` pages all return *this same array*, each one references
every sibling **and itself** — self-reference and reciprocity are structural, not
something you remember to do. The codes are valid by construction. There's exactly one
URL per code. And `x-default` is always present. (A note: the package emits exactly what
you return — it doesn't invent links or fix codes — so the discipline lives in the
generator, which is the point.)

Then split your sitemap by language and let the index tie them together — engines treat
per-language sitemaps with hreflang annotations as a first-class signal. Rankbeam's
sitemap builder pulls the **same** `getSEOAlternates()` set into `<xhtml:link>` entries,
so head tags and sitemap can't drift apart.

## Validate it — don't trust it

Even with a clean generator, a typo in the locale map or a half-translated slug can slip
through. So **check it**, the same way you'd check anything else that's easy to get subtly
wrong. Rankbeam Pro's scan reads the resolved alternates and flags the four single-page
mistakes (1–4 above) as stable, documented issue codes — and because the alternates resolve
from the model with no page fetch, this runs in the in-process scan, no live crawl:

```bash
php artisan seo-pro:scan
```

```text
✗ hreflang_invalid_code            "english" is not a valid BCP-47 language code
⚠ hreflang_missing_self_reference  alternates declared but none reference this page's locale
⚠ hreflang_duplicate_code          the same code maps to more than one URL
⚠ hreflang_missing_x_default       multi-language cluster has no x-default fallback
```

The checks only fire when a page actually declares alternates, so a single-language page is
never nagged. Wire the scan into CI and a broken cluster is visible before it ships. (If
you're on the free core only, the free `seo:audit` covers the metadata and **answer-readiness
(AEO)** checks; the hreflang validator is part of the Pro scan.)

*(roadmap)* Cross-page **reciprocity** validation — confirming page B actually links back
to page A across the whole cluster — is harder (it needs the engine to resolve every
alternate URL to its model and compare), and it's on the Rankbeam roadmap rather than
shipped today. Until then, the structural "build the identical cluster everywhere"
approach above is what keeps reciprocity correct; the scan covers the four single-page
codes.

## The takeaway

hreflang isn't hard — it's *unforgiving*. The winning move in Laravel is to make the
mistakes **impossible to forget** (generate the whole reciprocal cluster from one source,
including self and `x-default`) and then **verify** the result in CI instead of trusting
it. Build it once, validate it always, and the most error-prone tag in SEO becomes a
guardrail instead of a liability.

```bash
composer require rankbeam/laravel-seo
```

The free core renders hreflang from your `getSEOAlternates()` hook (head tags + sitemap,
from one source of truth); the [Pro scan](/pro/scan-issues) validates the four single-page
codes in CI. The [quickstart](/guide/quickstart) gets you from install to a rendered head
in five minutes.

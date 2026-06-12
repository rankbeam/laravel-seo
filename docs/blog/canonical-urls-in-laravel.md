---
title: "Canonical URLs in Laravel: derive by default, override on purpose"
description: "A production-tested canonical policy for Laravel apps — when to derive, when to set explicitly, and what to do with query strings."
---

# Canonical URLs in Laravel: derive by default, override on purpose

Canonical tags look trivial — one `<link>` element — and that's exactly why
they go wrong quietly. Get them wrong and search engines index
`?utm_source=newsletter` variants of your pages, split ranking signal across
duplicates, or (worse) consolidate everything onto a URL you didn't intend.

This is the canonical policy we converged on after running SEO for a large
production Laravel site for years, and the one that now ships as the default
behavior of [rankbeam/laravel-seo](https://github.com/rankbeam/laravel-seo).
The whole policy fits in two sentences:

> **Derive the canonical from the model's route, and strip the query string.
> If a human explicitly sets a canonical, preserve it verbatim — query string
> included.**

The rest of this post is why those two sentences are right, and where the
edge cases live.

## Why derived-by-default

Most pages in a content-driven Laravel app have exactly one correct canonical:
the URL of the route that renders the model. You already encode that knowledge
in your route definitions, so asking editors to retype it into a "canonical"
field per page is pure liability — it drifts the first time a slug changes.

In the package, a model tells the resolver where it lives once:

```php
use Rankbeam\Seo\Traits\HasSEO;

class Post extends Model
{
    use HasSEO;

    public function getUrlForSEO(): string
    {
        return route('posts.show', $this);
    }
}
```

`getUrlForSEO()` feeds the canonical, `og:url`, and the sitemap entry — three
outputs that must agree with each other, derived from one source of truth.
When no model is in play (a static page), the resolver falls back to the
current request URL.

## Why strip the query string — but only on derived values

Here's the production lesson. Query parameters reach your pages from places
you don't control:

- **Tracking**: `?utm_source=…`, `?fbclid=…`, `?gclid=…` — every campaign
  mints new URLs for the same content.
- **Pagination and filters on listing pages**: `?page=2`, `?sort=price`.
- **Plain noise**: users copy URLs with whatever junk their app appended.

If your canonical echoes the request URL *with* its query string, every one of
those variants declares itself canonical, and you've shipped duplicate
content with extra steps. On the production site that taught us this, the
resolver's fix was one line — `strtok($url, '?')` — and that exact behavior is
now in the package's `SEOResolver::ensureCanonical()`:

```php
// Strip query string for a clean canonical
$canonical = strtok($canonical, '?') ?: $canonical;
```

But — and this is the part most packages get wrong — the strip applies **only
to derived canonicals**. If an editor or your code explicitly saves a
canonical, the resolver preserves it byte-for-byte:

```php
$page->saveSEO(['canonical' => 'https://example.com/canonical?keep=this']);
// resolves to exactly that URL, query string included
```

Why? Because an explicit canonical is a human telling you they know better,
and sometimes they do. The classic case: a faceted page where one specific
query-string variant *is* the canonical version (`?color=black` is the
canonical product page because the bare URL 302s into it). If you "helpfully"
strip explicit values, that person has no escape hatch. Both behaviors are
locked by characterization tests in the package:

```php
it('strips the query string from a model-derived canonical', function () {
    $page = CharacterizationPage::create([
        'title'    => 'T',
        'page_url' => 'https://example.com/pages/about?utm_source=newsletter&page=2',
    ]);

    expect(resolveSeo($page)->canonical)->toBe('https://example.com/pages/about');
});

it('preserves an explicit canonical verbatim, query string included', function () {
    $page->saveSEO(['canonical' => 'https://example.com/canonical?keep=this']);

    expect(resolveSeo($page->fresh())->canonical)
        ->toBe('https://example.com/canonical?keep=this');
});
```

## Keep `og:url` in lockstep

A subtle duplicate-signal source: `og:url` disagreeing with the canonical.
Social scrapers consolidate share counts by `og:url`; search engines read the
canonical. If they diverge, your share counts and your indexing point at
different URLs. The resolver therefore sets `og:url` from the canonical in the
same post-processing step — there is no code path where they disagree by
accident:

```php
if (! $result->ogUrl) {
    $result = $result->with('ogUrl', $canonical);
}
```

## Pagination: canonical + robots, not canonical alone

`?page=2` raises the question the canonical alone can't answer. Pointing every
paginated page's canonical at page 1 is the common mistake — it tells Google
pages 2+ are duplicates of page 1, which they aren't, and your deep content
loses its path into the index. The policy that works:

- Each paginated page keeps a canonical to **itself** (with the page
  parameter — this is the explicit-canonical escape hatch in action), or
- You `noindex,follow` pages 2+ and let the canonical stay on the bare URL.

The second is simpler and is one line with the resolver's override API:

```php
$seo = SEO::resolve($category);

if ($page > 1) {
    $seo = app(SEOResolver::class)->resolveWithOverrides($seo, [
        'robots' => 'noindex,follow',
    ]);
}
```

## Give editors the field, but make empty the right answer

In the [Filament package](https://docs.rankbeam.dev/guide/filament), the SEO
section has a canonical input whose helper text does a lot of policy work:

> Leave empty for the automatic canonical URL (the page URL without query
> parameters).

Empty means *derived* — correct by default, immune to slug changes. The field
exists for the faceted-page exceptions, and the section's source indicators
show whether the effective canonical is "Derived from URL" or "Manual", so an
audit can spot accidental overrides at a glance.

## The checklist

1. **Derive canonicals from routes** (one `getUrlForSEO()` per model), never
   from editor-typed text by default.
2. **Strip query strings from derived canonicals** — tracking parameters are
   not alternate pages.
3. **Preserve explicit canonicals verbatim** — an override is a decision, not
   input to sanitize.
4. **Emit `og:url` from the canonical**, always, from the same code path.
5. **Paginated pages**: self-canonical with the page parameter, or
   `noindex,follow` — never canonical-to-page-1.
6. **Surface the derived/manual distinction in your admin** so overrides are
   visible, not buried.

All of this is the out-of-the-box behavior of
[`rankbeam/laravel-seo`](https://github.com/rankbeam/laravel-seo) (MIT):

```bash
composer require rankbeam/laravel-seo
```

— and the [quickstart](/guide/quickstart) gets you from install to a fully
rendered head in five minutes.

---
description: "Render SEO in server-rendered Laravel with the package's Blade directives — the all-in-one @seo resolves a model and emits meta, Open Graph, Twitter Cards and JSON-LD."
---

# Blade guide

For classic server-rendered apps, the package ships seven Blade directives.
One of them — `@seo` — is usually all you need.

## The all-in-one directive

```blade
<!DOCTYPE html>
<html>
<head>
    @seo($post)
</head>
```

`@seo` resolves the model through the [precedence chain](/concepts/resolver-precedence)
and renders the complete head block: `<title>`, meta description, canonical
link, robots, Open Graph tags, Twitter Card tags, and attached JSON-LD. The
robots tag is emitted **only when it deviates from the site default** — a
redundant `index,follow` is omitted (its absence already means index,follow).
Set `seo.robots.emit_default` to always render it. See the full
[Rendering Contract](/contributing/rendering-contract).

Signatures:

```blade
@seo($post)                  {{-- model page --}}
@seo($seoData)               {{-- a hand-built SEOData (model-less page) --}}
@seo($post, 'blog.show')     {{-- model + route defaults --}}
@seo($post, null, 'fr')      {{-- model + locale --}}
@seo(null)                   {{-- current page, no model --}}
```

`@seo` accepts a `Model`, a hand-built `SEOData`, or `null`. The route/locale
arguments apply only to the `Model`/`null` path — a hand-built `SEOData` carries
its own values.

## Route pages (no model)

For static pages, archives, and other route-backed pages:

```blade
@seoForRoute('pages.about')
@seoForRoute('contact', 'de')   {{-- with locale --}}
```

Route values come from `seo_defaults` rows scoped to the route name.

## Model-less pages: hand-built `SEOData`

Listings, search results, and anything composed in a controller often have no
single backing model. Build a `SEOData` and pass it straight to `@seo` (or the
`SEO` facade) — no need to reach for `app(TagRenderer::class)->render(...)`:

```php
use Rankbeam\Seo\Data\SEOData;

return view('search.results', [
    'seo' => new SEOData(
        title: "Results for \"{$query}\"",
        description: "Browse {$count} matches for {$query}.",
        ogImage: '/images/search-share.jpg',   // relative is fine — see below
    ),
]);
```

```blade
<head>
    @seo($seo)
</head>
```

A hand-built `SEOData` is treated as **explicit intent**. Every value you set is
preserved; only the render-time gaps are filled for you:

- `canonical` / `og:url` are derived from the current URL when absent (an
  explicit `canonical` is kept verbatim, query string included);
- the `title_suffix` is appended only when the title lacks it (and is skipped
  entirely when the title already carries a brand token — see
  [`title_suffix_skip_when_contains`](/reference/configuration));
- relative `og:image` / `twitter:image` paths are absolutized with `url()`
  (which honors your current scheme — it does **not** force HTTPS);
- `og:site_name` and `locale` are filled from config / the app locale.

The database precedence chain (global / model-type / route / `seo_meta`
defaults) is **not** merged into a hand-built `SEOData` — what you pass is what
renders, plus the gaps above.

The same value works through the facade:

```php
SEO::render($seoData);     // HTML string
SEO::toArray($seoData);    // Vue/React structure
SEO::forInertia($seoData); // Inertia Head structure
```

## A layout pattern that scales

One layout serving model pages, route pages, and everything else:

```blade
<head>
    @if(isset($seoModel))
        @seo($seoModel)
    @elseif(isset($seoRoute))
        @seoForRoute($seoRoute)
    @else
        @seo(null)
    @endif
</head>
```

Controllers then pass `'seoModel' => $post` or `'seoRoute' => 'blog.index'`
and never touch markup.

## Granular directives

When you need to control individual tags (for example, mixing with another
package's output):

| Directive | Renders |
|---|---|
| `@seoTitle($post)` | `<title>` only |
| `@seoMeta($post)` | meta description only |
| `@seoCanonical($post)` | canonical link only (falls back to the current URL) |
| `@seoRobots($post)` | robots meta only — always rendered (this is an explicit opt-in, so it does **not** apply the deviate-from-default suppression that `@seo` does) |
| `@seoSchema($post)` | the JSON-LD `<script>` only — valid in head or body |

All of them accept the same `($model, $route, $locale)` expression as `@seo`,
or no argument for the current page.

## Hreflang alternates

Models using `HasSEO` can provide hreflang links directly through the resolver:

```php
public function getSEOAlternates(): ?array
{
    return [
        ['hreflang' => 'en', 'href' => route('posts.show', ['locale' => 'en', 'post' => $this])],
        ['hreflang' => 'it', 'href' => route('posts.show', ['locale' => 'it', 'post' => $this])],
    ];
}
```

Use absolute URLs. `@seo($post)` resolves these entries and renders each one as
`<link rel="alternate" hreflang="..." href="...">`.

## Escaping and safety

Text values are escaped with `e()`. JSON-LD is encoded with
`JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP`, so a
`</script>` inside user content cannot break out of the script element.

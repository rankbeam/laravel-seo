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
@seo($post, 'blog.show')     {{-- model + route defaults --}}
@seo($post, null, 'fr')      {{-- model + locale --}}
@seo(null)                   {{-- current page, no model --}}
```

## Route pages (no model)

For static pages, archives, and other route-backed pages:

```blade
@seoForRoute('pages.about')
@seoForRoute('contact', 'de')   {{-- with locale --}}
```

Route values come from `seo_defaults` rows scoped to the route name.

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

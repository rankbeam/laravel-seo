# Livewire

The `@seo` Blade directives are framework-generic — they emit plain HTML into
the `<head>`, so they work in any Livewire app the same way they work in Blade.

## Initial (full-page) render

On a **full-page Livewire component** (a route returning a component), or any
Blade layout that wraps Livewire components, `@seo` works exactly as in the
[Blade guide](/guide/blade):

```blade
{{-- layouts/app.blade.php --}}
<head>
    @seo($post ?? null)
</head>
```

The first HTTP response carries the complete, crawler-visible head — title,
description, canonical, Open Graph, Twitter, and JSON-LD. This is the path
crawlers and social scrapers see, and it is fully correct.

## The `wire:navigate` caveat

Livewire's [`wire:navigate`](https://livewire.laravel.com/docs/navigate) turns
link clicks into SPA-style visits. On such a visit Livewire swaps the `<body>`
and **merges the `<head>`** — but with one important asymmetry for an SEO
package:

- **`<title>` and `<meta>`/`<link>`** are merged from the new page's head, so
  the resolved title and meta generally update.
- **`<script>` is treated as a non-removable asset.** Livewire keeps every
  `<script>` it has ever seen so re-executing them can't break your JS. That
  means your **JSON-LD `<script>` blocks accumulate**: after visiting three
  posts, all three posts' schema sit in the head at once, and a tool reading
  structured data sees the wrong (or multiple) entities.

To make cleanup possible, the renderer **tags every JSON-LD script** it emits:

```html
<script type="application/ld+json" data-seo-schema
        data-seo-url="https://example.com/blog/the-post"> … </script>
```

## Ship the JSON-LD cleanup

Add this once (e.g. in your root layout, after `@livewireScripts`). On each
`wire:navigate`, it keeps only the **current page's** schema and removes the
stale ones:

```blade
<script>
    document.addEventListener('livewire:navigated', () => {
        const scripts = document.querySelectorAll('script[data-seo-schema]')
        if (scripts.length < 2) return

        // Livewire appends the freshest page's schema last; its URL identifies
        // the current page. Remove every schema script from other pages.
        const current = scripts[scripts.length - 1].getAttribute('data-seo-url')
        scripts.forEach((el) => {
            if (el.getAttribute('data-seo-url') !== current) el.remove()
        })
    })
</script>
```

This relies only on the `data-seo-schema` marker and per-URL id the renderer
already emits — no per-page wiring.

::: tip Singleton meta on SPA navigation
Livewire's head-merge keeps singleton `<meta>`/`<link>` tags from going stale
in most cases, but the exact behaviour depends on your Livewire version and how
your layout is structured. For pages where correct crawler meta is critical,
prefer a **full-page reload** (a plain link without `wire:navigate`) or
**server-render** the page so the first HTTP response is authoritative. The
[`rankbeam-examples`](https://github.com/rankbeam) Livewire app exercises a real
`wire:navigate` flow in the browser to keep this honest.
:::

## Filament

Filament is Livewire under the hood, but it is an **admin authoring surface** —
it edits `seo_meta` and never renders your public front-end head. See the
[Filament guide](/guide/filament); nothing here applies to the admin panel.

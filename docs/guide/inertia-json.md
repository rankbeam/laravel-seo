# Inertia & JSON APIs

The same resolved `SEOData` that powers `@seo` renders to structured arrays —
one source of truth whether the head is built by Blade, Vue, React, Svelte, or
a separate frontend consuming your API.

::: warning Crawler-visible meta requires Inertia SSR or prerendering
A default (no-SSR) Inertia app injects meta **client-side**. The initial HTML a
crawler or social scraper fetches has *no* SEO meta until JavaScript runs. For
the head to be in the raw HTTP response you must enable
[Inertia SSR](https://inertiajs.com/server-side-rendering) (or prerender).
JSON-LD in particular should be server-rendered. See
[the Rendering Contract](/contributing/rendering-contract).
:::

## Inertia

`SEO::forInertia()` returns data shaped for Inertia's `<Head>` component, with a
stable **`head-key`** on every entry:

```php
use Rankbeam\Seo\Facades\SEO;

return Inertia::render('Blog/Post', [
    'post' => $post,
    'seo' => SEO::forInertia($post),
]);
```

The shape:

```json
{
    "title": "Custom SEO Title | My Site",
    "meta": [
        { "name": "description", "content": "...", "head-key": "description" },
        { "property": "og:title", "content": "...", "head-key": "og:title" },
        { "name": "twitter:card", "content": "summary_large_image", "head-key": "twitter:card" }
    ],
    "link": [
        { "rel": "canonical", "href": "https://example.com/blog/post", "head-key": "canonical" }
    ]
}
```

### Hreflang alternates

Define `getSEOAlternates(): ?array` on a model using `HasSEO` to feed
hreflang links into `SEO::resolve($post)` and `SEO::forInertia($post)`
automatically:

```php
public function getSEOAlternates(): ?array
{
    return [
        ['hreflang' => 'en', 'href' => route('posts.show', ['locale' => 'en', 'post' => $this])],
        ['hreflang' => 'it', 'href' => route('posts.show', ['locale' => 'it', 'post' => $this])],
    ];
}
```

Return absolute URLs. Inertia receives them in `link` with stable head keys
such as `alternate:en` and `alternate:it`.

::: tip Bind `:head-key`, not `:key`
Inertia dedupes head elements by their **`head-key`** attribute: a page `<Head>`
tag with the same `head-key` as a layout tag *replaces* it instead of stacking a
duplicate. Vue's `:key` is the unrelated `v-for` reconciliation key — it does
**nothing** for Inertia's head dedup. Without `:head-key`, page meta and layout
meta both persist and you get duplicate/stale tags across client-side visits.
The robots tag is omitted entirely when it matches the site default, so there is
no redundant `index,follow` to dedupe.
:::

### Vue

```vue
<script setup>
import { Head } from '@inertiajs/vue3'
defineProps({ seo: Object })
</script>

<template>
    <Head :title="seo.title">
        <meta v-for="m in seo.meta" :key="m['head-key']" :head-key="m['head-key']"
              :name="m.name" :property="m.property" :content="m.content" />
        <link v-for="l in seo.link" :key="l['head-key']" :head-key="l['head-key']"
              :rel="l.rel" :hreflang="l.hreflang" :href="l.href" />
    </Head>
</template>
```

### React

```jsx
import { Head } from '@inertiajs/react'

export default function Post({ seo }) {
    return (
        <Head title={seo.title}>
            {seo.meta.map((m) => (
                <meta key={m['head-key']} head-key={m['head-key']}
                      name={m.name} property={m.property} content={m.content} />
            ))}
            {seo.link.map((l) => (
                <link key={l['head-key']} head-key={l['head-key']}
                      rel={l.rel} hrefLang={l.hreflang} href={l.href} />
            ))}
        </Head>
    )
}
```

### Svelte

Inertia's Svelte adapter has **no `<Head>` component** — use Svelte's native
`<svelte:head>`, fed by the same `forInertia()` array. (`<svelte:head>` does not
have Inertia's `head-key` dedup, so keep your SEO tags in one place — the page
component — rather than splitting them between a layout and the page.)

```svelte
<script>
    export let seo
</script>

<svelte:head>
    <title>{seo.title}</title>
    {#each seo.meta as m (m['head-key'])}
        {#if m.name}
            <meta name={m.name} content={m.content} />
        {:else}
            <meta property={m.property} content={m.content} />
        {/if}
    {/each}
    {#each seo.link as l (l['head-key'])}
        <link rel={l.rel} hreflang={l.hreflang} href={l.href} />
    {/each}
</svelte:head>
```

## JSON-LD with Inertia

`forInertia()` deliberately omits the `script` section — Inertia's `<Head>`
manages `title`/`meta`/`link`, not raw script tags. Pass the JSON-LD through as
its own page prop instead:

```php
return Inertia::render('Blog/Post', [
    'seo'    => SEO::forInertia($post),
    'schema' => SEO::toArray($post)['script'], // [{ type, innerHTML }]
]);
```

Do **not** render this prop through Inertia `<Head>`, React
`dangerouslySetInnerHTML`, or Svelte `{@html}`. Those head-manager paths can
produce an empty script and can break the SSR render. Instead, render the prop
from Inertia's `$page` array in the root view, immediately after
`@inertiaHead`.

```blade
{{-- root app.blade.php --}}
@inertiaHead

@php
    $schema = $page['props']['schema'] ?? [];
    $schemaUrl = collect($page['props']['seo']['link'] ?? [])
        ->firstWhere('rel', 'canonical')['href'] ?? ($page['url'] ?? '');
@endphp

@foreach ($schema as $entry)
    <script type="application/ld+json"
            data-seo-schema
            data-seo-url="{{ $schemaUrl }}">{!! $entry['innerHTML'] !!}</script>
@endforeach
```

The root view receives the shared page props even though it does not receive
the original model. This keeps JSON-LD in the raw SSR response. `innerHTML` is
already `</script>`-safe (encoded with `JSON_HEX_TAG`), so print it raw rather
than encoding it a second time.

The root view only runs on the initial document request. Replace the marked
scripts after every client-side Inertia navigation:

```js
import { createInertiaApp, router } from '@inertiajs/vue3'

function updateSchema(page) {
    document.querySelectorAll('head script[data-seo-schema]')
        .forEach((script) => script.remove())

    const canonical = page.props.seo?.link
        ?.find((link) => link.rel === 'canonical')?.href ?? page.url

    for (const entry of page.props.schema ?? []) {
        const script = document.createElement('script')
        script.type = 'application/ld+json'
        script.dataset.seoSchema = ''
        script.dataset.seoUrl = canonical
        script.textContent = entry.innerHTML
        document.head.appendChild(script)
    }
}

router.on('navigate', ({ detail }) => updateSchema(detail.page))

createInertiaApp({
    // ...
})
```

For React or Svelte, use the same updater and import `router` from
`@inertiajs/react` or `@inertiajs/svelte`. Keep title, meta, and link rendering
in the framework head component as shown above; only JSON-LD uses this root-view
and navigation lifecycle.

## Plain arrays / JSON APIs

`SEO::toArray()` returns everything, including the JSON-LD script section:

```php
$seo = SEO::toArray($post);
// [
//     'title'  => '...',
//     'meta'   => [...],                      // no head-key (decoupled clients dedupe their own way)
//     'link'   => [['rel' => 'canonical', 'href' => '...']],
//     'script' => [['type' => 'application/ld+json', 'innerHTML' => '{...}']],
// ]
```

This is the format to expose from an API endpoint when a fully decoupled
frontend (Nuxt, Next, etc.) owns the document head. The `head-key` hint is
specific to Inertia's `<Head>`, so `toArray()` omits it — your client's own head
manager (`@unhead`, `next/head`, …) handles dedup.

## Lower-level access

When you need raw values instead of renderer output:

```php
$data = SEO::resolve($post);          // SEOData value object
$data = SEO::forRoute('blog.index');  // for route pages

$data->title;
$data->description;
$data->canonical;
$data->ogImage;       // always an absolute URL
```

`SEOData` is an immutable value object — see
[resolver precedence](/concepts/resolver-precedence) for how each property
gets its value, and [the Rendering Contract](/contributing/rendering-contract)
for the full checklist every stack's `<head>` must satisfy.

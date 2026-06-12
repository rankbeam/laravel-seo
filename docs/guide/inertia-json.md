# Inertia & JSON APIs

The same resolved `SEOData` that powers `@seo` renders to structured arrays —
one source of truth whether the head is built by Blade, Vue, React, or a
separate frontend consuming your API.

## Inertia

`SEO::forInertia()` returns data shaped for Inertia's `<Head>` component:

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
        { "name": "description", "content": "..." },
        { "name": "robots", "content": "index,follow" },
        { "property": "og:title", "content": "..." },
        { "name": "twitter:card", "content": "summary_large_image" }
    ],
    "link": [
        { "rel": "canonical", "href": "https://example.com/blog/post" }
    ]
}
```

Rendering in Vue:

```vue
<script setup>
import { Head } from '@inertiajs/vue3'
defineProps({ seo: Object })
</script>

<template>
    <Head :title="seo.title">
        <meta v-for="m in seo.meta" :key="m.name || m.property"
              :name="m.name" :property="m.property" :content="m.content" />
        <link v-for="l in seo.link" :key="l.rel" :rel="l.rel" :href="l.href" />
    </Head>
</template>
```

React works the same way with `<Head>` from `@inertiajs/react`.

::: tip JSON-LD with Inertia
`forInertia()` deliberately omits the `script` section — Inertia's `Head`
component is not a good place for raw script tags. Render JSON-LD
server-side in your root Blade template with `@seoSchema($post)`, or expose
it via `SEO::toArray()` and inject it yourself.
:::

## Plain arrays / JSON APIs

`SEO::toArray()` returns everything, including the JSON-LD script section:

```php
$seo = SEO::toArray($post);
// [
//     'title'  => '...',
//     'meta'   => [...],
//     'link'   => [['rel' => 'canonical', 'href' => '...']],
//     'script' => [['type' => 'application/ld+json', 'innerHTML' => '{...}']],
// ]
```

This is the format to expose from an API endpoint when a fully decoupled
frontend (Nuxt, Next, etc.) owns the document head.

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
gets its value.

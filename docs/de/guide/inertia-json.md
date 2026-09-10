---
description: "Dieselben aufgelösten SEO-Daten über Inertia oder eine JSON-API ausgeben und Meta-Tags sowie JSON-LD mit Inertia SSR oder Prerendering im ersten HTML bereitstellen."
---

# Inertia und JSON-APIs {#inertia-json-apis}

Das aufgelöste `SEOData`, das auch `@seo` verwendet, lässt sich als strukturiertes Array ausgeben. Damit bleibt die Datenquelle dieselbe, unabhängig davon, ob Blade, Vue, React, Svelte oder ein separates API-Frontend den Head aufbaut.

::: warning Für Crawler sichtbare Meta-Tags benötigen Inertia SSR oder Prerendering
Eine gewöhnliche Inertia-App ohne SSR fügt Meta-Tags **clientseitig** ein. Das erste HTML, das ein Crawler oder Social-Media-Scraper abruft, enthält **keine** SEO-Meta-Tags, bis JavaScript ausgeführt wird. Damit der Head bereits in der HTTP-Antwort steht, musst du [Inertia SSR](https://inertiajs.com/server-side-rendering) oder Prerendering aktivieren. Insbesondere JSON-LD sollte serverseitig gerendert werden. Siehe den [Rendering-Vertrag (EN)](/de/contributing/rendering-contract).
:::

## Inertia {#inertia}

`SEO::forInertia()` liefert Daten für Inertias `<Head>`-Komponente mit einem stabilen **`head-key`** für jeden Eintrag:

```php
use Rankbeam\Seo\Facades\SEO;

return Inertia::render('Blog/Post', [
    'post' => $post,
    'seo' => SEO::forInertia($post),
]);
```

Die Struktur:

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

### Hreflang-Alternativen {#hreflang-alternates}

Definiere `getSEOAlternates(): ?array` auf einem Modell mit `HasSEO`, damit Hreflang-Links automatisch in `SEO::resolve($post)` und `SEO::forInertia($post)` einfließen:

```php
public function getSEOAlternates(): ?array
{
    return [
        ['hreflang' => 'en', 'href' => route('posts.show', ['locale' => 'en', 'post' => $this])],
        ['hreflang' => 'it', 'href' => route('posts.show', ['locale' => 'it', 'post' => $this])],
    ];
}
```

Gib absolute URLs zurück. Inertia erhält sie in `link` mit stabilen Head-Keys wie `alternate:en` und `alternate:it`.

::: tip `:head-key` binden, nicht `:key`
Inertia entfernt doppelte Head-Elemente anhand ihres **`head-key`**-Attributs. Ein Tag im Seiten-`<Head>` mit demselben `head-key` wie ein Layout-Tag **ersetzt** dieses, statt als Duplikat hinzuzukommen. Vues `:key` dient dem Abgleich von `v-for`-Elementen und hat mit Inertias Head-Deduplizierung **nichts** zu tun. Ohne `:head-key` bleiben Seiten- und Layout-Meta-Tags nebeneinander bestehen; bei clientseitigen Besuchen entstehen doppelte oder veraltete Tags. Das Robots-Tag entfällt vollständig, wenn es dem Website-Standard entspricht. Ein redundantes `index,follow` muss deshalb nicht dedupliziert werden.
:::

### Vue {#vue}

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

### React {#react}

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

### Svelte {#svelte}

Inertias Svelte-Adapter hat **keine `<Head>`-Komponente**. Verwende Sveltes `<svelte:head>` mit demselben `forInertia()`-Array. Da `<svelte:head>` Inertias `head-key`-Deduplizierung nicht bietet, halte deine SEO-Tags an einer Stelle in der Seitenkomponente, statt sie zwischen Layout und Seite aufzuteilen.

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

## JSON-LD mit Inertia {#json-ld-with-inertia}

`forInertia()` lässt den Abschnitt `script` absichtlich aus. Inertias `<Head>` verwaltet `title`, `meta` und `link`, aber keine rohen Script-Tags. Übergib JSON-LD stattdessen als eigene Seiten-Prop:

```php
return Inertia::render('Blog/Post', [
    'seo'    => SEO::forInertia($post),
    'schema' => SEO::toArray($post)['script'], // [{ type, innerHTML }]
]);
```

Rendere diese Prop **nicht** über Inertia `<Head>`, React `dangerouslySetInnerHTML` oder Svelte `{@html}`. Diese Head-Verwaltung kann ein leeres Script erzeugen und das SSR-Rendering beeinträchtigen. Rendere die Prop stattdessen aus Inertias `$page`-Array in der Root-View, direkt nach `@inertiaHead`.

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

Die Root-View erhält die geteilten Seiten-Props, obwohl sie das ursprüngliche Modell nicht erhält. So bleibt JSON-LD in der unverarbeiteten SSR-Antwort. `innerHTML` ist durch `JSON_HEX_TAG` bereits gegen ein vorzeitiges `</script>` abgesichert. Gib es unverändert aus, statt es erneut zu kodieren.

Die Root-View wird nur bei der ersten Dokumentanfrage ausgeführt. Ersetze die markierten Scripts nach jeder clientseitigen Inertia-Navigation:

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

Verwende für React oder Svelte denselben Updater und importiere `router` aus `@inertiajs/react` beziehungsweise `@inertiajs/svelte`. Titel, Meta-Tags und Links bleiben wie oben in der Head-Komponente des Frameworks. Nur JSON-LD verwendet die Root-View und diesen Navigationsablauf.

## Einfache Arrays und JSON-APIs {#plain-arrays-json-apis}

`SEO::toArray()` liefert alles zurück, einschließlich des JSON-LD-Script-Abschnitts:

```php
$seo = SEO::toArray($post);
// [
//     'title'  => '...',
//     'meta'   => [...],                      // no head-key (decoupled clients dedupe their own way)
//     'link'   => [['rel' => 'canonical', 'href' => '...']],
//     'script' => [['type' => 'application/ld+json', 'innerHTML' => '{...}']],
// ]
```

Dieses Format eignet sich für einen API-Endpunkt, wenn ein vollständig getrenntes Frontend wie Nuxt oder Next den Dokument-Head verwaltet. Der Hinweis `head-key` ist spezifisch für Inertias `<Head>` und fehlt deshalb in `toArray()`. Die Head-Verwaltung des Clients, etwa `@unhead` oder `next/head`, übernimmt die Deduplizierung.

## Zugriff auf die zugrunde liegenden Werte {#lower-level-access}

Wenn du die Rohwerte statt der Renderer-Ausgabe brauchst:

```php
$data = SEO::resolve($post);          // SEOData value object
$data = SEO::forRoute('blog.index');  // for route pages

$data->title;
$data->description;
$data->canonical;
$data->ogImage;       // always an absolute URL
```

`SEOData` ist ein unveränderliches Wertobjekt. Die [Resolver-Priorität](/de/concepts/resolver-precedence) erklärt, woher jede Eigenschaft ihren Wert erhält. Der [Rendering-Vertrag (EN)](/de/contributing/rendering-contract) enthält die vollständigen Anforderungen an den `<head>` jedes Stacks.

---
description: "Render dezelfde uiteindelijke SEO-gegevens via Inertia of een JSON-API en maak metadata en JSON-LD zichtbaar voor crawlers in de initiële HTML met Inertia SSR of prerendering."
---

# Inertia en JSON-API's {#inertia-json-apis}

Dezelfde uiteindelijke `SEOData` die `@seo` voedt, kan als gestructureerde arrays worden gerenderd —
één bron van waarheid, ongeacht of Blade, Vue, React, Svelte of
een aparte frontend die je API gebruikt de head opbouwt.

::: warning Metadata die crawlers kunnen zien, vereist Inertia SSR of prerendering
Een standaard Inertia-app (zonder SSR) voegt metadata **aan de clientkant** toe. De initiële HTML die een
crawler of sociale scraper ophaalt, bevat *geen* SEO-metadata totdat JavaScript wordt uitgevoerd. Om
de head in het ruwe HTTP-antwoord op te nemen, moet je
[Inertia SSR](https://inertiajs.com/server-side-rendering) inschakelen (of prerenderen).
Met name JSON-LD hoort op de server te worden gerenderd. Zie
[het renderingcontract](/nl/contributing/rendering-contract).
:::

## Inertia {#inertia}

`SEO::forInertia()` retourneert gegevens in de vorm die Inertia's `<Head>`-component verwacht, met een
stabiele **`head-key`** bij elke vermelding:

```php
use Rankbeam\Seo\Facades\SEO;

return Inertia::render('Blog/Post', [
    'post' => $post,
    'seo' => SEO::forInertia($post),
]);
```

De structuur:

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

### Hreflang-alternatieven {#hreflang-alternates}

Definieer `getSEOAlternates(): ?array` op een model met `HasSEO` om
hreflang-links automatisch aan `SEO::resolve($post)` en `SEO::forInertia($post)`
door te geven:

```php
public function getSEOAlternates(): ?array
{
    return [
        ['hreflang' => 'en', 'href' => route('posts.show', ['locale' => 'en', 'post' => $this])],
        ['hreflang' => 'it', 'href' => route('posts.show', ['locale' => 'it', 'post' => $this])],
    ];
}
```

Retourneer absolute URL's. Inertia ontvangt ze in `link` met stabiele head-sleutels
zoals `alternate:en` en `alternate:it`.

::: tip Koppel `:head-key`, niet `:key`
Inertia verwijdert dubbele head-elementen op basis van hun **`head-key`**-attribuut: een `<Head>`-tag
van een pagina met dezelfde `head-key` als een layouttag *vervangt* die tag in plaats van een
duplicaat toe te voegen. Vue's `:key` is de daarvan losstaande `v-for`-reconciliatiesleutel — die doet
**niets** voor deduplicatie in Inertia's head. Zonder `:head-key` blijven zowel paginametadata als layoutmetadata bestaan en ontstaan bij navigatie aan de clientkant dubbele of verouderde tags.
De robots-tag wordt helemaal weggelaten wanneer hij overeenkomt met de sitestandaard. Er is dus
geen overbodige `index,follow` om te dedupliceren.
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

Inertia's Svelte-adapter heeft **geen `<Head>`-component** — gebruik Sveltes eigen
`<svelte:head>` met dezelfde `forInertia()`-array. (`<svelte:head>` heeft
niet de `head-key`-deduplicatie van Inertia. Houd je SEO-tags daarom op één plek — de paginacomponent — in plaats van ze over een layout en de pagina te verdelen.)

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

## JSON-LD met Inertia {#json-ld-with-inertia}

`forInertia()` laat de sectie `script` bewust weg — Inertia's `<Head>`
beheert `title`/`meta`/`link`, geen ruwe script-tags. Geef JSON-LD in plaats daarvan door als
een eigen paginaprop:

```php
return Inertia::render('Blog/Post', [
    'seo'    => SEO::forInertia($post),
    'schema' => SEO::toArray($post)['script'], // [{ type, innerHTML }]
]);
```

Render deze prop **niet** via Inertia `<Head>`, React
`dangerouslySetInnerHTML` of Svelte `{@html}`. Die head-managers kunnen
een leeg script opleveren en SSR-rendering laten mislukken. Render de prop in plaats daarvan
vanuit Inertia's `$page`-array in de root-view, direct na
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

De root-view ontvangt de gedeelde paginaprops, ook al ontvangt hij niet
het oorspronkelijke model. Zo blijft JSON-LD in het ruwe SSR-antwoord staan. `innerHTML` is
al veilig voor `</script>` (gecodeerd met `JSON_HEX_TAG`), dus geef de waarde onbewerkt weer in plaats van
haar nogmaals te coderen.

De root-view wordt alleen bij het eerste documentverzoek uitgevoerd. Vervang de gemarkeerde
scripts na elke Inertia-navigatie aan de clientkant:

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

Gebruik voor React of Svelte dezelfde updater en importeer `router` uit
`@inertiajs/react` of `@inertiajs/svelte`. Blijf title-, meta- en link-elementen renderen
in de head-component van het framework, zoals hierboven; alleen JSON-LD gebruikt deze root-view
en navigatielevenscyclus.

## Gewone arrays / JSON-API's {#plain-arrays-json-apis}

`SEO::toArray()` retourneert alles, inclusief de JSON-LD-scriptsectie:

```php
$seo = SEO::toArray($post);
// [
//     'title'  => '...',
//     'meta'   => [...],                      // no head-key (decoupled clients dedupe their own way)
//     'link'   => [['rel' => 'canonical', 'href' => '...']],
//     'script' => [['type' => 'application/ld+json', 'innerHTML' => '{...}']],
// ]
```

Dit is het formaat om vanuit een API-endpoint aan te bieden wanneer een volledig losgekoppelde
frontend (Nuxt, Next enzovoort) de document-head beheert. De `head-key`-hint is
specifiek voor Inertia's `<Head>`, dus `toArray()` laat die weg — de eigen head-manager van je client (`@unhead`, `next/head`, …) verzorgt de deduplicatie.

## Toegang op lager niveau {#lower-level-access}

Wanneer je ruwe waarden nodig hebt in plaats van rendereruitvoer:

```php
$data = SEO::resolve($post);          // SEOData value object
$data = SEO::forRoute('blog.index');  // for route pages

$data->title;
$data->description;
$data->canonical;
$data->ogImage;       // always an absolute URL
```

`SEOData` is een onveranderlijk waardeobject — zie de
[voorrangsvolgorde van de resolver](/nl/concepts/resolver-precedence) voor de herkomst van elke
eigenschapswaarde en [het renderingcontract](/nl/contributing/rendering-contract)
voor de volledige checklist waaraan de `<head>` van elke stack moet voldoen.

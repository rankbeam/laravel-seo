---
description: "Usa gli stessi dati SEO risolti con Inertia o un’API JSON. Con SSR o prerendering, metadati e JSON-LD sono presenti nell’HTML iniziale letto dai crawler."
---

# Inertia e API JSON {#inertia-json-apis}

Lo stesso `SEOData` risolto usato da `@seo` può produrre array strutturati: i dati hanno un’unica origine, sia che l’head venga costruito da Blade, Vue, React, Svelte o da un frontend separato che consuma l’API.

::: warning I metadati visibili ai crawler richiedono SSR o prerendering con Inertia
Un’app Inertia senza SSR inserisce i metadati **lato client**. L’HTML iniziale ricevuto da crawler e strumenti di anteprima social **non** contiene metadati SEO finché non viene eseguito JavaScript. Per includere l’head nella risposta HTTP devi attivare [Inertia SSR](https://inertiajs.com/server-side-rendering) oppure il prerendering. In particolare, il JSON-LD dovrebbe essere generato sul server. Vedi il [contratto di rendering (EN)](/it/contributing/rendering-contract).
:::

## Inertia {#inertia}

`SEO::forInertia()` restituisce dati nel formato atteso dal componente `<Head>` di Inertia, con un **`head-key`** stabile per ogni voce:

```php
use Rankbeam\Seo\Facades\SEO;

return Inertia::render('Blog/Post', [
    'post' => $post,
    'seo' => SEO::forInertia($post),
]);
```

La struttura:

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

### Versioni alternative con hreflang {#hreflang-alternates}

Definisci `getSEOAlternates(): ?array` su un modello che usa `HasSEO` per fornire automaticamente i link hreflang a `SEO::resolve($post)` e `SEO::forInertia($post)`:

```php
public function getSEOAlternates(): ?array
{
    return [
        ['hreflang' => 'en', 'href' => route('posts.show', ['locale' => 'en', 'post' => $this])],
        ['hreflang' => 'it', 'href' => route('posts.show', ['locale' => 'it', 'post' => $this])],
    ];
}
```

Restituisci URL assoluti. Inertia li riceve in `link`, con chiavi stabili come `alternate:en` e `alternate:it`.

::: tip Collega `:head-key`, non soltanto `:key`
Inertia elimina i duplicati nell’head tramite l’attributo **`head-key`**: un tag del `<Head>` della pagina con lo stesso `head-key` di un tag del layout lo **sostituisce**. Il `:key` di Vue serve invece alla riconciliazione di `v-for` e **non** gestisce i duplicati nell’head di Inertia. Senza `:head-key`, i metadati della pagina e del layout rimangono entrambi, causando tag duplicati o obsoleti durante la navigazione lato client. Il tag robots viene omesso quando coincide con il valore predefinito del sito: non rimane quindi un `index,follow` ridondante da deduplicare.
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

L’adapter Svelte di Inertia **non ha un componente `<Head>`**. Usa il `<svelte:head>` nativo, alimentato dallo stesso array di `forInertia()`. `<svelte:head>` non offre la deduplicazione tramite `head-key` di Inertia: mantieni i tag SEO in un solo punto, il componente della pagina, senza dividerli tra layout e pagina.

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

## JSON-LD con Inertia {#json-ld-with-inertia}

`forInertia()` omette intenzionalmente la sezione `script`: il `<Head>` di Inertia gestisce `title`, `meta` e `link`, non tag script grezzi. Passa il JSON-LD in una prop separata:

```php
return Inertia::render('Blog/Post', [
    'seo'    => SEO::forInertia($post),
    'schema' => SEO::toArray($post)['script'], // [{ type, innerHTML }]
]);
```

**Non** generare questa prop tramite il `<Head>` di Inertia, `dangerouslySetInnerHTML` di React o `{@html}` di Svelte. Questi percorsi possono produrre uno script vuoto o interrompere il rendering SSR. Genera invece la prop dall’array `$page` di Inertia nella vista principale, subito dopo `@inertiaHead`.

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

La vista principale riceve le prop condivise della pagina anche senza il modello originale. In questo modo il JSON-LD rimane nella risposta SSR. `innerHTML` è già protetto dalle sequenze `</script>` grazie a `JSON_HEX_TAG`: stampalo senza escaping aggiuntivo, evitando una seconda codifica.

La vista principale viene eseguita solo alla richiesta iniziale del documento. Sostituisci gli script contrassegnati dopo ogni navigazione Inertia lato client:

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

Con React o Svelte usa la stessa funzione di aggiornamento e importa `router` da `@inertiajs/react` o `@inertiajs/svelte`. Mantieni titolo, meta e link nel componente che gestisce l’head, come negli esempi sopra. Solo il JSON-LD usa la vista principale e gli eventi di navigazione.

## Array semplici e API JSON {#plain-arrays-json-apis}

`SEO::toArray()` restituisce tutti i dati, compresa la sezione script del JSON-LD:

```php
$seo = SEO::toArray($post);
// [
//     'title'  => '...',
//     'meta'   => [...],                      // no head-key (decoupled clients dedupe their own way)
//     'link'   => [['rel' => 'canonical', 'href' => '...']],
//     'script' => [['type' => 'application/ld+json', 'innerHTML' => '{...}']],
// ]
```

Esponi questo formato da un endpoint API quando un frontend completamente separato, come Nuxt o Next, gestisce l’head del documento. `head-key` è specifico del `<Head>` di Inertia, quindi `toArray()` lo omette: la deduplicazione spetta al gestore dell’head del client, come `@unhead` o `next/head`.

## Accesso ai valori risolti {#lower-level-access}

Quando ti servono i valori anziché l’output del renderer:

```php
$data = SEO::resolve($post);          // SEOData value object
$data = SEO::forRoute('blog.index');  // for route pages

$data->title;
$data->description;
$data->canonical;
$data->ogImage;       // always an absolute URL
```

`SEOData` è un value object immutabile. La [priorità del resolver](/it/concepts/resolver-precedence) spiega come viene scelto ogni valore; il [contratto di rendering (EN)](/it/contributing/rendering-contract) elenca i requisiti che l’`<head>` deve rispettare con ogni stack.

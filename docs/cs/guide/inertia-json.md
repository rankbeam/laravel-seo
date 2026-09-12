---
description: "Vykreslujte stejná vyhodnocená data SEO přes Inertii nebo JSON API a pomocí Inertia SSR či předvykreslení dostaňte meta značky a JSON-LD pro roboty už do počátečního HTML."
---

# Inertia a JSON API {#inertia-json-apis}

Stejná vyhodnocená data `SEOData`, ze kterých vychází `@seo`, lze vykreslit do strukturovaných polí. Máte tak jediný zdroj pravdy, ať hlavičku sestavuje Blade, Vue, React, Svelte nebo samostatný frontend využívající vaše API.

::: warning Meta značky viditelné pro roboty vyžadují Inertia SSR nebo předvykreslení
Výchozí aplikace Inertia bez SSR vkládá meta značky **na straně klienta**. Počáteční HTML, které stáhne robot nebo nástroj pro náhledy na sociálních sítích, neobsahuje **žádné** meta značky SEO, dokud se nespustí JavaScript. Aby byla hlavička už v samotné HTTP odpovědi, musíte zapnout [Inertia SSR](https://inertiajs.com/server-side-rendering) nebo předvykreslení. Zejména JSON-LD by se mělo vykreslovat na serveru. Viz [Kontrakt vykreslování](/cs/contributing/rendering-contract).
:::

## Inertia {#inertia}

`SEO::forInertia()` vrací data ve formátu pro komponentu `<Head>` v Inertii se stabilním **`head-key`** u každé položky:

```php
use Rankbeam\Seo\Facades\SEO;

return Inertia::render('Blog/Post', [
    'post' => $post,
    'seo' => SEO::forInertia($post),
]);
```

Struktura:

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

### Alternativní jazykové verze hreflang {#hreflang-alternates}

Na modelu využívajícím `HasSEO` definujte `getSEOAlternates(): ?array`. Odkazy hreflang se pak automaticky předají do `SEO::resolve($post)` a `SEO::forInertia($post)`:

```php
public function getSEOAlternates(): ?array
{
    return [
        ['hreflang' => 'en', 'href' => route('posts.show', ['locale' => 'en', 'post' => $this])],
        ['hreflang' => 'it', 'href' => route('posts.show', ['locale' => 'it', 'post' => $this])],
    ];
}
```

Vracejte absolutní URL. Inertia je obdrží v `link` se stabilními klíči hlavičky, například `alternate:en` a `alternate:it`.

::: tip Navažte `:head-key`, nikoli `:key`
Inertia odstraňuje duplicitní prvky hlavičky podle atributu **`head-key`**. Značka `<Head>` stránky se stejným `head-key` jako značka v layoutu ji *nahradí*, místo aby přidala duplikát. Atribut `:key` ve Vue je nesouvisející klíč pro párování prvků při `v-for` a deduplikaci hlavičky v Inertii **nijak neovlivní**. Bez `:head-key` zůstávají meta značky stránky i layoutu zachované a při navigaci na straně klienta vznikají duplicitní nebo zastaralé značky. Značka robots se zcela vynechá, pokud odpovídá výchozí hodnotě webu. Nevzniká tedy nadbytečné `index,follow`, které by bylo třeba deduplikovat.
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

Adaptér Inertie pro Svelte **nemá komponentu `<Head>`**. Použijte nativní `<svelte:head>` ve Svelte se stejným polem `forInertia()`. (`<svelte:head>` nemá deduplikaci podle `head-key` jako Inertia, proto ponechte značky SEO na jediném místě, v komponentě stránky, místo jejich rozdělení mezi layout a stránku.)

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

## JSON-LD s Inertií {#json-ld-with-inertia}

`forInertia()` záměrně vynechává sekci `script`. Komponenta `<Head>` v Inertii spravuje `title`/`meta`/`link`, nikoli přímo značky script. JSON-LD místo toho předejte jako samostatnou vlastnost stránky:

```php
return Inertia::render('Blog/Post', [
    'seo'    => SEO::forInertia($post),
    'schema' => SEO::toArray($post)['script'], // [{ type, innerHTML }]
]);
```

Tuto vlastnost **nevykreslujte** přes `<Head>` v Inertii, `dangerouslySetInnerHTML` v Reactu ani `{@html}` ve Svelte. Tyto způsoby správy hlavičky mohou vytvořit prázdný script a narušit vykreslování SSR. Vlastnost raději vykreslete z pole `$page` Inertie v kořenové šabloně bezprostředně za `@inertiaHead`.

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

Kořenová šablona dostává sdílené vlastnosti stránky, i když nemá původní model. JSON-LD tak zůstane v samotné odpovědi SSR. Hodnota `innerHTML` je už bezpečná pro `</script>` (je zakódovaná pomocí `JSON_HEX_TAG`), proto ji vypište přímo a nekódujte ji podruhé.

Kořenová šablona se zpracuje jen při počátečním požadavku na dokument. Po každé navigaci Inertie na straně klienta označené skripty nahraďte:

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

V Reactu nebo Svelte použijte stejnou aktualizační funkci a importujte `router` z `@inertiajs/react` nebo `@inertiajs/svelte`. Vykreslování titulku, meta značek a odkazů ponechte v komponentě hlavičky daného frameworku, jak ukazují příklady výše. Kořenovou šablonu a tento navigační životní cyklus používá pouze JSON-LD.

## Prostá pole / JSON API {#plain-arrays-json-apis}

`SEO::toArray()` vrací vše včetně sekce se skriptem JSON-LD:

```php
$seo = SEO::toArray($post);
// [
//     'title'  => '...',
//     'meta'   => [...],                      // no head-key (decoupled clients dedupe their own way)
//     'link'   => [['rel' => 'canonical', 'href' => '...']],
//     'script' => [['type' => 'application/ld+json', 'innerHTML' => '{...}']],
// ]
```

Tento formát vystavte z API endpointu, pokud hlavičku dokumentu spravuje zcela oddělený frontend (Nuxt, Next apod.). Pomocná hodnota `head-key` je specifická pro `<Head>` v Inertii, takže ji `toArray()` vynechává. Deduplikaci řeší vlastní správce hlavičky klienta (`@unhead`, `next/head` apod.).

## Přístup na nižší úrovni {#lower-level-access}

Pokud potřebujete přímo hodnoty místo výstupu rendereru:

```php
$data = SEO::resolve($post);          // SEOData value object
$data = SEO::forRoute('blog.index');  // for route pages

$data->title;
$data->description;
$data->canonical;
$data->ogImage;       // always an absolute URL
```

`SEOData` je neměnný hodnotový objekt. Podrobnosti o určení jednotlivých vlastností najdete v [prioritách resolveru](/cs/concepts/resolver-precedence). Úplný seznam požadavků, které musí `<head>` splnit v každé technologii, obsahuje [Kontrakt vykreslování](/cs/contributing/rendering-contract).

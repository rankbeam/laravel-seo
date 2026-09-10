---
description: "Usa los mismos datos SEO resueltos con Inertia o una API JSON y genera metadatos y JSON-LD visibles para rastreadores en el HTML inicial mediante SSR o prerenderizado."
---

# Inertia y API JSON {#inertia-json-apis}

El mismo `SEOData` resuelto que utiliza `@seo` puede generar arrays estructurados: una única fuente de datos tanto si el head lo construye Blade, Vue, React, Svelte como si lo hace un frontend independiente que consume tu API.

::: warning Los metadatos visibles para rastreadores requieren SSR de Inertia o prerenderizado
Una aplicación Inertia predeterminada, sin SSR, inserta los metadatos **en el cliente**. El HTML inicial que recibe un rastreador o un extractor de vistas previas sociales **no** contiene esos metadatos SEO hasta que se ejecuta JavaScript. Para incluir el head en la respuesta HTTP original, activa [Inertia SSR](https://inertiajs.com/server-side-rendering) o el prerenderizado. En particular, JSON-LD debe generarse en el servidor. Consulta el [contrato de renderizado (EN)](/es/contributing/rendering-contract).
:::

## Inertia {#inertia}

`SEO::forInertia()` devuelve datos preparados para el componente `<Head>` de Inertia, con un **`head-key`** estable en cada entrada:

```php
use Rankbeam\Seo\Facades\SEO;

return Inertia::render('Blog/Post', [
    'post' => $post,
    'seo' => SEO::forInertia($post),
]);
```

La estructura:

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

### Alternativas hreflang {#hreflang-alternates}

Define `getSEOAlternates(): ?array` en un modelo con `HasSEO` para incorporar los enlaces hreflang automáticamente a `SEO::resolve($post)` y `SEO::forInertia($post)`:

```php
public function getSEOAlternates(): ?array
{
    return [
        ['hreflang' => 'en', 'href' => route('posts.show', ['locale' => 'en', 'post' => $this])],
        ['hreflang' => 'it', 'href' => route('posts.show', ['locale' => 'it', 'post' => $this])],
    ];
}
```

Devuelve URL absolutas. Inertia las recibe en `link`, con claves estables como `alternate:en` y `alternate:it`.

::: tip Vincula `:head-key`, no `:key`
Inertia elimina duplicados del head mediante el atributo **`head-key`**: una etiqueta del `<Head>` de la página con el mismo `head-key` que otra del layout la **sustituye**, en lugar de añadir un duplicado. El `:key` de Vue es la clave de reconciliación de `v-for` y **no interviene** en este mecanismo de Inertia. Sin `:head-key`, permanecen los metadatos de página y layout, lo que deja etiquetas duplicadas u obsoletas al navegar en el cliente. La etiqueta robots se omite si coincide con el valor predeterminado del sitio, así que no hay un `index,follow` redundante que deduplicar.
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

El adaptador de Inertia para Svelte **no tiene un componente `<Head>`**. Usa el `<svelte:head>` nativo con el mismo array de `forInertia()`. `<svelte:head>` no aplica la deduplicación por `head-key` de Inertia, así que reúne las etiquetas SEO en el componente de página, sin repartirlas entre el layout y la página.

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

`forInertia()` omite deliberadamente la sección `script`: `<Head>` de Inertia gestiona `title`/`meta`/`link`, no etiquetas script sin procesar. Pasa JSON-LD como una prop independiente:

```php
return Inertia::render('Blog/Post', [
    'seo'    => SEO::forInertia($post),
    'schema' => SEO::toArray($post)['script'], // [{ type, innerHTML }]
]);
```

**No** renderices esa prop mediante `<Head>` de Inertia, `dangerouslySetInnerHTML` de React o `{@html}` de Svelte. Esas vías pueden producir un script vacío y romper el renderizado SSR. En su lugar, renderiza la prop desde el array `$page` de Inertia en la vista raíz, justo después de `@inertiaHead`.

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

La vista raíz recibe las props compartidas de la página aunque no reciba el modelo original. Así, JSON-LD permanece en la respuesta SSR original. `innerHTML` ya está protegido frente a `</script>` mediante `JSON_HEX_TAG`; imprímelo sin escapar en lugar de codificarlo una segunda vez.

La vista raíz solo se ejecuta en la petición inicial del documento. Sustituye los scripts marcados después de cada navegación de Inertia en el cliente:

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

Para React o Svelte, usa el mismo actualizador e importa `router` desde `@inertiajs/react` o `@inertiajs/svelte`. Mantén el renderizado de title, meta y link en el componente head del framework, como se muestra arriba; solo JSON-LD utiliza esta vista raíz y el ciclo de navegación.

## Arrays simples y API JSON {#plain-arrays-json-apis}

`SEO::toArray()` devuelve todo, incluida la sección script de JSON-LD:

```php
$seo = SEO::toArray($post);
// [
//     'title'  => '...',
//     'meta'   => [...],                      // no head-key (decoupled clients dedupe their own way)
//     'link'   => [['rel' => 'canonical', 'href' => '...']],
//     'script' => [['type' => 'application/ld+json', 'innerHTML' => '{...}']],
// ]
```

Expón este formato desde tu API cuando un frontend completamente independiente, como Nuxt o Next, gestione el head del documento. La indicación `head-key` pertenece al `<Head>` de Inertia, por lo que `toArray()` la omite; el gestor de head del cliente (`@unhead`, `next/head`, etc.) se encarga de los duplicados.

## Acceso a los valores originales {#lower-level-access}

Cuando necesites valores sin pasar por el renderizador:

```php
$data = SEO::resolve($post);          // SEOData value object
$data = SEO::forRoute('blog.index');  // for route pages

$data->title;
$data->description;
$data->canonical;
$data->ogImage;       // always an absolute URL
```

`SEOData` es un objeto de valor inmutable. Consulta la [precedencia del resolvedor](/es/concepts/resolver-precedence) para saber cómo obtiene cada propiedad y el [contrato de renderizado (EN)](/es/contributing/rendering-contract) para conocer todos los requisitos del `<head>` en cada stack.

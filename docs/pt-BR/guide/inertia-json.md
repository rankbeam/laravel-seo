---
description: "Use os mesmos dados SEO resolvidos no Inertia ou em uma API JSON e inclua metadados e JSON-LD no HTML inicial com SSR ou pré-renderização."
---

# Inertia e APIs JSON {#inertia-json-apis}

O mesmo `SEOData` resolvido usado por `@seo` pode gerar arrays estruturados. Os dados vêm de uma única fonte, seja o head construído por Blade, Vue, React, Svelte ou um frontend separado que consome sua API.

::: warning Metadados visíveis aos rastreadores exigem SSR ou pré-renderização
Uma aplicação Inertia sem SSR injeta metadados **no cliente**. O HTML inicial recebido por um rastreador ou serviço de prévia social não contém esses metadados até que o JavaScript execute. Para incluir o head na resposta HTTP original, ative o [SSR do Inertia](https://inertiajs.com/server-side-rendering) ou a pré-renderização. O JSON-LD, em especial, deve ser renderizado no servidor. Consulte o [contrato de renderização](/pt-BR/contributing/rendering-contract).
:::

## Inertia {#inertia}

`SEO::forInertia()` retorna dados no formato do componente `<Head>` do Inertia, com um **`head-key`** estável em cada entrada:

```php
use Rankbeam\Seo\Facades\SEO;

return Inertia::render('Blog/Post', [
    'post' => $post,
    'seo' => SEO::forInertia($post),
]);
```

Formato:

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

Defina `getSEOAlternates(): ?array` em um modelo com `HasSEO` para fornecer automaticamente os links hreflang a `SEO::resolve($post)` e `SEO::forInertia($post)`:

```php
public function getSEOAlternates(): ?array
{
    return [
        ['hreflang' => 'en', 'href' => route('posts.show', ['locale' => 'en', 'post' => $this])],
        ['hreflang' => 'it', 'href' => route('posts.show', ['locale' => 'it', 'post' => $this])],
    ];
}
```

Retorne URLs absolutas. O Inertia recebe essas entradas em `link`, com chaves estáveis como `alternate:en` e `alternate:it`.

::: tip Use `:head-key` para remover duplicatas
O Inertia identifica duplicatas pelo atributo **`head-key`**: uma tag do `<Head>` da página com a mesma chave de uma tag do layout a substitui. O `:key` do Vue é a chave de reconciliação de `v-for` e **não** remove duplicatas do head do Inertia. Sem `:head-key`, os metadados da página e do layout permanecem juntos, produzindo tags duplicadas ou desatualizadas durante a navegação no cliente. A tag robots é totalmente omitida quando coincide com o padrão do site, portanto não há um `index,follow` redundante para deduplicar.
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

O adaptador Svelte do Inertia **não tem um componente `<Head>`**. Use o `<svelte:head>` nativo com o mesmo array de `forInertia()`. Como `<svelte:head>` não oferece a deduplicação por `head-key` do Inertia, mantenha as tags SEO em um único lugar, o componente da página, sem dividi-las entre layout e página.

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

## JSON-LD com Inertia {#json-ld-with-inertia}

`forInertia()` omite a seção `script` de propósito: o `<Head>` do Inertia gerencia `title`, `meta` e `link`, não tags script brutas. Passe o JSON-LD como uma propriedade separada da página:

```php
return Inertia::render('Blog/Post', [
    'seo'    => SEO::forInertia($post),
    'schema' => SEO::toArray($post)['script'], // [{ type, innerHTML }]
]);
```

**Não** renderize essa propriedade pelo `<Head>` do Inertia, `dangerouslySetInnerHTML` do React ou `{@html}` do Svelte. Esses caminhos podem produzir um script vazio e interromper a renderização SSR. Renderize a propriedade a partir do array `$page` do Inertia na view raiz, logo depois de `@inertiaHead`.

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

A view raiz recebe as propriedades compartilhadas da página mesmo sem receber o modelo original. Isso mantém o JSON-LD na resposta SSR original. `innerHTML` já está protegido contra `</script>` com `JSON_HEX_TAG`; imprima-o diretamente, sem codificá-lo uma segunda vez.

A view raiz só executa na primeira requisição do documento. Substitua os scripts marcados após cada navegação do Inertia no cliente:

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

Para React ou Svelte, use a mesma função de atualização e importe `router` de `@inertiajs/react` ou `@inertiajs/svelte`. Mantenha título, metadados e links no componente de head do framework, conforme os exemplos acima. Apenas o JSON-LD usa a view raiz e esse ciclo de navegação.

## Arrays simples e APIs JSON {#plain-arrays-json-apis}

`SEO::toArray()` retorna tudo, incluindo a seção de scripts JSON-LD:

```php
$seo = SEO::toArray($post);
// [
//     'title'  => '...',
//     'meta'   => [...],                      // no head-key (decoupled clients dedupe their own way)
//     'link'   => [['rel' => 'canonical', 'href' => '...']],
//     'script' => [['type' => 'application/ld+json', 'innerHTML' => '{...}']],
// ]
```

Use esse formato em um endpoint de API quando um frontend desacoplado, como Nuxt ou Next, controla o head. A indicação `head-key` é específica do `<Head>` do Inertia, então `toArray()` a omite. O gerenciador do cliente, como `@unhead` ou `next/head`, cuida da deduplicação.

## Acesso aos valores resolvidos {#lower-level-access}

Quando você precisa dos valores antes da renderização:

```php
$data = SEO::resolve($post);          // SEOData value object
$data = SEO::forRoute('blog.index');  // for route pages

$data->title;
$data->description;
$data->canonical;
$data->ogImage;       // always an absolute URL
```

`SEOData` é um objeto de valor imutável. Consulte a [prioridade do resolvedor](/pt-BR/concepts/resolver-precedence) para entender a origem de cada propriedade e o [contrato de renderização](/pt-BR/contributing/rendering-contract) para conferir os requisitos do `<head>` em cada stack.

---
description: "通过 Inertia 或 JSON API 渲染同一份解析后的 SEO 数据，并使用 Inertia SSR 或预渲染，将爬虫可见的元数据和 JSON-LD 放入初始 HTML。"
---

# Inertia 与 JSON API {#inertia-json-apis}

为 `@seo` 提供数据的同一个解析后 `SEOData`，也可以渲染为结构化数组。无论 head 由 Blade、Vue、React、Svelte 还是调用 API 的独立前端构建，都使用一个可信来源。

::: warning 要让爬虫看到元数据，需要 Inertia SSR 或预渲染
默认不启用 SSR 的 Inertia 应用在**客户端**注入元数据。爬虫或社交抓取工具取得的初始 HTML，在 JavaScript 运行之前*没有* SEO 元数据。要让 head 出现在原始 HTTP 响应中，必须启用 [Inertia SSR](https://inertiajs.com/server-side-rendering) 或预渲染。JSON-LD 尤其应该在服务器端渲染。参见[渲染约定](/zh-CN/contributing/rendering-contract)。
:::

## Inertia {#inertia}

`SEO::forInertia()` 返回适合 Inertia `<Head>` 组件的数据，每个条目都有稳定的 **`head-key`**：

```php
use Rankbeam\Seo\Facades\SEO;

return Inertia::render('Blog/Post', [
    'post' => $post,
    'seo' => SEO::forInertia($post),
]);
```

数据结构：

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

### Hreflang 替代版本 {#hreflang-alternates}

在使用 `HasSEO` 的模型上定义 `getSEOAlternates(): ?array`，即可自动向 `SEO::resolve($post)` 和 `SEO::forInertia($post)` 提供 hreflang 链接：

```php
public function getSEOAlternates(): ?array
{
    return [
        ['hreflang' => 'en', 'href' => route('posts.show', ['locale' => 'en', 'post' => $this])],
        ['hreflang' => 'it', 'href' => route('posts.show', ['locale' => 'it', 'post' => $this])],
    ];
}
```

返回绝对网址。Inertia 在 `link` 中接收它们，并附带稳定的 head 键，例如 `alternate:en` 和 `alternate:it`。

::: tip 绑定 `:head-key`，不要误用 `:key`
Inertia 按 **`head-key`** 属性对 head 元素去重。页面中的 `<Head>` 标签如果与布局标签使用相同的 `head-key`，就会*替换*它，而不是叠加重复标签。Vue 的 `:key` 是用于 `v-for` 协调的另一个键，对 Inertia 的 head 去重**不起作用**。没有 `:head-key` 时，页面和布局元数据都会保留，导致客户端跳转后出现重复或陈旧标签。robots 标签与网站默认值相同时会完全省略，因此没有多余的 `index,follow` 需要去重。
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

Inertia 的 Svelte 适配器**没有 `<Head>` 组件**。使用 Svelte 原生的 `<svelte:head>`，同样从 `forInertia()` 数组获取数据。`<svelte:head>` 不具备 Inertia 的 `head-key` 去重机制，因此应将 SEO 标签集中放在页面组件中，不要分散到布局和页面两处。

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

## 在 Inertia 中使用 JSON-LD {#json-ld-with-inertia}

`forInertia()` 有意省略 `script` 部分，因为 Inertia 的 `<Head>` 管理 `title`/`meta`/`link`，不管理原始 script 标签。请将 JSON-LD 作为独立的页面 prop 传递：

```php
return Inertia::render('Blog/Post', [
    'seo'    => SEO::forInertia($post),
    'schema' => SEO::toArray($post)['script'], // [{ type, innerHTML }]
]);
```

**不要**通过 Inertia `<Head>`、React `dangerouslySetInnerHTML` 或 Svelte `{@html}` 渲染这个 prop。这些 head 管理路径可能生成空脚本，也可能破坏 SSR 渲染。应在根视图中，从 Inertia 的 `$page` 数组读取并渲染该 prop，紧接在 `@inertiaHead` 之后。

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

根视图虽然不接收原始模型，但能接收共享页面 props，因此可以让 JSON-LD 出现在原始 SSR 响应中。`innerHTML` 已通过 `JSON_HEX_TAG` 编码，对 `</script>` 是安全的，所以应直接输出，不要再次编码。

根视图只在初始文档请求时运行。每次 Inertia 客户端导航后，都要替换带标记的脚本：

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

React 或 Svelte 使用同一个更新函数，并从 `@inertiajs/react` 或 `@inertiajs/svelte` 导入 `router`。标题、meta 和 link 仍按上面的方式在框架 head 组件中渲染；只有 JSON-LD 使用根视图与导航生命周期。

## 普通数组与 JSON API {#plain-arrays-json-apis}

`SEO::toArray()` 返回全部内容，包括 JSON-LD 脚本部分：

```php
$seo = SEO::toArray($post);
// [
//     'title'  => '...',
//     'meta'   => [...],                      // no head-key (decoupled clients dedupe their own way)
//     'link'   => [['rel' => 'canonical', 'href' => '...']],
//     'script' => [['type' => 'application/ld+json', 'innerHTML' => '{...}']],
// ]
```

当完全解耦的前端，例如 Nuxt、Next，负责文档 head 时，应通过 API 端点提供这种格式。`head-key` 提示专用于 Inertia 的 `<Head>`，因此 `toArray()` 不包含它。由客户端自己的 head 管理器，例如 `@unhead`、`next/head`，负责去重。

## 更底层的访问 {#lower-level-access}

需要原始值，而不是渲染器输出时：

```php
$data = SEO::resolve($post);          // SEOData value object
$data = SEO::forRoute('blog.index');  // for route pages

$data->title;
$data->description;
$data->canonical;
$data->ogImage;       // always an absolute URL
```

`SEOData` 是不可变的值对象。每个属性如何取值，见[解析器优先级](/zh-CN/concepts/resolver-precedence)；每种技术栈的 `<head>` 都必须满足的完整检查清单，见[渲染约定](/zh-CN/contributing/rendering-contract)。

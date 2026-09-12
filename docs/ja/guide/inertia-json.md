---
description: "解決済みのSEOデータをInertiaやJSON APIで利用し、Inertia SSRまたはプリレンダリングで、クローラーが読み取れるmetaとJSON-LDを初期HTMLに含めます。"
---

# InertiaとJSON API {#inertia-json-apis}

`@seo`が利用する解決済みの`SEOData`は、構造化された配列としても出力できます。Blade、Vue、React、Svelte、あるいはAPIを利用する独立したフロントエンドのどれでheadを組み立てる場合も、同じデータを基準にできます。

::: warning クローラーが読めるmetaにはInertia SSRまたはプリレンダリングが必要
デフォルトのInertiaアプリ（SSRなし）は、metaを**クライアント側で**挿入します。クローラーやSNSのスクレイパーが取得する初期HTMLには、JavaScriptが実行されるまでSEO用のmetaが*含まれません*。生のHTTPレスポンスにheadの内容を含めるには、[Inertia SSR](https://inertiajs.com/server-side-rendering)を有効にするか、プリレンダリングを行う必要があります。特にJSON-LDは、サーバー側で出力してください。[出力の共通仕様](/ja/contributing/rendering-contract)も参照してください。
:::

## Inertia {#inertia}

`SEO::forInertia()`は、Inertiaの`<Head>`コンポーネント向けに整形したデータを返します。各エントリーには、一貫した**`head-key`**が付いています。

```php
use Rankbeam\Seo\Facades\SEO;

return Inertia::render('Blog/Post', [
    'post' => $post,
    'seo' => SEO::forInertia($post),
]);
```

データの構造：

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

### hreflangによる代替ページ {#hreflang-alternates}

`HasSEO`を使うモデルに`getSEOAlternates(): ?array`を定義すると、hreflangリンクが`SEO::resolve($post)`と`SEO::forInertia($post)`に自動的に渡されます。

```php
public function getSEOAlternates(): ?array
{
    return [
        ['hreflang' => 'en', 'href' => route('posts.show', ['locale' => 'en', 'post' => $this])],
        ['hreflang' => 'it', 'href' => route('posts.show', ['locale' => 'it', 'post' => $this])],
    ];
}
```

絶対URLを返してください。Inertiaは、`alternate:en`や`alternate:it`のような一貫したheadキーとともに、`link`配列でこれらのURLを受け取ります。

::: tip `:key`ではなく`:head-key`をバインドする
Inertiaは**`head-key`**属性でhead要素の重複を除きます。ページの`<Head>`内のタグがレイアウト側のタグと同じ`head-key`を持つ場合、重複して追加されるのではなく、そのタグを*置き換えます*。Vueの`:key`は`v-for`の差分更新に使う別のキーであり、Inertiaのhead要素の重複排除には**効果がありません**。`:head-key`がないと、ページとレイアウト両方のmetaが残り、クライアント側のページ遷移で重複したタグや古いタグが残ります。robotsタグはサイトのデフォルト値と一致する場合には出力されないため、冗長な`index,follow`の重複を処理する必要はありません。
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

InertiaのSvelteアダプターには**`<Head>`コンポーネントがありません**。同じ`forInertia()`の配列をSvelte標準の`<svelte:head>`に渡してください。`<svelte:head>`にはInertiaの`head-key`による重複排除機能がないため、SEOタグをレイアウトとページに分散させず、ページコンポーネントの1か所にまとめてください。

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

## InertiaでJSON-LDを出力する {#json-ld-with-inertia}

`forInertia()`は、意図的に`script`セクションを含めません。Inertiaの`<Head>`が管理するのは`title`、`meta`、`link`であり、scriptタグそのものではないためです。JSON-LDは専用のページpropとして渡してください。

```php
return Inertia::render('Blog/Post', [
    'seo'    => SEO::forInertia($post),
    'schema' => SEO::toArray($post)['script'], // [{ type, innerHTML }]
]);
```

このpropを、Inertiaの`<Head>`、Reactの`dangerouslySetInnerHTML`、Svelteの`{@html}`経由で出力しては**いけません**。これらのhead管理機能を通すと、空のscriptが生成されたり、SSRの描画が失敗したりする可能性があります。代わりに、ルートビューでInertiaの`$page`配列からpropを取得し、`@inertiaHead`の直後に出力します。

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

ルートビューは元のモデルを受け取りませんが、共有されたページpropsは受け取ります。この方法なら、生のSSRレスポンスにJSON-LDを含められます。`innerHTML`は、`JSON_HEX_TAG`でエンコードされており、`</script>`によってscript要素が途中で閉じられないように処理済みです。再度エンコードせず、そのまま出力してください。

ルートビューが実行されるのは、最初のドキュメント取得時だけです。クライアント側でInertiaのページ遷移が発生するたびに、マーカー付きのscriptを置き換えてください。

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

ReactやSvelteでも同じ更新処理を使い、`@inertiajs/react`または`@inertiajs/svelte`から`router`をインポートします。title、meta、linkは上記のとおりフレームワークのheadコンポーネントで出力してください。ルートビューとページ遷移時の更新処理を使うのは、JSON-LDだけです。

## 通常の配列とJSON API {#plain-arrays-json-apis}

`SEO::toArray()`は、JSON-LDのscriptセクションを含めたすべてのデータを返します。

```php
$seo = SEO::toArray($post);
// [
//     'title'  => '...',
//     'meta'   => [...],                      // no head-key (decoupled clients dedupe their own way)
//     'link'   => [['rel' => 'canonical', 'href' => '...']],
//     'script' => [['type' => 'application/ld+json', 'innerHTML' => '{...}']],
// ]
```

NuxtやNextなど、完全に分離されたフロントエンドがドキュメントのheadを管理する場合は、この形式をAPIエンドポイントから返します。`head-key`はInertiaの`<Head>`専用の情報なので、`toArray()`には含まれません。重複排除は、`@unhead`や`next/head`など、クライアント側のhead管理機能で行います。

## 出力前の値を直接取得する {#lower-level-access}

レンダラーの出力ではなく、解決済みの値を直接取得する場合は、次のように呼び出します。

```php
$data = SEO::resolve($post);          // SEOData value object
$data = SEO::forRoute('blog.index');  // for route pages

$data->title;
$data->description;
$data->canonical;
$data->ogImage;       // always an absolute URL
```

`SEOData`は不変の値オブジェクトです。各プロパティの値がどのように決まるかは[リゾルバーの優先順位](/ja/concepts/resolver-precedence)を、どの描画方式でも`<head>`が満たすべき要件は[出力の共通仕様](/ja/contributing/rendering-contract)を参照してください。

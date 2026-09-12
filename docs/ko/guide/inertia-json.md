---
description: "동일한 최종 SEO 데이터를 Inertia나 JSON API로 렌더링하고, Inertia SSR 또는 사전 렌더링으로 크롤러가 볼 수 있는 메타 태그와 JSON-LD를 초기 HTML에 포함하세요."
---

# Inertia와 JSON API {#inertia-json-apis}

`@seo`에 사용되는 동일한 최종 `SEOData` 데이터를 구조화된 배열로 렌더링할 수 있습니다. Blade, Vue, React, Svelte 또는 API를 사용하는 별도 프런트엔드가 head를 구성하더라도 기준 데이터는 하나입니다.

::: warning 크롤러가 메타 태그를 보려면 Inertia SSR 또는 사전 렌더링이 필요합니다
SSR이 없는 기본 Inertia 앱은 **클라이언트에서** 메타 태그를 삽입합니다. 크롤러나 소셜 스크레이퍼가 가져오는 초기 HTML에는 JavaScript가 실행되기 전까지 SEO 메타 태그가 *없습니다*. 원시 HTTP 응답에 head를 포함하려면 [Inertia SSR](https://inertiajs.com/server-side-rendering) 또는 사전 렌더링을 활성화해야 합니다. 특히 JSON-LD는 서버에서 렌더링해야 합니다. [렌더링 계약](/ko/contributing/rendering-contract)을 참고하세요.
:::

## Inertia {#inertia}

`SEO::forInertia()`는 Inertia의 `<Head>` 컴포넌트에 맞는 데이터를 반환하며, 각 항목에는 안정적인 **`head-key`**가 있습니다.

```php
use Rankbeam\Seo\Facades\SEO;

return Inertia::render('Blog/Post', [
    'post' => $post,
    'seo' => SEO::forInertia($post),
]);
```

데이터 형태는 다음과 같습니다.

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

### Hreflang 대체 페이지 {#hreflang-alternates}

`HasSEO`를 사용하는 모델에 `getSEOAlternates(): ?array` 메서드를 정의하면 `SEO::resolve($post)`와 `SEO::forInertia($post)`에 hreflang 링크가 자동으로 전달됩니다.

```php
public function getSEOAlternates(): ?array
{
    return [
        ['hreflang' => 'en', 'href' => route('posts.show', ['locale' => 'en', 'post' => $this])],
        ['hreflang' => 'it', 'href' => route('posts.show', ['locale' => 'it', 'post' => $this])],
    ];
}
```

절대 URL을 반환하세요. Inertia는 `link`에서 이 URL을 받으며, `alternate:en` 및 `alternate:it`와 같은 안정적인 head 키가 함께 제공됩니다.

::: tip `:key` 대신 `:head-key` 바인딩
Inertia는 **`head-key`** 속성으로 head 요소의 중복을 제거합니다. 페이지의 `<Head>` 태그가 레이아웃 태그와 같은 `head-key`를 가지면 중복으로 추가되는 대신 기존 태그를 *대체*합니다. Vue의 `:key`는 별개의 `v-for` 조정 키이며, Inertia의 head 중복 제거에는 **아무 역할도 하지 않습니다**. `:head-key`가 없으면 페이지와 레이아웃의 메타 태그가 모두 남아 클라이언트 내 탐색 시 중복되거나 오래된 태그가 생깁니다. robots 태그는 사이트 기본값과 같으면 완전히 생략되므로 불필요한 `index,follow`의 중복을 제거할 필요가 없습니다.
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

Inertia의 Svelte 어댑터에는 **`<Head>` 컴포넌트가 없습니다**. 같은 `forInertia()` 배열을 Svelte 기본 `<svelte:head>`에 전달하세요. `<svelte:head>`에는 Inertia의 `head-key` 중복 제거 기능이 없으므로 SEO 태그를 레이아웃과 페이지에 나누지 말고 페이지 컴포넌트 한곳에 두세요.

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

## Inertia에서 JSON-LD 사용 {#json-ld-with-inertia}

`forInertia()`는 의도적으로 `script` 섹션을 생략합니다. Inertia의 `<Head>`는 `title`/`meta`/`link` 요소를 관리하며, 원시 script 태그는 관리하지 않습니다. 대신 JSON-LD를 별도의 페이지 prop으로 전달하세요.

```php
return Inertia::render('Blog/Post', [
    'seo'    => SEO::forInertia($post),
    'schema' => SEO::toArray($post)['script'], // [{ type, innerHTML }]
]);
```

이 prop을 Inertia `<Head>`, React `dangerouslySetInnerHTML` 또는 Svelte `{@html}`로 렌더링하지 **마세요**. 이러한 head 관리 경로는 빈 script를 만들거나 SSR 렌더링을 깨뜨릴 수 있습니다. 대신 루트 뷰에서 Inertia의 `$page` 배열에 있는 prop을 `@inertiaHead` 바로 다음에 렌더링하세요.

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

루트 뷰는 원래 모델을 받지 않지만 공유 페이지 prop은 받습니다. 따라서 원시 SSR 응답에 JSON-LD를 유지할 수 있습니다. `innerHTML` 값은 이미 `JSON_HEX_TAG`로 인코딩되어 `</script>`에 안전하므로, 두 번 인코딩하지 말고 원시 값으로 출력하세요.

루트 뷰는 최초 문서 요청에만 실행됩니다. 클라이언트에서 Inertia 탐색이 일어날 때마다 표시된 script를 교체하세요.

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

React나 Svelte에서도 같은 갱신 함수를 사용하고 `@inertiajs/react` 또는 `@inertiajs/svelte`에서 `router`를 가져오세요. 제목, 메타, 링크 렌더링은 위 예시처럼 프레임워크의 head 컴포넌트에 유지합니다. 이 루트 뷰와 탐색 수명 주기를 사용하는 것은 JSON-LD뿐입니다.

## 일반 배열 / JSON API {#plain-arrays-json-apis}

`SEO::toArray()`는 JSON-LD script 섹션을 포함한 모든 데이터를 반환합니다.

```php
$seo = SEO::toArray($post);
// [
//     'title'  => '...',
//     'meta'   => [...],                      // no head-key (decoupled clients dedupe their own way)
//     'link'   => [['rel' => 'canonical', 'href' => '...']],
//     'script' => [['type' => 'application/ld+json', 'innerHTML' => '{...}']],
// ]
```

문서 head를 완전히 분리된 프런트엔드(Nuxt, Next 등)가 관리한다면 API 엔드포인트에서 이 형식을 제공하세요. `head-key` 힌트는 Inertia의 `<Head>` 전용이므로 `toArray()`에서는 생략됩니다. 중복 제거는 클라이언트의 head 관리자(`@unhead`, `next/head` 등)가 담당합니다.

## 하위 수준 접근 {#lower-level-access}

렌더러 출력 대신 원시 값이 필요할 때는 다음과 같이 사용하세요.

```php
$data = SEO::resolve($post);          // SEOData value object
$data = SEO::forRoute('blog.index');  // for route pages

$data->title;
$data->description;
$data->canonical;
$data->ogImage;       // always an absolute URL
```

`SEOData`는 불변 값 객체입니다. 각 속성의 값이 결정되는 과정은 [리졸버 우선순위](/ko/concepts/resolver-precedence)를, 모든 스택의 `<head>`가 충족해야 할 전체 점검 항목은 [렌더링 계약](/ko/contributing/rendering-contract)을 참고하세요.

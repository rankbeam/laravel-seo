---
description: "Выводите одинаковые итоговые данные SEO через Inertia или JSON API и включайте метаданные и JSON-LD в первоначальный HTML, доступный роботам, с помощью Inertia SSR или предварительного рендеринга."
---

# Inertia и JSON API {#inertia-json-apis}

Тот же итоговый `SEOData`, который использует `@seo`, можно вывести в структурированные массивы. Источник данных остаётся единым, независимо от того, формирует head Blade, Vue, React, Svelte или отдельный фронтенд, обращающийся к API.

::: warning Для метаданных, доступных роботам, нужен Inertia SSR или предварительный рендеринг
Стандартное приложение Inertia (без SSR) добавляет метаданные **на стороне клиента**. В первоначальном HTML, который получает робот или сборщик превью соцсети, SEO-метаданных *нет*, пока не выполнится JavaScript. Чтобы head присутствовал в исходном HTTP-ответе, необходимо включить [Inertia SSR](https://inertiajs.com/server-side-rendering) или предварительный рендеринг. В частности, JSON-LD следует выводить на сервере. См. [контракт рендеринга](/ru/contributing/rendering-contract).
:::

## Inertia {#inertia}

`SEO::forInertia()` возвращает данные в формате для компонента `<Head>` Inertia со стабильным **`head-key`** в каждой записи:

```php
use Rankbeam\Seo\Facades\SEO;

return Inertia::render('Blog/Post', [
    'post' => $post,
    'seo' => SEO::forInertia($post),
]);
```

Структура:

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

### Альтернативные языковые версии hreflang {#hreflang-alternates}

Определите `getSEOAlternates(): ?array` в модели с `HasSEO`, чтобы ссылки hreflang автоматически попадали в `SEO::resolve($post)` и `SEO::forInertia($post)`:

```php
public function getSEOAlternates(): ?array
{
    return [
        ['hreflang' => 'en', 'href' => route('posts.show', ['locale' => 'en', 'post' => $this])],
        ['hreflang' => 'it', 'href' => route('posts.show', ['locale' => 'it', 'post' => $this])],
    ];
}
```

Возвращайте абсолютные URL. Inertia получает их в `link` со стабильными ключами head, например `alternate:en` и `alternate:it`.

::: tip Привязывайте `:head-key`, а не `:key`
Inertia устраняет дубликаты элементов head по атрибуту **`head-key`**: тег страницы `<Head>` с тем же `head-key`, что и тег шаблона, *заменяет* его, а не добавляет дубликат. `:key` во Vue — отдельный ключ согласования `v-for`, который **не влияет** на устранение дубликатов head в Inertia. Без `:head-key` сохраняются и метаданные страницы, и метаданные шаблона, а при клиентских переходах появляются повторяющиеся или устаревшие теги. Тег robots полностью пропускается, если соответствует значению сайта по умолчанию, поэтому избыточного `index,follow` для удаления нет.
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

В адаптере Inertia для Svelte **нет компонента `<Head>`**: используйте встроенный `<svelte:head>` Svelte с тем же массивом `forInertia()`. (У `<svelte:head>` нет механизма устранения дубликатов Inertia по `head-key`, поэтому держите SEO-теги в одном месте — в компоненте страницы, не распределяя их между шаблоном и страницей.)

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

## JSON-LD с Inertia {#json-ld-with-inertia}

`forInertia()` намеренно не включает раздел `script`: `<Head>` Inertia управляет `title`/`meta`/`link`, а не произвольными тегами script. Вместо этого передайте JSON-LD как отдельное свойство страницы:

```php
return Inertia::render('Blog/Post', [
    'seo'    => SEO::forInertia($post),
    'schema' => SEO::toArray($post)['script'], // [{ type, innerHTML }]
]);
```

**Не** выводите это свойство через `<Head>` Inertia, `dangerouslySetInnerHTML` React или `{@html}` Svelte. Эти способы управления head могут создать пустой script и нарушить SSR-рендеринг. Вместо этого выведите свойство из массива `$page` Inertia в корневом представлении, сразу после `@inertiaHead`.

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

Корневое представление получает общие свойства страницы, хотя исходная модель ему не передаётся. Благодаря этому JSON-LD остаётся в исходном SSR-ответе. `innerHTML` уже безопасен относительно `</script>` (закодирован с `JSON_HEX_TAG`), поэтому выводите его напрямую, не кодируя повторно.

Корневое представление выполняется только при первоначальном запросе документа. Заменяйте помеченные скрипты после каждого клиентского перехода Inertia:

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

Для React или Svelte используйте тот же код обновления и импортируйте `router` из `@inertiajs/react` или `@inertiajs/svelte`. Заголовок, метатеги и ссылки по-прежнему выводите через компонент head фреймворка, как показано выше; только JSON-LD использует корневое представление и этот цикл навигации.

## Обычные массивы / JSON API {#plain-arrays-json-apis}

`SEO::toArray()` возвращает всё, включая раздел скриптов JSON-LD:

```php
$seo = SEO::toArray($post);
// [
//     'title'  => '...',
//     'meta'   => [...],                      // no head-key (decoupled clients dedupe their own way)
//     'link'   => [['rel' => 'canonical', 'href' => '...']],
//     'script' => [['type' => 'application/ld+json', 'innerHTML' => '{...}']],
// ]
```

Именно этот формат стоит отдавать из API, если head документа контролирует полностью отдельный фронтенд (Nuxt, Next и т. п.). Подсказка `head-key` относится только к `<Head>` Inertia, поэтому `toArray()` её не включает: устранением дубликатов занимается собственный менеджер head клиента (`@unhead`, `next/head`, …).

## Низкоуровневый доступ {#lower-level-access}

Если нужны исходные значения, а не результат рендеринга:

```php
$data = SEO::resolve($post);          // SEOData value object
$data = SEO::forRoute('blog.index');  // for route pages

$data->title;
$data->description;
$data->canonical;
$data->ogImage;       // always an absolute URL
```

`SEOData` — неизменяемый объект-значение. В разделе [приоритеты резолвера](/ru/concepts/resolver-precedence) описано, откуда берётся каждое свойство, а [контракт рендеринга](/ru/contributing/rendering-contract) содержит полный список требований к `<head>` любого стека.


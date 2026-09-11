---
description: "Aynı çözümlenmiş SEO verilerini Inertia veya JSON API üzerinden sunun. Inertia SSR ya da önceden oluşturmayla meta ve JSON-LD'yi tarayıcı botların görebildiği ilk HTML'e ekleyin."
---

# Inertia ve JSON API'leri {#inertia-json-apis}

`@seo` çıktısını sağlayan aynı çözümlenmiş `SEOData`, yapılandırılmış dizilere dönüştürülebilir. Head bölümünü Blade, Vue, React, Svelte veya API'nizi kullanan ayrı bir ön yüz oluştursa da verilerin tek bir kaynağı vardır.

::: warning Tarayıcı botların görebildiği meta verileri için Inertia SSR veya önceden oluşturma gerekir
Varsayılan bir Inertia uygulaması (SSR olmadan) meta verilerini **istemci tarafında** ekler. Bir arama motoru tarayıcısının veya sosyal paylaşım önizleme aracının aldığı ilk HTML, JavaScript çalışana kadar SEO meta verilerini *içermez*. Head bölümünün ham HTTP yanıtında bulunması için [Inertia SSR](https://inertiajs.com/server-side-rendering) özelliğini etkinleştirmeli veya sayfayı önceden oluşturmalısınız. Özellikle JSON-LD sunucuda oluşturulmalıdır. Bkz. [Çıktı Sözleşmesi](/tr/contributing/rendering-contract).
:::

## Inertia {#inertia}

`SEO::forInertia()`, Inertia'nın `<Head>` bileşenine uygun biçimde veri döndürür. Her girdinin sabit bir **`head-key`** değeri vardır:

```php
use Rankbeam\Seo\Facades\SEO;

return Inertia::render('Blog/Post', [
    'post' => $post,
    'seo' => SEO::forInertia($post),
]);
```

Veri yapısı:

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

### Hreflang alternatifleri {#hreflang-alternates}

Hreflang bağlantılarının `SEO::resolve($post)` ve `SEO::forInertia($post)` çıktılarına otomatik eklenmesi için `HasSEO` kullanan bir modelde `getSEOAlternates(): ?array` tanımlayın:

```php
public function getSEOAlternates(): ?array
{
    return [
        ['hreflang' => 'en', 'href' => route('posts.show', ['locale' => 'en', 'post' => $this])],
        ['hreflang' => 'it', 'href' => route('posts.show', ['locale' => 'it', 'post' => $this])],
    ];
}
```

Mutlak URL'ler döndürün. Inertia bunları `link` içinde, `alternate:en` ve `alternate:it` gibi sabit head anahtarlarıyla alır.

::: tip `:key` yerine `:head-key` bağlayın
Inertia, head öğelerindeki yinelenmeleri **`head-key`** özniteliğine göre giderir: bir sayfanın `<Head>` etiketi, yerleşimdeki bir etiketle aynı `head-key` değerine sahipse ikinci bir kopya olarak eklenmek yerine onun *yerini alır*. Vue'nun `:key` değeri, bununla ilgisiz olan `v-for` eşleştirme anahtarıdır; Inertia'nın head yinelenmelerini gidermesine **hiçbir etkisi yoktur**. `:head-key` olmadan hem sayfanın hem yerleşimin meta verileri kalır; istemci tarafındaki ziyaretlerde yinelenen veya eski etiketler oluşur. Robots etiketi site varsayılanıyla aynıysa tamamen atlanır; dolayısıyla yinelenmesi giderilecek gereksiz bir `index,follow` bulunmaz.
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

Inertia'nın Svelte adaptöründe **`<Head>` bileşeni yoktur**. Aynı `forInertia()` dizisiyle beslenen, Svelte'in yerleşik `<svelte:head>` öğesini kullanın. (`<svelte:head>`, Inertia'nın `head-key` ile yinelenmeleri giderme özelliğine sahip değildir. Bu nedenle SEO etiketlerini yerleşimle sayfa arasında bölmek yerine tek yerde, sayfa bileşeninde tutun.)

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

## Inertia ile JSON-LD {#json-ld-with-inertia}

`forInertia()`, `script` bölümünü bilerek dahil etmez: Inertia'nın `<Head>` bileşeni `title`/`meta`/`link` öğelerini yönetir, ham script etiketlerini değil. Bunun yerine JSON-LD'yi ayrı bir sayfa prop'u olarak iletin:

```php
return Inertia::render('Blog/Post', [
    'seo'    => SEO::forInertia($post),
    'schema' => SEO::toArray($post)['script'], // [{ type, innerHTML }]
]);
```

Bu prop'u Inertia `<Head>`, React `dangerouslySetInnerHTML` veya Svelte `{@html}` üzerinden **oluşturmayın**. Bu head yönetim yolları boş bir script üretebilir ve SSR çıktısını bozabilir. Bunun yerine kök görünümde, `@inertiaHead` hemen sonrasında, Inertia'nın `$page` dizisindeki prop'u çıktılayın.

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

Kök görünüm, asıl modeli almasa da paylaşılan sayfa prop'larını alır. Böylece JSON-LD ham SSR yanıtında kalır. `innerHTML` zaten `</script>` dizisine karşı güvenlidir (`JSON_HEX_TAG` ile kodlanır); bu nedenle ikinci kez kodlamak yerine ham olarak yazdırın.

Kök görünüm yalnızca ilk belge isteğinde çalışır. Her istemci taraflı Inertia gezinmesinden sonra işaretli script'leri değiştirin:

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

React veya Svelte için aynı güncelleyiciyi kullanın ve `router` öğesini `@inertiajs/react` veya `@inertiajs/svelte` paketinden içe aktarın. Başlık, meta ve bağlantı çıktılarını yukarıda gösterildiği gibi framework'ün head bileşeninde tutun; kök görünüm ve gezinme yaşam döngüsünü yalnızca JSON-LD için kullanın.

## Düz diziler / JSON API'leri {#plain-arrays-json-apis}

`SEO::toArray()`, JSON-LD script bölümü dahil her şeyi döndürür:

```php
$seo = SEO::toArray($post);
// [
//     'title'  => '...',
//     'meta'   => [...],                      // no head-key (decoupled clients dedupe their own way)
//     'link'   => [['rel' => 'canonical', 'href' => '...']],
//     'script' => [['type' => 'application/ld+json', 'innerHTML' => '{...}']],
// ]
```

Belgenin head bölümünü tamamen bağımsız bir ön yüz (Nuxt, Next vb.) yönetiyorsa API uç noktasından bu biçimi sunun. `head-key` ipucu Inertia'nın `<Head>` bileşenine özeldir; bu nedenle `toArray()` onu dahil etmez. Yinelenmeleri istemcinizin kendi head yöneticisi (`@unhead`, `next/head`, …) giderir.

## Alt düzey erişim {#lower-level-access}

Renderer çıktısı yerine ham değerlere ihtiyaç duyduğunuzda:

```php
$data = SEO::resolve($post);          // SEOData value object
$data = SEO::forRoute('blog.index');  // for route pages

$data->title;
$data->description;
$data->canonical;
$data->ogImage;       // always an absolute URL
```

`SEOData` değiştirilemez bir değer nesnesidir. Her özelliğin değerini nasıl aldığını öğrenmek için [çözümleyici öncelik sırasına](/tr/concepts/resolver-precedence), her teknoloji yığınının `<head>` bölümünün karşılaması gereken tam kontrol listesi için [Çıktı Sözleşmesine](/tr/contributing/rendering-contract) bakın.

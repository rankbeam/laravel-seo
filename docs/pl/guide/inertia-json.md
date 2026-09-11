---
description: "Renderuj te same rozstrzygnięte dane SEO przez Inertia lub API JSON i umieszczaj metadane oraz JSON-LD widoczne dla robotów indeksujących w początkowym HTML dzięki Inertia SSR lub prerenderowaniu."
---

# Inertia i API JSON {#inertia-json-apis}

Ten sam obiekt `SEOData`, który zasila `@seo`, można renderować do tablic o określonej strukturze. To jedno źródło prawdy niezależnie od tego, czy sekcję head buduje Blade, Vue, React, Svelte, czy osobny frontend korzystający z Twojego API.

::: warning Metadane widoczne dla robotów indeksujących wymagają Inertia SSR lub prerenderowania
Domyślna aplikacja Inertia (bez SSR) wstawia metadane **po stronie klienta**. Początkowy HTML pobierany przez robota indeksującego lub bota serwisu społecznościowego *nie zawiera* metadanych SEO, dopóki nie wykona się JavaScript. Aby sekcja head znalazła się w surowej odpowiedzi HTTP, musisz włączyć [Inertia SSR](https://inertiajs.com/server-side-rendering) lub prerenderowanie.

W szczególności JSON-LD powinien być renderowany po stronie serwera. Zobacz [kontrakt renderowania](/pl/contributing/rendering-contract).
:::

## Inertia {#inertia}

`SEO::forInertia()` zwraca dane w formacie dostosowanym do komponentu `<Head>` Inertia, ze stabilnym **`head-key`** w każdym wpisie:

```php
use Rankbeam\Seo\Facades\SEO;

return Inertia::render('Blog/Post', [
    'post' => $post,
    'seo' => SEO::forInertia($post),
]);
```


Struktura danych:

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


### Alternatywne wersje hreflang {#hreflang-alternates}

Zdefiniuj `getSEOAlternates(): ?array` w modelu korzystającym z `HasSEO`, aby automatycznie przekazywać linki hreflang do `SEO::resolve($post)` i `SEO::forInertia($post)`:

```php
public function getSEOAlternates(): ?array
{
    return [
        ['hreflang' => 'en', 'href' => route('posts.show', ['locale' => 'en', 'post' => $this])],
        ['hreflang' => 'it', 'href' => route('posts.show', ['locale' => 'it', 'post' => $this])],
    ];
}
```


Zwracaj bezwzględne adresy URL. Inertia otrzymuje je w `link` ze stabilnymi kluczami sekcji head, takimi jak `alternate:en` i `alternate:it`.

::: tip Przypisz `:head-key`, nie `:key`
Inertia usuwa duplikaty elementów sekcji head na podstawie atrybutu **`head-key`**: znacznik `<Head>` strony z tym samym `head-key` co znacznik szablonu *zastępuje* go, zamiast dodawać duplikat. Vue `:key` to niezależny klucz uzgadniania elementów `v-for` — **nie ma żadnego wpływu** na usuwanie duplikatów w sekcji head przez Inertia. Bez `:head-key` pozostają zarówno metadane strony, jak i szablonu, co powoduje duplikaty i nieaktualne znaczniki podczas przejść po stronie klienta.

Znacznik robots jest całkowicie pomijany, gdy odpowiada domyślnym ustawieniom witryny, więc nie ma zbędnego `index,follow`, którego duplikaty trzeba usuwać.
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

Adapter Svelte dla Inertia **nie ma komponentu `<Head>`**. Użyj natywnego `<svelte:head>` w Svelte, przekazując mu tę samą tablicę `forInertia()`. (`<svelte:head>` nie obsługuje usuwania duplikatów przez `head-key` znanego z Inertia, dlatego trzymaj znaczniki SEO w jednym miejscu — w komponencie strony — zamiast rozdzielać je między szablon a stronę.)

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


## JSON-LD z Inertia {#json-ld-with-inertia}

`forInertia()` celowo pomija sekcję `script`. Komponent `<Head>` Inertia zarządza `title`/`meta`/`link`, a nie surowymi znacznikami script. Przekaż JSON-LD jako osobną właściwość strony:

```php
return Inertia::render('Blog/Post', [
    'seo'    => SEO::forInertia($post),
    'schema' => SEO::toArray($post)['script'], // [{ type, innerHTML }]
]);
```


**Nie renderuj** tej właściwości przez `<Head>` Inertia, `dangerouslySetInnerHTML` React ani `{@html}` Svelte. Te mechanizmy zarządzania sekcją head mogą wygenerować pusty skrypt i zakłócić renderowanie SSR. Zamiast tego wyrenderuj właściwość z tablicy `$page` Inertia w widoku głównym, bezpośrednio po `@inertiaHead`.

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


Widok główny otrzymuje współdzielone właściwości strony, mimo że nie otrzymuje oryginalnego modelu. Dzięki temu JSON-LD trafia do surowej odpowiedzi SSR. `innerHTML` jest już bezpieczny dla `</script>` (zakodowany z użyciem `JSON_HEX_TAG`), więc wypisz go bez dodatkowego kodowania.

Widok główny wykonuje się tylko przy początkowym żądaniu dokumentu. Zastępuj oznaczone skrypty po każdym przejściu Inertia po stronie klienta:

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


W React lub Svelte użyj tego samego kodu aktualizującego i zaimportuj `router` z `@inertiajs/react` lub `@inertiajs/svelte`. Pozostaw renderowanie tytułu, metadanych i linków w komponencie head danego frameworka, tak jak pokazano powyżej. Tylko JSON-LD korzysta z tego cyklu obejmującego widok główny i nawigację.

## Zwykłe tablice i API JSON {#plain-arrays-json-apis}

`SEO::toArray()` zwraca wszystko, włącznie z sekcją skryptu JSON-LD:

```php
$seo = SEO::toArray($post);
// [
//     'title'  => '...',
//     'meta'   => [...],                      // no head-key (decoupled clients dedupe their own way)
//     'link'   => [['rel' => 'canonical', 'href' => '...']],
//     'script' => [['type' => 'application/ld+json', 'innerHTML' => '{...}']],
// ]
```


To format, który należy udostępnić w punkcie końcowym API, gdy za sekcję head dokumentu odpowiada całkowicie niezależny frontend (Nuxt, Next itd.). Wskazówka `head-key` jest specyficzna dla komponentu `<Head>` Inertia, dlatego `toArray()` ją pomija. Duplikatami zajmuje się mechanizm zarządzania sekcją head po stronie klienta (`@unhead`, `next/head`, …).

## Dostęp niższego poziomu {#lower-level-access}

Gdy potrzebujesz surowych wartości zamiast wyniku renderera:

```php
$data = SEO::resolve($post);          // SEOData value object
$data = SEO::forRoute('blog.index');  // for route pages

$data->title;
$data->description;
$data->canonical;
$data->ogImage;       // always an absolute URL
```


`SEOData` jest niemutowalnym obiektem wartości. Zobacz [priorytety resolvera](/pl/concepts/resolver-precedence), aby dowiedzieć się, skąd każda właściwość otrzymuje wartość, oraz [kontrakt renderowania](/pl/contributing/rendering-contract), który zawiera pełną listę wymagań dla `<head>` w każdym stosie technologicznym.

---
description: "Αποδώστε τα ίδια επιλυμένα δεδομένα SEO μέσω Inertia ή ενός JSON API και συμπεριλάβετε μεταδεδομένα και JSON-LD ορατά στους ανιχνευτές στο αρχικό HTML με Inertia SSR ή προαπόδοση."
---

# Inertia και JSON API {#inertia-json-apis}

Το ίδιο επιλυμένο `SEOData` που χρησιμοποιεί η `@seo` αποδίδεται σε δομημένους πίνακες —
μία πηγή αλήθειας, είτε το head δημιουργείται από Blade, Vue, React, Svelte είτε από
ένα ξεχωριστό frontend που καταναλώνει το API σας.

::: warning Τα μεταδεδομένα που βλέπουν οι ανιχνευτές απαιτούν Inertia SSR ή προαπόδοση
Μια προεπιλεγμένη εφαρμογή Inertia (χωρίς SSR) εισάγει μεταδεδομένα **στην πλευρά του πελάτη**. Το αρχικό HTML που
λαμβάνει ένας ανιχνευτής ή ένα εργαλείο συλλογής προεπισκοπήσεων κοινωνικών δικτύων *δεν έχει* μεταδεδομένα SEO μέχρι να εκτελεστεί η JavaScript. Για
να περιλαμβάνεται το head στην ακατέργαστη απόκριση HTTP, πρέπει να ενεργοποιήσετε το
[Inertia SSR](https://inertiajs.com/server-side-rendering) (ή να κάνετε προαπόδοση).
Ειδικά το JSON-LD θα πρέπει να αποδίδεται στον διακομιστή. Δείτε
[το Συμβόλαιο απόδοσης](/el/contributing/rendering-contract).
:::

## Inertia {#inertia}

Το `SEO::forInertia()` επιστρέφει δεδομένα στη μορφή που χρειάζεται το στοιχείο `<Head>` του Inertia, με ένα
σταθερό **`head-key`** σε κάθε εγγραφή:

```php
use Rankbeam\Seo\Facades\SEO;

return Inertia::render('Blog/Post', [
    'post' => $post,
    'seo' => SEO::forInertia($post),
]);
```

Η δομή:

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

### Εναλλακτικές εκδόσεις hreflang {#hreflang-alternates}

Ορίστε το `getSEOAlternates(): ?array` σε ένα μοντέλο που χρησιμοποιεί το `HasSEO`, ώστε οι
σύνδεσμοι hreflang να περνούν αυτόματα στα `SEO::resolve($post)` και `SEO::forInertia($post)`:

```php
public function getSEOAlternates(): ?array
{
    return [
        ['hreflang' => 'en', 'href' => route('posts.show', ['locale' => 'en', 'post' => $this])],
        ['hreflang' => 'it', 'href' => route('posts.show', ['locale' => 'it', 'post' => $this])],
    ];
}
```

Επιστρέψτε απόλυτες URL. Το Inertia τις λαμβάνει στο `link` με σταθερά κλειδιά head
όπως τα `alternate:en` και `alternate:it`.

::: tip Συνδέστε το `:head-key`, όχι το `:key`
Το Inertia αποτρέπει τα διπλότυπα στοιχεία head βάσει του γνωρίσματος **`head-key`**: μια ετικέτα `<Head>` σελίδας
με το ίδιο `head-key` με μια ετικέτα του layout την *αντικαθιστά*, αντί να προσθέσει
ένα διπλότυπο. Το `:key` του Vue είναι το άσχετο κλειδί συμφιλίωσης του `v-for` — δεν κάνει
**τίποτα** για την αποφυγή διπλοτύπων στο head του Inertia. Χωρίς το `:head-key`, τα μεταδεδομένα της σελίδας και του layout
παραμένουν και τα δύο, με αποτέλεσμα διπλές ή παρωχημένες ετικέτες κατά την πλοήγηση στην πλευρά του πελάτη.
Η ετικέτα robots παραλείπεται εντελώς όταν συμφωνεί με την προεπιλογή του ιστοτόπου, επομένως δεν υπάρχει
περιττό `index,follow` για αφαίρεση διπλοτύπων.
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

Ο προσαρμογέας Svelte του Inertia **δεν διαθέτει στοιχείο `<Head>`** — χρησιμοποιήστε το εγγενές
`<svelte:head>` του Svelte, με δεδομένα από τον ίδιο πίνακα `forInertia()`. (Το `<svelte:head>` δεν
διαθέτει την αποφυγή διπλοτύπων `head-key` του Inertia, οπότε κρατήστε τις ετικέτες SEO σε ένα σημείο — στο στοιχείο
της σελίδας — αντί να τις μοιράζετε μεταξύ layout και σελίδας.)

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

## JSON-LD με το Inertia {#json-ld-with-inertia}

Το `forInertia()` παραλείπει σκόπιμα την ενότητα `script` — το `<Head>` του Inertia
διαχειρίζεται `title`/`meta`/`link`, όχι ακατέργαστες ετικέτες script. Περάστε το JSON-LD
ως ξεχωριστό prop της σελίδας:

```php
return Inertia::render('Blog/Post', [
    'seo'    => SEO::forInertia($post),
    'schema' => SEO::toArray($post)['script'], // [{ type, innerHTML }]
]);
```

**Μην** αποδίδετε αυτό το prop μέσω του `<Head>` του Inertia, του
`dangerouslySetInnerHTML` του React ή του `{@html}` του Svelte. Αυτές οι διαδρομές διαχείρισης head μπορούν
να παράγουν κενό script και να διακόψουν την απόδοση SSR. Αντί γι' αυτό, αποδώστε το prop
από τον πίνακα `$page` του Inertia στη ριζική προβολή, αμέσως μετά το
`@inertiaHead`.

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

Η ριζική προβολή λαμβάνει τα κοινόχρηστα props της σελίδας, παρότι δεν λαμβάνει
το αρχικό μοντέλο. Έτσι το JSON-LD παραμένει στην ακατέργαστη απόκριση SSR. Το `innerHTML` είναι
ήδη ασφαλές ως προς το `</script>` (κωδικοποιημένο με `JSON_HEX_TAG`), οπότε εκτυπώστε το αυτούσιο
χωρίς να το κωδικοποιήσετε δεύτερη φορά.

Η ριζική προβολή εκτελείται μόνο στο αρχικό αίτημα εγγράφου. Αντικαταστήστε τα επισημασμένα
scripts μετά από κάθε πλοήγηση Inertia στην πλευρά του πελάτη:

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

Για React ή Svelte, χρησιμοποιήστε την ίδια συνάρτηση ενημέρωσης και εισαγάγετε το `router` από το
`@inertiajs/react` ή το `@inertiajs/svelte`. Κρατήστε την απόδοση τίτλου, μεταδεδομένων και συνδέσμων
στο στοιχείο head του framework, όπως φαίνεται παραπάνω· μόνο το JSON-LD χρησιμοποιεί αυτόν τον συνδυασμό ριζικής προβολής
και κύκλου ζωής πλοήγησης.

## Απλοί πίνακες / JSON API {#plain-arrays-json-apis}

Το `SEO::toArray()` επιστρέφει τα πάντα, μαζί με την ενότητα script του JSON-LD:

```php
$seo = SEO::toArray($post);
// [
//     'title'  => '...',
//     'meta'   => [...],                      // no head-key (decoupled clients dedupe their own way)
//     'link'   => [['rel' => 'canonical', 'href' => '...']],
//     'script' => [['type' => 'application/ld+json', 'innerHTML' => '{...}']],
// ]
```

Αυτή είναι η μορφή που πρέπει να εκθέτει ένα endpoint API όταν ένα πλήρως αποσυνδεδεμένο
frontend (Nuxt, Next κ.λπ.) διαχειρίζεται το head του εγγράφου. Η ένδειξη `head-key`
αφορά αποκλειστικά το `<Head>` του Inertia, επομένως το `toArray()` την παραλείπει — ο διαχειριστής head
του πελάτη σας (`@unhead`, `next/head`, …) φροντίζει για την αποφυγή διπλοτύπων.

## Πρόσβαση χαμηλότερου επιπέδου {#lower-level-access}

Όταν χρειάζεστε ακατέργαστες τιμές αντί για την έξοδο του renderer:

```php
$data = SEO::resolve($post);          // SEOData value object
$data = SEO::forRoute('blog.index');  // for route pages

$data->title;
$data->description;
$data->canonical;
$data->ogImage;       // always an absolute URL
```

Το `SEOData` είναι ένα αμετάβλητο αντικείμενο τιμών — δείτε την
[προτεραιότητα του resolver](/el/concepts/resolver-precedence) για το πώς αποκτά κάθε ιδιότητα
την τιμή της, και [το Συμβόλαιο απόδοσης](/el/contributing/rendering-contract)
για τον πλήρη κατάλογο ελέγχου που πρέπει να ικανοποιεί το `<head>` κάθε συστήματος.

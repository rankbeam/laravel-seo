---
description: "Utilisez les mêmes données SEO résolues avec Inertia ou une API JSON, et placez les métadonnées et le JSON-LD dans le HTML initial grâce au SSR ou au prérendu."
---

# Inertia et API JSON {#inertia-json-apis}

Le même `SEOData` résolu qui alimente `@seo` peut produire des tableaux structurés. Blade, Vue, React, Svelte ou un frontend séparé qui consomme votre API utilisent ainsi une même source pour construire le head.

::: warning Les métadonnées visibles dans la réponse initiale nécessitent le SSR ou le prérendu
Une application Inertia sans SSR injecte les métadonnées **côté client**. Le HTML initial reçu par un robot ou un outil de partage social ne contient *pas* ces métadonnées avant l'exécution de JavaScript. Pour les inclure dans la réponse HTTP brute, activez le [SSR d'Inertia](https://inertiajs.com/server-side-rendering) ou un prérendu. Le JSON-LD, en particulier, doit être rendu côté serveur. Consultez le [contrat de rendu](/fr/contributing/rendering-contract).
:::

## Inertia {#inertia}

`SEO::forInertia()` renvoie des données adaptées au composant `<Head>` d'Inertia, avec une **`head-key` stable** pour chaque entrée :

```php
use Rankbeam\Seo\Facades\SEO;

return Inertia::render('Blog/Post', [
    'post' => $post,
    'seo' => SEO::forInertia($post),
]);
```

Structure du résultat :

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

### Variantes hreflang {#hreflang-alternates}

Définissez `getSEOAlternates(): ?array` sur un modèle utilisant `HasSEO` pour transmettre automatiquement ses liens hreflang à `SEO::resolve($post)` et `SEO::forInertia($post)` :

```php
public function getSEOAlternates(): ?array
{
    return [
        ['hreflang' => 'en', 'href' => route('posts.show', ['locale' => 'en', 'post' => $this])],
        ['hreflang' => 'it', 'href' => route('posts.show', ['locale' => 'it', 'post' => $this])],
    ];
}
```

Renvoyez des URL absolues. Inertia les reçoit dans `link`, avec des clés de head stables comme `alternate:en` et `alternate:it`.

::: tip Utiliser `:head-key` pour la déduplication
Inertia déduplique les éléments du head grâce à leur attribut **`head-key`**. Une balise du `<Head>` de la page qui partage la `head-key` d'une balise du layout la *remplace*. La `:key` de Vue sert à la réconciliation de `v-for` et **ne déduplique pas** le head d'Inertia. Sans `:head-key`, les métadonnées du layout et de la page peuvent persister ensemble et créer des balises en double ou périmées lors des navigations côté client. La balise robots est omise lorsqu'elle correspond à la valeur par défaut du site ; aucun `index,follow` redondant n'est alors à dédupliquer.
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

L'adaptateur Svelte d'Inertia **n'a pas de composant `<Head>`**. Utilisez le composant natif `<svelte:head>`, alimenté par le même tableau `forInertia()`. Il ne bénéficie pas de la déduplication `head-key` d'Inertia. Conservez donc les balises SEO à un seul endroit, dans le composant de page, au lieu de les répartir entre layout et page.

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

## JSON-LD avec Inertia {#json-ld-with-inertia}

`forInertia()` omet volontairement la section `script`. Le `<Head>` d'Inertia gère `title`, `meta` et `link`, pas les balises script brutes. Transmettez le JSON-LD dans une propriété de page séparée :

```php
return Inertia::render('Blog/Post', [
    'seo'    => SEO::forInertia($post),
    'schema' => SEO::toArray($post)['script'], // [{ type, innerHTML }]
]);
```

N'affichez **pas** cette propriété dans le `<Head>` d'Inertia, avec `dangerouslySetInnerHTML` de React ou avec `{@html}` de Svelte. Ces chemins de gestion du head peuvent produire un script vide et perturber le SSR. Affichez plutôt la propriété depuis le tableau `$page` d'Inertia dans la vue racine, juste après `@inertiaHead` :

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

La vue racine reçoit les propriétés de page partagées, même si elle ne reçoit pas le modèle d'origine. Le JSON-LD reste ainsi dans la réponse SSR brute. `innerHTML` est déjà protégé contre les séquences `</script>` grâce à `JSON_HEX_TAG` : affichez-le tel quel, sans l'encoder une deuxième fois.

La vue racine ne s'exécute que lors de la requête initiale du document. Remplacez les scripts marqués après chaque navigation Inertia côté client :

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

Avec React ou Svelte, utilisez la même fonction de mise à jour et importez `router` depuis `@inertiajs/react` ou `@inertiajs/svelte`. Le titre, les balises meta et les liens restent dans le composant de head du framework, comme ci-dessus. Seul le JSON-LD utilise ce cycle entre vue racine et navigation.

## Tableaux ordinaires et API JSON {#plain-arrays-json-apis}

`SEO::toArray()` renvoie toutes les sections, y compris les scripts JSON-LD :

```php
$seo = SEO::toArray($post);
// [
//     'title'  => '...',
//     'meta'   => [...],                      // no head-key (decoupled clients dedupe their own way)
//     'link'   => [['rel' => 'canonical', 'href' => '...']],
//     'script' => [['type' => 'application/ld+json', 'innerHTML' => '{...}']],
// ]
```

Exposez ce format depuis une API si un frontend entièrement séparé, comme Nuxt ou Next, gère le head du document. L'indication `head-key` est propre au `<Head>` d'Inertia ; `toArray()` l'omet. Le gestionnaire de head du client, par exemple `@unhead` ou `next/head`, assure sa propre déduplication.

## Accès aux valeurs brutes {#lower-level-access}

Si vous avez besoin des valeurs avant leur transformation par le moteur de rendu :

```php
$data = SEO::resolve($post);          // SEOData value object
$data = SEO::forRoute('blog.index');  // for route pages

$data->title;
$data->description;
$data->canonical;
$data->ogImage;       // always an absolute URL
```

`SEOData` est un objet-valeur immuable. La [priorité du résolveur](/fr/concepts/resolver-precedence) explique l'origine de chaque propriété. Le [contrat de rendu](/fr/contributing/rendering-contract) décrit les exigences que doit respecter le `<head>` de chaque stack.

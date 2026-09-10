---
description: "Utilisez les directives @seo dans Livewire : elles produisent du HTML dans le head et fonctionnent dans les composants pleine page comme dans les layouts Blade."
---

# Livewire {#livewire}

Les directives Blade `@seo` produisent du HTML ordinaire dans le `<head>`. Elles fonctionnent donc dans les applications Livewire de la même manière que dans Blade.

## Rendu initial d'une page complète {#initial-full-page-render}

Dans un **composant Livewire pleine page**, renvoyé par une route, ou dans un layout Blade qui contient des composants Livewire, `@seo` s'utilise comme dans le [guide Blade](/fr/guide/blade) :

```blade
{{-- layouts/app.blade.php --}}
<head>
    @seo($post ?? null)
</head>
```

La première réponse HTTP contient le head complet, visible par les robots : titre, description, canonical, Open Graph, Twitter et JSON-LD. Les robots d'exploration et les outils de partage social reçoivent directement ces éléments.

## La particularité de `wire:navigate` {#the-wire-navigate-caveat}

[`wire:navigate`](https://livewire.laravel.com/docs/navigate) transforme les clics sur les liens en navigations de type SPA. Livewire remplace le `<body>` et **fusionne le `<head>`**, avec une différence qui compte pour le SEO :

- **`<title>` et `<meta>`/`<link>`** sont fusionnés à partir du head de la nouvelle page. Le titre et les métadonnées résolus sont donc généralement mis à jour.
- **Les `<script>` sont traités comme des ressources à conserver.** Livewire garde les scripts déjà rencontrés pour éviter qu'une nouvelle exécution perturbe le JavaScript. Les **blocs `<script>` JSON-LD s'accumulent** alors : après trois articles, les schémas des trois restent dans le head. Un outil qui lit les données structurées peut y trouver plusieurs entités ou une entité incorrecte.

Pour permettre le nettoyage, le moteur de rendu **marque chaque script JSON-LD** qu'il produit :

```html
<script type="application/ld+json" data-seo-schema
        data-seo-url="https://example.com/blog/the-post"> … </script>
```

## Ajouter le nettoyage du JSON-LD {#ship-the-json-ld-cleanup}

Ajoutez ce code une seule fois, par exemple dans le layout racine après `@livewireScripts`. À chaque `wire:navigate`, il conserve uniquement le schéma de la **page courante** et supprime les anciens :

```blade
<script>
    document.addEventListener('livewire:navigated', () => {
        // The page we are now on. data-seo-url is the canonical (query-stripped),
        // so compare on the query-stripped location.
        const here = window.location.href.split('#')[0].split('?')[0]

        // Keep only the LAST schema for this page; remove every other-URL
        // (stale) script AND same-URL duplicates Livewire re-adds when a page is
        // revisited — including clearing a lone stale script when this page has
        // none. Iterate from the end so the freshest copy is the one kept.
        const scripts = [...document.querySelectorAll('script[data-seo-schema]')]
        let kept = false
        for (let i = scripts.length - 1; i >= 0; i--) {
            const url = (scripts[i].getAttribute('data-seo-url') || '').split('?')[0]
            if (url === here && !kept) { kept = true; continue }
            scripts[i].remove()
        }
    })
</script>
```

Il s'appuie uniquement sur le marqueur `data-seo-schema` et l'identifiant par URL déjà produits par le moteur de rendu. Aucune configuration par page n'est nécessaire.

::: warning Comparer à l'URL courante, pas au dernier script ajouté
Une ancienne version de cet exemple s'arrêtait s'il y avait moins de deux scripts de schéma et considérait le *dernier script ajouté* comme celui de la page courante. Cela conservait un ancien schéma lors du passage d'une page **avec** JSON-LD à une page **sans** JSON-LD : seul l'ancien script restait, et le retour anticipé le préservait. Cette version ne supprimait pas non plus un **doublon de même URL** ajouté par Livewire lors d'une nouvelle visite. Comparer chaque `data-seo-url` à `window.location` et ne garder que la **dernière** correspondance supprime les anciens schémas et les doublons dans ces deux cas. L'application Livewire de `rankbeam-examples` et son test navigateur vérifient ce comportement.
:::

::: tip Balises meta uniques lors d'une navigation SPA
La fusion du head de Livewire actualise les balises `<meta>` et `<link>` uniques dans la plupart des cas. Le comportement exact dépend toutefois de la version de Livewire et de la structure du layout. Si la présence des métadonnées dans la réponse reçue par les robots est essentielle, utilisez un **rechargement complet** avec un lien sans `wire:navigate`, ou un **rendu côté serveur** pour que la première réponse HTTP contienne les bonnes valeurs. L'application Livewire de [`rankbeam-examples`](https://github.com/rankbeam) teste un véritable parcours `wire:navigate` dans le navigateur.
:::

## Filament {#filament}

Filament utilise Livewire, mais c'est une **interface d'administration pour rédiger les données**. Il modifie `seo_meta` et ne produit jamais le head de votre site public. Consultez le [guide Filament](/fr/guide/filament) ; les mécanismes décrits ici concernent les pages publiques.

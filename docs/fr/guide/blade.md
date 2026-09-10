---
description: "Affichez les métadonnées SEO dans Laravel avec les directives Blade : @seo résout un modèle et produit les balises meta, Open Graph, Twitter Cards et JSON-LD."
---

# Guide Blade {#blade-guide}

Le package fournit sept directives Blade pour les applications rendues côté serveur. L'une d'elles, `@seo`, suffit dans la plupart des cas.

## La directive tout-en-un {#the-all-in-one-directive}

```blade
<!DOCTYPE html>
<html>
<head>
    @seo($post)
</head>
```

`@seo` résout le modèle selon la [chaîne de priorité](/fr/concepts/resolver-precedence) et produit l'ensemble du bloc head : `<title>`, meta description, lien canonical, robots, balises Open Graph, Twitter Cards et JSON-LD associé. La balise robots apparaît **uniquement si sa valeur diffère de la valeur par défaut du site**. Un `index,follow` redondant est omis, puisque l'absence de balise correspond déjà à ce comportement. Activez `seo.robots.emit_default` pour toujours l'afficher. Consultez le [contrat de rendu (EN)](/fr/contributing/rendering-contract) complet.

Signatures :

```blade
@seo($post)                  {{-- model page --}}
@seo($seoData)               {{-- a hand-built SEOData (model-less page) --}}
@seo($post, 'blog.show')     {{-- model + route defaults --}}
@seo($post, null, 'fr')      {{-- model + locale --}}
@seo(null)                   {{-- current page, no model --}}
```

`@seo` accepte un `Model`, un `SEOData` construit manuellement ou `null`. Les arguments de route et de langue s'appliquent uniquement au chemin `Model`/`null`. Un `SEOData` construit manuellement porte ses propres valeurs.

## Pages de route sans modèle {#route-pages-no-model}

Pour les pages statiques, archives et autres pages définies par une route :

```blade
@seoForRoute('pages.about')
@seoForRoute('contact', 'de')   {{-- with locale --}}
```

Les valeurs de route proviennent des lignes de `seo_defaults` associées au nom de la route.

## Pages sans modèle : construire un `SEOData` {#model-less-pages-hand-built-seodata}

Une liste, des résultats de recherche ou une page composée dans un contrôleur n'ont pas toujours un modèle unique. Construisez un `SEOData` et passez-le directement à `@seo` ou à la façade `SEO`, sans appeler `app(TagRenderer::class)->render(...)` :

```php
use Rankbeam\Seo\Data\SEOData;

return view('search.results', [
    'seo' => new SEOData(
        title: "Results for \"{$query}\"",
        description: "Browse {$count} matches for {$query}.",
        ogImage: '/images/search-share.jpg',   // relative is fine — see below
    ),
]);
```

```blade
<head>
    @seo($seo)
</head>
```

Un `SEOData` construit manuellement est traité comme une **intention explicite**. Les valeurs fournies sont conservées ; seuls les éléments manquants suivants sont complétés au rendu :

- `canonical` et `og:url` sont dérivés de l'URL courante s'ils sont absents. Un `canonical` explicite est conservé tel quel, paramètres de requête compris.
- `title_suffix` est ajouté seulement si le titre ne le contient pas déjà. Il est entièrement ignoré si le titre contient déjà un terme de marque configuré ; voir [`title_suffix_skip_when_contains` (EN)](/fr/reference/configuration).
- Les chemins relatifs de `og:image` et `twitter:image` deviennent absolus avec `url()`, qui respecte le protocole courant et **ne force pas HTTPS**.
- `og:site_name` et `locale` sont complétés à partir de la configuration et de la langue de l'application.

La chaîne de priorité en base de données – valeurs globales, par type de modèle, par route et `seo_meta` – **n'est pas fusionnée** avec un `SEOData` construit manuellement. Le rendu utilise ce que vous fournissez, avec les compléments ci-dessus.

La même valeur fonctionne avec la façade :

```php
SEO::render($seoData);     // HTML string
SEO::toArray($seoData);    // Vue/React structure
SEO::forInertia($seoData); // Inertia Head structure
```

## Un layout commun à plusieurs types de pages {#a-layout-pattern-that-scales}

Un seul layout peut servir les pages de modèle, les pages de route et les autres pages :

```blade
<head>
    @if(isset($seoModel))
        @seo($seoModel)
    @elseif(isset($seoRoute))
        @seoForRoute($seoRoute)
    @else
        @seo(null)
    @endif
</head>
```

Les contrôleurs passent alors `'seoModel' => $post` ou `'seoRoute' => 'blog.index'` sans manipuler le balisage.

## Directives spécialisées {#granular-directives}

Pour contrôler les balises individuellement, par exemple en combinant leur rendu avec celui d'un autre package :

| Directive | Rendu |
|---|---|
| `@seoTitle($post)` | Uniquement `<title>` |
| `@seoMeta($post)` | Uniquement la meta description |
| `@seoCanonical($post)` | Uniquement le lien canonical, avec repli sur l'URL courante |
| `@seoRobots($post)` | Uniquement la balise meta robots, toujours affichée. Cet appel explicite **n'applique pas** la suppression des valeurs par défaut utilisée par `@seo` |
| `@seoSchema($post)` | Uniquement le `<script>` JSON-LD, valide dans le head ou le body |

Toutes acceptent la même expression `($model, $route, $locale)` que `@seo`, ou aucun argument pour la page courante.

## Variantes hreflang {#hreflang-alternates}

Les modèles utilisant `HasSEO` peuvent fournir leurs liens hreflang directement au résolveur :

```php
public function getSEOAlternates(): ?array
{
    return [
        ['hreflang' => 'en', 'href' => route('posts.show', ['locale' => 'en', 'post' => $this])],
        ['hreflang' => 'it', 'href' => route('posts.show', ['locale' => 'it', 'post' => $this])],
    ];
}
```

Utilisez des URL absolues. `@seo($post)` résout ces entrées et produit pour chacune un `<link rel="alternate" hreflang="..." href="...">`. Les codes sont d'abord normalisés au format BCP 47 (`it_IT` → `it-IT`). Les politiques `seo.hreflang` peuvent ajouter une référence à la page elle-même et un `x-default`. L'audit gratuit signale les entrées invalides, en double ou sans autoréférence. Voir [contenu multilingue](/fr/guide/multilingual#hreflang).

## Échappement et sécurité {#escaping-and-safety}

Les valeurs textuelles sont échappées avec `e()`. Le JSON-LD est encodé avec `JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP`. Un `</script>` présent dans un contenu utilisateur ne peut donc pas fermer prématurément l'élément script.

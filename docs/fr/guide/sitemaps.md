---
description: "Enregistrez des sources de sitemap depuis des modèles, fonctions ou listes d'URL, puis générez un index XML servi à /sitemap.xml."
---

# Registre des sitemaps

Le paquet génère un fichier XML par source et un index, servis à `/sitemap.xml` et `/sitemap-{name}.xml`. La génération utilise [spatie/laravel-sitemap](https://github.com/spatie/laravel-sitemap) :

```bash
composer require spatie/laravel-sitemap
```

## Enregistrer des sources {#registering-sources}

Déclarez des sources nommées dans `boot()` d'un fournisseur de services :

```php
use App\Models\Post;
use Rankbeam\Seo\Facades\SEO;

// A model class — every (indexable) record's getUrlForSEO()
SEO::sitemaps()->register('posts', Post::class);

// A closure returning URLs
SEO::sitemaps()->register('pages', fn () => ['/about', '/contact']);

// Any iterable of URLs
SEO::sitemaps()->register('legal', ['/imprint', '/privacy']);
```

Chaque source produit `sitemap-{name}.xml`. `sitemap.xml` devient l'index qui les répertorie. Le registre expose aussi `has($name)`, `names()`, `forget($name)` et `flush()`.

## Sources par configuration {#config-driven-sources}

Vous pouvez également définir les modèles et URL statiques dans `config/seo.php` :

```php
'sitemap' => [
    'models' => [
        \App\Models\Post::class => ['priority' => 0.8, 'changefreq' => 'weekly'],
    ],
    'static_urls' => [
        ['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily'],
    ],
],
```

::: info Priorité aux sources enregistrées
La découverte automatique ignore les modèles déjà couverts par une source nommée. Enregistrer `posts` ne produit donc pas aussi un `sitemap-post.xml`.
:::

## Génération {#generating}

```bash
php artisan seo:sitemap
```

Les fichiers sont écrits sur `seo.sitemap.disk`, par défaut `public`. Planifiez le traitement pour les actualiser :

```php
// routes/console.php or bootstrap/app.php scheduling
Schedule::command('seo:sitemap')->daily();
```

Les sitemaps dépassant `seo.sitemap.max_urls_per_sitemap`, par défaut 50 000 selon la limite XML, sont divisés automatiquement.

## Mise à disposition {#serving}

Les routes du paquet servent les fichiers générés avec les en-têtes XML, de cache et `X-Robots-Tag: noindex` :

- `/sitemap.xml` : index ou sitemap unique.
- `/sitemap-posts.xml` : source nommée.

Si vous servez vos propres fichiers statiques, désactivez ces routes :

```php
// config/seo.php
'routes' => ['enabled' => false],
```

## Affichage lisible dans le navigateur {#styled-sitemap-in-the-browser}

Rankbeam associe une feuille XSL au XML pour afficher un tableau lisible : URL, `lastmod`, fréquence de modification, priorité, nombre d'images et de variantes linguistiques, ainsi que des remarques de validation.

![Sitemap Rankbeam présenté dans un tableau lisible avec les couleurs du produit](/sitemap-styled.png)

Chaque fichier généré référence la feuille de style :

```xml
<?xml version="1.0" encoding="UTF-8"?>
<?xml-stylesheet type="text/xsl" href="https://your-app.test/sitemap.xsl"?>
<urlset ...>
```

Cette instruction modifie l'affichage humain ; le document reste un sitemap XML exploitable par les moteurs de recherche. L'index et les fichiers enfants utilisent le même style.

La fonction est **activée par défaut**. Elle ajoute une instruction, sans données ni traitement par enregistrement, contrairement aux extensions d'images et de langues.

::: warning spatie/laravel-sitemap 8.1 ou plus requis
L'instruction utilise `setStylesheet()`, introduite en 8.1. Avec une version antérieure, le paquet génère simplement du XML sans mise en forme. `composer update spatie/laravel-sitemap` permet d'obtenir une version compatible si les contraintes de votre application l'autorisent.
:::

Pour désactiver la feuille de style :

```php
// config/seo.php
'sitemap' => [
    'stylesheet' => ['enabled' => false],
],
```

### Remarques de validation {#validation-notes}

La page signale deux cas vérifiables dans le navigateur :

- **Absence de `lastmod` :** la date manquante est signalée, jamais inventée. Une date de fraîcheur doit correspondre à une modification réelle.
- **URL non absolue :** un `<loc>` qui n'est pas une URL `http(s)` absolue.

### Héberger la feuille de style {#self-hosting-the-stylesheet}

Par défaut, la route `/sitemap.xsl` du paquet sert la feuille. Le navigateur exige qu'elle ait la **même origine** que le sitemap. Si vos sitemaps sont sur un CDN, publiez le fichier sur cette origine et configurez son URL :

```bash
php artisan vendor:publish --tag=seo-assets
```

```php
// config/seo.php
'sitemap' => [
    'stylesheet' => [
        'url' => 'https://cdn.example.com/vendor/seo/sitemap.xsl',
    ],
],
```

::: info Conserver l'échappement
Toutes les valeurs affichées, URL comprises, passent par l'échappement XSLT. Un `<loc>` ne devient un lien que pour une URL `http(s)`. Le contenu d'une URL ne peut donc pas injecter du HTML ou un lien `javascript:` dans cette vue. Si vous personnalisez le fichier `.xsl`, conservez ce comportement et n'ajoutez pas `disable-output-escaping`.
:::

## Contenu inclus {#what-gets-included}

Les sources de modèles incluent les enregistrements résolus comme indexables. Un modèle dont les directives robots contiennent `noindex` est exclu. Les URL viennent de `getUrlForSEO()`, également utilisé pour les canonicals.

## Extensions images et hreflang {#image-hreflang-extensions}

Deux extensions facultatives ajoutent les données déjà résolues pour chaque modèle. Elles sont **désactivées par défaut** :

```php
'sitemap' => [
    'images' => true,      // <image:image> per URL
    'alternates' => true,  // <xhtml:link rel="alternate"> per URL
],
```

Elles s'appliquent aux modèles utilisant `HasSEO`, à partir de leur `seoData()` entièrement résolu :

- **`images`** ajoute une entrée de [sitemap d'images Google](https://developers.google.com/search/docs/crawling-indexing/sitemaps/image-sitemaps) avec la même image que `og:image`. Sans image propre au modèle, il s'agit de `default_og_image`. Activez cette extension seulement si les images par URL sont utiles à votre contenu.
- **`alternates`** ajoute des liens `<xhtml:link rel="alternate" hreflang="…">` issus de `getSEOAlternates()`, comme dans le `<head>`. Renvoyez des URL absolues :

```php
public function getSEOAlternates(): ?array
{
    return [
        ['hreflang' => 'en', 'href' => route('posts.show', [$this, 'locale' => 'en'])],
        ['hreflang' => 'fr', 'href' => route('posts.show', [$this, 'locale' => 'fr'])],
        ['hreflang' => 'x-default', 'href' => route('posts.show', $this)],
    ];
}
```

::: warning Références réciproques et autoréférence
Chaque version linguistique doit lister **sa propre URL et les autres versions**, avec des références réciproques. `getSEOAlternates()` doit renvoyer l'ensemble complet sur chaque variante. Utilisez des codes `language[-Script][-REGION]` valides ou `x-default`, avec des URL `http(s)` absolues. Une entrée sans `hreflang` ou `href` non vide est ignorée.

Les [politiques `seo.hreflang`](/fr/guide/multilingual#hreflang) s'appliquent avant l'écriture : normalisation (`it_IT` → `it-IT`), ajout éventuel de l'autoréférence et de `x-default`. La liste est commune au sitemap et au `<head>`. L'audit gratuit signale `hreflang_invalid_code`, `hreflang_duplicate_code` et `hreflang_missing_self`. La réciprocité nécessite un crawl, proposé par Pro.
:::

::: info Coût sur les grands catalogues
Activer une extension entraîne la résolution complète de `seoData()` pour chaque URL : valeurs par défaut, valeurs calculées et getters `getSEO*()`. Chaque enregistrement peut provoquer plusieurs opérations de cache ou de base de données ; vos getters peuvent ajouter des requêtes. Ce traitement est prévu pour la commande planifiée `seo:sitemap`. Mesurez son coût avant de l'activer près de la limite de 50 000 URL et laissez les deux options désactivées si elles ne vous servent pas.
:::

::: tip Configuration déjà publiée
La fusion de `config/seo.php` n'est pas récursive. Une configuration publiée avant l'ajout de ces extensions ne reçoit pas automatiquement `sitemap.images` et `sitemap.alternates`. Les seules variables `SEO_SITEMAP_IMAGES` et `SEO_SITEMAP_ALTERNATES` ne suffisent alors pas. Ajoutez les deux clés dans le tableau publié ou republiez la configuration.
:::

## Contrôle complet avec les balises Spatie {#full-control-hand-built-spatie-tags}

Pour des légendes d'images, des vidéos, des actualités ou une liste hreflang particulière, renvoyez une balise [`Spatie\Sitemap\Tags\Url`](https://github.com/spatie/laravel-sitemap#adding-images) construite à la main depuis votre source. Le générateur la conserve telle quelle et n'y ajoute pas ses propres extensions :

```php
use Spatie\Sitemap\Tags\Url;

SEO::sitemaps()->register('videos', fn () => Video::query()
    ->get()
    ->map(fn (Video $video) => Url::create($video->url)
        ->addImage($video->thumbnail_url, caption: $video->title)
        ->addVideo(
            thumbnailLoc: $video->thumbnail_url,
            title: $video->title,
            description: $video->description,
            contentLoc: $video->file_url,
        )
        ->addAlternate($video->frenchUrl, 'fr')
    ));
```

La même possibilité existe par enregistrement : lorsqu'un modèle implémente `Sitemapable` et que `toSitemapTag()` renvoie une `Url`, celle-ci est émise sans modification.

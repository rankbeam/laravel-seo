---
description: "Installez Rankbeam, ajoutez HasSEO à un modèle existant, enregistrez ses métadonnées et vérifiez les balises rendues par Blade."
---

# Démarrage rapide

Partez d'une application Laravel 11, 12 ou 13 existante, avec une base de données fonctionnelle. PHP 8.2 ou plus est requis, ou 8.3 pour Laravel 13. Le cœur est gratuit sous licence MIT ; aucun compte ni licence Pro n'est nécessaire.

## Installation {#install}

Exécutez ces commandes dans le répertoire de votre application :

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan migrate
```

Le fournisseur de services est découvert automatiquement. La migration crée les tables SEO, pas les modèles de contenu de votre application.

## Avant de commencer {#before-the-example}

L'exemple suppose un modèle `Post`, un article déjà enregistré et une route `posts.show` dont la vue Blade reçoit cet article dans `$post`. Adaptez ces noms à votre application. Ce guide ajoute le SEO à une page existante ; il ne crée pas le blog.

Définissez `APP_URL` dans `.env` avec l'origine publique de votre site. Pour d'autres modes de rendu, consultez [Inertia et JSON (EN)](/fr/guide/inertia-json) ou [Livewire (EN)](/fr/guide/livewire).

## 1. Ajouter le trait au modèle {#_1-add-the-trait-to-a-model}

```php
use Illuminate\Database\Eloquent\Model;
use Rankbeam\Seo\Traits\HasSEO;

class Post extends Model
{
    use HasSEO;

    public function getUrlForSEO(): string
    {
        return route('posts.show', $this);
    }
}
```

`getUrlForSEO()` indique au résolveur l'URL canonique du modèle. Elle sert aux balises canonical, à `og:url` et aux entrées de sitemap.

## 2. Rendre les balises dans le head {#_2-render-the-head}

```blade
<!DOCTYPE html>
<html>
<head>
    @seo($post)
</head>
```

`@seo($post)` produit le titre, la description, le canonical, les directives robots, les balises Open Graph et Twitter Card ainsi que le JSON-LD associé aux données résolues. Sans valeurs explicites, le résolveur utilise les attributs du modèle et vos valeurs par défaut ; voir [l'ordre de priorité](/fr/concepts/resolver-precedence).

## 3. Enregistrer des valeurs explicites {#_3-set-explicit-values}

```php
$post->saveSEO([
    'title' => 'Custom SEO Title',
    'description' => 'Custom meta description',
]);
```

Les valeurs explicites ont priorité sur toutes les valeurs de repli. Pour une version traduite, passez la langue : `$post->saveSEO(['title' => 'Titre'], 'fr')`.

::: tip Remplissage de la base avec des seeders
Le `DatabaseSeeder` fourni par Laravel utilise `WithoutModelEvents`, qui désactive aussi le hook de création automatique de `HasSEO`. Retirez ce trait ou appelez `saveSEO()` explicitement dans vos seeders.
:::

## 4. Vérifier le résultat {#_4-verify-the-result}

Ouvrez la page publique de l'article et choisissez **Afficher le code source de la page**. Dans `<head>`, vérifiez que le titre contient `Custom SEO Title`, que la description vaut `Custom meta description` et que le canonical correspond à l'URL publique de l'article. Le suffixe configuré peut suivre le titre.

N'utilisez `@seo($post)` qu'une fois par page. Remplacez les balises de titre et de métadonnées déjà présentes dans le layout pour éviter les doublons. En cas de valeur inattendue, le [guide d'explication (EN)](/fr/guide/explain) permet d'en retrouver l'origine.

## 5. Ajouter un sitemap, si nécessaire {#_5-add-a-sitemap-optional}

```php
// e.g. in AppServiceProvider::boot()
use App\Models\Post;
use Rankbeam\Seo\Facades\SEO;

SEO::sitemaps()->register('posts', Post::class);
```

```bash
composer require spatie/laravel-sitemap
php artisan seo:sitemap
```

`/sitemap.xml` sert maintenant l'index généré. Toutes les options figurent dans le [guide des sitemaps](/fr/guide/sitemaps).

## Pour aller plus loin {#where-to-go-next}

- [Priorité du résolveur](/fr/concepts/resolver-precedence) : choix des valeurs.
- [Blade (EN)](/fr/guide/blade) : les sept directives.
- [Inertia et JSON (EN)](/fr/guide/inertia-json) : rendu sans Blade.
- [Graphe de schémas (EN)](/fr/guide/schema) : JSON-LD lié.
- [Champs Filament](/fr/guide/filament) : interface d'administration.

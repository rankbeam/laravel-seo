---
description: "Passez d'un autre paquet SEO Laravel à HasSEO et saveSEO(), avec un importateur des données de ralphjsmit/laravel-seo."
---

# Migration depuis d'autres paquets SEO Laravel

Ce guide rapproche les API et modes de stockage courants des deux primitives de Rankbeam : le trait [`HasSEO`](/fr/guide/quickstart) et `saveSEO()`. Une commande importe les données du paquet qui les conserve par modèle. L'effort dépend de vos personnalisations.

::: tip Vous venez de WordPress ?
Le guide [Migration depuis WordPress (EN)](/guide/migrate-from-wordpress) décrit l'import CSV et la lecture de bases existantes pour Yoast et Rank Math.
:::

| Paquet de départ | Stockage | Méthode |
|---|---|---|
| [`ralphjsmit/laravel-seo`](#from-ralphjsmit-laravel-seo) | Table polymorphe `seo` | **`php artisan seo:import-from ralphjsmit`** puis remplacement du trait |
| [`artesaos/seotools`](#from-artesaos-seotools) | Aucun stockage en base, appels à l'exécution et configuration | Remplacer les appels par `saveSEO()` ou des getters calculés |
| [`spatie/*`](#from-spatie-packages) | Builders de schémas et sitemaps, sans table de métadonnées | Conserver les fonctions complémentaires et migrer les autres |

Seul **ralphjsmit** possède ici une table de données SEO à importer en masse. Pour les générateurs de balises à l'exécution, remplacez les appels par requête par des valeurs stockées dans `seo_meta` ou calculées.

## Depuis `ralphjsmit/laravel-seo` {#from-ralphjsmit-laravel-seo}

Ce paquet conserve une ligne polymorphe par modèle dans `seo`. Sa structure proche de `seo_meta` permet un import idempotent.

### 1. Installer Rankbeam à côté du paquet existant {#_1-install-rankbeam-alongside-it}

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan vendor:publish --tag=seo-migrations
php artisan migrate
```

Les deux paquets peuvent coexister pendant la migration : leurs tables (`seo` et `seo_meta`) et espaces de noms de traits diffèrent.

::: warning Une clé de configuration commune
Un ancien `config/seo.php` de ralphjsmit masque la configuration Rankbeam, car les deux utilisent la clé `seo`. Sauvegardez ce fichier, retirez-le, puis republiez celui de Rankbeam avec `php artisan vendor:publish --tag=seo-config`.
:::

### 2. Exécuter l'importateur {#_2-run-the-importer}

```bash
# Preview first — writes nothing
php artisan seo:import-from ralphjsmit --dry-run

# Then import for real
php artisan seo:import-from ralphjsmit
```

L'importateur lit `seo`, retrouve le modèle Eloquent de chaque ligne et écrit dans `seo_meta`.

| Option | Effet |
|---|---|
| `--dry-run` | Décrire l'import prévu sans rien écrire. |
| `--model="App\Models\Post"` | Limiter à une ou plusieurs classes ; répétable. |
| `--locale=fr` | Langue de destination ; celle de l'application par défaut. |
| `--table=legacy_seo` | Lire une table source renommée. |
| `--connection=legacy` | Lire depuis une autre connexion de base de données. |
| `--limit=100` | Importer au plus N lignes, pour une migration progressive. |
| `--overwrite` | Remplacer les valeurs non vides ; sans cette option, remplir seulement les champs vides. |
| `--json` | Produire un rapport lisible par machine. |
| `--force` | Supprimer la demande de confirmation pour un script ou la CI. |

Un nouvel appel met à jour les mêmes lignes sans doublons. Par défaut, les données déjà saisies dans Rankbeam sont conservées. `--overwrite` autorise leur remplacement par les valeurs importées.

### 3. Remplacer le trait des modèles {#_3-swap-the-trait-on-your-models}

Remplacez le trait ralphjsmit par celui de Rankbeam. Certains noms de méthodes changent ; le trait lit désormais `seo_meta`.

```php
// Before
use RalphJSmit\Laravel\SEO\Support\HasSEO;

// After
use Rankbeam\Seo\Traits\HasSEO;
```

Déplacez la logique de `getDynamicSEOData()` vers les getters `getSEOTitle()`, `getSEODescription()`, `getSEOImage()`, `getUrlForSEO()` et `getSEOAlternates()`. Le [démarrage rapide](/fr/guide/quickstart) les présente. Les valeurs explicites passent par `saveSEO()` :

```php
$post->saveSEO([
    'title' => 'A hand-written SEO title',
    'description' => 'A hand-written meta description.',
    'canonical' => 'https://example.com/posts/my-post',
    'robots' => 'noindex, nofollow',
    'og_image' => 'https://example.com/og/my-post.jpg',
]);
```

### Correspondance des champs {#field-mapping}

L'importateur associe les champs explicitement, sans copier de colonne absente du schéma Core 3.

| ralphjsmit `seo` | Rankbeam `seo_meta` | Remarques |
|---|---|---|
| `model_type` / `model_id` | `seoable_type` / `seoable_id` | Déduits à nouveau du modèle actuel, pas copiés tels quels. |
| `title` | `title` | Limité à 70 caractères, longueur de la colonne ; les troncatures sont signalées. |
| `description` | `description` | Limité à 160 caractères ; les troncatures sont signalées. |
| `canonical_url` | `canonical` | |
| `robots` | `robots` | Limité à 50 caractères. |
| `image` | `og_image` | `twitter:image` en hérite automatiquement via le résolveur. |
| `author` | Non importé | Core 3 n'a pas de colonne auteur dans `seo_meta`. L'auteur d'article relève du résolveur. Les lignes concernées sont comptées et signalées pour choisir un stockage ou une valeur calculée appropriée. |
| `id`, `created_at`, `updated_at` | Non importés | Champs structurels de la source. |

**Pourquoi recalculer le type polymorphe ?** Chaque ligne est résolue vers son modèle réel ; les clés `seoable` viennent de `getMorphClass()`. La relation respecte ainsi la [morph map actuelle](https://laravel.com/docs/eloquent-relationships#custom-polymorphic-types), même si l'ancien paquet utilisait une autre convention. Les modèles supprimés sont signalés comme ignorés et ne créent pas de lignes orphelines.

### Lire le rapport {#what-the-report-tells-you}

Sans `--json`, le résultat comprend un tableau et trois sections à examiner :

- **Truncated :** valeurs raccourcies pour tenir dans une colonne `seo_meta`.
- **Not imported :** colonnes renseignées, comme `author`, sans destination dans Core 3.
- **Skipped rows by reason :** lignes vides, modèles supprimés ou types impossibles à résoudre.

### Vérifier {#verify}

```bash
php artisan seo:audit            # confirm the imported metadata looks right
```

Après validation du résultat, vous pouvez retirer `ralphjsmit/laravel-seo` et supprimer son ancienne table `seo`.

## Depuis `artesaos/seotools` {#from-artesaos-seotools}

`artesaos/seotools` construit les balises **à l'exécution**. Les valeurs sont définies par requête avec `SEOMeta`, `OpenGraph`, `TwitterCard` et `JsonLd`, souvent dans un contrôleur, avec des défauts dans `config/seotools.php`. Aucune table par modèle n'est à importer : déplacez ces appels vers des valeurs enregistrées ou calculées.

| Appel artesaos/seotools | Équivalent Rankbeam |
|---|---|
| `SEOMeta::setTitle($t)` | `saveSEO(['title' => $t])` ou `getSEOTitle()` |
| `SEOMeta::setDescription($d)` | `saveSEO(['description' => $d])` ou `getSEODescription()` |
| `SEOMeta::setCanonical($u)` | `saveSEO(['canonical' => $u])` ou `getUrlForSEO()` |
| `SEOMeta::addKeyword(...)` | `saveSEO(['focus_keywords' => [...]])`, voir [audit](/fr/guide/audit) |
| `OpenGraph::setTitle / setDescription / addImage` | `saveSEO(['og_title' => …, 'og_description' => …, 'og_image' => …])` |
| `TwitterCard::setType / setTitle / setImage` | `saveSEO(['twitter_card' => …, 'twitter_title' => …, 'twitter_image' => …])` |
| `JsonLd::setType(...)` / `JsonLdMulti` | [Graphe JSON-LD (EN)](/guide/schema) |
| Défauts de `config/seotools.php` | `config/seo.php` et [priorité du résolveur](/fr/concepts/resolver-precedence) |
| `{!! SEO::generate() !!}` dans le layout | `@seo($model)`, voir [Blade (EN)](/guide/blade) |

Au lieu de définir les balises dans chaque contrôleur, stockez les métadonnées une fois par modèle dans `seo_meta`, puis laissez le résolveur les rendre. Les défauts du site passent dans la [configuration (EN)](/reference/configuration). Les pages statiques par route utilisent `@seoForRoute()`.

## Depuis les paquets Spatie {#from-spatie-packages}

Il n'existe pas de paquet de stockage de métadonnées `spatie/laravel-seo`. Les outils Spatie couramment associés au SEO sont complémentaires et peuvent être conservés ou remplacés séparément :

- **`spatie/schema-org` :** builder fluent JSON-LD. Le [graphe Rankbeam (EN)](/guide/schema) propose des builders typés pour `Article`, `FAQPage`, `Product`, `BreadcrumbList`, `LocalBusiness` et `Organization`, enregistrés dans `seo_meta.schema_jsonld` et rendus sans doublons. Transmettez le résultat `->toArray()` de vos objets existants à `saveSEO(['schema_jsonld' => $array])`, ou réécrivez-les avec les builders Rankbeam.
- **`spatie/laravel-sitemap` :** le [registre Rankbeam](/fr/guide/sitemaps) s'appuie dessus. Enregistrez vos modèles pour un sitemap combiné, ou gardez votre sitemap Spatie et désactivez la route Rankbeam.

Pour [`romanzipp/laravel-seo`](https://github.com/romanzipp/Laravel-SEO), autre générateur à l'exécution, suivez le même principe qu'avec artesaos : les appels `setTitle` et `addMeta` deviennent `saveSEO()` ou des getters calculés.

## Étendre l'importateur {#extending-the-importer}

`seo:import-from` utilise un registre d'implémentations de `Rankbeam\Seo\Importing\Contracts\Importer`. Une nouvelle source n'exige pas de modifier la commande. Les sources intégrées sont `ralphjsmit` et les [importateurs WordPress (EN)](/guide/migrate-from-wordpress) `wordpress-csv`, `yoast` et `rank-math`. Enregistrez les vôtres dans un fournisseur de services :

```php
use Rankbeam\Seo\Importing\ImporterRegistry;

$this->app->afterResolving(ImporterRegistry::class, function (ImporterRegistry $registry) {
    $registry->register('my-source', \App\Seo\MyImporter::class);
});
```

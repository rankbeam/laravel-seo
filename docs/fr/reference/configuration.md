---
description: Toutes les options de config/seo.php, regroupées par couche du résolveur, avec la valeur par défaut fournie pour chacune.
---

# Configuration {#configuration}

Publiez le fichier de configuration :

```bash
php artisan vendor:publish --tag=seo-config
```

Toutes les options ci-dessous se trouvent dans `config/seo.php`. Les valeurs affichées sont les valeurs par défaut.

## Valeurs par défaut du site : couche 1 {#site-wide-defaults-layer-1}

```php
'site_name'                       => env('APP_NAME', 'My Site'),
'title_suffix'                    => ' | ' . env('APP_NAME', 'My Site'),
'title_suffix_skip_when_contains' => [],   // brand tokens, e.g. ['Acme']
'default_og_image'                => env('SEO_DEFAULT_OG_IMAGE', '/images/og-default.jpg'),
'default_robots'                  => env('SEO_DEFAULT_ROBOTS', 'index,follow'),
'default_twitter_card'            => env('SEO_DEFAULT_TWITTER_CARD', 'summary_large_image'),
'twitter_site'                    => env('SEO_TWITTER_SITE'),     // @username, without @
'twitter_creator'                 => env('SEO_TWITTER_CREATOR'),
'favicon'                         => '/favicon.ico',
```

`title_suffix` est ajouté aux titres résolus, sauf si le titre se termine déjà par ce suffixe.

`title_suffix_skip_when_contains` permet d’éviter la répétition de la marque. Si le titre résolu contient déjà l’un de ces termes **comme mot entier**, le suffixe est omis. La comparaison ignore la casse et respecte les limites de mots : `Acmestic` ne correspond donc pas à `Acme`. La valeur par défaut `[]` conserve le comportement historique.

## Politique de rendu robots {#robots-rendering-policy}

```php
'robots' => [
    'emit_default' => env('SEO_EMIT_DEFAULT_ROBOTS', false),
],
```

Le `<head>` rendu omet `<meta name="robots">` si la directive résolue est égale à `default_robots`, défini plus haut. Un `index,follow` redondant ajoute du bruit ; son absence correspond précisément à index,follow pour un robot. Une directive **différente**, comme `noindex`, `nofollow` ou `max-snippet:-1`, est toujours émise telle quelle. Définissez `emit_default` à `true` pour toujours afficher la balise et rétablir le comportement antérieur à 3.1. La directive granulaire `@seoRobots` reste inchangée : cet appel explicite affiche toujours la balise. Consultez le [contrat de rendu](/fr/contributing/rendering-contract) pour les directives prises en charge et leur priorité.

## Protection contre l’indexation hors production {#indexing-guard-non-production-safety-net}

```php
'indexing_guard' => [
    'enabled' => env('SEO_INDEXING_GUARD', false),
    'allowed_environments' => ['production'],
    'send_header' => env('SEO_INDEXING_GUARD_HEADER', true),
],
```

Lorsque la protection est activée et que l’application s’exécute dans un environnement **absent** de `allowed_environments`, elle impose `noindex,nofollow` à chaque page, au-dessus de toute la chaîne de priorité, même d’une valeur enregistrée pour la page. Elle envoie l’en-tête `X-Robots-Tag` correspondant, émet un `robots.txt` interdisant toute exploration et fait afficher une bannière à `seo:audit`. Dans les environnements autorisés, `production` par défaut, elle n’agit pas.

Elle est **désactivée** par défaut : la sortie reste identique octet par octet tant que vous ne l’activez pas. Une ligne suffit dans les deux sens : `SEO_INDEXING_GUARD=true` pour l’activer, `SEO_INDEXING_GUARD=false` pour la désactiver. Modifiez la liste autorisée avec `SEO_INDEXING_GUARD_ALLOWED`, séparée par des virgules. Les jokers `Str::is()` comme `prod*` sont acceptés ; une liste vide protège tous les environnements.

`send_header`, activé par défaut dans la protection, envoie aussi `X-Robots-Tag: noindex,nofollow` sur chaque réponse passant par l’application. Les PDF, flux et images, sans `<meta robots>`, reçoivent ainsi la même instruction. Le middleware n’est enregistré que si la protection est activée. Cette option est vivement conseillée ; consultez le [guide complet de protection contre l’indexation](/fr/guide/indexing-guard).

## URL canoniques {#canonical-urls}

```php
'canonical' => [
    'query_whitelist' => [],   // e.g. ['page']
],
```

Une URL canonique **déduite** par le résolveur, depuis l’URL de requête ou `getUrlForSEO()` du modèle, perd ses paramètres de requête par défaut : suivi, filtres et tri créent plusieurs cibles canoniques pour une même page. Les clés de `query_whitelist` sont **conservées**, dans l’ordre indiqué, dans les URL canoniques déduites ; tous les autres paramètres sont retirés. Le cas habituel est `page` pour des archives paginées : `/blog?page=2` représente bien une page différente de `/blog`.

Une URL canonique **définie explicitement**, saisie dans l’administration ou issue d’une couche plus prioritaire, est toujours émise telle quelle, paramètres compris. La liste autorisée ne concerne que la valeur de repli déduite. La valeur par défaut `[]` conserve la suppression de tous les paramètres.

## Activation des fonctionnalités {#feature-toggles}

```php
'features' => [
    'auto_create_meta' => env('SEO_AUTO_CREATE_META', true),
    'sitemap'          => env('SEO_SITEMAP_ENABLED', true),
    'schema'           => env('SEO_SCHEMA_ENABLED', true),
    'multilingual'     => env('SEO_MULTILINGUAL_ENABLED', false),
],
```

`auto_create_meta` crée une ligne `seo_meta` vide à la création d’un modèle `HasSEO`. Les seeders utilisant `WithoutModelEvents` contournent ce mécanisme.

## Mots-clés principaux {#focus-keywords}

```php
'keywords' => [
    'enabled' => env('SEO_KEYWORDS_ENABLED', false),
],
```

Cette option **active le suivi des mots-clés principaux**. Tant qu’elle vaut `false`, sa valeur par défaut, une page sans mot-clé principal n’est signalée nulle part : ni [`seo:audit`](/fr/guide/audit) ni le scan Pro ne s’en plaignent. Une application qui n’adopte pas les mots-clés principaux ne reçoit donc pas de rappels pour une fonctionnalité inutilisée. Activez-la lorsque vous commencez à définir ces mots-clés, par exemple avec le [champ Filament dédié](/fr/guide/filament). L’audit gratuit, le scan Pro et l’éditeur Pro signalent alors `missing_focus_keyword` sur les pages qui n’en ont pas encore. Ils lisent le même indicateur et restent donc cohérents.

## Audit gratuit : `seo:audit` {#free-audit-seo-audit}

```php
'audit' => [
    // \App\Models\Post::class, \App\Models\Page::class
    'models' => [],
],
```

Cette liste définit les modèles contrôlés par la commande gratuite [`seo:audit`](/fr/guide/audit) en l’absence de `--model`. Tous doivent utiliser le trait `HasSEO`. Si elle est vide, la commande utilise les modèles enregistrés sous `sitemap.models`.

## Valeurs de repli calculées : couche 5 {#computed-fallbacks-layer-5}

```php
'computed' => [
    // Ordered attribute candidates for the description fallback.
    // Empty = built-in chain: excerpt, summary, description, intro,
    // lead, teaser, content, body, text, article.
    'description_fields' => [],

    // Truncation length — word boundary, no ellipsis.
    'description_max_length' => 160,

    // Social / Open Graph image selection.
    'image_selection' => [
        // 'first' (default) — first non-empty source wins, nothing measured.
        // 'best' — score local candidates by closeness to the ideal below,
        //          skipping any under the minimum.
        'strategy' => env('SEO_IMAGE_SELECTION', 'first'),
        'minimum_width' => 200,
        'minimum_height' => 200,
        'ideal_width' => 1200,
        'ideal_height' => 630,
    ],
],
```

Avec la stratégie facultative `best`, le constructeur évalue une liste ordonnée d’images candidates selon la proximité de leurs dimensions en pixels avec la taille idéale et **ignore celles qui sont sous le minimum**. L’ordre est : `getSEOImage()`, qui reste prioritaire, puis le hook `getSEOImages()` du modèle, les champs d’image courants, la première image du contenu et la valeur par défaut configurée. Seules les images **locales** sont mesurées : chemin relatif sous `public/`, disque public ou URL absolue sur votre propre hôte. Une URL distante n’est jamais récupérée et sert uniquement de repli. Si aucune image locale ne respecte le minimum, la sélection revient à la première correspondance : `best` ne renvoie donc jamais moins que `first`. Exposez les candidates depuis votre modèle :

```php
use Rankbeam\Seo\Data\SEOImageCandidate;

public function getSEOImages(): iterable
{
    return [
        SEOImageCandidate::make($this->hero_url)->priority(100),
        SEOImageCandidate::make($this->thumbnail_url)->priority(10),
    ];
}
```

## Sitemaps {#sitemaps}

```php
'sitemap' => [
    'disk'                 => env('SEO_SITEMAP_DISK', 'public'),
    'path'                 => 'sitemap.xml',
    'max_urls_per_sitemap' => 50000,

    // ModelClass::class => ['priority' => 0.8, 'changefreq' => 'weekly']
    'models' => [],

    // [['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily']]
    'static_urls' => [],

    'ping_search_engines' => env('SEO_SITEMAP_PING', false),
],
```

Consultez le [guide du registre de sitemaps](/fr/guide/sitemaps) pour les sources définies par code.

## Schéma JSON-LD {#schema-json-ld}

```php
'schema' => [
    'organization' => [
        'name' => env('APP_NAME'),
        'url'  => env('APP_URL'),
        'logo' => env('SEO_ORGANIZATION_LOGO'),
        // 'sameAs' => [],   // social profile URLs
    ],
    'publisher' => [
        'name' => env('APP_NAME'),
        'logo' => env('SEO_PUBLISHER_LOGO'),
    ],
    'website' => [
        'name' => env('APP_NAME'),
        'url'  => env('APP_URL'),
        // 'potentialAction' => [],   // SearchAction for sitelinks search box
    ],
],
```

Ces valeurs alimentent les nœuds du [graphe de schéma](/fr/guide/schema).

## Routes {#routes}

```php
'routes' => [
    'enabled'        => env('SEO_ROUTES_ENABLED', true),
    'prefix'         => '',
    'middleware'     => ['web'],
    'api_prefix'     => 'api/seo',
    'api_middleware' => ['api'],
],
```

Définissez `enabled => false` si votre application sert son propre `/sitemap.xml` statique.

## Cache {#cache}

```php
'cache' => [
    'prefix' => 'seo_',
    'store'  => env('SEO_CACHE_STORE'),   // null = app default

    // Resolver result cache — the scale lever for hot frontends. OFF by default.
    'resolver' => [
        'enabled' => env('SEO_RESOLVER_CACHE', false),
        'ttl'     => env('SEO_RESOLVER_CACHE_TTL', 3600),
    ],
],
```

### Cache des résultats du résolveur {#resolver-result-cache}

`SEOResolver` exécute la chaîne de priorité complète à **chaque** rendu front-end : configuration → valeurs globales / par type de modèle / par route → valeurs calculées du modèle → `seo_meta` explicite → suffixe du titre / URL canonique / schéma. Sur un site à fort trafic, environ 20 000 requêtes par jour pour l’application de référence, cela représente plusieurs lectures de base de données par page.

Activez `cache.resolver.enabled` pour mettre en cache les données SEO entièrement résolues d’un modèle. Une **entrée trouvée dans le cache évite toute la chaîne de priorité**. Dans le benchmark du package, une lecture en cache chaud exécute **zéro** requête en base, alors que chaque résolution sans cache relit le `seo_meta` du modèle. La charge utile est un tableau simple, reconstitué avec `SEOData::fromArray()`, jamais un objet : Laravel 13 utilise `cache.serializable_classes = false`, qui restitue un objet mis en cache sous forme de `__PHP_Incomplete_Class`.

Le cache utilise le `store` configuré plus haut. En production, choisissez donc un **cache partagé et persistant**, `redis` ou `memcached` : chaque worker web ou de file d’attente doit voir le cache et ses invalidations. Laissez cette option désactivée tant que ce cache n’est pas disponible.

**L’invalidation est automatique et cohérente** : la résolution avec cache reste identique à celle sans cache. Les entrées sont identifiées par `(classe du modèle, id, locale, route, URL de requête)` et supprimées lorsque :

- la ligne `seo_meta` de la page est **enregistrée ou supprimée**, quel que soit le chemin : `saveSEO()`, Filament ou écriture directe de `SEOMeta` ;
- un **champ de contenu** du modèle change parmi les colonnes de `getSEOContentFields()`. La liste par défaut couvre tous les champs de repli calculés intégrés : titre/headline, excerpt/summary/content/body/text/article et champs d’image courants comme `featured_image`, `thumbnail`, `cover_image`, `og_image`, `photo`, `banner` et `hero_image`. Redéfinissez-la si votre modèle calcule le SEO depuis d’autres colonnes ;
- **une ligne `seo_defaults` quelconque** change : une valeur par défaut peut alimenter n’importe quel modèle, ce qui vide tout le cache de résolution.

Sur un stockage **compatible avec les tags** (`redis`, `memcached`, `array`), les entrées d’un modèle sont supprimées via les **tags** du cache. Sur un stockage **sans tags** (`file`, `database`), le package utilise un **numéro de version par modèle**. Les deux méthodes fonctionnent sans parcourir les clés.

::: tip
Seules les résolutions associées à un modèle sont mises en cache. `SEO::render()`/`@seo()` pour un `SEOData` construit manuellement et `@seoForRoute()` pour une route sans modèle continuent de résoudre les données à chaque appel.
:::

::: warning
Le cache conserve les valeurs `updated_at` / `modified_time` calculées du modèle au dernier changement de **champ de contenu**, ou jusqu’à l’expiration du TTL. Un simple `touch()` qui ne modifie que `updated_at`, sans changer de colonne `getSEOContentFields()`, ne force pas une nouvelle résolution : `article:modified_time` peut donc avoir un retard allant jusqu’au TTL. Ajoutez les colonnes spécifiques à votre application dont dépend le calcul à `getSEOContentFields()` pour déclencher l’invalidation immédiatement.
:::

---
description: "Signalez aux moteurs de recherche la publication ou la modification d’une URL. Pro utilise api.indexnow.org, qui transmet aux moteurs participants. Désactivé par défaut."
---

# IndexNow : signaler les URL à la publication {#indexnow-—-push-on-publish-indexing}

Au lieu d’attendre qu’un robot découvre une page modifiée, **IndexNow** permet de *signaler* sa publication ou sa mise à jour aux moteurs de recherche. Pro envoie la notification au point d’accès commun `api.indexnow.org`, qui la **transmet à tous les moteurs participants** en un seul appel, sans requête distincte par moteur. La [FAQ officielle](https://www.indexnow.org/faq) mentionne Amazon, Bing, Naver, Seznam, Yandex et Yep. Une notification ne garantit pas l’indexation.

La fonctionnalité est **désactivée par défaut**. Aucun appel réseau n’a lieu tant que vous ne l’avez pas activée et qu’aucune URL n’est soumise.

## Installation {#setup}

### 1. Générer une clé {#_1-generate-a-key}

IndexNow utilise une **clé** pour vérifier le contrôle de l’hôte. Pro accepte 8 à 128 caractères parmi `[a-f0-9-]` ; une chaîne hexadécimale de 32 caractères convient. Générez-la une fois, conservez-la stable et exposez-la via l’environnement :

```dotenv
SEO_PRO_INDEXNOW_ENABLED=true
SEO_PRO_INDEXNOW_KEY=0123456789abcdef0123456789abcdef
```

::: tip La clé passe par la configuration et reste disponible avec `config:cache`
Contrairement aux identifiants Search Console, la clé IndexNow **n’est pas secrète** : elle est servie publiquement à `/{key}.txt` pour prouver votre contrôle de l’hôte. Pro la lit donc via `indexnow.key`, qui utilise `env('SEO_PRO_INDEXNOW_KEY')` par défaut. Ce choix est volontaire : après `config:cache`, les valeurs définies **uniquement dans `.env`** ne sont plus accessibles via `env()`, car Laravel ne charge plus ce fichier. Les véritables variables d’environnement du processus restent accessibles. Lue par la configuration, la clé est enregistrée dans le cache de configuration et reste disponible. En contrepartie, **une rotation de clé nécessite de relancer `php artisan config:cache`**. La clé n’est jamais journalisée. Consultez [Serveurs avec configuration en cache](#config-cached-servers) si son fichier renvoie une 404 en production.
:::

### 2. Servir le fichier de clé {#_2-serve-the-key-file}

IndexNow récupère `https://{host}/{key}.txt`, contenant seulement la clé, pour vérifier le contrôle de l’hôte. Avec l’option `route` activée, son défaut, **Pro le sert pour vous** :

```
GET https://example.com/0123456789abcdef0123456789abcdef.txt  →  the key, text/plain
```

Seul le chemin de la clé configurée répond. Les autres chemins interceptés par cette route renvoient une 404 ; toute la route renvoie une 404 lorsque IndexNow est désactivé. Pour héberger vous-même le fichier, éventuellement sur un CDN, désactivez `route` et définissez votre URL dans `key_location`.

## Soumettre des URL {#submitting-urls}

### Automatiquement à l’enregistrement {#automatically-on-save-the-push-on-publish-path}

Ajoutez le trait à un modèle et activez `auto_submit`. Chaque enregistrement met en file une soumission de son `getUrlForSEO()` :

```php
use Rankbeam\Seo\Pro\IndexNow\Concerns\SubmitsToIndexNow;

class Post extends Model
{
    use SubmitsToIndexNow;
}
```

```dotenv
SEO_PRO_INDEXNOW_AUTO_SUBMIT=true
```

Le trait respecte une condition de publication. Implémentez `shouldSubmitToIndexNow(): bool` pour tout contrôler ; sinon il utilise l’attribut `is_published` s’il existe, et à défaut soumet à chaque enregistrement. La soumission passe toujours par une **file d’attente** : enregistrer un modèle n’attend jamais le réseau.

### Manuellement {#manually}

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::indexNow()->submit('https://example.com/blog/my-post');   // one URL
SeoPro::indexNow()->submit([$urlA, $urlB]);                        // many (batched)
SeoPro::indexNow()->submitModel($post);                            // a model's URL
SeoPro::indexNow()->submitSitemap();                               // every sitemap URL
```

`submit()` utilise une file par défaut ; passez `queue: false` pour une exécution directe.

### En ligne de commande {#from-the-command-line}

```bash
php artisan seo-pro:indexnow https://example.com/a https://example.com/b
php artisan seo-pro:indexnow --sitemap     # submit every URL in the sitemap registry
php artisan seo-pro:indexnow --sitemap --sync   # run inline instead of queuing
```

::: warning Même hôte uniquement
Chaque URL doit utiliser `http(s)` **et** appartenir au `host` configuré. Toute autre URL est **écartée**, comptée mais jamais envoyée : vous ne pouvez soumettre que des URL sous votre contrôle, et le point d’accès refuserait de toute façon une divergence d’hôte. Les listes dépassant `max_urls_per_request`, 10 000 selon la limite du protocole, sont découpées automatiquement.
:::

## Configuration {#configuration}

```php
// config/seo-pro.php → 'indexnow'
'indexnow' => [
    'enabled' => env('SEO_PRO_INDEXNOW_ENABLED', false),
    'key' => env('SEO_PRO_INDEXNOW_KEY'),   // the key itself, captured by config:cache
    'key_env' => 'SEO_PRO_INDEXNOW_KEY',   // fallback env-var NAME (real OS env var) when 'key' is empty
    'key_location' => env('SEO_PRO_INDEXNOW_KEY_LOCATION'),  // null = the served /{key}.txt
    'endpoint' => env('SEO_PRO_INDEXNOW_ENDPOINT', 'https://api.indexnow.org/indexnow'),
    'host' => env('SEO_PRO_INDEXNOW_HOST'),  // null = derived from app.url
    'route' => env('SEO_PRO_INDEXNOW_ROUTE', true),         // serve /{key}.txt
    'auto_submit' => env('SEO_PRO_INDEXNOW_AUTO_SUBMIT', false),
    'max_urls_per_request' => 10000,
    'timeout' => 10,
    'queue' => [
        'connection' => env('SEO_PRO_INDEXNOW_QUEUE_CONNECTION'),
        'name' => env('SEO_PRO_INDEXNOW_QUEUE'),
    ],
    'tries' => 3,
    'backoff' => 30,
],
```

## Nouvelles tentatives {#how-retries-work}

Le job en file `SubmitToIndexNowJob` relance les erreurs susceptibles d’être temporaires : `429`, limitation de débit, `5xx` et dépassements de délai sont réessayés selon `backoff`, dans la limite de `tries`. Une erreur `400`/`403`/`422`, erreur client permanente comme une mauvaise clé ou un hôte différent, est journalisée puis **arrête** le traitement. Les statuts `200` et `202`, réception confirmée ou vérification de clé en attente, sont tous deux considérés comme des succès.

En production, attribuez une **file dédiée** au job afin qu’un point d’accès lent ne retarde pas les tâches destinées aux utilisateurs :

```php
Schedule::command('seo-pro:indexnow --sitemap')->daily();
```

## Résoudre les problèmes {#troubleshooting}

### Serveurs avec configuration en cache {#config-cached-servers}

Si `/{key}.txt` renvoie une 404 en production, ou si les soumissions restent sans effet alors que `indexnow.enabled` vaut bien `true`, la cause est souvent une clé définie **uniquement dans `.env`** et non capturée par la configuration sur un serveur utilisant `php artisan config:cache`. Laravel ne lit plus `.env` une fois la configuration en cache : `env('SEO_PRO_INDEXNOW_KEY')` renvoie alors `null`, la route de clé ne s’enregistre pas et chaque soumission est rejetée comme « non configurée ».

La configuration par défaut lit `indexnow.key` via `env(...)` : une installation normale capture donc la valeur à la création du cache. Le problème apparaît si vous avez **publié la configuration puis retiré la valeur par défaut `env(...)`**, ou choisi un **nom `key_env` personnalisé défini uniquement dans `.env`**. Deux solutions :

1. **Conserver la clé dans la configuration**, solution conseillée : laissez `indexnow.key` à `env('SEO_PRO_INDEXNOW_KEY')`, ou définissez une valeur littérale, puis relancez `php artisan config:cache`. Une rotation ultérieure nécessite de recréer le cache.
2. **Injecter une vraie variable d’environnement** : définissez `SEO_PRO_INDEXNOW_KEY` dans l’environnement du système ou du processus, avec `env[...]` du pool PHP-FPM, `Environment=` de systemd ou les réglages de votre plateforme, **pas seulement dans `.env`**. Les variables du système restent lisibles avec une configuration en cache.

Lancez `php artisan seo:doctor` pour confirmer. Il signale **« IndexNow is enabled but no valid key resolves »** avec la correction précise lorsqu’il détecte cet état. Pro journalise aussi un avertissement une fois par processus si l’application démarre avec une configuration en cache et une clé illisible.

::: tip Google
Google ne participe **pas** à IndexNow. Pour Google, utilisez l’intégration [Search Console](/fr/pro/search-console) et un sitemap à jour.
:::

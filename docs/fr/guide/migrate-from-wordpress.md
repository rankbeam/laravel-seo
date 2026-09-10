---
description: "Transférez les données SEO saisies dans Yoast ou Rank Math vers vos modèles Laravel : titres, descriptions, URL canoniques, directives robots et mots-clés principaux. Référence de correspondance des champs de l’importateur."
---

# Migrer depuis WordPress {#migrating-from-wordpress}

Vous quittez WordPress pour votre site de contenu ? Rankbeam peut transférer vers vos modèles Laravel les métadonnées SEO que votre équipe a rédigées dans Yoast ou Rank Math : titres, descriptions, URL canoniques, directives robots, mots-clés principaux et personnalisations sociales. Vous conservez ainsi ce travail d’optimisation lors de la migration.

::: tip Vous préparez une bascule réelle ?
Cette page est la *référence* de l’importateur : correspondance des champs, variables de template et clés sources. Pour la **procédure** pas à pas limitant les risques (coexistence, import, vérification, puis retrait de WordPress), suivez le [guide opérationnel de migration WordPress](/fr/guide/wordpress-migration-runbook).
:::

Deux méthodes utilisent la même commande `seo:import-from` :

| Méthode | Source | Usage conseillé |
|---|---|---|
| [**CSV**](#_1-csv-import) `wordpress-csv` | Un tableau exporté depuis WordPress | La plupart des migrations d’agence ; vous maîtrisez les URL exactes |
| [**Base de données**](#_2-database-import-yoast-rank-math) `yoast` / `rank-math` | La base de données WordPress | Fidélité complète, avec personnalisations OpenGraph/Twitter et redirections Rank Math |

Les deux méthodes sont **idempotentes** : les relancer met à jour les mêmes lignes sans créer de doublons. Elles prennent en charge **`--dry-run`** et, par défaut, ne font que *remplir* les champs vides, sans écraser les données SEO déjà définies dans Rankbeam. Utilisez **`--overwrite`** pour remplacer les valeurs existantes par les valeurs importées.

## Comment les lignes WordPress deviennent des lignes `seo_meta` {#how-wordpress-rows-become-seo-meta-rows}

Les données WordPress ne suivent pas le modèle polymorphe de Laravel : une ligne WordPress est identifiée par une **URL** ou un **ID de publication**, tandis que chaque ligne polymorphe de `seo_meta` dans Rankbeam est rattachée à un véritable modèle Eloquent. L’importateur fait donc correspondre chaque ligne WordPress à l’un de vos modèles et distingue clairement, dans son rapport, les lignes rattachées des lignes reposant uniquement sur une URL :

- **Rattachée à un modèle.** Indiquez le modèle cible avec `--model="App\Models\Post"`. Le **slug** de chaque ligne (dernier segment du chemin de l’URL ou `post_name` WordPress) est recherché dans ce modèle, sur sa clé de route par défaut ou sur une colonne choisie avec `--match-by=`. Les lignes correspondantes sont enregistrées dans `seo_meta`.
- **URL seule.** Une ligne sans modèle correspondant, ou un import sans `--model`, ne peut pas devenir une ligne `seo_meta` : aucun modèle ne permet de la rattacher. Elle est signalée comme ignorée avec le motif `url-only`. Son URL canonique peut néanmoins produire une [proposition de redirection](#redirects).

Les articles et pages WordPress correspondent généralement à des modèles Laravel *différents*. Exécutez donc l’importateur une fois par type de contenu, en délimitant les lignes :

```bash
php artisan seo:import-from yoast --model="App\Models\Post" --post-type=post
php artisan seo:import-from yoast --model="App\Models\Page" --post-type=page
```

::: warning Les types de publication personnalisés ne sont pas parcourus par défaut
Les lecteurs de base de données parcourent uniquement les types **`post`** et **`page`**. Pour un site fondé sur des types personnalisés (`product`, `event`, `pathology` d’un thème, etc.), indiquez explicitement chaque type en répétant `--post-type=` :

```bash
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Pathology" \
  --post-type=pathology --post-type=clinic
```
:::

---

## 1. Import CSV {#_1-csv-import}

Le CSV couvre la plupart des migrations d’agence. Exportez une ligne par URL avec cet en-tête. L’ordre des colonnes est libre ; les colonnes inconnues sont ignorées et signalées :

```csv
url,title,description,canonical,robots,focus_keyword
https://oldsite.com/blog/my-post/,"My SEO Title","My meta description.",https://newsite.com/blog/my-post,"index, follow","laravel seo"
```

Lancez l’import :

```bash
# Preview first — writes nothing
php artisan seo:import-from wordpress-csv \
  --file=storage/migrations/seo-export.csv \
  --model="App\Models\Post" \
  --dry-run

# Then import for real
php artisan seo:import-from wordpress-csv \
  --file=storage/migrations/seo-export.csv \
  --model="App\Models\Post"
```

| Colonne | Destination dans `seo_meta` | Remarques |
|---|---|---|
| `url` | *(clé de correspondance)* | Le slug, dernier segment du chemin, est recherché dans le modèle. Obligatoire. |
| `title` | `title` | Limité à 70 caractères ; les valeurs trop longues sont signalées. |
| `description` | `description` | Limitée à 160 caractères. |
| `canonical` | `canonical` | Sert aussi aux [propositions de redirection](#redirects). |
| `robots` | `robots` | Conservé tel quel, par exemple `noindex, nofollow` ; limité à 50 caractères. |
| `focus_keyword` | `focus_keywords` | Valeurs séparées par des virgules ; le premier mot-clé est le principal. |

Les lignes mal formées sont ignorées et comptées : absence de `url` ou nombre de colonnes différent de celui de l’en-tête.

---

## 2. Import depuis la base de données : Yoast / Rank Math {#_2-database-import-yoast-rank-math}

Si vous disposez encore de la base WordPress, l’importateur peut lire directement les métadonnées SEO, y compris les personnalisations OpenGraph/Twitter et, pour Rank Math, les redirections généralement absentes d’un export CSV.

### Configurer une connexion vers WordPress {#point-a-connection-at-wordpress}

Ajoutez une connexion à la base WordPress dans `config/database.php` :

```php
'connections' => [
    // ...
    'wordpress' => [
        'driver' => 'mysql',
        'host' => env('WP_DB_HOST', '127.0.0.1'),
        'database' => env('WP_DB_DATABASE', 'wordpress'),
        'username' => env('WP_DB_USERNAME'),
        'password' => env('WP_DB_PASSWORD'),
        'prefix' => '', // the table prefix is passed with --table=, see below
    ],
],
```

Lancez ensuite l’import. Le préfixe des tables est `wp_` par défaut ; modifiez-le avec `--table=` :

```bash
# Yoast SEO
php artisan seo:import-from yoast \
  --connection=wordpress --model="App\Models\Post" --dry-run

# Rank Math
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Post" --table=wp_
```

Le lecteur parcourt `{prefix}posts` (articles/pages publiés), récupère les métadonnées du plugin pour chaque publication dans `{prefix}postmeta` et fait correspondre son slug `post_name` à votre modèle.

::: tip Préfixe de tables personnalisé
Les hébergeurs WordPress gérés utilisent souvent un préfixe aléatoire, par exemple `wppg_` au lieu de `wp_`. Vérifiez les noms `CREATE TABLE` dans votre dump et passez le vrai préfixe, `--table=wppg_`, pour que le lecteur trouve `{prefix}posts` et `{prefix}postmeta`.
:::

::: tip Lire un dump restauré sous MySQL 8
Si vous restaurez un dump WordPress sous MySQL 8+ pour le lire localement, assouplissez le mode SQL strict avant de charger le fichier `.sql`. Les valeurs datetime par défaut `'0000-00-00'` de WordPress sont rejetées par les modes `STRICT`/`NO_ZERO_DATE` activés par défaut dans MySQL 8. L’import du dump échoue alors avec `Invalid default value for 'post_date'`, avant même l’exécution de l’import SEO :

```sql
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';
```
:::

### Correspondance des champs {#field-mapping}

Les deux importateurs font correspondre les champs **explicitement** : une clé sans colonne dans Core 3 est signalée comme *sans correspondance*, jamais inventée.

| Clé meta Yoast | Clé meta Rank Math | `seo_meta` |
|---|---|---|
| `_yoast_wpseo_title` | `rank_math_title` | `title` |
| `_yoast_wpseo_metadesc` | `rank_math_description` | `description` |
| `_yoast_wpseo_canonical` | `rank_math_canonical_url` | `canonical` |
| `_yoast_wpseo_meta-robots-*` | `rank_math_robots` | `robots` |
| `_yoast_wpseo_focuskw` | `rank_math_focus_keyword` | `focus_keywords` |
| `_yoast_wpseo_opengraph-title` | `rank_math_facebook_title` | `og_title` |
| `_yoast_wpseo_opengraph-description` | `rank_math_facebook_description` | `og_description` |
| `_yoast_wpseo_opengraph-image` | `rank_math_facebook_image` | `og_image` |
| `_yoast_wpseo_twitter-title` | `rank_math_twitter_title` | `twitter_title` |
| `_yoast_wpseo_twitter-description` | `rank_math_twitter_description` | `twitter_description` |
| `_yoast_wpseo_twitter-image` | `rank_math_twitter_image` | `twitter_image` |
| — | `rank_math_twitter_card_type` | `twitter_card` |

**Robots.** Seules les différences avec les valeurs WordPress par défaut sont enregistrées. Une page indexable ordinaire conserve donc `robots` à null et hérite de la valeur par défaut de votre site. Les indicateurs Yoast séparés `noindex`, `nofollow` et avancés (`noarchive`, `nosnippet`, `noimageindex`) sont réunis dans une chaîne. Le tableau `robots` sérialisé de Rank Math est lu de la même manière, en retirant les valeurs par défaut `index` et `follow`.

**Clés sans correspondance**, signalées mais jamais copiées : identifiants d’images jointes (`*-image-id`), scores de mots-clés/SEO (`linkdex`, `content_score`, `rank_math_seo_score`), choix de catégorie principale et marqueurs de schéma rich-snippet de Rank Math. Le [graphe de schéma](/fr/guide/schema) remplace ces derniers par une représentation typée plus riche.

::: warning Les URL canoniques sont importées telles quelles
Une URL canonique explicite (`rank_math_canonical_url` / `_yoast_wpseo_canonical`) est copiée **exactement comme elle est enregistrée**. Si une page utilise une URL absolue sur l’*ancien* domaine, situation fréquente sur les hébergements gérés ou de préproduction, par exemple `https://oldsite-staging.example.com/page/`, l’URL importée pointe toujours vers ce domaine. L’importateur ne réécrit jamais l’hôte. `--site-url` déduit les *chemins* de requête à partir d’URL absolues pour les [propositions de redirection](#redirects) et la correspondance des lignes CSV, mais ne réécrit **pas** les URL canoniques enregistrées. Après un changement de domaine, contrôlez les URL canoniques importées et corrigez l’hôte, ou effacez-les pour revenir à l’URL canonique autoréférente du résolveur. La plupart des pages n’ont pas de canonique explicite et ne sont pas concernées : Yoast et Rank Math la calculent au rendu.
:::

### Variables de template {#template-tokens}

Yoast et Rank Math stockent titres et descriptions sous forme de **templates** avec des variables : `%%title%%` chez Yoast, `%title%` chez Rank Math. L’importateur **résout les variables qu’il peut déduire** et **supprime les autres**, pour ne jamais enregistrer une chaîne brute `%%token%%` :

| Variable | Valeur résolue |
|---|---|
| `%%title%%` / `%title%` | Le titre de la publication WordPress |
| `%%sitename%%` / `%sitename%` | Le nom du blog dans `wp_options`, pour l’import depuis la base |
| `%%sep%%` / `%sep%` | `-` |
| `%%page%%`, `%%primary_category%%`, … | *Supprimée* : laissée vide, avec nettoyage des séparateurs voisins |

Si des variables ont été résolues, le rapport le précise. **Relisez les titres importés** pour vérifier leur formulation et corrigez ceux qui dépendaient de variables impossibles à déduire.

---

## Redirections {#redirects}

`seo_redirects` est une fonctionnalité de [Rankbeam **Pro**](/fr/pro/installation) : l’importateur du cœur n’écrit donc jamais directement dans cette table. Passez plutôt `--redirects-csv=` : l’importateur **produit un CSV** contenant les mêmes colonnes que la table de redirections Pro, `source_path,target_url,status_code,note`, à importer dans Pro.

```bash
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Post" \
  --redirects-csv=storage/migrations/redirects.csv
```

Les propositions de redirection proviennent des sources suivantes :

- **Import CSV** : une ligne dont `canonical` pointe vers un **chemin différent** de son `url` produit une `301` de l’ancien chemin vers la canonique. Une canonique autoréférente, de même chemin, n’est *pas* émise, car elle créerait une boucle.
- **Base Rank Math** : règles actives dans `{prefix}rank_math_redirections`. Seules les règles de **correspondance exacte** sont émises. Les règles regex/contains/start/end sont signalées comme ignorées, car elles ne correspondent pas à un chemin unique.
- **Yoast gratuit** n’a pas de table de redirections. Seul Yoast Premium en dispose, et son schéma ne fait pas partie du package gratuit. Utilisez le CSV pour les redirections Yoast.

Ces propositions sont **indicatives**. Contrôlez le CSV, puis importez-le dans Pro avec [`seo-pro:redirects-import`](/fr/guide/wordpress-migration-runbook#step-3-—-import-the-redirects-into-pro), qui valide chaque ligne et rejette les boucles, cibles dangereuses et doublons. La structure du CSV est un contrat stable, le **format CSV de redirections v1** : `source_path,target_url,status_code,note`.

---

## Ce que vous indique le rapport {#what-the-report-tells-you}

Sans `--json`, la commande affiche un tableau de résultats (created / updated / unchanged / skipped / scanned), un **rapport de vérification** et des sections à examiner :

- **Rapport de vérification** : la répartition à valider entre **matched**, lignes rattachées à un modèle, et **url-only**, sans modèle correspondant, ainsi que les totaux de valeurs tronquées et sans correspondance.
- **Truncated** : valeurs raccourcies pour tenir dans une colonne `seo_meta`.
- **Not imported** : clés sources contenant des données sans destination dans Core 3, **y compris chaque valeur `author` distincte**. L’auteur n’est pas une colonne enregistrée : il relève de [`getSEOAuthor()`](/fr/concepts/resolver-precedence). Le rapport indique donc les valeurs à replacer plutôt que de les faire disparaître silencieusement.
- **Redirect candidates** : nombre de propositions écrites et fichier de destination.
- **Skipped rows by reason** : lignes avec URL seule, publications sans métadonnées SEO et règles de redirection non exactes.
- **Warnings** : par exemple, résolution de variables de template.

Ajoutez `--json` pour obtenir une version exploitable par programme de toutes ces informations. Le bloc `verification` contient les nombres matched/url-only et chaque valeur d’auteur.

### Vérifier {#verify}

```bash
php artisan seo:audit --model="App\Models\Post" --strict   # CI/cutover gate
```

`--strict` renvoie un code de sortie non nul si une page présente un problème. Consultez l’[audit SEO gratuit](/fr/guide/audit). Pour toute la procédure de bascule dans l’ordre (coexistence → import → vérification → retrait), suivez le [guide opérationnel de migration WordPress](/fr/guide/wordpress-migration-runbook).

---

Vous venez plutôt d’un package SEO **Laravel** (ralphjsmit, artesaos, Spatie) ? Consultez [Migrer depuis d’autres packages Laravel](/fr/guide/migrate-from-other-packages).

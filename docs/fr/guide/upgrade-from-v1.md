---
description: "Passez de fibonoir/laravel-seo v1 à rankbeam/laravel-seo v2 : changement de nom et Core recentré sur les métadonnées, le rendu, JSON-LD et les sitemaps."
---

# Mise à niveau depuis fibonoir/laravel-seo v1 {#upgrading-from-fibonoir-laravel-seo-v1}

La version 2.0.0 renomme le package en `rankbeam/laravel-seo` et recentre le Core sur la résolution des métadonnées, le rendu, JSON-LD et les sitemaps. L'analyseur, le scanner, les redirections, le moniteur 404 et l'administration passent dans des packages séparés.

## 1. Remplacer le package {#_1-swap-the-package}

```bash
composer remove fibonoir/laravel-seo
composer require rankbeam/laravel-seo
```

## 2. Mettre à jour les espaces de noms {#_2-update-namespaces}

Les noms de classes restent identiques. Seule la racine change : `Fibonoir\LaravelSEO\*` devient `Rankbeam\Seo\*`. Une recherche-remplacement dans le projet couvre ce changement. L'alias de façade `SEO` et les directives Blade `@seo` restent inchangés.

## 3. Supprimer les anciens fichiers publiés {#_3-delete-stale-published-files}

Avant toute suppression de fichiers ou de tables, sauvegardez la configuration publiée et exportez les données concernées. Vérifiez que vous pouvez les restaurer. Ce guide ne migre pas l'historique v1 des redirections, des erreurs 404 ou des scans vers le schéma différent de Pro. La compatibilité des tables Core décrite ci-dessous concerne uniquement `seo_meta` et `seo_defaults`.

::: warning Des conflits peuvent rester silencieux
Le `seo:install` de v1 publiait des fichiers dans l'application. Ceux-ci peuvent entrer en conflit avec v2 sans produire de message d'erreur.
:::

- **`config/seo.php`** : une ancienne version publiée par v1, ou par `ralphjsmit/laravel-seo` et conservée par l'installateur v1, masque la configuration du package. Elle peut rendre `site_name` nul et affecter tous les modèles de texte `{site_name}`. Supprimez-la après sauvegarde, puis republiez avec `php artisan vendor:publish --tag=seo-config`.
- **Les migrations v1** des tables que le Core ne gère plus : `seo_redirects`, `seo_404_logs`, `seo_scan_runs`, `seo_scan_issues`, `seo_analytics_cache` et `seo_internal_links_index`. Supprimez leurs fichiers de migration. Si les tables existent en production, sauvegardez leurs données puis supprimez-les **avant** d'installer `rankbeam/laravel-seo-pro`, qui les recrée avec un autre schéma.
- **Les anciens fichiers générés** sous `app/` et `resources/js` pour Filament 3, Livewire, Vue ou React : ils référencent des classes qui n'existent plus.

Les deux tables Core, `seo_meta` et `seo_defaults`, gardent un schéma compatible. Leurs données sont conservées pendant cette mise à niveau.

## 4. Fonctions retirées et nouveaux emplacements {#_4-removed-features-and-where-they-went}

| Fonction v1 | Emplacement actuel |
|---|---|
| Section SEO des formulaires Filament | [`rankbeam/laravel-seo-filament`](/fr/guide/filament), gratuit sous MIT |
| Analyseur de contenu à 32 règles | Cet ancien analyseur n'est pas repris par la migration. Le scanner de `rankbeam/laravel-seo-pro` détecte les problèmes de SEO technique ; le score numérique dérivé des problèmes appartient à Pro. |
| Scanner du site | `rankbeam/laravel-seo-pro`, pipeline en queue et tableau de bord |
| Gestionnaire de redirections | `rankbeam/laravel-seo-pro`, avec validation des regex et protections contre les redirections ouvertes |
| Moniteur 404 | `rankbeam/laravel-seo-pro`, sans stockage des IP par défaut |
| Analytics GA4 et liens internes | Backlog de `rankbeam/laravel-seo-pro` |
| Installateur `seo:install` | Supprimé : installation avec require, publication de la configuration et migrations |

## 5. Changements de comportement à vérifier {#_5-behavior-changes-to-review}

- **`og:image` et `twitter:image` sont toujours des URL absolues.** v1 produisait tels quels les chemins relatifs définis manuellement.
- **Les canonicals dérivés perdent leurs paramètres de requête.** Les canonicals explicites restent inchangés.
- **La découverte automatique des sitemaps laisse la priorité aux sources enregistrées.** Une `sitemap-post.xml` n'est plus créée en double à côté d'une source `sitemap-posts.xml` enregistrée.
- **Le JSON-LD utilise l'échappement `JSON_HEX_*`.** Si vous retraitez les scripts bruts, prévoyez les séquences d'échappement de caractères comme `<`.

## 6. Points d'attention connus {#_6-known-gotchas}

- Le `DatabaseSeeder` par défaut de Laravel utilise `WithoutModelEvents`, qui désactive le hook de création automatique de `HasSEO` dans les seeders.
- Si un modèle de titre par défaut de route contient déjà votre marque, terminez-le par le `title_suffix` configuré. Le résolveur évitera de l'ajouter une deuxième fois.

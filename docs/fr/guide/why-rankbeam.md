---
title: Qu’est-ce que Rankbeam ? L’infrastructure SEO Laravel expliquée
description: "Rankbeam est une infrastructure SEO open-core pour Laravel : un cœur MIT gratuit pour les métadonnées, URL canoniques, JSON-LD, sitemaps et contrôles des robots, un moteur de suivi Pro commercial et une interface Filament facultative."
---

# Qu’est-ce que Rankbeam ? {#what-is-rankbeam}

**Rankbeam est une infrastructure SEO open-core pour Laravel : un cœur MIT gratuit pour les métadonnées, URL canoniques, cartes sociales, JSON-LD lié, sitemaps et contrôles des robots, complété par des fonctions facultatives de suivi et de gestion dans Pro, commercial.** Il ne se limite pas à un assistant de balises ajouté à l’application. Il résout le SEO depuis vos modèles et votre configuration, affiche les mêmes données typées dans Blade, un head Inertia ou une API JSON et, avec Pro, continue de les surveiller après le déploiement.

## La famille de packages {#the-package-family}

Rankbeam comprend trois packages qui partagent une matrice de compatibilité :

| Package | Licence | Fonction |
|---|---|---|
| [`rankbeam/laravel-seo`](https://github.com/rankbeam/laravel-seo) | **MIT, gratuit** | Le cœur : résolution des métadonnées, graphe de schéma JSON-LD lié, sitemaps XML, contrôles des robots, `seo:audit` gratuit et importateurs |
| [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament) | **MIT, gratuit** | Champs de formulaire et aperçus en direct Filament 4/5, avec écriture dans le `seo_meta` du cœur |
| `rankbeam/laravel-seo-pro` | **Commerciale** | Moteur d’exploitation : scans en file d’attente avec score de 0 à 100, gestionnaire de redirections, suivi des 404 sans IP, explorateur de liens cassés, analyses Search Console et assistance IA avec votre propre clé |

Cette séparation est volontaire. Tout ce que la page rendue émet est sous MIT et reste gratuit ; la couche payante concerne **l’audit et le suivi** en production. Pro est un package commercial distinct, jamais inclus dans le cœur gratuit.

## À qui s’adresse Rankbeam ? {#who-it-s-for}

Rankbeam devient utile quand le SEO doit être **enregistré, rattaché à des modèles, multilingue, headless et audité**, dans une application Laravel en production dont le contenu est dynamique ou issu de modèles. Pour quelques pages statiques ayant seulement besoin d’un titre et d’une description, un petit assistant de métadonnées à l’exécution convient mieux. La remarque sur [les cas où une pile assemblée suffit](#what-is-honestly-not-in-the-free-core) le précise plus bas.

## Versions prises en charge {#supported-versions}

Une matrice commune à toute la famille :

- **PHP** 8.2–8.4 (Laravel 11); 8.2–8.5 (Laravel 12); 8.3–8.5 (Laravel 13)
- **Laravel** 11 / 12 / 13 ; Laravel 13 nécessite PHP 8.3+
- **Filament** 4 / 5, facultatif

## Ce que Rankbeam ne remplace pas {#what-rankbeam-doesn-t-replace}

Rankbeam coordonne les données SEO émises par votre application Laravel. Ce n’est ni un outil hébergé de suivi de positions, ni une suite de recherche de mots-clés, ni un produit d’analytics. Il ne promet ni classement, ni indexation, ni citation par une IA. Pour générer les sitemaps XML, il s’appuie sur [`spatie/laravel-sitemap`](https://packagist.org/packages/spatie/laravel-sitemap). Votre contenu, vos routes et vos outils d’analytics restent en place.

Vous découvrez le projet ? [Installez le cœur gratuit](/fr/guide/installation) ou poursuivez pour consulter les résultats d’un remplacement réel en production. Pro et la liste d’attente de l’offre de lancement se trouvent sur [rankbeam.dev](https://rankbeam.dev/fr/).

## Pourquoi ne pas assembler trois packages et du code d’intégration ? {#why-not-three-packages-glue}

La plupart des applications Laravel n’ont pas « un package SEO », mais une **pile SEO** : un package pour stocker les métadonnées par modèle, un autre pour ajouter les champs à Filament, un troisième pour scanner les pages, puis du code propre à l’application pour les faire fonctionner ensemble. Chaque élément peut convenir séparément. Le coût vient des interfaces entre eux et du code d’intégration que vous devez maintenir dans la durée.

Cette page présente un remplacement réel en production, où une telle pile a été retirée au profit de la famille Rankbeam. Les chiffres ci-dessous décrivent les mesures de cette migration.

## L’application de référence {#the-reference-app}

Un véritable site de contenu Laravel en production, anonymisé ici :

- Un **site hospitalier / institutionnel**, en production depuis environ trois mois.
- **Migré depuis WordPress**, avec environ 900 pages dans le sitemap.
- Environ **20 000 visites par jour**.
- **Laravel 12**, administration **Filament 4**, front-end Blade et MySQL.

Sa pile SEO avant le remplacement :

| Couche | Package |
|---|---|
| Stockage des métadonnées, table `seo` par modèle | [`ralphjsmit/laravel-seo`](https://github.com/ralphjsmit/laravel-seo) |
| Champs SEO Filament | `ralphjsmit/laravel-filament-seo` |
| Scanner de pages | `backstage/laravel-seo-scanner` |
| Intégration entre les éléments | **Environ 30 classes propres à l’application** |

Nous avons retiré les trois packages, installé Rankbeam **Core + Pro + Filament** et exécuté la suite de tests SEO. L’application a démarré avec **zéro régression SEO détectée par cette suite**. Voici ce que coûtait réellement la couche d’intégration et ce qui a disparu.

## Ce que le remplacement a supprimé {#what-the-swap-deleted}

Le remplacement de la pile de scan par Rankbeam a supprimé **12 classes spécifiques**. L’application ne porte plus ce code, car la famille de packages fournit son équivalent :

| Classe supprimée dans l’application | Ancien rôle | Désormais fourni par |
|---|---|---|
| `Services/SeoService.php` | Point d’entrée SEO de l’application | Résolveur du cœur et façade `SEO` |
| `Services/SeoWarningEvaluator.php` | Seuils de longueur titre/description et de taille d’image | `SEOWarningEvaluator` du cœur, partagé entre audit, aperçu et scan |
| `Services/Seo/SeoAssetInspector.php` | Inspection des dimensions d’images locales | `LocalImageInspector` du cœur |
| `Jobs/ScanAllPagesSeo.php` | Lancement du scan de tout le site en file d’attente | [Chaîne de scan](/fr/pro/scan-issues) Pro en file d’attente |
| `Jobs/ScanPageSeo.php` | Scan par page | `PageScanner` Pro |
| `Jobs/ScanPublicPageSeo.php` | Scan de chaque page publique | Chaîne de scan Pro |
| `Models/SeoScanBatch.php` | Suivi des exécutions de scan | `seo_scan_runs` Pro |
| `Filament/Pages/SeoDashboard.php` | Tableau de bord SEO de l’administration | Plugin `SeoDashboard` Pro |
| `Filament/Widgets/SeoScanProgressWidget.php` | Widget de progression du scan | Widgets de scan Pro |
| `Filament/Widgets/SeoTrendChartWidget.php` | Widget de tendance des scans | Widgets de scan Pro |
| `Facades/Seo.php` | Façade de l’application sur le package de stockage | Façade `SEO` du cœur |
| `Console/Commands/RecoverLegacySeoMetadata.php` | Récupération ponctuelle d’anciennes métadonnées | [Importateurs](/fr/guide/migrate-from-wordpress) du cœur, `seo:import-from` |

::: info Décompte précis du reste
La migration a volontairement **conservé** l’explorateur de liens cassés fait maison de l’application, soit environ 17 classes : job de scan, contrôleur de liens, constructeur des URL de départ, résolveur de sources, deux modèles, deux enums, deux événements, ressource Filament et trois widgets, deux commandes. Quelques assistants meta/schema sont aussi restés : `CustomSEO`, `EntitySeoSection`, `DynamicSeoDataResolver`, `SitewideSchema` et `SeoKeywords`, soit environ **22 classes supplémentaires** au total. Elles n’ont pas été supprimées le premier jour, car les équivalents Rankbeam sont arrivés ensuite : [explorateur de liens cassés Pro](/fr/pro/production), **modèle lié comme cible** et **aperçu SERP/social** Filament pour `CustomSEO`/`EntitySeoSection`, **graphe de schéma** du cœur pour `SitewideSchema`. Avec l’adoption complète de la famille, ce périmètre personnalisé, environ **trois douzaines de classes au total**, peut être pris en charge par les packages.
:::

Le problème n’est pas la qualité de l’un de ces packages. C’est le code d’*intégration* : plus d’une douzaine de classes pour répercuter une modification de métadonnées dans le scanner, le tableau de bord et le head rendu. Ce code propre à l’application n’a ni projet amont, ni tests autres que les vôtres, ni retours de bugs d’autres utilisateurs.

## Comparaison {#side-by-side}

| Fonctionnalité | Pile assemblée : trois packages et intégration | Famille Rankbeam |
|---|---|---|
| Stockage des métadonnées par modèle | Package de métadonnées | **Core**, `seo_meta`, MIT |
| Stockage **par locale** | Généralement géré par l’intégration | **Core** : locale de `seo_meta` définie par une colonne |
| Champs SEO Filament | Package Filament-SEO | **`laravel-seo-filament`**, MIT |
| Modifier le SEO d’un modèle **lié** | Adapter vous-même le composant de champ | Résolveur `target:` intégré |
| Aperçu **SERP et social** en direct | Blade/Alpine sur mesure | Aperçu éditorial à onglets intégré |
| Rendu headless : Inertia / Livewire / JSON | L’application de référence utilisait Blade ; les autres piles nécessitent une intégration | **Un résolveur** → Blade, Inertia, Livewire, JSON, [testé selon le contrat](/fr/contributing/rendering-contract) |
| Scanner de pages et problèmes classés | Package de scan | [Chaîne de scan](/fr/pro/scan-issues) **Pro** et `IssueRegistry` |
| Score de 0 à 100 | Code d’intégration ou absent | **Pro**, [barème versionné](/fr/pro/scoring) transparent |
| Redirections et traitement des 404 | Autre package ou code spécifique | Gestionnaire de redirections **Pro** et suivi des 404 sans IP |
| Explorateur de liens cassés | Code spécifique, développé par l’application | Explorateur **Pro** borné et reprenable |
| **Graphe** de schéma JSON-LD | Constructeur et liens `@id` manuels | Graphe Organization/WebSite/WebPage lié dans **Core** |
| Sitemaps XML | Package de sitemap | Registre de sitemaps **Core**, fondé sur `spatie/laravel-sitemap` |
| Import WordPress / Yoast / Rank Math | Scripts ponctuels | `seo:import-from` **Core** et [guide opérationnel](/fr/guide/wordpress-migration-runbook) |
| **Qui maintient les interfaces** | **Vous** | La famille de packages, avec des versions coordonnées |

## Trois limites du code d’intégration {#the-three-things-glue-can-t-do-well}

**1. Une famille cohérente, des versions coordonnées.** Trois packages ont trois mainteneurs, trois historiques de modifications et trois rythmes de mise à jour ; le code d’intégration absorbe leurs divergences. Rankbeam Core, Pro et Filament évoluent de manière coordonnée, avec une [matrice de compatibilité](#tested-where-it-runs) commune et des [limites de mise à niveau documentées](/fr/reference/configuration). Un changement de comportement est annoncé au même endroit, au lieu d’être découvert lorsque deux packages divergent.

**2. La locale dans une colonne de stockage.** `seo_meta` est polymorphe **et** délimité par locale dans la couche de stockage. Le SEO multilingue repose sur une ligne par `(modèle, locale)`, plutôt que sur un bloc sérialisé ou une table d’intégration ajoutée manuellement. La [chaîne de priorité du résolveur](/fr/concepts/resolver-precedence) lit directement la locale active.

**3. Un résolveur pour le rendu headless.** Rankbeam résout un `SEOData` typé et affiche les *mêmes* données en HTML, en charge utile `Head` Inertia ou en tableau JSON. Cette parité est vérifiée selon un [contrat de rendu](/fr/contributing/rendering-contract) commun pour [Blade](/fr/guide/blade), [Inertia](/fr/guide/inertia-json) avec Vue/React/Svelte et [Livewire](/fr/guide/livewire). Aucun panneau d’administration n’est requis : chaque fonctionnalité Pro s’utilise aussi [en headless avec artisan](/fr/pro/headless).

## Ce que le cœur gratuit ne contient pas {#what-is-honestly-not-in-the-free-core}

Rankbeam est open-core, avec une séparation explicite pour savoir ce que vous obtenez avant `composer require` :

| Package | Licence | Contenu |
|---|---|---|
| `rankbeam/laravel-seo` | **MIT, gratuit** | Résolution des métadonnées, graphe JSON-LD, sitemaps, `seo:audit` gratuit et importateurs |
| `rankbeam/laravel-seo-filament` | **MIT, gratuit** | Champs et sections de formulaire Filament écrivant dans `seo_meta` |
| `rankbeam/laravel-seo-pro` | **Commerciale** | Scans en file d’attente, problèmes classés, score de 0 à 100, redirections, suivi des 404, explorateur de liens cassés, Search Console, assistance IA et tableau de bord Filament |

Les fonctions payantes concernent donc **l’audit SEO technique** et le **suivi du site** : scans, score, redirections, traitement des 404 et exploration des liens. Le moteur de métadonnées, le graphe de schéma, les sitemaps et l’audit gratuit exécuté dans le processus sont sous MIT et restent gratuits.

Deux propriétés vérifiables :

- **Aucun contrôle de licence à l’exécution.** La licence Pro est attribuée par projet à l’installation. Aucun appel de validation de licence ne part vers nous et aucun mécanisme de désactivation ne peut arrêter votre application. Pro émet une télémétrie opérationnelle *locale*, désactivable, dans vos propres journaux, jamais vers nous.
- **Votre propre clé pour l’IA.** L’[assistance IA](/fr/pro/ai-assist) facultative utilise *votre* clé Anthropic, OpenAI, Google ou celle de votre service de modèle local. Rankbeam ne sert pas d’intermédiaire et ne mesure ni ne revend cette consommation. La fonction est désactivée par défaut.

::: tip Quand une pile assemblée suffit
Si quelques pages statiques ont seulement besoin d’un `<title>` et d’une description, un constructeur de balises à l’exécution suffit. Rankbeam devient utile lorsque le SEO doit être **enregistré**, **multilingue**, **rattaché à des modèles**, **headless** et **audité**, dès que l’intégration entre packages représente du code réel à maintenir.
:::

## Réduire les risques en quittant WordPress {#the-lowest-risk-switch-off-wordpress}

L’application de référence provenait d’une migration WordPress d’environ 900 pages, avec des années d’optimisation Yoast/Rank Math à conserver. Rankbeam prévoit une procédure progressive pour ce cas :

1. **Coexistence.** Installez Rankbeam à côté du site en ligne, sans rien retirer.
2. **Import, avec simulation préalable.** `seo:import-from yoast` / `rank-math` / `wordpress-csv` lit titres, descriptions, URL canoniques, directives robots, mots-clés principaux et personnalisations sociales. Les importateurs sont **idempotents** et **remplissent uniquement les champs vides par défaut**. Sans `--overwrite`, ils conservent les métadonnées déjà définies ; `--dry-run` n’écrit rien.
3. **Transfert des redirections.** Core produit un CSV versionné ; `seo-pro:redirects-import` dans Pro valide chaque ligne avant écriture et rejette boucles, cibles dangereuses et doublons.
4. **Vérification avant toute suppression.** `seo:audit --strict` sert de contrôle bloquant en CI ou avant bascule et renvoie un code non nul en présence d’un problème. La base WordPress reste intacte jusqu’à votre décision de la supprimer.

La procédure complète est dans le [guide opérationnel de migration WordPress](/fr/guide/wordpress-migration-runbook). La correspondance des champs et le traitement des variables sont détaillés dans [Migrer depuis WordPress](/fr/guide/migrate-from-wordpress). Vous quittez plutôt un package SEO **Laravel**, ralphjsmit, artesaos ou Spatie ? [Cette migration dispose aussi d’une commande](/fr/guide/migrate-from-other-packages).

## Comportement à plus grande échelle {#does-it-hold-up-at-scale}

Les deux contraintes les plus fortes de l’application de référence, une résolution sur chacune des quelque 20 000 requêtes quotidiennes et une exploration de liens sur environ 900 pages, ont chacune un benchmark dans la suite de tests. Ces tests vérifient des résultats **déterministes**, nombre de requêtes et limites des jobs, sans s’appuyer sur des durées optimisées manuellement :

**Cache du résolveur : zéro accès à la base sur une entrée chaude.** Avec le cache de résolution facultatif, une résolution en cache évite *toute* la chaîne de priorité. Le benchmark effectue 25 résolutions du même modèle :

| | Requêtes en base |
|---|---|
| Sans cache, chaque résolution relit `seo_meta` | **≥ 25** |
| Entrée en cache chaud | **0** |

Le cache est **désactivé par défaut** et documenté comme une option de montée en charge. L’invalidation supprime les entrées concernées lorsque `seo_meta`, un champ de contenu ou les valeurs par défaut changent. Consultez [Configuration : cache](/fr/reference/configuration).

**Explorateur de liens cassés : travail borné sur 900 pages.** Le benchmark fait parcourir un corpus généré d’environ 900 pages par le vrai job :

- Exécution terminée en **au moins 18 jobs bornés**, avec 50 pages maximum par job.
- **Aucun job** ne dépasse son plafond de 50 pages.
- **1 800 liens** vérifiés ; chaque cible morte produit un résultat durable de lien confirmé cassé.

Des plafonds finis par exécution et un budget de temps strict par job bornent le travail. La validation SSRF s’applique à la requête initiale **et à chaque redirection**. Un verrou à durée limitée en base ne permet qu’une exécution active par périmètre. L’exploitation est décrite dans le [guide de configuration en production](/fr/pro/production).

## Testé sur les environnements pris en charge {#tested-where-it-runs}

Une matrice de compatibilité commune à toute la famille :

- **PHP** 8.2–8.4 (Laravel 11); 8.2–8.5 (Laravel 12); 8.3–8.5 (Laravel 13)
- **Laravel** 11 / 12 / 13
- **Filament** 4 / 5

## Faut-il encore assembler trois packages ? {#so-—-why-glue-three-packages-together}

Si votre pile nécessite plus d’une douzaine de classes spécifiques, des rythmes de mise à jour que vous ne maîtrisez pas, une intégration de rendu par pile et une gestion manuelle des locales, ce code mérite d’être comparé à une famille cohérente, headless et conçue pour les locales. La migration de l’application de 900 pages et 20 000 visites quotidiennes fournit un exemple concret de ce qui peut être retiré.

Commencez par le [démarrage rapide](/fr/guide/quickstart), de `composer require` à un `<head>` entièrement rendu en cinq minutes.

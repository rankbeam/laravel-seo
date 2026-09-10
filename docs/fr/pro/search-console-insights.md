---
description: "Analysez vos propres données Search Console : cinq rapports sur les plages de position, les CTR à examiner et les requêtes communes à plusieurs pages."
---

# Analyses Search Console {#search-console-insights}

Cinq rapports calculés depuis vos données Search Console : requêtes dans une plage de position choisie, CTR à examiner, recoupements entre requêtes et pages, groupes de requêtes et évolutions entre périodes. Trois utilisent l’historique synchronisé ; deux partagent une requête en direct mise en cache. Ils couvrent ces analyses précises, sans prétendre remplacer toutes les données et fonctions d’une plateforme tierce de recherche de mots-clés.

Cette fonction s’appuie sur [l’intégration Search Console en lecture seule](/fr/pro/search-console) et sa synchronisation d’historique. Si `seo-pro:gsc-sync` fonctionne déjà, trois des cinq vues ne nécessitent **aucun appel API supplémentaire**.

::: tip Prérequis
Les trois vues sur *instantanés* lisent l’historique enregistré dans `seo_gsc_metrics`. Planifiez d’abord `seo-pro:gsc-sync` ; voir [Search Console : historique](/fr/pro/search-console). Plus vous synchronisez de jours, plus la comparaison de tendances peut remonter loin.
:::

## Les cinq vues {#the-five-surfaces}

### 1. Mots-clés proches de la première page {#_1-striking-distance-keywords}

Requêtes dont la **position moyenne pondérée par les impressions se situe entre 5 et 20**, classées par impressions. Utilisez-les pour examiner la pertinence du contenu et les liens internes. Cette plage ne prouve pas qu’une petite modification fera passer une requête en première page.

### 2. Opportunités de CTR {#_2-ctr-opportunities}

Requêtes **bien positionnées mais moins cliquées que le taux attendu** pour leur position. Le CTR réel de chaque requête est comparé à une courbe de référence combinant des moyennes sectorielles par position. Celles qui se situent nettement en dessous, avec des impressions réelles, deviennent des **candidates à une révision du titre ou de la description**, classées par estimation des *clics manqués*. Cette liste peut alimenter les [suggestions de métadonnées par IA](/fr/pro/ai-assist), en indiquant les requêtes à examiner pour une réécriture.

### 3. Cannibalisation {#_3-cannibalization}

Requêtes pour lesquelles **au moins deux de vos URL apparaissent** sur le même terme. Le recoupement n’est pas nécessairement nuisible : vérifiez si les pages répondent à des intentions différentes avant de les regrouper ou de les différencier.

### 4. Groupes de requêtes {#_4-query-clusters}

Les **requêtes sur lesquelles chaque page apparaît réellement**, regroupées par page, donnent un aperçu de son périmètre thématique dans Google. Cette vue aide à repérer une page qui s’éloigne de son sujet prévu ou qui apparaît pour un terme utile que vous ne visiez pas.

### 5. Évolution par rapport à la période précédente {#_5-trend-vs-previous-period}

Les **plus fortes variations** de clics, impressions, position et CTR entre la période courante et la période de même durée qui la précède immédiatement. La position est comparée uniquement si une requête avait du trafic sur les deux périodes : une requête entièrement nouvelle ou disparue n’a pas de position comparable dans l’autre période.

## Origine des chiffres : direct ou instantanés {#where-the-numbers-come-from-live-vs-snapshot}

Chaque vue utilise la source qui répond correctement à son besoin avec le moins d’appels. L’historique enregistré ne permet pas de reconstituer quelle **requête** correspond à quelle **page**, car les dimensions sont stockées séparément. Seules les deux vues nécessitant cette paire utilisent donc des données en direct, et elles **partagent une seule requête mise en cache**.

| Vue | Source | Motif |
|---|---|---|
| Mots-clés proches de la première page | **Instantané local** | Position et impressions par requête déjà présentes dans l’historique synchronisé ; aucun appel API |
| Opportunités de CTR | **Instantané local** | Mêmes données propres au site ; la courbe de CTR attendu est une référence statique, sans requête externe |
| Variations de tendance | **Instantané local** | Historique quotidien réel, précisément ce que conserve la synchronisation |
| Cannibalisation | **Direct**, requête × page | La paire requête→page n’est pas enregistrée ; conserver chaque paire multiplierait le stockage |
| Groupes de requêtes | **Direct**, avec *la même récupération que la vue 3* | Mêmes paires, regroupées par page au lieu de par requête |

Une visite de la page d’analyses nécessite donc **au maximum une** requête Search Analytics, mise en cache pendant `search_console.cache_ttl` secondes. Les vues par paires utilisent volontairement le direct : cannibalisation et regroupement décrivent une situation à un *instant donné*. Le cache partagé limite les appels répétés. Le renouvellement du jeton peut nécessiter une requête d’authentification supplémentaire, et les quotas Google continuent de s’appliquer. Les vues sur instantanés n’accèdent jamais au réseau.

## Dans le tableau de bord {#in-the-dashboard}

Avec le plugin Filament, **Search Console Insights** apparaît dans le groupe de navigation *SEO*, uniquement lorsque l’intégration est activée. Tout est en lecture seule. Chaque vue occupe une section ; les vues sur instantanés invitent à synchroniser l’historique lorsqu’elles sont vides. Un échec de récupération en direct affiche un message nettoyé dans la section concernée, sans bloquer la page.

## Configuration {#configuration}

Tout se trouve sous `search_console.insights` dans `config/seo-pro.php`. Les valeurs par défaut constituent un point de départ ; ajustez les seuils au volume de votre site.

```php
'search_console' => [
    // ...
    'insights' => [
        // Rolling window (days) the snapshot surfaces aggregate over,
        // anchored to the latest synced day.
        'window_days' => 28,

        // Max rows a surface returns to its panel section.
        'max_rows' => 50,

        // (1) Striking distance: impression-weighted position in [min,max]
        // with at least this many impressions.
        'striking_distance' => [
            'min_position' => 5.0,
            'max_position' => 20.0,
            'min_impressions' => 30,
        ],

        // (2) CTR opportunity: queries ranking at/above max_position, with at
        // least min_impressions, whose CTR is at least min_gap_ratio below the
        // expected curve.
        'ctr_opportunity' => [
            'max_position' => 10.0,
            'min_impressions' => 50,
            'min_gap_ratio' => 0.30,
        ],

        // Optional override of the expected CTR-by-position curve
        // (position => percent). null uses the built-in blended curve.
        'ctr_curve' => null,

        // (3) Cannibalization: a query with this many URLs each drawing at
        // least min_impressions.
        'cannibalization' => [
            'min_urls' => 2,
            'min_impressions' => 10,
        ],

        // (4) Query clustering: queries per page above min_impressions.
        'clustering' => [
            'min_impressions' => 10,
            'max_queries_per_page' => 15,
        ],

        // (5) Trend deltas: surface queries with at least this many
        // impressions in either period.
        'trend' => [
            'min_impressions' => 20,
        ],

        // The shared live (query,page) fetch for surfaces 3 + 4.
        'pair_days' => 28,
        'pair_row_limit' => 5000,
    ],
],
```

::: info Courbe de CTR attendu
La courbe des opportunités de CTR est une **heuristique** combinant des moyennes publiées de CTR organique par position. C’est un repère, pas une affirmation sur votre site précis. Une requête signalée est une *candidate à examiner*, pas un défaut prouvé. Si vous avez votre propre courbe mesurée, fournissez-la dans `insights.ctr_curve` sous forme de correspondance `position => pourcentage`.
:::

## Voir aussi {#see-also}

- [Search Console](/fr/pro/search-console) : intégration en lecture seule et synchronisation d’historique utilisées par ces analyses
- [Rapports en marque blanche](/fr/pro/reports) : variations entre périodes dans le PDF personnalisé
- [Assistance IA](/fr/pro/ai-assist) : réviser les titres et descriptions signalés dans la vue CTR

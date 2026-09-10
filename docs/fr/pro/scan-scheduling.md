---
description: "Planifiez des scans SEO complets et suivez les problèmes apparus, revenus ou corrigés depuis le précédent, classés par impact dans le tableau de bord et dans un e-mail facultatif."
---

# Planification des scans et différences entre exécutions {#scan-scheduling-delta}

Lancez un scan SEO complet **à intervalles planifiés** et consultez **ce qui a changé depuis le précédent** : problèmes apparus, revenus ou corrigés, classés par impact dans le tableau de bord et, si vous le souhaitez, dans un e-mail de synthèse.

Ces deux fonctionnalités se complètent : les différences donnent son intérêt au résumé d’un scan planifié.

## Ce qui a changé depuis le dernier scan {#what-changed-since-the-last-scan}

Chaque scan terminé fige l’**ensemble de ses problèmes ouverts** dans un instantané léger, `seo_scan_run_issues`. Comparer les instantanés de deux exécutions produit une différence précise en trois catégories :

- **New, nouveau** : problème absent des problèmes ouverts auparavant, présent maintenant et jamais ouvert dans un scan antérieur. C’est une première détection.
- **Regressed, revenu** : problème qui avait été corrigé et qui **réapparaît**. Il ne s’agit pas d’une aggravation : la gravité est fixe par type de problème. La régression est donc un retour, ce que le [cycle de vie](/fr/pro/scan-issues#issue-lifecycle) appelle une réouverture.
- **Fixed, corrigé** : problème ouvert lors du scan précédent et désormais absent.

Chaque catégorie est [classée par impact](#impact-ordering) pour faire apparaître les priorités en premier.

### Pourquoi un instantané plutôt que la table des problèmes ? {#why-a-snapshot-not-the-issues-table}

Les problèmes suivent un [cycle de vie](/fr/pro/scan-issues#issue-lifecycle) avec correction et réouverture. La même ligne est mise à jour entre les scans ; son `scan_run_id` est remplacé par celui de la dernière exécution tant qu’elle reste ouverte. Cela conserve un historique durable du problème, mais la table courante ne peut pas dire *quels problèmes étaient ouverts à la fin de l’exécution N* : un problème persistant ne référence que la dernière exécution.

Chaque scan conserve donc son ensemble ouvert, identifié par une **empreinte de problème** stable : `issue_type | target | field`. Le [rapport en marque blanche](/fr/pro/reports) utilise la même identité. La différence repose ensuite sur des opérations d’ensembles entre deux jeux d’empreintes figés. Elle fonctionne pour **n’importe quelles** deux exécutions, pas seulement deux scans consécutifs.

### Cas particuliers et limites {#edge-cases-handled-honestly}

- **Une page sort des cibles de scan.** Ses problèmes ouverts ne sont plus réexaminés. Ils restent ouverts et sont inclus dans chaque nouvel instantané : ils apparaissent comme **toujours ouverts**, jamais comme faussement « corrigés ». Cesser de regarder une page ne prouve pas sa correction.
- **Un contrôle est désactivé entre deux scans.** Ses problèmes ne sont plus émis ; leur cycle de vie les marque corrigés et ils sortent de l’ensemble ouvert. Ils apparaissent donc comme **corrigés**, ce qui reflète l’avis actuel du scanner. Au niveau du problème, rien ne permet de distinguer une correction réelle de la désactivation du contrôle.
- **Premier scan après une mise à niveau.** Les exécutions antérieures à cette fonctionnalité n’ont pas d’instantané et ne servent jamais de référence. Le premier scan avec instantané établit une **référence**, état courant sans différence, au lieu de déclarer tout le site « nouveau ». La comparaison devient disponible dès le deuxième.

### Dans le tableau de bord {#on-the-dashboard}

Le widget **« What changed since the last scan »** du [tableau de bord SEO](/fr/pro/installation) compare les deux derniers scans terminés. Il affiche les nombres de problèmes nouveaux, revenus et corrigés, puis les principaux résultats de chaque catégorie classés par impact. Tant que deux instantanés ne sont pas disponibles, il affiche une courte note indiquant la référence initiale.

## Classement par impact {#impact-ordering}

Chaque catégorie est classée selon un score d’**impact**, pour présenter les problèmes prioritaires d’abord :

```
impact = severity_weight × page_importance
```

- **severity_weight** réutilise le [barème de score](/fr/pro/scoring) publié : `40` pour une erreur critique, `15` pour un avertissement et `5` pour une remarque. La gravité exprime l’évaluation du produit sur l’importance d’un défaut ; le classement la réutilise au lieu d’inventer une seconde échelle.
- **page_importance** dépend de la **demande de recherche observée**, soit les impressions de la page dans [Search Console](/fr/pro/search-console), un signal permettant de distinguer les pages :

  ```
  page_importance = 1 + demand_weight·demand + priority_weight·priority
  demand   = log1p(page impressions) / log1p(busiest page's impressions)   ∈ [0,1]
  priority = the page's configured per-class sitemap priority              ∈ [0,1]
  ```

  Les impressions sont converties sur une échelle logarithmique : dix fois plus de trafic ne signifie pas dix fois plus d’importance. Elles sont normalisées par rapport à la page la plus fréquentée, pour un comportement comparable sur un petit blog et un grand catalogue. Le `<priority>` du sitemap reste un **faible signal secondaire**. Il est absent par défaut et généralement uniforme lorsqu’il est défini ; il ne peut donc pas porter le classement. Il apporte un ajustement si vous configurez des priorités par type dans `seo.sitemap.models`.

**Sans Search Console, le classement reste utilisable.** Sans historique GSC synchronisé ni priorités configurées, `page_importance` vaut `1` pour toutes les pages. L’impact devient un simple **classement par gravité**, sans donnée inventée. Synchronisez [l’historique GSC](/fr/pro/search-console#historical-metrics) avec `seo-pro:gsc-sync` pour activer la pondération par demande.

Ajustez les poids et la période sous `seo-pro.scan.delta.impact`.

## Planifier un scan {#scheduling-a-scan}

Le package **ne planifie rien par défaut**. Activez la planification :

```php
// config/seo-pro.php
'schedule' => [
    'enabled' => true,          // env SEO_PRO_SCHEDULE_ENABLED
    'frequency' => 'weekly',    // daily | weekly | monthly | hourly
    'time' => '03:00',          // for daily/weekly/monthly
    'timezone' => null,         // null = app timezone
    // ...
],
```

Vous pouvez aussi définir une expression cron complète, prioritaire sur `frequency` :

```php
'cron' => '0 3 * * 1',   // env SEO_PRO_SCHEDULE_CRON
```

Le package enregistre alors `seo-pro:scan` dans l’ordonnanceur Laravel avec `withoutOverlapping`, pour empêcher le chevauchement des exécutions de la **commande** planifiée. Ce verrou ne couvre pas toute la durée des jobs en file d’attente. L’enregistrement a lieu uniquement dans un contexte d’ordonnanceur ou de console, sans **surcoût pour les requêtes web**.

::: warning Un ordonnanceur actif est nécessaire
La planification du package reste inactive si l’ordonnanceur Laravel ne tourne pas. Utilisez le cron standard `* * * * * php artisan schedule:run`, ou `php artisan schedule:work` en développement. Consultez la [configuration en production](/fr/pro/production#scheduler).
:::

Pour gérer vous-même la planification, laissez `schedule.enabled` désactivé et enregistrez la commande dans votre propre noyau console. Les différences et le résumé fonctionnent toujours :

```php
$schedule->command('seo-pro:scan --notify')->weekly();
```

`--sync` exécute directement le scan au lieu de mettre un job par cible en file d’attente. Cela convient à un petit site sans worker ; laissez cette option désactivée en production.

## E-mail de synthèse {#summary-e-mail}

Activez l’envoi d’un e-mail **« ce qui a changé depuis le dernier scan »** à la fin d’un scan planifié. Ce résumé HTML personnalisé classe par impact les problèmes nouveaux, revenus et corrigés :

```php
'schedule' => [
    // ...
    'notify' => [
        'enabled' => true,                       // env SEO_PRO_SCHEDULE_NOTIFY
        'recipients' => ['seo@agency.test'],     // falls back to reports.recipients
        'subject' => 'SEO scan summary',
        'only_on_change' => true,                // skip when nothing changed
    ],
],
```

Il réutilise la personnalisation et les réglages d’envoi des [rapports en marque blanche](/fr/pro/reports) : nom d’agence, logo et couleur d’accent. Sans destinataires distincts, ceux des rapports sont utilisés. `only_on_change` évite l’envoi si rien n’a changé ; le premier scan de référence est toujours envoyé.

Le résumé n’est envoyé que pour une exécution lancée avec `--notify`. L’ordonnanceur ajoute cette option automatiquement lorsque `notify.enabled` est activé. Un `seo-pro:scan` ponctuel **sans `--notify`** n’envoie d’e-mail à personne.

::: tip Un autre canal ?
Pour utiliser Slack, un webhook ou un résumé personnalisé, abonnez-vous à `Rankbeam\Seo\Pro\Events\SeoScanCompleted`. L’événement est émis une fois par exécution terminée et contient cette exécution. Vous pouvez construire la différence avec `Rankbeam\Seo\Pro\Scanning\Delta\ScanRunDelta` et l’envoyer vers le canal choisi.
:::

## Rétention {#retention}

Les instantanés sont supprimés en cascade avec leur exécution. [`seo-pro:scan-prune`](/fr/pro/production#scheduler) les retire donc automatiquement selon la rétention, sans nouvelle commande à planifier. Une exécution n’est purgée que lorsqu’elle ne porte plus de problème ouvert ; l’instantané d’une exécution récente reste ainsi disponible pour la comparaison.

Désactivez entièrement les instantanés, et donc les différences et résumés, avec `seo-pro.scan.delta.snapshot => false`.

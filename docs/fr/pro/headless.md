---
description: "Toutes les fonctionnalités Pro, scans, redirections et journalisation des 404, fonctionnent sans Filament. Référence des commandes pour gérer Pro entièrement avec artisan."
---

# Utilisation headless {#headless-usage}

Toutes les fonctionnalités Pro, scans, redirections et journalisation des 404, fonctionnent en headless : elles appartiennent au moteur et ne nécessitent pas Filament. Le panneau est uniquement une interface de gestion ; ces commandes en sont l’équivalent sans interface.

## Référence des commandes {#command-reference}

### Installation et diagnostic {#setup-health-check}

| Commande | Fonction |
|---|---|
| `seo-pro:install` | Publier `config/seo-pro.php` et les migrations Pro, les exécuter, puis afficher les prochaines étapes ; options `--no-migrate`, `--force` |
| `seo:doctor` | Diagnostic ponctuel : URL de l’application, tables du cœur et de Pro, cibles de scan, sitemap, files par charge de travail, fonctionnalités facultatives et état opérationnel, avec correction précise pour chaque avertissement ; `--json` pour le suivi |

`seo-pro:install` est la procédure d’installation documentée. Les migrations Pro doivent être publiées : le package ne les charge jamais automatiquement. L’installateur transforme donc un simple `composer require` en schéma fonctionnel. Il est idempotent et peut être relancé.

`seo:doctor` n’effectue aucun appel réseau et n’affiche jamais de secret : le contrôle IA indique seulement si la variable de clé configurée est *définie*. Il valide la configuration et l’historique récent, sans pouvoir prouver qu’un cron externe ou un worker fonctionne réellement. Il renvoie un code non nul uniquement en cas d’erreur critique, comme une table requise absente ; une machine de développement locale avec des avertissements peut donc terminer avec succès. `--json` fournit un `id` stable pour chaque contrôle. Lancez-le juste après [l’installation](/fr/pro/installation) et dans la CI.

### Scans {#scanning}

| Commande | Fonction |
|---|---|
| `seo-pro:scan` | Mettre en file d’attente un scan de toutes les cibles enregistrées ; `--sync` pour l’exécution directe ; **contrôle bloquant CI** avec `--fail-on-error`, `--fail-on-warning`, `--report=`, `--format=json\|md\|html`, qui nécessitent `--sync` |
| `seo-pro:scan-status` | Résumé du dernier scan et problèmes ouverts, les plus graves d’abord ; `--limit=20`, `--severity=critical\|warning\|notice` |
| `seo-pro:scan-recover` | Marquer comme échouées les exécutions abandonnées par un worker arrêté |
| `seo-pro:scan-prune` | Supprimer les exécutions terminées, avec leurs problèmes, au-delà de la durée de rétention |

### Explorateur de liens cassés {#broken-link-crawler}

Désactivé par défaut. Activez `seo-pro.broken_links.enabled` et appliquez les migrations de ses deux tables, publiées par `seo-pro:install`. L’exploration se répartit sur des jobs bornés en file d’attente ; utilisez un worker dédié à cette file. Consultez la [configuration en production](/fr/pro/production) pour les réglages.

| Commande | Fonction |
|---|---|
| `seo-pro:broken-links-scan` | Mettre en file une exploration bornée et reprenable ; `--scope=internal_only\|internal_and_external`, `--url=*` pour des URL de départ supplémentaires |
| `seo-pro:broken-links-status` | Résumé de la dernière exploration, résultats ouverts et [inspections typées](/fr/pro/broken-links#typed-link-inspections) de cette exécution ; **contrôle bloquant CI** avec `--fail-on-error`, `--fail-on-warning`, `--report=`, `--format=` |
| `seo-pro:broken-links-cancel` | Annuler une exploration en cours ou en attente ; `{run?}` désigne la dernière active par défaut |
| `seo-pro:broken-links-recover` | Marquer comme échouées les explorations abandonnées par un worker arrêté, avec bail périmé |
| `seo-pro:broken-links-prune` | Appliquer la rétention aux anciennes exécutions et aux résultats résolus |

### Redirections et 404 {#redirects-404s}

| Commande | Fonction |
|---|---|
| `seo-pro:redirect-create {source} {target}` | Créer une règle de redirection ; `--code=301`, `--regex`, `--no-preserve-query`, `--note=` |
| `seo-pro:404-list` | Lister les 404 journalisées, les plus fréquentes d’abord ; `--status=new\|ignored\|redirected\|all`, `--limit=20` |
| `seo-pro:redirects-flush-hits` | Écrire en base les compteurs de redirections regroupés en cache lorsque `redirects.hits.flush_immediately=false` |
| `seo-pro:404-prune` | Supprimer les anciennes entrées 404 et appliquer le plafond de lignes |

### Liste de contrôle on-page {#on-page-checklist}

| Commande | Fonction |
|---|---|
| `seo-pro:checklist {model} {id}` | Liste de contrôles réussite/avertissement/échec tenant compte du mot-clé pour un modèle ; `--json`, `--strict`, `--locale=`. Voir la [liste de contrôle on-page](/fr/pro/on-page-checklist) |

La même liste est accessible avec `SeoPro::checklistFor($model)`. Elle accompagne le travail éditorial, placement des mots-clés, longueur, images et liens internes, et reste distincte du [score SEO](/fr/pro/scoring).

### Search Console, en lecture seule {#search-console-read-only}

| Commande | Fonction |
|---|---|
| `seo-pro:search-console` | Pages présentant des problèmes ouverts **et** du trafic de recherche, selon la priorité d’opportunité ; `--view=attention` par défaut |
| `seo-pro:search-console --view=pages` | Pages principales par impressions, clics, CTR et position |
| `seo-pro:search-console --view=queries` | Requêtes principales ; `--days=`, `--limit=`, `--json` |

Ces métriques sont aussi accessibles via `SeoPro::searchConsole()` ; voir [Search Console](/fr/pro/search-console). Désactivé par défaut et strictement en lecture seule.

### Assistance IA {#ai-assist}

| Commande | Fonction |
|---|---|
| `seo-pro:ai-suggest {model} {id}` | Suggestions de titre/description en JSON ; `--field=title\|description\|all`. Voir l’[assistance IA](/fr/pro/ai-assist) |
| `seo-pro:ai-suggest --issue={id}` | Explication en langage courant de la correction d’un problème de scan, en JSON |

### Résoudre une 404 en une étape {#resolving-a-404-in-one-step}

`--from-404={path}` est l’équivalent headless de l’action *Create redirect* du suivi des 404. L’option crée la règle **et** marque l’entrée correspondante comme redirigée, en la liant à la nouvelle règle :

```bash
php artisan seo-pro:404-list

#  ID | Path        | Hits | Status | ...
#  1  | /old-pricing | 41  | new

php artisan seo-pro:redirect-create /old-pricing /pricing --from-404=/old-pricing

# Redirect #1 created: /old-pricing → /pricing (301).
# 404 log #1 (/old-pricing) marked redirected.
```

La commande utilise les mêmes validateurs que le formulaire Filament. Les expressions régulières invalides, valeurs trop longues et cibles externes hors liste autorisée sont refusées avant toute écriture.

## Planification conseillée {#recommended-schedule}

```php
use Illuminate\Support\Facades\Schedule;

// Only needed when seo-pro.redirects.hits.flush_immediately=false.
Schedule::command('seo-pro:redirects-flush-hits')->everyFiveMinutes();

// Keep the 404 log within retention and the row cap.
Schedule::command('seo-pro:404-prune')->daily();

// Scan cadence: weekly suits most sites; go daily when content
// changes fast. Scans are queued jobs - pair with a queue worker.
Schedule::command('seo-pro:scan')->weekly();

// Housekeeping for the scan pipeline.
Schedule::command('seo-pro:scan-recover')->hourly();
Schedule::command('seo-pro:scan-prune')->daily();

// Broken-link crawler (only when enabled). Run a dedicated worker for
// its queue so a long crawl never starves user-facing jobs.
Schedule::command('seo-pro:broken-links-scan')->weekly();
Schedule::command('seo-pro:broken-links-recover')->hourly();
Schedule::command('seo-pro:broken-links-prune')->daily();
```

Le guide de [configuration en production](/fr/pro/production) indique une fréquence conseillée pour chaque commande récurrente ci-dessus, ainsi que l’organisation des files, la configuration des workers, les règles de nouvelle tentative et de récupération, la rétention et la **télémétrie** structurée émise à chaque exécution terminée : pages récupérées, liens vérifiés, URL bloquées, durée et retard de file.

## Qu’est-ce qui nécessite l’interface Filament ? {#what-needs-the-filament-ui}

Aucune fonction du moteur. La chaîne de scan, le suivi des problèmes, la correspondance des redirections, la journalisation des 404, la purge et la récupération sont identiques avec ou sans Filament. Le panneau ajoute les *vues* : tableau de bord avec progression et statistiques de gravité, consultation filtrée des problèmes et fenêtres par page, boutons ignorer/rouvrir, formulaires de gestion des redirections et tableau des 404 avec action en un clic. Ignorer ou rouvrir un problème n’a pas encore de commande dédiée : utilisez le panneau ou le modèle `SEOScanIssue`, avec `markIgnored()` / `reopen()`, dans tinker ou votre propre code.

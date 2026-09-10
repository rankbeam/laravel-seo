---
description: "Exploitez Pro à grande échelle : files dédiées, ordonnanceur, nouvelles tentatives, récupération, rétention et télémétrie. Une organisation indépendante de Filament utilisée sur un site d’environ 900 pages."
---

# Configuration en production {#production-setup}

Les tâches courantes de Pro, scans du site, exploration des liens cassés, écriture facultative des compteurs de redirections et purge des 404, reposent sur les files d’attente et l’ordonnanceur Laravel. Ce guide de référence décrit leur exploitation à grande échelle : files dédiées, ordonnanceur, nouvelles tentatives, récupération, rétention et télémétrie. Il présente l’organisation utilisée par une installation en production d’environ 900 pages et 20 000 visites quotidiennes, sous une forme reproductible.

Tout est **indépendant de Filament** : moteur, commandes, files et télémétrie fonctionnent de façon identique avec ou sans panneau. Filament ajoute des vues sans modifier la planification ni le traitement du travail.

[[toc]]

## Ordre de mise en service {#safe-rollout-order}

Procédez dans cet ordre ; chaque étape peut être vérifiée avant la suivante :

1. **Installer** : publier la configuration et les migrations, puis les exécuter :

   ```bash
   php artisan seo-pro:install
   ```

   `seo-pro:install` publie `config/seo-pro.php` et les migrations Pro, puis lance `migrate`. Les migrations Pro doivent être **publiées** : le package ne les charge jamais automatiquement. Cette étape transforme donc un simple `composer require` en schéma fonctionnel. La commande est idempotente et peut être relancée. `--force` écrase les fichiers publiés ; `--no-migrate` les publie sans exécuter les migrations.

2. **Enregistrer les cibles** dans un service provider, `AppServiceProvider::boot()` :

   ```php
   use Rankbeam\Seo\Pro\Facades\SeoPro;

   SeoPro::targets()->register('posts', Post::class);
   SeoPro::targets()->registerRoutes('static', ['home', 'pricing']);
   // or: SeoPro::targets()->fromSitemaps();
   ```

3. **Vérifier** la configuration avant d’activer les tâches en arrière-plan :

   ```bash
   php artisan seo:doctor
   ```

   Corrigez chaque avertissement affiché : chacun indique la commande ou ligne de configuration précise. En CI, ajoutez `--json` et utilisez les identifiants stables des contrôles.

4. **Configurer les files et l’ordonnanceur** ci-dessous, déployer un worker et une entrée cron pour `schedule:run`.

5. **Activer les fonctions facultatives en dernier** : explorateur de liens cassés, assistance IA et Search Console sont désactivés par défaut. L’explorateur nécessite ses tables migrées, publiées à l’étape 1, et un worker dédié.

**Mise à niveau vers Pro 2.41.0 :** suspendez les workers de scan, publiez les migrations avec `php artisan vendor:publish --tag=seo-pro-migrations --force`, exécutez `php artisan migrate`, puis redémarrez les workers et lancez `php artisan seo:doctor`. La table `seo_scan_target_completions` et la colonne `seo_scan_runs.target_tracking` sont requises. Les enregistrements par exécution/cible empêchent les résultats terminaux en double d’augmenter les compteurs ; le premier résultat accepté prévaut. Les anciennes exécutions en attente sans cible traitée continuent. Celles partiellement traitées avant la mise à niveau conservent leur historique, mais se ferment à la livraison suivante avec une demande de nouveau scan. Relancez les cibles ayant épuisé leurs tentatives dans une nouvelle exécution. Pour revenir en arrière, arrêtez les workers et restaurez le code avant d’annuler la migration ; gardez une sauvegarde antérieure pour annuler aussi les scans ultérieurs.

## Files dédiées par type de travail {#dedicated-queues-per-workload}

Un long scan ou une exploration ne doit pas retarder les jobs destinés aux utilisateurs, e-mails et notifications. Donnez à chaque charge SEO sa propre file et son propre worker.

La chaîne de scan et l’explorateur utilisent chacun une file configurable :

| Travail | Configuration | Variable d’environnement | File par défaut |
|---|---|---|---|
| Jobs de scan on-page | `seo-pro.scan.queue` | `SEO_PRO_SCAN_QUEUE` | File par défaut |
| Jobs d’exploration des liens | `seo-pro.broken_links.queue.name` et `.connection` | `SEO_PRO_BROKEN_LINKS_QUEUE` et `_CONNECTION` | `seo-broken-links` |

### Exemple Redis : organisation en production {#redis-example-the-production-topology}

Dans `.env` :

```dotenv
QUEUE_CONNECTION=redis

# Dedicated queues so SEO work never starves user-facing jobs.
SEO_PRO_SCAN_QUEUE=seo
SEO_PRO_BROKEN_LINKS_QUEUE=broken_links
SEO_PRO_BROKEN_LINKS_QUEUE_CONNECTION=redis
```

Lancez un worker par file, chacun dans un processus ou programme Supervisor séparé :

```bash
# User-facing jobs — highest priority, most workers.
php artisan queue:work redis --queue=default --tries=3

# On-page scans — moderate; a scan target job is short.
php artisan queue:work redis --queue=seo --tries=3 --timeout=360

# Broken-link crawl — one worker is plenty; jobs are long and self-redispatch.
php artisan queue:work redis --queue=broken_links --tries=1 --timeout=240
```

Le `--timeout` du worker d’exploration doit dépasser `seo-pro.broken_links.batch.hard_time_budget_seconds`, 180 par défaut, plus le délai HTTP, afin de ne pas tuer un lot pendant l’enregistrement de sa progression. Le job fixe son propre `$timeout` à cette somme ; alignez l’option du worker avec lui et prévoyez une marge. Utilisez `--tries=1` pour l’exploration : un job arrêté est récupéré par la continuation suivante ou `seo-pro:broken-links-recover`. Les tentatives supplémentaires au niveau de la file sont inutiles.

`seo:doctor` indique la file de chaque charge et avertit si elle utilise `sync`, ce qui exécuterait le travail directement et bloquerait l’appel.

## Ordonnanceur {#scheduler}

Laravel 11, 12 et 13 définissent la planification dans **`routes/console.php`**. La méthode `schedule()` de `app/Console/Kernel.php` concerne les applications mises à niveau depuis Laravel 10 ; placez-y les mêmes entrées si votre application l’utilise encore. Ajoutez une seule entrée cron système pour lancer l’ordonnanceur chaque minute :

```bash
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Enregistrez ensuite chaque commande récurrente avec la fréquence conseillée :

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

// --- Core ---------------------------------------------------------------
// Regenerate the XML sitemap (needs spatie/laravel-sitemap + registered sources).
Schedule::command('seo:sitemap')->dailyAt('01:30');

// --- Scan pipeline ------------------------------------------------------
// Scan cadence: weekly suits most sites; go daily when content changes fast.
// Queued — pair with the `seo` queue worker above.
Schedule::command('seo-pro:scan')->weekly();
// Fail runs abandoned by a dead worker so they never hang the pipeline.
Schedule::command('seo-pro:scan-recover')->hourly();
// Delete finished runs (and their issues) past the retention window.
Schedule::command('seo-pro:scan-prune')->daily();

// --- Redirects & 404s ---------------------------------------------------
// Only needed when seo-pro.redirects.hits.flush_immediately=false.
Schedule::command('seo-pro:redirects-flush-hits')->everyFiveMinutes();
// Keep the 404 log within its retention window and row cap.
Schedule::command('seo-pro:404-prune')->daily();
// Re-fetch open 404 paths; mark any that return 200 again as recovered.
Schedule::command('seo-pro:404-recheck')->daily();

// --- Broken-link crawler (only when enabled) ----------------------------
Schedule::command('seo-pro:broken-links-scan')->weekly();
Schedule::command('seo-pro:broken-links-recover')->hourly();
Schedule::command('seo-pro:broken-links-prune')->daily();
```

Récapitulatif des fréquences :

| Commande | Fréquence | Motif |
|---|---|---|
| `seo:sitemap` | Quotidienne | Actualiser le sitemap depuis le contenu courant |
| `seo-pro:scan` | Hebdomadaire, quotidienne si le contenu change vite | Réexaminer toutes les cibles |
| `seo-pro:scan-recover` | Horaire | Récupérer les exécutions perdues après l’arrêt d’un worker |
| `seo-pro:scan-prune` | Quotidienne | Appliquer la rétention des scans |
| `seo-pro:redirects-flush-hits` | Toutes les 5 minutes, uniquement si `redirects.hits.flush_immediately=false` | Écrire en base les compteurs regroupés en cache |
| `seo-pro:404-prune` | Quotidienne | Appliquer durée de rétention et plafond de lignes au journal 404 |
| `seo-pro:404-recheck` | Quotidienne | Relire les chemins 404 ouverts et marquer récupérés ceux qui renvoient désormais 200 |
| `seo-pro:broken-links-scan` | Hebdomadaire | Réexplorer les liens ; confirmation sur plusieurs scans |
| `seo-pro:broken-links-recover` | Horaire | Récupérer les explorations perdues après l’arrêt d’un worker |
| `seo-pro:broken-links-prune` | Quotidienne | Appliquer la rétention de l’explorateur |

`seo-pro:scan` et `seo-pro:broken-links-scan` ne font que **mettre le travail en file** ; le worker l’exécute. Les commandes recover/prune s’exécutent directement et sont légères.

::: tip Confirmation des liens cassés sur plusieurs explorations
Un lien est déclaré cassé après `seo-pro.broken_links.mark_broken_after_failures` **scans consécutifs** en échec ; tout succès remet le compteur à zéro. C’est pourquoi l’exploration est planifiée plutôt que ponctuelle : une panne transitoire unique ne suffit pas. Avec le seuil par défaut de 3 et une fréquence hebdomadaire, la confirmation arrive environ deux semaines après la première observation en échec, ou jusqu’à trois semaines après la panne. Augmentez la fréquence ou réduisez le seuil pour confirmer plus vite.
:::

## Réglage des lots de l’explorateur {#batch-tuning-broken-link-crawler}

L’exploration se répartit entre des jobs bornés qui programment leur continuation. Les valeurs par défaut sont finies ; ajustez-les aux capacités de votre site et des hôtes vérifiés, sous `seo-pro.broken_links` :

| Clé | Défaut | Limite |
|---|---|---|
| `max_pages_per_run` | `2000` | Pages récupérées par exécution ; `null` autorise explicitement l’absence de plafond, jamais par défaut |
| `max_links_per_page` | `200` | Liens vérifiés par page |
| `max_total_links` | `null` | Plafond global facultatif de contrôles de liens |
| `batch.max_pages_per_job` | `50` | Pages par job en file |
| `batch.max_links_per_job` | `1500` | Contrôles de liens par job en file |
| `batch.hard_time_budget_seconds` | `180` | Au-delà, le job ne lance **aucune nouvelle récupération** et programme une continuation |
| `batch.dispatch_delay_seconds` | `1` | Délai entre les jobs de continuation |
| `http.timeout` / `http.connect_timeout` | `10` / `5` | Limites par requête |
| `http.max_response_bytes` | Hérite de `seo-pro.http.max_response_bytes` | Plafond appliqué pendant la lecture des corps de réponse des pages et cibles |
| `seed.max_response_bytes` | Hérite du plafond HTTP de l’explorateur ou partagé | Octets XML / `.gz` bruts récupérés pour les URL de départ |
| `seed.max_inflated_bytes` | Hérite du plafond de départ, de l’explorateur ou partagé | Octets décompressés acceptés depuis un sitemap `.gz` |
| `http.per_host_delay_ms` | `0` | Délai entre contrôles par hôte ; à augmenter pour `internal_and_external` |

Gardez `batch.hard_time_budget_seconds` nettement sous le `--timeout` du worker. Une requête en cours ne peut pas être interrompue au milieu : elle reste bornée par `http.timeout`. Le délai du worker doit donc couvrir budget + délai HTTP + marge.

Pour une exploration `internal_and_external`, élargissez `seo-pro.http.scope` ou `seo-pro.http.allowed_hosts` afin que `SsrfGuard` autorise les contrôles sortants. Augmentez `http.per_host_delay_ms` pour limiter la fréquence des requêtes vers un tiers. `seo:doctor` avertit si le périmètre d’exploration est externe mais que le garde bloque tous ces contrôles.

## Horizon / Supervisor {#horizon-supervisor}

### Supervisor {#supervisor}

Un programme par file. Exemple de `/etc/supervisor/conf.d/app-workers.conf` :

```ini
[program:app-queue-default]
command=php /path/to/app/artisan queue:work redis --queue=default --tries=3 --max-time=3600
numprocs=4
autostart=true
autorestart=true
stopwaitsecs=3600
user=www-data

[program:app-queue-seo]
command=php /path/to/app/artisan queue:work redis --queue=seo --tries=3 --timeout=360 --max-time=3600
numprocs=2
autostart=true
autorestart=true
stopwaitsecs=400
user=www-data

[program:app-queue-broken-links]
command=php /path/to/app/artisan queue:work redis --queue=broken_links --tries=1 --timeout=240 --max-time=3600
numprocs=1
autostart=true
autorestart=true
stopwaitsecs=260
user=www-data
```

`stopwaitsecs` doit dépasser le `--timeout` du worker pour qu’un redémarrage propre ne tue pas un job au milieu d’un lot.

### Horizon {#horizon}

Avec Horizon, définissez un superviseur par type de travail dans `config/horizon.php` et laissez-le gérer les processus à la place de Supervisor :

```php
'environments' => [
    'production' => [
        'default' => ['connection' => 'redis', 'queue' => ['default'], 'maxProcesses' => 6],
        'seo'     => ['connection' => 'redis', 'queue' => ['seo'], 'maxProcesses' => 2, 'timeout' => 360],
        'crawler' => ['connection' => 'redis', 'queue' => ['broken_links'], 'maxProcesses' => 1, 'timeout' => 240, 'tries' => 1],
    ],
],
```

## Nouvelles tentatives et gestion des échecs {#retry-failure-handling}

Le job de scan d’une cible possède sa propre politique issue de la configuration ; il ne dépend **pas** du `--tries` du worker :

| Clé | Défaut | Signification |
|---|---|---|
| `seo-pro.scan.tries` | `3` | Tentatives par job cible |
| `seo-pro.scan.backoff` | `30` | Secondes entre tentatives |
| `seo-pro.scan.timeout` | `300` | Délai maximal par job cible ; le verrou de chevauchement expire après ce délai + 60 |

Un job ayant épuisé ses tentatives marque sa cible **en échec**. L’exécution se termine quand même, en `partial` ou `failed` : un échec de cible traité ne laisse pas le scan en `running`. Un worker tué avant l’enregistrement de la progression nécessite encore la récupération ci-dessous. Les échecs sont conservés dans la table standard `failed_jobs`, à gérer normalement :

```bash
php artisan queue:failed
php artisan queue:retry all
```

Planifiez `queue:prune-failed` avec les commandes SEO pour limiter cette table :

```php
Schedule::command('queue:prune-failed --hours=168')->daily();
```

L’exploration utilise `--tries=1`. Un job arrêté est repris par la continuation suivante lorsque le battement du bail devient périmé, ou par `seo-pro:broken-links-recover`. Des tentatives au niveau de la file ne feraient que dupliquer le travail.

## Récupération {#recovery}

L’arrêt d’un worker au milieu d’un job empêche la progression de se régulariser seule. Deux commandes ferment ces exécutions ; planifiez-les **chaque heure** :

- `seo-pro:scan-recover` marque échoués les scans on-page sans progression depuis `seo-pro.scan.recovery.stuck_scan_timeout_hours`, 2 heures par défaut.
- `seo-pro:broken-links-recover` récupère les explorations dont le battement de bail est périmé, selon `seo-pro.broken_links.recovery.stuck_scan_timeout_hours`, 2 heures par défaut. Elle les marque échouées et libère la place réservée à une seule exécution active par périmètre.

`seo:doctor` présente cela comme une **preuve de progression récente**. Une fois les scans utilisés, il signale les exécutions bloquées et indique la commande recover. Il ne peut pas prouver que votre cron fonctionne réellement ; il décrit ce que montre l’historique des exécutions.

## Rétention {#retention}

Gardez les tables bornées. Valeurs par défaut, toutes sous `seo-pro.*` ; `null` désactive la purge correspondante :

| Données | Configuration | Défaut | Commande |
|---|---|---|---|
| Exécutions de scan et problèmes | `scan.retention.scan_runs_days` | `90` | `seo-pro:scan-prune` |
| Journal 404 | `monitor_404.retention_days`, avec `max_rows` à `10000` | `90` | `seo-pro:404-prune` |
| Exécutions d’exploration | `broken_links.retention.scan_runs_days` | `90` | `seo-pro:broken-links-prune` |
| Résultats résolus | `broken_links.retention.resolved_findings_days` | `30` | `seo-pro:broken-links-prune` |

## Télémétrie opérationnelle {#operational-telemetry}

Chaque exécution terminée, scan on-page **ou** exploration de liens, émet une ligne structurée dans les journaux. Vous disposez ainsi d’une trace de métriques sans panneau. La charge utile ne contient que des comptes et des durées, sans URL, corps, en-têtes ni données de visiteurs :

| Métrique | Scan | Exploration |
|---|:--:|:--:|
| `pages_fetched` | — | ✓ |
| `links_checked` | — | ✓ |
| `links_broken` | — | ✓ |
| `blocked_urls`, cibles refusées par la protection SSRF | — | ✓ |
| `transient_failures`, erreurs réseau réexaminées au prochain scan | — | ✓ |
| `total_targets` / `completed_targets` / `failed_targets` | ✓ | — |
| `issues_found` | ✓ | — |
| `duration_seconds` | ✓ | ✓ |
| `queue_lag_seconds`, de la mise en file au premier lot | ✓ | ✓ |

Configurez-la dans `seo-pro.telemetry` :

```php
'telemetry' => [
    'enabled' => env('SEO_PRO_TELEMETRY_ENABLED', true),
    'channel' => env('SEO_PRO_TELEMETRY_CHANNEL'), // null = default log channel
    'level'   => env('SEO_PRO_TELEMETRY_LEVEL', 'info'),
],
```

Choisissez un canal de journal dédié avec `channel` pour envoyer ces lignes vers Loki, Datadog ou CloudWatch sans les mélanger aux journaux de l’application :

```php
// config/logging.php
'channels' => [
    'seo' => ['driver' => 'single', 'path' => storage_path('logs/seo.log'), 'level' => 'info'],
],
```

```dotenv
SEO_PRO_TELEMETRY_CHANNEL=seo
```

Pour un traitement plus élaboré, abonnez-vous directement aux événements. Chacun expose la même charge utile `metrics()` :

```php
use Rankbeam\Seo\Pro\Events\SeoScanCompleted;
use Rankbeam\Seo\Pro\BrokenLinks\Events\BrokenLinkScanCompleted;

Event::listen(SeoScanCompleted::class, function (SeoScanCompleted $event) {
    Metrics::gauge('seo.scan.issues', $event->metrics()['issues_found']);
});

Event::listen(BrokenLinkScanCompleted::class, function (BrokenLinkScanCompleted $event) {
    Metrics::gauge('seo.crawl.broken', $event->metrics()['links_broken']);
});
```

La télémétrie fonctionne au mieux : un canal mal configuré ne peut pas faire échouer un scan.

## Déploiement indépendant de Filament {#filament-independent-deployment}

Rien sur cette page ne nécessite de panneau. Moteur, commandes, files, ordonnanceur, récupération, rétention et télémétrie sont identiques en headless. `SeoProPlugin` ajoute uniquement des **vues** : progression des scans, tableau des problèmes, gestion des redirections, suivi des 404 et tableau de bord des liens cassés. Déployez le moteur et utilisez la CLI avec l’ordonnanceur ; ajoutez le panneau plus tard, ou jamais, sans nouvelle migration ni reprise du travail. Consultez [l’utilisation headless](/fr/pro/headless) pour toutes les commandes.

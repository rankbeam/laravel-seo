---
description: "Un explorateur borné et reprenable qui repère les liens sans destination accessible : routes internes mortes, corrigeables par redirection en un clic, et liens externes cassés facultatifs. Désactivé par défaut."
---

# Explorateur de liens cassés {#broken-link-crawler}

Un **explorateur borné et reprenable** parcourt votre site, suit les liens de chaque page et enregistre ceux qui n’aboutissent pas : liens **internes** cassés, routes mortes sur votre hôte pouvant être corrigées par redirection en un clic, et éventuellement liens **externes** cassés. Il est **désactivé par défaut**.

Trois principes guident la conception :

- **Travail borné et reprenable.** Une exploration se répartit sur plusieurs petits jobs en file d’attente, chacun limité à un nombre de pages. Ils planifient leur continuation jusqu’à la fin ou jusqu’aux plafonds. L’exécution complète est aussi limitée : 2 000 pages par défaut. `null` autorise explicitement un nombre illimité, jamais par défaut ; les autres plafonds de lots et de temps continuent de s’appliquer. Adaptez limites et délais aux capacités du site et du serveur.
- **Périmètre prudent par défaut.** `internal_only` vérifie uniquement les liens sur votre hôte, sans requête à un tiers. Chaque récupération, interne ou externe, passe par le **SsrfGuard** partagé : protocoles autorisés, périmètre d’hôtes et rejet des adresses privées. Les liens externes nécessitent une activation explicite et restent protégés.
- **Séparation du score SEO.** Les résultats disposent de leurs propres tables et n’écrivent jamais dans `seo_scan_issues` ni dans le score de 0 à 100. Des liens sortants cassés ne modifient donc pas le score de la page. Ils constituent un suivi opérationnel distinct.

## Vues disponibles {#what-you-get}

Dans le tableau de bord Filament, uniquement si la fonction est activée :

- **Résumé des liens cassés** : nombres de liens ouverts internes/externes et dernière exploration, avec lien vers le tableau des résultats.
- **Exploration des liens cassés** : progression en direct, pages parcourues, liens vérifiés et liens cassés trouvés.
- **Liens cassés par scan** : tendance sur les explorations récentes.
- **Ressource de résultats** : chaque lien cassé `source → cible`, avec filtres et création de redirection pour les liens internes.

En headless, les mêmes données sont accessibles par les commandes `seo-pro:broken-links-*`.

## Pourquoi la fonction est désactivée par défaut {#why-it-s-off-by-default}

Contrairement au rendu passif et au calcul du score, l’explorateur **effectue des requêtes réseau** et nécessite de l’infrastructure. Son activation est donc volontaire ; il ne démarre pas silencieusement à l’installation.

- Ses deux tables principales doivent être **publiées**, comme toutes les migrations Pro, puis migrées avant que l’interface puisse les interroger. Les inspections typées utilisent aussi `seo_broken_link_inspections`.
- L’exploration passe par une **file dédiée** et nécessite un **worker**. Sans worker, elle ne progresse pas.
- La confirmation repose sur **plusieurs explorations**, comme expliqué plus bas. La fonction est conçue pour être **planifiée** sur plusieurs semaines, pas pour confirmer immédiatement tous les liens cassés à l’activation.

## Installation {#setup}

```dotenv
SEO_PRO_BROKEN_LINKS_ENABLED=true
```

Appliquez ensuite les migrations. `seo-pro:install` publie et exécute toutes les migrations Pro ; la commande est idempotente et peut être relancée :

```bash
php artisan seo-pro:install
```

Lancez un **worker dédié** à la file d’exploration `seo-broken-links`, afin qu’une longue exploration ne passe pas devant les jobs destinés aux utilisateurs :

```bash
# --tries=1: a dead job is reclaimed by the next continuation, so queue-level
#   retries are unnecessary. --timeout must exceed the batch's hard time budget
#   (seo-pro.broken_links.batch.hard_time_budget_seconds, default 180) plus the
#   HTTP timeout, so a batch is never killed mid-bookkeeping.
php artisan queue:work --queue=seo-broken-links --tries=1 --timeout=240
```

Vérifiez la configuration : `seo:doctor` contrôle l’activation, les tables et l’utilisation d’une vraie connexion de file, autre que `sync`, avec une correction précise pour chaque problème :

```bash
php artisan seo:doctor
```

Consultez la [configuration en production](/fr/pro/production) pour l’organisation complète des files, Redis, Supervisor, connexions dédiées et réglages des lots.

## Lancer une exploration {#running-a-crawl}

Utilisez l’action **Scan now** du tableau de bord ou la ligne de commande :

```bash
# Queue a crawl (internal links only, the default).
php artisan seo-pro:broken-links-scan

# Also check outbound/external links. Each external host must still pass the
# SsrfGuard, so widen seo-pro.http.scope (or allowed_hosts) for the fetch to be
# permitted, and raise http.per_host_delay_ms so a third-party host is never hit
# too fast.
php artisan seo-pro:broken-links-scan --scope=internal_and_external
```

Les deux commandes ne font que **mettre l’exploration en file** ; le worker réalise le travail.

## Confirmation d’un lien cassé {#how-a-link-gets-flagged}

Un lien n’est déclaré cassé qu’après `seo-pro.broken_links.mark_broken_after_failures` **explorations consécutives** en échec. Tout succès remet le compteur à zéro ; le seuil par défaut est **3**. Une panne transitoire unique ne suffit donc pas. C’est pourquoi l’exploration est conçue pour être **planifiée**. Avec une fréquence hebdomadaire et le seuil par défaut, trois explorations en échec confirment le lien environ deux semaines après la première observation, ou jusqu’à environ trois semaines après la panne. Augmentez la fréquence ou réduisez le seuil si une confirmation plus rapide est nécessaire.

## Inspections typées des liens {#typed-link-inspections}

Chaque lien parcouru passe aussi par des **inspections typées** : cohérence des barres obliques finales, encodages problématiques, chaînes de redirections, href `javascript:`, ancres internes absentes, textes de liens peu descriptifs, etc. Chaque inspection porte une **gravité** fixe, `critical` · `warning` · `notice`, avec le même vocabulaire que les [problèmes de scan](/fr/pro/scan-issues), ce qui permet un contrôle bloquant CI commun. Elle est enregistrée par exploration dans `seo_broken_link_inspections`. Contrairement au *résultat de lien cassé* confirmé après plusieurs explorations, l’inspection est un instantané par exécution : elle apparaît **dès la première exploration**, comme nécessaire en CI.

### Référence des inspections {#inspection-reference}

| Inspection | Gravité | Signalement | Périmètre |
| --- | --- | --- | --- |
| `broken_link` | critical | La cible renvoie HTTP ≥ 400 | Tout lien |
| `redirect_chain` | notice · warning | La cible nécessite une redirection ; `warning` au-delà de `redirect_chain_warning_hops` | Tout lien |
| `link_unreachable` | notice | Cible inaccessible durant cette exploration : erreur réseau, délai ou blocage, éventuellement transitoire | Tout lien |
| `insecure_link` | warning | Lien `http://` sur un site `https`, avec réduction de la sécurité du transport | Tout lien |
| `trailing_slash` | notice | Chemin interne contraire à la convention déclarée de barre oblique finale ; **désactivé sans réglage `trailing_slash`** | Interne |
| `double_slash_url` | warning | Chemin interne contenant `//`, segment vide | Interne |
| `duplicate_query_param` | notice | Clé de requête répétée, `?a=1&a=2` ; syntaxe de tableau `key[]` exemptée | Interne |
| `non_ascii_url` | notice | Caractères non ASCII non encodés dans le chemin interne | Interne |
| `uppercase_url` | notice | Majuscules dans le chemin interne ; examiner les variantes de casse servies séparément | Interne |
| `underscore_in_url` | notice | Traits de soulignement dans le chemin interne ; les traits d’union sont le séparateur conseillé pour le SEO | Interne |
| `javascript_link` | warning | Ancre avec href `javascript:`, sans destination explorable ordinaire | Toute ancre |
| `missing_fragment` | warning | `#fragment` sur la même page sans `id`/`name` correspondant | Même page |
| `non_descriptive_anchor` | notice | Texte générique, comme « click here » ou « read more », ou URL brute | Toute ancre |
| `absolute_internal_link` | notice | Lien interne écrit comme URL absolue plutôt que chemin relatif à la racine | Interne |

Les inspections de forme, barre oblique finale, casse, encodage ou double barre, concernent uniquement les liens **internes** : le style d’URL d’un autre site ne relève pas de votre convention. Redirections, liens cassés, cibles inaccessibles et liens non sécurisés concernent tous les liens. Les liens vers vos propres routes de framework et ressources statiques sont ignorés pour limiter le bruit au premier passage ; voir `exclude_paths` / `exclude_extensions` ci-dessous.

Chaque lien est récupéré à **l’URL exacte écrite dans la page**, avec uniquement le `#fragment` retiré. Une redirection canonique serveur, comme `/about/ → /about`, est ainsi observée et signalée par `redirect_chain`, au lieu d’être masquée par une normalisation préalable. Chaque forme écrite distincte d’un lien dans une page est inspectée : `/page#ok` et `/page#missing`, ou `/a//b` et `/a/b`, sont toutes examinées. Le *résultat de lien cassé* sous-jacent réunit toujours les alias d’une cible sous une identité. Les inspections sont enregistrées par `(page, cible, inspection)` : plusieurs ancres défaillantes d’une cible produisent donc une ligne `missing_fragment` avec un exemple, pas une ligne par ancre.

### Régler les inspections {#tuning-the-taxonomy}

Tout se trouve sous `seo-pro.broken_links.inspections` :

```php
'inspections' => [
    // Master switch. false = the crawler behaves exactly as before (broken-link
    // findings only, no inspection rows, zero added work).
    'enabled' => env('SEO_PRO_BROKEN_LINKS_INSPECTIONS', true),

    // The active rule set — remove a class to silence that inspection per client.
    'rules' => [ /* the 14 rule classes, see config/seo-pro.php */ ],

    // "Pre-learn your own noise": links whose TARGET path matches one of these
    // globs are not inspected at all (framework internals, generated routes).
    'exclude_paths' => ['/livewire/*', '/filament/*', '/admin/*', /* … */],

    // Static assets are still crawled for broken-link detection, but raise no
    // trailing-slash / casing / underscore hygiene noise.
    'exclude_extensions' => ['css', 'js', 'png', 'pdf', /* … */],

    // The site-wide trailing-slash convention. null = don't enforce a style
    // (a server-side slash redirect still shows up under redirect_chain);
    // 'always' or 'never' to enforce one.
    'trailing_slash' => null,

    'redirect_chain_warning_hops' => 2,
    'non_descriptive_anchors' => ['click here', 'read more', /* … */],
    'evidence_sample' => 5, // example rows shown per inspection in a report
],
```

**Désactivez une règle** en retirant sa classe de `rules`, ou **toutes les inspections** avec `SEO_PRO_BROKEN_LINKS_INSPECTIONS=false`. Deux règles méritent attention :

- `trailing_slash` est **désactivé jusqu’à la déclaration d’une convention**, `'always'` ou `'never'`. Si le site sert `/x` et `/x/` avec 200, aucun style n’est intrinsèquement contraire à une convention non définie. Si le serveur impose déjà une canonique par redirection, celle-ci apparaît dans `redirect_chain`.
- `absolute_internal_link` se déclenche pour **tout** lien interne écrit en URL absolue. Si c’est votre convention, vous obtiendrez beaucoup de lignes `notice` sans gravité. Retirez cette règle de `rules` pour les supprimer.

## Intégration continue {#continuous-integration}

Le contrôle des liens et l’[audit SEO](/fr/pro/scan-issues) peuvent **faire échouer un build** et **écrire un rapport**, pour utiliser Rankbeam comme contrôle qualité. `--fail-on-error` correspond au niveau `critical`, lien cassé ou problème critique. `--fail-on-warning` échoue sur `critical` **ou** `warning`. Aucun niveau « error » distinct n’existe.

```bash
# The audit: run synchronously and fail on any open critical issue, writing a
# machine-readable report. --fail-on-* require --sync — a queued scan has no
# results yet when the command returns.
php artisan seo-pro:scan --sync --fail-on-error --report=reports/audit.json

# The link scan is asynchronous, so gate it in two steps: crawl, drain the
# queue, then read the persisted results.
php artisan seo-pro:broken-links-scan
php artisan queue:work --queue=seo-broken-links --stop-when-empty
php artisan seo-pro:broken-links-status --fail-on-error --report=reports/links.md --format=md
```

`--report=<file|dir>` écrit le rapport ; un dossier fait déduire le nom du fichier. `--format` accepte `json`, par défaut, `md` ou `html`. JSON convient à l’analyse dans un pipeline ; HTML produit une page autonome à joindre à une exécution.

### GitHub Actions {#github-actions}

L’explorateur récupère vos pages par HTTP : la CI doit lui fournir du contenu accessible, application servie localement comme ci-dessous ou URL de préproduction via `SEO_PRO_BROKEN_LINKS_BASE_URL`. Enregistrez modèles et sitemap pour fournir les URL de départ.

```yaml
name: SEO gate
on: [pull_request]

jobs:
  seo:
    runs-on: ubuntu-latest
    env:
      APP_URL: http://127.0.0.1:8000
      SEO_PRO_BROKEN_LINKS_ENABLED: true
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3' }
      - run: composer install --no-interaction --prefer-dist

      - run: |
          cp .env.example .env
          php artisan key:generate
          touch database/database.sqlite
          php artisan migrate --force
          php artisan seo-pro:install   # publishes + runs the Pro migrations

      # Serve the app so the crawler can reach it.
      - run: php artisan serve --port=8000 &

      - name: SEO audit gate
        run: php artisan seo-pro:scan --sync --fail-on-warning --report=reports/audit.md --format=md

      - name: Broken-link + inspection gate
        run: |
          php artisan seo-pro:broken-links-scan
          php artisan queue:work --queue=seo-broken-links --stop-when-empty
          php artisan seo-pro:broken-links-status --fail-on-error --report=reports/links.md --format=md

      # Always upload the reports — when a gate fails the job, you still get the
      # artifact explaining why.
      - if: always()
        uses: actions/upload-artifact@v4
        with:
          name: seo-reports
          path: reports/
```

## Planification {#scheduling}

Enregistrez l’exploration et ses tâches de maintenance dans `routes/console.php` :

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('seo-pro:broken-links-scan')->weekly();     // re-crawl
Schedule::command('seo-pro:broken-links-recover')->hourly();  // reclaim dead-worker runs
Schedule::command('seo-pro:broken-links-prune')->daily();     // retention
```

## Référence des commandes {#command-reference}

| Commande | Fonction |
| --- | --- |
| `seo-pro:broken-links-scan` | Mettre en file une exploration bornée et reprenable ; `--scope=internal_only\|internal_and_external`, `--url=*` pour des départs supplémentaires |
| `seo-pro:broken-links-status` | Résumé de la dernière exploration, liens cassés ouverts et nombres d’inspections ; **contrôle bloquant CI** avec `--fail-on-error`, `--fail-on-warning`, `--report=<file\|dir>`, `--format=json\|md\|html` |
| `seo-pro:broken-links-cancel` | Annuler une exploration active ou en attente ; `{run?}` désigne la dernière active par défaut |
| `seo-pro:broken-links-recover` | Marquer comme échouées les explorations abandonnées par un worker arrêté, avec bail périmé |
| `seo-pro:broken-links-prune` | Appliquer la rétention aux anciennes exécutions et aux résultats résolus |

## Ajuster les limites {#tuning}

Les limites de l’exploration, pages par exécution, liens par page, plafonds par job, budget de temps strict et délais par hôte, se trouvent sous `seo-pro.broken_links`. Les valeurs par défaut sont prudentes et finies. Consultez le [tableau des réglages de lots en production](/fr/pro/production) avant de les augmenter.

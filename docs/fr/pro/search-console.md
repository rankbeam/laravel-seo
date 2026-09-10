---
description: "Un panneau Google Search Console strictement en lecture seule : requêtes et pages principales, impressions, clics, CTR et position, reliés aux pages connues du scanner. Désactivé par défaut."
---

# Search Console en lecture seule {#search-console-read-only}

Un panneau Google Search Console **en lecture seule** : vos principales requêtes et pages, avec **impressions, clics, CTR et position moyenne**, reliées aux pages déjà connues du scanner. Vous pouvez ainsi voir au même endroit qu’une page présente des problèmes **et** perd des impressions. La fonction est **désactivée par défaut**.

Trois principes guident la conception :

- **Lecture seule stricte.** L’intégration demande un seul scope OAuth, `webmasters.readonly`, fixé dans le package. Elle lit Search Analytics uniquement : elle ne soumet jamais de sitemap, ne demande pas d’indexation et ne modifie rien dans Search Console. Aucun réglage ne permet d’élargir le scope.
- **Votre propriété, vos identifiants.** Les requêtes partent de *votre serveur* directement vers Google, avec *vos* identifiants de compte de service ou OAuth. Le package ne sert pas d’intermédiaire, ne mesure ni ne revend cette consommation et n’envoie aucune télémétrie.
- **Les erreurs restent dans leur contexte.** Identifiants absents, 403, quotas dépassés ou délais expirés produisent un message dans la page sans interrompre son rendu. La commande de synchronisation historique signale les échecs et arrête la récupération des jours suivants, comme décrit plus bas.

## Vues disponibles {#what-you-get}

- **Pages à examiner** : pages avec des **problèmes de scan ouverts** qui **reçoivent encore du trafic de recherche**, classées par priorité d’opportunité, soit le plus d’impressions parmi les pages concernées. Commencez vos corrections par celles-ci.
- **Pages principales** et **requêtes principales** : les tableaux habituels de Search Analytics.

Dans Filament, la page **Search Console** apparaît dans le groupe de navigation *SEO*, uniquement lorsque l’intégration est activée. En headless, les mêmes métriques sont accessibles via `seo-pro:search-console` et `SeoPro::searchConsole()`.

## Installation {#setup}

Vous avez besoin d’identifiants Google autorisés à lire la propriété Search Console. Deux modes sont disponibles ; un **compte de service** est le plus simple pour un serveur.

### Compte de service, conseillé {#service-account-recommended}

1. Dans Google Cloud, activez **Search Console API**, créez un **compte de service** et téléchargez sa clé JSON.
2. Dans Search Console → *Paramètres → Utilisateurs et autorisations*, ajoutez l’adresse du compte de service, `…@….iam.gserviceaccount.com`, comme utilisateur. L’accès restreint suffit à la lecture seule.
3. Configurez la clé et la propriété dans le package :

```dotenv
SEO_PRO_GSC_ENABLED=true
SEO_PRO_GSC_CONNECTION=service_account
# The raw JSON, OR an absolute path to the .json key file:
SEO_PRO_GSC_CREDENTIALS=/etc/secrets/gsc-service-account.json
# The property exactly as it appears in Search Console:
SEO_PRO_GSC_SITE_URL=https://example.com/
# or a Domain property:  SEO_PRO_GSC_SITE_URL=sc-domain:example.com
```

Sans `SEO_PRO_GSC_SITE_URL`, une propriété de type préfixe d’URL est déduite de `app.url`.

### OAuth avec jeton de renouvellement hors ligne {#oauth-offline-refresh-token}

Si vous disposez d’un client OAuth et d’un **refresh token** de longue durée, de préférence autorisé uniquement pour `webmasters.readonly`, configurez-les ci-dessous. Chaque renouvellement demande ce scope. Le package rejette le jeton reçu si la réponse ne confirme pas explicitement ce scope de lecture seule exact ; il ne suppose pas que Google restreint toujours une autorisation initiale plus large.

```dotenv
SEO_PRO_GSC_ENABLED=true
SEO_PRO_GSC_CONNECTION=oauth
SEO_PRO_GSC_OAUTH_CLIENT_ID=xxxx.apps.googleusercontent.com
SEO_PRO_GSC_OAUTH_CLIENT_SECRET=...
SEO_PRO_GSC_OAUTH_REFRESH_TOKEN=1//...
SEO_PRO_GSC_SITE_URL=https://example.com/
```

### Publier la migration des jetons {#publish-the-token-migration}

Le cache chiffré des jetons d’accès se trouve dans `seo_gsc_tokens`. Publiez et appliquez la migration une fois :

```bash
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate
```

Confirmez ensuite la configuration avec `php artisan seo:doctor`. Il indique si Search Console est activé et configuré, sans appel réseau ni affichage de secret.

## Utilisation headless {#headless-usage}

```bash
# Pages with open issues AND search traffic (the default view):
php artisan seo-pro:search-console

# Top pages / top queries:
php artisan seo-pro:search-console --view=pages
php artisan seo-pro:search-console --view=queries

# Window + size, and machine-readable output:
php artisan seo-pro:search-console --view=queries --days=7 --limit=25 --json
```

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$gsc = SeoPro::searchConsole();

$gsc->isConfigured();             // bool, no network
$gsc->topQueries();               // SearchConsoleResult (rows: GscRow[])
$gsc->topPages(days: 7);          // SearchConsoleResult
$gsc->pagesNeedingAttention();    // rows annotated with issueCount + score

$result = $gsc->topQueries();
if ($result->ok) {
    foreach ($result->rows as $row) {
        // $row->key, ->clicks, ->impressions, ->ctrPercent(), ->position
    }
} else {
    // $result->errorCode (a stable code), $result->errorMessage (sanitized)
}
```

## Historique des métriques {#historical-metrics}

Le panneau et la commande ci-dessus lisent une **fenêtre glissante en direct** ; Search Console est alors le seul stockage. Pour conserver un **historique quotidien** interrogeable sur une période passée, lancez la synchronisation. Elle enregistre les métriques par jour et par requête, puis par jour et par page, dans `seo_gsc_metrics` :

```bash
# Publish + run the migration once (creates seo_gsc_metrics):
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate

# Backfill on the first run, then keep it current — schedule it daily:
php artisan seo-pro:gsc-sync

# Pull a specific number of days back (forces a full re-pull of that window):
php artisan seo-pro:gsc-sync --days=180
```

```php
// app/Console/Kernel.php (or bootstrap/app.php withSchedule)
$schedule->command('seo-pro:gsc-sync')->daily();
```

- **La première exécution récupère l’historique** sur `sync.backfill_days`, 90 jours par défaut. Search Console conserve environ 16 mois : augmentez la valeur pour remonter davantage. Les exécutions suivantes **reprennent à la dernière date enregistrée** et relisent les `sync.overlap_days` derniers jours pour intégrer la finalisation tardive des données récentes. La fenêtre se termine toujours trois jours avant aujourd’hui, pour tenir compte du retard des données.
- **Idempotence.** Les lignes sont créées ou mises à jour selon `(date, dimension, key)` ; une nouvelle exécution ne crée donc pas de doublons. Un jour en échec, par exemple à cause d’un quota, arrête proprement l’exécution et indique le nombre de lignes enregistrées. La suivante reprend au point atteint.
- **Usages.** Les **variations** Search Console du [rapport en marque blanche](/fr/pro/reports) passent à une vraie comparaison entre périodes, période courante et période précédente de même durée, dès que la table couvre les deux. Elles remplacent alors la différence entre instantanés de rapports. Cet historique sert aussi de base aux analyses de mots-clés.

Seules des métriques agrégées sont enregistrées : texte de requête, URL de page et quatre mesures quotidiennes, clics, impressions, CTR et position. Aucune donnée par utilisateur ou par requête HTTP individuelle n’est récupérée ni écrite.

## Traitement des données et sécurité {#data-handling-security}

- **Scope de lecture seule vérifié.** Le JWT du compte de service demande uniquement `webmasters.readonly`. Les renouvellements OAuth font de même ; le package rejette une réponse dont le scope est absent ou plus large. Utilisez des identifiants autorisés uniquement en lecture. Le package ne contient aucun appel modifiant Search Console.
- **Identifiants dans l’environnement.** La clé du compte de service, le secret OAuth et le refresh token sont lus au moment de l’appel depuis les variables d’environnement **nommées**, comme la clé IA. `php artisan config:cache` ne les écrit donc jamais dans `bootstrap/cache/config.php`. Rendez-les disponibles dans l’environnement du processus lorsque le cache de configuration empêche le chargement de `.env`.
- **Jetons chiffrés au repos.** Le jeton d’accès de courte durée obtenu depuis vos identifiants est conservé **chiffré** avec la clé d’application dans `seo_gsc_tokens`, puis réutilisé jusqu’à l’approche de son expiration. L’échange de jeton n’a donc pas lieu à chaque affichage. Les identifiants de longue durée ne sont jamais stockés en base, seulement dans votre environnement.
- **Protection SSRF pour chaque requête.** L’échange de jeton et l’appel Search Analytics passent par le `SsrfGuard` partagé : HTTPS uniquement, avec résolution de l’hôte vers une adresse publique. Les redirections sont désactivées pour empêcher un renvoi vers un service interne.
- **Aucun secret dans les journaux.** Jetons d’accès, clés et en-têtes d’authentification ne sont jamais journalisés. Une erreur API n’expose que le message de Google nettoyé et limité en longueur.
- **Métriques mises en cache localement** pendant `seo-pro.search_console.cache_ttl` secondes, 30 minutes par défaut, pour éviter un nouvel appel API à chaque rendu. Le panneau et la commande en direct ne conservent rien au-delà de ce cache et du jeton d’accès chiffré. Seule la commande facultative `seo-pro:gsc-sync` enregistre durablement des métriques dans `seo_gsc_metrics` : agrégats quotidiens par requête/page, sans données individuelles.

## Référence de configuration {#configuration-reference}

Toutes les clés se trouvent sous `config/seo-pro.php` → `search_console` :

| Clé | Défaut | Rôle |
| --- | --- | --- |
| `enabled` | `false` | Activation générale, `SEO_PRO_GSC_ENABLED`. |
| `connection` | `service_account` | `service_account` ou `oauth`. |
| `site_url` | Déduit de `app.url` | Propriété : `https://example.com/` ou `sc-domain:example.com`. |
| `service_account.credentials_env` | `SEO_PRO_GSC_CREDENTIALS` | **Nom** de la variable contenant la clé JSON ou son chemin. |
| `oauth.client_id` | — | Identifiant du client OAuth, non secret. |
| `oauth.client_secret_env` | `SEO_PRO_GSC_OAUTH_CLIENT_SECRET` | **Nom** de la variable contenant le secret du client. |
| `oauth.refresh_token_env` | `SEO_PRO_GSC_OAUTH_REFRESH_TOKEN` | **Nom** de la variable contenant le refresh token. |
| `default_days` | `28` | Fenêtre du rapport, se terminant trois jours plus tôt à cause du retard GSC. |
| `row_limit` | `100` | Nombre de lignes principales par rapport ; maximum API : 25 000. |
| `cache_ttl` | `1800` | Durée en secondes du cache d’un rapport récupéré. |
| `sync.backfill_days` | `90` | Jours récupérés par le premier `gsc-sync` sur table vide. |
| `sync.overlap_days` | `2` | Derniers jours relus à chaque exécution pour la finalisation tardive. |
| `sync.row_limit` | `5000` | Nombre maximal de lignes demandées par jour et par dimension. |

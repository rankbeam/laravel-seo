---
description: "Enregistrez les requêtes observées des robots IA : robots déclarés, fréquence, dernière URL et dernier statut. Le volet d’observation du contrôle des robots IA."
---

# Suivi des robots IA {#ai-bot-monitor}

Les requêtes sont attribuées par **correspondance du user-agent**, sans vérification de l’identité du robot. Le suivi enregistre des requêtes observées ; un user-agent peut être usurpé.

Le [contrôle des robots IA](/fr/guide/ai-crawlers) dans le cœur définit ce que `robots.txt` leur *demande*. Le **suivi des robots IA** de Pro enregistre l’autre volet : les requêtes *réellement observées*, les robots IA déclarés, leur fréquence, la dernière URL et le dernier statut HTTP rencontrés.

Il réutilise les mécanismes du suivi des 404 : middleware global terminable, modèle avec upsert et compteur de visites, même politique de confidentialité. Il utilise cependant le **robot** comme clé, au lieu du chemin, et enregistre **tous** les statuts de réponse, pour les robots IA que le suivi des 404 exclut volontairement. L’identification réutilise `AiCrawlerRegistry` du cœur : la politique robots.txt et le trafic observé partagent ainsi le même catalogue.

::: tip Nécessite Core ≥ 3.3
Le suivi identifie les robots avec le catalogue IA du cœur, accessible via [`SEO::aiCrawlers()`](/fr/guide/ai-crawlers). Avec un cœur plus ancien, il reste inactif.
:::

## Activer le suivi {#enabling-it}

Désactivé par défaut. Une fois activé, le middleware global enregistre les robots correspondants après chaque réponse, sans retarder l’envoi de la page :

```php
// config/seo-pro.php
'ai_bots' => [
    'enabled' => true,
],
```

Le middleware s’enregistre automatiquement ; désactivez cet enregistrement avec `ai_bots.auto_register_middleware` si nécessaire. Une ligne par robot connu est créée ou mise à jour : la taille de la table reste donc limitée par le catalogue.

## Lire le journal {#reading-the-log}

### Headless {#headless}

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::aiBots()->hits();                    // every bot seen, most-hit first
SeoPro::aiBots()->forPurpose('ai_training'); // just the trainers
SeoPro::aiBots()->totalHits();               // total recorded fetches
SeoPro::aiBots()->isEnabled();
```

Chaque ligne expose `bot`, `label`, `operator`, `purpose`, `hit_count`, `last_path`, `last_status`, `first_seen_at` et `last_seen_at`.

### Artisan {#artisan}

```bash
php artisan seo-pro:ai-bots                       # most-hit first
php artisan seo-pro:ai-bots --purpose=ai_training # filter by purpose
php artisan seo-pro:ai-bots-prune                 # drop stale bots + old daily buckets
```

### Filament {#filament}

Lorsque le plugin Pro est enregistré, un tableau **AI Bots** apparaît dans le groupe de navigation SEO : robot, opérateur, finalité, nombre de requêtes, dernier statut, dernier chemin et dernière observation. Il est en lecture seule et filtrable par finalité.

## Confidentialité {#privacy}

Comme pour le suivi des 404, **aucune IP n’est enregistrée par défaut**. L’option `ai_bots.hash_ip` conserve uniquement un SHA-256 avec clé, `ip_hash` ; l’IP brute n’est jamais écrite.

```php
'ai_bots' => [
    'enabled' => true,
    'hash_ip' => false,            // true → keyed sha256 only
    'exclude_paths' => ['/filament/*', '/livewire/*', /* … */],
    'max_path_length' => 500,
    'retention_days' => 90,        // lifetime rows; seo-pro:ai-bots-prune; null disables

    // Day-granular per-path buckets (below)
    'daily_enabled' => true,       // false → keep only the lifetime leaderboard
    'daily_max_paths' => 500,      // distinct paths tracked per bot per day
    'daily_retention_days' => 90,  // prune buckets older than this; null disables
],
```

## Métriques par période : regroupements quotidiens {#period-metrics-daily-buckets}

La table cumulée conserve une ligne par robot. Elle convient à un classement global, mais ne permet pas de compter les *requêtes* ou *URL distinctes* **sur une période précise**. Avec `daily_enabled`, activé par défaut, chaque requête est aussi enregistrée dans un regroupement par jour et chemin, `seo_ai_bot_daily`. Le [rapport en marque blanche](/fr/pro/reports) affiche ainsi de **vrais** chiffres par période, requêtes depuis le dernier rapport et URL distinctes sur la période, plutôt qu’une différence entre cumuls.

Le volume reste borné, comme dans le journal cumulé à une ligne par robot :

- un **plafond de chemins distincts par robot et par jour**, `daily_max_paths`. Au-delà, les nouveaux chemins du robot sont réunis dans un regroupement de dépassement unique. Le total quotidien des requêtes reste exact sans multiplication incontrôlée des lignes. Un nombre d’URL distinctes ayant atteint le plafond est affiché sous la forme « N+ » ;
- une **durée de rétention**, `daily_retention_days`, appliquée par `seo-pro:ai-bots-prune`.

Définissez `daily_enabled` à `false` pour ne conserver que le classement cumulé. Le rapport utilise alors la différence avec l’instantané du rapport précédent pour « depuis le dernier rapport ». Les regroupements existants sont ignorés afin de ne pas lire une table devenue périmée.

Les chiffres par période ont une **précision quotidienne** : « depuis le dernier rapport » compte des jours entiers à partir du jour du rapport précédent. Une requête de ce jour peut donc se situer avant ou après l’heure exacte de génération. À une fréquence habituelle, quotidienne, hebdomadaire ou mensuelle, cet écart de frontière est limité.

## Passer de l’observation au contrôle {#turning-observation-into-control}

Le suivi indique les *user-agents observés* ; le [contrôle des robots IA](/fr/guide/ai-crawlers) du cœur définit *ce que vous leur autorisez dans robots.txt*. Vous observez un robot d’entraînement que vous souhaitez limiter ?

```php
// config/seo.php
'ai_crawlers' => [
    'overrides' => ['bytespider' => 'disallow'],
],
```

```bash
php artisan seo:robots-txt
```

Certains robots sont documentés comme ne respectant pas `robots.txt`. Le suivi permet d’observer les requêtes correspondantes et de décider d’un blocage en périphérie, via pare-feu, WAF ou Cloudflare. Une règle robots.txt est une instruction d’exploration, pas un blocage réseau.

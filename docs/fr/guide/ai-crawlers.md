---
description: "Générez robots.txt et, si nécessaire, ai.txt depuis une politique pour les robots IA de recherche, d'assistance et d'entraînement."
---

# Contrôle des robots IA (robots.txt / ai.txt) {#ai-crawler-control-robots-txt-ai-txt}

Les fournisseurs d'IA utilisent des robots nommés pour parcourir le Web. Beaucoup consultent **robots.txt** pour déterminer ce qu'ils peuvent récupérer. Rankbeam fournit un catalogue maintenu et génère un fichier `robots.txt` géré, ainsi qu'un `ai.txt` facultatif, à partir de règles `allow` / `disallow`. Vous pouvez autoriser la recherche et les assistants tout en refusant l'entraînement.

Cette fonction est gratuite dans le Core. Pro ajoute un [journal des visites de robots IA](/fr/pro/ai-bot-monitor), pour observer les accès réellement reçus.

## Politique par défaut {#the-default-policy}

Chaque robot possède une finalité principale :

| Finalité | Usage | Défaut |
|---|---|---|
| `ai_search` | Récupérer les pages pour l'index d'une recherche IA | **allow** |
| `ai_assistant` | Récupérer une page en temps réel à la demande d'un utilisateur | **allow** |
| `ai_training` | Collecter du contenu pour entraîner un modèle | **disallow** |

La configuration initiale ouvre donc l'accès aux robots de recherche et d'assistance, comme ceux de ChatGPT Search ou Perplexity, tout en refusant la collecte pour l'entraînement. Chaque choix reste modifiable.

::: warning Accès ne signifie pas citation
Autoriser un robot rend la récupération possible. Cela ne garantit ni découverte, indexation, classement, inclusion dans une réponse, citation ou lien de source. La politique décrit l'accès, pas le résultat produit ensuite.
:::

## Démarrage rapide {#quick-start}

Affichez d'abord le bloc que vous publieriez :

```bash
php artisan seo:robots-txt --print
```

Deux méthodes sont disponibles.

### Option A — ajouter le bloc à un robots.txt existant {#option-a-—-paste-the-block-into-your-existing-robots-txt}

Si vous gérez déjà `public/robots.txt`, récupérez seulement le bloc géré et collez-le dans le fichier :

```php
use Rankbeam\Seo\Facades\SEO;

echo SEO::robotsTxt()->aiDirectives();
```

```
# --- AI crawlers (managed by Rankbeam) ---

# GPTBot — OpenAI (AI training)
User-agent: GPTBot
Disallow: /

# Bytespider — ByteDance (AI training) — advisory: this bot may not honour robots.txt
User-agent: Bytespider
Disallow: /
...
```

### Option B — confier le fichier complet à Rankbeam {#option-b-—-let-rankbeam-manage-the-whole-file}

Générez un `robots.txt` complet : section générale, directives IA, ligne `Sitemap:` et lien vers [llms.txt](/fr/guide/sitemaps).

```bash
php artisan seo:robots-txt          # writes public/robots.txt
php artisan seo:robots-txt --ai-txt # also write public/ai.txt
```

Planifiez la commande pour suivre les changements de politique :

```php
// routes/console.php
Schedule::command('seo:robots-txt')->daily();
```

Vous pouvez aussi servir le fichier dynamiquement : avec `seo.ai_crawlers.route = true`, le paquet répond à `/robots.txt` depuis la configuration actuelle, sans étape de génération.

::: warning Priorité au fichier statique
Le serveur Web sert généralement `public/robots.txt` avant que Laravel ne reçoive la requête. La route dynamique est désactivée par défaut pour éviter un conflit silencieux avec un fichier oublié. Ne l'utilisez qu'en l'absence de fichier statique.
:::

## Limites d'application des règles {#honesty-about-enforcement}

robots.txt exprime une demande ; ce n'est pas une barrière technique. De nombreux robots déclarent la respecter, mais certains agents déclenchés par un utilisateur (`ChatGPT-User`, `Perplexity-User`) ou robots d'entraînement (`Bytespider`) n'offrent pas cette garantie. Rankbeam marque ces lignes `advisory`. Pour bloquer réellement un robot non coopératif, utilisez des règles de serveur ou de périphérie : pare-feu, WAF ou règles Cloudflare. Le [journal Pro](/fr/pro/ai-bot-monitor) aide à identifier les visites observées.

## Content Signals : préférences d'utilisation {#content-signals-usage-preferences}

`Allow` et `Disallow` décrivent **l'accès**. Les [Content Signals](https://contentsignals.org), soutenus par Cloudflare, décrivent **l'utilisation souhaitée** du contenu après récupération. Une ligne `Content-Signal:` dans `User-agent: *` exprime trois préférences :

| Signal | Finalité associée | Signification |
|---|---|---|
| `search` | `ai_search` | Construire un index avec liens et courts extraits |
| `ai-input` | `ai_assistant` | Utiliser la page comme entrée de modèle en temps réel, par exemple pour le RAG |
| `ai-train` | `ai_training` | Entraîner ou ajuster un modèle |

La fonction est **désactivée par défaut**, sans changement des octets du fichier. Lorsqu'elle est activée, Rankbeam dérive la ligne de `policy` : `allow` devient `yes`, `disallow` devient `no`.

```php
'ai_crawlers' => [
    'content_signals' => true,   // env: SEO_AI_CONTENT_SIGNALS
    // ...with the default policy, this emits, in the User-agent: * group:
    //   Content-Signal: search=yes, ai-input=yes, ai-train=no
],
```

Une finalité retirée de `policy` produit un signal omis : aucune préférence exprimée, ce qui diffère d'un `yes` ou `no` explicite.

::: warning Des préférences, pas un contrôle technique
Un robot peut ignorer les Content Signals. Ils complètent les règles d'accès et les éventuels blocages réseau ; ils ne les remplacent pas.
:::

## Configuration {#configuration}

```php
// config/seo.php
'ai_crawlers' => [
    'enabled' => true,
    'route'   => false,             // serve /robots.txt dynamically (off by default)
    'disk'    => 'public',
    'path'    => 'robots.txt',
    'ai_txt_path' => 'ai.txt',

    // Policy by purpose. A purpose left out is allowed.
    'policy' => [
        'ai_training'   => 'disallow',
        'ai_search'     => 'allow',
        'ai_assistant'  => 'allow',
        'search_engine' => 'allow',   // Yandex, Baidu, Naver, Seznam, … (3.15)
    ],

    // Per-bot overrides, keyed by catalog id (win over the purpose policy).
    'overrides' => [
        'gptbot' => 'allow',          // e.g. opt GPTBot back in
        'baiduspider' => 'disallow',  // e.g. keep a search engine you don't serve off your bandwidth
    ],

    // 'blocked' = only disallowed bots get a line (lean file);
    // 'all'     = every known bot gets an explicit allow/disallow (auditable).
    'list' => 'blocked',

    // Emit a Content-Signal usage-preference line (off by default), derived
    // from `policy` above. See "Content signals" above.
    'content_signals' => false,

    // The general `User-agent: *` section: true = permissive default,
    // a string = your own rules verbatim, false = omit.
    'general' => true,

    'include_sitemap' => true,
    'sitemap_url'     => null,        // null = derive from the sitemap route
    'include_llms_txt' => true,
],
```

`overrides` remplace la règle de finalité pour un robot donné. Ses clés sont les **identifiants du catalogue**, par exemple `gptbot`, `claudebot`, `perplexitybot` ou `google-extended`.

## Catalogue {#the-catalog}

`SEO::aiCrawlers()` fournit le même catalogue que le journal Pro. La génération des règles et l'identification des visiteurs partagent donc leurs données.

```php
SEO::aiCrawlers()->all();               // every known AiCrawler
SEO::aiCrawlers()->get('gptbot');       // one bot
SEO::aiCrawlers()->actionFor('gptbot'); // 'allow' | 'disallow' (resolved policy)
SEO::aiCrawlers()->match($userAgent);   // identify a request UA, or null
```

Le catalogue inclut notamment OpenAI (GPTBot, OAI-SearchBot, ChatGPT-User), Anthropic (ClaudeBot, Claude-SearchBot, Claude-User), Google (Google-Extended), Perplexity, Apple (Applebot-Extended), Common Crawl (CCBot), Meta, Amazon et ByteDance, avec finalité documentée et token robots.txt.

### Moteurs de recherche régionaux {#regional-search-engines}

Depuis 3.15, le catalogue contient aussi des robots de recherche classiques, au-delà de Google et Bing. Leur finalité est `search_engine` et ils sont **autorisés par défaut** :

| ID | Token | Opérateur |
|---|---|---|
| `yandex` | `Yandex` | Yandex, Russie ; le token de base couvre ses robots |
| `baiduspider` | `Baiduspider` | Baidu, Chine |
| `yeti` | `Yeti` | Naver, Corée |
| `seznambot` | `SeznamBot` | Seznam, Tchéquie |
| `sogou` | `Sogou web spider` | Sogou, Chine |
| `360spider` | `360Spider` | Qihoo 360, Chine |
| `coccocbot` | `coccocbot-web` | Cốc Cốc, Vietnam |
| `duckduckbot` | `DuckDuckBot` | DuckDuckGo |

Ils suivent `policy` et `overrides` comme les autres robots. Par exemple, `'overrides' => ['baiduspider' => 'disallow', 'sogou' => 'disallow']` leur demande de ne pas parcourir le site. Avec `'list' => 'all'`, chacun reçoit une ligne explicite.

Ces moteurs sont exclus de `all()` et `match()` sauf demande explicite via `searchEngines()`, `all(true)` ou `match($ua, true)`. Ainsi, le journal IA et les compteurs de robots IA conservent leur sens :

```php
SEO::aiCrawlers()->searchEngines();          // the eight engines
SEO::aiCrawlers()->get('yandex');            // works for both lists
SEO::aiCrawlers()->match($userAgent, true);  // identify an engine too
```

Identifier un robot ne garantit pas la visibilité ni le classement dans son moteur. Les balises de vérification correspondantes (`yandex-verification`, `baidu-site-verification`, `naver-site-verification`, `seznam-wmt`) sont configurées sous `seo.verification` ; voir [contenus multilingues](/fr/guide/multilingual#site-verification).

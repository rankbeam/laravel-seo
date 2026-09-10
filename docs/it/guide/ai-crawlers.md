---
description: "Genera robots.txt e, facoltativamente, ai.txt da una policy sui crawler AI. Il core gratuito gestisce le preferenze di accesso; Pro aggiunge il monitoraggio."
---

# Controllo dei crawler AI: robots.txt / ai.txt {#ai-crawler-control-robots-txt-ai-txt}

Rankbeam include un catalogo di crawler AI e genera `robots.txt`, e facoltativamente `ai.txt`, da una policy allow/disallow. Puoi consentire i crawler di ricerca e degli assistenti ed esprimere il rifiuto per quelli che raccolgono dati di addestramento.

È una funzione del core gratuito. Pro aggiunge il [registro delle visite dei bot AI](/it/pro/ai-bot-monitor), per osservare le richieste ricevute.

## Policy predefinita {#the-default-policy}

Ogni bot del catalogo ha uno scopo principale:

| Scopo | Attività | Default |
|---|---|---|
| `ai_search` | Acquisisce pagine per indicizzarle nella ricerca AI | **allow** |
| `ai_assistant` | Recupera una pagina in tempo reale su richiesta dell'utente | **allow** |
| `ai_training` | Raccoglie contenuti per addestrare un modello | **disallow** |

Puoi modificare ogni scelta nella configurazione.

::: warning Consentire l'accesso non garantisce una citazione
Autorizzare un crawler rende possibile il recupero della pagina. Non garantisce scoperta, indicizzazione, posizionamento, inclusione nelle risposte o citazioni. La policy riguarda l'accesso.
:::

## Avvio rapido {#quick-start}

Visualizza il blocco delle direttive prima di pubblicarlo:

```bash
php artisan seo:robots-txt --print
```

Puoi usarlo in due modi.

### A. Aggiungi il blocco al robots.txt esistente {#option-a-—-paste-the-block-into-your-existing-robots-txt}

Se gestisci già `public/robots.txt`, copia solo il blocco dedicato ai crawler AI:

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

### B. Fai gestire l'intero file a Rankbeam {#option-b-—-let-rankbeam-manage-the-whole-file}

Genera un `robots.txt` completo con sezione generale, direttive AI, riga `Sitemap:` e collegamento a [llms.txt](/it/guide/sitemaps):

```bash
php artisan seo:robots-txt          # writes public/robots.txt
php artisan seo:robots-txt --ai-txt # also write public/ai.txt
```

Pianifica il comando per aggiornare il file quando cambia la policy:

```php
// routes/console.php
Schedule::command('seo:robots-txt')->daily();
```

In alternativa, imposta `seo.ai_crawlers.route` a `true`: Laravel risponderà su `/robots.txt` usando la configurazione corrente, senza generazione preventiva.

::: warning Il file statico ha la precedenza
Il server web serve normalmente `public/robots.txt` prima che la richiesta arrivi a Laravel. La rotta dinamica è disattivata per impostazione predefinita. Usala solo quando non c'è un file statico che la nasconde.
:::

## Limiti dell'applicazione delle regole {#honesty-about-enforcement}

robots.txt esprime una richiesta: non impedisce tecnicamente l'accesso. Alcuni agenti avviati dall'utente, come `ChatGPT-User` e `Perplexity-User`, e alcuni crawler di addestramento, come `Bytespider`, non garantiscono di rispettarlo. Rankbeam contrassegna queste righe come `advisory`.

Per bloccare un bot non conforme serve un controllo sul server o sull'edge, per esempio firewall, WAF o regole bot di Cloudflare. Il [registro Pro](/it/pro/ai-bot-monitor) attribuisce le richieste tramite user-agent, senza verificarne l’identità.

## Content signals: preferenze d'uso {#content-signals-usage-preferences}

`Allow` e `Disallow` riguardano l'accesso. I [Content signals](https://contentsignals.org) esprimono invece preferenze sull'uso dei contenuti già recuperati. Una riga `Content-Signal:` nel gruppo `User-agent: *` contiene tre segnali:

| Segnale | Scopo della policy | Significato |
|---|---|---|
| `search` | `ai_search` | Creazione di un indice di ricerca con collegamenti e brevi estratti |
| `ai-input` | `ai_assistant` | Uso della pagina come input di un modello in tempo reale, per esempio RAG |
| `ai-train` | `ai_training` | Addestramento o fine-tuning di un modello |

La funzione è **disattivata per impostazione predefinita**. Se la attivi, Rankbeam deriva i valori dalla `policy` esistente: `allow` diventa `yes`, `disallow` diventa `no`.

```php
'ai_crawlers' => [
    'content_signals' => true,   // env: SEO_AI_CONTENT_SIGNALS
    // ...with the default policy, this emits, in the User-agent: * group:
    //   Content-Signal: search=yes, ai-input=yes, ai-train=no
],
```

Uno scopo assente dalla `policy` produce un segnale omesso: nessuna preferenza espressa, distinto da un `yes` o `no` esplicito.

::: warning Sono preferenze, non controlli tecnici
Un crawler può ignorare i content signals. Affiancano le regole di accesso e gli eventuali blocchi sull'edge; non li sostituiscono.
:::

## Configurazione {#configuration}

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

Gli `overrides` prevalgono sullo scopo del bot e usano l'**id** del catalogo: per esempio `gptbot`, `claudebot`, `perplexitybot` o `google-extended`.

## Catalogo {#the-catalog}

`SEO::aiCrawlers()` espone lo stesso catalogo usato dal registro Pro per identificare le richieste:

```php
SEO::aiCrawlers()->all();               // every known AiCrawler
SEO::aiCrawlers()->get('gptbot');       // one bot
SEO::aiCrawlers()->actionFor('gptbot'); // 'allow' | 'disallow' (resolved policy)
SEO::aiCrawlers()->match($userAgent);   // identify a request UA, or null
```

Include OpenAI (`GPTBot`, `OAI-SearchBot`, `ChatGPT-User`), Anthropic (`ClaudeBot`, `Claude-SearchBot`, `Claude-User`), Google (`Google-Extended`), Perplexity, Apple (`Applebot-Extended`), Common Crawl (`CCBot`), Meta, Amazon, ByteDance e altri operatori. Ogni voce indica lo scopo e il token robots.txt documentati.

### Motori di ricerca regionali {#regional-search-engines}

Dalla versione 3.15, il catalogo include anche questi crawler di ricerca tradizionali, con scopo `search_engine` e accesso **consentito per impostazione predefinita**:

| id | Token | Operatore |
|---|---|---|
| `yandex` | `Yandex` | Yandex, Russia; il token generico copre i suoi bot |
| `baiduspider` | `Baiduspider` | Baidu, Cina |
| `yeti` | `Yeti` | Naver, Corea |
| `seznambot` | `SeznamBot` | Seznam, Cechia |
| `sogou` | `Sogou web spider` | Sogou, Cina |
| `360spider` | `360Spider` | Qihoo 360, Cina |
| `coccocbot` | `coccocbot-web` | Cốc Cốc, Vietnam |
| `duckduckbot` | `DuckDuckBot` | DuckDuckGo |

Partecipano a `policy` e `overrides` come gli altri bot. Per esempio, `'overrides' => ['baiduspider' => 'disallow', 'sogou' => 'disallow']` richiede a quei crawler di non accedere; `'list' => 'all'` genera una regola esplicita per ciascuno.

Sono esclusi da `all()` e `match()` salvo richiesta esplicita tramite `searchEngines()`, `all(true)` o `match($ua, true)`. In questo modo il registro Pro e i conteggi dei crawler AI mantengono il loro significato:

```php
SEO::aiCrawlers()->searchEngines();          // the eight engines
SEO::aiCrawlers()->get('yandex');            // works for both lists
SEO::aiCrawlers()->match($userAgent, true);  // identify an engine too
```

Riconoscere un crawler non garantisce visibilità o posizionamento nel relativo motore. I tag di verifica `yandex-verification`, `baidu-site-verification`, `naver-site-verification` e `seznam-wmt` si configurano in `seo.verification`; consulta [contenuti multilingua](/it/guide/multilingual#site-verification).

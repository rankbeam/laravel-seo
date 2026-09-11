---
description: "Genereer een beheerde robots.txt en optionele ai.txt vanuit een toestaan/blokkeren-beleid voor een bijgehouden AI-crawlercatalogus. Bepaal welke bots je site mogen ophalen. Gratis in Core."
---

# AI-crawlerbeheer (robots.txt / ai.txt) {#ai-crawler-control-robots-txt-ai-txt}

De grote AI-aanbieders crawlen het web met benoemde bots. De meeste lezen
**robots.txt** om te bepalen wat ze mogen ophalen. Rankbeam bevat een
bijgehouden catalogus van die bots en genereert een beheerde `robots.txt` en
optionele `ai.txt` vanuit een eenvoudig toestaan/blokkeren-beleid. Zo kun
je **crawlers voor AI-zoekdiensten en assistenten toelaten en crawlers die op
je content trainen beperken.**

Dit is een gratis Core-functie. Het Pro-pakket voegt het andere deel toe:
[inzicht in bezoeken via een AI-botlogboek](/nl/pro/ai-bot-monitor), waarin je ziet
welke AI-crawlers daadwerkelijk langskwamen.

## Het standaardbeleid {#the-default-policy}

Elke bot in de catalogus krijgt een label voor zijn voornaamste doel:

| Doel | Wat de bot doet | Standaard |
| --- | --- | --- |
| `ai_search` | Haalt je pagina's op om ze te indexeren voor antwoorden in **AI-zoekdiensten** (het kanaal voor verwijzingen vanuit AI) | **toestaan** |
| `ai_assistant` | Haalt tijdens een chat in realtime een pagina op namens een **gebruiker** | **toestaan** |
| `ai_training` | Verzamelt content om een model te **trainen** | **blokkeren** |

Dit sluit aan bij hoe de meeste uitgevers met AI omgaan: bereikbaar zijn voor
de zoek- en assistentcrawlers achter ChatGPT search, Perplexity en vergelijkbare
diensten, en tegelijk aangeven dat hun content geen trainingsdata mag worden.
Je kunt dit volledig aanpassen in de configuratie.

::: warning Toegang betekent geen bronvermelding
Een crawler toelaten maakt ophalen *mogelijk*. Het garandeert geen ontdekking,
indexering, positie in zoekresultaten, opname, citaat of bronvermelding. Dit
beleid regelt **toegang** — welke bots je pagina's mogen ophalen — en niets
wat daarna gebeurt.
:::

## Snelstart {#quick-start}

Toon het AI-crawlerblok om te zien wat je zou publiceren:

```bash
php artisan seo:robots-txt --print
```

Je kunt het op twee manieren gebruiken.

### Optie A: plak het blok in je bestaande robots.txt {#option-a-—-paste-the-block-into-your-existing-robots-txt}

Als je al een `public/robots.txt` beheert, kopieer dan alleen het beheerde blok en plak het erin:

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

### Optie B: laat Rankbeam het hele bestand beheren {#option-b-—-let-rankbeam-manage-the-whole-file}

Genereer een volledige `robots.txt`: algemene sectie, AI-instructies, een
`Sitemap:`-regel en een verwijzing naar je [llms.txt](/nl/guide/sitemaps):

```bash
php artisan seo:robots-txt          # writes public/robots.txt
php artisan seo:robots-txt --ai-txt # also write public/ai.txt
```

Plan het commando in, zodat het bestand je beleid blijft volgen:

```php
// routes/console.php
Schedule::command('seo:robots-txt')->daily();
```

Of bied het dynamisch aan. Stel `seo.ai_crawlers.route` in op `true`; het pakket
beantwoordt `/robots.txt` dan vanuit de actuele configuratie, zonder afzonderlijke generatiestap:

::: warning Een statisch bestand heeft voorrang
De meeste applicaties bevatten al een `public/robots.txt`. Je webserver biedt dat aan
voordat de aanvraag Laravel bereikt. De dynamische route staat **standaard uit**,
zodat ze niet ongemerkt een vergeten bestand kan overschaduwen of er zelf door
overschaduwd kan worden. Gebruik de route alleen als er geen statische `robots.txt` bestaat.
:::

## Wat daadwerkelijk wordt afgedwongen {#honesty-about-enforcement}

robots.txt is een verzoek, geen toegangsbarrière. Volgens hun documentatie
respecteren de meeste bots uit de catalogus het, maar enkele door gebruikers
gestarte agents (`ChatGPT-User`, `Perplexity-User`) en sommige trainingscrawlers
(`Bytespider`) doen dat **niet**. Rankbeam markeert die regels met
`advisory`, zodat het geen blokkade suggereert die niet standhoudt.
Om een bot die de regels negeert werkelijk te stoppen, heb je blokkering op
server- of netwerkrandniveau nodig: een firewall, WAF of Cloudflare-botregels.
Het [AI-botlogboek van Pro](/nl/pro/ai-bot-monitor) laat zien welke bots aandacht vragen.

## Content signals (gebruiksvoorkeuren) {#content-signals-usage-preferences}

`Allow` / `Disallow` regelen **toegang**: of een bot de pagina mag ophalen.
[Content signals](https://contentsignals.org), de standaard die Cloudflare
ondersteunt, beschrijven de andere kant: hoe opgehaalde content mag worden **gebruikt**.
Eén `Content-Signal:`-regel in de groep `User-agent: *` bevat drie voorkeuren:

| Signaal | Afgeleid van beleidsdoel | Betekenis |
| --- | --- | --- |
| `search` | `ai_search` | Een zoekindex opbouwen (links en korte fragmenten) |
| `ai-input` | `ai_assistant` | De pagina in realtime aan een AI-model aanbieden (RAG / grounding) |
| `ai-train` | `ai_training` | Een AI-model trainen of finetunen |

Dit staat **standaard uit**: het bestand blijft byte voor byte gelijk totdat je
het inschakelt. Daarna leidt Rankbeam de regel rechtstreeks af van je bestaande
`policy`: `allow` wordt `yes` en `disallow` wordt `no`:

```php
'ai_crawlers' => [
    'content_signals' => true,   // env: SEO_AI_CONTENT_SIGNALS
    // ...with the default policy, this emits, in the User-agent: * group:
    //   Content-Signal: search=yes, ai-input=yes, ai-train=no
],
```

Verwijder je een doel volledig uit `policy`, dan wordt het bijbehorende
signaal **weggelaten**. Dat betekent volgens de specificatie dat er geen
voorkeur is opgegeven, wat verschilt van een expliciete `yes`/`no`.

::: warning Een advies, net als robots.txt zelf
Content signals drukken een voorkeur uit en zijn **geen** technische
beveiliging. Een crawler kan ze negeren. Ze vullen de toegangsregels hierboven
en eventuele blokkering op netwerkrandniveau aan, en vervangen die niet.
:::

## Configuratie {#configuration}

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

Overschrijf het beleid voor één bot, ongeacht het doel, met `overrides`.
Gebruik de **id** uit de catalogus als sleutel, bijvoorbeeld `gptbot`,
`claudebot`, `perplexitybot` of `google-extended`.

## De catalogus {#the-catalog}

`SEO::aiCrawlers()` is de gezaghebbende bron. Het Pro-bezoeklogboek gebruikt dezelfde
catalogus om bezoekers te herkennen, zodat het bestand dat een bot aanstuurt
en het paneel dat die bot observeert elkaar nooit tegenspreken.

```php
SEO::aiCrawlers()->all();               // every known AiCrawler
SEO::aiCrawlers()->get('gptbot');       // one bot
SEO::aiCrawlers()->actionFor('gptbot'); // 'allow' | 'disallow' (resolved policy)
SEO::aiCrawlers()->match($userAgent);   // identify a request UA, or null
```

De catalogus omvat de grote aanbieders: OpenAI (GPTBot, OAI-SearchBot, ChatGPT-User),
Anthropic (ClaudeBot, Claude-SearchBot, Claude-User), Google (Google-Extended),
Perplexity, Apple (Applebot-Extended), Common Crawl (CCBot), Meta, Amazon,
ByteDance en meer. Elke vermelding bevat het gedocumenteerde doel en robots.txt-token.

### Regionale zoekmachines {#regional-search-engines}

Sinds 3.15 bevat de catalogus ook klassieke webzoekcrawlers die buiten
Google en Bing van belang zijn. Ze krijgen het doel `search_engine` en zijn
**standaard toegestaan**:

| id | Token | Aanbieder |
|---|---|---|
| `yandex` | `Yandex` | Yandex (Rusland): het kale token dekt alle bots van Yandex |
| `baiduspider` | `Baiduspider` | Baidu (China) |
| `yeti` | `Yeti` | Naver (Korea) |
| `seznambot` | `SeznamBot` | Seznam (Tsjechië) |
| `sogou` | `Sogou web spider` | Sogou (China) |
| `360spider` | `360Spider` | Qihoo 360 (China) |
| `coccocbot` | `coccocbot-web` | Cốc Cốc (Vietnam) |
| `duckduckbot` | `DuckDuckBot` | DuckDuckGo |

Ze vallen net als elke andere bot onder `policy` en `overrides`.
Met `'overrides' => ['baiduspider' => 'disallow', 'sogou' => 'disallow']` kun je dus twee crawlers die je niet wilt bedienen vragen geen
bandbreedte te gebruiken; `'list' => 'all'` geeft elke crawler een expliciete regel.
Ze blijven **buiten** `all()` en `match()`, tenzij je erom vraagt via
`searchEngines()`, `all(true)` of `match($ua, true)`. Zo blijven het Pro-AI-botlogboek
en elke telling van het aantal AI-crawlers hun betekenis behouden:

```php
SEO::aiCrawlers()->searchEngines();          // the eight engines
SEO::aiCrawlers()->get('yandex');            // works for both lists
SEO::aiCrawlers()->match($userAgent, true);  // identify an engine too
```

Een crawler herkennen garandeert geen zichtbaarheid of posities in die
zoekmachine. De bijbehorende siteverificatietags (`yandex-verification`, `baidu-site-verification`,
`naver-site-verification`, `seznam-wmt`) staan onder `seo.verification`.
Zie [Meertalige content](/nl/guide/multilingual#site-verification).

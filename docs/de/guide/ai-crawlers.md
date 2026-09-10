---
description: "Erzeuge robots.txt und optional ai.txt aus einer Richtlinie für KI-Crawler. Kostenlos im Core, mit getrennten Regeln für Suche, Assistenten und Training."
---

# KI-Crawler steuern (robots.txt / ai.txt) {#ai-crawler-control-robots-txt-ai-txt}

KI-Anbieter verwenden benannte Webcrawler. Viele lesen **robots.txt**, um zu entscheiden, welche Seiten sie abrufen dürfen. Rankbeam enthält einen gepflegten Katalog und erzeugt aus einer `allow`-/`disallow`-Richtlinie eine verwaltete `robots.txt` und optional `ai.txt`. So kannst du Crawler für KI-Suche und Assistenten zulassen und Trainingscrawler ausschließen.

Diese Funktion gehört zum kostenlosen Core. Pro ergänzt ein [Zugriffsprotokoll für KI-Bots](/de/pro/ai-bot-monitor), das tatsächlich beobachtete Besuche zeigt.

## Standardrichtlinie {#the-default-policy}

Jeder katalogisierte Bot hat einen primären Zweck:

| Zweck | Aufgabe | Standard |
|---|---|---|
| `ai_search` | Seiten für Antworten einer KI-Suche indexieren | **allow** |
| `ai_assistant` | Seiten während eines Chats im Auftrag eines Nutzers abrufen | **allow** |
| `ai_training` | Inhalte zum Modelltraining sammeln | **disallow** |

Die Vorgabe erlaubt Suche und Assistenten, etwa hinter ChatGPT Search und Perplexity, und lehnt Training ab. Du kannst jede dieser Entscheidungen in der Konfiguration ändern.

::: warning Zugriff bedeutet keine Zitierung
Ein erlaubter Abruf macht den Zugriff möglich. Er garantiert weder Entdeckung, Indexierung, Ranking, Aufnahme in eine Antwort noch Zitate oder Quellenverweise. Die Richtlinie beschreibt den **Zugriff**, nicht die nachgelagerte Verwendung in Suchergebnissen.
:::

## Schnellstart {#quick-start}

Zeige zunächst den zu veröffentlichenden KI-Crawler-Block an:

```bash
php artisan seo:robots-txt --print
```

Du kannst ihn auf zwei Arten verwenden.

### Option A — in eine vorhandene robots.txt einfügen {#option-a-—-paste-the-block-into-your-existing-robots-txt}

Wenn du `public/robots.txt` selbst pflegst, hole nur den verwalteten Block und füge ihn dort ein:

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

### Option B — die gesamte Datei verwalten lassen {#option-b-—-let-rankbeam-manage-the-whole-file}

Erzeuge eine vollständige `robots.txt` mit allgemeinem Abschnitt, KI-Regeln, `Sitemap:` und einem Verweis auf [llms.txt](/de/guide/sitemaps):

```bash
php artisan seo:robots-txt          # writes public/robots.txt
php artisan seo:robots-txt --ai-txt # also write public/ai.txt
```

Ein Zeitplan hält die Datei mit deiner Konfiguration synchron:

```php
// routes/console.php
Schedule::command('seo:robots-txt')->daily();
```

Alternativ kannst du `seo.ai_crawlers.route` auf `true` setzen. Dann beantwortet das Paket `/robots.txt` dynamisch aus der aktuellen Konfiguration, ohne Generierungsschritt.

::: warning Eine statische Datei hat Vorrang
Viele Anwendungen enthalten bereits `public/robots.txt`. Der Webserver liefert sie aus, bevor Laravel die Anfrage erhält. Die dynamische Route ist standardmäßig deaktiviert, damit sich Route und vergessene Datei nicht unbemerkt überlagern. Verwende die Route nur ohne statische `robots.txt`.
:::

## Grenzen der Durchsetzung {#honesty-about-enforcement}

robots.txt ist eine Aufforderung, keine Zugriffssperre. Viele katalogisierte Bots beachten sie laut ihrer Dokumentation. Bei manchen nutzergesteuerten Agenten (`ChatGPT-User`, `Perplexity-User`) und Trainingscrawlern (`Bytespider`) ist das nicht zugesichert. Rankbeam kennzeichnet solche Regeln als `advisory`. Um einen Bot tatsächlich zu sperren, brauchst du Regeln auf Server- oder Edge-Ebene, etwa Firewall, WAF oder Cloudflare-Bot-Regeln. Das [Pro-Zugriffsprotokoll](/de/pro/ai-bot-monitor) hilft, beobachtete Crawler zu erkennen.

## Content Signals: Nutzungspräferenzen {#content-signals-usage-preferences}

`Allow` und `Disallow` beschreiben den **Abruf**. [Content Signals](https://contentsignals.org), ein von Cloudflare unterstützter Standard, beschreiben dagegen die gewünschte **Nutzung** bereits abgerufener Inhalte. Eine Zeile `Content-Signal:` in der Gruppe `User-agent: *` trägt drei Präferenzen:

| Signal | Zugehöriger Zweck | Bedeutung |
|---|---|---|
| `search` | `ai_search` | Suchindex mit Links und kurzen Auszügen erstellen |
| `ai-input` | `ai_assistant` | Seiten in Echtzeit als Modelleingabe verwenden, etwa für RAG |
| `ai-train` | `ai_training` | Modelle trainieren oder feinabstimmen |

Die Funktion ist **standardmäßig aus**; ohne Aktivierung bleibt die Datei bytegleich. Bei Aktivierung leitet Rankbeam die Zeile aus deiner bestehenden `policy` ab: `allow` wird `yes`, `disallow` wird `no`.

```php
'ai_crawlers' => [
    'content_signals' => true,   // env: SEO_AI_CONTENT_SIGNALS
    // ...with the default policy, this emits, in the User-agent: * group:
    //   Content-Signal: search=yes, ai-input=yes, ai-train=no
],
```

Entfernst du einen Zweck vollständig aus `policy`, wird sein Signal weggelassen. Das bedeutet „keine Präferenz angegeben“ und unterscheidet sich von einem ausdrücklichen `yes` oder `no`.

::: warning Beratend wie robots.txt
Content Signals drücken eine Präferenz aus und erzwingen sie nicht technisch. Ein Crawler kann sie ignorieren. Sie ergänzen die Zugriffsregeln und mögliche Edge-Sperren.
:::

## Konfiguration {#configuration}

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

Mit `overrides` überschreibst du die Zweckrichtlinie für einzelne Bots. Die Schlüssel sind die Katalog-**IDs**, zum Beispiel `gptbot`, `claudebot`, `perplexitybot` oder `google-extended`.

## Katalog {#the-catalog}

`SEO::aiCrawlers()` liefert denselben Katalog, mit dem das Pro-Protokoll Besucher erkennt. Regelgenerierung und Besucherzuordnung verwenden damit dieselbe Grundlage.

```php
SEO::aiCrawlers()->all();               // every known AiCrawler
SEO::aiCrawlers()->get('gptbot');       // one bot
SEO::aiCrawlers()->actionFor('gptbot'); // 'allow' | 'disallow' (resolved policy)
SEO::aiCrawlers()->match($userAgent);   // identify a request UA, or null
```

Der Katalog enthält unter anderem OpenAI (GPTBot, OAI-SearchBot, ChatGPT-User), Anthropic (ClaudeBot, Claude-SearchBot, Claude-User), Google (Google-Extended), Perplexity, Apple (Applebot-Extended), Common Crawl (CCBot), Meta, Amazon und ByteDance, jeweils mit dokumentiertem Zweck und robots.txt-Token.

### Regionale Suchmaschinen {#regional-search-engines}

Seit 3.15 enthält der Katalog auch klassische Suchcrawler außerhalb von Google und Bing. Sie haben den Zweck `search_engine` und sind **standardmäßig erlaubt**:

| ID | Token | Betreiber |
|---|---|---|
| `yandex` | `Yandex` | Yandex, Russland; das Basistoken deckt seine Bots ab |
| `baiduspider` | `Baiduspider` | Baidu, China |
| `yeti` | `Yeti` | Naver, Korea |
| `seznambot` | `SeznamBot` | Seznam, Tschechien |
| `sogou` | `Sogou web spider` | Sogou, China |
| `360spider` | `360Spider` | Qihoo 360, China |
| `coccocbot` | `coccocbot-web` | Cốc Cốc, Vietnam |
| `duckduckbot` | `DuckDuckBot` | DuckDuckGo |

Sie verwenden dieselben `policy`- und `overrides`-Regeln. Beispielsweise fordert `'overrides' => ['baiduspider' => 'disallow', 'sogou' => 'disallow']` zwei Crawler auf, die Website nicht abzurufen. Mit `'list' => 'all'` erhält jeder Bot eine ausdrückliche Regel.

Ohne ausdrückliche Anforderung sind Suchmaschinen nicht in `all()` und `match()` enthalten. Nutze `searchEngines()`, `all(true)` oder `match($ua, true)`. So behalten das Pro-KI-Bot-Protokoll und Zähler für KI-Crawler ihre Bedeutung:

```php
SEO::aiCrawlers()->searchEngines();          // the eight engines
SEO::aiCrawlers()->get('yandex');            // works for both lists
SEO::aiCrawlers()->match($userAgent, true);  // identify an engine too
```

Die Erkennung eines Crawlers garantiert keine Sichtbarkeit oder Rankings. Zugehörige Verifizierungstags (`yandex-verification`, `baidu-site-verification`, `naver-site-verification`, `seznam-wmt`) stehen unter `seo.verification`; siehe [mehrsprachige Inhalte](/de/guide/multilingual#site-verification).

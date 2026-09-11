---
description: "Generuj zarządzany robots.txt (i opcjonalny ai.txt) na podstawie zasad allow/disallow dla wyselekcjonowanego katalogu robotów AI i wybieraj, które boty mogą pobierać Twoją witrynę. Bezpłatna funkcja rdzenia."
---

# Kontrola robotów AI (robots.txt / ai.txt) {#ai-crawler-control-robots-txt-ai-txt}

Najwięksi operatorzy AI przeszukują internet za pomocą nazwanych botów, a większość odczytuje **robots.txt**, aby ustalić, co może pobierać. Rankbeam dostarcza wyselekcjonowany katalog tych botów i generuje zarządzany `robots.txt` (oraz opcjonalny `ai.txt`) na podstawie prostych zasad allow / disallow — możesz więc **zezwolić na roboty wyszukiwania AI i asystentów, a ograniczyć te, które trenują na Twoich treściach.**

To bezpłatna funkcja rdzenia. Pakiet Pro dodaje drugą część — [obserwowalność: dziennik żądań botów AI](/pl/pro/ai-bot-monitor), pokazujący, które roboty AI rzeczywiście odwiedziły witrynę.

## Domyślne zasady {#the-default-policy}

Każdy bot w katalogu jest oznaczony według swojego głównego zastosowania:

| Zastosowanie | Co robi | Wartość domyślna |
| --- | --- | --- |
| `ai_search` | Pobiera strony, aby indeksować je dla odpowiedzi **wyszukiwania AI** (kanału ruchu odsyłającego z AI) | **allow** |
| `ai_assistant` | Pobiera stronę w czasie rzeczywistym w imieniu **użytkownika** na czacie | **allow** |
| `ai_training` | Zbiera treści do **trenowania** modelu | **disallow** |

Odzwierciedla to podejście większości wydawców do ery AI: dostęp dla robotów wyszukiwania AI i asystentów obsługujących wyszukiwanie ChatGPT, Perplexity i podobne narzędzia przy rezygnacji z udostępniania danych treningowych. Wszystkie te zasady możesz zmienić w konfiguracji.

::: warning Dostęp nie oznacza cytowania
Zezwolenie robotowi na dostęp sprawia, że pobranie jest *możliwe*; nie gwarantuje odkrycia, indeksowania, pozycji, uwzględnienia, zacytowania treści ani wskazania jej jako źródła. Te zasady kontrolują **dostęp** — które boty mogą pobierać strony — i nic, co dzieje się później.
:::

## Szybki start {#quick-start}

Wyświetl blok robotów AI, aby zobaczyć, co zostanie opublikowane:

```bash
php artisan seo:robots-txt --print
```

Możesz użyć go na dwa sposoby.

### Opcja A — wklej blok do istniejącego robots.txt {#option-a-—-paste-the-block-into-your-existing-robots-txt}

Jeśli już utrzymujesz `public/robots.txt`, pobierz sam zarządzany blok i wklej go:

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

### Opcja B — pozwól Rankbeam zarządzać całym plikiem {#option-b-—-let-rankbeam-manage-the-whole-file}

Wygeneruj kompletny `robots.txt` (sekcja ogólna + dyrektywy AI + linia `Sitemap:` + wskazanie Twojego [llms.txt](/pl/guide/sitemaps)):

```bash
php artisan seo:robots-txt          # writes public/robots.txt
php artisan seo:robots-txt --ai-txt # also write public/ai.txt
```

Zaplanuj generowanie, aby plik odzwierciedlał Twoje zasady:

```php
// routes/console.php
Schedule::command('seo:robots-txt')->daily();
```

Możesz też udostępniać go dynamicznie — ustaw `seo.ai_crawlers.route` na `true`, a pakiet odpowie na `/robots.txt` z bieżącej konfiguracji (bez kroku generowania):

::: warning Plik statyczny ma pierwszeństwo
Większość aplikacji ma już `public/robots.txt`, który serwer WWW udostępnia, zanim żądanie trafi do routingu Laravel. Trasa dynamiczna jest **domyślnie wyłączona**, aby nie przesłoniła po cichu zapomnianego pliku ani nie została przez niego przesłonięta. Korzystaj z trasy tylko wtedy, gdy nie ma statycznego `robots.txt`.
:::

## Uczciwie o egzekwowaniu zasad {#honesty-about-enforcement}

robots.txt jest prośbą, a nie barierą. Większość botów w katalogu ma udokumentowane przestrzeganie tego pliku, lecz niektórzy agenci uruchamiani przez użytkownika (`ChatGPT-User`, `Perplexity-User`) i część robotów trenujących (`Bytespider`) **go nie przestrzegają** — Rankbeam oznacza te linie jako `advisory`, zamiast sugerować skuteczną blokadę. Aby rzeczywiście zatrzymać bota nieprzestrzegającego zasad, potrzebujesz blokowania na poziomie serwera lub brzegu sieci (zapora, WAF, reguły botów Cloudflare); [dziennik żądań botów AI w Pro](/pl/pro/ai-bot-monitor) pokazuje, na które zwrócić uwagę.

## Content signals (preferencje wykorzystania) {#content-signals-usage-preferences}

`Allow` / `Disallow` kontrolują **dostęp** — czy bot może pobrać stronę. [Content signals](https://contentsignals.org) (standard promowany przez Cloudflare) określają drugi wymiar: jak pobrana treść może być **wykorzystana**. Jedna linia `Content-Signal:` w grupie `User-agent: *` przekazuje trzy preferencje:

| Sygnał | Wynika z zastosowania w zasadach | Znaczenie |
| --- | --- | --- |
| `search` | `ai_search` | Budowanie indeksu wyszukiwania (linki i krótkie fragmenty) |
| `ai-input` | `ai_assistant` | Przekazywanie strony do modelu AI w czasie rzeczywistym (RAG / grounding) |
| `ai-train` | `ai_training` | Trenowanie lub dostrajanie modelu AI |

Funkcja jest **domyślnie wyłączona** (plik pozostaje identyczny bajtowo, dopóki jej nie włączysz). Po włączeniu Rankbeam wyprowadza linię bezpośrednio z istniejącego `policy` — `allow` staje się `yes`, a `disallow` staje się `no`:

```php
'ai_crawlers' => [
    'content_signals' => true,   // env: SEO_AI_CONTENT_SIGNALS
    // ...with the default policy, this emits, in the User-agent: * group:
    //   Content-Signal: search=yes, ai-input=yes, ai-train=no
],
```

Całkowite usunięcie zastosowania z `policy` powoduje **pominięcie** jego sygnału — według specyfikacji oznacza to „nie wyrażono preferencji”, co różni się od jawnego `yes`/`no`.

::: warning Deklaratywne, tak jak sam robots.txt
Content signals wyrażają preferencję; **nie** są technicznym mechanizmem kontroli. Robot może je zignorować. Uzupełniają — a nie zastępują — powyższe reguły dostępu i blokady na brzegu sieci.
:::

## Konfiguracja {#configuration}

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

Nadpisz zachowanie pojedynczego bota niezależnie od jego zastosowania przez `overrides`, używając jako klucza **id** z katalogu (np. `gptbot`, `claudebot`, `perplexitybot`, `google-extended`).

## Katalog {#the-catalog}

`SEO::aiCrawlers()` jest źródłem prawdy — z tego samego katalogu korzysta dziennik Pro do identyfikacji odwiedzających, więc plik kontrolujący bota i panel obserwujący jego ruch pozostają zgodne.

```php
SEO::aiCrawlers()->all();               // every known AiCrawler
SEO::aiCrawlers()->get('gptbot');       // one bot
SEO::aiCrawlers()->actionFor('gptbot'); // 'allow' | 'disallow' (resolved policy)
SEO::aiCrawlers()->match($userAgent);   // identify a request UA, or null
```

Obejmuje największych operatorów — OpenAI (GPTBot, OAI-SearchBot, ChatGPT-User), Anthropic (ClaudeBot, Claude-SearchBot, Claude-User), Google (Google-Extended), Perplexity, Apple (Applebot-Extended), Common Crawl (CCBot), Meta, Amazon, ByteDance i innych — każdego z udokumentowanym zastosowaniem i tokenem robots.txt.

### Wyszukiwarki regionalne {#regional-search-engines}

Od wersji 3.15 katalog zawiera również klasyczne roboty wyszukiwarek istotne poza światem Google/Bing, oznaczone zastosowaniem `search_engine` i **domyślnie dozwolone**:

| id | Token | Operator |
|---|---|---|
| `yandex` | `Yandex` | Yandex (Rosja) — sam token obejmuje wszystkie jego boty |
| `baiduspider` | `Baiduspider` | Baidu (Chiny) |
| `yeti` | `Yeti` | Naver (Korea) |
| `seznambot` | `SeznamBot` | Seznam (Czechy) |
| `sogou` | `Sogou web spider` | Sogou (Chiny) |
| `360spider` | `360Spider` | Qihoo 360 (Chiny) |
| `coccocbot` | `coccocbot-web` | Cốc Cốc (Wietnam) |
| `duckduckbot` | `DuckDuckBot` | DuckDuckGo |

Biorą udział w `policy` i `overrides` jak każdy inny bot — dlatego `'overrides' => ['baiduspider' => 'disallow', 'sogou' => 'disallow']` ogranicza zużycie pasma przez dwa roboty, których nie obsługujesz, a `'list' => 'all'` nadaje każdemu jawną linię. Pozostają **poza** `all()` i `match()`, chyba że zostaną zażądane — `searchEngines()`, `all(true)`, `match($ua, true)` — dzięki czemu dziennik botów AI w Pro i każda liczba „N robotów AI” zachowują swoje znaczenie:

```php
SEO::aiCrawlers()->searchEngines();          // the eight engines
SEO::aiCrawlers()->get('yandex');            // works for both lists
SEO::aiCrawlers()->match($userAgent, true);  // identify an engine too
```

Rozpoznanie robota nie gwarantuje widoczności ani pozycji w danej wyszukiwarce. Odpowiednie tagi weryfikacji witryny (`yandex-verification`, `baidu-site-verification`, `naver-site-verification`, `seznam-wmt`) znajdują się w `seo.verification`; zobacz [Treści wielojęzyczne](/pl/guide/multilingual#site-verification).

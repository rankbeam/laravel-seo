---
description: "Vytvářejte spravovaný robots.txt a volitelný ai.txt z pravidel povolení a zákazu pro udržovaný katalog AI robotů. Sami určíte, kteří roboti smějí načítat váš web. Zdarma v Core."
---

# Řízení přístupu AI robotů (robots.txt / ai.txt) {#ai-crawler-control-robots-txt-ai-txt}

Hlavní provozovatelé AI procházejí web pomocí pojmenovaných robotů. Většina z nich čte **robots.txt**, aby zjistila, co smí načíst. Rankbeam dodává udržovaný katalog těchto robotů a vytváří spravovaný `robots.txt` a volitelný `ai.txt` podle jednoduchých pravidel povolení a zákazu. Můžete tak **povolit roboty AI vyhledávání a asistentů a omezit ty, kteří na vašem obsahu trénují modely.**

Jde o bezplatnou funkci balíčku Core. Pro přidává druhou část: [sledování návštěv AI robotů](/cs/pro/ai-bot-monitor), které ukazuje, kteří AI roboti web skutečně navštívili.

## Výchozí pravidla {#the-default-policy}

Každý robot v katalogu má přiřazený svůj hlavní účel:

| Účel | Co dělá | Výchozí pravidlo |
| --- | --- | --- |
| `ai_search` | Načítá stránky pro index odpovědí **AI vyhledávání**, které mohou na web přivést návštěvníky | **povolit** |
| `ai_assistant` | Načítá stránku v reálném čase jménem **uživatele** v chatu | **povolit** |
| `ai_training` | Sbírá obsah pro **trénování** modelu | **zakázat** |

To odpovídá přístupu většiny vydavatelů k AI: zůstat dostupní robotům vyhledávání a asistentů služeb jako ChatGPT search nebo Perplexity a zároveň odmítnout využití obsahu jako trénovacích dat. Vše lze změnit v konfiguraci.

::: warning Přístup neznamená citaci
Povolení robota *umožní* načtení obsahu. Nezaručí jeho objevení, indexaci, umístění, zařazení do odpovědi, citování ani uvedení zdroje. Tato pravidla řídí **přístup**, tedy kteří roboti smějí načítat stránky. Další zpracování už neovlivňují.
:::

## Rychlý začátek {#quick-start}

Vypište blok pravidel pro AI roboty a prohlédněte si, co byste zveřejnili:

```bash
php artisan seo:robots-txt --print
```

Použít jej můžete dvěma způsoby.

### Možnost A — vložení bloku do stávajícího robots.txt {#option-a-—-paste-the-block-into-your-existing-robots-txt}

Pokud už spravujete `public/robots.txt`, získejte jen spravovaný blok a vložte ho do souboru:

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

### Možnost B — celý soubor spravuje Rankbeam {#option-b-—-let-rankbeam-manage-the-whole-file}

Vygenerujte úplný `robots.txt`: obecná část, direktivy AI, řádek `Sitemap:` a odkaz na [llms.txt](/cs/guide/sitemaps):

```bash
php artisan seo:robots-txt          # writes public/robots.txt
php artisan seo:robots-txt --ai-txt # also write public/ai.txt
```

Naplánujte generování, aby soubor odpovídal aktuálním pravidlům:

```php
// routes/console.php
Schedule::command('seo:robots-txt')->daily();
```

Nebo jej poskytujte dynamicky: nastavte `seo.ai_crawlers.route` na `true` a balíček bude na `/robots.txt` odpovídat podle aktuální konfigurace bez nutnosti generování:

::: warning Statický soubor má přednost
Většina aplikací už obsahuje `public/robots.txt`, který webový server poskytne ještě před předáním požadavku Laravelu. Dynamická trasa je **ve výchozím nastavení vypnutá**, aby nemohla nepozorovaně překrýt zapomenutý soubor ani být překryta jím. Používejte ji jen tehdy, když statický `robots.txt` neexistuje.
:::

## Co lze skutečně vynutit {#honesty-about-enforcement}

robots.txt je žádost, nikoli bariéra. U většiny robotů v katalogu dokumentace uvádí, že jej respektují. U některých agentů spouštěných uživatelem (`ChatGPT-User`, `Perplexity-User`) a některých trénovacích robotů (`Bytespider`) tomu tak **není**. Rankbeam tyto řádky označuje jako `advisory`, aby nesliboval blokování, které nefunguje. Robota nerespektujícího pravidla skutečně zastavíte až blokováním na serveru nebo na okraji infrastruktury: firewallem, WAF či pravidly Cloudflare pro roboty. [Protokol návštěv AI robotů v Pro](/cs/pro/ai-bot-monitor) ukáže, na které se zaměřit.

## Content signals (preference využití obsahu) {#content-signals-usage-preferences}

`Allow` / `Disallow` řídí **přístup**, tedy zda robot smí stránku načíst. [Content signals](https://contentsignals.org), standard prosazovaný Cloudflare, popisují druhou oblast: jak se smí obsah po načtení **použít**. Jeden řádek `Content-Signal:` ve skupině `User-agent: *` nese tři preference:

| Signál | Odvozený z účelu v pravidlech | Význam |
| --- | --- | --- |
| `search` | `ai_search` | Vytváření vyhledávacího indexu: odkazy a krátké úryvky |
| `ai-input` | `ai_assistant` | Předávání stránky AI modelu v reálném čase (RAG / ukotvení odpovědi ve zdrojích) |
| `ai-train` | `ai_training` | Trénování nebo dotrénování AI modelu |

Funkce je **ve výchozím nastavení vypnutá**; soubor zůstává bajtově totožný, dokud ji nezapnete. Po zapnutí Rankbeam odvodí řádek přímo ze stávajících pravidel `policy`: `allow` se změní na `yes` a `disallow` na `no`:

```php
'ai_crawlers' => [
    'content_signals' => true,   // env: SEO_AI_CONTENT_SIGNALS
    // ...with the default policy, this emits, in the User-agent: * group:
    //   Content-Signal: search=yes, ai-input=yes, ai-train=no
],
```

Pokud účel z `policy` úplně odstraníte, příslušný signál se **vynechá**. Podle specifikace to znamená „preference není vyjádřena“, což se liší od výslovného `yes`/`no`.

::: warning Doporučení, stejně jako samotný robots.txt
Content signals vyjadřují preference; **nejsou** technickým omezením. Robot je může ignorovat. Doplňují výše uvedená pravidla přístupu a případné blokování na okraji infrastruktury, nenahrazují je.
:::

## Konfigurace {#configuration}

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

Pravidlo pro jednotlivého robota přepíšete bez ohledu na jeho účel pomocí `overrides`. Klíčem je **id** z katalogu, například `gptbot`, `claudebot`, `perplexitybot` nebo `google-extended`.

## Katalog {#the-catalog}

Zdrojem pravdy je `SEO::aiCrawlers()`. Stejný katalog používá protokol návštěv v Pro k identifikaci návštěvníků, takže pravidla pro robota a panel sledující jeho návštěvy vycházejí ze stejných údajů.

```php
SEO::aiCrawlers()->all();               // every known AiCrawler
SEO::aiCrawlers()->get('gptbot');       // one bot
SEO::aiCrawlers()->actionFor('gptbot'); // 'allow' | 'disallow' (resolved policy)
SEO::aiCrawlers()->match($userAgent);   // identify a request UA, or null
```

Pokrývá hlavní provozovatele: OpenAI (GPTBot, OAI-SearchBot, ChatGPT-User), Anthropic (ClaudeBot, Claude-SearchBot, Claude-User), Google (Google-Extended), Perplexity, Apple (Applebot-Extended), Common Crawl (CCBot), Meta, Amazon, ByteDance a další. U každého uvádí dokumentovaný účel a token pro robots.txt.

### Regionální vyhledávače {#regional-search-engines}

Od verze 3.15 obsahuje katalog také klasické vyhledávací roboty významné mimo prostředí Googlu a Bingu. Mají účel `search_engine` a jsou **ve výchozím nastavení povolení**:

| id | Token | Provozovatel |
|---|---|---|
| `yandex` | `Yandex` | Yandex (Rusko) — samotný token pokrývá všechny jeho roboty |
| `baiduspider` | `Baiduspider` | Baidu (Čína) |
| `yeti` | `Yeti` | Naver (Korea) |
| `seznambot` | `SeznamBot` | Seznam (Česko) |
| `sogou` | `Sogou web spider` | Sogou (Čína) |
| `360spider` | `360Spider` | Qihoo 360 (Čína) |
| `coccocbot` | `coccocbot-web` | Cốc Cốc (Vietnam) |
| `duckduckbot` | `DuckDuckBot` | DuckDuckGo |

Účastní se `policy` a `overrides` stejně jako ostatní roboti. Nastavení `'overrides' => ['baiduspider' => 'disallow', 'sogou' => 'disallow']` tak omezuje přenosy dvěma robotům, které nechcete obsluhovat, a `'list' => 'all'` vytvoří pro každého výslovný řádek. Z `all()` a `match()` jsou **vyloučeni**, pokud si je výslovně nevyžádáte pomocí `searchEngines()`, `all(true)` nebo `match($ua, true)`. Protokol AI robotů v Pro i všechny počty „N AI robotů“ si tak zachovávají svůj význam:

```php
SEO::aiCrawlers()->searchEngines();          // the eight engines
SEO::aiCrawlers()->get('yandex');            // works for both lists
SEO::aiCrawlers()->match($userAgent, true);  // identify an engine too
```

Rozpoznání robota nezaručuje viditelnost ani umístění v daném vyhledávači. Odpovídající značky ověření webu (`yandex-verification`, `baidu-site-verification`, `naver-site-verification`, `seznam-wmt`) jsou v `seo.verification`; viz [Vícejazyčný obsah](/cs/guide/multilingual#site-verification).

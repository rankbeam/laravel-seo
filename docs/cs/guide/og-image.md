---
description: "Každá stránka může mít vlastní obrázek Open Graph 1200×630 ze šablony Blade, vykreslený bezhlavým prohlížečem se správným zalomením a zkrácením titulku. Zdarma v Core, standardně vypnuto."
---

# Generované obrázky OG {#generated-og-images}

Od Core 3.20 vypíná vykreslování v Chromu JavaScript a blokuje požadavky na prostředky přes HTTP(S), FTP a WebSocket. Vlastní šablony musí používat statické HTML/CSS a vložené prostředky stejně jako dodané šablony.

Stránka bez vlastní karty pro sdílení použije společný `default_og_image`, tedy stejný obrázek při každém sdílení. Tato funkce dá každé stránce **vlastní** kartu Open Graph / Twitter 1200×630. Vykreslí ji skutečný bezhlavý prohlížeč ze šablony Blade přes [spatie/browsershot](https://github.com/spatie/browsershot). Titulek se tak zalamuje do řádků, diakritika se vykreslí, CJK použije vhodné náhradní písmo a příliš dlouhé titulky se správně zkrátí. Samotná ručně napsaná obrazová knihovna tohle vše neřeší.

Jde o bezplatnou funkci Core, **ve výchozím nastavení vypnutou**. Při vypnutí se `default_og_image` používá beze změny a balíček nepřibírá další závislosti.

::: info Záměrně statické generování předem
Karty se generují předem příkazem Artisan, nikoli během webového požadavku. Stránka odkazuje jen na kartu, která už existuje na disku. Požadavek návštěvníka tedy nikdy nespustí prohlížeč a neodkáže na chybějící obrázek s chybou 404. **Endpoint pro vykreslování za běhu neexistuje**; viz [Omezení](#caveats).
:::

## Požadavky {#requirements}

Ovladač prohlížeče je volitelná závislost, takže bezplatný Core se instaluje bez něj. Pro zapnutí funkce potřebujete v aplikaci:

```bash
composer require spatie/browsershot
```

A prostředí, které Browsershot ovládá:

- **Node.js** na serveru.
- **Puppeteer** nainstalovaný v **kořeni aplikace**, aby jej Node našel:
  ```bash
  npm install puppeteer
  ```
- **Chrome / Chromium** — Puppeteer standardně stáhne vlastní Chromium. Na produkci obvykle nastavíte systémový Chrome; viz [`chrome_path`](#configuration).

::: warning Ve Windows instalujte puppeteer do kořene aplikace
Ve Windows nainstalujte `puppeteer` do kořene aplikace, místo spoléhání na `npm_module_path`. Tento klíč odpovídá `setNodeModulePath()` Browsershotu, které vytvoří POSIX prefix `NODE_PATH=…` a **ve Windows nemá účinek**. Node tam hledá moduly v nadřazených adresářích aplikace, proto funguje instalace v kořeni. Viz [Omezení](#caveats).
:::

## Zapnutí {#enabling}

Pokud jste to ještě neudělali, zkopírujte konfiguraci do aplikace pomocí `php artisan vendor:publish --tag=seo-config` a zapněte přepínač:

```php
// config/seo.php
'og_image' => [
    'enabled' => true,   // requires spatie/browsershot + Chrome
],
```

Potom karty **vygenerujte předem**. Dokud to neuděláte, nic se nevykreslí:

```bash
php artisan seo:og-images
```

## Jak funguje vyhodnocování {#how-resolution-works}

Generování nikdy nepřepíše obrázek, který jste nastavili. Při zapnuté funkci resolver doplní `og:image` **jen tehdy, když stránka nemá vlastní obrázek**. Vyhodnocené `og:image` tedy musí být prázdné nebo stále odpovídat společnému statickému `default_og_image`. Výslovný obrázek modelu z `getSEOImage()`, řádku `seo_meta`, pole obsahu a podobně má vždy přednost před generovanou kartou.

Resolver volá vyhledání generátoru **podmíněné existencí souboru**. Spočítá cestu úložiště a vrátí veřejnou URL **jen tehdy, pokud soubor už existuje na nastaveném disku**. Nikdy nevykresluje. Odtud plynou ochrany:

- Webový požadavek **nikdy nespustí prohlížeč**. V nejhorším odkáže na statický `default_og_image`, stejně jako před zavedením funkce.
- Stránka **nikdy neodkáže na dosud nevygenerovaný obrázek**, takže nevznikne období, kdy sdílení míří na 404.

Mezeru mezi změnou obsahu a existencí karty uzavřete spuštěním příkazu [`seo:og-images`](#the-seo-og-images-command) při nasazení nebo podle plánu.

## Příkaz `seo:og-images` {#the-seo-og-images-command}

Vygeneruje karty předem, aby resolver měl co poskytovat.

```bash
php artisan seo:og-images                         # warm the configured models
php artisan seo:og-images --model="App\Models\Post"
php artisan seo:og-images --force                 # re-render even existing cards
php artisan seo:og-images --prune                 # + delete orphaned cards
```

- `--model=*` — jedna nebo více tříd modelů k předgenerování. Lze opakovat. Bez volby příkaz použije `seo.og_image.models`, případně [modely mapy webu](/cs/guide/sitemaps), `seo.sitemap.models`. Sdílí tak zdroje mapy webu stejně jako `seo:llms-txt`.
- `--force` — znovu vykreslí existující karty. Použijte po změně šablony nebo barev značky bez zvýšení `cache_version`.
- `--prune` — po předgenerování odstraní uložené karty pod nastavenou cestou, které už neodpovídají obsahu žádného současného modelu, viz níže. Z bezpečnostních důvodů odstraňuje jen soubory pojmenované generovaným hashem obsahu, nikdy jiné prostředky ve stejném adresáři. **Ignoruje se při běhu omezeném volbou `--model`**, protože seznam zachovávaných souborů by nepokrýval ostatní modely. Spouštějte bez `--model`.

Každý model musí používat trait `HasSEO`. Záznam bez titulku se přeskočí, protože na kartu není co vložit. Příkaz hlásí počty `generated`, `skipped`, `failed` a při `--prune` také `pruned`.

### Plánování {#scheduling}

Generujte podle plánu, aby karty odpovídaly obsahu, a odstraňujte osiřelé soubory po změnách titulků:

```php
// routes/console.php
Schedule::command('seo:og-images --prune')->daily();
```

### Způsob zneplatnění {#the-invalidation-model}

Název souboru karty je **hash všeho, co ovlivňuje její pixely**: titulku, názvu webu, názvu šablony, ovladače, rozměrů, barev přechodu značky, čísla `cache_version` **i nainstalované verze balíčku**.

Hash je klíčem mezipaměti a má dva důsledky:

- **Změna titulku → nový hash → nový soubor.** Stará karta je na disku *osiřelá* a stránka do nového předgenerování přejde na statickou výchozí. Příkaz vytvoří novou kartu a `--prune` odstraní osiřelou. Tak funguje zneplatnění; samostatný krok pro zneplatnění jedné stránky neexistuje.
- **Zvýšení `cache_version` nebo aktualizace balíčku → změna všech hashů.** Po úpravě šablony či barev použijte `cache_version` ke zneplatnění všech karet najednou. Aktualizace balíčku se zahrne automaticky, takže nové vydání měnící dodanou šablonu nemůže poskytovat zastaralé karty.

## Dodané šablony {#bundled-templates}

Balíček obsahuje tři šablony, všechny 1200×630 se stejným barevným přechodem značky:

| Šablona | Vhodná pro | Zobrazuje |
| --- | --- | --- |
| `seo::og.default` | Cokoli | Titulek a název webu |
| `seo::og.article` | Blog a zprávy | Označení sekce nad titulkem, titulek, autora a datum |
| `seo::og.product` | Produkty a nabídky | Značku, štítek kategorie, titulek a popis |

Zvolte jednu globálně pomocí `seo.og_image.template` nebo mapujte šablony **podle typu modelu**, aby článek a produkt automaticky dostaly různé karty:

```php
// config/seo.php
'og_image' => [
    'templates' => [
        App\Models\Post::class    => 'seo::og.article',
        App\Models\Product::class => 'seo::og.product',
    ],
],
```

Model může vlastní šablonu přepsat také za běhu definicí `getOgImageTemplate(): ?string`. Vraťte název view nebo `null` pro mapu či výchozí hodnotu. Přednost má hook modelu, pak mapa `templates` a nakonec globální `template`.

## Úprava šablony {#customizing-the-template}

Karta je view Blade, standardně `seo::og.default`, vykreslené do samostatného dokumentu HTML. Dodaný font je vložený jako datové URI, takže prohlížeč nepotřebuje síť. Dva způsoby úpravy:

**Zkopírujte a upravte dodané view:**

```bash
php artisan vendor:publish --tag=seo-views
```

Potom upravte `resources/views/vendor/seo/og/default.blade.php`.

**Nebo nastavte vlastní view:**

```php
// config/seo.php
'og_image' => [
    'template' => 'og.my-card',   // resources/views/og/my-card.blade.php
],
```

Šablona dostane tyto proměnné:

| Proměnná | Typ | Poznámky |
| --- | --- | --- |
| `$title` | `string` | Titulek OG, pokud je nastavený, jinak titulek stránky. |
| `$siteName` | `?string` | Vyhodnocené `og:site_name`. |
| `$fontDataUri` | `string` | Dodaný tučný font jako URI `data:`; prázdný řetězec při nedostupnosti, pak prohlížeč použije vlastní bezpatkové písmo. |
| `$gradientFrom` | `string` | `seo.og_image.gradient_from`. |
| `$gradientTo` | `string` | `seo.og_image.gradient_to`. |
| `$width` | `int` | Šířka výstupu, standardně `1200`. |
| `$height` | `int` | Výška výstupu, standardně `630`. |
| `$locale` | `?string` | Vyhodnocené národní prostředí stránky pro atribut `<html lang>`. |
| `$author` | `?string` | Autor článku, používá `seo::og.article`. |
| `$publishedDate` | `?string` | Datum publikace pro `seo::og.article`: střední formát ICU podle národního prostředí stránky, pokud je dostupný. Jinak Carbon přeloží měsíc v pořadí `M j, Y`. Null, pokud datum není uvedené. |
| `$section` | `?string` | Sekce či kategorie obsahu: označení nad titulkem článku nebo štítek produktu. |
| `$description` | `?string` | Popis OG, jinak popis stránky; používá `seo::og.product`. |

::: info Název šablony patří do klíče mezipaměti
**Název** šablony i barvy přechodu vstupují do hashe obsahu. Přepnutí šablony či změna barev proto automaticky zneplatní existující karty. Úprava šablony *na místě* nikoli, protože název zůstává. Po úpravě zvyšte `cache_version` nebo spusťte `--force`.
:::

## Konfigurace {#configuration}

```php
// config/seo.php
'og_image' => [
    'enabled' => false,             // master switch (off by default)
    'driver'  => 'browsershot',     // the render driver; register your own via OgImageManager::extend()
    'template' => 'seo::og.default', // the default Blade view rendered as the card
    'templates' => [],              // per-model-class template overrides (see "Bundled templates")
    'strip_title_suffix' => true,   // trim seo.title_suffix off the card title (the card shows the site name itself)

    'width'  => 1200,               // social-card standard
    'height' => 630,

    'disk' => 'public',             // must be publicly served — its url() becomes the og:image
    'path' => 'og-images',          // path prefix on that disk

    // Models seo:og-images warms. Empty → falls back to seo.sitemap.models.
    // Accepts a list [Post::class] or a map [Post::class => [...]].
    'models' => [],

    // Bump to invalidate every card after editing a template/colors in place.
    // The installed package version is folded in too, so an upgrade busts them.
    'cache_version' => 1,

    // Brand gradient (diagonal) for the bundled default template.
    'gradient_from' => '#1e2a5a',
    'gradient_to'   => '#3D5AFE',

    // Browsershot binary paths. null = its defaults (node/npx on PATH,
    // puppeteer's bundled Chromium). Set explicitly in production.
    'chrome_path'     => null,      // path to a system Chrome/Chromium
    'node_binary'     => null,      // path to the node binary
    'npm_module_path' => null,      // node_modules dir (no-op on Windows — see Caveats)

    'timeout' => 60,                // hard per-render timeout, seconds

    // Launch Chrome with --no-sandbox; weakens browser isolation.
    // Prefer configuring the host to support Chrome's sandbox (see below).
    'no_sandbox' => false,

    // Extra Chromium CLI flags, e.g. ['disable-dev-shm-usage', 'disable-gpu']
    // on a low-/dev-shm container. Leading "--" optional; map form for
    // value-bearing flags: ['proxy-server' => 'http://…'].
    'browsershot_args' => [],

    // Fallback font families for glyphs the bundled face lacks (CJK, Thai,
    // Arabic, …). null = the built-in Noto list; see "Fonts and non-Latin
    // scripts" below.
    'font_stack' => null,
],
```

Většina skalárních hodnot má odpovídající proměnnou prostředí, například `SEO_OG_IMAGE_ENABLED`, `SEO_OG_IMAGE_DISK`, `SEO_OG_IMAGE_CHROME_PATH` a `SEO_OG_IMAGE_NO_SANDBOX`. Úplný seznam je v konfiguračním souboru. Klíče s poli, `templates`, `models`, `browsershot_args` a `font_stack`, upravujte přímo v konfiguraci.

Disk musí být **veřejně dostupný**, protože resolver používá jeho `url()` jako hodnotu `og:image`. Pro disk `public` jednou spusťte `php artisan storage:link`, aby na něj odkazovalo `public/storage`.

## Běh na Linuxu (sandbox) {#running-on-linux-the-sandbox}

Na serverech omezujících mechanismy sandboxu Chromu může `php artisan seo:og-images` selhat s chybou:

```
No usable sandbox! Update your OS ... or see
https://chromium.googlesource.com/.../linux/suid_sandbox_development.md
```

Jednou z možných příčin jsou omezené uživatelské jmenné prostory na Ubuntu 23.10+. Zkontrolujte [průvodce řešením potíží Puppeteer](https://pptr.dev/troubleshooting) a skutečnou chybu při spuštění prohlížeče. Upřednostněte opravu konfigurace serveru, aby Chrome mohl sandbox zachovat.

**1. Výslovná náhradní možnost: Chrome s `--no-sandbox`.** Tím vypnete izolaci prohlížeče. Použijte jen tehdy, pokud tento kompromis při nasazení záměrně přijímáte:

```php
// config/seo.php
'og_image' => [
    'no_sandbox' => true,   // or set SEO_OG_IMAGE_NO_SANDBOX=true
],
```

Rankbeam vykresluje statické generované HTML a blokuje vzdálené prostředky, ale tyto kontroly nenahrazují sandbox Chromu. Vykreslovací proces ponechte bez zvýšených oprávnění a oddělený od nesouvisejících úloh a tajných údajů.

**2. Zachování sandboxu.** Ponechte `no_sandbox` vypnuté. Pokud je příčinou AppArmor, upravte profil pro přesný spustitelný soubor Chromu; viz [pokyny Chromium](https://chromium.googlesource.com/chromium/src/+/main/docs/security/apparmor-userns-restrictions.md). Například:

```
# /etc/apparmor.d/chrome-og
abi <abi/4.0>,
include <tunables/global>
profile chrome-og /path/to/chrome flags=(unconfined) {
  userns,
  include if exists <local/chrome-og>
}
```

Potom profil načtěte pomocí `sudo apparmor_parser -r /etc/apparmor.d/chrome-og` a ověřte, že Chrome startuje se zapnutým sandboxem.

::: tip Další přepínače
Pro kontejner s nedostatkem sdílené paměti, další běžnou příčinou pádu Chromu při vykreslování na Linuxu, přidejte přepínače přes `browsershot_args`:

```php
'browsershot_args' => ['disable-dev-shm-usage'],
```
:::

## Vlastní ovladače {#custom-drivers}

`browsershot` je jediný dodaný ovladač, ale renderer je za rozhraním `Rankbeam\Seo\Contracts\OgImageRenderer`. Zaregistrujte vlastní, například nad canvasem nebo službou, a vyberte jej přes `seo.og_image.driver`:

```php
use Rankbeam\Seo\Services\OgImage\OgImageManager;

app(OgImageManager::class)->extend('my-driver', fn ($app) => new MyRenderer());
```

Ovladač pouze převede samostatný řetězec HTML na bajty PNG v dané velikosti. Neřídí rozvržení ani šablony.

## Fonty a nelatinková písma {#fonts-and-non-latin-scripts}

Dodaný font karty, Noto Sans Bold pod OFL, pokrývá **latinku, cyrilici a řečtinu**. Ostatní písma, čínština, japonština, korejština, thajština, arabština, hebrejština, dévanágarí a emoji, pocházejí z fontů **nainstalovaných na stroji, kde běží `seo:og-images`**. Další fonty se záměrně nepřibalují: jediný font CJK má přes 16 MB a náhradní výběr jednotlivých znaků v Chromu funguje, jakmile je na serveru vhodný font.

Spolehlivost podporují tři věci, od verze 3.15:

1. **Zásobník `font-family` podle písma v každé dodané šabloně.** Tělo nejprve deklaruje `'OGBrand'`, dodaný font, pak `seo.og_image.font_stack`. Výchozí pořadí obsahuje `Noto Sans`, čtyři rodiny `Noto Sans CJK`, `Noto Sans Thai`, `Noto Sans Arabic`, `Noto Sans Hebrew`, `Noto Sans Devanagari` a `Noto Color Emoji`, nakonec `sans-serif`. Chrome pro jednotlivý znak použije první nainstalovanou rodinu; chybějící přeskočí, takže seznam pouze pomáhá. **Rodina CJK jazyka stránky se přesune dopředu**: `ja` → JP, `zh-Hans` → SC, `zh-Hant` / `zh-TW` / `zh-HK` → TC, `ko` → KR. Stejný kódový bod Han se totiž v jednotlivých národních fontech kreslí odlišně, jde o sjednocení Han. Atribut `<html lang>` nese národní prostředí stránky ve formátu BCP 47. Zásobník patří do klíče mezipaměti, takže jeho změna zneplatní všechny karty pro další předgenerování.

2. **Předběžná kontrola v `seo:og-images`.** Před vykreslením se příkaz dotáže fontconfig, `fc-list :lang=ja`, `th`, `ar` a další, zda některý font pokrývá písma v titulku, názvu webu a popisu včetně menšinově zastoupeného písma ve smíšeném textu. **Jednou pro každé písmo** upozorní a uvede balíček k instalaci:

   ```
   No installed font covers cjk text — its cards may render as boxes. Install one: apt-get install fonts-noto-cjk
   ```

   Pokud fontconfig chybí, například ve Windows, macOS nebo minimálním kontejneru, mlčí místo odhadování. Vykreslení samo kvůli chybějícímu fontu neselže: Chrome vykreslí čtverečky .notdef. Právě proto upozornění existuje.

3. **Testovací znaky pro každé písmo v živém smoke testu.** S `SEO_OG_IMAGE_LIVE_TEST=1` vykreslí `tests/Feature/OgImage/BrowsershotSmokeTest.php` titulek v ja, zh-Hans, zh-Hant, ko, el, ru, tr, th, ar, he a hi vedle stejně dlouhého kontrolního textu z nepřiřazeného kódového bodu, tedy zaručených čtverečků. Jsou-li obě PNG bajtově totožná, test selže a uvede písmo i balíček. Jde o orientační test, nikoli důkaz každého znaku: příměs latinky nebo jiné zalomení může obrázky odlišit i při chybějících znacích. Zkontrolujte skutečný výstup a použité fonty na serveru nasazení. Také jazykové upozornění FontProbe je předběžná kontrola, nikoli úplný certifikát pokrytí. Core nemá příkaz `seo:doctor`; pro tuto kontrolu použijte `seo:og-images`.

Na Debianu/Ubuntu:

```bash
apt-get install fonts-noto-cjk fonts-noto-core fonts-noto-color-emoji
fc-cache -f
```

Vlastní šablony zkopírované přes `--tag=seo-views` před verzí 3.15 dál fungují. Dostanou nové proměnné `$fontFamily` a `$lang`, které mohou ignorovat.

## Omezení {#caveats}

Výslovně uvedená, protože na produkci mají dopad:

- **Jen generování předem, bez endpointu za běhu (v1).** Neexistuje trasa vykreslující kartu na požádání. Protože se při webovém požadavku nic nevykresluje, **není zde rozhraní s podepsanými URL, SSRF či DoS k nastavení nebo ochraně**. Nevýhodou je nutnost spouštět [`seo:og-images`](#the-seo-og-images-command) při nasazení nebo podle plánu, aby karty existovaly.
- **`npm_module_path` ve Windows nemá účinek.** Odpovídá `setNodeModulePath()` Browsershotu, které před příkaz přidá POSIX `NODE_PATH=…`, ve Windows ignorované. Ve Windows instalujte `puppeteer` do **kořene aplikace**, aby jej Node našel přes nadřazené adresáře. Na Linuxu/macOS nastavení funguje podle očekávání.
- **Nelatinková písma potřebují font na serveru.** Viz [Fonty a nelatinková písma](#fonts-and-non-latin-scripts): dodaný font pokrývá latinku, cyrilici a řečtinu. Ostatní pocházejí z fontů nainstalovaných v prostředí nasazení a příkaz upozorní na chybějící.
- **Při chybě zachová výchozí obrázek.** Selže-li vykreslení kvůli chybějícímu balíčku, pádu prohlížeče nebo časovému limitu, příkaz to oznámí a stránka ponechá statický `default_og_image`. Nefunkční prohlížeč nikdy nezpůsobí stránce chybu 500.

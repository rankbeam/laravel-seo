---
description: "Generujte XML mapy webu — jeden soubor na zdroj a společný index — dostupné na /sitemap.xml. Registrujte modely, anonymní funkce nebo seznamy URL. Využívá spatie/laravel-sitemap."
---

# Registr map webu {#sitemap-registry}

Balíček generuje XML mapy webu, jeden soubor na zdroj a společný index, a poskytuje je na `/sitemap.xml` a `/sitemap-{name}.xml`. Generování využívá [spatie/laravel-sitemap](https://github.com/spatie/laravel-sitemap):

```bash
composer require spatie/laravel-sitemap
```

## Registrace zdrojů {#registering-sources}

Pojmenované zdroje registrujte v metodě `boot()` poskytovatele služeb:

```php
use App\Models\Post;
use Rankbeam\Seo\Facades\SEO;

// A model class — every (indexable) record's getUrlForSEO()
SEO::sitemaps()->register('posts', Post::class);

// A closure returning URLs
SEO::sitemaps()->register('pages', fn () => ['/about', '/contact']);

// Any iterable of URLs
SEO::sitemaps()->register('legal', ['/imprint', '/privacy']);
```

Každý zdroj se vykreslí do `sitemap-{name}.xml`. Soubor `sitemap.xml` se stane indexem všech těchto map.

API registru nabízí také `has($name)`, `names()`, `forget($name)` a `flush()`.

## Zdroje z konfigurace {#config-driven-sources}

Dáváte přednost konfiguraci? `config/seo.php` přijímá modelové zdroje i statické URL:

```php
'sitemap' => [
    'models' => [
        \App\Models\Post::class => ['priority' => 0.8, 'changefreq' => 'weekly'],
    ],
    'static_urls' => [
        ['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily'],
    ],
],
```

::: info Automatické objevování dává přednost registru
Pokud model pokrývá pojmenovaný registrovaný zdroj, automatické objevování jej přeskočí. Registrace `'posts'` tedy nevytvoří navíc i `sitemap-post.xml`.
:::

## Generování {#generating}

```bash
php artisan seo:sitemap
```

Soubory se zapisují na disk nastavený v `seo.sitemap.disk`, ve výchozím nastavení `public`. Naplánujte příkaz, aby mapy webu zůstávaly aktuální:

```php
// routes/console.php or bootstrap/app.php scheduling
Schedule::command('seo:sitemap')->daily();
```

Mapy překračující `seo.sitemap.max_urls_per_sitemap` se automaticky rozdělí. Výchozí hodnota 50 000 odpovídá limitu specifikace XML.

## Poskytování souborů {#serving}

Trasy balíčku poskytují soubory vygenerované příkazem, s hlavičkami XML, mezipaměti a `X-Robots-Tag: noindex`:

- `/sitemap.xml` — index nebo jediná mapa webu
- `/sitemap-posts.xml` — pojmenovaný zdroj

Poskytujete raději vlastní staticky generovanou mapu webu? Vypněte trasy:

```php
// config/seo.php
'routes' => ['enabled' => false],
```

## Stylovaná mapa webu v prohlížeči {#styled-sitemap-in-the-browser}

Generátor Spatie vytváří prostý blok XML. Když mapu Rankbeamu otevřete v prohlížeči, uvidíte místo něj čitelnou stránku s vizuální identitou Rankbeamu: tabulku všech URL s `lastmod`, četností změn, prioritou a počty obrázků či jazykových alternativ. Doplní ji validační poznámky:

![Mapa webu Rankbeamu zobrazená v prohlížeči jako čitelná tabulka s vizuální identitou značky](/sitemap-styled.png)

Každá vygenerovaná mapa odkazuje na stylopis XSL:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<?xml-stylesheet type="text/xsl" href="https://your-app.test/sitemap.xsl"?>
<urlset ...>
```

Vyhledávače tuto instrukci **ignorují**, takže mapa zůstává běžným strojově čitelným dokumentem XML. Mění se pouze to, co vidí *člověk*. Stejný vzhled má index i všechny podřízené mapy.

**Ve výchozím nastavení zapnuto.** Na rozdíl od rozšíření pro obrázky a hreflang stylopis nepřidává data ani práci pro jednotlivé záznamy. Jde o jediný řádek instrukce, který roboti přeskočí, proto je standardně zapnutý. Pokud chcete prosté XML, vypněte jej:

::: warning Vyžaduje spatie/laravel-sitemap ≥ 8.1
Instrukce se zapisuje pomocí `setStylesheet()` ze Spatie, přidaného v `spatie/laravel-sitemap` **8.1**. Pokud aplikace používá starší verzi, což u některých kombinací PHP a Laravelu nastává, mapy se vygenerují jako prosté XML bez stylů a vše dál funguje. Pro stylované zobrazení spusťte `composer update spatie/laravel-sitemap`.
:::

```php
// config/seo.php
'sitemap' => [
    'stylesheet' => ['enabled' => false],
],
```

### Validační poznámky {#validation-notes}

Vykreslená stránka upozorní na dvě věci, které může ověřit přímo v prohlížeči:

- **URL bez `lastmod`** — chybějící hodnotu označí, nikdy si ji nevymýšlí. Google přikládá menší váhu mapě, která uvádí nepravdivou aktuálnost, proto stylopis na chybějící údaj upozorní místo jeho doplnění.
- **Neabsolutní URL** — `<loc>`, které není absolutní adresou `http(s)`.

### Vlastní hostování stylopisu {#self-hosting-the-stylesheet}

Ve výchozím nastavení poskytuje balíček stylopis ze své trasy `/sitemap.xsl` a každá mapa na něj odkazuje. Prohlížeče použijí XSLT pouze tehdy, když má s mapou **stejný původ (origin)**. Pokud jsou mapy na jiném původu, například na CDN, zkopírujte soubor z balíčku a konfiguraci nasměrujte na vlastní kopii:

```bash
php artisan vendor:publish --tag=seo-assets
```

```php
// config/seo.php
'sitemap' => [
    'stylesheet' => [
        'url' => 'https://cdn.example.com/vendor/seo/sitemap.xsl',
    ],
],
```

::: info Bezpečnost je součástí návrhu
Každá hodnota vykreslená stylopisem, včetně URL, prochází escapováním výstupu XSLT. Z `<loc>` se stane klikací odkaz jen tehdy, jde-li o URL `http(s)`. Škodlivý obsah URL tak nemůže do stránky vložit značky ani odkaz `javascript:`. Pokud upravujete zkopírovaný `.xsl`, zachovejte toto chování a nepřidávejte `disable-output-escaping`.
:::

## Co mapa obsahuje {#what-gets-included}

Modelové zdroje zahrnují záznamy, které se vyhodnotí jako indexovatelné. Model s vyhodnoceným robots `noindex` se do mapy nedostane. URL pocházejí z `getUrlForSEO()`, stejné metody jako kanonické adresy. Mapa webu a kanonická URL tak vycházejí ze stejného zdroje.

## Rozšíření pro obrázky a hreflang {#image-hreflang-extensions}

Dvě volitelná rozšíření obohacují URL modelu o data, která balíček už pro daný záznam vyhodnocuje. Obě jsou **ve výchozím nastavení vypnutá**. Požadovaná zapněte v `config/seo.php`:

```php
'sitemap' => [
    'images' => true,      // <image:image> per URL
    'alternates' => true,  // <xhtml:link rel="alternate"> per URL
],
```

Platí pro modely používající trait `HasSEO`. Hodnoty pocházejí z plně vyhodnoceného `seoData()` modelu:

- **`images`** přidá položku [obrázkové mapy webu Google](https://developers.google.com/search/docs/crawling-indexing/sitemaps/image-sitemaps) sestavenou z vyhodnoceného OG obrázku nebo obrázku obsahu. Jde o *stejnou* hodnotu jako vykreslené `og:image`, takže mapa odpovídá stránce. Pokud záznam nemá vlastní obrázek, použije se celowebové `default_og_image`. Rozšíření proto zapínejte jen tehdy, když má obrázek pro každou URL u vašeho obsahu smysl.
- **`alternates`** přidá položky `<xhtml:link rel="alternate" hreflang="…">` z `getSEOAlternates()` modelu. Jde o stejné odkazy hreflang, které se vykreslí v `<head>` stránky. Vracejte absolutní URL:

```php
public function getSEOAlternates(): ?array
{
    return [
        ['hreflang' => 'en', 'href' => route('posts.show', [$this, 'locale' => 'en'])],
        ['hreflang' => 'fr', 'href' => route('posts.show', [$this, 'locale' => 'fr'])],
        ['hreflang' => 'x-default', 'href' => route('posts.show', $this)],
    ];
}
```

::: warning Hreflang musí být vzájemné a zahrnovat vlastní stránku
Google uznává anotaci jen tehdy, když každá jazyková verze uvádí **sebe i všechny ostatní** a odkazy jsou **vzájemné**, tedy každá stránka odkazuje zpět. Metoda `getSEOAlternates()` proto musí vracet **úplnou** sadu a všechny lokalizované varianty musí vracet tutéž úplnou sadu. Používejte platné kódy `language[-Script][-REGION]` nebo `x-default` a absolutní URL `http(s)`. Položky bez neprázdného `hreflang` nebo `href` se přeskočí.

Před zápisem seznam projde [pravidly `seo.hreflang`](/cs/guide/multilingual#hreflang). Kódy se normalizují na BCP 47 (`it_IT` → `it-IT`) a `include_self` / `x_default` mohou doplnit odkaz na vlastní stránku i `x-default`. Mapa vždy obsahuje stejný seznam jako `<head>` stránky. Bezplatný audit hlásí `hreflang_invalid_code`, `hreflang_duplicate_code` a `hreflang_missing_self`. Ověření vzájemnosti vyžaduje procházení stránek v Pro.
:::

::: info Náklady při větším objemu
Od **Core 3.20.1** se při sestavování jedné URL modelu opakovaně používá stejné vyhodnocené `seoData()` pro rozhodnutí o zařazení i rozšíření obrázků a hreflang. Toto sdílení končí po zpracování URL, a to i při chybě. Pozdější sestavení nebo jiná jazyková verze data vyhodnotí znovu. Ve verzi 3.20.0, s vypnutou mezipamětí resolveru jako ve výchozím nastavení, mohlo zařazení spolu s rozšířeními projít řetězec priorit dvakrát. Každé vyhodnocení může stále provádět operace s mezipamětí a databází a vlastní metody `getSEO*()` mohou přidat dotazy. Používejte **naplánovaný** příkaz `seo:sitemap` místo webového požadavku. Změřte výkon poblíž limitu 50 000 URL a nepotřebná rozšíření ponechte vypnutá.
:::

::: tip Máte už konfiguraci zkopírovanou do aplikace?
`config/seo.php` se slučuje **jen na první úrovni**. Aplikace, která zkopírovala konfigurační soubor před touto verzí, proto automaticky nezíská klíče `sitemap.images` / `sitemap.alternates`. Samotné proměnné prostředí `SEO_SITEMAP_IMAGES` / `SEO_SITEMAP_ALTERNATES` je nezapnou. Přidejte oba klíče do zkopírovaného pole `sitemap` podle příkladu výše nebo konfiguraci zkopírujte znovu.
:::

## Úplná kontrola: ručně sestavené značky Spatie {#full-control-hand-built-spatie-tags}

Pro vše, co vyhodnocená data nepokrývají — popisky obrázků, **video**, **zpravodajské** položky nebo vlastní sady `hreflang` — vraťte z registrovaného zdroje plně ručně sestavený [`Spatie\Sitemap\Tags\Url`](https://github.com/spatie/laravel-sitemap#adding-images). Generátor předává značky `Url` beze změny a nikdy k nim nepřidává vlastní rozšíření. Zachováváte tak plnou kontrolu:

```php
use Spatie\Sitemap\Tags\Url;

SEO::sitemaps()->register('videos', fn () => Video::query()
    ->get()
    ->map(fn (Video $video) => Url::create($video->url)
        ->addImage($video->thumbnail_url, caption: $video->title)
        ->addVideo(
            thumbnailLoc: $video->thumbnail_url,
            title: $video->title,
            description: $video->description,
            contentLoc: $video->file_url,
        )
        ->addAlternate($video->frenchUrl, 'fr')
    ));
```

Stejnou možnost máte u jednotlivých záznamů: model implementující `Sitemapable`, jehož `toSitemapTag()` vrací `Url`, se zapíše přesně v podobě, kterou metoda vrátila.

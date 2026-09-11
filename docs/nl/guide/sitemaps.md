---
description: "Genereer XML-sitemaps met één bestand per bron en een index op /sitemap.xml. Registreer bronnen vanuit modellen, closures of URL-lijsten, op basis van spatie/laravel-sitemap."
---

# Sitemapregister {#sitemap-registry}

Het pakket genereert XML-sitemaps (één bestand per bron en een index) en biedt ze
aan via `/sitemap.xml` en `/sitemap-{name}.xml`. De generatie maakt gebruik van
[spatie/laravel-sitemap](https://github.com/spatie/laravel-sitemap):

```bash
composer require spatie/laravel-sitemap
```

## Bronnen registreren {#registering-sources}

Registreer benoemde bronnen in de `boot()` van een serviceprovider:

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

Elke bron wordt geschreven naar `sitemap-{name}.xml`; `sitemap.xml` wordt de index
waarin ze allemaal staan.

De register-API biedt ook `has($name)`, `names()`, `forget($name)` en `flush()`.

## Bronnen via configuratie {#config-driven-sources}

Werk je liever met configuratie? `config/seo.php` accepteert modelbronnen en statische URL's:

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

::: info Het register heeft voorrang op automatische detectie
Als een model onder een benoemde geregistreerde bron valt, slaat automatische
detectie het over. Het registreren van `'posts'` levert dus niet daarnaast
ook een `sitemap-post.xml` op.
:::

## Genereren {#generating}

```bash
php artisan seo:sitemap
```

De bestanden worden naar de schijf onder `seo.sitemap.disk` geschreven
(standaard `public`). Plan het commando in om sitemaps actueel te houden:

```php
// routes/console.php or bootstrap/app.php scheduling
Schedule::command('seo:sitemap')->daily();
```

Sitemaps die meer dan `seo.sitemap.max_urls_per_sitemap` URL's bevatten, worden automatisch opgesplitst.
De standaardwaarde is 50.000, de limiet van de XML-specificatie.

## Aanbieden {#serving}

De routes van het pakket bieden de gegenereerde bestanden aan met XML-headers,
cacheheaders en `X-Robots-Tag: noindex`:

- `/sitemap.xml` — de index of de enige sitemap
- `/sitemap-posts.xml` — een benoemde bron

Bied je zelf een statisch gegenereerde sitemap aan? Schakel de routes dan uit:

```php
// config/seo.php
'routes' => ['enabled' => false],
```

## Een opgemaakte sitemap in de browser {#styled-sitemap-in-the-browser}

De engine van Spatie genereert onopgemaakte XML. Open je een Rankbeam-sitemap in
een browser, dan zie je een leesbare pagina in de huisstijl: elke URL staat in
een tabel met `lastmod`, wijzigingsfrequentie, prioriteit en aantallen
afbeeldingen en taalalternatieven. Validatieopmerkingen staan er direct bij:

![Een Rankbeam-sitemap als leesbare tabel in de huisstijl in de browser](/sitemap-styled.png)

Dit werkt door vanuit elke gegenereerde sitemap naar een XSL-stylesheet te verwijzen:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<?xml-stylesheet type="text/xsl" href="https://your-app.test/sitemap.xsl"?>
<urlset ...>
```

Zoekmachines **negeren** deze instructie. De sitemap blijft een gewoon machinaal
leesbaar XML-document; alleen wat een *mens* ziet verandert. De index en alle
onderliggende sitemaps krijgen dezelfde opmaak.

**Standaard aan.** Anders dan de image- en hreflang-uitbreidingen voegt de
stylesheet geen gegevens toe en doet ze geen werk per record. Het is één
instructieregel die crawlers overslaan en staat daarom standaard aan.
Schakel de opmaak uit om gewone XML uit te voeren:

::: warning Vereist spatie/laravel-sitemap ≥ 8.1
De instructie wordt geschreven via Spaties `setStylesheet()`, toegevoegd in
`spatie/laravel-sitemap` **8.1**. Als je applicatie een oudere versie gebruikt, wat bij
sommige PHP/Laravel-combinaties gebeurt, worden gewone sitemaps zonder opmaak
gegenereerd. Er gaat niets stuk. Gebruik `composer update spatie/laravel-sitemap` voor de opgemaakte weergave.
:::

```php
// config/seo.php
'sitemap' => [
    'stylesheet' => ['enabled' => false],
],
```

### Validatieopmerkingen {#validation-notes}

De gerenderde pagina markeert twee zaken die ze in de browser kan controleren:

- **URL's zonder `lastmod`** — de ontbrekende waarde wordt gemeld en nooit
  verzonnen. Google hecht minder waarde aan een sitemap die onjuiste actualiteit
  suggereert. De stylesheet wijst daarom op de ontbrekende waarde in plaats van die zelf in te vullen.
- **Niet-absolute URL's** — een `<loc>` die geen absolute `http(s)`-URL is.

### De stylesheet zelf hosten {#self-hosting-the-stylesheet}

Standaard biedt het pakket de stylesheet aan via zijn eigen `/sitemap.xsl`-route
en verwijst elke sitemap daarnaar. Browsers passen XSLT alleen toe als die
**dezelfde origin** heeft als de sitemap. Staan je sitemaps op een andere origin,
bijvoorbeeld een CDN, publiceer het bestand dan en verwijs in de configuratie naar je eigen kopie:

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

::: info Veilig opgezet
Elke waarde die de stylesheet rendert, inclusief URL's, wordt bij XSLT-uitvoer
geëscapet. Een `<loc>` wordt alleen een klikbare link als het een
`http(s)`-URL is. Kwaadaardige URL-inhoud kan dus geen markup of
`javascript:`-link in de pagina injecteren. Behoud dit als je de gepubliceerde
`.xsl` aanpast: voeg geen `disable-output-escaping` toe.
:::

## Wat wordt opgenomen {#what-gets-included}

Modelbronnen nemen records op die volgens de resolver indexeerbaar zijn.
Een model waarvan de uiteindelijke robots-waarde `noindex` is, blijft buiten
de sitemap. URL's komen uit `getUrlForSEO()`, dezelfde methode die canonieke URL's
bepaalt. De sitemap en de canonieke URL spreken elkaar dus nooit tegen.

## Uitbreidingen voor afbeeldingen en hreflang {#image-hreflang-extensions}

Twee optionele uitbreidingen verrijken elke model-URL met gegevens die het
pakket al voor dat record bepaalt. Beide staan **standaard uit**. Schakel de
gewenste uitbreidingen in via `config/seo.php`:

```php
'sitemap' => [
    'images' => true,      // <image:image> per URL
    'alternates' => true,  // <xhtml:link rel="alternate"> per URL
],
```

Ze gelden voor modellen met de trait `HasSEO`. De waarden komen uit de
volledig bepaalde `seoData()` van het model:

- **`images`** voegt een vermelding voor een
  [Google-afbeeldingensitemap](https://developers.google.com/search/docs/crawling-indexing/sitemaps/image-sitemaps)
  toe vanuit de uiteindelijke OG-/contentafbeelding. Dit is *dezelfde* waarde
  die als `og:image` wordt gerenderd, zodat de sitemap nooit van de pagina
  afwijkt. Heeft een record geen eigen afbeelding, dan wordt de sitebrede
  `default_og_image` gebruikt. Schakel dit dus alleen in als een afbeelding per URL
  zinvol is voor je content.
- **`alternates`** voegt `<xhtml:link rel="alternate" hreflang="…">`-vermeldingen toe vanuit
  `getSEOAlternates()` van het model: dezelfde hreflang-links die in de `<head>`
  van de pagina worden gerenderd. Geef absolute URL's terug:

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

::: warning hreflang moet wederkerig zijn en naar de eigen pagina verwijzen
Google houdt alleen rekening met een annotatie als elke taalversie **zichzelf
en alle andere versies** vermeldt en de verwijzingen **wederkerig** zijn:
elke pagina verwijst terug. `getSEOAlternates()` moet daarom de **volledige** set
teruggeven, en elke taalvariant moet dezelfde volledige set teruggeven.
Gebruik geldige `language[-Script][-REGION]`-codes of `x-default` en absolute
`http(s)`-URL's. Vermeldingen zonder een niet-lege `hreflang` of
`href` worden overgeslagen.

Vóór het schrijven wordt het [beleid van `seo.hreflang`](/nl/guide/multilingual#hreflang)
toegepast. Codes worden genormaliseerd naar BCP 47 (`it_IT` → `it-IT`),
en `include_self` / `x_default` kunnen de zelfverwijzing en `x-default`
voor je toevoegen. De sitemap bevat altijd dezelfde lijst als de `<head>`
van de pagina. De gratis audit rapporteert `hreflang_invalid_code`, `hreflang_duplicate_code` en
`hreflang_missing_self`. Wederkerigheid controleren vereist een crawl (Pro).
:::

::: info Kosten bij grote aantallen
Sinds **Core 3.20.1** hergebruiken de controle op modelopname en de image-/hreflang-uitbreidingen dezelfde uiteindelijke `seoData()` tijdens het opbouwen van één model-URL. Dat hergebruik eindigt na die URL, ook bij fouten. Een latere build of een andere locale bepaalt de gegevens opnieuw. In 3.20.0 konden opname en uitbreidingen de prioriteitsketen twee keer doorlopen als resolvercaching was uitgeschakeld, wat standaard het geval is. Elke resolutie kan nog steeds cache- en databasebewerkingen uitvoeren, en aangepaste `getSEO*()`-getters kunnen queries toevoegen. Gebruik het **ingeplande** commando `seo:sitemap` in plaats van een webaanvraag. Meet de prestaties in de buurt van de limiet van 50.000 URL's en laat uitbreidingen uit als je die vermeldingen niet nodig hebt.
:::

::: tip Heb je de configuratie al gepubliceerd?
`config/seo.php` wordt **ondiep** samengevoegd. Een applicatie die het
configuratiebestand vóór deze release publiceerde, krijgt de sleutels
`sitemap.images` / `sitemap.alternates` dus niet automatisch. Alleen de omgevingsvariabelen
`SEO_SITEMAP_IMAGES` / `SEO_SITEMAP_ALTERNATES` instellen schakelt de functies niet in.
Voeg de twee sleutels toe aan je gepubliceerde `sitemap`-array (zie het
voorbeeld hierboven) of publiceer de configuratie opnieuw.
:::

## Volledige controle: zelf opgebouwde Spatie-tags {#full-control-hand-built-spatie-tags}

Voor alles wat de bepaalde gegevens niet dekken — afbeeldingsbijschriften,
**video**, **nieuws** of eigen `hreflang`-sets — kun je vanuit een
geregistreerde bron een volledig zelf opgebouwde
[`Spatie\Sitemap\Tags\Url`](https://github.com/spatie/laravel-sitemap#adding-images) teruggeven.
De builder neemt `Url`-tags ongewijzigd over en voegt er nooit eigen
uitbreidingen aan toe. Je houdt dus de volledige controle:

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

Deze mogelijkheid bestaat ook per record. Als een model `Sitemapable` implementeert
en zijn `toSitemapTag()` een `Url` teruggeeft, wordt die exact zo weergegeven.

---
description: Alle opties in config/seo.php, gegroepeerd per resolverlaag, met de meegeleverde standaardwaarde voor elke instelling.
---

# Configuratie {#configuration}

Publiceer het configuratiebestand:

```bash
php artisan vendor:publish --tag=seo-config
```

Alles hieronder staat in `config/seo.php`. De getoonde waarden zijn de standaardwaarden.

## Sitebrede standaardwaarden (laag 1) {#site-wide-defaults-layer-1}

```php
'site_name'                       => env('APP_NAME', 'My Site'),
'title_suffix'                    => ' | ' . env('APP_NAME', 'My Site'),
'title_suffix_skip_when_contains' => [],   // brand tokens, e.g. ['Acme']
'default_og_image'                => env('SEO_DEFAULT_OG_IMAGE', '/images/og-default.jpg'),
'default_robots'                  => env('SEO_DEFAULT_ROBOTS', 'index,follow'),
'default_twitter_card'            => env('SEO_DEFAULT_TWITTER_CARD', 'summary_large_image'),
'twitter_site'                    => env('SEO_TWITTER_SITE'),     // @username, without @
'twitter_creator'                 => env('SEO_TWITTER_CREATOR'),
'favicon'                         => '/favicon.ico',
```

`title_suffix` wordt aan de uiteindelijke titel toegevoegd, tenzij die er al op eindigt.

`title_suffix_skip_when_contains` is een lijst die rekening houdt met de merknaam om het
achtervoegsel te onderdrukken. Als de uiteindelijke titel een van deze
tokens al **als volledig woord** bevat, wordt het achtervoegsel overgeslagen
om een dubbele merknaam te voorkomen. De vergelijking is niet hoofdlettergevoelig
en houdt rekening met woordgrenzen: `Acmestic` komt dus niet overeen met
`Acme`. De standaardwaarde `[]` behoudt het eerdere gedrag.

## Renderingbeleid voor robots {#robots-rendering-policy}

```php
'robots' => [
    'emit_default' => env('SEO_EMIT_DEFAULT_ROBOTS', false),
],
```

De gerenderde `<head>` laat de `<meta name="robots">`-tag weg wanneer de
uiteindelijke instructie gelijk is aan `default_robots` hierboven. Een
overbodige `index,follow` voegt ruis toe, en juist de afwezigheid daarvan
leest een crawler als index,follow. Een instructie die **afwijkt**, zoals
`noindex`, `nofollow` of `max-snippet:-1`, wordt altijd letterlijk
weergegeven. Zet `emit_default` op `true` om de tag altijd te renderen;
dit herstelt het gedrag van vóór 3.1. De afzonderlijke directive
`@seoRobots` verandert niet: die is een expliciete keuze en rendert altijd.
Zie het [renderingcontract](/nl/contributing/rendering-contract) voor de
ondersteunde instructies en hun prioriteit.

## Indexeringsbeveiliging buiten productie {#indexing-guard-non-production-safety-net}

```php
'indexing_guard' => [
    'enabled' => env('SEO_INDEXING_GUARD', false),
    'allowed_environments' => ['production'],
    'send_header' => env('SEO_INDEXING_GUARD_HEADER', true),
],
```

Als de beveiliging aanstaat en de app draait in een omgeving die **niet**
in `allowed_environments` staat, dwingt ze `noindex,nofollow` af op elke pagina.
Dit staat boven de volledige prioriteitsketen en overschrijft dus zelfs
een opgeslagen waarde per pagina. Ze verstuurt een overeenkomende
`X-Robots-Tag`-header, genereert een `robots.txt` die alle paden uitsluit en
laat `seo:audit` een opvallende melding tonen. In toegestane omgevingen,
standaard `production`, doet ze niets.

De beveiliging staat standaard **uit** en de uitvoer blijft byte voor byte
gelijk totdat je haar inschakelt. Inschakelen doe je met `SEO_INDEXING_GUARD=true`,
uitschakelen met `SEO_INDEXING_GUARD=false`: in beide gevallen één regel.
Vervang de toestemmingslijst via `SEO_INDEXING_GUARD_ALLOWED`, kommagescheiden.
`Str::is()`-jokertekens zoals `prod*` werken; een lege lijst
activeert de beveiliging overal.

`send_header` staat binnen de beveiliging standaard aan en verstuurt ook
`X-Robots-Tag: noindex,nofollow` met elke response die via de app loopt. Zo krijgen ook
PDF's, feeds en afbeeldingen zonder `<meta robots>` de indexeringsbeperking.
De middleware wordt alleen geregistreerd als de beveiliging aanstaat.
Dit wordt sterk aanbevolen. Zie de volledige
[handleiding voor indexeringsbeveiliging](/nl/guide/indexing-guard).

## Canonieke URL's {#canonical-urls}

```php
'canonical' => [
    'query_whitelist' => [],   // e.g. ['page']
],
```

Een canonieke URL die de resolver **afleidt** uit de aanvraag-URL of de
`getUrlForSEO()` van een model, verliest standaard de querystring.
Tracking-, filter- en sorteerparameters leveren immers verschillende
canonieke doelen op voor dezelfde pagina-inhoud. Vermeld sleutels in
`query_whitelist` om ze in afgeleide canonieke URL's **te behouden**, in de
opgegeven volgorde. Alle overige parameters worden nog steeds verwijderd.
Het gebruikelijke geval is `page` voor gepagineerde archieven:
`/blog?page=2` is werkelijk een andere pagina dan `/blog`.

Een **expliciet ingestelde** canonieke URL, ingevoerd door een beheerder
of afkomstig uit een laag met hogere prioriteit, wordt altijd letterlijk
weergegeven, inclusief querystring. De toestemmingslijst geldt alleen voor
de afgeleide terugvalwaarde. De standaardwaarde `[]` behoudt het
gedrag waarbij alle queryparameters worden verwijderd.

## Functieschakelaars {#feature-toggles}

```php
'features' => [
    'auto_create_meta' => env('SEO_AUTO_CREATE_META', true),
    'sitemap'          => env('SEO_SITEMAP_ENABLED', true),
    'schema'           => env('SEO_SCHEMA_ENABLED', true),
    'multilingual'     => env('SEO_MULTILINGUAL_ENABLED', false),
],
```

`auto_create_meta` maakt een lege `seo_meta`-rij aan wanneer een
`HasSEO`-model wordt aangemaakt. Let op: seeders met `WithoutModelEvents` omzeilen dit.

## Focuszoekwoorden {#focus-keywords}

```php
'keywords' => [
    'enabled' => env('SEO_KEYWORDS_ENABLED', false),
],
```

Dit is de **schakelaar voor de werkwijze met focuszoekwoorden**. Bij
`false`, de standaard, wordt een pagina zonder focuszoekwoord nergens
gemarkeerd: noch [`seo:audit`](/nl/guide/audit), noch de Pro-scan meldt dit.
Een app die geen focuszoekwoorden gebruikt, krijgt dus geen herinneringen
over een ongebruikte functie. Schakel de optie in zodra je focuszoekwoorden
gaat instellen, bijvoorbeeld met het
[Filament-veld voor focuszoekwoorden](/nl/guide/filament). De gratis audit,
Pro-scan en Pro-editor tonen dan allemaal de informatieve melding `missing_focus_keyword` op pagina's die nog
geen focuszoekwoord hebben. Ze lezen dezelfde instelling en komen dus altijd overeen.

## Gratis audit (`seo:audit`) {#free-audit-seo-audit}

```php
'audit' => [
    // \App\Models\Post::class, \App\Models\Page::class
    'models' => [],
],
```

De modellen die het gratis commando [`seo:audit`](/nl/guide/audit)
controleert als er geen optie `--model` is meegegeven. Elk model moet
de trait `HasSEO` gebruiken. Als de lijst leeg is, valt het commando
terug op de modellen die onder `sitemap.models` zijn geregistreerd.

## Berekende terugvalwaarden (laag 5) {#computed-fallbacks-layer-5}

```php
'computed' => [
    // Ordered attribute candidates for the description fallback.
    // Empty = built-in chain: excerpt, summary, description, intro,
    // lead, teaser, content, body, text, article.
    'description_fields' => [],

    // Truncation length — word boundary, no ellipsis.
    'description_max_length' => 160,

    // Social / Open Graph image selection.
    'image_selection' => [
        // 'first' (default) — first non-empty source wins, nothing measured.
        // 'best' — score local candidates by closeness to the ideal below,
        //          skipping any under the minimum.
        'strategy' => env('SEO_IMAGE_SELECTION', 'first'),
        'minimum_width' => 200,
        'minimum_height' => 200,
        'ideal_width' => 1200,
        'ideal_height' => 630,
    ],
],
```

Bij de optionele strategie `best` beoordeelt de builder een
geordende kandidatenlijst: eerst `getSEOImage()`, dat de kandidaat met de
hoogste prioriteit blijft, daarna de modelhook `getSEOImages()`, de gebruikelijke
afbeeldingsvelden, de eerste afbeelding uit de content en de geconfigureerde
standaardafbeelding. De beoordeling kijkt hoe dicht de pixelafmetingen bij
het ideaal liggen en **slaat kandidaten onder het minimum over**.
Alleen **lokale** afbeeldingen worden gemeten: een relatief pad onder
`public/`, de publieke schijf of een absolute URL op je eigen host.
Een externe URL wordt nooit opgehaald en dient alleen als terugvaloptie.
Als geen lokale kandidaat het minimum haalt, wordt de eerste overeenkomst
gekozen. `best` levert dus nooit minder op dan `first` zou doen.
Bied kandidaten vanuit je model aan:

```php
use Rankbeam\Seo\Data\SEOImageCandidate;

public function getSEOImages(): iterable
{
    return [
        SEOImageCandidate::make($this->hero_url)->priority(100),
        SEOImageCandidate::make($this->thumbnail_url)->priority(10),
    ];
}
```

## Sitemaps {#sitemaps}

```php
'sitemap' => [
    'disk'                 => env('SEO_SITEMAP_DISK', 'public'),
    'path'                 => 'sitemap.xml',
    'max_urls_per_sitemap' => 50000,

    // ModelClass::class => ['priority' => 0.8, 'changefreq' => 'weekly']
    'models' => [],

    // [['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily']]
    'static_urls' => [],

    'ping_search_engines' => env('SEO_SITEMAP_PING', false),
],
```

Zie de [gids voor het sitemapregister](/nl/guide/sitemaps) voor programmatisch geregistreerde bronnen.

## Schema (JSON-LD) {#schema-json-ld}

```php
'schema' => [
    'organization' => [
        'name' => env('APP_NAME'),
        'url'  => env('APP_URL'),
        'logo' => env('SEO_ORGANIZATION_LOGO'),
        // 'sameAs' => [],   // social profile URLs
    ],
    'publisher' => [
        'name' => env('APP_NAME'),
        'logo' => env('SEO_PUBLISHER_LOGO'),
    ],
    'website' => [
        'name' => env('APP_NAME'),
        'url'  => env('APP_URL'),
        // 'potentialAction' => [],   // SearchAction for sitelinks search box
    ],
],
```

Deze gegevens voeden de knooppunten van de [schemagraaf](/nl/guide/schema).

## Routes {#routes}

```php
'routes' => [
    'enabled'        => env('SEO_ROUTES_ENABLED', true),
    'prefix'         => '',
    'middleware'     => ['web'],
    'api_prefix'     => 'api/seo',
    'api_middleware' => ['api'],
],
```

Stel `enabled => false` in wanneer je app een eigen statische `/sitemap.xml` aanbiedt.

## Cache {#cache}

```php
'cache' => [
    'prefix' => 'seo_',
    'store'  => env('SEO_CACHE_STORE'),   // null = app default

    // Resolver result cache — the scale lever for hot frontends. OFF by default.
    'resolver' => [
        'enabled' => env('SEO_RESOLVER_CACHE', false),
        'ttl'     => env('SEO_RESOLVER_CACHE_TTL', 3600),
    ],
],
```

### Cache voor resolverresultaten {#resolver-result-cache}

`SEOResolver` doorloopt bij **elke** frontendrendering de volledige
prioriteitsketen: configuratie → globale, modeltype- en routestandaarden →
berekende modelwaarden → expliciete `seo_meta` → titelachtervoegsel,
canonieke URL en schema. Op een drukbezochte site, zoals de referentieapp
met ongeveer 20.000 aanvragen per dag, betekent dat meerdere databaselezingen per pagina.

Schakel `cache.resolver.enabled` in om de volledig bepaalde SEO van een model te cachen.
Een **cachetreffer slaat de hele prioriteitsketen over**. In de
pakketbenchmark doet een warme cachetreffer **nul** databasequeries,
terwijl een resolutie zonder cache telkens opnieuw de `seo_meta` van
het model leest. De gecachte payload is een gewone array, die met
`SEOData::fromArray()` opnieuw wordt opgebouwd, nooit een object. Laravel 13 gebruikt
namelijk `cache.serializable_classes = false`, waardoor een gecacht object als `__PHP_Incomplete_Class` terugkomt.

De cache gebruikt de hierboven ingestelde `store`. Verwijs die in
productie naar een **gedeelde, persistente cache**, zoals `redis` of
`memcached`. Elke web- en queueworker moet de cache en de invalidatie
ervan kunnen zien. Laat de optie uit totdat je zo'n cache hebt.

**Invalidatie is automatisch en correct**: met caching aan worden dezelfde
waarden bepaald als met caching uit. Vermeldingen hebben `(model class, id, locale, route, request URL)`
als sleutel en worden gewist wanneer:

- de `seo_meta`-rij van de pagina wordt **opgeslagen of verwijderd**,
  via elk pad: `saveSEO()`, Filament of rechtstreeks schrijven naar `SEOMeta`;
- een **contentveld** op het model verandert: de kolommen uit
  `getSEOContentFields()`. Standaard omvat dit elk ingebouwd veld voor berekende
  terugvalwaarden: titel-/kopvelden, excerpt-/summary-/content-/body-/text-/
  article-velden en gebruikelijke afbeeldingsvelden zoals `featured_image`,
  `thumbnail`, `cover_image`, `og_image`, `photo`, `banner`
  en `hero_image`. Pas de lijst aan als je model SEO uit extra kolommen berekent;
- **een willekeurige `seo_defaults`-rij** verandert. Een standaardwaarde kan
  elk model beïnvloeden, dus dit wist de volledige resolvercache.

In een cachestore **met tagondersteuning**, zoals `redis`,
`memcached` of `array`, worden modelvermeldingen gewist via
cache**tags**. In een store **zonder tagondersteuning**, zoals
`file` of `database`, gebruikt het pakket een **versiestempel**
per model. Beide werken zonder de sleutels te doorzoeken.

::: tip
Alleen resoluties met een onderliggend model worden gecachet.
`SEO::render()`/`@seo()` voor een handmatig opgebouwde `SEOData`
en `@seoForRoute()` voor een route zonder model worden nog steeds direct berekend.
:::

::: warning
De cache weerspiegelt de `updated_at` en berekende `modified_time` van
het model op het moment van de laatste **contentveldwijziging**, of totdat
de TTL verloopt. Alleen `touch()` uitvoeren, waarbij uitsluitend
`updated_at` verandert en geen kolom uit `getSEOContentFields()`, dwingt geen
nieuwe resolutie af. `article:modified_time` kan dus maximaal één TTL achterlopen.
Voeg applicatiespecifieke berekende kolommen toe aan `getSEOContentFields()` als
je de cache daarvoor direct ongeldig wilt maken.
:::

---
description: "Erzeuge XML-Sitemaps je Quelle und einen Index unter /sitemap.xml. Registriere Modelle, Closures oder URL-Listen mit spatie/laravel-sitemap."
---

# Sitemap-Registry {#sitemap-registry}

Das Paket erzeugt eine XML-Sitemap je Quelle sowie einen Index. Die Dateien werden unter `/sitemap.xml` und `/sitemap-{name}.xml` ausgeliefert. Die Erzeugung verwendet [spatie/laravel-sitemap](https://github.com/spatie/laravel-sitemap):

```bash
composer require spatie/laravel-sitemap
```

## Quellen registrieren {#registering-sources}

Registriere benannte Quellen in `boot()` eines Service Providers:

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

Jede Quelle erzeugt `sitemap-{name}.xml`; `sitemap.xml` listet die Dateien als Index auf. Die Registry bietet außerdem `has($name)`, `names()`, `forget($name)` und `flush()`.

## Quellen aus der Konfiguration {#config-driven-sources}

In `config/seo.php` kannst du auch Modellquellen und statische URLs angeben:

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

::: info Die Registry hat Vorrang vor automatischer Erkennung
Ein bereits registriertes Modell wird bei der automatischen Erkennung übersprungen. Die Quelle `'posts'` erzeugt daher nicht zusätzlich eine `sitemap-post.xml`.
:::

## Erzeugen {#generating}

```bash
php artisan seo:sitemap
```

Die Dateien landen auf `seo.sitemap.disk`, standardmäßig `public`. Plane den Befehl regelmäßig ein:

```php
// routes/console.php or bootstrap/app.php scheduling
Schedule::command('seo:sitemap')->daily();
```

Sitemaps oberhalb von `seo.sitemap.max_urls_per_sitemap` werden automatisch aufgeteilt. Der Standardwert von 50.000 URLs entspricht der Formatgrenze.

## Ausliefern {#serving}

Die Paketrouten liefern die erzeugten Dateien mit XML- und Cache-Headern sowie `X-Robots-Tag: noindex` aus:

- `/sitemap.xml` — Index oder einzelne Sitemap
- `/sitemap-posts.xml` — benannte Quelle

Wenn du eine eigene statische Sitemap auslieferst, deaktiviere die Routen:

```php
// config/seo.php
'routes' => ['enabled' => false],
```

## Lesbare Sitemap im Browser {#styled-sitemap-in-the-browser}

Im Browser erscheint eine Rankbeam-Sitemap als Tabelle mit URL, `lastmod`, Änderungsfrequenz, Priorität sowie Bild- und Sprachvariantenanzahl. Dazu kommen Hinweise auf bestimmte Formatprobleme.

![Rankbeam-Sitemap als lesbare Tabelle im Browser](/sitemap-styled.png)

Jede erzeugte Sitemap verweist auf ein XSL-Stylesheet:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<?xml-stylesheet type="text/xsl" href="https://your-app.test/sitemap.xsl"?>
<urlset ...>
```

Suchmaschinen ignorieren diese Anweisung. Die Sitemap bleibt maschinenlesbares XML; nur die Darstellung im Browser ändert sich. Das gilt für Index und untergeordnete Dateien.

Die Darstellung ist **standardmäßig aktiviert**. Sie fügt keine Inhaltsdaten hinzu und erfordert keine Arbeit pro Datensatz, sondern nur eine Stylesheet-Anweisung.

::: warning Benötigt spatie/laravel-sitemap ≥ 8.1
Die Anweisung verwendet `setStylesheet()`, eingeführt in Version 8.1. Mit älteren Versionen, die manche PHP/Laravel-Kombinationen auflösen, werden weiterhin normale XML-Sitemaps ohne Stylesheet erzeugt. Aktualisierung: `composer update spatie/laravel-sitemap`.
:::

So deaktivierst du die Darstellung:

```php
// config/seo.php
'sitemap' => [
    'stylesheet' => ['enabled' => false],
],
```

### Validierungshinweise {#validation-notes}

Die Browserdarstellung prüft zwei Dinge ohne externe Anfragen:

- **Fehlendes `lastmod`:** Eine fehlende Änderungszeit wird angezeigt, nicht erfunden. Datumsangaben müssen tatsächliche Änderungen widerspiegeln.
- **Nicht absolute URLs:** Ein `<loc>`, das keine absolute `http(s)`-URL enthält.

### Stylesheet selbst bereitstellen {#self-hosting-the-stylesheet}

Standardmäßig liefert das Paket das Stylesheet über `/sitemap.xsl`. Browser wenden XSLT nur an, wenn es **denselben Ursprung** wie die Sitemap hat. Liegt die Sitemap etwa auf einer CDN-Domain, veröffentliche das Stylesheet dort und konfiguriere diese Kopie:

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

::: info Werte werden escaped
Alle ausgegebenen Werte einschließlich URLs durchlaufen XSLT-Escaping. Ein `<loc>` wird nur bei `http(s)` als Link ausgegeben. Behalte diese Schutzmechanismen bei Anpassungen der `.xsl`-Datei bei und füge kein `disable-output-escaping` hinzu.
:::

## Enthaltene Datensätze {#what-gets-included}

Modellquellen enthalten indexierbare Datensätze. Ein Modell, dessen Robots-Wert `noindex` ergibt, bleibt ausgeschlossen. Die URLs stammen aus `getUrlForSEO()`, derselben Methode wie abgeleitete Canonicals.

## Bild- und hreflang-Erweiterungen {#image-hreflang-extensions}

Zwei optionale Erweiterungen ergänzen die bereits aufgelösten Daten des Modells. Beide sind **standardmäßig deaktiviert**:

```php
'sitemap' => [
    'images' => true,      // <image:image> per URL
    'alternates' => true,  // <xhtml:link rel="alternate"> per URL
],
```

Sie gelten für Modelle mit `HasSEO` und verwenden das vollständige Ergebnis von `seoData()`:

- **`images`** ergänzt einen [Google-Bild-Sitemap-Eintrag](https://developers.google.com/search/docs/crawling-indexing/sitemaps/image-sitemaps) aus dem aufgelösten OG- oder Inhaltsbild. Ohne eigenes Bild kann dies das globale `default_og_image` sein. Aktiviere die Erweiterung, wenn ein Bild je URL für deine Inhalte sinnvoll ist.
- **`alternates`** ergänzt `<xhtml:link rel="alternate" hreflang="…">` aus `getSEOAlternates()`, wie im `<head>` der Seite. Gib absolute URLs zurück:

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

::: warning hreflang muss gegenseitig sein und die eigene Seite enthalten
Jede Sprachversion muss sich selbst und alle anderen Versionen nennen; die Verweise müssen gegenseitig sein. `getSEOAlternates()` sollte deshalb für jede Variante dieselbe vollständige Liste liefern. Verwende gültige Codes `language[-Script][-REGION]` oder `x-default` und absolute `http(s)`-URLs. Einträge ohne nichtleeres `hreflang` oder `href` werden übersprungen.

Vor der Ausgabe greifen die [`seo.hreflang`-Regeln](/de/guide/multilingual#hreflang): Codes werden normalisiert (`it_IT` → `it-IT`); `include_self` und `x_default` können die entsprechenden Einträge ergänzen. Sitemap und `<head>` verwenden dieselbe Liste. Das kostenlose Audit meldet `hreflang_invalid_code`, `hreflang_duplicate_code` und `hreflang_missing_self`. Gegenseitige Verweise prüft der Pro-Crawl.
:::

::: info Aufwand bei großen Katalogen
Bei aktiver Erweiterung löst der Builder `seoData()` für Bilder und Sprachalternativen auf. Der normale Modellpfad löst die Metadaten außerdem in `shouldInclude()` auf, um robots zu prüfen. Ist der Resolver-Cache wie standardmäßig deaktiviert, kann ein aufgenommener Datensatz die Vorrangkette daher zweimal durchlaufen. Jede Auflösung kann Cache- oder Datenbankzugriffe auslösen; eigene `getSEO*()`-Getter können weitere Abfragen hinzufügen. Führe `seo:sitemap` als geplanten Befehl aus, nicht innerhalb einer Webanfrage. Miss die Kosten vor dem Einsatz nahe der Grenze von 50.000 URLs und lass beide Erweiterungen aus, wenn du sie nicht brauchst.
:::

::: tip Bereits veröffentlichte Konfiguration
`config/seo.php` wird nicht rekursiv zusammengeführt. Ältere veröffentlichte Dateien erhalten `sitemap.images` und `sitemap.alternates` nicht automatisch. Die Umgebungsvariablen `SEO_SITEMAP_IMAGES` und `SEO_SITEMAP_ALTERNATES` allein reichen dann nicht: Ergänze die Schlüssel im `sitemap`-Array oder veröffentliche die Konfiguration erneut.
:::

## Vollständige Kontrolle mit Spatie-Tags {#full-control-hand-built-spatie-tags}

Für Bildunterschriften, Videos, Nachrichten oder eigene hreflang-Listen kannst du fertige [`Spatie\Sitemap\Tags\Url`](https://github.com/spatie/laravel-sitemap#adding-images)-Objekte zurückgeben. Der Builder übernimmt sie unverändert und ergänzt keine eigenen Erweiterungen:

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

Dasselbe gilt pro Datensatz: Implementiert ein Modell `Sitemapable` und liefert `toSitemapTag()` ein `Url`-Objekt, wird es genau so ausgegeben.

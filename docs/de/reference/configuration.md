---
description: "Optionen in config/seo.php nach Resolver-Ebene geordnet, jeweils mit den mitgelieferten Standardwerten."
---

# Konfiguration {#configuration}

Veröffentliche die Konfigurationsdatei:

```bash
php artisan vendor:publish --tag=seo-config
```

Alle folgenden Einstellungen stehen in `config/seo.php`. Gezeigt werden die Standardwerte.

## Website-weite Standardwerte: Ebene 1 {#site-wide-defaults-layer-1}

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

`title_suffix` wird an aufgelöste Titel angehängt, sofern sie nicht bereits damit enden.

`title_suffix_skip_when_contains` unterdrückt das Suffix bei bereits enthaltenen Markennamen. Enthält der aufgelöste Titel einen der Einträge **als vollständiges Wort**, entfällt das Suffix, um die Marke nicht doppelt zu nennen. Der Vergleich ignoriert Groß-/Kleinschreibung und berücksichtigt Wortgrenzen; `Acmestic` passt also nicht zu `Acme`. Der Standard `[]` erhält das bisherige Verhalten.

## Robots-Ausgaberegel {#robots-rendering-policy}

```php
'robots' => [
    'emit_default' => env('SEO_EMIT_DEFAULT_ROBOTS', false),
],
```

Der gerenderte `<head>` lässt `<meta name="robots">` weg, wenn die aufgelöste Anweisung dem oben gesetzten `default_robots` entspricht. Ohne ein redundantes `index,follow` gilt für den Crawler bereits index,follow. **Abweichende** Anweisungen wie `noindex`, `nofollow` oder `max-snippet:-1` werden immer unverändert ausgegeben. Setze `emit_default` auf `true`, um das Tag immer auszugeben und das Verhalten vor 3.1 wiederherzustellen. Die einzelne Direktive `@seoRobots` bleibt davon unberührt: Ihr ausdrücklicher Aufruf rendert das Tag immer. Unterstützte Anweisungen und Priorität beschreibt der [Rendering-Vertrag](/de/contributing/rendering-contract).

## Indexierungsschutz für nicht öffentliche Umgebungen {#indexing-guard-non-production-safety-net}

```php
'indexing_guard' => [
    'enabled' => env('SEO_INDEXING_GUARD', false),
    'allowed_environments' => ['production'],
    'send_header' => env('SEO_INDEXING_GUARD_HEADER', true),
],
```

Wenn der Schutz aktiviert ist und die App in einer Umgebung **außerhalb** von `allowed_environments` läuft, erzwingt er `noindex,nofollow` für jede Seite. Dies geschieht oberhalb der gesamten Prioritätskette und überschreibt auch gespeicherte Seitenwerte. Er sendet einen passenden `X-Robots-Tag`-Header, erzeugt eine `robots.txt` mit vollständiger Disallow-Regel und lässt `seo:audit` einen Hinweis anzeigen. In freigegebenen Umgebungen, standardmäßig `production`, bleibt er inaktiv.

Der Schutz ist bei Auslieferung **ausgeschaltet**; die Ausgabe bleibt bis zur Aktivierung unverändert. `SEO_INDEXING_GUARD=true` aktiviert ihn, `SEO_INDEXING_GUARD=false` deaktiviert ihn. Die Freigabeliste lässt sich über `SEO_INDEXING_GUARD_ALLOWED` kommasepariert ersetzen. `Str::is()`-Platzhalter wie `prod*` funktionieren; eine ausdrücklich leere Konfigurationsliste aktiviert den Schutz überall.

`send_header` ist innerhalb des Schutzes standardmäßig aktiv und sendet `X-Robots-Tag: noindex,nofollow` auch für Nicht-HTML-Antworten wie PDFs, Feeds und Bilder, soweit sie durch die App laufen. Die Middleware wird nur bei aktiviertem Schutz registriert. Die Aktivierung wird ausdrücklich empfohlen; siehe die vollständige [Anleitung zum Indexierungsschutz](/de/guide/indexing-guard).

## Canonical-URLs {#canonical-urls}

```php
'canonical' => [
    'query_whitelist' => [],   // e.g. ['page']
],
```

Bei einem vom Resolver **abgeleiteten** Canonical aus Anfrage-URL oder `getUrlForSEO()` des Modells wird der Query-String standardmäßig entfernt. Tracking-, Filter- und Sortierparameter können sonst verschiedene Canonical-Ziele für dieselbe Seite erzeugen. Schlüssel in `query_whitelist` bleiben in dieser Reihenfolge **erhalten**; andere Parameter werden weiterhin entfernt. Ein typischer Fall ist `page` für paginierte Archive, denn `/blog?page=2` bezeichnet eine andere Seite als `/blog`.

Ein **explizit gesetzter** Canonical aus einer Admin-Eingabe oder einer höher priorisierten Ebene wird immer unverändert ausgegeben, einschließlich Query-String. Die Freigabeliste gilt nur für den abgeleiteten Fallback. Standardmäßig entfernt `[]` alle Query-Parameter aus diesem Fallback.

## Funktionsschalter {#feature-toggles}

```php
'features' => [
    'auto_create_meta' => env('SEO_AUTO_CREATE_META', true),
    'sitemap'          => env('SEO_SITEMAP_ENABLED', true),
    'schema'           => env('SEO_SCHEMA_ENABLED', true),
    'multilingual'     => env('SEO_MULTILINGUAL_ENABLED', false),
],
```

`auto_create_meta` legt beim Erstellen eines `HasSEO`-Modells einen leeren `seo_meta`-Datensatz an. Seeder mit `WithoutModelEvents` umgehen diesen Hook.

## Fokus-Keywords {#focus-keywords}

```php
'keywords' => [
    'enabled' => env('SEO_KEYWORDS_ENABLED', false),
],
```

Dieser Schalter aktiviert den **Fokus-Keyword-Workflow**. Solange er wie standardmäßig auf `false` steht, wird ein fehlendes Fokus-Keyword weder in [`seo:audit`](/de/guide/audit) noch im Pro-Scan bemängelt. Eine App ohne diesen Workflow erhält damit keine unnötigen Hinweise. Aktiviere ihn, sobald du Fokus-Keywords pflegst, etwa über das [Filament-Fokus-Keyword-Feld](/de/guide/filament). Kostenloses Audit, Pro-Scan und Pro-Editor melden dann `missing_focus_keyword` für Seiten ohne Keyword. Sie verwenden denselben Schalter.

## Kostenloses Audit: `seo:audit` {#free-audit-seo-audit}

```php
'audit' => [
    // \App\Models\Post::class, \App\Models\Page::class
    'models' => [],
],
```

Diese Modelle prüft der kostenlose Befehl [`seo:audit`](/de/guide/audit), wenn kein `--model` übergeben wird. Jedes muss `HasSEO` verwenden. Bei leerer Liste greift der Befehl auf die unter `sitemap.models` registrierten Modelle zurück.

## Berechnete Fallbacks: Ebene 5 {#computed-fallbacks-layer-5}

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

Bei der optionalen Strategie `best` bewertet der Builder eine geordnete Kandidatenliste anhand der Nähe ihrer Pixelmaße zu den Idealmaßen. Zuerst kommt `getSEOImage()` als höchstpriorisierter Kandidat, danach der Modell-Hook `getSEOImages()`, übliche Bildfelder, das erste Inhaltsbild und der konfigurierte Standard. Bilder **unterhalb der Mindestmaße werden übersprungen**. Gemessen werden nur **lokale** Bilder: relative Pfade unter `public/`, der öffentliche Datenträger oder absolute URLs des eigenen Hosts. Externe URLs werden nie abgerufen und dienen nur als Fallback. Erfüllt kein lokaler Kandidat die Mindestmaße, greift die Auswahl des ersten Treffers. `best` verliert damit keinen Wert, den `first` geliefert hätte. Kandidaten stellt dein Modell so bereit:

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

Programmatische Quellen beschreibt die [Sitemap-Registry-Anleitung](/de/guide/sitemaps).

## Schema: JSON-LD {#schema-json-ld}

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

Diese Werte befüllen die Knoten des [Schema-Graphen](/de/guide/schema).

## Routen {#routes}

```php
'routes' => [
    'enabled'        => env('SEO_ROUTES_ENABLED', true),
    'prefix'         => '',
    'middleware'     => ['web'],
    'api_prefix'     => 'api/seo',
    'api_middleware' => ['api'],
],
```

Setze `enabled => false`, wenn deine App eine eigene statische `/sitemap.xml` ausliefert.

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

### Cache für Resolver-Ergebnisse {#resolver-result-cache}

`SEOResolver` durchläuft bei **jedem** Frontend-Rendering die vollständige Prioritätskette: Konfiguration, globale/Modelltyp-/Routen-Standardwerte, berechnete Modellwerte, explizites `seo_meta`, Titelsuffix, Canonical und Schema. Das verursacht mehrere Datenbank-Lesezugriffe pro Seite. Die Referenz-App mit etwa 20.000 Anfragen täglich ist ein Beispiel für diese Last.

Mit `cache.resolver.enabled` wird das vollständig aufgelöste SEO-Ergebnis eines Modells gecacht. Ein **Cache-Treffer überspringt die Prioritätskette vollständig**. Im Paket-Benchmark führt ein warmer Treffer **keine** Datenbankabfrage aus, während eine ungecachte Auflösung `seo_meta` erneut liest. Gespeichert wird ein gewöhnliches Array, das mit `SEOData::fromArray()` wiederhergestellt wird, kein Objekt. Laravel 13 verwendet `cache.serializable_classes = false`; ein gecachtes Objekt würde als `__PHP_Incomplete_Class` zurückkehren.

Verwendet wird der oben konfigurierte `store`. Wähle in Produktion einen **gemeinsamen dauerhaften Cache** wie `redis` oder `memcached`, damit alle Web- und Queue-Worker Cache und Invalidierungen sehen. Lass die Funktion ausgeschaltet, bis ein solcher Store vorhanden ist.

**Die Invalidierung erfolgt automatisch**, damit aktivierter und deaktivierter Cache dieselben Werte auflösen. Einträge sind über `(model class, id, locale, route, request URL)` identifiziert und werden in folgenden Fällen gelöscht:

- Der `seo_meta`-Datensatz einer Seite wird **gespeichert oder gelöscht**, unabhängig vom Weg: `saveSEO()`, Filament oder direktes Schreiben eines `SEOMeta`-Modells.
- Ein **Inhaltsfeld** des Modells ändert sich. Maßgeblich sind die Spalten aus `getSEOContentFields()`. Standardmäßig umfasst die Liste alle eingebauten Fallback-Felder: Titel-/Headline-Felder, excerpt, summary, content, body, text und article sowie gängige Bildfelder wie `featured_image`, `thumbnail`, `cover_image`, `og_image`, `photo`, `banner` und `hero_image`. Ergänze eigene Spalten, wenn dein Modell daraus SEO-Werte berechnet.
- **Ein beliebiger `seo_defaults`-Datensatz** ändert sich. Da ein Standardwert jedes Modell betreffen kann, wird der gesamte Auflösungs-Cache geleert.

Bei einem Store **mit Tag-Unterstützung**, etwa `redis`, `memcached` oder `array`, werden die Modelleinträge über Cache-**Tags** gelöscht. Bei Stores **ohne Tags**, etwa `file` oder `database`, verwendet das Paket einen **Versionswert pro Modell**. Beide Verfahren benötigen keinen Scan der Cache-Schlüssel.

::: tip
Nur modellbasierte Auflösungen werden gecacht. `SEO::render()` und `@seo()` mit einem selbst erstellten `SEOData` sowie `@seoForRoute()` für Routen ohne Modell werden weiterhin direkt aufgelöst.
:::

::: warning
Der Cache enthält `updated_at` beziehungsweise das berechnete `modified_time` vom letzten **Inhaltsfeldwechsel** bis zur Invalidierung oder zum Ablauf der TTL. Ein bloßes `touch()`, das nur `updated_at` verändert, erzwingt ohne Änderung einer `getSEOContentFields()`-Spalte keine neue Auflösung. `article:modified_time` kann deshalb bis zur TTL zurückliegen. Ergänze anwendungsspezifische berechnete Spalten in `getSEOContentFields()`, wenn ihre Änderungen den Cache sofort verwerfen sollen.
:::

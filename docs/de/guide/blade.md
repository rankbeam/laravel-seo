---
description: "SEO in serverseitig gerenderten Laravel-Apps: Die Blade-Direktive @seo löst ein Modell auf und gibt Meta-Tags, Open Graph, Twitter Cards und JSON-LD aus."
---

# Blade-Anleitung {#blade-guide}

Für klassische serverseitig gerenderte Apps bietet das Paket sieben Blade-Direktiven. Meist genügt eine davon: `@seo`.

## Die vollständige Ausgabe mit einer Direktive {#the-all-in-one-directive}

```blade
<!DOCTYPE html>
<html>
<head>
    @seo($post)
</head>
```

`@seo` löst das Modell anhand der [Prioritätskette](/de/concepts/resolver-precedence) auf und rendert den vollständigen Head-Block: `<title>`, Meta-Beschreibung, Canonical-Link, Robots, Open-Graph-Tags, Twitter-Card-Tags und angehängtes JSON-LD. Das Robots-Tag erscheint **nur, wenn sein Wert vom Website-Standard abweicht**. Ein redundantes `index,follow` entfällt, denn ohne das Tag gilt bereits index,follow. Mit `seo.robots.emit_default` wird es immer ausgegeben. Die Einzelheiten stehen im [Rendering-Vertrag (EN)](/de/contributing/rendering-contract).

Signaturen:

```blade
@seo($post)                  {{-- model page --}}
@seo($seoData)               {{-- a hand-built SEOData (model-less page) --}}
@seo($post, 'blog.show')     {{-- model + route defaults --}}
@seo($post, null, 'fr')      {{-- model + locale --}}
@seo(null)                   {{-- current page, no model --}}
```

`@seo` akzeptiert ein `Model`, ein selbst erstelltes `SEOData` oder `null`. Die Argumente für Route und Locale gelten nur bei `Model`/`null`; ein selbst erstelltes `SEOData` bringt seine eigenen Werte mit.

## Routenseiten ohne Modell {#route-pages-no-model}

Für statische Seiten, Archive und andere Seiten auf Basis einer Route:

```blade
@seoForRoute('pages.about')
@seoForRoute('contact', 'de')   {{-- with locale --}}
```

Die Routenwerte stammen aus `seo_defaults`-Datensätzen, die dem Routennamen zugeordnet sind.

## Seiten ohne Modell: eigenes `SEOData` {#model-less-pages-hand-built-seodata}

Listen, Suchergebnisse und im Controller zusammengestellte Seiten haben oft kein einzelnes zugrunde liegendes Modell. Erstelle ein `SEOData` und übergib es direkt an `@seo` oder die `SEO`-Fassade. Ein Aufruf von `app(TagRenderer::class)->render(...)` ist dafür nicht nötig:

```php
use Rankbeam\Seo\Data\SEOData;

return view('search.results', [
    'seo' => new SEOData(
        title: "Results for \"{$query}\"",
        description: "Browse {$count} matches for {$query}.",
        ogImage: '/images/search-share.jpg',   // relative is fine — see below
    ),
]);
```

```blade
<head>
    @seo($seo)
</head>
```

Ein selbst erstelltes `SEOData` gilt als **ausdrückliche Vorgabe**. Alle gesetzten Werte bleiben erhalten; beim Rendern werden nur folgende Lücken ergänzt:

- Fehlende Werte für `canonical` und `og:url` werden aus der aktuellen URL abgeleitet. Ein explizites `canonical` bleibt unverändert, einschließlich Query-String.
- `title_suffix` wird nur angehängt, wenn der Titel es noch nicht enthält. Enthält der Titel bereits einen Markennamen aus der konfigurierten Liste, entfällt es ganz; siehe [`title_suffix_skip_when_contains` (EN)](/de/reference/configuration).
- Relative Pfade für `og:image` und `twitter:image` werden mit `url()` absolut. Dabei gilt das aktuelle URL-Schema; HTTPS wird **nicht** erzwungen.
- `og:site_name` und `locale` werden aus der Konfiguration beziehungsweise der App-Locale ergänzt.

Die Datenbank-Prioritätskette mit globalen, Modelltyp-, Routen- und `seo_meta`-Standardwerten wird **nicht** in ein selbst erstelltes `SEOData` eingemischt. Gerendert werden deine Angaben und die oben beschriebenen Ergänzungen.

Dasselbe Objekt funktioniert auch über die Fassade:

```php
SEO::render($seoData);     // HTML string
SEO::toArray($seoData);    // Vue/React structure
SEO::forInertia($seoData); // Inertia Head structure
```

## Ein gemeinsames Layout {#a-layout-pattern-that-scales}

Ein Layout für Modellseiten, Routenseiten und alle übrigen Fälle:

```blade
<head>
    @if(isset($seoModel))
        @seo($seoModel)
    @elseif(isset($seoRoute))
        @seoForRoute($seoRoute)
    @else
        @seo(null)
    @endif
</head>
```

Die Controller übergeben dann `'seoModel' => $post` oder `'seoRoute' => 'blog.index'`, ohne Markup zu bearbeiten.

## Einzelne Direktiven {#granular-directives}

Wenn du einzelne Tags steuern möchtest, etwa neben der Ausgabe eines anderen Pakets:

| Direktive | Ausgabe |
|---|---|
| `@seoTitle($post)` | Nur `<title>` |
| `@seoMeta($post)` | Nur die Meta-Beschreibung |
| `@seoCanonical($post)` | Nur der Canonical-Link; fällt auf die aktuelle URL zurück |
| `@seoRobots($post)` | Nur das Robots-Meta-Tag, immer ausgegeben. Dieser ausdrückliche Aufruf unterdrückt den Website-Standard **nicht**, anders als `@seo`. |
| `@seoSchema($post)` | Nur das JSON-LD-`<script>`, im Head oder Body gültig |

Alle akzeptieren wie `@seo` den Ausdruck `($model, $route, $locale)` oder keine Argumente für die aktuelle Seite.

## Hreflang-Alternativen {#hreflang-alternates}

Modelle mit `HasSEO` können Hreflang-Links direkt über den Resolver bereitstellen:

```php
public function getSEOAlternates(): ?array
{
    return [
        ['hreflang' => 'en', 'href' => route('posts.show', ['locale' => 'en', 'post' => $this])],
        ['hreflang' => 'it', 'href' => route('posts.show', ['locale' => 'it', 'post' => $this])],
    ];
}
```

Verwende absolute URLs. `@seo($post)` löst die Einträge auf und rendert jeden als `<link rel="alternate" hreflang="..." href="...">`. Die Codes werden zunächst in die BCP-47-Schreibweise gebracht (`it_IT` → `it-IT`). Die Regeln unter `seo.hreflang` können einen Selbstverweis der Seite und `x-default` ergänzen. Das kostenlose Audit meldet ungültige, doppelte oder fehlende Selbstverweise. Siehe [Mehrsprachige Inhalte](/de/guide/multilingual#hreflang).

## Escaping und Sicherheit {#escaping-and-safety}

Textwerte werden mit `e()` maskiert. JSON-LD wird mit `JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP` kodiert, sodass ein `</script>` in Nutzerinhalten das Script-Element nicht vorzeitig schließen kann.

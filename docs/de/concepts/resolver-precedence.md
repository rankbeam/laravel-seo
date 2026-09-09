---
description: "So löst Rankbeam SEO-Werte aus sechs Ebenen auf: Höhere Ebenen haben Vorrang, null überschreibt keine niedrigeren Werte."
---

# Priorität des Resolvers

`SEOResolver` führt **sechs Ebenen** zusammen, um Titel, Beschreibung, Canonical, Robots und Bilder zu bestimmen. Höhere Ebenen gewinnen. `null` überschreibt keinen Wert einer niedrigeren Ebene.

## Die sechs Ebenen {#the-six-layers}

Von der niedrigsten zur höchsten Priorität:

| # | Ebene | Quelle | Typischer Einsatz |
|---|---|---|---|
| 1 | **Website-Konfiguration** | `config/seo.php`: `site_name`, `title_suffix`, `default_og_image`, `default_robots`, … | Markenweite Vorgaben |
| 2 | **Globale Datenbankvorgaben** | `seo_defaults` ohne Modelltyp | Änderbare Website-Vorgaben ohne Deployment |
| 3 | **Vorgaben je Modelltyp** | `seo_defaults` für eine Modellklasse | Gemeinsames OG-Bild für alle Produkte |
| 4 | **Routenvorgaben** | `seo_defaults` für einen Routennamen | Statische Seiten wie `home` oder `contact` ohne Modell |
| 5 | **Berechnete Werte** | Attribute des Modells | Titel aus `title`, Beschreibung aus `excerpt` oder `body` |
| 6 | **Explizite Werte** | `seo_meta` des Modells über `saveSEO()` | Von Redakteuren eingetragene Werte |

```php
use Rankbeam\Seo\Facades\SEO;

$seo = SEO::resolve($post);          // model page: layers 1-3 + 5-6
$seo = SEO::forRoute('contact');     // route page: layers 1-2 + 4
```

Das Ergebnis ist ein unveränderliches `SEOData`-Wertobjekt für alle Renderer: Blade, Array und Inertia.

## Berechnete Fallbacks: Ebene 5 {#computed-fallbacks-layer-5}

Fehlt ein expliziter Wert, leitet der Resolver ihn vom Modell ab:

- **Titel:** Attribut `title` oder `name`.
- **Beschreibung:** erstes Attribut mit aussagekräftigem Text aus `seo.computed.description_fields`. Standardreihenfolge: `excerpt`, `summary`, `description`, `intro`, `lead`, `teaser`, `content`, `body`, `text`, `article`. HTML wird entfernt, Entities werden dekodiert und der Text an einer Wortgrenze gekürzt, ohne Auslassungszeichen. `seo.computed.description_max_length` ist standardmäßig 160.
- **Robots:** optionaler `getSEORobots()`-Hook oder Attribut `is_indexable`, wie unten beschrieben.
- **URL-Werte:** Canonical und `og:url` aus `getUrlForSEO()`.

## Robots und Indexierbarkeit steuern {#controlling-robots-and-indexability}

Der Resolver unterstützt `noindex` je Modell. `HasSEO` schreibt keine Robots-Methode vor; der Hook ist optional. Die Quellen gelten in dieser Reihenfolge:

| Priorität | Quelle | Beispiel |
|---|---|---|
| 1 | **Explizites `seo_meta.robots`** | `$page->saveSEO(['robots' => 'noindex,follow'])` |
| 2 | **`getSEORobots(): ?string`** am Modell | Gibt `'noindex, nofollow'` zurück oder `null` für den nächsten Fallback |
| 3 | **Attribut `is_indexable`**, Spalte oder Accessor | Falsch ⇒ `noindex, nofollow`; wahr ⇒ `index, follow` |

```php
class Page extends Model
{
    use HasSEO;

    // Option A: let the resolver derive robots from a boolean flag.
    //   Schema::table('pages', fn ($t) => $t->boolean('is_indexable')->default(true));

    // Option B: compute it from your own state.
    public function getSEORobots(): ?string
    {
        return $this->status === 'draft' ? 'noindex, nofollow' : null;
    }
}

// Option C: set it explicitly per page (wins over A and B).
$page->saveSEO(['robots' => 'noindex, follow']);
```

### Tatsächliche Ausgabe {#what-actually-renders}

Vor der Ausgabe im `<head>` greift die Emissionsregel: `<meta name="robots">` wird **nur ausgegeben, wenn die Direktive von `default_robots` abweicht**, standardmäßig `index,follow`.

- Eine **indexierbare** Seite mit `index, follow` erhält keinen Robots-Tag. Ohne Tag gilt bereits `index,follow`.
- Eine **nicht indexierbare** Seite erhält `<meta name="robots" content="noindex, nofollow">`.
- Andere abweichende Direktiven wie `noindex`, `max-snippet:-1` oder `unavailable_after` werden unverändert ausgegeben, einschließlich eingegebener Leerzeichen.

Mit `seo.robots.emit_default = true` wird der Tag immer ausgegeben. Details: [Robots-Ausgaberegeln (EN)](/reference/configuration#robots-rendering-policy).

## Regeln nach der Auflösung {#policies-applied-after-resolution}

Diese Schritte gelten unabhängig von der Quelle:

- **Titelsuffix:** `title_suffix` wird angehängt, sofern der Titel nicht bereits damit endet. Enthält eine Routenvorlage schon die Marke, sollte sie mit diesem Suffix enden, um Ergebnisse wie „Brand — X | Brand“ zu vermeiden.
- **Canonical-Query:** Bei abgeleiteten Canonicals aus Modell- oder aktueller URL werden Query-Parameter entfernt, außer den Einträgen in [`canonical.query_whitelist` (EN)](/reference/configuration#canonical-urls), etwa `page` für paginierte Archive. Explizit gesetzte Canonicals bleiben unverändert.
- **Absolute Social-Bilder:** `og:image` und `twitter:image` werden als absolute URLs ausgegeben, auch wenn ein relativer Pfad gespeichert wurde.

## Die maßgebliche Ebene erkennen {#inspecting-which-layer-won}

Das [Filament-Paket](/de/guide/filament) zeigt je Feld die Quelle: manuell, Inhalt, Modelltypvorgabe, globale Vorgabe, Website-Konfiguration oder URL. `SEOWarningEvaluator` bietet dieselbe Unterscheidung zwischen manuellen Werten und Fallbacks für eigene Verwaltungsanzeigen.

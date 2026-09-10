---
description: "Fünf Auswertungen deiner Search-Console-Daten: Positionsbereiche, CTR-Prüfkandidaten und Suchanfragen, für die mehrere deiner Seiten erscheinen."
---

# Search-Console-Auswertungen {#search-console-insights}

Fünf Berichte verwenden deine eigenen Search-Console-Daten: Suchanfragen in einem ausgewählten Positionsbereich, CTR-Prüfkandidaten, Überschneidungen zwischen Suchanfragen und Seiten, Anfragegruppen und Zeitraumvergleiche. Drei lesen synchronisierte Historie; zwei teilen sich eine gecachte Live-Anfrage. Sie decken diese Analysen ab, nicht den vollständigen Datenbestand oder Funktionsumfang einer externen Keyword-Plattform.

Die Funktion baut auf der [Search-Console-Integration mit Lesezugriff](/de/pro/search-console) und deren Historien-Synchronisierung auf. Läuft `seo-pro:gsc-sync` bereits, benötigen drei der fünf Ansichten **keine zusätzlichen API-Aufrufe**.

::: tip Voraussetzung
Die drei *Snapshot*-Ansichten lesen die gespeicherte Historie aus `seo_gsc_metrics`. Plane zuerst `seo-pro:gsc-sync`, wie unter [Search Console und Historie](/de/pro/search-console) beschrieben. Je mehr Tage synchronisiert wurden, desto länger kann der Trendvergleich zurückreichen.
:::

## Die fünf Ansichten {#the-five-surfaces}

### 1. Keywords in erreichbarer Nähe {#_1-striking-distance-keywords}

Suchanfragen mit einer **nach Impressionen gewichteten Durchschnittsposition zwischen 5 und 20**, nach Impressionen sortiert. Prüfe damit Relevanz und interne Verlinkung. Der Positionsbereich belegt nicht, dass eine kleine Änderung die Anfrage auf die erste Ergebnisseite bringen wird.

### 2. CTR-Prüfkandidaten {#_2-ctr-opportunities}

Suchanfragen, die **gut positioniert sind, aber seltener als der Vergleichswert angeklickt werden**. Ihre tatsächliche CTR wird mit einer gemittelten CTR-Kurve je Position verglichen. Deutlich darunterliegende Anfragen mit tatsächlichen Impressionen werden als **Kandidaten für eine Überarbeitung von Titel und Beschreibung** nach geschätzten entgangenen Klicks sortiert. Diese Liste kann als Ausgangspunkt für den [KI-Metadaten-Assistenten](/de/pro/ai-assist) dienen und zeigt die betreffenden Suchanfragen.

### 3. Kannibalisierung {#_3-cannibalization}

Suchanfragen, für die **mindestens zwei deiner URLs erscheinen**. Eine Überschneidung ist nicht zwangsläufig schädlich. Prüfe zunächst, ob die Seiten unterschiedliche Suchabsichten bedienen, bevor du sie zusammenlegst oder stärker voneinander abgrenzt.

### 4. Suchanfragen gruppieren {#_4-query-clusters}

Die **Suchanfragen, für die eine Seite tatsächlich erscheint**, nach Seite gruppiert. Damit kannst du erkennen, ob eine Seite von ihrem geplanten Thema abweicht oder für einen relevanten Begriff sichtbar wird, den du bisher nicht gezielt behandelt hast.

### 5. Vergleich mit dem vorherigen Zeitraum {#_5-trend-vs-previous-period}

Die **größten Veränderungen** bei Klicks, Impressionen, Position und CTR im aktuellen Zeitraum gegenüber dem unmittelbar vorangehenden gleich langen Zeitraum. Positionen werden nur verglichen, wenn eine Anfrage in beiden Zeiträumen Traffic hatte. Bei neuen oder vollständig verschwundenen Anfragen fehlt die Vergleichsbasis.

## Datenquellen: Live-Daten und Snapshots {#where-the-numbers-come-from-live-vs-snapshot}

Jede Ansicht verwendet die geeignete Quelle mit möglichst wenigen zusätzlichen Abfragen. Die gespeicherte Historie kann nicht rekonstruieren, welche **Suchanfrage** mit welcher **Seite** verbunden war, weil beide Dimensionen getrennt gespeichert werden. Nur die beiden Ansichten, die diese Zuordnung benötigen, fragen Live-Daten ab und **teilen sich eine gecachte Anfrage**.

| Ansicht | Quelle | Grund |
|---|---|---|
| Keywords im Positionsbereich | **Lokaler Snapshot** | Position und Impressionen je Anfrage sind bereits synchronisiert; kein zusätzlicher API-Aufruf |
| CTR-Prüfkandidaten | **Lokaler Snapshot** | Dieselben eigenen Daten; die Vergleichskurve ist ein statischer Maßstab, keine externe Abfrage |
| Trendänderungen | **Lokaler Snapshot** | Benötigt die tageweise Historie, die der Sync speichert |
| Kannibalisierung | **Live**, Suchanfrage × Seite | Die Zuordnung wird nicht gespeichert; alle Paare würden den Speicherbedarf vervielfachen |
| Anfragegruppen | **Live**, teilt den Abruf von Ansicht 3 | Dieselben Paardaten, nach Seite statt nach Anfrage gruppiert |

Ein Aufruf der Insights-Seite benötigt damit **höchstens eine Search-Analytics-Anfrage**, gecacht für `search_console.cache_ttl` Sekunden. Die Zuordnungsansichten zeigen bewusst eine Momentaufnahme. Der gemeinsame Cache begrenzt wiederholte Abrufe. Eine Token-Erneuerung kann eine zusätzliche Authentifizierungsanfrage benötigen; Googles Quoten gelten weiterhin. Die Snapshot-Ansichten greifen nicht auf das Netzwerk zu.

## Im Dashboard {#in-the-dashboard}

Bei installiertem Filament-Plugin erscheint **Search Console Insights** unter *SEO*, sofern die Integration aktiviert ist. Die Seite ist schreibgeschützt. Jede Ansicht bildet einen eigenen Abschnitt. Leere Snapshot-Ansichten weisen auf die nötige Historien-Synchronisierung hin. Scheitert der Live-Abruf, erscheint ein bereinigter Hinweis im betreffenden Bereich, ohne die gesamte Seite zu blockieren.

## Konfiguration {#configuration}

Alle Einstellungen stehen unter `search_console.insights` in `config/seo-pro.php`. Passe die Schwellenwerte an den Umfang deiner Website an.

```php
'search_console' => [
    // ...
    'insights' => [
        // Rolling window (days) the snapshot surfaces aggregate over,
        // anchored to the latest synced day.
        'window_days' => 28,

        // Max rows a surface returns to its panel section.
        'max_rows' => 50,

        // (1) Striking distance: impression-weighted position in [min,max]
        // with at least this many impressions.
        'striking_distance' => [
            'min_position' => 5.0,
            'max_position' => 20.0,
            'min_impressions' => 30,
        ],

        // (2) CTR opportunity: queries ranking at/above max_position, with at
        // least min_impressions, whose CTR is at least min_gap_ratio below the
        // expected curve.
        'ctr_opportunity' => [
            'max_position' => 10.0,
            'min_impressions' => 50,
            'min_gap_ratio' => 0.30,
        ],

        // Optional override of the expected CTR-by-position curve
        // (position => percent). null uses the built-in blended curve.
        'ctr_curve' => null,

        // (3) Cannibalization: a query with this many URLs each drawing at
        // least min_impressions.
        'cannibalization' => [
            'min_urls' => 2,
            'min_impressions' => 10,
        ],

        // (4) Query clustering: queries per page above min_impressions.
        'clustering' => [
            'min_impressions' => 10,
            'max_queries_per_page' => 15,
        ],

        // (5) Trend deltas: surface queries with at least this many
        // impressions in either period.
        'trend' => [
            'min_impressions' => 20,
        ],

        // The shared live (query,page) fetch for surfaces 3 + 4.
        'pair_days' => 28,
        'pair_row_limit' => 5000,
    ],
],
```

::: info Erwartete CTR-Kurve
Die Vergleichskurve ist eine **Heuristik** aus veröffentlichten durchschnittlichen organischen Klickraten je Position. Sie ist ein Maßstab, keine Aussage über deine konkrete Website. Ein markierter Eintrag ist ein *Prüfkandidat*, kein nachgewiesener Fehler. Eine eigene gemessene Kurve kannst du unter `insights.ctr_curve` als Zuordnung `position => percent` hinterlegen.
:::

## Siehe auch {#see-also}

- [Search Console](/de/pro/search-console): Integration mit Lesezugriff und Historien-Sync als Datenquelle.
- [White-Label-Berichte](/de/pro/reports): Zeitraumveränderungen in der PDF mit deinem Branding.
- [KI-Hilfe](/de/pro/ai-assist): Titel und Beschreibungen zu CTR-Prüfkandidaten überarbeiten.

---
description: "seo:explain zeigt, welche Resolver-Ebene jedes SEO-Feld gesetzt und welche Werte sie überschrieben hat. Unerwartete Titel oder Robots-Tags untersuchen, ohne Schreibzugriff, Netzwerk oder Lizenz."
---

# Die Auflösung erklären (`seo:explain`) {#explain-the-resolution-seo-explain}

Rankbeam löst die SEO-Daten einer Seite anhand einer [mehrstufigen Prioritätskette](/de/concepts/resolver-precedence) auf: Konfiguration, Datenbank-Standardwerte für Website, Modelltyp und Route, berechnete Modellwerte und schließlich explizite `seo_meta`-Werte. Danach folgen Nachverarbeitung wie Titelsuffix, Canonical und absolute Bild-URLs sowie der [Indexierungsschutz](/de/guide/indexing-guard). Wenn `<title>` oder das `robots`-Tag anders ausfallen als erwartet, zeigt **`seo:explain`, welche Ebene jedes Feld gesetzt und welche Werte sie dabei überschrieben hat.**

Der Befehl liest nur, benötigt weder Netzwerk noch Lizenz und bildet die Zusammenführung nicht erneut nach. Die Zuordnung stammt aus den Beiträgen der Resolver-Ebenen selbst, die Endwerte aus dem tatsächlichen Resolver. Erklärung und gerenderte Werte verwenden somit denselben Auflösungsweg.

## Verwendung {#usage}

```bash
# Explain a specific record
php artisan seo:explain "App\Models\Post" 42

# Explain the first record of a model
php artisan seo:explain "App\Models\Post"

# With a route-defaults layer and a locale
php artisan seo:explain "App\Models\Post" 42 --route=posts.show --locale=de

# Machine-readable
php artisan seo:explain "App\Models\Post" 42 --json
```

Das Modell muss den Trait [`HasSEO`](/de/guide/quickstart) verwenden.

## Die Ausgabe lesen {#reading-the-output}

```
SEO resolution — Post #42  (locale: en, route: posts.show)
Layers, low → high: config · global · model-type · route · computed · explicit

 Field         Final value                    Set by            Overrode
 title         My Post | Acme                 computed          —
                 ↳ title suffix ' | Acme' appended
 description   A hand-written summary…        explicit          computed: "An auto excerpt…"
 canonical     https://acme.com/blog/my-post  post-processing   —
                 ↳ derived from model getUrlForSEO() (query string stripped)
 robots        noindex,nofollow               explicit          config: index,follow
                 ↳ indexing guard forced 'noindex,nofollow' (environment 'staging' …)
 og_image      https://acme.com/share.jpg     explicit          config: /default-og.jpg
                 ↳ absolutized from '/share.jpg'
```

- **Set by**: die maßgebliche Ebene, also die höchstpriorisierte Ebene mit einem Wert ungleich null. `post-processing` erscheint, wenn keine Ebene das Feld gesetzt hat, der Wert aber *abgeleitet* wurde: etwa Canonical aus Anfrage- oder Modell-URL, og:url aus Canonical oder eine absolute Bild-URL.
- **Overrode**: alle nachrangigen Ebenen, deren angebotener Wert verdrängt wurde, in ihrer Reihenfolge. So erkennst du verdeckte Werte.
- **↳ notes**: Nachverarbeitung, die den Wert nach dem Zusammenführen verändert hat. Dazu gehören Titelsuffix, Entfernen des Canonical-Query-Strings, Ableitung von og:url, absolute Bild-URLs und der Indexierungsschutz, der `noindex` oberhalb aller Ebenen erzwingt.

::: tip og:type und twitter:card
Diese beiden Felder haben Framework-Standardwerte ungleich null (`website` beziehungsweise `summary_large_image`). Deshalb gewinnt die höchste Ebene, die sie setzt, normalerweise `computed`, gegenüber `config`. Eine Seite ohne gespeicherten `seo_meta`-Datensatz trägt für sie keine Werte bei. Ein berechnetes `og:type` wie `article` wird deshalb nicht durch ein bloßes `website` verdeckt. Das entspricht der tatsächlichen Zusammenführung.
:::

## Website-weite Auflösung {#site-level-resolution}

Gemäß der [Ergänzung zur Herkunft der Website-Konfiguration](/de/concepts/resolver-precedence) zeigt `seo:explain` auch Website-weite Werte an, deren Herkunft häufig unklar ist: **Welche Quelle hat den Canonical-Host, den Website-Namen und die Standard-Locale gesetzt?**

```
Site-level resolution
 Value           Resolved     Source
 Site name       Acme         env (APP_NAME)
 Default locale  en           config (app.locale)
 Canonical host  acme.com     programmatic (model getUrlForSEO())
```

Prüfe besonders den Canonical-Host. Ein versehentlich übernommenes `localhost`, `http://` auf einer HTTPS-Website oder eine App-URL, die nicht zur Modell-URL passt, verursacht häufig fehlerhafte Selbst-Canonicals.

## JSON-Ausgabe {#json-output}

`--json` gibt die vollständige Aufzeichnung für Tools oder CI aus: `target`, je Feld `winner`, `losers`, `final` und `notes` sowie die Übersicht `site_level`:

```json
{
  "target": { "model": "App\\Models\\Post", "id": 42, "route": "posts.show", "locale": "en" },
  "fields": {
    "title": {
      "final": "My Post | Acme",
      "winner": { "layer": "computed", "value": "My Post" },
      "losers": [],
      "notes": ["title suffix ' | Acme' appended"]
    }
  },
  "site_level": {
    "canonical_host": { "value": "acme.com", "source": "programmatic (model getUrlForSEO())" }
  }
}
```

## Siehe auch {#see-also}

- [Resolver-Priorität](/de/concepts/resolver-precedence): die vollständige Kette, die `seo:explain` verfolgt.
- [Kostenloses SEO-Audit](/de/guide/audit): `seo:audit` findet *Fehler*; `seo:explain` erklärt, *warum ein Wert seinen aktuellen Stand hat*.

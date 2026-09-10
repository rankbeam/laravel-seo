---
description: "Prüfe SEO-Metadaten mit seo:audit: eine kostenlose Tabelle pro Seite, ohne Queue, Lizenz oder Netzwerkzugriff."
---

# Kostenloser SEO-Audit (`seo:audit`)

`php artisan seo:audit` beantwortet mit einem kostenlosen Befehl: **Was stimmt gerade mit meinen SEO-Metadaten nicht?** Der Befehl durchläuft deine `HasSEO`-Modelle im laufenden Prozess, **ohne Queue, Lizenz oder Netzwerkzugriff**, und zeigt pro Seite **pass / warn / fail** sowie eine Zusammenfassung.

```bash
php artisan seo:audit
```

```
+--------------+--------+----------------------------+
| Page         | Status | Findings                   |
+--------------+--------+----------------------------+
| Post #1      | PASS   | —                          |
| Post #2      | WARN   | notice title_too_short     |
| Post #3      | FAIL   | critical missing_title     |
|              |        | warning missing_description|
+--------------+--------+----------------------------+

3 page(s) — 1 passed, 1 warned, 1 failed
4 issue(s) — 1 critical, 1 warning, 2 notice
```

## Was geprüft wird {#what-it-checks}

Der Audit führt ausschließlich Prüfungen der Ausführungsklasse **metadata** aus. Sie lassen sich aus dem Modell und dem [Resolver](/de/concepts/resolver-precedence) ableiten, ohne eine Seite abzurufen:

| Prüfung | Codes |
|---|---|
| Titel und Beschreibung vorhanden, einschließlich Fallbacks | `missing_title`, `missing_description` |
| OG-Bild vorhanden, einschließlich Fallbacks | `missing_og_image` |
| Länge von Titel und Beschreibung | `title_too_long`, `title_too_short`, `description_too_long`, `description_too_short` |
| Doppelte Titel und Beschreibungen auf der Website | `duplicate_title`, `duplicate_description` |
| Widersprüchliche Robots-Angaben und auffälliges noindex | `robots_conflict_indexing`, `robots_conflict_following`, `noindex_warning` |
| Canonical: Format, andere Domain, gemeinsame oder unsichere URL | `invalid_canonical`, `cross_domain_canonical`, `shared_canonical`, `insecure_canonical` |
| Antwortbereitschaft (AEO): strukturierte Artikeldaten | `aeo_missing_author`, `aeo_article_missing_date` |
| Fokus-Keyword gesetzt, nach Aktivierung | `missing_focus_keyword` |
| hreflang-Alternativen, nur im Core und nur bei vorhandener Liste | `hreflang_invalid_code`, `hreflang_duplicate_code`, `hreflang_missing_self` |

Das sind dieselben Fehlercodes wie im Pro-Scan; ein Befund hat daher dieselbe Bedeutung. Die hreflang-Codes und `blank_explicit_override` gibt es nur im Core. Für Längen gilt das [Budget je Schriftsystem](/de/guide/multilingual#title-and-description-budgets-per-script): 60/160 Grapheme für lateinische Texte, etwa 30/80 für CJK. Geprüft wird der **aufgelöste Wert einschließlich Titelsuffix**. Der [Filament-Editor](/de/guide/filament) verwendet dieselbe Richtlinie; noch nicht gespeicherte Eingaben können vom aufgelösten Wert abweichen.

Die hreflang-Prüfung verwendet die Liste nach Anwendung der Richtlinien aus `seo.hreflang`, also dieselbe Liste wie Tags und Sitemap. Gegenseitige Verweise erfordern einen Crawl und werden in Pro geprüft.

Die beratenden **AEO-Prüfungen** greifen nur bei JSON-LD-Artikeln (`Article`, `BlogPosting`, `NewsArticle`, …), denen eine `author`-Entität oder eine Zeitangabe (`datePublished` / `dateModified`) fehlt. Damit werden Herkunft und zeitliche Einordnung in den strukturierten Daten geprüft. Ohne Artikeldeklaration entsteht kein solcher Befund. Diese Hinweise haben die Stufe `notice` und fließen nicht in den Pro-Score von 0–100 ein.

## Was nicht geprüft wird — die Funktionsgrenze {#what-it-does-not-check-—-the-capability-boundary}

Ein kostenloser Audit im Anwendungsprozess deckt nicht den gesamten Pro-Scan ab. Der Befehl weist bei jedem Lauf darauf hin. Er führt Folgendes nicht aus:

- **Prüfungen des ausgelieferten HTML:** `missing_h1`, `multiple_h1`, `missing_image_alt`, `thin_content`, `mixed_content` benötigen den tatsächlichen Seiteninhalt.
- **Netzwerkprüfungen des Canonical-Ziels:** `canonical_target_broken` / `_redirect` / `_noindex` benötigen einen abgesicherten ausgehenden Abruf.
- **Den numerischen Score von 0–100:** Pro speichert ihn mit einer versionierten Bewertungsgrundlage im Scan-Ergebnis; siehe [SEO-Score (EN)](/de/pro/scoring).

Diese Funktionen gehören zum **Pro-Scan**. Die vollständige [Fehlerliste (EN)](/de/pro/scan-issues) beschreibt ihre Grenzen.

## Modelle auswählen {#choosing-what-to-audit}

Standardmäßig prüft der Befehl `seo.audit.models`; fehlt die Liste, verwendet er `seo.sitemap.models`:

```php
// config/seo.php
'audit' => [
    'models' => [
        \App\Models\Post::class,
        \App\Models\Page::class,
    ],
],
```

Alternativ kannst du Modelle ausdrücklich angeben:

```bash
php artisan seo:audit --model="App\Models\Post" --model="App\Models\Page"
```

## Optionen {#options}

| Option | Wirkung |
|---|---|
| `--model=` | Zu prüfende `HasSEO`-Modellklasse; mehrfach möglich, überschreibt die Konfiguration. |
| `--locale=` | Sprache für die Auflösung der SEO-Daten; standardmäßig die Anwendungssprache. |
| `--limit=` | Höchstzahl der Datensätze pro Modell; `0` bedeutet alle. |
| `--issues-only` | Nur Seiten mit mindestens einem Befund anzeigen. |
| `--strict` | Bei einem Befund einen Fehlerstatus zurückgeben, etwa für CI. |
| `--json` | Maschinenlesbares JSON mit Seiten, Zusammenfassung und Prüfumfang statt der Tabelle ausgeben. |

### CI-Prüfung {#ci-gate}

Mit `--strict` wird der Audit zur Build-Prüfung:

```bash
php artisan seo:audit --strict
```

Der Exit-Code ist `1`, sobald eine Seite eine Warnung oder einen Fehler hat, und `0`, wenn alle geprüften Seiten bestehen.

### JSON {#json}

```bash
php artisan seo:audit --json
```

```json
{
  "pages": [
    {
      "model": "App\\Models\\Post",
      "key": 3,
      "label": "Post #3",
      "url": "https://example.com/blog/...",
      "status": "fail",
      "issues": [
        { "code": "missing_title", "severity": "critical", "field": "title", "message": "Page is missing a title tag." }
      ]
    }
  ],
  "summary": { "pages": 3, "passed": 1, "warned": 1, "failed": 1, "issues": 4, "by_severity": { "critical": 1, "warning": 1, "notice": 2 } },
  "skipped": [],
  "coverage": { "executes": "metadata", "note": "...", "reference": "https://rankbeam.dev/pro/scan-issues" }
}
```

## Fokus-Keywords {#focus-keywords}

Der Hinweis `missing_focus_keyword` ist **standardmäßig deaktiviert**. Er wird erst nach Aktivierung des Fokus-Keyword-Workflows ausgegeben:

```php
// config/seo.php
'keywords' => [
    'enabled' => true,
],
```

Der Pro-Scan liest dieselbe Einstellung. Audit, Scan und Editor verwenden damit dieselbe Aktivierung. Setze Keywords im [Filament-Feld](/de/guide/filament) oder über `$model->saveSEO(['focus_keywords' => [['keyword' => 'laravel seo', 'is_primary' => true]]])`.

## Unerwartete Werte mit `seo:explain` erklären {#when-a-value-isn-t-what-you-expect-seo-explain}

`seo:audit` zeigt, **was nicht stimmt**. [`seo:explain`](/de/guide/explain) zeigt, **warum ein Feld diesen Wert hat**: welche Ebene (Konfiguration, Standardwert, berechneter oder expliziter Wert) ihn gesetzt und überschrieben hat und welche Nachbearbeitung folgte, etwa Titelsuffix, Canonical-Bereinigung oder Indexierungsschutz. Nutze es bei unerwarteten Befunden oder Tags:

```bash
php artisan seo:explain "App\Models\Post" 42
```

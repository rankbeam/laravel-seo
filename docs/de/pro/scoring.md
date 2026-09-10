---
description: "Die SEO-Bewertung des Pro-Scans von 0 bis 100: deterministisch und nachvollziehbar, mit jedem Punktabzug einem Scan-Befund zugeordnet. Gleiche Befunde ergeben denselben Wert."
---

# SEO-Bewertung: nachvollziehbar, versioniert und Teil von Pro {#the-seo-score-—-transparent-versioned-pro-owned}

Der Pro-Scan gibt jeder Seite eine **SEO-Bewertung von 0 bis 100**, wie sie Umsteiger von Rank Math oder Yoast erwarten. Jeder Punktabzug lässt sich auf genau einen [Scan-Befund](/de/pro/scan-issues) zurückführen. Dieselbe Befundmenge erzeugt denselben Wert; die Berechnung ist vollständig nachvollziehbar.

```
score = 100 − Σ penalty(issue) for each scored issue   (floored at 0)
```

::: tip Eine Zahl mit klarer Zuständigkeit
Die numerische Bewertung gehört zu **Pro** und wird in `seo_scan_results` gespeichert, nicht in `seo_meta` des Cores. Die alte Spalte `seo_score` wurde in Core 3 entfernt. Das kostenlose [`seo:audit`](/de/guide/audit) meldet je Seite **pass, warn oder fail ohne Zahl**. Die numerische Bewertung ist eine zusätzliche Pro-Funktion.
:::

## Die Bewertungsregel {#the-rubric}

Die Berechnung verwendet die **veröffentlichte, versionierte Regel** `Rankbeam\Seo\Pro\Scanning\ScoreRubric`: eine ausdrückliche **Liste bewerteter Befundcodes** und einen festen **Abzug je Schweregrad**.

| Schweregrad | Abzug | Bedeutung |
|---|---|---|
| `critical` | **−40** | In dieser Bewertungsregel stark gewichteter Befund |
| `warning` | **−15** | Zeitnah zu untersuchender Befund |
| `notice` | **−5** | Ergänzender Verbesserungshinweis |

Der Schweregrad jedes Codes stammt direkt aus der [Befund-Registry](/de/pro/scan-issues). Die Bewertungsregel leitet ihn nicht erneut ab. Jedem Code ist genau ein Schweregrad zugeordnet, damit die Berechnung deterministisch bleibt.

### Was in die Bewertung eingeht {#what-the-score-counts}

Die folgenden deterministischen Prüfungen wurden für Rankbeams Produktbewertung ausgewählt. Dazu gehören Heuristiken, die redaktionelle Einordnung benötigen. Critical kostet 40 Punkte, warning 15 und notice 5. Der Wert ist keine Prognose der Suchperformance.

| Code | Schweregrad | Abzug |
|---|---|---|
| `missing_title` | critical | −40 |
| `missing_description` | warning | −15 |
| `missing_og_image` | notice | −5 |
| `duplicate_title` | warning | −15 |
| `duplicate_description` | warning | −15 |
| `title_too_long` | warning | −15 |
| `title_too_short` | notice | −5 |
| `description_too_long` | warning | −15 |
| `description_too_short` | notice | −5 |
| `robots_conflict_indexing` | critical | −40 |
| `robots_conflict_following` | warning | −15 |
| `noindex_warning` | warning | −15 |
| `invalid_canonical` | critical | −40 |
| `cross_domain_canonical` | warning | −15 |
| `shared_canonical` | notice | −5 |
| `insecure_canonical` | warning | −15 |
| `http_error` | critical | −40 |
| `empty_response` | critical | −40 |
| `missing_canonical` | notice | −5 |
| `missing_h1` | notice | −5 |
| `missing_image_alt` | warning | −15 |
| `thin_content` | notice | −5 |
| `mixed_content` | warning | −15 |
| `canonical_target_broken` | critical | −40 |
| `canonical_target_redirect` | warning | −15 |
| `canonical_target_noindex` | warning | −15 |

Metadatencodes entstehen beim Modellscan, gerenderte und Netzwerkbefunde nur beim URL-Scan; siehe [Ausführungsklassen](/de/pro/scan-issues#execution-classes). Die Bewertung eines **Modellziels** beschreibt deshalb Metadatenprüfungen, die eines **URL-Ziels** die gerenderte Seite. 100 Punkte beim Modellscan bedeutet keine erkannten bewerteten Metadatenfehler, nicht eine fehlerfreie gerenderte Seite. Prüfe dafür auch die URL.

### Was bewusst nicht bewertet wird {#what-the-score-deliberately-does-not-count}

Die folgenden Registry-Codes sind ausdrücklich ausgeschlossen. Ein Test stellt sicher, dass jeder Registry-Code entweder bewertet oder als Ausschluss aufgeführt ist:

| Code | Grund für den Ausschluss |
|---|---|
| `missing_focus_keyword` | **Hinweis.** Gehört zum optionalen Workflow `seo.keywords.enabled`. Eine Seite darf nicht wegen Nichtnutzung von Fokus-Keywords schlechter bewertet werden; der Wert soll nicht von diesem Schalter abhängen. |
| `noindex_page` | **Information.** `noindex` kann beabsichtigt sein und ist allein kein Metadatenfehler. Die Heuristik „noindex mit Selbst-Canonical“ wird stattdessen über `noindex_warning` bewertet. |
| `multiple_h1` | **Information.** Mehrere H1-Elemente verursachen hier keinen Abzug; Google toleriert mehrere H1. |
| `blocked_url` | **Fehlender Nachweis.** Der SsrfGuard hat den Abruf verweigert; die Seite wurde nicht geprüft. |
| `canonical_target_blocked` | **Fehlender Nachweis.** Das Canonical-Ziel ließ sich nicht prüfen. |
| `hreflang_invalid_code`, `hreflang_missing_self_reference`, `hreflang_duplicate_code`, `hreflang_missing_x_default` | **Derzeit Hinweise.** Diese Pro-Codes erscheinen im Scan; das kostenlose Audit besitzt eigene Hreflang-Codes. Ihre Aufnahme in die Bewertung benötigt eine neue `VERSION`. |
| `html_lang_missing`, `html_lang_invalid`, `html_lang_mismatch` | **Hinweise.** Sprachprüfungen sind von dieser Bewertungsregel ausgenommen. |
| `hreflang_not_reciprocal` | **Hinweis.** Optionale Prüfung gegenseitiger Verweise, ohne Punktabzug. |
| `hreflang_target_unverified` | **Fehlender Nachweis.** Die Gegenseitigkeit konnte nicht geprüft werden. |
| `aeo_missing_author`, `aeo_article_missing_date` | **Hinweise.** AEO-Prüfungen auf fehlende Autorenentität oder Veröffentlichungsdatum im Scan und kostenlosen Audit. Ein Punktabzug würde eine neue `VERSION` erfordern. |

Keyword-Dichte, Power-Wörter und die übrige [Onpage-Checkliste](/de/pro/on-page-checklist) fließen überhaupt nicht in die Bewertung ein. Sie bilden eine eigene hinweisende Pass/Warn/Fail-Liste und sind keine Registry-Codes.

## Versionierung: gespeicherte Bewertungen bleiben nachvollziehbar {#versioning-—-historical-scores-never-silently-change}

Jeder gespeicherte Wert trägt die erzeugende `ScoreRubric::VERSION` in `rubric_version`. Daraus folgen zwei Regeln:

- Ein **neuer Befundcode** bewirkt **keinen Abzug**, bis er ausdrücklich in die Bewertungsliste aufgenommen wird. Eine neue Prüfung verändert gespeicherte Werte daher nicht rückwirkend. Änderungen der Liste oder Gewichte erhöhen die Version der Bewertungsregel.
- Der Wert wird **gespeichert und beim Lesen nicht neu berechnet**. Ein zuvor gespeichertes Ergebnis bleibt zusammen mit seiner erklärenden Regel erhalten, bis ein neuer Scan das aktuelle Ziel neu bewertet.

## Speicherort {#where-it-s-stored}

Jeder Scan legt pro Ziel einen Datensatz in `seo_scan_results` an oder aktualisiert ihn:

| Spalte | Inhalt |
|---|---|
| `scannable_type` / `scannable_id` | Bewertetes Modell; null bei URL-Zielen |
| `url` | Bewertete URL |
| `score` | Wert von 0 bis 100 |
| `rubric_version` | Verwendete Bewertungsregel |
| `penalty_total` | Summe der Abzüge **vor** der Begrenzung auf mindestens 0 |
| `scored_issues` | Anzahl der Befunde mit Einfluss auf den Wert |
| `breakdown` | Vollständige Aufschlüsselung als `[{code, severity, penalty}, …]` |
| `keywords_enabled` | Zustand von `seo.keywords.enabled` beim Scan, zur Nachvollziehbarkeit; ohne Einfluss auf den Wert |
| `scan_run_id` | Bewertender Lauf. Beim Bereinigen des Laufs auf null gesetzt; das Ergebnis bleibt als aktueller Stand erhalten und ist keine Laufhistorie. |
| `scored_at` | Zeitpunkt der Bewertung |

## Bewertung lesen {#reading-the-score}

**Ohne Oberfläche** den letzten Wert eines Modells abrufen:

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$result = SeoPro::resultFor($post);

$result?->score;     // e.g. 85
$result?->grade();   // 'A'..'F'
$result?->breakdown; // [['code' => 'cross_domain_canonical', 'severity' => 'warning', 'penalty' => 15]]
```

`php artisan seo-pro:scan-status` zeigt die **durchschnittliche Website-Bewertung** in der Zusammenfassung. Im Filament-Dashboard erscheint sie als „Avg. SEO score“, nach Bewertungsstufe eingefärbt.

### Bewertungsstufen {#grade-bands}

Die Buchstabennote dient der Darstellung und wird aus der Zahl abgeleitet; maßgeblich bleibt die Zahl:

| Wert | Stufe |
|---|---|
| 90–100 | A |
| 75–89 | B |
| 50–74 | C |
| 25–49 | D |
| 0–24 | F |

## Die Heuristik hinter `noindex_warning` {#the-shipping-signal-noindex-warning}

`noindex_warning` wird ausgelöst, wenn eine Seite `noindex` mit einem **Selbst-Canonical** kombiniert, also einem Canonical auf ihre eigene URL. Rankbeam behandelt dies als prüfbedürftiges Veröffentlichungssignal. Ein Selbst-Canonical beweist jedoch keine Indexierungsabsicht; die Kombination kann gewollt sein. Ein Canonical auf eine andere Domain erfüllt diese Heuristik nicht. Der Befund enthält `context.shipping_signal`, etwa `self_canonical`, sowie die verglichenen Werte `canonical` und `page_url`.

Beide Scanner verwenden diese Prüfung. Der Modellscan `PageScanner` vergleicht den gespeicherten Canonical mit der Modell-URL. Der gerenderte URL-Scan `UrlScanner` stuft eine noindex-Seite mit Selbst-Canonical vom rein informativen `noindex_page` auf das bewertete `noindex_warning` hoch. Deshalb ist `noindex_page` selbst von der Bewertung ausgeschlossen; der Abzug hängt auf beiden Wegen an `noindex_warning`. Prüfe die tatsächliche Seitenabsicht, bevor du die Anweisung änderst.

## Konfiguration {#configuration}

```php
// config/seo-pro.php → 'scan'
'score' => [
    'enabled' => true, // turn the scoring pass + its persistence on/off
],
```

Bewertungsliste und Gewichte sind **nicht konfigurierbar**. Für eine bestimmte `rubric_version` muss die Berechnung auf jeder Installation gleich sein. Eine Änderung erfolgt deshalb an der Bewertungsregel im Code, nicht über eine Einstellung.

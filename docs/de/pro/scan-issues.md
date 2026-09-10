---
description: "Die Registry stabiler Befundcodes für den Pro-Scan: feste Schweregrade und Felder, damit Dashboards und Exporte Codes statt Meldungstexten auswerten."
---

# Scan-Befunde – die Registry der Befundcodes {#scan-issues-—-the-issue-code-registry}

Jedes vom Pro-Scan gemeldete Problem erhält einen **stabilen Befundcode** aus der zentralen Registry `Rankbeam\Seo\Pro\Scanning\IssueRegistry`. Scanner erfinden keine Codes während der Ausführung. Sie erzeugen Befunde über `IssueRegistry::make()`, das Schweregrad und Feld aus der Registry übernimmt und **nicht definierte Codes ablehnt**. Der folgende Katalog ist damit eine verlässliche Schnittstelle: Dashboards, Exporte und der [Pro-Score](/de/pro/scoring) werten Codes aus, statt Meldungstexte zu zerlegen. Das kostenlose [`seo:audit`](/de/guide/audit) verwendet eine eigene Metadaten-Registry des Core-Pakets mit geringerem Prüfumfang und teilweise anderen hreflang-Codes.

Jeder Code enthält:

- **id**: die stabile Zeichenfolge, die als `seo_scan_issues.issue_type` gespeichert wird.
- **severity**: `critical`, `warning` oder `notice`, **pro Code festgelegt**. Statt den Schweregrad zu variieren, wird ein Code aufgeteilt.
- **field**: das betroffene Feld von `seo_meta` oder *page* für Befunde auf Seitenebene.
- **execution class**: die Voraussetzungen für die Prüfung; siehe unten.
- **evidence**: die Schlüssel im Array `context` des Befunds.

## Ausführungsklassen {#execution-classes}

Jede Prüfung gehört anhand ihrer Voraussetzungen zu genau einer von drei Klassen:

| Klasse | Voraussetzung | Ausführung |
|---|---|---|
| **metadata** | Modell und Core-Resolver, ohne Seitenabruf | Modell-Scan (`PageScanner`); das kostenlose [`seo:audit`](/de/guide/audit) deckt einen Teil dieser Metadatenprüfungen ab |
| **rendered** | Ausgeliefertes HTML der Seite, über eine Kernel-Anfrage im Prozess oder einen externen Abruf | URL-Scan (`UrlScanner`) |
| **network** | **Ausgehender** Abruf zur Prüfung eines *anderen* Ziels, etwa einer abweichenden Canonical-URL | URL-Scan, **immer über den `SsrfGuard`** |

Deshalb deckt ein kostenloses Audit innerhalb des Prozesses nicht denselben Umfang ab wie ein vollständiger Pro-Scan. Nur die **metadata**-Codes lassen sich ohne Rendering einer Seite ermitteln. Die Pro-Pipeline ruft zusätzlich gerendertes HTML ab und prüft Canonical-Ziele über das Netzwerk. Mit `IssueRegistry::byClass(IssueRegistry::EXEC_METADATA)` kannst du die Registry nach Klasse filtern.

## Metadaten-Codes {#metadata-codes}

`PageScanner` ermittelt diese Befunde aus Modell und Resolver. `missing_title`, `missing_description` und die Längencodes werden auch vom Scan gerenderter URLs ausgegeben. Dort messen sie den ausgelieferten `<head>`; Codes und Bedeutung bleiben gleich.

| Code | Schweregrad | Feld | Belege | Bedeutung |
|---|---|---|---|---|
| `missing_title` | critical | title | — | Weder ein Titel noch ein berechenbarer Fallback ist vorhanden. |
| `missing_description` | warning | description | — | Weder eine Meta-Beschreibung noch ein berechenbarer Fallback ist vorhanden. |
| `missing_og_image` | notice | og_image | — | Weder ein Open-Graph-Bild noch ein berechenbarer Fallback ist vorhanden. |
| `missing_focus_keyword` | notice | focus_keywords | — | Kein Fokus-Keyword festgelegt. |
| `duplicate_title` | warning | title | `title`, `duplicate_urls` | Andere Seiten derselben Sprache verwenden denselben Titel. |
| `duplicate_description` | warning | description | `description`, `duplicate_urls` | Andere Seiten derselben Sprache verwenden dieselbe Beschreibung. |
| `title_too_long` | warning | title | `length`, `max`, `script` | Der aufgelöste Titel überschreitet die Empfehlung für seine Schrift: 60 bei lateinischer Schrift, ungefähr 30 bei CJK. |
| `title_too_short` | notice | title | `length`, `min`, `script` | Der aufgelöste Titel unterschreitet die Untergrenze für seine Schrift: 30 bei lateinischer Schrift, ungefähr 15 bei CJK. |
| `description_too_long` | warning | description | `length`, `max`, `script` | Die aufgelöste Beschreibung überschreitet die Empfehlung für ihre Schrift: 160 beziehungsweise ungefähr 80. |
| `description_too_short` | notice | description | `length`, `min`, `script` | Die aufgelöste Beschreibung unterschreitet die Untergrenze für ihre Schrift: 70 beziehungsweise ungefähr 35. |
| `robots_conflict_indexing` | critical | robots | `robots` | Die Robots-Anweisung enthält sowohl `index` als auch `noindex`. |
| `robots_conflict_following` | warning | robots | `robots` | Die Robots-Anweisung enthält sowohl `follow` als auch `nofollow`. |
| `noindex_warning` | warning | robots | `robots`, `canonical`, `page_url`, `shipping_signal` | Eine Seite mit Canonical-Verweis auf sich selbst ist auf `noindex` gesetzt. Das ist eine zu prüfende Heuristik, kein Beweis, dass die Seite indexiert werden muss. Wird sowohl bei Modell- als auch bei gerenderten URL-Scans ausgegeben. |
| `invalid_canonical` | critical | canonical | `canonical` | Der Canonical-Wert ist keine gültige URL. |
| `cross_domain_canonical` | warning | canonical | `canonical`, `page_url` | Die Canonical-URL verweist auf einen anderen Host als die Seite. |
| `shared_canonical` | notice | canonical | `canonical` | Mehrere Seiten geben dieselbe Canonical-URL an. |
| `insecure_canonical` | warning | canonical | `canonical` | Eine HTTPS-Website verwendet eine Canonical-URL mit `http://`. |
| `hreflang_invalid_code` | warning | alternates | `invalid_codes` | Eine hreflang-Alternative verwendet weder `x-default` noch einen gültigen BCP-47-Sprachcode. |
| `hreflang_missing_self_reference` | warning | alternates | `locale`, `page_url` | Alternativen sind angegeben, aber keine verweist auf die eigene Sprache der Seite; der hreflang-Selbstverweis fehlt. |
| `hreflang_duplicate_code` | warning | alternates | `duplicate_codes` | Derselbe hreflang-Code verweist auf mehrere URLs und macht die Zuordnung mehrdeutig. |
| `hreflang_missing_x_default` | notice | alternates | `languages` | Einer hreflang-Gruppe mit mehreren Sprachen fehlt der Fallback `x-default`. |
| `aeo_missing_author` | notice | schema | — | Ein Artikel in den strukturierten Daten der Seite enthält keine Autorenentität; Urheberschaft beziehungsweise Herkunft sind im Schema nicht ausdrücklich angegeben. |
| `aeo_article_missing_date` | notice | schema | — | Ein Artikel in den strukturierten Daten enthält kein Veröffentlichungs-/Änderungsdatum; der zeitliche Verlauf ist im Schema nicht ausdrücklich angegeben. |

Die Längengrenzen stammen aus der schriftabhängigen [Längenrichtlinie](/de/guide/multilingual#title-and-description-budgets-per-script) des Core-Pakets (Pro 2.33): 60/160 für lateinischen Text und ungefähr 30/80 für CJK. Gezählt werden Grapheme, damit Scan und Zeichenzähler des Editors dieselbe Grundlage verwenden. Die Untergrenzen – bei lateinischer Schrift 30 für Titel und 70 für Beschreibungen, bei CJK ungefähr die Hälfte – dienen dem Scan als Hinweis auf möglicherweise zu kurze Metadaten. Der Kontextschlüssel `script` nennt die verwendete Schriftgruppe. Gemessen werden **aufgelöster** Titel und aufgelöste Beschreibung, also die tatsächliche Ausgabe einschließlich Fallbacks und Titelsuffix.

Die Codes `hreflang_*` prüfen die von der Seite angegebenen hreflang-Alternativen aus `alternates` des Resolvers: ungültige oder doppelte Codes, einen fehlenden Selbstverweis und fehlendes `x-default` bei einer mehrsprachigen Gruppe. Sie laufen nur, wenn die Seite Alternativen angibt. Ob andere Seiten **zurückverweisen**, prüfen diese Metadatenprüfungen nicht. Dafür ruft die optionale Netzwerkprüfung weiter unten die andere Seite ab.

Die Codes `aeo_*` sind Signale für die **Aufbereitung als Antwortquelle (AEO)**: Ist der Artikelinhalt in den strukturierten Daten nachvollziehbar beschrieben? Sie lesen den aufgelösten JSON-LD-Graphen und schlagen **nur** bei angegebenen Artikeltypen wie `Article`, `BlogPosting` oder `NewsArticle` an, wenn eine `author`-Entität für ausdrückliche Urheberschaft/Herkunft oder `datePublished` beziehungsweise `dateModified` für die zeitliche Einordnung fehlen. Seiten ohne Artikel erhalten diese Befunde nicht. Die Prüfungen hängen von `seo-pro.scan.checks.aeo` ab (standardmäßig aktiviert) und entsprechen dem kostenlosen [`seo:audit`](/de/guide/audit).

::: tip `missing_focus_keyword` hängt von einer Freigabe ab
Der Hinweis auf ein fehlendes Fokus-Keyword erscheint nur, wenn der Fokus-Keyword-Ablauf im **Core-Paket** aktiviert ist: `seo.keywords.enabled`, standardmäßig `false`. Bei deaktivierter Funktion meldet der Scan kein fehlendes Fokus-Keyword. Das kostenlose [`seo:audit`](/de/guide/audit) und der Filament-Editor lesen **denselben** Core-Schalter. Scan, Audit und Editor verwenden damit eine einzige gemeinsame Freigabe.
:::

## Codes für gerendertes HTML {#rendered-codes}

`UrlScanner` ermittelt diese Befunde aus dem ausgelieferten HTML. Ziele auf demselben Host werden über eine Kernel-Anfrage im Prozess ohne ausgehenden Netzwerkverkehr abgerufen; externe Ziele über einen geschützten Abruf.

| Code | Schweregrad | Feld | Belege | Bedeutung |
|---|---|---|---|---|
| `http_error` | critical | page | `status` | Die URL antwortet mit einem Status 4xx oder 5xx. |
| `empty_response` | critical | page | — | Die URL liefert einen leeren Antwortinhalt. |
| `missing_canonical` | notice | canonical | — | Im gerenderten Head fehlt `<link rel="canonical">`. |
| `noindex_page` | notice | robots | `robots` | Die gerenderte Seite ist auf `noindex` gesetzt; rein informativ. Verweist ihre Canonical-URL zusätzlich **auf sie selbst**, wird stattdessen der bewertete Befund `noindex_warning` ausgegeben. |
| `missing_h1` | notice | page | — | Keine `<h1>`-Überschrift vorhanden. |
| `multiple_h1` | notice | page | `count` | Mehr als eine `<h1>` vorhanden; rein informativ. |
| `missing_image_alt` | warning | page | `count`, `total`, `sample` | Inhaltsbilder haben kein `alt`-Attribut. Ein ausdrücklich leeres `alt=""` gilt als dekorativ und wird nicht gemeldet. |
| `thin_content` | notice | page | `word_count`, `threshold`, `segmenter` | Der Haupttext unterschreitet die konfigurierte Wortzahl. Der Checklisten-Tokenizer verwendet Worttrennung für Schriften mit Leerzeichen und ICU-Wörterbuchsegmentierung für Chinesisch, Japanisch und Thai (`segmenter: intl`, benötigt ext-intl). Ein japanischer Artikel mit 400 Wörtern zählt dadurch nicht als ein einziges „Wort“. |
| `mixed_content` | warning | page | `count`, `sample` | Eine HTTPS-Seite bindet Unterressourcen über `http://` ein. |
| `html_lang_missing` | notice | page | — | `<html lang>` fehlt oder ist leer. Assistive Technik könnte eine ungeeignete Stimme wählen. |
| `html_lang_invalid` | notice | page | `declared` | Der Wert von `lang` ist kein gültiges BCP-47-Tag, etwa `english`, `en_US` mit Unterstrich oder `jp`. |
| `html_lang_mismatch` | warning | page | `declared`, `declared_script`, `detected_script` | Die Schrift des sichtbaren Haupttexts passt nicht zur angegebenen Sprache, etwa `lang="en"` auf einer japanischen Seite oder `lang="ru"` bei lateinischer Schrift. Die Prüfung erkennt nur Schriftunterschiede; sie errät keine falsche Sprache innerhalb derselben Schrift. Mindestens 40 Buchstaben im Haupttext sind erforderlich. |

## Netzwerk-Codes {#network-codes}

`UrlScanner` ermittelt diese Befunde nur bei aktivierter passender Option: `seo-pro.scan.url_checks.check_canonical_target` für Canonical-Ziele und `check_hreflang_reciprocity` für hreflang-Alternativen. Jeder Zielabruf läuft **durch den `SsrfGuard`** mit Schema-Allowlist, Host-Scope, Ablehnung privater IPs sowie Weiterleitungs-, Zeit- und Größenlimits. Weiterleitungen werden dabei **nicht verfolgt**, damit eine weiterleitende Canonical-URL sichtbar bleibt. Canonical- und Alternativverweise auf die Seite selbst werden übersprungen; die Seite wurde gerade bereits abgerufen.

| Code | Schweregrad | Feld | Belege | Bedeutung |
|---|---|---|---|---|
| `blocked_url` | notice | page | `reason` | Der `SsrfGuard` hat ein Ziel vor jeder HTTP-Anfrage abgelehnt. |
| `canonical_target_broken` | critical | canonical | `canonical`, `status` | Die Canonical-URL verweist auf eine Seite mit HTTP-Fehler. |
| `canonical_target_redirect` | warning | canonical | `canonical`, `status`, `location` | Die Canonical-URL verweist auf eine Weiterleitung. Verwende die endgültige URL. |
| `canonical_target_noindex` | warning | canonical | `canonical` | Das Canonical-Ziel ist selbst auf `noindex` gesetzt. |
| `canonical_target_blocked` | notice | canonical | `canonical`, `reason` | Das Canonical-Ziel konnte nicht geprüft werden, etwa wegen Ablehnung durch den Guard oder fehlender Auflösbarkeit. |
| `hreflang_not_reciprocal` | warning | alternates | `hreflang`, `href`, `status` | Eine angegebene Alternative verweist nicht auf die Ausgangsseite zurück. Das hreflang-Paar könnte ignoriert werden; dadurch allein wird die Übersetzung nicht unindexierbar. |
| `hreflang_target_unverified` | notice | alternates | `hreflang`, `href`, `reason` | Die Alternative konnte nicht abgerufen werden, etwa wegen Guard-Ablehnung, Fehlerstatus, Weiterleitung oder überschrittenem Größenlimit. Der Rückverweis wurde deshalb nicht geprüft. Das ist ein fehlender Nachweis, kein festgestellter Fehler. |

Die Rückverweisprüfung ruft pro Seite höchstens `hreflang_max_alternates` Ziele ab, standardmäßig 10, einschließlich `x-default`. Duplikate und die Seite selbst werden übersprungen. Die modellbezogenen Metadaten-Codes `hreflang_*` prüfen die *angegebene* Liste; diese zusätzliche Prüfung benötigt die andere Seite.

Alle hier beschriebenen Netzwerkpfade verwenden den gemeinsamen `SsrfGuard`. Das Bedrohungsmodell und die verbleibende TOCTOU-Einschränkung stehen in [SECURITY.md](https://github.com/rankbeam/laravel-seo-pro/blob/master/SECURITY.md).

## Wie die Codes den Score beeinflussen {#how-codes-feed-the-score}

Der [Pro-SEO-Score](/de/pro/scoring) beginnt bei `100` und zieht für jeden bewerteten Befund einen festen, nach den oben genannten Schweregraden gewichteten Betrag ab. Die meisten Codes zählen; einige sind bewusst ausgeschlossen: `missing_focus_keyword` als redaktioneller Hinweis, `noindex_page` und `multiple_h1` als Informationen sowie `blocked_url`, `canonical_target_blocked` und `hreflang_target_unverified`, weil eine nicht mögliche Prüfung keinen Fehler beweist. Auch `hreflang_*`, `html_lang_*` und `aeo_*` bleiben derzeit als beratende Signale außerhalb des Scores. Die [Seite zur Bewertung](/de/pro/scoring) enthält die vollständige Allowlist und den Abzug für jeden Code.

## Lebenszyklus eines Befunds {#issue-lifecycle}

Ein Befund ist mehr als eine Zeile, die nur während eines Problems existiert. Er hat einen Lebenszyklus. Ein Scan **gleicht** die Befunde eines Ziels ab, statt sie zu löschen und neu anzulegen. Jeder Befund hat eine stabile Identität: das Ziel (`scannable_type` und `scannable_id` bei Modellen oder `url` bei Routen-/Sitemap-Zielen) plus `issue_type`. Pro Ziel und Scan wird jeder Code höchstens einmal ausgegeben. Codes für mehrere betroffene Elemente wie `missing_image_alt`, `mixed_content` oder `hreflang_*` fassen diese in einer Zeile mit `count` beziehungsweise `sample` zusammen. Die Identität bleibt damit eindeutig.

Bei jedem Scan gilt für jedes Ziel:

- Ein Befund **ohne vorhandene Zeile** wird als `open` mit `detected_at` angelegt.
- Ein Befund, der **zu einer bestehenden offenen Zeile passt**, aktualisiert die Belege und behält das ursprüngliche `detected_at`. Der Zeitpunkt der ersten Feststellung wird nicht bei jedem Scan zurückgesetzt.
- Ein offener Befund, den eine vollständig ausgeführte Prüfung **nicht mehr findet**, wird als **`fixed`** markiert und erhält `resolved_at`. Die Zeile **bleibt erhalten**; die Behebung wird dokumentiert.
- Ein **behobener** Befund, der **zurückkehrt**, wird in derselben Zeile **wieder geöffnet**. Diese Regression erhält ein neues `detected_at`.
- Ein im Dashboard durch einen Nutzer als **`ignored`** markierter Befund bleibt unverändert.

| Status | Bedeutung | Gesetzt durch |
|---|---|---|
| `open` | Der Befund liegt aktuell vor. | Scan bei einem neuen oder weiterhin festgestellten Problem |
| `fixed` | Der Befund lag vor und wird nicht mehr gefunden. | Automatisch beim nächsten Scan, der ihn nicht erneut feststellt |
| `ignored` | Durch einen Nutzer ausgeblendet; zählt weder zu offenen Befunden noch zum Score. | Ignore-Aktion im Dashboard |

Weil Behebungen gespeichert werden, kann der [White-Label-Bericht](/de/pro/reports) **tatsächlich gespeicherte neue und behobene Befunde** eines Zeitraums zählen, statt nur zwei Berichts-Snapshots zu vergleichen. Dashboard, [`seo-pro:scan-status`](/de/pro/headless) und [Score](/de/pro/scoring) filtern offene Befunde auf `open`. Gespeicherte `fixed`-Zeilen erhöhen diese Zahlen deshalb nicht. Behobene Zeilen werden dem Lauf zugeordnet, der sie aufgelöst hat, und nach der normalen [Aufbewahrungsfrist](/de/pro/production) für Scan-Läufe entfernt.

## Konfiguration {#configuration}

```php
// config/seo-pro.php → 'scan'
'url_checks' => [
    'enabled' => true,
    'crawl_external' => false,             // fetch external URL targets (guarded)
    'check_canonical_target' => false,     // EXEC_NETWORK canonical validation (guarded)
    'check_hreflang_reciprocity' => false, // EXEC_NETWORK hreflang link-back crawl (guarded)
    'hreflang_max_alternates' => 10,       // targets fetched per page by that crawl
],
'checks' => [
    'length' => true,            // title/description length (metadata + rendered)
    'rendered_content' => true,  // H1 / alt / thin content / mixed content / html lang
],
'content' => [
    'min_word_count' => 200,     // thin_content threshold
    'evidence_sample' => 5,      // max example URLs stored per issue
],
```

Das Größenlimit für Antworten bei geschützten Abrufen ist `seo-pro.http.max_response_bytes`, standardmäßig 2 MB. Für Scans desselben Hosts innerhalb des Prozesses gilt dieses Limit nicht.

## Kompatibilitätshinweis zur Umbenennung eines Befundcodes {#compatibility-note-issue-code-rename}

Der bisherige einzelne Code `robots_conflict`, der zwei Schweregrade haben konnte, wurde aufgeteilt. Jeder Code entspricht jetzt genau einem Schweregrad:

| Alter Code | Neuer Code | Schweregrad |
|---|---|---|
| `robots_conflict` (index + noindex) | `robots_conflict_indexing` | critical |
| `robots_conflict` (follow + nofollow) | `robots_conflict_following` | warning |

Wenn du `robots_conflict` gespeichert oder danach gefiltert hast, stelle auf die beiden neuen Codes um.

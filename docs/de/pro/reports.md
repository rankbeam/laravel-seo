---
description: "PDF-Bericht mit deinem Branding: Bewertung, Befundtrend, behobene und neue Probleme, wiederhergestellte 404-Pfade, Search-Console-Veränderungen und KI-Bot-Aktivität. Per Befehl und optional nach Zeitplan versenden."
---

# White-Label-Berichte {#white-label-reports}

Ein **PDF-Bericht mit eigenem Branding** für eine Website: Gesamtbewertung, Befundtrend, **behobene und neue Probleme seit dem letzten Bericht**, reparierte 404-Pfade und Links, Search-Console-Veränderungen sowie KI-Bot-Aktivität. Ein Befehl erzeugt ihn; optional lässt er sich **nach Zeitplan per E-Mail versenden**. Für Agenturen stehen Logo, Farbe und eine Kundenbeschriftung „erstellt für {Kunde}“ bereit.

[Erzeugten englischen Beispielbericht herunterladen, PDF, 98 KB](/pro-walkthrough/merchant-demo-report.pdf) oder die [Anleitung Scan → Korrektur → Bericht](/de/pro/walkthrough) nachvollziehen. Das Beispiel verwendet vorbereitete Merchant-Inhalte und zwei neue Scans. Es zeigt einen behobenen Befund, 19 offene und keine Search-Console-Daten.

[![Erste Seite des erzeugten englischen Merchant-Demoberichts.](/pro-walkthrough/report-preview.png)](/pro-walkthrough/merchant-demo-report.pdf)

## Inhalt {#what-s-in-it}

- **Gesamtbewertung**: Durchschnitt der letzten Seitenbewertungen anhand der veröffentlichten [Bewertungsregel](/de/pro/scoring), von A bei mindestens 90 bis F. Dazu kommen die Änderung seit dem letzten Bericht und ein **Bewertungstrend** über jüngere Scans. Jeder Scan speichert die Website-Bewertung im Lauf. Der Trend beginnt deshalb mit dem ersten Scan nach dem entsprechenden Upgrade; ältere Läufe ohne Wert werden ausgelassen.
- **Befunde je Scan**: Verlauf über die letzten abgeschlossenen Scans; weniger Befunde entsprechen einem besseren Prüfergebnis.
- **Behobene und neue Befunde**: Änderungen seit dem letzten Bericht. Sobald ein ganzer Zeitraum unter dem [Lebenszyklus](/de/pro/scan-issues#issue-lifecycle) für behobene und wieder geöffnete Befunde erfasst ist, wird dessen Historie verwendet. Andernfalls dient der vorherige Berichtssnapshot als Vergleich.
- **Wiederhergestellt**: Behobene defekte Links, **wiederhergestellte 404-Pfade**, die wieder selbst 200 liefern, und seit dem letzten Bericht **weitergeleitete 404-Pfade**, dazu offene Einträge. [`seo-pro:404-recheck`](/de/pro/production#scheduler) prüft die Wiederherstellung. Ein wieder erreichbarer Quellpfad wird getrennt von einer Weiterleitung gezählt.
- **Search Console**: Führende Suchanfragen und Seiten sowie die größten Klickveränderungen gegenüber dem letzten Bericht. Ohne GSC-Einrichtung wird dieser Bereich entsprechend ausgelassen.
- **KI-Bot-Aktivität**: Anfragen nach User-Agent-Zuordnung, keine verifizierten Bot-Identitäten, mit Gesamtzahlen. Deckt die [Tageshistorie](/de/pro/ai-bot-monitor#period-metrics-daily-buckets) den Zeitraum ab, werden **Treffer und unterschiedliche URLs je Bot für diesen Zeitraum** berechnet. Andernfalls gilt die Differenz der Gesamtstände aus Berichtssnapshots.

## „Seit dem letzten Bericht“ {#since-the-last-report}

Der Vergleich bezieht sich auf den **vorherigen Bericht**, nicht auf ein beliebiges Datum. Bei jeder Erzeugung wird ein kleiner Snapshot in `seo_report_runs` gespeichert: Bewertung, Identitäten offener Befunde, Search-Console-Zeilen und Trefferzähler je Bot. Der nächste Bericht vergleicht den aktuellen Stand damit.

Dies ist der Fallback für Daten ohne eigene Historie, etwa Seitenbewertungen, von denen nur der neueste Stand gespeichert wird. Andere Signale besitzen inzwischen eine eigene Historie, die der Bericht bevorzugt: der [Befund-Lebenszyklus](/de/pro/scan-issues#issue-lifecycle), [tagesweise Search-Console-Metriken](/de/pro/search-console#historical-metrics) und [KI-Bot-Tagesgruppen](/de/pro/ai-bot-monitor#period-metrics-daily-buckets). Sie ermöglichen tatsächliche Zeitraumwerte, sobald ihre Historie den vollständigen Zeitraum abdeckt. Beim ersten Bericht nach einem Upgrade oder bei unvollständiger Historie bleibt der Snapshot-Vergleich der Fallback.

Zwei Folgen:

- **Der erste Bericht ist ein Ausgangsstand.** Er zeigt die aktuelle Situation. Behobene und neue Befunde, Veränderungen und „seit dem letzten Bericht“ füllen sich ab dem **zweiten** Bericht.
- **Du bestimmst den Rhythmus.** Monatliche Berichte vergleichen Monate, wöchentliche Berichte Wochen. `--no-store` erzeugt eine zusätzliche Vorschau, ohne den Ausgangsstand fortzuschreiben.

## Einen Bericht erzeugen {#generate-a-report}

```bash
php artisan seo-pro:report
```

Ohne Optionen wird eine PDF unter `storage/app/seo-reports/` geschrieben. Du kannst ein anderes Ziel wählen oder den Bericht versenden:

```bash
# Write to a specific file or directory
php artisan seo-pro:report --output=/tmp/acme-october.pdf

# E-mail it to one or more recipients (the PDF is attached)
php artisan seo-pro:report --email=client@acme.com --email=pm@agency.com

# One-off preview that does NOT store a snapshot (deltas won't advance)
php artisan seo-pro:report --no-store --output=/tmp/preview.pdf

# Machine-readable summary
php artisan seo-pro:report --json
```

### Optionen {#options}

| Option | Wirkung |
|---|---|
| `--client=` | Kundenbeschriftung „erstellt für“ überschreiben |
| `--agency=` | Agenturname überschreiben |
| `--accent=` | Akzentfarbe als Hex-Wert überschreiben, etwa `#3D5AFE` |
| `--logo=` | Pfad zur Logodatei überschreiben |
| `--email=` | Empfängeradresse; wiederholbar, versendet den Bericht |
| `--send` | An konfigurierte Empfänger senden |
| `--output=` | PDF in diese Datei oder dieses Verzeichnis schreiben |
| `--no-store` | Keinen Snapshot speichern; Vergleichsstand bleibt erhalten |
| `--json` | Maschinenlesbare Zusammenfassung ausgeben |

## E-Mail-Versand planen {#schedule-the-e-mail}

Das Paket plant den Berichtsversand nicht selbst. Trage den gewünschten Rhythmus im Konsolenzeitplan deiner App ein, in `routes/console.php` oder `app/Console/Kernel.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('seo-pro:report --send')->monthly();
```

Setze Standardempfänger einmal in Konfiguration oder `.env`:

```dotenv
SEO_PRO_REPORT_RECIPIENTS="client@acme.com,pm@agency.com"
```

`--send` verwendet diese Empfänger. Explizite `--email`-Optionen ersetzen sie.

## Branding {#branding}

Branding-Werte sind nicht geheim und stehen in der Konfiguration. Nach einmaliger Einrichtung verwendet sie jeder Bericht. Mit den obigen Befehlsoptionen lässt sich jedes Feld pro Bericht überschreiben, etwa wenn eine Installation unterschiedlich beschriftete Kundenberichte erzeugt.

```dotenv
SEO_PRO_REPORT_AGENCY="Blue Whale Studio"
SEO_PRO_REPORT_LOGO="/var/www/brand/logo.png"
SEO_PRO_REPORT_ACCENT="#3D5AFE"
SEO_PRO_REPORT_CLIENT="Acme Outdoor Co."
SEO_PRO_REPORT_CONTACT="hello@bluewhale.studio · bluewhale.studio"
SEO_PRO_REPORT_FOOTER="Confidential — prepared for Acme Outdoor Co."
```

Hinweise:

- **Logo**: Absoluter Pfad zu `PNG`, `JPG`, `GIF`, `WEBP` oder `SVG`. Nachdem die Anwendung die Datei gelesen hat, wird sie als Data-URI eingebettet. Der Renderer muss sie daher nicht über das Netzwerk abrufen. `PNG` oder `JPG` sind die verlässlichsten Formate.
- **Akzentfarbe**: Wird als Hex-Farbwert validiert. Ein ungültiger Wert fällt auf den Standard zurück. Die Eingabe wird nur als Farbe verwendet, nicht als rohes CSS.
- **Agenturname**: Standardmäßig der App-Name aus `config('app.name')`.

Der vollständige Block steht unter `reports` in `config/seo-pro.php`. Er enthält auch `paper`, standardmäßig `a4`, `include_gsc` sowie die Anzahl einzubeziehender Trend-Läufe, GSC-Zeilen und Bots.

## Eine Website pro Installation {#one-site-per-install}

Pro scannt die Anwendung, in der es installiert ist. Ein Bericht beschreibt deshalb **diese Installation**. Eine Agentur mit mehreren Kundenwebsites erzeugt einen Bericht je Installation und beschriftet ihn mit `--client` beziehungsweise Branding-Overrides. Ein mandantenfähiges „sites“-Modell gibt es nicht.

## Technische Erzeugung {#how-it-s-built}

Standardmäßig rendert **dompdf** die PDF mit PHP, ohne Node oder Headless Chromium. Ein geplanter Bericht kann damit im Queue-Worker oder Cronjob ohne zusätzliche Renderer-Systemprogramme laufen. Netzwerkabrufe sind im Renderer deaktiviert; das Logo wird eingebettet. Ein gerendertes Datenfeld kann dadurch keinen Bildabruf auslösen.

### Berichte für verschiedene Schriftsysteme mit Browsershot {#reports-in-every-script-browsershot-renderer}

Seit Core 3.20 und Pro 2.40 deaktivieren die Chrome-Renderer JavaScript und blockieren Asset-Anfragen über HTTP(S), FTP und WebSocket. Veröffentlichte Templates müssen statisches HTML/CSS mit eingebetteten Assets verwenden. Diese Regeln betreffen Seiten-Assets; Chrome benötigt weiterhin einen korrekt eingerichteten Host und eine passende Sandbox. Meldet Fontconfig eine fehlende Schrift für ein verwendetes Schriftsystem, auch innerhalb gemischten Texts, protokolliert der PDF-Renderer einen Installationshinweis. Chrome kann trotzdem eine PDF erzeugen. Prüfe sie deshalb vor dem Versand.

dompdf verwendet nur die eingebettete Schrift DejaVu Sans mit lateinischen, kyrillischen und griechischen Zeichen. Japanische, thailändische oder arabische Texte erscheinen damit als fehlende Glyphen. Seit Pro 2.34 kann stattdessen **Headless Chrome** über `spatie/browsershot` rendern. Dieselbe Abhängigkeit verwendet der Core für OG-Bilder; die Maschine muss dafür nur einmal eingerichtet werden:

```php
// config/seo-pro.php → 'reports'
'renderer' => 'browsershot',   // default 'dompdf'
'browsershot' => [
    'chrome_path' => null,      // null = reuse seo.og_image.chrome_path
    'node_binary' => null,      //   …  seo.og_image.node_binary
    'npm_module_path' => null,  //   …  seo.og_image.npm_module_path
    'no_sandbox' => null,       //   …  seo.og_image.no_sandbox
    'timeout' => 90,
],
'locale' => null,               // report language; null captures the app locale
'format_locale' => null,        // optional regional date/number format
```

Chrome nutzt die installierten Schriften. Das Template verwendet den schriftsystemabhängigen Font-Stack des Cores: `Noto Sans`, bevorzugt die zur Seitensprache passende `Noto Sans CJK`-Familie, dazu Thai, Arabisch, Hebräisch, Devanagari, farbige Emojis und DejaVu Sans als lateinischen Fallback. Installiere die benötigten Familien wie für [OG-Bilder](/de/guide/multilingual#og-images-in-every-script), unter Debian/Ubuntu beispielsweise mit `apt install fonts-noto-cjk fonts-noto-core fonts-noto-color-emoji`. Die Schriftwarnungen von `seo:og-images` helfen auch bei Berichten. Blade-Template, Daten und Snapshot bleiben bei beiden Engines gleich; nur der Renderer wechselt. `ReportGenerator::renderer()` zeigt die gebundene Implementierung.

### Datums- und Zahlenformate der Leser-Locale {#dates-and-numbers-in-the-reader-s-locale}

Beim Erzeugen wird `seo-pro.reports.locale` festgehalten. Null verwendet die App-Locale. Die aufgelöste Übersetzungssprache steuert PDF-/E-Mail-Beschriftungen, Standardbetreff, Schriftwahl und HTML-`lang`. Regionale Locales ohne eigene Übersetzungsdatei fallen auf die mitgelieferte Basissprache, danach auf Englisch zurück. Vereinfachtes (`zh_CN`) und traditionelles (`zh_TW`) Chinesisch bleiben getrennt.

Mit installiertem `ext-intl` folgen Datum und Zahlen über ICU der angeforderten Locale. `seo-pro.reports.format_locale` wählt bei Bedarf ein anderes regionales Format. Beispielsweise erzeugen `locale=it` und `format_locale=en_US` italienische Beschriftungen mit US-Datums- und Zahlenformaten. Ohne `ext-intl` bleiben englische Datumsformate und Zahlen mit Komma-Gruppierung als Fallback.

Queue-Mails behalten die erfasste Sprache, Formatierung und den Betreff, auch wenn sich die Worker-Konfiguration später ändert. Wähle die Sprache vor der PDF-Erzeugung; eine nachträgliche Locale-Änderung am Mailable übersetzt den Anhang nicht. Alte Queue-Nutzdaten vor Pro 2.39 besitzen keine gespeicherten Einstellungen und verwenden die Worker-Konfiguration. Eigene Betreffzeilen, Branding und gespeicherte Befundmeldungen bleiben Quelldaten.

Die CLI-Anzeigesprache ist unabhängig: `php artisan seo-pro:report --display-locale=it` übersetzt die Befehlszusammenfassung. Die Berichtskonfiguration wählt die Sprache von PDF und E-Mail. CLI verwendet standardmäßig Englisch, konfigurierbar über `SEO_PRO_CLI_LOCALE`. JSON-Schlüssel und Codes bleiben stabil; lesbare Beschriftungen können übersetzt werden. Veröffentliche `seo-pro-lang`, um Berichts- und Workflow-Texte in `lang/vendor/seo-pro/{locale}/seo-pro.php` anzupassen.

Für programmatischen Zugriff löse `ReportGenerator` aus dem Container auf:

```php
use Rankbeam\Seo\Pro\Reports\Branding;
use Rankbeam\Seo\Pro\Reports\ReportGenerator;

$report = app(ReportGenerator::class)->generate(
    Branding::fromConfig()->withOverrides(['prepared_for' => 'Acme Outdoor Co.']),
);

$report->pdf;        // raw PDF bytes
$report->data;       // the assembled ReportData
$report->run;        // the persisted SEOReportRun snapshot
```

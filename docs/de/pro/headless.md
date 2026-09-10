---
description: "Pro ohne Filament verwalten: Scans, Weiterleitungen und 404-Protokollierung laufen in der Engine. Befehlsreferenz für die Bedienung über Artisan."
---

# Verwendung ohne Oberfläche {#headless-usage}

Pro-Funktionen wie Scans, Weiterleitungen und 404-Protokollierung laufen in der Engine und benötigen kein Filament. Das Panel dient der Verwaltung; die folgenden Befehle bieten den entsprechenden Zugriff ohne Oberfläche.

## Befehlsreferenz {#command-reference}

### Einrichtung und Zustandsprüfung {#setup-health-check}

| Befehl | Aufgabe |
|---|---|
| `seo-pro:install` | `config/seo-pro.php` und Pro-Migrationen veröffentlichen, ausführen und nächste Schritte anzeigen; Optionen `--no-migrate`, `--force` |
| `seo:doctor` | Einmalige Prüfung von App-URL, Core-/Pro-Tabellen, Scan-Zielen, Sitemap, Queues je Aufgabe, optionalen Funktionen und Betriebszustand mit Korrekturhinweisen; `--json` für Monitoring |

`seo-pro:install` ist der dokumentierte Einrichtungsweg. Pro-Migrationen werden nur veröffentlicht, nicht automatisch vom Paket geladen. Der Installer ergänzt deshalb `composer require` um das benötigte Datenbankschema. Er ist idempotent und kann erneut ausgeführt werden.

`seo:doctor` ruft kein Netzwerk auf und gibt keine geheimen Werte aus. Die KI-Prüfung meldet nur, ob die konfigurierte Schlüsselvariable *gesetzt* ist. Geprüft werden Konfiguration und letzte Laufhistorie; ein tatsächlich laufender externer Cronjob oder Worker lässt sich damit nicht beweisen. Nur kritische Fehler wie eine fehlende erforderliche Tabelle ergeben einen Exit-Code ungleich null. Warnungen auf einer lokalen Entwicklungsumgebung verhindern keinen erfolgreichen Exit. `--json` liefert je Prüfung eine stabile `id`. Führe den Befehl direkt nach der [Installation](/de/pro/installation) und in CI aus.

### Scans {#scanning}

| Befehl | Aufgabe |
|---|---|
| `seo-pro:scan` | Vollständigen Scan aller registrierten Ziele einreihen; `--sync` führt direkt aus. **CI-Prüfoptionen** `--fail-on-error`, `--fail-on-warning`, `--report=`, `--format=json\|md\|html` benötigen `--sync`. |
| `seo-pro:scan-status` | Letzten Lauf und offene Befunde anzeigen, schwerste zuerst; `--limit=20`, `--severity=critical\|warning\|notice` |
| `seo-pro:scan-recover` | Durch ausgefallene Queue-Worker verlassene Läufe als fehlgeschlagen markieren |
| `seo-pro:scan-prune` | Abgeschlossene Läufe samt Befunden nach Ablauf der Aufbewahrungsfrist löschen |

### Broken-Link-Crawler {#broken-link-crawler}

Standardmäßig ausgeschaltet. Aktiviere `seo-pro.broken_links.enabled` und migriere die beiden Haupttabellen, deren Migrationen `seo-pro:install` veröffentlicht. Der Crawl läuft über begrenzte Queue-Jobs; verwende einen eigenen Worker für diese Queue. Einstellungen beschreibt [Produktion einrichten](/de/pro/production).

| Befehl | Aufgabe |
|---|---|
| `seo-pro:broken-links-scan` | Begrenzten, fortsetzbaren Crawl einreihen; `--scope=internal_only\|internal_and_external`, zusätzliche Start-URLs mit `--url=*` |
| `seo-pro:broken-links-status` | Letzten Crawl, offene Befunde und [typisierte Linkprüfungen](/de/pro/broken-links#typed-link-inspections) dieses Laufs anzeigen; **CI-Prüfoptionen** `--fail-on-error`, `--fail-on-warning`, `--report=`, `--format=` |
| `seo-pro:broken-links-cancel` | Laufenden oder eingereihten Crawl abbrechen; `{run?}` wählt standardmäßig den letzten aktiven Lauf |
| `seo-pro:broken-links-recover` | Nach Worker-Ausfall verlassene Crawls mit veralteter Lease als fehlgeschlagen markieren |
| `seo-pro:broken-links-prune` | Aufbewahrungsregeln auf alte Läufe und erledigte Befunde anwenden |

### Weiterleitungen und 404-Einträge {#redirects-404s}

| Befehl | Aufgabe |
|---|---|
| `seo-pro:redirect-create {source} {target}` | Weiterleitungsregel erstellen; `--code=301`, `--regex`, `--no-preserve-query`, `--note=` |
| `seo-pro:404-list` | Protokollierte 404-Einträge nach Häufigkeit anzeigen; `--status=new\|ignored\|redirected\|all`, `--limit=20` |
| `seo-pro:redirects-flush-hits` | Im Cache gesammelte Weiterleitungszähler in die Datenbank schreiben, wenn `redirects.hits.flush_immediately=false` |
| `seo-pro:404-prune` | Alte 404-Einträge löschen und die Höchstzahl an Zeilen durchsetzen |

### Onpage-Checkliste {#on-page-checklist}

| Befehl | Aufgabe |
|---|---|
| `seo-pro:checklist {model} {id}` | Keyword-bezogene Pass/Warn/Fail-Checkliste für ein Modell; `--json`, `--strict`, `--locale=`. Siehe [Onpage-Checkliste](/de/pro/on-page-checklist). |

Dieselbe Checkliste steht als `SeoPro::checklistFor($model)` bereit. Sie unterstützt die redaktionelle Bearbeitung von Keyword-Platzierung, Länge, Bildern und internen Links. Sie ist von der [SEO-Bewertung](/de/pro/scoring) getrennt.

### Search Console mit reinem Lesezugriff {#search-console-read-only}

| Befehl | Aufgabe |
|---|---|
| `seo-pro:search-console` | Seiten mit offenen Befunden **und** Suchtraffic, nach Handlungsbedarf priorisiert; standardmäßig `--view=attention` |
| `seo-pro:search-console --view=pages` | Führende Seiten nach Impressionen, Klicks, CTR und Position |
| `seo-pro:search-console --view=queries` | Führende Suchanfragen; `--days=`, `--limit=`, `--json` |

Dieselben Metriken liefert `SeoPro::searchConsole()`. Siehe [Search Console](/de/pro/search-console). Die Funktion ist standardmäßig aus und verwendet ausschließlich Lesezugriff.

### KI-Hilfe {#ai-assist}

| Befehl | Aufgabe |
|---|---|
| `seo-pro:ai-suggest {model} {id}` | Titel-/Beschreibungsvorschläge als JSON; `--field=title\|description\|all`. Siehe [KI-Hilfe](/de/pro/ai-assist). |
| `seo-pro:ai-suggest --issue={id}` | Lesbare Erklärung einer Korrektur für einen Scan-Befund als JSON |

### Einen 404-Eintrag in einem Schritt bearbeiten {#resolving-a-404-in-one-step}

`--from-404={path}` entspricht der Aktion *Create redirect* im 404-Monitor. Es erstellt die Regel **und** markiert den passenden Protokolleintrag als weitergeleitet, mit Verweis auf die neue Regel:

```bash
php artisan seo-pro:404-list

#  ID | Path        | Hits | Status | ...
#  1  | /old-pricing | 41  | new

php artisan seo-pro:redirect-create /old-pricing /pricing --from-404=/old-pricing

# Redirect #1 created: /old-pricing → /pricing (301).
# 404 log #1 (/old-pricing) marked redirected.
```

Der Befehl verwendet dieselben Validatoren wie das Filament-Formular. Ungültige Regex-Muster, überlange Werte und nicht freigegebene externe Ziele werden abgelehnt, bevor etwas geschrieben wird.

## Empfohlener Zeitplan {#recommended-schedule}

```php
use Illuminate\Support\Facades\Schedule;

// Only needed when seo-pro.redirects.hits.flush_immediately=false.
Schedule::command('seo-pro:redirects-flush-hits')->everyFiveMinutes();

// Keep the 404 log within retention and the row cap.
Schedule::command('seo-pro:404-prune')->daily();

// Scan cadence: weekly suits most sites; go daily when content
// changes fast. Scans are queued jobs - pair with a queue worker.
Schedule::command('seo-pro:scan')->weekly();

// Housekeeping for the scan pipeline.
Schedule::command('seo-pro:scan-recover')->hourly();
Schedule::command('seo-pro:scan-prune')->daily();

// Broken-link crawler (only when enabled). Run a dedicated worker for
// its queue so a long crawl never starves user-facing jobs.
Schedule::command('seo-pro:broken-links-scan')->weekly();
Schedule::command('seo-pro:broken-links-recover')->hourly();
Schedule::command('seo-pro:broken-links-prune')->daily();
```

Die [Produktionsanleitung](/de/pro/production) nennt für jeden wiederkehrenden Befehl einen empfohlenen Rhythmus. Sie beschreibt außerdem Queue-Aufteilung, Worker-Konfiguration, Wiederholungs- und Wiederherstellungsregeln, Aufbewahrung und die strukturierten **Betriebsdaten** abgeschlossener Läufe: abgerufene Seiten, geprüfte Links, blockierte URLs, Dauer und Queue-Verzögerung.

## Was benötigt die Filament-Oberfläche? {#what-needs-the-filament-ui}

Die Engine für Scan-Pipeline, Befundverwaltung, Weiterleitungsabgleich, 404-Protokollierung, Bereinigung und Wiederherstellung ist mit und ohne Filament dieselbe. Das Panel ergänzt die *Ansichten*: Dashboard mit Live-Fortschritt und Schweregradstatistik, gefilterte Befundlisten und Seitenmodale, Ignorieren/Wiederöffnen, Weiterleitungsformulare und die 404-Tabelle samt Direktaktion. Zum Ignorieren oder Wiederöffnen von Befunden gibt es derzeit keinen eigenen Befehl. Verwende dafür das Panel oder das Modell `SEOScanIssue` mit `markIgnored()` beziehungsweise `reopen()` in Tinker oder eigenem Code.

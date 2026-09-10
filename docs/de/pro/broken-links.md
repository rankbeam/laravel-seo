---
description: "Begrenzter, fortsetzbarer Crawler für nicht erreichbare Links: defekte interne Routen mit Weiterleitungsaktion und optional externe Links. Standardmäßig ausgeschaltet."
---

# Broken-Link-Crawler {#broken-link-crawler}

Ein **begrenzter, fortsetzbarer Crawler** durchläuft deine Website, prüft Links auf jeder Seite und erfasst nicht erreichbare Ziele. Dazu gehören defekte **interne** Links auf deinem Host, für die du eine Weiterleitung erstellen kannst, und optional defekte **externe** Links. Die Funktion ist **standardmäßig aus**.

Drei Eigenschaften bestimmen den Aufbau:

- **Begrenzt und fortsetzbar.** Ein Crawl besteht aus vielen kleinen Queue-Jobs mit begrenzter Seitenzahl. Sie reihen Fortsetzungen ein, bis der Lauf endet oder eine Grenze erreicht. Auch der gesamte Lauf ist begrenzt, standardmäßig auf 2000 Seiten. Nur ausdrücklich gesetztes `null` hebt diese Gesamtgrenze auf; die übrigen Batch- und Zeitgrenzen bleiben bestehen. Stimme Grenzen und Pausen auf Website und Serverkapazität ab.
- **Kontrollierter Standardumfang.** `internal_only` prüft ausschließlich Links des eigenen Hosts, ohne Anfragen an Dritte. Jeder interne oder externe Abruf durchläuft den gemeinsamen **SsrfGuard** mit erlaubten URL-Schemata, Host-Grenzen und Ablehnung privater Adressen. Externe Prüfungen sind optional und bleiben ebenfalls geschützt.
- **Getrennt von der SEO-Bewertung.** Befunde liegen in eigenen Tabellen und verändern weder `seo_scan_issues` noch die Bewertung von 0 bis 100. Defekte ausgehende Links werden als eigener Betriebsbereich verfolgt.

## Verfügbare Ansichten {#what-you-get}

Im Filament-Dashboard erscheinen bei aktivierter Funktion:

- **Broken-link summary** mit offenen internen und externen Befunden, letztem Crawl und Verweis auf die Befundtabelle.
- **Broken-link crawl** mit Live-Fortschritt: gecrawlte Seiten, geprüfte Links und gefundene defekte Ziele.
- **Broken links per scan** als Verlauf der jüngeren Crawls.
- **Eine Befundressource** für defekte Verbindungen `Quelle → Ziel`, mit Filtern und einer Weiterleitungsaktion für interne Ziele.

Ohne Oberfläche liefern die Befehle `seo-pro:broken-links-*` dieselben Daten.

## Warum die Funktion standardmäßig aus ist {#why-it-s-off-by-default}

Der Crawler **stellt Netzwerkanfragen** und benötigt Infrastruktur. Er startet deshalb nicht still bei der Paketinstallation:

- Seine zwei Haupttabellen müssen wie alle Pro-Migrationen **veröffentlicht und migriert** werden, bevor die Oberfläche sie abfragen kann. Typisierte Prüfungen verwenden zusätzlich `seo_broken_link_inspections`.
- Ein Crawl läuft über eine **eigene Queue** und benötigt einen **Worker**. Ohne Worker gibt es keinen Fortschritt.
- Die Bestätigung defekter Links erfolgt **über mehrere Crawls**. Dafür ist regelmäßige Planung vorgesehen, nicht nur ein einmaliger Lauf.

## Einrichtung {#setup}

```dotenv
SEO_PRO_BROKEN_LINKS_ENABLED=true
```

Führe danach die Migrationen aus. `seo-pro:install` veröffentlicht und startet alle Pro-Migrationen und lässt sich idempotent wiederholen:

```bash
php artisan seo-pro:install
```

Starte einen **eigenen Worker** für die Crawl-Queue `seo-broken-links`, damit lange Crawls nicht vor nutzerbezogenen Jobs liegen:

```bash
# --tries=1: a dead job is reclaimed by the next continuation, so queue-level
#   retries are unnecessary. --timeout must exceed the batch's hard time budget
#   (seo-pro.broken_links.batch.hard_time_budget_seconds, default 180) plus the
#   HTTP timeout, so a batch is never killed mid-bookkeeping.
php artisan queue:work --queue=seo-broken-links --tries=1 --timeout=240
```

Prüfe die Einrichtung. `seo:doctor` kontrolliert Schalter, Tabellen und eine tatsächliche Queue-Verbindung statt `sync`, jeweils mit Korrekturhinweisen:

```bash
php artisan seo:doctor
```

Die vollständige Queue-Aufteilung mit Redis, Supervisor, eigenen Verbindungen und Batch-Einstellungen beschreibt [Produktion einrichten](/de/pro/production).

## Einen Crawl starten {#running-a-crawl}

Verwende im Dashboard **Scan now** oder einen Befehl:

```bash
# Queue a crawl (internal links only, the default).
php artisan seo-pro:broken-links-scan

# Also check outbound/external links. Each external host must still pass the
# SsrfGuard, so widen seo-pro.http.scope (or allowed_hosts) for the fetch to be
# permitted, and raise http.per_host_delay_ms so a third-party host is never hit
# too fast.
php artisan seo-pro:broken-links-scan --scope=internal_and_external
```

Beide Befehle **reihen den Crawl nur ein**. Die Arbeit erledigt der Worker.

## Wann ein Link als defekt gilt {#how-a-link-gets-flagged}

Ein Link gilt erst dann als bestätigt defekt, wenn er in `seo-pro.broken_links.mark_broken_after_failures` **aufeinanderfolgenden Crawls** nicht erreichbar war. Jeder erfolgreiche Abruf setzt den Zähler zurück; standardmäßig sind **drei** Fehlschläge nötig. Eine einzelne vorübergehende Störung genügt daher nicht. Bei wöchentlichem Rhythmus bestätigen drei fehlgeschlagene Crawls den Link etwa zwei Wochen nach der ersten Beobachtung oder bis zu ungefähr drei Wochen nach Eintritt des Fehlers. Kürzere Abstände oder ein kleinerer Schwellenwert beschleunigen die Bestätigung.

## Typisierte Linkprüfungen {#typed-link-inspections}

Neben der Erreichbarkeit wird jeder gecrawlte Link durch **typisierte Prüfungen** ausgewertet. Diese melden etwa uneinheitliche abschließende Schrägstriche, problematische Kodierung, Weiterleitungsketten, `javascript:`-Ziele, fehlende Seitenanker und wenig beschreibende Linktexte. Jede Prüfung hat einen festen **Schweregrad** `critical`, `warning` oder `notice`, wie die [Scan-Befunde](/de/pro/scan-issues), und wird pro Crawl in `seo_broken_link_inspections` gespeichert.

Anders als ein erst nach mehreren Crawls bestätigter Broken-Link-*Befund* ist eine solche Prüfung eine Momentaufnahme des einzelnen Laufs. Sie erscheint **sofort beim ersten Crawl** und kann deshalb für CI-Prüfungen verwendet werden.

### Prüfungsreferenz {#inspection-reference}

| Prüfung | Schweregrad | Meldet | Gilt für |
|---|---|---|---|
| `broken_link` | critical | Ziel liefert HTTP ≥ 400 | Jeden Link |
| `redirect_chain` | notice · warning | Ziel nur über Weiterleitung erreichbar; warning ab Überschreitung von `redirect_chain_warning_hops` | Jeden Link |
| `link_unreachable` | notice | In diesem Crawl wegen Netzwerkfehler, Timeout oder Sperre unerreichbar; möglicherweise vorübergehend | Jeden Link |
| `insecure_link` | warning | `http://`-Link auf einer HTTPS-Website, also Wechsel zu unverschlüsseltem Transport | Jeden Link |
| `trailing_slash` | notice | Interner Pfad verletzt die erklärte Slash-Konvention; **ohne gesetztes `trailing_slash` ausgeschaltet** | Interne Links |
| `double_slash_url` | warning | `//` und damit leeres Segment im internen Pfad | Interne Links |
| `duplicate_query_param` | notice | Wiederholter Query-Schlüssel wie `?a=1&a=2`; Array-Syntax `key[]` ausgenommen | Interne Links |
| `non_ascii_url` | notice | Nicht kodierte Nicht-ASCII-Zeichen im internen Pfad | Interne Links |
| `uppercase_url` | notice | Großbuchstaben im internen Pfad; getrennt ausgelieferte Schreibvarianten prüfen | Interne Links |
| `underscore_in_url` | notice | Unterstriche im internen Pfad statt der für SEO üblichen Bindestriche | Interne Links |
| `javascript_link` | warning | `javascript:` im href statt eines gewöhnlichen crawlbaren Ziels | Jeden Ankerlink |
| `missing_fragment` | warning | Seiteneigenes `#fragment` ohne passende `id` oder `name` | Dieselbe Seite |
| `non_descriptive_anchor` | notice | Allgemeiner Linktext wie „click here“, „read more“ oder eine bloße URL | Jeden Ankerlink |
| `absolute_internal_link` | notice | Interner Link als absolute URL statt relativ zur Website-Wurzel | Interne Links |

Prüfungen von Slash-Konvention, Schreibweise, Kodierung und doppelten Schrägstrichen gelten nur für **interne** Links. Der URL-Stil externer Websites liegt nicht in deiner Zuständigkeit. Weiterleitung, defektes Ziel, Unerreichbarkeit und unsicherer Transport werden bei jedem Link geprüft. Konfigurierte Framework-Pfade und statische Assets werden über `exclude_paths` und `exclude_extensions` ausgelassen.

Abgerufen wird die **genau angegebene URL**, lediglich ohne `#fragment`. So bleibt eine serverseitige Canonical-Weiterleitung wie `/about/ → /about` als `redirect_chain` sichtbar, statt durch vorherige Normalisierung zu verschwinden. Jede unterschiedliche angegebene Form auf einer Seite wird geprüft, etwa `/page#ok` und `/page#missing` oder `/a//b` und `/a/b`.

Der zugrunde liegende bestätigte Broken-Link-*Befund* fasst Zielaliase weiterhin zu einer Identität zusammen. Prüfzeilen werden pro `(page, target, inspection)` gespeichert. Mehrere fehlerhafte Anker desselben Ziels ergeben deshalb eine `missing_fragment`-Zeile mit einem Beispiel, nicht eine Zeile pro Anker.

### Prüfregeln anpassen {#tuning-the-taxonomy}

Alle Einstellungen stehen unter `seo-pro.broken_links.inspections`:

```php
'inspections' => [
    // Master switch. false = the crawler behaves exactly as before (broken-link
    // findings only, no inspection rows, zero added work).
    'enabled' => env('SEO_PRO_BROKEN_LINKS_INSPECTIONS', true),

    // The active rule set — remove a class to silence that inspection per client.
    'rules' => [ /* the 14 rule classes, see config/seo-pro.php */ ],

    // "Pre-learn your own noise": links whose TARGET path matches one of these
    // globs are not inspected at all (framework internals, generated routes).
    'exclude_paths' => ['/livewire/*', '/filament/*', '/admin/*', /* … */],

    // Static assets are still crawled for broken-link detection, but raise no
    // trailing-slash / casing / underscore hygiene noise.
    'exclude_extensions' => ['css', 'js', 'png', 'pdf', /* … */],

    // The site-wide trailing-slash convention. null = don't enforce a style
    // (a server-side slash redirect still shows up under redirect_chain);
    // 'always' or 'never' to enforce one.
    'trailing_slash' => null,

    'redirect_chain_warning_hops' => 2,
    'non_descriptive_anchors' => ['click here', 'read more', /* … */],
    'evidence_sample' => 5, // example rows shown per inspection in a report
],
```

Entferne die Klasse einer Regel aus `rules`, um **nur diese Regel** auszuschalten. `SEO_PRO_BROKEN_LINKS_INSPECTIONS=false` deaktiviert **alle typisierten Prüfungen**. Zwei Regeln verdienen besondere Beachtung:

- `trailing_slash` ist **aus, bis du eine Konvention festlegst**, `always` oder `never`. Liefert eine Website `/x` und `/x/` beide mit 200, gibt es ohne erklärte Konvention keinen falschen Stil. Eine tatsächliche serverseitige Weiterleitung erscheint ohnehin als `redirect_chain`.
- `absolute_internal_link` meldet **jeden** internen Link mit absoluter URL. Verwendet deine Website solche URLs absichtlich, können viele harmlose Hinweise entstehen. Entferne die Regel aus `rules`, wenn du diese Meldungen nicht brauchst.

## Continuous Integration {#continuous-integration}

Linkprüfung und [SEO-Scan](/de/pro/scan-issues) können einen **Build fehlschlagen lassen** und ein **Berichtsartefakt schreiben**. `--fail-on-error` meint den Schweregrad `critical`. `--fail-on-warning` schlägt bei `critical` **oder** `warning` fehl. Einen zusätzlichen Schweregrad „error“ gibt es nicht.

```bash
# The audit: run synchronously and fail on any open critical issue, writing a
# machine-readable report. --fail-on-* require --sync — a queued scan has no
# results yet when the command returns.
php artisan seo-pro:scan --sync --fail-on-error --report=reports/audit.json

# The link scan is asynchronous, so gate it in two steps: crawl, drain the
# queue, then read the persisted results.
php artisan seo-pro:broken-links-scan
php artisan queue:work --queue=seo-broken-links --stop-when-empty
php artisan seo-pro:broken-links-status --fail-on-error --report=reports/links.md --format=md
```

`--report=<file|dir>` schreibt das Artefakt; bei einem Verzeichnis wird der Dateiname abgeleitet. `--format` akzeptiert `json` als Standard, `md` oder `html`. JSON eignet sich zur Auswertung in einer Pipeline; HTML ist eine eigenständige Seite als Laufanhang.

### GitHub Actions {#github-actions}

Der Crawler ruft Seiten über HTTP ab. In CI benötigt er deshalb erreichbare Inhalte: eine lokal gestartete App wie unten oder eine Staging-URL über `SEO_PRO_BROKEN_LINKS_BASE_URL`. Registriere Modelle beziehungsweise Sitemap-Quellen, damit Startseiten vorhanden sind.

```yaml
name: SEO gate
on: [pull_request]

jobs:
  seo:
    runs-on: ubuntu-latest
    env:
      APP_URL: http://127.0.0.1:8000
      SEO_PRO_BROKEN_LINKS_ENABLED: true
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3' }
      - run: composer install --no-interaction --prefer-dist

      - run: |
          cp .env.example .env
          php artisan key:generate
          touch database/database.sqlite
          php artisan migrate --force
          php artisan seo-pro:install   # publishes + runs the Pro migrations

      # Serve the app so the crawler can reach it.
      - run: php artisan serve --port=8000 &

      - name: SEO audit gate
        run: php artisan seo-pro:scan --sync --fail-on-warning --report=reports/audit.md --format=md

      - name: Broken-link + inspection gate
        run: |
          php artisan seo-pro:broken-links-scan
          php artisan queue:work --queue=seo-broken-links --stop-when-empty
          php artisan seo-pro:broken-links-status --fail-on-error --report=reports/links.md --format=md

      # Always upload the reports — when a gate fails the job, you still get the
      # artifact explaining why.
      - if: always()
        uses: actions/upload-artifact@v4
        with:
          name: seo-reports
          path: reports/
```

## Zeitplan {#scheduling}

Registriere Crawl und Wartungsaufgaben in `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('seo-pro:broken-links-scan')->weekly();     // re-crawl
Schedule::command('seo-pro:broken-links-recover')->hourly();  // reclaim dead-worker runs
Schedule::command('seo-pro:broken-links-prune')->daily();     // retention
```

## Befehlsreferenz {#command-reference}

| Befehl | Aufgabe |
|---|---|
| `seo-pro:broken-links-scan` | Begrenzten, fortsetzbaren Crawl einreihen; `--scope=internal_only\|internal_and_external`, zusätzliche Start-URLs mit `--url=*` |
| `seo-pro:broken-links-status` | Letzten Crawl, offene bestätigte Befunde und Prüfungszahlen dieses Laufs anzeigen; **CI-Prüfoptionen** `--fail-on-error`, `--fail-on-warning`, `--report=<file\|dir>`, `--format=json\|md\|html` |
| `seo-pro:broken-links-cancel` | Laufenden oder eingereihten Crawl abbrechen; `{run?}` wählt standardmäßig den letzten aktiven |
| `seo-pro:broken-links-recover` | Durch ausgefallene Worker verlassene Crawls mit veralteter Lease als fehlgeschlagen markieren |
| `seo-pro:broken-links-prune` | Aufbewahrungsregeln auf alte Läufe und erledigte Befunde anwenden |

## Grenzen anpassen {#tuning}

Seiten pro Lauf, Links pro Seite, Job-Grenzen, Zeitbudget und Pausen je Host stehen unter `seo-pro.broken_links`. Die Standardwerte sind begrenzt und zurückhaltend. Lies die [Tabelle zu Batch-Grenzen in der Produktionsanleitung](/de/pro/production), bevor du sie erhöhst.

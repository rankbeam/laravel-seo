---
description: "Pro im Produktionsbetrieb: eigene Queues, Scheduler, Wiederholungen, Wiederherstellung, Aufbewahrung und Telemetrie – unabhängig von Filament."
---

# Einrichtung für den Produktionsbetrieb {#production-setup}

Die täglichen Aufgaben von Pro – Website-Scans, der Crawler für defekte Links, das optionale Schreiben von Weiterleitungszählern und das Bereinigen des 404-Protokolls – laufen über Laravels Queue und Scheduler. Dieser Leitfaden beschreibt die Einrichtung für größere Websites: eigene Queues, einen Scheduler, Regeln für Wiederholungen und Wiederherstellung, Aufbewahrungsfristen und die passende Telemetrie. Grundlage ist die beschriebene Produktionsinstallation mit rund 900 Seiten und 20.000 Besuchen pro Tag; die Konfiguration lässt sich übernehmen. Diese Größenangaben beschreiben den Anwendungsfall, keine unabhängig geprüfte Leistungszusage.

Alles hier funktioniert **unabhängig von Filament**. Engine, Befehle, Queues und Telemetrie sind mit und ohne Panel identisch. Filament ergänzt Ansichten; es verändert weder die Planung noch die Verarbeitung der Aufgaben.

[[toc]]

## Sichere Reihenfolge für die Einführung {#safe-rollout-order}

Gehe in dieser Reihenfolge vor. Prüfe jeden Schritt, bevor du den nächsten beginnst:

1. **Installieren** – Konfiguration und Migrationen veröffentlichen und ausführen:

   ```bash
   php artisan seo-pro:install
   ```

   `seo-pro:install` veröffentlicht `config/seo-pro.php` und die Pro-Migrationen und führt anschließend `migrate` aus. Pro-Migrationen müssen **veröffentlicht werden**; das Paket lädt sie niemals automatisch. Dieser Schritt ergänzt also nach `composer require` das benötigte Datenbankschema. Der Installer ist idempotent und lässt sich erneut ausführen. Mit `--force` überschreibst du veröffentlichte Dateien; mit `--no-migrate` veröffentlichst du sie, ohne die Migrationen auszuführen.

2. **Scan-Ziele registrieren**, in einem Service Provider (`AppServiceProvider::boot()`):

   ```php
   use Rankbeam\Seo\Pro\Facades\SeoPro;

   SeoPro::targets()->register('posts', Post::class);
   SeoPro::targets()->registerRoutes('static', ['home', 'pricing']);
   // or: SeoPro::targets()->fromSitemaps();
   ```

3. **Einrichtung prüfen**, bevor du Hintergrundaufgaben aktivierst:

   ```bash
   php artisan seo:doctor
   ```

   Behebe jede ausgegebene Warnung. Sie enthält jeweils den passenden Befehl oder Konfigurationseintrag. Verwende in CI `--json` und werte die stabilen Prüfungs-IDs aus.

4. **Queues und Scheduler konfigurieren** (siehe unten), einen Queue-Worker bereitstellen und einen Cron-Eintrag für `schedule:run` anlegen.

5. **Optionale Funktionen zuletzt aktivieren**. Der Crawler für defekte Links, die KI-Unterstützung und Search Console sind standardmäßig deaktiviert. Für den Crawler müssen seine Tabellen migriert sein (Schritt 1 veröffentlicht die Migrationen bereits); außerdem braucht er einen eigenen Worker (siehe unten).

## Eigene Queues für jede Aufgabenart {#dedicated-queues-per-workload}

Ein langer Scan oder Crawl sollte keine nutzerbezogenen Jobs wie E-Mails oder Benachrichtigungen aufhalten. Gib jeder SEO-Aufgabenart eine eigene Queue und einen eigenen Worker.

Die Scan-Pipeline und der Crawler für defekte Links verwenden jeweils eine konfigurierbare Queue:

| Aufgabenart | Konfiguration | Umgebungsvariable | Standard-Queue |
|---|---|---|---|
| Jobs für Onpage-Scans | `seo-pro.scan.queue` | `SEO_PRO_SCAN_QUEUE` | die Standard-Queue |
| Crawl-Jobs für defekte Links | `seo-pro.broken_links.queue.name` (+ `.connection`) | `SEO_PRO_BROKEN_LINKS_QUEUE` (+ `_CONNECTION`) | `seo-broken-links` |

### Redis-Beispiel für die Produktionsumgebung {#redis-example-the-production-topology}

`.env`:

```dotenv
QUEUE_CONNECTION=redis

# Dedicated queues so SEO work never starves user-facing jobs.
SEO_PRO_SCAN_QUEUE=seo
SEO_PRO_BROKEN_LINKS_QUEUE=broken_links
SEO_PRO_BROKEN_LINKS_QUEUE_CONNECTION=redis
```

Starte pro Queue einen Worker, jeweils als eigenen Prozess beziehungsweise eigenes Supervisor-Programm:

```bash
# User-facing jobs — highest priority, most workers.
php artisan queue:work redis --queue=default --tries=3

# On-page scans — moderate; a scan target job is short.
php artisan queue:work redis --queue=seo --tries=3 --timeout=360

# Broken-link crawl — one worker is plenty; jobs are long and self-redispatch.
php artisan queue:work redis --queue=broken_links --tries=1 --timeout=240
```

Der `--timeout` des Crawl-Workers muss über `seo-pro.broken_links.batch.hard_time_budget_seconds` (Standard: 180) zuzüglich HTTP-Timeout liegen. So bleibt nach einem Abruf Zeit, den Batch-Status zu speichern. Der Job setzt seinen eigenen `$timeout` auf diese Summe; stimme die Worker-Option darauf ab. Verwende für den Crawl `--tries=1`: Ein abgebrochener Job wird von der nächsten Fortsetzung oder von `seo-pro:broken-links-recover` aufgegriffen. Wiederholungen auf Queue-Ebene sind deshalb nicht erforderlich.

`seo:doctor` zeigt die Queue jeder Aufgabenart an und warnt, wenn sie auf `sync` aufgelöst wird. Dann würde die Arbeit direkt ausgeführt und den aufrufenden Prozess blockieren.

## Scheduler {#scheduler}

Neue Anwendungen mit Laravel 11, 12 und 13 definieren ihre Zeitpläne in **`routes/console.php`**. Die Methode `schedule()` in `app/Console/Kernel.php` findet sich noch in Anwendungen, die von Laravel 10 aktualisiert wurden. Falls deine Anwendung sie weiterhin verwendet, trage dieselben Aufgaben dort ein. Ein einzelner System-Cron-Eintrag ruft den Scheduler jede Minute auf:

```bash
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Registriere anschließend die wiederkehrenden Befehle mit den empfohlenen Intervallen:

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

// --- Core ---------------------------------------------------------------
// Regenerate the XML sitemap (needs spatie/laravel-sitemap + registered sources).
Schedule::command('seo:sitemap')->dailyAt('01:30');

// --- Scan pipeline ------------------------------------------------------
// Scan cadence: weekly suits most sites; go daily when content changes fast.
// Queued — pair with the `seo` queue worker above.
Schedule::command('seo-pro:scan')->weekly();
// Fail runs abandoned by a dead worker so they never hang the pipeline.
Schedule::command('seo-pro:scan-recover')->hourly();
// Delete finished runs (and their issues) past the retention window.
Schedule::command('seo-pro:scan-prune')->daily();

// --- Redirects & 404s ---------------------------------------------------
// Only needed when seo-pro.redirects.hits.flush_immediately=false.
Schedule::command('seo-pro:redirects-flush-hits')->everyFiveMinutes();
// Keep the 404 log within its retention window and row cap.
Schedule::command('seo-pro:404-prune')->daily();
// Re-fetch open 404 paths; mark any that return 200 again as recovered.
Schedule::command('seo-pro:404-recheck')->daily();

// --- Broken-link crawler (only when enabled) ----------------------------
Schedule::command('seo-pro:broken-links-scan')->weekly();
Schedule::command('seo-pro:broken-links-recover')->hourly();
Schedule::command('seo-pro:broken-links-prune')->daily();
```

Die empfohlenen Intervalle im Überblick:

| Befehl | Intervall | Zweck |
|---|---|---|
| `seo:sitemap` | täglich | Sitemap aus den aktuellen Inhalten neu erstellen |
| `seo-pro:scan` | wöchentlich; bei häufigen Inhaltsänderungen täglich | Alle Ziele erneut prüfen |
| `seo-pro:scan-recover` | stündlich | Läufe nach einem Worker-Abbruch abschließen |
| `seo-pro:scan-prune` | täglich | Aufbewahrungsfrist für Scan-Läufe anwenden |
| `seo-pro:redirects-flush-hits` | alle 5 Minuten, nur bei `redirects.hits.flush_immediately=false` | Im Cache gesammelte Aufrufzähler in die Datenbank schreiben |
| `seo-pro:404-prune` | täglich | 404-Protokoll nach Aufbewahrungsfrist und Zeilenlimit bereinigen |
| `seo-pro:404-recheck` | täglich | Offene 404-Pfade erneut abrufen und inzwischen mit 200 antwortende Pfade als behoben markieren |
| `seo-pro:broken-links-scan` | wöchentlich | Links erneut crawlen; die Bestätigung erfolgt über mehrere Scans |
| `seo-pro:broken-links-recover` | stündlich | Crawls nach einem Worker-Abbruch abschließen |
| `seo-pro:broken-links-prune` | täglich | Aufbewahrungsfristen des Crawlers anwenden |

`seo-pro:scan` und `seo-pro:broken-links-scan` stellen die Arbeit nur **in die Queue**. Der Worker führt sie aus. Die Befehle für Wiederherstellung und Bereinigung laufen direkt und verursachen wenig Aufwand.

::: tip Bestätigung defekter Links über mehrere Scans
Ein Link wird erst als defekt markiert, wenn er in `seo-pro.broken_links.mark_broken_after_failures` **aufeinanderfolgenden Scans** nicht erreichbar war. Jeder erfolgreiche Abruf setzt den Zähler zurück. Deshalb sollte der Crawl regelmäßig laufen: Ein einmaliger vorübergehender Ausfall markiert noch keinen defekten Link. Beim Standardwert 3 bestätigen wöchentliche Scans den Fehler etwa zwei Wochen nach der ersten fehlgeschlagenen Beobachtung beziehungsweise bis zu ungefähr drei Wochen nach dem tatsächlichen Ausfall. Verkürze das Intervall oder senke den Schwellenwert, wenn du eine schnellere Bestätigung brauchst.
:::

## Batch-Einstellungen für den Crawler für defekte Links {#batch-tuning-broken-link-crawler}

Ein Crawl verteilt sich auf mehrere begrenzte Jobs, die ihre Fortsetzung selbst einreihen. Die Standardkonfiguration begrenzt den Umfang; passe sie an die Kapazität deiner Website und der geprüften Hosts an. Die Einstellungen liegen in `seo-pro.broken_links`:

| Schlüssel | Standard | Begrenzung |
|---|---|---|
| `max_pages_per_run` | `2000` | Abgerufene Seiten pro vollständigem Lauf. `null` aktiviert ausdrücklich einen unbegrenzten Lauf und ist nie der Standard |
| `max_links_per_page` | `200` | Geprüfte Links pro Seite |
| `max_total_links` | `null` | Optionales Gesamtlimit für Linkprüfungen im Lauf |
| `batch.max_pages_per_job` | `50` | Seiten pro Queue-Job |
| `batch.max_links_per_job` | `1500` | Linkprüfungen pro Queue-Job |
| `batch.hard_time_budget_seconds` | `180` | Danach beginnt der Job **keinen neuen Abruf** mehr und reiht eine Fortsetzung ein |
| `batch.dispatch_delay_seconds` | `1` | Verzögerung zwischen Fortsetzungs-Jobs |
| `http.timeout` / `http.connect_timeout` | `10` / `5` | Zeitlimits pro Anfrage |
| `http.max_response_bytes` | erbt `seo-pro.http.max_response_bytes` | Während des Streamings angewandtes Größenlimit für Antwortinhalte von Seiten- und Linkzielabrufen |
| `seed.max_response_bytes` | erbt das Crawler-/gemeinsame HTTP-Limit | Beim Einlesen der Start-URLs abgerufene Rohbytes von Sitemap-XML oder `.gz` |
| `seed.max_inflated_bytes` | erbt das Seed-/Crawler-/gemeinsame Limit | Akzeptierte entpackte Bytes einer `.gz`-Sitemap |
| `http.per_host_delay_ms` | `0` | Wartezeit zwischen Prüfungen auf einem Host; bei `internal_and_external` erhöhen |

Halte `batch.hard_time_budget_seconds` mit genügend Abstand unter dem `--timeout` des Crawl-Workers. Eine bereits laufende Anfrage lässt sich beim Erreichen des Batch-Budgets nicht sofort abbrechen; für sie gilt `http.timeout`. Plane deshalb für den Worker Batch-Budget + HTTP-Timeout + Reserve ein.

Erweitere für einen Crawl mit `internal_and_external` den Wert von `seo-pro.http.scope` oder die Liste `seo-pro.http.allowed_hosts`, damit der SsrfGuard ausgehende Prüfungen zulässt. Erhöhe außerdem `http.per_host_delay_ms`, um fremde Hosts nicht zu schnell hintereinander anzufragen. `seo:doctor` warnt, wenn der Crawl externe Ziele einschließt, der Guard aber sämtliche externen Prüfungen blockieren würde.

## Horizon / Supervisor {#horizon-supervisor}

### Supervisor {#supervisor}

Lege pro Queue ein Programm an. Beispiel für `/etc/supervisor/conf.d/app-workers.conf`:

```ini
[program:app-queue-default]
command=php /path/to/app/artisan queue:work redis --queue=default --tries=3 --max-time=3600
numprocs=4
autostart=true
autorestart=true
stopwaitsecs=3600
user=www-data

[program:app-queue-seo]
command=php /path/to/app/artisan queue:work redis --queue=seo --tries=3 --timeout=360 --max-time=3600
numprocs=2
autostart=true
autorestart=true
stopwaitsecs=400
user=www-data

[program:app-queue-broken-links]
command=php /path/to/app/artisan queue:work redis --queue=broken_links --tries=1 --timeout=240 --max-time=3600
numprocs=1
autostart=true
autorestart=true
stopwaitsecs=260
user=www-data
```

`stopwaitsecs` muss über dem `--timeout` des Workers liegen, damit ein geordneter Neustart keinen Job mitten im Batch beendet.

### Horizon {#horizon}

Wenn du Horizon verwendest, definiere in `config/horizon.php` für jede Aufgabenart einen Supervisor. Horizon übernimmt dann die Prozessverwaltung:

```php
'environments' => [
    'production' => [
        'default' => ['connection' => 'redis', 'queue' => ['default'], 'maxProcesses' => 6],
        'seo'     => ['connection' => 'redis', 'queue' => ['seo'], 'maxProcesses' => 2, 'timeout' => 360],
        'crawler' => ['connection' => 'redis', 'queue' => ['broken_links'], 'maxProcesses' => 1, 'timeout' => 240, 'tries' => 1],
    ],
],
```

## Wiederholungen und Fehlerbehandlung {#retry-failure-handling}

Der Job für ein Scan-Ziel bezieht seine Wiederholungsregeln aus der Konfiguration. Er verlässt sich **nicht** auf `--tries` des Workers:

| Schlüssel | Standard | Bedeutung |
|---|---|---|
| `seo-pro.scan.tries` | `3` | Versuche pro Ziel-Job |
| `seo-pro.scan.backoff` | `30` | Sekunden zwischen Versuchen |
| `seo-pro.scan.timeout` | `300` | Timeout pro Ziel-Job; die Überschneidungssperre läuft nach Timeout + 60 ab |

Sind die Versuche eines Ziel-Jobs ausgeschöpft, wird das Ziel als **fehlgeschlagen** gespeichert. Der Lauf endet trotzdem mit `partial` oder `failed`; behandelte Zielfehler lassen ihn nicht im Status `running` zurück. Stirbt ein Worker vor dem Speichern dieses Status, ist weiterhin die unten beschriebene Wiederherstellung nötig. Fehlgeschlagene Jobs landen in der üblichen Tabelle `failed_jobs` und lassen sich mit Laravels Standardbefehlen verwalten:

```bash
php artisan queue:failed
php artisan queue:retry all
```

Plane neben den SEO-Aufgaben auch `queue:prune-failed` ein, um die Größe dieser Tabelle zu begrenzen:

```php
Schedule::command('queue:prune-failed --hours=168')->daily();
```

Der Crawler für defekte Links verwendet `--tries=1`. Seine nächste Fortsetzung erkennt einen abgebrochenen Job am veralteten Lease-Heartbeat; alternativ greift `seo-pro:broken-links-recover` ein. Queue-Wiederholungen würden die Arbeit deshalb nur duplizieren.

## Wiederherstellung {#recovery}

Stirbt ein Worker mitten im Job, kann die Fortschrittsverwaltung den Lauf nicht selbst abschließen. Dafür gibt es zwei Bereinigungsläufe. Plane beide **stündlich** ein:

- `seo-pro:scan-recover` markiert Onpage-Scan-Läufe ohne Fortschritt seit `seo-pro.scan.recovery.stuck_scan_timeout_hours` (Standard: 2 Stunden) als fehlgeschlagen.
- `seo-pro:broken-links-recover` greift Crawl-Läufe mit veraltetem Lease-Heartbeat auf (`seo-pro.broken_links.recovery.stuck_scan_timeout_hours`, Standard: 2 Stunden). Es markiert sie als fehlgeschlagen und gibt den Platz für einen aktiven Lauf pro Scope frei.

`seo:doctor` zeigt dazu **Hinweise aus den zuletzt gespeicherten Heartbeats**. Sobald Scans verwendet werden, meldet es festgefahrene Läufe und nennt den Wiederherstellungsbefehl. Das ist kein Nachweis, dass dein Cron tatsächlich läuft: Die Prüfung wertet den gespeicherten Verlauf aus.

## Aufbewahrung {#retention}

Begrenze die Tabellengrößen. Die Standardwerte liegen alle unter `seo-pro.*`; `null` deaktiviert die jeweilige zeitbasierte Bereinigung:

| Daten | Konfiguration | Standard | Befehl |
|---|---|---|---|
| Scan-Läufe und Befunde | `scan.retention.scan_runs_days` | `90` | `seo-pro:scan-prune` |
| 404-Protokoll | `monitor_404.retention_days` (+ `max_rows` `10000`) | `90` | `seo-pro:404-prune` |
| Crawl-Läufe | `broken_links.retention.scan_runs_days` | `90` | `seo-pro:broken-links-prune` |
| Behobene Befunde | `broken_links.retention.resolved_findings_days` | `30` | `seo-pro:broken-links-prune` |

## Betriebliche Telemetrie {#operational-telemetry}

Jeder abgeschlossene Onpage-Scan und jeder abgeschlossene Crawl für defekte Links schreibt eine strukturierte Abschlusszeile über das Logging-System. So erhältst du auch ohne Panel einen Metrikverlauf. Die Nutzdaten enthalten nur Zähler und Zeitangaben: keine URLs, Antwortinhalte, Header oder Besucherdaten.

| Metrik | Scan | Crawl |
|---|:--:|:--:|
| `pages_fetched` | — | ✓ |
| `links_checked` | — | ✓ |
| `links_broken` | — | ✓ |
| `blocked_urls` (vom SSRF-Schutz abgelehnte Ziele) | — | ✓ |
| `transient_failures` (Netzwerkfehler; Prüfung im nächsten Scan) | — | ✓ |
| `total_targets` / `completed_targets` / `failed_targets` | ✓ | — |
| `issues_found` | ✓ | — |
| `duration_seconds` | ✓ | ✓ |
| `queue_lag_seconds` (Einreihen → erster Batch) | ✓ | ✓ |

Konfiguration unter `seo-pro.telemetry`:

```php
'telemetry' => [
    'enabled' => env('SEO_PRO_TELEMETRY_ENABLED', true),
    'channel' => env('SEO_PRO_TELEMETRY_CHANNEL'), // null = default log channel
    'level'   => env('SEO_PRO_TELEMETRY_LEVEL', 'info'),
],
```

Setze `channel` auf einen eigenen Log-Kanal, wenn du die Zeilen an Loki, Datadog oder CloudWatch weitergeben und von den Anwendungslogs trennen möchtest:

```php
// config/logging.php
'channels' => [
    'seo' => ['driver' => 'single', 'path' => storage_path('logs/seo.log'), 'level' => 'info'],
],
```

```dotenv
SEO_PRO_TELEMETRY_CHANNEL=seo
```

Für eine weitergehende Verarbeitung kannst du die Events direkt abonnieren. Beide stellen dieselben Nutzdaten über `metrics()` bereit:

```php
use Rankbeam\Seo\Pro\Events\SeoScanCompleted;
use Rankbeam\Seo\Pro\BrokenLinks\Events\BrokenLinkScanCompleted;

Event::listen(SeoScanCompleted::class, function (SeoScanCompleted $event) {
    Metrics::gauge('seo.scan.issues', $event->metrics()['issues_found']);
});

Event::listen(BrokenLinkScanCompleted::class, function (BrokenLinkScanCompleted $event) {
    Metrics::gauge('seo.crawl.broken', $event->metrics()['links_broken']);
});
```

Telemetrie arbeitet nach dem Best-Effort-Prinzip: Ein falsch konfigurierter Kanal lässt keinen Scan fehlschlagen.

## Deployment ohne Filament {#filament-independent-deployment}

Keine Funktion auf dieser Seite benötigt ein Panel. Engine, sämtliche Befehle, Queues, Scheduler, Wiederherstellung, Aufbewahrung und Telemetrie verhalten sich ohne Oberfläche identisch. Das Filament-Panel (`SeoProPlugin`) ergänzt ausschließlich **Ansichten**: Live-Fortschritt von Scans, Befundtabelle, Verwaltung von Weiterleitungen, 404-Monitor und Dashboard für defekte Links. Betreibe die Engine über CLI und Scheduler und ergänze das Panel später – oder gar nicht. Dafür musst du weder erneut migrieren noch die Einrichtung wiederholen. Die vollständige Befehlsreferenz findest du unter [Verwendung ohne Filament](/de/pro/headless).

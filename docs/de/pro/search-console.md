---
description: "Google Search Console mit reinem Lesezugriff: führende Suchanfragen und Seiten, Impressionen, Klicks, CTR und Position, verbunden mit bekannten Scan-Seiten. Standardmäßig aus."
---

# Search Console mit Lesezugriff {#search-console-read-only}

Ein **schreibgeschütztes** Google-Search-Console-Panel zeigt führende Suchanfragen und Seiten mit **Impressionen, Klicks, CTR und Durchschnittsposition** und verbindet sie mit den Seiten des Scanners. So kannst du offene Befunde und Suchperformance an derselben Stelle betrachten. Die Funktion ist **standardmäßig aus**.

Drei Eigenschaften bestimmen die Integration:

- **Ausschließlich Lesezugriff.** Angefordert wird nur der fest im Paket gesetzte OAuth-Scope `webmasters.readonly`. Die Integration liest Search Analytics, übermittelt keine Sitemap, beantragt keine Indexierung und verändert nichts in Search Console. Eine Konfigurationsoption zum Erweitern des Scopes gibt es nicht.
- **Deine Property und Zugangsdaten.** Anfragen gehen von deinem Server direkt an Google und verwenden dein Dienstkonto oder deine OAuth-Zugangsdaten. Rankbeam schaltet keinen Proxy dazwischen, verkauft keine Nutzung weiter und empfängt keine Telemetrie dieser Integration.
- **Fehler bleiben im jeweiligen Bereich.** Fehlende Zugangsdaten, 403, Quotenfehler oder Timeouts erscheinen als Hinweis, ohne das Panel-Rendering zu unterbrechen. Der Historien-Sync meldet Fehler und beendet den Abruf weiterer Tage, wie unten beschrieben.

## Verfügbare Ansichten {#what-you-get}

- **Seiten mit Handlungsbedarf**: Seiten mit **offenen Scan-Befunden und weiter vorhandenem Suchtraffic**, nach Impressionen priorisiert. Damit erkennst du auffällige Seiten, die bereits sichtbar sind.
- **Top pages** und **Top queries**: die gewöhnlichen Search-Analytics-Tabellen für Seiten und Suchanfragen.

Im Filament-Dashboard erscheint die Seite **Search Console** unter *SEO*, sobald die Integration aktiviert ist. Ohne Oberfläche liefern `seo-pro:search-console` und `SeoPro::searchConsole()` dieselben Metriken.

## Einrichtung {#setup}

Du benötigst Google-Zugangsdaten mit Lesezugriff auf die Search-Console-Property. Zwei Verfahren sind unterstützt. Für einen Server ist ein **Dienstkonto** meist der einfachste Weg.

### Dienstkonto, empfohlen {#service-account-recommended}

1. Aktiviere in Google Cloud die **Search Console API**, erstelle ein **Dienstkonto** und lade dessen JSON-Schlüssel herunter.
2. Füge unter Search Console → *Einstellungen → Nutzer und Berechtigungen* die E-Mail des Dienstkontos (`…@….iam.gserviceaccount.com`) als Nutzer hinzu. Die eingeschränkte Berechtigung reicht für Lesezugriff.
3. Setze Schlüssel und Property in der Paketkonfiguration:

```dotenv
SEO_PRO_GSC_ENABLED=true
SEO_PRO_GSC_CONNECTION=service_account
# The raw JSON, OR an absolute path to the .json key file:
SEO_PRO_GSC_CREDENTIALS=/etc/secrets/gsc-service-account.json
# The property exactly as it appears in Search Console:
SEO_PRO_GSC_SITE_URL=https://example.com/
# or a Domain property:  SEO_PRO_GSC_SITE_URL=sc-domain:example.com
```

Ohne `SEO_PRO_GSC_SITE_URL` wird eine URL-Präfix-Property aus `app.url` abgeleitet.

### OAuth mit Offline-Refresh-Token {#oauth-offline-refresh-token}

Konfiguriere einen vorhandenen OAuth-Client mit einem langlebigen **Refresh-Token**, möglichst ausschließlich für `webmasters.readonly` autorisiert. Jede Erneuerung fordert diesen Scope an. Das Paket akzeptiert den zurückgegebenen Token nur, wenn die Antwort ausdrücklich genau diesen Lese-Scope bestätigt. Es verlässt sich nicht darauf, dass Google eine weitergehende Berechtigung automatisch einschränkt.

```dotenv
SEO_PRO_GSC_ENABLED=true
SEO_PRO_GSC_CONNECTION=oauth
SEO_PRO_GSC_OAUTH_CLIENT_ID=xxxx.apps.googleusercontent.com
SEO_PRO_GSC_OAUTH_CLIENT_SECRET=...
SEO_PRO_GSC_OAUTH_REFRESH_TOKEN=1//...
SEO_PRO_GSC_SITE_URL=https://example.com/
```

### Token-Migration veröffentlichen {#publish-the-token-migration}

Der verschlüsselte Access-Token-Cache liegt in `seo_gsc_tokens`. Veröffentliche die Migration und führe sie einmal aus:

```bash
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate
```

Prüfe danach mit `php artisan seo:doctor`, ob Search Console aktiviert und konfiguriert ist. Dabei erfolgen weder Netzwerkaufrufe noch die Ausgabe geheimer Werte.

## Verwendung ohne Oberfläche {#headless-usage}

```bash
# Pages with open issues AND search traffic (the default view):
php artisan seo-pro:search-console

# Top pages / top queries:
php artisan seo-pro:search-console --view=pages
php artisan seo-pro:search-console --view=queries

# Window + size, and machine-readable output:
php artisan seo-pro:search-console --view=queries --days=7 --limit=25 --json
```

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$gsc = SeoPro::searchConsole();

$gsc->isConfigured();             // bool, no network
$gsc->topQueries();               // SearchConsoleResult (rows: GscRow[])
$gsc->topPages(days: 7);          // SearchConsoleResult
$gsc->pagesNeedingAttention();    // rows annotated with issueCount + score

$result = $gsc->topQueries();
if ($result->ok) {
    foreach ($result->rows as $row) {
        // $row->key, ->clicks, ->impressions, ->ctrPercent(), ->position
    }
} else {
    // $result->errorCode (a stable code), $result->errorMessage (sanitized)
}
```

## Historische Metriken {#historical-metrics}

Panel und Befehl lesen ein **laufendes Live-Zeitfenster**; Search Console selbst ist dabei der einzige Datenspeicher. Für **tageweise Historie**, die du später für zurückliegende Zeiträume abfragen kannst, verwende den Sync-Befehl. Er speichert Tagesmetriken getrennt nach Suchanfrage und Seite in `seo_gsc_metrics`:

```bash
# Publish + run the migration once (creates seo_gsc_metrics):
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate

# Backfill on the first run, then keep it current — schedule it daily:
php artisan seo-pro:gsc-sync

# Pull a specific number of days back (forces a full re-pull of that window):
php artisan seo-pro:gsc-sync --days=180
```

```php
// app/Console/Kernel.php (or bootstrap/app.php withSchedule)
$schedule->command('seo-pro:gsc-sync')->daily();
```

- **Der erste Lauf lädt die Vergangenheit nach**, entsprechend `sync.backfill_days`, standardmäßig 90 Tage. Search Console hält ungefähr 16 Monate vor; ein höherer Wert kann mehr davon abrufen. Spätere Läufe setzen ab dem letzten gespeicherten Datum fort und lesen die letzten `sync.overlap_days` erneut, um nachträgliche Aktualisierungen zu erfassen. Das Zeitfenster endet wegen der Datenverzögerung drei Tage vor heute.
- **Idempotent.** Upserts verwenden `(date, dimension, key)`, sodass Wiederholungen keine Duplikate anlegen. Scheitert ein Tag, etwa wegen der Quote, beendet der Lauf weitere Abrufe und meldet die bereits gespeicherten Zeilen. Der nächste Lauf setzt am gespeicherten Stand an.
- **Verwendung der Historie.** Sobald beide Zeiträume abgedeckt sind, berechnet der [White-Label-Bericht](/de/pro/reports) seine Search-Console-Veränderungen aus tatsächlicher Zeitraumhistorie statt aus der Differenz zweier Berichtssnapshots. Die Daten bilden auch die Grundlage weiterer Keyword-Auswertungen.

Gespeichert werden nur aggregierte Metriken: Suchanfragetext, Seiten-URL und die vier Kennzahlen Klicks, Impressionen, CTR und Position je Tag. Personen- oder anfragebezogene Einzeldaten werden weder abgerufen noch gespeichert.

## Datenverarbeitung und Sicherheit {#data-handling-security}

- **Lese-Scope wird geprüft.** Das Dienstkonto-JWT fordert nur `webmasters.readonly` an. OAuth-Erneuerungen tun dasselbe; Antworten mit fehlendem oder breiterem Scope werden abgelehnt. Verwende ausschließlich für Lesezugriff autorisierte Zugangsdaten. Das Paket enthält keinen schreibenden Search-Console-Aufruf.

- **Zugangsdaten bleiben in der Umgebung.** Dienstkontoschlüssel, OAuth-Secret und Refresh-Token werden zur Laufzeit aus den *benannten* Umgebungsvariablen gelesen. `php artisan config:cache` schreibt sie deshalb nicht in `bootstrap/cache/config.php`. Stelle sie als Prozess-Umgebungsvariablen bereit, wenn gecachte Konfiguration das Laden von `.env` verhindert.
- **Tokens werden verschlüsselt gespeichert.** Kurzlebige Access-Tokens liegen mit dem App-Schlüssel verschlüsselt in `seo_gsc_tokens` und werden bis kurz vor Ablauf wiederverwendet. Der Token-Austausch erfolgt damit nicht bei jedem Aufruf. Die langlebigen Zugangsdaten stehen nur in deiner Umgebung, nicht in der Datenbank.
- **Jede Anfrage durchläuft den SSRF-Schutz.** Token-Austausch und Search-Analytics-Aufruf verwenden den gemeinsamen `SsrfGuard`: nur HTTPS, öffentlich auflösbare Host-Adressen und keine Weiterleitungen. Die Anfrage kann nicht per Redirect an einen internen Dienst weitergereicht werden.
- **Geheime Werte werden nicht protokolliert.** Access-Tokens, Schlüssel und Authentifizierungsheader fehlen in den Logs. Bei API-Fehlern erscheint nur Googles bereinigte, längenbegrenzte Fehlermeldung.
- **Metriken werden lokal gecacht**, für `seo-pro.search_console.cache_ttl` Sekunden, standardmäßig 30 Minuten. Panel und Live-Befehl speichern außer diesem Cache und dem verschlüsselten Access-Token nichts dauerhaft. Erst der optionale Befehl `seo-pro:gsc-sync` schreibt aggregierte Tageswerte in `seo_gsc_metrics`, ohne personenbezogene Einzeldaten.

## Konfigurationsreferenz {#configuration-reference}

Alle Schlüssel stehen in `config/seo-pro.php` unter `search_console`:

| Schlüssel | Standard | Zweck |
|---|---|---|
| `enabled` | `false` | Hauptschalter `SEO_PRO_GSC_ENABLED` |
| `connection` | `service_account` | `service_account` oder `oauth` |
| `site_url` | Aus `app.url` abgeleitet | Property als `https://example.com/` oder `sc-domain:example.com` |
| `service_account.credentials_env` | `SEO_PRO_GSC_CREDENTIALS` | **Name** der Variable mit Schlüssel-JSON oder Dateipfad |
| `oauth.client_id` | — | OAuth-Client-ID, nicht geheim |
| `oauth.client_secret_env` | `SEO_PRO_GSC_OAUTH_CLIENT_SECRET` | **Name** der Variable mit Client-Secret |
| `oauth.refresh_token_env` | `SEO_PRO_GSC_OAUTH_REFRESH_TOKEN` | **Name** der Variable mit Refresh-Token |
| `default_days` | `28` | Berichtszeitraum, endet wegen GSC-Verzögerung drei Tage zurück |
| `row_limit` | `100` | Top-N-Zeilen je Bericht; API-Maximum 25000 |
| `cache_ttl` | `1800` | Cache-Dauer eines abgerufenen Berichts in Sekunden |
| `sync.backfill_days` | `90` | Beim ersten `gsc-sync` nachgeladene Tage bei leerer Tabelle |
| `sync.overlap_days` | `2` | Erneut gelesene letzte Tage für nachträgliche Aktualisierungen |
| `sync.row_limit` | `5000` | Maximal angeforderte Zeilen pro Tag und Dimension beim Sync |

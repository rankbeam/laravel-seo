---
description: "Vollständige SEO-Scans planen und Änderungen seit dem letzten Lauf sehen: neue, zurückgekehrte und behobene Befunde, priorisiert im Dashboard und optional per E-Mail."
---

# Scan-Zeitplan und Änderungen {#scan-scheduling-delta}

Führe vollständige SEO-Scans **nach Zeitplan** aus und sieh **die Änderungen seit dem letzten Scan**: neu aufgetretene, zurückgekehrte oder behobene Befunde, nach Auswirkung geordnet im Dashboard und optional in einer Zusammenfassungs-E-Mail.

Zeitplan und Änderungsvergleich gehören zusammen. Die Differenz macht sichtbar, was seit dem letzten geplanten Lauf Aufmerksamkeit benötigt.

## Änderungen seit dem letzten Scan {#what-changed-since-the-last-scan}

Jeder abgeschlossene Scan hält seine **Menge offener Befunde** in einem kleinen Snapshot fest, `seo_scan_run_issues`. Der Vergleich zweier Snapshots ergibt drei Gruppen:

- **New**: jetzt offen, zuvor weder im Vergleichslauf noch in einem früheren Scan offen gewesen; ein erstmals erfasster Befund.
- **Regressed**: bereits behoben gewesen und **zurückgekehrt**. Gemeint ist keine höhere Schwere, denn diese ist je Problemtyp festgelegt, sondern das Wiederöffnen aus dem [Befund-Lebenszyklus](/de/pro/scan-issues#issue-lifecycle).
- **Fixed**: im früheren Scan offen, im aktuellen nicht mehr vorhanden.

Jede Gruppe ist [nach Auswirkung sortiert](#impact-ordering).

### Warum Snapshots statt der laufenden Befundtabelle? {#why-a-snapshot-not-the-issues-table}

Der [Lebenszyklus](/de/pro/scan-issues#issue-lifecycle) eines Befunds wechselt zwischen offen, behoben und wieder geöffnet. Über mehrere Scans wird dieselbe Zeile aktualisiert. Solange der Befund offen ist, erhält `scan_run_id` jeweils den neuesten Lauf. Das erhält eine dauerhafte Befundhistorie, beantwortet aber nicht, **welche Befunde am Ende von Lauf N offen waren**: Ein dauerhaft offener Befund verweist nur noch auf den letzten Lauf.

Deshalb speichert jeder Lauf seine offene Menge anhand eines stabilen **Befund-Fingerprints**, `issue_type | target | field`. Dieselbe Identität verwendet der [White-Label-Bericht](/de/pro/reports). Die Differenz wird aus zwei unveränderlichen Mengen berechnet und funktioniert für **beliebige zwei** Läufe, nicht nur direkt aufeinanderfolgende.

### Sonderfälle {#edge-cases-handled-honestly}

- **Eine Seite entfällt aus den Scan-Zielen.** Ihre offenen Befunde werden nicht erneut geprüft, bleiben offen und fließen weiter in jeden Snapshot ein. Sie erscheinen als **weiterhin offen**, nicht fälschlich als behoben.
- **Eine Prüfung wird zwischen Scans ausgeschaltet.** Ihre Befunde werden nicht mehr ausgegeben, im Lebenszyklus als behoben markiert und aus der offenen Menge entfernt. Im Vergleich stehen sie deshalb unter **Fixed**. Das beschreibt den aktuellen Scannerzustand; auf Befundebene lässt sich eine tatsächliche Korrektur nicht vom Abschalten der Prüfung unterscheiden.
- **Der erste Scan nach einem Upgrade.** Ältere Läufe ohne Snapshot dienen nicht als Vergleichsbasis. Der erste Lauf mit Snapshot bildet einen **Ausgangsstand** ohne Differenz, statt die gesamte Website als neu auffällig zu melden. Ab dem zweiten Snapshot steht der Vergleich bereit.

### Im Dashboard {#on-the-dashboard}

Das Widget **„What changed since the last scan“** im [SEO-Dashboard](/de/pro/installation) zeigt die Anzahl neuer, zurückgekehrter und behobener Befunde sowie die wichtigsten Einträge jeder Gruppe. Verglichen werden die zwei letzten abgeschlossenen Scans, nach Auswirkung sortiert. Solange keine zwei Snapshots vorhanden sind, erscheint ein kurzer Hinweis auf den Ausgangsstand.

## Sortierung nach Auswirkung {#impact-ordering}

Jede Gruppe wird anhand eines **Impact-Werts** geordnet:

```
impact = severity_weight × page_importance
```

- **severity_weight** verwendet die veröffentlichte [Bewertungsregel](/de/pro/scoring): `40` für critical, `15` für warning und `5` für notice. Diese Schweregrade sind die Gewichtung des Produkts, keine unabhängige Messung eines Ranking-Effekts.
- **page_importance** verwendet beobachtete **Suchnachfrage**, also Impressionen der Seite in [Search Console](/de/pro/search-console), um zwischen Seiten zu unterscheiden:

  ```
  page_importance = 1 + demand_weight·demand + priority_weight·priority
  demand   = log1p(page impressions) / log1p(busiest page's impressions)   ∈ [0,1]
  priority = the page's configured per-class sitemap priority              ∈ [0,1]
  ```

  Impressionen werden logarithmisch skaliert und auf die sichtbarste Seite normalisiert. Zehnmal mehr Traffic bedeutet also nicht zehnmal mehr Gewicht. Damit arbeitet dieselbe Formel auf einem kleinen Blog und einem großen Katalog. Sitemap-`<priority>` ist nur ein **schwaches ergänzendes Signal**. Standardmäßig fehlt es; ein einheitlicher Wert unterscheidet Seiten nicht. Erst eigene Prioritäten je Modelltyp unter `seo.sitemap.models` beeinflussen die Sortierung.

**Ohne Search Console** und ohne konfigurierte Prioritäten ist `page_importance` für jede Seite `1`. Die Sortierung folgt dann ausschließlich dem **Schweregrad**, ohne erfundene Nachfragewerte. Synchronisiere die [GSC-Historie](/de/pro/search-console#historical-metrics) mit `seo-pro:gsc-sync`, um Impressionen einzubeziehen.

Gewichte und Zeitraum stehen unter `seo-pro.scan.delta.impact`.

## Einen Scan planen {#scheduling-a-scan}

Das Paket plant **standardmäßig keine Aufgaben**. So aktivierst du den Zeitplan:

```php
// config/seo-pro.php
'schedule' => [
    'enabled' => true,          // env SEO_PRO_SCHEDULE_ENABLED
    'frequency' => 'weekly',    // daily | weekly | monthly | hourly
    'time' => '03:00',          // for daily/weekly/monthly
    'timezone' => null,         // null = app timezone
    // ...
],
```

Für vollständige Kontrolle kannst du einen Cron-Ausdruck setzen. Dieser hat Vorrang vor `frequency`:

```php
'cron' => '0 3 * * 1',   // env SEO_PRO_SCHEDULE_CRON
```

Das Paket registriert `seo-pro:scan` in Laravels Scheduler mit `withoutOverlapping`. Die Sperre verhindert überlappende Ausführungen des geplanten **Befehls**, umfasst aber nicht die gesamte Lebensdauer eingereihter Jobs. Die Registrierung läuft nur im Scheduler-/Konsolenkontext und fügt Webanfragen keinen zusätzlichen Registrierungsschritt hinzu.

::: warning Der Scheduler muss laufen
Ohne laufenden Laravel-Scheduler bleibt die Paketplanung wirkungslos. Verwende den üblichen Cronjob `* * * * * php artisan schedule:run` oder in der Entwicklung `php artisan schedule:work`. Siehe [Produktion und Scheduler](/de/pro/production#scheduler).
:::

Möchtest du die Planung selbst übernehmen, lass `schedule.enabled` ausgeschaltet und plane den Befehl im eigenen Konsolen-Kernel. Änderungsvergleich und Zusammenfassung funktionieren weiterhin:

```php
$schedule->command('seo-pro:scan --notify')->weekly();
```

`--sync` führt den Scan direkt aus, statt je Ziel einen Job einzureihen. Das eignet sich für kleine Websites ohne Queue-Worker; für den regulären Produktionsbetrieb verwende die Queue.

## Zusammenfassung per E-Mail {#summary-e-mail}

Optional erhältst du nach einem geplanten Scan eine **Zusammenfassung der Änderungen seit dem letzten Lauf**: eine HTML-E-Mail mit Branding und nach Auswirkung sortierten neuen, zurückgekehrten und behobenen Befunden.

```php
'schedule' => [
    // ...
    'notify' => [
        'enabled' => true,                       // env SEO_PRO_SCHEDULE_NOTIFY
        'recipients' => ['seo@agency.test'],     // falls back to reports.recipients
        'subject' => 'SEO scan summary',
        'only_on_change' => true,                // skip when nothing changed
    ],
],
```

Die E-Mail übernimmt Branding und Mail-Einstellungen des [White-Label-Berichts](/de/pro/reports), einschließlich Agenturname, Logo und Akzentfarbe. Ohne eigene Empfängerliste gelten die Berichtsempfänger. `only_on_change` überspringt die E-Mail, wenn sich nichts geändert hat; der erste Ausgangsstand wird immer gesendet.

Eine Zusammenfassung wird nur für einen mit `--notify` gestarteten Lauf gesendet. Der Scheduler fügt diese Option bei `notify.enabled` automatisch hinzu. Ein manuelles `seo-pro:scan` **ohne `--notify`** sendet keine E-Mail.

::: tip Einen anderen Kanal verwenden
Für Slack, einen Webhook oder eine eigene Zusammenfassung kannst du `Rankbeam\Seo\Pro\Events\SeoScanCompleted` abonnieren. Das Event wird einmal pro abgeschlossenem Lauf ausgelöst und enthält den Lauf. Berechne die Differenz mit `Rankbeam\Seo\Pro\Scanning\Delta\ScanRunDelta` und leite sie an den gewünschten Kanal weiter.
:::

## Aufbewahrung {#retention}

Snapshots werden zusammen mit ihrem Lauf kaskadierend gelöscht. [`seo-pro:scan-prune`](/de/pro/production#scheduler) entfernt sie deshalb im bestehenden Bereinigungsablauf; eine zusätzliche geplante Aufgabe ist nicht nötig. Ein Lauf wird erst entfernt, wenn er keine offenen Befunde mehr trägt. Snapshots jüngerer Läufe bleiben für Vergleiche verfügbar.

Mit `seo-pro.scan.delta.snapshot => false` kannst du Snapshots vollständig abschalten; dann entfallen Änderungsvergleich und Zusammenfassung.

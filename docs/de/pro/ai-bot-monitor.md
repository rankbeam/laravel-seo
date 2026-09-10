---
description: "Beobachtete Anfragen von KI-Crawlern anhand des User-Agents erfassen: Häufigkeit, letzter Pfad und HTTP-Status je Bot. Ergänzt die KI-Crawler-Regeln um Betriebsdaten."
---

# KI-Bot-Monitor {#ai-bot-monitor}

Die Zuordnung erfolgt durch **Abgleich des User-Agents**, nicht durch verifizierte Bot-Identität. Der Monitor protokolliert beobachtete Anfragen; ein User-Agent lässt sich fälschen.

Die [KI-Crawler-Steuerung](/de/guide/ai-crawlers) im Core legt fest, was `robots.txt` den Crawlern *mitteilt*. Der **KI-Bot-Monitor** in Pro ergänzt dazu die Beobachtung: Welche passenden User-Agents haben deine Website abgerufen, wie häufig und mit welchem letzten Pfad und HTTP-Status?

Er verwendet dieselben Grundbausteine wie der 404-Monitor: eine terminierbare globale Middleware, ein Modell mit Upsert und Trefferzähler sowie dieselben Datenschutzvorgaben. Der Schlüssel ist hier jedoch der **Bot** statt des Pfads; erfasst wird **jeder** Antwortstatus. Es geht um genau jene KI-Crawler, die der 404-Monitor ausdrücklich ausschließt. Die Erkennung nutzt `AiCrawlerRegistry` aus dem Core, sodass Robots-Regeln und beobachteter Traffic denselben Katalog verwenden.

::: tip Benötigt Core ≥ 3.3
Die Erkennung verwendet den KI-Crawler-Katalog im Core, [`SEO::aiCrawlers()`](/de/guide/ai-crawlers). Mit älteren Core-Versionen bleibt der Monitor inaktiv.
:::

## Aktivieren {#enabling-it}

Standardmäßig ausgeschaltet. Nach Aktivierung erfasst die globale Middleware passende Crawler nach jeder Antwort; die Protokollierung erfolgt nach der Seitenausgabe:

```php
// config/seo-pro.php
'ai_bots' => [
    'enabled' => true,
],
```

Die Middleware wird automatisch registriert. Das lässt sich mit `ai_bots.auto_register_middleware` abschalten. Je bekanntem Bot wird ein Datensatz angelegt oder aktualisiert; die Tabelle bleibt damit durch den Katalog begrenzt.

## Protokoll lesen {#reading-the-log}

### Ohne Oberfläche {#headless}

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::aiBots()->hits();                    // every bot seen, most-hit first
SeoPro::aiBots()->forPurpose('ai_training'); // just the trainers
SeoPro::aiBots()->totalHits();               // total recorded fetches
SeoPro::aiBots()->isEnabled();
```

Jeder Datensatz enthält `bot`, `label`, `operator`, `purpose`, `hit_count`, `last_path`, `last_status`, `first_seen_at` und `last_seen_at`.

### Artisan {#artisan}

```bash
php artisan seo-pro:ai-bots                       # most-hit first
php artisan seo-pro:ai-bots --purpose=ai_training # filter by purpose
php artisan seo-pro:ai-bots-prune                 # drop stale bots + old daily buckets
```

### Filament {#filament}

Mit registriertem Pro-Plugin erscheint unter der SEO-Navigationsgruppe die Tabelle **AI Bots**. Sie zeigt Bot, Betreiber, Zweck, Treffer, letzten Status, letzten Pfad und letzten Zeitpunkt. Die Tabelle ist schreibgeschützt und nach Zweck filterbar.

## Datenschutz {#privacy}

Wie beim 404-Monitor wird **standardmäßig keine IP-Adresse gespeichert**. Die optionale Einstellung `ai_bots.hash_ip` speichert ausschließlich einen schlüsselbasierten SHA-256-Wert in `ip_hash`, niemals die rohe IP-Adresse.

```php
'ai_bots' => [
    'enabled' => true,
    'hash_ip' => false,            // true → keyed sha256 only
    'exclude_paths' => ['/filament/*', '/livewire/*', /* … */],
    'max_path_length' => 500,
    'retention_days' => 90,        // lifetime rows; seo-pro:ai-bots-prune; null disables

    // Day-granular per-path buckets (below)
    'daily_enabled' => true,       // false → keep only the lifetime leaderboard
    'daily_max_paths' => 500,      // distinct paths tracked per bot per day
    'daily_retention_days' => 90,  // prune buckets older than this; null disables
],
```

## Zeitraum-Metriken in Tagesgruppen {#period-metrics-daily-buckets}

Die Gesamttabelle enthält einen Datensatz pro Bot. Sie eignet sich für eine Rangliste, beantwortet aber nicht, **wie viele Treffer oder unterschiedliche URLs** ein Bot in einem **bestimmten Zeitraum** hatte. Mit standardmäßig aktiviertem `daily_enabled` wird jeder Treffer zusätzlich pro Tag und Pfad in `seo_ai_bot_daily` erfasst. Der [White-Label-Bericht](/de/pro/reports) kann dadurch Treffer seit dem letzten Bericht und unterschiedliche URLs im Zeitraum aus den Tagesdaten berechnen, statt nur Gesamtstände zu vergleichen.

Die Anzahl der gespeicherten Zeilen bleibt begrenzt:

- Eine **Höchstzahl unterschiedlicher Pfade je Bot und Tag**, `daily_max_paths`. Weitere neue Pfade fließen in eine gemeinsame Überlaufgruppe. Die Trefferzahl des Tages bleibt exakt; bei erreichter Grenze wird die Anzahl unterschiedlicher URLs als „N+“ angezeigt.
- Eine **Aufbewahrungsfrist**, `daily_retention_days`, die `seo-pro:ai-bots-prune` durchsetzt.

Mit `daily_enabled = false` bleibt nur die Gesamtrangliste. Für „seit dem letzten Bericht“ verwendet der Bericht dann die Differenz zum vorherigen gespeicherten Berichtsstand. Vorhandene Tagesgruppen werden ignoriert, damit keine veralteten Daten einfließen.

Die Zeitraumwerte haben **Tagesauflösung**. „Seit dem letzten Bericht“ zählt ganze Tage ab dessen Datum, nicht ab der exakten Erzeugungszeit. Treffer desselben Tages können daher vor oder nach diesem Zeitpunkt liegen. Berücksichtige diese Grenze beim Vergleich genauer Zeitfenster; bei gewöhnlichen täglichen, wöchentlichen oder monatlichen Berichten ist sie meist klein.

## Aus Beobachtung Regeln ableiten {#turning-observation-into-control}

Der Monitor zeigt, welche Crawler-User-Agents auftreten. Die [KI-Crawler-Steuerung](/de/guide/ai-crawlers) im Core legt die gewünschten Zugriffsregeln fest. Möchtest du beispielsweise einen beobachteten Trainingscrawler ausschließen:

```php
// config/seo.php
'ai_crawlers' => [
    'overrides' => ['bytespider' => 'disallow'],
],
```

```bash
php artisan seo:robots-txt
```

Einige Bots halten sich laut ihrer Dokumentation nicht an `robots.txt`. Der Monitor hilft, entsprechende Anfragen zu erkennen. Eine tatsächliche Sperre benötigt gegebenenfalls Regeln am Netzwerkrand, etwa Firewall, WAF oder Cloudflare; der User-Agent allein beweist die Identität nicht.
